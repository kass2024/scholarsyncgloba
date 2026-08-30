<?php
session_start();
require_once __DIR__ . '/db.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['admin_id'])) {
  header('Location: login.php');
  exit;
}

// Collect form data
$admin_id = $_SESSION['admin_id'];
$reason = $_POST['reason'];
$range = $_POST['leave_range']; // "2025-07-14 to 2025-07-16"
$dates = explode(" to ", $range);
$start_date = $dates[0];
$end_date = $dates[1] ?? $start_date;
$rangeStr = ($start_date === $end_date) ? $start_date : "$start_date to $end_date";

// Insert into leave_requests table
$stmt = $conn->prepare("INSERT INTO leave_requests (admin_id, leave_date, reason) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $admin_id, $rangeStr, $reason);
$stmt->execute();
$stmt->close();

// Get staff info
$staff_name = $_SESSION['name'];
$submitted_date = date("Y-m-d H:i");

// Get all superadmin email addresses
$result = $conn->query("SELECT email FROM admins WHERE role = 'superadmin' AND email IS NOT NULL");
$superadmin_emails = [];
while ($row = $result->fetch_assoc()) {
    $superadmin_emails[] = $row['email'];
}

// Prepare email content
$subject = "📥 New Leave Request from $staff_name";
$body = "
  <div style='font-family: Arial, sans-serif; font-size:14px; color:#333;'>
    <h3 style='color:#0c3c78;'>📅 New Leave Request</h3>
    <table cellpadding='6' cellspacing='0' border='0'>
      <tr><td><strong>🧑 Staff:</strong></td><td>$staff_name</td></tr>
      <tr><td><strong>📆 Date(s):</strong></td><td>$rangeStr</td></tr>
      <tr><td><strong>📝 Reason:</strong></td><td>$reason</td></tr>
      <tr><td><strong>⏱️ Submitted:</strong></td><td>$submitted_date</td></tr>
    </table>
    <br>
    <p>Login to <a href='https://scholarsyncglobal.ca/admin-login.php'>Leave Approval Panel</a> to respond.</p>
    <p style='margin-top:15px; font-size:12px; color:#666;'>ScholarSync Global</p>
  </div>
";

// Send email to each superadmin
foreach ($superadmin_emails as $admin_email) {
  try {
      $mail = new PHPMailer(true);
      $mail->isSMTP();
      $mail->Host       = 'scholarsyncglobal.ca';
      $mail->SMTPAuth   = true;
      $mail->Username   = 'infos@scholarsyncglobal.ca';
      $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
      $mail->Port       = 465;
      $mail->CharSet    = 'UTF-8';

      $mail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync Global');
      $mail->addAddress($admin_email);
      $mail->isHTML(true);
      $mail->Subject = $subject;
      $mail->Body    = $body;

      $mail->send();
  } catch (Exception $e) {
      error_log("Leave request email error: " . $mail->ErrorInfo);
  }
}

header("Location: leave-request.php?success=1");
exit;
