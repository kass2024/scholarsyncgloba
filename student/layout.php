<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/company_branding.php';
require_once __DIR__ . '/../helpers/urls.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$studentName = trim((string)($_SESSION['student_name'] ?? 'Student'));
$studentEmail = strtolower(trim((string)($_SESSION['student_email'] ?? '')));
$studentAppId = (int)($_SESSION['student_application_id'] ?? 0);
$pageTitle = $pageTitle ?? (PCVC_COMPANY_DISPLAY_NAME . ' — Student Portal');

function pcvc_student_nav_active(string $path): string
{
    $cur = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    return (stripos($cur, $path) !== false) ? 'active' : '';
}

/**
 * Smart sidebar: show only items that exist in DB for this student.
 * Expects $conn (mysqli) to be available in the including page.
 */
$pcvc_has_profile = false;
$pcvc_has_credit = false;
$pcvc_has_loan = false;
$pcvc_has_contract = false;

if (isset($conn) && ($conn instanceof mysqli) && $studentEmail !== '') {
    $st = $conn->prepare("SELECT 1 FROM student_applications WHERE LOWER(TRIM(email)) = ? LIMIT 1");
    if ($st) { $st->bind_param('s', $studentEmail); $st->execute(); $pcvc_has_profile = (bool)$st->get_result()->fetch_assoc(); $st->close(); }

    $st = $conn->prepare("SELECT 1 FROM credit_transfer_applications WHERE LOWER(TRIM(email)) = ? LIMIT 1");
    if ($st) { $st->bind_param('s', $studentEmail); $st->execute(); $pcvc_has_credit = (bool)$st->get_result()->fetch_assoc(); $st->close(); }

    $st = $conn->prepare("SELECT 1 FROM master_loan_applications WHERE LOWER(TRIM(email)) = ? LIMIT 1");
    if ($st) { $st->bind_param('s', $studentEmail); $st->execute(); $pcvc_has_loan = (bool)$st->get_result()->fetch_assoc(); $st->close(); }

    if ($studentAppId > 0) {
        $st = $conn->prepare("SELECT 1 FROM student_contracts WHERE student_id = ? LIMIT 1");
        if ($st) { $st->bind_param('i', $studentAppId); $st->execute(); $pcvc_has_contract = (bool)$st->get_result()->fetch_assoc(); $st->close(); }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars((string)$pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --pcv-green:#173c6d;
      --pcv-blue:#1ba7a0;
      --pcv-red:#f4b52e;
      --bg:#f6f8fb;
      --card:#ffffff;
      --text:#0f172a;
      --muted:#64748b;
      --border:#e2e8f0;
    }
    body{font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;background:var(--bg);color:var(--text);}
    .topbar{
      background:linear-gradient(135deg,var(--pcv-green) 0%, #102d55 60%, #0b2140 100%);
      border-bottom:3px solid var(--pcv-red);
    }
    .topbar .brand{font-weight:800;letter-spacing:.02em}
    .sidebar{
      background:var(--card);
      border-right:1px solid var(--border);
      min-height: calc(100vh - 64px);
      position: sticky;
      top: 64px;
    }
    .nav-pills .nav-link{color:var(--text);border-radius:12px;padding:10px 12px;font-weight:600}
    .nav-pills .nav-link.active{
      background:linear-gradient(135deg, rgba(66,116,49,.12), rgba(54,97,185,.10));
      color:var(--pcv-green);
      border:1px solid rgba(66,116,49,.22);
    }
    .card{border:1px solid var(--border);border-radius:16px;box-shadow:0 10px 30px rgba(2,6,23,.06)}
    .badge-stage{background:rgba(54,97,185,.12);color:var(--pcv-blue);border:1px solid rgba(54,97,185,.24)}
    .muted{color:var(--muted)}
    .kpi{padding:14px 16px;border:1px solid var(--border);border-radius:16px;background:var(--card)}
    .kpi .label{font-size:.8rem;color:var(--muted);font-weight:600}
    .kpi .value{font-size:1.25rem;font-weight:800}
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg topbar navbar-dark" style="min-height:64px">
    <div class="container-fluid px-3">
      <span class="navbar-brand brand"><?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></span>
      <div class="ms-auto d-flex align-items-center gap-3">
        <div class="text-white-50 small d-none d-md-block">Signed in as</div>
        <div class="text-white fw-semibold"><?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?></div>
        <a class="btn btn-sm btn-light" href="<?= htmlspecialchars(pcvc_url('/student/logout.php'), ENT_QUOTES, 'UTF-8') ?>">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container-fluid">
    <div class="row g-0">
      <aside class="col-12 col-lg-2 sidebar p-3">
        <div class="mb-3">
          <div class="small muted fw-semibold">Student portal</div>
          <div class="fw-bold">Applications</div>
        </div>
        <nav class="nav nav-pills flex-column gap-2">
          <?php if ($pcvc_has_profile): ?>
            <a class="nav-link <?= pcvc_student_nav_active('/student/index.php') ?>" href="<?= htmlspecialchars(pcvc_url('/student/index.php'), ENT_QUOTES, 'UTF-8') ?>">All university admissions</a>
          <?php endif; ?>
          <?php if ($pcvc_has_loan): ?>
            <a class="nav-link <?= pcvc_student_nav_active('/student/edit_master_loan.php') ?>" href="<?= htmlspecialchars(pcvc_url('/student/edit_master_loan.php'), ENT_QUOTES, 'UTF-8') ?>">Study Loan Applications</a>
          <?php endif; ?>
          <?php if ($pcvc_has_credit): ?>
            <a class="nav-link <?= pcvc_student_nav_active('/student/edit_credit_transfer.php') ?>" href="<?= htmlspecialchars(pcvc_url('/student/edit_credit_transfer.php'), ENT_QUOTES, 'UTF-8') ?>">Credit Transfer Applications</a>
          <?php endif; ?>
          <?php if ($pcvc_has_contract): ?>
            <a class="nav-link <?= pcvc_student_nav_active('/student/index.php') ?>" href="<?= htmlspecialchars(pcvc_url('/student/index.php'), ENT_QUOTES, 'UTF-8') ?>">Student contract</a>
          <?php endif; ?>

          <div class="mt-3 small muted fw-semibold">My portal</div>
          <?php if ($pcvc_has_profile): ?>
            <a class="nav-link <?= pcvc_student_nav_active('/student/edit_profile.php') ?>" href="<?= htmlspecialchars(pcvc_url('/student/edit_profile.php'), ENT_QUOTES, 'UTF-8') ?>">Track my infos</a>
            <a class="nav-link <?= pcvc_student_nav_active('/student/materials.php') ?>" href="<?= htmlspecialchars(pcvc_url('/student/materials.php'), ENT_QUOTES, 'UTF-8') ?>">Upload materials</a>
          <?php endif; ?>
          <a class="nav-link <?= pcvc_student_nav_active('/student/refund-request.php') ?>" href="<?= htmlspecialchars(pcvc_url('/student/refund-request.php'), ENT_QUOTES, 'UTF-8') ?>">Request refund</a>
          <a class="nav-link <?= pcvc_student_nav_active('/student/refund-requests.php') ?>" href="<?= htmlspecialchars(pcvc_url('/student/refund-requests.php'), ENT_QUOTES, 'UTF-8') ?>">My refund requests</a>
        </nav>
      </aside>
      <main class="col-12 col-lg-10 p-3 p-lg-4">
        <?php if (!empty($flash_success)): ?>
          <div class="alert alert-success"><?= htmlspecialchars((string)$flash_success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if (!empty($flash_error)): ?>
          <div class="alert alert-danger"><?= htmlspecialchars((string)$flash_error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

