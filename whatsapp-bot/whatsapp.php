<?php

require_once "config.php";

/* =========================================================
   WHATSAPP CLOUD API CLIENT – PRODUCTION VERSION
   Fast | Non-Blocking | Stable | Clean Architecture
========================================================= */

define('WHATSAPP_API_VERSION', 'v19.0');
define('WHATSAPP_MAX_LENGTH', 3900);

/* =========================================================
   MAIN SEND FUNCTION
========================================================= */

function sendWhatsApp($to, $message, $withProcessingNotice = false)
{
    if (!validateWhatsAppInput($to, $message)) {
        return false;
    }

    // Optional instant UX feedback (no blocking sleep)
    if ($withProcessingNotice) {
        sendSingleMessage($to, "⏳ Please wait while I prepare your response...");
    }

    $parts = splitLongMessage($message);

    foreach ($parts as $part) {

        $result = sendSingleMessage($to, $part);

        if (!$result) {
            return false;
        }

        // Tiny micro-delay to avoid rate limiting (non-blocking minimal)
        usleep(150000); // 0.15 sec
    }

    return true;
}

/* =========================================================
   SEND SINGLE TEXT MESSAGE
========================================================= */

function sendSingleMessage($to, $message)
{
    $payload = [
        "messaging_product" => "whatsapp",
        "to" => $to,
        "type" => "text",
        "text" => [
            "preview_url" => false,
            "body" => $message
        ]
    ];

    return sendRawRequest($payload, $to);
}

/* =========================================================
   CORE API REQUEST
========================================================= */

function sendRawRequest($payload, $phone = null)
{
    $url = "https://graph.facebook.com/" .
        WHATSAPP_API_VERSION . "/" .
        PHONE_NUMBER_ID . "/messages";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . WHATSAPP_TOKEN,
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        logWhatsAppError("CURL_ERROR", curl_error($ch), $phone);
        curl_close($ch);
        return false;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        logWhatsAppError("HTTP_ERROR_" . $httpCode, $response, $phone);
        return false;
    }

    $decoded = json_decode($response, true);

    if (!isset($decoded['messages'][0]['id'])) {
        logWhatsAppError("INVALID_RESPONSE", $response, $phone);
        return false;
    }

    return $decoded['messages'][0]['id'];
}

/* =========================================================
   VALIDATION
========================================================= */

function validateWhatsAppInput($to, $message)
{
    if (empty($to) || empty($message)) {
        return false;
    }

    // E.164 format basic numeric validation
    if (!preg_match('/^[0-9]{7,15}$/', $to)) {
        logWhatsAppError("INVALID_PHONE_FORMAT", $to);
        return false;
    }

    return true;
}

/* =========================================================
   SAFE MESSAGE SPLITTING
========================================================= */

function splitLongMessage($message)
{
    $message = trim($message);

    if (mb_strlen($message, 'UTF-8') <= WHATSAPP_MAX_LENGTH) {
        return [$message];
    }

    return str_split($message, WHATSAPP_MAX_LENGTH);
}

/* =========================================================
   ERROR LOGGER
========================================================= */

function logWhatsAppError($type, $data = '', $phone = null)
{
    $file = __DIR__ . "/whatsapp_error_log.txt";
    $time = date("Y-m-d H:i:s");

    $entry = "[$time] TYPE: $type";

    if ($phone) {
        $entry .= " | USER: $phone";
    }

    if (!empty($data)) {
        $entry .= " | DATA: " . substr($data, 0, 1000);
    }

    $entry .= PHP_EOL;

    file_put_contents($file, $entry, FILE_APPEND);
}