<?php
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/employment_opportunities_schema.php';
require_once __DIR__ . '/helpers/eo_contract_schema.php';
require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json');

eo_ensure_schema($conn);
eo_contract_ensure_schema($conn);

$LOG_FILE = __DIR__ . '/logs/eo-contract-signing.log';

function eoLogMsg(string $msg, array $data = []): void
{
    global $LOG_FILE;
    if (!is_dir(dirname($LOG_FILE))) {
        @mkdir(dirname($LOG_FILE), 0777, true);
    }
    @file_put_contents($LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $msg . ' ' . json_encode($data) . PHP_EOL, FILE_APPEND);
}

function eoRespond(array $payload, int $code = 200): void
{
    ob_clean();
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function eoFail(string $message, int $code = 400, array $debug = []): void
{
    eoLogMsg('FAIL: ' . $message, $debug);
    eoRespond(['success' => false, 'error' => $message], $code);
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    eoFail('Invalid JSON payload', 400);
}

$token       = trim($data['token'] ?? '');
$name        = trim($data['client_name'] ?? '');
$signedDate  = trim($data['signed_date'] ?? '');
$signature   = $data['signature'] ?? '';
$email       = trim($data['client_email'] ?? '');
$passport    = trim($data['client_passport'] ?? '');
$phone       = trim($data['client_phone'] ?? '');
$address     = trim($data['client_address'] ?? '');
$agreementDate = trim($data['agreement_date'] ?? '') ?: $signedDate;

if ($token === '' || $name === '' || $signedDate === '' || $email === '' || $signature === '') {
    $missing = [];
    if ($token === '') $missing[] = 'token';
    if ($name === '') $missing[] = 'client_name';
    if ($signedDate === '') $missing[] = 'signed_date';
    if ($email === '') $missing[] = 'client_email';
    if ($signature === '') $missing[] = 'signature';
    eoFail('Missing required fields: ' . implode(', ', $missing), 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    eoFail('Invalid email address', 400);
}

if (!str_starts_with($signature, 'data:image/') || !str_contains($signature, 'base64,')) {
    eoFail('Invalid signature format', 400);
}

$stmt = $conn->prepare("SELECT id, status, application_id FROM eo_employment_contracts WHERE contract_token = ? LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contract) {
    eoFail('Contract not found', 404);
}

if ($contract['status'] === 'signed') {
    eoRespond(['success' => true, 'status' => 'already_signed', 'message' => 'This contract has already been signed.']);
}

$contractId = (int) $contract['id'];

$conn->begin_transaction();

try {
    $stmt = $conn->prepare('SELECT id FROM eo_employment_contracts WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $contractId);
    $stmt->execute();
    $stmt->close();

    $applicationId = !empty($contract['application_id']) ? (int) $contract['application_id'] : null;

    if (!$applicationId) {
        $stmt = $conn->prepare("SELECT id FROM employment_opportunities_applications WHERE LOWER(email) = LOWER(?) ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $existingApp = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!empty($existingApp['id'])) {
            $applicationId = (int) $existingApp['id'];
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO eo_employment_signatures
        (contract_id, client_name, client_email, client_passport, signed_date, signature_image, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param('isssss', $contractId, $name, $email, $passport, $signedDate, $signature);
    $stmt->execute();
    $stmt->close();

    $appIdForUpdate = $applicationId > 0 ? $applicationId : null;

    $stmt = $conn->prepare("
        UPDATE eo_employment_contracts SET
            application_id           = COALESCE(?, application_id),
            status                   = 'signed',
            signed_at                = NOW(),
            agreement_date           = ?,
            external_client_name     = ?,
            external_client_email    = ?,
            external_client_phone    = ?,
            external_client_passport = ?,
            external_client_address  = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        'issssssi',
        $appIdForUpdate,
        $agreementDate,
        $name,
        $email,
        $phone,
        $passport,
        $address,
        $contractId
    );
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    eoLogMsg('Contract finalized', ['contract_id' => $contractId]);
} catch (Throwable $e) {
    $conn->rollback();
    eoFail('Signing failed', 500, ['message' => $e->getMessage()]);
}

$pdfPath = null;
$pdfError = null;

try {
    require_once __DIR__ . '/generate-eo-contract-pdf.php';

    if (!function_exists('generateEoContractPDF')) {
        throw new RuntimeException('PDF generator missing');
    }

    $pdfPath = generateEoContractPDF($contractId);

    if (!$pdfPath || !file_exists($pdfPath)) {
        throw new RuntimeException('PDF file was not created');
    }

    $stmt = $conn->prepare('UPDATE eo_employment_contracts SET pdf_path = ? WHERE id = ?');
    $stmt->bind_param('si', $pdfPath, $contractId);
    $stmt->execute();
    $stmt->close();

    eoLogMsg('PDF generated', ['contract_id' => $contractId, 'pdf_path' => $pdfPath]);
} catch (Throwable $e) {
    $pdfError = $e->getMessage();
    eoLogMsg('PDF generation failed', ['contract_id' => $contractId, 'message' => $pdfError]);
}

eoRespond([
    'success'     => true,
    'status'      => 'signed',
    'contract_id' => $contractId,
    'pdf_path'    => $pdfPath,
    'pdf_error'   => $pdfError,
]);
