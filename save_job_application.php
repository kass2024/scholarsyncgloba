<?php
/**
 * save_job_application.php
 * ASYNC FILE ARCHITECTURE – PRODUCTION READY
 * PHP 8.x / MariaDB / cPanel SAFE
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

error_reporting(0);
ini_set('display_errors', '0');

/* =============================
   RESPONSE HELPER
============================= */
function respond(string $status, string $message, array $data = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode([
        'status'  => $status,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =============================
   SESSION
============================= */
session_name('XGS_JOB_FORM');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond('error', 'Invalid request method', [], 405);
}

if (empty($_SESSION['user_id'])) {
    respond('error', 'Session expired. Please refresh.', [], 401);
}

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    respond('error', 'Security validation failed', [], 403);
}

$user_id = $_SESSION['user_id'];

if (empty($_POST['user_id']) || $_POST['user_id'] !== $user_id) {
    respond('error', 'User validation failed', [], 403);
}

/* 🔥 IMPORTANT: RELEASE SESSION LOCK */
session_write_close();

/* =============================
   DATABASE
============================= */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/phone_whatsapp_normalize.php';

if ($conn->connect_error) {
    respond('error', 'Database connection failed', [], 500);
}

$conn->set_charset('utf8mb4');
$conn->begin_transaction();

/* =============================
   DUPLICATE CHECK
============================= */
$check = $conn->prepare(
    "SELECT id FROM job_applications WHERE user_id = ? LIMIT 1"
);
$check->bind_param("s", $user_id);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $check->close();
    $conn->rollback();
    respond('error', 'Application already submitted', [], 409);
}
$check->close();

/* =============================
   REQUIRED FIELDS
============================= */
$required = [
    'first_name','last_name','email',
    'phone_area_code','phone_number',
    'work_country_id','address_country_id',
    'province_state','district','sector',
    'cell_ward','village',
    'emergency_full_name','emergency_relationship',
    'emergency_email','emergency_area_code',
    'emergency_phone_number'
];

foreach ($required as $field) {
    if (empty($_POST[$field])) {
        $conn->rollback();
        respond('error', "Missing field: {$field}", [], 422);
    }
}

[$phone_area_code, $phone_number] = xander_normalize_job_phone_pair(
    (string) $_POST['phone_area_code'],
    (string) $_POST['phone_number']
);
[$emergency_area_code, $emergency_phone_number] = xander_normalize_job_phone_pair(
    (string) $_POST['emergency_area_code'],
    (string) $_POST['emergency_phone_number']
);

$phone_full_len = strlen($phone_area_code . $phone_number);
$em_full_len = strlen($emergency_area_code . $emergency_phone_number);
if ($phone_full_len < 10 || $phone_full_len > 15) {
    $conn->rollback();
    respond('error', 'Invalid phone: include country code (saved as digits only, no +).', [], 422);
}
if ($em_full_len < 10 || $em_full_len > 15) {
    $conn->rollback();
    respond('error', 'Invalid emergency phone: include country code (digits only, no +).', [], 422);
}

/* =============================
   REQUIRED FILE PATHS (TEMP)
============================= */
$requiredFiles = ['passport', 'photo'];

foreach ($requiredFiles as $file) {
    if (empty($_POST[$file])) {
        $conn->rollback();
        respond('error', ucfirst($file) . ' is required', [], 422);
    }
}

/* =============================
   PATH SECURITY
============================= */
function validateTempPath(string $path, string $user_id): string {
    $base = realpath(__DIR__ . "/uploads/tmp/{$user_id}/");
    $full = realpath(__DIR__ . '/' . $path);

    if (!$base || !$full || !str_starts_with($full, $base)) {
        respond('error', 'Invalid file reference', [], 400);
    }

    if (!file_exists($full)) {
        respond('error', 'Uploaded file missing', [], 400);
    }

    return $full;
}

/* =============================
   FINAL UPLOAD DIRECTORY
============================= */
$finalDir = __DIR__ . "/uploads/job/{$user_id}/";

if (!is_dir($finalDir) && !mkdir($finalDir, 0755, true)) {
    $conn->rollback();
    respond('error', 'Unable to create upload directory', [], 500);
}

file_put_contents($finalDir . 'index.html', 'Access denied');

/* =============================
   MOVE FILES
============================= */
$fileFields = [
    'passport','photo','national_id','cv',
    'academic_certificates','experience_letters','bank_statement'
];

$files = [];

foreach ($fileFields as $field) {
    if (!empty($_POST[$field])) {

        $tempFullPath = validateTempPath($_POST[$field], $user_id);

        $ext = pathinfo($tempFullPath, PATHINFO_EXTENSION);
        $newName = $field . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

        $finalRelative = "uploads/job/{$user_id}/{$newName}";
        $finalAbsolute = __DIR__ . '/' . $finalRelative;

        if (!rename($tempFullPath, $finalAbsolute)) {
            $conn->rollback();
            respond('error', "Failed to move {$field}", [], 500);
        }

        $files[$field] = $finalRelative;
    }
}

/* =============================
   INSERT APPLICATION
============================= */
$stmt = $conn->prepare("
INSERT INTO job_applications (
    user_id, first_name, last_name, email,
    phone_area_code, phone_number,
    work_country_id, address_country_id,
    province_state, district, sector,
    cell_ward, village,
    emergency_full_name, emergency_relationship,
    emergency_email, emergency_area_code,
    emergency_phone_number, created_at
) VALUES (
    ?,?,?,?,?,?, ?,?,
    ?,?,?,?,?,
    ?,?,?,?,?,
    NOW()
)
");

$stmt->bind_param(
    "ssssssiiisssssssss",
    $user_id,
    $_POST['first_name'],
    $_POST['last_name'],
    $_POST['email'],
    $phone_area_code,
    $phone_number,
    $_POST['work_country_id'],
    $_POST['address_country_id'],
    $_POST['province_state'],
    $_POST['district'],
    $_POST['sector'],
    $_POST['cell_ward'],
    $_POST['village'],
    $_POST['emergency_full_name'],
    $_POST['emergency_relationship'],
    $_POST['emergency_email'],
    $emergency_area_code,
    $emergency_phone_number
);

if (!$stmt->execute()) {
    $conn->rollback();
    respond('error', 'Failed to save application', [], 500);
}
$stmt->close();

/* =============================
   INSERT DOCUMENTS
============================= */
if ($files) {
    $doc = $conn->prepare("
        INSERT INTO job_documents (user_id, document_type, file_path, uploaded_at)
        VALUES (?, ?, ?, NOW())
    ");

    foreach ($files as $type => $path) {
        $doc->bind_param("sss", $user_id, $type, $path);
        if (!$doc->execute()) {
            $conn->rollback();
            respond('error', 'Failed to save documents', [], 500);
        }
    }
    $doc->close();
}

/* =============================
   COMMIT
============================= */
$conn->commit();

$reference = 'XGS-' . strtoupper(substr(hash('sha256', $user_id), 0, 8));

require_once __DIR__ . '/helpers/application_confirmation_emails.php';
try {
    xander_send_job_application_confirmation_emails($conn, $user_id, $reference);
} catch (Throwable $e) {
    error_log('[save_job_application] confirmation email: ' . $e->getMessage());
}

/* =============================
   SUCCESS
============================= */
respond('success', 'Application submitted successfully', [
    'reference_number' => $reference
]);
