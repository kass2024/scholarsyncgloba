<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once "config.php";
session_start();

/* ==========================================================
   PRODUCTION SETTINGS
========================================================== */
ini_set('display_errors', 0);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ==========================================================
   LOGGER
========================================================== */
function logEvent(string $message): void {
    file_put_contents(
        __DIR__ . "/broadcast.log",
        "[" . date("Y-m-d H:i:s") . "] " . $message . PHP_EOL,
        FILE_APPEND
    );
}

try {

    /* ======================================================
       REQUEST VALIDATION
    ======================================================= */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        throw new Exception("Invalid CSRF token");
    }

    if (empty($_POST['campaign_name']) || empty($_POST['dynamic_text'])) {
        throw new Exception("Campaign name and message required");
    }

    $campaignName = trim($_POST['campaign_name']);
    $dynamicText  = trim($_POST['dynamic_text']);
    $segment      = $_POST['segment'] ?? '';

    if (strlen($campaignName) > 150) {
        throw new Exception("Campaign name too long");
    }

    if (strlen($dynamicText) > 1000) {
        throw new Exception("Message too long");
    }

    /* ======================================================
       DATABASE CONNECTION
    ======================================================= */
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
    $conn->begin_transaction();

    /* ======================================================
       IMAGE VALIDATION
    ======================================================= */
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Image upload failed");
    }

    if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
        throw new Exception("Image exceeds 2MB limit");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($_FILES['image']['tmp_name']);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png'
    ];

    if (!isset($allowed[$mime])) {
        throw new Exception("Invalid image type");
    }

    $uploadDir = __DIR__ . "/uploads/";
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new Exception("Upload directory error");
    }

    $filename = bin2hex(random_bytes(16)) . "." . $allowed[$mime];
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
        throw new Exception("Failed to save image");
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? "https://"
        : "http://";

    $imageUrl = $protocol . $_SERVER['HTTP_HOST'] .
        "/whatsapp-bot/uploads/" . $filename;

    /* ======================================================
       INSERT CAMPAIGN
    ======================================================= */
    $templateName = defined('BROADCAST_TEMPLATE_NAME')
        ? BROADCAST_TEMPLATE_NAME
        : 'default_template';

    $stmt = $conn->prepare("
        INSERT INTO campaigns
        (campaign_name, template_name, image_url, dynamic_text, segment, status, total_recipients, created_at)
        VALUES (?, ?, ?, ?, ?, 'queued', 0, NOW())
    ");

    $stmt->bind_param(
        "sssss",
        $campaignName,
        $templateName,
        $imageUrl,
        $dynamicText,
        $segment
    );

    $stmt->execute();
    $campaignId = $stmt->insert_id;
    $stmt->close();

    /* ======================================================
       FETCH TARGET CONTACTS (SEGMENT FILTERED)
    ======================================================= */
    if ($segment !== '') {
        $contactStmt = $conn->prepare("
            SELECT phone FROM contacts
            WHERE opted_in = 1
            AND status = 'active'
            AND segment = ?
        ");
        $contactStmt->bind_param("s", $segment);
    } else {
        $contactStmt = $conn->prepare("
            SELECT phone FROM contacts
            WHERE opted_in = 1
            AND status = 'active'
        ");
    }

    $contactStmt->execute();
    $contacts = $contactStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $contactStmt->close();

    if (empty($contacts)) {
        throw new Exception("No contacts found for selected segment");
    }

    /* ======================================================
       BULK INSERT INTO QUEUE
    ======================================================= */
    $values = [];
    $params = [];
    $types  = "";

    foreach ($contacts as $row) {

        $phone = preg_replace('/[^0-9]/', '', $row['phone']);

        if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
            continue;
        }

        $values[] = "(?, ?, 'pending', 0, NOW())";
        $types   .= "is";
        $params[] = $campaignId;
        $params[] = $phone;
    }

    if (empty($values)) {
        throw new Exception("No valid phone numbers found");
    }

    $sql = "
        INSERT INTO campaign_queue
        (campaign_id, phone, status, attempts, created_at)
        VALUES " . implode(",", $values);

    $queueStmt = $conn->prepare($sql);
    $queueStmt->bind_param($types, ...$params);
    $queueStmt->execute();
    $queueStmt->close();

    $totalRecipients = count($values);

    /* ======================================================
       UPDATE TOTAL COUNT
    ======================================================= */
    $update = $conn->prepare("
        UPDATE campaigns
        SET total_recipients = ?
        WHERE id = ?
    ");
    $update->bind_param("ii", $totalRecipients, $campaignId);
    $update->execute();
    $update->close();

    $conn->commit();

    logEvent("Campaign {$campaignId} created ({$segment}) with {$totalRecipients} recipients");

    /* ======================================================
       TRIGGER WORKER (ASYNC)
    ======================================================= */
    $workerPath = __DIR__ . "/worker.php";

    if (file_exists($workerPath)) {

        $phpPath = '/usr/local/bin/php';

        exec(
            $phpPath . " " .
            escapeshellarg($workerPath) .
            " > /dev/null 2>&1 &"
        );

        logEvent("Worker triggered for campaign {$campaignId}");
    }

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "campaign_id" => $campaignId,
        "total_recipients" => $totalRecipients
    ]);

} catch (Throwable $e) {

    if (isset($conn)) {
        $conn->rollback();
    }

    logEvent("ERROR: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Unable to create campaign"
    ]);
}