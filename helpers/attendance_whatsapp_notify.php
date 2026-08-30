<?php
declare(strict_types=1);

/**
 * Attendance WhatsApp — Meta Utility templates (template-only, no 24h session window).
 *
 * Register these templates in Meta Business → WhatsApp → Message templates.
 * Full copy-paste bodies: sql/whatsapp_attendance_templates.md
 *
 * Checkout (instant on check-out):  pcvc_checkout_attendance
 * Daily digest (cron daily_check):  pcvc_daily_attendance_summary
 */

require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/student_status_notify.php';

const PCVC_CHECKOUT_WA_TEMPLATE_DEFAULT = 'pcvc_checkout_attendance';
const PCVC_DAILY_WA_TEMPLATE_DEFAULT    = 'pcvc_daily_attendance_summary';

/**
 * Resolve WhatsApp destination from an admins row (phone_number, phone, mobile, area_code).
 */
function pcvc_admin_resolve_whatsapp_phone(array $admin): string
{
    $area = trim((string) ($admin['area_code'] ?? ''));
    $main = trim((string) ($admin['phone_number'] ?? ''));

    $candidates = [];
    if ($area !== '' && $main !== '') {
        $candidates[] = $area . $main;
    }
    if ($main !== '') {
        $candidates[] = $main;
    }
    foreach (['phone', 'mobile', 'whatsapp', 'whatsapp_number'] as $key) {
        $v = trim((string) ($admin[$key] ?? ''));
        if ($v !== '') {
            $candidates[] = $v;
        }
    }

    foreach ($candidates as $raw) {
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits !== null && strlen($digits) >= 8) {
            return $raw;
        }
    }

    return '';
}

/**
 * @return array{name: string, lang: string, params: int}
 */
function pcvc_checkout_whatsapp_template_config(): array
{
    xander_load_env_file();
    $name = trim(xander_env_get('WHATSAPP_CHECKOUT_ATTENDANCE_TEMPLATE_NAME'));
    if ($name === '') {
        $name = trim(xander_env_get('WHATSAPP_ATTENDANCE_DAILY_TEMPLATE_NAME'));
    }
    if ($name === '') {
        $name = PCVC_CHECKOUT_WA_TEMPLATE_DEFAULT;
    }
    $lang = trim(xander_env_get('WHATSAPP_CHECKOUT_ATTENDANCE_TEMPLATE_LANG'));
    if ($lang === '') {
        $lang = trim(xander_env_get('WHATSAPP_ATTENDANCE_DAILY_TEMPLATE_LANG'));
    }
    if ($lang === '') {
        $lang = 'en';
    }
    $params = (int) trim(xander_env_get('WHATSAPP_CHECKOUT_ATTENDANCE_TEMPLATE_PARAMS'));
    if ($params < 1) {
        $params = (int) trim(xander_env_get('WHATSAPP_ATTENDANCE_DAILY_TEMPLATE_PARAMS'));
    }
    if ($params < 1) {
        $params = 6;
    }
    if ($params > 6) {
        $params = 6;
    }

    return ['name' => $name, 'lang' => $lang, 'params' => $params];
}

/**
 * @return array{name: string, lang: string, params: int}
 */
function pcvc_daily_whatsapp_template_config(): array
{
    xander_load_env_file();
    $name = trim(xander_env_get('WHATSAPP_ATTENDANCE_DAILY_TEMPLATE_NAME'));
    if ($name === '') {
        $name = PCVC_DAILY_WA_TEMPLATE_DEFAULT;
    }
    $lang = trim(xander_env_get('WHATSAPP_ATTENDANCE_DAILY_TEMPLATE_LANG'));
    if ($lang === '') {
        $lang = 'en';
    }
    $params = (int) trim(xander_env_get('WHATSAPP_ATTENDANCE_DAILY_TEMPLATE_PARAMS'));
    if ($params < 1) {
        $params = 6;
    }
    if ($params > 6) {
        $params = 6;
    }

    return ['name' => $name, 'lang' => $lang, 'params' => $params];
}

/**
 * @return array{0: string, 1: string}
 */
function pcvc_attendance_whatsapp_credentials(): array
{
    $token = trim(xander_env_get('WHATSAPP_ACCESS_TOKEN'));
    if ($token === '') {
        $token = trim(xander_env_get('WHATSAPP_TOKEN'));
    }

    return [$token, trim(xander_env_get('WHATSAPP_PHONE_NUMBER_ID'))];
}

/**
 * Build 6 template body variables for attendance summaries.
 *
 * @return list<string>
 */
function pcvc_attendance_whatsapp_body_params(array $summary, int $paramCount): array
{
    $flat = static function (string $text, int $maxLen = 240): string {
        $t = trim(preg_replace('/[\r\n]+/', ' ', $text) ?? '');
        $t = preg_replace('/\s{2,}/', ' ', $t) ?? $t;
        if ($t === '') {
            $t = '—';
        }

        return xander_whatsapp_sanitize_user_text(xander_notify_text_clip($t, $maxLen));
    };

    $texts = [
        $flat((string) ($summary['name'] ?? 'Staff')),
        $flat((string) ($summary['date'] ?? date('Y-m-d'))),
        $flat((string) ($summary['check_in_label'] ?? 'Not recorded')),
        $flat((string) ($summary['check_out_label'] ?? 'Not recorded')),
        $flat((string) ($summary['work_label'] ?? '0 min')),
        $flat((string) ($summary['salary_label'] ?? 'RWF 0')),
    ];

    return array_slice($texts, 0, max(1, min(6, $paramCount)));
}

/**
 * Send attendance summary to admin WhatsApp (Meta template only).
 *
 * @param array<string, mixed> $summary Must include name, date, labels; phone from admins row or summary['phone']
 * @return array{sent: bool, method: string, error: string, detail: string, to: string}
 */
function pcvc_attendance_whatsapp_send_summary(array $summary, ?array $templateCfg = null): array
{
    $empty = ['sent' => false, 'method' => '', 'error' => '', 'detail' => '', 'to' => ''];

    [$token, $phoneId] = pcvc_attendance_whatsapp_credentials();
    if ($token === '' || $phoneId === '') {
        $empty['error'] = 'WhatsApp not configured. Set WHATSAPP_ACCESS_TOKEN and WHATSAPP_PHONE_NUMBER_ID in .env.';

        return $empty;
    }

    $rawPhone = trim((string) ($summary['phone'] ?? ''));
    if ($rawPhone === '' && !empty($summary['admin_row']) && is_array($summary['admin_row'])) {
        $rawPhone = pcvc_admin_resolve_whatsapp_phone($summary['admin_row']);
    }
    if ($rawPhone === '') {
        $adminId = (int) ($summary['admin_id'] ?? 0);
        $empty['error'] = 'No phone on admin record (admins.phone_number). Update staff profile — admin #' . $adminId . '.';

        return $empty;
    }

    $defaultCc = trim(xander_env_get('WHATSAPP_DEFAULT_COUNTRY_CODE'));
    $to = xander_format_phone_for_whatsapp_e164(
        $rawPhone,
        $defaultCc !== '' ? $defaultCc : null
    );
    if ($to === null) {
        $empty['error'] = 'Invalid admin phone "' . $rawPhone . '". Set WHATSAPP_DEFAULT_COUNTRY_CODE=250 for local numbers.';
        $empty['to'] = $rawPhone;

        return $empty;
    }

    if (!function_exists('curl_init')) {
        $empty['error'] = 'Server has no cURL (enable php-curl).';

        return $empty;
    }

    $cfg = $templateCfg ?? pcvc_daily_whatsapp_template_config();
    $version = trim(xander_env_get('META_GRAPH_VERSION'));
    if ($version === '') {
        $version = 'v19.0';
    }
    $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($phoneId) . '/messages';
    $bodyTexts = pcvc_attendance_whatsapp_body_params($summary, $cfg['params']);

    $result = pcvc_attendance_whatsapp_send_template_only(
        $to,
        $url,
        $token,
        $cfg['name'],
        $cfg['lang'],
        $cfg['params'],
        $bodyTexts
    );

    $result['to'] = $to;

    if (!$result['sent']) {
        error_log('[attendance-whatsapp] admin #' . (int) ($summary['admin_id'] ?? 0)
            . ' to ' . $to . ' template ' . $cfg['name'] . ' — ' . $result['error']);
    }

    return $result;
}

/**
 * Meta template only — never falls back to session text (no 24-hour window).
 *
 * @param array<int, string> $templateBodyTexts
 * @return array{sent: bool, method: string, error: string, detail: string}
 */
function pcvc_attendance_whatsapp_send_template_only(
    string $to,
    string $url,
    string $token,
    string $templateName,
    string $templateLang,
    int $paramCount,
    array $templateBodyTexts
): array {
    $empty = ['sent' => false, 'method' => 'template', 'error' => '', 'detail' => ''];
    $template = trim($templateName);
    if ($template === '') {
        $empty['error'] = 'WhatsApp template name is not configured.';

        return $empty;
    }

    $lang = $templateLang !== '' ? $templateLang : 'en';
    $components = [];
    if ($paramCount > 0) {
        $bodyParams = [];
        for ($i = 0; $i < $paramCount; $i++) {
            $t = (string) ($templateBodyTexts[$i] ?? '—');
            $bodyParams[] = [
                'type' => 'text',
                'text' => xander_notify_text_clip(xander_whatsapp_sanitize_user_text($t), 1024),
            ];
        }
        $components[] = ['type' => 'body', 'parameters' => $bodyParams];
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $to,
        'type'              => 'template',
        'template'          => [
            'name'     => $template,
            'language' => ['code' => $lang],
        ],
    ];
    if ($components !== []) {
        $payload['template']['components'] = $components;
    }

    $res = xander_whatsapp_graph_post($url, $token, $payload);
    error_log('[attendance-whatsapp] template HTTP ' . $res['http'] . ' body: ' . $res['body']);

    if ($res['http'] >= 200 && $res['http'] < 300 && xander_whatsapp_response_has_message_id($res['json'])) {
        return ['sent' => true, 'method' => 'template', 'error' => '', 'detail' => ''];
    }

    $err = xander_whatsapp_extract_error($res['json']) ?? [
        'code'    => 0,
        'subcode' => 0,
        'message' => 'HTTP ' . $res['http'],
    ];

    return [
        'sent'   => false,
        'method' => 'template',
        'error'  => xander_whatsapp_user_hint($err),
        'detail' => $res['body'],
    ];
}
