<?php
// Very basic upload test
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    if (empty($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['file'];
    $field = $_POST['field'] ?? 'test';
    
    // Basic validation
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error: ' . $file['error']);
    }
    
    // Create upload directory
    $uploadDir = __DIR__ . '/uploads/tmp/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate filename (any extension or none)
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $field . '_' . time() . '_' . uniqid() . ($ext !== '' ? '.' . $ext : '');
    $targetPath = $uploadDir . $filename;
    
    // Move file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to move file');
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'file_path' => 'uploads/tmp/' . $filename,
        'original_name' => $file['name'],
        'size' => $file['size'],
        'debug_info' => [
            'field' => $field,
            'tmp_name' => $file['tmp_name'],
            'target_path' => $targetPath
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'files' => array_keys($_FILES),
            'post_keys' => array_keys($_POST),
            'file_error' => $file['error'] ?? 'no file'
        ]
    ]);
}
?>
