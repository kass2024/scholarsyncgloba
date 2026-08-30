<?php
declare(strict_types=1);

require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/receipt_render.php';
require_once __DIR__ . '/mailer.php';
require_once dirname(__DIR__) . '/generateReceiptPdf.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

function pcvc_special_payment_notify_log(string $msg, $data = null): void
{
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/special_payment_notify.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data !== null) {
        $line .= ' :: ' . (is_scalar($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

/**
 * Resolve admin notify email from .env (SPECIAL_PAYMENT_NOTIFY_EMAIL).
 */
function pcvc_special_payment_notify_email(): string
{
    xander_load_env_file();

    $email = trim(xander_env_get('SPECIAL_PAYMENT_NOTIFY_EMAIL'));
    if ($email === '') {
        $email = trim(xander_env_get_from_dotenv_file('SPECIAL_PAYMENT_NOTIFY_EMAIL'));
    }
    if ($email === '') {
        $email = 'hatheo75@gmail.com';
    }

    return $email;
}

/**
 * Build PHPMailer using shared finance SMTP settings (.env SMTP_*).
 */
function pcvc_special_payment_smtp_mailer(): PHPMailer
{
    $mail = pcvc_finance_smtp_mailer('ScholarSync Global – Finance');
    $fromEmail = $mail->From;
    $mail->addReplyTo($fromEmail, 'ScholarSync Global Finance');

    return $mail;
}

function pcvc_special_payment_smtp_debug_enabled(): bool
{
    xander_load_env_file();
    $v = strtolower(trim(xander_env_get('SPECIAL_PAYMENT_SMTP_DEBUG')));
    if ($v === '') {
        $v = strtolower(trim(xander_env_get_from_dotenv_file('SPECIAL_PAYMENT_SMTP_DEBUG')));
    }

    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

function pcvc_special_payment_smtp_debug_line(string $line): void
{
    $line = trim($line);
    if ($line === '') {
        return;
    }
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents(
        $logDir . '/special_payment_smtp.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL,
        FILE_APPEND
    );
}

/** Extract Exim queue id from SMTP "250 OK id=..." response. */
function pcvc_special_payment_smtp_queue_id(array $transcript): string
{
    foreach ($transcript as $line) {
        if (preg_match('/250 OK id=([^\s]+)/i', $line, $m)) {
            return $m[1];
        }
    }

    return '';
}

/**
 * Email admin after a special-program payment (email only — WhatsApp disabled).
 */
function pcvc_send_special_payment_notify(mysqli $conn, string $receiptNo): array
{
    $receiptNo = trim($receiptNo);
    if ($receiptNo === '') {
        return ['status' => 'error', 'reason' => 'receipt_no'];
    }

    $data = pcvc_load_receipt_data($conn, $receiptNo);
    if (!$data) {
        pcvc_special_payment_notify_log('Receipt not found', $receiptNo);
        return ['status' => 'error', 'reason' => 'not_found'];
    }

    $notifyEmail = pcvc_special_payment_notify_email();
    pcvc_special_payment_notify_log('Notify email target', $notifyEmail);

    $programLabel = match ($data['source_table'] ?? '') {
        'credit_transfer_applications' => 'Credit Transfer',
        'upafa_registrations'            => 'UPAFA Registration',
        default                          => 'Special Program',
    };

    $studentName = (string) ($data['customer_name'] ?? 'Unknown');
    $currency    = (string) ($data['currency'] ?? '');
    $totalPaid   = number_format((float) ($data['total_amount'] ?? 0), 2);
    $method      = (string) ($data['payment_method'] ?? '');
    $package     = (string) ($data['package_title'] ?? '');

    $pdfPath = dirname(__DIR__) . '/receipts/' . $receiptNo . '.pdf';
    if (!is_file($pdfPath)) {
        try {
            $pdfPath = generateReceiptPdf($receiptNo, $conn);
        } catch (Throwable $e) {
            pcvc_special_payment_notify_log('PDF generation failed', $e->getMessage());
        }
    }

    $emailSent = false;
    $errors    = [];

    // Only the .env address — never the student email from receipt data.
    $recipient = trim(strtolower($notifyEmail));
    if ($recipient === '') {
        pcvc_special_payment_notify_log('No notify email configured');
        return ['status' => 'error', 'email_sent' => false, 'whatsapp_sent' => false, 'errors' => ['email: not configured']];
    }

    pcvc_special_payment_notify_log('Sending admin email', [
        'to'      => $recipient,
        'student' => $studentName,
        'receipt' => $receiptNo,
    ]);

    $h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

    $subject = "Payment Recorded — {$programLabel} — {$receiptNo}";

    $plainBody = "New payment recorded\n\n"
        . "Program: {$programLabel}\n"
        . "Student: {$studentName}\n"
        . "Receipt: {$receiptNo}\n"
        . "Package: {$package}\n"
        . "Amount: {$currency} {$totalPaid}\n"
        . "Method: {$method}\n\n"
        . "The student was NOT emailed. Receipt is in the dashboard.";

    $htmlBody = '
    <div style="font-family:Arial,sans-serif;font-size:14px;color:#1f2933;max-width:640px;">
      <div style="background:#0b3c5d;color:#fff;padding:16px 20px;border-radius:8px 8px 0 0;">
        <div style="font-size:18px;font-weight:800;">New Payment — ' . $h($programLabel) . '</div>
      </div>
      <div style="border:1px solid #e5e7eb;border-top:0;padding:20px;background:#fff;border-radius:0 0 8px 8px;">
        <p>A payment was recorded for a <strong>' . $h($programLabel) . '</strong> student. The student was <strong>not</strong> emailed.</p>
        <table style="width:100%;border-collapse:collapse;margin:12px 0;font-size:13px;">
          <tr><td style="padding:6px 0;color:#6b7280;width:130px;"><strong>Student</strong></td><td>' . $h($studentName) . '</td></tr>
          <tr><td style="padding:6px 0;color:#6b7280;"><strong>Receipt No.</strong></td><td>' . $h($receiptNo) . '</td></tr>
          <tr><td style="padding:6px 0;color:#6b7280;"><strong>Package</strong></td><td>' . $h($package) . '</td></tr>
          <tr><td style="padding:6px 0;color:#6b7280;"><strong>Amount</strong></td><td style="font-weight:700;color:#0b3c5d;">' . $h($currency . ' ' . $totalPaid) . '</td></tr>
          <tr><td style="padding:6px 0;color:#6b7280;"><strong>Method</strong></td><td>' . $h($method) . '</td></tr>
        </table>
        <p style="font-size:12px;color:#6b7280;">Receipt is available in the dashboard under Check payment Receipt.</p>
      </div>
    </div>';

    $smtpDebug = pcvc_special_payment_smtp_debug_enabled();
    $smtpTranscript = [];

    try {
        $mail = pcvc_special_payment_smtp_mailer(false);
        // Always capture server lines so we can log Exim queue id; full log only when debug on.
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = static function (string $str, int $level) use (&$smtpTranscript, $smtpDebug): void {
            $line = trim($str);
            if ($line === '') {
                return;
            }
            $smtpTranscript[] = $line;
            if ($smtpDebug) {
                pcvc_special_payment_smtp_debug_line($line);
            }
        };

        $mail->addAddress($recipient, 'Finance Admin');
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainBody;

        $pdfAttached = false;
        if (is_file($pdfPath)) {
            $mail->addAttachment($pdfPath, $receiptNo . '.pdf');
            $pdfAttached = true;
        }

        $mail->send();
        $emailSent = true;
        $queueId = pcvc_special_payment_smtp_queue_id($smtpTranscript);
        pcvc_special_payment_notify_log('Admin email SMTP accepted', [
            'to'           => $recipient,
            'from'         => $mail->From,
            'hostname'     => $mail->Hostname,
            'subject'      => $subject,
            'message_id'   => $mail->getLastMessageID(),
            'exim_queue'   => $queueId !== '' ? $queueId : null,
            'pdf_attached' => $pdfAttached,
            'pdf_size'     => $pdfAttached ? filesize($pdfPath) : 0,
            'note'         => 'SMTP 250 OK means queued on mail server — check inbox/spam or hosting mail queue for final delivery',
        ]);
    } catch (Throwable $e) {
        $errors[] = 'email: ' . $e->getMessage();
        pcvc_special_payment_notify_log('Admin email failed', [
            'to'    => $recipient,
            'error' => $e->getMessage(),
        ]);
    }

    return [
        'status'        => $emailSent ? 'ok' : 'error',
        'email_sent'    => $emailSent,
        'whatsapp_sent' => false,
        'errors'        => $errors,
    ];
}
