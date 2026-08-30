<?php
declare(strict_types=1);

require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Improve deliverability: correct EHLO hostname, envelope sender, Message-ID domain.
 */
function pcvc_smtp_apply_deliverability(PHPMailer $mail, string $fromEmail): void
{
    $domain = substr(strrchr($fromEmail, '@'), 1) ?: 'scholarsyncglobal.ca';
    $mail->Hostname = $domain;
    $mail->Sender = $fromEmail;
    $mail->XMailer = 'ScholarSync-MIS-Finance';
}

/**
 * Central SMTP settings for PHPMailer across the project.
 * Credentials are read from project-root .env (SMTP_* keys).
 */
function app_mailer(?string $fromNameOverride = null): PHPMailer
{
    xander_load_env_file();

    $host = xander_env_get('SMTP_HOST') ?: 'scholarsyncglobal.ca';
    $username = xander_env_get('SMTP_USERNAME') ?: 'infos@scholarsyncglobal.ca';
    $password = xander_env_get('SMTP_PASSWORD');
    if ($password === '') {
        $password = xander_env_get_from_dotenv_file('SMTP_PASSWORD');
    }
    $portStr = xander_env_get('SMTP_PORT');
    $port = $portStr !== '' ? (int) $portStr : 465;
    $fromEmail = xander_env_get('SMTP_FROM_EMAIL') ?: $username;
    $fromName = $fromNameOverride
        ?? (xander_env_get('SMTP_FROM_NAME') ?: 'ScholarSync Global');

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = $port > 0 ? $port : 465;
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 30;
    $mail->SMTPDebug = SMTP::DEBUG_OFF;
    $mail->isHTML(true);
    $mail->setFrom($fromEmail, $fromName);
    pcvc_smtp_apply_deliverability($mail, $fromEmail);

    return $mail;
}

/**
 * Finance/receipt SMTP — same settings as sendReceiptEmail.php (.env SMTP_* with fallbacks).
 */
function pcvc_finance_smtp_mailer(?string $fromNameOverride = null): PHPMailer
{
    xander_load_env_file();

    $host = xander_env_get('SMTP_HOST') ?: 'scholarsyncglobal.ca';
    $username = xander_env_get('SMTP_USERNAME') ?: 'infos@scholarsyncglobal.ca';
    $password = xander_env_get('SMTP_PASSWORD');
    if ($password === '') {
        $password = xander_env_get_from_dotenv_file('SMTP_PASSWORD');
    }
    if ($password === '') {
        throw new RuntimeException('SMTP_PASSWORD is not configured.');
    }

    $portStr = xander_env_get('SMTP_PORT');
    $port = $portStr !== '' ? (int) $portStr : 465;
    $fromEmail = xander_env_get('SMTP_FROM_EMAIL') ?: $username;
    $fromName = $fromNameOverride
        ?? (xander_env_get('SMTP_FROM_NAME') ?: 'ScholarSync Global – Finance');
    $fromName = trim($fromName, " \t\n\r\0\x0B\"'");

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = $port > 0 ? $port : 465;
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 30;
    $mail->SMTPDebug = SMTP::DEBUG_OFF;
    $mail->isHTML(true);
    $mail->setFrom($fromEmail, $fromName);
    pcvc_smtp_apply_deliverability($mail, $fromEmail);

    return $mail;
}

/**
 * Mailer preset for applicant-facing admission letters.
 */
function app_admission_mailer(): PHPMailer
{
    return app_mailer('ScholarSync Global');
}

/**
 * Staff contract notifications — uses STAFF_CONTRACT_SMTP_* (.env) on same host/port as finance SMTP.
 */
function pcvc_staff_contract_smtp_mailer(?string $fromNameOverride = null): PHPMailer
{
    xander_load_env_file();

    $host = xander_env_get('SMTP_HOST') ?: 'scholarsyncglobal.ca';
    $portStr = xander_env_get('SMTP_PORT');
    $port = $portStr !== '' ? (int) $portStr : 465;

    $username = xander_env_get('STAFF_CONTRACT_SMTP_USERNAME');
    if ($username === '') {
        $username = 'infos@scholarsyncglobal.ca';
    }
    $password = xander_env_get('STAFF_CONTRACT_SMTP_PASSWORD');
    if ($password === '') {
        $password = xander_env_get_from_dotenv_file('STAFF_CONTRACT_SMTP_PASSWORD');
    }

    $fromEmail = xander_env_get('STAFF_CONTRACT_SMTP_FROM_EMAIL') ?: $username;
    $fromName = $fromNameOverride
        ?? (xander_env_get('STAFF_CONTRACT_SMTP_FROM_NAME') ?: 'ScholarSync Global');
    $fromName = trim($fromName, " \t\n\r\0\x0B\"'");

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = $port > 0 ? $port : 465;
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 30;
    $mail->SMTPDebug = SMTP::DEBUG_OFF;
    $mail->isHTML(true);
    $mail->setFrom($fromEmail, $fromName);
    $mail->XMailer = 'ScholarSync-MIS-StaffContract';
    pcvc_smtp_apply_deliverability($mail, $fromEmail);

    return $mail;
}

