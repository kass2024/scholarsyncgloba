<?php
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$userId = $_GET['user_id'] ?? '';
$lastId = (int)($_GET['last_id'] ?? 0);

if (!$userId) {
    echo json_encode(['messages' => []]);
    exit;
}

// Load admin names
$adminNames = [];
$admins_res = mysqli_query($conn, "SELECT username, last_name FROM admins");
while ($a = mysqli_fetch_assoc($admins_res)) {
    $adminNames[$a['username']] = $a['last_name'] ?: $a['username'];
}

// Find conversation_id for this user
$stmtConv = $conn->prepare("SELECT id FROM chat_conversations WHERE user_id = ? AND is_active = 1 LIMIT 1");
$stmtConv->bind_param("s", $userId);
$stmtConv->execute();
$stmtConv->bind_result($conversationId);
$stmtConv->fetch();
$stmtConv->close();

if (!$conversationId) {
    echo json_encode(['messages' => []]);
    exit;
}

// Query latest messages (after last_id)
$stmt = $conn->prepare("
    SELECT id, sender, message, created_at 
    FROM chat_messages 
    WHERE conversation_id = ? 
    AND user_id = ? 
    AND id > ? 
    ORDER BY created_at ASC 
    LIMIT 50
");

$stmt->bind_param("isi", $conversationId, $userId, $lastId);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];

while ($row = $result->fetch_assoc()) {
    $sender = htmlspecialchars($row['sender']);
    $message = nl2br(htmlspecialchars($row['message']));
    $time = date("H:i", strtotime($row['created_at']));

    // Determine sender class + display name
    if ($sender === 'ai') {
        $sender_class = '';
        $displaySender = 'AI Bot';
    } elseif ($sender === 'student') {
        $sender_class = 'user';
        $displaySender = 'Student';
    } elseif (isset($adminNames[$sender])) {
        $sender_class = '';
        $displaySender = 'Visa Consultant';
    } else {
        $sender_class = '';
        $displaySender = htmlspecialchars($sender);
    }

    // Build HTML for front-end chat window
    $html = "<div class='chat-message {$sender_class}' data-id='{$row['id']}'>
                <div><strong>{$displaySender}:</strong> {$message}</div>
                <div style='font-size:11px;color:#888;'>{$time}</div>
            </div>";

    $messages[] = [
        'id' => (int)$row['id'],
        'html' => $html
    ];
}

$stmt->close();

echo json_encode(['messages' => $messages]);
?>
