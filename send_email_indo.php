<?php
// Load PHPMailer manually (since no Composer autoload exists)
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendStudentEmail($data, $filePath) {
    $mail = new PHPMailer(true);

    try {
        // ✅ SMTP Config
        $mail->isSMTP();
        $mail->Host       = 'scholarsyncglobal.ca';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'infos@scholarsyncglobal.ca';
        $mail->Password   = getenv('SMTP_PASSWORD') ?: ''; // Better to use env var
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $mail->Port       = 465;

        // ✅ Encoding
        $mail->CharSet = 'UTF-8';

        // ✅ Sender & Recipient
        $mail->setFrom('infos@scholarsyncglobal.ca', 'Indo European - ScholarSync App');
        $mail->addAddress('ujeanmethode@gmail.com');
        $mail->addAddress('infos@scholarsyncglobal.ca');
        // $mail->addAddress('europe@indoeuropean.in'); // optional extra recipient

        // ✅ Attach Excel file with absolute path
        if ($filePath) {
            $absolutePath = realpath($filePath); // resolve absolute path
            if ($absolutePath && file_exists($absolutePath)) {
                $mail->addAttachment($absolutePath);
            } else {
                error_log("Attachment not found: " . $filePath);
            }
        }

        // ✅ Email Content
        $mail->isHTML(true);
        $mail->Subject = "New Student Query - " . htmlspecialchars($data['name']);
        $mail->Body = "
          <h3>New Student Query Submitted</h3>
          <p><b>Name:</b> " . htmlspecialchars($data['name']) . "<br>
             <b>Age:</b> " . htmlspecialchars($data['age']) . "<br>
             <b>Intake:</b> " . htmlspecialchars($data['intake']) . "<br>
             <b>Passport Place:</b> " . htmlspecialchars($data['passport_place']) . "<br>
             <b>Preferences:</b> " . htmlspecialchars($data['country1'] . ' ' . $data['other1']) . ", 
             " . htmlspecialchars($data['country2'] . ' ' . $data['other2']) . ", 
             " . htmlspecialchars($data['country3'] . ' ' . $data['other3']) . "</p>
          <p>✅ The completed Excel form is attached for your reference.</p>
        ";

        // ✅ Send
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Email could not be sent. Error: {$mail->ErrorInfo}");
        return false;
    }
}
