<?php
declare(strict_types=1);

/**
 * Francophonie Mobility — live upload (saves directly to permanent storage).
 */
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request');
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        throw new RuntimeException('Upload failed (code ' . $code . ')');
    }

    $field = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_POST['field'] ?? 'file')));
    if ($field === '') {
        $field = 'file';
    }

    $permDir = __DIR__ . '/uploads/francophonie_mobility';
    if (!is_dir($permDir) && !mkdir($permDir, 0755, true) && !is_dir($permDir)) {
        throw new RuntimeException('Cannot create upload directory');
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'fm_' . date('Ymd') . '_' . uniqid() . '_' . $field . ($ext !== '' ? '.' . $ext : '');
    $dest = $permDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save file');
    }

    echo json_encode([
        'success' => true,
        'message' => 'File uploaded',
        'file_path' => 'uploads/francophonie_mobility/' . $filename,
        'original_name' => $file['name'],
        'size' => $file['size'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
