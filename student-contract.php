<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
/**
 * 0. Safety check: DB connection
 */
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    exit("Database connection error.");
}

/**
 * 1. Validate token presence
 */
if (!isset($_GET['token']) || trim($_GET['token']) === '') {
    http_response_code(400);
    exit("Invalid contract link.");
}

$token = trim($_GET['token']);

/**
 * 2. Load contract session
 */
$sql = "
    SELECT *
    FROM student_contracts
    WHERE contract_token = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    exit("Query preparation failed.");
}

$stmt->bind_param("s", $token);
$stmt->execute();
$result   = $stmt->get_result();
$contract = $result->fetch_assoc();
$stmt->close();

/**
 * 3. Token not found
 */
if (!$contract) {
    http_response_code(404);
    exit("This contract link is invalid or expired.");
}

/**
 * 4. Contract state flag (DO NOT EXIT)
 */
$isSigned = ($contract['status'] === 'signed');
$selectedPackageCode = trim((string)($contract['selected_package_code'] ?? ''));
$selectedPackageLabel = trim((string)($contract['selected_package_label'] ?? ''));
$contractPdfPath = trim((string)($contract['pdf_path'] ?? ''));

// Load student signature image (stored as data URL) when signed
$studentSignatureData = null;
if ($isSigned && !empty($contract['id'])) {
    $contractId = (int)$contract['id'];
    $stmt = $conn->prepare("
        SELECT signature_image
        FROM student_signatures
        WHERE contract_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("i", $contractId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!empty($row['signature_image']) && is_string($row['signature_image'])) {
            $studentSignatureData = $row['signature_image'];
        }
    }
}
?>
<?php
/* =====================================================
   LOAD STUDENT DATA FOR SERVER-SIDE RENDERING (SAFE)
===================================================== */
$student = null;
$externalStudent = null;

if (!empty($contract['student_id']) && is_numeric($contract['student_id'])) {

    $studentId = (int) $contract['student_id'];

    $stmt = $conn->prepare("
        SELECT
            first_name,
            last_name,
            email,
            dob,
            nationality,
            passport_number,
            phone_number
        FROM student_applications
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("i", $studentId);
        $stmt->execute();

        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $student = $result->fetch_assoc();
        }

        $stmt->close();
    }
} else {
    // Load external student data from contract record
    $externalStudent = [
        'first_name' => $contract['external_student_name'] ?? '',
        'last_name' => '',
        'email' => $contract['external_student_email'] ?? '',
        'dob' => $contract['external_student_dob'] ?? '',
        'nationality' => $contract['external_student_nationality'] ?? '',
        'passport_number' => $contract['external_student_passport'] ?? '',
        'phone_number' => $contract['external_student_phone'] ?? ''
    ];
    
    // Split full name into first and last name if available
    if (!empty($externalStudent['first_name'])) {
        $nameParts = explode(' ', trim($externalStudent['first_name']), 2);
        $externalStudent['first_name'] = $nameParts[0] ?? '';
        $externalStudent['last_name'] = $nameParts[1] ?? '';
    }
}

// Determine which student data to use
$displayStudent = $student ?? $externalStudent ?? [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'dob' => '',
    'nationality' => '',
    'passport_number' => '',
    'phone_number' => ''
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>International Student Admission & Visa Consultancy Agreement</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
/* =====================================================
   ROOT DESIGN TOKENS
===================================================== */
:root {
  --ink: #111827;
  --muted: #374151;
  --border: #d1d5db;
  --soft: #f9fafb;
  --paper: #ffffff;
  --link: #1d4ed8;
  --warn: #b91c1c;
  --success: #15803d;

  --radius-sm: 6px;
  --radius-md: 10px;

  --shadow-paper: 0 10px 40px rgba(0,0,0,.08);
}

/* =====================================================
   PAGE BACKGROUND - MOBILE FIRST
===================================================== */
body {
  margin: 0;
  padding: 12px 8px;
  background: linear-gradient(180deg, #f8fafc, #e2e8f0);
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  color: var(--ink);
  min-height: 100vh;
}

/* =====================================================
   CONTRACT SHEET - MOBILE FIRST
===================================================== */
.contract-page {
  max-width: 100%;
  margin: 0 auto;
  background: var(--paper);
  padding: 20px 16px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
  border-radius: 12px;

  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  font-size: 14px;
  line-height: 1.6;
  overflow-wrap: anywhere;
  word-break: break-word;
}

/* =====================================================
   RESPONSIVE BREAKPOINTS - MOBILE FIRST
===================================================== */

/* Tablet and up */
@media (min-width: 768px) {
  body {
    padding: 24px 20px;
  }

  .contract-page {
    max-width: 900px;
    padding: 40px 48px;
    font-size: 15px;
    line-height: 1.7;
  }
}

/* Desktop and up */
@media (min-width: 1024px) {
  body {
    padding: 48px 32px;
  }

  .contract-page {
    padding: 64px 72px;
    font-size: 16px;
    line-height: 1.75;
    font-family: "Georgia", "Times New Roman", serif;
  }
}

/* =====================================================
   MOBILE INPUT STYLES - CRITICAL FOR USABILITY
===================================================== */
.contract-page input[type="email"],
.contract-page input[type="text"],
.contract-page input[type="tel"],
.contract-page input[type="date"] {
  width: 100% !important;
  max-width: 100% !important;
  font-size: 16px !important; /* Prevents zoom on iOS */
  padding: 12px 16px !important;
  margin: 8px 0 !important;
  border: 2px solid #e2e8f0 !important;
  border-radius: 8px !important;
  background: #ffffff !important;
  box-sizing: border-box !important;
  transition: border-color 0.2s ease !important;
}

.contract-page input[type="email"]:focus,
.contract-page input[type="text"]:focus,
.contract-page input[type="tel"]:focus,
.contract-page input[type="date"]:focus {
  outline: none !important;
  border-color: #3b82f6 !important;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
}

/* =====================================================
   MOBILE LABEL STYLES
===================================================== */
.contract-page strong {
  display: block !important;
  margin-bottom: 6px !important;
  font-size: 14px !important;
  font-weight: 600 !important;
  color: #374151 !important;
}

/* =====================================================
   MOBILE FORM CONTAINER - IMPROVED
===================================================== */
.contract-page div[style*="margin-bottom"] {
  margin-bottom: 24px !important;
}

/* =====================================================
   ENHANCED MOBILE RESPONSIVENESS
===================================================== */
@media (max-width: 767px) {
  .contract-page {
    padding: 12px 8px !important;
  }
  
  .contract-section {
    padding: 0 8px !important;
  }
  
  /* Improve signature text stacking on mobile */
  .signature-section p {
    font-size: 14px !important;
    line-height: 1.4 !important;
    margin-bottom: 12px !important;
    word-break: keep-all !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
  }
  
  .signature-section strong {
    font-size: 14px !important;
    display: inline !important;
    margin-right: 6px !important;
  }
  
  /* Fix package selection on mobile */
  .package-item {
    margin-bottom: 16px !important;
    padding: 12px !important;
  }
  
  .package-label {
    font-size: 14px !important;
    line-height: 1.4 !important;
  }
  
  .package-details {
    font-size: 13px !important;
    padding-left: 20px !important;
  }
  
  /* Student signature section mobile fixes */
  .signature-block {
    margin-bottom: 24px !important;
  }
  
  /* Input field improvements for mobile */
  .contract-page input[type="text"],
  .contract-page input[type="email"],
  .contract-page input[type="tel"],
  .contract-page input[type="date"] {
    font-size: 16px !important; /* Prevents zoom on iOS */
    padding: 14px 16px !important;
    border-radius: 8px !important;
    border: 2px solid #e5e7eb !important;
  }
  
  .contract-page input:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
    outline: none !important;
  }
}

/* =====================================================
   TABLET OPTIMIZATIONS
===================================================== */
@media (min-width: 768px) and (max-width: 1023px) {
  .contract-page {
    padding: 24px 20px !important;
  }
  
  .signature-grid {
    gap: 32pt 48pt !important;
  }
  
  .signature-canvas {
    height: 170px !important;
  }
}

/* =====================================================
   MOBILE HEADINGS
===================================================== */
.contract-page h1 {
  font-size: 20px !important;
  line-height: 1.3 !important;
  margin-bottom: 16px !important;
  text-align: center !important;
}

.contract-page h3 {
  font-size: 16px !important;
  margin-top: 24px !important;
  margin-bottom: 12px !important;
}

/* =====================================================
   SIGNATURE CANVAS - MOBILE OPTIMIZED
===================================================== */
.signature-canvas {
  width: 100% !important;
  height: 150px !important;
  border: 2px dashed #cbd5e1 !important;
  border-radius: 8px !important;
  background: #ffffff !important;
  touch-action: none !important;
  margin: 16px 0 !important;
  cursor: crosshair;
}

@media (min-width: 768px) {
  .signature-canvas {
    height: 180px !important;
  }
}

@media (min-width: 1024px) {
  .signature-canvas {
    height: 200px !important;
  }
}

/* =====================================================
   BUTTON STYLES - MOBILE FRIENDLY ENHANCED
===================================================== */
.contract-page button {
  padding: 14px 28px !important;
  font-size: 16px !important;
  font-weight: 600 !important;
  border-radius: 8px !important;
  margin: 8px 4px !important;
  min-height: 52px !important;
  min-width: 120px !important;
  touch-action: manipulation !important;
  transition: all 0.2s ease !important;
}

@media (max-width: 767px) {
  .contract-page button {
    width: 100% !important;
    margin: 6px 0 !important;
    max-width: none !important;
  }
}

/* =====================================================
   PACKAGE SELECTION - MOBILE
===================================================== */
.package-label {
  line-height: 1.45 !important;
  font-size: 14px !important;
}

.package-details {
  padding-left: 12px !important;
  font-size: 14px !important;
  line-height: 1.5 !important;
}

/* =====================================================
   TEXT ELEMENTS
===================================================== */
.contract-page p {
  margin: 0 0 14pt 0;
  text-align: justify;
}

.contract-page strong {
  font-weight: 700;
}

/* =====================================================
   LISTS
===================================================== */
.contract-page ul,
.contract-page ol {
  margin: 0 0 16pt 26pt;
  padding: 0;
}

.contract-page li {
  margin-bottom: 8pt;
}

/* =====================================================
   LINKS
===================================================== */
.contract-page a {
  color: var(--link);
  text-decoration: underline;
  font-weight: 500;
}

/* =====================================================
   FORM INPUTS – CONTRACT STYLE
===================================================== */
.contract-page input[type="text"],
.contract-page input[type="email"],
.contract-page input[type="date"],
.contract-page input[type="tel"] {
  width: 100%;
  max-width: 520px;
  padding: 8px 6px;

  font-family: inherit;
  font-size: 12.2pt;

  border: none;
  border-bottom: 1.6px solid var(--ink);
  background: transparent;
  outline: none;
}

.contract-page input:focus {
  border-bottom-color: var(--link);
}

/* =====================================================
   SECTION WRAPPER
===================================================== */
.contract-section {
  max-width: 900px;
  margin: 0 auto;
  padding: 0 72px;
}

/* =====================================================
   ARTICLE 7 – PACKAGE SELECTION
===================================================== */
.package-item {
  margin-bottom: 18pt;
  padding: 12pt 14pt;
  border-radius: var(--radius-md);
  border: 2px solid #e5e7eb;
  transition: background .15s ease, border-color .15s ease, box-shadow .15s ease;
}

.package-item:hover {
  background: var(--soft);
  border-color: #cbd5e1;
}

.package-item.is-selected {
  background: #f0fdf4;
  border-color: #16a34a;
  box-shadow: 0 4px 14px rgba(22, 163, 74, 0.12);
}

.package-label {
  font-weight: 700;
  cursor: pointer;
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 10px;
  align-items: start;
  line-height: 1.45;
}

.package-label input {
  margin-top: 4px;
  width: 18px;
  height: 18px;
  accent-color: #16a34a;
  flex-shrink: 0;
}

.package-label-text {
  min-width: 0;
  word-break: break-word;
}

.article-7-intro {
  padding: 14px 16px;
  margin-bottom: 20px;
  background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
  border: 1px solid #bfdbfe;
  border-radius: var(--radius-md);
  font-size: 14px;
  color: #1e3a5f;
}

.article-7-intro strong {
  color: #0f172a;
}

.signed-status-banner {
  max-width: 900px;
  margin: 0 auto 24px;
  padding: 18px 20px;
  background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
  border: 1px solid #86efac;
  border-radius: var(--radius-md);
  box-shadow: 0 4px 16px rgba(22, 163, 74, 0.1);
}

.signed-status-banner h2 {
  margin: 0 0 8px;
  font-size: 18px;
  color: #14532d;
}

.signed-status-banner p {
  margin: 0 0 14px;
  text-align: left;
  color: #166534;
}

.signed-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.signed-actions a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 12px 18px;
  border-radius: 8px;
  font-weight: 600;
  text-decoration: none;
  font-size: 14px;
}

.signed-actions .btn-download {
  background: #15803d;
  color: #fff;
}

.signed-actions .btn-view {
  background: #fff;
  color: #15803d;
  border: 2px solid #16a34a;
}

.contract-signed .package-label input {
  pointer-events: none;
}

.contract-signed #clearSignature,
.contract-signed #signContract {
  display: none;
}

.package-details {
  display: none;
  margin-top: 10pt;
  padding: 12px 14px 12px 28px;
  font-size: 11.6pt;
  line-height: 1.75;
  color: var(--muted);
  background: #f8faf8;
  border-left: 3px solid #2e7d32;
  border-radius: 0 8px 8px 0;
}

/* Highlighted fee amounts (Article 7 packages) */
.fee-price {
  display: inline-block;
  font-weight: 700;
  color: #0d5c2e;
  background: linear-gradient(135deg, #e8f5e9 0%, #f4fbf4 100%);
  border: 1px solid #81c784;
  border-radius: 5px;
  padding: 2px 7px;
  margin: 0 2px;
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.02em;
  box-shadow: 0 1px 2px rgba(46, 125, 50, 0.12);
}

.package-details strong .fee-price,
.package-details strong:has(.fee-price) {
  font-size: 1.05em;
}

.additional-fees .fee-price {
  font-size: 1em;
}

/* =====================================================
   WARNINGS & NOTES
===================================================== */
.contract-warning {
  margin-top: 22pt;
  font-size: 11.6pt;
  font-style: italic;
  color: var(--warn);
}

/* =====================================================
   ADDITIONAL FEES
===================================================== */
.additional-fees {
  margin-top: 30pt;
  padding-top: 18pt;
  border-top: 1px solid var(--border);
  font-size: 11.6pt;
}

.additional-fees h4 {
  font-size: 13pt;
  font-weight: 700;
  margin-bottom: 12pt;
}

/* =====================================================
   IMPORTANT NOTE BOX
===================================================== */
.important-note {
  margin-top: 18pt;
  padding: 14pt 16pt;
  border-left: 5px solid var(--warn);
  background: #fff5f5;
  font-size: 11.4pt;
  border-radius: var(--radius-sm);
}

/* =====================================================
   SIGNATURE CANVAS
===================================================== */
.signature-canvas {
  width: 100%;
  height: 140px;
  border: 2px dashed #9ca3af;
  border-radius: var(--radius-sm);
  background: #ffffff;
  cursor: crosshair;
}

/* =====================================================
   SIGNATURE GRID - MOBILE FIRST RESPONSIVE
===================================================== */
.signature-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 32pt;
  margin: 32pt 0;
}

@media (min-width: 768px) {
  .signature-grid {
    grid-template-columns: 1fr 1fr;
    gap: 40pt 64pt;
  }
}

@media (min-width: 1024px) {
  .signature-grid {
    gap: 48pt 80pt;
  }
}

/* =====================================================
   BUTTONS (SIGNATURE ACTIONS)
===================================================== */
button {
  font-family: system-ui, sans-serif;
  font-size: 14px;
  font-weight: 600;
  padding: 10px 18px;
  border-radius: var(--radius-sm);
  border: none;
  cursor: pointer;
}

#clearSignature {
  background: #f3f4f6;
  color: var(--ink);
}

#clearSignature:hover {
  background: #e5e7eb;
}

#signContract {
  background: var(--link);
  color: #ffffff;
}

#signContract:hover {
  background: #1e40af;
}

#signContract:disabled {
  background: #9ca3af;
  cursor: not-allowed;
}

/* =====================================================
   FOOTER
===================================================== */
.footer-ref {
  margin-top: 48pt;
  text-align: center;
  font-size: 10.5pt;
  color: #6b7280;
}

/* =====================================================
   PRINT OPTIMIZATION
===================================================== */
@media print {
  body {
    background: #ffffff;
    padding: 0;
  }

  .contract-page {
    box-shadow: none;
    border-radius: 0;
  }

  button {
    display: none;
  }
}
</style>

</head>

<body<?= $isSigned ? ' class="contract-signed"' : '' ?>>

<?php if ($isSigned): ?>
<div class="signed-status-banner">
  <h2>Contract signed successfully</h2>
  <p>
    <?php if ($selectedPackageLabel !== ''): ?>
      Selected package: <strong><?= htmlspecialchars($selectedPackageLabel) ?></strong>
    <?php else: ?>
      Your agreement has been recorded and a signed PDF is available.
    <?php endif; ?>
  </p>
  <div class="signed-actions">
    <a class="btn-download" href="download-contract.php?token=<?= urlencode($token) ?>">Download signed PDF</a>
    <a class="btn-view" href="download-contract.php?token=<?= urlencode($token) ?>&amp;inline=1" target="_blank" rel="noopener">View PDF</a>
  </div>
</div>
<?php endif; ?>

<!-- ============================
     CONTRACT HEADER + ARTICLE 1
============================ -->
<div class="contract-page">

  <!-- MAIN TITLE -->
  <h1 style="
    text-align:center;
    font-size:30px;
    font-weight:700;
    letter-spacing:0.6px;
    margin-bottom:28px;
    text-transform:uppercase;
    color:#111827;
  ">
    INTERNATIONAL STUDENT ADMISSION<br>
    &amp; VISA CONSULTANCY AGREEMENT
  </h1>

  <!-- INTRO PARAGRAPH -->
  <p style="
    font-size:16px;
    text-align:justify;
    margin-bottom:30px;
  ">
    This <strong>International Student Admission and Visa Consultancy Agreement</strong>
    (“<strong>Agreement</strong>”) is made and entered into on the date of signature
    by and between:
  </p>

  <!-- ELECTRONIC NOTICE -->
  <p style="
    font-size:14px;
    font-style:italic;
    color:#374151;
    margin-bottom:40px;
  ">
    Please read this Agreement carefully. By signing electronically, you acknowledge
    that you fully understand and agree to all the terms and conditions contained herein.
  </p>

  <!-- ============================
       ARTICLE 1 – PARTIES
  ============================ -->
  <h3 style="
    font-size:20px;
    font-weight:700;
    margin-bottom:20px;
    color:#111827;
  ">
    1. PARTIES
  </h3>

  <!-- 1.1 Consultant -->
  <p style="font-size:16px; font-weight:700; margin-bottom:8px;">
    1.1 The Consultant
  </p>
<p>
  <strong>ScholarSync Global Company Ltd.</strong><br>
  Registered Address: Gasanze Cell, Nduba Sector, Gasabo District, Kigali – Rwanda<br>
  Email:
  <a href="mailto:infos@scholarsyncglobal.ca">infos@scholarsyncglobal.ca</a>
  &amp;
  <a href="mailto:infos@scholarsyncglobal.ca">infos@scholarsyncglobal.ca</a><br>
  Website:
  <a href="https://www.scholarsyncglobal.ca">www.scholarsyncglobal.ca</a>
  &amp;
  <a href="https://www.scholarsyncglobal.ca">www.scholarsyncglobal.ca</a>
</p>


  <!-- 1.2 Student -->
  <p style="font-size:16px; font-weight:700; margin-bottom:12px;">
    1.2 The Student
  </p>

  <!-- STUDENT DETAILS (AUTOFILL SAFE) -->
  <div style="font-size:15px; max-width:720px;">
<div style="margin-bottom:18px;">
      <strong>Email:</strong>
      <input
        type="email"
        id="student_email"
        name="student_email"
        required
        autocomplete="email"
        value="<?= htmlspecialchars($displayStudent['email'] ?? '') ?>"
        placeholder="Enter your email address">
    </div>
    <div style="margin-bottom:12px;">
      <strong>Full Legal Name:</strong>
      <input
        type="text"
        id="student_name"
        name="student_name"
        required
        autocomplete="name"
        value="<?= htmlspecialchars(trim(($displayStudent['first_name'] ?? '') . ' ' . ($displayStudent['last_name'] ?? ''))) ?>"
        placeholder="Enter your full legal name">
    </div>

    <div style="margin-bottom:12px;">
      <strong>Date of Birth:</strong>
      <input
        type="date"
        id="student_dob"
        name="student_dob"
        required
        autocomplete="bday"
        value="<?= htmlspecialchars($displayStudent['dob'] ?? '') ?>"
        placeholder="Select your date of birth">
    </div>

    <div style="margin-bottom:12px;">
      <strong>Nationality:</strong>
      <input
        type="text"
        id="student_nationality"
        name="student_nationality"
        required
        autocomplete="country-name"
        value="<?= htmlspecialchars($displayStudent['nationality'] ?? '') ?>"
        placeholder="Enter your nationality">
    </div>

    <div style="margin-bottom:12px;">
      <strong>Passport Number:</strong>
      <input
        type="text"
        id="student_passport"
        name="student_passport"
        autocomplete="off"
        value="<?= htmlspecialchars($displayStudent['passport_number'] ?? '') ?>"
        placeholder="Enter your passport number">
    </div>

    <div style="margin-bottom:12px;">
      <strong>Phone:</strong>
      <input
        type="tel"
        id="student_phone"
        name="student_phone"
        required
        autocomplete="tel"
        value="<?= htmlspecialchars($displayStudent['phone_number'] ?? '') ?>"
        placeholder="Enter your phone number">
    </div>

    

  </div>

  <p style="font-size:15px;">
    (Hereinafter referred to as <strong>“The Student”</strong>)
  </p>
<!-- ============================
     ARTICLES 2 – 6
============================ -->

<!-- ARTICLE 2 -->
<h3>2. PURPOSE OF AGREEMENT</h3>

<p>
  This Agreement governs the provision of
  <strong>international study admission, visa consultancy, and related advisory services</strong>
  for the Student intending to study or visit foreign countries, including
  <strong>Canada, the United States of America, Europe, and South Korea</strong>.
  The Student expressly acknowledges and agrees that
  <strong>
    all final decisions rest solely with educational institutions, embassies,
    immigration authorities, and other third-party entities
  </strong>,
  and are beyond the control of the Consultant.
</p>

<!-- ARTICLE 3 -->
<h3>3. SCOPE OF SERVICES</h3>

<p>
  Subject to the specific service package selected by the Student, the Consultant
  shall provide professional assistance which may include, but is not limited to:
</p>

<p>
  <strong>
    admission guidance, visa application assistance, document preparation support,
    interview preparation (where applicable), loan guidance for loan-based programs,
    job search assistance, accommodation search support, and pre-departure orientation
  </strong>.
</p>

<!-- ARTICLE 4 -->
<h3>4. CONSULTANT’S OBLIGATIONS</h3>

<p>The Consultant agrees to perform the services with professionalism and integrity and shall:</p>

<ol>
  <li>Provide services diligently, professionally, and in good faith</li>
  <li>Comply with applicable immigration, embassy, and institutional regulations</li>
  <li>Maintain confidentiality of all Student information in accordance with this Agreement</li>
  <li>Communicate material updates and progress transparently to the Student</li>
  <li>Refrain from falsification, misrepresentation, or unethical practices</li>
</ol>

<!-- ARTICLE 5 -->
<h3>5. STUDENT’S OBLIGATIONS</h3>

<p>The Student agrees and undertakes to:</p>

<ol>
  <li>Provide accurate, complete, and truthful personal and academic information</li>
  <li>Submit only genuine, valid, and verifiable documents</li>
  <li>Pay all applicable fees strictly within the prescribed timelines</li>
  <li>Cooperate fully with the Consultant throughout the admission and visa process</li>
  <li>
    Accept full responsibility for any consequences, delays, refusals, or losses
    arising from false, misleading, or incomplete information provided by the Student
  </li>
</ol>

<!-- ARTICLE 6 -->
<h3>6. NO GUARANTEE DISCLAIMER</h3>

<p>The Student expressly understands and agrees that:</p>

<ul>
  <li>
    Admission outcomes, visa approvals, loan approvals, and processing timelines
    are <strong>not guaranteed</strong> under any circumstances
  </li>
  <li>
    All decisions are made independently by educational institutions, embassies,
    immigration authorities, and other third-party entities
  </li>
  <li>
    Any refusal, delay, or unfavorable outcome shall not constitute a breach
    of this Agreement by the Consultant
  </li>
</ul>


<!-- ============================
     ARTICLE 7 – FEES & PAYMENT TERMS
============================ -->

<div class="contract-section">

<h3>7. FEES & PAYMENT TERMS (CONSOLIDATED PRICING)</h3>

<p>
  The Student shall select <strong>one (1)</strong> applicable service package from the options below.
  Fees apply <strong>only</strong> to the selected package.
</p>

<div class="article-7-intro">
  <strong>How to choose:</strong> Select one package radio button below. The fee details will expand underneath your selection.
  <?= $isSigned ? 'This contract is signed — your selected package is shown below.' : 'You must choose a package before signing at the bottom of this page.' ?>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" value="p70" onclick="showPkg('p70')">
    <span class="package-label-text">7.1 FEES PAID BY STUDENTS FOR OUR PARTNER UNIVERSITIES, COMPANIES, AND SOME TARGETED COUNTRIES</span>
  </label>
  <div id="p70" class="package-details">
    ➤ USA – Registration and Application Fee: USD 150 (Paid once offer of admission is out)<br>
    ➤ Canada – Registration and Application Fee: CAD 225 (Paid once offer of admission is out)<br>
    ➤ Europe – Registration and Application Fee: USD 150 (Paid before starting application)<br>
    ➤ South Korea – Registration and Application Fee: USD 250 (Paid before starting application)<br>
    <em>Note: Students are responsible for all additional costs associated with their visa application and immigration process, including any fees charged by embassies, visa application centers, government agencies, medical institutions, or other relevant authorities.</em>
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p71')">
    7.2 🇺🇸 Study in the USA (Loan-Based)
  </label>
  <div id="p71" class="package-details">
    ✔ Admission Support<br>
    ➤ Registration & Application Fee: USD 150 (Refundable if admission is not secured within 4 months)<br>
    ➤ After Loan Approval: USD 1,200<br>
    ➤ MOCK Interview Preparation Fees: USD 150<br>
    ➤ After Visa Approval: USD 1,500<br>
    <strong>Total Package: USD 3,000</strong>
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p72')">
    7.3 🇺🇸 Study in the USA (Without Loan)
  </label>
  <div id="p72" class="package-details">
    ✔ Admission Support<br>
    ➤ Registration & Application Fee: USD 150 (Refundable if admission is not secured within 4 months)<br>
    ➤ MOCK Interview Preparation Fees: USD 150<br>
    ➤ After Visa Approval: USD 2,000<br>
    <strong>Total Package: USD 2,300</strong>
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p73')">
    7.4 🇪🇺 Study in Europe (Without Loan)
  </label>
  <div id="p73" class="package-details">
    ➤ Registration & Application Fee: USD 250 (Refundable if admission is not secured within 4 months)<br>
    ➤ Before Visa Application: USD 250<br>
    ➤ After Visa Approval: USD 1,500<br>
    <strong>Total Package: USD 2,000</strong>
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p74')">
    7.5 🇨🇦 Study in Canada (Loan-Based)
  </label>
  <div id="p74" class="package-details">
    ➤ Registration & Application Fee: CAD 450 (Refundable if admission is not secured within 4 months)<br>
    ➤ After Visa Approval: CAD 3,050<br>
    <strong>Total Package: CAD 3,500</strong><br>
    <em>Note: Tuition deposit CAD 500–5,000 payable directly by the Student.</em>
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p75')">
    7.6 🇨🇦 Study in Canada (Without Loan)
  </label>
  <div id="p75" class="package-details">
    ➤ Registration & Application Fee: CAD 450 (Refundable if admission is not secured within 4 months)<br>
    ➤ After Visa Approval: CAD 2,050<br>
    <strong>🔥 Your Complete Visa Support Package: CAD 2,500</strong>
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p76')">
    7.7 🇨🇦 Canada – High School Graduate (Loan-Based)
  </label>
  <div id="p76" class="package-details">
    ➤ Registration & Application Fee: CAD 450<br>
    ➤ Study Permit Fees: CAD 150<br>
    ➤ Biometrics Fees: CAD 85<br>
    ➤ CAQ Fees (Quebec Only): CAD 132<br>
    ➤ Border Pass Fees: CAD 250<br>
    ➤ Loan Processing Fees: CAD 1,000<br>
    ➤ Service Fees After Visa Approval: CAD 1,933<br>
    <strong>Total Package: CAD 4,000</strong>
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p77ca')">
    7.8 🇨🇦 Study in Canada (With Your Own Admission Letter)
  </label>
  <div id="p77ca" class="package-details">
    ➤ Document Handling, Visa Application & Biometric Fees: CAD 735<br>
    ➤ Service Fees (payable after visa approval): CAD 1,000<br>
    <strong>🔥 Total Package: CAD 1,735</strong>
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p77')">
    7.9 🇰🇷 Study in South Korea (Self-Sponsored)
  </label>
  <div id="p77" class="package-details">
    ➤ Registration and Application Follow-up fees: USD 500 (Must be paid before starting the admission process; refundable if admission letter is not secured)<br>
    ➤ Self-Sponsored Service Fees – Bachelor: USD 2,000 (Includes free Korean language training for 3 months & Pre-Departure Orientation)<br>
    ➤ Self-Sponsored Service Fees – Master’s: USD 2,400 (Includes free Korean language training for 3 months & Pre-Departure Orientation)<br>
    ➤ Self-Sponsored Service Fees – PhD: USD 2,800 (Includes free Korean language training for 3 months & Pre-Departure Orientation)<br>
    ✔ Once the Final Acceptance Letter is approved, one-half (1/2) of the applicable service fees must be paid before the visa application<br>
    ✔ Free Korean language training (3 months) and Pre-Departure Orientation are provided if needed
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p78')">
    7.10 🇰🇷 South Korea Visitor Visa
  </label>
  <div id="p78" class="package-details">
    ➤ Registration & Application Fee: USD 500<br>
    ➤ Service Fee (Paid After Receiving the Invitation Letter and Guarantee Letter): USD 1,500<br>
    ➤ Participation fees vary depending on the event organizer.
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p79')">
    7.11 Credit Transfer (Bachelor, Masters, PhD)
  </label>
  <div id="p79" class="package-details">
    ➤ Bachelor: USD 920<br>
    ➤ Masters: USD 1,220<br>
    ➤ PhD: USD 1,620
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p710')">
    7.12 🇨🇦 Canada Visit Visa
  </label>
  <div id="p710" class="package-details">
    ➤ Documents & Invitation Letter: USD 1,000<br>
    ➤ Visa Application Fees: CAD 100<br>
    ➤ Biometrics Fees: CAD 85<br>
    ➤ Service Fees (After Visa Approval): CAD 2,000
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p710b')">
    7.13 🇨🇦 Canada Visit Visa – With Invitation Letter
  </label>
  <div id="p710b" class="package-details">
    ➤ Invitation Letter: Already Provided by Applicant<br>
    ➤ Document Preparation and Visa Application Screening: CAD 815<br>
    ➤ Visa Application Fee: CAD 100<br>
    ➤ Biometrics Fee: CAD 85<br>
    ➤ Service Fee (After Visa Approval): CAD 2,000<br>
    <strong>Total Cost: CAD 3,000</strong>
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p711')">
    7.14 🇺🇸 USA Visit Visa
  </label>
  <div id="p711" class="package-details">
    ➤ Documents & Invitation Letter: USD 1,000<br>
    ➤ Visa Application Fees: USD 185<br>
    ➤ Service Fees (After Visa Approval): USD 1,500
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p712')">
    7.15 🇪🇺 Europe Visit Visa
  </label>
  <div id="p712" class="package-details">
    ➤ Documents & Invitation Letter: €600<br>
    ➤ Visa Application Fees: €85 – €500 (depending on country)<br>
    ➤ Service Fees (After Visa Approval): €1,000
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p713')">
    7.16 Asia Visit Visa
  </label>
  <div id="p713" class="package-details">
    ➤ Documents & Invitation Letter: USD 800<br>
    ➤ Visa Application Fees: USD 85 – USD 500<br>
    ➤ Service Fees (After Visa Approval): USD 1,500
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p714')">
    7.17 SHORT COURSES-CANADA
  </label>
  <div id="p714" class="package-details">
    ➤ Registration & Application Fee: CAD 450 (Refundable if admission is not secured within 2 weeks)<br>
    ➤ Registration & Application Fee for Family Member: CAD 200 (If applicable)<br>
    ➤ Tuition Fees Deposit after getting offer letter: CAD 535 (Paid directly to school)<br>
    ➤ Before starting Visa Application: CAD 100 for visit visa application (Paid to embassy)<br>
    ➤ Biometrics: CAD 85 (Paid to embassy)<br>
    ➤ After Visa Approval: CAD 2,500<br>
    <strong>Total Package: CAD 3,670</strong>
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p715')">
    7.18 STUDY PhD IN CANADA-USA-EUROPE & ASIA
  </label>
  <div id="p715" class="package-details">
    ➤ Registration & Application Fee for Canada: CAD 500 (Refundable if admission is not secured within 9 months)<br>
    ➤ Registration & Application Fee for USA, Europe & Asia: USD 350 (Refundable if admission is not secured within 9 months)<br>
    ➤ Paper Publication Fee (Non-Refundable): USD 280 (If applicable)<br>
    ➤ Assistance for PhD Research Proposal Writing (Non-Refundable): USD 300 (If applicable)<br>
    ➤ Visa Application Fee for Family Member (Non-Refundable): Canada CAD 400 (If applicable)<br>
    ➤ Visa Application Fee for Family Member (Non-Refundable): USA, Europe & Asia USD 250 (If applicable)<br>
    ➤ Tuition Fees Deposit after getting offer letter for Canada: CAD 1,000 to CAD 5,000 (Paid directly to school after getting admission)<br>
    ➤ Tuition Fees Deposit after getting offer letter for USA, Europe & Asia: USD 500 to USD 5,000 (Paid directly to school after getting admission)<br>
    ➤ After Visa Approval for Canada: CAD 5,000<br>
    ➤ After Visa Approval for USA, Europe & Asia: USD 4,500<br>
    <em>Note: All visa application fees must be paid to the embassy by the applicant. An additional fee of CAD 800 (Canada) or USD 500 (USA, Europe, and Asia) applies for each family member after visa approval.</em>
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p716')">
    7.19 WES EVALUATION – INTERNATIONAL EQUIVALENCE
  </label>
  <div id="p716" class="package-details">
    <strong>1. Professional Service Fees: CAD 200</strong><br>
    The fee includes professional consultation, guidance, document preparation assistance, and personalized support throughout the WES evaluation process.<br><br>
    <strong>2. Application &amp; Processing Costs: CAD 300</strong><br>
    The amount covers application-related expenses, communication with institutions, document handling, and processing follow-up during the evaluation procedure.<br><br>
    <strong>3. University &amp; Verification Coordination: CAD 100</strong><br>
    The service involves contacting universities, registrars, and authorized offices to ensure transcripts and academic records are properly verified and submitted to WES.<br><br>
    <strong>4. Document Shipping &amp; Delivery Expenses: CAD 100</strong><br>
    The cost also includes courier charges, document shipping, electronic submission support, and tracking to ensure documents safely reach WES on time.<br><br>
    <strong>5. Time, Administrative Work &amp; Follow-up: CAD 200</strong><br>
    Considerable time and administrative effort are required for monitoring submissions, correcting issues, responding to updates, and supporting applicants until the evaluation process is completed successfully.<br><br>
    <strong>🔹 Total Package: CAD 900</strong>
  </div>
</div>

<div class="package-item">
  <label class="package-label">
    <input type="radio" name="package" onclick="showPkg('p717')">
    7.20 GUARANTEED EVALUATION SUPPORT!
  </label>
  <div id="p717" class="package-details">
    <strong>1. Professional Service Fees: CAD 200</strong><br>
    The fee includes professional consultation, guidance, document preparation assistance, and personalized support throughout the all evaluation process.<br><br>
    <strong>2. Application &amp; Processing Costs: CAD 300</strong><br>
    The amount covers application-related expenses, communication with institutions, document handling, and processing follow-up during the evaluation procedure.<br><br>
    <strong>3. University &amp; Verification Coordination: CAD 100</strong><br>
    The service involves contacting universities, registrars, and authorized offices to ensure transcripts and academic records are properly verified and submitted.<br><br>
    <strong>4. Document Shipping &amp; Delivery Expenses: CAD 100</strong><br>
    The cost also includes courier charges, document shipping, electronic submission support, and tracking to ensure documents safely reach on time.<br><br>
    <strong>5. Time, Administrative Work &amp; Follow-up: CAD 200</strong><br>
    Considerable time and administrative effort are required for monitoring submissions, correcting issues, responding to updates, and supporting applicants until the evaluation process is completed successfully.<br><br>
    <strong>🔹 Total packages: CAD 900</strong>
  </div>
</div>

<p class="contract-warning">
  ⚠ <strong>Important:</strong> All government fees, embassy charges, biometric fees,
  tuition deposits, lawyer fees, border pass fees, and third-party charges
  are paid separately by the Student and are non-refundable once submitted.
</p>

<div class="additional-fees">
  <h4>Additional Pricing Provisions (Without Loan & Special Services)</h4>

  <p>1. Spring, Winter, Summer, or Fall Short Courses (Worldwide)</p>
  <ul>
    <li>Application and Registration Fees: <strong>EUR 250</strong>, refundable if approval is not secured within four (4) months.</li>
    <li>Service Fees: <strong>EUR 2,000</strong>, payable only once the visa is approved.</li>
  </ul>

  <p>2. Canadian Immigration Lawyer – Visa Application (Canada Only)</p>
  <p>Additional charge of <strong>CAD 300 per applicant</strong>.</p>

  <p>3. Canadian Immigration Lawyer – Legal Advice or Consultation (Canada Only)</p>
  <p>Consultation fee of <strong>CAD 300</strong>.</p>

  <div class="important-note">
    <strong>⚠ Important:</strong>
    All government fees, embassy charges, biometric fees, SEVIS fees, tuition deposits,
    lawyer fees, border pass fees, and third-party charges are non-refundable once submitted.
  </div>
</div>


</div>

<!-- ============================
     ARTICLE 8 – PAYMENT OF SERVICE FEES
============================ -->

<div class="contract-section">

<h3>8. PAYMENT OF SERVICE FEES</h3>

<p>
  Where applicable, final service fees become immediately payable upon visa approval.
  Once the visa is approved, the Student shall pay all outstanding service fees
  <strong>no later than five (5) days</strong> from the date of approval.
  Failure to make payment within this period constitutes a
  <strong>material breach of this Agreement</strong> and may result in
  legal action and/or enforcement in accordance with applicable law.
</p>

</div>

<!-- ============================
     ARTICLE 9 – REFUND POLICY
============================ -->

<div class="contract-section">

<h3>9. REFUND POLICY</h3>

<p>
  Only registration fees are refundable strictly under the conditions expressly
  stated in this Agreement. All other fees, including but not limited to
  <strong>service fees, loan processing fees, legal fees, and third-party charges</strong>,
  are non-refundable regardless of visa or admission outcome.
</p>

</div>

<!-- ============================
     ARTICLE 10 – TERMINATION
============================ -->

<div class="contract-section">

<h3>10. TERMINATION</h3>

<p>
  The Consultant reserves the right to terminate this Agreement immediately
  in the event of non-payment, submission of fraudulent or misleading documents,
  or breach of any obligation by the Student. Termination shall not release
  the Student from the obligation to pay all outstanding fees already incurred.
</p>

</div>

<!-- ============================
     ARTICLE 11 – LIMITATION OF LIABILITY
============================ -->

<div class="contract-section">

<h3>11. LIMITATION OF LIABILITY</h3>

<p>
  The Consultant shall not be liable for decisions, delays, refusals, or outcomes
  issued by embassies, educational institutions, government authorities,
  policy changes, or other third-party entities beyond the Consultant’s control.
</p>

</div>

<!-- ============================
     ARTICLE 12 – CONFIDENTIALITY
============================ -->

<div class="contract-section">

<h3>12. CONFIDENTIALITY</h3>

<p>
  All information exchanged between the parties shall be treated as confidential
  and shall not be disclosed to any third party except where disclosure is
  required by law or by competent authorities.
</p>

</div>

<!-- ============================
     ARTICLE 13 – GOVERNING LAW & JURISDICTION
============================ -->

<div class="contract-section">

<h3>13. GOVERNING LAW &amp; JURISDICTION</h3>

<p>
  This Agreement shall be governed by and construed in accordance with the
  laws of the <strong>Republic of Rwanda</strong>, with exclusive jurisdiction
  vested in the competent courts of Rwanda.
</p>

</div>

<!-- ============================
     ARTICLE 14 – ENTIRE AGREEMENT
============================ -->

<div class="contract-section">

<h3>14. ENTIRE AGREEMENT</h3>

<p>
  This Agreement constitutes the entire understanding between the parties
  and supersedes all prior discussions or representations. Any amendment or
  modification shall be valid only if made in writing and signed by both parties.
</p>

</div>
<!-- ============================
     15. SIGNATURES - TWO COLUMNS
     Left: Dr. Twajamahoro | Right: Student e-sign
============================ -->
<div class="contract-section signature-section" style="margin-top:40px;">

<h3 style="font-size:20px;font-weight:700;margin-bottom:32px;">
15. SIGNATURES
</h3>

<div class="signature-grid">

<!-- LEFT: ScholarSync Global / Dr. Twajamahoro -->
<div class="signature-block">
<p style="font-weight:700;margin-bottom:18px;font-size:16px;">
For ScholarSync Global Co. Ltd
</p>

<div style="margin-bottom:18px;">
<strong>Name:</strong>
<div style="border-bottom:1px solid #000;height:24px;width:85%;margin-top:6px;padding-top:2px;">
Dr. Jean Pierre Twajamahoro
</div>
</div>

<div style="margin-bottom:18px;">
<strong>Title:</strong>
<div style="border-bottom:1px solid #000;height:24px;width:85%;margin-top:6px;padding-top:2px;">
Owner &amp; Managing Director
</div>
</div>

<div style="margin-bottom:18px;">
<strong>Signature:</strong>
<div style="margin-top:6px;width:85%;">
<img src="admin/signature-manager.png" alt="Managing Director Signature" style="max-height:70px;max-width:280px;display:block;">
</div>
</div>

<div style="margin-bottom:18px;">
<strong>Company Stamp:</strong>
<div style="margin-top:6px;width:85%;">
<img src="admin/employer-signature.png" alt="Company Stamp" style="max-height:140px;max-width:320px;display:block;">
</div>
</div>

<div style="margin-bottom:18px;">
<strong>Date:</strong>
<div style="border-bottom:1px solid #000;height:24px;width:85%;margin-top:6px;padding-top:2px;" id="scholarsync_date">
<?= date('Y/m/d') ?>
</div>
</div>
</div>

<!-- RIGHT: Student details + e-sign -->
<div class="signature-block" style="
padding:20px;
background:#f9fafb;
border-radius:12px;
border:1px solid #e5e7eb;
">

<p style="font-weight:700;margin-bottom:20px;font-size:16px;">
For the Student
</p>

<div style="margin-bottom:20px;">
<label style="display:block;margin-bottom:8px;font-weight:600;color:#374151;">Full Name:</label>
<input type="text" id="sig_student_name"
style="
width:100%;
border:2px solid #e5e7eb;
border-radius:6px;
padding:12px 16px;
font-size:16px;
box-sizing:border-box;
transition:border-color 0.2s ease;
"
placeholder="Enter your full legal name">
</div>

<div style="margin-bottom:20px;">
<label style="display:block;margin-bottom:12px;font-weight:600;color:#374151;">Signature:</label>
<div style="
border:2px dashed #9ca3af;
height:150px;
padding:8px;
margin-bottom:14px;
background:#ffffff;
border-radius:8px;
display:flex;
align-items:center;
justify-content:center;
position:relative;
">

<?php if ($isSigned && !empty($studentSignatureData)): ?>
<img src="<?= $studentSignatureData ?>" style="max-height:130px;" alt="Student Signature">
<?php else: ?>
<canvas class="signature-canvas"></canvas>
<div style="position:absolute;top:8px;right:8px;font-size:12px;color:#9ca3af;">
Draw your signature above
</div>
<?php endif; ?>

</div>
</div>

<div style="margin-bottom:24px;">
<label style="display:block;margin-bottom:8px;font-weight:600;color:#374151;">Date:</label>
<input type="date" id="sig_signed_date"
style="
width:100%;
border:2px solid #e5e7eb;
border-radius:6px;
padding:12px 16px;
font-size:16px;
box-sizing:border-box;
transition:border-color 0.2s ease;
">
</div>

<div style="display:flex;gap:12px;flex-wrap:wrap;">
<button id="clearSignature" type="button" style="flex:1;background:#f3f4f6;color:#374151;border:none;">Clear Signature</button>
<button id="signContract" type="button" style="flex:2;background:#3b82f6;color:#ffffff;border:none;">Sign & Submit Contract</button>
<input type="hidden" id="signatureData">
<input type="hidden" id="selected_package_code" value="<?= htmlspecialchars($selectedPackageCode) ?>">
</div>

</div>

</div> <!-- End signature grid -->

</div>
  <!-- FOOTER REF -->
  <div style="
    text-align:center;
    margin-top:40px;
    font-size:12px;
    color:#6b7280;
  ">
    Contract Reference: <?= htmlspecialchars($contract['contract_token']) ?>
  </div>

</div>
</div>
<script>
(() => {
  /* ==========================
     CONFIG & ELEMENTS
  ========================== */
  const canvas = document.querySelector('.signature-canvas');
  if (!canvas) {
    return;
  }
  const ctx = canvas.getContext('2d');

  const btnClear = document.getElementById('clearSignature');
  const btnSubmit = document.getElementById('signContract');

  const inputName = document.getElementById('sig_student_name');
  const inputDate = document.getElementById('sig_signed_date');
  const hiddenSignature = document.getElementById('signatureData');

  // Auto-fill signature name + date from main student fields
  const mainStudentName = document.getElementById('student_name');
  const todayIso = new Date().toISOString().slice(0, 10);
  if (inputDate && !inputDate.value) inputDate.value = todayIso;
  if (mainStudentName && inputName && !inputName.value) inputName.value = (mainStudentName.value || '').trim();
  if (mainStudentName && inputName) {
    mainStudentName.addEventListener('input', () => {
      inputName.value = (mainStudentName.value || '').trim();
    });
  }

  let drawing = false;
let points = [];


  /* ==========================
     CANVAS SETUP (RETINA SAFE)
  ========================== */
  function resizeCanvas() {
    const ratio = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();

    canvas.width = rect.width * ratio;
    canvas.height = rect.height * ratio;

    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.lineWidth = 2;
    ctx.lineCap = "round";
    ctx.strokeStyle = "#000";
  }

  resizeCanvas();
  window.addEventListener('resize', resizeCanvas);

  /* ==========================
     DRAWING HELPERS
  ========================== */
  function getPos(e) {
    const rect = canvas.getBoundingClientRect();

    if (e.touches) {
      return {
        x: e.touches[0].clientX - rect.left,
        y: e.touches[0].clientY - rect.top
      };
    }
    return { x: e.offsetX, y: e.offsetY };
  }

  function startDraw(e) {
  e.preventDefault();
  drawing = true;
  points = [];

  const pos = getPos(e);
  points.push(pos);

  ctx.beginPath();
  ctx.moveTo(pos.x, pos.y);
}

function draw(e) {
  if (!drawing) return;
  e.preventDefault();

  const pos = getPos(e);
  points.push(pos);

  // First points: draw simple line
  if (points.length < 3) {
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    return;
  }

  // Take last 3 points
  const p0 = points[points.length - 3];
  const p1 = points[points.length - 2];
  const p2 = points[points.length - 1];

  // Midpoint between p1 and p2
  const midX = (p1.x + p2.x) / 2;
  const midY = (p1.y + p2.y) / 2;

  ctx.beginPath();
  ctx.moveTo(p0.x, p0.y);
  ctx.quadraticCurveTo(p1.x, p1.y, midX, midY);
  ctx.stroke();
}

function stopDraw() {
  drawing = false;
  points = [];
}

  /* ==========================
     EVENT LISTENERS
  ========================== */
  canvas.addEventListener('mousedown', startDraw);
  canvas.addEventListener('mousemove', draw);
  canvas.addEventListener('mouseup', stopDraw);
  canvas.addEventListener('mouseleave', stopDraw);

  canvas.addEventListener('touchstart', startDraw, { passive: false });
  canvas.addEventListener('touchmove', draw, { passive: false });
  canvas.addEventListener('touchend', stopDraw);

  /* ==========================
     CLEAR SIGNATURE
  ========================== */
  btnClear.addEventListener('click', () => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
  });

  /* ==========================
     VALIDATION HELPERS
  ========================== */
  function hasSignature() {
    const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
    return pixels.some(channel => channel !== 0);
  }

  /* ==========================
     SUBMIT SIGNATURE - ENHANCED VALIDATION
  ========================== */
 btnSubmit.addEventListener('click', () => {

  /* ==========================
     1. SAFETY CHECKS
  ========================== */
  if (!inputName || !inputDate || !canvas) {
    alert("Required signature fields are missing. Please reload the page.");
    return;
  }

  /* ==========================
     2. PACKAGE SELECTION (ARTICLE 7)
  ========================== */
  const selectedRadio = document.querySelector('input[name="package"]:checked');

  if (!selectedRadio) {
    alert("Please select one service package under Article 7 before signing.");
    selectedRadio?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

  const selectedPackageLabel = selectedRadio
    .closest('label')
    ?.textContent
    ?.trim();

  if (!selectedPackageLabel) {
    alert("Invalid package selection. Please reselect your package.");
    return;
  }

  /* ==========================
     3. STUDENT NAME VALIDATION
  ========================== */
  const studentName = inputName.value.trim();
  if (!studentName) {
    alert("Please enter your full name before signing.");
    inputName.focus();
    return;
  }

  /* ==========================
     4. SIGNING DATE VALIDATION
  ========================== */
  const signedDate = inputDate.value;
  if (!signedDate) {
    alert("Please select the signing date.");
    inputDate.focus();
    return;
  }

  /* ==========================
     5. SIGNATURE VALIDATION
  ========================== */
  if (!hasSignature()) {
    alert("Please draw your signature before submitting.");
    return;
  }

  /* ==========================
     6. STUDENT DATA VALIDATION (FOR NEW STUDENTS)
  ========================== */
  const emailInput = document.getElementById('student_email');
  const dobInput = document.getElementById('student_dob');
  const nationalityInput = document.getElementById('student_nationality');
  const passportInput = document.getElementById('student_passport');
  const phoneInput = document.getElementById('student_phone');

  if (!emailInput?.value.trim()) {
    alert("Please enter your email address.");
    emailInput?.focus();
    return;
  }

  if (!emailInput.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
    alert("Please enter a valid email address.");
    emailInput?.focus();
    return;
  }

  /* ==========================
     7. SHOW SUBMISSION PROGRESS
  ========================== */
  btnSubmit.disabled = true;
  btnSubmit.textContent = 'Submitting...';
  btnSubmit.style.background = '#6b7280';

  /* ==========================
     8. CAPTURE SIGNATURE
  ========================== */
  const signature = canvas.toDataURL("image/png");
  hiddenSignature.value = signature;

  /* ==========================
     9. SUBMIT (FINAL)
  ========================== */
  submitSignature(
    signature,
    studentName,
    signedDate,
    selectedPackageLabel
  );
});


  /* ==========================
     SEND TO BACKEND
  ========================== */
function submitSignature(signature, name, date, selectedPackage) {

  /* ==========================
     1. HARD SAFETY CHECKS
  ========================== */
  if (!signature || !name || !date || !selectedPackage) {
    alert("Missing required data. Please review the form and try again.");
    return;
  }

  /* ==========================
     2. STUDENT FIELD REFERENCES
  ========================== */
  const emailInput       = document.getElementById('student_email');
  const dobInput         = document.getElementById('student_dob');
  const nationalityInput = document.getElementById('student_nationality');
  const passportInput    = document.getElementById('student_passport');
  const phoneInput       = document.getElementById('student_phone');

  if (!emailInput || !dobInput || !nationalityInput || !passportInput || !phoneInput) {
    alert("Student information fields are missing. Please reload the page.");
    return;
  }

  /* ==========================
   BUILD PAYLOAD
========================== */
const payload = {
  token: "<?= htmlspecialchars($token) ?>",

  /* ==========================
     📦 ARTICLE 7 – PACKAGE (LOCKED)
  ========================== */
  selected_package_label: selectedPackage,
  selected_package_code: document.getElementById('selected_package_code')?.value || null,

  /* ==========================
     ✍️ SIGNATURE DATA
  ========================== */
  student_name: name,
  signed_date: date,
  signature: signature,

  /* ==========================
     👤 STUDENT DATA
  ========================== */
  student_email: emailInput.value.trim(),
  student_dob: dobInput.value,
  student_nationality: nationalityInput.value.trim(),
  student_passport: passportInput.value.trim(),
  student_phone: phoneInput.value.trim()
};

/* ==========================
   FINAL VALIDATION - ENHANCED
========================== */
if (!payload.student_email) {
  alert("Student email is required.");
  emailInput.focus();
  return;
}

if (!payload.student_email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
  alert("Please enter a valid email address.");
  emailInput.focus();
  return;
}

if (!payload.student_name || payload.student_name.trim().length < 2) {
  alert("Please enter your full name (at least 2 characters).");
  return;
}

if (!payload.selected_package_label) {
  alert("Selected package is missing. Please reselect a package under Article 7.");
  return;
}

if (!payload.selected_package_code) {
  alert("Package code is missing. Please click a package under Article 7 again, then sign.");
  return;
}

function resetSubmitButton() {
  const btn = document.getElementById('signContract');
  if (!btn) return;
  btn.disabled = false;
  btn.textContent = 'Sign & Submit Contract';
  btn.style.background = '#3b82f6';
}

function extractServerErrorMessage(responseText, fallback) {
  const trimmed = (responseText || '').trim();
  if (!trimmed) {
    return fallback;
  }

  try {
    const parsed = JSON.parse(trimmed);
    if (parsed && parsed.error) {
      return parsed.error;
    }
  } catch (e) {
    // not JSON
  }

  const textOnly = trimmed.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
  if (textOnly.length > 0 && textOnly.length <= 500) {
    return textOnly;
  }

  return fallback;
}

/* ==========================
   SUBMIT TO BACKEND
========================== */
fetch("submit-signature.php", {
  method: "POST",
  headers: {
    "Content-Type": "application/json"
  },
  body: JSON.stringify(payload)
})
.then(async res => {
  let data;

  // Debug: Log the raw response before attempting to parse
  const responseText = await res.text();
  console.log('Raw server response:', responseText);
  console.log('Response status:', res.status);
  console.log('Response headers:', Object.fromEntries(res.headers.entries()));

  try {
    data = JSON.parse(responseText);
  } catch (e) {
    console.error('JSON parse error:', e);
    console.error('Response text that failed to parse:', responseText);
    resetSubmitButton();
    throw new Error(extractServerErrorMessage(
      responseText,
      'Server returned an invalid response while signing. Please try again or contact support.'
    ));
  }

  // HTTP-level error but JSON returned
  if (!res.ok) {
    resetSubmitButton();
    throw new Error(data.error || "Server error");
  }

  return data;
})
.then(data => {

  // ✅ SUCCESS
  if (data.success) {
    if (data.pdf_error) {
      alert(
        "Contract signed successfully, but the PDF could not be generated automatically.\n\n" +
        "Reason: " + data.pdf_error + "\n\n" +
        "You can reload the page and try Download again."
      );
    } else {
      alert(
        "Contract signed successfully.\n\n" +
        "You can now download or view the signed agreement."
      );
    }
    window.location.reload();
    return;
  }

  resetSubmitButton();

  // ⚠️ EXPECTED CASE: already signed
  if (data.error && data.error.toLowerCase().includes("already signed")) {
    alert(
      "This contract has already been signed.\n\n" +
      "You can now download or view the signed agreement."
    );
    window.location.reload();
    return;
  }

  // ❌ OTHER BACKEND ERRORS
  alert(data.error || "Submission failed.");

})
.catch(err => {
  console.error("Signature submission error:", err);
  resetSubmitButton();

  alert(err?.message || (
    "Unable to submit at this time.\n" +
    "Please check your connection and try again."
  ));
});


}

})();
</script>
<script>
(() => {
  'use strict';

  /* =====================================================
     FIELD REFERENCES (REAL INPUTS ONLY)
  ===================================================== */
  const fields = {
    email: document.getElementById('student_email'),
    name: document.getElementById('student_name'),
    dob: document.getElementById('student_dob'),
    nationality: document.getElementById('student_nationality'),
    passportNumber: document.getElementById('student_passport'), // ✅ REAL TEXTBOX
    phone: document.getElementById('student_phone')
  };

  /* =====================================================
     SAFETY CHECK
  ===================================================== */
  if (!fields.email) {
    console.warn('Student autofill: email field not found');
    return;
  }

  const DEBOUNCE_DELAY = 500;
  let debounceTimer   = null;
  let emailConfirmed = false;
  let autofilled     = false;

  /* =====================================================
     EMAIL-ONLY LIVE SEARCH
  ===================================================== */
/* =====================================================
   EMAIL INPUT LISTENER (RESET + SEARCH)
===================================================== */
fields.email.addEventListener('input', () => {
  const email = fields.email.value.trim();

  // ⛔ Reset everything immediately on email change
  resetStudentFields();

  clearTimeout(debounceTimer);

  // Too short → do nothing, manual entry allowed
  if (email.length < 3) {
    return;
  }

  // ⏳ Debounced search
  debounceTimer = setTimeout(() => {
    searchByEmail(email);
  }, DEBOUNCE_DELAY);
});
function resetStudentFields() {
  autofilled = false;
  emailConfirmed = false;

  Object.entries(fields).forEach(([key, input]) => {
    if (!input) return;

    // Clear all except email
    if (key !== 'email') {
      input.value = '';
    }

    input.readOnly = false;
    input.style.backgroundColor = '#fff';
  });

  console.log('Student fields reset due to email change');
}

  /* =====================================================
     FETCH STUDENT BY EMAIL
  ===================================================== */
  function searchByEmail(email) {
    fetch('student-autofill.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email })
    })
      .then(res => res.json())
      .then(data => {
        if (!data || !data.possible_match || !data.student) return;
        autofillStudent(data.student);
      })
      .catch(err => console.error('Student autofill error:', err));
  }

  /* =====================================================
     AUTOFILL (SAFE & CLEAN) - ENHANCED FOR NEW STUDENTS
  ===================================================== */
  function autofillStudent(student) {
    console.log('autofillStudent called with:', student);
    if (!student || autofilled) return;

    // Check if fields are already populated with REAL data (not placeholders)
    const nameValue = fields.name.value.trim();
    const dobValue = fields.dob.value;
    const nationalityValue = fields.nationality.value.trim();
    const phoneValue = fields.phone.value.trim();
    const passportValue = fields.passportNumber.value.trim();
    
    // Check for placeholder text vs real data
    const hasRealName = nameValue && nameValue !== 'Enter your full legal name';
    const hasRealDob = dobValue && dobValue !== 'mm/dd/yyyy';
    const hasRealNationality = nationalityValue && nationalityValue !== 'Select nationality';
    const hasRealPhone = phoneValue && phoneValue !== '+250780000000'; // Basic format check
    const hasRealPassport = passportValue && passportValue !== 'Enter your passport number';
    
    const hasExistingData = hasRealName || hasRealDob || hasRealNationality || hasRealPhone || hasRealPassport;
    
    // Only skip autofill if ALL fields have real data (not just placeholders)
    if (hasExistingData && hasRealName && hasRealDob && hasRealNationality && hasRealPhone && hasRealPassport) {
      console.log('All fields already populated with real data, skipping autofill');
      autofilled = true;
      confirmStudent();
      return;
    }
    
    console.log('Fields have placeholders or incomplete data, proceeding with autofill');

    // For new students without data, don't try to autofill from empty student object
    if (!student.email && !student.first_name && !student.last_name) {
      console.log('No student data to autofill, allowing manual entry');
      autofilled = true; // Mark as processed to prevent repeated attempts
      // Don't lock fields for new students - let them enter data manually
      return;
    }

    // Always overwrite email with full DB email
    if (student.email) {
      fields.email.value = student.email;
    }

    // Only fill fields that are empty or have placeholder text
    if (fields.name && (!hasRealName)) {
      const composed = (student.full_name && String(student.full_name).trim())
        || [student.first_name, student.middle_name, student.last_name]
            .map(v => (v == null ? '' : String(v).trim()))
            .filter(Boolean)
            .join(' ');
      if (composed) {
        console.log('Setting Name:', composed, 'to field:', fields.name);
        fields.name.value = composed;
        // Sync the signature "Name" input that auto-mirrors student_name
        fields.name.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }

    if (fields.dob && (!hasRealDob) && student.dob) {
      console.log('Setting DOB:', student.dob, 'to field:', fields.dob);
      fields.dob.value = student.dob;
      console.log('DOB field value after setting:', fields.dob.value);
    }

    if (fields.nationality && (!hasRealNationality) && student.nationality) {
      console.log('Setting Nationality:', student.nationality, 'to field:', fields.nationality);
      fields.nationality.value = student.nationality;
    }

    if (fields.phone && (!hasRealPhone) && student.phone_number) {
      console.log('Setting Phone:', student.phone_number, 'to field:', fields.phone);
      fields.phone.value = student.phone_number;
    }

    // ✅ REAL PASSPORT NUMBER (TEXT FIELD)
    if (fields.passportNumber && (!hasRealPassport) && student.passport_number) {
      console.log('Setting Passport:', student.passport_number, 'to field:', fields.passportNumber);
      fields.passportNumber.value = student.passport_number;
      console.log('Passport field value after setting:', fields.passportNumber.value);
    }

    autofilled = true;
    confirmStudent();
  }

  /* =====================================================
     CONFIRM & LOCK
  ===================================================== */
  function confirmStudent() {
    if (emailConfirmed) return;

    emailConfirmed = true;
    lockFields();
    console.log('Student confirmed via email autofill');
  }

  /* =====================================================
     LOCK FIELDS (ENHANCED LOGIC)
  ===================================================== */
function lockFields() {
  Object.entries(fields).forEach(([key, input]) => {
    if (!input || key === 'email') return;

    // 🔓 If value is empty, user must type it
    if (!input.value || input.value.trim() === '') {
      input.readOnly = false;
      input.style.backgroundColor = '#fff';
      input.style.borderColor = '#e5e7eb';
      return;
    }

    // 🔒 Lock only autofilled fields with visual feedback
    input.readOnly = true;
    input.style.backgroundColor = '#f0f9ff';
    input.style.borderColor = '#3b82f6';
    input.title = 'This field was auto-filled from your existing record';
  });
}


  /* =====================================================
     PUBLIC HELPER
  ===================================================== */
  window.isStudentConfirmed = () => emailConfirmed;

})();
</script>
<script>
/**
 * =====================================================
 * ARTICLE 7 – PACKAGE SELECTION CONTROLLER
 * =====================================================
 * ✔ Works with existing onclick="showPkg('p7x')"
 * ✔ Ensures ONLY ONE package is visible at a time
 * ✔ Prevents UI conflicts
 * ✔ Safe with autofill & signature scripts
 * ✔ Ready for backend binding later
 * =====================================================
 */

(function () {
  'use strict';

  /**
   * Hide all package detail blocks
   */
  function hideAllPackages() {
    const packages = document.querySelectorAll('[id^="p7"]');
    packages.forEach(pkg => {
      pkg.style.display = 'none';
    });
  }

  /**
   * Public function used by inline onclick
   * @param {string} id
   */
window.showPkg = function (id) {
  hideAllPackages();

  const selected = document.getElementById(id);
  if (selected) {
    selected.style.display = 'block';
  }

  document.querySelectorAll('.package-item').forEach(item => {
    item.classList.remove('is-selected');
  });

  const radio = document.querySelector(`input[onclick*="showPkg('${id}')"]`);
  if (radio) {
    radio.checked = true;
    radio.closest('.package-item')?.classList.add('is-selected');
    if (!radio.value) {
      radio.value = id;
    }
  }

  const holder = document.getElementById('selected_package_code');
  if (holder) {
    holder.value = id;
  }
};

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('input[name="package"]').forEach(radio => {
    const match = (radio.getAttribute('onclick') || '').match(/showPkg\('([^']+)'\)/);
    if (match && !radio.value) {
      radio.value = match[1];
    }

    radio.addEventListener('change', function() {
      const onclickMatch = (this.getAttribute('onclick') || '').match(/showPkg\('([^']+)'\)/);
      if (onclickMatch) {
        showPkg(onclickMatch[1]);
      }
    });
  });

  if (document.body.classList.contains('contract-signed')) {
    document.querySelectorAll('input[name="package"]').forEach(radio => {
      radio.disabled = true;
    });
  }

  const presetCode = document.getElementById('selected_package_code')?.value?.trim();
  const checkedRadio = document.querySelector('input[name="package"]:checked');

  if (presetCode) {
    const presetRadio = document.querySelector(`input[name="package"][value="${presetCode}"]`)
      || document.querySelector(`input[onclick*="showPkg('${presetCode}')"]`);
    if (presetRadio) {
      presetRadio.checked = true;
    }
    showPkg(presetCode);
    return;
  }

  if (checkedRadio) {
    const match = (checkedRadio.getAttribute('onclick') || '').match(/showPkg\('([^']+)'\)/);
    if (match) {
      showPkg(match[1]);
    }
  }
});


  /**
   * Optional helper: get selected package number (for backend)
   */
  window.getSelectedPackage = function () {
    const selectedRadio = document.querySelector('input[name="package"]:checked');
    if (!selectedRadio) return null;

    const label = selectedRadio.closest('label');
    return label ? label.textContent.trim() : null;
  };

})();

/**
 * Highlight currency amounts in package fee blocks (Article 7).
 */
(function () {
  'use strict';

  const NUM = '(?:\\d{1,3}(?:,\\d{3})*|\\d+)(?:\\.\\d+)?';
  const PRICE_RE = new RegExp(
    '(?:(?:USD|CAD|EUR)\\s+' + NUM +
      '(?:\\s*(?:–|-|to)\\s*(?:(?:USD|CAD|EUR)\\s+)?' + NUM + ')?' +
      '|€\\s*' + NUM +
      '(?:\\s*(?:–|-)\\s*€?\\s*' + NUM + ')?)',
    'gi'
  );

  function highlightPricesInContainer(container) {
    if (!container || container.dataset.pricesHighlighted === '1') {
      return;
    }
    container.innerHTML = container.innerHTML.replace(
      PRICE_RE,
      (match) => '<span class="fee-price">' + match + '</span>'
    );
    container.dataset.pricesHighlighted = '1';
  }

  function initPriceHighlights() {
    document.querySelectorAll('.package-details, .additional-fees').forEach(highlightPricesInContainer);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPriceHighlights);
  } else {
    initPriceHighlights();
  }
})();
</script>


</body>
</html>
