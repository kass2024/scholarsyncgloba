<?php
/**
 * Background status worker (legacy CLI).
 * Approval packages are now sent via eo_approval_async.php (HTTP).
 * This CLI path only sends the office package on approve — never emails the applicant.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$appId = isset($argv[1]) ? (int) $argv[1] : 0;
$status = trim((string) ($argv[2] ?? ''));

$allowed = ['pending', 'under_review', 'approved', 'rejected'];
if ($appId <= 0 || !in_array($status, $allowed, true)) {
    fwrite(STDERR, "Invalid args\n");
    exit(1);
}

@set_time_limit(300);
ini_set('display_errors', '0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/employment_opportunities_schema.php';
require_once __DIR__ . '/helpers/employment_opportunities_files.php';
require_once __DIR__ . '/helpers/employment_opportunities_notify.php';

eo_ensure_schema($conn);

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/eo-status-notify.log';
$log = static function (string $msg) use ($logFile, $appId): void {
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] [app:{$appId}] {$msg}" . PHP_EOL, FILE_APPEND);
};

$stmt = $conn->prepare('SELECT * FROM employment_opportunities_applications WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $appId);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) {
    $log('Application not found');
    exit(1);
}

$log('Worker started status=' . $status . ' (applicant notify DISABLED)');

// On approval only: office package. Do not email the applicant.
if ($status === 'approved') {
    try {
        $ok = eo_notify_office_new_application($app);
        $log('Approval package: ' . ($ok ? 'OK' : 'FAILED') . ' to ' . eo_notify_recipient_email());
    } catch (Throwable $e) {
        $log('Approval package exception: ' . $e->getMessage());
    }
} else {
    $log('No office email for status=' . $status);
}

$log('Worker finished');
exit(0);
