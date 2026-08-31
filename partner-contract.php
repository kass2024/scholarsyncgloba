<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    exit("Database connection error.");
}

if (!isset($_GET['token']) || trim($_GET['token']) === '') {
    http_response_code(400);
    exit("Invalid contract link.");
}

$token = trim($_GET['token']);

$sql = "SELECT * FROM partner_contracts WHERE contract_token = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    exit("Query preparation failed.");
}

$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$contract = $result->fetch_assoc();
$stmt->close();

if (!$contract) {
    http_response_code(404);
    exit("This contract link is invalid or expired.");
}

$isSigned = ($contract['status'] === 'signed');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Strategic Partnership Agreement</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<style>
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

body {
  margin: 0;
  padding: 48px 16px;
  background: linear-gradient(180deg, #eef2f7, #e5e7eb);
  font-family: "Inter", "Segoe UI", system-ui, sans-serif;
  color: var(--ink);
}

.contract-page {
  max-width: 900px;
  margin: auto;
  background: var(--paper);
  padding: 64px 72px;
  box-shadow: var(--shadow-paper);
  border-radius: var(--radius-md);
  font-family: "Georgia", "Times New Roman", serif;
  font-size: 12.2pt;
  line-height: 1.75;
  overflow-wrap: anywhere;
  word-wrap: break-word;
}

/* Enhanced Mobile Responsive Design */
@media screen and (max-width: 768px) {
  body {
    padding: 20px 12px !important;
  }
  
  .contract-page {
    padding: 28px 20px !important;
    margin: 8px !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    overflow-x: hidden !important;
  }
  
  .contract-page h1 {
    font-size: 18pt !important;
    margin-bottom: 20pt !important;
    line-height: 1.2 !important;
  }
  
  .contract-page h3 {
    font-size: 14pt !important;
    margin-top: 20pt !important;
    margin-bottom: 12pt !important;
    line-height: 1.3 !important;
  }
  
  .contract-page p {
    font-size: 11pt !important;
    line-height: 1.6 !important;
    margin: 0 0 12pt 0 !important;
  }
  
  .form-section {
    padding: 16px !important;
    margin: 12px 0 !important;
    border-radius: 6px !important;
  }
  
  .form-section h4 {
    font-size: 13pt !important;
    margin-bottom: 12px !important;
  }
  
  .signature-grid {
    grid-template-columns: 1fr !important;
    gap: 20px !important;
  }
  
  .signature-section {
    margin-bottom: 20px !important;
    padding: 16px !important;
  }
  
  .input-group {
    margin-bottom: 16px !important;
  }
  
  .input-group input {
    font-size: 14px !important;
    padding: 12px !important;
    max-width: 100% !important;
  }
  
  .btn {
    padding: 14px 20px !important;
    font-size: 14px !important;
    margin: 8px 4px !important;
  }
}

@media screen and (max-width: 480px) {
  body {
    padding: 12px 6px !important;
  }
  
  .contract-page {
    padding: 20px 12px !important;
    margin: 4px !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    overflow-x: hidden !important;
  }
  
  .contract-page h1 {
    font-size: 16pt !important;
    margin-bottom: 16pt !important;
    line-height: 1.2 !important;
  }
  
  .contract-page h3 {
    font-size: 12pt !important;
    margin-top: 16pt !important;
    margin-bottom: 8pt !important;
    line-height: 1.3 !important;
  }
  
  .contract-page p {
    font-size: 10pt !important;
    line-height: 1.5 !important;
    margin: 0 0 8pt 0 !important;
  }
  
  .form-section {
    padding: 12px !important;
    margin: 8px 0 !important;
    border-radius: 4px !important;
  }
  
  .form-section h4 {
    font-size: 12pt !important;
    margin-bottom: 10px !important;
  }
  
  .signature-canvas {
    width: 100% !important;
    max-width: 260px !important;
    height: 100px !important;
  }
  
  .btn {
    width: 100% !important;
    margin: 6px 0 !important;
    text-align: center !important;
    padding: 12px 16px !important;
  }
  
  .btn-clear {
    margin-bottom: 8px !important;
  }
  
  .input-group input {
    font-size: 16px !important;
    padding: 12px !important;
    max-width: 100% !important;
  }
}

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

.contract-page p {
  margin: 0 0 14pt 0;
  text-align: justify;
}

.contract-page strong {
  font-weight: 700;
}

.contract-page input[type="text"],
.contract-page input[type="email"],
.contract-page input[type="date"],
.contract-page input[type="tel"] {
  width: 100%;
  max-width: 520px;
  padding: 10px 12px;
  font-family: inherit;
  font-size: 12.2pt;
  border: none;
  border-bottom: 2px solid var(--ink);
  background: rgba(255, 255, 255, 0.9);
  border-radius: 4px;
  outline: none;
  transition: all 0.3s ease;
  box-sizing: border-box;
}

.contract-page input:focus {
  border-bottom-color: var(--link);
  background: rgba(255, 255, 255, 1);
  box-shadow: 0 0 0 3px rgba(31, 79, 216, 0.1);
}

.contract-page input::placeholder {
  color: #9ca3af;
  font-style: italic;
}

.contract-page input:required {
  border-left: 3px solid var(--link);
}

/* Smart form styling */
.form-section {
  background: rgba(31, 79, 216, 0.05);
  border-radius: 8px;
  padding: 20px;
  margin: 15px 0;
  border: 1px solid var(--border);
}

.form-section h4 {
  margin: 0 0 15px 0;
  color: var(--ink);
  font-size: 14pt;
  font-weight: 600;
}

.input-group {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 15px;
  align-items: center;
  margin-bottom: 15px;
}

.input-group label {
  font-weight: 600;
  color: var(--muted);
  font-size: 11pt;
  margin-bottom: 5px;
  display: block;
}

.input-group input {
  flex: 1;
}

/* Mobile input group layout */
@media screen and (max-width: 768px) {
  .input-group {
    grid-template-columns: 1fr !important;
    gap: 8px !important;
    margin-bottom: 16px !important;
  }
  
  .input-group label {
    margin-bottom: 6px !important;
    font-size: 12pt !important;
  }
}

@media screen and (max-width: 480px) {
  .input-group {
    gap: 6px !important;
    margin-bottom: 14px !important;
  }
  
  .input-group label {
    margin-bottom: 4px !important;
    font-size: 11pt !important;
  }
}

/* Date picker styling */
input[type="date"] {
  position: relative;
}

input[type="date"]::-webkit-calendar-picker-indicator {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  width: 20px;
  height: 20px;
  cursor: pointer;
  background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" fill="%231d4ed8" viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>') no-repeat;
  background-size: contain;
}

input[type="date"]::-moz-calendar-picker {
  width: 20px;
  height: 20px;
  cursor: pointer;
}

.signature-canvas {
  width: 100%;
  height: 140px;
  max-width: 400px;
  border: 2px dashed #9ca3af;
  border-radius: var(--radius-sm);
  background: #ffffff;
  cursor: crosshair;
  transition: all 0.3s ease;
  touch-action: none;
  display: block;
}

.signature-canvas:hover {
  border-color: var(--link);
  background: #f8fafc;
  transform: scale(1.02);
}

.signature-canvas.drawing {
  border-color: var(--link);
  background: #f0f9ff;
  box-shadow: 0 0 10px rgba(31, 79, 216, 0.1);
}

@media (max-width: 768px) {
  .signature-canvas {
    height: 120px;
    max-width: 280px;
  }
}

@media (max-width: 480px) {
  .signature-canvas {
    height: 100px;
    max-width: 240px;
  }
}

/* Signature section improvements */
.signature-section {
  background: linear-gradient(135deg, #f8fafc, #e0e7ff);
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 20px;
  margin: 20px 0;
}

.signature-section h4 {
  color: var(--ink);
  margin-bottom: 15px;
  font-size: 16px;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  font-family: system-ui, sans-serif;
}

.btn-clear {
  background: #f3f4f6;
  color: var(--ink);
  border: 1px solid #d1d5db;
}

.btn-clear:hover {
  background: #e5e7eb;
  color: var(--ink);
}

.btn-submit {
  background: var(--link);
  color: #ffffff;
  border: 1px solid var(--link);
}

.btn-submit:hover {
  background: #1e40af;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(31, 79, 216, 0.15);
}

.btn-submit:disabled {
  background: #9ca3af;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.signature-status {
  font-size: 12px;
  color: var(--success);
  margin-top: 10px;
  display: none;
}

.signature-status.show {
  display: block;
}

.signature-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 40pt 64pt;
}

@media (max-width: 768px) {
  .signature-grid { grid-template-columns: 1fr; gap: 28pt; }
}

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

#signContract {
  background: var(--link);
  color: #ffffff;
}

#signContract:hover { background: #1e40af; }
#signContract:disabled { background: #9ca3af; cursor: not-allowed; }

.footer-ref {
  margin-top: 48pt;
  text-align: center;
  font-size: 10.5pt;
  color: #6b7280;
}

@media print {
  body { background: #ffffff; padding: 0; }
  .contract-page { box-shadow: none; border-radius: 0; }
  button { display: none; }
}
</style>
</head>
<body>

<div class="contract-page">

<h1>STRATEGIC PARTNERSHIP AGREEMENT</h1>

<p style="font-size:16px; text-align:justify; margin-bottom:30px;">
This <strong>Strategic Partnership Agreement</strong> is made and entered into on the date of signature
by and between:
</p>

<h3>1. PARTIES</h3>

<p style="font-size:16px; font-weight:700; margin-bottom:8px;">1.1 The Partner Company</p>
<div class="form-section">
  <h4>Company Information</h4>
  <div class="input-group">
    <label for="company_name">Company Name *</label>
    <input type="text" id="company_name" name="company_name" required placeholder="Enter your company name" value="<?= htmlspecialchars($contract['company_name'] ?? '') ?>">
  </div>
  <div class="input-group">
    <label for="representative_name">Representative Name *</label>
    <input type="text" id="representative_name" name="representative_name" required placeholder="Enter representative name" value="<?= htmlspecialchars($contract['representative_name'] ?? '') ?>">
  </div>
  <div class="input-group">
    <label for="representative_title">Title *</label>
    <input type="text" id="representative_title" name="representative_title" required placeholder="Enter your title" value="<?= htmlspecialchars($contract['representative_title'] ?? '') ?>">
  </div>
  <div class="input-group">
    <label for="company_email">Company Email *</label>
    <input type="email" id="company_email" name="company_email" required placeholder="Enter your company email" value="<?= htmlspecialchars($contract['company_email'] ?? '') ?>">
  </div>
  <div class="input-group">
    <label for="company_phone">Company Phone</label>
    <input type="tel" id="company_phone" name="company_phone" placeholder="Enter your company phone" value="<?= htmlspecialchars($contract['company_phone'] ?? '') ?>">
  </div>
  <div class="input-group">
    <label for="company_address">Full Address</label>
    <input type="text" id="company_address" name="company_address" placeholder="Enter your full address" value="<?= htmlspecialchars($contract['company_address'] ?? '') ?>">
  </div>
</div>

<p style="font-size:16px; font-weight:700; margin-bottom:8px;">1.2 ScholarSync Global Co. Ltd</p>
<p>
<strong>ScholarSync Global Co. Ltd.</strong><br>
Company Email: infos@scholarsyncglobal.ca<br>
Company Phone Number: +1 (438) 290-6688<br>
Full address: Rwanda - Kigali<br>
Town Center Building (near Simba Supermarket),<br>
2nd Floor, Door: F2B-022C, Nyarugenge<br>
Full address: Canada - Quebec<br>
294 Rue Vezina App 202<br>
Lasalle, Quebec H8R 3M9
</p>

<h3>2. PURPOSE OF THE AGREEMENT</h3>
<p>The primary purpose of this Agreement is to establish a complete and structured student support system, ensuring professional guidance at every stage, including:</p>
<ul>
<li>Document screening and eligibility assessment</li>
<li>University/college selection</li>
<li>Admission securing</li>
<li>Partial scholarship and student loan assistance (where applicable)</li>
<li>Visa consultation and approval</li>
<li>Travel arrangements</li>
<li>Airport pickup and settlement support in the destination country</li>
</ul>

<h3>3. SCOPE OF PARTNERSHIP</h3>
<p><strong>3.1 Student Recruitment & Counseling</strong><br>
Identification and recruitment of qualified students<br>
Academic and career guidance aligned with global opportunities</p>

<p><strong>3.2 Document Screening & Admission Process</strong><br>
Comprehensive document screening and eligibility verification<br>
University and program selection worldwide<br>
Application preparation and submission<br>
Securing admission offers from institutions<br>
Support for scholarship opportunities and loan assistance where applicable</p>

<p><strong>3.3 Visa Processing & Immigration Support</strong><br>
Professional visa consultation under Dr. Jean Pierre Twajamahoro<br>
Documentation review and compliance with destination-country laws<br>
Visa application processing and follow-up</p>

<p><strong>3.4 Travel & Pre-Departure Services</strong><br>
Travel planning and flight guidance<br>
Pre-departure orientation</p>

<p><strong>3.5 Airport Pickup & Settlement (Core Commitment)</strong><br>
Guaranteed airport pickup arrangements in the student's destination country<br>
Initial accommodation guidance<br>
Settlement assistance upon arrival abroad<br>
Coordination with local partners</p>

<h3>4. CORE MISSION STATEMENT</h3>
<p>Both parties agree to operate as a full-service global education consultancy, delivering:</p>
<p> "From Screening to Settlement" Service Model<br>
 Covering all international destinations worldwide<br>
 Including admission, visa, travel, and arrival support<br>
 Ensuring a seamless transition from initial assessment to full settlement abroad.</p>

<h3>5. ROLES AND RESPONSIBILITIES</h3>
<p><strong>5.1 Partner Company</strong><br>
Recruit and prepare students<br>
Support document collection and initial screening<br>
Assist in application preparation<br>
Provide pre-departure guidance<br>
Maintain communication with applicants</p>

<p><strong>5.2 ScholarSync Global Co. Ltd</strong><br>
Conduct initial document screening and eligibility assessment<br>
Support university/college selection<br>
Assist in securing admission offers<br>
Provide partial scholarship and student loan assistance (where applicable)<br>
Provide expert visa consultation and processing services<br>
Ensure compliance with immigration laws of destination countries<br>
Handle visa documentation and application procedures<br>
Support travel planning coordination<br>
Facilitate or coordinate airport pickup and settlement support in the student's destination country<br>
Provide post-arrival support where applicable</p>

<h3>6. FINANCIAL ARRANGEMENT</h3>
<p>Both parties agree that each company retains the right to independently charge service fees to their respective students/clients based on their internal policies.</p>
<p>ScholarSync Global Co. Ltd shall pay application service fees to Company Name upon successful issuance of an Offer Letter of Admission.</p>
<p>The agreed university application fees are:</p>
<ul>
<li> Canada: 125 CAD per student</li>
<li> United States: 100 USD per student</li>
<li> Europe: 100 EUR per student</li>
<li> Asia: 100 USD per student</li>
</ul>
<p>Payment shall be made immediately after issuance of the Offer Letter, using agreed payment methods.</p>
<p>Both parties agree to maintain full transparency, accountability, and proper financial records.</p>

<h3>7. VALUE PROPOSITION</h3>
<p>This partnership delivers a premium global service model that:</p>
<ul>
<li>Covers the entire student journey from screening to settlement</li>
<li>Improves admission and visa success rates</li>
<li>Provides financial guidance through scholarships and loans</li>
<li>Ensures safe arrival and integration abroad</li>
</ul>

<h3>8. COMMUNICATION AND COORDINATION</h3>
<p>Dedicated representatives from both parties<br>
Continuous monitoring of student progress<br>
Real-time updates across all stages</p>

<h3>9. CONFIDENTIALITY CLAUSE</h3>
<p>All student and business information shall remain strictly confidential.</p>

<h3>10. COMPLIANCE AND ETHICS</h3>
<ul>
<li>Full compliance with international education and immigration laws</li>
<li>Ethical and transparent operations</li>
<li>Zero tolerance for fraud or misrepresentation</li>
</ul>

<h3>11. DURATION AND TERMINATION</h3>
<p>Effective upon signing<br>
Valid for 1 Year<br>
30-day written termination notice<br>
Ongoing cases must be completed</p>

<h3>12. DISPUTE RESOLUTION</h3>
<p>Mutual negotiation<br>
Arbitration if necessary</p>

<h3>13. FORCE MAJEURE</h3>
<p>Neither party shall be liable for uncontrollable events affecting obligations.</p>

<h3>14. CONCLUSION</h3>
<p>This Agreement represents a powerful global partnership, delivering complete international education services from document screening to airport pickup and settlement worldwide.</p>

<h3 style="font-size:20px;font-weight:700;margin-bottom:32px;">15. SIGNATURES</h3>

<div class="signature-grid">

<!-- LEFT: ScholarSync Global / Dr. Twajamahoro (same layout as student contract) -->
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

<!-- RIGHT: Partner Company — all details + e-sign -->
<div class="signature-block" style="
padding:20px;
background:#f9fafb;
border-radius:12px;
border:1px solid #e5e7eb;
">

<p style="font-weight:700;margin-bottom:8px;font-size:16px;">
Partner Company
</p>
<p style="font-size:13px;color:#6b7280;margin-bottom:16px;line-height:1.5;">
Details below are copied automatically from section <strong>1.1 Company Information</strong> above.
Review them, then sign only.
</p>

<?php
$sigLineStyle = 'border-bottom:1px solid #000;height:24px;width:85%;margin-top:6px;padding-top:2px;';
$sigCompany = htmlspecialchars($contract['company_name'] ?? '');
$sigRep = htmlspecialchars($contract['representative_name'] ?? '');
$sigTitle = htmlspecialchars($contract['representative_title'] ?? '');
$sigEmail = htmlspecialchars($contract['representative_email'] ?? $contract['company_email'] ?? '');
$sigPhone = htmlspecialchars($contract['company_phone'] ?? '');
$sigAddress = htmlspecialchars($contract['company_address'] ?? '');
$sigDateDisplay = !empty($contract['signed_date']) ? htmlspecialchars($contract['signed_date']) : date('Y/m/d');
?>

<div style="margin-bottom:18px;">
<strong>Company Name:</strong>
<div id="sig_display_company_name" style="<?= $sigLineStyle ?>"><?= $sigCompany ?></div>
</div>

<div style="margin-bottom:18px;">
<strong>Representative Name:</strong>
<div id="sig_display_representative_name" style="<?= $sigLineStyle ?>"><?= $sigRep ?></div>
</div>

<div style="margin-bottom:18px;">
<strong>Title:</strong>
<div id="sig_display_representative_title" style="<?= $sigLineStyle ?>"><?= $sigTitle ?></div>
</div>

<div style="margin-bottom:18px;">
<strong>Email:</strong>
<div id="sig_display_email" style="<?= $sigLineStyle ?>"><?= $sigEmail ?></div>
</div>

<div style="margin-bottom:18px;">
<strong>Phone:</strong>
<div id="sig_display_phone" style="<?= $sigLineStyle ?>"><?= $sigPhone ?></div>
</div>

<div style="margin-bottom:18px;">
<strong>Company Address:</strong>
<div id="sig_display_address" style="<?= $sigLineStyle ?>"><?= $sigAddress ?></div>
</div>

<input type="hidden" id="contract_start_date" value="<?= date('Y-m-d') ?>">
<input type="hidden" id="sig_signed_date" value="<?= htmlspecialchars(!empty($contract['signed_date']) ? $contract['signed_date'] : date('Y-m-d')) ?>">

<div style="margin-bottom:16px;">
<label style="display:block;margin-bottom:12px;font-weight:600;color:#374151;">Signature:</label>
<div style="
border:2px dashed #9ca3af;
height:150px;
padding:8px;
margin-bottom:10px;
background:#ffffff;
border-radius:8px;
display:flex;
align-items:center;
justify-content:center;
position:relative;
">
<?php if ($isSigned && !empty($contract['signature_image'])): ?>
<img src="<?= $contract['signature_image'] ?>" style="max-height:130px;border:1px solid #e5e7eb;padding:5px;border-radius:4px;" alt="Partner Signature">
<?php else: ?>
<canvas class="signature-canvas"></canvas>
<div style="position:absolute;top:8px;right:8px;font-size:12px;color:#9ca3af;">
Draw your signature above
</div>
<?php endif; ?>
</div>
<div class="signature-status" id="signatureStatus">✓ Signature captured successfully</div>
</div>

<div style="margin-bottom:20px;">
<strong>Date:</strong>
<div id="sig_display_date" style="<?= $sigLineStyle ?>"><?= $sigDateDisplay ?></div>
</div>

<?php if (!$isSigned): ?>
<div style="display:flex;gap:12px;flex-wrap:wrap;">
<button id="clearSignature" type="button" style="flex:1;background:#f3f4f6;color:#374151;border:none;padding:12px 16px;border-radius:6px;font-weight:600;cursor:pointer;">Clear Signature</button>
<button id="signContract" type="button" style="flex:2;background:#3b82f6;color:#ffffff;border:none;padding:12px 16px;border-radius:6px;font-weight:600;cursor:pointer;">Sign &amp; Submit Contract</button>
</div>
<?php endif; ?>

</div>

</div>

</div>

<div class="footer-ref">
Contract Reference: <?= htmlspecialchars($contract['contract_token']) ?>
</div>

</div>

<?php if (!$isSigned): ?>
<script>
(() => {
  const canvas = document.querySelector('.signature-canvas');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  const btnClear = document.getElementById('clearSignature');
  const btnSubmit = document.getElementById('signContract');

  const inputCompany = document.getElementById('company_name');
  const inputName = document.getElementById('representative_name');
  const inputTitle = document.getElementById('representative_title');
  const inputEmail = document.getElementById('company_email');
  const inputPhone = document.getElementById('company_phone');
  const inputAddress = document.getElementById('company_address');
  const inputDate = document.getElementById('sig_signed_date');
  const inputContractDate = document.getElementById('contract_start_date');

  const displayCompany = document.getElementById('sig_display_company_name');
  const displayName = document.getElementById('sig_display_representative_name');
  const displayTitle = document.getElementById('sig_display_representative_title');
  const displayEmail = document.getElementById('sig_display_email');
  const displayPhone = document.getElementById('sig_display_phone');
  const displayAddress = document.getElementById('sig_display_address');
  const displayDate = document.getElementById('sig_display_date');
  const scholarsyncDate = document.getElementById('scholarsync_date');

  let drawing = false;
  let lastX = 0;
  let lastY = 0;
  let currentPoints = []; // Store points for smooth drawing
  let canvasWidth = 0;
  let canvasHeight = 0;
  let scaleX = 1;
  let scaleY = 1;

  // FIXED: Proper canvas initialization with device pixel ratio handling
  function resizeCanvas() {
    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    
    // Store actual display dimensions
    canvasWidth = rect.width;
    canvasHeight = rect.height;
    
    // Set canvas internal dimensions with DPR for sharp rendering
    canvas.width = canvasWidth * dpr;
    canvas.height = canvasHeight * dpr;
    
    // Calculate scaling factors for coordinate conversion
    scaleX = canvas.width / canvasWidth;
    scaleY = canvas.height / canvasHeight;
    
    // Scale context for proper drawing
    ctx.setTransform(scaleX, 0, 0, scaleY, 0, 0);
    
    // Set drawing styles
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#000000';
    
    // Redraw existing signature if any (preserve on resize)
    if (signatureData && signatureData.length > 0) {
      redrawSignature();
    }
  }

  // Store signature paths for persistence
  let signatureData = [];
  let currentPath = [];

  // FIXED: Get accurate coordinates using getBoundingClientRect
  function getCanvasCoordinates(e) {
    const rect = canvas.getBoundingClientRect();
    let clientX, clientY;
    
    if (e.touches) {
      // Touch event
      clientX = e.touches[0].clientX;
      clientY = e.touches[0].clientY;
    } else {
      // Mouse event
      clientX = e.clientX;
      clientY = e.clientY;
    }
    
    // Calculate relative position within canvas
    let x = clientX - rect.left;
    let y = clientY - rect.top;
    
    // Clamp to canvas boundaries
    x = Math.max(0, Math.min(x, canvasWidth));
    y = Math.max(0, Math.min(y, canvasHeight));
    
    return { x, y };
  }

  // FIXED: Start drawing with proper coordinate handling
  function startDrawing(e) {
    e.preventDefault();
    drawing = true;
    
    const coords = getCanvasCoordinates(e);
    lastX = coords.x;
    lastY = coords.y;
    
    // Start new path
    currentPath = [{ x: lastX, y: lastY }];
    
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(lastX, lastY);
    ctx.stroke();
    
    canvas.classList.add('drawing');
  }

  // FIXED: Smooth drawing with interpolation
  function draw(e) {
    if (!drawing) return;
    e.preventDefault();
    
    const coords = getCanvasCoordinates(e);
    const currentX = coords.x;
    const currentY = coords.y;
    
    // Add point to current path
    currentPath.push({ x: currentX, y: currentY });
    
    // Draw line from last position to current
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(currentX, currentY);
    ctx.stroke();
    
    // Update last position
    lastX = currentX;
    lastY = currentY;
  }

  // FIXED: Stop drawing and save path
  function stopDrawing() {
    if (drawing && currentPath.length > 0) {
      // Save the completed path
      signatureData.push([...currentPath]);
      currentPath = [];
    }
    drawing = false;
    canvas.classList.remove('drawing');
  }

  // FIXED: Redraw all saved signatures (for resize preservation)
  function redrawSignature() {
    ctx.clearRect(0, 0, canvasWidth, canvasHeight);
    
    // Set drawing styles
    ctx.beginPath();
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#000000';
    
    // Redraw all saved paths
    for (const path of signatureData) {
      if (path.length === 0) continue;
      
      ctx.beginPath();
      ctx.moveTo(path[0].x, path[0].y);
      
      for (let i = 1; i < path.length; i++) {
        ctx.lineTo(path[i].x, path[i].y);
      }
      ctx.stroke();
    }
  }

  // FIXED: Enhanced signature detection (checks if canvas has actual drawing)
  function hasSignature() {
    // First check if we have any saved paths
    if (signatureData.length > 0) {
      return true;
    }
    
    // Also check pixel data as backup
    try {
      const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
      for (let i = 0; i < pixels.length; i += 4) {
        if (pixels[i] !== 255 || pixels[i+1] !== 255 || pixels[i+2] !== 255) {
          return true;
        }
      }
    } catch(e) {
      console.warn('Pixel check failed:', e);
    }
    
    return false;
  }

  // Setup event listeners with proper touch/mouse handling
  function setupCanvasEvents() {
    // Mouse events
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseleave', stopDrawing);
    
    // Touch events with proper handling
    canvas.addEventListener('touchstart', startDrawing, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', stopDrawing);
    canvas.addEventListener('touchcancel', stopDrawing);
  }

  // Initialize canvas
  resizeCanvas();
  setupCanvasEvents();
  
  // FIXED: Handle window resize without clearing canvas
  let resizeTimeout;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
      resizeCanvas();
    }, 100);
  });

  // Clear signature
  btnClear.addEventListener('click', () => {
    ctx.clearRect(0, 0, canvasWidth, canvasHeight);
    signatureData = [];
    currentPath = [];
    drawing = false;
    
    const status = document.getElementById('signatureStatus');
    if (status) {
      status.classList.remove('show');
    }
  });

  // Update signature status when drawing
  function updateSignatureStatus() {
    const status = document.getElementById('signatureStatus');
    const hasSig = hasSignature();
    
    if (hasSig && status && !status.classList.contains('show')) {
      status.textContent = '✓ Signature captured successfully';
      status.classList.add('show');
    } else if (!hasSig && status && status.classList.contains('show')) {
      status.classList.remove('show');
    }
  }

  // Monitor signature status
  setInterval(updateSignatureStatus, 500);

  function syncSignatureDisplay() {
    const companyValue = inputCompany ? inputCompany.value.trim() : '';
    const nameValue = inputName ? inputName.value.trim() : '';
    const titleValue = inputTitle ? inputTitle.value.trim() : '';
    const emailValue = inputEmail ? inputEmail.value.trim() : '';
    const phoneValue = inputPhone ? inputPhone.value.trim() : '';
    const addressValue = inputAddress ? inputAddress.value.trim() : '';
    const emptyHint = '—';

    if (displayCompany) displayCompany.textContent = companyValue || emptyHint;
    if (displayName) displayName.textContent = nameValue || emptyHint;
    if (displayTitle) displayTitle.textContent = titleValue || emptyHint;
    if (displayEmail) displayEmail.textContent = emailValue || emptyHint;
    if (displayPhone) displayPhone.textContent = phoneValue || emptyHint;
    if (displayAddress) displayAddress.textContent = addressValue || emptyHint;

    const inlineNames = [
      document.getElementById('display_company_name'),
      document.getElementById('display_company_name2'),
      document.getElementById('display_company_name3')
    ];
    inlineNames.forEach((el) => {
      if (el) el.textContent = companyValue || '______________________________';
    });
  }

  function scrollToFirstMissingField() {
    const required = [
      { el: inputCompany, msg: 'Please enter your company name at the top of the contract.' },
      { el: inputName, msg: 'Please enter your representative name at the top of the contract.' },
      { el: inputTitle, msg: 'Please enter your title at the top of the contract.' },
      { el: inputEmail, msg: 'Please enter your company email at the top of the contract.' }
    ];
    for (const item of required) {
      if (!item.el || item.el.value.trim()) continue;
      item.el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      item.el.focus();
      alert(item.msg);
      return true;
    }
    return false;
  }

  function setDefaultDate() {
    const today = new Date().toISOString().split('T')[0];
    if (inputDate) inputDate.value = today;
    if (inputContractDate) inputContractDate.value = today;
    if (displayDate) displayDate.textContent = today.replace(/-/g, '/');
    if (scholarsyncDate) scholarsyncDate.textContent = today.replace(/-/g, '/');
  }

  [inputCompany, inputName, inputTitle, inputEmail, inputPhone, inputAddress].forEach((el) => {
    if (!el) return;
    ['input', 'change', 'keyup', 'paste'].forEach((evt) => {
      el.addEventListener(evt, syncSignatureDisplay);
    });
  });

  setDefaultDate();
  syncSignatureDisplay();

  // FIXED: Enhanced submission with proper signature capture
  btnSubmit.addEventListener('click', () => {
    syncSignatureDisplay();

    if (scrollToFirstMissingField()) {
      return;
    }

    const finalCompany = inputCompany ? inputCompany.value.trim() : '';
    const finalEmail = inputEmail ? inputEmail.value.trim() : '';
    const finalPhone = inputPhone ? inputPhone.value.trim() : '';
    const finalAddress = inputAddress ? inputAddress.value.trim() : '';
    const finalName = inputName ? inputName.value.trim() : '';
    const finalTitle = inputTitle ? inputTitle.value.trim() : '';
    const signedDate = inputDate ? inputDate.value : '';
    
    if (!signedDate) {
      alert('Signing date is missing.');
      return;
    }
    
    // FIXED: Enhanced signature validation
    if (!hasSignature()) {
      alert('Please draw your signature in the canvas area before submitting.');
      return;
    }

    // Show loading state
    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Submitting...';

    // FIXED: Ensure we capture the signature at full quality
    let signatureImage;
    try {
      signatureImage = canvas.toDataURL('image/png');
      // Validate that signature image is not empty
      if (!signatureImage || signatureImage === 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==') {
        throw new Error('Empty signature captured');
      }
    } catch(e) {
      console.error('Signature capture error:', e);
      alert('Error capturing signature. Please try drawing your signature again.');
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Sign & Submit Contract';
      return;
    }

    const payload = {
      token: "<?= htmlspecialchars($token) ?>",
      representative_name: finalName,
      representative_title: finalTitle,
      representative_email: finalEmail,
      signed_date: signedDate,
      signature: signatureImage,
      company_name: finalCompany,
      company_phone: finalPhone,
      company_address: finalAddress
    };

    fetch('submit-partner-signature.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert('Contract signed successfully!');
        window.location.reload();
      } else {
        alert(data.error || 'Submission failed. Please try again.');
        btnSubmit.disabled = false;
        btnSubmit.textContent = 'Sign & Submit Contract';
      }
    })
    .catch(err => {
      console.error('Error:', err);
      alert('Submission failed. Please check your connection and try again.');
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Sign & Submit Contract';
    });
  });
})();
</script>
<?php endif; ?>

</body>
</html>