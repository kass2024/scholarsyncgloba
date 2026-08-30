<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/company_branding.php';
require_once __DIR__ . '/helpers/csrf.php';
require_once __DIR__ . '/helpers/student_portal_schema.php';
require_once __DIR__ . '/helpers/student_portal_accounts.php';
require_once __DIR__ . '/helpers/urls.php';

pcvc_student_portal_ensure_schema($conn);

// If already logged in, go to dashboard.
if (!empty($_SESSION['student_account_id'])) {
    header('Location: ' . pcvc_url('/student/index.php'));
    exit;
}

$error = '';
$mode = 'login'; // activation disabled (default password login)

$prefillEmail = '';
if (!empty($_GET['email'])) {
    $prefillEmail = pcvc_email_norm((string)$_GET['email']);
}

function pcvc_email_norm(string $email): string {
    $email = trim($email);
    return strtolower($email);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!pcvc_csrf_validate_post()) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'login');
        if ($action === 'login') {
            $email = pcvc_email_norm((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if ($email === '' || $password === '') {
                $error = 'Please enter your email and password.';
            } else {
                $stmt = $conn->prepare("SELECT id, student_application_id, email, password_hash, status FROM student_portal_accounts WHERE email = ? LIMIT 1");
                if (!$stmt) {
                    $error = 'System error. Please try again later.';
                } else {
                    $stmt->bind_param('s', $email);
                    $stmt->execute();
                    $acc = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $defaultPw = PCVC_STUDENT_DEFAULT_PASSWORD;

                    // If account missing OR password wrong, allow default-password login by auto-creating/resetting
                    // as long as email exists in student_applications.
                    $pwOk = ($acc && password_verify($password, (string)$acc['password_hash']));
                    if (!$pwOk) {
                        if ($password !== $defaultPw) {
                            $error = 'Invalid email or password.';
                        } else {
                            // Allow default password login if email exists in ANY supported table.
                            $exists = false;
                            foreach ([
                                "SELECT 1 FROM student_applications WHERE LOWER(TRIM(email)) = ? LIMIT 1",
                                "SELECT 1 FROM credit_transfer_applications WHERE LOWER(TRIM(email)) = ? LIMIT 1",
                                "SELECT 1 FROM master_loan_applications WHERE LOWER(TRIM(email)) = ? LIMIT 1",
                            ] as $q) {
                                $stX = $conn->prepare($q);
                                if (!$stX) continue;
                                $stX->bind_param('s', $email);
                                $stX->execute();
                                if ($stX->get_result()->fetch_assoc()) $exists = true;
                                $stX->close();
                                if ($exists) break;
                            }

                            if (!$exists) {
                                $error = 'Invalid email or password.';
                            } else {
                                // Create/reset portal account for this email (links to student_applications when available).
                                pcvc_student_portal_ensure_account_for_email($conn, $email);

                                $stmtR = $conn->prepare("SELECT id, student_application_id, email, password_hash, status FROM student_portal_accounts WHERE email = ? LIMIT 1");
                                if ($stmtR) {
                                    $emailNorm = pcvc_email_norm($email);
                                    $stmtR->bind_param('s', $emailNorm);
                                    $stmtR->execute();
                                    $acc = $stmtR->get_result()->fetch_assoc();
                                    $stmtR->close();
                                    if (!$acc || ($acc['status'] ?? '') !== 'active' || !password_verify($defaultPw, (string)$acc['password_hash'])) {
                                        $error = 'Invalid email or password.';
                                    }
                                } else {
                                    $error = 'Invalid email or password.';
                                }
                            }
                        }
                    }

                    if ($error !== '') {
                        // stop here
                    } elseif (($acc['status'] ?? '') !== 'active') {
                        $error = 'This account is disabled. Please contact support.';
                    } else {
                        // Load student name from student_applications.
                        $sid = (int)$acc['student_application_id'];
                        $stmt2 = $conn->prepare("SELECT first_name, last_name, email FROM student_applications WHERE id = ? LIMIT 1");
                        $name = 'Student';
                        $sEmail = $email;
                        if ($stmt2) {
                            $stmt2->bind_param('i', $sid);
                            $stmt2->execute();
                            $stu = $stmt2->get_result()->fetch_assoc();
                            $stmt2->close();
                            if ($stu) {
                                $name = trim((string)($stu['first_name'] ?? '') . ' ' . (string)($stu['last_name'] ?? ''));
                                if ($name === '') $name = 'Student';
                                if (!empty($stu['email'])) $sEmail = pcvc_email_norm((string)$stu['email']);
                            }
                        }

                        session_regenerate_id(true);
                        $_SESSION['student_account_id'] = (int)$acc['id'];
                        $_SESSION['student_application_id'] = $sid;
                        $_SESSION['student_email'] = $sEmail;
                        $_SESSION['student_name'] = $name;

                        $up = $conn->prepare("UPDATE student_portal_accounts SET last_login_at = NOW() WHERE id = ?");
                        if ($up) {
                            $aid = (int)$acc['id'];
                            $up->bind_param('i', $aid);
                            $up->execute();
                            $up->close();
                        }

                        header('Location: ' . pcvc_url('/student/index.php'));
                        exit;
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student sign in | <?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{--pcv-green:#427431;--pcv-blue:#3661B9;--pcv-red:#E21D1E;--muted:#64748b;}
    body{font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
      background:
        radial-gradient(ellipse 100% 80% at 15% 10%, rgba(66,116,49,0.35) 0%, transparent 55%),
        radial-gradient(ellipse 80% 60% at 90% 85%, rgba(226,29,30,0.18) 0%, transparent 50%),
        radial-gradient(ellipse 60% 50% at 50% 50%, rgba(54,97,185,0.08) 0%, transparent 60%),
        linear-gradient(168deg, #e8edf4 0%, #f1f5f9 45%, #e2e8f0 100%);
    }
    .shell{max-width:980px;width:100%;background:rgba(255,255,255,.93);border-radius:28px;overflow:hidden;
      box-shadow:0 32px 64px -12px rgba(15,23,42,.22), 0 0 0 1px rgba(255,255,255,.55), inset 0 1px 0 rgba(255,255,255,.8);
      display:grid;grid-template-columns:1fr;
    }
    @media (min-width:900px){.shell{grid-template-columns:1fr 1fr;min-height:560px}}
    .brand{
      background:linear-gradient(165deg,var(--pcv-green) 0%, #2f5a26 55%, #1e3d18 100%);
      color:#fff;padding:46px 34px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;position:relative;
    }
    .brand .logo{width:180px;height:180px;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;
      background:rgba(255,255,255,.12);border:3px solid rgba(255,255,255,.45);
      box-shadow:0 20px 50px rgba(0,0,0,.28), inset 0 0 0 1px rgba(255,255,255,.2);
      margin-bottom:18px;
    }
    .brand .logo img{width:88%;height:88%;object-fit:contain}
    .brand h1{font-size:1.5rem;font-weight:800;line-height:1.25;margin:0 0 12px}
    .brand p{font-size:.9rem;opacity:.92;max-width:340px}
    .panel{padding:44px 40px;background:linear-gradient(180deg,#fff 0%, #fafbfd 100%);position:relative}
    .panel:before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--pcv-green),var(--pcv-blue),var(--pcv-red));opacity:.85}
    .panel-inner{max-width:380px;margin:0 auto}
    .muted{color:var(--muted)}
    .tablink{font-weight:700;text-decoration:none}
    .tablink.active{color:var(--pcv-green)}
  </style>
</head>
<body>
  <div class="shell">
    <aside class="brand">
      <div class="logo">
        <img src="scholarsync-global-logo.jpg" alt="<?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?>" onerror="this.style.display='none'">
      </div>
      <h1><?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="mb-0">Student portal sign in. Track your application status and upload required materials securely.</p>
    </aside>
    <section class="panel">
      <div class="panel-inner">
        <div class="d-flex justify-content-between align-items-end mb-3">
          <div>
            <h2 class="h4 fw-bold mb-1">Student access</h2>
            <div class="muted small">Use your application email · Default password: <span class="fw-semibold">ScholarSync@2026</span></div>
          </div>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

          <form method="post" autocomplete="off" novalidate>
            <?= pcvc_csrf_input() ?>
            <input type="hidden" name="action" value="login">
            <div class="mb-3">
              <label class="form-label fw-semibold">Email</label>
              <input class="form-control" type="email" name="email" required placeholder="you@example.com" value="<?= htmlspecialchars($prefillEmail, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Password</label>
              <input class="form-control" type="password" name="password" required placeholder="••••••••">
            </div>
            <button class="btn btn-success w-100 fw-semibold" type="submit">Sign in</button>
          </form>
      </div>
    </section>
  </div>
</body>
</html>

