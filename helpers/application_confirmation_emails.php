<?php
/**
 * Sends admin + applicant confirmation emails after job / Form 17 visa applications.
 * Failures are logged only; callers should not block success on mail errors.
 */
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/mail_smtp.php';

function xander_h(string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @param mysqli $conn
 */
function xander_send_job_application_confirmation_emails($conn, string $userId, string $referenceDisplay): void
{
    $stmt = $conn->prepare('SELECT * FROM job_applications WHERE user_id = ? LIMIT 1');
    if (!$stmt) {
        error_log('[JOB CONFIRM MAIL] prepare failed: ' . $conn->error);
        return;
    }
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$app) {
        error_log('[JOB CONFIRM MAIL] no application for user_id=' . $userId);
        return;
    }

    $docs = [];
    $q = $conn->prepare('SELECT document_type, file_path FROM job_documents WHERE user_id = ?');
    if ($q) {
        $q->bind_param('s', $userId);
        $q->execute();
        $res = $q->get_result();
        while ($row = $res->fetch_assoc()) {
            $docs[] = $row;
        }
        $q->close();
    }

    $fullName = trim((string) ($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? ''));
    $email = trim((string) ($app['email'] ?? ''));
    $phone = trim((string) (($app['phone_area_code'] ?? '') . ' ' . ($app['phone_number'] ?? '')));

    $docsHtml = '<ul>';
    if ($docs === []) {
        $docsHtml .= '<li>No documents uploaded</li>';
    } else {
        foreach ($docs as $d) {
            $docsHtml .= '<li>' . xander_h(ucfirst(str_replace('_', ' ', (string) $d['document_type']))) . '</li>';
        }
    }
    $docsHtml .= '</ul>';

    try {
        $mail = xander_create_phpmailer();
        $mail->isHTML(true);
        $mail->clearAddresses();
        $mail->addAddress('infos@scholarsyncglobal.ca');
        $mail->Subject = 'New Job Application – ' . $fullName;
        $mail->Body = '
<h2>New Job Application Submitted</h2>
<table cellpadding="6">
<tr><td><strong>Applicant</strong></td><td>' . xander_h($fullName) . '</td></tr>
<tr><td><strong>Email</strong></td><td>' . xander_h($email) . '</td></tr>
<tr><td><strong>Phone</strong></td><td>' . xander_h($phone) . '</td></tr>
<tr><td><strong>Reference</strong></td><td>' . xander_h($referenceDisplay) . '</td></tr>
<tr><td><strong>User ID</strong></td><td>' . xander_h($userId) . '</td></tr>
</table>
<h3>Address</h3>
<p>'
            . xander_h($app['province_state'] ?? '') . ', ' . xander_h($app['district'] ?? '') . '<br>'
            . xander_h($app['sector'] ?? '') . ', ' . xander_h($app['cell_ward'] ?? '') . '<br>'
            . xander_h($app['village'] ?? '') . '
</p>
<h3>Emergency Contact</h3>
<p>'
            . xander_h($app['emergency_full_name'] ?? '') . ' (' . xander_h($app['emergency_relationship'] ?? '') . ')<br>'
            . xander_h($app['emergency_email'] ?? '') . '<br>'
            . xander_h(($app['emergency_area_code'] ?? '') . ' ' . ($app['emergency_phone_number'] ?? '')) . '
</p>
<h3>Uploaded Documents</h3>' . $docsHtml;
        $mail->send();
    } catch (Throwable $e) {
        error_log('[JOB CONFIRM MAIL] admin send failed: ' . $e->getMessage());
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log('[JOB CONFIRM MAIL] skip applicant: invalid email');
        return;
    }

    try {
        $mail = xander_create_phpmailer_applicant_sender();
        $mail->isHTML(true);
        $mail->clearAddresses();
        $mail->addAddress($email, $fullName);
        $mail->Subject = 'Job Application Submitted Successfully';
        $mail->Body = '
<p>Dear <strong>' . xander_h($fullName) . '</strong>,</p>
<p>Thank you for submitting your job application with <strong>ScholarSync Global</strong>.</p>
<p>Your application has been received and is currently under review. Our recruitment team will contact you if additional information is required.</p>
<p><strong>Reference ID:</strong> ' . xander_h($referenceDisplay) . '</p>
<p>Kind regards,<br><strong>ScholarSync Global</strong><br>Recruitment Team</p>';
        $mail->send();
    } catch (Throwable $e) {
        error_log('[JOB CONFIRM MAIL] applicant send failed: ' . $e->getMessage());
    }
}

/**
 * @param mysqli $conn
 * @param array<string,mixed> $applicationRow Row from form_17_applications (personal fields + visa fields)
 */
function xander_send_form17_visa_confirmation_emails($conn, string $userId, string $referenceDisplay, array $applicationRow): void
{
    $first = trim((string) ($applicationRow['first_name'] ?? ''));
    $last = trim((string) ($applicationRow['last_name'] ?? ''));
    $fullName = trim($first . ' ' . $last);
    $email = trim((string) ($applicationRow['email'] ?? ''));
    $mobile = trim((string) ($applicationRow['applicant_mobile'] ?? ''));
    $visaType = trim((string) ($applicationRow['visa_type'] ?? ''));
    $destination = trim((string) ($applicationRow['country_to_visit'] ?? ''));

    try {
        $mail = xander_create_phpmailer();
        $mail->isHTML(true);
        $mail->clearAddresses();
        $mail->addAddress('infos@scholarsyncglobal.ca');
        $mail->Subject = 'New Visa Application (Form 17) – ' . $fullName;
        $mail->Body = '
<h2>New Visa Application Submitted</h2>
<table cellpadding="6">
<tr><td><strong>Applicant</strong></td><td>' . xander_h($fullName) . '</td></tr>
<tr><td><strong>Email</strong></td><td>' . xander_h($email) . '</td></tr>
<tr><td><strong>Mobile</strong></td><td>' . xander_h($mobile) . '</td></tr>
<tr><td><strong>Visa type</strong></td><td>' . xander_h($visaType) . '</td></tr>
<tr><td><strong>Destination</strong></td><td>' . xander_h($destination) . '</td></tr>
<tr><td><strong>Reference</strong></td><td>' . xander_h($referenceDisplay) . '</td></tr>
<tr><td><strong>Application user_id</strong></td><td>' . xander_h($userId) . '</td></tr>
</table>';
        $mail->send();
    } catch (Throwable $e) {
        error_log('[VISA CONFIRM MAIL] admin send failed: ' . $e->getMessage());
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log('[VISA CONFIRM MAIL] skip applicant: invalid email');
        return;
    }

    try {
        $mail = xander_create_phpmailer_applicant_sender();
        $mail->isHTML(true);
        $mail->clearAddresses();
        $mail->addAddress($email, $fullName);
        $mail->Subject = 'Visa Application Submitted Successfully';
        $mail->Body = '
<p>Dear <strong>' . xander_h($fullName) . '</strong>,</p>
<p>Thank you for submitting your visa application with <strong>ScholarSync Global</strong>.</p>
<p>Your application has been received and is under review. We will contact you within a few business days if we need anything further.</p>
<p><strong>Visa type you applied for:</strong> ' . xander_h($visaType !== '' ? $visaType : '—') . '<br>
<strong>Destination:</strong> ' . xander_h($destination !== '' ? $destination : '—') . '</p>
<p><strong>Reference ID:</strong> ' . xander_h($referenceDisplay) . '</p>
<p><strong>Application ID:</strong> ' . xander_h($userId) . '</p>
<p>Kind regards,<br><strong>ScholarSync Global</strong></p>';
        $mail->send();
    } catch (Throwable $e) {
        error_log('[VISA CONFIRM MAIL] applicant send failed: ' . $e->getMessage());
    }
}
