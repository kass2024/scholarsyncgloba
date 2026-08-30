<?php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['admin_id'])) {
    die("Access denied.");
}

$admin_id = $_SESSION['admin_id'];
$admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM admins WHERE id = '$admin_id'"));
$admin_username = $admin['username'];

$userId = $_POST['user_id'] ?? '';
$message = trim($_POST['message'] ?? '');

if (!$userId || !$message) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO chat_messages (user_id, sender, message, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("sss", $userId, $admin_username, $message);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to send.']);
}

$stmt->close();
