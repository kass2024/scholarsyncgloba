<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/eo_contract_schema.php';

if (!isset($_SESSION['admin_id']) && empty($_SESSION['id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

if (!isset($_POST['contract_id']) || !ctype_digit($_POST['contract_id'])) {
    http_response_code(400);
    exit('Invalid request');
}

eo_contract_ensure_schema($conn);

$contractId = (int) $_POST['contract_id'];

$stmt = $conn->prepare('SELECT pdf_path FROM eo_employment_contracts WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $contractId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    exit('Contract not found');
}

if (!empty($row['pdf_path'])) {
    $filePath = realpath(__DIR__ . '/' . $row['pdf_path']);
    if (!$filePath) {
        $filePath = realpath($row['pdf_path']);
    }
    $baseDir = realpath(__DIR__ . '/uploads/eo_contracts');
    if ($filePath && $baseDir && strpos($filePath, $baseDir) === 0 && file_exists($filePath)) {
        unlink($filePath);
    }
}

$stmt = $conn->prepare('DELETE FROM eo_employment_signatures WHERE contract_id = ?');
$stmt->bind_param('i', $contractId);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare('DELETE FROM eo_employment_contracts WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $contractId);
$stmt->execute();
$stmt->close();

header('Location: admin-eo-contracts.php?deleted=1');
exit;
