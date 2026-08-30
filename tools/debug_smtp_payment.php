<?php
declare(strict_types=1);

/**
 * Local XAMPP SMTP debug for credit-transfer payment receipts.
 * Usage: php tools/debug_smtp_payment.php [to@email.com] [receipt_no]
 */

$root = dirname(__DIR__);
require_once $root . '/helpers/env_load.php';
require_once $root . '/helpers/mailer.php';
require_once $root . '/helpers/special_payment_notify.php';
require_once $root . '/PHPMailer/src/PHPMailer.php';
require_once $root . '/PHPMailer/src/SMTP.php';
require_once $root . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$logFile = $root . '/logs/smtp_debug.log';
$logDir  = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

function smtp_debug_log(string $msg): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

$to = $argv[1] ?? pcvc_special_payment_notify_email();
$receiptNo = $argv[2] ?? '';

xander_load_env_file();

$config = [
    'php_sapi'        => PHP_SAPI,
    'php_version'     => PHP_VERSION,
    'openssl'         => extension_loaded('openssl'),
    'curl'            => extension_loaded('curl'),
    'SMTP_HOST'       => xander_env_get('SMTP_HOST') ?: 'scholarsyncglobal.ca',
    'SMTP_PORT'       => xander_env_get('SMTP_PORT') ?: '465',
    'SMTP_USERNAME'   => xander_env_get('SMTP_USERNAME') ?: 'infos@scholarsyncglobal.ca',
    'SMTP_FROM_EMAIL' => xander_env_get('SMTP_FROM_EMAIL') ?: 'infos@scholarsyncglobal.ca',
    'SMTP_PASSWORD'   => xander_env_get('SMTP_PASSWORD') !== '' ? '[set len=' . strlen(xander_env_get('SMTP_PASSWORD')) . ']' : '[empty]',
    'SPECIAL_PAYMENT_NOTIFY_EMAIL' => pcvc_special_payment_notify_email(),
    'hostname'        => gethostname() ?: php_uname('n'),
    'server_name'     => $_SERVER['SERVER_NAME'] ?? '(cli)',
];

smtp_debug_log('=== SMTP PAYMENT DEBUG START ===');
smtp_debug_log('Config: ' . json_encode($config, JSON_UNESCAPED_UNICODE));

// DNS checks for deliverability
$fromDomain = substr(strrchr($config['SMTP_FROM_EMAIL'], '@'), 1) ?: 'scholarsyncglobal.ca';
foreach (['MX', 'TXT', 'SPF'] as $check) {
    if ($check === 'SPF') {
        $txt = @dns_get_record($fromDomain, DNS_TXT) ?: [];
        $spf = array_filter($txt, static fn($r) => isset($r['txt']) && stripos($r['txt'], 'v=spf1') !== false);
        smtp_debug_log("DNS SPF for {$fromDomain}: " . json_encode(array_values($spf)));
        continue;
    }
    $type = $check === 'MX' ? DNS_MX : DNS_TXT;
    $recs = @dns_get_record($fromDomain, $type) ?: [];
    smtp_debug_log("DNS {$check} for {$fromDomain}: " . json_encode($recs));
}

$dkimHost = 'default._domainkey.' . $fromDomain;
$dkim = @dns_get_record($dkimHost, DNS_TXT) ?: [];
smtp_debug_log("DNS DKIM (default._domainkey): " . json_encode($dkim));

// Test 1: TCP connect
$host = $config['SMTP_HOST'];
$port = (int) $config['SMTP_PORT'];
$errno = 0;
$errstr = '';
$fp = @fsockopen('ssl://' . $host, $port, $errno, $errstr, 15);
if (!$fp) {
    smtp_debug_log("TCP FAIL ssl://{$host}:{$port} errno={$errno} err={$errstr}");
} else {
    smtp_debug_log("TCP OK ssl://{$host}:{$port}");
    fclose($fp);
}

// Test 2: PHPMailer with full SMTP transcript (uses fixed Hostname / Sender)
$transcript = [];
$mail = pcvc_finance_smtp_mailer('ScholarSync Global – Finance');
$mail->addReplyTo($mail->From, 'ScholarSync Global Finance');
$mail->Timeout = 60;
$mail->SMTPDebug = SMTP::DEBUG_SERVER;
$mail->Debugoutput = static function ($str, $level) use (&$transcript) {
    $line = trim($str);
    if ($line !== '') {
        $transcript[] = $line;
    }
};

smtp_debug_log('Mailer Hostname=' . $mail->Hostname . ' Sender=' . $mail->Sender);

$mail->addAddress($to, 'Debug Recipient');
$mail->Subject = '[DEBUG] Credit Transfer Receipt Test ' . date('Y-m-d H:i:s');
$mail->Body = '<p>SMTP debug test from local XAMPP. If you receive this, delivery works.</p>';
$mail->AltBody = 'SMTP debug test from local XAMPP.';
$mail->Sender = $mail->From;

$pdfPath = '';
if ($receiptNo !== '') {
    $candidate = $root . '/receipts/' . $receiptNo . '.pdf';
    if (is_file($candidate)) {
        $pdfPath = $candidate;
        $mail->addAttachment($pdfPath, basename($candidate));
        smtp_debug_log('Attachment: ' . $candidate . ' size=' . filesize($candidate));
    } else {
        smtp_debug_log('Attachment missing: ' . $candidate);
    }
}

smtp_debug_log('Sending test to: ' . $to);

try {
    $mail->send();
    smtp_debug_log('PHPMailer send() returned TRUE');
    smtp_debug_log('Message-ID: ' . $mail->getLastMessageID());
    smtp_debug_log('From: ' . $mail->From);
    smtp_debug_log('To: ' . implode(', ', array_column($mail->getToAddresses(), 0)));
} catch (Throwable $e) {
    smtp_debug_log('PHPMailer send() FAILED: ' . $e->getMessage());
}

smtp_debug_log('--- SMTP TRANSCRIPT ---');
foreach ($transcript as $line) {
    smtp_debug_log('  ' . $line);
}
smtp_debug_log('--- END TRANSCRIPT ---');

// Test 3: compare gmail if target is mail.com
if (stripos($to, '@mail.com') !== false) {
    smtp_debug_log('Target is mail.com — also sending comparison to Gmail if SMTP_TEST_GMAIL is set in .env');
    $gmail = trim(xander_env_get('SMTP_TEST_GMAIL'));
    if ($gmail !== '') {
        try {
            $mail2 = pcvc_finance_smtp_mailer('ScholarSync Global – Finance');
            $mail2->addAddress($gmail, 'Gmail Compare');
            $mail2->Subject = '[DEBUG compare] same SMTP to Gmail ' . date('H:i:s');
            $mail2->Body = '<p>Same SMTP server, sent to Gmail for delivery comparison.</p>';
            $mail2->AltBody = 'Gmail comparison test.';
            $mail2->Sender = $mail2->From;
            $mail2->send();
            smtp_debug_log('Comparison sent to Gmail: ' . $gmail . ' id=' . $mail2->getLastMessageID());
        } catch (Throwable $e) {
            smtp_debug_log('Gmail comparison FAILED: ' . $e->getMessage());
        }
    } else {
        smtp_debug_log('Set SMTP_TEST_GMAIL=your@gmail.com in .env to compare delivery.');
    }
}

smtp_debug_log('=== SMTP PAYMENT DEBUG END ===');
smtp_debug_log('Full log: ' . $logFile);
