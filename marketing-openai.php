<?php
/**
 * FINAL CLEAN & ERROR-PROOF AI HELPER
 * Provides: ai_marketing_analyze_short($filesArray)
 */

require_once __DIR__ . '/helpers/env_bootstrap.php';
$OPENAI_KEY = pcvc_env('OPENAI_API_KEY');


function ai_marketing_analyze_short($filesArray)
{
    global $OPENAI_KEY;

    if (!is_array($filesArray) || count($filesArray) === 0) {
        return "No files available for analysis.";
    }

    $fileList = implode(", ", $filesArray);

    $prompt = "
Analyze these marketing filenames:

$fileList

Return EXACTLY:
1. Total files
2. Number of images
3. Number of videos
4. One short insight (max 10 words)

Keep the output clean, no decorations, no extra lines.
";

    // ---------------------------------------------------
    // API REQUEST
    // ---------------------------------------------------
    $payload = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "You generate short, clean analytics summaries."],
            ["role" => "user",   "content" => $prompt]
        ],
        "temperature" => 0.2
    ];

    $headers = [
        "Content-Type: application/json",
        "Authorization: " . "Bearer " . $OPENAI_KEY
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    // ---------------------------------------------------
    // HANDLE CURL FAILURE
    // ---------------------------------------------------
    if ($curlErr) {
        return "AI Error: " . $curlErr;
    }

    // ---------------------------------------------------
    // VALIDATE JSON RESPONSE
    // ---------------------------------------------------
    $json = json_decode($response, true);

    if (!$json) {
        return "AI Error: Invalid JSON response.";
    }

    // ---------------------------------------------------
    // HANDLE KNOWN OPENAI ERROR FORMAT
    // ---------------------------------------------------
    if (isset($json["error"])) {
        return "AI Error: " . ($json["error"]["message"] ?? "Unknown API error.");
    }

    // ---------------------------------------------------
    // CHECK IF CHOICES EXIST
    // ---------------------------------------------------
    if (!isset($json["choices"][0]["message"]["content"])) {
        return "AI Error: Model returned an incomplete response.";
    }

    // ---------------------------------------------------
    // FINAL OUTPUT (ALWAYS SAFE)
    // ---------------------------------------------------
    $output = trim($json["choices"][0]["message"]["content"]);

    if ($output === "" || strlen($output) < 3) {
        return "AI Error: Empty or invalid response.";
    }

    return $output;
}
?>
