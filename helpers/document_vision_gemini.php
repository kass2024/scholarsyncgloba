<?php
declare(strict_types=1);

/**
 * Gemini vision/OCR for application document extraction and validation.
 * Use GEMINI_API_KEY in .env — OpenAI remains for chat, marketing, job scoring, etc.
 */

require_once __DIR__ . '/env_bootstrap.php';

function pcvc_docvision_api_key(): string
{
    $key = pcvc_env('GEMINI_API_KEY');
    if ($key === '') {
        $key = pcvc_env('GOOGLE_AI_API_KEY');
    }

    return $key;
}

function pcvc_docvision_model(): string
{
    $model = pcvc_env('GEMINI_MODEL');
    if ($model === '') {
        $model = pcvc_env('GEMINI_DOCUMENT_MODEL');
    }

    return $model !== '' ? $model : 'gemini-2.0-flash';
}

function pcvc_docvision_is_configured(): bool
{
    return pcvc_docvision_api_key() !== '';
}

function pcvc_docvision_curl_options(int $timeout = 120): array
{
    return [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    ];
}

function pcvc_docvision_endpoint(): string
{
    $model = rawurlencode(pcvc_docvision_model());
    $key = rawurlencode(pcvc_docvision_api_key());

    return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
}

function pcvc_docvision_is_scanned_pdf(string $pdfPath): bool
{
    $sample = @file_get_contents($pdfPath, false, null, 0, 5000);
    if ($sample === false || $sample === '') {
        return true;
    }

    return !preg_match('/[A-Za-z]{4,}/', $sample);
}

/** @return array<int, string> */
function pcvc_docvision_post_document_texts(): array
{
    $raw = $_POST['document_text'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $idx => $value) {
        $out[(int)$idx] = trim((string)$value);
    }

    return $out;
}

function pcvc_docvision_pdf_extract_text(string $pdfPath): string
{
    $stderr = DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null';
    $bins = [];
    $custom = trim((string)(getenv('AUTOFILL_PDFTOTEXT_BIN') ?: ''));
    if ($custom !== '') {
        $bins[] = $custom;
    }
    $bins[] = 'pdftotext';
    if (DIRECTORY_SEPARATOR === '\\') {
        $bins[] = 'C:\\Program Files\\xpdf\\bin64\\pdftotext.exe';
        $bins[] = 'C:\\Program Files (x86)\\xpdf\\bin\\pdftotext.exe';
    }

    foreach ($bins as $bin) {
        $cmd = escapeshellarg($bin) . ' -layout -enc UTF-8 -nopgbrk '
            . escapeshellarg($pdfPath) . ' - ' . $stderr;
        if (function_exists('shell_exec')) {
            $out = @shell_exec($cmd);
            if (is_string($out) && trim($out) !== '') {
                return trim($out);
            }
        }
    }

    $content = @file_get_contents($pdfPath);
    if ($content === false || $content === '') {
        return '';
    }
    if (preg_match_all('/[\x20-\x7E\xC0-\xFF]{4,}/u', $content, $matches)) {
        return trim(implode("\n", $matches[0]));
    }

    return '';
}

function pcvc_docvision_max_scan_pages_for_name(string $originalName): int
{
    $hint = strtolower($originalName);
    if (preg_match('/\b(passport|passeport)\b/', $hint)) {
        return 1;
    }
    if (preg_match('/\b(cv|resume|curriculum)\b/', $hint)) {
        return 2;
    }
    if (preg_match('/\b(transcript|releve|relevé|academic|grade)\b/', $hint)) {
        return 2;
    }

    return 2;
}

function pcvc_docvision_scanned_pdf_to_images(
    string $pdfPath,
    string $outDir,
    int $maxPages = 4,
    int $dpi = 144
): array {
    if (!class_exists('Imagick')) {
        throw new RuntimeException('Scanned PDF support requires Imagick on the server.');
    }

    if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
        throw new RuntimeException('Failed to create scan output directory.');
    }

    $images = [];
    $im = new Imagick();
    $im->setResolution($dpi, $dpi);
    $im->readImage($pdfPath);

    $pageCount = 0;
    foreach ($im as $i => $page) {
        if ($pageCount >= $maxPages) {
            break;
        }
        $page->setImageFormat('jpeg');
        $page->setImageCompressionQuality(72);
        $out = $outDir . 'page_' . ($i + 1) . '_' . bin2hex(random_bytes(3)) . '.jpg';
        $page->writeImage($out);
        $images[] = $out;
        $pageCount++;
    }

    $im->clear();
    $im->destroy();

    return $images;
}

/**
 * Raw file bytes for API vision — no pdftotext, Imagick, or local OCR.
 *
 * @return array<int, array<string, string>>
 */
function pcvc_docvision_raw_file_blocks(string $tmpPath, string $originalName): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $mime = mime_content_type($tmpPath) ?: 'application/octet-stream';
    $data = @file_get_contents($tmpPath);
    if ($data === false || $data === '') {
        throw new RuntimeException('Unable to read uploaded file.');
    }

    $encoded = base64_encode($data);

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'tif', 'tiff'], true)) {
        if ($mime === 'image/jpg') {
            $mime = 'image/jpeg';
        }

        return [[
            'type' => 'input_image',
            'image_url' => 'data:' . $mime . ';base64,' . $encoded,
        ]];
    }

    if ($ext === 'pdf') {
        return [[
            'type' => 'input_pdf',
            'mime' => 'application/pdf',
            'data' => $encoded,
        ]];
    }

    if ($ext === 'docx') {
        return [[
            'type' => 'input_file',
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'data' => $encoded,
        ]];
    }

    throw new RuntimeException('Unsupported file type. Please use PDF, DOCX, JPG, JPEG, PNG, or WEBP.');
}

/**
 * Build OpenAI-style user content blocks (input_text / input_image) for one document.
 *
 * @return array<int, array<string, string>>
 */
function pcvc_docvision_build_document_content(
    string $tmpPath,
    string $originalName,
    array &$cleanup,
    int $maxScanPages = 4,
    int $scanDpi = 144
): array {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $mime = mime_content_type($tmpPath) ?: 'application/octet-stream';
    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'tif', 'tiff'], true);

    if ($isImage) {
        $imageData = @file_get_contents($tmpPath);
        if ($imageData === false) {
            throw new RuntimeException('Unable to read image data.');
        }

        return [[
            'type' => 'input_image',
            'image_url' => 'data:' . $mime . ';base64,' . base64_encode($imageData),
        ]];
    }

    if ($ext === 'docx') {
        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            throw new RuntimeException('Unable to read DOCX document.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!$xml) {
            throw new RuntimeException('DOCX document is empty.');
        }

        $text = trim((string)preg_replace('/\s+/u', ' ', strip_tags($xml)));
        if ($text === '') {
            throw new RuntimeException('DOCX text could not be extracted.');
        }

        return [[
            'type' => 'input_text',
            'text' => "Document text:\n" . mb_substr($text, 0, 18000, 'UTF-8'),
        ]];
    }

    if ($ext === 'pdf') {
        if (!pcvc_docvision_is_scanned_pdf($tmpPath)) {
            $text = pcvc_docvision_pdf_extract_text($tmpPath);
            if (mb_strlen($text, 'UTF-8') >= 80) {
                return [[
                    'type' => 'input_text',
                    'text' => "Document text:\n" . mb_substr($text, 0, 12000, 'UTF-8'),
                ]];
            }
        }

        if (pcvc_docvision_is_scanned_pdf($tmpPath)) {
            if (!class_exists('Imagick')) {
                $pdfData = @file_get_contents($tmpPath);
                if ($pdfData === false || $pdfData === '') {
                    throw new RuntimeException('Unable to read scanned PDF file.');
                }

                return [[
                    'type' => 'input_pdf',
                    'mime' => 'application/pdf',
                    'data' => base64_encode($pdfData),
                ]];
            }

            $scanDir = dirname($tmpPath) . '/scan_' . bin2hex(random_bytes(4)) . '/';
            $cleanup[] = rtrim($scanDir, '/');

            $pageLimit = min($maxScanPages, pcvc_docvision_max_scan_pages_for_name($originalName));
            try {
                $images = pcvc_docvision_scanned_pdf_to_images($tmpPath, $scanDir, $pageLimit, $scanDpi);
            } catch (Throwable $scanErr) {
                $pdfData = @file_get_contents($tmpPath);
                if ($pdfData === false || $pdfData === '') {
                    throw new RuntimeException(
                        'Scanned PDF could not be prepared: ' . $scanErr->getMessage()
                    );
                }

                return [[
                    'type' => 'input_pdf',
                    'mime' => 'application/pdf',
                    'data' => base64_encode($pdfData),
                ]];
            }

            $content = [];

            foreach ($images as $imagePath) {
                $cleanup[] = $imagePath;
                $imageData = @file_get_contents($imagePath);
                if ($imageData === false) {
                    continue;
                }
                $content[] = [
                    'type' => 'input_image',
                    'image_url' => 'data:image/jpeg;base64,' . base64_encode($imageData),
                ];
            }

            if (!$content) {
                $pdfData = @file_get_contents($tmpPath);
                if ($pdfData !== false && $pdfData !== '') {
                    return [[
                        'type' => 'input_pdf',
                        'mime' => 'application/pdf',
                        'data' => base64_encode($pdfData),
                    ]];
                }

                throw new RuntimeException('Scanned PDF pages could not be prepared for OCR.');
            }

            return $content;
        }

        $pdfData = @file_get_contents($tmpPath);
        if ($pdfData === false || $pdfData === '') {
            throw new RuntimeException('Unable to read PDF file.');
        }

        return [[
            'type' => 'input_pdf',
            'mime' => 'application/pdf',
            'data' => base64_encode($pdfData),
        ]];
    }

    throw new RuntimeException('Unsupported file type. Please use PDF, DOCX, JPG, JPEG, PNG, or WEBP.');
}

function pcvc_docvision_text_has_contact_signals(string $text): bool
{
    $text = trim($text);
    if ($text === '') {
        return false;
    }

    if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $text)) {
        return true;
    }

    if (preg_match('/\+\d{1,4}[\s\-.]?\d{2,4}[\s\-.]?\d{3,4}[\s\-.]?\d{3,6}/', $text)) {
        return true;
    }

    if (preg_match('/\b(?:tel|phone|mobile|cell|whatsapp)\s*[:\-]?\s*\+?\d{7,15}/i', $text)) {
        return true;
    }

    return false;
}

function pcvc_docvision_needs_vision(string $originalName, string $text): bool
{
    $hint = strtolower($originalName);
    $text = trim($text);
    $len = mb_strlen($text, 'UTF-8');

    if (preg_match('/\b(transcript|releve|relevé|academic|grade|diploma|degree|a0)\b/', $hint)) {
        return $len < 60;
    }

    if (preg_match('/\b(cv|resume|curriculum|vitae)\b/', $hint)) {
        return $len < 80 || !pcvc_docvision_text_has_contact_signals($text);
    }

    if (preg_match('/\b(passport|passeport)\b/', $hint)) {
        return $len < 100;
    }

    if (preg_match('/\b(birth[\s_-]?cert|naissance)\b/', $hint)) {
        return $len < 80;
    }

    return $len < 80;
}

function pcvc_docvision_fast_mode_enabled(): bool
{
    $flag = pcvc_env('DOCUMENT_AI_FAST_MODE');
    if ($flag === '0') {
        return false;
    }
    if ($flag === '1') {
        return true;
    }

    return pcvc_docvision_is_configured();
}

function pcvc_docvision_analysis_concurrency(): int
{
    $n = (int)pcvc_env('DOCUMENT_AI_CONCURRENCY');
    if ($n < 1) {
        return pcvc_docvision_fast_mode_enabled() ? 5 : 2;
    }

    return min(4, $n);
}

/** @return array<string, int|float|string> */
function pcvc_docvision_gemini_generation_config(float $temperature = 0): array
{
    return [
        'temperature' => $temperature,
        'responseMimeType' => 'application/json',
        'maxOutputTokens' => 2048,
    ];
}

function pcvc_docvision_guess_document_type_from_filename(string $originalName): string
{
    $hint = strtolower($originalName);
    if (preg_match('/\b(passport|passeport)\b/', $hint)) {
        return 'valid_passport';
    }
    if (preg_match('/\b(cv|resume|curriculum|vitae|biodata|bio[\s_-]?data|profile)\b/', $hint)) {
        return 'cv_resume';
    }
    if (preg_match('/\b(transcript|releve|relevé|academic|grade|diploma|degree)\b/', $hint)) {
        return 'degree_transcripts';
    }
    if (preg_match('/\b(high[\s_-]?school|lycee|lycée|baccalaureat|baccalauréat|secondary)\b/', $hint)) {
        return 'high_school_degree';
    }
    if (preg_match('/\b(birth[\s_-]?cert|naissance)\b/', $hint)) {
        return 'birth_certificate';
    }
    if (preg_match('/\b(ielts|toefl|english|anglais|proficien)\b/', $hint)) {
        return 'english_certificate';
    }
    if (preg_match('/\b(recommend|reference[\s_-]?letter)\b/', $hint)) {
        return 'recommendation_letters';
    }
    if (preg_match('/\b(motivation|personal[\s_-]?statement|cover[\s_-]?letter)\b/', $hint)) {
        return 'personal_statement';
    }
    if (preg_match('/\b(payment|receipt|invoice|proof|paid)\b/', $hint)) {
        return 'payment_proof';
    }

    return '';
}

/**
 * Regex/MRZ supplement when the model misses contact or identity fields.
 *
 * @param array<int, string> $nameHints
 */
function pcvc_docvision_supplement_fields_from_text(array $fields, string $text, array $nameHints = []): array
{
    $text = trim($text);
    if ($text === '') {
        return $fields;
    }

    if (empty($fields['email'])) {
        if (preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $text, $emails)) {
            $generic = [
                'info@', 'contact@', 'admin@', 'office@', 'admission@', 'admissions@',
                'support@', 'help@', 'registrar@', 'noreply@', 'no-reply@', 'webmaster@',
            ];
            $best = '';
            $bestScore = -1;
            $nameTokens = [];
            foreach ($nameHints as $hint) {
                $hint = strtolower(trim((string)$hint));
                if ($hint !== '' && strlen($hint) >= 3) {
                    $nameTokens[] = $hint;
                    foreach (preg_split('/\s+/', $hint) ?: [] as $part) {
                        if (strlen($part) >= 3) {
                            $nameTokens[] = $part;
                        }
                    }
                }
            }
            $nameTokens = array_values(array_unique($nameTokens));

            foreach ($emails[0] as $email) {
                $lower = strtolower(trim($email));
                $skip = false;
                foreach ($generic as $g) {
                    if (str_starts_with($lower, $g)) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) {
                    continue;
                }

                $score = 10;
                foreach ($nameTokens as $token) {
                    if (str_contains($lower, $token)) {
                        $score += 20;
                    }
                }
                if (preg_match('/@(gmail|yahoo|hotmail|outlook|icloud|live)\./', $lower)) {
                    $score += 5;
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $lower;
                }
            }

            if ($best !== '') {
                $fields['email'] = $best;
            }
        }
    }

    if (empty($fields['phone_international'])) {
        $patterns = [
            '/\+\d{1,4}[\s\-.]?\(?\d{1,4}\)?[\s\-.]?\d{2,4}[\s\-.]?\d{2,4}[\s\-.]?\d{2,6}/',
            '/\b00\d{8,14}\b/',
            '/\b(?:tel|phone|mobile|cell|whatsapp|mob)\s*[:\-]?\s*(\+?\d[\d\s().\-]{7,20})/i',
            '/\b(?:call|contact)\s*[:\-]?\s*(\+?\d[\d\s().\-]{7,20})/i',
        ];
        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $text, $m)) {
                continue;
            }
            $raw = trim($m[0]);
            if (isset($m[1])) {
                $raw = trim($m[1]);
            }
            $digits = preg_replace('/\D+/', '', $raw);
            if (!is_string($digits) || strlen($digits) < 8 || strlen($digits) > 15) {
                continue;
            }
            $fields['phone_international'] = str_starts_with(trim($raw), '+')
                ? preg_replace('/[^\d+]/', '', $raw)
                : ('+' . $digits);
            break;
        }
    }

    if (empty($fields['phone_international'])) {
        $digitsOnly = preg_replace('/\D+/', '', $text);
        if (is_string($digitsOnly) && preg_match('/(?:250)?(7[2389]\d{7})/', $digitsOnly, $rw)) {
            $fields['phone_international'] = '+250' . $rw[1];
        } elseif (preg_match('/\b0?(7[2389]\d{7})\b/', $text, $rwLocal)) {
            $fields['phone_international'] = '+250' . $rwLocal[1];
        } elseif (preg_match('/\b0?(7\d{8})\b/', $text, $keLocal)) {
            $fields['phone_international'] = '+254' . $keLocal[1];
        } elseif (preg_match('/\b(?:\+?1[\s\-.]?)?\(?(\d{3})\)?[\s\-.]?(\d{3})[\s\-.]?(\d{4})\b/', $text, $na)) {
            $fields['phone_international'] = '+1' . $na[1] . $na[2] . $na[3];
        }
    }

    if (empty($fields['passport_number'])) {
        $fields['passport_number'] = pcvc_docvision_extract_passport_number_from_text($text);
    }

    if (empty($fields['student_national_id']) && preg_match('/\b\d{16}\b/', $text, $nid)) {
        $fields['student_national_id'] = $nid[0];
    }

    if (empty($fields['address_line1']) && preg_match('/\b(?:address|residence|addr)\s*[:\-]\s*(.{8,120})/i', $text, $addr)) {
        $fields['address_line1'] = trim($addr[1]);
    }

    if (preg_match('/P\s*[<\[]\s*[A-Z]{3}([A-Z<]{2,})<<([A-Z<]{2,})/', strtoupper($text), $mrz)) {
        $last = trim(str_replace('<', ' ', $mrz[1]));
        $first = trim(str_replace('<', ' ', $mrz[2]));
        if (empty($fields['last_name']) && $last !== '') {
            $fields['last_name'] = $last;
        }
        if (empty($fields['first_name']) && $first !== '') {
            $fields['first_name'] = $first;
        }
    }

    return $fields;
}

/**
 * Parse passport / travel document number from OCR text or MRZ (ICAO TD3).
 */
function pcvc_docvision_extract_passport_number_from_text(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $upper = strtoupper($text);
    $compact = preg_replace('/\s+/', '', $upper) ?? $upper;

    // MRZ line 2: 9-char document number, check digit, then nationality (e.g. RWA)
    if (preg_match('/([A-Z0-9<]{9})<\d[A-Z]{3}\d{7}[MF<]/', $compact, $mrz)) {
        $candidate = strtoupper(rtrim(str_replace('<', '', $mrz[1]), '<'));
        if (pcvc_docvision_is_plausible_passport_number($candidate)) {
            return $candidate;
        }
    }

    // MRZ line 2 at start of a line (OCR often preserves line breaks)
    foreach (preg_split('/\R+/', $upper) ?: [] as $line) {
        $lineCompact = preg_replace('/\s+/', '', $line) ?? $line;
        if (preg_match('/^([A-Z0-9<]{9})<\d[A-Z]{3}\d{7}[MF<]/', $lineCompact, $lineMrz)) {
            $candidate = strtoupper(rtrim(str_replace('<', '', $lineMrz[1]), '<'));
            if (pcvc_docvision_is_plausible_passport_number($candidate)) {
                return $candidate;
            }
        }
    }

    // Rwanda format: PC1234567 followed by MRZ filler / nationality
    if (preg_match('/\b(PC\d{7})\b/i', $text, $rw)) {
        return strtoupper($rw[1]);
    }
    if (preg_match('/(PC\d{7})<\d?RWA/i', $compact, $rwMrz)) {
        return strtoupper($rwMrz[1]);
    }
    if (preg_match('/PC\s*(\d{7})\b/i', $text, $rwSpaced)) {
        return 'PC' . $rwSpaced[1];
    }

    // Labeled passport number fields (visual zone)
    $labelPatterns = [
        '/\b(?:passport|passeport|travel\s*document)\s*(?:no|number|num|#|n[o°]\s*)\s*[:\.\-]?\s*([A-Z0-9]{6,12})\b/i',
        '/\b(?:document|doc)\s*(?:no|number|#)\s*[:\.\-]?\s*([A-Z0-9]{6,12})\b/i',
        '/\bno\.?\s*du\s*passeport\s*[:\.\-]?\s*([A-Z0-9]{6,12})\b/i',
    ];
    foreach ($labelPatterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $candidate = strtoupper(preg_replace('/\s+/', '', $m[1]) ?? $m[1]);
            if (pcvc_docvision_is_plausible_passport_number($candidate)) {
                return $candidate;
            }
        }
    }

    // Generic ICAO-style numbers (exclude pure 16-digit national IDs)
    if (preg_match_all('/\b([A-Z]{1,3}\d{6,9})\b/', $upper, $all)) {
        foreach ($all[1] as $candidate) {
            if (pcvc_docvision_is_plausible_passport_number($candidate)) {
                return $candidate;
            }
        }
    }

    return '';
}

function pcvc_docvision_is_plausible_passport_number(string $value): bool
{
    $value = strtoupper(trim($value));
    if ($value === '' || strlen($value) < 6 || strlen($value) > 12) {
        return false;
    }
    if (ctype_digit($value) && strlen($value) >= 14) {
        return false;
    }
    if (!preg_match('/^[A-Z0-9]+$/', $value)) {
        return false;
    }
    if (preg_match('/^\d+$/', $value)) {
        return strlen($value) >= 6 && strlen($value) <= 10;
    }

    return (bool)preg_match('/[A-Z]/', $value) && (bool)preg_match('/\d/', $value);
}

/**
 * Laser-focused passport number extraction (MRZ + visual zone).
 *
 * @param array<int, array<string, string>> $userContent
 */
function pcvc_docvision_extract_passport_from_content(array $userContent): array
{
    $system = <<<'PROMPT'
You are reading a passport or travel document image/PDF.
Return JSON only: {"passport_number":""}

CRITICAL instructions:
1. Read the MRZ machine-readable zone (two lines of <<< at the bottom of the bio page).
2. On MRZ line 2, the FIRST 9 characters (before the first "<" check digit) are the passport/document number.
3. Also check the visual "Passport No" / "N° du passeport" field on the photo page.
4. Rwanda passports often look like PC1234567 (PC + 7 digits).
5. Return ONLY the document number — not the 16-digit national ID.
6. Empty string only if truly unreadable.
PROMPT;

    return pcvc_docvision_generate_json($system, $userContent, 2, 500);
}

/**
 * @param array<int, array<string, string>> $userContent
 */
function pcvc_docvision_extract_contact_from_content(array $userContent): array
{
    $system = <<<'PROMPT'
Extract the applicant's personal contact details from this document.
Return JSON only: {"email":"","phone_international":""}
Rules:
- phone_international must include country code when visible (+250, +1, +44, etc.)
- prefer the applicant's personal email (gmail/outlook) over university/office inboxes
- use empty string only when truly not visible
PROMPT;

    return pcvc_docvision_generate_json($system, $userContent, 1, 300);
}

/**
 * Focused identity/address extraction for passport, CV, or ID documents.
 *
 * @param array<int, array<string, string>> $userContent
 */
function pcvc_docvision_extract_identity_from_content(array $userContent): array
{
    $system = <<<'PROMPT'
Extract applicant identity and address from this document. Return JSON only:
{"passport_number":"","student_national_id":"","first_name":"","last_name":"","dob":"","gender":"","nationality":"","country_of_birth":"","city_of_birth":"","address_line1":"","address_line2":"","city":"","state_province":"","postal_code":""}
Rules: read MRZ and labeled fields carefully; passport_number is the travel document number; use empty string only if truly not visible.
PROMPT;

    return pcvc_docvision_generate_json($system, $userContent, 1, 400);
}

/**
 * Merge contact fields without overwriting existing non-empty values.
 */
function pcvc_docvision_merge_contact_fields(array $target, array $patch): array
{
    foreach (['email', 'area_code', 'phone_number', 'phone_international'] as $key) {
        if (!isset($patch[$key])) {
            continue;
        }
        $value = trim((string)$patch[$key]);
        if ($value === '') {
            continue;
        }
        if (empty($target[$key])) {
            $target[$key] = $value;
        }
    }

    return $target;
}

function pcvc_docvision_gather_source_text(string $tmpPath, string $originalName, string $clientText = ''): string
{
    $clientText = trim($clientText);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $serverText = '';

    if ($ext === 'pdf') {
        $serverText = pcvc_docvision_pdf_extract_text($tmpPath);
    } elseif ($ext === 'docx') {
        $zip = new ZipArchive();
        if ($zip->open($tmpPath) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            if ($xml) {
                $serverText = trim((string)preg_replace('/\s+/u', ' ', strip_tags($xml)));
            }
        }
    }

    if ($clientText !== '' && $serverText !== '') {
        return $clientText . "\n\n" . $serverText;
    }

    return $clientText !== '' ? $clientText : $serverText;
}

/**
 * Hybrid: reference text when available + document vision (PDF/images) for accuracy.
 *
 * @return array<int, array<string, string>>
 */
function pcvc_docvision_build_analysis_content(
    string $tmpPath,
    string $originalName,
    array &$cleanup,
    string $fileInstruction,
    string $clientText = '',
    int $maxScanPages = 3,
    int $scanDpi = 168
): array {
    $header = 'File name: ' . $originalName . "\n" . $fileInstruction;
    $clientText = trim($clientText);
    $pageLimit = min($maxScanPages, pcvc_docvision_max_scan_pages_for_name($originalName));

    $blocks = [['type' => 'input_text', 'text' => $header]];

    if ($clientText !== '') {
        $blocks[0]['text'] .= "\n\nReference text (OCR/layout may be imperfect — verify against the document images/PDF below):\n"
            . mb_substr($clientText, 0, 10000, 'UTF-8');
    }

    if (pcvc_docvision_needs_vision($originalName, $clientText)) {
        return array_merge(
            $blocks,
            pcvc_docvision_build_document_content($tmpPath, $originalName, $cleanup, $pageLimit, $scanDpi)
        );
    }

    $serverText = pcvc_docvision_gather_source_text($tmpPath, $originalName, '');
    if (mb_strlen($serverText, 'UTF-8') >= 80) {
        $blocks[0]['text'] .= "\n\nDocument text:\n" . mb_substr($serverText, 0, 12000, 'UTF-8');

        return $blocks;
    }

    return array_merge(
        $blocks,
        pcvc_docvision_build_document_content($tmpPath, $originalName, $cleanup, $pageLimit, $scanDpi)
    );
}

/**
 * @param array<int, array<string, string>> $openAiStyleContent
 * @return array<int, array<string, mixed>>
 */
function pcvc_docvision_content_to_gemini_parts(array $openAiStyleContent): array
{
    $parts = [];

    foreach ($openAiStyleContent as $block) {
        $type = (string)($block['type'] ?? '');

        if ($type === 'input_text') {
            $text = trim((string)($block['text'] ?? ''));
            if ($text !== '') {
                $parts[] = ['text' => $text];
            }
            continue;
        }

        if ($type === 'input_image') {
            $url = (string)($block['image_url'] ?? '');
            if (!preg_match('#^data:([^;]+);base64,(.+)$#', $url, $m)) {
                continue;
            }
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $m[1],
                    'data' => $m[2],
                ],
            ];
            continue;
        }

        if ($type === 'input_pdf' || $type === 'input_file') {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => (string)($block['mime'] ?? 'application/pdf'),
                    'data' => (string)($block['data'] ?? ''),
                ],
            ];
        }
    }

    return $parts;
}

function pcvc_docvision_decode_json(string $text): array
{
    $trimmed = trim($text);
    if ($trimmed === '') {
        return [];
    }

    $trimmed = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $trimmed);
    $decoded = json_decode((string)$trimmed, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * @param array<int, array<string, string>> $userContent OpenAI-style blocks
 */
function pcvc_docvision_generate_json(
    string $systemPrompt,
    array $userContent,
    int $maxRetries = 2,
    int $delayMs = 400,
    float $temperature = 0
): array {
    $apiKey = pcvc_docvision_api_key();
    if ($apiKey === '') {
        return ['error' => ['message' => 'GEMINI_API_KEY is not configured in .env.']];
    }

    $parts = pcvc_docvision_content_to_gemini_parts($userContent);
    if ($parts === []) {
        return ['error' => ['message' => 'No document content to analyze.']];
    }

    $body = [
        'systemInstruction' => [
            'parts' => [['text' => $systemPrompt]],
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => $parts,
            ],
        ],
        'generationConfig' => pcvc_docvision_gemini_generation_config($temperature),
    ];

    $url = pcvc_docvision_endpoint();

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, pcvc_docvision_curl_options() + [
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            if ($attempt < $maxRetries - 1) {
                usleep($delayMs * 1000);
                continue;
            }

            return ['error' => ['message' => $error]];
        }

        if ($httpCode === 0 || trim((string)$response) === '') {
            if ($attempt < $maxRetries - 1) {
                usleep($delayMs * 1000);
                continue;
            }

            return ['error' => ['message' => 'Gemini returned an empty response (connection timed out).']];
        }

        $data = json_decode((string)$response, true);
        if (!is_array($data)) {
            return ['error' => ['message' => 'Invalid response from Gemini.']];
        }

        if ($httpCode === 429 && $attempt < $maxRetries - 1) {
            usleep($delayMs * 1000 * ($attempt + 1));
            continue;
        }

        if (!empty($data['error'])) {
            $msg = (string)($data['error']['message'] ?? 'Gemini API error');
            if ($attempt < $maxRetries - 1 && ($httpCode >= 500 || $httpCode === 429)) {
                usleep($delayMs * 1000);
                continue;
            }

            return ['error' => ['message' => $msg]];
        }

        $text = '';
        if (!empty($data['candidates'][0]['content']['parts'])) {
            foreach ($data['candidates'][0]['content']['parts'] as $part) {
                if (!empty($part['text']) && is_string($part['text'])) {
                    $text .= $part['text'];
                }
            }
        }

        $text = trim($text);
        if ($text === '') {
            $blockReason = (string)($data['candidates'][0]['finishReason'] ?? 'unknown');
            return ['error' => ['message' => 'Gemini returned no text (finish: ' . $blockReason . ').']];
        }

        $json = pcvc_docvision_decode_json($text);

        return ['json' => $json, 'raw_text' => $text];
    }

    return ['error' => ['message' => 'AI extraction failed after retries.']];
}

/**
 * Parallel Gemini calls (one document per request — faster than sequential uploads).
 *
 * @param array<int, array{system: string, user: array<int, array<string, string>>}> $requests
 * @return array<int, array<string, mixed>>
 */
function pcvc_docvision_generate_json_multi(array $requests): array
{
    if ($requests === []) {
        return [];
    }

    $apiKey = pcvc_docvision_api_key();
    if ($apiKey === '') {
        $err = ['error' => ['message' => 'GEMINI_API_KEY is not configured in .env.']];
        return array_fill(0, count($requests), $err);
    }

    if (count($requests) === 1) {
        $i = array_key_first($requests);
        $r = $requests[$i];

        return [$i => pcvc_docvision_generate_json($r['system'], $r['user'])];
    }

    $url = pcvc_docvision_endpoint();
    $mh = curl_multi_init();
    if ($mh === false) {
        $out = [];
        foreach ($requests as $i => $r) {
            $out[$i] = pcvc_docvision_generate_json($r['system'], $r['user']);
        }

        return $out;
    }

    $handles = [];
    $encoded = [];

    foreach ($requests as $i => $request) {
        $parts = pcvc_docvision_content_to_gemini_parts($request['user']);
        $body = [
            'systemInstruction' => [
                'parts' => [['text' => $request['system']]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => $parts],
            ],
            'generationConfig' => pcvc_docvision_gemini_generation_config(0),
        ];
        $encoded[$i] = json_encode($body, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        if ($ch === false) {
            continue;
        }
        curl_setopt_array($ch, pcvc_docvision_curl_options() + [
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encoded[$i],
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }

    $running = null;
    do {
        $mrc = curl_multi_exec($mh, $running);
        if ($mrc === CURLM_OK && $running > 0) {
            curl_multi_select($mh, 0.12);
        }
    } while ($running > 0);

    $results = [];
    foreach ($handles as $i => $ch) {
        $errno = curl_errno($ch);
        $cerr = curl_error($ch);
        $raw = curl_multi_getcontent($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        if ($errno !== 0) {
            $results[$i] = ['error' => ['message' => $cerr !== '' ? $cerr : ('curl error ' . $errno)]];
            continue;
        }

        $data = json_decode((string)$raw, true);
        if (!is_array($data) || $httpCode >= 400 || !empty($data['error'])) {
            $msg = is_array($data) ? (string)($data['error']['message'] ?? "HTTP {$httpCode}") : "HTTP {$httpCode}";
            $results[$i] = ['error' => ['message' => $msg]];
            continue;
        }

        $text = '';
        if (!empty($data['candidates'][0]['content']['parts'])) {
            foreach ($data['candidates'][0]['content']['parts'] as $part) {
                if (!empty($part['text'])) {
                    $text .= $part['text'];
                }
            }
        }

        $results[$i] = [
            'json' => pcvc_docvision_decode_json(trim($text)),
            'raw_text' => trim($text),
            'provider' => 'gemini',
        ];
    }

    curl_multi_close($mh);

    $n = count($requests);
    for ($i = 0; $i < $n; $i++) {
        if (!isset($results[$i])) {
            $results[$i] = ['error' => ['message' => 'Request was not executed']];
        }
    }

    return $results;
}

/**
 * Vision extract from a single image data URL (transcript OCR, etc.).
 */
function pcvc_docvision_image_data_url_json(
    string $dataUrl,
    string $systemPrompt,
    string $userPrompt,
    int $maxRetries = 2
): array {
    return pcvc_docvision_generate_json(
        $systemPrompt,
        [
            ['type' => 'input_text', 'text' => $userPrompt],
            ['type' => 'input_image', 'image_url' => $dataUrl],
        ],
        $maxRetries
    );
}
