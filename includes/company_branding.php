<?php
declare(strict_types=1);

/**
 * ScholarSync MIS — public name, URLs, and contact hints for templates and emails.
 * Used by student applications, portal emails, and internal tools.
 */

/** Primary name shown in titles, banners, and email signatures */
const PCVC_COMPANY_DISPLAY_NAME = 'ScholarSync Global';

/** Same name in French (UI / bilingual templates) */
const PCVC_COMPANY_DISPLAY_NAME_FR = 'ScholarSync Global Consultant en Visa';

/** Public website (marketing / student-facing links) */
const PCVC_COMPANY_WEBSITE = 'https://scholarsyncglobal.ca';

/** Default admissions contact (aligns with SMTP / admissions flow) */
const PCVC_COMPANY_SUPPORT_EMAIL = 'infos@scholarsyncglobal.ca';

/** Shown when no staff member is assigned on an application (admin lists) */
const PCVC_DEFAULT_ASSIGNED_PERSON_LABEL = 'ScholarSync Global';

/** Payroll and salary report display currency */
const PCVC_PAYROLL_CURRENCY = 'RWF';

/**
 * Public base URL for this MIS install (receipt email, webhooks, internal curl, meeting links).
 * Prefers app.baseURL from .env (e.g. https://scholarsyncglobal.ca/), then APP_PUBLIC_URL.
 */
function pcvc_public_base_url(): string
{
    if (!function_exists('xander_env_get')) {
        $envLoader = __DIR__ . '/../helpers/env_load.php';
        if (is_file($envLoader)) {
            require_once $envLoader;
        }
    }

    $env = '';
    if (function_exists('xander_env_get')) {
        foreach (['app.baseURL', 'APP_PUBLIC_URL'] as $key) {
            $candidate = trim((string) xander_env_get($key));
            $candidate = trim($candidate, "\"'");
            if ($candidate !== '') {
                $env = $candidate;
                break;
            }
        }
    }
    if ($env !== '') {
        return rtrim($env, '/');
    }

    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $forwarded = strtolower(trim((string) strtok((string) $_SERVER['HTTP_X_FORWARDED_PROTO'], ',')));
        if ($forwarded === 'https') {
            $scheme = 'https';
        }
    }

    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    return rtrim($scheme . '://' . $host . $dir, '/');
}
