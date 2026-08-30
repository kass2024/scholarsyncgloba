<?php
/**
 * Admin password reset tokens (stored hashed) + optional column migration.
 */
require_once __DIR__ . '/mail_smtp.php';

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Append to PHP error log and optional project log file (creates logs/ if missing).
 */
function xander_password_reset_log(string $message, bool $isError = false): void
{
    $prefix = $isError ? '[xander_password_reset][ERROR] ' : '[xander_password_reset] ';
    error_log($prefix . $message);

    $dir = __DIR__ . '/../logs';
    $file = $dir . '/password_reset.log';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (is_dir($dir) && (is_writable($dir) || !file_exists($file))) {
        $line = date('c') . ' ' . ($isError ? 'ERROR ' : '') . $message . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}

function xander_normalize_admin_email(?string $email): string
{
    return strtolower(trim((string) $email));
}

/** Username or email for lookup (lowercase, trimmed). */
function xander_normalize_password_reset_identifier(?string $raw): string
{
    return strtolower(trim((string) $raw));
}

function xander_ensure_admin_password_reset_columns(mysqli $conn): void
{
    foreach (['password_reset_token', 'password_reset_expires'] as $col) {
        $esc = $conn->real_escape_string($col);
        $r = @$conn->query("SHOW COLUMNS FROM `admins` LIKE '{$esc}'");
        if ($r && $r->num_rows > 0) {
            continue;
        }
        if ($col === 'password_reset_token') {
            $sql = "ALTER TABLE `admins` ADD COLUMN `password_reset_token` VARCHAR(64) NULL DEFAULT NULL";
        } else {
            $sql = "ALTER TABLE `admins` ADD COLUMN `password_reset_expires` DATETIME NULL DEFAULT NULL";
        }
        if (!@$conn->query($sql)) {
            xander_password_reset_log('Column migration failed: ' . $conn->error, true);
        }
    }
}

function xander_http_base_path_from_script(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    $dir = dirname(str_replace('\\', '/', (string) $script));
    if ($dir === '/' || $dir === '.') {
        return '';
    }
    return rtrim($dir, '/');
}

function xander_public_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/**
 * @return array{ok: bool, error_info: string}
 */
function xander_send_admin_password_reset_email(string $toEmail, string $toName, string $resetUrl): array
{
    $mail = null;
    try {
        $mail = xander_create_phpmailer();
        $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Reset your admin password — ScholarSync Global';
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $mail->Body = '<p>Hello' . ($toName !== '' ? ' ' . htmlspecialchars($toName, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '') . ',</p>'
            . '<p>We received a request to reset the password for your admin account.</p>'
            . '<p><a href="' . $safeUrl . '" style="display:inline-block;padding:12px 20px;background:#427431;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;">Reset password</a></p>'
            . '<p>Or copy this link into your browser:<br><span style="word-break:break-all;">' . $safeUrl . '</span></p>'
            . '<p>This link expires in one hour. If you did not request this, you can ignore this email.</p>'
            . '<p>— ScholarSync Global</p>';
        $mail->AltBody = "Reset your password:\n{$resetUrl}\n\nThis link expires in one hour.";

        if (!$mail->send()) {
            $info = $mail->ErrorInfo;
            xander_password_reset_log('send() returned false. ErrorInfo: ' . $info, true);
            return ['ok' => false, 'error_info' => $info];
        }

        xander_password_reset_log('Mail sent OK to ' . $toEmail . ' (host=' . (getenv('SMTP_HOST') ?: 'xanderglobalscholars.com') . ')');
        return ['ok' => true, 'error_info' => ''];
    } catch (MailException $e) {
        $info = ($mail instanceof PHPMailer) ? $mail->ErrorInfo : '';
        xander_password_reset_log(
            'PHPMailer Exception: ' . $e->getMessage() . ($info !== '' ? ' | ErrorInfo: ' . $info : ''),
            true
        );
        return ['ok' => false, 'error_info' => $info !== '' ? $info : $e->getMessage()];
    } catch (\Throwable $e) {
        $info = ($mail instanceof PHPMailer) ? $mail->ErrorInfo : '';
        xander_password_reset_log(
            'Throwable: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . ($info !== '' ? ' | ErrorInfo: ' . $info : ''),
            true
        );
        return ['ok' => false, 'error_info' => $info !== '' ? $info : $e->getMessage()];
    }
}

/**
 * Clear reset token for one admin (used after failed send).
 */
function xander_admin_clear_password_reset_token(mysqli $conn, int $adminId): void
{
    $st = $conn->prepare('UPDATE admins SET password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?');
    if ($st) {
        $st->bind_param('i', $adminId);
        if (!$st->execute()) {
            xander_password_reset_log('Failed to clear token after mail error for admin id=' . $adminId . ': ' . $st->error, true);
        }
        $st->close();
    }
}

/**
 * Fetch one admin row by username / email (same idea as login + sensible fallbacks).
 *
 * @return array<string, mixed>|null
 */
function xander_admin_fetch_for_password_reset(mysqli $conn, string $trimmedInput): ?array
{
    // 1) Same as admin-login.php: WHERE username = ? (trimmed POST value)
    $st = $conn->prepare('SELECT id, full_name, email, username FROM admins WHERE username = ? LIMIT 1');
    if ($st) {
        $st->bind_param('s', $trimmedInput);
        $ok = $st->execute();
        $row = null;
        if ($ok) {
            $res = $st->get_result();
            $row = $res ? $res->fetch_assoc() : null;
        }
        $st->close();
        if ($row) {
            return $row;
        }
    }

    // 2) Case / whitespace tolerant
    $st = $conn->prepare(
        'SELECT id, full_name, email, username FROM admins WHERE LOWER(TRIM(username)) = LOWER(TRIM(?)) LIMIT 1'
    );
    if ($st) {
        $st->bind_param('s', $trimmedInput);
        $ok = $st->execute();
        $row = null;
        if ($ok) {
            $res = $st->get_result();
            $row = $res ? $res->fetch_assoc() : null;
        }
        $st->close();
        if ($row) {
            return $row;
        }
    }

    // 3) Typed email instead of username — same row; reset still sent to admins.email
    if (filter_var($trimmedInput, FILTER_VALIDATE_EMAIL)) {
        $em = xander_normalize_admin_email($trimmedInput);
        $st = $conn->prepare(
            'SELECT id, full_name, email, username FROM admins WHERE LOWER(TRIM(email)) = ? LIMIT 1'
        );
        if ($st) {
            $st->bind_param('s', $em);
            $ok = $st->execute();
            $row = null;
            if ($ok) {
                $res = $st->get_result();
                $row = $res ? $res->fetch_assoc() : null;
            }
            $st->close();
            if ($row) {
                return $row;
            }
        }
    }

    return null;
}

/**
 * Request reset: find admin by login username (or email on same row), send link to email on that row.
 *
 * @return string one of: sent | no_account | no_email_on_file | invalid_input | db_error | mail_failed
 */
function xander_admin_password_reset_request(mysqli $conn, string $username): string
{
    $u = trim((string) $username);
    if ($u === '' || strlen($u) > 255) {
        xander_password_reset_log('Rejected: empty or too long username');
        return 'invalid_input';
    }

    $row = xander_admin_fetch_for_password_reset($conn, $u);
    if (!$row) {
        xander_password_reset_log(
            'No admin row matched. Attempted (trimmed): "' . $u . '" (length ' . strlen($u) . '). Check admins.username / admins.email in DB.'
        );
        return 'no_account';
    }

    $to = trim((string) ($row['email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        xander_password_reset_log(
            'Admin id=' . (int) $row['id'] . ' matched but admins.email is missing or invalid; cannot send mail.',
            true
        );
        return 'no_email_on_file';
    }

    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $expires = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+1 hour')->format('Y-m-d H:i:s');

    $upd = $conn->prepare('UPDATE admins SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?');
    if (!$upd) {
        xander_password_reset_log('DB prepare failed (token update): ' . $conn->error, true);
        return 'db_error';
    }
    $id = (int) $row['id'];
    $upd->bind_param('ssi', $hash, $expires, $id);
    if (!$upd->execute()) {
        xander_password_reset_log('DB execute failed (token update) admin_id=' . $id . ': ' . $upd->error, true);
        $upd->close();
        return 'db_error';
    }
    $upd->close();

    $base = xander_public_origin() . xander_http_base_path_from_script();
    $resetUrl = $base . '/admin-reset-password.php?t=' . rawurlencode($raw);
    $name = trim((string) ($row['full_name'] ?? ''));

    $send = xander_send_admin_password_reset_email($to, $name, $resetUrl);
    if (!$send['ok']) {
        xander_admin_clear_password_reset_token($conn, $id);
        xander_password_reset_log('Token rolled back for admin_id=' . $id . ' after mail failure.', true);
        return 'mail_failed';
    }

    xander_password_reset_log('Password reset flow completed for admin_id=' . $id);
    return 'sent';
}
