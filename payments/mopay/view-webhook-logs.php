<?php
// Simple helper to view recent webhook callbacks on shared hosting.
// NOTE: This is for debugging only.

$logPath = __DIR__ . '/logs/webhook.log.jsonl';
if (!file_exists($logPath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'No webhook log file found at: ' . $logPath;
    exit;
}

$lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!is_array($lines)) {
    $lines = [];
}
$tailCount = isset($_GET['n']) ? max(1, (int)$_GET['n']) : 50;
$lines = array_slice($lines, -$tailCount);

header('Content-Type: text/plain; charset=UTF-8');
echo 'Showing last ' . count($lines) . ' webhook log entries from ' . $logPath . PHP_EOL . PHP_EOL;
foreach ($lines as $line) {
    echo $line . PHP_EOL;
}

