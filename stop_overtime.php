<?php
/**
 * stop_overtime.php
 * Stops a running overtime session and permanently completes the request
 * FINAL PRODUCTION VERSION
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

if (!isset($data['session_id']) || !is_numeric($data['session_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit;
}

$sessionId = (int)$data['session_id'];

/* -------------------------------------------------
   FETCH RUNNING SESSION + REQUEST
------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT 
        s.id,
        s.start_time,
        s.request_id
    FROM overtime_sessions s
    WHERE s.id = ?
      AND s.staff_id = ?
      AND s.status = 'running'
    LIMIT 1
");
$stmt->bind_param("ii", $sessionId, $staffId);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No running overtime session found.'
    ]);
    exit;
}

$session = $res->fetch_assoc();

/* -------------------------------------------------
   CALCULATE TOTAL MINUTES (SERVER-SIDE)
------------------------------------------------- */
$startTime = new DateTime($session['start_time']);
$endTime   = new DateTime();
$interval  = $startTime->diff($endTime);

$totalMinutes =
    ($interval->days * 24 * 60) +
    ($interval->h * 60) +
    $interval->i;

/* -------------------------------------------------
   TRANSACTION (CRITICAL)
------------------------------------------------- */
$conn->begin_transaction();

try {

    /* STOP SESSION */
    $stop = $conn->prepare("
        UPDATE overtime_sessions
        SET 
            end_time = NOW(),
            total_minutes = ?,
            status = 'stopped'
        WHERE id = ?
    ");
    $stop->bind_param("ii", $totalMinutes, $sessionId);
    $stop->execute();

    /* 🔒 LOCK REQUEST */
    $lock = $conn->prepare("
        UPDATE overtime_requests
        SET status = 'completed'
        WHERE id = ?
          AND status = 'approved'
    ");
    $lock->bind_param("i", $session['request_id']);
    $lock->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Overtime completed and locked.',
        'total_minutes' => $totalMinutes
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => 'Failed to stop overtime. Please try again.'
    ]);
}
