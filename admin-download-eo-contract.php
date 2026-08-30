<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/eo_contract_schema.php';

if (!isset($_SESSION['admin_id']) && empty($_SESSION['id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

eo_contract_ensure_schema($conn);

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    exit('Invalid contract ID');
}

$contractId = (int) $_GET['id'];

$stmt = $conn->prepare("SELECT id, pdf_path, status, contract_token FROM eo_employment_contracts WHERE id = ? AND status = 'signed' LIMIT 1");
$stmt->bind_param('i', $contractId);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contract) {
    http_response_code(404);
    exit('Contract not found');
}

$pdfPath = trim((string) ($contract['pdf_path'] ?? ''));

if ($pdfPath === '' || !is_file($pdfPath)) {
    require_once __DIR__ . '/generate-eo-contract-pdf.php';
    try {
        $pdfPath = generateEoContractPDF($contractId);
        $update = $conn->prepare('UPDATE eo_employment_contracts SET pdf_path = ? WHERE id = ?');
        $update->bind_param('si', $pdfPath, $contractId);
        $update->execute();
        $update->close();
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Unable to generate PDF');
    }
}

if (!is_file($pdfPath)) {
    http_response_code(404);
    exit('PDF not found');
}

$filename = 'eo_employment_contract_' . $contractId . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . (string) filesize($pdfPath));
readfile($pdfPath);
exit;
