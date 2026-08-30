<?php
/**
 * One-shot migration: Korea Invitation Contract tables ONLY.
 *
 * Usage (browser or CLI on cPanel):
 *   php migrate_korea_invitation_contract.php
 *   https://your-domain.com/scholarsyncglobal/migrate_korea_invitation_contract.php
 *
 * Safe to re-run. Also runs automatically via kic_contract_ensure_schema()
 * on admin dashboard and contract pages after deploy.
 */
declare(strict_types=1);

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/korea_invitation_contract_schema.php';

$ok = kic_contract_ensure_schema($conn);
$status = kic_contract_schema_status($conn);

$lines = [
    'Korea Invitation Contract migration',
    '-----------------------------------',
    'Schema ensure: ' . ($ok ? 'OK' : 'FAILED — check PHP error log'),
];

foreach ($status as $table => $exists) {
    $lines[] = 'Table ' . $table . ': ' . ($exists ? 'EXISTS' : 'MISSING');
}

$lines[] = 'Upload dir: ' . (__DIR__ . '/uploads/korea_invitation_contracts');
$lines[] = 'Scope: Korea invitation contract only (no other tables touched)';

echo implode(PHP_EOL, $lines) . PHP_EOL;

if (!$ok || in_array(false, $status, true)) {
    if ($isCli) {
        exit(1);
    }
    http_response_code(500);
}
