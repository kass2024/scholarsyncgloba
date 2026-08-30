<?php
declare(strict_types=1);

ob_start();
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/load_env.php';
require_once __DIR__ . '/helpers/document_vision_router.php';
pcvc_load_dotenv(__DIR__);

$ENV_PATH = __DIR__ . '/.env';
$MODEL = pcvc_docvision_model();
$LOG_FILE = __DIR__ . '/upload_debug.log';
$TEMP_ROOT = __DIR__ . '/temp/autofill/';

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

    return ['area_code' => '', 'phone_number' => ''];
}

function normalize_fields(array $fields, string $lang, mysqli $conn): array
{
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

    return $out;
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

function map_student_document_to_loan_attachment(string $studentType): string
{
    return match ($studentType) {
        'valid_passport' => 'valid_passport',
        'birth_certificate' => 'id_document',
        'degree_transcripts' => 'bachelor_transcript',
        'high_school_degree' => 'bachelor_degree',
        'cv_resume', 'recommendation_letters', 'personal_statement' => 'cv',
        'english_certificate' => 'english_certificate',
        'payment_proof' => 'admission_fees',
        default => '',
    };
}

function loan_attachment_labels(string $lang): array
{
    return [
        'acceptance_letter' => $lang === 'fr' ? 'Lettre d\'acceptation' : 'Acceptance Letter',
        'bachelor_degree' => $lang === 'fr' ? 'Diplôme de licence' : 'Bachelor Degree',
        'bachelor_transcript' => $lang === 'fr' ? 'Relevé de licence' : 'Bachelor Transcript',
        'cv' => 'CV',
        'id_document' => $lang === 'fr' ? 'Pièce d\'identité' : 'ID Document',
        'valid_passport' => $lang === 'fr' ? 'Passeport valide' : 'Valid Passport',
        'english_certificate' => $lang === 'fr' ? 'Certificat d\'anglais' : 'English Certificate',
        'admission_fees' => $lang === 'fr' ? 'Frais d\'admission' : 'Admission Fees',
        'scholarship_letter' => $lang === 'fr' ? 'Lettre de bourse' : 'Scholarship Letter',
        'bank_statement' => $lang === 'fr' ? 'Relevé bancaire' : 'Bank Statement',
    ];
}

function map_merged_student_fields_to_loan_form(array $merged): array
{
    $out = [];
    foreach (['first_name', 'last_name', 'email'] as $k) {
        if (!empty($merged[$k])) {
            $out[$k] = (string)$merged[$k];
        }
    }
    if (!empty($merged['gender'])) {
        $out['gender'] = (string)$merged['gender'];
    }
    if (!empty($merged['dob'])) {
        $out['dob'] = (string)$merged['dob'];
    }
    $phone = '';
    if (!empty($merged['area_code']) && !empty($merged['phone_number'])) {
        $phone = trim((string)$merged['area_code'] . ' ' . (string)$merged['phone_number']);
    } elseif (!empty($merged['phone_number'])) {
        $phone = (string)$merged['phone_number'];
    }
    if ($phone !== '') {
        $out['phone_number'] = $phone;
    }
    if (!empty($merged['address_line1'])) {
        $out['address1'] = (string)$merged['address_line1'];
    }
    if (!empty($merged['address_line2'])) {
        $out['address2'] = (string)$merged['address_line2'];
    }
    foreach (['city', 'postal_code'] as $k) {
        if (!empty($merged[$k])) {
            $out[$k] = (string)$merged[$k];
        }
    }
    if (!empty($merged['state_province'])) {
        $out['state'] = (string)$merged['state_province'];
    }

    return $out;
}

$debug = [
    'api_key_status' => 'unknown',
    'env_path' => $ENV_PATH,
    'log_file' => $LOG_FILE,
    'model' => $MODEL,
    'documents_received' => 0,
    'stages' => []
];

$applicationId = trim((string)($_POST['application_id'] ?? ''));
$loanSessionId = trim((string)($_SESSION['loan_user_id'] ?? ''));
if ($applicationId === '' || $loanSessionId === '' || !hash_equals($loanSessionId, $applicationId)) {
    $debug['api_key_status'] = 'not_checked';
    add_stage($debug, 'prepare', 'Loan form session mismatch.');
    json_exit([
        'status' => 'error',
        'message' => 'Your session does not match this application. Please reload the page.',
        'debug' => $debug
    ], 401);
}

if (!pcvc_docvision_autofill_ready()) {
    $debug['api_key_status'] = 'missing';
    add_stage($debug, 'prepare', 'No document AI API key in .env.');
    json_exit([
        'status' => 'error',
        'message' => 'AI document autofill is not configured. Set GEMINI_API_KEY and/or ANTHROPIC_API_KEY in .env.',
        'debug' => $debug
    ], 500);
}

$debug['api_key_status'] = 'configured';
$lang = (($_POST['lang'] ?? 'en') === 'fr') ? 'fr' : 'en';
$uploadedFiles = flatten_uploaded_files('documents');
$debug['documents_received'] = count($uploadedFiles);
add_stage($debug, 'prepare', 'Batch upload accepted.');

if (!$uploadedFiles) {
    json_exit([
        'status' => 'error',
        'message' => 'Please choose at least one document.',
        'debug' => $debug
    ], 400);
}

ensure_dir($TEMP_ROOT);

$loanAttachmentLabels = loan_attachment_labels($lang);

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

$systemPrompt = <<<PROMPT
You are an admissions document classification and extraction assistant.

Classify each uploaded document into exactly one of:
- valid_passport
- degree_transcripts
- high_school_degree
- cv_resume
- recommendation_letters
- personal_statement
- english_certificate
- birth_certificate
- payment_proof
- unknown

Rules:
1. Extract only applicant facts explicitly visible in the document.
2. Never invent data.
3. If the document mostly refers to someone other than the applicant, keep fields empty.
4. Recommendation letters may mention other people; only extract student data if it is clearly about the applicant.
5. Return country names, not codes.
6. When the document is a CV or resume, prioritize extracting the main contact block first: email, phone, address, city, nationality, and education institution details.
7. For CV/resume documents, if the phone is written locally but the country is explicit elsewhere in the same document, convert it to a full international number in phone_international.
8. Return the strongest real applicant email address visible in the document, not a school or company address unless it is clearly the applicant contact.
9. Ignore sample, placeholder, dummy, or template contact details.
10. Return JSON only.

JSON schema:
{
  "document_type": "valid_passport|degree_transcripts|high_school_degree|cv_resume|recommendation_letters|personal_statement|english_certificate|birth_certificate|payment_proof|unknown",
  "confidence": 0.0,
  "summary": "short summary",
  "fields": {
    "first_name": "",
    "last_name": "",
    "email": "",
    "phone_international": "",
    "dob": "",
    "gender": "",
    "passport_number": "",
    "student_national_id": "",
    "country_of_birth": "",
    "city_of_birth": "",
    "nationality": "",
    "second_nationality": "",
    "address_line1": "",
    "address_line2": "",
    "city": "",
    "state_province": "",
    "postal_code": "",
    "previous_institution_name": "",
    "previous_institution_city": "",
    "previous_institution_province": "",
    "previous_institution_country": "",
    "previous_institution_post_code": "",
    "previous_study_start": "",
    "previous_study_graduation": "",
    "language_of_instruction": "",
    "father_first_name": "",
    "father_last_name": "",
    "mother_first_name": "",
    "mother_last_name": ""
  }
}
PROMPT;

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
        $fileNameHint = strtolower($originalName);
        $docInstruction = 'Classify this document and extract applicant fields.';
        if (str_contains($fileNameHint, 'cv') || str_contains($fileNameHint, 'resume')) {
            $docInstruction .= ' This file is likely a CV/resume, so prioritize extracting applicant email, phone, address, nationality, and education history.';
        } elseif (str_contains($fileNameHint, 'passport')) {
            $docInstruction .= ' This file may be a passport, so prioritize legal identity fields like first name, last name, date of birth, nationality, and passport number.';
        } elseif (str_contains($fileNameHint, 'transcript') || str_contains($fileNameHint, 'degree')) {
            $docInstruction .= ' This file may be an academic record, so prioritize previous institution, study dates, and language of instruction.';
        }
        $content = pcvc_docvision_build_api_only_content(
            $tempPath,
            $originalName,
            $cleanup,
            $docInstruction,
            3,
            168
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

if ($jobs !== []) {
    add_stage($debug, 'ai', 'Sending ' . (string)count($jobs) . ' document(s) via API (Gemini/Claude).');
    $apiRequests = array_map(static fn(array $job): array => [
        'system' => $job['system'],
        'user' => $job['user'],
    ], $jobs);
    $responses = pcvc_docvision_analyze_parallel($apiRequests);

    foreach ($jobs as $idx => $job) {
        $originalName = $job['original_name'];
        $clientIndex = $job['client_index'];
        cleanup_paths($job['cleanup']);

        $response = $responses[$idx] ?? ['error' => ['message' => 'No API response']];
        if (isset($response['error'])) {
            $warnings[] = $originalName . ': ' . (string)($response['error']['message'] ?? 'AI extraction failed.');
            continue;
        }

        try {
            $ai = $response['json'] ?? [];
            if (!$ai || empty($ai['document_type'])) {
                throw new RuntimeException('AI returned an invalid extraction result.');
            }

            $documentType = (string)$ai['document_type'];
            $confidence = max(0.0, min(1.0, (float)($ai['confidence'] ?? 0)));
            $summary = normalize_text((string)($ai['summary'] ?? ''));

            if (!array_key_exists($documentType, $fieldLabels) || $confidence < 0.45) {
                $documents[] = [
                    'client_index' => $clientIndex,
                    'original_name' => $originalName,
                    'field' => '',
                    'field_label' => '',
                    'confidence' => $confidence,
                    'summary' => $summary
                ];
                $warnings[] = $originalName . ': the document could not be matched confidently to a supported attachment field.';
                continue;
            }

            $targetField = map_student_document_to_loan_attachment($documentType);
            if ($targetField === '' || !array_key_exists($targetField, $loanAttachmentLabels)) {
                $documents[] = [
                    'client_index' => $clientIndex,
                    'original_name' => $originalName,
                    'field' => '',
                    'field_label' => '',
                    'confidence' => $confidence,
                    'summary' => $summary
                ];
                $warnings[] = $originalName . ': this document type is not used on the loan application form.';
                continue;
            }

            $normalized = normalize_fields((array)($ai['fields'] ?? []), $lang, $conn);
            merge_candidate_fields($mergedFields, $fieldScores, $normalized, $documentType, $confidence);

            $documents[] = [
                'client_index' => $clientIndex,
                'original_name' => $originalName,
                'field' => $targetField,
                'field_label' => $loanAttachmentLabels[$targetField],
                'confidence' => $confidence,
                'summary' => $summary
            ];
            add_stage($debug, 'parse', 'Parsed ' . $originalName . ' as ' . $documentType . ' -> ' . $targetField . '.');
        } catch (Throwable $e) {
            $warnings[] = $originalName . ': ' . $e->getMessage();
        }
    }
}

file_put_contents(
    $LOG_FILE,
    "\n=== " . date('Y-m-d H:i:s') . " ===\nBATCH AUTOFILL DEBUG\n" . json_encode([
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

add_stage($debug, 'save', 'Batch analysis completed successfully.');
$uploadToken = bin2hex(random_bytes(16));
$_SESSION['smart_autofill_batch_upload_token_loan'] = $uploadToken;
$_SESSION['smart_autofill_batch_upload_token_loan_expires'] = time() + 900;
json_exit([
    'status' => 'success',
    'message' => 'Documents analyzed successfully.',
    'fields' => map_merged_student_fields_to_loan_form($mergedFields),
    'documents' => $documents,
    'warnings' => $warnings,
    'upload_token' => $uploadToken,
    'debug' => $debug
]);
