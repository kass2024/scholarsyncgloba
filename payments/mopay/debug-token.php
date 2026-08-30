<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$cfg = require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    $authValue = mopay_get_authorization_value($cfg);
    echo json_encode([
        'ok' => true,
        'authorization_value_length' => strlen($authValue),
        'authorization_preview' => substr($authValue, 0, 10) . '...',
        'cache_file' => 'payments/mopay/storage/token_cache.json',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'hint' => 'Set MOPAY_TOKEN_URL in .env to the correct /token endpoint if needed.',
    ]);
}

