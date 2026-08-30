<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/agent_contract_schema.php';

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

agent_contract_ensure_schema($conn);

$contractId = (int) $_POST['contract_id'];

$stmt = $conn->prepare('SELECT pdf_path FROM agent_contracts WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $contractId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header('Location: admin-agent-contracts.php?error=' . urlencode('Contract not found'));
    exit;
}

if (!empty($row['pdf_path'])) {
    $filePath = realpath($row['pdf_path']);
    $baseDir  = realpath(agent_contract_upload_dir());
    if ($filePath && $baseDir && strpos($filePath, $baseDir) === 0 && file_exists($filePath)) {
        unlink($filePath);
    }
}

$sigFile = agent_contract_upload_dir() . '/signature_' . $contractId . '.png';
if (is_file($sigFile)) {
    @unlink($sigFile);
}

$stmt = $conn->prepare('DELETE FROM agent_signatures WHERE contract_id = ?');
$stmt->bind_param('i', $contractId);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare('DELETE FROM agent_contracts WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $contractId);
$stmt->execute();
$stmt->close();

header('Location: admin-agent-contracts.php?deleted=1');
exit;
