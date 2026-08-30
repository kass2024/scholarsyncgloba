<?php
/**
 * mark_overtime_paid.php
 * ---------------------------------------
 * Records ONE payment per overtime request
 * Enforces accounting integrity
 * Supports RWF & USD
 */

session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

/* =====================================================
   SECURITY CHECK
===================================================== */
if (
    !isset($_SESSION['admin_id'], $_SESSION['role']) ||
    $_SESSION['role'] !== 'superadmin'
) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

$adminId = (int) $_SESSION['admin_id'];

/* =====================================================
   INPUT VALIDATION
===================================================== */
$data = json_decode(file_get_contents("php://input"), true);

if (
    empty($data['request_id']) ||
    empty($data['amount']) ||
    empty($data['currency'])
) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

$requestId = (int) $data['request_id'];
$amount    = (float) $data['amount'];
$currency  = strtoupper(trim($data['currency']));

if ($amount <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Amount must be greater than zero'
    ]);
    exit;
}

if (!in_array($currency, ['RWF', 'USD'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid currency'
    ]);
    exit;
}

/* =====================================================
   ENSURE OVERTIME EXISTS & IS COMPLETED
===================================================== */
$checkRequest = $conn->prepare("
    SELECT staff_id 
    FROM overtime_requests
    WHERE id = ? AND status = 'completed'
    LIMIT 1
");
$checkRequest->bind_param("i", $requestId);
$checkRequest->execute();
$requestRes = $checkRequest->get_result();

if ($requestRes->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Overtime not completed or not found'
    ]);
    exit;
}

$staffId = (int) $requestRes->fetch_assoc()['staff_id'];

/* =====================================================
   PREVENT DOUBLE PAYMENT (CRITICAL)
===================================================== */
$checkPaid = $conn->prepare("
    SELECT id 
    FROM overtime_payments
    WHERE overtime_request_id = ?
    LIMIT 1
");
$checkPaid->bind_param("i", $requestId);
$checkPaid->execute();
$checkPaid->store_result();

if ($checkPaid->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'This overtime has already been paid'
    ]);
    exit;
}

/* =====================================================
   INSERT PAYMENT (ATOMIC & SAFE)
===================================================== */
$conn->begin_transaction();

try {

    $insert = $conn->prepare("
        INSERT INTO overtime_payments
        (overtime_request_id, staff_id, amount, currency, paid_by, paid_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $insert->bind_param(
        "iidsi",
        $requestId,
        $staffId,
        $amount,
        $currency,
        $adminId
    );

    if (!$insert->execute()) {
        throw new Exception("Payment insert failed");
    }

    /* Optional: lock request status explicitly */
    $lock = $conn->prepare("
        UPDATE overtime_requests
        SET status = 'completed'
        WHERE id = ?
    ");
    $lock->bind_param("i", $requestId);
    $lock->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Payment recorded successfully ({$currency} " . number_format($amount, 2) . ")"
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => 'Failed to record payment'
    ]);
}
