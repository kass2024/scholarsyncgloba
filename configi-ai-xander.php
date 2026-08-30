<?php
/**
 * AI chat configuration (legacy filename retained for includes).
 * LOCAL + CPANEL + PRODUCTION — set OPENAI_API_KEY / domain via .env (loaded in helpers/env_bootstrap.php).
 */

require_once __DIR__ . '/helpers/env_bootstrap.php';

/* =====================================================
   ENVIRONMENT DETECTION
===================================================== */

// Detect LOCAL environment safely
$isLocal = false;

if (
    in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) ||
    ($_SERVER['HTTP_HOST'] ?? '') === 'localhost'
) {
    $isLocal = true;
}

if ($isLocal) {
    /* ================= DEVELOPMENT ================= */
    define('ENVIRONMENT', 'development');
    define('DEBUG_MODE', true);
} else {
    /* ================= PRODUCTION ================= */
    define('ENVIRONMENT', 'production');
    define('DEBUG_MODE', false);
}

$__pcvc_openai = pcvc_env('OPENAI_API_KEY');
define('OPENAI_API_KEY', $__pcvc_openai);
unset($__pcvc_openai);

/** Shown in chat when users ask for a human; set in root .env. */
define('PCVC_SUPPORT_EMAIL', pcvc_env('PCVC_SUPPORT_EMAIL', 'infos@scholarsyncglobal.ca'));
define('PCVC_SUPPORT_PHONE', pcvc_env('PCVC_SUPPORT_PHONE'));
define('PCVC_SUPPORT_WHATSAPP', pcvc_env('PCVC_SUPPORT_WHATSAPP'));

if (!function_exists('pcvc_support_contact_prompt_block')) {
    /**
     * Extra system-prompt text so the model quotes real contact details (not invented numbers).
     */
    function pcvc_support_contact_prompt_block(): string
    {
        $lines = [];
        if (defined('PCVC_SUPPORT_EMAIL') && PCVC_SUPPORT_EMAIL !== '') {
            $lines[] = 'Email: ' . PCVC_SUPPORT_EMAIL;
        }
        if (defined('PCVC_SUPPORT_PHONE') && PCVC_SUPPORT_PHONE !== '') {
            $lines[] = 'Phone: ' . PCVC_SUPPORT_PHONE;
        }
        if (defined('PCVC_SUPPORT_WHATSAPP') && PCVC_SUPPORT_WHATSAPP !== '') {
            $lines[] = 'WhatsApp: ' . PCVC_SUPPORT_WHATSAPP;
        }
        if ($lines === []) {
            return '';
        }

        return "\n\n"
            . "================= OFFICIAL CONTACT (use these exact details when the user asks for a human or how to reach the team) =================\n"
            . implode("\n", $lines)
            . "\n=====================================================================================================================\n";
    }
}

/* =====================================================
   AI MODEL SETTINGS
===================================================== */

// IMPORTANT: this model WORKS with /v1/chat/completions
define('AI_MODEL', 'gpt-4o-mini');   // ✅ stable
// Alternative fallback: gpt-3.5-turbo

define('AI_TEMPERATURE', 0.6);
define('AI_MAX_TOKENS', 350);
define('AI_TIMEOUT', 30);

/* =====================================================
   APPLICATION SETTINGS
===================================================== */

define('APP_NAME', 'ScholarSync Global AI Assistant');
define('APP_VERSION', '1.0.0');

/* =====================================================
   RATE LIMITING (future use)
===================================================== */

define('RATE_LIMIT_REQUESTS', 50); // per hour
define('RATE_LIMIT_PERIOD', 3600);

/* =====================================================
   SECURITY
===================================================== */

define('SESSION_LIFETIME', 1800); // 30 minutes
define('ENABLE_CONTENT_FILTER', true);

/* =====================================================
   LOGGING
===================================================== */

define('LOG_ERRORS', true);
define('LOG_FILE', __DIR__ . '/logs/ai_errors.log');

if (LOG_ERRORS && !is_dir(dirname(LOG_FILE))) {
    mkdir(dirname(LOG_FILE), 0755, true);
}

/* =====================================================
   ERROR HANDLING
===================================================== */

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');

    if (LOG_ERRORS) {
        ini_set('log_errors', '1');
        ini_set('error_log', LOG_FILE);
    }
}

/* =====================================================
   API KEY VALIDATION
===================================================== */

function validateApiKey(): bool
{
    if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === '') {
        error_log('[XGS AI] OpenAI API key missing');
        return false;
    }

    // Accept modern OpenAI keys (sk-, sk-proj-, underscores, dashes)
    if (!preg_match('/^sk-[A-Za-z0-9_\-]+$/', OPENAI_API_KEY)) {
        error_log('[XGS AI] Invalid OpenAI API key format');
        return false;
    }

    return true;
}

/* =====================================================
   OPENAI HEADERS
===================================================== */

function getApiHeaders(): array
{
    return [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ];
}

/* =====================================================
   API AVAILABILITY CHECK
===================================================== */

function isApiAvailable(): bool
{
    return validateApiKey() && function_exists('curl_init');
}
