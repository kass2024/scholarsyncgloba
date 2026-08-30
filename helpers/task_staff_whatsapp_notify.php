<?php
declare(strict_types=1);

/**
 * Task assignment monitor — staff WhatsApp notifications using Meta Cloud API.
 *
 * ---------------------------------------------------------------------------
 * CANONICAL META TEMPLATE NAMES (create these in WhatsApp Manager, Utility)
 * ---------------------------------------------------------------------------
 *
 * 1) Template internal name: pcvc_staff_task_utility
 *    Category: Utility → Default. Language: English (code en, or en_US if you pick US English in Meta).
 *    Body (exactly 3 variables, this order — matches WHATSAPP_TASK_STAFF_TEMPLATE_PARAMS=3):
 *
 *    Hello {{1}},
 *
 *    Internal work notice. You have a message from {{2}}:
 *
 *    {{3}}
 *
 *    — ScholarSync MIS
 *
 * 2) Template internal name: pcvc_staff_task_utility_urgent
 *    Same category/type and same {{1}}{{2}}{{3}} order. Approved body example (yours may match this):
 *
 *    URGENT — Hello {{1}},
 *
 *    {{2}}:
 *
 *    {{3}}
 *
 *    Please review when you can. — ScholarSync MIS
 *
 * Variable meaning (code sends in this order — Meta “sample” text is only for review, not what users see):
 *   {{1}} = staff display name (target row in admins)
 *   {{2}} = company (PCVC_COMPANY_DISPLAY_NAME) + " — " + supervisor display name + " #" + admin id (sender row in admins)
 *   {{3}} = supervisor comments only (Notify modal message)
 *
 * In Meta, set body variables to type **Text** (not Number) so names and messages send correctly.
 *
 * .env overrides (optional): if WHATSAPP_TASK_STAFF_TEMPLATE_NAME is empty, the code uses
 * PCVC_WA_TASK_TEMPLATE_STAFF_DEFAULT; if WHATSAPP_TASK_STAFF_TEMPLATE_URGENT_NAME is empty,
 * the code uses PCVC_WA_TASK_TEMPLATE_STAFF_URGENT for the "Urgent" UI option.
 *
 * Reads from .env: WHATSAPP_ACCESS_TOKEN (or WHATSAPP_TOKEN), WHATSAPP_PHONE_NUMBER_ID,
 * WHATSAPP_DEFAULT_COUNTRY_CODE, META_GRAPH_VERSION, and optional template overrides above.
 *
 * Placeholder mapping for PARAMS (if you use 1 or 2 instead of 3 — not recommended):
 *   1 — single blob; 2 — name + block; 3 — name, sender, message (default).
 */

require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/student_status_notify.php';
require_once __DIR__ . '/../includes/company_branding.php';

/** Default Meta template name when WHATSAPP_TASK_STAFF_TEMPLATE_NAME is not set in .env */
const PCVC_WA_TASK_TEMPLATE_STAFF_DEFAULT = 'pcvc_staff_task_utility';

/** Urgent Meta template name when WHATSAPP_TASK_STAFF_TEMPLATE_URGENT_NAME is not set in .env */
const PCVC_WA_TASK_TEMPLATE_STAFF_URGENT = 'pcvc_staff_task_utility_urgent';

/**
 * @return array{0: string, 1: string}
 */
function pcvc_task_staff_wa_credentials(): array
{
    $token = trim(xander_env_get('WHATSAPP_ACCESS_TOKEN'));
    if ($token === '') {
        $token = trim(xander_env_get('WHATSAPP_TOKEN'));
    }
    $phoneId = trim(xander_env_get('WHATSAPP_PHONE_NUMBER_ID'));

    return [$token, $phoneId];
}

/**
 * @return array{0: string, 1: string, 2: int}
 */
function pcvc_task_staff_resolve_whatsapp_template(string $variant): array
{
    $v = strtolower(trim($variant)) === 'urgent';
    if ($v) {
        $name = trim(xander_env_get('WHATSAPP_TASK_STAFF_TEMPLATE_URGENT_NAME'));
        if ($name === '') {
            $name = PCVC_WA_TASK_TEMPLATE_STAFF_URGENT;
        }
    } else {
        $name = trim(xander_env_get('WHATSAPP_TASK_STAFF_TEMPLATE_NAME'));
        if ($name === '') {
            $name = PCVC_WA_TASK_TEMPLATE_STAFF_DEFAULT;
        }
    }

    $lang = trim(xander_env_get('WHATSAPP_TASK_STAFF_TEMPLATE_LANG'));
    if ($lang === '') {
        $lang = 'en';
    }

    $pc = (int) trim(xander_env_get('WHATSAPP_TASK_STAFF_TEMPLATE_PARAMS'));
    if ($pc < 1) {
        $pc = 3;
    }
    if ($pc > 3) {
        $pc = 3;
    }

    return [$name, $lang, $pc];
}

function pcvc_task_staff_whatsapp_session_body(string $staffName, string $senderLineWithCompany, string $userMessage): string
{
    $co = xander_whatsapp_sanitize_user_text(PCVC_COMPANY_DISPLAY_NAME);
    $n = xander_whatsapp_sanitize_user_text($staffName !== '' ? $staffName : 'there');
    $from = trim($senderLineWithCompany);
    if ($from === '') {
        $from = xander_whatsapp_sanitize_user_text(PCVC_COMPANY_DISPLAY_NAME . ' — Supervisor');
    } else {
        $from = xander_whatsapp_sanitize_user_text($from);
    }
    $msg = xander_whatsapp_sanitize_user_text(trim($userMessage));
    $parts = [
        '*' . $co . '*',
        '',
        'Hello ' . $n . ',',
        '',
        'From: ' . $from,
        '',
        $msg,
        '',
        '— Task assignment monitor',
    ];

    return xander_notify_text_clip(implode("\n", $parts), 4096);
}

/**
 * @return list<string>
 */
function pcvc_task_staff_template_body_texts(int $paramCount, string $staffName, string $senderLineWithCompany, string $userMessage): array
{
    $n = $staffName !== '' ? $staffName : 'Staff';
    $s = trim($senderLineWithCompany);
    if ($s === '') {
        $s = PCVC_COMPANY_DISPLAY_NAME . ' — Supervisor';
    }
    $m = trim($userMessage);

    if ($paramCount <= 0) {
        return [];
    }
    if ($paramCount === 1) {
        $blob = $n . "\n\n" . $s . "\n\n" . $m;

        return [xander_notify_text_clip(xander_whatsapp_sanitize_user_text($blob), 1024)];
    }
    if ($paramCount === 2) {
        $block = xander_whatsapp_sanitize_user_text($s) . "\n\n" . xander_whatsapp_sanitize_user_text($m);

        return [
            xander_notify_text_clip(xander_whatsapp_sanitize_user_text($n), 1024),
            xander_notify_text_clip($block, 1024),
        ];
    }

    return [
        xander_notify_text_clip(xander_whatsapp_sanitize_user_text($n), 1024),
        xander_notify_text_clip(xander_whatsapp_sanitize_user_text($s), 1024),
        xander_notify_text_clip(xander_whatsapp_sanitize_user_text($m), 1024),
    ];
}

/**
 * Send WhatsApp to staff: tries Meta template from .env, then session text (same pipeline as other modules).
 *
 * @return array{sent:bool,method:string,error:string,detail:string}
 */
function pcvc_task_monitor_send_staff_whatsapp(
    string $rawPhone,
    string $staffName,
    string $senderLineWithCompany,
    string $userMessage,
    string $templateVariant = 'default'
): array {
    $empty = ['sent' => false, 'method' => '', 'error' => '', 'detail' => ''];

    [$token, $phoneId] = pcvc_task_staff_wa_credentials();
    if ($token === '' || $phoneId === '') {
        $empty['error'] = 'WhatsApp is not configured (set WHATSAPP_ACCESS_TOKEN or WHATSAPP_TOKEN, and WHATSAPP_PHONE_NUMBER_ID in .env).';

        return $empty;
    }

    $defaultCc = trim(xander_env_get('WHATSAPP_DEFAULT_COUNTRY_CODE'));
    $to = xander_format_phone_for_whatsapp_e164($rawPhone, $defaultCc !== '' ? $defaultCc : null);
    if ($to === null) {
        $empty['error'] = 'Staff phone number is missing country code or is invalid for WhatsApp. Set WHATSAPP_DEFAULT_COUNTRY_CODE in .env if numbers are national format.';

        return $empty;
    }

    if (!function_exists('curl_init')) {
        $empty['error'] = 'Server has no cURL (enable php-curl).';

        return $empty;
    }

    $version = trim(xander_env_get('META_GRAPH_VERSION'));
    if ($version === '') {
        $version = 'v19.0';
    }
    $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($phoneId) . '/messages';

    $variant = strtolower(trim($templateVariant)) === 'urgent' ? 'urgent' : 'default';
    [$tplName, $tplLang, $paramCount] = pcvc_task_staff_resolve_whatsapp_template($variant);

    $sessionBody = pcvc_task_staff_whatsapp_session_body($staffName, $senderLineWithCompany, $userMessage);
    $bodyTexts = pcvc_task_staff_template_body_texts($paramCount, $staffName, $senderLineWithCompany, $userMessage);

    return xander_whatsapp_send_template_or_session(
        $to,
        $url,
        $token,
        $tplName,
        $tplLang,
        $paramCount,
        $bodyTexts,
        $sessionBody
    );
}
