<?php
require_once __DIR__ . '/db.php';
$threads_sql = "
    SELECT c.user_id, 
           MAX(m.created_at) AS last_time, 
           SUM(CASE WHEN m.is_read_by_admin = 0 THEN 1 ELSE 0 END) AS unread_count
    FROM chat_conversations c
    INNER JOIN chat_messages m ON c.user_id = m.user_id
    WHERE c.is_active = 1
    GROUP BY c.user_id
    ORDER BY last_time DESC
";

$threads_res = mysqli_query($conn, $threads_sql);
$threads = mysqli_fetch_all($threads_res, MYSQLI_ASSOC);

$html = '<h5 class="mb-3">Chat Threads</h5>';

if (empty($threads)) {
    $html .= '<p>No active chats.</p>';
} else {
    foreach ($threads as $thread) {
        $user_id = htmlspecialchars($thread['user_id']);
        $last_time = htmlspecialchars($thread['last_time']);
        $unread_count = (int)$thread['unread_count'];

        $badge = '';
        if ($unread_count > 0) {
            $badge = '<span class="badge-unread ms-2">' . $unread_count . '</span>';
        }

        $html .= "
        <div class='chat-thread'>
            <a href='admin_chat.php?user_id={$user_id}'>
                {$user_id}<br>
                <small class='text-muted'>{$last_time}</small>
                {$badge}
            </a>
        </div>";
    }
}

echo $html;
