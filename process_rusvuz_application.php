<?php
declare(strict_types=1);

/**
 * ==================================================
 * HARD FAIL-SAFE: ALWAYS RETURN JSON
 * ==================================================
 */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

/**
 * ==================================================
 * LOGGING SETUP
 * ==================================================
 */
$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/rusvuz_debug.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

function log_debug(string $msg): void {
    global $logFile;
    error_log(
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL,
        3,
        $logFile
    );
}

/**
 * ==================================================
 * SHUTDOWN HANDLER (CATCH FATAL ERRORS)
 * ==================================================
 */
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err !== null) {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Fatal server error',
            'details' => $err['message']
        ]);
    }
});

log_debug("===== REQUEST START =====");
log_debug("POST: " . print_r($_POST, true));
log_debug("FILES: " . print_r($_FILES, true));

/**
 * ==================================================
 * DATABASE (KEEP YOUR db.php)
 * ==================================================
 */
require_once __DIR__ . '/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    log_debug("DB connection missing");
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Database connection failed']);
    exit;
}

log_debug("DB connected");

/**
 * ==================================================
 * BASIC VALIDATION
 * ==================================================
 */
$required = [
    'user_id','session_id','academic_session','destination',
    'intended_study_level','field_of_study','education_language',
    'first_name','last_name','gender','dob',
    'passport_number','passport_issue_date','passport_expiry_date',
    'address','phone_number','email','application_date'
];

foreach ($required as $f) {
    if (empty($_POST[$f])) {
        log_debug("Missing field: {$f}");
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>"Missing field: {$f}"]);
        exit;
    }
}

if (empty($_POST['confirmation'])) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'You must confirm the declaration']);
    exit;
}

/**
 * ==================================================
 * FILE UPLOADS
 * ==================================================
 */
$uploadDir = __DIR__ . '/uploads/rusvuz/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

function upload_file(string $key): ?string {
    global $uploadDir;
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    $name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$key]['tmp_name'], $uploadDir . $name)) {
        return null;
    }
    return $name;
}

$passportFile     = upload_file('passport_file');
$certificatesFile = upload_file('certificates_file');
$signedForm       = upload_file('signed_form');

if (!$passportFile || !$certificatesFile) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Passport and certificates are required']);
    exit;
}

/**
 * ==================================================
 * DEGREE-SPECIFIC FILES
 * ==================================================
 */
$degree = $_POST['intended_study_level'];

$bachelorFile = upload_file('bachelor_file');
$masterFile   = upload_file('master_file');

if ($degree === "Master's" && !$bachelorFile) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>"Bachelor file required for Master's"]);
    exit;
}

if ($degree === "Ph.D/PG" && (!$bachelorFile || !$masterFile)) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>"Bachelor & Master files required for PhD"]);
    exit;
}

/**
 * ==================================================
 * PROGRAM SELECTION
 * ==================================================
 */
$bachelorProgram = $mastersProgram = $phdProgram = null;

if ($degree === "Bachelor's") $bachelorProgram = $_POST['field_of_study'];
if ($degree === "Master's")   $mastersProgram  = $_POST['field_of_study'];
if ($degree === "Ph.D/PG")    $phdProgram      = $_POST['field_of_study'];

/**
 * ==================================================
 * FLAGS & JSON FIELDS
 * ==================================================
 */
$studiedInRussia = ($_POST['studied_russia'] ?? 'No') === 'Yes' ? 1 : 0;
$studiedRussian  = ($_POST['studied_russian'] ?? 'No') === 'Yes' ? 1 : 0;
$submitted       = 1;

$selectedUniversities = null;
if (!empty($_POST['selected_universities'])) {
    $selectedUniversities = $_POST['selected_universities']; // already JSON
}

/**
 * ==================================================
 * SQL INSERT (MATCHES TABLE)
 * ==================================================
 */
$sql = "
INSERT INTO student_applications (
    user_id, first_name, middle_name, last_name, email, phone_number,
    gender, marital_status, country_of_birth, nationality, city_of_birth,
    dob, address_line1, passport_number, passport_issue_date, passport_expiry_date,
    destination, intended_study_level,
    bachelor_program, masters_program, phd_program,
    previous_institution_name, previous_institution_street,
    previous_study_start, previous_study_graduation,
    college_name, college_address, college_start_date, college_end_date,
    studied_in_russia, studied_in_russia_details,
    studied_russian_language, studied_russian_language_details,
    selected_universities,
    signed_application_form,
    application_date, submitted,
    session_id, academic_session,
    education_language
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    log_debug("Prepare error: ".$conn->error);
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'SQL prepare failed']);
    exit;
}

$stmt->bind_param(
    "ssssssssssssssssssssssssssssssiissssss",
    $_POST['user_id'],
    $_POST['first_name'],
    $_POST['middle_name'],
    $_POST['last_name'],
    $_POST['email'],
    $_POST['phone_number'],
    $_POST['gender'],
    $_POST['marital_status'],
    $_POST['country_of_birth'],
    $_POST['nationality'],
    $_POST['city_of_birth'],
    $_POST['dob'],
    $_POST['address'],
    $_POST['passport_number'],
    $_POST['passport_issue_date'],
    $_POST['passport_expiry_date'],
    $_POST['destination'],
    $_POST['intended_study_level'],
    $bachelorProgram,
    $mastersProgram,
    $phdProgram,
    $_POST['previous_institution_name'],
    $_POST['previous_institution_street'],
    $_POST['previous_study_start'],
    $_POST['previous_study_graduation'],
    $_POST['college_name'],
    $_POST['college_address'],
    $_POST['college_start_date'],
    $_POST['college_end_date'],
    $studiedInRussia,
    $_POST['studied_russia_details'],
    $studiedRussian,
    $_POST['studied_russian_details'],
    $selectedUniversities,
    $signedForm,
    $_POST['application_date'],
    $submitted,
    $_POST['session_id'],
    $_POST['academic_session'],
    $_POST['education_language']
);

if (!$stmt->execute()) {
    log_debug("Execute error: ".$stmt->error);
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$stmt->error]);
    exit;
}

$appId = $stmt->insert_id;
$stmt->close();

log_debug("SUCCESS application_id={$appId}");

echo json_encode([
    'status' => 'success',
    'message' => 'Application submitted successfully',
    'id' => $appId
]);
