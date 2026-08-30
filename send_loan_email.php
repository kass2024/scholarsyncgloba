<?php
ob_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
require_once __DIR__ . '/db.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 📌 Get user ID from GET request
$userId = $_GET['user_id'] ?? null;

if (!$userId) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Missing user ID']);
    exit;
}

// 🔄 Load application record
$formData = [];
$get = $conn->prepare("SELECT * FROM master_loan_applications WHERE user_id = ?");
$get->bind_param("s", $userId);
$get->execute();
$result = $get->get_result();
if ($result && $result->num_rows > 0) {
    $formData = $result->fetch_assoc();
}
$get->close();

if (empty($formData)) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'No data found for given user']);
    exit;
}

$studentName = trim($formData['first_name'] . ' ' . $formData['last_name']);
$email       = $formData['email'] ?? '';

try {
    // 📧 Prepare admin email
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'scholarsyncglobal.ca';
    $mail->SMTPAuth = true;
    $mail->Username = 'infos@scholarsyncglobal.ca';
    $mail->Password = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync Global');

    // ✅ Admin recipients
    $mail->addAddress('infos@scholarsyncglobal.ca');
    $mail->addAddress('infos@scholarsyncglobal.ca');
    $mail->addAddress('priya.shukla@applyboard.com'); // Optional

    $mail->isHTML(true);
    $mail->Subject = "New Master Loan Request Submitted by {$studentName}";

    // ✉️ Body content
    $mail->Body = "
        <h3>New Master Loan Application Received</h3>
        <p><strong>Applicant Name:</strong> " . htmlspecialchars($formData['first_name'] ?? '') . " " . htmlspecialchars($formData['last_name'] ?? '') . "</p>
        <p><strong>Email:</strong> " . htmlspecialchars($formData['email'] ?? '') . "</p>
        <p><strong>Phone Number:</strong> " . htmlspecialchars($formData['phone_number'] ?? '') . "</p>
        <p><strong>Date of Birth:</strong> " . htmlspecialchars($formData['dob'] ?? '') . "</p>
        <p><strong>Citizenship:</strong> " . htmlspecialchars($formData['citizenship_country'] ?? '') . "</p>
        <p><strong>Program:</strong> " . htmlspecialchars($formData['masters_program_name'] ?? '') . "</p>
        <p><strong>School:</strong> " . htmlspecialchars($formData['school_name'] ?? '') . "</p>
        <p><strong>Degree Type:</strong> " . htmlspecialchars($formData['degree_type'] ?? '') . "</p>
        <p><strong>Application Type:</strong> " . htmlspecialchars($formData['application_type'] ?? '') . "</p>
        <p><strong>Intake:</strong> " . htmlspecialchars($formData['intake'] ?? '') . "</p>
        <p><strong>Loan Reason:</strong> " . htmlspecialchars($formData['loan_reason'] ?? '') . "</p>
        <p><strong>Reference:</strong> " . htmlspecialchars($formData['ref_first_name'] ?? '') . " " . htmlspecialchars($formData['ref_last_name'] ?? '') . " (" . htmlspecialchars($formData['ref_relationship'] ?? '') . ")<br>
        Email: " . htmlspecialchars($formData['ref_email'] ?? '') . "<br>
        Phone: " . htmlspecialchars($formData['ref_phone'] ?? '') . "</p>
        <p><strong>Submitted on:</strong> " . htmlspecialchars($formData['created_at'] ?? '') . "</p>
    ";

    // Attach all files
    $attachments = [
        'acceptance_letter'     => 'Admission Letter',
        'bachelor_degree'       => 'Bachelor Degree',
        'bachelor_transcript'   => 'Bachelor Transcript',
        'cv'                    => 'CV',
        'id_document'           => 'ID Document',
        'valid_passport'        => 'Passport',
        'english_certificate'   => 'English Certificate',
        'admission_fees'        => 'Admission Fees',
        'scholarship_letter'    => 'Scholarship Letter',
        'bank_statement'        => 'Bank Statement'
    ];

    foreach ($attachments as $field => $label) {
        if (empty($formData[$field])) {
            continue;
        }
        $rel = trim(str_replace('\\', '/', (string)$formData[$field]));
        $rel = ltrim($rel, '/');
        if ($rel === '') {
            continue;
        }
        $filePath = __DIR__ . '/' . $rel;
        if (!is_file($filePath)) {
            continue;
        }
        $mail->addAttachment($filePath, "{$label} - " . basename($formData[$field]));
    }

    $mail->send();
} catch (Exception $e) {
    error_log("Admin Email Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to send admin email']);
    exit;
}

// 🎓 Email to student
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

        $studentMail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync Global');
        $studentMail->addAddress($email, $studentName);

        $studentMail->isHTML(true);
        $studentMail->Subject = "Your Loan Application Was Received";
        $studentMail->Body = "
            <p>Dear $studentName,</p>
            <p>Your master loan application has been received successfully. We will contact you shortly.</p>
            <p>Best regards,<br>ScholarSync Global Team</p>
        ";

        $studentMail->send();
    }
} catch (Exception $e) {
    error_log("Student Email Error: " . $e->getMessage());
}

ob_end_clean();
echo json_encode(['status' => 'success', 'message' => 'Emails sent successfully']);
exit;
?>
