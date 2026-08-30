<?php
declare(strict_types=1);

session_start();

/* =====================================================
   CORE INCLUDES
===================================================== */
require_once __DIR__ . '/db.php';
/* ===== PHPMailer (MANUAL LOAD) ===== */
require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";
require_once __DIR__ . "/PHPMailer/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* =====================================================
   AUTH & CSRF
===================================================== */
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit("Unauthorized access");
}

if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit("Invalid CSRF token");
}

if (!isset($_POST['contract_id']) || !ctype_digit($_POST['contract_id'])) {
    http_response_code(400);
    exit("Invalid request");
}

$contractId = (int) $_POST['contract_id'];

/* =====================================================
   LOAD CONTRACT
===================================================== */
$stmt = $conn->prepare("
    SELECT c.pdf_path, s.email, s.first_name, s.last_name
    FROM student_contracts_special c
    JOIN student_applications s ON s.id = c.student_id
    WHERE c.id = ? AND c.status = 'signed'
    LIMIT 1
");
$stmt->bind_param("i", $contractId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['pdf_path']) || !file_exists($row['pdf_path'])) {
    exit("Signed contract not found");
}

$studentEmail = trim($row['email']);
$studentName  = trim($row['first_name'] . ' ' . $row['last_name']);

if (!filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
    exit("Invalid student email");
}

/* =====================================================
   SEND EMAIL
===================================================== */
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'scholarsyncglobal.ca';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'infos@scholarsyncglobal.ca';
    $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    /* ENCODING */
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';

    $mail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync Global');
    $mail->addAddress($studentEmail, $studentName);

    /* SUBJECT (SAFE ASCII) */
    $mail->Subject = "Your Signed Contract - Visa Consultant Canada";
    $mail->isHTML(true);

    /* =====================================================
       CLEAN, LEFT-ALIGNED EMAIL BODY
    ===================================================== */
    $mail->Body = "
    <div style='font-family:Arial,Helvetica,sans-serif;
                font-size:14px;
                color:#222;
                line-height:1.6'>

        <p style='margin-top:0'>
            <strong>ScholarSync Global</strong>
        </p>

        <p>
            Dear {$studentName},
        </p>

        <p>
            We are pleased to inform you that your contract has been successfully signed.
        </p>

        <p>
            Your signed contract is attached to this email for your records.
        </p>

        <p>
            If you have any questions or require further assistance,
            please feel free to reply to this email.
        </p>

        <p style='margin-top:30px'>
            Kind regards,<br>
            <strong>ScholarSync Global</strong><br>
            <a href='mailto:infos@scholarsyncglobal.ca'>
                infos@scholarsyncglobal.ca
            </a>
        </p>

    </div>
    ";

    /* TEXT FALLBACK */
    $mail->AltBody =
        "ScholarSync Global\n\n".
        "Dear {$studentName},\n\n".
        "Your contract has been successfully signed.\n".
        "Your signed contract is attached to this email.\n\n".
        "If you have any questions, please reply to this email.\n\n".
        "Kind regards,\nScholarSync Global";

    /* ATTACH PDF */
    $mail->addAttachment($row['pdf_path']);

    $mail->send();

} catch (Exception $e) {
    http_response_code(500);
    exit("Email failed");
}

/* =====================================================
   UPDATE sent_at
===================================================== */
$update = $conn->prepare("
    UPDATE student_contracts_special
    SET sent_at = NOW()
    WHERE id = ?
");
$update->bind_param("i", $contractId);
$update->execute();
$update->close();

/* =====================================================
   REDIRECT
===================================================== */
header("Location: admin-contracts-special.php?sent=1");
exit;
