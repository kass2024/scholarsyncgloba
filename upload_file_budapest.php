<?php
/**
 * 🎓 upload_file_budapest.php
 * AI-Validated Uploader for 8-Days Budapest Winter School
 * ---------------------------------------------------------
 * ✅ Context-specific AI prompts per document type
 * ✅ Performs name detection & verification (except for passport photos)
 * ✅ Saves AI summary, name, and confidence to DB
 * ✅ Works on shared hosting (cPanel safe)
 */

declare(strict_types=1);
ob_start();
session_start();
require_once __DIR__ . '/helpers/env_bootstrap.php';
require_once __DIR__ . '/helpers/document_vision_gemini.php';
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

// =========================================
// CONFIG
// =========================================
if (!pcvc_docvision_is_configured()) {
    exit(json_encode(['status' => 'error', 'message' => 'Set GEMINI_API_KEY in .env for document validation.']));
}
$LOG_FILE = __DIR__ . '/upload_budapest_debug.log';
$TEMP_DIR = __DIR__ . '/temp/';
$UPLOAD_DIR = __DIR__ . '/uploads/';

foreach ([$TEMP_DIR, $UPLOAD_DIR] as $dir)
    if (!is_dir($dir)) mkdir($dir, 0755, true);

// =========================================
// BASIC VALIDATION
// =========================================
if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK)
    exit(json_encode(['status' => 'error', 'message' => 'File upload failed or missing']));

$field = $_POST['field'] ?? 'unknown';
$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name'] ?? '');
$fullName  = trim("$firstName $lastName");
$email     = trim($_POST['email'] ?? '');

// =========================================
// EXPECTED DOCUMENT TYPES
// =========================================
$expectedTypes = [
    'valid_passport'      => 'passport or ID page',
    'degree_certificate'  => 'degree certificate',
    'transcripts'         => 'academic transcript or grade report',
    'cv_resume'           => 'curriculum vitae or résumé',
    'passport_photo'      => 'passport-size photograph',
    'payment_proof'       => 'payment receipt or bank transfer proof'
];
$expectedType = $expectedTypes[$field] ?? 'academic or identification document';

// =========================================
// SAVE TEMP FILE
// =========================================
$fileName = time() . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '_', $_FILES['file']['name']);
$tmpPath  = $TEMP_DIR . $fileName;

if (!move_uploaded_file($_FILES['file']['tmp_name'], $tmpPath))
    exit(json_encode(['status' => 'error', 'message' => 'Cannot save uploaded file']));

$mime = mime_content_type($tmpPath) ?: 'application/octet-stream';
$ext  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// =========================================
// HANDLE IMAGE / PDF
// =========================================
$isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'tiff']);

if ($isImage) {
    $imageData = base64_encode(file_get_contents($tmpPath));
    $dataUrl = 'data:' . $mime . ';base64,' . $imageData;
} else {
    $pdfData = base64_encode((string)file_get_contents($tmpPath));
    $pdfInline = [
        'type' => 'input_pdf',
        'mime' => 'application/pdf',
        'data' => $pdfData,
    ];
}

// =========================================
// AI PROMPT CONSTRUCTION
// =========================================
$systemPrompt = <<<PROMPT
You are a strict document validation AI for university admissions.
Your role: identify whether the uploaded document is genuine, legible, and relevant.

If image: perform OCR text extraction.
If PDF: analyze textual and layout content.

Return ONLY JSON strictly matching this structure:
{
  "valid": true or false,
  "detected_type": "string",
  "confidence": 0.0-1.0,
  "summary": "1-3 short sentences summarizing document content and purpose",
  "name_detected": "string",
  "name_match": true or false
}
PROMPT;

$nameInstruction = $fullName
    ? "Extract the applicant’s name and compare with '{$fullName}'. If they refer to the same person, set name_match=true; otherwise false."
    : "";

// =========================================
// CUSTOM PROMPTS PER FIELD
// =========================================
switch ($field) {
    case 'degree_certificate':
        $userPrompt = "Determine if this document is an authentic academic degree certificate from a recognized institution. 
        Check for seals, signatures, university name, award title, and date of issue. 
        Confirm it grants an academic qualification such as Bachelor’s, Master’s, or PhD. 
        $nameInstruction";
        break;

    case 'transcripts':
        $userPrompt = "Analyze this document to confirm it is an official transcript or academic record. 
        Look for student name, institution name, course list, grades, GPA, and official formatting or signatures. 
        $nameInstruction";
        break;

    case 'cv_resume':
        $userPrompt = "Determine whether this document is a Curriculum Vitae (CV) or résumé. "
        . "A CV/resume is often self-authored and may be simple; do NOT require stamps/signatures. "
        . "ACCEPT if it plausibly matches CV/resume structure and includes multiple typical sections such as: "
        . "name/contact details, education, skills, experience (optional), projects, internships, certifications, "
        . "languages, references, or a professional summary. "
        . "Do NOT require employment history; fresh graduates may only list education + skills + projects. "
        . "REJECT if it is clearly another document type (passport/ID, transcript, diploma/certificate, "
        . "recommendation letter, personal statement, bank/payment proof, or English test certificate). "
        . "If uncertain but it plausibly looks like a CV/resume, prefer valid=true. "
        . $nameInstruction;
        break;

    case 'valid_passport':
        $userPrompt = "Check that this document is a valid passport or government-issued ID. 
        Verify presence of passport number, issuing authority, photograph, and full name. 
        $nameInstruction";
        break;

    case 'passport_photo':
        // No name check for photos
        $userPrompt = "
        Evaluate this uploaded image to confirm it is a photograph of a person.
        ✅ Accept any clear image showing a single human face or upper body, even if it's not strictly passport-style.
        ❌ Do not check or compare names, printed text, or ID information.
        🧠 Ignore all name detection, text, or watermark analysis completely.
        Mark the photo as VALID if it clearly depicts one person with reasonable lighting, visibility, and focus.
        Mark it as INVALID only if:
          - The image is blank, too dark, or too blurry,
          - It shows multiple people,
          - Or it clearly does not contain a human face.
        ";
        $nameInstruction = "";
        break;
case 'payment_proof':
    $userPrompt = "
    This document has been uploaded as *proof of payment* for the Budapest Winter School program.

    ✅ **Accepted document types:**
    - Bank transfer receipts or deposit confirmations (actual proof of payment).
    - Written or electronic *pledge forms* or *consent letters* confirming that the applicant 
      will pay at a later date (must include applicant’s name and email).

    ✅ **Validation Criteria:**
    A document can be considered valid if it satisfies **either condition (A)** or **condition (B)** below:

    **(A) Direct Payment Receipt (existing rule):**
    1️⃣ The document clearly contains the applicant’s full name: '{$fullName}' (or a very close match).
    2️⃣ It contains a visible payment amount in one of the following currencies:
       - **Euro (€):** between **€210 and €290**
       - **US Dollars ($):** between **$230 and $310**
       - **Rwandan Francs (RWF or FRW):** between **RWF 300,000 and RWF 440,000**
    
    ⚖️ **Decision Logic for (A):**
    - Mark **valid = true** only if *both* the name and one of these currency amounts appear.
    - Mark **valid = false** if:
        • The document does not include the applicant’s name, OR
        • No payment-related numeric value or currency symbol is detected.
    - Ignore all other details (institution name, stamps, formatting, etc.).

    **(B) Pledge or Consent-to-Pay Form (new rule):**
    The document should:
    - Include the applicant’s full name ('{$fullName}' or close match),
    - Include the applicant’s email address, and
    - Contain clear intent or agreement to pay later using phrases such as:
        “consent to pay later”, “agree to pay service fees”, “pledge to pay”, 
        “payment at a later date”, “acknowledge payment obligation”, etc.

    ⚖️ **Decision Logic for (B):**
    - Mark **valid = true** if such intent and applicant identifiers (name + email) appear together.
    - Mark **valid = false** if:
        • The name or email are missing, OR
        • No phrase or clause indicating payment intent is detected.

    🚫 **Do NOT accept**:
    - Academic documents (CVs, transcripts, certificates, etc.).
    - Random text or unrelated forms with no name, email, or payment intent/amount.

    💬 **Output expectation:**
    - If (A) satisfied → valid = true, detected_type = 'payment proof'
    - If (B) satisfied → valid = true, detected_type = 'payment pledge form'
    - If neither satisfied → valid = false, detected_type = describe the actual document (e.g., 'CV', 'photo', etc.)
    - confidence should represent how clearly the criteria were met.

    $nameInstruction
    ";
    break;


    default:
        $userPrompt = "Verify if this is a valid {$expectedType}. $nameInstruction";
        break;
}

// =========================================
// PREPARE AI INPUT
// =========================================
$content = $isImage
    ? [["type" => "input_text", "text" => $userPrompt], ["type" => "input_image", "image_url" => $dataUrl]]
    : [["type" => "input_text", "text" => $userPrompt], $pdfInline];

$data = pcvc_docvision_generate_json($systemPrompt, $content);

// =========================================
// LOG + PARSE
// =========================================
file_put_contents(
    $LOG_FILE,
    "\n=== " . date('Y-m-d H:i:s') . " ===\nField:$field\nEmail:$email\nFile:$fileName\nResponse:\n" .
        json_encode($data, JSON_PRETTY_PRINT) . "\n",
    FILE_APPEND
);

if (isset($data['error'])) {
    exit(json_encode(['status' => 'error', 'message' => $data['error']['message']]));
}

$ai = $data['json'] ?? [];
$aiText = $data['raw_text'] ?? '';

if (!$ai || !isset($ai['valid']))
    exit(json_encode(['status' => 'error', 'message' => 'Invalid AI response', 'debug' => substr($aiText ?: json_encode($data), 0, 400)]));

// =========================================
// SAVE FILE + UPDATE DB
// =========================================
$finalPath = $UPLOAD_DIR . $fileName;
rename($tmpPath, $finalPath);
$fileUrl = 'uploads/' . $fileName;

if (!empty($email)) {
    $stmt = $conn->prepare("UPDATE budapest_applications 
                            SET ai_name_detected=?, ai_summary=?, ai_confidence=? 
                            WHERE email=? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("ssds",
        $ai['name_detected'],
        $ai['summary'],
        $ai['confidence'],
        $email
    );
    $stmt->execute();
    $stmt->close();
}

// =========================================
// FINAL OUTPUT
// =========================================
$out = [
    'file_path' => $fileUrl,
    'confidence' => $ai['confidence'] ?? null,
    'summary' => $ai['summary'] ?? '',
    'name_detected' => $ai['name_detected'] ?? '',
    'name_match' => $ai['name_match'] ?? null
];

if ($ai['valid']) {

    // ✅ Skip name check for passport photo
    if ($field === 'passport_photo') {
        $out += [
            'status' => 'success',
            'message' => "✅ Valid photograph detected and accepted (no name check required)."
        ];
    }
    // All other fields perform name verification
    elseif (isset($ai['name_match']) && !$ai['name_match']) {
        $out += [
            'status' => 'error',
            'message' => "⚠️ Valid {$ai['detected_type']} detected but name mismatch: found '{$ai['name_detected']}', expected '{$fullName}'."
        ];
    } else {
        $out += [
            'status' => 'success',
            'message' => "✅ Verified as {$ai['detected_type']} (expected {$expectedType}). Name confirmed: '{$ai['name_detected']}'"
        ];
    }

} else {
    $out += ['status' => 'error', 'message' => "❌ Not a valid {$expectedType}"];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
?>
