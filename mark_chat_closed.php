<?php
require_once __DIR__ . '/db.php';
$user_id = $_POST['user_id'] ?? null;

if ($user_id) {
    $stmt = $conn->prepare("UPDATE chat_conversations SET is_active = 0, admin_is_active = 0 WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("s", $user_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Chat closed', 'user_id' => $user_id]);
        } else {
            error_log("Failed to execute close chat: " . $stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Execute failed']);
        }
    } else {
        error_log("Failed to prepare close chat: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Missing user_id']);
}
?>
