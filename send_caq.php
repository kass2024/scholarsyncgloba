<?php
/**
 * Send CAQ (Certificat d'acceptation du Québec) PDF to applicant — Canada only.
 * Same pattern as send_admission.php but without university selection.
 *
 * POST: student_id, table, email, letter (PDF file)
 */
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/mailer.php';
require_once __DIR__ . '/helpers/caq_status.php';

use PHPMailer\PHPMailer\Exception as MailException;

$id    = (int) ($_POST['student_id'] ?? 0);
$table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($_POST['table'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));

$allowed_tables = ['student_applications'];

if ($id < 1 || $table === '' || !in_array($table, $allowed_tables, true)) {
    exit('Invalid input');
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit('Invalid email address');
}

pcvc_ensure_caq_column($conn);

$stmtDest = $conn->prepare('SELECT destination, first_name, last_name, masters_program, bachelor_program, phd_program FROM student_applications WHERE id = ? LIMIT 1');
if (!$stmtDest) {
    exit('Database error.');
}
$stmtDest->bind_param('i', $id);
$stmtDest->execute();
$row = $stmtDest->get_result()->fetch_assoc();
$stmtDest->close();

if (!$row) {
    exit('Application record not found.');
}

if (!pcvc_destination_is_canada((string) ($row['destination'] ?? ''))) {
    exit('CAQ is only available for Canada destination applicants.');
}

if (empty($_FILES['letter']) || (int) ($_FILES['letter']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    exit('Please upload the CAQ PDF letter.');
}

$file = $_FILES['letter'];
$tmp = (string) ($file['tmp_name'] ?? '');
$origName = (string) ($file['name'] ?? 'CAQ.pdf');

if ($tmp === '' || !is_uploaded_file($tmp)) {
    exit('Invalid upload.');
}

$mime = '';
if (function_exists('finfo_open')) {
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) {
        $mime = (string) finfo_file($fi, $tmp);
        finfo_close($fi);
    }
}
if ($mime !== '' && stripos($mime, 'pdf') === false && $mime !== 'application/octet-stream') {
    exit('Only PDF files are allowed for CAQ letters.');
}

$uploadDir = __DIR__ . '/admission_letters/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$filename = uniqid('caq_', true) . '.pdf';
$filepath = $uploadDir . $filename;

if (!move_uploaded_file($tmp, $filepath)) {
    exit('Failed to save the CAQ PDF on the server.');
}

$studentName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
$program = trim((string) (
    $row['masters_program']
    ?? $row['bachelor_program']
    ?? $row['phd_program']
    ?? ''
));

$safeBase = preg_replace('/[^a-zA-Z0-9_\-\s]/', '_', pathinfo($origName, PATHINFO_FILENAME));
$safeBase = trim(preg_replace('/_+/', '_', (string) $safeBase), '_');
if ($safeBase === '') {
    $safeBase = 'CAQ';
}
$attachName = $safeBase . '_CAQ.pdf';
if (strlen($attachName) > 180) {
    $attachName = substr($attachName, 0, 180) . '.pdf';
}

xander_load_env_file();
if (xander_env_get('SMTP_PASSWORD') === '') {
    @unlink($filepath);
    error_log('CAQ email failed: SMTP_PASSWORD is not set in .env');
    exit('Email is not configured. Set SMTP_PASSWORD in .env and try again.');
}

$programHtml = $program !== ''
    ? '<p>Program context: <em>' . htmlspecialchars($program, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</em></p>'
    : '';

try {
    $mail = app_admission_mailer();
    $mail->addAddress($email, $studentName);
    $mail->Subject = '=?UTF-8?B?' . base64_encode('Your CAQ (Certificat d’acceptation du Québec)') . '?=';
    $mail->Body = '
        <p>Dear ' . htmlspecialchars($studentName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</p>
        <p>Congratulations! Your <strong>Certificat d’acceptation du Québec (CAQ)</strong> for studies has been issued.</p>
        ' . $programHtml . '
        <p>Please find your official CAQ attestation letter attached to this email.</p>
        <p>You may now proceed with your study permit application to Immigration, Refugees and Citizenship Canada (IRCC), including this letter.</p>
        <p>If you have any questions, feel free to reach out.</p>
        <p>Warm regards,<br><strong>ScholarSync Global Team</strong></p>
    ';
    $mail->addAttachment($filepath, $attachName);
    $mail->send();

    $allFlags = [
        'incomplete_app', 'submitted', 'sent_to_platform', 'app_paid', 'admit', 'caq', 'i20_sent', 'sevis_paid',
        'visa_scheduled', 'visa_approved', 'enrolled', 'addn_doc', 'deny', 'app_start',
    ];
    $existing = [];
    foreach ($allFlags as $f) {
        $chk = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($f) . "'");
        if ($chk && $chk->num_rows > 0) {
            $existing[] = $f;
        }
    }
    if ($existing === []) {
        @unlink($filepath);
        exit('Email sent, but CAQ status column is missing.');
    }

    $resetFlags = implode(', ', array_map(static fn ($f) => "`$f` = 0", $existing));
    $updateSQL = "UPDATE `$table` SET $resetFlags, `caq` = 1 WHERE id = ?";
    $stmtUp = $conn->prepare($updateSQL);
    if (!$stmtUp) {
        @unlink($filepath);
        error_log('CAQ email sent but DB update prepare failed: ' . $conn->error);
        exit('Email sent, but failed to update applicant status. Please refresh and check the record.');
    }
    $stmtUp->bind_param('i', $id);
    if (!$stmtUp->execute()) {
        $stmtUp->close();
        @unlink($filepath);
        error_log('CAQ email sent but DB update failed: ' . $conn->error);
        exit('Email sent, but failed to update applicant status. Please refresh and check the record.');
    }
    $stmtUp->close();

    @unlink($filepath);
    echo 'ok';
} catch (MailException $e) {
    @unlink($filepath);
    $info = $e->getMessage();
    error_log('CAQ email failed: ' . $info);
    if (stripos($info, 'authenticate') !== false || stripos($info, '535') !== false) {
        exit('Email server login failed. Update SMTP_PASSWORD in .env with the current admission@ mailbox password.');
    }
    exit('mail_error');
} catch (Throwable $e) {
    @unlink($filepath);
    error_log('CAQ email failed: ' . $e->getMessage());
    exit('mail_error');
}
