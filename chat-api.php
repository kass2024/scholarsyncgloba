<?php
/**
 * CHAT ROUTER – SCHOLARSYNC GLOBAL (AI ↔ HUMAN)
 * JSON API with structured logging: logs/chat_api.log
 */

ob_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/chat_api_logger.php';

$jsonFlags = JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}

/**
 * Send JSON and exit. Clears output buffer so stray bytes from includes cannot break JSON.
 *
 * @param array<string,mixed> $payload
 */
function pcvc_chat_emit(array $payload, int $httpCode = 200): void
{
    global $jsonFlags;
    if (!isset($payload['request_id'])) {
        $payload['request_id'] = pcvc_chat_request_id();
    }
    if (!isset($payload['ok'])) {
        $payload['ok'] = ($httpCode >= 200 && $httpCode < 300);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, $jsonFlags);
    exit;
}

try {
    define('PCVC_CHAT_API', true);

    pcvc_chat_log('request_start', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        pcvc_chat_log('reject_method');
        pcvc_chat_emit(['reply' => 'Method not allowed', 'ok' => false], 405);
    }

    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/configi-ai-xander.php';
    require_once __DIR__ . '/ai_knowledge.php';
    require_once __DIR__ . '/includes/knowledge_chat_lib.php';
    require_once __DIR__ . '/includes/chat_ensure_tables.php';

    /** @var int FAQ rows for this tenant */
    define('PCVC_KB_CLIENT_ID', 1);

    ob_clean();
    pcvc_ensure_chat_tables($conn);
    pcvc_chat_log('includes_loaded', ['openai_configured' => defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '']);

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        pcvc_chat_log('bad_json_body');
        pcvc_chat_emit(['reply' => 'Invalid request', 'ok' => false], 400);
    }

    $sessionId = trim((string) ($input['session'] ?? ''));
    $userMsg   = trim((string) ($input['message'] ?? ''));

    if ($sessionId === '' || $userMsg === '') {
        pcvc_chat_log('missing_session_or_message');
        pcvc_chat_emit(['reply' => 'Invalid request', 'ok' => false], 400);
    }

    pcvc_chat_log('input_ok', ['session_len' => strlen($sessionId), 'msg_len' => strlen($userMsg)]);

    $stmt = $conn->prepare('
      INSERT INTO chat_sessions (session_id, mode, status, updated_at)
      VALUES (?, \'ai\', \'open\', NOW())
      ON DUPLICATE KEY UPDATE updated_at = NOW()
    ');
    if (!$stmt) {
        pcvc_chat_log('db_prepare_fail', ['err' => $conn->error]);
        throw new RuntimeException('Database error (sessions)');
    }
    $stmt->bind_param('s', $sessionId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('
      INSERT INTO chat_messages (session_id, sender, message, created_at, delivered)
      VALUES (?, \'user\', ?, NOW(), 1)
    ');
    if (!$stmt) {
        pcvc_chat_log('db_prepare_fail', ['err' => $conn->error]);
        throw new RuntimeException('Database error (messages)');
    }
    $stmt->bind_param('ss', $sessionId, $userMsg);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('SELECT mode FROM chat_sessions WHERE session_id = ?');
    if (!$stmt) {
        throw new RuntimeException('Database error (mode)');
    }
    $stmt->bind_param('s', $sessionId);
    $stmt->execute();
    $stmt->bind_result($mode);
    $stmt->fetch();
    $stmt->close();

    $mode = $mode ?: 'ai';

    if ($mode === 'human') {
        pcvc_chat_log('mode_human');
        pcvc_chat_emit([
            'reply' => 'A live advisor is reviewing your message and will respond shortly.',
            'source' => 'human',
        ]);
    }

    $blockedWords = [
        'illegal',
        'fake visa',
        'bribe',
        'guarantee visa',
        'forged documents',
    ];

    foreach ($blockedWords as $word) {
        if (stripos($userMsg, $word) !== false) {
            $safeReply =
                'I can’t assist with that request. Please speak with a certified ScholarSync Global advisor.';
            $stmt = $conn->prepare('
              INSERT INTO chat_messages (session_id, sender, message, created_at, delivered)
              VALUES (?, \'ai\', ?, NOW(), 1)
            ');
            if ($stmt) {
                $stmt->bind_param('ss', $sessionId, $safeReply);
                $stmt->execute();
                $stmt->close();
            }
            pcvc_chat_log('blocked_word');
            pcvc_chat_emit(['reply' => $safeReply, 'source' => 'safety']);
        }
    }

    $faqAnswer = null;
    if (pcvc_user_requests_human_advisor($userMsg)) {
        pcvc_chat_log('human_intent_skip_faq');
    } else {
        try {
            $faqAnswer = pcvc_find_faq_answer($conn, $userMsg, PCVC_KB_CLIENT_ID);
        } catch (Throwable $e) {
            pcvc_chat_log('faq_exception', ['err' => $e->getMessage(), 'line' => $e->getLine()]);
        }
    }

    if ($faqAnswer !== null && $faqAnswer !== '') {
        pcvc_chat_log('faq_hit', ['answer_len' => strlen($faqAnswer)]);
        $stmt = $conn->prepare('
          INSERT INTO chat_messages (session_id, sender, message, created_at, delivered)
          VALUES (?, \'ai\', ?, NOW(), 1)
        ');
        if ($stmt) {
            $stmt->bind_param('ss', $sessionId, $faqAnswer);
            $stmt->execute();
            $stmt->close();
        }
        pcvc_chat_emit([
            'reply' => nl2br(htmlspecialchars($faqAnswer, ENT_QUOTES, 'UTF-8')),
            'source' => 'faq',
        ]);
    }

    pcvc_chat_log('faq_miss');

    if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === '') {
        pcvc_chat_log('openai_key_missing');
        pcvc_chat_emit([
            'reply' => 'The assistant is not configured (missing OPENAI_API_KEY). Add OPENAI_API_KEY to your .env file in the site root.',
            'ok' => false,
            'source' => 'config',
        ], 503);
    }

    $contactPrompt = function_exists('pcvc_support_contact_prompt_block') ? pcvc_support_contact_prompt_block() : '';

    $systemPrompt = <<<PROMPT
You are MISA, the official AI Education & Global Mobility Assistant of ScholarSync Global (XGS).

No curated FAQ matched this message — you are answering directly. Be smart, concise, and on-topic.

HOW TO RESPOND:
- Address the user’s actual intent first (e.g. if they ask for a human advisor, explain that certified advisors are available and give the OFFICIAL CONTACT details below verbatim; do not invent phone numbers or emails; do not paste unrelated document checklists).
- Answer clearly in short paragraphs or bullets when it helps; avoid dumping long generic templates unless the user asked for a checklist.
- Use the XGS knowledge below as ground truth; do not invent universities, scholarships, fees, deadlines, or guarantees.
- Never promise visa or admission approval; emphasize compliance and honesty.
- If something is not in the knowledge base, say you are not sure and suggest a certified advisor for a personalized assessment.

================= XGS KNOWLEDGE BASE =================
$XGS_KNOWLEDGE
=====================================================
{$contactPrompt}
PROMPT;

    $model = defined('AI_MODEL') ? AI_MODEL : 'gpt-4o-mini';
    $temp = defined('AI_TEMPERATURE') ? AI_TEMPERATURE : 0.6;
    $maxTok = defined('AI_MAX_TOKENS') ? AI_MAX_TOKENS : 350;
    $timeout = defined('AI_TIMEOUT') ? (int) AI_TIMEOUT : 30;

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMsg],
        ],
        'temperature' => (float) $temp,
        'max_tokens' => (int) $maxTok,
    ];

    pcvc_chat_log('openai_request', ['model' => $model]);

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

    $data = is_string($response) ? json_decode($response, true) : null;
    $aiReply = null;

    if ($response === false || $curlErr !== '') {
        pcvc_chat_log('openai_curl_fail', ['curl_error' => $curlErr]);
        $aiReply = 'We could not reach the assistant. Please check your connection and try again in a moment.';
    } elseif (!is_array($data)) {
        pcvc_chat_log('openai_bad_json', ['raw_snip' => substr((string) $response, 0, 400)]);
        $aiReply = 'The assistant returned an unexpected response. Please try again.';
    } elseif ($httpCode < 200 || $httpCode >= 300) {
        $apiMsg = isset($data['error']['message']) ? (string) $data['error']['message'] : '';
        pcvc_chat_log('openai_http_error', ['http' => $httpCode, 'api_msg' => $apiMsg]);
        $aiReply = 'The assistant is temporarily unavailable. Please try again or contact ScholarSync Global.';
    } elseif (!isset($data['choices'][0]['message']['content'])) {
        pcvc_chat_log('openai_no_choices', ['raw_snip' => substr((string) $response, 0, 400)]);
        $aiReply = 'The assistant could not complete a reply. Please try again.';
    } else {
        $aiReply = (string) $data['choices'][0]['message']['content'];
        pcvc_chat_log('openai_ok', ['reply_len' => strlen($aiReply)]);
    }

    if ($aiReply === null || $aiReply === '') {
        $aiReply = 'I’m currently unavailable. Please contact a human advisor.';
    }

    $stmt = $conn->prepare('
      INSERT INTO chat_messages (session_id, sender, message, created_at, delivered)
      VALUES (?, \'ai\', ?, NOW(), 1)
    ');
    if ($stmt) {
        $stmt->bind_param('ss', $sessionId, $aiReply);
        $stmt->execute();
        $stmt->close();
    }

    pcvc_chat_log('response_done', ['source' => 'openai']);

    pcvc_chat_emit([
        'reply' => nl2br(htmlspecialchars($aiReply, ENT_QUOTES, 'UTF-8')),
        'source' => 'openai',
    ]);
} catch (Throwable $e) {
    pcvc_chat_log('fatal', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    pcvc_chat_emit([
        'reply' => 'Something went wrong on the server. Please try again. If this continues, contact support and mention request ID.',
        'ok' => false,
        'error' => 'server',
    ], 500);
}
