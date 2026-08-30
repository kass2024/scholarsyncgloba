<?php
declare(strict_types=1);

/**
 * Smart Brochure Sharing — PDF → text → beautified HTML extractor.
 *
 * Strategy (best → fallback):
 *   1) `pdftotext` (xpdf / poppler-utils) — works on most cPanel / xampp boxes.
 *   2) Naive PHP regex extraction from PDF text streams (uncompressed only).
 *
 * The resulting HTML is intentionally simple: headings, paragraphs, lists.
 * Hosts that don't have either path simply get a friendly placeholder, and the
 * upload action still falls back to the embedded PDF viewer.
 */

/**
 * Public entry point. Returns ['text' => string, 'html' => string, 'engine' => string, 'ai_used' => bool].
 *
 * When the OpenAI key is configured in .env, the cleaned text is sent to the
 * AI formatter to produce a mobile-first, semantically marked-up HTML
 * fragment. Falls back to the regex formatter on any failure.
 */
function pcvc_brochure_extract_pdf(string $pdfAbsolutePath, string $title = '', string $regionName = ''): array
{
    if (!is_file($pdfAbsolutePath)) {
        return ['text' => '', 'html' => '', 'engine' => 'missing', 'ai_used' => false];
    }

    $text   = '';
    $engine = '';

    $cli = pcvc_brochure_run_pdftotext($pdfAbsolutePath);
    if ($cli !== null && trim($cli) !== '') {
        $text   = $cli;
        $engine = 'pdftotext';
    } else {
        $php = pcvc_brochure_php_extract($pdfAbsolutePath);
        if ($php !== null && trim($php) !== '') {
            $text   = $php;
            $engine = 'php-regex';
        }
    }

    if ($text === '') {
        return ['text' => '', 'html' => '', 'engine' => 'none', 'ai_used' => false];
    }

    $text   = pcvc_brochure_clean_extracted_text($text);
    $html   = '';
    $aiUsed = false;

    if (function_exists('pcvc_brochure_ai_enabled') && pcvc_brochure_ai_enabled()) {
        $aiHtml = pcvc_brochure_ai_html_from_text($text, $title, $regionName);
        if (is_string($aiHtml) && trim($aiHtml) !== '') {
            $html   = $aiHtml;
            $engine .= '+ai';
            $aiUsed = true;
        }
    }

    if ($html === '') {
        $html = pcvc_brochure_text_to_html($text);
    }

    return ['text' => $text, 'html' => $html, 'engine' => $engine, 'ai_used' => $aiUsed];
}

/**
 * Try the `pdftotext` binary in a few common locations.
 */
function pcvc_brochure_run_pdftotext(string $pdf): ?string
{
    $candidates = ['pdftotext'];
    if (DIRECTORY_SEPARATOR === '\\') {
        $candidates[] = 'C:\\Program Files\\xpdf\\bin64\\pdftotext.exe';
        $candidates[] = 'C:\\Program Files (x86)\\xpdf\\bin\\pdftotext.exe';
    } else {
        $candidates[] = '/usr/bin/pdftotext';
        $candidates[] = '/usr/local/bin/pdftotext';
        $candidates[] = '/opt/homebrew/bin/pdftotext';
    }

    foreach ($candidates as $bin) {
        $cmd = escapeshellarg($bin) . ' -layout -enc UTF-8 -nopgbrk ' . escapeshellarg($pdf) . ' - 2>/dev/null';
        $out = pcvc_brochure_shell_run($cmd);
        if (is_string($out) && trim($out) !== '') {
            return $out;
        }
    }

    return null;
}

/** Run a shell command via proc_open or shell_exec (cPanel often disables proc_open). */
function pcvc_brochure_shell_run(string $cmd): ?string
{
    if (function_exists('proc_open')) {
        $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open($cmd, $desc, $pipes);
        if (is_resource($proc)) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            if (is_string($out) && trim($out) !== '' && stripos((string) $err, 'not found') === false) {
                return $out;
            }
        }
    }
    if (function_exists('shell_exec')) {
        $out = @shell_exec($cmd);
        if (is_string($out) && trim($out) !== '') {
            return $out;
        }
    }
    return null;
}

/**
 * Decompress a PDF FlateDecode stream (zlib or raw deflate).
 */
function pcvc_brochure_inflate_pdf_stream(string $data): ?string
{
    if ($data === '') {
        return null;
    }
    if (function_exists('gzuncompress')) {
        $un = @gzuncompress($data);
        if ($un !== false && $un !== '') {
            return $un;
        }
    }
    if (function_exists('gzinflate')) {
        $un = @gzinflate($data);
        if ($un !== false && $un !== '') {
            return $un;
        }
        // Skip 2-byte zlib header if present
        if (strlen($data) > 2) {
            $un = @gzinflate(substr($data, 2));
            if ($un !== false && $un !== '') {
                return $un;
            }
        }
    }
    if (function_exists('zlib_decode')) {
        $un = @zlib_decode($data);
        if ($un !== false && $un !== '') {
            return $un;
        }
    }
    return null;
}

/**
 * Minimalist pure-PHP fallback. Pulls text from BT...ET blocks of uncompressed
 * content streams. Good enough for simple, non-encrypted PDFs.
 */
function pcvc_brochure_php_extract(string $pdf): ?string
{
    $raw = @file_get_contents($pdf);
    if ($raw === false || $raw === '') {
        return null;
    }

    // Decompress FlateDecode streams (try zlib wrapper, raw deflate, zlib_decode).
    $raw = preg_replace_callback(
        '/stream\\r?\\n(.*?)\\r?\\nendstream/s',
        static function (array $m): string {
            $data = $m[1] ?? '';
            $decoded = pcvc_brochure_inflate_pdf_stream($data);
            if ($decoded !== null && $decoded !== '') {
                return "stream\n" . $decoded . "\nendstream";
            }
            return $m[0];
        },
        $raw
    ) ?? $raw;

    if (!preg_match_all('/BT\s*(.*?)\s*ET/s', $raw, $blocks)) {
        return null;
    }

    $out = [];
    foreach ($blocks[1] as $block) {
        // Strings: (text) Tj   OR   [(t)(e)(x)(t)] TJ
        if (preg_match_all('/\((?:\\\\.|[^()\\\\])*\)\s*T[jJ]/', $block, $hits)) {
            $line = '';
            foreach ($hits[0] as $h) {
                if (preg_match_all('/\((?:\\\\.|[^()\\\\])*\)/', $h, $strs)) {
                    foreach ($strs[0] as $s) {
                        $s = substr($s, 1, -1);
                        $s = str_replace(
                            ['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'],
                            ['(', ')', '\\', "\n", "\r", "\t"],
                            $s
                        );
                        $line .= $s . ' ';
                    }
                }
            }
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }
    }
    if (!$out) {
        return null;
    }

    return implode("\n", $out);
}

/**
 * Normalise extracted text: collapse stray whitespace, fix common artifacts.
 */
function pcvc_brochure_clean_extracted_text(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    // Common ligature replacements
    $text = strtr($text, [
        'ﬁ' => 'fi', 'ﬂ' => 'fl', 'ﬃ' => 'ffi', 'ﬄ' => 'ffl',
        "\u{00A0}" => ' ',
    ]);
    // Remove form-feed and other control chars except newline/tab.
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? $text;
    // Collapse 3+ blank lines to 2.
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    // Right-strip spaces on every line.
    $lines = preg_split("/\n/", $text) ?: [];
    $lines = array_map(static fn(string $l): string => rtrim($l), $lines);
    return trim(implode("\n", $lines));
}

/**
 * Convert the plaintext into a small set of safe HTML blocks.
 * Handles: bullets (•, ✓, ✗, -, *, →, etc.), numbered lists,
 * "N. Heading" followed by bullets, headings (ALL CAPS or trailing ":"), and
 * intelligently joins soft-wrapped lines into clean paragraphs.
 */
function pcvc_brochure_text_to_html(string $text): string
{
    $blocks = preg_split("/\n\s*\n/", $text) ?: [];
    $html   = '';

    $bulletPattern   = '/^\s*([\x{2022}\x{25CF}\x{25CB}\x{25A0}\x{2013}\x{2014}\x{00B7}\x{2192}\x{27A2}\x{2714}\x{2713}\x{2717}\x{2718}\-\*o])\s+(.+)$/u';
    $numberedPattern = '/^\s*(\d{1,3})[\.\)]\s+(.+)$/';
    $numHeadingOnly  = '/^\s*(\d{1,3})[\.\)]\s+([A-Z][^.!?]{1,80})$/u';
    // True pictographic emoji only — DO NOT include U+2600..U+27BF here, that
    // range contains bullet/symbol characters (✓, ✗, ➢, etc.) we want to keep.
    $emojiPrefix     = '/^[\x{1F300}-\x{1FAFF}\x{1F000}-\x{1F2FF}]\s+(.+)$/u';

    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }
        $lines = array_values(array_filter(array_map('trim', preg_split("/\n/", $block) ?: []), static fn($l) => $l !== ''));
        if (!$lines) {
            continue;
        }

        // Strip emoji prefixes (e.g. "📜 What You Need" -> "What You Need").
        $lines = array_map(static function (string $l) use ($emojiPrefix) {
            return preg_match($emojiPrefix, $l, $m) ? $m[1] : $l;
        }, $lines);

        // 1) All-bullets block
        $allBullets = true;
        foreach ($lines as $ln) {
            if (!preg_match($bulletPattern, $ln)) { $allBullets = false; break; }
        }
        if ($allBullets && count($lines) >= 1) {
            $html .= '<ul class="brochure-list">';
            foreach ($lines as $ln) {
                if (preg_match($bulletPattern, $ln, $m)) {
                    $html .= '<li>' . pcvc_brochure_escape_inline($m[2]) . '</li>';
                }
            }
            $html .= '</ul>';
            continue;
        }

        // 2) All-numbered block (and not single-line "N. Heading")
        $allNumbered = true;
        foreach ($lines as $ln) {
            if (!preg_match($numberedPattern, $ln)) { $allNumbered = false; break; }
        }
        if ($allNumbered && count($lines) > 1) {
            $html .= '<ol class="brochure-list">';
            foreach ($lines as $ln) {
                if (preg_match($numberedPattern, $ln, $m)) {
                    $html .= '<li>' . pcvc_brochure_escape_inline($m[2]) . '</li>';
                }
            }
            $html .= '</ol>';
            continue;
        }

        // 2b) Bullets followed by a "N. Heading" trailer → split into ul + heading
        if (count($lines) >= 2) {
            $bulletCount = 0;
            foreach ($lines as $ln) {
                if (preg_match($bulletPattern, $ln)) { $bulletCount++; } else { break; }
            }
            if ($bulletCount >= 1 && $bulletCount < count($lines)
                && preg_match($numHeadingOnly, $lines[$bulletCount], $nm)) {
                $html .= '<ul class="brochure-list">';
                for ($i = 0; $i < $bulletCount; $i++) {
                    if (preg_match($bulletPattern, $lines[$i], $b)) {
                        $html .= '<li>' . pcvc_brochure_escape_inline($b[2]) . '</li>';
                    }
                }
                $html .= '</ul>';
                $html .= '<h4 class="brochure-subheading">' . pcvc_brochure_escape_inline($nm[1] . '. ' . $nm[2]) . '</h4>';
                // Any remaining lines after the heading
                $tail = array_slice($lines, $bulletCount + 1);
                if ($tail) {
                    $html .= '<p class="brochure-para">' . pcvc_brochure_escape_inline(pcvc_brochure_join_soft_wraps($tail)) . '</p>';
                }
                continue;
            }
        }

        // 3) "N. Title" (first line) followed by bullets → subheading + list
        if (count($lines) >= 2 && preg_match($numHeadingOnly, $lines[0], $m)) {
            $rest = array_slice($lines, 1);
            $restAllBullets = true;
            foreach ($rest as $ln) {
                if (!preg_match($bulletPattern, $ln)) { $restAllBullets = false; break; }
            }
            if ($restAllBullets) {
                $html .= '<h4 class="brochure-subheading">' . pcvc_brochure_escape_inline($m[1] . '. ' . $m[2]) . '</h4>';
                $html .= '<ul class="brochure-list">';
                foreach ($rest as $ln) {
                    if (preg_match($bulletPattern, $ln, $b)) {
                        $html .= '<li>' . pcvc_brochure_escape_inline($b[2]) . '</li>';
                    }
                }
                $html .= '</ul>';
                continue;
            }
        }

        // 4) Single short uppercase line → top heading.
        if (count($lines) === 1) {
            $only = $lines[0];
            $isUpper = $only !== ''
                && mb_strlen($only) >= 4 && mb_strlen($only) <= 90
                && mb_strtoupper($only, 'UTF-8') === $only
                && preg_match('/[A-Z]/', $only);
            if ($isUpper) {
                $html .= '<h3 class="brochure-heading">' . pcvc_brochure_escape_inline($only) . '</h3>';
                continue;
            }
            // "Section Title:" or "N. Section Title" alone → subheading.
            $isColonTitle = preg_match('/^[A-Z][^.!?]{2,80}:\s*$/u', $only);
            $isNumHead    = preg_match($numHeadingOnly, $only);
            $isTitleCase  = !$isColonTitle && !$isNumHead
                && mb_strlen($only) >= 3 && mb_strlen($only) <= 90
                && preg_match('/^[A-Z][^.!?]{2,}$/u', $only)
                && substr($only, -1) !== '.';
            if ($isColonTitle || $isNumHead || $isTitleCase) {
                $clean = $isColonTitle ? rtrim($only, ': ') : $only;
                $html .= '<h4 class="brochure-subheading">' . pcvc_brochure_escape_inline($clean) . '</h4>';
                continue;
            }
        }

        // 5) Default: join soft-wrapped lines into clean paragraphs.
        $joined = pcvc_brochure_join_soft_wraps($lines);
        $body   = pcvc_brochure_escape_inline($joined);
        $html  .= '<p class="brochure-para">' . $body . '</p>';
    }

    if ($html === '') {
        $html = '<p class="brochure-para">' . pcvc_brochure_escape_inline($text) . '</p>';
    }
    return $html;
}

/**
 * Glue soft-wrapped paragraph lines back together. A line is treated as a
 * continuation unless the previous one ends with terminal punctuation.
 */
function pcvc_brochure_join_soft_wraps(array $lines): string
{
    $out   = '';
    $count = count($lines);
    foreach ($lines as $i => $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $endsHard = (bool) preg_match('/[\.\!\?\:\;\)]$/u', $line);
        $out .= $line;
        if ($i < $count - 1) {
            // Hyphenated wrap: "informa-\ntion" → "information"
            if (substr($line, -1) === '-') {
                $out = substr($out, 0, -1);
            } else {
                $out .= $endsHard ? "\n" : ' ';
            }
        }
    }
    // Newline → <br> only when caller wants paragraph breaks inside body.
    return preg_replace("/\n+/", "<br>", $out) ?? $out;
}

/**
 * Escape user text and turn URLs into clickable links.
 */
function pcvc_brochure_escape_inline(string $s): string
{
    $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $s = preg_replace_callback(
        '~\b(https?://[^\s<]+|www\.[^\s<]+)~i',
        static function (array $m): string {
            $url = $m[1];
            $href = stripos($url, 'http') === 0 ? $url : 'http://' . $url;
            return '<a href="' . $href . '" target="_blank" rel="noopener">' . $url . '</a>';
        },
        $s
    ) ?? $s;
    $s = preg_replace_callback(
        '~([A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})~',
        static fn(array $m): string => '<a href="mailto:' . $m[1] . '">' . $m[1] . '</a>',
        $s
    ) ?? $s;
    return $s;
}
