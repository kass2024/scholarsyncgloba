<?php
declare(strict_types=1);

/**
 * Commission FX: USD/CAD → RWF using the same live source as payments checkout.
 */

function pcvc_normalize_commission_currency(string $currency): string
{
    $cur = strtoupper(trim($currency));
    return in_array($cur, ['USD', 'CAD'], true) ? $cur : 'USD';
}

function pcvc_get_fx_rate_to_rwf(string $currency): float
{
    $cur = pcvc_normalize_commission_currency($currency);
    if ($cur === 'RWF') {
        return 1.0;
    }

    $fxPath = __DIR__ . '/../payments/lib/fx.php';
    $rate = 0.0;
    if (is_file($fxPath)) {
        require_once $fxPath;
        if (function_exists('payments_fx_get_rate_to_rwf')) {
            $rate = (float) payments_fx_get_rate_to_rwf($cur);
        }
    }

    $min = $cur === 'CAD' ? 500.0 : 100.0;
    $max = 50000.0;

    // 1.0 is the API-failure sentinel in payments_fx_get_rate_to_rwf.
    if ($rate < $min || $rate > $max) {
        $envKey = $cur === 'CAD' ? 'PCVC_CAD_TO_RWF_RATE' : 'PCVC_USD_TO_RWF_RATE';
        $fallbackDefault = $cur === 'CAD' ? 1050.0 : 1300.0;
        $raw = getenv($envKey);
        $fallback = ($raw !== false && trim((string) $raw) !== '')
            ? (float) trim((string) $raw)
            : $fallbackDefault;
        if ($fallback > 0) {
            $rate = $fallback;
        }
    }

    if ($rate <= 0) {
        $rate = $cur === 'CAD' ? 1050.0 : 1300.0;
    }

    return $rate;
}

/**
 * @return array{rwf: int, rate: float, amount: float, currency: string}
 */
function pcvc_currency_to_rwf_conversion(string $currency, float $amount): array
{
    $cur = pcvc_normalize_commission_currency($currency);
    if ($amount <= 0) {
        return ['rwf' => 0, 'rate' => 0.0, 'amount' => $amount, 'currency' => $cur];
    }

    $rate = pcvc_get_fx_rate_to_rwf($cur);
    $rwf = (int) round($amount * $rate);

    return [
        'rwf'      => max(1, $rwf),
        'rate'     => $rate,
        'amount'   => $amount,
        'currency' => $cur,
    ];
}

/**
 * @return array{rwf: int, rate: float, usd: float}
 */
function pcvc_usd_to_rwf_conversion(float $usd): array
{
    $conv = pcvc_currency_to_rwf_conversion('USD', $usd);

    return ['rwf' => $conv['rwf'], 'rate' => $conv['rate'], 'usd' => $usd];
}

/**
 * @return array{rwf: int, rate: float, cad: float}
 */
function pcvc_cad_to_rwf_conversion(float $cad): array
{
    $conv = pcvc_currency_to_rwf_conversion('CAD', $cad);

    return ['rwf' => $conv['rwf'], 'rate' => $conv['rate'], 'cad' => $cad];
}
