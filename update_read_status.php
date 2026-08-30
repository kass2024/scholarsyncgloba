<?php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = $_POST['user_id'] ?? '';
$last_message_id = (int) ($_POST['last_message_id'] ?? 0);

if ($user_id && $last_message_id > 0) {
    $stmt = $conn->prepare("
        UPDATE chat_conversations 
        SET admin_last_read_message_id = ? 
        WHERE user_id = ? AND is_active = 1
    ");
    $stmt->bind_param("is", $last_message_id, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
}
?>
