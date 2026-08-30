<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/role.php';
require_once __DIR__ . '/../helpers/mailer.php';
require_once __DIR__ . '/../helpers/student_portal_accounts.php';
require_once __DIR__ . '/../helpers/urls.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('Method not allowed', false, 405);
}

// Admin auth
$adminId = 0;
if (!empty($_SESSION['id'])) {
    $adminId = (int)$_SESSION['id'];
} elseif (!empty($_SESSION['admin_id'])) {
    $adminId = (int)$_SESSION['admin_id'];
}
if ($adminId <= 0) {
    jsonResponse('Unauthorized', false, 401);
}

$stmtRole = $conn->prepare("SELECT role FROM admins WHERE id = ? LIMIT 1");
if (!$stmtRole) {
    jsonResponse('Server error', false, 500);
}
$stmtRole->bind_param('i', $adminId);
$stmtRole->execute();
$roleRow = $stmtRole->get_result()->fetch_assoc();
$stmtRole->close();

if (!$roleRow) {
    jsonResponse('Unauthorized', false, 401);
}

// Allow any logged-in admin to share access (not only superadmin).
// (We still keep the session/auth checks above.)
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$name = trim((string)($_POST['name'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse('Invalid email', false, 400);
}

// Ensure portal account exists / reset to default password.
try {
    pcvc_student_portal_ensure_account_for_email($conn, $email);
} catch (Throwable $e) {
    // keep going; email still may be sent
}

// Build login link (prefill email)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$base = $scheme . '://' . $host;
$loginUrl = $base . pcvc_url('/student-login.php') . '?email=' . rawurlencode($email);

$defaultPw = PCVC_STUDENT_DEFAULT_PASSWORD;
$studentName = $name !== '' ? $name : 'Student';

$subject = 'Your Student Portal Access – ScholarSync MIS';
$body = "
  <div style=\"font-family:Arial,sans-serif;line-height:1.6;color:#111\">
    <h2 style=\"margin:0 0 12px 0\">Student Portal Access</h2>
    <p>Hello <strong>" . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
    <p>Your student portal is ready. From now on, <strong>all your information and documents will be managed in the portal</strong>.</p>
    <p style=\"margin:16px 0\">
      <a href=\"" . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . "\" style=\"
        display:inline-block;background:#3661B9;color:#fff;text-decoration:none;
        padding:10px 14px;border-radius:8px;font-weight:700;
      \">Open Student Portal</a>
    </p>
    <p><strong>Login details</strong></p>
    <ul>
      <li>Email: <strong>" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</strong></li>
      <li>Default password: <strong>" . htmlspecialchars($defaultPw, ENT_QUOTES, 'UTF-8') . "</strong></li>
    </ul>
    <p>If the button doesn’t work, copy/paste this link:</p>
    <p><a href=\"" . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . "</a></p>
    <p style=\"margin-top:18px\">Thank you,<br>ScholarSync Global</p>
  </div>
";

try {
    $mail = app_mailer(); // uses current SMTP settings (env overrides)
    $mail->addAddress($email, $studentName);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->AltBody = "Student Portal Access\n\nLogin: {$loginUrl}\nEmail: {$email}\nPassword: {$defaultPw}\n";
    $mail->send();
} catch (Throwable $e) {
    error_log('share-access mail failed: ' . $e->getMessage());
    jsonResponse('Failed to send email. Please check SMTP settings.', false, 500);
}

jsonResponse([
    'sent' => true,
    'email' => $email,
    'login_url' => $loginUrl,
]);

