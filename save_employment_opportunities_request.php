<?php
declare(strict_types=1);

/**
 * save_employment_opportunities_request.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_name('EO_EMPLOYMENT_FORM');
    session_start();
}

ob_start();
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function eo_json(bool $ok, string $message, array $extra = [], int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Spawn detached CLI worker so SMTP does not block the HTTP response.
 */
function eo_spawn_notify_worker(string $referenceId): bool
{
    $candidates = [];
    if (defined('PHP_BINARY') && PHP_BINARY !== '') {
        $dir = dirname(PHP_BINARY);
        $cli = $dir . DIRECTORY_SEPARATOR . 'php.exe';
        if (is_file($cli)) {
            $candidates[] = $cli;
        }
        $candidates[] = PHP_BINARY;
    }
    $candidates[] = 'C:\\xampp\\php\\php.exe';
    $candidates[] = 'php';

    $php = null;
    foreach ($candidates as $c) {
        if ($c === 'php' || is_file($c)) {
            $php = $c;
            break;
        }
    }
    if ($php === null) {
        error_log('EO notify worker: no PHP binary found');
        return false;
    }

    $script = __DIR__ . DIRECTORY_SEPARATOR . 'eo_notify_worker.php';
    if (!is_file($script)) {
        error_log('EO notify worker missing: ' . $script);
        return false;
    }

    $refArg = escapeshellarg($referenceId);
    $scriptArg = escapeshellarg($script);
    $phpArg = escapeshellarg($php);

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Detach from Apache: start /B runs without waiting for the child.
        $cmd = 'cmd /c start /B "" ' . $phpArg . ' ' . $scriptArg . ' ' . $refArg;
        $h = @popen($cmd, 'r');
        if ($h === false) {
            error_log('EO notify worker spawn failed: ' . $cmd);
            return false;
        }
        @pclose($h);
        return true;
    }

    $cmd = $phpArg . ' ' . $scriptArg . ' ' . $refArg . ' > /dev/null 2>&1 &';
    @exec($cmd);
    return true;
}

/**
 * Return JSON to the browser immediately, then keep running (fallback if spawn fails).
 * Does NOT exit — caller continues after this returns.
 */
function eo_json_flush_continue(bool $ok, string $message, array $extra = [], int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $payload = json_encode(
        array_merge(['success' => $ok, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE
    );

    ignore_user_abort(true);
    @set_time_limit(180);

    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    // FastCGI / LiteSpeed: close the connection cleanly, then keep running.
    if (function_exists('fastcgi_finish_request')) {
        header('Content-Length: ' . (string) strlen($payload));
        echo $payload;
        @fastcgi_finish_request();
        return;
    }

    if (function_exists('litespeed_finish_request')) {
        header('Content-Length: ' . (string) strlen($payload));
        echo $payload;
        @litespeed_finish_request();
        return;
    }

    // Apache mod_php fallback: emit the exact JSON and flush. Do NOT pad the body
    // (padding + Content-Length mismatch can corrupt the JSON the browser parses).
    echo $payload;
    @ob_flush();
    @flush();
}

set_exception_handler(static function (Throwable $e): void {
    error_log('Employment opportunities save: ' . $e->getMessage());
    eo_json(false, 'Server error: ' . $e->getMessage(), [], 500);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    eo_json(false, 'Invalid request', [], 405);
}

$user_id = trim((string) ($_POST['user_id'] ?? ''));
if ($user_id === '') {
    eo_json(false, 'Session expired. Please refresh the page and try again.', [], 401);
}
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $user_id;
} elseif ($user_id !== $_SESSION['user_id']) {
    eo_json(false, 'Session expired. Please refresh the page and try again.', [], 401);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/employment_opportunities_schema.php';
require_once __DIR__ . '/helpers/employment_opportunities_files.php';
require_once __DIR__ . '/helpers/employment_opportunities_notify.php';

// Auto-migrate before insert (idempotent — runs on production on every submit).
eo_ensure_schema($conn);

$full_name = trim((string) ($_POST['full_name'] ?? ''));
$passport_number = strtoupper(trim((string) ($_POST['passport_number'] ?? '')));
$phone_area_code = preg_replace('/\D+/', '', (string) ($_POST['phone_area_code'] ?? '')) ?? '';
$phone_number = preg_replace('/\D+/', '', (string) ($_POST['phone_number'] ?? '')) ?? '';
$messaging_app = strtolower(trim((string) ($_POST['messaging_app'] ?? 'whatsapp')));
$training_field = trim((string) ($_POST['training_field'] ?? ''));
$emailRaw = trim((string) ($_POST['email'] ?? ''));
$email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);

$passport_file = eo_normalize_rel_path((string) ($_POST['passport_file'] ?? ''));
$academicTemps = eo_collect_post_file_paths('academic_docs_file');

$allowedFields = array_keys(eo_training_fields());
$missing = [];
if ($full_name === '') {
    $missing[] = 'Full Name';
}
if ($passport_number === '') {
    $missing[] = 'Passport Number';
}
if (!$email) {
    $missing[] = 'Valid Email';
}
if ($phone_number === '') {
    $missing[] = 'Phone Number';
}
if (!in_array($messaging_app, ['whatsapp', 'telegram'], true)) {
    $missing[] = 'Telegram or WhatsApp';
}
if (!in_array($training_field, $allowedFields, true)) {
    $missing[] = 'Training Field';
}
if ($passport_file === '' || !eo_validate_stored_path($passport_file)) {
    $missing[] = 'Passport Scan';
}
$academicValid = [];
foreach ($academicTemps as $p) {
    $p = eo_normalize_rel_path($p);
    if ($p !== '' && eo_validate_stored_path($p)) {
        $academicValid[] = $p;
    }
}
if ($academicValid === []) {
    $missing[] = 'At least one Academic Document';
}

if ($missing !== []) {
    eo_json(false, 'Please complete the required fields below.', ['missing' => array_values(array_unique($missing))], 422);
}

$check = $conn->prepare('SELECT id FROM employment_opportunities_applications WHERE user_id = ? LIMIT 1');
$check->bind_param('s', $user_id);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    eo_json(false, 'An application for this session already exists.', [], 409);
}
$check->close();

$reference_id = 'EO' . date('Y') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
$nameParts = preg_split('/\s+/', $full_name, 2) ?: [$full_name];
$first_name = $nameParts[0] ?? $full_name;
$last_name = $nameParts[1] ?? '';
$academic_docs_file = eo_encode_stored_files($academicValid);
$emailStore = strtolower((string) $email);
$messaging_username = '';

$sql = 'INSERT INTO employment_opportunities_applications (
    user_id, reference_id, full_name, first_name, last_name, email,
    phone_area_code, phone_number, messaging_app, messaging_username,
    passport_number, training_field, passport_file, academic_docs_file, status, created_at
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,"pending",NOW())';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    eo_json(false, 'Database error', [], 500);
}

$stmt->bind_param(
    'ssssssssssssss',
    $user_id,
    $reference_id,
    $full_name,
    $first_name,
    $last_name,
    $emailStore,
    $phone_area_code,
    $phone_number,
    $messaging_app,
    $messaging_username,
    $passport_number,
    $training_field,
    $passport_file,
    $academic_docs_file
);

if (!$stmt->execute()) {
    $stmt->close();
    eo_json(false, 'Could not save application', [], 500);
}
$application_id = (int) $conn->insert_id;
$stmt->close();

$row = [
    'user_id' => $user_id,
    'reference_id' => $reference_id,
    'full_name' => $full_name,
    'first_name' => $first_name,
    'last_name' => $last_name,
    'email' => $emailStore,
    'phone_area_code' => $phone_area_code,
    'phone_number' => $phone_number,
    'messaging_app' => $messaging_app,
    'passport_number' => $passport_number,
    'training_field' => $training_field,
    'passport_file' => $passport_file,
    'academic_docs_file' => $academic_docs_file,
];

$successMsg = 'Application submitted successfully. A confirmation email will arrive shortly.';

// Free this browser for another application (new form session id).
$_SESSION['user_id'] = 'eo_' . bin2hex(random_bytes(6)) . '_' . time();

// Queue applicant confirmation using the same CLI background worker pattern
// used by Francophonie Mobility on cPanel.
$queued = eo_fire_async_applicant_notify($application_id);
if (!$queued) {
    // Absolute last resort: send inline (may delay response a few seconds).
    try {
        eo_notify_applicant_received($row);
    } catch (Throwable $e) {
        error_log('EO inline notify fallback failed [' . $reference_id . ']: ' . $e->getMessage());
    }
}

eo_json(true, $successMsg, [
    'reference_id' => $reference_id,
    'user_id' => $user_id,
    'email_queued' => $queued,
]);