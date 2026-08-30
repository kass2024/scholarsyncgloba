<?php
declare(strict_types=1);

/* =====================================================
   USE STATEMENTS
===================================================== */
use PHPMailer\PHPMailer\PHPMailer;

/* =====================================================
   HEADERS & SETTINGS
===================================================== */
header('Content-Type: application/json; charset=utf-8');
set_time_limit(300);
ini_set('memory_limit', '256M');

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', $logDir . '/job_email_fatal.log');

/* =====================================================
   BOOTSTRAP
===================================================== */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

/* =====================================================
   LOGGER
===================================================== */
$LOG = $logDir . '/job_application_email.log';

function logMsg(string $msg, $data = null): void {
    global $LOG;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data !== null) {
        $line .= ' :: ' . (
            is_array($data) || is_object($data)
                ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                : $data
        );
    }
    file_put_contents($LOG, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/* =====================================================
   FATAL HANDLER
===================================================== */
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err !== null) {
        logMsg('🔥 PHP FATAL', $err);
    }
});

logMsg('=== JOB APPLICATION EMAIL START ===');

try {

    /* ================= SECURITY ================= */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        exit;
    }

    $userId = trim($_POST['user_id'] ?? '');
    if ($userId === '') {
        logMsg('MISSING USER ID');
        exit;
    }

    /* ================= FETCH APPLICATION ================= */
    $stmt = $conn->prepare("
        SELECT *
        FROM job_applications
        WHERE user_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$app) {
        logMsg('APPLICATION NOT FOUND', $userId);
        exit;
    }

    logMsg('APPLICATION FETCHED', $app);

    /* ================= FETCH DOCUMENTS ================= */
    $docs = [];
    $q = $conn->prepare("
        SELECT document_type, file_path
        FROM job_documents
        WHERE user_id = ?
    ");
    if ($q) {
        $q->bind_param('s', $userId);
        $q->execute();
        $res = $q->get_result();
        while ($row = $res->fetch_assoc()) {
            $docs[] = $row;
        }
        $q->close();
    }

    /* ================= NORMALIZE ================= */
    $fullName = trim($app['first_name'] . ' ' . $app['last_name']);
    $email    = $app['email'];
    $phone    = $app['phone_area_code'] . ' ' . $app['phone_number'];

    /* ================= DOCUMENT LIST ================= */
    $docsHtml = '<ul>';
    if (empty($docs)) {
        $docsHtml .= '<li>No documents uploaded</li>';
    } else {
        foreach ($docs as $d) {
            $docsHtml .= '<li>' . ucfirst(str_replace('_',' ', $d['document_type'])) . '</li>';
        }
    }
    $docsHtml .= '</ul>';

    /* =====================================================
       ADMIN EMAIL
    ===================================================== */
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'scholarsyncglobal.ca';
    $mail->SMTPAuth = true;
    $mail->Username = 'infos@scholarsyncglobal.ca';
    $mail->Password = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    $mail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync Global');

    $mail->addAddress('infos@scholarsyncglobal.ca');
    $mail->Subject = "New Job Application – $fullName";

    $mail->Body = "
<h2>New Job Application Submitted</h2>

<table cellpadding='6'>
<tr><td><strong>Applicant</strong></td><td>$fullName</td></tr>
<tr><td><strong>Email</strong></td><td>$email</td></tr>
<tr><td><strong>Phone</strong></td><td>$phone</td></tr>
<tr><td><strong>User ID</strong></td><td>$userId</td></tr>
</table>

<h3>Address</h3>
<p>
{$app['province_state']}, {$app['district']}<br>
{$app['sector']}, {$app['cell_ward']}<br>
{$app['village']}
</p>

<h3>Emergency Contact</h3>
<p>
{$app['emergency_full_name']} ({$app['emergency_relationship']})<br>
{$app['emergency_email']}<br>
{$app['emergency_area_code']} {$app['emergency_phone_number']}
</p>

<h3>Uploaded Documents</h3>
$docsHtml
";

    $mail->send();
    logMsg('ADMIN EMAIL SENT');

    /* =====================================================
       APPLICANT EMAIL
    ===================================================== */
    if ($email !== '') {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'scholarsyncglobal.ca';
        $mail->SMTPAuth = true;
        $mail->Username = 'infos@scholarsyncglobal.ca';
        $mail->Password = getenv('SMTP_PASSWORD') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync Global');

        $mail->addAddress($email, $fullName);
        $mail->Subject = 'Job Application Submitted Successfully';

        $mail->Body = "
<p>Dear <strong>$fullName</strong>,</p>

<p>
Thank you for submitting your job application with
<strong>ScholarSync Global</strong>.
</p>

<p>
Your application has been received and is currently under review.
Our recruitment team will contact you if additional information
is required.
</p>

<p>
<strong>Reference ID:</strong> $userId
</p>

<p>
Kind regards,<br>
<strong>ScholarSync Global</strong><br>
Recruitment Team
</p>
";

        $mail->send();
        logMsg('APPLICANT EMAIL SENT');
    }

    $conn->close();
    logMsg('=== JOB APPLICATION EMAIL END SUCCESS ===');

    echo json_encode(['status' => 'ok']);
    exit;

} catch (Throwable $e) {

    logMsg('💥 EXCEPTION', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine()
    ]);

    http_response_code(500);
    echo json_encode(['status' => 'error']);
    exit;
}
