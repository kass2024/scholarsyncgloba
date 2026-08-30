<?php
declare(strict_types=1);

/**
 * Local document autofill: OCR + heuristics (no OpenAI).
 * Used by student / credit transfer / master loan smart autofill.
 */

function lda_shell_run(string $cmd): ?string
{
    if (function_exists('proc_open')) {
        $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open($cmd, $desc, $pipes);
        if (is_resource($proc)) {
            $out = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            if (is_string($out) && trim($out) !== '') {
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

function lda_tesseract_binaries(): array
{
    $bins = [];
    $custom = trim((string)(getenv('AUTOFILL_TESSERACT_BIN') ?: ''));
    if ($custom !== '') {
        $bins[] = $custom;
    }
    $bins[] = 'tesseract';
    if (DIRECTORY_SEPARATOR === '\\') {
        $bins[] = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
        $bins[] = 'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe';
    } else {
        $bins[] = '/usr/bin/tesseract';
        $bins[] = '/usr/local/bin/tesseract';
    }
    return $bins;
}

function lda_run_tesseract(string $imagePath, string $lang = 'eng'): ?string
{
    $stderr = DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null';
    foreach (lda_tesseract_binaries() as $bin) {
        $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($imagePath) . ' stdout -l '
            . escapeshellarg($lang) . ' ' . $stderr;
        $out = lda_shell_run($cmd);
        if (is_string($out) && trim($out) !== '') {
            return $out;
        }
    }
    return null;
}

function lda_pdftotext_binaries(): array
{
    $bins = [];
    $custom = trim((string)(getenv('AUTOFILL_PDFTOTEXT_BIN') ?: ''));
    if ($custom !== '') {
        $bins[] = $custom;
    }
    $bins[] = 'pdftotext';
    if (DIRECTORY_SEPARATOR === '\\') {
        $bins[] = 'C:\\Program Files\\xpdf\\bin64\\pdftotext.exe';
        $bins[] = 'C:\\Program Files (x86)\\xpdf\\bin\\pdftotext.exe';
    } else {
        $bins[] = '/usr/bin/pdftotext';
        $bins[] = '/usr/local/bin/pdftotext';
    }
    return $bins;
}

function lda_run_pdftotext(string $pdfPath): ?string
{
    $stderr = DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null';
    foreach (lda_pdftotext_binaries() as $bin) {
        $cmd = escapeshellarg($bin) . ' -layout -enc UTF-8 -nopgbrk '
            . escapeshellarg($pdfPath) . ' - ' . $stderr;
        $out = lda_shell_run($cmd);
        if (is_string($out) && trim($out) !== '') {
            return $out;
        }
    }
    return null;
}

function lda_pdf_text_fallback(string $pdfPath): string
{
    $content = @file_get_contents($pdfPath);
    if ($content === false || $content === '') {
        return '';
    }
    if (preg_match_all('/[\x20-\x7E\xC0-\xFF]{4,}/u', $content, $matches)) {
        return implode("\n", $matches[0]);
    }
    return '';
}

function lda_is_scanned_pdf(string $pdfPath): bool
{
    $sample = @file_get_contents($pdfPath, false, null, 0, 5000);
    if ($sample === false || $sample === '') {
        return true;
    }
    return !preg_match('/[A-Za-z]{4,}/', $sample);
}

function lda_scanned_pdf_to_images(string $pdfPath, string $outDir, int $maxPages = 3): array
{
    if (!class_exists('Imagick')) {
        throw new RuntimeException('Scanned PDF support requires Imagick on the server.');
    }

    $images = [];
    $im = new Imagick();
    $im->setResolution(200, 200);
    $im->readImage($pdfPath);

    $pageNum = 0;
    foreach ($im as $i => $page) {
        if ($pageNum >= $maxPages) {
            break;
        }
        $page->setImageFormat('jpeg');
        $page->setImageCompressionQuality(88);
        $out = $outDir . 'page_' . ($i + 1) . '_' . bin2hex(random_bytes(3)) . '.jpg';
        $page->writeImage($out);
        $images[] = $out;
        $pageNum++;
    }

    $im->clear();
    $im->destroy();

    return $images;
}

function lda_extract_docx_text(string $tmpPath): string
{
    $zip = new ZipArchive();
    if ($zip->open($tmpPath) !== true) {
        throw new RuntimeException('Unable to read DOCX document.');
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if (!$xml) {
        throw new RuntimeException('DOCX document is empty.');
    }
    $text = preg_replace('/\s+/u', ' ', strip_tags($xml));
    $text = trim((string)$text);
    if ($text === '') {
        throw new RuntimeException('DOCX text could not be extracted.');
    }
    return mb_substr($text, 0, 18000, 'UTF-8');
}

function lda_normalize_extracted_text(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') {
        return '';
    }

    $lines = explode("\n", $text);
    $clean = [];
    foreach ($lines as $line) {
        $line = trim(preg_replace('/[ \t]+/u', ' ', $line));
        if ($line !== '') {
            $clean[] = $line;
        }
    }

    return implode("\n", $clean);
}

function lda_compact_text(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string)$text);
}

/**
 * Server-side text extraction without external binaries (ZipArchive + PDF regex only).
 * Images and scanned PDFs rely on browser OCR (document_text[] POST field).
 */
function lda_extract_document_text_php_only(string $tmpPath, string $originalName): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'tif', 'tiff'], true)) {
        throw new RuntimeException(
            'This image must be read in your browser. Enable JavaScript, then run Start analysis again.'
        );
    }

    if ($ext === 'docx') {
        return lda_normalize_extracted_text(lda_extract_docx_text($tmpPath));
    }

    if ($ext === 'pdf') {
        $text = lda_normalize_extracted_text(lda_pdf_text_fallback($tmpPath));
        if ($text !== '') {
            return $text;
        }
        throw new RuntimeException(
            'This PDF has little embedded text. Enable JavaScript so your browser can OCR scanned pages.'
        );
    }

    throw new RuntimeException('Unsupported file type. Please use PDF, DOCX, JPG, JPEG, PNG, or WEBP.');
}

/** @return array<int, string> */
function lda_post_document_texts(): array
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

function lda_text_blob(string $text, string $fileName): string
{
    return strtolower($text . ' ' . $fileName);
}

function lda_filename_type_hint(string $fileName): ?string
{
    $hint = strtolower($fileName);
    if (preg_match('/\b(passport|passeport)\b/', $hint)) {
        return 'valid_passport';
    }
    if (preg_match('/\b(cv|resume|curriculum)\b/', $hint)) {
        return 'cv_resume';
    }
    if (preg_match('/\b(transcript|releve|relevé|academic|grade)\b/', $hint)) {
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
    return null;
}

function lda_score_document_types(string $blob, ?string $filenameHint): array
{
    $scores = [
        'valid_passport' => 0.0,
        'degree_transcripts' => 0.0,
        'high_school_degree' => 0.0,
        'cv_resume' => 0.0,
        'recommendation_letters' => 0.0,
        'personal_statement' => 0.0,
        'english_certificate' => 0.0,
        'birth_certificate' => 0.0,
        'payment_proof' => 0.0,
    ];

    if (preg_match('/\bP<[A-Z]{3}[A-Z<]{2,}/', $blob)) {
        $scores['valid_passport'] += 8;
    }
    if (preg_match('/\b(passport|passeport|travel document|document de voyage)\b/', $blob)) {
        $scores['valid_passport'] += 4;
    }
    if (preg_match('/\b(surname|given name|nom|prénom|date of birth|lieu de naissance|nationality|nationalité)\b/', $blob)) {
        $scores['valid_passport'] += 2;
    }

    if (preg_match('/\b(birth certificate|certificat de naissance|acte de naissance)\b/', $blob)) {
        $scores['birth_certificate'] += 6;
    }
    if (preg_match('/\b(father|mother|père|mère|parent)\b/', $blob) && preg_match('/\b(born|birth|naissance)\b/', $blob)) {
        $scores['birth_certificate'] += 2;
    }

    if (preg_match('/\b(curriculum vitae|curriculum|resume|résumé|work experience|employment history|professional experience)\b/', $blob)) {
        $scores['cv_resume'] += 5;
    }
    if (preg_match('/\b(skills|linkedin|objective|profile)\b/', $blob)) {
        $scores['cv_resume'] += 2;
    }

    if (preg_match('/\b(transcript|relevé de notes|releve de notes|academic record|grade report|gpa|credits|semester|matriculation)\b/', $blob)) {
        $scores['degree_transcripts'] += 5;
    }
    if (preg_match('/\b(university|college|faculty|institute|école|school of)\b/', $blob) && preg_match('/\b(course|module|programme|program)\b/', $blob)) {
        $scores['degree_transcripts'] += 2;
    }

    if (preg_match('/\b(high school|secondary school|lycée|lycee|baccalaureate|baccalauréat|o-level|a-level)\b/', $blob)) {
        $scores['high_school_degree'] += 5;
    }

    if (preg_match('/\b(ielts|toefl|cefr|cambridge english|english proficiency|language proficiency|band score|overall score)\b/', $blob)) {
        $scores['english_certificate'] += 6;
    }

    if (preg_match('/\b(recommendation|reference letter|letter of recommendation|dear admissions|to whom it may concern)\b/', $blob)) {
        $scores['recommendation_letters'] += 5;
    }

    if (preg_match('/\b(personal statement|motivation letter|statement of purpose|why i (want|wish)|career goal)\b/', $blob)) {
        $scores['personal_statement'] += 5;
    }

    if (preg_match('/\b(payment receipt|proof of payment|transaction|invoice|amount paid|bank transfer|paid to|payment confirmation)\b/', $blob)) {
        $scores['payment_proof'] += 5;
    }

    if ($filenameHint !== null && isset($scores[$filenameHint])) {
        $scores[$filenameHint] += 4;
    }

    return $scores;
}

function lda_pick_document_type(array $scores): array
{
    arsort($scores, SORT_NUMERIC);
    $types = array_keys($scores);
    $top = $types[0] ?? 'unknown';
    $topScore = (float)($scores[$top] ?? 0);
    $secondScore = isset($types[1]) ? (float)($scores[$types[1]] ?? 0) : 0.0;

    if ($topScore < 2.5) {
        return ['document_type' => 'unknown', 'confidence' => 0.2, 'summary' => 'Document type could not be determined from extracted text.'];
    }

    $margin = max(0.0, $topScore - $secondScore);
    $confidence = min(0.92, 0.45 + ($topScore * 0.04) + ($margin * 0.05));

    $labels = [
        'valid_passport' => 'Passport identity document',
        'birth_certificate' => 'Birth certificate',
        'cv_resume' => 'CV / resume',
        'degree_transcripts' => 'Academic transcript or degree record',
        'high_school_degree' => 'High school certificate',
        'english_certificate' => 'English proficiency certificate',
        'recommendation_letters' => 'Recommendation letter',
        'personal_statement' => 'Personal or motivation statement',
        'payment_proof' => 'Payment or application fee proof',
    ];

    return [
        'document_type' => $top,
        'confidence' => round($confidence, 2),
        'summary' => $labels[$top] ?? 'Recognized supporting document',
    ];
}

function lda_clean_name(?string $value): string
{
    $value = trim((string)$value);
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = preg_replace('/[^A-Za-zÀ-ÿ\'\-\s]/u', '', $value);
    return trim((string)$value);
}

function lda_title_case_person_name(string $value): string
{
    $value = lda_clean_name($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('/^[A-Z\s\'\-]+$/u', $value)) {
        return $value;
    }

    return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

function lda_sanitize_person_name(?string $value): string
{
    $value = lda_clean_name($value);
    if ($value === '') {
        return '';
    }

    if (mb_strlen($value, 'UTF-8') < 2 || mb_strlen($value, 'UTF-8') > 60) {
        return '';
    }

    if (!preg_match("/^[\p{L} .'-]+$/u", $value)) {
        return '';
    }

    if (preg_match_all('/\p{L}/u', $value, $letters) < 2) {
        return '';
    }

    if (preg_match('/^[A-Z]{2,}(?:[\s\'\-][A-Z]{2,}){1,5}$/u', $value)) {
        $value = lda_title_case_person_name($value);
    }

    $lower = mb_strtolower($value, 'UTF-8');
    $blocked = [
        'passport', 'republic of', 'republic', 'nationality', 'surname', 'given names', 'given name',
        'date of birth', 'male', 'female', 'document', 'travel document', 'minister', 'authority',
        'place of birth', 'code', 'type', 'sex', 'country', 'valid until', 'page',
    ];
    foreach ($blocked as $word) {
        if ($lower === $word || str_starts_with($lower, $word . ' ')) {
            return '';
        }
    }

    return $value;
}

function lda_is_plausible_date(string $date): bool
{
    if ($date === '' || $date === '0000-00-00') {
        return false;
    }
    return $date >= '1900-01-01' && $date <= date('Y-m-d', strtotime('+1 day'));
}

function lda_extract_email(string $text): string
{
    if (!preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $text, $matches)) {
        return '';
    }
    $generic = ['info@', 'contact@', 'admin@', 'office@', 'admission@', 'admissions@', 'support@', 'help@', 'registrar@', 'noreply@'];
    $best = '';
    foreach ($matches[0] as $email) {
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
        if ($best === '' || strlen($lower) < strlen($best)) {
            $best = $lower;
        }
    }
    return $best;
}

function lda_extract_phone(string $text): string
{
    $candidates = [];
    $patterns = [
        '/\+\d{1,4}[\s\-.]?\d{2,4}[\s\-.]?\d{3,4}[\s\-.]?\d{3,6}/',
        '/\b00\d{8,14}\b/',
        '/\b\d{3}[\s\-.]?\d{3}[\s\-.]?\d{3,4}[\s\-.]?\d{3,4}\b/',
        '/\b\d{9,15}\b/',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $text, $all)) {
            continue;
        }
        foreach ($all[0] as $raw) {
            $digits = preg_replace('/\D+/', '', $raw);
            if (!is_string($digits) || strlen($digits) < 8 || strlen($digits) > 15) {
                continue;
            }
            if (preg_match('/^(\d)\1{5,}$/', $digits)) {
                continue;
            }
            $candidates[] = ['raw' => trim($raw), 'digits' => $digits];
        }
    }

    if (!$candidates) {
        return '';
    }

    usort($candidates, static function (array $a, array $b): int {
        $aScore = (str_starts_with($a['raw'], '+') ? 10 : 0) + strlen($a['digits']);
        $bScore = (str_starts_with($b['raw'], '+') ? 10 : 0) + strlen($b['digits']);
        return $bScore <=> $aScore;
    });

    $pick = $candidates[0]['raw'];
    if (!str_starts_with($pick, '+')) {
        $pick = '+' . $candidates[0]['digits'];
    }

    return $pick;
}

function lda_extract_dates(string $text): array
{
    $dates = [];
    if (preg_match_all('/\b(\d{1,2}[\/. \-]\d{1,2}[\/. \-]\d{2,4}|\d{4}[\/. \-]\d{1,2}[\/. \-]\d{1,2}|\d{2}[\/. \-]\d{2}[\/. \-]\d{4})\b/', $text, $all)) {
        foreach ($all[1] as $raw) {
            $ts = strtotime($raw);
            if ($ts === false) {
                continue;
            }
            $date = date('Y-m-d', $ts);
            if (lda_is_plausible_date($date)) {
                $dates[] = $date;
            }
        }
    }
    return array_values(array_unique($dates));
}

function lda_extract_labeled_value(string $text, array $labels): string
{
    foreach ($labels as $label) {
        $pattern = '/(?:' . preg_quote($label, '/') . ')\s*[:\-]?\s*([^\n\r,;]{2,80})/iu';
        if (preg_match($pattern, $text, $m)) {
            return trim($m[1]);
        }
    }
    return '';
}

/**
 * Passport/CV labels often appear on one line and the value on the next (visual zone).
 */
function lda_extract_labeled_value_multiline(string $text, array $labels): string
{
    $lines = preg_split('/\r?\n/', $text) ?: [];

    foreach ($lines as $index => $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        foreach ($labels as $label) {
            $quoted = preg_quote($label, '/');
            if (preg_match('/^' . $quoted . '\s*[:\-]?\s*(.+)$/iu', $line, $match)) {
                $value = trim($match[1]);
                if ($value !== '' && mb_strlen($value, 'UTF-8') <= 80) {
                    return $value;
                }
            }

            if (preg_match('/^' . $quoted . '\s*$/iu', $line) && isset($lines[$index + 1])) {
                $next = trim((string)$lines[$index + 1]);
                if (
                    $next !== ''
                    && mb_strlen($next, 'UTF-8') <= 80
                    && !preg_match('/[@\d]{4,}/', $next)
                    && !preg_match('/^(?:date|sex|gender|nationality|passport|document)\b/i', $next)
                ) {
                    return $next;
                }
            }
        }
    }

    return '';
}

function lda_mrz_tokens_to_name(string $raw): string
{
    $raw = strtoupper(trim($raw));
    $raw = str_replace(['«', '‹', '>'], ['<', '<', '<'], $raw);
    $raw = preg_replace('/\s+/', ' ', str_replace('<', ' ', $raw)) ?? $raw;
    return lda_sanitize_person_name(lda_title_case_person_name($raw));
}

function lda_parse_mrz(string $text): array
{
    $fields = [];
    $blobs = [];
    $upper = strtoupper($text);
    $blobs[] = preg_replace('/\s+/', '', $upper) ?? $upper;

    foreach (preg_split('/\r?\n/', $upper) ?: [] as $line) {
        $line = trim($line);
        if (substr_count($line, '<') >= 2 || preg_match('/P\s*[<\[]/', $line)) {
            $blobs[] = preg_replace('/\s+/', '', $line) ?? $line;
        }
    }

    $parsed = [];
    foreach ($blobs as $blob) {
        $blob = str_replace(['«', '‹', '>', ' '], ['<', '<', '<', ''], $blob);

        if (preg_match('/P\s*[<\[]\s*[A-Z]{3}([A-Z<]{2,})<<([A-Z<]{2,})/', $blob, $match)) {
            $parsed[] = [$match[1], $match[2]];
        }
        if (preg_match('/P<([A-Z]{3})([A-Z<]+)<<([A-Z<]+)/', $blob, $match)) {
            $parsed[] = [$match[2], $match[3]];
        }
        if (preg_match('/([A-Z]{2,})<<([A-Z<]{2,})/', $blob, $match) && strlen($match[1]) >= 2) {
            $parsed[] = [$match[1], $match[2]];
        }
    }

    foreach ($parsed as [$surnameRaw, $givenRaw]) {
        $last = lda_mrz_tokens_to_name($surnameRaw);
        $first = lda_mrz_tokens_to_name($givenRaw);
        if ($last !== '' && $first !== '') {
            $fields['last_name'] = $last;
            $fields['first_name'] = $first;
            break;
        }
    }

    return $fields;
}

function lda_extract_passport_visual_zone(string $text): array
{
    $fields = [];

    $surname = lda_extract_labeled_value_multiline($text, [
        'Surname', 'Last name', 'Family name', 'Nom', 'Apellidos', '1. Surname',
    ]);
    if ($surname !== '') {
        $fields['last_name'] = lda_sanitize_person_name($surname);
    }

    $given = lda_extract_labeled_value_multiline($text, [
        'Given names', 'Given name', 'Forename', 'First name', 'Prénoms', 'Prenoms', 'Prenom',
        'Other names', '2. Given names',
    ]);
    if ($given !== '') {
        $fields['first_name'] = lda_sanitize_person_name($given);
    }

    return $fields;
}

function lda_extract_cv_identity_names(string $text): array
{
    $fields = [];
    $lines = preg_split('/\r?\n/', $text) ?: [];
    $scan = array_slice($lines, 0, 24);

    foreach ($scan as $line) {
        $line = trim($line);
        if ($line === '' || mb_strlen($line, 'UTF-8') > 70) {
            continue;
        }

        if (preg_match('/^(?:name|full\s*name|applicant)\s*[:\-]\s*(.+)$/i', $line, $label)) {
            $name = lda_sanitize_person_name($label[1]);
            if ($name !== '') {
                $parts = preg_split('/\s+/', $name) ?: [];
                if (count($parts) >= 2) {
                    $fields['first_name'] = $parts[0];
                    $fields['last_name'] = implode(' ', array_slice($parts, 1));
                    return $fields;
                }
            }
        }
    }

    foreach ($scan as $line) {
        $line = trim($line);
        if ($line === '' || mb_strlen($line, 'UTF-8') > 70) {
            continue;
        }
        if (preg_match('/@|https?:|linkedin|curriculum|resume|vitae|objective|experience|education|phone|e-mail|\d{5,}/i', $line)) {
            continue;
        }

        if (preg_match('/^[A-Z][a-z]+(?:[\s\'\-][A-Z][a-z\']+){1,4}$/u', $line, $match)) {
            $parts = preg_split('/\s+/', $match[0]) ?: [];
            if (count($parts) >= 2) {
                $fields['first_name'] = lda_sanitize_person_name($parts[0]);
                $fields['last_name'] = lda_sanitize_person_name(implode(' ', array_slice($parts, 1)));
                return $fields;
            }
        }

        if (preg_match('/^[A-Z]{2,}(?:[\s\'\-][A-Z]{2,}){1,4}$/u', $line)) {
            $full = lda_sanitize_person_name(lda_title_case_person_name($line));
            if ($full !== '') {
                $parts = preg_split('/\s+/', $full) ?: [];
                if (count($parts) >= 2) {
                    $fields['first_name'] = $parts[0];
                    $fields['last_name'] = implode(' ', array_slice($parts, 1));
                    return $fields;
                }
            }
        }
    }

    return $fields;
}

function lda_finalize_applicant_names(array $fields): array
{
    foreach (['first_name', 'last_name'] as $key) {
        if (empty($fields[$key])) {
            continue;
        }
        $clean = lda_sanitize_person_name((string)$fields[$key]);
        if ($clean === '') {
            unset($fields[$key]);
        } else {
            $fields[$key] = $clean;
        }
    }

    return $fields;
}

function lda_applicant_names_complete(array $fields): bool
{
    $first = lda_sanitize_person_name($fields['first_name'] ?? '');
    $last = lda_sanitize_person_name($fields['last_name'] ?? '');

    return $first !== '' && $last !== '';
}

function lda_extract_names_from_lines(string $text, string $documentType): array
{
    $fields = [];
    $lines = preg_split('/\r?\n/', $text) ?: [];

    foreach ($lines as $idx => $line) {
        $line = trim($line);
        if ($line === '' || strlen($line) > 90) {
            continue;
        }

        if (preg_match('/^(?:name|full\s*name|applicant|candidate)\s*[:\-]\s*(.+)$/i', $line, $label)) {
            $name = lda_sanitize_person_name($label[1]);
            if ($name !== '') {
                $parts = preg_split('/\s+/', $name) ?: [];
                if (count($parts) >= 2) {
                    $fields['first_name'] = $parts[0];
                    $fields['last_name'] = implode(' ', array_slice($parts, 1));
                }
            }
            continue;
        }

        if (preg_match('/^(?:surname|last\s*name|family\s*name|nom)\s*[:\-]\s*(.+)$/i', $line, $label)) {
            $fields['last_name'] = lda_sanitize_person_name($label[1]);
            continue;
        }

        if (preg_match('/^(?:given\s*names?|first\s*name|forename|pr[eé]nom)\s*[:\-]\s*(.+)$/i', $line, $label)) {
            $fields['first_name'] = lda_sanitize_person_name($label[1]);
            continue;
        }

        if (preg_match('/@/', $line) || preg_match('/\d{5,}/', $line)) {
            continue;
        }

        if (preg_match('/^[A-Z]{2,}(?:[\s\'\-][A-Z]{2,}){1,5}$/u', $line)) {
            $full = lda_sanitize_person_name($line);
            if ($full !== '') {
                $parts = preg_split('/\s+/', $full) ?: [];
                if (count($parts) >= 2) {
                    $fields['first_name'] = $parts[0];
                    $fields['last_name'] = implode(' ', array_slice($parts, 1));
                    break;
                }
            }
        }

        if ($documentType === 'cv_resume' && preg_match('/^([A-Z][a-z]+(?:\s+[A-Z][a-z\']+){1,4})$/', $line, $nm)) {
            $parts = preg_split('/\s+/', $nm[1]) ?: [];
            if (count($parts) >= 2) {
                $fields['first_name'] = lda_sanitize_person_name($parts[0]);
                $fields['last_name'] = lda_sanitize_person_name(implode(' ', array_slice($parts, 1)));
                break;
            }
        }
    }

    return $fields;
}

function lda_extract_fields(string $text, string $documentType, string $fileName): array
{
    $fields = [];
    $mrz = lda_parse_mrz($text);
    foreach ($mrz as $k => $v) {
        if ($v !== '') {
            $fields[$k] = $v;
        }
    }

    $email = lda_extract_email($text);
    if ($email !== '') {
        $fields['email'] = $email;
    }

    $phone = lda_extract_phone($text);
    if ($phone !== '') {
        $fields['phone_international'] = $phone;
    }

    if ($documentType === 'valid_passport' || $documentType === 'birth_certificate') {
        foreach (lda_extract_passport_visual_zone($text) as $key => $value) {
            if ($value !== '' && empty($fields[$key])) {
                $fields[$key] = $value;
            }
        }
    }

    if ($documentType === 'cv_resume') {
        foreach (lda_extract_cv_identity_names($text) as $key => $value) {
            if ($value !== '' && empty($fields[$key])) {
                $fields[$key] = $value;
            }
        }
    }

    if (empty($fields['first_name'])) {
        $v = lda_extract_labeled_value_multiline($text, ['Given names', 'Given name', 'First name', 'Prénom', 'Prenom', 'Forename']);
        if ($v === '') {
            $v = lda_extract_labeled_value($text, ['Given names', 'Given name', 'First name', 'Prénom', 'Prenom', 'Forename']);
        }
        if ($v !== '') {
            $fields['first_name'] = lda_sanitize_person_name($v);
        }
    }
    if (empty($fields['last_name'])) {
        $v = lda_extract_labeled_value_multiline($text, ['Surname', 'Last name', 'Family name', 'Nom']);
        if ($v === '') {
            $v = lda_extract_labeled_value($text, ['Surname', 'Last name', 'Family name', 'Nom']);
        }
        if ($v !== '') {
            $fields['last_name'] = lda_sanitize_person_name($v);
        }
    }

    if (!empty($fields['first_name'])) {
        $fields['first_name'] = lda_sanitize_person_name($fields['first_name']);
        if ($fields['first_name'] === '') {
            unset($fields['first_name']);
        }
    }
    if (!empty($fields['last_name'])) {
        $fields['last_name'] = lda_sanitize_person_name($fields['last_name']);
        if ($fields['last_name'] === '') {
            unset($fields['last_name']);
        }
    }

    if (empty($fields['passport_number'])) {
        $v = lda_extract_labeled_value($text, ['Passport No', 'Passport number', 'Passport #', 'No du passeport', 'Document No']);
        if ($v === '' && preg_match('/\b[A-Z]{1,2}\d{6,9}\b/', $text, $pm)) {
            $v = $pm[0];
        }
        if ($v !== '') {
            $fields['passport_number'] = strtoupper(preg_replace('/\s+/', '', $v));
        }
    }

    $lineNames = lda_extract_names_from_lines($text, $documentType);
    foreach ($lineNames as $k => $v) {
        if ($v !== '' && empty($fields[$k])) {
            $fields[$k] = $v;
        }
    }

    $dob = lda_extract_labeled_value($text, ['Date of birth', 'Birth date', 'DOB', 'Date de naissance', 'Born on', 'Né le', 'Ne le']);
    if ($dob !== '') {
        $ts = strtotime($dob);
        if ($ts !== false) {
            $date = date('Y-m-d', $ts);
            if (lda_is_plausible_date($date)) {
                $fields['dob'] = $date;
            }
        }
    } elseif ($documentType === 'valid_passport' || $documentType === 'birth_certificate') {
        $dates = lda_extract_dates($text);
        if (!empty($dates[0])) {
            $fields['dob'] = $dates[0];
        }
    }

    $gender = lda_extract_labeled_value($text, ['Sex', 'Gender', 'Sexe']);
    if ($gender !== '') {
        $fields['gender'] = $gender;
    } elseif (preg_match('/\b(?:sex|gender|sexe)\s*[:\-]?\s*(M|F|male|female|homme|femme)\b/i', $text, $gm)) {
        $fields['gender'] = $gm[1];
    }

    foreach ([
        'nationality' => ['Nationality', 'Nationalité', 'Nationalite', 'Citizen of'],
        'country_of_birth' => ['Country of birth', 'Place of birth', 'Pays de naissance', 'Lieu de naissance'],
        'city_of_birth' => ['City of birth', 'Town of birth', 'Ville de naissance'],
        'address_line1' => ['Address', 'Residential address', 'Adresse', 'Street'],
        'city' => ['City', 'Town', 'Ville'],
        'state_province' => ['State', 'Province', 'Region', 'County'],
        'postal_code' => ['Postal code', 'Post code', 'ZIP', 'Code postal'],
        'previous_institution_name' => ['University', 'College', 'Institution', 'School', 'Faculty', 'Établissement'],
        'previous_institution_city' => ['Institution city', 'Campus city'],
        'previous_institution_country' => ['Institution country', 'Country of study'],
        'father_first_name' => ['Father first name', 'Father\'s first name', 'Père prénom'],
        'father_last_name' => ['Father last name', 'Father\'s surname', 'Père nom'],
        'mother_first_name' => ['Mother first name', 'Mother\'s first name', 'Mère prénom'],
        'mother_last_name' => ['Mother last name', 'Mother\'s surname', 'Mère nom'],
    ] as $field => $labels) {
        if (empty($fields[$field])) {
            $v = lda_extract_labeled_value($text, $labels);
            if ($v !== '') {
                $fields[$field] = trim($v);
            }
        }
    }

    if ($documentType === 'degree_transcripts' || $documentType === 'high_school_degree') {
        $dates = lda_extract_dates($text);
        if (!empty($dates[0]) && empty($fields['previous_study_start'])) {
            $fields['previous_study_start'] = $dates[0];
        }
        if (!empty($dates[1]) && empty($fields['previous_study_graduation'])) {
            $fields['previous_study_graduation'] = $dates[count($dates) - 1];
        }
        if (preg_match('/\b(english|french|français|francais)\b/i', $text, $lm)) {
            $fields['language_of_instruction'] = $lm[1];
        }
    }

    return lda_finalize_applicant_names($fields);
}

/**
 * Analyze pre-extracted document text. Returns OpenAI-compatible structure.
 */
function lda_analyze_from_text(string $text, string $originalName): array
{
    $text = lda_normalize_extracted_text($text);
    if ($text === '') {
        throw new RuntimeException('No readable text could be extracted from the document.');
    }

    $blob = lda_text_blob(lda_compact_text($text), $originalName);
    $filenameHint = lda_filename_type_hint($originalName);
    $scores = lda_score_document_types($blob, $filenameHint);
    $meta = lda_pick_document_type($scores);
    $documentType = (string)$meta['document_type'];

    if ($documentType === 'unknown') {
        return [
            'document_type' => 'unknown',
            'confidence' => (float)$meta['confidence'],
            'summary' => (string)$meta['summary'],
            'fields' => [],
        ];
    }

    $fields = lda_extract_fields($text, $documentType, $originalName);

    return [
        'document_type' => $documentType,
        'confidence' => (float)$meta['confidence'],
        'summary' => (string)$meta['summary'],
        'fields' => $fields,
    ];
}

/**
 * Analyze one uploaded file. Prefer $clientText from browser OCR; else PHP-only fallback.
 */
function lda_analyze_document(string $tmpPath, string $originalName, array &$cleanup, string $clientText = ''): array
{
    $clientText = trim($clientText);
    if ($clientText !== '') {
        return lda_analyze_from_text($clientText, $originalName);
    }

    $text = lda_extract_document_text_php_only($tmpPath, $originalName);
    return lda_analyze_from_text($text, $originalName);
}

function lda_engine_status(): array
{
    return [
        'engine' => 'browser_ocr',
        'server' => 'php_classification_only',
        'docx' => class_exists('ZipArchive') ? 'ziparchive' : 'missing',
        'note' => 'Document text is extracted in the user browser (Tesseract.js). No server OCR tools required.',
    ];
}
