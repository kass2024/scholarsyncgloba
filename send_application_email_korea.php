<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* =====================================================
   SAFE BOOTSTRAP
===================================================== */
header('Content-Type: application/json');
set_time_limit(300);
ini_set('memory_limit', '512M');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

/* =====================================================
   LOGGING
===================================================== */
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$LOG = $logDir . '/korean_application_email.log';

function logMsg(string $msg, $data = null): void {
    global $LOG;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data !== null) {
        $line .= ' :: ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    file_put_contents($LOG, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/* =====================================================
   INPUT VALIDATION
===================================================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$applicationId = (int)($_POST['application_id'] ?? 0);
if ($applicationId <= 0) {
    logMsg('INVALID APPLICATION ID');
    exit;
}

/* =====================================================
   FETCH APPLICATION
===================================================== */
$stmt = $conn->prepare("
    SELECT *
    FROM student_applications
    WHERE id = ?
      AND submitted = 1
    LIMIT 1
");
$stmt->bind_param('i', $applicationId);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) {
    logMsg('APPLICATION NOT FOUND', $applicationId);
    exit;
}

/* =====================================================
   NORMALIZE CORE INFO
===================================================== */
$studentName  = trim($app['first_name'] . ' ' . $app['last_name']);
$studentEmail = (string)$app['email'];
$phone        = trim(($app['area_code'] ?? '') . ' ' . ($app['phone_number'] ?? ''));
$agentName    = trim(($app['agent_first_name'] ?? '') . ' ' . ($app['agent_last_name'] ?? ''));
$submittedAt  = (string)$app['updated_at'];

/* =====================================================
   STUDY SELECTION (FORCED)
===================================================== */
$studyHtml = '<ul><li><strong>Destination Country:</strong> South Korea</li></ul>';

/* =====================================================
   ADMIN EMAIL SETUP
===================================================== */
$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host       = 'scholarsyncglobal.ca';
$mail->SMTPAuth   = true;
$mail->Username   = 'infos@scholarsyncglobal.ca';
$mail->Password   = getenv('SMTP_PASSWORD') ?: '';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
$mail->Port       = 465;

$mail->CharSet = 'UTF-8';
$mail->isHTML(true);
$mail->setFrom('infos@scholarsyncglobal.ca', 'Visa Consultant Canada');
$mail->addAddress('infos@scholarsyncglobal.ca');
$mail->addAddress('ukpi2023@gmail.com');
$mail->addAddress('ujeanmethode@gmail.com');
$mail->Subject = "Korean Application Submitted – {$studentName}";

/* =====================================================
   EMAIL BODY
===================================================== */
$mail->Body = "
<h2>Korean Student Application</h2>

<h3>Application Summary</h3>
<ul>
  <li><strong>Application ID:</strong> {$applicationId}</li>
  <li><strong>Submitted At:</strong> {$submittedAt}</li>
</ul>

<h3>Student Information</h3>
<ul>
  <li><strong>Name:</strong> {$studentName}</li>
  <li><strong>Email:</strong> {$studentEmail}</li>
  <li><strong>Phone:</strong> {$phone}</li>
  <li><strong>Gender:</strong> {$app['gender']}</li>
  <li><strong>Date of Birth:</strong> {$app['dob']}</li>
  <li><strong>Nationality:</strong> {$app['nationality']}</li>
  <li><strong>Passport No:</strong> {$app['passport_number']}</li>
</ul>

<h3>Address</h3>
<p>
{$app['address_line1']}<br>
{$app['address_line2']}<br>
{$app['city']}, {$app['state_province']} {$app['postal_code']}
</p>

<h3>Study Selection</h3>
{$studyHtml}

<h3>Agent</h3>
<p>{$agentName} ({$app['agent_email']})</p>

<h3>Comments</h3>
<p>" . nl2br(htmlspecialchars((string)$app['comments'])) . "</p>
";

/* =====================================================
   DOCUMENT LABELS
===================================================== */
$DOCUMENT_LABELS = [
    'korean_photo_uploaded'                  => 'Passport Photo',
    'valid_passport'                         => 'Passport Copy',
    'final_certificate_uploaded'             => 'Final Certificate',
    'final_transcript_uploaded'              => 'Academic Transcript',
    'translator_confirmation_uploaded'       => 'Translator Confirmation',
    'parent_income_statement_uploaded'       => 'Parent Income Statement',
    'parent_employment_certificate_uploaded' => 'Parent Employment Certificate',
    'parent_business_certificate_uploaded'   => 'Parent Business Certificate',
    'bank_balance_certificate_uploaded'      => 'Bank Balance Certificate',
    'applicant_id_uploaded'                  => 'Applicant ID',
    'father_id_uploaded'                     => 'Father ID',
    'mother_id_uploaded'                     => 'Mother ID',
    'birth_certificate_translated_uploaded'  => 'Birth Certificate (Translated)',
    'self_introduction_letter_uploaded'      => 'Self Introduction Letter',
    'study_plan_uploaded'                    => 'Study Plan',
    'personal_information_consent_uploaded'  => 'Personal Information Consent'
];

/* =====================================================
   ATTACH DOCUMENTS (HUMAN NAMES)
===================================================== */
foreach ($DOCUMENT_LABELS as $field => $label) {

    if (empty($app[$field])) {
        continue;
    }

    $value = trim((string)$app[$field]);

    // MULTI FILE
    if ($value !== '' && $value[0] === '[') {

        $files = json_decode($value, true);
        if (!is_array($files)) continue;

        $i = 1;
        foreach ($files as $path) {
            $full = __DIR__ . '/' . ltrim($path, '/');
            if (!is_file($full)) continue;

            $ext = pathinfo($full, PATHINFO_EXTENSION);
            $mail->addAttachment($full, "{$label} {$i}.{$ext}");
            $i++;
        }
        continue;
    }

    // SINGLE FILE
    $full = __DIR__ . '/' . ltrim($value, '/');
    if (!is_file($full)) continue;

    $ext = pathinfo($full, PATHINFO_EXTENSION);
    $mail->addAttachment($full, "{$label}.{$ext}");
}

/* =====================================================
   SEND ADMIN EMAIL
===================================================== */
$mail->send();
logMsg('ADMIN EMAIL SENT', $applicationId);

/* =====================================================
   STUDENT CONFIRMATION EMAIL
===================================================== */
if ($studentEmail !== '') {

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'scholarsyncglobal.ca';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'infos@scholarsyncglobal.ca';
    $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    $mail->setFrom('infos@scholarsyncglobal.ca', 'Visa Consultant Canada');
    $mail->addAddress($studentEmail, $studentName);
    $mail->Subject = 'Your Korean Application Has Been Submitted';

    $mail->Body = "
<p>Dear <strong>{$studentName}</strong>,</p>

<p>Your application has been successfully submitted.</p>

<p><strong>Application ID:</strong> {$applicationId}</p>

<h3>Study Destination</h3>
<p><strong>South Korea</strong></p>

<p>Our admissions team will contact you if additional steps are required.</p>

<p>Kind regards,<br><strong>Visa Consultant Canada</strong></p>
";

    $mail->send();
    logMsg('STUDENT EMAIL SENT', $studentEmail);
}

$conn->close();
echo json_encode(['status' => 'success']);
exit;
