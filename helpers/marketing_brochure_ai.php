<?php
declare(strict_types=1);

/**
 * Smart Brochure Sharing — AI HTML formatter.
 *
 * Given the raw text extracted from a PDF, asks OpenAI to return a clean,
 * mobile-first, semantically marked-up HTML fragment for the public reader.
 * Falls back gracefully (returns null) when the API key is missing, cURL is
 * absent, the response is malformed, or the model refuses — so the caller can
 * keep using the regex-based extractor as a safety net.
 *
 * Configuration (.env):
 *   OPENAI_API_KEY         (required)
 *   OPENAI_MODEL           (optional, default: gpt-4o-mini)
 *   OPENAI_BASE_URL        (optional, default: https://api.openai.com)
 *   PCVC_AI_MAX_CHARS      (optional, default: 14000)
 */

require_once __DIR__ . '/env_load.php';

function pcvc_brochure_ai_enabled(): bool
{
    if (trim(xander_env_get('PCVC_SKIP_BROCHURE_AI')) === '1') {
        return false;
    }
    return trim(xander_env_get('OPENAI_API_KEY')) !== '' && function_exists('curl_init');
}

/** Skip AI calls for 1h after a quota/rate-limit error (avoids slow bulk re-extract). */
function pcvc_brochure_ai_quota_paused(): bool
{
    $flag = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcvc_brochure_ai_quota.flag';
    if (!is_file($flag)) {
        return false;
    }
    return (time() - (int) @filemtime($flag)) < 3600;
}

function pcvc_brochure_ai_mark_quota_exhausted(): void
{
    $flag = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcvc_brochure_ai_quota.flag';
    @touch($flag);
}

/**
 * Convert raw PDF text into smart, mobile-friendly HTML using OpenAI.
 * Returns null when AI is unavailable or fails — callers should fall back
 * to the regex-based formatter in that case.
 *
 * @return string|null
 */
function pcvc_brochure_ai_html_from_text(string $rawText, string $title, string $regionName): ?string
{
    $key = trim(xander_env_get('OPENAI_API_KEY'));
    if ($key === '' || !function_exists('curl_init') || pcvc_brochure_ai_quota_paused()) {
        return null;
    }
    $text = trim($rawText);
    if ($text === '') {
        return null;
    }
    $cap = (int) (xander_env_get('PCVC_AI_MAX_CHARS') ?: 14000);
    if (strlen($text) > $cap) {
        $text = substr($text, 0, $cap) . "\n\n[... document truncated ...]";
    }

    $model = trim(xander_env_get('OPENAI_MODEL'));
    if ($model === '') {
        $model = 'gpt-4o-mini';
    }
    $base = rtrim(trim(xander_env_get('OPENAI_BASE_URL')) ?: 'https://api.openai.com', '/');
    $url  = $base . '/v1/chat/completions';

    $system = pcvc_brochure_ai_system_prompt();
    $user   = pcvc_brochure_ai_user_prompt($title, $regionName, $text);

    $payload = [
        'model'           => $model,
        'temperature'     => 0.15,
        'response_format' => ['type' => 'json_object'],
        'messages'        => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $http < 200 || $http >= 300) {
        if ($http === 429 || (is_string($body) && stripos($body, 'insufficient_quota') !== false)) {
            pcvc_brochure_ai_mark_quota_exhausted();
        }
        @error_log('[brochure-ai] HTTP ' . $http . ' ' . $err . ' body=' . substr((string) $body, 0, 300));
        return null;
    }

    $json = json_decode((string) $body, true);
    $msg  = $json['choices'][0]['message']['content'] ?? null;
    if (!is_string($msg) || trim($msg) === '') {
        return null;
    }
    $obj = json_decode($msg, true);
    if (!is_array($obj)) {
        return null;
    }
    $html = (string) ($obj['html'] ?? '');
    if (trim($html) === '') {
        return null;
    }
    return pcvc_brochure_ai_sanitize_html($html);
}

function pcvc_brochure_ai_system_prompt(): string
{
    return <<<SYS
You are a senior content designer at ScholarSync Global. You convert raw text extracted from a PDF brochure into a clean, mobile-first HTML fragment a customer will read on their phone.

OUTPUT FORMAT
- Reply with a single JSON object: {"html": "<...>"} — nothing else.
- The "html" value is an HTML fragment only (no <!doctype>, <html>, <head>, <body>, <style>, or <script>).
- Use these CSS classes that already exist in the host page:
    h2.brochure-heading        (top-level section title)
    h3.brochure-subheading     (sub-section title)
    p.brochure-para            (regular paragraph)
    ul.brochure-list           (bulleted list — REQUIRES <li> children)
    ol.brochure-list           (numbered list — REQUIRES <li> children)
    div.brochure-callout       (highlighted note / important info box)
    div.brochure-warning       (warning / "must have" box)
    div.brochure-tip           (helpful tip)
    table.brochure-table       (use for structured data: requirements, fees, timelines — wrap with <div class="brochure-table-wrap"> for horizontal scroll on mobile)

STYLE RULES
- Be CONCISE. Customers read on phones. Prefer short sentences and tight bullet lists over long paragraphs.
- Put the most important information FIRST — what the customer must prepare, the key documents required, deadlines.
- Group related items under clear headings.
- Convert "checklist-like" content into bullet or numbered lists; never let lists run as inline text.
- Convert document/fee/timeline data into a <table class="brochure-table"> wrapped in <div class="brochure-table-wrap"> when it makes sense.
- Use <strong> for the noun/document name in each bullet, then a short explanation.
- Wrap critical reminders (e.g. "Original required", "Apostille mandatory", "Must be within 6 months") in <div class="brochure-callout">.
- Wrap warnings (e.g. "Without this your application will be rejected") in <div class="brochure-warning">.
- Optional helpful hints (e.g. "Tip: scan in colour at 300 dpi") go in <div class="brochure-tip">.
- Strip noisy PDF artefacts: page numbers, headers/footers, repeated branding, "Page X of Y", phone watermarks, broken hyphenations across lines.
- Auto-link URLs and emails with <a href="..."> (add rel="noopener" target="_blank" for external links).
- Never invent facts that aren't in the source text. If something is unclear, omit it instead of guessing.
- No inline styles, no class attributes other than the ones listed above, no IDs, no <img>, no <iframe>, no <script>, no <style>.

KEEP THE TONE
- Friendly, professional, helpful — like a counsellor briefing a student.
- Use the second person ("you", "your application") so it feels personal.
- End with a short closing paragraph encouraging the reader to reach out for help.
SYS;
}

function pcvc_brochure_ai_user_prompt(string $title, string $regionName, string $text): string
{
    return "BROCHURE TITLE: {$title}\nDESTINATION / REGION: {$regionName}\n\nRAW PDF TEXT (verbatim, may contain artefacts):\n" .
        "----------------------------------------\n" .
        $text .
        "\n----------------------------------------\n\n" .
        'Return the JSON object now. Remember: "html" must be the fragment, mobile-first, friendly, no extra commentary.';
}

/**
 * Lightweight sanitiser: strips disallowed tags and unwraps the response if
 * the model wrapped its fragment in <html>/<body>. Keeps only the allowed
 * subset of tags and attributes used by the brochure reader.
 */
function pcvc_brochure_ai_sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    if (stripos($html, '<body') !== false) {
        if (preg_match('~<body[^>]*>(.*?)</body>~si', $html, $m)) {
            $html = $m[1];
        }
    }
    $html = preg_replace('~<\?xml[^>]*\?>~i', '', $html) ?? $html;
    $html = preg_replace('~<!doctype[^>]*>~i', '', $html) ?? $html;
    $html = preg_replace('~<(html|head|body|meta|link|style|script|iframe|object|embed|form|input|button)\b[^>]*>~i', '', $html) ?? $html;
    $html = preg_replace('~</(html|head|body|style|script|iframe|object|embed|form|input|button)>~i', '', $html) ?? $html;

    $html = preg_replace_callback('~<a\s+([^>]*)>~i', static function ($m) {
        $attrs = $m[1];
        if (preg_match('~href\s*=\s*"([^"]*)"~i', $attrs, $h) || preg_match("~href\\s*=\\s*'([^']*)'~i", $attrs, $h)) {
            $href = $h[1];
            if (preg_match('~^(mailto:|tel:|#)~i', $href)) {
                return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
            }
            if (preg_match('~^https?://~i', $href)) {
                return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">';
            }
        }
        return '<a>';
    }, $html) ?? $html;

    return $html;
}
