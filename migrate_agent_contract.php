<?php
/**
 * One-shot migration: Agent Referral Contract tables ONLY.
 *
 * Usage (browser or CLI):
 *   php migrate_agent_contract.php
 *   https://your-domain.com/scholarsyncglobal/migrate_agent_contract.php
 */
declare(strict_types=1);

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/agent_contract_schema.php';

$ok = agent_contract_ensure_schema($conn);
$status = agent_contract_schema_status($conn);

$lines = [
    'Agent Referral Contract migration',
    '---------------------------------',
    'Schema ensure: ' . ($ok ? 'OK' : 'FAILED — check PHP error log'),
];

foreach ($status as $table => $exists) {
    $lines[] = 'Table ' . $table . ': ' . ($exists ? 'EXISTS' : 'MISSING');
}

$lines[] = 'Upload dir: ' . agent_contract_upload_dir();
$lines[] = 'Scope: agent contract only (no other tables touched)';

echo implode(PHP_EOL, $lines) . PHP_EOL;

if (!$ok || in_array(false, $status, true)) {
    if ($isCli) {
        exit(1);
    }
    http_response_code(500);
}
