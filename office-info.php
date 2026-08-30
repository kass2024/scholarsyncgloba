<?php
// =====================================================
// OFFICE INFO API (ANDROID + WEB)
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';
header("Content-Type: application/json");

// =====================================================
// LOGGER (SILENT)
// =====================================================
function log_event($message, $data = []) {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $line = sprintf(
        "[%s] %s | %s\n",
        date("Y-m-d H:i:s"),
        $message,
        json_encode($data)
    );

    file_put_contents($dir . '/office-info.log', $line, FILE_APPEND);
}

// =====================================================
// 1. AUTHENTICATION (SESSION ONLY)
// =====================================================
$admin_id = $_SESSION['admin_id'] ?? $_SESSION['id'] ?? null;

if (!$admin_id) {
    log_event("Authentication failed");
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Not authenticated"
    ]);
    exit;
}

$admin_id = (int)$admin_id;
log_event("Authenticated", ["admin_id" => $admin_id]);

// =====================================================
// 2. LOAD OFFICE ASSIGNMENT
// =====================================================
$stmt = $conn->prepare("
    SELECT 
        o.id,
        o.office_name,
        o.latitude,
        o.longitude,
        o.radius_meters
    FROM admins a
    LEFT JOIN offices o ON a.office_id = o.id
    WHERE a.id = ?
");

if (!$stmt) {
    log_event("Prepare failed", ["error" => $conn->error]);
    echo json_encode([
        "success" => false,
        "message" => "System error"
    ]);
    exit;
}

$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result(
    $office_id,
    $office_name,
    $office_lat,
    $office_lng,
    $office_radius
);
$stmt->fetch();
$stmt->close();

log_event("Office query result", [
    "office_id" => $office_id,
    "lat" => $office_lat,
    "lng" => $office_lng,
    "radius" => $office_radius
]);

// =====================================================
// 3. VALIDATION
// =====================================================
if (
    empty($office_id) ||
    empty($office_lat) ||
    empty($office_lng) ||
    empty($office_radius)
) {
    log_event("Office not configured");
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Office not configured for this user"
    ]);
    exit;
}

// =====================================================
// 4. RESPONSE (ANDROID & WEB FRIENDLY)
// =====================================================
$response = [
    "success" => true,
    "office" => [
        "id"     => (int)$office_id,
        "name"   => $office_name,
        "lat"    => (float)$office_lat,
        "lng"    => (float)$office_lng,
        "radius" => (int)$office_radius
    ]
];

log_event("Office info sent", $response);

echo json_encode($response);
exit;
