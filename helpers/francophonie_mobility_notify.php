<?php
/**
 * Francophonie Mobility — email-only notifications (applicant + admin alerts).
 */
declare(strict_types=1);

require_once __DIR__ . '/mail_smtp.php';
require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/francophonie_mobility_schema.php';

function fm_level_label(string $level): string
{
    return ucfirst(str_replace('_', ' ', $level));
}

function fm_yes_no_label(string $v): string
{
    return strtolower($v) === 'yes' ? 'Yes' : 'No';
}

function fm_applicant_name(array $row): string
{
    return trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
}

function fm_status_label(string $status): string
{
    $map = [
        'pending' => 'Received',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];
    return $map[$status] ?? $status;
}

function fm_email_wrap(string $title, string $innerHtml): string
{
    return "
    <html><body style='font-family:Arial,sans-serif;max-width:640px;margin:0 auto;padding:20px;color:#1e293b'>
      <div style='background:linear-gradient(135deg,#1e4d2b 0%,#3661B9 100%);color:#fff;padding:28px;border-radius:12px 12px 0 0;text-align:center'>
        <h1 style='margin:0;font-size:22px'>{$title}</h1>
        <p style='margin:8px 0 0;opacity:.9;font-size:14px'>ScholarSync Global — Mobilité Francophone</p>
      </div>
      <div style='background:#fff;border:1px solid #e2e8f0;border-top:0;padding:28px;border-radius:0 0 12px 12px'>
        {$innerHtml}
        <p style='margin-top:24px;font-size:12px;color:#64748b;text-align:center'>© " . date('Y') . " ScholarSync Global</p>
      </div>
    </body></html>";
}

/** Office inbox from .env SMTP settings (for BCC copies). */
function fm_office_smtp_email(): string
{
    xander_load_env_file();
    $from = trim(xander_env_get('SMTP_FROM_EMAIL'));
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return $from;
    }
    $user = trim(xander_env_get('SMTP_USERNAME'));
    if ($user !== '' && filter_var($user, FILTER_VALIDATE_EMAIL)) {
        return $user;
    }
    return '';
}

function fm_office_bcc_list(): array
{
    $office = fm_office_smtp_email();
    return $office !== '' ? [$office] : [];
}

function fm_notify_applicant_status(array $row, string $status, string $note = ''): bool
{
    $to = trim((string) ($row['email'] ?? ''));
    if ($to === '') {
        return false;
    }

    $name = htmlspecialchars(fm_applicant_name($row), ENT_QUOTES, 'UTF-8');
    $ref = htmlspecialchars((string) ($row['reference_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $statusLabel = fm_status_label($status);

    $body = "<p>Dear {$name},</p>
        <p>Your <strong>Canada Francophonie Mobility</strong> application status is now: <strong>{$statusLabel}</strong>.</p>
        <p><strong>Reference:</strong> {$ref}</p>";

    if ($note !== '') {
        $body .= '<div style="background:#f8fafc;border-left:4px solid #3661B9;padding:12px 16px;margin:16px 0">'
            . '<strong>Message from our team:</strong><br>' . nl2br(htmlspecialchars($note, ENT_QUOTES, 'UTF-8'))
            . '</div>';
    }

    if ($status === 'approved') {
        $body .= '<p>Congratulations! Our team has approved your file for forwarding. You will receive further instructions by email.</p>';
    } elseif ($status === 'rejected') {
        $body .= '<p>Unfortunately we cannot proceed with your file at this time. Please contact our office if you have questions.</p>';
    } else {
        $body .= '<p>We will keep you updated by email. Please check your inbox regularly.</p>';
    }

    $subject = 'Francophonie Mobility Application — ' . $statusLabel . ' — ' . ($row['reference_id'] ?? '');
    return sendSMTPMail($to, $subject, fm_email_wrap('Application Update', $body), [], fm_office_bcc_list());
}

/** @deprecated Staff blast removed — office receives BCC via fm_office_smtp_email() only */
function fm_notify_admins_new_application(array $row): void
{
    $office = fm_office_smtp_email();
    if ($office === '') {
        return;
    }

    $name = htmlspecialchars(fm_applicant_name($row), ENT_QUOTES, 'UTF-8');
    $ref = htmlspecialchars((string) ($row['reference_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $profession = htmlspecialchars((string) ($row['profession'] ?? ''), ENT_QUOTES, 'UTF-8');

    $body = "<p>A new <strong>Francophonie Mobility</strong> candidate form was submitted.</p>
        <ul>
          <li><strong>Name:</strong> {$name}</li>
          <li><strong>Reference:</strong> {$ref}</li>
          <li><strong>Email:</strong> {$email}</li>
          <li><strong>Profession:</strong> {$profession}</li>
        </ul>
        <p>Review it in the admin panel under <em>Francophonie Mobility</em>.</p>";

    $subject = 'New Francophonie Mobility Application — ' . ($row['reference_id'] ?? '');
    sendSMTPMail($office, $subject, fm_email_wrap('New Candidate Form', $body));
}

function fm_notify_applicant_received(array $row): bool
{
    $to = trim((string) ($row['email'] ?? ''));
    if ($to === '') {
        return false;
    }

    $name = htmlspecialchars(fm_applicant_name($row), ENT_QUOTES, 'UTF-8');
    $ref = htmlspecialchars((string) ($row['reference_id'] ?? ''), ENT_QUOTES, 'UTF-8');

    $body = "<p>Dear {$name},</p>
        <p>Thank you for submitting your <strong>Canada Francophonie Mobility (Mobilité Francophone)</strong> candidate information form.</p>
        <p style='font-family:monospace;font-size:18px;background:#f1f5f9;padding:12px;border-radius:8px'><strong>{$ref}</strong></p>
        <p>Save this reference ID. All updates will be sent to this email address.</p>
        <p>Our team will review your documents and contact you if anything else is needed.</p>";

    $subject = 'Francophonie Mobility — Application Received — ' . ($row['reference_id'] ?? '');
    return sendSMTPMail($to, $subject, fm_email_wrap('Application Received', $body), [], fm_office_bcc_list());
}

function fm_build_form_summary_html(array $row): string
{
    $esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

    $frenchCerts = [];
    if (!empty($row['french_tef'])) {
        $frenchCerts[] = 'TEF';
    }
    if (!empty($row['french_tcf'])) {
        $frenchCerts[] = 'TCF';
    }
    $englishCerts = [];
    if (!empty($row['english_toefl'])) {
        $englishCerts[] = 'TOEFL';
    }
    if (!empty($row['english_ielts'])) {
        $englishCerts[] = 'IELTS';
    }

    return '
    <h3 style="margin-top:0;color:#1e4d2b">1. Personal Information</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:14px">
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0;width:40%"><strong>Full Name</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc(fm_applicant_name($row)) . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Date of Birth</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['date_of_birth'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Passport Number</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['passport_number'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Age</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['age'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Nationality</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['nationality'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Address</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . nl2br($esc($row['address'] ?? '')) . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Country of Residence</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['country_of_residence'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Profession</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['profession'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Years of Experience</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['years_experience'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Email</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['email'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Phone</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">+' . $esc(($row['phone_area_code'] ?? '') . ' ' . ($row['phone_number'] ?? '')) . '</td></tr>
    </table>
    <h3 style="color:#1e4d2b">2. Education Background</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:14px">
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0;width:40%"><strong>Highest Degree</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['highest_degree'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Field of Study</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['field_of_study'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>University/College</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['university_name'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Country of Study</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['country_of_study'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Graduation Year</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc($row['graduation_year'] ?? '') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0"><strong>Other Certifications</strong></td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . nl2br($esc($row['other_certifications'] ?? '')) . '</td></tr>
    </table>
    <h3 style="color:#1e4d2b">3. Language Abilities</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:14px">
      <tr><td colspan="2" style="padding:8px 6px;background:#f8fafc"><strong>French</strong></td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0;width:40%">Level</td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc(fm_level_label((string) ($row['french_level'] ?? ''))) . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0">Certificates</td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc(implode(', ', $frenchCerts) ?: '—') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0">Work professionally in French</td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc(fm_yes_no_label((string) ($row['french_professional'] ?? ''))) . '</td></tr>
      <tr><td colspan="2" style="padding:8px 6px;background:#f8fafc"><strong>English</strong></td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0">Level</td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc(fm_level_label((string) ($row['english_level'] ?? ''))) . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0">Certificates</td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc(implode(', ', $englishCerts) ?: '—') . '</td></tr>
      <tr><td style="padding:6px;border-bottom:1px solid #e2e8f0">Work professionally in English</td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $esc(fm_yes_no_label((string) ($row['english_professional'] ?? ''))) . '</td></tr>
    </table>
    <h3 style="color:#1e4d2b">4. WES</h3>
    <p><strong>Do you have WES?</strong> ' . $esc(fm_yes_no_label((string) ($row['has_wes'] ?? ''))) . '</p>
    <h3 style="color:#1e4d2b">5. Canada Available Job Opportunities</h3>
    <p><strong>Preferred Job Offer:</strong> ' . $esc(fm_job_offer_label((string) ($row['job_offer'] ?? ''))) . '</p>';
}

function fm_resolve_upload_path(string $relativePath): string
{
    require_once __DIR__ . '/francophonie_mobility_files.php';
    $abs = fm_abs_upload_path($relativePath);
    return $abs ?? '';
}

function fm_approval_recipient_email(): string
{
    xander_load_env_file();
    $to = trim(xander_env_get('FRANCOPHONIE_MOBILITY_APPROVAL_EMAIL'));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $to = trim(xander_env_get_from_dotenv_file('FRANCOPHONIE_MOBILITY_APPROVAL_EMAIL'));
    }
    return ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) ? $to : '';
}

/**
 * @return array{attachments: array<int, array{path:string, name:string}>, labels: string[]}
 */
function fm_collect_approval_attachments(array $row): array
{
    $attachments = [];
    $labels = [];
    $ref = (string) ($row['reference_id'] ?? '');

    $singleMap = [
        'cv_file' => 'CV',
        'french_cert_file' => 'French Certificate',
        'english_cert_file' => 'English Certificate',
    ];
    foreach ($singleMap as $col => $label) {
        $abs = fm_resolve_upload_path((string) ($row[$col] ?? ''));
        if ($abs === '') {
            continue;
        }
        $ext = pathinfo($abs, PATHINFO_EXTENSION);
        $attachments[] = [
            'path' => $abs,
            'name' => $ref . '_' . str_replace(' ', '_', $label) . ($ext ? '.' . $ext : ''),
        ];
        $labels[] = $label;
    }

    require_once __DIR__ . '/francophonie_mobility_files.php';
    $academicPaths = fm_parse_stored_files((string) ($row['academic_docs_file'] ?? ''));
    foreach ($academicPaths as $i => $rel) {
        $abs = fm_resolve_upload_path($rel);
        if ($abs === '') {
            continue;
        }
        $ext = pathinfo($abs, PATHINFO_EXTENSION);
        $suffix = count($academicPaths) > 1 ? ' ' . ($i + 1) : '';
        $label = 'Academic Document' . $suffix;
        $attachments[] = [
            'path' => $abs,
            'name' => $ref . '_Academic' . ($suffix !== '' ? '_' . ($i + 1) : '') . ($ext ? '.' . $ext : ''),
        ];
        $labels[] = $label;
    }

    return ['attachments' => $attachments, 'labels' => $labels];
}

/**
 * On approval: email full form + all documents to FRANCOPHONIE_MOBILITY_APPROVAL_EMAIL (.env).
 */
function fm_send_approval_package(array $row): bool
{
    $to = fm_approval_recipient_email();
    if ($to === '') {
        error_log('FRANCOPHONIE_MOBILITY_APPROVAL_EMAIL is not set or invalid in .env');
        return false;
    }

    $ref = (string) ($row['reference_id'] ?? '');
    $summary = fm_build_form_summary_html($row);
    $pack = fm_collect_approval_attachments($row);
    $attachments = $pack['attachments'];
    $labels = $pack['labels'];

    $attachHtml = $labels === []
        ? '<p><em>No document files were attached (none on file or paths missing).</em></p>'
        : '<p><strong>Attached files (' . count($labels) . '):</strong></p><ul><li>'
            . implode('</li><li>', array_map(static fn($l) => htmlspecialchars($l, ENT_QUOTES, 'UTF-8'), $labels))
            . '</li></ul>';

    $body = '<p>Approved Francophonie Mobility candidate package for <strong>'
        . htmlspecialchars(fm_applicant_name($row), ENT_QUOTES, 'UTF-8')
        . '</strong>.</p>'
        . '<p><strong>Reference:</strong> ' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Candidate email:</strong> ' . htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
        . $attachHtml
        . '<hr style="border:0;border-top:1px solid #e2e8f0;margin:20px 0">'
        . $summary;

    $subject = 'Approved Francophonie Mobility Package — ' . $ref;
    $ok = sendSMTPMail($to, $subject, fm_email_wrap('Approved Candidate Package', $body), $attachments);
    if (!$ok) {
        error_log('FM approval package failed to send to ' . $to . ' ref ' . $ref);
    }
    return $ok;
}
