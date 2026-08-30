<?php
require_once __DIR__ . '/db.php';
// Count unread messages where is_read_by_admin = 0 and is_active = 1
$res = mysqli_query($conn, "
    SELECT COUNT(*) AS unread_count 
    FROM chat_messages 
    INNER JOIN chat_conversations ON chat_messages.user_id = chat_conversations.user_id
    WHERE chat_messages.is_read_by_admin = 0 
    AND chat_conversations.is_active = 1
");

$row = mysqli_fetch_assoc($res);

$count = (int)($row['unread_count'] ?? 0);

header('Content-Type: application/json');
echo json_encode(['unread' => $count]);
?>
