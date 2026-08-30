<?php
/**
 * Job applications + visa (form_17) status notifications (email / WhatsApp).
 * Reuses WhatsApp Cloud helpers from student_status_notify.php.
 *
 * Meta templates: set constants below to match approved template names.
 * If empty, only session messages (24h window) are used for WhatsApp.
 */
require_once __DIR__ . '/mail_smtp.php';
require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/student_status_notify.php';
require_once __DIR__ . '/job_application_status.php';

/** Job WhatsApp template ({{1}} name, {{2}} status, {{3}} details) — empty = session only */
const XANDER_WHATSAPP_JOB_TEMPLATE_NAME = '';
const XANDER_WHATSAPP_JOB_TEMPLATE_LANG = 'en_US';
const XANDER_WHATSAPP_JOB_TEMPLATE_PARAMS = 3;

/** Visit visa — form_17 applications where visa_type does not contain "study" */
const XANDER_WHATSAPP_VISA_VISIT_TEMPLATE_NAME = '';
/** Study visa */
const XANDER_WHATSAPP_VISA_STUDY_TEMPLATE_NAME = '';
const XANDER_WHATSAPP_VISA_TEMPLATE_LANG = 'en_US';
const XANDER_WHATSAPP_VISA_TEMPLATE_PARAMS = 3;

/**
 * @return array<int, string>
 */
function xander_job_notify_detail_lines(array $row): array
{
    $lines = [];
    $uid = trim((string) ($row['user_id'] ?? ''));
    if ($uid !== '') {
        $lines[] = 'Reference: ' . $uid;
    }
    $loc = trim((string) ($row['province_state'] ?? ''));
    $dist = trim((string) ($row['district'] ?? ''));
    if ($loc !== '' || $dist !== '') {
        $lines[] = 'Location: ' . trim($loc . ', ' . $dist);
    }
    $sec = trim((string) ($row['sector'] ?? ''));
    $cw = trim((string) ($row['cell_ward'] ?? ''));
    $vil = trim((string) ($row['village'] ?? ''));
    if ($sec !== '' || $cw !== '' || $vil !== '') {
        $lines[] = 'Area: ' . trim($sec . ' · ' . $cw . ' · ' . $vil);
    }
    $en = trim((string) ($row['emergency_full_name'] ?? ''));
    $er = trim((string) ($row['emergency_relationship'] ?? ''));
    $ep = trim((string) ($row['emergency_area_code'] ?? '') . ' ' . (string) ($row['emergency_phone_number'] ?? ''));
    if ($en !== '' || $ep !== '') {
        $lines[] = 'Emergency: ' . trim($en . ($er !== '' ? ' (' . $er . ')' : '')) . ($ep !== '' ? ' · ' . $ep : '');
    }

    return $lines;
}

function xander_job_template_detail_block(array $row): string
{
    $lines = xander_job_notify_detail_lines($row);

    return $lines === [] ? '—' : implode("\n", $lines);
}

function xander_job_applicant_name(array $row): string
{
    return trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
}

function xander_job_applicant_phone_raw(array $row): string
{
    return trim((string) ($row['phone_area_code'] ?? '') . ' ' . (string) ($row['phone_number'] ?? ''));
}

/**
 * @return array<string, mixed>|null
 */
function xander_fetch_job_application_for_notify(mysqli $conn, int $id): ?array
{
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }
    $res = $conn->query('SELECT * FROM `job_applications` WHERE id = ' . $id . ' LIMIT 1');
    if (!$res) {
        return null;
    }
    $row = $res->fetch_assoc();
    $res->free();

    return $row ?: null;
}

function xander_send_job_status_email(string $toEmail, string $name, string $statusLabel, array $row, string $rejectionReason = ''): bool
{
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    try {
        $mail = xander_create_phpmailer();
        $mail->addAddress($toEmail, $name ?: $toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Job application status — ScholarSync Global';
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeStatus = htmlspecialchars($statusLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $rows = '';
        foreach (xander_job_notify_detail_lines($row) as $line) {
            $s = htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rows .= '<tr><td style="padding:8px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-size:14px;">' . $s . '</td></tr>';
        }
        $detailBlock = $rows !== ''
            ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin:16px 0;background:#fafafa;">' . $rows . '</table>'
            : '';

        $rr = trim($rejectionReason);
        $reasonBlock = '';
        if ($rr !== '') {
            $safeR = nl2br(htmlspecialchars($rr, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $reasonBlock = '<div style="margin:0 0 16px;padding:14px 16px;background:#fef2f2;border-left:4px solid #dc2626;border-radius:8px;">'
                . '<p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#991b1b;text-transform:uppercase;">Message from our team</p>'
                . '<p style="margin:0;font-size:15px;line-height:1.55;color:#1e293b;">' . $safeR . '</p></div>';
        }

        $mail->Body = '<div style="font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:600px;margin:0 auto;color:#1e293b;">'
            . '<p style="margin:0 0 12px;font-size:16px;">Dear ' . $safeName . ',</p>'
            . '<p style="margin:0 0 8px;">Your <strong>job application</strong> status is now</p>'
            . '<p style="margin:0 0 16px;font-size:18px;font-weight:700;color:#427431;">' . $safeStatus . '</p>'
            . $reasonBlock
            . $detailBlock
            . '<p style="margin:16px 0 0;font-size:14px;color:#475569;">Questions? Reply to this email.</p>'
            . '<p style="margin:12px 0 0;font-size:13px;color:#94a3b8;">ScholarSync Global</p></div>';

        $plain = "Dear {$name},\n\nYour job application status is now: {$statusLabel}\n\n";
        if ($rr !== '') {
            $plain .= "Message from our team:\n{$rr}\n\n";
        }
        $plain .= implode("\n", xander_job_notify_detail_lines($row));
        $plain .= "\n\n— ScholarSync Global";
        $mail->AltBody = $plain;

        return $mail->send();
    } catch (\Throwable $e) {
        error_log('[application_status_notify] job email: ' . $e->getMessage());

        return false;
    }
}

function xander_job_whatsapp_session_body(string $name, string $statusLabel, array $row, string $rejectionReason = ''): string
{
    $n = xander_whatsapp_sanitize_user_text($name !== '' ? $name : 'Applicant');
    $s = xander_whatsapp_sanitize_user_text($statusLabel);
    $parts = [
        '*ScholarSync Global*',
        '*Job application — status update*',
        '',
        'Hello ' . $n . ',',
        '',
        'Your application status is now:',
        '*' . $s . '*',
    ];
    $rr = trim($rejectionReason);
    if ($rr !== '') {
        $parts[] = '';
        $parts[] = '*Message from our team*';
        $parts[] = xander_whatsapp_sanitize_user_text($rr);
    }
    $lines = xander_job_notify_detail_lines($row);
    if ($lines !== []) {
        $parts[] = '';
        $parts[] = '*Application details*';
        foreach ($lines as $line) {
            $parts[] = xander_whatsapp_sanitize_user_text($line);
        }
    }
    $parts[] = '';
    $parts[] = 'Questions? Reply on WhatsApp.';
    $parts[] = '';
    $parts[] = '— ScholarSync Global';

    return xander_notify_text_clip(implode("\n", $parts), 4096);
}

function xander_send_job_status_whatsapp(string $phoneRaw, string $name, string $statusLabel, array $row, string $rejectionReason = ''): array
{
    $empty = ['sent' => false, 'method' => '', 'error' => '', 'detail' => ''];

    $token = xander_env_get('WHATSAPP_ACCESS_TOKEN');
    $phoneId = xander_env_get('WHATSAPP_PHONE_NUMBER_ID');
    if ($token === '' || $phoneId === '') {
        if (function_exists('xander_whatsapp_env_debug_report_missing_credentials')) {
            xander_whatsapp_env_debug_report_missing_credentials();
        }
        $empty['error'] = 'WhatsApp is not configured (missing token or phone number ID).';

        return $empty;
    }

    $defaultCc = xander_env_get('WHATSAPP_DEFAULT_COUNTRY_CODE');
    $defaultCcOrNull = $defaultCc !== '' ? $defaultCc : null;
    $to = xander_format_phone_for_whatsapp_e164($phoneRaw, $defaultCcOrNull);
    if ($to === null) {
        $empty['error'] = 'Invalid phone number for WhatsApp.';

        return $empty;
    }
    if (!function_exists('curl_init')) {
        $empty['error'] = 'Server has no cURL.';

        return $empty;
    }

    $version = xander_env_get('META_GRAPH_VERSION') ?: 'v19.0';
    $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode((string) $phoneId) . '/messages';

    $pc = (int) XANDER_WHATSAPP_JOB_TEMPLATE_PARAMS;
    $templateBodyTexts = [
        $name ?: 'Applicant',
        $statusLabel,
    ];
    if ($pc >= 3) {
        $detail = xander_job_template_detail_block($row);
        if (trim($rejectionReason) !== '') {
            $detail = xander_notify_text_clip(
                "Message from our team:\n" . xander_whatsapp_sanitize_user_text(trim($rejectionReason)) . "\n\n" . $detail,
                1024
            );
        }
        $templateBodyTexts[] = $detail;
    }
    $templateBodyTexts = array_values(array_slice($templateBodyTexts, 0, max(0, $pc)));

    return xander_whatsapp_send_template_or_session(
        $to,
        $url,
        $token,
        XANDER_WHATSAPP_JOB_TEMPLATE_NAME,
        XANDER_WHATSAPP_JOB_TEMPLATE_LANG,
        $pc,
        $templateBodyTexts,
        xander_job_whatsapp_session_body($name, $statusLabel, $row, $rejectionReason)
    );
}

/**
 * @return array{email:array,whatsapp:array}|null
 */
function xander_notify_job_application_change(
    mysqli $conn,
    int $applicationId,
    string $statusKey,
    bool $sendEmail,
    bool $sendWhatsapp,
    string $rejectionReason = ''
): ?array {
    if (!$sendEmail && !$sendWhatsapp) {
        return null;
    }

    xander_load_env_file();

    $emailOut = ['requested' => $sendEmail, 'sent' => null, 'error' => ''];
    $waOut = ['requested' => $sendWhatsapp, 'sent' => null, 'method' => '', 'error' => ''];

    $row = xander_fetch_job_application_for_notify($conn, $applicationId);
    if (!$row) {
        if ($sendEmail) {
            $emailOut['sent'] = false;
            $emailOut['error'] = 'Application not found.';
        }
        if ($sendWhatsapp) {
            $waOut['sent'] = false;
            $waOut['error'] = 'Application not found.';
        }

        return ['email' => $emailOut, 'whatsapp' => $waOut];
    }

    $labels = xander_job_application_process_statuses();
    $label = $labels[$statusKey] ?? $statusKey;
    $name = xander_job_applicant_name($row);

    if ($sendEmail) {
        $to = trim((string) ($row['email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $emailOut['sent'] = false;
            $emailOut['error'] = 'No valid email on file.';
        } else {
            $ok = xander_send_job_status_email($to, $name, $label, $row, $rejectionReason);
            $emailOut['sent'] = $ok;
            $emailOut['error'] = $ok ? '' : 'Email could not be sent.';
        }
    }

    if ($sendWhatsapp) {
        $phone = xander_job_applicant_phone_raw($row);
        $r = xander_send_job_status_whatsapp($phone, $name, $label, $row, $rejectionReason);
        $waOut['sent'] = $r['sent'];
        $waOut['method'] = $r['method'];
        $waOut['error'] = $r['error'];
    }

    return ['email' => $emailOut, 'whatsapp' => $waOut];
}

/* ===================== Form 17 (visit / study visa) ===================== */

function xander_form17_is_study_visa(array $row): bool
{
    $s = strtolower(trim((string) ($row['visa_type'] ?? '')));

    return strpos($s, 'study') !== false;
}

/**
 * @return array<int, string>
 */
function xander_form17_notify_detail_lines(array $row): array
{
    $lines = [];
    $uid = trim((string) ($row['user_id'] ?? ''));
    if ($uid !== '') {
        $lines[] = 'Application ID: ' . $uid;
    }
    $vt = trim((string) ($row['visa_type'] ?? ''));
    if ($vt !== '') {
        $lines[] = 'Visa type: ' . $vt;
    }
    $cf = trim((string) ($row['country_applying_from'] ?? ''));
    $ct = trim((string) ($row['country_to_visit'] ?? ''));
    if ($cf !== '') {
        $lines[] = 'From: ' . $cf;
    }
    if ($ct !== '') {
        $lines[] = 'To: ' . $ct;
    }
    $reg = trim((string) ($row['region_name'] ?? ''));
    if ($reg === '' && isset($row['_region_name'])) {
        $reg = trim((string) $row['_region_name']);
    }
    $cn = trim((string) ($row['country_name'] ?? ''));
    if ($cn === '' && isset($row['_country_name'])) {
        $cn = trim((string) $row['_country_name']);
    }
    if ($reg !== '') {
        $lines[] = 'Region: ' . $reg;
    }
    if ($cn !== '') {
        $lines[] = 'Country: ' . $cn;
    }
    $pp = trim((string) ($row['passport_number'] ?? ''));
    if ($pp !== '') {
        $lines[] = 'Passport: ' . $pp;
    }

    return $lines;
}

function xander_form17_template_detail_block(array $row): string
{
    $lines = xander_form17_notify_detail_lines($row);

    return $lines === [] ? '—' : implode("\n", $lines);
}

function xander_form17_applicant_name(array $row): string
{
    $n = trim((string) ($row['prefix'] ?? ''));
    $n .= ' ' . trim((string) ($row['first_name'] ?? ''));
    if (!empty($row['middle_name'])) {
        $n .= ' ' . trim((string) $row['middle_name']);
    }
    $n .= ' ' . trim((string) ($row['last_name'] ?? ''));

    return trim(preg_replace('/\s+/', ' ', $n));
}

/**
 * @return array<string, mixed>|null
 */
function xander_fetch_form17_for_notify(mysqli $conn, string $userId): ?array
{
    $userId = trim($userId);
    if ($userId === '') {
        return null;
    }
    $q = $conn->real_escape_string($userId);
    $sql = "SELECT f.*, r.name AS _region_name, c.name AS _country_name
            FROM form_17_applications f
            LEFT JOIN regions r ON f.region_id = r.id
            LEFT JOIN countries c ON f.country_id = c.id
            WHERE f.user_id = '{$q}' LIMIT 1";
    $res = $conn->query($sql);
    if (!$res) {
        return null;
    }
    $row = $res->fetch_assoc();
    $res->free();

    return $row ?: null;
}

function xander_visa_template_name_for_row(array $row): string
{
    return xander_form17_is_study_visa($row)
        ? trim((string) XANDER_WHATSAPP_VISA_STUDY_TEMPLATE_NAME)
        : trim((string) XANDER_WHATSAPP_VISA_VISIT_TEMPLATE_NAME);
}

function xander_send_form17_status_email(string $toEmail, string $name, string $statusLabel, array $row, string $rejectionReason = ''): bool
{
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $kind = xander_form17_is_study_visa($row) ? 'Study visa' : 'Visit visa';

    try {
        $mail = xander_create_phpmailer();
        $mail->addAddress($toEmail, $name ?: $toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Visa application status — ScholarSync Global';
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeStatus = htmlspecialchars($statusLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $rows = '';
        foreach (xander_form17_notify_detail_lines($row) as $line) {
            $s = htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rows .= '<tr><td style="padding:8px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-size:14px;">' . $s . '</td></tr>';
        }
        $detailBlock = $rows !== ''
            ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin:16px 0;background:#fafafa;">' . $rows . '</table>'
            : '';

        $rr = trim($rejectionReason);
        $reasonBlock = '';
        if ($rr !== '') {
            $safeR = nl2br(htmlspecialchars($rr, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $reasonBlock = '<div style="margin:0 0 16px;padding:14px 16px;background:#fef2f2;border-left:4px solid #dc2626;border-radius:8px;">'
                . '<p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#991b1b;text-transform:uppercase;">Message from our team</p>'
                . '<p style="margin:0;font-size:15px;line-height:1.55;color:#1e293b;">' . $safeR . '</p></div>';
        }

        $mail->Body = '<div style="font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:600px;margin:0 auto;color:#1e293b;">'
            . '<p style="margin:0 0 12px;font-size:16px;">Dear ' . $safeName . ',</p>'
            . '<p style="margin:0 0 8px;">Your <strong>' . htmlspecialchars($kind, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</strong> application status is now</p>'
            . '<p style="margin:0 0 16px;font-size:18px;font-weight:700;color:#427431;">' . $safeStatus . '</p>'
            . $reasonBlock
            . $detailBlock
            . '<p style="margin:16px 0 0;font-size:14px;color:#475569;">Questions? Reply to this email.</p>'
            . '<p style="margin:12px 0 0;font-size:13px;color:#94a3b8;">ScholarSync Global</p></div>';

        $plain = "Dear {$name},\n\nYour {$kind} application status is now: {$statusLabel}\n\n";
        if ($rr !== '') {
            $plain .= "Message from our team:\n{$rr}\n\n";
        }
        $plain .= implode("\n", xander_form17_notify_detail_lines($row));
        $plain .= "\n\n— ScholarSync Global";
        $mail->AltBody = $plain;

        return $mail->send();
    } catch (\Throwable $e) {
        error_log('[application_status_notify] visa email: ' . $e->getMessage());

        return false;
    }
}

function xander_form17_whatsapp_session_body(string $name, string $statusLabel, array $row, string $rejectionReason = ''): string
{
    $kind = xander_form17_is_study_visa($row) ? 'Study visa' : 'Visit visa';
    $n = xander_whatsapp_sanitize_user_text($name !== '' ? $name : 'Applicant');
    $s = xander_whatsapp_sanitize_user_text($statusLabel);
    $parts = [
        '*ScholarSync Global*',
        '*' . xander_whatsapp_sanitize_user_text($kind) . ' — status update*',
        '',
        'Hello ' . $n . ',',
        '',
        'Your application status is now:',
        '*' . $s . '*',
    ];
    $rr = trim($rejectionReason);
    if ($rr !== '') {
        $parts[] = '';
        $parts[] = '*Message from our team*';
        $parts[] = xander_whatsapp_sanitize_user_text($rr);
    }
    $lines = xander_form17_notify_detail_lines($row);
    if ($lines !== []) {
        $parts[] = '';
        $parts[] = '*Application details*';
        foreach ($lines as $line) {
            $parts[] = xander_whatsapp_sanitize_user_text($line);
        }
    }
    $parts[] = '';
    $parts[] = 'Questions? Reply on WhatsApp.';
    $parts[] = '';
    $parts[] = '— ScholarSync Global';

    return xander_notify_text_clip(implode("\n", $parts), 4096);
}

function xander_send_form17_status_whatsapp(string $phoneRaw, string $name, string $statusLabel, array $row, string $rejectionReason = ''): array
{
    $empty = ['sent' => false, 'method' => '', 'error' => '', 'detail' => ''];

    $token = xander_env_get('WHATSAPP_ACCESS_TOKEN');
    $phoneId = xander_env_get('WHATSAPP_PHONE_NUMBER_ID');
    if ($token === '' || $phoneId === '') {
        if (function_exists('xander_whatsapp_env_debug_report_missing_credentials')) {
            xander_whatsapp_env_debug_report_missing_credentials();
        }
        $empty['error'] = 'WhatsApp is not configured (missing token or phone number ID).';

        return $empty;
    }

    $defaultCc = xander_env_get('WHATSAPP_DEFAULT_COUNTRY_CODE');
    $to = xander_format_phone_for_whatsapp_e164($phoneRaw, $defaultCc !== '' ? $defaultCc : null);
    if ($to === null) {
        $empty['error'] = 'Invalid phone number for WhatsApp.';

        return $empty;
    }
    if (!function_exists('curl_init')) {
        $empty['error'] = 'Server has no cURL.';

        return $empty;
    }

    $version = xander_env_get('META_GRAPH_VERSION') ?: 'v19.0';
    $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode((string) $phoneId) . '/messages';

    $tplName = xander_visa_template_name_for_row($row);
    $pc = (int) XANDER_WHATSAPP_VISA_TEMPLATE_PARAMS;
    $templateBodyTexts = [
        $name ?: 'Applicant',
        $statusLabel,
    ];
    if ($pc >= 3) {
        $detail = xander_form17_template_detail_block($row);
        if (trim($rejectionReason) !== '') {
            $detail = xander_notify_text_clip(
                "Message from our team:\n" . xander_whatsapp_sanitize_user_text(trim($rejectionReason)) . "\n\n" . $detail,
                1024
            );
        }
        $templateBodyTexts[] = $detail;
    }
    $templateBodyTexts = array_values(array_slice($templateBodyTexts, 0, max(0, $pc)));

    return xander_whatsapp_send_template_or_session(
        $to,
        $url,
        $token,
        $tplName,
        XANDER_WHATSAPP_VISA_TEMPLATE_LANG,
        $pc,
        $templateBodyTexts,
        xander_form17_whatsapp_session_body($name, $statusLabel, $row, $rejectionReason)
    );
}

/**
 * @return array{email:array,whatsapp:array}|null
 */
function xander_notify_form17_visa_change(
    mysqli $conn,
    string $userId,
    string $statusKey,
    bool $sendEmail,
    bool $sendWhatsapp,
    string $rejectionReason = ''
): ?array {
    if (!$sendEmail && !$sendWhatsapp) {
        return null;
    }

    xander_load_env_file();

    $emailOut = ['requested' => $sendEmail, 'sent' => null, 'error' => ''];
    $waOut = ['requested' => $sendWhatsapp, 'sent' => null, 'method' => '', 'error' => ''];

    $row = xander_fetch_form17_for_notify($conn, $userId);
    if (!$row) {
        if ($sendEmail) {
            $emailOut['sent'] = false;
            $emailOut['error'] = 'Application not found.';
        }
        if ($sendWhatsapp) {
            $waOut['sent'] = false;
            $waOut['error'] = 'Application not found.';
        }

        return ['email' => $emailOut, 'whatsapp' => $waOut];
    }

    $labels = xander_job_application_process_statuses();
    $label = $labels[$statusKey] ?? $statusKey;
    $name = xander_form17_applicant_name($row);

    if ($sendEmail) {
        $to = trim((string) ($row['email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $emailOut['sent'] = false;
            $emailOut['error'] = 'No valid email on file.';
        } else {
            $ok = xander_send_form17_status_email($to, $name, $label, $row, $rejectionReason);
            $emailOut['sent'] = $ok;
            $emailOut['error'] = $ok ? '' : 'Email could not be sent.';
        }
    }

    if ($sendWhatsapp) {
        $phone = trim((string) ($row['applicant_mobile'] ?? ''));
        $r = xander_send_form17_status_whatsapp($phone, $name, $label, $row, $rejectionReason);
        $waOut['sent'] = $r['sent'];
        $waOut['method'] = $r['method'];
        $waOut['error'] = $r['error'];
    }

    return ['email' => $emailOut, 'whatsapp' => $waOut];
}
