<?php
/**
 * Drains reminder_emails_queue — sends due rows via SMTP.
 *
 * IMPORTANT: Failed sends used to leave sent_at NULL forever, so every cron run
 * retried the same rows indefinitely (rate-limit storms + bounce loops). We now:
 * - cap retries (abandon row after max attempts so it is no longer selected)
 * - single-instance lock (flock) so parallel crons cannot double-send
 * - smaller batch + optional stagger
 */
declare(strict_types=1);

define('REMINDERS_CRON', true);

require_once __DIR__ . '/../helpers/env_load.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

mysqli_set_charset($conn, 'utf8mb4');

/** Stop retrying after this many failed SMTP attempts (row is marked "sent" to drop off queue). */
const REMINDER_EMAIL_MAX_ATTEMPTS = 5;

/** Max rows to process per invocation (cron should not blast hundreds per minute). */
const REMINDER_EMAIL_BATCH_LIMIT = 15;

/** Optional pause between messages in seconds (reduces hourly rate). */
const REMINDER_EMAIL_SLEEP_SECONDS = 1;

function reminder_make_mailer(): PHPMailer
{
    $m = new PHPMailer(true);
    $m->CharSet = 'UTF-8';
    $m->isSMTP();
    $m->Host = xander_env_get('SMTP_HOST') ?: 'scholarsyncglobal.ca';
    $m->SMTPAuth = true;
    $m->Username = xander_env_get('SMTP_USERNAME') ?: 'infos@scholarsyncglobal.ca';
    $pass = xander_env_get('SMTP_PASSWORD');
    $m->Password = $pass !== '' ? $pass : '';
    $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $port = (int) (xander_env_get('SMTP_PORT') ?: '465');
    $m->Port = $port > 0 ? $port : 465;
    $fromName = xander_env_get('SMTP_FROM_NAME');
    $m->setFrom(
        xander_env_get('SMTP_FROM_EMAIL') ?: 'infos@scholarsyncglobal.ca',
        $fromName !== '' ? $fromName : 'Event Reminder'
    );

    return $m;
}

$lockDir = __DIR__ . '/data';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0755, true);
}
$lockFile = $lockDir . '/send_due_emails.lock';
$fp = @fopen($lockFile, 'c+');
if ($fp === false) {
    if (PHP_SAPI === 'cli') {
        echo "[email-sender] cannot open lock\n";
    }
    exit(1);
}
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    if (PHP_SAPI === 'cli') {
        echo "[email-sender] skipped (another instance is running)\n";
    }
    fclose($fp);
    exit(0);
}

$processed = 0;
$abandoned = 0;

for ($n = 0; $n < REMINDER_EMAIL_BATCH_LIMIT; $n++) {
    $conn->begin_transaction();
    $sql = 'SELECT * FROM reminder_emails_queue
            WHERE sent_at IS NULL
              AND attempts < ' . (int) REMINDER_EMAIL_MAX_ATTEMPTS . "
              AND scheduled_at_utc <= UTC_TIMESTAMP()
            ORDER BY id ASC
            LIMIT 1
            FOR UPDATE";
    $rs = $conn->query($sql);
    if (!$rs) {
        $conn->rollback();
        break;
    }
    $row = $rs->fetch_assoc();
    if (!$row) {
        $conn->rollback();
        break;
    }
    $id = (int) $row['id'];
    $conn->commit();

    $to = trim((string) ($row['send_to'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare('UPDATE reminder_emails_queue SET sent_at = UTC_TIMESTAMP(), attempts = attempts + 1, last_error = ? WHERE id = ?');
        $err = 'Invalid email address';
        $stmt->bind_param('si', $err, $id);
        $stmt->execute();
        $stmt->close();
        $processed++;
        continue;
    }

    try {
        $mail = reminder_make_mailer();
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = (string) $row['subject'];
        $mail->Body = nl2br((string) $row['body']);
        $mail->AltBody = (string) $row['body'];
        $mail->send();

        $stmt = $conn->prepare('UPDATE reminder_emails_queue SET sent_at = UTC_TIMESTAMP(), attempts = attempts + 1, last_error = NULL WHERE id = ? AND sent_at IS NULL');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $processed++;
    } catch (Exception $e) {
        $err = $e->getMessage();
        $stmt = $conn->prepare('UPDATE reminder_emails_queue SET attempts = attempts + 1, last_error = ? WHERE id = ?');
        $stmt->bind_param('si', $err, $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('SELECT attempts FROM reminder_emails_queue WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ares = $stmt->get_result();
        $arow = $ares ? $ares->fetch_assoc() : null;
        $stmt->close();
        $attemptsNow = (int) ($arow['attempts'] ?? 0);

        if ($attemptsNow >= REMINDER_EMAIL_MAX_ATTEMPTS) {
            $abMsg = '[abandoned after ' . REMINDER_EMAIL_MAX_ATTEMPTS . ' failed attempts] ' . $err;
            $stmt = $conn->prepare('UPDATE reminder_emails_queue SET sent_at = UTC_TIMESTAMP(), last_error = ? WHERE id = ?');
            $stmt->bind_param('si', $abMsg, $id);
            $stmt->execute();
            $stmt->close();
            $abandoned++;
        }
    }

    if (REMINDER_EMAIL_SLEEP_SECONDS > 0) {
        sleep(REMINDER_EMAIL_SLEEP_SECONDS);
    }
}

flock($fp, LOCK_UN);
fclose($fp);

if (PHP_SAPI === 'cli') {
    echo '[email-sender] processed=' . $processed . ' abandoned=' . $abandoned . ' @ ' . gmdate('c') . PHP_EOL;
}
