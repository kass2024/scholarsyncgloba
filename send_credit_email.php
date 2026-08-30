<?php
require_once __DIR__ . '/db.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PhpmailerException;

$userId = $argv[1] ?? null;

if (!$userId) {
    exit("Missing user ID.\n");
}

// Fetch data
$stmt = $conn->prepare('SELECT first_name, middle_name, last_name, email, current_program, proposed_program FROM credit_transfer_applications WHERE user_id = ?');
$stmt->bind_param('s', $userId);
$stmt->execute();
$stmt->bind_result($firstName, $middleName, $lastName, $email, $currentProgram, $proposedProgram);
$stmt->fetch();
$stmt->close();

$studentName = trim(implode(' ', array_filter([
    (string)($firstName ?? ''),
    (string)($middleName ?? ''),
    (string)($lastName ?? ''),
], static fn ($p) => $p !== '')));

$email = (string)($email ?? '');
$currentProgram = (string)($currentProgram ?? '');
$proposedProgram = (string)($proposedProgram ?? '');

$isDraftEmail = $email !== '' && strpos($email, '@credit-transfer-draft.local') !== false;

// Send email
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'scholarsyncglobal.ca';
    $mail->SMTPAuth = true;
    $mail->Username = 'infos@scholarsyncglobal.ca';
    $mail->Password = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync Global');

    // Admin
    $mail->addAddress('infos@scholarsyncglobal.ca');
    $mail->isHTML(true);
    $mail->Subject = 'New Credit Transfer Request Received';
    $mail->Body = '<p>A new credit transfer request has been submitted.</p>'
        . '<p><strong>Student:</strong> ' . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>Current Program:</strong> ' . htmlspecialchars($currentProgram, ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>Proposed Program:</strong> ' . htmlspecialchars($proposedProgram, ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>User ID:</strong> ' . htmlspecialchars((string)$userId, ENT_QUOTES, 'UTF-8') . '</p>';

    if ($isDraftEmail) {
        $mail->Body .= '<p><em>Note: Applicant used quick submit; profile may be incomplete. Update contact details as needed.</em></p>';
    }

    if ($email !== '' && !$isDraftEmail) {
        $mail->addAddress($email, $studentName);
        $mail->Body .= '<hr><p>Dear ' . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>We have received your credit transfer request and will contact you shortly.</p>'
            . '<p>Best regards,<br>ScholarSync Global Team</p>';
    }

    $mail->send();
} catch (PhpmailerException $e) {
    error_log('Email send failed for ' . $userId . ': ' . $e->getMessage());
}

$conn->close();
