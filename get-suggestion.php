<?php
// get-suggestion.php
header('Content-Type: application/json');
require_once __DIR__ . '/helpers/env_bootstrap.php';
require_once 'db.php'; // Optional if you log suggestions, not required for OpenAI

// Ensure title and description are received
$title = trim($_POST['job_title'] ?? '');
$description = trim($_POST['job_description'] ?? '');

if (empty($title) || empty($description)) {
    echo json_encode([
        "error" => "Job title and description are required."
    ]);
    exit;
}

$api_key = pcvc_env('OPENAI_API_KEY');
if ($api_key === '') {
    echo json_encode(['error' => 'OpenAI API key not configured (set OPENAI_API_KEY in .env).']);
    exit;
}

// Prepare prompt for AI
$prompt = "Evaluate the productivity of the following job. Provide a productivity score out of 100 and suggest improvements if needed.\n\nTitle: $title\nDescription: $description\n\nRespond in JSON format like this:\n{\"score\": 85, \"suggestion\": \"Be more specific with task goals.\"}";

// Setup OpenAI API request
$data = [
    "model" => "gpt-3.5-turbo",
    "messages" => [
        ["role" => "user", "content" => $prompt]
    ],
    "temperature" => 0.7
];

$headers = [
    "Authorization: Bearer $api_key",
    "Content-Type: application/json"
];

$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    echo json_encode([
        "error" => "Failed to connect to AI. HTTP code: $http_code"
    ]);
    exit;
}

// Extract AI response
$json = json_decode($response, true);
$reply = $json['choices'][0]['message']['content'] ?? '{"score":0,"suggestion":"AI did not return response."}';

try {
    $insights = json_decode($reply, true);
    if (is_array($insights) && isset($insights['suggestion'])) {
        echo json_encode([
            "suggestion" => $insights['suggestion'],
            "score" => 0 // Score not used now, will be evaluated at check-out
        ]);
    } else {
        throw new Exception("Malformed JSON from AI.");
    }
} catch (Exception $e) {
    echo json_encode([
        "error" => "Error parsing AI response: " . $e->getMessage()
    ]);
}
?>
