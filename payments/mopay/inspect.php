<?php
// Inspect a specific transaction across trace + webhook logs.

$trx = isset($_GET['trx']) ? trim((string)$_GET['trx']) : '';
if ($trx === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Missing trx. Use: inspect.php?trx=YOUR_TRANSACTION_ID";
    exit;
}

function tail_matches(string $filePath, string $needle, int $limit = 200): array
{
    if (!file_exists($filePath)) {
        return [];
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }
    $lines = array_slice($lines, -$limit);
    $out = [];
    foreach ($lines as $line) {
        if (strpos($line, $needle) !== false) {
            $out[] = $line;
        }
    }
    return $out;
}

$tracePath = __DIR__ . '/logs/trace.log.jsonl';
$trxPath = __DIR__ . '/storage/transactions.jsonl';
$webhookPath = __DIR__ . '/logs/webhook.log.jsonl';

$matches = [
    'trace' => tail_matches($tracePath, $trx, 500),
    'transactions' => tail_matches($trxPath, $trx, 500),
    'webhook' => tail_matches($webhookPath, $trx, 500),
];

header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'trx' => $trx,
    'files' => [
        'trace' => $tracePath,
        'transactions' => $trxPath,
        'webhook' => $webhookPath,
    ],
    'matches' => $matches,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

