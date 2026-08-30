<?php
declare(strict_types=1);

/**
 * South Korea Event Participation — email notifications.
 */
require_once __DIR__ . '/mail_smtp.php';
require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/korea_event_schema.php';
require_once __DIR__ . '/korea_event_files.php';

function kep_email_wrap(string $title, string $innerHtml): string
{
    return "
    <html><body style='font-family:Arial,sans-serif;max-width:640px;margin:0 auto;padding:20px;color:#1e293b'>
      <div style='background:linear-gradient(135deg,#CD2E3A 0%,#0047A0 100%);color:#fff;padding:28px;border-radius:12px 12px 0 0;text-align:center'>
        <h1 style='margin:0;font-size:22px'>{$title}</h1>
        <p style='margin:8px 0 0;opacity:.9;font-size:14px'>ScholarSync Global — South Korea Event Participation</p>
      </div>
      <div style='background:#fff;border:1px solid #e2e8f0;border-top:0;padding:28px;border-radius:0 0 12px 12px'>
        {$innerHtml}
        <p style='margin-top:24px;font-size:12px;color:#64748b;text-align:center'>© " . date('Y') . " ScholarSync Global</p>
      </div>
    </body></html>";
}

function kep_notify_recipient_email(): string
{
    xander_load_env_file();
    foreach (['KOREA_EVENT_NOTIFY_EMAIL', 'KOREA_EVENT_APPROVAL_EMAIL'] as $key) {
        $to = trim(xander_env_get($key));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $to = trim(xander_env_get_from_dotenv_file($key));
        }
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $to;
        }
    }
    return '';
}

function kep_build_summary_html(array $row): string
{
    $esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $msgApp = ucfirst((string) ($row['messaging_app'] ?? 'whatsapp'));
    $phone = trim('+' . ($row['phone_area_code'] ?? '') . ' ' . ($row['phone_number'] ?? ''));
    $dob = (string) ($row['date_of_birth'] ?? '');
    if ($dob !== '' && $dob !== '0000-00-00') {
        $ts = strtotime($dob);
        $dob = $ts ? date('j M Y', $ts) : $dob;
    } else {
        $dob = '—';
    }

    return '
    <h3 style="margin-top:0;color:#0047A0">Applicant Details</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:14px">
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0;width:40%"><strong>Full Name</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['full_name'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Reference</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['reference_id'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Date of Birth</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($dob) . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Gender</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc(kep_gender_label((string) ($row['gender'] ?? ''))) . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Nationality</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['nationality'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Country of Residence</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['country_of_residence'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Passport Number</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['passport_number'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Phone</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($phone) . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Contact App</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($msgApp) . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Email</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['email'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Occupation</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['occupation'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Organization</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['organization'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Event</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['event_name'] ?? '') . '</td></tr>
    </table>
    <p style="font-size:13px;color:#64748b">Passport scan and CV are attached when available.</p>';
}

/**
 * @return array{attachments: array<int, array{path:string, name:string}>, labels: string[]}
 */
function kep_collect_attachments(array $row): array
{
    $attachments = [];
    $labels = [];
    $ref = (string) ($row['reference_id'] ?? 'KEP');

    $passportAbs = kep_abs_upload_path((string) ($row['passport_file'] ?? ''));
    if ($passportAbs !== null) {
        $ext = pathinfo($passportAbs, PATHINFO_EXTENSION);
        $attachments[] = [
            'path' => $passportAbs,
            'name' => $ref . '_Passport' . ($ext ? '.' . $ext : ''),
        ];
        $labels[] = 'Passport';
    }

    $cvAbs = kep_abs_upload_path((string) ($row['cv_file'] ?? ''));
    if ($cvAbs !== null) {
        $ext = pathinfo($cvAbs, PATHINFO_EXTENSION);
        $attachments[] = [
            'path' => $cvAbs,
            'name' => $ref . '_CV' . ($ext ? '.' . $ext : ''),
        ];
        $labels[] = 'CV';
    }

    return ['attachments' => $attachments, 'labels' => $labels];
}

function kep_notify_office_new_application(array $row): bool
{
    $to = kep_notify_recipient_email();
    if ($to === '') {
        return false;
    }

    $pack = kep_collect_attachments($row);
    $body = '<p>A new <strong>South Korea Event Participation</strong> application has been received.</p>'
        . kep_build_summary_html($row);

    if ($pack['labels'] !== []) {
        $body .= '<p><strong>Attachments:</strong> ' . htmlspecialchars(implode(', ', $pack['labels']), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $subject = 'New South Korea Event Participation — ' . ($row['reference_id'] ?? '');
    return sendSMTPMail($to, $subject, kep_email_wrap('New Event Application', $body), $pack['attachments']);
}

function kep_notify_applicant_received(array $row): bool
{
    $to = trim((string) ($row['email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $name = htmlspecialchars((string) ($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $ref = htmlspecialchars((string) ($row['reference_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $event = htmlspecialchars((string) ($row['event_name'] ?? 'South Korea Event'), ENT_QUOTES, 'UTF-8');

    $body = "<p>Dear {$name},</p>
        <p>Thank you for submitting the <strong>South Korea Event Participation Form</strong>.</p>
        <p style='font-family:monospace;font-size:18px;background:#f1f5f9;padding:12px;border-radius:8px'><strong>{$ref}</strong></p>
        <p><strong>Event:</strong> {$event}</p>
        <p>Save this reference ID. Our team will review your passport and CV and contact you on WhatsApp or Telegram using the number you provided.</p>";

    $subject = 'South Korea Event Participation — Application Received — ' . ($row['reference_id'] ?? '');
    return sendSMTPMail($to, $subject, kep_email_wrap('Application Received', $body));
}

function kep_spawn_cli_worker(string $scriptBasename, int $applicationId): bool
{
    $root = dirname(__DIR__);
    $script = $root . DIRECTORY_SEPARATOR . $scriptBasename;
    $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
    if (!is_file($script) || $applicationId <= 0) {
        return false;
    }

    $id = (int) $applicationId;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cmd = 'start /B "" ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . $id;
        @pclose(@popen($cmd, 'r'));
    } else {
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . $id . ' > /dev/null 2>&1 &';
        @exec($cmd);
    }

    return true;
}

function kep_fire_async_applicant_notify(int $applicationId): bool
{
    return kep_spawn_cli_worker('korea_event_background_email.php', $applicationId);
}

function kep_send_new_application_email_job(mysqli $conn, int $applicationId): bool
{
    $st = $conn->prepare('SELECT * FROM korea_event_applications WHERE id = ? LIMIT 1');
    if (!$st) {
        return false;
    }
    $st->bind_param('i', $applicationId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row) {
        return false;
    }

    xander_load_env_file();
    $okApplicant = kep_notify_applicant_received($row);
    kep_notify_office_new_application($row);
    return $okApplicant;
}
