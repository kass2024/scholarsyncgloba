<?php

function mopay_trace_log(array $entry): void
{
    $entry['time'] = $entry['time'] ?? date('c');
    $path = __DIR__ . '/logs/trace.log.jsonl';
    file_put_contents($path, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function mopay_trace_auth_mode(string $authValue): string
{
    return (stripos($authValue, 'bearer ') === 0) ? 'bearer' : 'direct-auth-key';
}

