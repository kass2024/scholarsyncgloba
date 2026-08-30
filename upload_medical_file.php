<?php
/**
 * upload_medical_file.php
 * Dedicated file upload handler for Canada Medical Exams Request
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Start session
session_start();

// Debug: Log session info
error_log("Session started. Name: " . session_name() . ", ID: " . session_id());
error_log("Session data: " . json_encode($_SESSION));

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success'=>false, 'message'=>'Session expired']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate CSRF token
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success'=>false, 'message'=>'Invalid CSRF token']);
    exit;
}

// Debug: Log files info
error_log("FILES data: " . json_encode($_FILES));
error_log("POST data: " . json_encode($_POST));

if (empty($_FILES['file']['name'])) {
    error_log("No file uploaded");
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>'No file uploaded']);
    exit;
}

$field = $_POST['field'] ?? 'unknown';
error_log("Field: " . $field);

// Allowed file types
$allowed = ['pdf','jpg','jpeg','png','doc','docx'];
$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>'Invalid file type. Allowed: ' . implode(', ', $allowed)]);
    exit;
}

// Check file size - 15MB limit
$maxSize = 15 * 1024 * 1024; // 15MB
if ($_FILES['file']['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>'File too large. Maximum size is 15MB']);
    exit;
}

// Create upload directory
$base = __DIR__ . "/uploads/tmp/{$user_id}/";
if (!file_exists($base)) {
    if (!mkdir($base, 0755, true)) {
        echo json_encode(['success'=>false, 'message'=>'Failed to create upload directory']);
        exit;
    }
}

// Generate unique filename
$filename = "{$field}_" . bin2hex(random_bytes(8)) . ".{$ext}";
$path = $base . $filename;

// Move uploaded file
if (!move_uploaded_file($_FILES['file']['tmp_name'], $path)) {
    echo json_encode(['success'=>false, 'message'=>'Failed to move uploaded file']);
    exit;
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'File uploaded successfully',
    'field'   => $field,
    'file_path' => "uploads/tmp/{$user_id}/{$filename}",
    'original_name' => $_FILES['file']['name'],
    'size' => $_FILES['file']['size']
]);
?>
