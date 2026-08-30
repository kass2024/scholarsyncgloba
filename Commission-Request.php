<?php
session_start();
// Main database (e.g. student_applications)
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/company_branding.php';
require_once __DIR__ . '/helpers/commission_currency.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/commission_cyprus_db.php';

if (empty($_SESSION['username']) && empty($_SESSION['admin_id']) && empty($_SESSION['id'])) {
    header('Location: admin-login.php');
    exit;
}

$pcvcFxSample = pcvc_usd_to_rwf_conversion(1.0);
$pcvcFxRate = $pcvcFxSample['rate'];
$pcvcCadSample = pcvc_cad_to_rwf_conversion(1.0);
$pcvcCadFxRate = $pcvcCadSample['rate'];

// Get current agent info from session
$agentUsername = $_SESSION['username'] ?? '';
$agentInfo = null;

if ($agentUsername) {
    $stmt = $conn->prepare('SELECT id, first_name, last_name, email, phone_number FROM admins WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $agentUsername);
    $stmt->execute();
    $stmt->bind_result($id, $first, $last, $email, $phone);

    if ($stmt->fetch()) {
        $_SESSION['user_id'] = (int) $id;
        $_SESSION['agent_email'] = $email;

        $agentInfo = [
            'first_name' => trim((string) $first),
            'last_name'  => trim((string) $last),
            'email'      => trim((string) $email),
            'phone'      => trim((string) $phone),
        ];
    }

    $stmt->close();
}

if (!$agentInfo && !empty($_SESSION['admin_id'])) {
    $aid = (int) $_SESSION['admin_id'];
    $st2 = $conn->prepare('SELECT id, first_name, last_name, email, phone_number FROM admins WHERE id = ? LIMIT 1');
    if ($st2) {
        $st2->bind_param('i', $aid);
        $st2->execute();
        $st2->bind_result($id2, $f2, $l2, $e2, $p2);
        if ($st2->fetch()) {
            $_SESSION['user_id'] = (int) $id2;
            $_SESSION['agent_email'] = $e2;
            $agentInfo = [
                'first_name' => trim((string) $f2),
                'last_name'  => trim((string) $l2),
                'email'      => trim((string) $e2),
                'phone'      => trim((string) $p2),
            ];
        }
        $st2->close();
    }
}

if (!$agentInfo && !empty($_SESSION['id'])) {
    $aid = (int) $_SESSION['id'];
    $st3 = $conn->prepare('SELECT id, first_name, last_name, email, phone_number FROM admins WHERE id = ? LIMIT 1');
    if ($st3) {
        $st3->bind_param('i', $aid);
        $st3->execute();
        $st3->bind_result($id3, $f3, $l3, $e3, $p3);
        if ($st3->fetch()) {
            $_SESSION['user_id'] = (int) $id3;
            $_SESSION['agent_email'] = $e3;
            $agentInfo = [
                'first_name' => trim((string) $f3),
                'last_name'  => trim((string) $l3),
                'email'      => trim((string) $e3),
                'phone'      => trim((string) $p3),
            ];
        }
        $st3->close();
    }
}

$commissionUserRole = '';
$uidForRole = (int) ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
if ($uidForRole > 0) {
    $sr = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
    if ($sr) {
        $sr->bind_param('i', $uidForRole);
        $sr->execute();
        $sr->bind_result($roleCol);
        if ($sr->fetch()) {
            $commissionUserRole = (string) $roleCol;
        }
        $sr->close();
    }
}
$isCommissionSuperadmin = pcvc_is_superadmin_role($commissionUserRole);

$students = [];
$agentEmail = $_SESSION['agent_email'] ?? '';

$useAjaxStudentSearch = $isCommissionSuperadmin;

// Always preload recruited students for the logged-in agent (including superadmin).
if ($agentEmail) {
    $agentEmailKey = strtolower(trim($agentEmail));
    $stmt1 = $conn->prepare('SELECT id, first_name, last_name, email FROM student_applications WHERE LOWER(TRIM(agent_email)) = ? ORDER BY id DESC LIMIT 120');
    $stmt1->bind_param('s', $agentEmailKey);
    $stmt1->execute();
    $result1 = $stmt1->get_result();

    while ($row = $result1->fetch_assoc()) {
        $students[] = [
            'id'    => 's_' . $row['id'],
            'name'  => trim($row['first_name'] . ' ' . $row['last_name']),
            'email' => $row['email'],
        ];
    }
    $stmt1->close();
}

if (!$agentInfo) {
    header('Location: admin-login.php');
    exit;
}

$pcvcCommissionSubmitRedirect = $isCommissionSuperadmin ? 'commission_report' : 'dashboard';
$agentPhoneReadonly = trim((string) ($agentInfo['phone'] ?? '')) !== '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Commission Request | ScholarSync Global</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/x-icon" href="favicon.ico">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5.min.css" rel="stylesheet">

<style>
/* ===== GLOBAL ===== */
body {
  font-family: 'Inter', system-ui, sans-serif;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  color: #1e293b;
  min-height: 100vh;
  padding: 20px;
  margin: 0;
}

/* ===== LOGO & HEADER ===== */
.pcvc-commission-header {
  text-align: center;
  margin-bottom: 40px;
  padding-top: 20px;
}

.pcvc-commission-logo {
  font-size: 2.8rem;
  font-weight: 900;
  margin: 0;
  background: linear-gradient(135deg, #427431 0%, #E21D1E 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  letter-spacing: -1px;
}

.pcvc-commission-tagline {
  color: #64748b;
  font-size: 1.1rem;
  margin-top: 8px;
  font-weight: 500;
}

/* ===== FORM CONTAINER ===== */
.form-container {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 20px 60px;
}

/* ===== PAGE HEADER ===== */
.page-header {
  text-align: center;
  margin-bottom: 50px;
}

.page-header h1 {
  font-size: 2.2rem;
  font-weight: 800;
  color: #1e293b;
  margin-bottom: 12px;
}

.page-header p {
  font-size: 1.1rem;
  color: #64748b;
  max-width: 700px;
  margin: 0 auto 25px;
  line-height: 1.6;
}

/* ===== SECTION ===== */
.form-section {
  position: relative;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  padding: 32px;
  margin-bottom: 32px;
  box-shadow: 0 8px 25px rgba(15, 23, 42, 0.06);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  overflow: visible;
}

.form-section-student-select.select2-section-open {
  z-index: 100;
}

.form-section:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.1);
}

.form-section::before {
  content: "";
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 5px;
  background: linear-gradient(180deg, #427431, #E21D1E);
  border-radius: 20px 0 0 20px;
}

.form-section h3 {
  font-size: 1.25rem;
  font-weight: 700;
  margin-bottom: 6px;
  color: #1e293b;
}

.section-help {
  font-size: 0.9rem;
  color: #64748b;
  margin-bottom: 24px;
  line-height: 1.5;
}

.commission-hero-bar {
  max-width: 1100px;
  margin: 0 auto 24px;
  padding: 18px 22px;
  border-radius: 16px;
  background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
  border: 1px solid #e2e8f0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.commission-hero-bar strong {
  color: #427431;
  font-size: 1.05rem;
}
.commission-hero-bar .fx-pill {
  font-size: 0.85rem;
  color: #475569;
  background: #fff;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  align-items: center;
}
.commission-hero-bar .fx-rate-item {
  white-space: nowrap;
}

.rwf-preview-box {
  margin-top: 12px;
  padding: 14px 18px;
  border-radius: 12px;
  background: linear-gradient(135deg, rgba(66,116,49,0.08), rgba(54,97,185,0.08));
  border: 1px solid rgba(66,116,49,0.2);
  font-weight: 600;
  color: #1e3a2f;
}
.rwf-preview-box span.num {
  color: #3661B9;
  font-size: 1.15rem;
}

/* ===== INPUTS ===== */
.form-control, .form-select {
  height: 48px;
  font-size: 15px;
  border-radius: 12px;
  border: 1px solid #dbe2ea;
  background-color: #fff;
  transition: all 0.3s ease;
  padding: 0 16px;
}

.form-control:focus, .form-select:focus {
  border-color: #427431;
  box-shadow: 0 0 0 4px rgba(30, 58, 95, 0.15);
  outline: none;
}

.form-control:read-only {
  background-color: #f8fafc;
  cursor: not-allowed;
  color: #64748b;
}

.select2-container--bootstrap-5 .select2-selection {
  min-height: 48px;
  font-size: 15px;
  border-radius: 12px;
  border: 1px solid #dbe2ea;
}

/* Select2 dropdown must sit above following form cards */
.select2-container--open {
  z-index: 2000 !important;
}
.select2-dropdown {
  z-index: 2001 !important;
  border-radius: 12px;
  border-color: #dbe2ea;
  box-shadow: 0 14px 32px rgba(15, 23, 42, 0.16);
}
.select2-container--bootstrap-5 .select2-dropdown .select2-results__option--highlighted {
  background-color: #427431;
}

/* ===== RADIO BUTTONS ===== */
.radio-group {
  display: flex;
  gap: 28px;
  margin-top: 15px;
  flex-wrap: wrap;
}

.radio-item {
  display: flex;
  align-items: center;
  background: #f8fafc;
  padding: 12px 20px;
  border-radius: 10px;
  border: 2px solid #e2e8f0;
  transition: all 0.3s ease;
  cursor: pointer;
}

.radio-item:hover {
  border-color: #cbd5e1;
  background: #f1f5f9;
}

.radio-item input[type="radio"] {
  width: 20px;
  height: 20px;
  margin-right: 12px;
  accent-color: #427431;
  cursor: pointer;
}

.radio-item label {
  margin: 0;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
  font-size: 15px;
}

/* ===== TEXTAREA ===== */
textarea.form-control {
  min-height: 120px;
  padding: 12px 16px;
  line-height: 1.5;
}

/* ===== SUBMIT BUTTON ===== */
.submit-wrap {
  text-align: center;
  margin-top: 50px;
  padding-top: 30px;
  border-top: 1px solid #e2e8f0;
}

.btn-submit {
  background: linear-gradient(135deg, #427431 0%, #3661B9 100%);
  color: #fff;
  border: none;
  padding: 18px 50px;
  font-weight: 700;
  border-radius: 14px;
  font-size: 1.1rem;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  box-shadow: 0 8px 20px rgba(30, 58, 95, 0.2);
}

.btn-submit:hover {
  background: linear-gradient(135deg, #3661B9 0%, #3c6499 100%);
  transform: translateY(-3px);
  box-shadow: 0 12px 25px rgba(30, 58, 95, 0.3);
}

.btn-submit:active {
  transform: translateY(-1px);
}

.btn-submit:disabled {
  background: #94a3b8;
  transform: none;
  box-shadow: none;
  cursor: not-allowed;
}

/* ===== SUBMIT PROGRESS OVERLAY ===== */
#uploadOverlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.88);
  backdrop-filter: blur(12px);
  z-index: 2147483000;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.upload-card {
  background: #fff;
  padding: 36px 32px 32px;
  border-radius: 20px;
  text-align: center;
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
  max-width: 440px;
  width: 100%;
  animation: fadeIn 0.35s ease;
  border: 1px solid rgba(66, 116, 49, 0.2);
}

.submit-progress-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: #1e293b;
  margin-bottom: 6px;
}
.submit-progress-sub {
  font-size: 0.88rem;
  color: #64748b;
  margin-bottom: 22px;
  min-height: 1.4em;
}

.submit-progress-track {
  height: 10px;
  background: #e2e8f0;
  border-radius: 999px;
  overflow: hidden;
  margin-bottom: 14px;
}
.submit-progress-fill {
  height: 100%;
  width: 0%;
  border-radius: 999px;
  background: linear-gradient(90deg, #427431, #3661B9);
  transition: width 0.45s cubic-bezier(0.4, 0, 0.2, 1);
}

.submit-steps {
  display: flex;
  justify-content: space-between;
  gap: 6px;
  margin-top: 18px;
  font-size: 0.68rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.submit-steps span {
  flex: 1;
  padding: 6px 4px;
  border-radius: 8px;
  transition: background 0.25s, color 0.25s;
}
.submit-steps span.active {
  background: rgba(66, 116, 49, 0.12);
  color: #427431;
}
.submit-steps span.done {
  color: #427431;
}

.submit-error-box {
  display: none;
  margin-top: 16px;
  padding: 12px 14px;
  border-radius: 12px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  font-size: 0.88rem;
  text-align: left;
  max-height: 120px;
  overflow-y: auto;
}
.submit-success-icon {
  width: 56px;
  height: 56px;
  margin: 0 auto 14px;
  border-radius: 50%;
  background: linear-gradient(135deg, #427431, #3661B9);
  color: #fff;
  display: none;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
}
.submit-success-icon.show { display: flex; }

.css-spin {
  width: 44px;
  height: 44px;
  margin: 0 auto 16px;
  border: 4px solid #e2e8f0;
  border-top-color: #427431;
  border-radius: 50%;
  animation: pcvcSpin 0.85s linear infinite;
}
@keyframes pcvcSpin {
  to { transform: rotate(360deg); }
}

@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
  body {
    padding: 15px;
  }
  
  .form-container {
    padding: 0 15px 40px;
  }
  
  .pcvc-commission-logo {
    font-size: 2.2rem;
  }
  
  .page-header h1 {
    font-size: 1.8rem;
  }
  
  .form-section {
    padding: 24px;
    border-radius: 16px;
  }
  
  .radio-group {
    gap: 15px;
  }
  
  .radio-item {
    padding: 10px 16px;
    flex: 1;
    min-width: 120px;
  }
  
  .btn-submit {
    padding: 16px 30px;
    width: 100%;
  }
}

/* ===== FOOTER ===== */
.pcvc-commission-footer {
  text-align: center;
  margin-top: 60px;
  padding-top: 30px;
  border-top: 1px solid #e2e8f0;
  color: #94a3b8;
  font-size: 0.9rem;
}

.pcvc-commission-footer p {
  margin: 5px 0;
}

/* ===== VALIDATION HIGHLIGHTS ===== */
.form-control.is-invalid,
.form-select.is-invalid,
.select2-container--bootstrap-5 .select2-selection.is-invalid {
  border-color: #dc2626 !important;
  box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
}
.select2-container--bootstrap-5 .select2-selection.is-invalid {
  min-height: 48px;
}
.radio-group.field-invalid {
  padding: 10px 12px;
  border-radius: 12px;
  border: 2px solid #fecaca;
  background: #fef2f2;
}
.form-validation-banner {
  display: none;
  max-width: 1100px;
  margin: 0 auto 20px;
  padding: 14px 18px;
  border-radius: 14px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  font-size: 0.95rem;
  font-weight: 600;
}
.form-validation-banner.show { display: block; }
</style>
<script src="js/commission_fx.js?v=2"></script>
</head>

<body>

<div class="pcvc-commission-header">
  <h1 class="pcvc-commission-logo"><?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
  <p class="pcvc-commission-tagline">Empowering Global Education Opportunities</p>
</div>

<div class="form-container">
  <div class="commission-hero-bar">
    <div>
      <strong>Request commission</strong>
      <div class="small text-muted mt-1">Enter the amount in USD or CAD; RWF is calculated automatically for payout.</div>
    </div>
    <div class="fx-pill" id="fxPillDisplay">
      <span class="fx-rate-item">1 USD ≈ <strong id="fxUsdRate"><?= htmlspecialchars(number_format($pcvcFxRate, 2), ENT_QUOTES, 'UTF-8') ?></strong> RWF</span>
      <span class="fx-rate-item">1 CAD ≈ <strong id="fxCadRate"><?= htmlspecialchars(number_format($pcvcCadFxRate, 2), ENT_QUOTES, 'UTF-8') ?></strong> RWF</span>
      <span class="text-muted">(live rate, same as checkout; cached daily)</span>
    </div>
  </div>
  <div class="page-header">
    <h1>Commission Request Form</h1>
    <p>Submit your commission requests for recruited students through <?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?>. Supervisors are notified after you submit.</p>
  </div>

  <div id="formValidationBanner" class="form-validation-banner" role="alert"></div>

  <form id="commissionForm" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <!-- AGENT INFORMATION -->
    <section class="form-section">
      <h3>Agent Information</h3>
      <p class="section-help">Your details are pre-filled from your profile and cannot be modified.</p>
      <div class="row g-4">
        <div class="col-md-6">
          <label class="form-label fw-semibold">First Name</label>
          <input class="form-control" name="first_name" value="<?= htmlspecialchars($agentInfo['first_name'] ?? '') ?>" readonly placeholder="First Name" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Last Name</label>
          <input class="form-control" name="last_name" value="<?= htmlspecialchars($agentInfo['last_name'] ?? '') ?>" readonly placeholder="Last Name" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Email Address</label>
          <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($agentInfo['email'] ?? '') ?>" readonly placeholder="Email" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Phone Number</label>
          <input class="form-control" name="phone" value="<?= htmlspecialchars($agentInfo['phone'] ?? '') ?>" <?= $agentPhoneReadonly ? 'readonly' : '' ?> placeholder="Phone Number" required>
        </div>
      </div>
    </section>

    <!-- STUDENT SELECTION -->
    <section class="form-section form-section-student-select" id="studentSelectionSection">
      <h3>Student Selection</h3>
      <p class="section-help">Select the student for whom you're requesting commission from the list below.</p>
      <div class="row g-4">
        <div class="col-12">
          <label class="form-label fw-semibold">Select Student *</label>
          <select id="studentSelect" class="form-select" name="recruited_student_id" required>
            <option value="">-- Select Student --</option>
            <?php if (!$useAjaxStudentSearch): ?>
            <?php foreach ($students as $s): ?>
              <option value="<?= htmlspecialchars($s['id']) ?>">
                <?= htmlspecialchars($s['name']) ?> - <?= htmlspecialchars($s['email']) ?>
              </option>
            <?php endforeach; ?>
            <?php endif; ?>
          </select>
          <?php if ($useAjaxStudentSearch): ?>
          <p class="text-muted small mt-2 mb-0">Open the dropdown to see your recruited students, or type to search all students.</p>
          <?php elseif (count($students) === 0): ?>
          <p class="text-danger small mt-2 mb-0"><strong>No students found.</strong> Link students to your agent email in applications.</p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- COMMISSION AMOUNT -->
    <section class="form-section" id="commissionAmountBlock"
      data-usd-rwf-rate="<?= htmlspecialchars(number_format((float) $pcvcFxRate, 6, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
      data-cad-rwf-rate="<?= htmlspecialchars(number_format((float) $pcvcCadFxRate, 6, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
      <h3>Commission amount</h3>
      <p class="section-help">Enter the commission in US dollars (USD) or Canadian dollars (CAD). Estimated RWF uses the same live exchange rate as student checkout (refreshed daily).</p>
      <div class="row g-4">
        <div class="col-md-4">
          <label class="form-label fw-semibold" for="commissionCurrency">Currency *</label>
          <select class="form-select" name="commission_currency" id="commissionCurrency" required
            onchange="if(window.pcvcOnCurrencyChange){window.pcvcOnCurrencyChange();}">
            <option value="USD">USD — US Dollar</option>
            <option value="CAD">CAD — Canadian Dollar</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold" id="amountLabel" for="amountUsd">Amount requested (USD) *</label>
          <input type="number" class="form-control" name="amount_usd" id="amountUsd" min="0.01" step="0.01" placeholder="e.g. 150.00" required
            oninput="if(window.pcvcUpdateRwfPreview){window.pcvcUpdateRwfPreview();}"
            onchange="if(window.pcvcUpdateRwfPreview){window.pcvcUpdateRwfPreview();}">
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div class="rwf-preview-box w-100">
            Estimated RWF: <span class="num" id="rwfPreview">—</span>
          </div>
        </div>
      </div>
    </section>

    <!-- PAYMENT ADDRESS -->
    <section class="form-section">
      <h3>Payment Address</h3>
      <p class="section-help">Address where commission payment should be sent (optional but recommended for faster processing).</p>
      <div class="row g-4">
        <div class="col-12">
          <label class="form-label fw-semibold">Street Address</label>
          <input class="form-control" name="street_address" placeholder="Street Address">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Address Line 2</label>
          <input class="form-control" name="address_line_2" placeholder="Apartment, Suite, Unit, etc. (Optional)">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">City</label>
          <input class="form-control" name="city" placeholder="City">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">State / Province</label>
          <input class="form-control" name="state" placeholder="State / Province">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Postal Code</label>
          <input class="form-control" name="postal_code" placeholder="Postal Code">
        </div>
      </div>
    </section>

    <!-- APPLICATION DETAILS -->
    <section class="form-section">
      <h3>Application Details</h3>
      <p class="section-help">Provide details about the student's application.</p>
      <div class="row g-4">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Country Applied For</label>
          <input class="form-control" name="country_applied" placeholder="e.g., Canada, Australia, UK">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Submission Date *</label>
          <input type="date" class="form-control" name="date" required>
        </div>
      </div>
    </section>

    <!-- STATUS INFORMATION -->
    <section class="form-section">
      <h3>Status Information</h3>
      <p class="section-help">Please provide the current status of the student's application.</p>

      <div class="row g-4">
        <div class="col-md-6">
          <label class="form-label fw-semibold" id="loanStatusLabel">Loan Status *</label>
          <div class="radio-group" id="loanStatusGroup" aria-labelledby="loanStatusLabel">
            <div class="radio-item">
              <input type="radio" id="loan_approved" name="loan_status" value="APPROVED" required>
              <label for="loan_approved">APPROVED</label>
            </div>
            <div class="radio-item">
              <input type="radio" id="loan_denied" name="loan_status" value="DENIED">
              <label for="loan_denied">DENIED</label>
            </div>
            <div class="radio-item">
              <input type="radio" id="loan_na" name="loan_status" value="NOT APPLICABLE">
              <label for="loan_na">NOT APPLICABLE</label>
            </div>
          </div>
        </div>
        
        <div class="col-md-6">
          <label class="form-label fw-semibold" id="visaStatusLabel">Visa Status *</label>
          <div class="radio-group" id="visaStatusGroup" aria-labelledby="visaStatusLabel">
            <div class="radio-item">
              <input type="radio" id="visa_approved" name="visa_status" value="APPROVED" required>
              <label for="visa_approved">APPROVED</label>
            </div>
            <div class="radio-item">
              <input type="radio" id="visa_denied" name="visa_status" value="DENIED">
              <label for="visa_denied">DENIED</label>
            </div>
            <div class="radio-item">
              <input type="radio" id="visa_na" name="visa_status" value="NOT APPLICABLE">
              <label for="visa_na">NOT APPLICABLE</label>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-5">
        <label class="form-label fw-semibold" id="contractStatusLabel">Contract Status *</label>
        <p class="section-help">Have you signed the recruitment contract with ScholarSync Global?</p>
        <div class="radio-group" id="contractStatusGroup" aria-labelledby="contractStatusLabel">
          <div class="radio-item">
            <input type="radio" id="contract_yes" name="contract_signed" value="YES" required>
            <label for="contract_yes">YES</label>
          </div>
          <div class="radio-item">
            <input type="radio" id="contract_no" name="contract_signed" value="NO">
            <label for="contract_no">NO</label>
          </div>
        </div>
      </div>
    </section>

    <!-- COMMENTS & SIGNATURE -->
    <section class="form-section">
      <h3>Additional Information</h3>
      <p class="section-help">Add any comments and provide your electronic signature.</p>
      <div class="row g-4">
        <div class="col-12">
          <label class="form-label fw-semibold">Comments</label>
          <textarea class="form-control" name="comments" rows="4" placeholder="Any additional notes, special instructions, or comments regarding this commission request..."></textarea>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Electronic Signature *</label>
          <input class="form-control" name="signature" placeholder="Type your full name as electronic signature" required>
          <div class="form-text mt-2">
            By typing your name, you agree to the terms and confirm the accuracy of this request.
          </div>
        </div>
      </div>
    </section>

    <div class="submit-wrap">
      <button type="submit" class="btn-submit">Submit Commission Request</button>
    </div>
  </form>
</div>

<div class="pcvc-commission-footer">
  <p>© <?= date('Y') ?> <?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</p>
  <p>For support, contact: <?php
    $pcvcSe = defined('PCVC_SUPPORT_EMAIL') ? (string) PCVC_SUPPORT_EMAIL : PCVC_COMPANY_SUPPORT_EMAIL;
    echo $pcvcSe !== '' ? htmlspecialchars($pcvcSe, ENT_QUOTES, 'UTF-8') : 'your administrator';
  ?></p>
</div>

<!-- Submit progress overlay -->
<div id="uploadOverlay" aria-hidden="true">
  <div class="upload-card" role="dialog" aria-labelledby="submitProgressTitle" aria-live="polite">
    <div class="submit-success-icon" id="submitSuccessIcon" aria-hidden="true">✓</div>
    <div class="css-spin" id="submitSpinner"></div>
    <div class="submit-progress-title" id="submitProgressTitle">Submitting request</div>
    <p class="submit-progress-sub" id="submitStatusLine">Preparing your data…</p>
    <div class="submit-progress-track">
      <div class="submit-progress-fill" id="submitProgressFill"></div>
    </div>
    <div style="font-size:0.8rem;font-weight:700;color:#64748b;" id="submitPercentLabel">0%</div>
    <div class="submit-steps" id="submitSteps">
      <span data-step="0">Prepare</span>
      <span data-step="1">Send</span>
      <span data-step="2">Save</span>
      <span data-step="3">Done</span>
    </div>
    <div class="submit-error-box" id="submitErrorBox"></div>
    <button type="button" class="btn-submit mt-3" id="submitOverlayClose" style="display:none;padding:12px 28px;font-size:0.95rem;">Close</button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
window.PCVC_COMMISSION_SUBMIT_REDIRECT = <?= json_encode($pcvcCommissionSubmitRedirect) ?>;
window.PCVC_COMMISSION_AJAX_STUDENTS = <?= $useAjaxStudentSearch ? 'true' : 'false' ?>;

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("commissionForm");
  if (!form) return;

  const amountUsd = document.getElementById("amountUsd");
  const validationBanner = document.getElementById("formValidationBanner");
  const overlay = document.getElementById("uploadOverlay");
  const closeBtn = document.getElementById("submitOverlayClose");
  const signatureInput = form.querySelector('input[name="signature"]');

  function getSelectedCurrency() {
    if (typeof window.pcvcGetSelectedCurrency === "function") {
      return window.pcvcGetSelectedCurrency();
    }
    const sel = document.getElementById("commissionCurrency");
    return String(sel && sel.value ? sel.value : "USD").toUpperCase();
  }

  function setStudentSelectInvalid(on) {
    const sel = document.getElementById("studentSelect");
    if (!sel || !window.jQuery) return;
    const $s = window.jQuery(sel);
    const container = $s.next(".select2-container").find(".select2-selection");
    container.toggleClass("is-invalid", !!on);
  }

  function clearFieldErrors() {
    form.querySelectorAll(".is-invalid").forEach((el) => el.classList.remove("is-invalid"));
    form.querySelectorAll(".radio-group.field-invalid").forEach((el) => el.classList.remove("field-invalid"));
    setStudentSelectInvalid(false);
    if (validationBanner) {
      validationBanner.classList.remove("show");
      validationBanner.textContent = "";
    }
  }

  function showValidationErrors(messages, focusEl) {
    const text = messages.length ? "Please complete the highlighted fields: " + messages.join("; ") + "." : "";
    if (validationBanner) {
      validationBanner.textContent = text;
      validationBanner.classList.add("show");
    }
    const target = focusEl || form.querySelector(".is-invalid, .radio-group.field-invalid");
    if (target && typeof target.scrollIntoView === "function") {
      target.scrollIntoView({ behavior: "smooth", block: "center" });
    }
    if (focusEl && typeof focusEl.focus === "function") {
      try { focusEl.focus(); } catch (e) { /* readonly fields */ }
    }
  }

  const submitBtn = form.querySelector("button[type='submit']");
  const ajaxStudents = window.PCVC_COMMISSION_AJAX_STUDENTS === true;
  const $studentSelect = window.jQuery("#studentSelect");
  const studentSearchUrl = new URL("api/commission-student-search.php", window.location.href).href;

  const select2Opts = {
    theme: "bootstrap-5",
    placeholder: ajaxStudents ? "Select or search students…" : "Search for a student…",
    width: "100%",
    allowClear: true,
    dropdownParent: window.jQuery(document.body)
  };

  if (ajaxStudents) {
    select2Opts.minimumInputLength = 0;
    select2Opts.ajax = {
      url: studentSearchUrl,
      dataType: "json",
      delay: 300,
      cache: true,
      data: function (params) {
        return { q: params.term || "" };
      },
      processResults: function (data) {
        var list = (data && Array.isArray(data.results)) ? data.results : [];
        return { results: list };
      },
      transport: function (params, success, failure) {
        var request = window.jQuery.ajax(params);
        request.then(success);
        request.fail(function () {
          failure();
        });
        return request;
      }
    };
    select2Opts.language = {
      searching: function () { return "Searching…"; },
      noResults: function () { return "No students found"; },
      errorLoading: function () { return "Could not load students — try again"; }
    };
  }

  $studentSelect.select2(select2Opts);

  const studentSection = document.getElementById("studentSelectionSection");
  $studentSelect.on("select2:open", function () {
    if (studentSection) studentSection.classList.add("select2-section-open");
  });
  $studentSelect.on("select2:close", function () {
    if (studentSection) studentSection.classList.remove("select2-section-open");
  });

  window.jQuery("#studentSelect").on("change", () => {
    setStudentSelectInvalid(false);
    if (validationBanner && validationBanner.classList.contains("show")) {
      validationBanner.classList.remove("show");
      validationBanner.textContent = "";
    }
  });

  const today = new Date().toISOString().split("T")[0];
  const dateInput = document.querySelector('input[name="date"]');
  if (dateInput) {
    dateInput.value = today;
    dateInput.max = today;
  }

  if (closeBtn && overlay) {
    closeBtn.addEventListener("click", () => {
      overlay.style.display = "none";
      overlay.setAttribute("aria-hidden", "true");
    });
  }

  const fill = document.getElementById("submitProgressFill");
  const pctLabel = document.getElementById("submitPercentLabel");
  const statusLine = document.getElementById("submitStatusLine");
  const titleEl = document.getElementById("submitProgressTitle");
  const spinner = document.getElementById("submitSpinner");
  const successIcon = document.getElementById("submitSuccessIcon");
  const errorBox = document.getElementById("submitErrorBox");
  const stepEls = document.querySelectorAll("#submitSteps span");

  function setBar(p) {
    const x = Math.max(0, Math.min(100, p));
    if (fill) fill.style.width = x + "%";
    if (pctLabel) pctLabel.textContent = Math.round(x) + "%";
  }
  function setStep(i) {
    stepEls.forEach((s, idx) => {
      s.classList.remove("active", "done");
      if (idx < i) s.classList.add("done");
      if (idx === i) s.classList.add("active");
    });
  }
  function openWorkingOverlay() {
    if (!overlay) return;
    if (errorBox) { errorBox.style.display = "none"; errorBox.textContent = ""; }
    if (closeBtn) { closeBtn.style.display = "none"; closeBtn.textContent = "Close"; }
    if (successIcon) successIcon.classList.remove("show");
    if (spinner) spinner.style.display = "block";
    if (titleEl) titleEl.textContent = "Submitting request";
    setBar(0);
    setStep(0);
    if (statusLine) statusLine.textContent = "Preparing your data…";
    overlay.style.display = "flex";
    overlay.setAttribute("aria-hidden", "false");
  }

  function showSubmitSuccess(data) {
    stopProgressTimer();
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = "Submit Commission Request"; }
    setBar(100);
    setStep(3);
    if (spinner) spinner.style.display = "none";
    if (successIcon) successIcon.classList.add("show");
    if (titleEl) titleEl.textContent = "Submitted successfully!";
    const reqId = data.id != null ? data.id : "—";
    if (statusLine) {
      statusLine.textContent = (data.message || "Your commission request was saved.") + " (Request #" + reqId + ")";
    }
    if (errorBox) errorBox.style.display = "none";
    if (closeBtn) {
      closeBtn.style.display = "inline-block";
      closeBtn.textContent = "Done";
      closeBtn.onclick = () => {
        overlay.style.display = "none";
        overlay.setAttribute("aria-hidden", "true");
        const mode = window.PCVC_COMMISSION_SUBMIT_REDIRECT || "dashboard";
        const inFrame = window.parent && window.parent !== window;
        if (inFrame) {
          if (mode === "commission_report" && typeof window.parent.loadInFrame === "function") {
            window.parent.loadInFrame("commission-requests-report.php", "All Commission Requests");
          } else if (typeof window.parent.showDashboard === "function") {
            window.parent.showDashboard();
          }
        } else if (mode === "commission_report") {
          window.location.href = "commission-requests-report.php";
        } else {
          window.location.href = "admin-dashboard.php";
        }
      };
    }
    if (overlay) {
      overlay.style.display = "flex";
      overlay.setAttribute("aria-hidden", "false");
    }
  }

  function showSubmitError(title, line, detail) {
    stopProgressTimer();
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = "Submit Commission Request"; }
    setBar(0);
    if (spinner) spinner.style.display = "none";
    if (successIcon) successIcon.classList.remove("show");
    if (titleEl) titleEl.textContent = title || "Could not submit";
    if (statusLine) statusLine.textContent = line || "Please try again.";
    if (errorBox) {
      errorBox.innerHTML = detail ? String(detail).replace(/</g, "&lt;") : "";
      errorBox.style.display = detail ? "block" : "none";
    }
    if (closeBtn) { closeBtn.style.display = "inline-block"; closeBtn.textContent = "Close"; }
    if (overlay) {
      overlay.style.display = "flex";
      overlay.setAttribute("aria-hidden", "false");
    }
  }

  let progressTimer = null;
  function startIndeterminateProgress() {
    clearInterval(progressTimer);
    let p = 8;
    setBar(p);
    setStep(0);
    progressTimer = setInterval(() => {
      p += 6 + Math.random() * 10;
      if (p > 92) p = 92;
      setBar(p);
      if (p > 22 && statusLine) { setStep(1); statusLine.textContent = "Sending securely to the server…"; }
      if (p > 48 && statusLine) { setStep(2); statusLine.textContent = "Saving commission request…"; }
    }, 380);
  }
  function stopProgressTimer() {
    clearInterval(progressTimer);
    progressTimer = null;
  }

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    clearFieldErrors();

    const studentSelect = document.getElementById("studentSelect");
    const studentVal = (window.jQuery && studentSelect)
      ? String(window.jQuery(studentSelect).val() || "").trim()
      : String(studentSelect?.value || "").trim();
    const loanStatus = form.querySelector('input[name="loan_status"]:checked');
    const visaStatus = form.querySelector('input[name="visa_status"]:checked');
    const contractStatus = form.querySelector('input[name="contract_signed"]:checked');
    const usdVal = parseFloat(String(amountUsd?.value || "").replace(",", "."));
    const signatureInput = form.querySelector('input[name="signature"]');
    const signature = (signatureInput?.value || "").trim();

    const missing = [];
    let firstFocus = null;

    if (!studentVal) {
      missing.push("student");
      setStudentSelectInvalid(true);
      firstFocus = studentSelect;
    }
    if (!isFinite(usdVal) || usdVal <= 0) {
      missing.push("commission amount (" + getSelectedCurrency() + ")");
      if (amountUsd) amountUsd.classList.add("is-invalid");
      if (!firstFocus) firstFocus = amountUsd;
    }
    if (!loanStatus) {
      missing.push("loan status");
      document.getElementById("loanStatusGroup")?.classList.add("field-invalid");
      if (!firstFocus) firstFocus = document.getElementById("loanStatusGroup");
    }
    if (!visaStatus) {
      missing.push("visa status");
      document.getElementById("visaStatusGroup")?.classList.add("field-invalid");
      if (!firstFocus) firstFocus = document.getElementById("visaStatusGroup");
    }
    if (!contractStatus) {
      missing.push("contract status");
      document.getElementById("contractStatusGroup")?.classList.add("field-invalid");
      if (!firstFocus) firstFocus = document.getElementById("contractStatusGroup");
    }
    if (!signature || signature.length < 2) {
      missing.push("electronic signature");
      if (signatureInput) signatureInput.classList.add("is-invalid");
      if (!firstFocus) firstFocus = signatureInput;
    }
    if (dateInput && !dateInput.value) {
      missing.push("submission date");
      dateInput.classList.add("is-invalid");
      if (!firstFocus) firstFocus = dateInput;
    }

    if (missing.length) {
      showValidationErrors(missing, firstFocus);
      if (!studentVal && window.jQuery) {
        window.jQuery("#studentSelect").select2("open");
      }
      return;
    }

    const formData = new FormData(form);
    if (studentVal) formData.set("recruited_student_id", studentVal);

    submitBtn.disabled = true;
    submitBtn.textContent = "Submitting…";

    openWorkingOverlay();
    startIndeterminateProgress();

    const saveUrl = new URL("save_commission.php", window.location.href).href;

    fetch(saveUrl, { method: "POST", body: formData, credentials: "same-origin" })
      .then((res) => res.text().then((raw) => ({ res, raw })))
      .then(({ res, raw }) => {
        let data = null;
        try {
          data = JSON.parse(raw);
        } catch (err) {
          showSubmitError(
            "Something went wrong",
            "The server did not return a valid response.",
            "<strong>HTTP " + res.status + "</strong><br>" + (raw.length > 500 ? raw.slice(0, 500) + "…" : raw)
          );
          console.error("save_commission response:", raw);
          return;
        }
        if (data.status === "success") {
          showSubmitSuccess(data);
          return;
        }
        showSubmitError(
          "Could not submit",
          "Please fix the issue and try again.",
          data.message || raw || "Submission failed."
        );
      })
      .catch(() => {
        showSubmitError("Network error", "Check your connection and try again.", "No response from server.");
      });
  });

  if (signatureInput) {
    signatureInput.addEventListener("input", () => signatureInput.classList.remove("is-invalid"));
    signatureInput.addEventListener("blur", () => {
      const value = signatureInput.value.trim();
      if (value && !/^[A-Za-z\s.,'-]{2,}$/.test(value)) {
        signatureInput.classList.add("is-invalid");
        if (validationBanner) {
          validationBanner.textContent = "Signature: use letters, spaces, and common punctuation (at least 2 characters).";
          validationBanner.classList.add("show");
        }
        signatureInput.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    });
  }

  if (amountUsd) {
    amountUsd.addEventListener("input", () => amountUsd.classList.remove("is-invalid"));
  }
  if (dateInput) {
    dateInput.addEventListener("change", () => dateInput.classList.remove("is-invalid"));
  }

  document.querySelectorAll('.radio-item input[type="radio"]').forEach((radio) => {
    radio.addEventListener("change", () => {
      const name = radio.name;
      const group = radio.closest(".radio-group");
      group?.classList.remove("field-invalid");
      document.querySelectorAll(`.radio-item input[name="${name}"]`).forEach((r) => {
        const item = r.closest(".radio-item");
        if (!item) return;
        if (r.checked) {
          item.style.borderColor = "#427431";
          item.style.background = "#f0f4ff";
        } else {
          item.style.borderColor = "#e2e8f0";
          item.style.background = "#f8fafc";
        }
      });
    });
  });

  document.querySelectorAll(".radio-item").forEach((item) => {
    item.addEventListener("click", function () {
      const radio = this.querySelector('input[type="radio"]');
      if (!radio) return;
      radio.checked = true;
      radio.dispatchEvent(new Event("change", { bubbles: true }));
    });
  });
});
</script>

</body>
</html>