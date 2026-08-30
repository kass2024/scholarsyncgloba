<?php

function mopay_http_request(string $method, string $url, array $headers = [], $body = null): array
{
    $ch = curl_init();
    if ($ch === false) {
        throw new Exception('Failed to initialize cURL');
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Helpful defaults for API testing
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    if ($body !== null) {
        if (is_array($body)) {
            $body = json_encode($body);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $responseBody = curl_exec($ch);
    $curlErrNo = curl_errno($ch);
    $curlErr = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status_code' => $statusCode,
        'body' => $responseBody !== false ? $responseBody : null,
        'curl_error' => $curlErrNo ? ($curlErr ?: "cURL error #{$curlErrNo}") : null,
    ];
}

