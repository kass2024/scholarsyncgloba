<?php
/**
 * Legacy chat endpoint — prefer chat-api.php for production.
 */
ob_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/configi-ai-xander.php';
require_once __DIR__ . '/ai_knowledge.php';
require_once __DIR__ . '/includes/knowledge_chat_lib.php';

$data = json_decode(file_get_contents('php://input'), true);

$sessionId = $data['session'] ?? '';
$message   = trim($data['message'] ?? '');

if (!$sessionId || $message === '') {
    echo json_encode(['reply' => 'Invalid request']);
    exit;
}

/* ===== ENSURE SESSION EXISTS ===== */
$stmt = $conn->prepare('INSERT IGNORE INTO chat_sessions (session_id) VALUES (?)');
$stmt->bind_param('s', $sessionId);
$stmt->execute();
$stmt->close();

/* ===== STORE USER MESSAGE ===== */
$stmt = $conn->prepare('INSERT INTO chat_messages (session_id, sender, message) VALUES (?, \'user\', ?)');
$stmt->bind_param('ss', $sessionId, $message);
$stmt->execute();
$stmt->close();

/* ===== CHECK MODE ===== */
$stmt = $conn->prepare('SELECT mode FROM chat_sessions WHERE session_id = ?');
$stmt->bind_param('s', $sessionId);
$stmt->execute();
$stmt->bind_result($mode);
$stmt->fetch();
$stmt->close();

/* ===== HUMAN MODE ===== */
if ($mode === 'human') {
    echo json_encode([
        'reply' => 'A live advisor is reviewing your message and will respond shortly.',
    ]);
    exit;
}

/* ===== FAQ FIRST ===== */
$faqAnswer = null;
if (!pcvc_user_requests_human_advisor($message)) {
    try {
        $faqAnswer = pcvc_find_faq_answer($conn, $message, 1);
    } catch (Throwable $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE && defined('LOG_ERRORS') && LOG_ERRORS) {
            error_log('[chat-router] FAQ: ' . $e->getMessage());
        }
    }
}

if ($faqAnswer !== null && $faqAnswer !== '') {
    $stmt = $conn->prepare('INSERT INTO chat_messages (session_id, sender, message) VALUES (?, \'ai\', ?)');
    $stmt->bind_param('ss', $sessionId, $faqAnswer);
    $stmt->execute();
    $stmt->close();
    $ust = $conn->prepare('UPDATE chat_sessions SET updated_at = NOW() WHERE session_id = ?');
    $ust->bind_param('s', $sessionId);
    $ust->execute();
    $ust->close();
    ob_clean();
    echo json_encode([
        'reply' => nl2br(htmlspecialchars($faqAnswer, ENT_QUOTES, 'UTF-8')),
        'source' => 'faq',
    ]);
    exit;
}

if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === '') {
    ob_clean();
    echo json_encode(['reply' => 'The assistant is not configured (missing OPENAI_API_KEY). Add it to your .env file.']);
    exit;
}

/* ===== AI MODE ===== */
$contactPrompt = function_exists('pcvc_support_contact_prompt_block') ? pcvc_support_contact_prompt_block() : '';
$systemPrompt = "You are MISA, the AI assistant of ScholarSync Global (XGS).\n\n"
    . "No curated FAQ matched this message — answer directly. Be concise and on-topic: address the user’s intent first "
    . "(e.g. if they ask for a human advisor, give OFFICIAL CONTACT details below verbatim; do not invent numbers; do not paste unrelated checklists). "
    . "Use the knowledge below as ground truth; do not invent universities, fees, or guarantees; never promise visa approval.\n\n"
    . $XGS_KNOWLEDGE
    . $contactPrompt;

$model = defined('AI_MODEL') ? AI_MODEL : 'gpt-4o-mini';
$temp = defined('AI_TEMPERATURE') ? AI_TEMPERATURE : 0.6;
$maxTok = defined('AI_MAX_TOKENS') ? AI_MAX_TOKENS : 300;
$timeout = defined('AI_TIMEOUT') ? (int) AI_TIMEOUT : 30;

$payload = [
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $message],
    ],
    'temperature' => (float) $temp,
    'max_tokens' => (int) $maxTok,
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => $timeout,
]);

$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resp = is_string($response) ? json_decode($response, true) : null;

if ($response === false || $curlErr !== '') {
    $reply = 'We could not reach the assistant. Please try again in a moment.';
} elseif (!is_array($resp) || $httpCode < 200 || $httpCode >= 300) {
    $reply = 'The assistant is temporarily unavailable. Please try again.';
} elseif (!isset($resp['choices'][0]['message']['content'])) {
    $reply = 'Unable to respond now. Please try again.';
} else {
    $reply = (string) $resp['choices'][0]['message']['content'];
}

/* ===== STORE AI MESSAGE ===== */
$stmt = $conn->prepare('INSERT INTO chat_messages (session_id, sender, message) VALUES (?, \'ai\', ?)');
$stmt->bind_param('ss', $sessionId, $reply);
$stmt->execute();
$stmt->close();

$ust = $conn->prepare('UPDATE chat_sessions SET updated_at = NOW() WHERE session_id = ?');
$ust->bind_param('s', $sessionId);
$ust->execute();
$ust->close();

ob_clean();
echo json_encode([
    'reply' => nl2br(htmlspecialchars($reply, ENT_QUOTES, 'UTF-8')),
    'source' => 'openai',
]);
exit;
