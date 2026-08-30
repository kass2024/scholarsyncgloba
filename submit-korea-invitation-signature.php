<?php
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/korea_invitation_contract_schema.php';
require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json');

kic_contract_ensure_schema($conn);

$LOG_FILE = __DIR__ . '/logs/korea-invitation-contract-signing.log';

function kicLogMsg(string $msg, array $data = []): void
{
    global $LOG_FILE;
    if (!is_dir(dirname($LOG_FILE))) {
        mkdir(dirname($LOG_FILE), 0777, true);
    }
    file_put_contents(
        $LOG_FILE,
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . ' ' . json_encode($data) . PHP_EOL,
        FILE_APPEND
    );
}

function kicRespond(array $payload, int $code = 200): void
{
    ob_clean();
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function kicFail(string $message, int $code = 400, array $debug = []): void
{
    kicLogMsg('FAIL: ' . $message, $debug);
    kicRespond(['success' => false, 'error' => $message], $code);
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    kicFail('Invalid JSON payload', 400);
}

$token              = trim($data['token'] ?? '');
$name               = trim($data['client_name'] ?? '');
$signedDate         = trim($data['signed_date'] ?? '');
$signature          = $data['signature'] ?? '';
$email              = trim($data['client_email'] ?? '');
$passport           = trim($data['client_passport'] ?? '');
$phone              = trim($data['client_phone'] ?? '');
$agreementDate      = trim($data['agreement_date'] ?? '') ?: $signedDate;

if ($token === '' || $name === '' || $signedDate === '' || $email === '' || $signature === '' || $passport === '') {
    $missing = [];
    if ($token === '') $missing[] = 'token';
    if ($name === '') $missing[] = 'client_name';
    if ($signedDate === '') $missing[] = 'signed_date';
    if ($email === '') $missing[] = 'client_email';
    if ($signature === '') $missing[] = 'signature';
    if ($passport === '') $missing[] = 'client_passport';
    kicFail('Missing required fields: ' . implode(', ', $missing), 400);
}

if ($phone === '') {
    kicFail('Please complete telephone before signing.', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    kicFail('Invalid email address', 400);
}

if (!str_starts_with($signature, 'data:image/') || !str_contains($signature, 'base64,')) {
    kicFail('Invalid signature format', 400);
}

$stmt = $conn->prepare('SELECT id, status FROM korea_invitation_contracts WHERE contract_token = ? LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contract) {
    kicFail('Contract not found', 404);
}

if ($contract['status'] === 'signed') {
    kicRespond([
        'success' => true,
        'status'  => 'already_signed',
        'message' => 'This contract has already been signed.',
    ]);
}

$contractId = (int) $contract['id'];

$conn->begin_transaction();

try {
    $stmt = $conn->prepare('SELECT id FROM korea_invitation_contracts WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $contractId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        INSERT INTO korea_invitation_signatures
        (contract_id, client_name, client_email, client_passport, signed_date, signature_image, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param('isssss', $contractId, $name, $email, $passport, $signedDate, $signature);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE korea_invitation_contracts SET
            status = 'signed',
            signed_at = NOW(),
            agreement_date = ?,
            external_client_name = ?,
            external_client_email = ?,
            external_client_phone = ?,
            external_client_passport = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        'sssssi',
        $agreementDate,
        $name,
        $email,
        $phone,
        $passport,
        $contractId
    );
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    kicLogMsg('Contract finalized', ['contract_id' => $contractId]);
} catch (Throwable $e) {
    $conn->rollback();
    kicFail('Signing failed', 500, ['message' => $e->getMessage()]);
}

$pdfPath = null;
$pdfError = null;

try {
    require_once __DIR__ . '/generate-korea-invitation-contract-pdf.php';
    if (!function_exists('generateKoreaInvitationContractPDF')) {
        throw new RuntimeException('PDF generator missing');
    }
    $pdfPath = generateKoreaInvitationContractPDF($contractId);
    if (!$pdfPath || !file_exists($pdfPath)) {
        throw new RuntimeException('PDF file was not created');
    }
    $stmt = $conn->prepare('UPDATE korea_invitation_contracts SET pdf_path = ? WHERE id = ?');
    $stmt->bind_param('si', $pdfPath, $contractId);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    $pdfError = $e->getMessage();
    kicLogMsg('PDF generation failed', ['contract_id' => $contractId, 'message' => $pdfError]);
}

kicRespond([
    'success'     => true,
    'status'      => 'signed',
    'contract_id' => $contractId,
    'pdf_path'    => $pdfPath,
    'pdf_error'   => $pdfError,
]);
