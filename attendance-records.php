<?php
// =====================================================
// ATTENDANCE RECORDS API (ANDROID + WEB) — FINAL
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/attendance_checkout.php';
require_once __DIR__ . '/helpers/daily_attendance_notify.php';
header("Content-Type: application/json");

// =====================================================
// LOGGER (SILENT, SERVER-SIDE)
// =====================================================
function log_event(string $message, array $data = []): void {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $line = sprintf(
        "[%s] %s | %s\n",
        date('Y-m-d H:i:s'),
        $message,
        json_encode($data, JSON_UNESCAPED_SLASHES)
    );

    file_put_contents($dir . '/attendance-records.log', $line, FILE_APPEND);
}

// =====================================================
// GET — checkout eligibility (web UI)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $admin_id = $_SESSION['admin_id'] ?? $_SESSION['id'] ?? null;
    if (!$admin_id) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Not authenticated"]);
        exit;
    }

    $timezone = $_GET['timezone'] ?? 'UTC';
    if (in_array($timezone, timezone_identifiers_list(), true)) {
        date_default_timezone_set($timezone);
    }

    $today = date('Y-m-d');
    $now   = date('Y-m-d H:i:s');
    $status = pcvc_attendance_checkout_status($conn, (int) $admin_id, $today, $now);

    echo json_encode([
        "success" => true,
        "status"  => $status,
    ]);
    exit;
}

// =====================================================
// ONLY POST BEYOND THIS POINT
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// =====================================================
// AUTHENTICATION (SESSION IS AUTHORITATIVE)
// =====================================================
$admin_id = $_SESSION['admin_id']
         ?? $_SESSION['id']
         ?? null;

if (!$admin_id) {
    log_event("AUTH_FAILED");
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Not authenticated"
    ]);
    exit;
}

$admin_id = (int)$admin_id;
log_event("AUTH_OK", ["admin_id" => $admin_id]);

// =====================================================
// INPUTS (MATCH ANDROID ApiService)
// =====================================================
$action   = strtolower(trim($_POST['action'] ?? ''));
$lat      = (float)($_POST['lat'] ?? 0);
$lng      = (float)($_POST['lng'] ?? 0);
$location = trim($_POST['location'] ?? 'Unknown');
$timezone = $_POST['timezone'] ?? 'UTC';
$isMock   = (int)($_POST['mock'] ?? 0);

// =====================================================
// TIMEZONE
// =====================================================
if (in_array($timezone, timezone_identifiers_list(), true)) {
    date_default_timezone_set($timezone);
}

$now   = date('Y-m-d H:i:s');
$today = date('Y-m-d');

log_event("INPUT_RECEIVED", compact(
    'action','lat','lng','location','timezone','isMock'
));

// =====================================================
// VALIDATION
// =====================================================
if (!in_array($action, ['checkin', 'checkout'], true)) {
    log_event("INVALID_ACTION", ["action" => $action]);
    echo json_encode(["success" => false, "message" => "Invalid action"]);
    exit;
}

if ($lat == 0.0 || $lng == 0.0) {
    log_event("INVALID_GPS");
    echo json_encode(["success" => false, "message" => "Invalid GPS coordinates"]);
    exit;
}

if ($isMock === 1) {
    log_event("MOCK_GPS_DETECTED");
    echo json_encode(["success" => false, "message" => "Fake GPS detected"]);
    exit;
}

// =====================================================
// LOAD OFFICE (ANDROID MapsActivity LOGIC)
// =====================================================
$officeQ = $conn->prepare("
    SELECT o.latitude, o.longitude, o.radius_meters, o.office_name
    FROM admins a
    JOIN offices o ON a.office_id = o.id
    WHERE a.id = ?
");

$officeQ->bind_param("i", $admin_id);
$officeQ->execute();
$officeQ->bind_result($officeLat, $officeLng, $officeRadius, $officeName);
$officeQ->fetch();
$officeQ->close();

if (!$officeLat || !$officeLng || !$officeRadius) {
    log_event("OFFICE_NOT_CONFIGURED");
    echo json_encode(["success" => false, "message" => "Office not configured"]);
    exit;
}

// =====================================================
// DISTANCE — HAVERSINE (SAME AS ANDROID)
// =====================================================
function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) ** 2 +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) ** 2;

    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

$distance = haversine($lat, $lng, $officeLat, $officeLng);

log_event("DISTANCE_CHECK", [
    "distance" => round($distance),
    "radius"   => $officeRadius
]);

if ($distance > $officeRadius) {
    echo json_encode([
        "success"  => false,
        "message"  => "Outside office radius",
        "distance" => round($distance),
        "allowed"  => $officeRadius
    ]);
    exit;
}

// =====================================================
// CHECK-IN
// =====================================================
if ($action === 'checkin') {

    $check = $conn->prepare("
        SELECT id FROM attendance
        WHERE admin_id = ? AND date = ?
    ");
    $check->bind_param("is", $admin_id, $today);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        log_event("CHECKIN_DUPLICATE");
        echo json_encode(["success" => false, "message" => "Already checked in"]);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO attendance
        (admin_id, date, check_in_time, check_in_location, check_in_lat, check_in_lng)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "isssdd",
        $admin_id, $today, $now, $location, $lat, $lng
    );
    $stmt->execute();

    log_event("CHECKIN_SUCCESS", ["time" => $now]);

    echo json_encode([
        "success" => true,
        "message" => "Check-in successful",
        "time"    => $now,
        "office"  => $officeName
    ]);
    exit;
}

// =====================================================
// CHECK-OUT
// =====================================================
if ($action === 'checkout') {

    $get = $conn->prepare("
        SELECT id, check_in_time
        FROM attendance
        WHERE admin_id = ? AND date = ?
    ");
    $get->bind_param("is", $admin_id, $today);
    $get->execute();
    $get->bind_result($attendance_id, $check_in_time);
    $get->fetch();
    $get->close();

    if (!$check_in_time) {
        log_event("CHECKOUT_WITHOUT_CHECKIN");
        echo json_encode(["success" => false, "message" => "You must check in first"]);
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
        log_event("CHECKOUT_BLOCKED", [
            'reason'          => $checkoutCheck['message'],
            'jobs_completed'  => $checkoutCheck['jobs_completed'],
            'elapsed_minutes' => $checkoutCheck['elapsed_minutes'],
        ]);
        echo json_encode([
            "success" => false,
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
        log_event('CHECKOUT_SAVE_FAILED');
        echo json_encode(['success' => false, 'message' => 'Could not save checkout. Please try again.']);
        exit;
    }

    $notify = pcvc_attendance_notify_after_checkout($conn, $admin_id, $today);

    log_event('CHECKOUT_SUCCESS', [
        'minutes'  => $checkout['worked_minutes'],
        'salary'   => $checkout['daily_salary_rwf'],
        'notify'   => ['email' => $notify['email'], 'whatsapp' => $notify['whatsapp']],
    ]);

    $message = 'Checked out successfully.'
        . "\n\nTime worked: " . $checkout['work_label']
        . "\nDaily salary: " . $checkout['salary_label'];

    $sentVia = [];
    if ($notify['email']) {
        $message .= "\n\nSummary sent to your email.";
    }

    echo json_encode([
        'success'          => true,
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
