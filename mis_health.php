<?php
/**
 * Quick MIS health check — confirms PHP + database are working.
 * Does not call Gemini or scan documents.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$status = [
    'ok'        => true,
    'time'      => gmdate('c'),
    'php'       => PHP_VERSION,
    'database'  => 'unknown',
    'message'   => 'MIS is running.',
];

try {
    require_once __DIR__ . '/db.php';
    if ($conn->ping()) {
        $status['database'] = 'connected';
    } else {
        $status['ok'] = false;
        $status['database'] = 'ping failed';
        $status['message'] = 'Database ping failed.';
    }
} catch (Throwable $e) {
    $status['ok'] = false;
    $status['database'] = 'error';
    $status['message'] = $e->getMessage();
}

http_response_code($status['ok'] ? 200 : 503);
echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
