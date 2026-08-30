<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/contract_signature_image.php';
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
    FROM student_contracts_special
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

// Load student signature (stored as data URL) when signed
$studentSignatureData = null;
$signedStudentName    = '';
$signedStudentDate    = '';
if ($isSigned && !empty($contract['id'])) {
    $contractId = (int)$contract['id'];
    $stmt = $conn->prepare("
        SELECT student_name, signed_date, signature_image
        FROM student_signatures_special
        WHERE contract_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("i", $contractId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $signedStudentName = trim((string)($row['student_name'] ?? ''));
            $signedStudentDate = trim((string)($row['signed_date'] ?? ''));
            if (!empty($row['signature_image']) && is_string($row['signature_image'])) {
                $studentSignatureData = $row['signature_image'];
            }
        }
    }
}

/** Image URL for signed contract (PNG file preferred; data-URL fallback). */
$studentSignatureSrc = '';
if ($isSigned && !empty($studentSignatureData) && !empty($contract['id'])) {
    $studentSignatureSrc = contract_signature_resolve_img_src(
        (int) $contract['id'],
        $studentSignatureData
    );
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

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
   MOBILE FORM CONTAINER
===================================================== */
.contract-page div[style*="margin-bottom"] {
  margin-bottom: 20px !important;
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
  height: 120px !important;
  border: 2px dashed #cbd5e1 !important;
  border-radius: 8px !important;
  background: #ffffff !important;
  touch-action: none !important;
  margin: 16px 0 !important;
}

/* =====================================================
   BUTTON STYLES - MOBILE FRIENDLY
===================================================== */
.contract-page button {
  padding: 12px 24px !important;
  font-size: 16px !important;
  font-weight: 600 !important;
  border-radius: 8px !important;
  margin: 8px 4px !important;
  min-height: 48px !important;
  touch-action: manipulation !important;
}

@media (max-width: 768px) {
  body {
    padding: 24px 12px;
  }

  .contract-page {
    padding: 32px 16px;
    max-width: 100%;
    overflow-x: hidden;
    font-size: 11.2pt;
    line-height: 1.65;
  }

  .contract-page h1 {
    font-size: clamp(15pt, 4.5vw, 21pt);
    line-height: 1.25;
    letter-spacing: 0.04em;
  }

  .contract-page h3 {
    font-size: 13pt;
    margin-top: 24pt;
  }

  .contract-section {
    padding: 0 16px;
    max-width: 100%;
    overflow-x: hidden;
  }

  .package-label {
    flex-wrap: wrap;
    gap: 8px;
    align-items: flex-start;
    line-height: 1.35;
  }

  .package-details {
    padding-left: 8px;
    overflow-wrap: anywhere;
    word-break: break-word;
  }

  .signature-canvas {
    max-width: 100%;
    height: 160px;
  }

  .signature-pad {
    min-height: 160px;
  }

  .signatures-layout {
    grid-template-columns: 1fr !important;
    column-gap: 0 !important;
    row-gap: 28px;
  }

  .signatures-divider {
    display: none;
  }

  .student-details-form input[type="email"],
  .student-details-form input[type="text"],
  .student-details-form input[type="date"],
  .student-details-form input[type="tel"] {
    width: 100% !important;
    max-width: 100% !important;
    display: block;
    margin-top: 6px;
    font-size: 16px !important;
    min-height: 44px;
    box-sizing: border-box;
  }

  .student-details-form > div {
    margin-bottom: 16px !important;
  }

  .sig-inline-input {
    width: 100% !important;
    max-width: 100% !important;
    display: block;
    margin-top: 6px !important;
    margin-left: 0 !important;
    font-size: 16px !important;
    min-height: 44px;
    box-sizing: border-box;
  }

  .signature-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .signature-actions button {
    width: 100%;
    min-height: 48px;
    font-size: 16px;
  }

  .package-label {
    padding: 10px 4px;
    min-height: 44px;
  }

  .package-label input[type="radio"] {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
  }
}

@media (max-width: 480px) {
  body {
    padding: 16px 8px;
  }

  .contract-page {
    padding: 22px 12px;
    font-size: 10.5pt;
    border-radius: 8px;
  }

  .contract-page h1 {
    font-size: 13pt;
  }

  .contract-section {
    padding: 0 10px;
  }

  .package-details {
    font-size: 10.5pt;
    padding-left: 4px;
  }
}

/* =====================================================
   HEADINGS
===================================================== */
.contract-page h1 {
  text-align: center;
  font-size: 24pt;
  font-weight: 700;
  letter-spacing: .6px;
  text-transform: uppercase;
  margin-bottom: 32pt;
}

.contract-page h3 {
  font-size: 15pt;
  font-weight: 700;
  margin-top: 34pt;
  margin-bottom: 14pt;
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
  padding: 10pt 12pt;
  border-radius: var(--radius-sm);
  transition: background .15s ease;
}

.package-item:hover {
  background: var(--soft);
}

.package-label {
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.package-label input {
  margin-right: 10px;
}

.package-details {
  display: none;
  margin-top: 10pt;
  padding-left: 28px;
  font-size: 11.6pt;
  line-height: 1.7;
  color: var(--muted);
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
.signature-pad {
  touch-action: none;
  -webkit-user-select: none;
  user-select: none;
  width: 100%;
  min-height: 140px;
  box-sizing: border-box;
  background: #ffffff;
}

.signature-canvas {
  width: 100%;
  height: 140px;
  border: none;
  border-radius: var(--radius-sm);
  background: #ffffff !important;
  cursor: crosshair;
  touch-action: none;
  display: block;
}

.signature-pad .signed-signature-img {
  display: block;
  max-height: 140px;
  max-width: 100%;
  width: auto;
  height: auto;
  margin: 0 auto;
  background: #ffffff;
}

.signature-pad .signature-missing {
  margin: 0;
  padding: 24px 12px;
  text-align: center;
  color: #6b7280;
  font-size: 14px;
}

.signatures-layout {
  display: grid;
  grid-template-columns: 1fr 2px 1fr;
  column-gap: 36px;
  align-items: start;
}

.signatures-layout > div {
  min-width: 0;
  word-break: normal;
  overflow-wrap: break-word;
}

.signatures-divider {
  background: #000;
}

.signature-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 14px;
}

.signature-actions button {
  min-height: 44px;
}

#signContract {
  flex: 1 1 160px;
}

/* =====================================================
   SIGNATURE GRID
===================================================== */
.signature-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 40pt 64pt;
}

@media (max-width: 768px) {
  .signature-grid {
    grid-template-columns: 1fr;
    gap: 28pt;
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

<body>

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
  <div class="student-details-form" style="font-size:15px; max-width:720px;">
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
<input type="hidden" id="selected_package_code" value="">
<!-- ============================
     ARTICLE 7 – FEES & PAYMENT TERMS
============================ -->

<div class="contract-section" id="article-7">

  <h3>7. FEES & PAYMENT TERMS (CONSOLIDATED PRICING)</h3>

  <p>
    The Student shall select <strong>one (1)</strong> applicable service package only.
    Fees apply exclusively to the selected package. Once selected, the package
    cannot be changed without the prior written consent of the Company.
  </p>

  <!-- =========================
       7.1 Partner Universities – Targeted Countries
  ========================== -->
  <div class="package-item">
    <label class="package-label">
      <input
        type="radio"
        name="package"
        value="p70"
        data-label="7.1 FEES PAID BY STUDENTS FOR OUR PARTNER UNIVERSITIES, COMPANIES, AND SOME TARGETED COUNTRIES"
        onclick="showPkg('p70')"
      >
      7.1 FEES PAID BY STUDENTS FOR OUR PARTNER UNIVERSITIES, COMPANIES, AND SOME TARGETED COUNTRIES
    </label>
    <div id="p70" class="package-details">
      ➤ USA – Registration and Application Fee: USD 150 (Paid once offer of admission is out)<br>
      ➤ Canada – Registration and Application Fee: CAD 225 (Paid once offer of admission is out)<br>
      ➤ Europe – Registration and Application Fee: USD 150 (Paid before starting application)<br>
      ➤ South Korea – Registration and Application Fee: USD 250 (Paid before starting application)<br>
      <em>Note: Students are responsible for all additional costs associated with their visa application and immigration process, including any fees charged by embassies, visa application centers, government agencies, medical institutions, or other relevant authorities.</em>
    </div>
  </div>

  <!-- =========================
       7.2 USA – Loan-Based
  ========================== -->
  <div class="package-item">
    <label class="package-label">
      <input
        type="radio"
        name="package"
        value="p71"
        data-label="7.2 🇺🇸 Study in the USA (Loan-Based)"
        onclick="showPkg('p71')"
        required
      >
      7.2 🇺🇸 Study in the USA (Loan-Based)
    </label>
    <div id="p71" class="package-details">
      ✔ Admission Support<br>
      ➤ Registration & Application Fee: USD 150 (Refundable if admission is not secured within 4 months)<br>
      ➤ Loan Approval Fees (payable after visa approval): USD 1,200<br>
      ➤ MOCK Interview Preparation Fees: USD 150<br>
      ➤ Service Fees (payable after visa approval): USD 1,500<br>
      <strong>🔥 Total Package: USD 3,000</strong>
    </div>
  </div>

  <!-- =========================
       7.3 USA – Without Loan
  ========================== -->
  <div class="package-item">
    <label class="package-label">
      <input
        type="radio"
        name="package"
        value="p72"
        data-label="7.3 🇺🇸 Study in the USA (Without Loan-Based)"
        onclick="showPkg('p72')"
      >
      7.3 🇺🇸 Study in the USA (Without Loan-Based)
    </label>
    <div id="p72" class="package-details">
      ✔ Admission Support<br>
      ➤ Registration & Application Fee: USD 150 (Refundable if admission is not secured within 4 months)<br>
      ➤ MOCK Interview Preparation Fees: USD 150<br>
      ➤ Service Fees (payable after visa approval): USD 2,000<br>
      <strong>🔥 Total Package: USD 2,300</strong>
    </div>
  </div>

  <!-- =========================
       7.4 Europe – Without Loan
  ========================== -->
  <div class="package-item">
    <label class="package-label">
      <input
        type="radio"
        name="package"
        value="p73"
        data-label="7.4 🇪🇺 Study in Europe (Without Loan-Based)"
        onclick="showPkg('p73')"
      >
      7.4 🇪🇺 Study in Europe (Without Loan-Based)
    </label>
    <div id="p73" class="package-details">
      ➤ Registration & Application Fee: USD 250 (Refundable if admission is not secured within 4 months)<br>
      ➤ Fees payable before visa application: USD 250<br>
      ➤ Service Fees (payable after visa approval): USD 1,500<br>
      <strong>🔥 Total Package: USD 2,000</strong>
    </div>
  </div>

  <!-- =========================
       7.5 Canada – Loan-Based
  ========================== -->
  <div class="package-item">
    <label class="package-label">
      <input
        type="radio"
        name="package"
        value="p74"
        data-label="7.5 🇨🇦 Study in Canada (Loan-Based)"
        onclick="showPkg('p74')"
      >
      7.5 🇨🇦 Study in Canada (Loan-Based)
    </label>
    <div id="p74" class="package-details">
      ➤ Registration & Application Fee: CAD 450 (Refundable if admission is not secured within 4 months)<br>
      ➤ Service Fees (payable after visa approval): CAD 3,050<br>
      <strong>🔥 Total Package: CAD 3,500</strong><br>
      <em>Note: Canadian institutions may require a tuition deposit ranging from CAD 500 to CAD 5,000, payable directly by the Student.</em>
    </div>
  </div>

  <!-- =========================
       7.6 Canada – Without Loan
  ========================== -->
  <div class="package-item">
    <label class="package-label">
      <input
        type="radio"
        name="package"
        value="p75"
        data-label="7.6 🇨🇦 Study in Canada (Without Loan-Based)"
        onclick="showPkg('p75')"
      >
      7.6 🇨🇦 Study in Canada (Without Loan-Based)
    </label>
    <div id="p75" class="package-details">
      ➤ Registration & Application Fee: CAD 450 (Refundable if admission is not secured within 4 months)<br>
      ➤ After Visa Approval: CAD 2,050<br>
      <strong>🔥 Your Complete Visa Support Package: CAD 2,500</strong>
    </div>
  </div>

  <!-- =========================
       7.7 Canada – High School Graduate
  ========================== -->
  <div class="package-item">
    <label class="package-label">
      <input
        type="radio"
        name="package"
        value="p76"
        data-label="7.7 🇨🇦 Canada – High School Graduate (Loan-Based)"
        onclick="showPkg('p76')"
      >
      7.7 🇨🇦 Canada – High School Graduate (Loan-Based)
    </label>
    <div id="p76" class="package-details">
      ➤ Registration & Application Fee: CAD 450<br>
      ➤ Study Permit Fees (Embassy): CAD 150<br>
      ➤ Biometrics Fees (Embassy): CAD 85<br>
      ➤ CAQ Fees (Quebec Only): CAD 132<br>
      ➤ Border Pass Fees (Lawyer): CAD 250<br>
      ➤ Loan Approval Fees (payable after visa approval): CAD 1,000<br>
      ➤ Service Fees (payable after visa approval): CAD 1,933<br>
      <strong>🔥 Total Package: CAD 4,000</strong>
    </div>
  </div>

  <!-- =========================
       7.8 South Korea – Study
  ========================== -->
  <div class="package-item">
    <label class="package-label">
      <input
        type="radio"
        name="package"
        value="p77"
        data-label="7.8 🇰🇷 Study in South Korea (Self-Sponsored)"
        onclick="showPkg('p77')"
      >
      7.8 🇰🇷 Study in South Korea (Self-Sponsored)
    </label>
    <div id="p77" class="package-details">
      ➤ Registration & Application Fee: USD 500 (Refundable if admission is not secured)<br>
      ➤ Service Fees – Bachelor’s: USD 1,800<br>
      ➤ Service Fees – Master’s: USD 2,000<br>
      ➤ Service Fees – PhD: USD 2,200<br>
      ✔ Includes free Korean language training (3 months) and pre-departure orientation<br>
      ✔ USD 500 payable before starting admission process<br>
      ✔ All service fees payable before starting admission process
    </div>
  </div>

  <!-- =========================
       7.9 South Korea – Visit Visa
  ========================== -->
  <div class="package-item">
    <label class="package-label">
      <input
        type="radio"
        name="package"
        value="p78"
        data-label="7.9 🇰🇷 South Korea Visitor Visa"
        onclick="showPkg('p78')"
      >
      7.9 🇰🇷 South Korea Visitor Visa
    </label>
    <div id="p78" class="package-details">
      ➤ Registration & Application Fee: USD 500<br>
      ➤ Service Fee (Paid After Receiving the Invitation Letter and Guarantee Letter): USD 1,500<br>
      ➤ Participation fees vary depending on the event organizer.
    </div>
  </div>

  <!-- =========================
       7.10 Credit Transfer
  ========================== -->
  <div class="package-item">
    <label class="package-label">
      <input
        type="radio"
        name="package"
        value="p79"
        data-label="7.10 Credit Transfer (Bachelor, Masters & PhD)"
        onclick="showPkg('p79')"
      >
      7.10 Credit Transfer (Bachelor, Masters & PhD)
    </label>
    <div id="p79" class="package-details">
      ➤ Bachelor: USD 920<br>
      ➤ Masters: USD 1,220<br>
      ➤ PhD: USD 1,620
    </div>
  </div>

  <!-- =========================
       7.11 Canada Visit Visa
  ========================== -->
  <div class="package-item">
    <label class="package-label">
      <input
        type="radio"
        name="package"
        value="p710"
        data-label="7.11 🇨🇦 Canada Visit Visa"
        onclick="showPkg('p710')"
      >
      7.11 🇨🇦 Canada Visit Visa
    </label>
    <div id="p710" class="package-details">
      ➤ Documents Preparation & Invitation Letter: USD 1,000<br>
      ➤ Visa Application Fees (Embassy): CAD 100<br>
      ➤ Biometrics Fees (Embassy): CAD 85<br>
      ➤ Service Fees (payable after visa approval): CAD 2,000
    </div>
  </div>

  <!-- =========================
       7.12 USA Visit Visa
  ========================== -->
  <div class="package-item">
    <label class="package-label">
      <input
        type="radio"
        name="package"
        value="p711"
        data-label="7.12 🇺🇸 USA Visit Visa"
        onclick="showPkg('p711')"
      >
      7.12 🇺🇸 USA Visit Visa
    </label>
    <div id="p711" class="package-details">
      ➤ Documents Preparation & Invitation Letter: USD 1,000<br>
      ➤ Visa Application Fees (Embassy): USD 185<br>
      ➤ Service Fees (payable after visa approval): USD 1,500
    </div>
  </div>

  <!-- =========================
       7.13 Europe Visit Visa
  ========================== -->
  <div class="package-item">
    <label class="package-label">
      <input
        type="radio"
        name="package"
        value="p712"
        data-label="7.13 🇪🇺 Europe Visit Visa"
        onclick="showPkg('p712')"
      >
      7.13 🇪🇺 Europe Visit Visa
    </label>
    <div id="p712" class="package-details">
      ➤ Documents Preparation & Invitation Letter: EUR 600<br>
      ➤ Visa Application Fees (Embassy): EUR 85 – EUR 500 (depending on country)<br>
      ➤ Service Fees (payable after visa approval): EUR 1,000
    </div>
  </div>

  <!-- =========================
       7.14 Asia Visit Visa
  ========================== -->
  <div class="package-item">
    <label class="package-label">
      <input
        type="radio"
        name="package"
        value="p713"
        data-label="7.14 Asia Visit Visa"
        onclick="showPkg('p713')"
      >
      7.14 Asia Visit Visa
    </label>
    <div id="p713" class="package-details">
      ➤ Documents Preparation & Invitation Letter: USD 800<br>
      ➤ Visa Application Fees (Embassy): USD 85 – USD 500<br>
      ➤ Service Fees (payable after visa approval): USD 1,500
    </div>
  </div>

  <p class="contract-warning">
    ⚠ <strong>Important:</strong> All government fees, embassy charges, biometric fees,
    SEVIS fees, tuition deposits, lawyer fees, border pass fees, and third-party charges
    are paid separately by the Student and are strictly non-refundable once submitted.
  </p>

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
     15. SIGNATURES
     Left: Dr. Twajamahoro | Right: Student e-sign
============================ -->
<div class="contract-section" style="margin-top:40px;">

  <h3 style="font-size:20px;font-weight:700;margin-bottom:24px;">
    15. SIGNATURES
  </h3>

  <div class="signatures-layout">

    <!-- LEFT: ScholarSync / Dr. Twajamahoro -->
    <div>
      <p style="font-weight:700;margin-bottom:10px;">
        For ScholarSync Global Co. Ltd
      </p>

      <p>Name:</p>
      <div style="border-bottom:1px solid #000;height:16px;margin-bottom:12px;padding-top:2px;">
        Dr. Jean Pierre Twajamahoro
      </div>

      <p>Title:</p>
      <div style="border-bottom:1px solid #000;height:16px;margin-bottom:12px;padding-top:2px;">
        Owner &amp; Managing Director
      </div>

      <p style="margin-top:16px;">Signature:</p>
      <div style="margin-bottom:12px;">
        <img src="admin/signature-manager.png" alt="Managing Director Signature" style="max-height:70px;max-width:280px;display:block;">
      </div>

      <p>Company Stamp:</p>
      <div style="margin-bottom:12px;">
        <img src="admin/employer-signature.png" alt="Company Stamp" style="max-height:140px;max-width:320px;display:block;">
      </div>

      <p>Date: <?= date('Y/m/d') ?></p>
    </div>

    <!-- VERTICAL DIVIDER -->
    <div class="signatures-divider" aria-hidden="true"></div>

    <!-- RIGHT: Student + e-sign -->
    <div>
      <p style="font-weight:700;margin-bottom:10px;">
        For the Student
      </p>

      <p>
        Name:
        <input type="text" id="sig_student_name" class="sig-inline-input"
               value="<?= htmlspecialchars($signedStudentName, ENT_QUOTES, 'UTF-8') ?>"
               <?= $isSigned ? 'readonly' : '' ?>
               style="border:none;border-bottom:1px solid #000;">
      </p>

      <p style="margin-top:12px;">Signature:</p>

      <div class="signature-pad" style="
        border:1px dashed #9ca3af;
        padding:8px;
        margin-bottom:14px;
        box-sizing:border-box;
        background:#ffffff;
      ">
        <?php if ($isSigned): ?>
          <?php if (!empty($studentSignatureSrc)): ?>
          <img src="<?= htmlspecialchars($studentSignatureSrc, ENT_QUOTES, 'UTF-8') ?>"
               alt="Student signature"
               class="signed-signature-img">
          <?php else: ?>
          <p class="signature-missing">Signature not stored for this contract. Please contact support to re-sign.</p>
          <?php endif; ?>
        <?php else: ?>
          <canvas class="signature-canvas" aria-label="Draw your signature here"></canvas>
        <?php endif; ?>
      </div>

      <p style="margin-top:4px;">
        Date:
        <input type="date" id="sig_signed_date" class="sig-inline-input"
               value="<?= htmlspecialchars($signedStudentDate, ENT_QUOTES, 'UTF-8') ?>"
               <?= $isSigned ? 'readonly' : '' ?>
               style="border:none;border-bottom:1px solid #000;">
      </p>

      <?php if (!$isSigned): ?>
      <div class="signature-actions">
        <button id="clearSignature" type="button">Clear</button>
        <button id="signContract" type="button">Sign &amp; Submit</button>
        <input type="hidden" id="signatureData">
      </div>

      <div id="signatureProgress" style="display:none;margin-top:10px;">
        <div style="height:8px;background:#e5e7eb;border-radius:999px;">
          <div id="signatureProgressBar"
               style="height:100%;width:0%;background:#2563eb;"></div>
        </div>
        <div id="signatureProgressText"
             style="font-size:12px;text-align:center;margin-top:4px;">
          Submitting signature…
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div>
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
  'use strict';

  const canvas = document.querySelector('.signature-canvas');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  const btnClear = document.getElementById('clearSignature');
  const btnSubmit = document.getElementById('signContract');
  const inputName = document.getElementById('sig_student_name');
  const inputDate = document.getElementById('sig_signed_date');
  const hiddenSignature = document.getElementById('signatureData');
  const progressBox = document.getElementById('signatureProgress');
  const progressBar = document.getElementById('signatureProgressBar');
  const progressText = document.getElementById('signatureProgressText');

  const mainStudentName = document.getElementById('student_name');
  const todayIso = new Date().toISOString().slice(0, 10);
  if (inputDate && !inputDate.value) inputDate.value = todayIso;
  if (mainStudentName && inputName && !inputName.value) {
    inputName.value = (mainStudentName.value || '').trim();
  }
  if (mainStudentName && inputName) {
    mainStudentName.addEventListener('input', () => {
      inputName.value = (mainStudentName.value || '').trim();
    });
  }

  let drawing = false;
  let isSubmitting = false;
  let progressTimer = null;
  let resizeTimer = null;

  function paintWhiteBackground() {
    const rect = canvas.getBoundingClientRect();
    ctx.save();
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, rect.width, rect.height);
    ctx.restore();
  }

  function resizeCanvas() {
    const ratio = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    if (rect.width < 1 || rect.height < 1) return;

    const snapshot = hasSignature()
      ? canvas.toDataURL('image/png')
      : null;

    canvas.width = Math.floor(rect.width * ratio);
    canvas.height = Math.floor(rect.height * ratio);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#000000';

    paintWhiteBackground();

    if (snapshot) {
      const img = new Image();
      img.onload = () => {
        paintWhiteBackground();
        ctx.drawImage(img, 0, 0, rect.width, rect.height);
      };
      img.src = snapshot;
    }
  }

  function scheduleResize() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(resizeCanvas, 120);
  }

  resizeCanvas();
  window.addEventListener('resize', scheduleResize);
  window.addEventListener('orientationchange', scheduleResize);

  function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    const pt = e.touches ? e.touches[0] : e;
    return {
      x: pt.clientX - rect.left,
      y: pt.clientY - rect.top
    };
  }

  function startDraw(e) {
    if (isSubmitting) return;
    e.preventDefault();
    drawing = true;
    const pos = getPos(e);
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y);
  }

  function draw(e) {
    if (!drawing || isSubmitting) return;
    e.preventDefault();
    const pos = getPos(e);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
  }

  function stopDraw(e) {
    if (e) e.preventDefault();
    drawing = false;
  }

  canvas.addEventListener('mousedown', startDraw);
  canvas.addEventListener('mousemove', draw);
  canvas.addEventListener('mouseup', stopDraw);
  canvas.addEventListener('mouseleave', stopDraw);
  canvas.addEventListener('touchstart', startDraw, { passive: false });
  canvas.addEventListener('touchmove', draw, { passive: false });
  canvas.addEventListener('touchend', stopDraw, { passive: false });
  canvas.addEventListener('touchcancel', stopDraw, { passive: false });

  if (btnClear) {
    btnClear.addEventListener('click', () => {
      paintWhiteBackground();
    });
  }

  function hasSignature() {
    const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
    for (let i = 0; i < pixels.length; i += 4) {
      const a = pixels[i + 3];
      if (a < 16) continue;
      const r = pixels[i], g = pixels[i + 1], b = pixels[i + 2];
      if (r < 240 || g < 240 || b < 240) return true;
    }
    return false;
  }

  function captureSignature() {
    const exportCanvas = document.createElement('canvas');
    exportCanvas.width = canvas.width;
    exportCanvas.height = canvas.height;
    const ex = exportCanvas.getContext('2d');
    ex.fillStyle = '#ffffff';
    ex.fillRect(0, 0, exportCanvas.width, exportCanvas.height);
    ex.drawImage(canvas, 0, 0);
    return exportCanvas.toDataURL('image/png');
  }

  function getSelectedPackage() {
    const selectedRadio = document.querySelector('input[name="package"]:checked');
    if (!selectedRadio) return null;

    const code = selectedRadio.value
      || document.getElementById('selected_package_code')?.value
      || '';

    const label = selectedRadio.dataset.label
      || selectedRadio.closest('label')?.textContent?.trim()
      || '';

    if (!code || !label) return null;
    return { code, label };
  }

  function startProgress() {
    isSubmitting = true;
    if (btnSubmit) btnSubmit.disabled = true;
    if (btnClear) btnClear.disabled = true;
    if (progressBox) progressBox.style.display = 'block';
    if (progressBar) {
      progressBar.style.width = '12%';
      progressBar.style.background = '#2563eb';
    }
    if (progressText) progressText.textContent = 'Submitting signature…';

    let p = 12;
    progressTimer = setInterval(() => {
      p = Math.min(90, p + Math.random() * 14);
      if (progressBar) progressBar.style.width = p + '%';
    }, 350);
  }

  function finishProgress(success) {
    clearInterval(progressTimer);
    isSubmitting = false;
    if (progressBar) {
      progressBar.style.width = '100%';
      progressBar.style.background = success ? '#16a34a' : '#dc2626';
    }
    if (progressText) {
      progressText.textContent = success
        ? 'Signature submitted successfully'
        : 'Submission failed — you can try again';
    }
    if (btnSubmit) btnSubmit.disabled = false;
    if (btnClear) btnClear.disabled = false;
  }

  function submitSignature(signature, name, date, pkg) {
    const emailInput = document.getElementById('student_email');
    const dobInput = document.getElementById('student_dob');
    const nationalityInput = document.getElementById('student_nationality');
    const passportInput = document.getElementById('student_passport');
    const phoneInput = document.getElementById('student_phone');

    if (!emailInput || !dobInput || !nationalityInput || !passportInput || !phoneInput) {
      finishProgress(false);
      alert('Student information fields are missing. Please reload the page.');
      return;
    }

    const payload = {
      token: <?= json_encode($token, JSON_THROW_ON_ERROR) ?>,
      selected_package_label: pkg.label,
      selected_package_code: pkg.code,
      student_name: name,
      signed_date: date,
      signature: signature,
      student_email: emailInput.value.trim(),
      student_dob: dobInput.value,
      student_nationality: nationalityInput.value.trim(),
      student_passport: passportInput.value.trim(),
      student_phone: phoneInput.value.trim()
    };

    if (!payload.student_email) {
      finishProgress(false);
      alert('Student email is required.');
      emailInput.focus();
      return;
    }

    if (hiddenSignature) hiddenSignature.value = signature;

    fetch('submit-signature-special.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(async (res) => {
        let data;
        try {
          data = await res.json();
        } catch (e) {
          throw new Error('Invalid response from server. Please try again.');
        }
        if (!res.ok) {
          throw new Error(data.error || 'Server error');
        }
        return data;
      })
      .then((data) => {
        if (data.success) {
          finishProgress(true);
          alert('Contract signed successfully.\n\nYou can now download or view the signed agreement.');
          window.location.reload();
          return;
        }
        if (data.error && data.error.toLowerCase().includes('already signed')) {
          finishProgress(true);
          alert('This contract has already been signed.\n\nYou can now download or view the signed agreement.');
          window.location.reload();
          return;
        }
        finishProgress(false);
        alert(data.error || 'Submission failed.');
      })
      .catch((err) => {
        console.error('Signature submission error:', err);
        finishProgress(false);
        alert(err?.message || 'Unable to submit. Please check your connection and try again.');
      });
  }

  if (btnSubmit) {
    btnSubmit.addEventListener('click', () => {
      if (isSubmitting) return;

      const pkg = getSelectedPackage();
      if (!pkg) {
        alert('Please select one service package under Article 7 before signing.');
        document.getElementById('article-7')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }

      const studentName = (inputName?.value || '').trim();
      if (!studentName) {
        alert('Please enter your full name before signing.');
        inputName?.focus();
        return;
      }

      if (!inputDate?.value) {
        alert('Please select the signing date.');
        inputDate?.focus();
        return;
      }

      if (!hasSignature()) {
        alert('Please draw your signature in the box before submitting.');
        canvas.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }

      startProgress();
      submitSignature(
        captureSignature(),
        studentName,
        inputDate.value,
        pkg
      );
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
     AUTOFILL (SAFE & CLEAN)
  ===================================================== */
  function autofillStudent(student) {
    if (!student || autofilled) return;

    // Check if fields are already populated (external students)
    const hasExistingData = fields.name.value.trim() || 
                          fields.dob.value || 
                          fields.nationality.value.trim() || 
                          fields.phone.value.trim() || 
                          fields.passportNumber.value.trim();
    
    if (hasExistingData) {
      console.log('Fields already populated, skipping autofill');
      autofilled = true;
      confirmStudent();
      return;
    }

    // For new students without data, don't try to autofill from empty student object
    if (!student.email && !student.first_name && !student.last_name) {
      console.log('No student data to autofill, allowing manual entry');
      autofilled = true; // Mark as processed to prevent repeated attempts
      return;
    }

    // Always overwrite email with full DB email
    if (student.email) {
      fields.email.value = student.email;
    }

    if (fields.name) {
      const composed = (student.full_name && String(student.full_name).trim())
        || [student.first_name, student.middle_name, student.last_name]
            .map(v => (v == null ? '' : String(v).trim()))
            .filter(Boolean)
            .join(' ');
      if (composed) {
        fields.name.value = composed;
        // Sync the signature "Name" input that auto-mirrors student_name
        fields.name.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }

    if (fields.dob && student.dob) {
      fields.dob.value = student.dob;
    }

    if (fields.nationality && student.nationality) {
      fields.nationality.value = student.nationality;
    }

    if (fields.phone && student.phone_number) {
      fields.phone.value = student.phone_number;
    }

    // ✅ REAL PASSPORT NUMBER (TEXT FIELD)
    if (fields.passportNumber && student.passport_number) {
      fields.passportNumber.value = student.passport_number;
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
     LOCK FIELDS (EXCEPT EMAIL)
  ===================================================== */
function lockFields() {
  Object.entries(fields).forEach(([key, input]) => {
    if (!input || key === 'email') return;

    // 🔓 If value is empty, user must type it
    if (!input.value || input.value.trim() === '') {
      input.readOnly = false;
      input.style.backgroundColor = '#fff';
      return;
    }

    // 🔒 Lock only autofilled fields
    input.readOnly = true;
    input.style.backgroundColor = '#f7f9fc';
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

  // ✅ SAVE SELECTED PACKAGE CODE
  const holder = document.getElementById('selected_package_code');
  if (holder) {
    holder.value = id; // e.g. "p74"
  }
};


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
</script>


</body>
</html>
