<?php
/**
 * One-shot migration: South Korea Event Participation tables ONLY.
 *
 * Usage (browser or CLI) — works on cPanel File Manager / public URL:
 *   php migrate_korea_event_participation.php
 *   https://yoursite.com/migrate_korea_event_participation.php
 *
 * Idempotent. Tables are also auto-created on the form, save, admin list,
 * dashboard, and retrieve endpoints.
 */
declare(strict_types=1);

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/korea_event_schema.php';

$ok = kep_ensure_schema($conn);

$exists = false;
$res = $conn->query("SHOW TABLES LIKE 'korea_event_applications'");
if ($res) {
    $exists = $res->num_rows > 0;
    $res->free();
}

$cols = [];
if ($exists) {
    $c = $conn->query('SHOW COLUMNS FROM `korea_event_applications`');
    if ($c) {
        while ($row = $c->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
        $c->free();
    }
}

$uploadDir = __DIR__ . '/uploads/korea_event';
$uploadOk = is_dir($uploadDir) || @mkdir($uploadDir, 0755, true);

$lines = [
    'South Korea Event Participation migration',
    '----------------------------------------',
    'Schema ensure: ' . ($ok ? 'OK' : 'FAILED — check PHP error log'),
    'Table korea_event_applications: ' . ($exists ? 'EXISTS' : 'MISSING'),
];
if ($cols !== []) {
    $lines[] = 'Columns (' . count($cols) . '): ' . implode(', ', $cols);
}
$lines[] = 'Upload dir: ' . $uploadDir . ' — ' . ($uploadOk ? 'OK' : 'MISSING');
$lines[] = 'Scope: Korea Event Participation only (no other tables touched)';

echo implode(PHP_EOL, $lines) . PHP_EOL;

if (!$ok || !$exists) {
    if ($isCli) {
        exit(1);
    }
    http_response_code(500);
}
