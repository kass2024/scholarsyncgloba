<?php
/**
 * Set a new admin password using token from email.
 */
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/admin_password_reset.php';

xander_ensure_admin_password_reset_columns($conn);

$error = '';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $tokenIn = trim((string) $_POST['token']);
} elseif (isset($_GET['t'])) {
    $tokenIn = trim((string) $_GET['t']);
} else {
    $tokenIn = '';
}
$validToken = false;
$adminId = 0;

if ($tokenIn !== '' && preg_match('/^[a-f0-9]{64}$/i', $tokenIn)) {
    $hash = hash('sha256', $tokenIn);
    $st = $conn->prepare('SELECT id, password_reset_expires FROM admins WHERE password_reset_token = ? LIMIT 1');
    if ($st) {
        $st->bind_param('s', $hash);
        $st->execute();
        $res = $st->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $st->close();
        if ($row) {
            $expStr = $row['password_reset_expires'] ?? null;
            if ($expStr) {
                try {
                    $exp = new DateTimeImmutable((string) $expStr, new DateTimeZone('UTC'));
                    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
                    if ($exp >= $now) {
                        $validToken = true;
                        $adminId = (int) $row['id'];
                    } else {
                        $error = 'This reset link has expired. Please request a new one.';
                    }
                } catch (\Exception $e) {
                    $error = 'Invalid reset link.';
                }
            } else {
                $error = 'Invalid reset link.';
            }
        } else {
            $error = 'Invalid or expired reset link.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['t'])) {
    $error = 'Invalid reset link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken && $adminId > 0) {
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['password_confirm'] ?? '';
    if (strlen($p1) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($p1 !== $p2) {
        $error = 'Passwords do not match.';
    } else {
        $newHash = password_hash($p1, PASSWORD_DEFAULT);
        $clr = $conn->prepare('UPDATE admins SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ? AND password_reset_token = ?');
        if ($clr) {
            $clr->bind_param('sis', $newHash, $adminId, $hash);
            $clr->execute();
            if ($clr->affected_rows > 0) {
                $message = 'Your password has been updated. You can sign in now.';
                $validToken = false;
            } else {
                $error = 'Could not update password. Request a new reset link.';
            }
            $clr->close();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set new password | SCHOLARSYNC GLOBAL</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --primary: #427431;
  --primary-dark: #2f5a26;
  --accent: #E21D1E;
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
h1 { font-size: 1.5rem; font-weight: 700; text-align: center; margin-bottom: 8px; }
.sub { text-align: center; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 28px; }
.msg.success {
  background: var(--success-light);
  color: #065f46;
  padding: 14px 16px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 0.9rem;
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
.back { text-align: center; margin-top: 24px; font-size: 0.9rem; }
.back a { color: var(--primary); font-weight: 500; text-decoration: none; }
.footer-note {
  text-align: center; font-size: 0.8rem; color: var(--text-muted);
  margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--border);
}
</style>
</head>
<body>
<div class="login-wrapper">
  <div class="login-card">
    <h1>Set new password</h1>
    <p class="sub">Choose a strong password for your admin account.</p>

    <?php if ($message): ?>
      <div class="msg success"><?= htmlspecialchars($message) ?></div>
      <p class="back"><a href="admin-login.php">Go to login</a></p>
    <?php elseif (!$validToken && !$message): ?>
      <?php if ($error): ?><div class="msg error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <p class="back"><a href="admin-forgot-password.php">Request a new link</a> · <a href="admin-login.php">Back to login</a></p>
    <?php else: ?>
      <?php if ($error): ?><div class="msg error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="token" value="<?= htmlspecialchars($tokenIn) ?>">
        <div class="form-group">
          <label for="password">New password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock"></i>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
          </div>
        </div>
        <div class="form-group">
          <label for="password_confirm">Confirm password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock"></i>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password" placeholder="Repeat password">
          </div>
        </div>
        <button type="submit" class="submit-btn">Update password</button>
      </form>
      <p class="back"><a href="admin-login.php">Cancel</a></p>
    <?php endif; ?>

    <div class="footer-note">© <?= date('Y') ?> SCHOLARSYNC GLOBAL</div>
  </div>
</div>
</body>
</html>
