<?php

function base64url_decode(string $data): string
{
    $b64 = strtr($data, '-_', '+/');
    $padLen = (4 - (strlen($b64) % 4)) % 4;
    if ($padLen > 0) {
        $b64 .= str_repeat('=', $padLen);
    }
    $decoded = base64_decode($b64, true);
    if ($decoded === false) {
        throw new Exception('Invalid base64url string');
    }
    return $decoded;
}

function verify_jwt_hs256(string $jwt, string $secret): array
{
    // We expect: header.payload.signature (all base64url)
    $parts = explode('.', trim($jwt));
    if (count($parts) !== 3) {
        throw new Exception('Invalid JWT format');
    }

    [$headerB64, $payloadB64, $signatureB64] = $parts;

    $signedPart = $headerB64 . '.' . $payloadB64;
    $expectedSignature = hash_hmac('sha256', $signedPart, $secret, true);
    $givenSignature = base64url_decode($signatureB64);

    if (!hash_equals($expectedSignature, $givenSignature)) {
        throw new Exception('JWT signature verification failed');
    }

    $payloadJson = base64url_decode($payloadB64);
    $payload = json_decode($payloadJson, true);
    if (!is_array($payload)) {
        throw new Exception('JWT payload is not valid JSON');
    }

    return $payload;
}

