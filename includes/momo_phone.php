<?php
declare(strict_types=1);

/**
 * Pick the first non-empty phone string from admin row fields (staff UI uses phone_number).
 *
 * @param array<string, mixed> $row
 */
function pcvc_admin_phone_raw_from_row(array $row): string
{
    foreach (['phone', 'mobile', 'phone_number'] as $k) {
        if (!isset($row[$k])) {
            continue;
        }
        $t = trim((string) $row[$k]);
        if ($t !== '') {
            return $t;
        }
    }

    return '';
}

/**
 * True if digit string looks like a non-Rwanda international number (do not prepend 250).
 * Called only after Rwanda-local patterns (07… / 9-digit 7…) have failed — conservative
 * to avoid misclassifying partial local numbers.
 *
 * @param non-empty-string $d digits only
 */
function pcvc_rw_digits_look_foreign(string $d): bool
{
    if (strlen($d) === 12 && substr($d, 0, 3) === '250') {
        return false;
    }

    // US/Canada NANP (country code 1)
    if (strlen($d) === 11 && $d[0] === '1') {
        return true;
    }

    // Russia / Kazakhstan style (11 digits starting with 7 — not RW 9-digit national)
    if (strlen($d) === 11 && $d[0] === '7') {
        return true;
    }

    // Neighbouring / common African codes (3 digits), not 250
    $cc3 = ['211', '212', '213', '216', '218', '251', '252', '253', '254', '255', '256', '257', '258', '260', '261', '262', '263', '264', '265', '266', '267', '268', '269'];
    foreach ($cc3 as $cc) {
        if (strlen($d) >= 11 && substr($d, 0, 3) === $cc) {
            return true;
        }
    }

    // UK, China, Korea (typical staff records with full CC, no +)
    if (strlen($d) >= 12 && (substr($d, 0, 2) === '44' || substr($d, 0, 2) === '86' || substr($d, 0, 2) === '82')) {
        return true;
    }

    return false;
}

/**
 * Rwanda mobile E.164: 250 + 9 digits; national mobile subscriber number starts with 7.
 *
 * @param non-empty-string $d12
 */
function pcvc_rw_mobile_plausible(string $d12): bool
{
    if (strlen($d12) !== 12 || substr($d12, 0, 3) !== '250') {
        return false;
    }

    return $d12[3] === '7';
}

/**
 * Normalize Rwanda MoMo MSISDN to 12 digits: 250 + 9-digit national mobile number.
 *
 * - Strips formatting; handles leading 00 international access prefix.
 * - Local numbers without country code: 07XXXXXXXX or 7XXXXXXXX (9 digits) → 250…
 * - Explicit +250 / 250 already present: accepted when valid mobile.
 * - Does not prepend 250 when a non-Rwanda country code is detected.
 *
 * @return non-empty-string|null
 */
function pcvc_normalize_rw_momo_msisdn(?string $raw): ?string
{
    if ($raw === null) {
        return null;
    }
    $d = preg_replace('/\D+/', '', $raw);
    if ($d === null || $d === '') {
        return null;
    }

    while (strlen($d) > 12 && substr($d, 0, 2) === '00') {
        $d = substr($d, 2);
    }

    if (substr($d, 0, 3) === '250') {
        if (strlen($d) === 12 && pcvc_rw_mobile_plausible($d)) {
            return $d;
        }

        return null;
    }

    // Rwanda local: 07XX XXX XXX (10 digits)
    if (strlen($d) === 10 && substr($d, 0, 2) === '07') {
        $out = '250' . substr($d, 1);

        return strlen($out) === 12 && pcvc_rw_mobile_plausible($out) ? $out : null;
    }

    // National mobile without trunk 0: 7XXXXXXXX (9 digits)
    if (strlen($d) === 9 && $d[0] === '7') {
        $out = '250' . $d;

        return pcvc_rw_mobile_plausible($out) ? $out : null;
    }

    if (pcvc_rw_digits_look_foreign($d)) {
        return null;
    }

    return null;
}

/** Display helper: "250 7XX XXX XXX" or em dash if invalid. */
function pcvc_format_rw_momo_display(?string $msisdn): string
{
    if ($msisdn === null || strlen($msisdn) !== 12 || substr($msisdn, 0, 3) !== '250') {
        return '—';
    }

    return substr($msisdn, 0, 3) . ' ' .
        substr($msisdn, 3, 3) . ' ' .
        substr($msisdn, 6, 3) . ' ' .
        substr($msisdn, 9, 3);
}
