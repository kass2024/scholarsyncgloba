<?php
declare(strict_types=1);

/**
 * Routes document analysis to Gemini and/or Claude APIs only (no local OCR tools).
 */

require_once __DIR__ . '/document_vision_gemini.php';
require_once __DIR__ . '/document_vision_claude.php';

function pcvc_docvision_autofill_ready(): bool
{
    return pcvc_docvision_is_configured() || pcvc_docvision_claude_is_configured();
}

function pcvc_docvision_dual_provider_enabled(): bool
{
    if (pcvc_docvision_fast_mode_enabled() && pcvc_env('DOCUMENT_AI_DUAL_PROVIDER') !== '1') {
        return false;
    }

    if (pcvc_env('DOCUMENT_AI_DUAL_PROVIDER') === '0') {
        return false;
    }

    return pcvc_docvision_is_configured() && pcvc_docvision_claude_is_configured();
}

/** gemini | claude */
function pcvc_docvision_pick_provider(int $jobIndex, array $userContent = []): string
{
    foreach ($userContent as $block) {
        if (($block['type'] ?? '') === 'input_file') {
            return pcvc_docvision_is_configured() ? 'gemini' : 'claude';
        }
    }

    $primary = strtolower(trim(pcvc_env('DOCUMENT_AI_PRIMARY')));
    if ($primary === 'claude' && pcvc_docvision_claude_is_configured()) {
        return 'claude';
    }
    if ($primary === 'gemini' && pcvc_docvision_is_configured()) {
        return 'gemini';
    }

    if (!pcvc_docvision_dual_provider_enabled()) {
        if (pcvc_docvision_is_configured()) {
            return 'gemini';
        }

        return 'claude';
    }

    return ($jobIndex % 2 === 0) ? 'gemini' : 'claude';
}

/**
 * @param array<int, array<string, string>> $userContent
 */
function pcvc_docvision_generate_with_provider(
    string $provider,
    string $systemPrompt,
    array $userContent
): array {
    if ($provider === 'claude') {
        $result = pcvc_docvision_claude_generate_json($systemPrompt, $userContent);
    } else {
        $result = pcvc_docvision_generate_json($systemPrompt, $userContent);
        if (!isset($result['error'])) {
            $result['provider'] = 'gemini';
        }
    }

    return $result;
}

/**
 * Build payload for Gemini/Claude only — no pdftotext, Imagick, or browser OCR.
 *
 * @return array<int, array<string, string>>
 */
function pcvc_docvision_build_api_only_content(
    string $tmpPath,
    string $originalName,
    array &$cleanup,
    string $fileInstruction,
    int $maxScanPages = 3,
    int $scanDpi = 168
): array {
    unset($maxScanPages, $scanDpi);

    $header = 'File name: ' . $originalName . "\n" . $fileInstruction
        . "\n\nRead the attached document with API vision/OCR. Extract every visible applicant field.";

    return array_merge(
        [['type' => 'input_text', 'text' => $header]],
        pcvc_docvision_raw_file_blocks($tmpPath, $originalName)
    );
}

/**
 * @param array<int, array<string, string>> $userContent
 */
function pcvc_docvision_payload_is_text_only(array $userContent): bool
{
    foreach ($userContent as $block) {
        if (!is_array($block)) {
            continue;
        }
        $type = (string) ($block["type"] ?? "");
        if (in_array($type, ["input_image", "input_pdf", "input_file"], true)) {
            return false;
        }
    }

    return true;
}

/**
 * @param array<int, array{system: string, user: array<int, array<string, string>>}> $requests
 * @return array<int, array<string, mixed>>
 */
function pcvc_docvision_analyze_parallel_gemini_chunk(array $requests): array
{
    $geminiRequests = [];
    foreach ($requests as $i => $request) {
        if (!pcvc_docvision_payload_is_text_only($request['user'])) {
            continue;
        }
        $geminiRequests[$i] = $request;
    }

    if ($geminiRequests === []) {
        return [];
    }

    return pcvc_docvision_generate_json_multi($geminiRequests);
}

/**
 * Analyze documents with true parallel API calls (curl_multi on Gemini when available).
 *
 * @param array<int, array{system: string, user: array<int, array<string, string>>}> $requests
 * @return array<int, array<string, mixed>>
 */
function pcvc_docvision_analyze_parallel(array $requests): array
{
    if ($requests === []) {
        return [];
    }

    if (count($requests) === 1) {
        $i = (int)array_key_first($requests);

        return [$i => pcvc_docvision_analyze_one($i, $requests[$i])];
    }

    $concurrency = pcvc_docvision_analysis_concurrency();
    $results = [];

    if (pcvc_docvision_is_configured() && !pcvc_docvision_dual_provider_enabled()) {
        foreach (array_chunk($requests, $concurrency, true) as $chunk) {
            $parallel = pcvc_docvision_generate_json_multi($chunk);
            foreach ($chunk as $i => $request) {
                $response = $parallel[$i] ?? ['error' => ['message' => 'No API response']];
                if (isset($response['error'])) {
                    $results[$i] = pcvc_docvision_retry_with_fallback_provider(
                        'gemini',
                        $request['system'],
                        $request['user'],
                        (string)($response['error']['message'] ?? 'Analysis failed.')
                    );
                } else {
                    $response['provider'] = 'gemini';
                    $results[$i] = $response;
                }
            }
        }

        ksort($results);

        return $results;
    }

    foreach (array_chunk($requests, max(1, $concurrency), true) as $chunk) {
        $geminiChunk = [];
        $otherChunk = [];

        foreach ($chunk as $i => $request) {
            $provider = pcvc_docvision_pick_provider((int)$i, $request['user']);
            if ($provider === 'gemini' && pcvc_docvision_is_configured()) {
                $geminiChunk[$i] = $request;
            } else {
                $otherChunk[$i] = $request;
            }
        }

        if ($geminiChunk !== []) {
            $parallel = pcvc_docvision_generate_json_multi($geminiChunk);
            foreach ($geminiChunk as $i => $request) {
                $response = $parallel[$i] ?? ['error' => ['message' => 'No API response']];
                if (isset($response['error'])) {
                    $results[$i] = pcvc_docvision_retry_with_fallback_provider(
                        'gemini',
                        $request['system'],
                        $request['user'],
                        (string)($response['error']['message'] ?? 'Analysis failed.')
                    );
                } else {
                    $response['provider'] = 'gemini';
                    $results[$i] = $response;
                }
            }
        }

        foreach ($otherChunk as $i => $request) {
            $results[$i] = pcvc_docvision_analyze_one((int)$i, $request);
        }
    }

    ksort($results);

    return $results;
}

/**
 * @param array{system: string, user: array<int, array<string, string>>} $request
 * @return array<string, mixed>
 */
function pcvc_docvision_analyze_one(int $jobIndex, array $request): array
{
    $provider = pcvc_docvision_pick_provider($jobIndex, $request['user']);
    $response = pcvc_docvision_generate_with_provider($provider, $request['system'], $request['user']);

    if (!isset($response['error'])) {
        return $response;
    }

    return pcvc_docvision_retry_with_fallback_provider(
        $provider,
        $request['system'],
        $request['user'],
        (string)($response['error']['message'] ?? '')
    );
}

/**
 * @param array<int, array{system: string, user: array<int, array<string, string>>}> $requests
 * @return array<int, array<string, mixed>>
 */
function pcvc_docvision_analyze_sequential(array $requests): array
{
    $results = [];
    foreach ($requests as $i => $request) {
        $results[$i] = pcvc_docvision_analyze_one((int)$i, $request);
    }

    return $results;
}

function pcvc_docvision_retry_with_fallback_provider(
    string $failedProvider,
    string $systemPrompt,
    array $userContent,
    string $firstError
): array {
    $timeoutLike = preg_match('/timed out|timeout|curl error 28/i', $firstError) === 1;
    if ($timeoutLike) {
        return ['error' => ['message' => $firstError]];
    }

    $candidates = [];
    if ($failedProvider !== 'gemini' && pcvc_docvision_is_configured()) {
        $candidates[] = 'gemini';
    }
    if ($failedProvider !== 'claude' && pcvc_docvision_claude_is_configured()) {
        $candidates[] = 'claude';
    }

    $lastError = $firstError !== '' ? $firstError : 'Analysis failed.';
    foreach ($candidates as $provider) {
        $retry = pcvc_docvision_generate_with_provider($provider, $systemPrompt, $userContent);
        if (!isset($retry['error'])) {
            $retry['fallback_from'] = $failedProvider;

            return $retry;
        }
        $lastError = (string)($retry['error']['message'] ?? $lastError);
    }

    return ['error' => ['message' => $lastError]];
}

/**
 * @return array{url: string, headers: array<int, string>, body: string}|null
 */
function pcvc_docvision_build_curl_payload(string $provider, string $systemPrompt, array $userContent): ?array
{
    if ($provider === 'claude') {
        return null;
    }

    if (!pcvc_docvision_is_configured()) {
        return null;
    }

    $parts = pcvc_docvision_content_to_gemini_parts($userContent);
    if ($parts === []) {
        return null;
    }

    $body = json_encode([
        'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents' => [['role' => 'user', 'parts' => $parts]],
        'generationConfig' => pcvc_docvision_gemini_generation_config(0),
    ], JSON_UNESCAPED_UNICODE);

    return [
        'url' => pcvc_docvision_endpoint(),
        'headers' => ['Content-Type: application/json'],
        'body' => (string)$body,
    ];
}

function pcvc_docvision_parse_curl_response(string $provider, string $raw, int $httpCode): array
{
    $raw = trim($raw);
    if ($raw === '' || $httpCode === 0) {
        return ['error' => ['message' => 'API returned empty response (request timed out or connection lost).']];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $preview = mb_substr($raw, 0, 180, 'UTF-8');

        return ['error' => ['message' => 'Invalid API response: ' . $preview]];
    }

    if ($provider === 'claude') {
        if ($httpCode >= 400 || !empty($data['error'])) {
            $msg = (string)($data['error']['message'] ?? $data['message'] ?? "Claude HTTP {$httpCode}");

            return ['error' => ['message' => $msg]];
        }
        $text = '';
        foreach ($data['content'] ?? [] as $part) {
            if (($part['type'] ?? '') === 'text' && !empty($part['text'])) {
                $text .= $part['text'];
            }
        }
        $text = trim($text);
        if ($text === '') {
            return ['error' => ['message' => 'Claude returned no text.']];
        }

        return ['json' => pcvc_docvision_decode_json($text), 'raw_text' => $text, 'provider' => 'claude'];
    }

    if ($httpCode >= 400 || !empty($data['error'])) {
        $msg = (string)($data['error']['message'] ?? "Gemini HTTP {$httpCode}");

        return ['error' => ['message' => $msg]];
    }

    $text = '';
    if (!empty($data['candidates'][0]['content']['parts'])) {
        foreach ($data['candidates'][0]['content']['parts'] as $part) {
            if (!empty($part['text'])) {
                $text .= $part['text'];
            }
        }
    }
    $text = trim($text);
    if ($text === '') {
        return ['error' => ['message' => 'Gemini returned no text.']];
    }

    return ['json' => pcvc_docvision_decode_json($text), 'raw_text' => $text, 'provider' => 'gemini'];
}
