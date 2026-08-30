<?php
declare(strict_types=1);

/**
 * Refund request notifications — uses project .env SMTP_* and WHATSAPP_* (same as commission / student status).
 */

require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/mail_smtp.php';
require_once __DIR__ . '/student_status_notify.php';
require_once __DIR__ . '/../includes/company_branding.php';

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

const PCVC_REFUND_WA_VAR_MAX = 900;

function pcvc_refund_whatsapp_template_config(): array
{
    xander_load_env_file();
    $name = trim(xander_env_get('WHATSAPP_REFUND_TEMPLATE_NAME'));
    if ($name === '') {
        $name = 'pcvc_refund_update';
    }
    $lang = trim(xander_env_get('WHATSAPP_REFUND_TEMPLATE_LANG'));
    if ($lang === '') {
        $lang = 'en';
    }
    $params = (int) trim(xander_env_get('WHATSAPP_REFUND_TEMPLATE_PARAMS'));
    if ($params < 1) {
        $params = 7;
    }

    return ['name' => $name, 'lang' => $lang, 'params' => $params];
}

/**
 * Non-secret .env readiness check (shown in admin UI when notify fails).
 *
 * @return array<string, string>
 */
function pcvc_refund_notify_env_diagnosis(): array
{
    xander_load_env_file();
    $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    $token = trim(xander_env_get('WHATSAPP_ACCESS_TOKEN'));
    if ($token === '') {
        $token = trim(xander_env_get('WHATSAPP_TOKEN'));
    }

    return [
        'env_file' => is_readable($envPath) ? 'OK — .env found' : 'MISSING — create .env in scholarsyncglobal root',
        'smtp_host' => xander_env_get('SMTP_HOST') !== '' ? 'OK' : 'MISSING SMTP_HOST',
        'smtp_user' => xander_env_get('SMTP_USERNAME') !== '' ? 'OK' : 'MISSING SMTP_USERNAME',
        'smtp_pass' => xander_env_get('SMTP_PASSWORD') !== '' ? 'OK (set)' : 'MISSING SMTP_PASSWORD',
        'smtp_from' => xander_env_get('SMTP_FROM_EMAIL') !== '' ? 'OK' : 'MISSING SMTP_FROM_EMAIL (will use SMTP_USERNAME)',
        'whatsapp_token' => $token !== '' ? 'OK (' . strlen($token) . ' chars)' : 'MISSING WHATSAPP_ACCESS_TOKEN or WHATSAPP_TOKEN',
        'whatsapp_phone_id' => xander_env_get('WHATSAPP_PHONE_NUMBER_ID') !== '' ? 'OK' : 'MISSING WHATSAPP_PHONE_NUMBER_ID',
        'whatsapp_country_code' => xander_env_get('WHATSAPP_DEFAULT_COUNTRY_CODE') !== ''
            ? ('OK (' . xander_env_get('WHATSAPP_DEFAULT_COUNTRY_CODE') . ')')
            : 'optional — set WHATSAPP_DEFAULT_COUNTRY_CODE=250 for local numbers',
        'whatsapp_template' => pcvc_refund_whatsapp_template_config()['name']
            . ' (' . pcvc_refund_whatsapp_template_config()['params'] . ' params, lang '
            . pcvc_refund_whatsapp_template_config()['lang'] . ')',
    ];
}

function pcvc_refund_comment_parts_for_whatsapp(string $comment, int $partCount = 2, int $maxLen = PCVC_REFUND_WA_VAR_MAX): array
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
function pcvc_refund_whatsapp_template_body_texts(
    string $name,
    string $referenceId,
    string $statusLabel,
    string $servicePaid,
    string $amountLine,
    string $adminComment,
    int $paramCount
): array {
    $safeName = xander_whatsapp_sanitize_user_text($name !== '' ? $name : 'Student');
    $safeRef = xander_whatsapp_sanitize_user_text($referenceId);
    $safeStatus = xander_whatsapp_sanitize_user_text($statusLabel);
    $safeService = xander_whatsapp_sanitize_user_text($servicePaid !== '' ? $servicePaid : '—');
    $safeAmount = xander_whatsapp_sanitize_user_text($amountLine !== '' ? $amountLine : '—');
    $comment = trim($adminComment);

    if ($paramCount <= 4) {
        $legacyComment = $comment !== '' ? $comment : '—';

        return [
            $safeName,
            $safeRef,
            $safeStatus,
            xander_whatsapp_sanitize_user_text(xander_notify_text_clip($legacyComment, PCVC_REFUND_WA_VAR_MAX)),
        ];
    }

    if ($paramCount === 5) {
        $legacyComment = $comment !== '' ? $comment : '—';

        return [
            $safeName,
            $safeRef,
            $safeStatus,
            $safeService,
            xander_whatsapp_sanitize_user_text(xander_notify_text_clip($legacyComment, PCVC_REFUND_WA_VAR_MAX)),
        ];
    }

    if ($paramCount === 6) {
        $parts = pcvc_refund_comment_parts_for_whatsapp($comment, 1);

        return [
            $safeName,
            $safeRef,
            $safeStatus,
            $safeService,
            $safeAmount,
            xander_whatsapp_sanitize_user_text($parts[0]),
        ];
    }

    $commentParts = pcvc_refund_comment_parts_for_whatsapp($comment, 2);
    $texts = [
        $safeName,
        $safeRef,
        $safeStatus,
        $safeService,
        $safeAmount,
        xander_whatsapp_sanitize_user_text($commentParts[0]),
        xander_whatsapp_sanitize_user_text($commentParts[1]),
    ];

    if ($paramCount > 7) {
        $extraParts = pcvc_refund_comment_parts_for_whatsapp($comment, 3);
        $texts[] = xander_whatsapp_sanitize_user_text($extraParts[2]);
    }

    return array_slice($texts, 0, $paramCount);
}

function pcvc_refund_notify_email_html(
    string $name,
    string $referenceId,
    string $statusLabel,
    string $servicePaid,
    string $amountLine,
    string $adminComment
): string {
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeRef = htmlspecialchars($referenceId, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeStatus = htmlspecialchars($statusLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeService = htmlspecialchars($servicePaid, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeAmount = htmlspecialchars($amountLine, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $co = htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $commentBlock = '';
    $msg = trim($adminComment);
    if ($msg !== '') {
        $safeM = nl2br(htmlspecialchars($msg, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $commentBlock = '<div style="margin:16px 0;padding:14px 16px;background:#f0fdf4;border-left:4px solid #427431;border-radius:8px;">'
            . '<p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#166534;">Message from our team</p>'
            . '<p style="margin:0;font-size:15px;line-height:1.55;color:#1e293b;">' . $safeM . '</p></div>';
    }

    return '<div style="font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:600px;margin:0 auto;color:#1e293b;">'
        . '<div style="background:linear-gradient(135deg,#427431,#2f5a26);color:#fff;padding:24px;border-radius:12px 12px 0 0;text-align:center;">'
        . '<h1 style="margin:0;font-size:22px;">Refund Request Update</h1>'
        . '<p style="margin:8px 0 0;opacity:0.9;font-size:14px;">' . $co . '</p></div>'
        . '<div style="background:#fff;padding:24px;border:1px solid #e2e8f0;border-radius:0 0 12px 12px;">'
        . '<p style="margin:0 0 12px;font-size:16px;">Dear ' . $safeName . ',</p>'
        . '<p style="margin:0 0 8px;">Your refund request <strong>' . $safeRef . '</strong> has been updated:</p>'
        . '<p style="margin:0 0 16px;font-size:18px;font-weight:700;color:#427431;">' . $safeStatus . '</p>'
        . '<p style="margin:0 0 6px;font-size:14px;color:#64748b;">Service: <strong style="color:#1e293b;">' . $safeService . '</strong></p>'
        . '<p style="margin:0 0 16px;font-size:14px;color:#64748b;">Amount: <strong style="color:#1e293b;">' . $safeAmount . '</strong></p>'
        . $commentBlock
        . '<p style="margin:16px 0 0;font-size:14px;color:#475569;">Questions? Reply to this email.</p>'
        . '<p style="margin:12px 0 0;font-size:13px;color:#94a3b8;">' . $co . '</p></div></div>';
}

function pcvc_refund_notify_whatsapp_fallback(
    string $name,
    string $referenceId,
    string $statusLabel,
    string $servicePaid,
    string $amountLine,
    string $adminComment
): string {
    $parts = [
        '*ScholarSync Global*',
        '*Refund request update*',
        '',
        'Hello ' . xander_whatsapp_sanitize_user_text($name !== '' ? $name : 'Student') . ',',
        '',
        'Reference: ' . xander_whatsapp_sanitize_user_text($referenceId),
        'Status: *' . xander_whatsapp_sanitize_user_text($statusLabel) . '*',
    ];
    if (trim($servicePaid) !== '') {
        $parts[] = 'Service: ' . xander_whatsapp_sanitize_user_text($servicePaid);
    }
    if (trim($amountLine) !== '') {
        $parts[] = 'Amount: ' . xander_whatsapp_sanitize_user_text($amountLine);
    }
    $msg = trim($adminComment);
    if ($msg !== '') {
        $parts[] = '';
        $parts[] = '*Message from our team*';
        $parts[] = xander_whatsapp_sanitize_user_text($msg);
    }
    $parts[] = '';
    $parts[] = '— ScholarSync Global';

    return xander_notify_text_clip(implode("\n", $parts), 4096);
}

/**
 * Send refund email via app_mailer / SMTP_* in .env (same stack as student status emails).
 *
 * @return array{sent: bool, error: string}
 */
function pcvc_refund_send_email(string $toEmail, string $toName, string $subject, string $htmlBody): array
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
function pcvc_send_refund_status_whatsapp(
    string $phoneRaw,
    string $name,
    string $referenceId,
    string $statusLabel,
    string $servicePaid,
    string $amountLine,
    string $adminComment
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
    if (trim($phoneRaw) === '') {
        $empty['error'] = 'No phone number on this refund request.';

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

    $tpl = pcvc_refund_whatsapp_template_config();
    $templateBodyTexts = pcvc_refund_whatsapp_template_body_texts(
        $name,
        $referenceId,
        $statusLabel,
        $servicePaid,
        $amountLine,
        $adminComment,
        $tpl['params']
    );

    $version = xander_env_get('META_GRAPH_VERSION') ?: 'v19.0';
    $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($phoneId) . '/messages';
    $fallback = pcvc_refund_notify_whatsapp_fallback(
        $name,
        $referenceId,
        $statusLabel,
        $servicePaid,
        $amountLine,
        $adminComment
    );

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
    if (!$r['sent'] && ($r['error'] === '' || $r['error'] === 'Unknown error')) {
        $hint = xander_whatsapp_user_hint(xander_whatsapp_extract_error(
            is_string($r['detail'] ?? null) ? json_decode($r['detail'], true) : null
        ) ?? ['message' => '']);
        if ($hint !== '') {
            $r['error'] = $hint;
        }
    }

    return $r;
}

/**
 * @return array{email: array<string, mixed>, whatsapp: array<string, mixed>, env: array<string, string>}|null
 */
function pcvc_notify_refund_request_change(
    mysqli $conn,
    int $id,
    string $statusLabel,
    bool $sendEmail,
    bool $sendWhatsapp,
    string $adminComment = ''
): ?array {
    if (!$sendEmail && !$sendWhatsapp) {
        return null;
    }

    xander_load_env_file();
    $envDiag = pcvc_refund_notify_env_diagnosis();

    $emailOut = ['requested' => $sendEmail, 'sent' => null, 'error' => '', 'to' => ''];
    $waOut = ['requested' => $sendWhatsapp, 'sent' => null, 'method' => '', 'error' => '', 'to' => ''];

    $stmt = $conn->prepare(
        'SELECT id, reference_id, first_name, last_name, email, phone, service_paid_for, amount, currency
         FROM refund_requests WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        return ['email' => $emailOut, 'whatsapp' => $waOut, 'env' => $envDiag];
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

        return ['email' => $emailOut, 'whatsapp' => $waOut, 'env' => $envDiag];
    }

    $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
    $ref = trim((string) ($row['reference_id'] ?? ''));
    $service = trim((string) ($row['service_paid_for'] ?? ''));
    $cur = strtoupper(trim((string) ($row['currency'] ?? 'USD'))) ?: 'USD';
    $amountLine = number_format((float) ($row['amount'] ?? 0), 2) . ' ' . $cur;

    if ($sendEmail) {
        $to = trim((string) ($row['email'] ?? ''));
        $emailOut['to'] = $to;
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $emailOut['sent'] = false;
            $emailOut['error'] = 'No valid email on this refund request.';
        } else {
            $html = pcvc_refund_notify_email_html($name, $ref, $statusLabel, $service, $amountLine, $adminComment);
            $subj = 'Refund request ' . $ref . ' — ' . $statusLabel;
            $em = pcvc_refund_send_email($to, $name, $subj, $html);
            $emailOut['sent'] = $em['sent'];
            $emailOut['error'] = $em['error'];
        }
    }

    if ($sendWhatsapp) {
        $phone = trim((string) ($row['phone'] ?? ''));
        try {
            $r = pcvc_send_refund_status_whatsapp(
                $phone,
                $name,
                $ref,
                $statusLabel,
                $service,
                $amountLine,
                $adminComment
            );
            $waOut['sent'] = $r['sent'];
            $waOut['method'] = $r['method'];
            $waOut['error'] = $r['error'];
            $waOut['to'] = $r['to'] ?? '';
            if (!empty($r['detail'])) {
                $waOut['detail'] = $r['detail'];
            }
        } catch (Throwable $e) {
            $waOut['sent'] = false;
            $waOut['error'] = 'WhatsApp: ' . $e->getMessage();
        }
    }

    return ['email' => $emailOut, 'whatsapp' => $waOut, 'env' => $envDiag];
}
