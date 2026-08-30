<?php

require_once "config.php";

/* =========================================================
   ENTERPRISE OPENAI CLIENT – OPTIMIZED VERSION
   Fast | Cached | Secure | Scalable | Production Ready
========================================================= */

if (isset($conn) && $conn instanceof mysqli) {
    $conn->set_charset("utf8mb4");
}

/* =========================================================
   MAIN AI FUNCTION
========================================================= */
function getAIResponse($message, $userPhone = null)
{
    if (!$message || trim($message) === '') {
        return "Please send your question clearly so I can assist you.";
    }

    if (!defined('OPENAI_KEY') || empty(OPENAI_KEY)) {
        logOpenAIError("MISSING_API_KEY", "OPENAI_KEY not defined", $userPhone);
        return "⚠️ System configuration incomplete.";
    }

    $message = trim($message);

    /* =====================================================
       LOAD BASE SYSTEM PROMPT FROM FILE
    ===================================================== */
    $basePromptFile = __DIR__ . "/system_prompt.txt";

    if (!file_exists($basePromptFile)) {
        return "⚠️ AI configuration error (missing system prompt).";
    }

    $basePrompt = file_get_contents($basePromptFile);

    if ($basePrompt === false) {
        return "⚠️ AI configuration read error.";
    }

    /* =====================================================
       LOAD CACHED DATABASE CONTEXT (FAST)
    ===================================================== */
    $universitiesList = getCachedUniversityContext();
    $programsList     = getCachedProgramsContext();

    /* =====================================================
       FINAL SYSTEM PROMPT BUILD
    ===================================================== */
    $systemPrompt = $basePrompt . "\n\n" .
        "==================================================\n" .
        "OFFICIAL PARTNER UNIVERSITIES (VERIFIED DATABASE)\n" .
        "==================================================\n" .
        $universitiesList . "\n\n" .
        "==================================================\n" .
        "VERIFIED PROGRAMS & LEVELS\n" .
        "==================================================\n" .
        $programsList;

    /* =====================================================
       OPENAI REQUEST PAYLOAD
    ===================================================== */
    $payload = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user", "content" => $message]
        ],
        "temperature" => 0.2,
        "max_tokens" => 350
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . OPENAI_KEY,
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        logOpenAIError("CURL_ERROR", curl_error($ch), $userPhone);
        curl_close($ch);
        return "⚠️ AI system temporarily busy. Please try again shortly.";
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        logOpenAIError("HTTP_ERROR_" . $httpCode, $response, $userPhone);

        if ($httpCode == 401) {
            return "⚠️ Authentication error. Please contact administrator.";
        }

        if ($httpCode == 429) {
            return "⚠️ High traffic. Please try again shortly.";
        }

        return "⚠️ AI system temporarily unavailable.";
    }

    $result = json_decode($response, true);

    if (!isset($result['choices'][0]['message']['content'])) {
        logOpenAIError("INVALID_RESPONSE_STRUCTURE", $response, $userPhone);
        return "⚠️ AI response processing error.";
    }

    return trim($result['choices'][0]['message']['content']);
}

/* =========================================================
   CACHE WRAPPERS (1 HOUR CACHE)
========================================================= */

function getCachedUniversityContext()
{
    $cacheFile = __DIR__ . "/cache_universities.txt";

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        return file_get_contents($cacheFile);
    }

    $data = buildUniversityContext();
    file_put_contents($cacheFile, $data);

    return $data;
}

function getCachedProgramsContext()
{
    $cacheFile = __DIR__ . "/cache_programs.txt";

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        return file_get_contents($cacheFile);
    }

    $data = buildProgramsContext();
    file_put_contents($cacheFile, $data);

    return $data;
}

/* =========================================================
   DATABASE BUILDERS (OPTIMIZED LIMITS)
========================================================= */

function buildUniversityContext()
{
    global $conn;

    $sql = "
        SELECT u.name AS university, r.name AS region
        FROM universities u
        LEFT JOIN regions r ON u.region_id = r.id
        ORDER BY r.name, u.name
        LIMIT 200
    ";

    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        return "University list currently updating.";
    }

    $output = "";

    while ($row = $result->fetch_assoc()) {
        $region = strtoupper(trim($row['region'] ?? "OTHER"));
        $university = trim($row['university']);
        $output .= "$region - $university\n";
    }

    return $output;
}

function buildProgramsContext()
{
    global $conn;

    $sql = "
        SELECT 
            u.name AS university,
            pl.name AS level_name,
            p.program_name
        FROM programs p
        LEFT JOIN universities u ON p.university_id = u.id
        LEFT JOIN program_levels pl ON pl.id = p.program_level_id
        WHERE p.is_active = 1
        ORDER BY u.name
        LIMIT 800
    ";

    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        return "Programs currently updating.";
    }

    $output = "";

    while ($row = $result->fetch_assoc()) {
        $output .= trim($row['university']) . " | "
            . trim($row['level_name']) . " | "
            . trim($row['program_name']) . "\n";
    }

    return $output;
}

/* =========================================================
   ERROR LOGGER
========================================================= */

function logOpenAIError($type, $message, $phone = null)
{
    $logFile = __DIR__ . "/openai_error_log.txt";
    $time = date("Y-m-d H:i:s");

    $entry = "[$time] TYPE: $type";

    if ($phone) {
        $entry .= " | USER: $phone";
    }

    $entry .= " | MESSAGE: " . substr($message, 0, 1500) . PHP_EOL;

    file_put_contents($logFile, $entry, FILE_APPEND);
}