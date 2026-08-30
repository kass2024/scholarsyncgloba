<?php
/**
 * update_overtime_status.php
 * Handles approve / reject overtime requests
 * PRODUCTION READY
 */

session_start();
require_once __DIR__ . '/db.php';
/* -------------------------------------------------
   FORCE JSON RESPONSE
------------------------------------------------- */
header('Content-Type: application/json; charset=utf-8');

/* -------------------------------------------------
   BASIC SESSION VALIDATION
------------------------------------------------- */
if (!isset($_SESSION['admin_id'], $_SESSION['role'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please login again.'
    ]);
    exit;
}

/* -------------------------------------------------
   ROLE VALIDATION (MATCHES YOUR DB)
------------------------------------------------- */
if ($_SESSION['role'] !== 'superadmin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized action.'
    ]);
    exit;
}

/* -------------------------------------------------
   READ & VALIDATE INPUT
------------------------------------------------- */
$rawInput = file_get_contents("php://input");

if (!$rawInput) {
    echo json_encode([
        'success' => false,
        'message' => 'Empty request.'
    ]);
    exit;
}

$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload.'
    ]);
    exit;
}

if (
    !isset($data['id'], $data['status']) ||
    !is_numeric($data['id'])
) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid parameters.'
    ]);
    exit;
}

$id = (int)$data['id'];
$status = trim($data['status']);

/* -------------------------------------------------
   ALLOW ONLY VALID STATUS VALUES
------------------------------------------------- */
$allowedStatus = ['approved', 'rejected'];

if (!in_array($status, $allowedStatus, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status value.'
    ]);
    exit;
}

/* -------------------------------------------------
   CHECK REQUEST EXISTS & IS PENDING
------------------------------------------------- */
$checkStmt = $conn->prepare("
    SELECT status 
    FROM overtime_requests 
    WHERE id = ?
    LIMIT 1
");
$checkStmt->bind_param("i", $id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Overtime request not found.'
    ]);
    exit;
}

$current = $checkResult->fetch_assoc();

if ($current['status'] !== 'pending') {
    echo json_encode([
        'success' => false,
        'message' => 'Request already processed.'
    ]);
    exit;
}

/* -------------------------------------------------
   UPDATE REQUEST STATUS
------------------------------------------------- */
$updateStmt = $conn->prepare("
    UPDATE overtime_requests
    SET 
        status = ?, 
        approved_by = ?, 
        approved_at = NOW()
    WHERE id = ?
");

$updateStmt->bind_param(
    "sii",
    $status,
    $_SESSION['admin_id'],
    $id
);

$success = $updateStmt->execute();

/* -------------------------------------------------
   FINAL RESPONSE
------------------------------------------------- */
if ($success) {
    echo json_encode([
        'success' => true,
        'message' => "Overtime request {$status} successfully."
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Database error. Please try again.'
    ]);
}
