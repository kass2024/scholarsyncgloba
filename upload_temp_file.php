<?php
declare(strict_types=1);
header('Content-Type: application/json');

// Start session - handle both job form and medical form sessions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for either session type
$user_id = null;
if (!empty($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (!empty($_SESSION['MED_USER_ID'])) {
    $user_id = $_SESSION['MED_USER_ID'];
}

if (empty($user_id)) {
    http_response_code(401);
    echo json_encode(['success'=>false, 'message'=>'Session expired']);
    exit;
}

// Validate CSRF token if present
if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['success'=>false, 'message'=>'Invalid CSRF token']);
        exit;
    }
}

if (empty($_FILES['file']['name'])) {
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>'No file uploaded']);
    exit;
}

$field = $_POST['field'] ?? 'unknown';

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
