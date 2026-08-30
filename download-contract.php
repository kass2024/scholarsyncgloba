<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (!isset($_GET['token']) || trim($_GET['token']) === '') {
    http_response_code(400);
    exit('Invalid contract link.');
}

$token = trim($_GET['token']);
$inline = isset($_GET['inline']) && $_GET['inline'] === '1';

$stmt = $conn->prepare("
    SELECT id, pdf_path, status
    FROM student_contracts
    WHERE contract_token = ?
    LIMIT 1
");
$stmt->bind_param('s', $token);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contract) {
    http_response_code(404);
    exit('Contract not found.');
}

if ($contract['status'] !== 'signed') {
    http_response_code(403);
    exit('This contract has not been signed yet.');
}

$contractId = (int) $contract['id'];
$pdfPath = trim((string) ($contract['pdf_path'] ?? ''));

if ($pdfPath === '' || !is_file($pdfPath)) {
    require_once __DIR__ . '/generate-contract-pdf.php';

    if (!function_exists('generateContractPDF')) {
        http_response_code(500);
        exit('PDF generator unavailable.');
    }

    try {
        $pdfPath = generateContractPDF($contractId);
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Unable to generate signed PDF.');
    }

    if ($pdfPath !== '' && is_file($pdfPath)) {
        $update = $conn->prepare('UPDATE student_contracts SET pdf_path = ? WHERE id = ?');
        if ($update) {
            $update->bind_param('si', $pdfPath, $contractId);
            $update->execute();
            $update->close();
        }
    }
}

if ($pdfPath === '' || !is_file($pdfPath)) {
    http_response_code(404);
    exit('Signed PDF not found.');
}

$filename = 'signed_contract_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $token) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
header('Content-Length: ' . (string) filesize($pdfPath));
header('Cache-Control: private, no-store');
header('Pragma: no-cache');

readfile($pdfPath);
exit;
