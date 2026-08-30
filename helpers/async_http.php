<?php
declare(strict_types=1);

/**
 * Send HTTP response body to the client and close the connection so background work can continue.
 */
function pcvc_finish_http_response(string $body, int $statusCode = 200): void
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Connection: close');
        header('Content-Length: ' . (string) strlen($body));
    }

    echo $body;

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }

    ignore_user_abort(true);

    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    flush();

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

/**
 * POST to another endpoint after the client response (email, WhatsApp). Returns true if HTTP accepted.
 */
function pcvc_trigger_background_post(string $relativeScript, array $postFields, int $timeoutSec = 15): bool
{
    if (!function_exists('curl_init')) {
        error_log('pcvc_trigger_background_post: curl extension missing');
        return false;
    }

    if (!function_exists('pcvc_public_base_url')) {
        require_once dirname(__DIR__) . '/includes/company_branding.php';
    }

    $url = rtrim(pcvc_public_base_url(), '/') . '/' . ltrim($relativeScript, '/');
    $payload = http_build_query($postFields);

    $ch = curl_init($url);
    if ($ch === false) {
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_TIMEOUT        => max(5, $timeoutSec),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_NOSIGNAL       => true,
        CURLOPT_FRESH_CONNECT  => true,
        CURLOPT_FORBID_REUSE   => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER     => ['Connection: Close'],
    ]);

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    $error    = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        error_log("pcvc_trigger_background_post curl error [{$errno}] {$error} url={$url}");
        return false;
    }

    if ($httpCode >= 400) {
        error_log("pcvc_trigger_background_post HTTP {$httpCode} url={$url} body=" . substr((string) $response, 0, 200));
        return false;
    }

    return true;
}
