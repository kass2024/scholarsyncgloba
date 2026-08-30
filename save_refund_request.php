<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/csrf.php';
require_once __DIR__ . '/helpers/refund_requests_schema.php';

function refund_save_respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    refund_save_respond(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

if (!pcvc_csrf_validate_post()) {
    refund_save_respond(['ok' => false, 'error' => 'Invalid security token. Please reload and try again.'], 403);
}

pcvc_ensure_refund_requests_schema($conn);

$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone = trim((string) ($_POST['phone'] ?? ''));
$applicationId = trim((string) ($_POST['application_id'] ?? ''));
$studentAppId = isset($_POST['student_application_id']) ? (int) $_POST['student_application_id'] : 0;
$isExisting = !empty($_POST['is_existing_student']) ? 1 : 0;
$servicePaid = trim((string) ($_POST['service_paid_for'] ?? ''));
$reason = trim((string) ($_POST['reason'] ?? ''));
$amountRaw = trim((string) ($_POST['amount'] ?? ''));
$currency = strtoupper(trim((string) ($_POST['currency'] ?? 'USD'))) ?: 'USD';
$submittedBy = trim((string) ($_POST['submitted_by'] ?? 'public'));
$portalAccountId = isset($_POST['student_portal_account_id']) ? (int) $_POST['student_portal_account_id'] : 0;

if ($firstName === '' || $lastName === '') {
    refund_save_respond(['ok' => false, 'error' => 'First and last name are required.'], 400);
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    refund_save_respond(['ok' => false, 'error' => 'A valid email address is required.'], 400);
}
if ($servicePaid === '') {
    refund_save_respond(['ok' => false, 'error' => 'Please specify the service you paid for.'], 400);
}
if ($reason === '') {
    refund_save_respond(['ok' => false, 'error' => 'Please describe the reason for your refund request.'], 400);
}
if (!is_numeric($amountRaw) || (float) $amountRaw <= 0) {
    refund_save_respond(['ok' => false, 'error' => 'Please enter a valid amount greater than zero.'], 400);
}
$amount = round((float) $amountRaw, 2);

if (!in_array($submittedBy, ['public', 'student_portal'], true)) {
    $submittedBy = 'public';
}

// Student portal: verify session
if ($submittedBy === 'student_portal') {
    $sessAccount = (int) ($_SESSION['student_account_id'] ?? 0);
    if ($sessAccount < 1) {
        refund_save_respond(['ok' => false, 'error' => 'Please sign in to submit from your account.'], 401);
    }
    $portalAccountId = $sessAccount;
}

// Handle file upload
$proofPath = null;
if (!empty($_FILES['payment_proof']) && is_array($_FILES['payment_proof'])) {
    $f = $_FILES['payment_proof'];
    $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_OK) {
        $maxBytes = 8 * 1024 * 1024;
        $size = (int) ($f['size'] ?? 0);
        if ($size > $maxBytes) {
            refund_save_respond(['ok' => false, 'error' => 'Proof of payment must be 8 MB or smaller.'], 400);
        }
        $orig = (string) ($f['name'] ?? 'proof');
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $allowed, true)) {
            refund_save_respond(['ok' => false, 'error' => 'Proof must be PDF or an image (JPG, PNG, WEBP).'], 400);
        }
        $dir = pcvc_refund_upload_dir();
        $safe = pcvc_refund_safe_filename($orig);
        $fname = 'proof_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $fname;
        if (!move_uploaded_file((string) $f['tmp_name'], $dest)) {
            refund_save_respond(['ok' => false, 'error' => 'Could not save proof of payment. Try again.'], 500);
        }
        $proofPath = 'uploads/refund_requests/' . $fname;
    } elseif ($err !== UPLOAD_ERR_NO_FILE) {
        refund_save_respond(['ok' => false, 'error' => 'File upload failed. Please try again.'], 400);
    }
}

if ($proofPath === null) {
    refund_save_respond(['ok' => false, 'error' => 'Proof of payment is required.'], 400);
}

$referenceId = pcvc_refund_generate_reference();
$status = 'pending';
$studentAppIdVal = $studentAppId > 0 ? $studentAppId : 0;
$portalVal = $portalAccountId > 0 ? $portalAccountId : 0;

$stmt = $conn->prepare("
    INSERT INTO refund_requests (
        reference_id, student_application_id, student_portal_account_id,
        first_name, last_name, email, phone, application_id, is_existing_student,
        service_paid_for, amount, currency, reason, payment_proof_file,
        request_status, submitted_by, created_at
    ) VALUES (?, NULLIF(?, 0), NULLIF(?, 0), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
if (!$stmt) {
    refund_save_respond(['ok' => false, 'error' => 'Database error.'], 500);
}

$stmt->bind_param(
    'siisssssisdsssss',
    $referenceId,
    $studentAppIdVal,
    $portalVal,
    $firstName,
    $lastName,
    $email,
    $phone,
    $applicationId,
    $isExisting,
    $servicePaid,
    $amount,
    $currency,
    $reason,
    $proofPath,
    $status,
    $submittedBy
);

if (!$stmt->execute()) {
    $stmt->close();
    refund_save_respond(['ok' => false, 'error' => 'Could not save your request. Please try again.'], 500);
}
$newId = (int) $stmt->insert_id;
$stmt->close();

// Log initial status
$log = $conn->prepare(
    'INSERT INTO refund_request_status_logs (refund_request_id, old_status, new_status, admin_id, comment, created_at)
     VALUES (?, NULL, ?, NULL, ?, NOW())'
);
if ($log) {
    $initComment = 'Request submitted via ' . ($submittedBy === 'student_portal' ? 'student portal' : 'public form');
    $log->bind_param('iss', $newId, $status, $initComment);
    $log->execute();
    $log->close();
}

refund_save_respond([
    'ok' => true,
    'id' => $newId,
    'reference_id' => $referenceId,
    'message' => 'Your refund request has been submitted successfully.',
]);
