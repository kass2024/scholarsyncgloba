<?php
declare(strict_types=1);

/**
 * Daily attendance digest — run once per day via cron (e.g. 22:00).
 * php daily_check.php
 * php daily_check.php 2026-06-07   (optional date override)
 */

require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/daily_attendance_notify.php';

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$dateToday = isset($argv[1]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $argv[1])
    ? (string) $argv[1]
    : date('Y-m-d');

$ranMarker = $logDir . '/daily_check_last_date.txt';
if (
    !isset($argv[1])
    && is_readable($ranMarker)
    && trim((string) file_get_contents($ranMarker)) === $dateToday
) {
    echo 'daily_check already ran for ' . $dateToday . ". Skipping.\n";
    exit(0);
}

$lockFp = @fopen($logDir . '/daily_check.lock', 'c+');
if ($lockFp !== false && !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "daily_check already running.\n";
    exit(0);
}

$channels = pcvc_daily_attendance_notify_channels();
$summaries = pcvc_daily_attendance_fetch_summaries($conn, $dateToday);

$stats = [
    'admins'        => count($summaries),
    'email_sent'    => 0,
    'whatsapp_sent' => 0,
    'wa_skipped'    => 0,
];

foreach ($summaries as $summary) {
    $notify = pcvc_daily_attendance_notify_admin($summary, $channels);
    if ($notify['email']) {
        $stats['email_sent']++;
    }
    if ($notify['whatsapp']) {
        $stats['whatsapp_sent']++;
    } elseif (!empty($channels['whatsapp'])) {
        $stats['wa_skipped']++;
    }
}

$superReport = false;
if (!empty($channels['email'])) {
    $superReport = pcvc_daily_attendance_send_superadmin_report($conn, $dateToday, $summaries);
}

$conn->close();

@file_put_contents($ranMarker, $dateToday, LOCK_EX);
if ($lockFp !== false) {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
}

echo sprintf(
    "daily_check done for %s — admins:%d email:%d whatsapp:%d wa_skipped:%d super_report:%s channels:%s\n",
    $dateToday,
    $stats['admins'],
    $stats['email_sent'],
    $stats['whatsapp_sent'],
    $stats['wa_skipped'],
    $superReport ? 'yes' : 'no',
    json_encode($channels)
);
