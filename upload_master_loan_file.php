<?php
declare(strict_types=1);

ob_start();
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/db.php';

if (empty($_SESSION['loan_user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please reload the form.']);
    exit;
}

$userId = trim((string)($_POST['user_id'] ?? ''));
if ($userId === '' || !hash_equals((string)$_SESSION['loan_user_id'], $userId)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid application context.']);
    exit;
}

$chk = $conn->prepare('SELECT id FROM master_loan_applications WHERE user_id = ? LIMIT 1');
if (!$chk) {
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    exit;
}
$chk->bind_param('s', $userId);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    $chk->close();
    echo json_encode(['status' => 'error', 'message' => 'No application record found. Continue the form or reload the page.']);
    exit;
}
$chk->close();

$allowed = [
    'acceptance_letter',
    'bachelor_degree',
    'bachelor_transcript',
    'cv',
    'id_document',
    'valid_passport',
    'english_certificate',
    'admission_fees',
    'scholarship_letter',
    'bank_statement',
];

$field = trim((string)($_POST['field'] ?? ''));
if (!in_array($field, $allowed, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Unsupported attachment field.']);
    exit;
}

if (empty($_FILES['file']['tmp_name']) || (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'File upload failed or missing.']);
    exit;
}

$skipAiValidation = ($_POST['skip_ai_validation'] ?? '') === '1';
$batchUploadToken = trim((string)($_POST['smart_autofill_batch_token'] ?? ''));
$sessionBatchToken = trim((string)($_SESSION['smart_autofill_batch_upload_token_loan'] ?? ''));
$sessionBatchExpiry = (int)($_SESSION['smart_autofill_batch_upload_token_loan_expires'] ?? 0);
$trustedBatchUpload = $skipAiValidation
    && $batchUploadToken !== ''
    && $sessionBatchToken !== ''
    && $sessionBatchExpiry >= time()
    && hash_equals($sessionBatchToken, $batchUploadToken);

if (!$trustedBatchUpload) {
    echo json_encode(['status' => 'error', 'message' => 'Smart autofill batch token missing or expired. Run analysis again.']);
    exit;
}

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
    echo json_encode(['status' => 'error', 'message' => 'Upload directory is not available.']);
    exit;
}

$fileName = time() . '_' . bin2hex(random_bytes(4)) . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '_', basename((string)($_FILES['file']['name'] ?? 'file')));
$targetAbs = $uploadDir . $fileName;
if (!move_uploaded_file((string)$_FILES['file']['tmp_name'], $targetAbs)) {
    echo json_encode(['status' => 'error', 'message' => 'Could not save the uploaded file.']);
    exit;
}

$relative = 'uploads/' . $fileName;
$sql = 'UPDATE master_loan_applications SET `' . $field . '` = ? WHERE user_id = ? LIMIT 1';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    @unlink($targetAbs);
    echo json_encode(['status' => 'error', 'message' => 'Database prepare failed.']);
    exit;
}
$stmt->bind_param('ss', $relative, $userId);
if (!$stmt->execute()) {
    @unlink($targetAbs);
    echo json_encode(['status' => 'error', 'message' => 'Could not attach the file to your application.']);
    $stmt->close();
    exit;
}
$stmt->close();

echo json_encode([
    'status' => 'success',
    'file_path' => $relative,
    'message' => 'Document attached from smart autofill batch.',
], JSON_UNESCAPED_UNICODE);
