<?php
require_once __DIR__ . '/db.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';
require_once 'generate-pdf.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $degree = isset($_POST['degree']) ? implode(',', $_POST['degree']) : '';
    $mode = isset($_POST['mode']) ? implode(',', $_POST['mode']) : '';

    // Directories
    $uploadDir = 'uploads/';
    $pdfDir = 'pdfs/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);

    // 1. Handle Passport Upload
    $passportPath = '';
    if (!empty($_FILES['passport_copy']['tmp_name']) && $_FILES['passport_copy']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['passport_copy']['name'], PATHINFO_EXTENSION);
        $passportName = time() . '_passport.' . $ext;
        $passportPath = $uploadDir . $passportName;
        move_uploaded_file($_FILES['passport_copy']['tmp_name'], $passportPath);
    }

    // 2. Handle Transcript Upload
    $transcriptPath = '';
    if (!empty($_FILES['transcript']['tmp_name']) && $_FILES['transcript']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['transcript']['name'], PATHINFO_EXTENSION);
        $transcriptName = time() . '_transcript.' . $ext;
        $transcriptPath = $uploadDir . $transcriptName;
        move_uploaded_file($_FILES['transcript']['tmp_name'], $transcriptPath);
    }

    // 3. Handle Certificates (Multiple Uploads)
    $certificatePaths = [];
    if (!empty($_FILES['certificates']['tmp_name']) && is_array($_FILES['certificates']['name'])) {
        foreach ($_FILES['certificates']['name'] as $key => $name) {
            if ($_FILES['certificates']['error'][$key] === UPLOAD_ERR_OK) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $certName = time() . "_cert_$key." . $ext;
                $certPath = $uploadDir . $certName;
                if (move_uploaded_file($_FILES['certificates']['tmp_name'][$key], $certPath)) {
                    $certificatePaths[] = $certPath;
                }
            }
        }
    }
    $certificatesJoined = implode(',', $certificatePaths);

    // Save to DB
    $stmt = $conn->prepare("INSERT INTO malta_applications (
        session_from, session_to, degree_program, specialty, alt1, alt2, mode_of_study,
        surname, name, middle_name, gender, marital_status, dob, birth_place, nationality,
        passport_no, issue_date, expiry_date, address, contact_number, email, visa_country,
        school_name, school_address, school_from, school_to, school_certificate,
        college_name, college_address, college_from, college_to, college_certificate,
        studied_malta, studied_malta_info, malta_lang, malta_lang_info,
        passport_copy_path, certificates_paths, transcript_path,
        signed_date, signature
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )");

    if (!$stmt) die("<p style='color:red;'>SQL Prepare Failed: " . $conn->error . "</p>");

    $stmt->bind_param(str_repeat("s", 41),
        $_POST['session_from'], $_POST['session_to'], $degree, $_POST['specialty'], $_POST['alt1'], $_POST['alt2'], $mode,
        $_POST['surname'], $_POST['name'], $_POST['middle'], $_POST['gender'], $_POST['marital_status'], $_POST['dob'], $_POST['birth_place'],
        $_POST['nationality'], $_POST['passport_no'], $_POST['issue_date'], $_POST['expiry_date'], $_POST['address'], $_POST['contact_number'],
        $_POST['email'], $_POST['visa_country'], $_POST['school_name'], $_POST['school_address'], $_POST['school_from'], $_POST['school_to'],
        $_POST['school_certificate'], $_POST['college_name'], $_POST['college_address'], $_POST['college_from'], $_POST['college_to'],
        $_POST['college_certificate'], $_POST['studied_malta'], $_POST['studied_malta_info'], $_POST['malta_lang'], $_POST['malta_lang_info'],
        $passportPath, $certificatesJoined, $transcriptPath, $_POST['signed_date'], $_POST['signature']
    );

    if (!$stmt->execute()) die("<p style='color:red;'>Failed to Insert Data: " . $stmt->error . "</p>");

    $lastId = $stmt->insert_id;

    try {
        // Generate PDF
        $pdfRelativePath = generateApplicationPDF($lastId, $conn);
        $update = $conn->prepare("UPDATE malta_applications SET pdf_path = ? WHERE id = ?");
        $update->bind_param("si", $pdfRelativePath, $lastId);
        $update->execute();

        // Fetch data for email
        $res = $conn->query("SELECT * FROM malta_applications WHERE id = $lastId");
        $studentData = $res->fetch_assoc();

        // Admin Email
$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host = 'scholarsyncglobal.ca';
$mail->SMTPAuth = true;
$mail->Username = 'infos@scholarsyncglobal.ca';
$mail->Password = getenv('SMTP_PASSWORD') ?: '';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
$mail->Port = 465;
$mail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync Global');
$mail->addAddress('infos@scholarsyncglobal.ca');
$mail->addAddress('methode@scholarsyncglobal.ca');
//$mail->addAddress('partners@ieu.edu.ua');
$mail->isHTML(true);
$mail->Subject = "New Malta Application Submitted - {$studentData['name']} {$studentData['surname']}";

// Compose clean, minimal body
$body = "<h3>New Student Application Received</h3>";
$body .= "<p><strong>Name:</strong> {$studentData['name']} {$studentData['surname']}</p>";
$body .= "<p><strong>Email:</strong> {$studentData['email']}</p>";
$body .= "<p><strong>Phone:</strong> {$studentData['contact_number']}</p>";
$body .= "<p><strong>Program:</strong> {$studentData['degree_program']}</p>";
$body .= "<p><strong>Specialty:</strong> {$studentData['specialty']}</p>";
$body .= "<p><strong>Date of Birth:</strong> {$studentData['dob']}</p>";
$body .= "<p><strong>Gender:</strong> {$studentData['gender']}</p>";
$body .= "<p><strong>Nationality:</strong> {$studentData['nationality']}</p>";
$body .= "<p><strong>Visa Country:</strong> {$studentData['visa_country']}</p>";
$body .= "<p><strong>Attachments are included below.</strong></p>";

$mail->Body = $body;

        // Attachments
        if (!empty($studentData['pdf_path']) && file_exists($studentData['pdf_path'])) {
            $mail->addAttachment($studentData['pdf_path'], 'Application_Form.pdf');
        }
        if (!empty($studentData['passport_copy_path']) && file_exists($studentData['passport_copy_path'])) {
            $mail->addAttachment($studentData['passport_copy_path'], 'Passport_Copy.pdf');
        }
        if (!empty($studentData['transcript_path']) && file_exists($studentData['transcript_path'])) {
            $mail->addAttachment($studentData['transcript_path'], 'Transcript.pdf');
        }
        if (!empty($studentData['certificates_paths'])) {
            foreach (explode(',', $studentData['certificates_paths']) as $i => $certPath) {
                if (file_exists($certPath)) {
                    $mail->addAttachment($certPath, "Certificate_" . ($i + 1) . ".pdf");
                }
            }
        }
        $mail->send();

        // Confirmation to Student
        $studentMail = new PHPMailer(true);
        $studentMail->isSMTP();
        $studentMail->Host = 'scholarsyncglobal.ca';
        $studentMail->SMTPAuth = true;
        $studentMail->Username = 'infos@scholarsyncglobal.ca';
        $studentMail->Password = getenv('SMTP_PASSWORD') ?: '';
        $studentMail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $studentMail->Port = 465;
        $studentMail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync Global');
        $studentMail->addAddress($studentData['email'], $studentData['name']);
        $studentMail->isHTML(true);
        $studentMail->Subject = "Your Malta Application Was Received";
        $studentMail->Body = "<p>Dear <strong>{$studentData['name']}</strong>,</p>
            <p>Thank you for submitting your application to our Malta Campus. Your Application ID is <strong>$lastId</strong>.</p>
            <p>We will review your application and contact you shortly.</p><p>Best regards,<br>ScholarSync Global Team</p>";
        $studentMail->send();

        echo "<div style='text-align:center; font-family: Arial;'>
            <h2 style='color: green;'>✅ Application Submitted Successfully!</h2>
            <p>Your application ID is: <strong>$lastId</strong></p>
            <p>📄 <a href='$pdfRelativePath' download>Click here to download your PDF</a></p>
        </div>";

    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ Email or PDF Error: {$e->getMessage()}</p>";
    }
}
?>
