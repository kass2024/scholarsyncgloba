<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    exit("Invalid request");
}

$contractId = (int) $_GET['id'];

$stmt = $conn->prepare("
    SELECT pdf_path, signature_image, language
    FROM partner_contracts
    WHERE id = ? AND status = 'signed'
    LIMIT 1
");
$stmt->bind_param("i", $contractId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    exit("Contract not found");
}

$pdfPath = $row['pdf_path'];
$language = $row['language'] ?? 'english';

// Always regenerate so PDF matches the latest signed data and layout.
if (!empty($row['signature_image'])) {
    if ($language === 'french') {
        require_once __DIR__ . '/generate-partner-contract-pdf-french-professional.php';
        if (function_exists('generatePartnerContractPDFFrench')) {
            $pdfPath = generatePartnerContractPDFFrench($contractId);
        }
    } else {
        require_once __DIR__ . '/generate-partner-contract-pdf-professional.php';
        if (function_exists('generatePartnerContractPDF')) {
            $pdfPath = generatePartnerContractPDF($contractId);
        }
    }

    if ($pdfPath) {
        $stmt = $conn->prepare("UPDATE partner_contracts SET pdf_path = ? WHERE id = ?");
        $stmt->bind_param("si", $pdfPath, $contractId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!$pdfPath || !file_exists($pdfPath)) {
    http_response_code(404);
    exit("PDF not found");
}

header("Content-Type: application/pdf");
$language = $row['language'] ?? 'english';
$filename = $language === 'french' ? "contrat-partenariat-{$contractId}.pdf" : "partner-contract-{$contractId}.pdf";
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Content-Length: " . filesize($pdfPath));
header("Cache-Control: no-store");
header("Pragma: no-cache");

readfile($pdfPath);
exit;
