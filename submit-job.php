<?php
/**
 * submit-job.php — Other Job completion (screenshot required)
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/attendance_checkout.php';

date_default_timezone_set('UTC');
ini_set('display_errors', '0');
error_reporting(E_ALL);

function submit_job_fail(string $message, bool $ajax = true): void
{
    if ($ajax) {
        echo $message;
        exit;
    }
    exit($message);
}

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    submit_job_fail('Access denied');
}

$admin_id = (int) $_SESSION['id'];
$today    = date('Y-m-d');

$job_id          = (int) ($_POST['job_id'] ?? 0);
$job_title       = trim($_POST['job_title'] ?? '');
$job_description = trim($_POST['job_description'] ?? '');

if ($job_id <= 0 || $job_title === '' || $job_description === '') {
    submit_job_fail('Missing required data');
}

if (
    empty($_FILES['screenshot'])
    || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK
) {
    submit_job_fail('Screenshot is required');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['screenshot']['tmp_name']);

if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
    submit_job_fail('Invalid screenshot type. Use PNG or JPG.');
}

if ((int) $_FILES['screenshot']['size'] > 5 * 1024 * 1024) {
    submit_job_fail('Screenshot too large (max 5 MB)');
}

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    submit_job_fail('Upload directory missing');
}

$ext = $mime === 'image/png' ? 'png' : 'jpg';
$filename = 'job_' . $job_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$relativePath = 'uploads/' . $filename;
$absolutePath = $uploadDir . $filename;

if (!move_uploaded_file($_FILES['screenshot']['tmp_name'], $absolutePath)) {
    submit_job_fail('Failed to save screenshot');
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO jobs (
            admin_id,
            attendance_id,
            date,
            job_title,
            job_description,
            hours_spent,
            productivity_score,
            remarks,
            ai_suggestions,
            created_at
        ) VALUES (
            ?, NULL, ?, ?, ?, 0, 0, '', '', NOW()
        )
    ");

    $stmt->bind_param(
        'isss',
        $admin_id,
        $today,
        $job_title,
        $job_description
    );

    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error);
    }

    $entry_id = (int) $stmt->insert_id;
    $stmt->close();

    $completedAt = date('Y-m-d H:i:s');

    $upd = $conn->prepare('UPDATE job_list SET screenshot_path = ? WHERE id = ?');
    $upd->bind_param('si', $relativePath, $job_id);
    $upd->execute();
    $upd->close();

    pcvc_job_list_mark_completed($conn, $job_id, $completedAt);

    try {
        require __DIR__ . '/vendor/autoload.php';
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/credentials.json');

        $client = new Google_Client();
        $client->useApplicationDefaultCredentials();
        $client->addScope(Google_Service_Sheets::SPREADSHEETS);

        $service = new Google_Service_Sheets($client);
        $spreadsheetId = '1Bt9UirQs8RR7RxlzbZXEOO6XORPhu3OMJrMstmOz_GY';

        $values = [[
            $entry_id,
            $admin_id,
            $today,
            $job_title,
            $job_description,
            0,
            0,
            '',
            '',
            date('Y-m-d H:i:s'),
        ]];

        $body = new Google_Service_Sheets_ValueRange(['values' => $values]);
        $service->spreadsheets_values->append(
            $spreadsheetId,
            'Sheet1!A:J',
            $body,
            ['valueInputOption' => 'RAW']
        );
    } catch (Throwable $e) {
        error_log('Sheets error: ' . $e->getMessage());
    }

    $conn->commit();

    $_SESSION['job_save_success'] = 'Job "' . $job_title . '" was saved and marked completed.';
    echo 'success';
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    @unlink($absolutePath);
    error_log('JOB SAVE ERROR: ' . $e->getMessage());
    submit_job_fail('Failed to save job.');
}
