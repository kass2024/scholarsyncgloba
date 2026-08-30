<?php
header('Content-Type: application/json');
require 'db.php'; // Make sure this connects and provides $conn

if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing user_id']);
    exit;
}

$userId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $_POST['user_id']);
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Get the uploaded file field name
$field = null;
foreach ($_FILES as $key => $file) {
    $field = $key;
    break;
}

if (!$field || !isset($_FILES[$field])) {
    echo json_encode(['status' => 'error', 'message' => 'No file field found']);
    exit;
}

$file = $_FILES[$field];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Upload error: ' . $file['error']]);
    exit;
}

// Generate safe file name
$originalName = basename($file['name']);
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
$safeName = $userId . '_' . $field . '_' . time() . '.' . $extension;
$targetPath = $uploadDir . $safeName;

// Move the uploaded file
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Insert path into database
    $column = mapFieldToColumn($field); // Map form field name to DB column
    $stmt = $conn->prepare("UPDATE dphu SET $column = ? WHERE user_id = ?");
    $stmt->bind_param("ss", $targetPath, $userId);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'path' => $targetPath]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database update failed']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file']);
}

// Helper function
function mapFieldToColumn($field) {
    $map = [
        'passport_photo' => 'photo',
        'national_id_or_passport' => 'passport',
        'diploma_certificate' => 'degree_certificate',
        'academic_transcripts' => 'academic_transcript',
        'language_proof' => 'language_proof',
        // handle others as needed
    ];
    return $map[$field] ?? $field; // fallback to same name
}
