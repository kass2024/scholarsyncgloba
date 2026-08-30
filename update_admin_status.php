<?php
require_once __DIR__ . '/db.php';
$user_id = $_POST['user_id'] ?? null;
$active = isset($_POST['active']) ? (int)$_POST['active'] : 0;

if ($user_id) {
    $stmt = $conn->prepare("UPDATE chat_conversations SET admin_is_active = ? WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("is", $active, $user_id);
        $stmt->execute();

        echo json_encode([
            'status' => 'success',
            'user_id' => $user_id,
            'new_active' => $active
        ]);
    } else {
        error_log("Failed to prepare update_admin_status: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Missing user_id']);
}
?>
