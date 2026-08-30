<?php
// Show PHP errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load PHPMailer manually (no composer)
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendMail($subject, $messageHtml)
{
    // Create mail object
    $mail = new PHPMailer(true);

    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = 'scholarsyncglobal.ca'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'infos@scholarsyncglobal.ca';
        $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $mail->Port       = 465;

        // Sender
        $mail->setFrom('infos@scholarsyncglobal.ca', 'Visa Consultant Canada');

        // Recipient
        $mail->addAddress('ujeanmethode@gmail.com', 'UJEAN METHODE');

        // Email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $messageHtml;
        $mail->AltBody = strip_tags($messageHtml);

        $mail->send();
        return "Email sent successfully!";
    } catch (Exception $e) {
        return "Email failed: " . $mail->ErrorInfo;
    }
}

// --------- RUN TEST EMAIL ----------
echo "<h2>Sending Test Email...</h2>";

$result = sendMail(
    "Test Email from Localhost",
    "<h3>Hello!</h3><p>This is a test email sent from your PHP SMTP file.</p>"
);

echo "<p><strong>Result:</strong> $result</p>";
?>
