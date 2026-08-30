<?php
/**
 * Background email worker for Employment Opportunities.
 * Usage: php eo_notify_worker.php EO2026XXXXXXXX
 *
 * Invoked after form save so SMTP does not block the applicant.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$referenceId = trim((string) ($argv[1] ?? ''));
if ($referenceId === '' || !preg_match('/^EO[A-Z0-9]+$/i', $referenceId)) {
    fwrite(STDERR, "Invalid reference_id\n");
    exit(1);
}

@set_time_limit(180);
ini_set('display_errors', '0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/employment_opportunities_schema.php';
require_once __DIR__ . '/helpers/employment_opportunities_files.php';
require_once __DIR__ . '/helpers/employment_opportunities_notify.php';

eo_ensure_schema($conn);

$stmt = $conn->prepare('SELECT * FROM employment_opportunities_applications WHERE reference_id = ? LIMIT 1');
if (!$stmt) {
    fwrite(STDERR, "DB prepare failed\n");
    exit(1);
}
$stmt->bind_param('s', $referenceId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    fwrite(STDERR, "Application not found: {$referenceId}\n");
    exit(1);
}

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/eo-notify-worker.log';
$log = static function (string $msg) use ($logFile, $referenceId): void {
    @file_put_contents(
        $logFile,
        '[' . date('Y-m-d H:i:s') . "] [{$referenceId}] {$msg}" . PHP_EOL,
        FILE_APPEND
    );
};

$log('Worker started');

// Submission: notify applicant only. Office package is sent on approval.
try {
    $ok = eo_notify_applicant_received($row);
    $log('Applicant notify: ' . ($ok ? 'OK' : 'FAILED'));
} catch (Throwable $e) {
    $log('Applicant notify exception: ' . $e->getMessage());
}

$log('Worker finished');
exit(0);
