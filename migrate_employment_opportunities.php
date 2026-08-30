<?php
/**
 * One-shot migration: Employment Opportunities ONLY.
 *
 * Usage (browser or CLI):
 *   php migrate_employment_opportunities.php
 *   http://localhost/scholarsyncglobal/migrate_employment_opportunities.php
 *
 * Does not modify Francophonie Mobility or any other schemas.
 */
declare(strict_types=1);

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/employment_opportunities_schema.php';
require_once __DIR__ . '/helpers/eo_contract_schema.php';

$ok = eo_ensure_schema($conn);
eo_contract_ensure_schema($conn);

$exists = false;
$res = $conn->query("SHOW TABLES LIKE 'employment_opportunities_applications'");
if ($res) {
    $exists = $res->num_rows > 0;
    $res->free();
}

$contractTables = [];
foreach (['eo_employment_contracts', 'eo_employment_signatures'] as $t) {
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    if ($r) {
        $contractTables[$t] = $r->num_rows > 0 ? 'EXISTS' : 'MISSING';
        $r->free();
    }
}

$cols = [];
if ($exists) {
    $c = $conn->query('SHOW COLUMNS FROM `employment_opportunities_applications`');
    if ($c) {
        while ($row = $c->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
        $c->free();
    }
}

$lines = [
    'Employment Opportunities migration',
    '----------------------------------',
    'Schema ensure: ' . ($ok ? 'OK' : 'FAILED — check PHP error log'),
    'Table employment_opportunities_applications: ' . ($exists ? 'EXISTS' : 'MISSING'),
];
if ($cols !== []) {
    $lines[] = 'Columns (' . count($cols) . '): ' . implode(', ', $cols);
}
foreach ($contractTables as $t => $state) {
    $lines[] = 'Table ' . $t . ': ' . $state;
}
$lines[] = 'Upload dir: ' . (__DIR__ . '/uploads/employment_opportunities');
$lines[] = 'Contract upload dir: ' . (__DIR__ . '/uploads/eo_contracts');
$lines[] = 'Scope: EO only (no other tables touched)';

echo implode(PHP_EOL, $lines) . PHP_EOL;

if (!$ok || !$exists) {
    if ($isCli) {
        exit(1);
    }
    http_response_code(500);
}
