<?php
session_start();
require_once __DIR__ . '/db.php';
$userId = $_SESSION['user_id'] ?? '';
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone_number'] ?? '');

if ($userId && $email && $phone) {
    // Save or update
    $stmt = $conn->prepare("
        INSERT INTO student_chat_users (user_id, email, phone_number)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE email = VALUES(email), phone_number = VALUES(phone_number)
    ");
    $stmt->bind_param("sss", $userId, $email, $phone);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
}
