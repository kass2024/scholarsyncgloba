<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
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

$contractId = (int) $_POST['contract_id'];

$stmt = $conn->prepare('SELECT pdf_path FROM partner_contracts WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $contractId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header('Location: admin-partner-contracts.php?error=' . urlencode('Contract not found'));
    exit;
}

if (!empty($row['pdf_path'])) {
    $filePath = realpath($row['pdf_path']);
    if (!$filePath) {
        $filePath = realpath(__DIR__ . '/' . ltrim((string) $row['pdf_path'], '/\\'));
    }
    $baseDir = realpath(__DIR__);
    if ($filePath && $baseDir && strpos($filePath, $baseDir) === 0 && is_file($filePath)) {
        @unlink($filePath);
    }
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('DELETE FROM partner_signatures WHERE contract_id = ?');
    $stmt->bind_param('i', $contractId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM partner_contracts WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $contractId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Partner contract delete failed: ' . $e->getMessage());
    header('Location: admin-partner-contracts.php?error=' . urlencode('Failed to delete contract'));
    exit;
}

header('Location: admin-partner-contracts.php?deleted=1');
exit;
