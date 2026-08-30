<?php
declare(strict_types=1);

/**
 * Employment Opportunities — live file upload (passport or academic docs).
 * Fast path: skip full schema migration; only ensure upload directory exists.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request');
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $map = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File too large',
            UPLOAD_ERR_PARTIAL => 'Upload was interrupted — please try again',
            UPLOAD_ERR_NO_FILE => 'No file received',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder missing',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write file',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension',
        ];
        throw new RuntimeException($map[$code] ?? ('Upload failed (code ' . $code . ')'));
    }

    $field = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_POST['field'] ?? 'file')));
    if ($field === '') {
        $field = 'file';
    }
    if (!in_array($field, ['passport', 'academic'], true)) {
        $field = 'file';
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Allowed types: PDF, JPG, PNG, WEBP, DOC, DOCX');
    }
    if ((int) ($file['size'] ?? 0) > 15 * 1024 * 1024) {
        throw new RuntimeException('File too large (max 15MB)');
    }

    $permDir = __DIR__ . '/uploads/employment_opportunities';
    if (!is_dir($permDir) && !mkdir($permDir, 0755, true) && !is_dir($permDir)) {
        throw new RuntimeException('Cannot create upload directory');
    }

    $filename = 'eo_' . date('Ymd') . '_' . bin2hex(random_bytes(8)) . '_' . $field . '.' . $ext;
    $dest = $permDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save file');
    }

    echo json_encode([
        'success' => true,
        'message' => 'File uploaded',
        'file_path' => 'uploads/employment_opportunities/' . $filename,
        'original_name' => $file['name'],
        'size' => (int) $file['size'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
