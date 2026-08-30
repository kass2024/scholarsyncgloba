<?php
/**
 * 🎓 Send Email — Budapest Winter School Application
 * --------------------------------------------------
 * Sends confirmation to both applicant and admin.
 * Uses PHPMailer with same SMTP credentials as main system.
 */
require_once __DIR__ . '/db.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ---------- INPUT ---------- */
$email = $_GET['email'] ?? null;
if (!$email) {
    exit('Missing applicant email');
}

/* ---------- Fetch Application Data ---------- */
$stmt = $conn->prepare("SELECT full_name, phone, valid_passport, degree_certificate, transcripts, cv_resume, passport_photo, payment_proof 
                        FROM budapest_applications 
                        WHERE email = ? 
                        ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($fullName, $phone, $passport, $degree, $transcripts, $cv, $photo, $payment);
$stmt->fetch();
$stmt->close();

if (empty($fullName)) {
    exit('No applicant found for this email.');
}

$submittedDate = date('Y-m-d H:i:s');

/* ---------- Compose Admin Email ---------- */
try {
    $adminMail = new PHPMailer(true);
    $adminMail->isSMTP();
    $adminMail->Host = 'scholarsyncglobal.ca';
    $adminMail->SMTPAuth = true;
    $adminMail->Username = 'infos@scholarsyncglobal.ca';
    $adminMail->Password = getenv('SMTP_PASSWORD') ?: '';
    $adminMail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $adminMail->Port = 465;
    $adminMail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync-Canada');
    $adminMail->addAddress('infos@scholarsyncglobal.ca');
    $adminMail->isHTML(true);
    $adminMail->CharSet = 'UTF-8';

    $adminMail->Subject = "🎓 New Budapest Winter School Application – $fullName";

    $adminMail->Body = "
    <div style='font-family:Arial, sans-serif; font-size:14px; color:#333;'>
        <h2 style='color:#0c3c78;'>📩 New Budapest Winter School Application</h2>
        <p><strong>Submitted:</strong> $submittedDate</p>
        <table cellpadding='6' cellspacing='0' border='0'>
            <tr><td><strong>👤 Name:</strong></td><td>$fullName</td></tr>
            <tr><td><strong>📧 Email:</strong></td><td>$email</td></tr>
            <tr><td><strong>📱 Phone:</strong></td><td>$phone</td></tr>
        </table>
        <hr>
        <h3 style='color:#0c3c78;'>📎 Uploaded Documents</h3>
        <ul style='line-height:1.6;'>
            <li>Valid Passport: <a href='$passport' target='_blank'>View</a></li>
            <li>Degree Certificate: <a href='$degree' target='_blank'>View</a></li>
            <li>Transcripts / Reports: <a href='$transcripts' target='_blank'>View</a></li>
            <li>Curriculum Vitae (CV): <a href='$cv' target='_blank'>View</a></li>
            <li>Passport-Size Photo: <a href='$photo' target='_blank'>View</a></li>
            <li>Payment Proof: <a href='$payment' target='_blank'>View</a></li>
        </ul>
        <p style='margin-top:15px; font-size:12px; color:#666;'>ScholarSync Global System</p>
    </div>";

    $adminMail->send();
} catch (Exception $e) {
    error_log("Admin Email Error (Budapest): " . $e->getMessage());
}

/* ---------- Send Confirmation to Applicant ---------- */
try {
    if (!empty($email)) {
        $studentMail = new PHPMailer(true);
        $studentMail->isSMTP();
        $studentMail->Host = 'scholarsyncglobal.ca';
        $studentMail->SMTPAuth = true;
        $studentMail->Username = 'infos@scholarsyncglobal.ca';
        $studentMail->Password = getenv('SMTP_PASSWORD') ?: '';
        $studentMail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $studentMail->Port = 465;
        $studentMail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync-Canada');
        $studentMail->addAddress($email, $fullName);
        $studentMail->isHTML(true);
        $studentMail->CharSet = 'UTF-8';
        $studentMail->Subject = "🎓 Your Budapest Winter School Application Has Been Received!";
        $studentMail->Body = "
        <div style='font-family:Arial,sans-serif; font-size:15px; color:#333;'>
            <p>Dear <strong>$fullName</strong>,</p>
            <p>Thank you for submitting your application for the <strong>8-Days Budapest Winter School</strong>.</p>
            <p>We have successfully received your documents:</p>
            <ul>
                <li>✅ Valid Passport</li>
                <li>✅ Degree Certificate</li>
                <li>✅ Transcripts / Reports</li>
                <li>✅ Curriculum Vitae (CV)</li>
                <li>✅ Passport-Size Photo</li>
                <li>✅ Payment Proof</li>
            </ul>
            <p>Our admissions team will review your application and contact you shortly with further details.</p>
            <p>Warm regards,<br>
            <strong>Budapest Winter School Admissions Team</strong><br>
            ScholarSync Global</p>
        </div>";
        $studentMail->send();
    }
} catch (Exception $e) {
    error_log("Student Email Error (Budapest): " . $e->getMessage());
}

/* ---------- Cleanup ---------- */
$conn->close();
echo 'Email sent';
?>
