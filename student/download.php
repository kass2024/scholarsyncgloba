<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/student_portal_schema.php';
require_once __DIR__ . '/auth.php';

pcvc_student_portal_ensure_schema($conn);

$accountId = (int)($_SESSION['student_account_id'] ?? 0);
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    exit('Not found');
}

$stmt = $conn->prepare("
    SELECT id, student_account_id, original_name, stored_name, mime_type, size_bytes, storage_path
    FROM student_portal_uploads
    WHERE id = ?
    LIMIT 1
");
$row = null;
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$row || (int)$row['student_account_id'] !== $accountId) {
    http_response_code(404);
    exit('Not found');
}

$rel = (string)($row['storage_path'] ?? '');
$path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
if ($rel === '' || !is_file($path)) {
    http_response_code(404);
    exit('File missing');
}

$mime = (string)($row['mime_type'] ?? 'application/octet-stream');
$name = (string)($row['original_name'] ?? 'download');
$size = (int)($row['size_bytes'] ?? 0);

header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');

readfile($path);
exit;

