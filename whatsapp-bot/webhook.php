<?php

/* =========================================================
   PRODUCTION WHATSAPP WEBHOOK
   Fast | Secure | Stable | Enterprise Ready
========================================================= */

$verify_token = "myverify123";

/* =========================================================
   1️⃣ META WEBHOOK VERIFICATION (GET)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (
        isset($_GET['hub_verify_token']) &&
        $_GET['hub_verify_token'] === $verify_token
    ) {
        echo $_GET['hub_challenge'];
        exit;
    }

    http_response_code(403);
    exit;
}

/* =========================================================
   2️⃣ INSTANT 200 RESPONSE (CRITICAL FOR META)
========================================================= */
http_response_code(200);
ignore_user_abort(true);

ob_start();
echo "OK";
header("Connection: close");
header("Content-Length: " . ob_get_length());
ob_end_flush();
flush();

/* =========================================================
   3️⃣ LOAD DEPENDENCIES
========================================================= */
require_once "config.php";
require_once "database.php";
require_once "session.php";
require_once "openai.php";
require_once "whatsapp.php";

/* =========================================================
   4️⃣ SAFE INPUT PARSING
========================================================= */
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!$data || !isset($data['entry'][0]['changes'][0]['value'])) {
    exit;
}

$value = $data['entry'][0]['changes'][0]['value'];

/* =========================================================
   5️⃣ HANDLE DELIVERY STATUS (BROADCAST TRACKING)
========================================================= */
if (!empty($value['statuses'])) {

    foreach ($value['statuses'] as $statusData) {

        $messageId  = $statusData['id'] ?? null;
        $statusType = $statusData['status'] ?? null;

        if (!$messageId || !$statusType) continue;

        try {

            $stmt = $conn->prepare("
                SELECT campaign_id 
                FROM campaign_queue 
                WHERE message_id = ?
                LIMIT 1
            ");
            $stmt->bind_param("s", $messageId);
            $stmt->execute();
            $result = $stmt->get_result();
            $queue  = $result->fetch_assoc();
            $stmt->close();

            if (!$queue) continue;

            $campaignId = (int)$queue['campaign_id'];

            // Update queue
            $stmt = $conn->prepare("
                UPDATE campaign_queue
                SET status = ?, updated_at = NOW()
                WHERE message_id = ?
            ");
            $stmt->bind_param("ss", $statusType, $messageId);
            $stmt->execute();
            $stmt->close();

            // Update campaign counters safely
            if ($statusType === "delivered") {
                $stmt = $conn->prepare("UPDATE campaigns SET delivered_count = delivered_count + 1 WHERE id = ?");
            } elseif ($statusType === "read") {
                $stmt = $conn->prepare("UPDATE campaigns SET read_count = read_count + 1 WHERE id = ?");
            } elseif ($statusType === "failed") {
                $stmt = $conn->prepare("UPDATE campaigns SET failed_count = failed_count + 1 WHERE id = ?");
            } else {
                continue;
            }

            $stmt->bind_param("i", $campaignId);
            $stmt->execute();
            $stmt->close();

        } catch (Exception $e) {
            logWebhookError("DELIVERY_STATUS_ERROR", $e->getMessage());
        }
    }

    exit;
}

/* =========================================================
   6️⃣ HANDLE INCOMING USER MESSAGE
========================================================= */
if (empty($value['messages'][0])) {
    exit;
}

$msg = $value['messages'][0];
$phone = $msg['from'] ?? null;

if (!$phone) exit;

/* =========================================================
   EXTRACT MESSAGE CONTENT
========================================================= */
$message = "";

if (!empty($msg['text']['body'])) {
    $message = trim($msg['text']['body']);
}

if (!empty($msg['button']['text'])) {
    $message = trim($msg['button']['text']);
}

if ($message === "") {
    exit;
}

/* =========================================================
   AUTO UNSUBSCRIBE HANDLING
========================================================= */
$unsubscribeKeywords = ["stop", "unsubscribe", "cancel", "optout"];

if (in_array(strtolower($message), $unsubscribeKeywords)) {

    try {
        $stmt = $conn->prepare("
            UPDATE contacts 
            SET status='unsubscribed', opted_in=0 
            WHERE phone=?
        ");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->close();

        sendWhatsApp($phone, "You have been unsubscribed from marketing messages.");
    } catch (Exception $e) {
        logWebhookError("UNSUBSCRIBE_ERROR", $e->getMessage());
    }

    exit;
}

/* =========================================================
   PROCESS CHATBOT
========================================================= */
try {

    updateSession($phone);

    if (isSessionOpen($phone)) {
        $reply = getAIResponse($message, $phone);
    } else {
        $reply = "Hello 👋 Welcome to Visa Consultant Canada.\n\nPlease send any message to start conversation.";
    }

    /* Save conversation */
    $stmt = $conn->prepare("
        INSERT INTO conversations 
        (phone, message, response, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("sss", $phone, $message, $reply);
    $stmt->execute();
    $stmt->close();

    /* Send reply (includes simulated typing inside) */
    sendWhatsApp($phone, $reply);

} catch (Exception $e) {

    logWebhookError("CHATBOT_ERROR", $e->getMessage());

    sendWhatsApp($phone, "⚠️ System temporarily unavailable. Please try again shortly.");
}

exit;


/* =========================================================
   ERROR LOGGER
========================================================= */
function logWebhookError($type, $message)
{
    $file = __DIR__ . "/webhook_error_log.txt";
    $time = date("Y-m-d H:i:s");

    $entry = "[$time] TYPE: $type | MESSAGE: " . substr($message, 0, 1000) . PHP_EOL;

    file_put_contents($file, $entry, FILE_APPEND);
}