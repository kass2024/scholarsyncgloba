<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/fx.php';

header('Content-Type: application/json; charset=utf-8');

$from = strtoupper(trim((string)($_GET['from'] ?? 'RWF')));
if ($from === '') {
    $from = 'RWF';
}

$rate = payments_fx_get_rate_to_rwf($from);
echo json_encode([
    'ok' => true,
    'from' => $from,
    'to' => 'RWF',
    'rate' => $rate,
], JSON_UNESCAPED_SLASHES);
exit;

