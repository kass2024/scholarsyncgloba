<?php
require_once __DIR__ . '/helpers/env_bootstrap.php';
require_once __DIR__ . '/helpers/document_vision_gemini.php';

if (!pcvc_docvision_is_configured()) {
    echo json_encode(["status" => "error", "message" => "Set GEMINI_API_KEY in .env for file validation."]);
    exit;
}

if (!isset($_FILES['file']) || !isset($_POST['expected_type'])) {
    echo json_encode(["status" => "error", "message" => "Missing file or expected type"]);
    exit;
}

$file = $_FILES['file'];
$tmpPath = $file['tmp_name'];
$expected = strtolower(trim($_POST['expected_type']));

$extractedText = '';
if (mime_content_type($tmpPath) === 'application/pdf') {
    $extractedText = shell_exec("pdftotext " . escapeshellarg($tmpPath) . " -");
} elseif (str_contains((string)mime_content_type($tmpPath), 'image')) {
    $extractedText = shell_exec("tesseract " . escapeshellarg($tmpPath) . " stdout");
}

$systemPrompt = 'You are a file validation assistant. Respond with JSON only: {"result":"valid"} or {"result":"invalid"}.';
$userPrompt = "Analyze this text and decide if it matches a {$expected}. Text:\n" . substr((string)$extractedText, 0, 1500);

$result = pcvc_docvision_generate_json($systemPrompt, [['type' => 'input_text', 'text' => $userPrompt]]);

if (isset($result['error'])) {
    echo json_encode(["status" => "error", "message" => $result['error']['message'] ?? 'Validation failed']);
    exit;
}

$parsed = $result['json'] ?? [];
$resultWord = strtolower(trim((string)($parsed['result'] ?? '')));

if ($resultWord === 'valid' || str_contains($resultWord, 'valid')) {
    echo json_encode(["status" => "ok", "message" => "File validated successfully"]);
} else {
    echo json_encode(["status" => "reject", "message" => "File content does not match expected type"]);
}
