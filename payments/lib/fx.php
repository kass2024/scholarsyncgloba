<?php
declare(strict_types=1);

/**
 * Minimal FX helper with caching.
 * Uses a free public endpoint and caches results to disk.
 */

function payments_fx_cache_path(): string
{
    return __DIR__ . '/../storage/fx_cache.json';
}

function payments_fx_get_rate_to_rwf(string $fromCurrency): float
{
    $from = strtoupper(trim($fromCurrency));
    if ($from === '' || $from === 'RWF') {
        return 1.0;
    }

    $cachePath = payments_fx_cache_path();
    $cache = null;
    if (file_exists($cachePath)) {
        $cache = json_decode((string)file_get_contents($cachePath), true);
    }

    $today = date('Y-m-d');
    if (is_array($cache) && ($cache['date'] ?? '') === $today) {
        $rates = $cache['rates'] ?? null;
        if (is_array($rates) && isset($rates[$from]) && is_numeric($rates[$from])) {
            return (float)$rates[$from];
        }
    }

    // Endpoint returns rates as: 1 <BASE> = <RWF> ... we query base=<from>.
    $url = 'https://open.er-api.com/v6/latest/' . rawurlencode($from);
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw) || $raw === '') {
        // Safe fallback: keep last cached rate if present (even if stale).
        if (is_array($cache) && isset($cache['rates'][$from]) && is_numeric($cache['rates'][$from])) {
            return (float)$cache['rates'][$from];
        }
        return 1.0;
    }

    $json = json_decode($raw, true);
    $rwf = is_array($json) ? ($json['rates']['RWF'] ?? null) : null;
    if (!is_numeric($rwf) || (float)$rwf <= 0) {
        if (is_array($cache) && isset($cache['rates'][$from]) && is_numeric($cache['rates'][$from])) {
            return (float)$cache['rates'][$from];
        }
        return 1.0;
    }

    $newCache = [
        'date' => $today,
        'rates' => is_array($cache['rates'] ?? null) ? $cache['rates'] : [],
        'source' => 'open.er-api.com',
        'fetched_at' => date('c'),
    ];
    $newCache['rates'][$from] = (float) $rwf;
    if (!is_dir(dirname($cachePath))) {
        @mkdir(dirname($cachePath), 0755, true);
    }
    @file_put_contents($cachePath, json_encode($newCache, JSON_UNESCAPED_SLASHES), LOCK_EX);

    return (float)$rwf;
}

