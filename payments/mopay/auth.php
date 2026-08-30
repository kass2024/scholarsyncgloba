<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mopay_http.php';

/**
 * Returns a value suitable to be placed after `Authorization:` header.
 *
 * The PDF shows two different patterns:
 * - A token endpoint (client_credentials) that returns an access_token (Bearer).
 * - Other endpoints where `Authorization` appears to be the auth key value directly.
 *
 * To make it robust:
 * - If MOPAY_BEARER_TOKEN is explicitly set, use it as `Bearer ...`.
 * - Otherwise try to fetch an access_token from /token (if reachable).
 * - If token fetch fails (DNS/406/etc.), fallback to using MOPAY_AUTH_KEY directly.
 */
function mopay_get_authorization_value(array $cfg): string
{
    if (!empty($cfg['bearer_token'])) {
        $val = (string)$cfg['bearer_token'];
        // If caller already provided "Bearer ...", keep it; otherwise wrap.
        if (stripos($val, 'bearer ') === 0) {
            return $val;
        }
        return 'Bearer ' . $val;
    }

    $cacheFile = __DIR__ . '/storage/token_cache.json';
    if (file_exists($cacheFile)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['access_token']) && !empty($cached['expires_at'])) {
            if (time() < ((int)$cached['expires_at'] - 60)) {
                return 'Bearer ' . (string)$cached['access_token'];
            }
        }
    }

    $basic = trim((string)($cfg['auth_key'] ?? ''));
    if ($basic === '') {
        throw new Exception('Missing MOPAY_AUTH_KEY (Basic credential).');
    }

    $serverBase = rtrim((string)($cfg['server_base_url'] ?? ''), '/');
    if ($serverBase === '') {
        throw new Exception('Missing MOPAY_SERVER_BASE_URL.');
    }

    // The PDF shows /token on the Bizao host.
    // Sometimes the "server_base_url" provided by users points at a different host for the debit APIs,
    // so we try multiple token URLs to avoid hard failures.
    $tokenUrls = [];
    if (!empty(getenv('MOPAY_TOKEN_URL'))) {
        $tokenUrls[] = (string)getenv('MOPAY_TOKEN_URL');
    }

    $tokenUrls[] = $serverBase . '/token';
    $tokenUrls[] = preg_replace('#^http://#', 'https://', $serverBase) . '/token';
    $tokenUrls[] = 'https://preproduction-gateway.bizao.com/token';

    $headers = [
        'Authorization: Basic ' . $basic,
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ];

    $body = http_build_query(['grant_type' => 'client_credentials']);

    $lastError = '';
    foreach ($tokenUrls as $tokenUrl) {
        try {
            $res = mopay_http_request('POST', $tokenUrl, $headers, $body);
            if (!empty($res['curl_error'])) {
                $lastError = 'curl_error: ' . $res['curl_error'] . ' (' . $tokenUrl . ')';
                continue;
            }

            $raw = is_string($res['body']) ? $res['body'] : '';
            $json = $raw !== '' ? json_decode($raw, true) : null;

            if ($res['status_code'] < 200 || $res['status_code'] >= 300) {
                $msg = is_array($json) ? (json_encode($json)) : $raw;
                $lastError = 'Token HTTP ' . $res['status_code'] . ' from ' . $tokenUrl . ': ' . (string)$msg;
                continue;
            }

            if (!is_array($json) || empty($json['access_token'])) {
                $lastError = 'Token response missing access_token from ' . $tokenUrl . ': ' . (string)$raw;
                continue;
            }

            $accessToken = (string)$json['access_token'];
            $expiresIn = isset($json['expires_in']) ? (int)$json['expires_in'] : 3600;
            $expiresAt = time() + max(60, $expiresIn);

            file_put_contents($cacheFile, json_encode([
                'access_token' => $accessToken,
                'expires_at' => $expiresAt,
                'fetched_at' => time(),
                'token_url' => $tokenUrl,
            ]), LOCK_EX);

            return 'Bearer ' . $accessToken;
        } catch (Throwable $e) {
            $lastError = 'Token exception from ' . $tokenUrl . ': ' . $e->getMessage();
        }
    }

    // Fallback: use auth_key directly in Authorization header (as shown in PDF for debit/status/settings).
    // This avoids failing when the /token endpoint isn't reachable from your network.
    return $basic;
}

