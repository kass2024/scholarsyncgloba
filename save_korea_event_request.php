<?php
declare(strict_types=1);

/**
 * save_korea_event_request.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_name('KEP_EVENT_FORM');
    session_start();
}

ob_start();
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function kep_json(bool $ok, string $message, array $extra = [], int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

set_exception_handler(static function (Throwable $e): void {
    error_log('Korea event save: ' . $e->getMessage());
    kep_json(false, 'Server error: ' . $e->getMessage(), [], 500);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kep_json(false, 'Invalid request', [], 405);
}

$user_id = trim((string) ($_POST['user_id'] ?? ''));
if ($user_id === '') {
    kep_json(false, 'Session expired. Please refresh the page and try again.', [], 401);
}
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $user_id;
} elseif ($user_id !== $_SESSION['user_id']) {
    kep_json(false, 'Session expired. Please refresh the page and try again.', [], 401);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/korea_event_schema.php';
require_once __DIR__ . '/helpers/korea_event_files.php';
require_once __DIR__ . '/helpers/korea_event_notify.php';

kep_ensure_schema($conn);

$full_name = trim((string) ($_POST['full_name'] ?? ''));
$date_of_birth = trim((string) ($_POST['date_of_birth'] ?? ''));
$gender = strtolower(trim((string) ($_POST['gender'] ?? '')));
$nationality = trim((string) ($_POST['nationality'] ?? ''));
$country_of_residence = trim((string) ($_POST['country_of_residence'] ?? ''));
$passport_number = strtoupper(trim((string) ($_POST['passport_number'] ?? '')));
$phone_area_code = preg_replace('/\D+/', '', (string) ($_POST['phone_area_code'] ?? '')) ?? '';
$phone_number = preg_replace('/\D+/', '', (string) ($_POST['phone_number'] ?? '')) ?? '';
$messaging_app = strtolower(trim((string) ($_POST['messaging_app'] ?? 'whatsapp')));
$occupation = trim((string) ($_POST['occupation'] ?? ''));
$organization = trim((string) ($_POST['organization'] ?? ''));
$event_name = trim((string) ($_POST['event_name'] ?? ''));
if ($event_name === '') {
    $event_name = 'South Korea Event';
}
$participation_purpose = trim((string) ($_POST['participation_purpose'] ?? ''));
$emailRaw = trim((string) ($_POST['email'] ?? ''));
$email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);

$passport_file = kep_normalize_rel_path((string) ($_POST['passport_file'] ?? ''));
$cv_file = kep_normalize_rel_path((string) ($_POST['cv_file'] ?? ''));

$allowedGenders = array_keys(kep_gender_options());
$missing = [];
if ($full_name === '') {
    $missing[] = 'Full Name';
}
if ($date_of_birth === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
    $missing[] = 'Date of Birth';
}
if (!in_array($gender, $allowedGenders, true)) {
    $missing[] = 'Gender';
}
if ($nationality === '') {
    $missing[] = 'Nationality';
}
if ($country_of_residence === '') {
    $missing[] = 'Country of Residence';
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
if ($occupation === '') {
    $missing[] = 'Occupation';
}
if ($passport_file === '' || !kep_validate_stored_path($passport_file)) {
    $missing[] = 'Passport Scan';
}
if ($cv_file === '' || !kep_validate_stored_path($cv_file)) {
    $missing[] = 'CV / Resume';
}

if ($missing !== []) {
    kep_json(false, 'Please complete the required fields below.', ['missing' => array_values(array_unique($missing))], 422);
}

$check = $conn->prepare('SELECT id FROM korea_event_applications WHERE user_id = ? LIMIT 1');
$check->bind_param('s', $user_id);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    kep_json(false, 'An application for this session already exists.', [], 409);
}
$check->close();

$reference_id = 'KEP' . date('Y') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
$nameParts = preg_split('/\s+/', $full_name, 2) ?: [$full_name];
$first_name = $nameParts[0] ?? $full_name;
$last_name = $nameParts[1] ?? '';
$emailStore = strtolower((string) $email);

$sql = 'INSERT INTO korea_event_applications (
    user_id, reference_id, full_name, first_name, last_name, date_of_birth, gender,
    nationality, country_of_residence, passport_number, email,
    phone_area_code, phone_number, messaging_app, occupation, organization,
    event_name, participation_purpose, passport_file, cv_file, status, created_at
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"pending",NOW())';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    kep_json(false, 'Database error', [], 500);
}

$stmt->bind_param(
    'ssssssssssssssssssss',
    $user_id,
    $reference_id,
    $full_name,
    $first_name,
    $last_name,
    $date_of_birth,
    $gender,
    $nationality,
    $country_of_residence,
    $passport_number,
    $emailStore,
    $phone_area_code,
    $phone_number,
    $messaging_app,
    $occupation,
    $organization,
    $event_name,
    $participation_purpose,
    $passport_file,
    $cv_file
);

if (!$stmt->execute()) {
    $stmt->close();
    kep_json(false, 'Could not save application', [], 500);
}
$application_id = (int) $conn->insert_id;
$stmt->close();

$row = [
    'user_id' => $user_id,
    'reference_id' => $reference_id,
    'full_name' => $full_name,
    'first_name' => $first_name,
    'last_name' => $last_name,
    'date_of_birth' => $date_of_birth,
    'gender' => $gender,
    'nationality' => $nationality,
    'country_of_residence' => $country_of_residence,
    'email' => $emailStore,
    'phone_area_code' => $phone_area_code,
    'phone_number' => $phone_number,
    'messaging_app' => $messaging_app,
    'occupation' => $occupation,
    'organization' => $organization,
    'event_name' => $event_name,
    'participation_purpose' => $participation_purpose,
    'passport_number' => $passport_number,
    'passport_file' => $passport_file,
    'cv_file' => $cv_file,
];

$successMsg = 'Application submitted successfully. A confirmation email will arrive shortly.';

$_SESSION['user_id'] = 'kep_' . bin2hex(random_bytes(6)) . '_' . time();

$queued = kep_fire_async_applicant_notify($application_id);
if (!$queued) {
    try {
        kep_notify_applicant_received($row);
        kep_notify_office_new_application($row);
    } catch (Throwable $e) {
        error_log('KEP inline notify fallback failed [' . $reference_id . ']: ' . $e->getMessage());
    }
}

kep_json(true, $successMsg, [
    'reference_id' => $reference_id,
    'user_id' => $user_id,
    'email_queued' => $queued,
]);
