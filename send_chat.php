<?php
session_start();
require_once __DIR__ . '/helpers/env_bootstrap.php';
require_once __DIR__ . '/db.php';
$apiKey = pcvc_env('OPENAI_API_KEY');

// Receive
$userId = $_POST['user_id'] ?? '';
$message = trim($_POST['message'] ?? '');
$sender = trim($_POST['sender'] ?? 'student');

if ($userId && $message) {

    // Find or create conversation
    $conversationId = null;

    $stmt = $conn->prepare("SELECT id FROM chat_conversations WHERE user_id = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $stmt->bind_result($conversationId);

    if ($stmt->fetch()) {
        // conversation found
    } else {
        $stmt->close();

        $stmtNew = $conn->prepare("INSERT INTO chat_conversations (user_id) VALUES (?)");
        $stmtNew->bind_param("s", $userId);

        if ($stmtNew->execute()) {
            $conversationId = $stmtNew->insert_id;
        } else {
            error_log("Error creating conversation: " . $stmtNew->error);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create conversation']);
            exit;
        }

        $stmtNew->close();
    }

    $stmt->close();

    if ($conversationId === null) {
        echo json_encode(['status' => 'error', 'message' => 'Conversation ID missing']);
        exit;
    }

    // Determine if this is from ADMIN
    $isAdmin = false;
    if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0) {
        $isAdmin = true;
    }

    // Insert message
    $stmtMsg = $conn->prepare("
        INSERT INTO chat_messages (conversation_id, user_id, sender, message, is_forwarded_to_admin, is_read_by_admin, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $forwardToAdmin = 1;
    $readByAdmin = 0;

    if ($isAdmin) {
        $forwardToAdmin = 0;
        $readByAdmin = 1;
    }

    $stmtMsg->bind_param("isssii", 
        $conversationId, 
        $userId, 
        $sender, 
        $message, 
        $forwardToAdmin, 
        $readByAdmin
    );

    $stmtMsg->execute();
    $msg_id = $stmtMsg->insert_id;
    $stmtMsg->close();

    // ✅ Send FCM to ALL admin tokens
    $result = mysqli_query($conn, "SELECT token FROM admin_fcm_tokens");

    while ($row = mysqli_fetch_assoc($result)) {
        $deviceToken = $row['token'];

        $postData = http_build_query([
            'device_token' => $deviceToken,
            'user_id' => $userId
        ]);

        $opts = ['http' =>
            [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postData
            ]
        ];

        $context  = stream_context_create($opts);
        $fcmResult = file_get_contents('https://scholarsyncglobal.ca/send_fcm_v1.php', false, $context);

        // Log per token
        error_log("FCM result to token {$deviceToken}: {$fcmResult}");
    }

    // AI Reply if student
    if (!$isAdmin && strtolower($sender) === 'student') {

        // Check if admin is active
        $admin_is_active = 0;
        $stmtActive = $conn->prepare("SELECT admin_is_active FROM chat_conversations WHERE id = ?");
        $stmtActive->bind_param("i", $conversationId);
        $stmtActive->execute();
        $stmtActive->bind_result($admin_is_active);
        $stmtActive->fetch();
        $stmtActive->close();

        if ($admin_is_active == 1) {
            // Skip AI reply
            echo json_encode([
                'status' => 'success',
                'ai_skipped' => true,
                'msg_id' => $msg_id,
                'sender' => $sender
            ]);
        } else {
            // Call AI
            $data = [
                "model" => "gpt-3.5-turbo",
                "messages" => [
                    ["role" => "system", "content" => "You are a helpful student support assistant for ScholarSync Global. Never ask about payments. If you do not know the answer, reply politely: 'I will notify a human to help you.'"],
                    ["role" => "user", "content" => $message]
                ],
                "max_tokens" => 150
            ];

            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

            $response = curl_exec($ch);
            $api_error = '';

            if (curl_errno($ch)) {
                $ai_reply = "Sorry, I could not process your request right now. I will notify a human to help you.";
                $api_error = "Curl error: " . curl_error($ch);
                error_log($api_error);
            } else {
                $result = json_decode($response, true);
                if (isset($result['choices'][0]['message']['content'])) {
                    $ai_reply = $result['choices'][0]['message']['content'];
                } else {
                    $ai_reply = "Sorry, I did not understand. I will notify a human to help you.";
                    $api_error = "OpenAI error parsing: " . $response;
                    error_log($api_error);
                }
            }

            curl_close($ch);

            // Insert AI reply
            $stmtAI = $conn->prepare("
                INSERT INTO chat_messages (conversation_id, user_id, sender, message, is_forwarded_to_admin, is_read_by_admin, created_at)
                VALUES (?, ?, 'ai', ?, 0, 1, NOW())
            ");

            if ($stmtAI) {
                $stmtAI->bind_param("iss", $conversationId, $userId, $ai_reply);
                $stmtAI->execute();
                $ai_msg_id = $stmtAI->insert_id;
                $stmtAI->close();
            } else {
                error_log("Prepare failed for AI insert: " . $conn->error);
                $ai_msg_id = 0;
            }

            $needs_human = (stripos($ai_reply, 'notify a human') !== false);

            echo json_encode([
                'status' => 'success',
                'ai_reply' => $ai_reply,
                'ai_msg_id' => $ai_msg_id,
                'needs_human' => $needs_human,
                'api_error' => $api_error,
                'msg_id' => $msg_id,
                'sender' => $sender
            ]);
        }

    } else {
        // Admin message — no AI reply
        echo json_encode([
            'status' => 'success',
            'msg_id' => $msg_id,
            'sender' => $sender
        ]);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
}
?>
