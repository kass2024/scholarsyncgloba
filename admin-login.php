<?php
/**
 * =========================================================
 * ADMIN LOGIN — PRODUCTION SAFE
 * =========================================================
 * ✔ Split-panel UI (ScholarSync Global)
 * ✔ Preserves ORIGINAL backend behavior
 * ✔ Dashboard-safe (NO breaking changes)
 * =========================================================
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/admin_password_reset.php';

xander_ensure_admin_password_reset_columns($conn);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare(
        "SELECT id, password_hash, full_name, role
         FROM admins
         WHERE username = ?"
    );

    if (!$stmt) {
        $error = "System error. Please contact administrator.";
    } else {

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin  = $result->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, $admin['password_hash'])) {

            session_regenerate_id(true);

            $_SESSION['id']        = $admin['id'];
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['username'] = $username;
            $_SESSION['name']     = $admin['full_name'];
            $_SESSION['role']     = pcvc_normalize_role_string($admin['role'] ?? '');

            $clr = $conn->prepare('UPDATE admins SET password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?');
            if ($clr) {
                $aid = (int) $admin['id'];
                $clr->bind_param('i', $aid);
                $clr->execute();
                $clr->close();
            }

            header("Location: admin-dashboard.php");
            exit;

        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Admin login for ScholarSync Global — study abroad and visa application support.">
<title>Sign in | ScholarSync Global</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --pcv-green: #173c6d;
  --pcv-green-hover: #102d55;
  --pcv-green-deep: #0b2140;
  --pcv-red: #f4b52e;
  --pcv-blue: #1ba7a0;
  --pcv-input-bg: #E8F0FE;
  --pcv-text: #1e293b;
  --pcv-text-muted: #64748b;
  --pcv-border: #c5d9f5;
  --pcv-card: rgba(255, 255, 255, 0.92);
  --pcv-danger: #dc2626;
  --pcv-danger-bg: #fef2f2;
  --shell-glow: 0 32px 64px -12px rgba(15, 23, 42, 0.22), 0 0 0 1px rgba(255, 255, 255, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, sans-serif;
  min-height: 100vh;
  color: var(--pcv-text);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
  position: relative;
  overflow-x: hidden;
  background:
    radial-gradient(ellipse 100% 80% at 15% 10%, rgba(66, 116, 49, 0.35) 0%, transparent 55%),
    radial-gradient(ellipse 80% 60% at 90% 85%, rgba(226, 29, 30, 0.18) 0%, transparent 50%),
    radial-gradient(ellipse 60% 50% at 50% 50%, rgba(54, 97, 185, 0.08) 0%, transparent 60%),
    linear-gradient(168deg, #e8edf4 0%, #f1f5f9 45%, #e2e8f0 100%);
}

body::before {
  content: '';
  position: fixed;
  inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.035'/%3E%3C/svg%3E");
  pointer-events: none;
  z-index: 0;
}

.shell {
  width: 100%;
  max-width: 980px;
  background: var(--pcv-card);
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  border-radius: 28px;
  box-shadow: var(--shell-glow);
  overflow: hidden;
  display: grid;
  grid-template-columns: 1fr;
  position: relative;
  z-index: 1;
}

@media (min-width: 900px) {
  .shell { grid-template-columns: 1fr 1fr; min-height: 580px; }
}

/* --- Left brand --- */
.brand-panel {
  background:
    linear-gradient(155deg, rgba(255, 255, 255, 0.08) 0%, transparent 42%),
    linear-gradient(165deg, var(--pcv-green) 0%, var(--pcv-green-deep) 55%, #1e3d18 100%);
  color: #fff;
  padding: 44px 32px 40px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  position: relative;
}

.brand-panel::after {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 70% 100%, rgba(226, 29, 30, 0.15) 0%, transparent 45%);
  pointer-events: none;
}

/* Circular seal logo — PNG uses transparency (no black box) */
.brand-panel .logo-wrap {
  width: 200px;
  height: 200px;
  max-width: min(220px, 58vw);
  margin-bottom: 24px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.12);
  border: 3px solid rgba(255, 255, 255, 0.45);
  box-shadow:
    0 20px 50px rgba(0, 0, 0, 0.28),
    inset 0 0 0 1px rgba(255, 255, 255, 0.2);
  animation: logoFloat 6s ease-in-out infinite;
  position: relative;
  z-index: 1;
}

@keyframes logoFloat {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-5px); }
}

.brand-panel .logo-wrap img {
  display: block;
  width: 88%;
  height: 88%;
  object-fit: contain;
  object-position: center;
}

/* Fallback vector mark: slightly smaller inside circle */
.brand-panel .logo-wrap img.logo-fallback-svg {
  width: 72%;
  height: 72%;
}

.brand-panel h1 {
  font-size: 1.65rem;
  font-weight: 700;
  line-height: 1.25;
  letter-spacing: -0.02em;
  margin-bottom: 16px;
}

.brand-panel .lead {
  font-size: 0.9rem;
  line-height: 1.65;
  color: rgba(255, 255, 255, 0.92);
  max-width: 340px;
}

.brand-panel .lead strong {
  font-weight: 700;
  color: #fff;
}

.brand-panel .cta-pill {
  display: inline-block;
  margin-top: 28px;
  background: var(--pcv-red);
  color: #fff;
  font-size: 0.875rem;
  font-weight: 600;
  padding: 12px 20px;
  border-radius: 10px;
  text-decoration: none;
  box-shadow: 0 4px 16px rgba(226, 29, 30, 0.4);
  transition: filter 0.2s ease, transform 0.2s ease;
}

.brand-panel .cta-pill:hover {
  filter: brightness(1.05);
  transform: translateY(-1px);
}

/* --- Right form --- */
.form-panel {
  padding: 44px 40px 40px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  background: linear-gradient(180deg, #ffffff 0%, #fafbfd 100%);
  position: relative;
}

.form-panel::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--pcv-green), var(--pcv-blue), var(--pcv-red));
  opacity: 0.85;
}

.form-panel-inner {
  width: 100%;
  max-width: 380px;
  margin: 0 auto;
}

.mobile-brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  margin-bottom: 28px;
}

@media (min-width: 900px) {
  .mobile-brand { display: none; }
}

.mobile-brand .logo-sm {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(66, 116, 49, 0.08);
  box-shadow: 0 6px 20px rgba(66, 116, 49, 0.18);
  border: 2px solid rgba(66, 116, 49, 0.15);
  margin-bottom: 12px;
}

.mobile-brand .logo-sm img {
  display: block;
  width: 86%;
  height: 86%;
  object-fit: contain;
  object-position: center;
}
.mobile-brand .logo-sm img.logo-fallback-svg {
  width: 70%;
  height: 70%;
}
.mobile-brand h2 { font-size: 1.1rem; font-weight: 700; color: var(--pcv-text); }
.mobile-brand p { font-size: 0.8rem; color: var(--pcv-text-muted); margin-top: 4px; }

.form-panel h2 {
  font-size: 1.375rem;
  font-weight: 700;
  color: var(--pcv-text);
  margin-bottom: 6px;
}

.form-panel .subtitle {
  font-size: 0.875rem;
  color: var(--pcv-text-muted);
  margin-bottom: 28px;
}

.form-group { margin-bottom: 18px; }

.form-group label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--pcv-text);
  margin-bottom: 8px;
}

.input-shell {
  position: relative;
}

.input-shell input[type="text"],
.input-shell input[type="password"] {
  width: 100%;
  padding: 13px 44px 13px 14px;
  border-radius: 12px;
  border: 1px solid var(--pcv-border);
  background: var(--pcv-input-bg);
  font-size: 0.95rem;
  color: var(--pcv-text);
  transition: border-color 0.2s, box-shadow 0.2s;
}

.input-shell input::placeholder { color: #94a3b8; }

.input-shell input:focus {
  outline: none;
  border-color: var(--pcv-green);
  box-shadow: 0 0 0 3px rgba(66, 116, 49, 0.22);
}

.pw-toggle {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--pcv-text-muted);
  padding: 8px;
  cursor: pointer;
  border-radius: 8px;
  line-height: 0;
}

.pw-toggle:hover { color: var(--pcv-green); }

.row-options {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 22px;
  font-size: 0.8125rem;
}

.row-options label {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--pcv-text-muted);
  cursor: pointer;
  user-select: none;
}

.row-options input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: var(--pcv-green);
}

.row-options a {
  color: var(--pcv-green);
  font-weight: 500;
  text-decoration: none;
}

.row-options a:hover { text-decoration: underline; }

.btn-login {
  width: 100%;
  padding: 14px 16px;
  border: none;
  border-radius: 12px;
  background: var(--pcv-green);
  color: #fff;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s, transform 0.15s;
  box-shadow: 0 4px 16px rgba(66, 116, 49, 0.38);
}

.btn-login:hover { background: var(--pcv-green-hover); }
.btn-login:active { transform: translateY(1px); }

.btn-login:disabled {
  opacity: 0.65;
  cursor: not-allowed;
  transform: none;
}

.error {
  background: var(--pcv-danger-bg);
  color: var(--pcv-danger);
  padding: 12px 14px;
  border-radius: 10px;
  font-size: 0.875rem;
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  gap: 8px;
  border: 1px solid #fecaca;
}

.footer-note {
  margin-top: 28px;
  padding-top: 20px;
  border-top: 1px solid #e8ecf1;
  text-align: center;
  font-size: 0.75rem;
  color: #94a3b8;
  line-height: 1.5;
}

@media (prefers-reduced-motion: reduce) {
  .brand-panel .logo-wrap { animation: none; }
}
</style>
</head>
<body>

<div class="shell">

  <aside class="brand-panel" aria-hidden="false">
    <div class="logo-wrap">
      <img src="scholarsync-global-logo.jpg" alt="ScholarSync Global" loading="eager" decoding="async" onerror="this.onerror=null;this.src='assets/brand/scholarsync-mark.svg';this.classList.add('logo-fallback-svg');">
    </div>
    <h1>ScholarSync Global</h1>
    <p class="lead">
      Your global <strong>study abroad</strong> and <strong>visa application</strong> partner. We help you explore pathways to study and visit
      in Canada, the USA, Europe, and beyond—with ethical guidance, admissions support, and visa services built around your goals.
    </p>
    <a class="cta-pill" href="https://scholarsyncglobal.ca/" target="_blank" rel="noopener noreferrer">Study abroad &amp; visa services</a>
  </aside>

  <section class="form-panel">
    <div class="form-panel-inner">

      <div class="mobile-brand">
        <div class="logo-sm">
          <img src="scholarsync-global-logo.jpg" alt="" loading="lazy" onerror="this.onerror=null;this.src='assets/brand/scholarsync-mark.svg';this.classList.add('logo-fallback-svg');">
        </div>
        <h2>ScholarSync Global</h2>
        <p>Admin sign in</p>
      </div>

      <h2>Sign in to your account</h2>
      <p class="subtitle">Access your ScholarSync Global dashboard</p>

      <?php if ($error): ?>
        <div class="error" role="alert">
          <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="post" id="loginForm" autocomplete="off" novalidate>
        <div class="form-group">
          <label for="username">Email address</label>
          <div class="input-shell">
            <input
              type="text"
              id="username"
              name="username"
              placeholder="infos@scholarsyncglobal.ca"
              required
              autocomplete="username"
              autofocus
              inputmode="email"
              aria-label="Email address or username"
            >
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-shell">
            <input
              type="password"
              id="password"
              name="password"
              placeholder="••••••••"
              required
              autocomplete="current-password"
              aria-label="Password"
            >
            <button type="button" class="pw-toggle" id="passwordToggle" aria-label="Toggle password visibility">
              <i class="fas fa-eye" id="passwordIcon" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div class="row-options">
          <label>
            <input type="checkbox" id="rememberMe" name="remember_me" value="1">
            <span>Remember me</span>
          </label>
          <a href="admin-forgot-password.php">Forgot password?</a>
        </div>

        <button type="submit" class="btn-login" id="submitBtn">Log in</button>
      </form>

      <div class="footer-note">
        Secure admin access · © <?= date('Y') ?> ScholarSync Global
      </div>

    </div>
  </section>

</div>

<script>
(function () {
  var form = document.getElementById('loginForm');
  var passwordInput = document.getElementById('password');
  var passwordToggle = document.getElementById('passwordToggle');
  var passwordIcon = document.getElementById('passwordIcon');
  var submitBtn = document.getElementById('submitBtn');
  var usernameInput = document.getElementById('username');

  passwordToggle.addEventListener('click', function () {
    var isPw = passwordInput.getAttribute('type') === 'password';
    passwordInput.setAttribute('type', isPw ? 'text' : 'password');
    passwordIcon.classList.toggle('fa-eye', !isPw);
    passwordIcon.classList.toggle('fa-eye-slash', isPw);
  });

  form.addEventListener('submit', function () {
    submitBtn.disabled = true;
  });

  var remembered = localStorage.getItem('admin_username');
  if (remembered && !usernameInput.value) {
    usernameInput.value = remembered;
    passwordInput.focus();
  }

  form.addEventListener('submit', function () {
    if (document.getElementById('rememberMe').checked) {
      localStorage.setItem('admin_username', usernameInput.value);
    } else {
      localStorage.removeItem('admin_username');
    }
  });

  if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
  }
})();
</script>

</body>
</html>
