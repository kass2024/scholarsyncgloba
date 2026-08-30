<?php
declare(strict_types=1);

/* =====================================================
   USE STATEMENTS (MUST BE FIRST)
===================================================== */
use PHPMailer\PHPMailer\PHPMailer;

/* =====================================================
   HEADERS & SAFE SETTINGS
===================================================== */
header('Content-Type: application/json');
set_time_limit(300);
ini_set('memory_limit', '256M');

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $logDir . '/php_fatal.log');
error_reporting(E_ALL);

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
$LOG = $logDir . '/application_email.log';

function logMsg(string $msg, $data = null): void
{
    global $LOG;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;

    if ($data !== null) {
        $line .= ' :: ' . (
            is_array($data) || is_object($data)
                ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                : (string)$data
        );
    }

    file_put_contents($LOG, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/* =====================================================
   PHP FATAL SHUTDOWN HANDLER
===================================================== */
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err !== null) {
        logMsg('🔥 PHP FATAL', $err);
    }
});

logMsg('=== APPLICATION EMAIL START ===');

/* =====================================================
   GLOBAL TRY / CATCH
===================================================== */
try {

    /* ================= SECURITY ================= */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logMsg('INVALID METHOD', $_SERVER['REQUEST_METHOD'] ?? 'NONE');
        exit;
    }

    /* ================= INPUT ================= */
    $applicationId = (int)($_POST['application_id'] ?? 0);
    if ($applicationId <= 0) {
        logMsg('INVALID APPLICATION ID');
        exit;
    }

    logMsg('APPLICATION ID', $applicationId);

    /* ================= FETCH APPLICATION ================= */
    $stmt = $conn->prepare("
        SELECT
            first_name,
            last_name,
            email,
            area_code,
            phone_number,
            destination,
            agent_first_name,
            agent_last_name,
            comments,
            submitted
        FROM student_applications
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    logMsg('APPLICATION FETCHED', $app);

    if (!$app || (int)$app['submitted'] !== 1) {
        logMsg('APPLICATION NOT FINAL OR NOT FOUND');
        exit;
    }

    /* ================= NORMALIZE ================= */
    $studentName  = trim($app['first_name'] . ' ' . $app['last_name']);
    $studentEmail = trim((string)$app['email']);
    $phone        = trim($app['area_code'] . ' ' . $app['phone_number']);
    $destination  = (string)$app['destination'];
    $agentName    = trim($app['agent_first_name'] . ' ' . $app['agent_last_name']);
    $comments     = nl2br(htmlspecialchars((string)$app['comments']));
    $submittedAt  = date('Y-m-d H:i:s');

    /* ================= STUDY CHOICES ================= */
    $choices = [];

   $q = $conn->prepare("
    SELECT
        u.name AS university_name,
        pl.name AS program_level,
        pl.abbreviation AS program_level_abbr,
        p.program_name
    FROM application_study_choices sc
    JOIN programs p ON p.id = sc.program_id
    JOIN universities u ON u.id = sc.university_id
    JOIN program_levels pl ON pl.id = sc.program_level_id
    WHERE sc.application_id = ?
    ORDER BY sc.id ASC
");


    if ($q) {
        $q->bind_param('i', $applicationId);
        $q->execute();
        $res = $q->get_result();
        while ($row = $res->fetch_assoc()) {
            $choices[] = $row;
        }
        $q->close();
    }

    logMsg('STUDY CHOICES', $choices);

    /* ================= BUILD CHOICES HTML ================= */
    $choicesHtml = '<h3>Study Choices</h3>';

    if (empty($choices)) {
        $choicesHtml .= '<p><em>No study choices recorded.</em></p>';
    } else {
        $i = 1;
        foreach ($choices as $c) {
            $choicesHtml .= "
                <div style='margin-bottom:12px;'>
                    <strong>Choice #{$i}</strong><br>
                    <strong>University:</strong> {$c['university_name']}<br>
                    <strong>Program Level:</strong> {$c['program_level']} ({$c['program_level_abbr']})<br>
                    <strong>Program:</strong> {$c['program_name']}
                </div>
            ";
            $i++;
        }
    }

    /* ================= MAILER ================= */
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

    $mail->SMTPDebug = 0;

    /* ================= ADMIN EMAIL ================= */
    $mail->addAddress('infos@scholarsyncglobal.ca');
    $mail->Subject = "New Application Submitted – $studentName";
    $mail->Body = "
<div style='font-family: Arial, Helvetica, sans-serif; font-size:14px; color:#333;'>

    <h2 style='color:#0a7c4a;'>New Student Application Submitted</h2>

    <table cellpadding='6' cellspacing='0' width='100%' style='border-collapse:collapse;'>
        <tr>
            <td width='30%'><strong>Application ID</strong></td>
            <td>$applicationId</td>
        </tr>
        <tr>
            <td><strong>Submission Date</strong></td>
            <td>$submittedAt</td>
        </tr>
    </table>

    <hr>

    <h3>Student Information</h3>
    <table cellpadding='6' cellspacing='0' width='100%' style='border-collapse:collapse;'>
        <tr>
            <td width='30%'><strong>Full Name</strong></td>
            <td>$studentName</td>
        </tr>
        <tr>
            <td><strong>Email</strong></td>
            <td>$studentEmail</td>
        </tr>
        <tr>
            <td><strong>Phone</strong></td>
            <td>$phone</td>
        </tr>
        <tr>
            <td><strong>Destination</strong></td>
            <td>$destination</td>
        </tr>
    </table>

    <hr>

    <h3>Study Choices</h3>
    $choicesHtml

    <hr>

    <h3>Agent Information</h3>
    <table cellpadding='6' cellspacing='0' width='100%' style='border-collapse:collapse;'>
        <tr>
            <td width='30%'><strong>Agent Name</strong></td>
            <td>$agentName</td>
        </tr>
    </table>

    <hr>

    <h3>Additional Comments</h3>
    <p>" . (!empty($comments) ? $comments : "<em>No additional comments provided.</em>") . "</p>

    <hr>

    <p style='font-size:12px; color:#777;'>
        This email was generated automatically by the Visa Consultant Canada application system.
    </p>

</div>
";


    $mail->send();
    logMsg('ADMIN EMAIL SENT');

    /* ================= STUDENT EMAIL ================= */
    if ($studentEmail !== '') {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'scholarsyncglobal.ca';
        $mail->SMTPAuth = true;
        $mail->Username = 'infos@scholarsyncglobal.ca';
        $mail->Password = getenv('SMTP_PASSWORD') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->isHTML(true);
        $mail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync Global');

        $mail->addAddress($studentEmail, $studentName);
        $mail->Subject = 'Application Submitted';
       $mail->Body = "
<div style='font-family: Arial, Helvetica, sans-serif; font-size:14px; color:#333;'>

    <p>Dear <strong>$studentName</strong>,</p>

    <p>
        We are pleased to inform you that your application has been
        <strong>successfully submitted</strong>.
    </p>

    <table cellpadding='6' cellspacing='0' width='100%' style='border-collapse:collapse;'>
        <tr>
            <td width='30%'><strong>Application ID</strong></td>
            <td>$applicationId</td>
        </tr>
        <tr>
            <td><strong>Destination</strong></td>
            <td>$destination</td>
        </tr>
    </table>

    <br>

    <h3>Your Selected University & Program</h3>
    $choicesHtml

    <br>

    <p>
        Our admissions team will now review your application and guide you
        through the next steps if any additional documents or actions are required.
    </p>

    <p>
        Thank you for choosing <strong>ScholarSync Global</strong>.
    </p>

    <br>

    <p>
        Kind regards,<br>
        <strong>ScholarSync Global</strong><br>
        <span style='font-size:12px; color:#777;'>Admissions Team</span>
    </p>

</div>
";


        $mail->send();
        logMsg('STUDENT EMAIL SENT');
    }

    $conn->close();
    logMsg('=== APPLICATION EMAIL END SUCCESS ===');

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
