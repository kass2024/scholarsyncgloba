<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/role.php';
require_once __DIR__ . '/../helpers/env_load.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * @param list<string> $toEmails
 */
function pcvc_send_commission_html_mail(array $toEmails, string $subject, string $htmlBody): bool
{
    $toEmails = array_values(array_filter(array_map('trim', $toEmails)));
    if ($toEmails === []) {
        return false;
    }

    $host = xander_env_get('SMTP_HOST');
    $user = xander_env_get('SMTP_USERNAME');
    $pass = xander_env_get('SMTP_PASSWORD');
    $portStr = xander_env_get('SMTP_PORT');
    $port = $portStr !== '' ? (int) $portStr : 465;
    $from = xander_env_get('SMTP_FROM_EMAIL');
    if ($from === '') {
        $from = $user;
    }
    $fromName = xander_env_get('SMTP_FROM_NAME');
    if ($fromName === '') {
        $fromName = 'ScholarSync Global';
    }

    if (!is_string($host) || $host === '' || !is_string($from) || $from === '') {
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = is_string($user) ? $user : '';
        $mail->Password = is_string($pass) ? $pass : '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $port > 0 ? $port : 465;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, $fromName);
        foreach ($toEmails as $addr) {
            if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($addr);
            }
        }
        if (count($mail->getToAddresses()) === 0) {
            return false;
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        return $mail->send();
    } catch (Exception $e) {
        error_log('commission mail: ' . $e->getMessage());

        return false;
    }
}

/**
 * @return list<string>
 */
function pcvc_superadmin_emails(mysqli $conn): array
{
    $out = [];
    $q = $conn->query("SELECT email, role FROM admins WHERE email IS NOT NULL AND TRIM(email) != ''");
    if (!$q) {
        return $out;
    }
    while ($row = $q->fetch_assoc()) {
        $em = trim((string) ($row['email'] ?? ''));
        $role = (string) ($row['role'] ?? '');
        if ($em !== '' && function_exists('pcvc_is_superadmin_role') && pcvc_is_superadmin_role($role)) {
            $out[] = $em;
        }
    }
    $q->free();

    return array_values(array_unique($out));
}
