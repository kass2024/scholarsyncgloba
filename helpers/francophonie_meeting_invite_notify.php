<?php
declare(strict_types=1);

require_once __DIR__ . '/mail_smtp.php';
require_once __DIR__ . '/francophonie_mobility_notify.php';
require_once __DIR__ . '/zoom_meeting_sdk.php';

/**
 * @param array<string, mixed> $meeting
 */
function fm_meeting_invitation_email_html(
    string $recipientName,
    array $meeting,
    string $customMessage = ''
): string {
    $name = htmlspecialchars($recipientName !== '' ? $recipientName : 'Applicant', ENT_QUOTES, 'UTF-8');
    $topic = htmlspecialchars((string) ($meeting['topic'] ?? 'Meeting'), ENT_QUOTES, 'UTF-8');
    $joinUrlRaw = fm_meeting_normalize_public_url((string) ($meeting['join_url'] ?? ''));
    $joinUrlAttr = htmlspecialchars($joinUrlRaw, ENT_QUOTES, 'UTF-8');
    $password = htmlspecialchars((string) ($meeting['password'] ?? ''), ENT_QUOTES, 'UTF-8');
    $meetingNumber = htmlspecialchars((string) ($meeting['meeting_number'] ?? ''), ENT_QUOTES, 'UTF-8');

    $when = '';
    if (!empty($meeting['start_time_display'])) {
        $when = htmlspecialchars((string) $meeting['start_time_display'], ENT_QUOTES, 'UTF-8');
    }

    $duration = (int) ($meeting['duration'] ?? 60);
    $extra = '';
    if (trim($customMessage) !== '') {
        $extra = '<div style="background:#f8fafc;border-left:4px solid #1e4d2b;padding:14px 16px;margin:20px 0;border-radius:0 8px 8px 0">'
            . '<p style="margin:0;color:#334155;line-height:1.6">' . nl2br(htmlspecialchars($customMessage, ENT_QUOTES, 'UTF-8')) . '</p></div>';
    }

    $pwdRow = $password !== ''
        ? '<tr><td style="padding:8px;border-bottom:1px solid #e2e8f0"><strong>Passcode</strong></td><td style="padding:8px;border-bottom:1px solid #e2e8f0;font-family:monospace">' . $password . '</td></tr>'
        : '';

    $inner = "
      <p style='font-size:16px'>Dear {$name},</p>
      <p>You are invited to an online information session for <strong>Mobilité Francophone</strong>.</p>
      {$extra}
      <table style='width:100%;border-collapse:collapse;margin:20px 0;font-size:14px'>
        <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;width:38%'><strong>Topic</strong></td><td style='padding:8px;border-bottom:1px solid #e2e8f0'>{$topic}</td></tr>
        " . ($when !== '' ? "<tr><td style='padding:8px;border-bottom:1px solid #e2e8f0'><strong>Date &amp; time</strong></td><td style='padding:8px;border-bottom:1px solid #e2e8f0'>{$when}</td></tr>" : '') . "
        <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0'><strong>Duration</strong></td><td style='padding:8px;border-bottom:1px solid #e2e8f0'>{$duration} minutes</td></tr>
        " . ($meetingNumber !== '' ? "<tr><td style='padding:8px;border-bottom:1px solid #e2e8f0'><strong>Meeting ID</strong></td><td style='padding:8px;border-bottom:1px solid #e2e8f0;font-family:monospace'>{$meetingNumber}</td></tr>" : '') . "
        {$pwdRow}
      </table>
      <p style='text-align:center;margin:28px 0'>
        <a href='{$joinUrlAttr}' style='display:inline-block;background:linear-gradient(135deg,#1e4d2b,#3661B9);color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px'>Join in your browser</a>
      </p>
      <p style='font-size:13px;color:#64748b;text-align:center;margin:-12px 0 20px'>No Zoom app required — opens directly in your web browser.</p>
      <p style='font-size:13px;color:#64748b;word-break:break-all'>Or copy this link: <a href='{$joinUrlAttr}' style='color:#3661B9'>{$joinUrlAttr}</a></p>
      <p style='font-size:13px;color:#64748b;margin-top:20px'>Please join a few minutes early. If you have technical issues, reply to this email.</p>";

    return fm_email_wrap('Meeting Invitation — Mobilité Francophone', $inner);
}

/**
 * Try staff-contract SMTP first, then finance/admission fallbacks.
 *
 * @return array{ok: bool, error?: string}
 */
function fm_send_meeting_invitation_email(
    string $to,
    string $recipientName,
    array $meeting,
    string $customMessage = ''
): array {
    $subject = 'Meeting invitation: ' . trim((string) ($meeting['topic'] ?? 'Mobilité Francophone'));
    $html = fm_meeting_invitation_email_html($recipientName, $meeting, $customMessage);
    $bcc = fm_office_bcc_list();

    $mailers = [
        static fn () => pcvc_staff_contract_smtp_mailer('ScholarSync Global'),
        static fn () => pcvc_finance_smtp_mailer('ScholarSync Global'),
        static fn () => app_admission_mailer(),
    ];

    $errors = [];
    foreach ($mailers as $factory) {
        $result = sendSMTPMailDetailed($to, $subject, $html, [], $bcc, $factory);
        if (!empty($result['ok'])) {
            return $result;
        }
        $errors[] = trim((string) ($result['error'] ?? 'SMTP failed'));
    }

    return ['ok' => false, 'error' => implode(' · ', array_filter($errors)) ?: 'SMTP send failed'];
}
