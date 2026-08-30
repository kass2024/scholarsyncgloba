<?php
require_once __DIR__ . '/db.php';
session_start();

$userId = $_SESSION['user_id'] ?? '';

$stmt = $conn->prepare("SELECT email, phone_number FROM student_chat_users WHERE user_id = ?");
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if ($data) {
    echo json_encode(['status' => 'found', 'email' => $data['email'], 'phone' => $data['phone_number']]);
} else {
    echo json_encode(['status' => 'not_found']);
}
