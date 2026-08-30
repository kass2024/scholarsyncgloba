<?php
declare(strict_types=1);

/**
 * Claude (Anthropic) vision for document extraction — used alongside Gemini.
 */

require_once __DIR__ . '/env_bootstrap.php';

function pcvc_docvision_claude_api_key(): string
{
    return pcvc_env('ANTHROPIC_API_KEY');
}

/** @return array<int, string> */
function pcvc_docvision_claude_model_candidates(): array
{
    $preferred = trim(pcvc_env('ANTHROPIC_MODEL'));
    if ($preferred === '') {
        $preferred = trim(pcvc_env('ANTHROPIC_DOCUMENT_MODEL'));
    }

    $candidates = [
        $preferred,
        'claude-sonnet-4-6',
        'claude-sonnet-4-5-20250929',
        'claude-haiku-4-5-20251001',
    ];

    $out = [];
    foreach ($candidates as $model) {
        $model = trim((string)$model);
        if ($model !== '' && !in_array($model, $out, true)) {
            $out[] = $model;
        }
    }

    return $out !== [] ? $out : ['claude-sonnet-4-6'];
}

function pcvc_docvision_claude_model(): string
{
    $models = pcvc_docvision_claude_model_candidates();

    return $models[0];
}

function pcvc_docvision_claude_is_configured(): bool
{
    return pcvc_docvision_claude_api_key() !== '';
}

/**
 * @param array<int, array<string, string>> $openAiStyleContent
 * @return array{blocks: array<int, array<string, mixed>>, has_pdf: bool}
 */
function pcvc_docvision_content_to_claude_blocks(array $openAiStyleContent): array
{
    $blocks = [];
    $hasPdf = false;

    foreach ($openAiStyleContent as $block) {
        $type = (string)($block['type'] ?? '');

        if ($type === 'input_text') {
            $text = trim((string)($block['text'] ?? ''));
            if ($text !== '') {
                $blocks[] = ['type' => 'text', 'text' => $text];
            }
            continue;
        }

        if ($type === 'input_image') {
            $url = (string)($block['image_url'] ?? '');
            if (!preg_match('#^data:([^;]+);base64,(.+)$#', $url, $m)) {
                continue;
            }
            $media = $m[1];
            if ($media === 'image/jpg') {
                $media = 'image/jpeg';
            }
            $blocks[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $media,
                    'data' => $m[2],
                ],
            ];
            continue;
        }

        if ($type === 'input_pdf') {
            $hasPdf = true;
            $blocks[] = [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'application/pdf',
                    'data' => (string)($block['data'] ?? ''),
                ],
            ];
            continue;
        }

        if ($type === 'input_file') {
            $fileMime = (string)($block['mime'] ?? 'application/octet-stream');
            if ($fileMime === 'application/pdf') {
                $hasPdf = true;
            }
            $blocks[] = [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $fileMime,
                    'data' => (string)($block['data'] ?? ''),
                ],
            ];
        }
    }

    return ['blocks' => $blocks, 'has_pdf' => $hasPdf];
}

function pcvc_docvision_claude_extract_error(array $data, int $httpCode): string
{
    if (isset($data['error']) && is_array($data['error'])) {
        $msg = trim((string)($data['error']['message'] ?? ''));
        if ($msg !== '') {
            return $msg;
        }
        $type = (string)($data['error']['type'] ?? '');

        return $type !== '' ? $type : "Claude HTTP {$httpCode}";
    }

    if (isset($data['error']) && is_string($data['error'])) {
        return $data['error'];
    }

    return "Claude HTTP {$httpCode}";
}

/**
 * @param array<int, array<string, string>> $userContent
 */
function pcvc_docvision_claude_generate_json(
    string $systemPrompt,
    array $userContent,
    int $maxRetries = 2,
    int $delayMs = 500
): array {
    require_once __DIR__ . '/document_vision_gemini.php';

    $apiKey = pcvc_docvision_claude_api_key();
    if ($apiKey === '') {
        return ['error' => ['message' => 'ANTHROPIC_API_KEY is not configured in .env.']];
    }

    $converted = pcvc_docvision_content_to_claude_blocks($userContent);
    $content = $converted['blocks'];
    $hasPdf = (bool)$converted['has_pdf'];
    if ($content === []) {
        return ['error' => ['message' => 'No document content to analyze.']];
    }

    $headers = [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ];
    if ($hasPdf) {
        $headers[] = 'anthropic-beta: pdfs-2024-09-25';
    }

    $url = 'https://api.anthropic.com/v1/messages';
    $lastError = 'Claude extraction failed.';

    foreach (pcvc_docvision_claude_model_candidates() as $model) {
        $body = [
            'model' => $model,
            'max_tokens' => 4096,
            'temperature' => 0,
            'system' => $systemPrompt . "\n\nRespond with valid JSON only. No markdown fences.",
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
        ];

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, pcvc_docvision_curl_options(300) + [
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            ]);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($error) {
                $lastError = $error;
                if ($attempt < $maxRetries - 1) {
                    usleep($delayMs * 1000);
                    continue;
                }
                break;
            }

            if ($httpCode === 0 || trim((string)$response) === '') {
                $lastError = 'Claude returned an empty response (connection timed out).';
                break;
            }

            $data = json_decode((string)$response, true);
            if (!is_array($data)) {
                $lastError = 'Invalid response from Claude.';
                break;
            }

            if ($httpCode >= 400 || ($data['type'] ?? '') === 'error') {
                $lastError = pcvc_docvision_claude_extract_error($data, $httpCode);
                $isModelError = stripos($lastError, 'model') !== false
                    || stripos($lastError, 'not_found') !== false
                    || $httpCode === 404;
                if ($isModelError) {
                    break;
                }
                if ($attempt < $maxRetries - 1 && ($httpCode >= 500 || $httpCode === 429)) {
                    usleep($delayMs * 1000);
                    continue;
                }
                break;
            }

            $text = '';
            if (!empty($data['content']) && is_array($data['content'])) {
                foreach ($data['content'] as $part) {
                    if (($part['type'] ?? '') === 'text' && !empty($part['text'])) {
                        $text .= $part['text'];
                    }
                }
            }

            $text = trim($text);
            if ($text === '') {
                $lastError = 'Claude returned no text.';
                break;
            }

            return [
                'json' => pcvc_docvision_decode_json($text),
                'raw_text' => $text,
                'provider' => 'claude',
                'model' => $model,
            ];
        }
    }

    return ['error' => ['message' => $lastError]];
}
