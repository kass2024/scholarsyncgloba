<?php
/**
 * start_overtime.php
 * Starts an overtime session for approved requests
 * PRODUCTION READY
 */

session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

/* -------------------------------------------------
   SESSION & ROLE CHECK
------------------------------------------------- */
if (!isset($_SESSION['admin_id'], $_SESSION['role'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please login again.'
    ]);
    exit;
}

if ($_SESSION['role'] !== 'staff') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized action.'
    ]);
    exit;
}

$staffId = (int)$_SESSION['admin_id'];

/* -------------------------------------------------
   READ INPUT
------------------------------------------------- */
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['request_id']) || !is_numeric($data['request_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit;
}

$requestId = (int)$data['request_id'];

/* -------------------------------------------------
   VERIFY REQUEST BELONGS TO STAFF & IS APPROVED
------------------------------------------------- */
$checkRequest = $conn->prepare("
    SELECT id 
    FROM overtime_requests
    WHERE id = ?
      AND staff_id = ?
      AND status = 'approved'
    LIMIT 1
");
$checkRequest->bind_param("ii", $requestId, $staffId);
$checkRequest->execute();
$result = $checkRequest->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Overtime request not approved or not found.'
    ]);
    exit;
}

/* -------------------------------------------------
   PREVENT MULTIPLE RUNNING SESSIONS
------------------------------------------------- */
$checkRunning = $conn->prepare("
    SELECT id
    FROM overtime_sessions
    WHERE staff_id = ?
      AND status = 'running'
    LIMIT 1
");
$checkRunning->bind_param("i", $staffId);
$checkRunning->execute();
$running = $checkRunning->get_result();

if ($running->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'An overtime session is already running.'
    ]);
    exit;
}

/* -------------------------------------------------
   START OVERTIME SESSION
------------------------------------------------- */
$insert = $conn->prepare("
    INSERT INTO overtime_sessions (
        request_id,
        staff_id,
        start_time,
        status
    ) VALUES (?, ?, NOW(), 'running')
");

$insert->bind_param("ii", $requestId, $staffId);
$success = $insert->execute();

/* -------------------------------------------------
   RESPONSE
------------------------------------------------- */
if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Overtime started successfully.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to start overtime. Please try again.'
    ]);
}
