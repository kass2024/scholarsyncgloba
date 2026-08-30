<?php
// ==================================================
// Cron Job: Notify Admins of Incomplete Jobs
// Runs every 5 minutes
// ==================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --------------------------------------------------
// 1. Fetch all admins who have at least one not completed job
// --------------------------------------------------
$sql = "
    SELECT a.id, a.full_name AS admin_name, a.email AS admin_email
    FROM admins a
    WHERE a.id IN (
        SELECT DISTINCT admin_id FROM job_list WHERE status = 'not_completed'
    )
";
$admins = $conn->query($sql);

if (!$admins || $admins->num_rows === 0) {
    echo date('Y-m-d H:i:s') . " ✅ No incomplete jobs found.\n";
    exit;
}

// --------------------------------------------------
// 2. For each admin, get detailed list of unfinished jobs
// --------------------------------------------------
while ($admin = $admins->fetch_assoc()) {
    $adminId    = $admin['id'];
    $adminName  = $admin['admin_name'];
    $adminEmail = $admin['admin_email'];

    // Fetch that admin's pending jobs
    $jobs = $conn->query("
        SELECT title, applicant_name, applicant_email, created_at
        FROM job_list 
        WHERE admin_id = $adminId AND status = 'not_completed'
        ORDER BY created_at DESC
    ");

    if (!$jobs || $jobs->num_rows === 0) continue;

    // Build HTML list of pending jobs
    $jobListHTML = "<ul style='font-size:14px;'>";
    while ($job = $jobs->fetch_assoc()) {
        $title   = htmlspecialchars($job['title']);
        $name    = htmlspecialchars($job['applicant_name']);
        $email   = htmlspecialchars($job['applicant_email']);
        $date    = date('Y-m-d', strtotime($job['created_at']));
        $jobListHTML .= "<li><strong>$title</strong> – $name (<a href='mailto:$email'>$email</a>) <em>[$date]</em></li>";
    }
    $jobListHTML .= "</ul>";

    // --------------------------------------------------
    // 3. Send personalized email reminder
    // --------------------------------------------------
    try {
        $mail = new PHPMailer(true);
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';
        $mail->isSMTP();
        $mail->Host       = 'scholarsyncglobal.ca';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'infos@scholarsyncglobal.ca';
        $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync-Canada');
        $mail->addAddress($adminEmail, $adminName);

        $mail->isHTML(true);
        $mail->Subject = "⏳ Reminder: You have pending jobs to complete";
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; font-size:14px; color:#2c3e50;'>
                <p>Dear <strong>$adminName</strong>,</p>
                <p>You currently have the following job(s) still <strong>not completed</strong>:</p>
                $jobListHTML
                <p>Please log in to the system and update or complete them as soon as possible.</p>
                <p style='margin-top:20px;'>Best regards,<br>
                <strong>ScholarSync Global Admin System</strong></p>
            </div>
        ";

        $mail->send();
        echo date('Y-m-d H:i:s') . " 📧 Reminder sent to $adminEmail\n";

    } catch (Exception $e) {
        error_log("Email send error to $adminEmail: " . $mail->ErrorInfo);
    }
}

$conn->close();
echo date('Y-m-d H:i:s') . " ✅ Cron finished.\n";
?>
