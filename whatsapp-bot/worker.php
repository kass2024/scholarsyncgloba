<?php
require_once "config.php";

/* ==========================================================
   PRODUCTION SETTINGS
========================================================== */
ini_set('display_errors', 0);
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ==========================================================
   DATABASE CONNECTION
========================================================== */
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset("utf8mb4");

/* ==========================================================
   SAFE DEFAULT CONFIG
========================================================== */
$BATCH_LIMIT = defined('MAX_BATCH_SEND') ? (int)MAX_BATCH_SEND : 5;
$SEND_DELAY  = defined('SEND_DELAY_SECONDS') ? (int)SEND_DELAY_SECONDS : 1;
$MAX_ATTEMPTS = 3;

/* ==========================================================
   SIMPLE LOGGER
========================================================== */
function workerLog($message)
{
    $file = __DIR__ . "/worker.log";
    file_put_contents(
        $file,
        "[" . date("Y-m-d H:i:s") . "] " . $message . PHP_EOL,
        FILE_APPEND
    );
}

/* ==========================================================
   LOCK SYSTEM (CRON SAFE)
========================================================== */
$lockPath = __DIR__ . "/worker.lock";
$lockFile = fopen($lockPath, "c");

if (!$lockFile) {
    workerLog("Cannot create lock file");
    exit;
}

if (!flock($lockFile, LOCK_EX | LOCK_NB)) {
    // Another worker is running
    exit;
}

workerLog("Worker started");

/* ==========================================================
   FETCH PENDING MESSAGES
========================================================== */
$stmt = $conn->prepare("
    SELECT cq.id, cq.phone, cq.campaign_id, cq.attempts,
           c.image_url, c.dynamic_text
    FROM campaign_queue cq
    JOIN campaigns c ON cq.campaign_id = c.id
    WHERE cq.status = 'pending'
    AND cq.attempts < ?
    ORDER BY cq.id ASC
    LIMIT ?
");

$stmt->bind_param("ii", $MAX_ATTEMPTS, $BATCH_LIMIT);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    workerLog("No pending messages");
    flock($lockFile, LOCK_UN);
    fclose($lockFile);
    exit;
}

/* ==========================================================
   PROCESS EACH MESSAGE
========================================================== */
while ($row = $result->fetch_assoc()) {

    $queueId     = (int)$row['id'];
    $phone       = preg_replace('/[^0-9]/', '', $row['phone']);
    $campaignId  = (int)$row['campaign_id'];
    $imageUrl    = $row['image_url'];
    $dynamicText = $row['dynamic_text'];
    $attempts    = (int)$row['attempts'];

    if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        workerLog("Invalid phone format: $phone");
        continue;
    }

    workerLog("Sending to $phone");

    /* ======================================================
       BUILD WHATSAPP PAYLOAD (CLOUD API TEMPLATE)
    ======================================================= */
    $payload = [
        "messaging_product" => "whatsapp",
        "to" => $phone,
        "type" => "template",
        "template" => [
            "name" => BROADCAST_TEMPLATE_NAME,
            "language" => ["code" => "en"],
            "components" => [
                [
                    "type" => "header",
                    "parameters" => [[
                        "type" => "image",
                        "image" => ["link" => $imageUrl]
                    ]]
                ],
                [
                    "type" => "body",
                    "parameters" => [[
                        "type" => "text",
                        "text" => $dynamicText
                    ]]
                ]
            ]
        ]
    ];

    /* ======================================================
       SEND REQUEST
    ======================================================= */
    $ch = curl_init(WHATSAPP_BASE_URL);

    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . WHATSAPP_TOKEN,
            "Content-Type: application/json"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    $resultApi = json_decode($response, true);

    /* ======================================================
       SUCCESS
    ======================================================= */
    if ($httpCode >= 200 && $httpCode < 300 && isset($resultApi['messages'][0]['id'])) {

        $messageId = $resultApi['messages'][0]['id'];

        $update = $conn->prepare("
            UPDATE campaign_queue
            SET status='sent',
                message_id=?,
                updated_at=NOW()
            WHERE id=?
        ");
        $update->bind_param("si", $messageId, $queueId);
        $update->execute();
        $update->close();

        $updateCampaign = $conn->prepare("
            UPDATE campaigns
            SET sent_count = sent_count + 1,
                status='sending'
            WHERE id=?
        ");
        $updateCampaign->bind_param("i", $campaignId);
        $updateCampaign->execute();
        $updateCampaign->close();

        workerLog("Message sent successfully to $phone");

    }
    /* ======================================================
       FAILURE
    ======================================================= */
    else {

        $errorMessage = $response ?: $curlErr ?: "Unknown error";

        $fail = $conn->prepare("
            UPDATE campaign_queue
            SET attempts = attempts + 1,
                status = IF(attempts + 1 >= ?, 'failed', 'pending'),
                error_message=?,
                updated_at=NOW()
            WHERE id=?
        ");
        $fail->bind_param("isi", $MAX_ATTEMPTS, $errorMessage, $queueId);
        $fail->execute();
        $fail->close();

        $updateCampaign = $conn->prepare("
            UPDATE campaigns
            SET failed_count = failed_count + 1
            WHERE id=?
        ");
        $updateCampaign->bind_param("i", $campaignId);
        $updateCampaign->execute();
        $updateCampaign->close();

        workerLog("Failed sending to $phone: $errorMessage");
    }

    sleep($SEND_DELAY);
}

/* ==========================================================
   AUTO COMPLETE CAMPAIGNS
========================================================== */
$conn->query("
    UPDATE campaigns c
    SET status='completed'
    WHERE status='sending'
    AND NOT EXISTS (
        SELECT 1 FROM campaign_queue q
        WHERE q.campaign_id = c.id
        AND q.status = 'pending'
    )
");

workerLog("Worker finished");

/* ==========================================================
   RELEASE LOCK
========================================================== */
flock($lockFile, LOCK_UN);
fclose($lockFile);