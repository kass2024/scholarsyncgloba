<?php
declare(strict_types=1);

@set_time_limit(1200);
@ini_set('max_execution_time', '1200');
@ignore_user_abort(true);

session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/env_bootstrap.php';
require_once __DIR__ . '/helpers/document_vision_router.php';

$ENV_PATH = __DIR__ . '/.env';
$MODEL = pcvc_docvision_model();
$LOG_FILE = __DIR__ . '/upload_debug.log';
$TEMP_ROOT = __DIR__ . '/temp/autofill/';
$MAX_SCAN_PAGES = 2;
$SCAN_DPI = pcvc_docvision_fast_mode_enabled() ? 132 : 144;

function autofill_log(string $message): void
{
    global $LOG_FILE;
    @file_put_contents($LOG_FILE, date('Y-m-d H:i:s') . ' ' . $message . "\n", FILE_APPEND);
}

function json_exit(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create temp directory.');
    }
}

function add_stage(array &$debug, string $stage, string $detail): void
{
    $debug['stages'][] = [
        'stage' => $stage,
        'detail' => $detail,
        'time' => date('H:i:s')
    ];
}

function normalize_text(?string $value): string
{
    $value = trim((string)$value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim((string)$value);
}

function normalize_date(?string $value): string
{
    $value = normalize_text($value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    $date = date('Y-m-d', $timestamp);
    if ($date < '1900-01-01' || $date > date('Y-m-d', strtotime('+1 day'))) {
        return '';
    }

    return $date;
}

function normalize_gender(?string $value, string $lang): string
{
    $value = strtolower(normalize_text($value));
    if ($value === '') {
        return '';
    }

    if (in_array($value, ['male', 'man', 'm', 'homme', 'masculin'], true)) {
        return $lang === 'fr' ? 'Homme' : 'Male';
    }

    if (in_array($value, ['female', 'woman', 'f', 'femme', 'feminin'], true)) {
        return $lang === 'fr' ? 'Femme' : 'Female';
    }

    return '';
}

function normalize_language(?string $value, string $lang): string
{
    $value = strtolower(normalize_text($value));
    if ($value === '') {
        return '';
    }

    if (in_array($value, ['english', 'anglais'], true)) {
        return $lang === 'fr' ? 'Anglais' : 'English';
    }

    if (in_array($value, ['french', 'francais', 'français'], true)) {
        return $lang === 'fr' ? 'Français' : 'French';
    }

    if (in_array($value, ['other', 'autre'], true)) {
        return $lang === 'fr' ? 'Autre' : 'Other';
    }

    return '';
}

function normalize_email(?string $value): string
{
    $value = strtolower(normalize_text($value));
    if ($value === '' || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    [$local] = explode('@', $value, 2);
    $genericLocals = [
        'info', 'contact', 'admin', 'office', 'admission', 'admissions',
        'apply', 'application', 'support', 'help', 'registrar', 'enquiry',
        'enquiries', 'inquiry', 'hello'
    ];
    if (in_array($local, $genericLocals, true)) {
        return '';
    }

    return $value;
}

function normalize_country_name(?string $value): string
{
    $value = strtolower(normalize_text($value));
    if ($value === '') {
        return '';
    }

    $value = str_replace(
        ['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'î', 'ï', 'ô', 'ö', 'ù', 'û', 'ü', 'ç'],
        ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'c'],
        $value
    );
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    return trim((string)$value);
}

function lookup_country_id(mysqli $conn, ?string $name): string
{
    $name = normalize_text($name);
    if ($name === '') {
        return '';
    }

    if (ctype_digit($name)) {
        return $name;
    }

    $stmt = $conn->prepare('SELECT id FROM countries WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $stmt->bind_result($id);
        $found = $stmt->fetch();
        $stmt->close();
        if ($found) {
            return (string)$id;
        }
    }

    $like = '%' . $name . '%';
    $stmt = $conn->prepare('SELECT id FROM countries WHERE LOWER(name) LIKE LOWER(?) ORDER BY CHAR_LENGTH(name) ASC LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $stmt->bind_result($id);
        $found = $stmt->fetch();
        $stmt->close();
        if ($found) {
            return (string)$id;
        }
    }

    return '';
}

function country_dial_code_from_name(?string $country): string
{
    $country = normalize_country_name($country);
    if ($country === '') {
        return '';
    }

    $codes = [
        'rwanda' => '250',
        'kenya' => '254',
        'uganda' => '256',
        'tanzania' => '255',
        'burundi' => '257',
        'democratic republic of congo' => '243',
        'dr congo' => '243',
        'congo kinshasa' => '243',
        'ethiopia' => '251',
        'eritrea' => '291',
        'djibouti' => '253',
        'somalia' => '252',
        'south sudan' => '211',
        'sudan' => '249',
        'zambia' => '260',
        'zimbabwe' => '263',
        'malawi' => '265',
        'mozambique' => '258',
        'namibia' => '264',
        'botswana' => '267',
        'south africa' => '27',
        'nigeria' => '234',
        'ghana' => '233',
        'cameroon' => '237',
        'senegal' => '221',
        'cote d ivoire' => '225',
        'ivory coast' => '225',
        'benin' => '229',
        'togo' => '228',
        'morocco' => '212',
        'algeria' => '213',
        'tunisia' => '216',
        'egypt' => '20',
        'india' => '91',
        'pakistan' => '92',
        'bangladesh' => '880',
        'nepal' => '977',
        'china' => '86',
        'canada' => '1',
        'united states' => '1',
        'usa' => '1',
        'united kingdom' => '44',
        'uk' => '44',
        'france' => '33',
        'germany' => '49',
        'belgium' => '32',
        'netherlands' => '31',
        'turkey' => '90',
        'united arab emirates' => '971',
        'uae' => '971',
        'saudi arabia' => '966',
        'qatar' => '974',
        'oman' => '968',
    ];

    return $codes[$country] ?? '';
}

function normalize_phone_pair(?string $value, array $countryHints = []): array
{
    $value = normalize_text($value);
    if ($value === '') {
        return ['area_code' => '', 'phone_number' => ''];
    }

    $hasPlus = str_starts_with($value, '+');
    $digits = preg_replace('/\D+/', '', $value);
    if ($digits === null || $digits === '') {
        return ['area_code' => '', 'phone_number' => ''];
    }

    if ($hasPlus && preg_match('/^\+(\d{1,4})/', $value, $match)) {
        $areaDigits = $match[1];
        $phoneDigits = substr($digits, strlen($areaDigits));
        if ($phoneDigits !== false && $phoneDigits !== '') {
            return [
                'area_code' => '+' . $areaDigits,
                'phone_number' => $phoneDigits
            ];
        }
    }

    foreach ($countryHints as $hint) {
        $dialCode = country_dial_code_from_name($hint);
        if ($dialCode === '') {
            continue;
        }

        $phoneDigits = $digits;
        if (str_starts_with($phoneDigits, $dialCode)) {
            $phoneDigits = substr($phoneDigits, strlen($dialCode));
        } elseif (str_starts_with($phoneDigits, '0')) {
            $phoneDigits = ltrim($phoneDigits, '0');
        }

        if ($phoneDigits !== '' && preg_match('/^\d{6,15}$/', $phoneDigits)) {
            return [
                'area_code' => '+' . $dialCode,
                'phone_number' => $phoneDigits
            ];
        }
    }

    if (preg_match('/^0?(7[2389]\d{7})$/', $digits, $rwMatch)) {
        return [
            'area_code' => '+250',
            'phone_number' => $rwMatch[1]
        ];
    }

    if (preg_match('/^0?(7\d{8})$/', $digits, $keMatch)) {
        return [
            'area_code' => '+254',
            'phone_number' => $keMatch[1]
        ];
    }

    if (strlen($digits) === 10 && !str_starts_with($digits, '0')) {
        return [
            'area_code' => '+1',
            'phone_number' => $digits
        ];
    }

    if (str_starts_with($digits, '0') && strlen($digits) >= 9 && strlen($digits) <= 11) {
        $localDigits = ltrim($digits, '0');
        if ($localDigits !== '' && preg_match('/^\d{8,10}$/', $localDigits)) {
            return [
                'area_code' => '+250',
                'phone_number' => $localDigits
            ];
        }
    }

    return ['area_code' => '', 'phone_number' => ''];
}

function normalize_fields(array $fields, string $lang, mysqli $conn): array
{
    $aliases = [
        'phone' => 'phone_international',
        'mobile' => 'phone_international',
        'telephone' => 'phone_international',
        'cell' => 'phone_international',
        'whatsapp' => 'phone_international',
        'e_mail' => 'email',
        'email_address' => 'email',
        'mail' => 'email',
        'document_number' => 'passport_number',
        'passport_no' => 'passport_number',
        'passport_num' => 'passport_number',
        'travel_document_number' => 'passport_number',
    ];
    foreach ($aliases as $from => $to) {
        if (!empty($fields[$from]) && empty($fields[$to])) {
            $fields[$to] = $fields[$from];
        }
    }
    if (
        empty($fields['phone_international'])
        && !empty($fields['phone_number'])
        && preg_match('/^\+/', trim((string)$fields['phone_number']))
    ) {
        $fields['phone_international'] = trim((string)$fields['phone_number']);
    }
    if (empty($fields['phone_international'])) {
        $areaCode = normalize_text($fields['area_code'] ?? '');
        $phoneDigits = preg_replace('/\D+/', '', (string)($fields['phone_number'] ?? ''));
        if ($areaCode !== '' && is_string($phoneDigits) && $phoneDigits !== '') {
            $fields['phone_international'] = $areaCode . $phoneDigits;
        }
    }

    $normalized = [];
    $stringFields = [
        'first_name',
        'last_name',
        'email',
        'passport_number',
        'student_national_id',
        'country_of_birth',
        'city_of_birth',
        'nationality',
        'second_nationality',
        'address_line1',
        'address_line2',
        'city',
        'state_province',
        'postal_code',
        'previous_institution_name',
        'previous_institution_city',
        'previous_institution_province',
        'previous_institution_country',
        'previous_institution_post_code',
        'father_first_name',
        'father_last_name',
        'mother_first_name',
        'mother_last_name'
    ];

    foreach ($stringFields as $field) {
        $value = normalize_text($fields[$field] ?? '');
        if ($value !== '') {
            $normalized[$field] = $value;
        }
    }

    if (!empty($normalized['email'])) {
        $normalized['email'] = normalize_email($normalized['email']);
        if ($normalized['email'] === '') {
            unset($normalized['email']);
        }
    }

    foreach (['dob', 'previous_study_start', 'previous_study_graduation'] as $dateField) {
        $date = normalize_date($fields[$dateField] ?? '');
        if ($date !== '') {
            $normalized[$dateField] = $date;
        }
    }

    $gender = normalize_gender($fields['gender'] ?? '', $lang);
    if ($gender !== '') {
        $normalized['gender'] = $gender;
    }

    $language = normalize_language($fields['language_of_instruction'] ?? '', $lang);
    if ($language !== '') {
        $normalized['language_of_instruction'] = $language;
    }

    if (!empty($normalized['passport_number'])) {
        $normalized['passport_number'] = strtoupper(preg_replace('/\s+/', '', $normalized['passport_number']));
        if (!pcvc_docvision_is_plausible_passport_number($normalized['passport_number'])) {
            unset($normalized['passport_number']);
        }
    }

    if (!empty($normalized['student_national_id'])) {
        $normalized['student_national_id'] = strtoupper($normalized['student_national_id']);
    }

    foreach (['country_of_birth', 'nationality', 'second_nationality', 'previous_institution_country'] as $countryField) {
        $countryId = lookup_country_id($conn, $fields[$countryField] ?? '');
        if ($countryId !== '') {
            $normalized[$countryField] = $countryId;
        }
    }

    $phone = normalize_phone_pair(
        $fields['phone_international'] ?? '',
        [
            $fields['nationality'] ?? '',
            $fields['country_of_birth'] ?? '',
            $fields['previous_institution_country'] ?? '',
            $fields['address_line1'] ?? '',
            $fields['city'] ?? ''
        ]
    );
    if ($phone['area_code'] !== '' && $phone['phone_number'] !== '') {
        $normalized['area_code'] = $phone['area_code'];
        $normalized['phone_number'] = $phone['phone_number'];
    }

    return $normalized;
}

function flatten_uploaded_files(string $key): array
{
    if (empty($_FILES[$key])) {
        return [];
    }

    $files = $_FILES[$key];
    if (!is_array($files['name'])) {
        return [[
            'name' => $files['name'],
            'tmp_name' => $files['tmp_name'] ?? '',
            'error' => $files['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'] ?? 0,
            'client_index' => 0
        ]];
    }

    $out = [];
    foreach ($files['name'] as $index => $name) {
        $out[] = [
            'name' => $name,
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
            'client_index' => $index
        ];
    }

    if (isset($_POST['document_client_index']) && is_numeric($_POST['document_client_index'])) {
        $forcedIndex = (int)$_POST['document_client_index'];
        foreach ($out as &$file) {
            $file['client_index'] = $forcedIndex;
        }
        unset($file);
    }

    return $out;
}

function scanned_pdf_to_images(string $pdfPath, string $outDir, int $maxPages = 4, int $dpi = 144): array
{
    if (!class_exists('Imagick')) {
        throw new RuntimeException('Scanned PDF support requires Imagick on the server.');
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
        $page->setImageCompressionQuality(82);
        $out = $outDir . 'page_' . ($i + 1) . '_' . bin2hex(random_bytes(3)) . '.jpg';
        $page->writeImage($out);
        $images[] = $out;
        $pageCount++;
    }

    $im->clear();
    $im->destroy();

    return $images;
}


function cleanup_paths(array $paths): void
{
    rsort($paths);
    foreach ($paths as $path) {
        if (!is_string($path) || $path === '') {
            continue;
        }

        if (is_file($path)) {
            @unlink($path);
        } elseif (is_dir($path)) {
            @rmdir($path);
        }
    }
}

function field_priority(string $field, string $source): int
{
    $preferences = [
        'first_name' => ['valid_passport', 'birth_certificate', 'cv_resume', 'degree_transcripts', 'high_school_degree'],
        'last_name' => ['valid_passport', 'birth_certificate', 'cv_resume', 'degree_transcripts', 'high_school_degree'],
        'dob' => ['valid_passport', 'birth_certificate', 'degree_transcripts', 'high_school_degree'],
        'gender' => ['valid_passport', 'birth_certificate'],
        'passport_number' => ['valid_passport'],
        'student_national_id' => ['valid_passport', 'birth_certificate'],
        'country_of_birth' => ['valid_passport', 'birth_certificate'],
        'city_of_birth' => ['valid_passport', 'birth_certificate'],
        'nationality' => ['valid_passport', 'birth_certificate', 'cv_resume'],
        'second_nationality' => ['valid_passport', 'birth_certificate'],
        'email' => ['cv_resume', 'personal_statement', 'recommendation_letters', 'payment_proof'],
        'area_code' => ['cv_resume', 'personal_statement'],
        'phone_number' => ['cv_resume', 'personal_statement'],
        'address_line1' => ['cv_resume', 'valid_passport', 'personal_statement'],
        'address_line2' => ['cv_resume', 'valid_passport', 'personal_statement'],
        'city' => ['cv_resume', 'valid_passport', 'personal_statement'],
        'state_province' => ['cv_resume', 'valid_passport', 'personal_statement'],
        'postal_code' => ['cv_resume', 'valid_passport', 'personal_statement'],
        'previous_institution_name' => ['degree_transcripts', 'high_school_degree', 'english_certificate'],
        'previous_institution_city' => ['degree_transcripts', 'high_school_degree'],
        'previous_institution_province' => ['degree_transcripts', 'high_school_degree'],
        'previous_institution_country' => ['degree_transcripts', 'high_school_degree'],
        'previous_institution_post_code' => ['degree_transcripts', 'high_school_degree'],
        'previous_study_start' => ['degree_transcripts', 'high_school_degree'],
        'previous_study_graduation' => ['degree_transcripts', 'high_school_degree'],
        'language_of_instruction' => ['english_certificate', 'degree_transcripts', 'high_school_degree'],
        'father_first_name' => ['birth_certificate'],
        'father_last_name' => ['birth_certificate'],
        'mother_first_name' => ['birth_certificate'],
        'mother_last_name' => ['birth_certificate']
    ];

    $list = $preferences[$field] ?? ['valid_passport', 'cv_resume', 'degree_transcripts', 'high_school_degree', 'birth_certificate'];
    $index = array_search($source, $list, true);
    return $index === false ? 0 : (count($list) - $index);
}

function merge_candidate_fields(array &$merged, array &$scores, array $candidate, string $source, float $confidence): void
{
    foreach ($candidate as $field => $value) {
        if ($value === '' || $value === null) {
            continue;
        }

        $score = (field_priority($field, $source) * 100) + (int)round($confidence * 100);
        if (!isset($scores[$field]) || $score > $scores[$field]) {
            $merged[$field] = $value;
            $scores[$field] = $score;
        }
    }
}

$debug = [
    'api_key_status' => 'unknown',
    'env_path' => $ENV_PATH,
    'log_file' => $LOG_FILE,
    'model' => $MODEL,
    'documents_received' => 0,
    'stages' => []
];

if (empty($_SESSION['user_id'])) {
    $debug['api_key_status'] = 'not_checked';
    add_stage($debug, 'prepare', 'Session user id missing.');
    json_exit([
        'status' => 'error',
        'message' => 'User session is missing. Please reload the application form.',
        'debug' => $debug
    ], 401);
}

$existingUploadToken = '';
$existingUploadTokenExpiry = 0;
if (
    !empty($_SESSION['smart_autofill_batch_upload_token'])
    && (int)($_SESSION['smart_autofill_batch_upload_token_expires'] ?? 0) > time()
) {
    $existingUploadToken = (string)$_SESSION['smart_autofill_batch_upload_token'];
    $existingUploadTokenExpiry = (int)$_SESSION['smart_autofill_batch_upload_token_expires'];
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if (!pcvc_docvision_autofill_ready()) {
    $debug['api_key_status'] = 'missing';
    add_stage($debug, 'prepare', 'No document AI API key in .env (GEMINI_API_KEY or ANTHROPIC_API_KEY).');
    json_exit([
        'status' => 'error',
        'message' => 'AI document autofill is not configured. Set GEMINI_API_KEY and/or ANTHROPIC_API_KEY in .env.',
        'debug' => $debug
    ], 500);
}

$debug['dual_provider'] = pcvc_docvision_dual_provider_enabled();
$debug['fast_mode'] = pcvc_docvision_fast_mode_enabled();
$debug['concurrency'] = pcvc_docvision_analysis_concurrency();
$debug['providers'] = array_values(array_filter([
    pcvc_docvision_is_configured() ? 'gemini' : null,
    pcvc_docvision_claude_is_configured() ? 'claude' : null,
]));

$debug['api_key_status'] = 'configured';
$lang = (($_POST['lang'] ?? 'en') === 'fr') ? 'fr' : 'en';
$uploadedFiles = flatten_uploaded_files('documents');
$debug['documents_received'] = count($uploadedFiles);
add_stage($debug, 'prepare', 'Batch upload accepted.');
autofill_log('START autofill files=' . count($uploadedFiles));

if (!$uploadedFiles) {
    json_exit([
        'status' => 'error',
        'message' => 'Please choose at least one document.',
        'debug' => $debug
    ], 400);
}

ensure_dir($TEMP_ROOT);

$fieldLabels = [
    'degree_transcripts' => $lang === 'fr' ? 'Diplomes / Releves de Notes' : 'Degree / Academic Transcripts',
    'high_school_degree' => $lang === 'fr' ? 'Certificat de Lycee' : 'High School Certificate',
    'valid_passport' => $lang === 'fr' ? 'Passeport Valide' : 'Valid Passport',
    'recommendation_letters' => $lang === 'fr' ? 'Lettres de Recommandation' : 'Recommendation Letter(s)',
    'personal_statement' => $lang === 'fr' ? 'Lettre de Motivation' : 'Personal Statement / Motivation Letter',
    'cv_resume' => $lang === 'fr' ? 'CV / Curriculum Vitae' : 'CV / Resume',
    'english_certificate' => $lang === 'fr' ? 'Certificat d Anglais' : 'English Proficiency Certificate',
    'birth_certificate' => $lang === 'fr' ? 'Certificat de Naissance' : 'Birth Certificate',
    'payment_proof' => $lang === 'fr' ? 'Preuve de Paiement' : 'Application / Payment Proof'
];

$systemPrompt = <<<'PROMPT'
Classify the attached admissions document and extract applicant fields. Return JSON only.

document_type: valid_passport | degree_transcripts | high_school_degree | cv_resume | recommendation_letters | personal_statement | english_certificate | birth_certificate | payment_proof | unknown

Rules: extract every visible fact; never invent data; empty string if not visible; country names as words; prefer applicant email/phone over office addresses.
- Passport/ID: MUST extract passport_number (document number), nationality, dob, gender, country_of_birth, city_of_birth.
- CV/resume: MUST extract email, phone_international, address_line1, city, state_province, postal_code when visible.
- Never leave passport_number or address_line1 empty when they are clearly printed on the document.

{"document_type":"","confidence":0.0,"summary":"","fields":{"first_name":"","last_name":"","email":"","phone_international":"","dob":"","gender":"","passport_number":"","student_national_id":"","country_of_birth":"","city_of_birth":"","nationality":"","second_nationality":"","address_line1":"","address_line2":"","city":"","state_province":"","postal_code":"","previous_institution_name":"","previous_institution_city":"","previous_institution_province":"","previous_institution_country":"","previous_institution_post_code":"","previous_study_start":"","previous_study_graduation":"","language_of_instruction":"","father_first_name":"","father_last_name":"","mother_first_name":"","mother_last_name":""}}
PROMPT;

function document_instruction_from_name(string $originalName): string
{
    $fileNameHint = strtolower($originalName);
    $docInstruction = 'Classify this document and extract applicant fields.';
    if (str_contains($fileNameHint, 'cv') || str_contains($fileNameHint, 'resume')) {
        $docInstruction .= ' This is a CV/resume — CRITICAL: extract email and phone_international (with country code) from the contact/header section.';
    } elseif (str_contains($fileNameHint, 'passport')) {
        $docInstruction .= ' This is a passport — CRITICAL: extract passport_number (document no.), nationality, dob, gender, country_of_birth, city_of_birth, and address if printed.';
    } elseif (str_contains($fileNameHint, 'birth')) {
        $docInstruction .= ' This is a birth certificate — extract legal identity, parent names, place of birth, and national ID if visible.';
    } elseif (str_contains($fileNameHint, 'transcript') || str_contains($fileNameHint, 'degree') || str_contains($fileNameHint, 'academic')) {
        $docInstruction .= ' This file may be an academic record, so prioritize previous institution, study dates, and language of instruction.';
    }

    return $docInstruction;
}

function resolve_document_type_for_attachment(string $documentType, string $originalName, float $confidence): array
{
    $documentType = strtolower(trim($documentType));
    $filenameGuess = pcvc_docvision_guess_document_type_from_filename($originalName);

    if ($filenameGuess !== '') {
        if ($documentType === 'unknown' || $documentType === '' || $confidence < 0.80) {
            return [$filenameGuess, max($confidence, 0.82)];
        }
        if ($documentType !== $filenameGuess && $confidence < 0.93) {
            return [$filenameGuess, max($confidence, 0.85)];
        }
    }

    if ($documentType === 'unknown' || $documentType === '') {
        if ($filenameGuess !== '') {
            return [$filenameGuess, max($confidence, 0.72)];
        }
    }

    if ($documentType !== 'unknown' && $documentType !== '') {
        return [$documentType, $confidence];
    }

    if ($filenameGuess !== '') {
        return [$filenameGuess, max($confidence, 0.65)];
    }

    return [$documentType, $confidence];
}

function process_ai_document_result(
    array $ai,
    array $preparedDoc,
    array $fieldLabels,
    string $lang,
    mysqli $conn,
    array &$mergedFields,
    array &$fieldScores,
    array &$documents,
    array &$warnings,
    string $sourceText = '',
    string $rawText = ''
): void {
    $originalName = $preparedDoc['original_name'];
    $clientIndex = $preparedDoc['client_index'];

    if (!$ai) {
        $warnings[] = $originalName . ': AI returned an invalid extraction result.';
        return;
    }

    $documentType = (string)($ai['document_type'] ?? 'unknown');
    $confidence = max(0.0, min(1.0, (float)($ai['confidence'] ?? 0)));
    $summary = normalize_text((string)($ai['summary'] ?? ''));

    [$documentType, $confidence] = resolve_document_type_for_attachment($documentType, $originalName, $confidence);

    $rawFields = (array)($ai['fields'] ?? []);
    $nameHints = array_filter([
        (string)($rawFields['first_name'] ?? ''),
        (string)($rawFields['last_name'] ?? ''),
        (string)($mergedFields['first_name'] ?? ''),
        (string)($mergedFields['last_name'] ?? ''),
    ]);
    if ($sourceText !== '') {
        $rawFields = pcvc_docvision_supplement_fields_from_text($rawFields, $sourceText, $nameHints);
    }
    if ($rawText !== '') {
        $rawFields = pcvc_docvision_supplement_fields_from_text($rawFields, $rawText, $nameHints);
    }

    $isPassportDoc = $documentType === 'valid_passport'
        || (bool)preg_match('/\b(passport|passeport)\b/i', $originalName);
    if ($isPassportDoc && empty($rawFields['passport_number'])) {
        $fromRaw = pcvc_docvision_extract_passport_number_from_text($rawText);
        if ($fromRaw !== '') {
            $rawFields['passport_number'] = $fromRaw;
        }
    }

    $normalized = normalize_fields($rawFields, $lang, $conn);
    if (!empty($normalized)) {
        merge_candidate_fields(
            $mergedFields,
            $fieldScores,
            $normalized,
            $documentType !== '' ? $documentType : 'unknown',
            max($confidence, 0.55)
        );
    }

    $attachField = '';
    $attachConfidence = $confidence;
    if (array_key_exists($documentType, $fieldLabels) && $confidence >= 0.30) {
        $attachField = $documentType;
    } else {
        $filenameGuess = pcvc_docvision_guess_document_type_from_filename($originalName);
        if ($filenameGuess !== '' && array_key_exists($filenameGuess, $fieldLabels)) {
            $attachField = $filenameGuess;
            $attachConfidence = max($confidence, 0.72);
        } elseif (
            array_key_exists($documentType, $fieldLabels)
            && preg_match('/\b(cv|resume|curriculum|vitae|passport|transcript|diploma|degree|birth|ielts|toefl|recommend|statement)\b/i', $summary)
        ) {
            $attachField = $documentType;
            $attachConfidence = max($confidence, 0.55);
        }
    }

    if ($attachField === '') {
        $documents[] = [
            'client_index' => $clientIndex,
            'original_name' => $originalName,
            'field' => '',
            'field_label' => '',
            'confidence' => $confidence,
            'summary' => $summary
        ];
        $warnings[] = $originalName . ': the document could not be matched confidently to a supported attachment field.';
        return;
    }

    $documents[] = [
        'client_index' => $clientIndex,
        'original_name' => $originalName,
        'field' => $attachField,
        'field_label' => $fieldLabels[$attachField],
        'confidence' => $attachConfidence,
        'summary' => $summary
    ];
}

function autofill_harvest_contact_fields(
    array &$mergedFields,
    array $jobs,
    array $responses,
    string $lang,
    mysqli $conn
): void {
    $nameHints = array_filter([
        (string)($mergedFields['first_name'] ?? ''),
        (string)($mergedFields['last_name'] ?? ''),
    ]);
    $rawBlob = '';

    foreach ($jobs as $idx => $job) {
        $response = $responses[$idx] ?? null;
        if (!is_array($response)) {
            continue;
        }
        if (!empty($response['raw_text'])) {
            $rawBlob .= "\n" . (string)$response['raw_text'];
        }
        if (!empty($response['json']) && is_array($response['json'])) {
            $rawBlob .= "\n" . json_encode($response['json'], JSON_UNESCAPED_UNICODE);
        }
        $rawBlob .= "\n" . (string)($job['original_name'] ?? '');
    }

    if ($rawBlob !== '') {
        $supplemented = pcvc_docvision_supplement_fields_from_text($mergedFields, $rawBlob, $nameHints);
        $patch = normalize_fields($supplemented, $lang, $conn);
        $mergedFields = pcvc_docvision_merge_contact_fields($mergedFields, $patch);
    }

    if (!empty($mergedFields['email']) && !empty($mergedFields['phone_number'])) {
        return;
    }

    $contactPriority = [];
    foreach ($jobs as $idx => $job) {
        $hint = strtolower((string)($job['original_name'] ?? ''));
        $response = $responses[$idx] ?? null;
        $docType = is_array($response)
            ? strtolower((string)(($response['json']['document_type'] ?? '') ?: ''))
            : '';
        $score = 1;
        if (preg_match('/\b(cv|resume|curriculum|vitae)\b/', $hint) || $docType === 'cv_resume') {
            $score = 4;
        } elseif (preg_match('/\b(passport|passeport)\b/', $hint) || $docType === 'valid_passport') {
            $score = 3;
        } elseif (in_array($docType, ['personal_statement', 'recommendation_letters'], true)) {
            $score = 2;
        }
        $contactPriority[] = ['idx' => $idx, 'job' => $job, 'score' => $score];
    }

    usort($contactPriority, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

    foreach ($contactPriority as $entry) {
        if (!empty($mergedFields['email']) && !empty($mergedFields['phone_number'])) {
            return;
        }

        $contact = pcvc_docvision_extract_contact_from_content($entry['job']['user']);
        if (isset($contact['error'])) {
            continue;
        }

        $raw = (array)($contact['json'] ?? []);
        if (!empty($contact['raw_text'])) {
            $raw = pcvc_docvision_supplement_fields_from_text($raw, (string)$contact['raw_text'], $nameHints);
        }

        $patch = normalize_fields($raw, $lang, $conn);
        $mergedFields = pcvc_docvision_merge_contact_fields($mergedFields, $patch);
    }
}

function autofill_harvest_passport_number(
    array &$mergedFields,
    array $jobs,
    array $responses,
    string $lang,
    mysqli $conn
): void {
    if (!empty($mergedFields['passport_number'])) {
        return;
    }

    $passportJobs = [];
    foreach ($jobs as $idx => $job) {
        $hint = strtolower((string)($job['original_name'] ?? ''));
        $response = $responses[$idx] ?? null;
        $docType = is_array($response)
            ? strtolower((string)(($response['json']['document_type'] ?? '') ?: ''))
            : '';
        $isPassport = preg_match('/\b(passport|passeport)\b/', $hint) || $docType === 'valid_passport';
        if (!$isPassport) {
            continue;
        }
        $passportJobs[$idx] = $job;
    }

    if ($passportJobs === []) {
        foreach ($jobs as $idx => $job) {
            $hint = strtolower((string)($job['original_name'] ?? ''));
            $response = $responses[$idx] ?? null;
            $docType = is_array($response)
                ? strtolower((string)(($response['json']['document_type'] ?? '') ?: ''))
                : '';
            if (
                preg_match('/\b(passport|passeport|birth[\s_-]?cert|id|identity)\b/', $hint)
                || in_array($docType, ['valid_passport', 'birth_certificate'], true)
            ) {
                $passportJobs[$idx] = $job;
            }
        }
    }

    foreach ($passportJobs as $idx => $job) {
        $response = $responses[$idx] ?? null;
        $rawText = is_array($response) ? (string)($response['raw_text'] ?? '') : '';
        if ($rawText !== '') {
            $fromText = pcvc_docvision_extract_passport_number_from_text($rawText);
            if ($fromText !== '') {
                $mergedFields['passport_number'] = strtoupper($fromText);
                return;
            }
            $supplemented = pcvc_docvision_supplement_fields_from_text(
                $mergedFields,
                $rawText,
                array_filter([
                    (string)($mergedFields['first_name'] ?? ''),
                    (string)($mergedFields['last_name'] ?? ''),
                ])
            );
            if (!empty($supplemented['passport_number'])) {
                $mergedFields['passport_number'] = strtoupper((string)$supplemented['passport_number']);
                return;
            }
        }

        $passport = pcvc_docvision_extract_passport_from_content($job['user']);
        if (isset($passport['error'])) {
            continue;
        }

        $num = trim((string)(($passport['json']['passport_number'] ?? '') ?: ''));
        if ($num === '' && !empty($passport['raw_text'])) {
            $num = pcvc_docvision_extract_passport_number_from_text((string)$passport['raw_text']);
        }
        if ($num !== '' && pcvc_docvision_is_plausible_passport_number($num)) {
            $mergedFields['passport_number'] = strtoupper(preg_replace('/\s+/', '', $num));
            return;
        }
    }
}

function autofill_harvest_identity_fields(
    array &$mergedFields,
    array $jobs,
    array $responses,
    string $lang,
    mysqli $conn
): void {
    $needsPassport = empty($mergedFields['passport_number']);
    $needsAddress = empty($mergedFields['address_line1']) && empty($mergedFields['city']);
    if (!$needsPassport && !$needsAddress) {
        return;
    }

    foreach ($jobs as $idx => $job) {
        $hint = strtolower((string)($job['original_name'] ?? ''));
        $isIdentityDoc = (bool)preg_match(
            '/\b(passport|passeport|cv|resume|curriculum|vitae|birth[\s_-]?cert|id|identity)\b/',
            $hint
        );
        if (!$isIdentityDoc) {
            continue;
        }

        $identity = pcvc_docvision_extract_identity_from_content($job['user']);
        if (isset($identity['error'])) {
            continue;
        }

        $raw = (array)($identity['json'] ?? []);
        if (!empty($identity['raw_text'])) {
            $nameHints = array_filter([
                (string)($mergedFields['first_name'] ?? ''),
                (string)($mergedFields['last_name'] ?? ''),
            ]);
            $raw = pcvc_docvision_supplement_fields_from_text($raw, (string)$identity['raw_text'], $nameHints);
        }

        $patch = normalize_fields($raw, $lang, $conn);
        foreach ($patch as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            if (empty($mergedFields[$key])) {
                $mergedFields[$key] = $value;
            }
        }

        $needsPassport = empty($mergedFields['passport_number']);
        $needsAddress = empty($mergedFields['address_line1']) && empty($mergedFields['city']);
        if (!$needsPassport && !$needsAddress) {
            return;
        }
    }
}

$mergedFields = [];
$fieldScores = [];
$documents = [];
$warnings = [];
$jobs = [];

foreach ($uploadedFiles as $file) {
    $originalName = basename((string)($file['name'] ?? 'document'));
    $clientIndex = (int)($file['client_index'] ?? 0);

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        $warnings[] = $originalName . ': upload failed before analysis.';
        continue;
    }

    if ((int)($file['size'] ?? 0) > 15 * 1024 * 1024) {
        $warnings[] = $originalName . ': file is too large (max 15MB).';
        continue;
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'docx', 'jpg', 'jpeg', 'png', 'webp'], true)) {
        $warnings[] = $originalName . ': unsupported file type.';
        continue;
    }

    $tempName = time() . '_' . bin2hex(random_bytes(4)) . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '_', $originalName);
    $tempPath = $TEMP_ROOT . $tempName;
    $cleanup = [$tempPath];

    if (!move_uploaded_file((string)$file['tmp_name'], $tempPath)) {
        $warnings[] = $originalName . ': failed to prepare the file for AI extraction.';
        continue;
    }

    try {
        add_stage($debug, 'extract', 'Preparing ' . $originalName . ' for AI.');
        $content = pcvc_docvision_build_api_only_content(
            $tempPath,
            $originalName,
            $cleanup,
            document_instruction_from_name($originalName),
            $MAX_SCAN_PAGES,
            $SCAN_DPI
        );

        $contentModes = [];
        foreach ($content as $block) {
            $type = (string)($block['type'] ?? 'unknown');
            if (!in_array($type, $contentModes, true)) {
                $contentModes[] = $type;
            }
        }
        add_stage(
            $debug,
            'extract',
            $originalName . ' ready (' . implode(' + ', $contentModes) . ', API-only).'
        );

        $jobs[] = [
            'system' => $systemPrompt,
            'user' => $content,
            'cleanup' => $cleanup,
            'client_index' => $clientIndex,
            'original_name' => $originalName,
        ];
    } catch (Throwable $e) {
        $warnings[] = $originalName . ': ' . $e->getMessage();
        cleanup_paths($cleanup);
    }
}

if (!$jobs) {
    add_stage($debug, 'parse', 'No usable documents were prepared for analysis.');
    json_exit([
        'status' => 'error',
        'message' => 'No document could be prepared for analysis. Please check file types and sizes.',
        'warnings' => $warnings,
        'debug' => $debug
    ], 422);
}

$contactOnly = (string)($_POST['contact_only'] ?? '') === '1';
$passportOnly = (string)($_POST['passport_only'] ?? '') === '1';
if (($contactOnly || $passportOnly) && count($jobs) === 1) {
    $job = $jobs[0];
    add_stage($debug, 'ai', ($passportOnly ? 'Passport-only' : 'Contact-only') . ' extraction for ' . $job['original_name'] . '.');
    $mergedFields = [];
    $fieldScores = [];

    if ($passportOnly) {
        $passport = pcvc_docvision_extract_passport_from_content($job['user']);
        if (!isset($passport['error'])) {
            $num = trim((string)(($passport['json']['passport_number'] ?? '') ?: ''));
            if ($num === '' && !empty($passport['raw_text'])) {
                $num = pcvc_docvision_extract_passport_number_from_text((string)$passport['raw_text']);
            }
            if ($num !== '' && pcvc_docvision_is_plausible_passport_number($num)) {
                $mergedFields['passport_number'] = strtoupper(preg_replace('/\s+/', '', $num));
            }
            add_stage($debug, 'parse', 'Passport-only result: ' . ($mergedFields['passport_number'] ?? '(none)'));
        } else {
            $warnings[] = $job['original_name'] . ': ' . (string)($passport['error']['message'] ?? 'Passport extraction failed.');
        }
    } else {
        $contact = pcvc_docvision_extract_contact_from_content($job['user']);
        if (!isset($contact['error'])) {
            $raw = (array)($contact['json'] ?? []);
            if (!empty($contact['raw_text'])) {
                $raw = pcvc_docvision_supplement_fields_from_text($raw, (string)$contact['raw_text']);
            }
            $mergedFields = normalize_fields($raw, $lang, $conn);
            add_stage($debug, 'parse', 'Contact-only result: email=' . ($mergedFields['email'] ?? '(none)'));
        } else {
            $warnings[] = $job['original_name'] . ': ' . (string)($contact['error']['message'] ?? 'Contact extraction failed.');
        }
    }

    cleanup_paths($job['cleanup']);
    json_exit([
        'status' => 'success',
        'message' => $passportOnly ? 'Passport number extracted.' : 'Contact details extracted.',
        'fields' => $mergedFields,
        'documents' => [],
        'warnings' => $warnings,
        'upload_token' => '',
        'debug' => $debug
    ]);
}

$providerNote = pcvc_docvision_dual_provider_enabled()
    ? 'Gemini + Claude'
    : (pcvc_docvision_is_configured() ? 'Gemini' : 'Claude');
$modeNote = pcvc_docvision_fast_mode_enabled() ? 'parallel API' : 'full vision';
add_stage(
    $debug,
    'ai',
    'Analyzing ' . count($jobs) . ' document(s) via ' . $providerNote . ' (' . $modeNote . ', up to ' . pcvc_docvision_analysis_concurrency() . ' at once).'
);
$apiRequests = array_map(static fn(array $job): array => [
    'system' => $job['system'],
    'user' => $job['user'],
], $jobs);
$analysisStarted = microtime(true);
$responses = pcvc_docvision_analyze_parallel($apiRequests);
add_stage(
    $debug,
    'ai',
    'All API calls finished in ' . round(microtime(true) - $analysisStarted, 1) . 's.'
);

foreach ($jobs as $idx => $job) {
    $response = $responses[$idx] ?? ['error' => ['message' => 'No API response']];
    if (isset($response['error'])) {
        $warnings[] = $job['original_name'] . ': ' . (string)($response['error']['message'] ?? 'AI extraction failed.');
        continue;
    }

    $usedProvider = (string)($response['provider'] ?? pcvc_docvision_pick_provider((int)$idx, $job['user']));
    add_stage($debug, 'ai', 'Analyzed ' . $job['original_name'] . ' via ' . $usedProvider . '.');

    $ai = $response['json'] ?? [];
    if (!$ai) {
        $warnings[] = $job['original_name'] . ': AI returned an invalid extraction result.';
        continue;
    }

    $preparedDoc = [
        'client_index' => $job['client_index'],
        'original_name' => $job['original_name'],
    ];

    process_ai_document_result(
        $ai,
        $preparedDoc,
        $fieldLabels,
        $lang,
        $conn,
        $mergedFields,
        $fieldScores,
        $documents,
        $warnings,
        '',
        (string)($response['raw_text'] ?? '')
    );
    add_stage($debug, 'parse', 'Parsed ' . $job['original_name'] . ' as ' . ($ai['document_type'] ?? 'unknown') . '.');
}

autofill_harvest_contact_fields($mergedFields, $jobs, $responses, $lang, $conn);
autofill_harvest_passport_number($mergedFields, $jobs, $responses, $lang, $conn);
autofill_harvest_identity_fields($mergedFields, $jobs, $responses, $lang, $conn);
add_stage(
    $debug,
    'parse',
    'Contact harvest: email=' . ($mergedFields['email'] ?? '(none)')
        . ', phone=' . ($mergedFields['phone_number'] ?? '(none)')
        . ', passport=' . ($mergedFields['passport_number'] ?? '(none)')
        . ', address=' . ($mergedFields['address_line1'] ?? ($mergedFields['city'] ?? '(none)'))
);

foreach ($jobs as $job) {
    cleanup_paths($job['cleanup']);
}

file_put_contents(
    $LOG_FILE,
    "\n=== " . date('Y-m-d H:i:s') . " ===\nPARALLEL AUTOFILL DEBUG\n" . json_encode([
        'documents' => $documents,
        'warnings' => $warnings,
        'fields' => $mergedFields,
        'debug' => $debug
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND
);

if (!$documents && !$mergedFields) {
    add_stage($debug, 'parse', 'No usable documents were analyzed successfully.');
    json_exit([
        'status' => 'error',
        'message' => 'No document could be analyzed successfully. Please try clearer passport, CV, or academic files.',
        'warnings' => $warnings,
        'debug' => $debug
    ], 422);
}

add_stage($debug, 'save', 'Document analysis completed successfully.');
$uploadToken = $existingUploadToken !== '' ? $existingUploadToken : bin2hex(random_bytes(16));
$uploadTokenExpiry = $existingUploadTokenExpiry > time()
    ? $existingUploadTokenExpiry
    : (time() + 1800);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['smart_autofill_batch_upload_token'] = $uploadToken;
$_SESSION['smart_autofill_batch_upload_token_expires'] = $uploadTokenExpiry;
session_write_close();

json_exit([
    'status' => 'success',
    'message' => 'Documents analyzed successfully.',
    'fields' => $mergedFields,
    'documents' => $documents,
    'warnings' => $warnings,
    'upload_token' => $uploadToken,
    'debug' => $debug
]);
