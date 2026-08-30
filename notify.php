<?php
require_once __DIR__ . '/db.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// CONFIG
$adminEmail = 'infos@scholarsyncglobal.ca';
$adminName = 'ScholarSync-Canada';

// Find conversations where AI said "notify a human" in last 15 mins
$query = "
    SELECT c.user_id, MAX(m.created_at) AS last_time, MAX(m.message) AS ai_reply
    FROM chat_conversations c
    INNER JOIN chat_messages m ON c.user_id = m.user_id
    WHERE c.is_active = 1
      AND m.sender = 'ai'
      AND m.created_at >= NOW() - INTERVAL 15 MINUTE
    GROUP BY c.user_id
    HAVING ai_reply LIKE '%notify a human%'
";

$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    $userId = $row['user_id'];
    $lastMsg = $row['ai_reply'];
    $lastTime = $row['last_time'];

    // Send email to Admin
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'scholarsyncglobal.ca';
        $mail->SMTPAuth = true;
        $mail->Username = 'infos@scholarsyncglobal.ca';
        $mail->Password = getenv('SMTP_PASSWORD') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync-Canada');
        $mail->addAddress($adminEmail);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        $mail->Subject = "❗️ AI needs human help - Chat with User ID: $userId";

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; font-size:14px; color:#333;'>
            <h2 style='color:#b10000;'>❗️ AI Bot notified need for human reply</h2>
            <p><strong>User ID:</strong> $userId</p>
            <p><strong>AI Reply:</strong> $lastMsg</p>
            <p><strong>Last Message Time:</strong> $lastTime</p>
            <hr>
            <p><a href='https://scholarsyncglobal.ca/admin_chat.php?user_id=$userId' 
                  style='display:inline-block; padding:10px 15px; background:#0c3c78; color:#fff; text-decoration:none; border-radius:5px;'>
                  💬 Go to Admin Chat Panel
               </a></p>
            <p style='margin-top:15px; font-size:12px; color:#666;'>ScholarSync Global System</p>
        </div>";

        $mail->send();

        echo "✅ Notified admin for user: $userId <br>";
    } catch (Exception $e) {
        error_log("Notify Email Error (User: $userId): " . $e->getMessage());
    }
}

echo "<br>✅ Notify script done.";
?>
