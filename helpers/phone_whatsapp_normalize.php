<?php
/**
 * Normalize phone values for storage and WhatsApp Cloud API (E.164 digits only, no +).
 */

function xander_phone_digits_only(string $s): string
{
    $d = preg_replace('/\D+/', '', $s);

    return $d ?? '';
}

/**
 * Job form stores dial code + national parts. Strip + from area; dedupe if national includes country code.
 *
 * @return array{0:string,1:string} [dial_digits, national_digits]
 */
function xander_normalize_job_phone_pair(string $areaRaw, string $numberRaw): array
{
    $cc = xander_phone_digits_only($areaRaw);
    $nat = xander_phone_digits_only($numberRaw);

    if ($nat !== '' && $cc !== '' && str_starts_with($nat, $cc)) {
        $nat = substr($nat, strlen($cc));
    }

    if ($nat !== '' && $nat[0] === '0') {
        $nat = ltrim($nat, '0');
    }

    return [$cc, $nat];
}

/**
 * Single-field mobile (visa): digits only, full international where possible.
 */
function xander_normalize_visa_mobile_storage(string $raw): string
{
    return xander_phone_digits_only($raw);
}
