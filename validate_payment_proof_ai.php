<?php
/**
 * validate_payment_proof_ai.php
 * AI-powered payment proof validation using Gemini (document vision).
 */

declare(strict_types=1);
ob_start();
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'User not authenticated']);
    exit;
}

require_once __DIR__ . '/helpers/env_bootstrap.php';
require_once __DIR__ . '/helpers/document_vision_gemini.php';

$LOG_FILE = __DIR__ . '/upload_debug.log';
$TEMP_DIR = __DIR__ . '/temp/';
$UPLOAD_DIR = __DIR__ . '/uploads/';

foreach ([$TEMP_DIR, $UPLOAD_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

if (!pcvc_docvision_is_configured()) {
    echo json_encode(['success'=>false,'message'=>'Set GEMINI_API_KEY in .env for payment proof validation.']);
    exit;
}

if (empty($_POST['file_path'])) {
    echo json_encode(['success'=>false,'message'=>'File path is required']);
    exit;
}

if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']);
    exit;
}

$file_path = trim($_POST['file_path']);
$base_dir = __DIR__ . '/uploads/';
$full_path = realpath($base_dir . basename($file_path));

if (!$full_path || !file_exists($full_path) || strpos($full_path, realpath($base_dir)) !== 0) {
    echo json_encode(['success'=>false,'message'=>'Invalid file path']);
    exit;
}

$fileName = basename($full_path);
$mime = mime_content_type($full_path);
$isPdf = ($mime === 'application/pdf');
$isImage = in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'], true);

if (!$isPdf && !$isImage) {
    echo json_encode(['success'=>false,'message'=>'Invalid file type. Only PDF and images allowed']);
    exit;
}

$prompt = getPaymentProofPrompt();
$userContent = [['type' => 'input_text', 'text' => $prompt]];

if ($isImage) {
    $dataUrl = 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($full_path));
    $userContent[] = ['type' => 'input_image', 'image_url' => $dataUrl];
} else {
    $userContent[] = [
        'type' => 'input_pdf',
        'mime' => 'application/pdf',
        'data' => base64_encode((string)file_get_contents($full_path)),
    ];
}

$systemPrompt = 'You analyze payment proof documents. Return only valid JSON matching the requested schema.';

$result = pcvc_docvision_generate_json($systemPrompt, $userContent, 2, 500, 0.1);

if (isset($result['error'])) {
    echo json_encode(['success'=>false,'message'=>$result['error']['message'] ?? 'API error']);
    exit;
}

$output = $result['json'] ?? [];

if ($output === []) {
    echo json_encode(['success'=>false,'message'=>'Invalid JSON response from AI']);
    exit;
}

file_put_contents($LOG_FILE, "\nPayment Proof AI Validation: " . json_encode($output) . "\n", FILE_APPEND);

echo json_encode([
    'success' => true,
    'contains_payment_info' => $output['contains_payment_info'] ?? false,
    'confidence' => $output['confidence'] ?? 0,
    'details' => $output['details'] ?? [],
    'message' => $output['message'] ?? 'Validation completed',
    'detected_amount' => $output['detected_amount'] ?? null,
    'detected_transaction_id' => $output['detected_transaction_id'] ?? null,
]);

function getPaymentProofPrompt(): string
{
    return <<<'PROMPT'
Analyze this payment proof document and return JSON:
{
  "contains_payment_info": true or false,
  "confidence": 0.0-1.0,
  "message": "short explanation",
  "details": [],
  "detected_amount": null or number,
  "detected_transaction_id": null or string
}
PROMPT;
}
