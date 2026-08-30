<?php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['admin_id'])) {
    die("Access denied.");
}

// Load threads: show all active conversations, newest first
$sql = "
    SELECT c.user_id, 
           MAX(m.created_at) AS last_time,
           SUM(CASE WHEN m.is_read_by_admin = 0 THEN 1 ELSE 0 END) AS unread_count
    FROM chat_conversations c
    INNER JOIN chat_messages m ON c.user_id = m.user_id
    WHERE c.is_active = 1
    GROUP BY c.user_id
    ORDER BY last_time DESC
";

$res = mysqli_query($conn, $sql);

if (!$res) {
    echo "<p style='color:red;'>Error loading threads!</p>";
    exit;
}

echo '<h5 class="mb-3">Chat Threads</h5>';

if (mysqli_num_rows($res) === 0) {
    echo "<p>No active chats.</p>";
} else {
    while ($row = mysqli_fetch_assoc($res)) {
        $userId   = htmlspecialchars($row['user_id']);
        $lastTime = date("Y-m-d H:i:s", strtotime($row['last_time']));
        $unread   = (int)$row['unread_count'];

        echo "<div class='chat-thread mb-2'>
            <a href='admin_chat.php?user_id={$userId}' 
               class='d-block px-2 py-2 rounded bg-white border text-decoration-none'
               data-user-id='{$userId}' data-last-id='0'>";

        echo "<div class='user-id'>{$userId}</div>";

        if ($unread > 0) {
            echo "<div class='badge bg-danger ms-2'>{$unread}</div>";
        }

        echo "<div><small class='text-muted'>{$lastTime}</small></div>";

        echo "</a>
        </div>";
    }
}
?>
