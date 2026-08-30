<?php
/**
 * upload_file.php
 * UNIVERSAL FINAL VERSION (2025-10-22)
 * Supports PDF, images, DOCX → PDF.
 * Accepts English certificates confirming study in English.
 * Adds AI-based applicant name verification.
 * Works reliably on shared cPanel hosting.
 */

declare(strict_types=1);
ob_start();
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/application_draft.php';
// =========================================
// SESSION VALIDATION (MUST BE FIRST)
// =========================================
if (empty($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error','message'=>'User not authenticated']);
    exit;
}
// =========================================
// ATTACH TO EXISTING OR JUST-CREATED DRAFT
// =========================================
$sessionId = session_id();
$userId = trim((string)$_SESSION['user_id']);
$postedAppId = (int)($_POST['application_id'] ?? 0);

try {
    $draft = pcvc_ensure_application_draft($conn, $sessionId, $userId, $postedAppId);
    $appId = (int)$draft['application_id'];
} catch (Throwable $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'No active application draft found. Please start or continue your application first.',
        'debug'   => $e->getMessage()
    ]);
    exit;
}

if (!$appId) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'No active application draft found. Please start or continue your application first.'
    ]);
    exit;
}

$skipAiValidation = ($_POST['skip_ai_validation'] ?? '') === '1';
$batchUploadToken = trim((string)($_POST['smart_autofill_batch_token'] ?? ''));
$sessionBatchToken = trim((string)($_SESSION['smart_autofill_batch_upload_token'] ?? ''));
$sessionBatchExpiry = (int)($_SESSION['smart_autofill_batch_upload_token_expires'] ?? 0);
$trustedBatchUpload = $skipAiValidation
    && $batchUploadToken !== ''
    && $sessionBatchToken !== ''
    && $sessionBatchExpiry >= time()
    && hash_equals($sessionBatchToken, $batchUploadToken);

require_once __DIR__ . '/helpers/load_env.php';
require_once __DIR__ . '/helpers/document_vision_gemini.php';
pcvc_load_dotenv(__DIR__);

$ENV_PATH = __DIR__ . '/.env';
$LOG_FILE = __DIR__ . '/upload_debug.log';
$TEMP_DIR = __DIR__ . '/temp/';
$UPLOAD_DIR = __DIR__ . '/uploads/';
$MODEL = pcvc_docvision_model();
foreach ([$TEMP_DIR, $UPLOAD_DIR] as $dir)
    if (!is_dir($dir)) mkdir($dir, 0755, true);

// =========================================
// CONFIG (document OCR/validation uses GEMINI_API_KEY in .env)
// Batch routing after Smart AI analysis does not need to re-call Gemini.
// =========================================
if (!$trustedBatchUpload && !pcvc_docvision_is_configured()) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Document verification is not configured. Set GEMINI_API_KEY in .env on the server.',
        'debug'   => [
            'api_key_status' => 'missing',
            'env_path' => $ENV_PATH,
            'log_file' => $LOG_FILE,
            'model' => $MODEL
        ]
    ]);
    exit;
}

function appendDebugStage(array &$debug, string $stage, string $detail): void {
    $debug['stages'][] = [
        'stage' => $stage,
        'detail' => $detail,
        'time' => date('H:i:s')
    ];
}

function allowedAttachmentFields(): array {
    return [
        'degree_transcripts',
        'high_school_degree',
        'valid_passport',
        'recommendation_letters',
        'personal_statement',
        'cv_resume',
        'english_certificate',
        'birth_certificate',
        'payment_proof'
    ];
}

function multiAttachmentFields(): array {
    return [
        'degree_transcripts',
        'recommendation_letters'
    ];
}

function saveAttachmentToDraft(
    mysqli $conn,
    int $appId,
    string $field,
    string $tmpPath,
    string $uploadDir,
    string $fileName
): string {
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Upload directory is not available.');
    }

    $finalPath = $uploadDir . $fileName;
    if (!rename($tmpPath, $finalPath)) {
        throw new RuntimeException('Failed to finalize uploaded file');
    }

    $filePath = 'uploads/' . $fileName;
    $multiFileFields = multiAttachmentFields();

    if (in_array($field, $multiFileFields, true)) {
        $stmt = $conn->prepare("SELECT {$field} FROM student_applications WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException('Failed to load existing attachments.');
        }
        $stmt->bind_param('i', $appId);
        $stmt->execute();
        $stmt->bind_result($existing);
        $stmt->fetch();
        $stmt->close();

        $files = [];
        if (!empty($existing)) {
            $decoded = json_decode($existing, true);
            if (is_array($decoded)) {
                $files = $decoded;
            }
        }

        $files[] = $filePath;
        $json = json_encode($files, JSON_UNESCAPED_UNICODE);

        $stmt = $conn->prepare("UPDATE student_applications SET {$field} = ? WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException('Failed to save attachment.');
        }
        $stmt->bind_param('si', $json, $appId);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("UPDATE student_applications SET {$field} = ? WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException('Failed to save attachment.');
        }
        $stmt->bind_param('si', $filePath, $appId);
        $stmt->execute();
        $stmt->close();
    }

    return $filePath;
}

// =========================================
// BASIC VALIDATION
// =========================================
if (empty($_SESSION['user_id']))
    exit(json_encode(['status'=>'error','message'=>'User not authenticated']));
if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK)
    exit(json_encode(['status'=>'error','message'=>'File upload failed or missing']));

$field = $_POST['field'] ?? 'unknown';
$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name'] ?? '');
$fullName  = trim("$firstName $lastName");
$lang = (($_POST['lang'] ?? 'en') === 'fr') ? 'fr' : 'en';

// =========================================
// EXPECTED DOCUMENT TYPE
// =========================================
$expectedTypes = [
    'degree_transcripts'     => 'university degree or transcript',
    'high_school_degree'     => 'high school diploma or certificate',
    'valid_passport'         => 'passport or ID page',
    'personal_statement'     => 'personal statement or motivation letter',
    'cv_resume'              => 'curriculum vitae or resume',
    'english_certificate'    => 'English proficiency certificate (accept if document confirms instruction in English)',
    'birth_certificate'      => 'birth certificate or national ID',
    'recommendation_letters' => 'recommendation or reference letter',
    'payment_proof'          => 'payment receipt or transaction proof'
];
$expectedType = $expectedTypes[$field] ?? 'academic or identification document';
$debug = [
    'api_key_status' => 'configured',
    'env_path' => $ENV_PATH,
    'log_file' => $LOG_FILE,
    'model' => $MODEL,
    'field' => $field,
    'expected_type' => $expectedType,
    'file_name' => $_FILES['file']['name'] ?? '',
    'mime' => '',
    'processing_mode' => '',
    'detected_type' => '',
    'confidence' => null,
    'stages' => []
];
appendDebugStage($debug, 'prepare', 'Draft found and upload accepted.');
$allowedFileFields = allowedAttachmentFields();

// =========================================
// SAVE TEMP FILE LOCALLY
// =========================================
$fileName = time().'_'.preg_replace('/[^A-Za-z0-9.\-_]/','_',$_FILES['file']['name']);
$tmpPath  = $TEMP_DIR.$fileName;
if (!move_uploaded_file($_FILES['file']['tmp_name'],$tmpPath))
    exit(json_encode([
        'status'=>'error',
        'message'=>'Cannot save uploaded file',
        'debug' => $debug
    ]));
appendDebugStage($debug, 'prepare', 'Temporary file saved on server.');

if ($trustedBatchUpload) {
    if (!in_array($field, $allowedFileFields, true)) {
        @unlink($tmpPath);
        exit(json_encode([
            'status' => 'error',
            'message' => 'Unsupported attachment field for batch save.',
            'debug' => $debug
        ], JSON_UNESCAPED_UNICODE));
    }

    try {
        $filePath = saveAttachmentToDraft($conn, (int)$appId, $field, $tmpPath, $UPLOAD_DIR, $fileName);
        appendDebugStage($debug, 'save', 'Attachment saved from smart autofill batch without re-running AI validation.');
        echo json_encode([
            'status' => 'success',
            'file_path' => $filePath,
            'confidence' => null,
            'summary' => '',
            'name_detected' => '',
            'name_match' => null,
            'autofill_fields' => [],
            'debug' => $debug,
            'message' => 'Document attached from smart autofill batch.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        @unlink($tmpPath);
        exit(json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'debug' => $debug
        ], JSON_UNESCAPED_UNICODE));
    }
}

$mime = mime_content_type($tmpPath) ?: 'application/octet-stream';
$ext  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$debug['mime'] = $mime;
// Detect scanned PDF (no text layer)
$isPdf = ($mime === 'application/pdf');
$isScannedPdf = false;

if ($isPdf) {
    $sample = file_get_contents($tmpPath, false, null, 0, 5000);
    if (!preg_match('/[A-Za-z]{4,}/', $sample)) {
        $isScannedPdf = true;
    }
}

function scannedPdfToImages(string $pdfPath, string $outDir): array {
    if (!class_exists('Imagick')) {
        exit(json_encode([
            'status'=>'error',
            'message'=>'Scanned PDF detected but Imagick is not available on server'
        ]));
    }

    $images = [];
    $im = new Imagick();
    $im->setResolution(200, 200);
    $im->readImage($pdfPath);

    foreach ($im as $i => $page) {
        $page->setImageFormat('jpeg');
        $page->setImageCompressionQuality(90);
        $out = $outDir . 'page_' . ($i+1) . '.jpg';
        $page->writeImage($out);
        $images[] = $out;
    }

    $im->clear();
    return $images;
}

function normalizeAutofillText(?string $value): string {
    $value = trim((string)$value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim((string)$value);
}

function normalizeAutofillDate(?string $value): string {
    $value = normalizeAutofillText($value);
    if ($value === '') {
        return '';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return '';
    }

    $date = date('Y-m-d', $ts);
    if ($date < '1900-01-01' || $date > date('Y-m-d', strtotime('+1 day'))) {
        return '';
    }

    return $date;
}

function normalizeAutofillGender(?string $value, string $lang = 'en'): string {
    $value = strtolower(normalizeAutofillText($value));
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

function normalizeAutofillLanguage(?string $value, string $lang = 'en'): string {
    $value = strtolower(normalizeAutofillText($value));
    if ($value === '') {
        return '';
    }

    $english = ['english', 'anglais'];
    $french = ['french', 'francais', 'français'];
    $other = ['other', 'autre'];

    if (in_array($value, $english, true)) {
        return $lang === 'fr' ? 'Anglais' : 'English';
    }

    if (in_array($value, $french, true)) {
        return $lang === 'fr' ? 'Français' : 'French';
    }

    if (in_array($value, $other, true)) {
        return $lang === 'fr' ? 'Autre' : 'Other';
    }

    return '';
}

function normalizeAutofillEmail(?string $value): string {
    $value = strtolower(normalizeAutofillText($value));
    if ($value === '' || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    [$local] = explode('@', $value, 2);
    $genericLocals = [
        'info', 'contact', 'admin', 'office', 'admission', 'admissions',
        'apply', 'application', 'support', 'help', 'registrar', 'enquiry',
        'enquiries', 'inquiry', 'hello'
    ];

    return in_array($local, $genericLocals, true) ? '' : $value;
}

function normalizeAutofillPhone(?string $value): array {
    $value = normalizeAutofillText($value);
    if ($value === '') {
        return ['area_code' => '', 'phone_number' => ''];
    }

    $hasPlus = str_starts_with($value, '+');
    $digits = preg_replace('/\D+/', '', $value);
    if ($digits === null || $digits === '') {
        return ['area_code' => '', 'phone_number' => ''];
    }

    if ($hasPlus && preg_match('/^\+(\d{1,4})/', $value, $m)) {
        $areaDigits = $m[1];
        $phoneDigits = substr($digits, strlen($areaDigits));
        if ($phoneDigits !== false && $phoneDigits !== '') {
            return [
                'area_code' => '+' . $areaDigits,
                'phone_number' => $phoneDigits
            ];
        }
    }

    return ['area_code' => '', 'phone_number' => ''];
}

function buildAutofillFields(array $fields, string $lang = 'en'): array {
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
        'language_of_instruction',
        'father_first_name',
        'father_last_name',
        'mother_first_name',
        'mother_last_name'
    ];

    foreach ($stringFields as $name) {
        $value = normalizeAutofillText($fields[$name] ?? '');
        if ($value !== '') {
            $normalized[$name] = $value;
        }
    }

    if (!empty($normalized['email'])) {
        $normalized['email'] = normalizeAutofillEmail($normalized['email']);
        if ($normalized['email'] === '') {
            unset($normalized['email']);
        }
    }

    if (!empty($fields['gender'])) {
        $gender = normalizeAutofillGender((string)$fields['gender'], $lang);
        if ($gender !== '') {
            $normalized['gender'] = $gender;
        }
    }

    if (!empty($fields['language_of_instruction'])) {
        $language = normalizeAutofillLanguage((string)$fields['language_of_instruction'], $lang);
        if ($language !== '') {
            $normalized['language_of_instruction'] = $language;
        }
    }

    foreach (['dob', 'previous_study_start', 'previous_study_graduation'] as $dateField) {
        $date = normalizeAutofillDate($fields[$dateField] ?? '');
        if ($date !== '') {
            $normalized[$dateField] = $date;
        }
    }

    if (!empty($normalized['passport_number'])) {
        $normalized['passport_number'] = strtoupper(preg_replace('/\s+/', '', $normalized['passport_number']));
    }

    if (!empty($normalized['student_national_id'])) {
        $normalized['student_national_id'] = strtoupper($normalized['student_national_id']);
    }

    $phone = normalizeAutofillPhone($fields['phone_international'] ?? '');
    if ($phone['area_code'] !== '' && $phone['phone_number'] !== '') {
        $normalized['area_code'] = $phone['area_code'];
        $normalized['phone_number'] = $phone['phone_number'];
    }

    return $normalized;
}

// =========================================
// STEP 1️⃣  PREPARE BASED ON TYPE
// =========================================
$fileId = null;
$content = [];
$isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'tiff']);
$isScannedPdfImages = [];


if ($isImage) {
    $debug['processing_mode'] = 'image_ocr';
    appendDebugStage($debug, 'extract', 'Image detected, preparing inline OCR payload.');
    // ---------- IMAGE HANDLING ----------
    $info = @getimagesize($tmpPath);
    if (!$info) exit(json_encode(['status'=>'error','message'=>'Unreadable or unsupported image file']));

    [$w, $h] = $info;
    $src = match($ext) {
        'png'   => imagecreatefrompng($tmpPath),
        'webp'  => imagecreatefromwebp($tmpPath),
        'bmp'   => imagecreatefrombmp($tmpPath),
        'tiff'  => @imagecreatefromstring(file_get_contents($tmpPath)),
        default => imagecreatefromjpeg($tmpPath),
    };
    if (!$src) exit(json_encode(['status'=>'error','message'=>'Failed to read image data']));

    // Resize if large (improves OCR)
    $maxW = 1200;
    if ($w > $maxW) {
        $ratio = $maxW / $w;
        $newW = $maxW;
        $newH = (int)($h * $ratio);
        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagejpeg($dst, $tmpPath, 90);
        imagedestroy($dst);
    }
    imagedestroy($src);

    // No API upload needed for OCR — encode base64 inline
    $imageData = base64_encode(file_get_contents($tmpPath));
    $dataUrl = 'data:' . $mime . ';base64,' . $imageData;

    file_put_contents($LOG_FILE, "\n✅ Image ready (base64 embedded): $fileName\n", FILE_APPEND);
}

elseif ($ext === 'docx') {
    $debug['processing_mode'] = 'docx_text_extract';
    appendDebugStage($debug, 'extract', 'DOCX detected, extracting text for AI.');
    // ---------- DOCX → PDF ----------
    $zip = new ZipArchive;
    if ($zip->open($tmpPath) === TRUE) {
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!$xml) exit(json_encode(['status'=>'error','message'=>'Empty DOCX']));
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($xml)));

        $pdfPath = $TEMP_DIR . pathinfo($fileName, PATHINFO_FILENAME) . '.pdf';
        $escaped = str_replace(['(', ')'], '', $text);
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
             . "2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n"
             . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R>>endobj\n"
             . "4 0 obj<</Length " . strlen($escaped) . ">>stream\nBT /F1 12 Tf 72 720 Td ($escaped) Tj ET\nendstream\nendobj\n"
             . "xref\n0 5\n0000000000 65535 f \ntrailer<</Size 5/Root 1 0 R>>\nstartxref\n0\n%%EOF";
        file_put_contents($pdfPath, $pdf);

        $tmpPath = $pdfPath;
        $fileName = basename($pdfPath);
        $mime = 'application/pdf';
        $isPdf = true;
        $isScannedPdf = false;
    } else {
        exit(json_encode(['status'=>'error','message'=>'Failed to read DOCX']));
    }

    file_put_contents($LOG_FILE, "\n✅ DOCX converted to PDF for Gemini: $fileName\n", FILE_APPEND);
}

elseif ($isPdf && $isScannedPdf) {
    $debug['processing_mode'] = 'scanned_pdf_ocr';
    // ---------- SCANNED PDF (PREPARE FOR OCR IN STEP 2) ----------

    // Convert PDF pages to images
    $isScannedPdfImages = scannedPdfToImages($tmpPath, $TEMP_DIR);

    // Mark as handled; content will be built later
    $fileId = null;

    file_put_contents(
        $LOG_FILE,
        "\n🧠 Scanned PDF prepared for OCR (" . count($isScannedPdfImages) . " pages): $fileName\n",
        FILE_APPEND
    );
    appendDebugStage($debug, 'extract', 'Scanned PDF converted to images for OCR.');
}
 else {
    $debug['processing_mode'] = 'text_pdf_inline';
    appendDebugStage($debug, 'extract', 'Text PDF detected, preparing inline PDF for Gemini.');
    file_put_contents($LOG_FILE, "\n✅ Text PDF ready for Gemini: $fileName\n", FILE_APPEND);
}


// =========================================
// STEP 2️⃣ PROMPTS (with Name Check)
// =========================================
$systemPrompt = <<<PROMPT
You are a document validation AI for university admissions screening.

Your task is to determine whether a document appears to be a valid and plausible official document
based on its internal consistency, structure, formatting, stamps, signatures, and content.

Do NOT require online verification, QR codes, cryptographic security features, or external databases.
A document may be considered valid even if its authenticity cannot be externally verified,
as long as it appears officially formatted and internally consistent for the stated institution and country.

If you receive an image, perform OCR-style text extraction.
If you receive a PDF, analyze its text content or visual structure.

Return ONLY JSON in this format:
{
  "valid": true or false,
  "detected_type": "string",
  "confidence": 0.0-1.0,
  "summary": "1–3 short sentences summarizing the document",
  "name_detected": "string",
  "name_match": true or false,
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


if ($isImage || $isScannedPdf || $isPdf) {
    appendDebugStage($debug, 'ai', 'Preparing AI request payload.');

    $nameInstruction = $fullName
        ? " Detect the main full name appearing in the document. Compare it to '{$fullName}'. "
          . "If they refer to the same person, set name_match=true; otherwise false. "
        : "";

    // -------------------------------
    // FIELD-SPECIFIC PROMPTS
    // -------------------------------
    if ($field === 'english_certificate') {

        $userPrompt =
            "Determine whether this document can serve as valid English proficiency proof. "
          . "Accept both official test certificates and academic certificates or letters "
          . "explicitly confirming that the language of instruction was English. "
          . "Also extract any clearly visible applicant personal details into the fields object. "
          . $nameInstruction;

    } elseif ($field === 'cv_resume') {

        $userPrompt =
            "Determine whether this document is a genuine Curriculum Vitae (CV) or Resume. "
          . "ACCEPT ONLY documents that clearly list employment history, job titles, "
          . "professional experience, internships, skills, or work responsibilities "
          . "in a standard CV or resume format. "
          . "REJECT English proficiency certificates, academic confirmation letters, "
          . "transcripts, diplomas, recommendation letters, passports, or personal statements. "
          . "If the document does not clearly look like a CV or resume, set valid=false. "
          . "Also extract any clearly visible applicant personal details into the fields object. "
          . "Prioritize the applicant contact block first: email, phone, address, city, nationality, and education history. "
          . "If the phone is shown locally but the country is explicit elsewhere in the same CV, convert it to a full international number in phone_international. "
          . "Ignore placeholder, sample, recruiter, school, or company contact details unless they clearly belong to the applicant. "
          . $nameInstruction;

    } else {

        $userPrompt =
            "Verify whether this document is a valid {$expectedType}. "
          . "Analyze content, structure, formatting, consistency, stamps, and signatures. "
          . "Do NOT require online or external verification. "
          . "Also extract any clearly visible applicant personal details into the fields object. "
          . $nameInstruction;
    }


 // ✅ Correct payload for image, scanned PDF, or text PDF
if ($isImage) {

    $content = [
        ["type" => "input_text", "text" => $userPrompt],
        ["type" => "input_image", "image_url" => $dataUrl]
    ];

} elseif ($isScannedPdf) {

    $content = [
        ["type" => "input_text", "text" => $userPrompt]
    ];

    foreach ($isScannedPdfImages as $img) {
        $b64 = base64_encode(file_get_contents($img));
        $content[] = [
            "type" => "input_image",
            "image_url" => "data:image/jpeg;base64,$b64"
        ];
    }

} elseif ($isPdf && !$isScannedPdf) {

    $pdfData = base64_encode((string)file_get_contents($tmpPath));
    $content = [
        ["type" => "input_text", "text" => $userPrompt],
        ["type" => "input_pdf", "mime" => "application/pdf", "data" => $pdfData],
    ];
}

}

// =========================================
// STEP 3️⃣ API CALL (Gemini)
// =========================================
appendDebugStage($debug, 'ai', 'Sending request to Gemini.');
$geminiResult = pcvc_docvision_generate_json($systemPrompt, $content);
$data = $geminiResult;

// =========================================
// STEP 4️⃣ LOG & PARSE
// =========================================
file_put_contents(
  $LOG_FILE,
  "\n=== ".date('Y-m-d H:i:s')." ===\nField:$field\nApplicant:$fullName\nFile:$fileName\nResponse:\n".json_encode($data,JSON_PRETTY_PRINT)."\n",
  FILE_APPEND
);
if (isset($data['error'])) {
    appendDebugStage($debug, 'ai', 'Gemini request failed: ' . ($data['error']['message'] ?? 'unknown error'));
    exit(json_encode([
        'status'=>'error',
        'message'=>$data['error']['message'],
        'debug' => $debug
    ]));
}
appendDebugStage($debug, 'ai', 'Gemini response received.');

$ai = $data['json'] ?? [];
$aiText = $data['raw_text'] ?? '';
if (!$ai || !isset($ai['valid']))
    exit(json_encode([
        'status'=>'error',
        'message'=>'Invalid AI response',
        'debug'=>array_merge($debug, [
            'response_preview' => substr($aiText ?: json_encode($data),0,400)
        ])
    ]));
appendDebugStage($debug, 'parse', 'AI JSON parsed successfully.');

$autofillFields = buildAutofillFields((array)($ai['fields'] ?? []), $lang);
$debug['detected_type'] = $ai['detected_type'] ?? '';
$debug['confidence'] = $ai['confidence'] ?? null;
appendDebugStage($debug, 'parse', 'Autofill fields prepared: ' . count($autofillFields));

// =========================================
// STEP 5️⃣ FINAL DECISION + SAVE (SAFE)
// =========================================

// Fields that allow MULTIPLE files
$multiFileFields = multiAttachmentFields();

// -----------------------------------------
// 1️⃣ Decide if document is allowed to save
// -----------------------------------------
$shouldSave = false;

// 🚀 Payment proof always allowed
if ($field === 'payment_proof') {
    $shouldSave = true;
}

// 🔐 All other documents must pass AI validation
elseif ($ai['valid'] === true) {

    // Name mismatch → reject
    if (isset($ai['name_match']) && $ai['name_match'] === false) {

        unlink($tmpPath);

        echo json_encode([
            'status'  => 'error',
            'message' =>
                "⚠️ Name mismatch: found '{$ai['name_detected']}', expected '{$fullName}'.",
            'debug' => $debug
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $shouldSave = true;
}

// -----------------------------------------
// 2️⃣ Save ONLY if allowed
// -----------------------------------------
if ($shouldSave && $appId && in_array($field, $allowedFileFields, true)) {
    try {
        $filePath = saveAttachmentToDraft($conn, (int)$appId, $field, $tmpPath, $UPLOAD_DIR, $fileName);
    } catch (Throwable $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage(),
            'debug' => $debug
        ]);
        exit;
    }
    appendDebugStage($debug, 'save', 'Attachment saved to application draft.');

    // ✅ SUCCESS RESPONSE
    echo json_encode([
        'status'        => 'success',
        'file_path'     => $filePath,
        'confidence'    => $ai['confidence'] ?? null,
        'summary'       => $ai['summary'] ?? '',
        'name_detected' => $ai['name_detected'] ?? '',
        'name_match'    => $ai['name_match'] ?? null,
        'autofill_fields' => $autofillFields,
        'debug'         => $debug,
        'message'       =>
            "✅ Verified as {$ai['detected_type']} "
          . "(expected {$expectedType}). "
          . "Name confirmed."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// -----------------------------------------
// 3️⃣ Invalid document → cleanup & exit
// -----------------------------------------
unlink($tmpPath);

echo json_encode([
    'status'  => 'error',
    'message' => "❌ Not a valid {$expectedType}",
    'debug' => $debug
], JSON_UNESCAPED_UNICODE);
exit;

