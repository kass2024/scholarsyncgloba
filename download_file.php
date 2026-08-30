<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/secure_file.php';

pcvc_secure_file_require_auth();

if (!isset($_GET['id'])) {
    http_response_code(400);
    exit('No file specified.');
}

$fileId = (int) $_GET['id'];
if ($fileId <= 0) {
    http_response_code(400);
    exit('Invalid file id.');
}

$query = 'SELECT * FROM upafa_registration_files WHERE id = ? LIMIT 1';
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $fileId);
$stmt->execute();
$result = $stmt->get_result();
$file = $result->fetch_assoc();
$stmt->close();

if (!$file) {
    http_response_code(404);
    exit('File not found in database.');
}

$relPath = pcvc_norm_upload_rel_path((string) ($file['storage_path'] ?? ''));
$filePath = pcvc_secure_file_resolve($relPath);
if ($filePath === null) {
    http_response_code(404);
    exit('File not found on server.');
}

$fileName = (string) ($file['original_name'] ?? 'download');
$mime = (string) ($file['mime_type'] ?? 'application/octet-stream');

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fileName) . '"');
header('Content-Length: ' . (string) filesize($filePath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600, no-transform');

readfile($filePath);
exit;
