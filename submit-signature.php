<?php
declare(strict_types=1);

ob_start(); // 🔑 CRITICAL: buffer all output
require_once __DIR__ . '/db.php';
require_once __DIR__ . "/vendor/autoload.php";

header("Content-Type: application/json");


/* =====================================================
   CONFIG
===================================================== */
$LOG_FILE = __DIR__ . "/logs/contract-signing.log";

/* =====================================================
   HELPERS
===================================================== */
function logMsg(string $msg, array $data = []): void {
    global $LOG_FILE;

    if (!is_dir(dirname($LOG_FILE))) {
        mkdir(dirname($LOG_FILE), 0777, true);
    }

    file_put_contents(
        $LOG_FILE,
        "[" . date("Y-m-d H:i:s") . "] {$msg} " . json_encode($data) . PHP_EOL,
        FILE_APPEND
    );
}

function respond(array $payload, int $code = 200): void {
    // Capture any accidental output before cleaning
    $accidentalOutput = ob_get_contents();
    if (!empty($accidentalOutput)) {
        // Log any accidental output for debugging
        error_log("ACCIDENTAL OUTPUT BEFORE JSON: " . $accidentalOutput);
    }
    
    ob_clean(); // 🔑 remove ANY accidental output
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}


function fail(string $message, int $code = 400, array $debug = []): void {
    logMsg("FAIL: {$message}", $debug);
    respond([
        "success" => false,
        "error"   => $message
    ], $code);
}

/* =====================================================
   1. READ INPUT
===================================================== */
$raw  = file_get_contents("php://input");
$data = json_decode($raw, true);

logMsg("RAW INPUT", ["raw" => $raw]);

if (!is_array($data)) {
    fail("Invalid JSON payload", 400);
}

logMsg("PARSED DATA", ["data" => $data]);

/* =====================================================
   2. EXTRACT DATA
===================================================== */
/* --- CORE --- */
$token      = trim($data['token'] ?? '');
$name       = trim($data['student_name'] ?? '');
$signedDate = trim($data['signed_date'] ?? '');
$signature  = $data['signature'] ?? '';

/* --- ARTICLE 7 PACKAGE --- */
$pkgLabel = trim($data['selected_package_label'] ?? '');
$pkgCode  = trim($data['selected_package_code'] ?? '');

/* --- CLIENT / STUDENT --- */
$fullName    = trim($data['full_name'] ?? '');
$fullName    = $fullName !== '' ? $fullName : $name;
$name        = $name !== '' ? $name : $fullName;
$email       = trim($data['student_email'] ?? '');
$dob         = $data['student_dob'] ?? null;
$nationality = trim($data['student_nationality'] ?? '');
$passport    = trim($data['student_passport'] ?? '');
$phone       = trim($data['student_phone'] ?? '');
$clientTypes = is_array($data['client_type'] ?? null)
    ? implode(', ', $data['client_type'])
    : '';

logMsg("EXTRACTED VARIABLES", [
    "token" => $token,
    "name" => $name,
    "fullName" => $fullName,
    "email" => $email,
    "signedDate" => $signedDate,
    "pkgCode" => $pkgCode,
    "pkgLabel" => $pkgLabel,
    "dob" => $dob,
    "nationality" => $nationality,
    "passport" => $passport,
    "phone" => $phone,
    "pkgCodeEmpty" => empty($pkgCode),
    "pkgCodeValue" => var_export($pkgCode, true)
]);

/* =====================================================
   3. HARD VALIDATION
===================================================== */
if (
    $token === '' ||
    $fullName === '' ||
    $signedDate === '' ||
    $email === '' ||
    $signature === '' ||
    $pkgLabel === '' ||
    $pkgCode === ''
) {

    $missing = [];
    if ($token === '') $missing[] = 'token';
    if ($fullName === '') $missing[] = 'full_name or student_name';
    if ($signedDate === '') $missing[] = 'signed_date';
    if ($email === '') $missing[] = 'student_email';
    if ($signature === '') $missing[] = 'signature';
    if ($pkgLabel === '') $missing[] = 'selected_package_label';
    if ($pkgCode === '') $missing[] = 'selected_package_code';
    
    fail("Missing required fields: " . implode(', ', $missing), 400, ["missing" => $missing]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail("Invalid email address", 400);
}

if (!str_starts_with($signature, 'data:image/') || !str_contains($signature, 'base64,')) {
    fail("Invalid signature format", 400);
}

/* =====================================================
   4. LOAD CONTRACT (NO LOCK YET)
===================================================== */
$stmt = $conn->prepare("
    SELECT id, status, student_id
    FROM student_contracts
    WHERE contract_token = ?
    LIMIT 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contract) {
    fail("Contract not found", 404);
}

/* =====================================================
   5. ALREADY SIGNED = SUCCESS (IDEMPOTENT)
===================================================== */
if ($contract['status'] === 'signed') {
    respond([
        "success" => true,
        "status"  => "already_signed",
        "message" => "This contract has already been signed."
    ]);
}

$contractId = (int) $contract['id'];

/* =====================================================
   6. TRANSACTION
===================================================== */
$conn->begin_transaction();

try {

    /* =====================================================
       6.1 LOCK CONTRACT ROW
    ===================================================== */
    $stmt = $conn->prepare("
        SELECT id
        FROM student_contracts
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $contractId);
    $stmt->execute();
    $stmt->close();

    logMsg("Contract locked", ["contract_id" => $contractId]);

    /* =====================================================
       6.2 RESOLVE OR CREATE STUDENT - ENHANCED
    ===================================================== */
    $studentId = !empty($contract['student_id']) ? (int)$contract['student_id'] : 0;
    
    if ($studentId <= 0) {
        // Try to find existing student by email first
        $stmt = $conn->prepare("
            SELECT id, first_name, last_name, email, dob, nationality, passport_number, phone_number
            FROM student_applications
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($existing['id'])) {
            $studentId = (int)$existing['id'];
            logMsg("Found existing student", ["student_id" => $studentId, "email" => $email]);
        } else {
            // Create new student record with better validation
            $stmt = $conn->prepare("
                INSERT INTO student_applications
                (first_name, last_name, email, dob, nationality, passport_number, phone_number, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            // Split full name into first and last name with better handling
            $nameParts = explode(' ', trim($name), 2);
            $firstName = trim($nameParts[0] ?? '');
            $lastName = trim($nameParts[1] ?? '');
            
            // Handle case where only one name is provided
            if (empty($lastName)) {
                $lastName = 'Not provided';
            }
            
            // Validate required fields
            if (empty($firstName) || empty($email)) {
                throw new Exception("Missing required student information");
            }
            
            $stmt->bind_param("sssssss", 
                $firstName, 
                $lastName, 
                $email, 
                $dob, 
                $nationality, 
                $passport, 
                $phone
            );
            $stmt->execute();
            $studentId = $stmt->insert_id;
            $stmt->close();
            
            logMsg("Created new student record", ["student_id" => $studentId, "email" => $email, "name" => $name]);
        }
    }

    /* =====================================================
       6.3 SAVE SIGNATURE
    ===================================================== */
    $stmt = $conn->prepare("
        INSERT INTO student_signatures
        (contract_id, student_name, student_email, signed_date, signature_image, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param(
        "issss",
        $contractId,
        $name,
        $email,
        $signedDate,
        $signature
    );
    $stmt->execute();
    $stmt->close();

    /* =====================================================
       6.4 FINALIZE CONTRACT
    ===================================================== */
    $stmt = $conn->prepare("
        UPDATE student_contracts SET
            student_id             = ?,
            status                 = 'signed',
            signed_at              = NOW(),
            selected_package_code  = ?,
            selected_package_label = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        "isss",
        $studentId,
        $pkgCode,
        $pkgLabel,
        $contractId
    );
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    logMsg("Contract finalized", ["contract_id" => $contractId]);

} catch (Throwable $e) {
    $conn->rollback();
    fail("Signing failed", 500, [
        "message" => $e->getMessage(),
        "line"    => $e->getLine()
    ]);
}

/* =====================================================
   7. GENERATE PDF (POST-COMMIT)
===================================================== */
$pdfPath = null;
$pdfError = null;

try {
    require_once __DIR__ . '/generate-contract-pdf.php';

    if (!function_exists('generateContractPDF')) {
        throw new RuntimeException('PDF generator missing');
    }

    $pdfPath = generateContractPDF($contractId);

    if (!$pdfPath || !file_exists($pdfPath)) {
        throw new RuntimeException('PDF file was not created');
    }

    $stmt = $conn->prepare('UPDATE student_contracts SET pdf_path = ? WHERE id = ?');
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare pdf_path update');
    }
    $stmt->bind_param('si', $pdfPath, $contractId);
    $stmt->execute();
    $stmt->close();

    logMsg('PDF generated', ['contract_id' => $contractId, 'pdf_path' => $pdfPath]);
} catch (Throwable $e) {
    $pdfError = $e->getMessage();
    logMsg('PDF generation failed', [
        'contract_id' => $contractId,
        'message'     => $pdfError,
        'line'        => $e->getLine(),
        'file'        => $e->getFile(),
    ]);
}

/* =====================================================
   8. FINAL RESPONSE (ALWAYS JSON)
===================================================== */
respond([
    'success'      => true,
    'status'       => 'signed',
    'contract_id'  => $contractId,
    'student_id'   => $studentId,
    'pdf_path'     => $pdfPath,
    'pdf_error'    => $pdfError,
]);
