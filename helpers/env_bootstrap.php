<?php
declare(strict_types=1);

/**
 * Loads project root .env via payments/bootstrap.php (putenv / $_ENV).
 * Use pcvc_env('KEY') for secrets — never hardcode API keys in committed PHP.
 */
require_once dirname(__DIR__) . '/payments/bootstrap.php';

function pcvc_env(string $key, string $default = ''): string
{
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return trim((string) $v);
    }

    return $default;
}
