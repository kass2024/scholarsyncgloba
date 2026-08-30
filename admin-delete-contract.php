<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
/* AUTH */
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

/* CSRF CHECK */
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit("Invalid CSRF token");
}

/* VALIDATE */
if (!isset($_POST['contract_id']) || !ctype_digit($_POST['contract_id'])) {
    http_response_code(400);
    exit("Invalid request");
}

$contractId = (int)$_POST['contract_id'];

/* FETCH PDF PATH */
$stmt = $conn->prepare("SELECT pdf_path FROM student_contracts WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $contractId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    exit("Contract not found");
}

/* DELETE FILE */
if (!empty($row['pdf_path'])) {
    $filePath = realpath(__DIR__ . '/' . $row['pdf_path']);
    $baseDir  = realpath(__DIR__ . '/uploads/contracts');

    if ($filePath && strpos($filePath, $baseDir) === 0 && file_exists($filePath)) {
        unlink($filePath);
    }
}

/* DELETE DB RECORD */
$stmt = $conn->prepare("DELETE FROM student_contracts WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $contractId);
$stmt->execute();
$stmt->close();

/* REDIRECT BACK */
header("Location: admin-contracts.php?deleted=1");
exit;
