<?php
/**
 * Request a password reset link sent to the admin email on file.
 */
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/admin_password_reset.php';

xander_ensure_admin_password_reset_columns($conn);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? $_POST['account'] ?? $_POST['email'] ?? '';
    $result = xander_admin_password_reset_request($conn, $username);
    if ($result === 'invalid_input') {
        $error = 'Please enter your username (same as on the login page).';
    } elseif ($result === 'no_email_on_file') {
        $error = 'Your account has no email address on file. Ask a superadmin to add your email to your profile, then try again.';
    } elseif ($result === 'db_error') {
        $error = 'Something went wrong. Please try again in a few minutes.';
    } else {
        // sent | no_account | mail_failed — same text (avoid revealing if account exists)
        $message = 'If that username exists and has an email on file, we have sent a reset link. Check your inbox and spam folder.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot password | SCHOLARSYNC GLOBAL</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --primary: #427431;
  --primary-dark: #2f5a26;
  --accent: #E21D1E;
  --bg: #f8fafc;
  --text: #1e293b;
  --text-muted: #94a3b8;
  --danger: #dc2626;
  --danger-light: #fee2e2;
  --success: #10b981;
  --success-light: #d1fae5;
  --border: #e2e8f0;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: Inter, system-ui, sans-serif;
  min-height: 100vh;
  background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 50%, #f8fafc 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  color: var(--text);
}
.login-wrapper { max-width: 460px; width: 100%; }
.login-card {
  background: #fff;
  border-radius: 20px;
  padding: 48px 40px;
  box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15);
  border: 1px solid rgba(226,232,240,0.8);
}
.login-card::before {
  content: '';
  display: block;
  height: 4px;
  margin: -48px -40px 32px;
  border-radius: 20px 20px 0 0;
  background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 50%, var(--primary) 100%);
}
.brand { text-align: center; margin-bottom: 28px; }
.brand .brand-logo-frame {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 140px;
  height: 140px;
  max-width: 100%;
  padding: 0;
  background: linear-gradient(145deg, #f8fafc 0%, #fff 100%);
  border-radius: 50%;
  box-shadow: 0 8px 28px rgba(15, 23, 42, 0.1);
  border: 2px solid var(--border);
  overflow: hidden;
}
.brand img {
  display: block;
  width: 86%;
  height: 86%;
  margin: 0 auto;
  object-fit: contain;
}
h1 { font-size: 1.5rem; font-weight: 700; text-align: center; margin-bottom: 8px; }
.sub { text-align: center; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 28px; }
.msg.success {
  background: var(--success-light);
  color: #065f46;
  padding: 14px 16px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 0.9rem;
  border: 1px solid rgba(16,185,129,0.2);
}
.msg.error {
  background: var(--danger-light);
  color: var(--danger);
  padding: 14px 16px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 0.9rem;
}
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-size: 0.875rem; font-weight: 500; }
.input-wrapper { position: relative; }
.input-wrapper i {
  position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
  color: #64748b; z-index: 1;
}
.input-wrapper input {
  width: 100%; padding: 14px 16px 14px 44px;
  border-radius: 12px; border: 2px solid var(--border);
  font-size: 0.95rem;
}
.input-wrapper input:focus {
  outline: none; border-color: var(--primary);
  box-shadow: 0 0 0 4px rgba(30,58,95,0.1);
}
.submit-btn {
  width: 100%; padding: 16px; border: none; border-radius: 12px;
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
  color: #fff; font-weight: 600; font-size: 1rem; cursor: pointer;
}
.submit-btn:hover { filter: brightness(1.05); }
.back {
  text-align: center; margin-top: 24px;
  font-size: 0.9rem;
}
.back a { color: var(--primary); font-weight: 500; text-decoration: none; }
.back a:hover { text-decoration: underline; }
.footer-note {
  text-align: center; font-size: 0.8rem; color: var(--text-muted);
  margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--border);
}
</style>
</head>
<body>
<div class="login-wrapper">
  <div class="login-card">
    <div class="brand">
      <div class="brand-logo-frame">
        <img src="scholarsync-global-logo.jpg" alt="SCHOLARSYNC GLOBAL" loading="lazy" onerror="this.onerror=null;this.src='assets/brand/scholarsync-mark.svg';">
      </div>
    </div>
    <h1>Forgot password</h1>
    <p class="sub">Enter your admin username (same as login). You can also enter your account email. The reset link is always sent to the email on file for that admin.</p>

    <?php if ($message): ?>
      <div class="msg success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="msg error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <div class="form-group">
        <label for="username">Username</label>
        <div class="input-wrapper">
          <i class="fas fa-user"></i>
          <input type="text" id="username" name="username" required placeholder="Same username as login" autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? $_POST['account'] ?? '') ?>">
        </div>
      </div>
      <button type="submit" class="submit-btn">Send reset link</button>
    </form>

    <p class="back"><a href="admin-login.php"><i class="fas fa-arrow-left"></i> Back to login</a></p>
    <div class="footer-note">© <?= date('Y') ?> SCHOLARSYNC GLOBAL</div>
  </div>
</div>
</body>
</html>
