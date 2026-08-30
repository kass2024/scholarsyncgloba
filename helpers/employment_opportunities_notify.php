<?php
declare(strict_types=1);

/**
 * Employment Opportunities — email notifications (office inbox + optional applicant).
 */
require_once __DIR__ . '/mail_smtp.php';
require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/employment_opportunities_schema.php';
require_once __DIR__ . '/employment_opportunities_files.php';

function eo_email_wrap(string $title, string $innerHtml): string
{
    return "
    <html><body style='font-family:Arial,sans-serif;max-width:640px;margin:0 auto;padding:20px;color:#1e293b'>
      <div style='background:linear-gradient(135deg,#1e4d2b 0%,#3661B9 100%);color:#fff;padding:28px;border-radius:12px 12px 0 0;text-align:center'>
        <h1 style='margin:0;font-size:22px'>{$title}</h1>
        <p style='margin:8px 0 0;opacity:.9;font-size:14px'>ScholarSync Global — Employment Opportunities</p>
      </div>
      <div style='background:#fff;border:1px solid #e2e8f0;border-top:0;padding:28px;border-radius:0 0 12px 12px'>
        {$innerHtml}
        <p style='margin-top:24px;font-size:12px;color:#64748b;text-align:center'>© " . date('Y') . " ScholarSync Global</p>
      </div>
    </body></html>";
}

function eo_notify_recipient_email(): string
{
    xander_load_env_file();
    // Prefer APPROVAL_EMAIL (matches Francophonie naming), fall back to NOTIFY_EMAIL.
    foreach (['EMPLOYMENT_OPPORTUNITIES_APPROVAL_EMAIL', 'EMPLOYMENT_OPPORTUNITIES_NOTIFY_EMAIL'] as $key) {
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

function eo_build_summary_html(array $row): string
{
    $esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $field = eo_training_field_label((string) ($row['training_field'] ?? ''));
    $msgApp = ucfirst((string) ($row['messaging_app'] ?? 'whatsapp'));
    $phone = trim('+' . ($row['phone_area_code'] ?? '') . ' ' . ($row['phone_number'] ?? ''));

    return '
    <h3 style="margin-top:0;color:#1e4d2b">Applicant Details</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:14px">
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0;width:40%"><strong>Full Name</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['full_name'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Reference</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['reference_id'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Passport Number</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['passport_number'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Phone</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($phone) . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Contact App</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($msgApp) . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Email</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['email'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Training Field</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($field) . '</td></tr>
    </table>
    <p style="font-size:13px;color:#64748b">Passport scan and academic documents are attached to this email.</p>';
}

/**
 * @return array{attachments: array<int, array{path:string, name:string}>, labels: string[]}
 */
function eo_collect_attachments(array $row): array
{
    $attachments = [];
    $labels = [];
    $ref = (string) ($row['reference_id'] ?? 'EO');

    $passportAbs = eo_abs_upload_path((string) ($row['passport_file'] ?? ''));
    if ($passportAbs !== null) {
        $ext = pathinfo($passportAbs, PATHINFO_EXTENSION);
        $attachments[] = [
            'path' => $passportAbs,
            'name' => $ref . '_Passport' . ($ext ? '.' . $ext : ''),
        ];
        $labels[] = 'Passport';
    }

    $academicPaths = eo_parse_stored_files((string) ($row['academic_docs_file'] ?? ''));
    foreach ($academicPaths as $i => $rel) {
        $abs = eo_abs_upload_path($rel);
        if ($abs === null) {
            continue;
        }
        $ext = pathinfo($abs, PATHINFO_EXTENSION);
        $n = $i + 1;
        $attachments[] = [
            'path' => $abs,
            'name' => $ref . '_Academic_' . $n . ($ext ? '.' . $ext : ''),
        ];
        $labels[] = 'Academic Document ' . $n;
    }

    return ['attachments' => $attachments, 'labels' => $labels];
}

/** Email full application + documents to EMPLOYMENT_OPPORTUNITIES_NOTIFY_EMAIL (on approval). */
function eo_notify_office_new_application(array $row): bool
{
    $to = eo_notify_recipient_email();
    if ($to === '') {
        error_log('EMPLOYMENT_OPPORTUNITIES_NOTIFY_EMAIL is not set or invalid in .env');
        return false;
    }

    $pack = eo_collect_attachments($row);
    $body = '<p>An <strong>Employment Opportunities</strong> application has been <strong>approved</strong>. Full details and documents are attached.</p>'
        . eo_build_summary_html($row);

    if ($pack['labels'] !== []) {
        $body .= '<p><strong>Attachments:</strong> ' . htmlspecialchars(implode(', ', $pack['labels']), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $subject = 'Approved Employment Opportunities Application — ' . ($row['reference_id'] ?? '');
    return sendSMTPMail($to, $subject, eo_email_wrap('Approved Application Package', $body), $pack['attachments']);
}

function eo_notify_applicant_received(array $row): bool
{
    $to = trim((string) ($row['email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $name = htmlspecialchars((string) ($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $ref = htmlspecialchars((string) ($row['reference_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $field = htmlspecialchars(eo_training_field_label((string) ($row['training_field'] ?? '')), ENT_QUOTES, 'UTF-8');

    $body = "<p>Dear {$name},</p>
        <p>Thank you for applying to <strong>Employment Opportunities</strong> (professional training with Russian language).</p>
        <p style='font-family:monospace;font-size:18px;background:#f1f5f9;padding:12px;border-radius:8px'><strong>{$ref}</strong></p>
        <p><strong>Selected field:</strong> {$field}</p>
        <p>Save this reference ID. Our team will contact you on WhatsApp or Telegram using the number you provided.</p>";

    $subject = 'Employment Opportunities — Application Received — ' . ($row['reference_id'] ?? '');
    return sendSMTPMail($to, $subject, eo_email_wrap('Application Received', $body));
}

/**
 * Dispatch background mail exactly like Francophonie Mobility: same PHP binary,
 * detached CLI process, and inline fallback in the caller if dispatch fails.
 */
function eo_spawn_cli_worker(string $scriptBasename, int $applicationId): bool
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

function eo_fire_async_applicant_notify(int $applicationId): bool
{
    return eo_spawn_cli_worker('eo_background_email.php', $applicationId);
}

function eo_fire_async_approval_package(int $applicationId): bool
{
    return eo_spawn_cli_worker('eo_background_approval.php', $applicationId);
}

function eo_send_new_application_email_job(mysqli $conn, int $applicationId): bool
{
    $st = $conn->prepare('SELECT * FROM employment_opportunities_applications WHERE id = ? LIMIT 1');
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
    return eo_notify_applicant_received($row);
}

function eo_send_approval_package_job(mysqli $conn, int $applicationId): bool
{
    $st = $conn->prepare('SELECT * FROM employment_opportunities_applications WHERE id = ? LIMIT 1');
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
    return eo_notify_office_new_application($row);
}

