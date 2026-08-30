<?php
/**
 * Structured logging for chat-api.php (file + optional error_log).
 * Log file: logs/chat_api.log (create logs/ automatically).
 */

declare(strict_types=1);

/**
 * Stable ID for one chat-api request (for correlating browser console ↔ server log).
 */
function pcvc_chat_request_id(): string
{
    static $id = null;
    if ($id === null) {
        try {
            $id = bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            $id = 'req_' . (string) time();
        }
    }
    return $id;
}

/**
 * Append one line to logs/chat_api.log. Never throws.
 *
 * @param array<string,mixed> $context
 */
function pcvc_chat_log(string $stage, array $context = []): void
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . DIRECTORY_SEPARATOR . 'chat_api.log';
    $rid = pcvc_chat_request_id();
    $line = [
        'ts' => gmdate('Y-m-d\TH:i:s\Z'),
        'rid' => $rid,
        'stage' => $stage,
    ];
    if ($context !== []) {
        $line['ctx'] = $context;
    }
    $jf = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jf |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = @json_encode($line, $jf);
    if ($json === false) {
        $json = '{"ts":"' . gmdate('c') . '","rid":"' . $rid . '","stage":"log_encode_fail"}';
    }
    @file_put_contents($file, $json . "\n", FILE_APPEND | LOCK_EX);

    if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
        error_log('[chat-api][' . $rid . '] ' . $stage . (isset($context['err']) ? ' ' . (string) $context['err'] : ''));
    }
}
