<?php
/**
 * 🎓 Budapest Winter School — Final Application Saver (2025)
 * ---------------------------------------------------------------
 * ✅ 13 parameters matched exactly to DB schema
 * ✅ Handles accommodation
 * ✅ JSON-safe output (no HTML leaks)
 * ✅ Logs all actions
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/db.php';
/* ---------- HELPERS ---------- */
function send_json(array $arr, int $code = 200): void {
    http_response_code($code);
    header_remove('X-Powered-By');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function log_debug(string $label, $data = null): void {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir . '/budapest_debug.log';
    $msg = "[" . date('Y-m-d H:i:s') . "] $label";
    if ($data !== null) $msg .= " => " . (is_scalar($data) ? $data : print_r($data, true));
    $msg .= "\n";
    @file_put_contents($file, $msg, FILE_APPEND);
}

/* ---------- INPUT ---------- */
$full_name     = trim($_POST['full_name'] ?? '');
$email         = trim($_POST['email'] ?? '');
$phone         = trim($_POST['phone'] ?? '');
$accommodation = trim($_POST['accommodation'] ?? '');

if ($full_name === '' || $email === '') {
    send_json(['status'=>'error','message'=>'Missing applicant name or email.'],400);
}

/* ---------- REQUIRED FILES ---------- */
$requiredDocs = [
    'valid_passport','degree_certificate','transcripts',
    'cv_resume','passport_photo','payment_proof'
];
$missing = [];
foreach ($requiredDocs as $f) if (empty($_POST[$f])) $missing[] = $f;
if ($missing) {
    log_debug('MISSING FILES', $missing);
    send_json(['status'=>'error','message'=>'Please upload all required documents before submitting.','missing'=>$missing],400);
}

/* ---------- DUPLICATE CHECK ---------- */
$stmt = $conn->prepare("SELECT id FROM budapest_applications WHERE email=? LIMIT 1");
if (!$stmt) send_json(['status'=>'error','message'=>'DB prepare failed: '.$conn->error],500);
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    send_json(['status'=>'error','message'=>'This email has already been used to apply.']);
}
$stmt->close();

/* ---------- COLLECT DATA ---------- */
$ai_name_detected = $_POST['ai_name_detected'] ?? null;
$ai_summary       = $_POST['ai_summary'] ?? null;
$ai_confidence    = $_POST['ai_confidence'] ?? null;

$files = [];
foreach ($requiredDocs as $f) $files[$f] = trim($_POST[$f] ?? '');

/* ---------- PREPARE INSERT ---------- */
$sql = "INSERT INTO budapest_applications
(full_name, email, phone, accommodation,
 valid_passport, degree_certificate, transcripts,
 cv_resume, passport_photo, payment_proof,
 ai_name_detected, ai_summary, ai_confidence)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    log_debug('SQL PREPARE FAIL', $conn->error);
    send_json(['status'=>'error','message'=>'Database prepare failed: '.$conn->error],500);
}

/* 13 parameters → 13 types  */
$stmt->bind_param(
    "ssssssssssssd",
    $full_name,
    $email,
    $phone,
    $accommodation,
    $files['valid_passport'],
    $files['degree_certificate'],
    $files['transcripts'],
    $files['cv_resume'],
    $files['passport_photo'],
    $files['payment_proof'],
    $ai_name_detected,
    $ai_summary,
    $ai_confidence
);

if (!$stmt->execute()) {
    log_debug('SQL EXECUTE FAIL', $stmt->error);
    send_json(['status'=>'error','message'=>'Database execution failed: '.$stmt->error],500);
}

$insertId = $stmt->insert_id;
$stmt->close();
log_debug('APPLICATION INSERTED',['id'=>$insertId,'email'=>$email,'accommodation'=>$accommodation]);

/* ---------- EMAIL TRIGGER ---------- */
try {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $url = ($host === 'localhost')
        ? "http://localhost/scholarsyncglobal/send_budapest_email.php?email=" . urlencode($email)
        : "https://scholarsyncglobal.ca/send_budapest_email.php?email=" . urlencode($email);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>1,
        CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_SSL_VERIFYHOST=>false
    ]);
    @curl_exec($ch);
    @curl_close($ch);
    log_debug('EMAIL SENT', $email);
} catch (Throwable $e) {
    log_debug('EMAIL ERROR', $e->getMessage());
}

/* ---------- SUCCESS ---------- */
send_json([
    'status'=>'success',
    'message'=>'🎉 Application submitted successfully! A confirmation email has been sent.',
    'insert_id'=>$insertId
]);
?>
