<?php
require_once __DIR__ . '/db.php';
$userId = $_POST['user_id'] ?? '';
$field = $_POST['field'] ?? '';
$file = $_FILES['file'] ?? null;

if (!$userId || !$field || !$file) {
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
    exit;
}

$filePath = 'uploads/' . uniqid() . '_' . basename($file['name']);
if (move_uploaded_file($file['tmp_name'], $filePath)) {
    $stmt = $conn->prepare("UPDATE master_loan_applications SET $field = ? WHERE user_id = ?");
    $stmt->bind_param("ss", $filePath, $userId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success', 'message' => "$field uploaded"]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Upload failed']);
}
