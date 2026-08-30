<?php
declare(strict_types=1);

/**
 * Daily attendance digest — email notifications (checkout + daily cron).
 *
 * ---------------------------------------------------------------------------
 * META TEMPLATE (create in WhatsApp Manager → Message templates → Utility)
 * ---------------------------------------------------------------------------
 * Internal name: pcvc_daily_attendance_summary
 * Language: English (en)
 * Body (6 variables — matches WHATSAPP_ATTENDANCE_DAILY_TEMPLATE_PARAMS=6):
 *
 * Hello {{1}},
 *
 * Here is your attendance summary for {{2}} at ScholarSync Global:
 *
 * Check-in: {{3}}
 * Check-out: {{4}}
 * Time worked: {{5}}
 * Salary earned: {{6}}
 *
 * If anything looks incorrect, contact your supervisor. Thank you for your work today.
 *
 * — ScholarSync MIS
 *
 * {{1}} staff full name
 * {{2}} date (YYYY-MM-DD)
 * {{3}} check-in time or "Not recorded"
 * {{4}} check-out time or "Not recorded"
 * {{5}} worked duration (e.g. "6h 30m" or "0 min")
 * {{6}} salary (e.g. "RWF 3,250" or "RWF 0")
 *
 * .env: DAILY_ATTENDANCE_NOTIFY_CHANNELS=email|whatsapp|both|none
 *       WHATSAPP_ATTENDANCE_DAILY_TEMPLATE_NAME (default pcvc_daily_attendance_summary)
 */

require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/mail_smtp.php';
require_once __DIR__ . '/student_status_notify.php';
require_once __DIR__ . '/attendance_whatsapp_notify.php';
require_once __DIR__ . '/../includes/company_branding.php';

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * @return array{email: bool, whatsapp: bool}
 */
function pcvc_daily_attendance_notify_channels(): array
{
    xander_load_env_file();
    $raw = strtolower(trim(xander_env_get('DAILY_ATTENDANCE_NOTIFY_CHANNELS')));
    if ($raw === 'none' || $raw === 'off' || $raw === '0') {
        return ['email' => false, 'whatsapp' => false];
    }
    if ($raw === 'whatsapp' || $raw === 'wa') {
        return ['email' => false, 'whatsapp' => true];
    }

    // Default and "email" / legacy "both": email only (WhatsApp disabled for attendance).
    return ['email' => true, 'whatsapp' => false];
}

/**
 * @return array{name: string, lang: string, params: int}
 */
function pcvc_daily_attendance_whatsapp_template_config(): array
{
    return pcvc_daily_whatsapp_template_config();
}

function pcvc_daily_attendance_format_duration(int $minutes): string
{
    if ($minutes <= 0) {
        return '0 min';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h > 0 && $m > 0) {
        return $h . 'h ' . $m . 'm';
    }
    if ($h > 0) {
        return $h . 'h';
    }

    return $m . ' min';
}

function pcvc_daily_attendance_format_salary(int $rwf): string
{
    return 'RWF ' . number_format(max(0, $rwf));
}

function pcvc_daily_attendance_format_time(?string $datetime): string
{
    $t = trim((string) $datetime);
    if ($t === '') {
        return 'Not recorded';
    }
    $ts = strtotime($t);
    if ($ts === false) {
        return $t;
    }

    return date('g:i A', $ts);
}

function pcvc_daily_attendance_flat_var(string $text, int $maxLen = 240): string
{
    $t = trim(preg_replace('/[\r\n]+/', ' ', $text) ?? '');
    $t = preg_replace('/\s{2,}/', ' ', $t) ?? $t;
    if ($t === '') {
        $t = '—';
    }

    return xander_whatsapp_sanitize_user_text(xander_notify_text_clip($t, $maxLen));
}

/**
 * @return array<string, mixed>
 */
function pcvc_daily_attendance_build_summary(
    array $admin,
    string $date,
    ?array $attendance
): array {
    $name = trim((string) ($admin['full_name'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($admin['first_name'] ?? '') . ' ' . (string) ($admin['last_name'] ?? ''));
    }

    $status = 'no_attendance';
    $checkIn = null;
    $checkOut = null;
    $minutes = 0;
    $salary = 0;

    if ($attendance !== null) {
        $checkIn = $attendance['check_in_time'] ?? null;
        $checkOut = $attendance['check_out_time'] ?? null;
        $minutes = (int) ($attendance['total_work_minutes'] ?? 0);
        $salary = (int) ($attendance['daily_salary_rwf'] ?? 0);
        if ($salary <= 0) {
            $salary = (int) ($attendance['total_payment_rwf'] ?? 0);
        }

        if (!empty($checkIn) && !empty($checkOut)) {
            $status = 'full';
        } elseif (!empty($checkIn)) {
            $status = 'checkin_only';
        }
    }

    return [
        'admin_id'   => (int) ($admin['id'] ?? 0),
        'name'       => $name !== '' ? $name : 'Staff',
        'email'      => trim((string) ($admin['email'] ?? '')),
        'phone'      => pcvc_admin_resolve_whatsapp_phone($admin),
        'admin_row'  => $admin,
        'role'       => trim((string) ($admin['role'] ?? '')),
        'date'       => $date,
        'status'     => $status,
        'check_in'   => $checkIn,
        'check_out'  => $checkOut,
        'minutes'    => $minutes,
        'salary'     => $salary,
        'work_label' => pcvc_daily_attendance_format_duration($minutes),
        'salary_label' => pcvc_daily_attendance_format_salary($salary),
        'check_in_label' => pcvc_daily_attendance_format_time($checkIn),
        'check_out_label' => pcvc_daily_attendance_format_time($checkOut),
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function pcvc_daily_attendance_fetch_summaries(mysqli $conn, string $date): array
{
    $summaries = [];

    $admins = $conn->query("
        SELECT id, full_name, first_name, last_name, email, phone_number, role
        FROM admins
        WHERE role IN ('staff', 'superadmin')
        ORDER BY id ASC
    ");
    if (!$admins) {
        return [];
    }

    $attStmt = $conn->prepare("
        SELECT check_in_time, check_out_time, total_work_minutes, daily_salary_rwf, total_payment_rwf
        FROM attendance
        WHERE admin_id = ? AND date = ?
        LIMIT 1
    ");

    while ($admin = $admins->fetch_assoc()) {
        $attendance = null;
        if ($attStmt) {
            $adminId = (int) $admin['id'];
            $attStmt->bind_param('is', $adminId, $date);
            $attStmt->execute();
            $res = $attStmt->get_result();
            if ($res && $res->num_rows > 0) {
                $attendance = $res->fetch_assoc();
            }
        }

        $summaries[] = pcvc_daily_attendance_build_summary($admin, $date, $attendance);

        if ($attendance === null) {
            $adminId = (int) $admin['id'];
            $insert = $conn->prepare(
                'INSERT IGNORE INTO job_summary (admin_id, summary_date, total_jobs, total_hours, avg_productivity_score)
                 VALUES (?, ?, 0, 0, 0)'
            );
            if ($insert) {
                $insert->bind_param('is', $adminId, $date);
                $insert->execute();
                $insert->close();
            }
        }
    }

    if ($attStmt) {
        $attStmt->close();
    }
    $admins->free();

    return $summaries;
}

function pcvc_daily_attendance_status_headline(array $summary): string
{
    return match ($summary['status'] ?? '') {
        'full' => 'Full attendance recorded',
        'checkin_only' => 'Check-in without check-out',
        default => 'No attendance recorded',
    };
}

function pcvc_daily_attendance_email_html(array $summary): string
{
    $safeName = htmlspecialchars((string) $summary['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeDate = htmlspecialchars((string) $summary['date'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $co = htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $headline = htmlspecialchars(pcvc_daily_attendance_status_headline($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $checkIn = htmlspecialchars((string) $summary['check_in_label'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $checkOut = htmlspecialchars((string) $summary['check_out_label'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $worked = htmlspecialchars((string) $summary['work_label'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $salary = htmlspecialchars((string) $summary['salary_label'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $statusColor = match ($summary['status'] ?? '') {
        'full' => '#166534',
        'checkin_only' => '#b45309',
        default => '#b91c1c',
    };

    return '<div style="font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:600px;margin:0 auto;color:#1e293b;">'
        . '<p style="margin:0 0 12px;font-size:16px;">Dear ' . $safeName . ',</p>'
        . '<p style="margin:0 0 16px;font-size:15px;line-height:1.5;">Your daily attendance summary for <strong>' . $safeDate . '</strong>:</p>'
        . '<div style="margin:0 0 18px;padding:16px 18px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">'
        . '<p style="margin:0 0 10px;font-size:13px;font-weight:700;color:' . $statusColor . ';">' . $headline . '</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:15px;">'
        . '<tr><td style="padding:6px 0;color:#64748b;">Check-in</td><td style="padding:6px 0;text-align:right;font-weight:600;">' . $checkIn . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#64748b;">Check-out</td><td style="padding:6px 0;text-align:right;font-weight:600;">' . $checkOut . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#64748b;">Time worked</td><td style="padding:6px 0;text-align:right;font-weight:600;">' . $worked . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#64748b;">Salary earned</td><td style="padding:6px 0;text-align:right;font-weight:700;color:#427431;">' . $salary . '</td></tr>'
        . '</table></div>'
        . '<p style="margin:0;font-size:14px;color:#475569;">If anything looks incorrect, contact your supervisor.</p>'
        . '<p style="margin:16px 0 0;font-size:13px;color:#94a3b8;">' . $co . ' — Attendance System</p></div>';
}

function pcvc_daily_attendance_email_subject(array $summary, string $prefix = 'Daily attendance'): string
{
    $date = (string) ($summary['date'] ?? date('Y-m-d'));
    $salary = (string) ($summary['salary_label'] ?? 'RWF 0');

    return $prefix . ' — ' . $date . ' — ' . $salary;
}

/**
 * Channels for instant checkout notifications (defaults to email only).
 *
 * @return array{email: bool, whatsapp: bool}
 */
function pcvc_checkout_notify_channels(): array
{
    xander_load_env_file();
    $raw = strtolower(trim(xander_env_get('CHECKOUT_ATTENDANCE_NOTIFY_CHANNELS')));
    if ($raw === '') {
        $raw = strtolower(trim(xander_env_get('DAILY_ATTENDANCE_NOTIFY_CHANNELS')));
    }
    if ($raw === 'none' || $raw === 'off' || $raw === '0') {
        return ['email' => false, 'whatsapp' => false];
    }
    if ($raw === 'whatsapp' || $raw === 'wa') {
        return ['email' => false, 'whatsapp' => true];
    }

    return ['email' => true, 'whatsapp' => false];
}

/**
 * @return array{sent: bool, method: string, error: string, detail: string, to: string}
 */
function pcvc_daily_attendance_send_whatsapp(array $summary): array
{
    return pcvc_attendance_whatsapp_send_summary($summary, pcvc_daily_whatsapp_template_config());
}

/**
 * Checkout instant WhatsApp (uses pcvc_checkout_attendance template).
 *
 * @return array{sent: bool, method: string, error: string, detail: string, to: string}
 */
function pcvc_checkout_attendance_send_whatsapp(array $summary): array
{
    return pcvc_attendance_whatsapp_send_summary($summary, pcvc_checkout_whatsapp_template_config());
}

function pcvc_daily_attendance_send_email(array $summary, string $subjectPrefix = 'Daily attendance'): bool
{
    $email = trim((string) ($summary['email'] ?? ''));
    if ($email === '') {
        return false;
    }

    if (trim(xander_env_get('SMTP_PASSWORD')) === '') {
        error_log('[daily-attendance] SMTP_PASSWORD missing — skip email to ' . $email);

        return false;
    }

    try {
        $mail = xander_create_phpmailer(PCVC_COMPANY_DISPLAY_NAME);
        $mail->addAddress($email, (string) $summary['name']);
        $mail->Subject = pcvc_daily_attendance_email_subject($summary, $subjectPrefix);
        $mail->Body = pcvc_daily_attendance_email_html($summary);
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $mail->Body));
        $mail->send();

        return true;
    } catch (MailerException $e) {
        error_log('[daily-attendance] Email failed for ' . $email . ': ' . $e->getMessage());

        return false;
    }
}

/**
 * Notify one admin via configured channels.
 *
 * @return array{email: bool, whatsapp: bool, wa_error: string}
 */
function pcvc_daily_attendance_notify_admin(
    array $summary,
    array $channels,
    string $emailSubjectPrefix = 'Daily attendance'
): array {
    $result = ['email' => false, 'whatsapp' => false, 'wa_error' => ''];

    if (!empty($channels['email'])) {
        $result['email'] = pcvc_daily_attendance_send_email($summary, $emailSubjectPrefix);
    }

    if (!empty($channels['whatsapp'])) {
        $wa = ($emailSubjectPrefix === 'Checkout confirmed')
            ? pcvc_checkout_attendance_send_whatsapp($summary)
            : pcvc_daily_attendance_send_whatsapp($summary);
        $result['whatsapp'] = $wa['sent'];
        $result['wa_error'] = $wa['error'];
        if (!$wa['sent'] && $wa['error'] !== '') {
            error_log('[daily-attendance] WhatsApp failed for admin '
                . (int) ($summary['admin_id'] ?? 0) . ' to ' . ($wa['to'] ?? '') . ': ' . $wa['error']);
        }
    }

    return $result;
}

/**
 * Superadmin rollup email sections.
 *
 * @param list<array<string, mixed>> $summaries
 */
function pcvc_daily_attendance_superadmin_report_html(string $date, array $summaries): string
{
    $full = '';
    $checkinOnly = '';
    $none = '';

    foreach ($summaries as $s) {
        $name = htmlspecialchars((string) $s['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $worked = htmlspecialchars((string) $s['work_label'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $salary = htmlspecialchars((string) $s['salary_label'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $line = '<li><strong>' . $name . '</strong> — ' . $worked . ' — ' . $salary . '</li>';

        if (($s['status'] ?? '') === 'full') {
            $full .= $line;
        } elseif (($s['status'] ?? '') === 'checkin_only') {
            $checkinOnly .= $line;
        } else {
            $none .= $line;
        }
    }

    if ($full === '') {
        $full = '<li><em>None</em></li>';
    }
    if ($checkinOnly === '') {
        $checkinOnly = '<li><em>None</em></li>';
    }
    if ($none === '') {
        $none = '<li><em>None</em></li>';
    }

    $safeDate = htmlspecialchars($date, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return '<div style="font-family:Arial,sans-serif;font-size:14px;">'
        . '<h2>Attendance report for ' . $safeDate . '</h2>'
        . '<h3>Full attendance</h3><ul>' . $full . '</ul>'
        . '<h3>Check-in only</h3><ul>' . $checkinOnly . '</ul>'
        . '<h3>No check-in</h3><ul>' . $none . '</ul>'
        . '<p style="margin-top:20px;font-size:12px;color:#64748b;">Generated by ScholarSync MIS Attendance System</p></div>';
}

/**
 * Send email + WhatsApp right after a successful checkout.
 *
 * @return array{email: bool, whatsapp: bool, wa_error: string, summary: array<string, mixed>|null}
 */
function pcvc_attendance_notify_after_checkout(mysqli $conn, int $adminId, string $date): array
{
    $empty = ['email' => false, 'whatsapp' => false, 'wa_error' => '', 'summary' => null];

    $stmt = $conn->prepare("
        SELECT id, full_name, first_name, last_name, email, phone_number, role
        FROM admins
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return $empty;
    }
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$admin) {
        return $empty;
    }

    $attStmt = $conn->prepare("
        SELECT check_in_time, check_out_time, total_work_minutes, daily_salary_rwf, total_payment_rwf
        FROM attendance
        WHERE admin_id = ? AND date = ?
        LIMIT 1
    ");
    if (!$attStmt) {
        return $empty;
    }
    $attStmt->bind_param('is', $adminId, $date);
    $attStmt->execute();
    $attendance = $attStmt->get_result()->fetch_assoc();
    $attStmt->close();

    if (!$attendance || empty($attendance['check_out_time'])) {
        return $empty;
    }

    $summary = pcvc_daily_attendance_build_summary($admin, $date, $attendance);
    $summary['phone'] = pcvc_admin_resolve_whatsapp_phone($admin);
    $summary['admin_row'] = $admin;

    $notify = pcvc_daily_attendance_notify_admin(
        $summary,
        pcvc_checkout_notify_channels(),
        'Checkout confirmed'
    );

    return [
        'email'    => $notify['email'],
        'whatsapp' => $notify['whatsapp'],
        'wa_error' => $notify['wa_error'],
        'summary'  => $summary,
    ];
}

function pcvc_daily_attendance_send_superadmin_report(mysqli $conn, string $date, array $summaries): bool
{
    if (trim(xander_env_get('SMTP_PASSWORD')) === '') {
        return false;
    }

    $supers = $conn->query("SELECT full_name, email FROM admins WHERE role = 'superadmin'");
    if (!$supers || $supers->num_rows === 0) {
        return false;
    }

    try {
        $mail = xander_create_phpmailer(PCVC_COMPANY_DISPLAY_NAME);
        while ($super = $supers->fetch_assoc()) {
            $em = trim((string) ($super['email'] ?? ''));
            if ($em !== '') {
                $mail->addAddress($em, (string) ($super['full_name'] ?? 'Superadmin'));
            }
        }
        $supers->free();

        if (count($mail->getToAddresses()) === 0) {
            return false;
        }

        $mail->Subject = 'Daily attendance summary — ' . $date;
        $mail->Body = pcvc_daily_attendance_superadmin_report_html($date, $summaries);
        $mail->send();

        return true;
    } catch (MailerException $e) {
        error_log('[daily-attendance] Superadmin report failed: ' . $e->getMessage());

        return false;
    }
}
