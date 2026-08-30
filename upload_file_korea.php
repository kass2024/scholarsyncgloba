<?php
/**
 * upload_file_korea.php
 * FULL PRODUCTION-HARDENED VERSION
 */

declare(strict_types=1);
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
/* =====================================================
   AUTHENTICATION
===================================================== */
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'User not authenticated'
    ]);
    exit;
}

/* =====================================================
   FIND ACTIVE DRAFT APPLICATION
===================================================== */
$sessionId = session_id();

$stmt = $conn->prepare("
    SELECT id
    FROM student_applications
    WHERE session_id = ?
      AND submitted = 0
    LIMIT 1
");
$stmt->bind_param('s', $sessionId);
$stmt->execute();
$stmt->bind_result($applicationId);
$stmt->fetch();
$stmt->close();

if (!$applicationId) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'No active application draft found'
    ]);
    exit;
}

/* =====================================================
   CONFIGURATION
===================================================== */
$UPLOAD_DIR = __DIR__ . '/uploads/';
$MAX_FILE_SIZE = 10 * 1024 * 1024;

$ALLOWED_EXTENSIONS = ['pdf','jpg','jpeg','png','docx'];
$ALLOWED_MIME_TYPES = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Upload directory unavailable']);
    exit;
}

/* =====================================================
   ALLOWED FIELDS (DOUBLE-LOCKED)
===================================================== */
$ALLOWED_FIELDS = [
    'korean_photo_uploaded',
    'valid_passport',
    'final_certificate_uploaded',
    'final_transcript_uploaded',
    'translator_confirmation_uploaded',
    'parent_income_statement_uploaded',
    'parent_employment_certificate_uploaded',
    'parent_business_certificate_uploaded',
    'bank_balance_certificate_uploaded',
    'applicant_id_uploaded',
    'father_id_uploaded',
    'mother_id_uploaded',
    'birth_certificate_translated_uploaded',
    'self_introduction_letter_uploaded',
    'study_plan_uploaded',
    'personal_information_consent_uploaded'
];

$MULTI_FILE_FIELDS = ['final_transcript_uploaded'];

/* =====================================================
   FILE INPUT VALIDATION
===================================================== */
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Upload failed']);
    exit;
}

$field = $_POST['field'] ?? '';

if (!in_array($field, $ALLOWED_FIELDS, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid document field']);
    exit;
}

$tmpPath  = $_FILES['file']['tmp_name'];
$fileSize = $_FILES['file']['size'];
$origName = $_FILES['file']['name'];
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if (!in_array($ext, $ALLOWED_EXTENSIONS, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Unsupported file type']);
    exit;
}

if ($fileSize > $MAX_FILE_SIZE) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'File exceeds size limit']);
    exit;
}

/* MIME validation */
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($tmpPath);

if (!in_array($mime, $ALLOWED_MIME_TYPES, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid file content']);
    exit;
}

/* =====================================================
   SAFE FILE STORAGE
===================================================== */
$filename     = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$absolutePath = $UPLOAD_DIR . $filename;
$relativePath = 'uploads/' . $filename;

if (!move_uploaded_file($tmpPath, $absolutePath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to store file']);
    exit;
}

/* =====================================================
   DATABASE TRANSACTION (ATOMIC)
===================================================== */
$conn->begin_transaction();

try {

    if (in_array($field, $MULTI_FILE_FIELDS, true)) {

        $stmt = $conn->prepare("SELECT {$field} FROM student_applications WHERE id = ?");
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $stmt->bind_result($existingJson);
        $stmt->fetch();
        $stmt->close();

        $files = [];
        if ($existingJson) {
            $decoded = json_decode($existingJson, true);
            if (is_array($decoded)) {
                $files = $decoded;
            }
        }

        $files[] = $relativePath;
        $json = json_encode($files, JSON_UNESCAPED_UNICODE);

        $stmt = $conn->prepare("
            UPDATE student_applications
            SET {$field} = ?
            WHERE id = ?
        ");
        $stmt->bind_param('si', $json, $applicationId);
        $stmt->execute();

    } else {

        $stmt = $conn->prepare("
            UPDATE student_applications
            SET {$field} = ?
            WHERE id = ?
        ");
        $stmt->bind_param('si', $relativePath, $applicationId);
        $stmt->execute();
    }

    if ($stmt->affected_rows !== 1) {
        throw new Exception('Database update failed');
    }

    $stmt->close();
    $conn->commit();

} catch (Throwable $e) {

    $conn->rollback();
    if (file_exists($absolutePath)) {
        unlink($absolutePath);
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Upload failed',
    ]);
    exit;
}

/* =====================================================
   FINAL SUCCESS RESPONSE (JS-LOCKED)
===================================================== */
echo json_encode([
    'status'     => 'success',
    'message'    => 'Upload completed',
    'field'      => $field,
    'file_path'  => $relativePath
], JSON_UNESCAPED_UNICODE);
