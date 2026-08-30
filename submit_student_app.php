<?php
/**
 * submit_student_app.php
 * AJAX-ONLY ENDPOINT
 * FINAL PRODUCTION VERSION
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/attendance_checkout.php';
/* =======================
   HARDENING
======================= */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('UTC');

/* =======================
   AUTH
======================= */
if (empty($_SESSION['admin_id'])) {
    echo "unauthorized";
    exit;
}
$admin_id = (int) $_SESSION['admin_id'];

/* =======================
   INPUT
======================= */
$job_id              = (int) ($_POST['job_id'] ?? 0);
$applicant_name      = trim($_POST['applicant_name'] ?? '');
$email               = trim($_POST['email'] ?? '');
$platform             = trim($_POST['platform'] ?? '');
$destination_country = trim($_POST['destination'] ?? '');
$university_id        = (int) ($_POST['university_id'] ?? 0);
$phone_number         = trim($_POST['phone_number'] ?? '');
$country              = trim($_POST['country'] ?? 'Rwanda');
$city                 = trim($_POST['city'] ?? 'Kigali');
$status_app           = trim($_POST['status'] ?? 'Submitted');
$application_remarks  = trim($_POST['application_remarks'] ?? '');
$subagent             = trim($_POST['subagent'] ?? '');

/* =======================
   BASIC VALIDATION
======================= */
if ($job_id <= 0 || $applicant_name === '' || $email === '') {
    echo "Invalid input";
    exit;
}

/* =======================
   SCREENSHOT (REQUIRED)
======================= */
if (
    empty($_FILES['screenshot']) ||
    $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK
) {
    echo "Screenshot is required";
    exit;
}

/* Validate image */
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['screenshot']['tmp_name']);

if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
    echo "Invalid screenshot type";
    exit;
}

if ($_FILES['screenshot']['size'] > 5 * 1024 * 1024) {
    echo "Screenshot too large (max 5MB)";
    exit;
}

/* Save screenshot */
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    echo "Upload directory missing";
    exit;
}

$ext = $mime === 'image/png' ? 'png' : 'jpg';
$filename = 'job_' . $job_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$relativePath = 'uploads/' . $filename;

if (!move_uploaded_file($_FILES['screenshot']['tmp_name'], $uploadDir . $filename)) {
    echo "Failed to save screenshot";
    exit;
}

/* =======================
   SAVE SCREENSHOT PATH (THIS WAS MISSING)
======================= */
$upd = $conn->prepare("
    UPDATE job_list
    SET screenshot_path = ?
    WHERE id = ?
");
$upd->bind_param("si", $relativePath, $job_id);
$upd->execute();
$upd->close();

/* =======================
   FETCH JOB TYPE
======================= */
$job_type = '';
$stmt = $conn->prepare("SELECT job_type FROM job_list WHERE id=?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$stmt->bind_result($job_type);
$stmt->fetch();
$stmt->close();

if ($job_type === '') {
    echo "Invalid job";
    exit;
}

/* =======================
   UNIQUE APPLICATION ID
======================= */
do {
    $application_id = "APP-" . strtoupper(bin2hex(random_bytes(6)));
    $chk = $conn->prepare("SELECT id FROM student_app WHERE application_id=?");
    $chk->bind_param("s", $application_id);
    $chk->execute();
    $chk->store_result();
    $exists = $chk->num_rows > 0;
    $chk->close();
} while ($exists);

/* =======================
   UNIVERSITY NAME
======================= */
$university_name = '';
if ($university_id > 0) {
    $u = $conn->prepare("SELECT name FROM universities WHERE id=?");
    $u->bind_param("i", $university_id);
    $u->execute();
    $u->bind_result($university_name);
    $u->fetch();
    $u->close();
}

/* =======================
   INSERT STUDENT APP (UNCHANGED)
======================= */
$stmt = $conn->prepare("
    INSERT INTO student_app (
        applicant_name,
        application_id,
        email,
        platform,
        destination_country,
        university_id,
        phone_number,
        country,
        city,
        status_app,
        application_remarks,
        subagent,
        job_id,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param(
    "sssssiisssssi",
    $applicant_name,
    $application_id,
    $email,
    $platform,
    $destination_country,
    $university_id,
    $phone_number,
    $country,
    $city,
    $status_app,
    $application_remarks,
    $subagent,
    $job_id
);

if (!$stmt->execute()) {
    echo "Database error";
    exit;
}
$stmt->close();

/* =======================
   GOOGLE SHEETS (UNCHANGED)
======================= */
try {
    require_once __DIR__ . '/vendor/autoload.php';
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/credentials.json');

    $sheetId = null;
    $s = $conn->prepare("SELECT sheet_id FROM admins WHERE id=?");
    $s->bind_param("i", $admin_id);
    $s->execute();
    $s->bind_result($sheetId);
    $s->fetch();
    $s->close();

    if ($sheetId) {
        $client = new Google_Client();
        $client->useApplicationDefaultCredentials();
        $client->addScope(Google_Service_Sheets::SPREADSHEETS);

        $service = new Google_Service_Sheets($client);

        $rows = $service->spreadsheets_values
            ->get($sheetId, "Sheet1!A:A")
            ->getValues();

        $row = max(count($rows) + 1, 3);
        $sn  = $row - 2;

        $values = [[
            $sn,
            $applicant_name,
            "",
            $email,
            $platform,
            $job_type,
            $destination_country,
            $university_name,
            $phone_number,
            $country,
            $city,
            $status_app,
            $application_remarks,
            $subagent,
            date("Y-m-d H:i:s")
        ]];

        $body = new Google_Service_Sheets_ValueRange(['values' => $values]);

        $service->spreadsheets_values->update(
            $sheetId,
            "Sheet1!A{$row}:O{$row}",
            $body,
            ['valueInputOption' => 'RAW']
        );
    }
} catch (Throwable $e) {
    error_log("Google Sheets error: " . $e->getMessage());
}

/* =======================
   MARK JOB COMPLETED
======================= */
pcvc_job_list_mark_completed($conn, $job_id, date('Y-m-d H:i:s'));

/* =======================
   SUCCESS
======================= */
$_SESSION['job_save_success'] = 'Student admission job was saved and marked completed.';
echo 'success';
exit;
