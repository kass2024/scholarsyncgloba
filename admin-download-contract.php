<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';
/* ===============================
   ADMIN SECURITY
================================ */
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

/* ===============================
   VALIDATE INPUT
================================ */
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    exit("Invalid request");
}

$contractId = (int) $_GET['id'];

/* ===============================
   LOAD CONTRACT
================================ */
$stmt = $conn->prepare("
    SELECT pdf_path
    FROM student_contracts
    WHERE id = ?
      AND status = 'signed'
    LIMIT 1
");
$stmt->bind_param("i", $contractId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['pdf_path'])) {
    http_response_code(404);
    exit("PDF not found");
}

$pdfPath = $row['pdf_path'];

/* ===============================
   FILE SAFETY
================================ */
if (!file_exists($pdfPath)) {
    http_response_code(404);
    exit("File missing on server");
}

/* ===============================
   FORCE DOWNLOAD
================================ */
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=\"" . basename($pdfPath) . "\"");
header("Content-Length: " . filesize($pdfPath));
header("Cache-Control: no-store");
header("Pragma: no-cache");

readfile($pdfPath);
exit;
