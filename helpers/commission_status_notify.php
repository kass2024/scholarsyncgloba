<?php
declare(strict_types=1);

/**
 * Commission status email + WhatsApp — uses .env SMTP_* and WHATSAPP_* (Meta template when configured).
 *
 * WhatsApp template (default pcvc_commission_update): register in Meta Business → WhatsApp → Message templates.
 * See .env.example for Meta copy-paste body (default 4 variables — passes Meta length rules).
 */

require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/mail_smtp.php';
require_once __DIR__ . '/student_status_notify.php';
require_once __DIR__ . '/../includes/company_branding.php';

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

const PCVC_COMMISSION_WA_VAR_MAX = 900;

function pcvc_commission_whatsapp_template_config(): array
{
    xander_load_env_file();
    $name = trim(xander_env_get('WHATSAPP_COMMISSION_TEMPLATE_NAME'));
    if ($name === '') {
        $name = 'pcvc_commission_update';
    }
    $lang = trim(xander_env_get('WHATSAPP_COMMISSION_TEMPLATE_LANG'));
    if ($lang === '') {
        $lang = 'en';
    }
    // pcvc_commission_update in Meta is always 4 body variables (see .env.example).
    $requested = (int) trim(xander_env_get('WHATSAPP_COMMISSION_TEMPLATE_PARAMS'));
    $params = 4;
    if ($requested >= 1 && $requested <= 4 && $name !== 'pcvc_commission_update') {
        $params = $requested;
    }

    return ['name' => $name, 'lang' => $lang, 'params' => $params];
}

/**
 * Flatten text for a single WhatsApp template variable (Meta rejects some newline-heavy payloads).
 */
function pcvc_commission_whatsapp_flat_var(string $text, int $maxLen = PCVC_COMMISSION_WA_VAR_MAX): string
{
    $t = trim(preg_replace('/[\r\n]+/', ' ', $text) ?? '');
    $t = preg_replace('/\s{2,}/', ' ', $t) ?? $t;
    if ($t === '') {
        $t = '-';
    }

    return xander_whatsapp_sanitize_user_text(xander_notify_text_clip($t, $maxLen));
}

/**
 * Build exactly 4 body parameters for pcvc_commission_update Meta template.
 *
 * {{1}} agent name, {{2}} #request id, {{3}} status, {{4}} student + reason/update (one line)
 *
 * @return list<string>
 */
function pcvc_commission_whatsapp_body_params_4(
    string $name,
    int $requestId,
    string $statusLabel,
    string $recruitedName,
    string $extraMessage
): array {
    $detailParts = [];
    $student = trim($recruitedName);
    if ($student !== '') {
        $detailParts[] = 'Student: ' . $student;
    }
    $comment = trim($extraMessage);
    if ($comment !== '') {
        $detailParts[] = (stripos($statusLabel, 'reject') !== false ? 'Reason: ' : 'Update: ') . $comment;
    }
    if ($detailParts === []) {
        $detailParts[] = 'Status updated to ' . trim($statusLabel) . '.';
    }

    return [
        pcvc_commission_whatsapp_flat_var($name !== '' ? $name : 'Agent'),
        pcvc_commission_whatsapp_flat_var('#' . (int) $requestId),
        pcvc_commission_whatsapp_flat_var($statusLabel),
        pcvc_commission_whatsapp_flat_var(implode(' ', $detailParts)),
    ];
}

function pcvc_commission_comment_parts_for_whatsapp(string $comment, int $partCount = 2, int $maxLen = PCVC_COMMISSION_WA_VAR_MAX): array
{
    $partCount = max(1, min(3, $partCount));
    $text = trim(preg_replace("/\r\n|\r/", "\n", $comment) ?? '');
    $out = [];

    if ($text === '') {
        for ($i = 0; $i < $partCount; $i++) {
            $out[] = '—';
        }

        return $out;
    }

    $remaining = $text;
    for ($i = 0; $i < $partCount; $i++) {
        if ($remaining === '') {
            $out[] = '—';
            continue;
        }
        if (strlen($remaining) <= $maxLen) {
            $out[] = $remaining;
            $remaining = '';
            continue;
        }
        $chunk = substr($remaining, 0, $maxLen);
        $breakAt = strrpos($chunk, "\n");
        if ($breakAt === false || $breakAt < (int) ($maxLen * 0.5)) {
            $breakAt = strrpos($chunk, ' ');
        }
        if ($breakAt !== false && $breakAt > (int) ($maxLen * 0.4)) {
            $piece = substr($remaining, 0, $breakAt);
            $remaining = ltrim(substr($remaining, $breakAt));
        } else {
            $piece = $chunk;
            $remaining = ltrim(substr($remaining, $maxLen));
        }
        $out[] = $piece;
    }

    if ($remaining !== '' && $partCount > 0) {
        $last = count($out) - 1;
        $merged = trim($out[$last] . ' ' . $remaining);
        $out[$last] = xander_notify_text_clip($merged, $maxLen);
    }

    while (count($out) < $partCount) {
        $out[] = '—';
    }

    return array_slice($out, 0, $partCount);
}

/**
 * @return list<string>
 */
function pcvc_commission_whatsapp_template_body_texts(
    string $name,
    int $requestId,
    string $statusLabel,
    string $recruitedName,
    string $extraMessage,
    int $paramCount
): array {
    $safeName = xander_whatsapp_sanitize_user_text($name !== '' ? $name : 'Agent');
    $safeRef = xander_whatsapp_sanitize_user_text('#' . (int) $requestId);
    $safeStatus = xander_whatsapp_sanitize_user_text($statusLabel);
    $safeStudent = xander_whatsapp_sanitize_user_text(trim($recruitedName) !== '' ? trim($recruitedName) : '—');
    $comment = trim($extraMessage);

    if ($paramCount <= 4) {
        return pcvc_commission_whatsapp_body_params_4(
            $name,
            $requestId,
            $statusLabel,
            $recruitedName,
            $extraMessage
        );
    }

    if ($paramCount === 5) {
        return [
            $safeName,
            $safeRef,
            $safeStatus,
            $safeStudent,
            xander_whatsapp_sanitize_user_text(
                xander_notify_text_clip($comment !== '' ? $comment : '—', PCVC_COMMISSION_WA_VAR_MAX)
            ),
        ];
    }

    $commentParts = pcvc_commission_comment_parts_for_whatsapp($comment, 2);
    $texts = [
        $safeName,
        $safeRef,
        $safeStatus,
        $safeStudent,
        xander_whatsapp_sanitize_user_text($commentParts[0]),
        xander_whatsapp_sanitize_user_text($commentParts[1]),
    ];

    if ($paramCount > 6) {
        $extraParts = pcvc_commission_comment_parts_for_whatsapp($comment, 3);
        $texts[] = xander_whatsapp_sanitize_user_text($extraParts[2]);
    }

    return array_slice($texts, 0, $paramCount);
}

function pcvc_commission_notify_email_html(
    string $name,
    int $requestId,
    string $statusLabel,
    string $recruitedName,
    string $extraMessage
): string {
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeStatus = htmlspecialchars($statusLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeRec = htmlspecialchars($recruitedName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $co = htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $msg = trim($extraMessage);
    $msgBlock = '';
    if ($msg !== '') {
        $safeM = nl2br(htmlspecialchars($msg, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $isReject = stripos($statusLabel, 'reject') !== false;
        if ($isReject) {
            $msgBlock = '<div style="margin:16px 0;padding:14px 16px;background:#fef2f2;border-left:4px solid #b91c1c;border-radius:8px;">'
                . '<p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#991b1b;">Reason for rejection</p>'
                . '<p style="margin:0;font-size:15px;line-height:1.55;color:#1e293b;">' . $safeM . '</p></div>';
        } else {
            $msgBlock = '<div style="margin:16px 0;padding:14px 16px;background:#f0fdf4;border-left:4px solid #427431;border-radius:8px;">'
                . '<p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#166534;">Message from our team</p>'
                . '<p style="margin:0;font-size:15px;line-height:1.55;color:#1e293b;">' . $safeM . '</p></div>';
        }
    }

    return '<div style="font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:600px;margin:0 auto;color:#1e293b;">'
        . '<p style="margin:0 0 12px;font-size:16px;">Dear ' . $safeName . ',</p>'
        . '<p style="margin:0 0 8px;">Your <strong>commission request #' . (int) $requestId . '</strong> status is now:</p>'
        . '<p style="margin:0 0 16px;font-size:18px;font-weight:700;color:#427431;">' . $safeStatus . '</p>'
        . ($safeRec !== '' ? '<p style="margin:0 0 12px;font-size:14px;">Student: <strong>' . $safeRec . '</strong></p>' : '')
        . $msgBlock
        . '<p style="margin:16px 0 0;font-size:14px;color:#475569;">Questions? Reply to this email.</p>'
        . '<p style="margin:12px 0 0;font-size:13px;color:#94a3b8;">' . $co . '</p></div>';
}

function pcvc_commission_notify_whatsapp_body(
    string $name,
    int $requestId,
    string $statusLabel,
    string $recruitedName,
    string $extraMessage
): string {
    $n = xander_whatsapp_sanitize_user_text($name !== '' ? $name : 'Agent');
    $s = xander_whatsapp_sanitize_user_text($statusLabel);
    $parts = [
        '*ScholarSync Global*',
        '*Commission request — status update*',
        '',
        'Hello ' . $n . ',',
        '',
        'Request #' . (int) $requestId . ' is now:',
        '*' . $s . '*',
    ];
    $rec = trim($recruitedName);
    if ($rec !== '') {
        $parts[] = '';
        $parts[] = 'Student: ' . xander_whatsapp_sanitize_user_text($rec);
    }
    $msg = trim($extraMessage);
    if ($msg !== '') {
        $parts[] = '';
        $parts[] = stripos($statusLabel, 'reject') !== false ? '*Reason for rejection*' : '*Message from our team*';
        $parts[] = xander_whatsapp_sanitize_user_text($msg);
    }
    $parts[] = '';
    $parts[] = '— ScholarSync Global';

    return xander_notify_text_clip(implode("\n", $parts), 4096);
}

/**
 * @return array{sent: bool, error: string}
 */
function pcvc_commission_send_email(string $toEmail, string $toName, string $subject, string $htmlBody): array
{
    xander_load_env_file();
    $out = ['sent' => false, 'error' => ''];

    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $out['error'] = 'Invalid recipient email.';

        return $out;
    }

    $host = xander_env_get('SMTP_HOST');
    if ($host === '') {
        $out['error'] = 'SMTP_HOST is not set in .env';

        return $out;
    }

    $mail = null;
    try {
        $mail = xander_create_phpmailer();
        $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        $out['sent'] = $mail->send();
        if (!$out['sent']) {
            $info = $mail->ErrorInfo ?? '';
            $out['error'] = $info !== '' ? ('SMTP: ' . $info) : 'SMTP send returned false.';
        }
    } catch (MailerException $e) {
        $info = ($mail instanceof PHPMailer) ? (string) ($mail->ErrorInfo ?? '') : '';
        $out['error'] = 'Email: ' . $e->getMessage() . ($info !== '' ? ' (' . $info . ')' : '');
    } catch (Throwable $e) {
        $out['error'] = 'Email: ' . $e->getMessage();
    }

    return $out;
}

/**
 * @return array{sent:bool,method:string,error:string,detail:string,to:string}
 */
function pcvc_send_commission_status_whatsapp(
    string $phoneRaw,
    string $name,
    int $requestId,
    string $statusLabel,
    string $recruitedName,
    string $extraMessage
): array {
    $empty = ['sent' => false, 'method' => '', 'error' => '', 'detail' => '', 'to' => ''];
    $token = trim(xander_env_get('WHATSAPP_ACCESS_TOKEN'));
    if ($token === '') {
        $token = trim(xander_env_get('WHATSAPP_TOKEN'));
    }
    $phoneId = trim(xander_env_get('WHATSAPP_PHONE_NUMBER_ID'));
    if ($token === '') {
        $empty['error'] = 'WHATSAPP_ACCESS_TOKEN / WHATSAPP_TOKEN not set in .env';

        return $empty;
    }
    if ($phoneId === '') {
        $empty['error'] = 'WHATSAPP_PHONE_NUMBER_ID not set in .env';

        return $empty;
    }
    $defaultCc = xander_env_get('WHATSAPP_DEFAULT_COUNTRY_CODE');
    $defaultCcOrNull = $defaultCc !== '' ? $defaultCc : null;
    $to = xander_format_phone_for_whatsapp_e164($phoneRaw, $defaultCcOrNull);
    if ($to === null) {
        $empty['error'] = 'Invalid phone for WhatsApp: "' . $phoneRaw . '". Use +250… or set WHATSAPP_DEFAULT_COUNTRY_CODE=250 in .env';

        return $empty;
    }
    $empty['to'] = $to;
    if (!function_exists('curl_init')) {
        $empty['error'] = 'Server has no cURL extension.';

        return $empty;
    }

    $tpl = pcvc_commission_whatsapp_template_config();
    $templateBodyTexts = pcvc_commission_whatsapp_template_body_texts(
        $name,
        $requestId,
        $statusLabel,
        $recruitedName,
        $extraMessage,
        $tpl['params']
    );
    while (count($templateBodyTexts) < $tpl['params']) {
        $templateBodyTexts[] = '-';
    }
    $templateBodyTexts = array_slice($templateBodyTexts, 0, $tpl['params']);

    $version = xander_env_get('META_GRAPH_VERSION') ?: 'v19.0';
    $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($phoneId) . '/messages';
    $fallback = pcvc_commission_notify_whatsapp_body($name, $requestId, $statusLabel, $recruitedName, $extraMessage);

    $r = xander_whatsapp_send_template_or_session(
        $to,
        $url,
        $token,
        $tpl['name'],
        $tpl['lang'],
        $tpl['params'],
        $templateBodyTexts,
        $fallback
    );
    $r['to'] = $to;
    $r['template'] = $tpl['name'];
    $r['template_lang'] = $tpl['lang'];
    $r['template_params'] = $tpl['params'];
    if (!$r['sent'] && ($r['error'] === '' || $r['error'] === 'Unknown error')) {
        $decoded = is_string($r['detail'] ?? null) ? json_decode($r['detail'], true) : null;
        $hint = xander_whatsapp_user_hint(xander_whatsapp_extract_error($decoded) ?? ['message' => '']);
        if ($hint !== '') {
            $r['error'] = $hint;
        }
    }

    return $r;
}

/**
 * @return array{email: array<string, mixed>, whatsapp: array<string, mixed>}|null
 */
function pcvc_notify_commission_request_change(
    mysqli $conn,
    int $id,
    string $newStatus,
    string $statusLabel,
    bool $sendEmail,
    bool $sendWhatsapp,
    string $notifyMessage = ''
): ?array {
    if (!$sendEmail && !$sendWhatsapp) {
        return null;
    }

    xander_load_env_file();

    $emailOut = ['requested' => $sendEmail, 'sent' => null, 'error' => '', 'to' => ''];
    $waOut = ['requested' => $sendWhatsapp, 'sent' => null, 'method' => '', 'error' => '', 'to' => ''];

    $stmt = $conn->prepare(
        'SELECT id, first_name, last_name, email, phone, recruited_name FROM commission_requests WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        return ['email' => $emailOut, 'whatsapp' => $waOut];
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        if ($sendEmail) {
            $emailOut['sent'] = false;
            $emailOut['error'] = 'Request not found.';
        }
        if ($sendWhatsapp) {
            $waOut['sent'] = false;
            $waOut['error'] = 'Request not found.';
        }

        return ['email' => $emailOut, 'whatsapp' => $waOut];
    }

    $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
    $recruited = trim((string) ($row['recruited_name'] ?? ''));

    if ($sendEmail) {
        $to = trim((string) ($row['email'] ?? ''));
        $emailOut['to'] = $to;
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $emailOut['sent'] = false;
            $emailOut['error'] = 'No valid email on file.';
        } else {
            $html = pcvc_commission_notify_email_html($name, $id, $statusLabel, $recruited, $notifyMessage);
            $subj = 'Commission request #' . $id . ' — ' . $statusLabel;
            $em = pcvc_commission_send_email($to, $name, $subj, $html);
            $emailOut['sent'] = $em['sent'];
            $emailOut['error'] = $em['error'];
        }
    }

    if ($sendWhatsapp) {
        $phone = trim((string) ($row['phone'] ?? ''));
        try {
            $r = pcvc_send_commission_status_whatsapp($phone, $name, $id, $statusLabel, $recruited, $notifyMessage);
            $waOut['sent'] = $r['sent'];
            $waOut['method'] = $r['method'];
            $waOut['error'] = $r['error'];
            $waOut['to'] = $r['to'] ?? '';
            $waOut['template'] = $r['template'] ?? pcvc_commission_whatsapp_template_config()['name'];
            $waOut['template_params'] = $r['template_params'] ?? 4;
        } catch (Throwable $e) {
            $waOut['sent'] = false;
            $waOut['error'] = 'WhatsApp: ' . $e->getMessage();
        }
    }

    return ['email' => $emailOut, 'whatsapp' => $waOut];
}
