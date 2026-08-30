<?php
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . "/vendor/autoload.php";

header("Content-Type: application/json");

$LOG_FILE = __DIR__ . "/logs/partner-contract-signing.log";

function logMsg(string $msg, array $data = []): void {
    global $LOG_FILE;
    if (!is_dir(dirname($LOG_FILE))) {
        mkdir(dirname($LOG_FILE), 0777, true);
    }
    file_put_contents(
        $LOG_FILE,
        "[" . date("Y-m-d H:i:s") . "] {$msg} " . json_encode($data) . PHP_EOL,
        FILE_APPEND
    );
}

function respond(array $payload, int $code = 200): void {
    ob_clean();
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $code = 400, array $debug = []): void {
    logMsg("FAIL: {$message}", $debug);
    respond(["success" => false, "error" => $message], $code);
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

logMsg("RAW INPUT", ["raw" => $raw]);

if (!is_array($data)) {
    fail("Invalid JSON payload", 400);
}

$token = trim($data['token'] ?? '');
$name = trim($data['representative_name'] ?? '');
$title = trim($data['representative_title'] ?? '');
$email = trim($data['representative_email'] ?? '');
$signedDate = trim($data['signed_date'] ?? '');
$signature = $data['signature'] ?? '';
$companyName = trim($data['company_name'] ?? '');
$companyPhone = trim($data['company_phone'] ?? '');
$companyAddress = trim($data['company_address'] ?? '');
$companyEmail = trim($data['representative_email'] ?? '');

if ($token === '' || $name === '' || $signedDate === '' || $email === '' || $signature === '' || $companyName === '') {
    fail("Missing required fields", 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail("Invalid email address", 400);
}

if (!str_starts_with($signature, 'data:image/png;base64,')) {
    fail("Invalid signature format", 400);
}

$stmt = $conn->prepare("SELECT id, status FROM partner_contracts WHERE contract_token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contract) {
    fail("Contract not found", 404);
}

if ($contract['status'] === 'signed') {
    respond([
        "success" => true,
        "status" => "already_signed",
        "message" => "This contract has already been signed."
    ]);
}

$contractId = (int) $contract['id'];

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("SELECT id FROM partner_contracts WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $contractId);
    $stmt->execute();
    $stmt->close();

    logMsg("Contract locked", ["contract_id" => $contractId]);

    $stmt = $conn->prepare("
        INSERT INTO partner_signatures
        (contract_id, company_name, representative_name, representative_email, signed_date, signature_image, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("isssss", $contractId, $companyName, $name, $email, $signedDate, $signature);
    $stmt->execute();
    $stmt->close();

    $updateSql = "UPDATE partner_contracts SET 
        company_name = ?, company_email = ?, company_phone = ?, company_address = ?,
        representative_name = ?, representative_title = ?, representative_email = ?,
        signed_date = ?, status = 'signed', signed_at = NOW(), signature_image = ?
        WHERE id = ? AND contract_token = ?";

    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("sssssssssis", 
        $companyName, $companyEmail, $companyPhone, $companyAddress,
        $name, $title, $email,
        $signedDate, $signature, $contractId, $token
    );
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    logMsg("Partner contract finalized", ["contract_id" => $contractId]);

    // Generate PDF based on contract language
    $stmt = $conn->prepare("SELECT language FROM partner_contracts WHERE id = ?");
    $stmt->bind_param("i", $contractId);
    $stmt->execute();
    $result = $stmt->get_result();
    $contractData = $result->fetch_assoc();
    $stmt->close();
    
    $language = $contractData['language'] ?? 'english';
    
    if ($language === 'french') {
        require_once __DIR__ . '/generate-partner-contract-pdf-french-professional.php';
        $pdfPath = generatePartnerContractPDFFrench($contractId);
    } else {
        require_once __DIR__ . '/generate-partner-contract-pdf-professional.php';
        $pdfPath = generatePartnerContractPDF($contractId);
    }
    
    if ($pdfPath) {
        logMsg("PDF generated successfully", ["contract_id" => $contractId, "language" => $language, "path" => $pdfPath]);
    } else {
        logMsg("PDF generation failed", ["contract_id" => $contractId, "language" => $language]);
    }

} catch (Throwable $e) {
    $conn->rollback();
    fail("Signing failed", 500, ["message" => $e->getMessage(), "line" => $e->getLine()]);
}

respond([
    "success" => true,
    "status" => "signed",
    "contract_id" => $contractId
]);
