<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/attendance_checkout.php';
require_once __DIR__ . '/../helpers/daily_attendance_notify.php';
header("Content-Type: application/json");

// =============================================================
// 1. ONLY POST REQUESTS
// =============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

// =============================================================
// 2. ALWAYS use POST admin_id (NO SESSIONS for Android compatibility)
// =============================================================
$admin_id = intval($_POST['admin_id'] ?? 0);

if ($admin_id <= 0) {
    echo json_encode(["status" => "error", "message" => "admin_id missing"]);
    exit;
}

// =============================================================
// 3. CLEAN INPUTS
// =============================================================
$action   = $_POST['action'] ?? '';
$lat      = floatval($_POST['lat'] ?? 0);
$lng      = floatval($_POST['lng'] ?? 0);
$is_mock  = intval($_POST['mock'] ?? 0);
$location = trim($_POST['location'] ?? 'Unknown');
$timezone = $_POST['timezone'] ?? 'UTC';

date_default_timezone_set(
    in_array($timezone, timezone_identifiers_list()) ? $timezone : 'UTC'
);

$now       = date("Y-m-d H:i:s");
$today     = date("Y-m-d");
$dayOfWeek = date("w");
$isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);

// =============================================================
// 4. BLOCK FAKE GPS
// =============================================================
if ($is_mock == 1) {
    echo json_encode(["status" => "error", "message" => "Fake GPS detected"]);
    exit;
}

// =============================================================
// 5. LOAD OFFICE COORDINATES
// =============================================================
$q = $conn->prepare("
    SELECT o.latitude, o.longitude, o.radius_meters
    FROM admins a
    LEFT JOIN offices o ON a.office_id = o.id
    WHERE a.id = ?
");
$q->bind_param("i", $admin_id);
$q->execute();
$q->bind_result($officeLat, $officeLng, $officeRadius);
$q->fetch();
$q->close();

if (!$officeLat || !$officeLng || !$officeRadius) {
    echo json_encode(["status" => "error", "message" => "Office not configured"]);
    exit;
}

// =============================================================
// 6. CALCULATE DISTANCE
// =============================================================
function geoDistance($lat1, $lon1, $lat2, $lon2) {
    $earth = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2)**2 +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2)**2;

    return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
}

$distance = geoDistance($lat, $lng, $officeLat, $officeLng);

// =============================================================
// 7. GEO-FENCE CHECK
// =============================================================
if ($distance > $officeRadius) {
    echo json_encode([
        "status" => "error",
        "message" => "Outside office radius",
        "distance" => round($distance),
        "radius" => $officeRadius
    ]);
    exit;
}

// =============================================================
// 8. CHECK-IN LOGIC
// =============================================================
if ($action === "checkin") {

    // prevent double check-in
    $chk = $conn->prepare("SELECT id FROM attendance WHERE admin_id = ? AND date = ?");
    $chk->bind_param("is", $admin_id, $today);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Already checked in"]);
        exit;
    }

    // insert check-in
    $ins = $conn->prepare("
        INSERT INTO attendance 
        (admin_id, date, check_in_time, check_in_location, check_in_lat, check_in_lng)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $ins->bind_param("isssdd", $admin_id, $today, $now, $location, $lat, $lng);
    $ins->execute();

    echo json_encode([
        "status" => "success",
        "message" => $isWeekend ? "Weekend check-in (no salary)." : "Check-in successful",
        "time" => $now
    ]);
    exit;
}

// =============================================================
// 9. CHECK-OUT LOGIC
// =============================================================
if ($action === "checkout") {

    // get check-in
    $get = $conn->prepare("SELECT id, check_in_time FROM attendance WHERE admin_id = ? AND date = ?");
    $get->bind_param("is", $admin_id, $today);
    $get->execute();
    $get->bind_result($attendance_id, $check_in_time);
    $get->fetch();
    $get->close();

    if (!$check_in_time) {
        echo json_encode(["status" => "error", "message" => "No check-in found"]);
        exit;
    }

    $checkoutCheck = pcvc_validate_attendance_checkout(
        $conn,
        $admin_id,
        $check_in_time,
        $now,
        $today
    );

    if (!$checkoutCheck['ok']) {
        echo json_encode([
            "status"  => "error",
            "message" => $checkoutCheck['message'],
        ]);
        exit;
    }

    $checkout = pcvc_attendance_save_checkout(
        $conn,
        $admin_id,
        (int) $attendance_id,
        $check_in_time,
        $now,
        $today,
        $location,
        $lat,
        $lng,
        $checkoutCheck['elapsed_minutes'],
        (bool) ($checkoutCheck['salary_eligible'] ?? true)
    );

    if ($checkout === null) {
        echo json_encode(['status' => 'error', 'message' => 'Could not save checkout. Please try again.']);
        exit;
    }

    $notify = pcvc_attendance_notify_after_checkout($conn, $admin_id, $today);

    $message = $checkout['is_weekend']
        ? 'Weekend checkout recorded.'
        : 'Checkout successful.';

    $message .= "\nTime worked: " . $checkout['work_label']
        . "\nDaily salary: " . $checkout['salary_label'];

    if ($notify['email']) {
        $message .= "\nSummary sent to your email.";
    }

    echo json_encode([
        'status'           => 'success',
        'message'          => $message,
        'worked_minutes'   => $checkout['worked_minutes'],
        'daily_salary_rwf' => $checkout['daily_salary_rwf'],
        'salary'           => $checkout['daily_salary_rwf'],
        'work_label'       => $checkout['work_label'],
        'salary_label'     => $checkout['salary_label'],
        'is_weekend'       => $checkout['is_weekend'],
        'notify'           => [
            'email'    => $notify['email'],
            'whatsapp' => $notify['whatsapp'],
            'wa_error' => $notify['wa_error'],
        ],
    ]);
    exit;
}

// =============================================================
// 10. INVALID REQUEST
// =============================================================
echo json_encode(["status" => "error", "message" => "Invalid action"]);
exit;

?>
