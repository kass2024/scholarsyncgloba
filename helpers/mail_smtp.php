<?php
/**
 * Shared PHPMailer SMTP — delegates to helpers/mailer.php (.env SMTP_*).
 */
require_once __DIR__ . '/mailer.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * @return PHPMailer Configured for SMTP; caller sets recipients, subject, body.
 */
function xander_create_phpmailer(): PHPMailer
{
    return app_mailer('ScholarSync Global');
}

/**
 * SMTP identity used for outbound mail to applicants (matches send-job-Email / legacy scripts).
 */
function xander_create_phpmailer_applicant_sender(): PHPMailer
{
    return app_admission_mailer();
}

/**
 * Simple HTML email send with optional attachments and BCC copies.
 *
 * @param array<int, array{path:string, name?:string}> $attachments
 * @param string[] $bcc
 */
function sendSMTPMail(string $to, string $subject, string $htmlBody, array $attachments = [], array $bcc = []): bool
{
    return sendSMTPMailDetailed($to, $subject, $htmlBody, $attachments, $bcc)['ok'];
}

/**
 * @param array<int, array{path:string, name?:string}> $attachments
 * @param string[] $bcc
 * @return array{ok: bool, error?: string}
 */
function sendSMTPMailDetailed(
    string $to,
    string $subject,
    string $htmlBody,
    array $attachments = [],
    array $bcc = [],
    ?callable $mailerFactory = null
): array {
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient email'];
    }

    try {
        $mail = $mailerFactory ? $mailerFactory() : xander_create_phpmailer_applicant_sender();
        $mail->clearAddresses();
        $mail->clearAttachments();
        $mail->clearBCCs();
        $mail->addAddress($to);
        foreach ($bcc as $copy) {
            $copy = trim((string) $copy);
            if ($copy !== '' && filter_var($copy, FILTER_VALIDATE_EMAIL) && strcasecmp($copy, $to) !== 0) {
                $mail->addBCC($copy);
            }
        }
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        foreach ($attachments as $att) {
            $path = $att['path'] ?? '';
            if ($path !== '' && is_file($path)) {
                $mail->addAttachment($path, $att['name'] ?? basename($path));
            }
        }

        $mail->send();

        return ['ok' => true];
    } catch (Throwable $e) {
        $msg = trim($e->getMessage());
        error_log('sendSMTPMail failed: ' . $msg);

        return ['ok' => false, 'error' => $msg !== '' ? $msg : 'SMTP send failed'];
    }
}
