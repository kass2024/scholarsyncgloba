<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
require_once __DIR__ . '/helpers/fm_mobility_contract_schema.php';

fm_ensure_schema($conn);
fm_contract_ensure_schema($conn);

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    exit('Database connection error.');
}

if (!isset($_GET['token']) || trim($_GET['token']) === '') {
    http_response_code(400);
    exit('Invalid contract link.');
}

$token = trim($_GET['token']);

$stmt = $conn->prepare('SELECT * FROM fm_mobility_contracts WHERE contract_token = ? LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contract) {
    http_response_code(404);
    exit('This contract link is invalid or expired.');
}

$isSigned = ($contract['status'] === 'signed');
$referenceId = '';

$clientSignatureData = null;
if ($isSigned && !empty($contract['id'])) {
    $contractId = (int) $contract['id'];
    $stmt = $conn->prepare('SELECT signature_image FROM fm_mobility_signatures WHERE contract_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $contractId);
    $stmt->execute();
    $sigRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!empty($sigRow['signature_image'])) {
        $clientSignatureData = $sigRow['signature_image'];
    }
}

$applicant = null;
if (!empty($contract['application_id'])) {
    $appId = (int) $contract['application_id'];
    $stmt = $conn->prepare('SELECT * FROM francophonie_mobility_applications WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $appId);
    $stmt->execute();
    $applicant = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($applicant) {
        $referenceId = $applicant['reference_id'] ?? '';
    }
}

$displayClient = [
    'name'        => $contract['external_client_name'] ?? '',
    'email'       => $contract['external_client_email'] ?? '',
    'phone'       => $contract['external_client_phone'] ?? '',
    'dob'         => $contract['external_client_dob'] ?? '',
    'nationality' => $contract['external_client_nationality'] ?? '',
    'passport'    => $contract['external_client_passport'] ?? '',
    'address'     => $contract['external_client_address'] ?? '',
];

if ($applicant && !$isSigned) {
    if ($displayClient['name'] === '') {
        $displayClient['name'] = trim(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? ''));
    }
    if ($displayClient['email'] === '') {
        $displayClient['email'] = $applicant['email'] ?? '';
    }
    if ($displayClient['phone'] === '') {
        $area = ltrim((string) ($applicant['phone_area_code'] ?? ''), '+');
        $num  = trim((string) ($applicant['phone_number'] ?? ''));
        $displayClient['phone'] = ($area !== '' || $num !== '') ? '+' . $area . ' ' . $num : '';
    }
    if ($displayClient['nationality'] === '' && ($applicant['nationality'] ?? '') !== 'N/A') {
        $displayClient['nationality'] = $applicant['nationality'] ?? '';
    }
    if ($displayClient['dob'] === '' && !empty($applicant['date_of_birth'])) {
        $displayClient['dob'] = $applicant['date_of_birth'];
    }
    if ($displayClient['passport'] === '' && !empty($applicant['passport_number'])) {
        $displayClient['passport'] = $applicant['passport_number'];
    }
    if ($displayClient['address'] === '' && !empty($applicant['address'])) {
        $displayClient['address'] = $applicant['address'];
    } elseif ($displayClient['address'] === '') {
        $res = $applicant['country_of_residence'] ?? '';
        if ($res !== '' && $res !== 'N/A') {
            $displayClient['address'] = 'Country of Residence: ' . $res;
        }
    }
}

$agreementDate = $contract['agreement_date'] ?? date('Y-m-d');
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Francophonie Mobility Service Agreement | ScholarSync Global</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root {
  --primary: #2d5a27;
  --primary-light: #427431;
  --accent: #c0392b;
  --ink: #1a2e1a;
  --muted: #4b5563;
  --border: #d4e4d4;
  --soft: #f0f7f0;
  --paper: #ffffff;
  --success: #15803d;
}

* { box-sizing: border-box; }

body {
  margin: 0;
  padding: 16px 12px 40px;
  background: linear-gradient(160deg, #e8f5e9 0%, #f0f4f0 40%, #e2e8f0 100%);
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  color: var(--ink);
  min-height: 100vh;
}

.contract-signed .sign-section,
.contract-signed #clearSignature,
.contract-signed #signContract { display: none !important; }

.signed-banner {
  max-width: 860px;
  margin: 0 auto 20px;
  background: linear-gradient(135deg, #dcfce7, #bbf7d0);
  border: 1px solid #86efac;
  border-radius: 14px;
  padding: 20px 24px;
  text-align: center;
}

.signed-banner h2 { margin: 0 0 8px; color: var(--success); font-size: 20px; }
.signed-banner p { margin: 0 0 16px; color: #166534; font-size: 14px; }
.signed-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
.btn-dl, .btn-view {
  padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 14px;
  text-decoration: none; display: inline-block;
}
.btn-dl { background: var(--primary); color: #fff; }
.btn-view { background: #fff; color: var(--primary); border: 2px solid var(--primary); }

.contract-wrap {
  max-width: 860px;
  margin: 0 auto;
  background: var(--paper);
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(45, 90, 39, 0.15);
  overflow: hidden;
  border-top: 5px solid var(--primary);
}

.contract-header {
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  color: #fff;
  padding: 28px 24px;
  text-align: center;
}

.contract-header .badge {
  display: inline-block;
  background: rgba(255,255,255,0.2);
  padding: 4px 14px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.5px;
  margin-bottom: 12px;
}

.contract-header h1 {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 700;
  letter-spacing: 0.3px;
}

.contract-header h2 {
  margin: 0;
  font-size: 15px;
  font-weight: 500;
  opacity: 0.95;
}

.contract-body { padding: 28px 24px 32px; }

.notice {
  background: #fffbeb;
  border-left: 4px solid #f59e0b;
  padding: 14px 16px;
  border-radius: 0 8px 8px 0;
  font-size: 13px;
  color: #92400e;
  margin-bottom: 24px;
  line-height: 1.5;
}

.section-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--primary);
  margin: 28px 0 14px;
  padding-bottom: 6px;
  border-bottom: 2px solid var(--border);
}

.section-title:first-of-type { margin-top: 0; }

p { font-size: 14px; line-height: 1.65; margin: 0 0 12px; text-align: justify; }

ul.contract-list {
  margin: 8px 0 16px;
  padding-left: 22px;
  font-size: 14px;
  line-height: 1.65;
}
ul.contract-list li { margin-bottom: 6px; }

.client-form {
  background: var(--soft);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  margin: 16px 0 24px;
}

.client-form h3 {
  margin: 0 0 16px;
  font-size: 15px;
  color: var(--primary);
}

.form-row { margin-bottom: 14px; }
.form-row label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
  margin-bottom: 5px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.form-row input, .form-row textarea {
  width: 100%;
  padding: 11px 14px;
  border: 2px solid var(--border);
  border-radius: 8px;
  font-size: 15px;
  transition: border-color 0.2s;
  font-family: inherit;
}

.form-row input:focus, .form-row textarea:focus {
  outline: none;
  border-color: var(--primary-light);
}

.form-row input[readonly] { background: #f3f4f6; }

.autofill-hint {
  font-size: 12px;
  color: var(--muted);
  margin-top: 6px;
}

.autofill-match {
  background: #e8f5e9;
  border: 1px solid #a5d6a7;
  border-radius: 8px;
  padding: 12px 14px;
  margin-top: 12px;
  font-size: 13px;
  display: none;
}

.autofill-match.visible { display: block; }
.autofill-match button {
  margin-top: 8px;
  background: var(--primary);
  color: #fff;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
}

.fee-grid {
  display: grid;
  gap: 14px;
  margin: 16px 0;
}

@media (min-width: 640px) {
  .fee-grid { grid-template-columns: repeat(3, 1fr); }
}

.fee-card {
  border: 2px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  background: #fff;
}

.fee-card .amount {
  font-size: 22px;
  font-weight: 700;
  color: var(--primary);
  margin: 8px 0;
}

.fee-card .label { font-size: 13px; font-weight: 700; color: var(--ink); }
.fee-card ul { margin: 10px 0 0; padding-left: 16px; font-size: 12px; color: var(--muted); }
.fee-card ul li { margin-bottom: 4px; }

.total-fee {
  text-align: center;
  background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
  border-radius: 12px;
  padding: 18px;
  margin: 20px 0;
  font-size: 18px;
  font-weight: 700;
  color: var(--primary);
}

.sign-section {
  max-width: 520px;
  margin: 32px auto 0;
  padding: 24px;
  background: #fafbfc;
  border-radius: 14px;
  border: 2px solid var(--border);
}

.sign-section h3 { text-align: center; margin: 0 0 20px; font-size: 17px; color: var(--primary); }

.sig-canvas-wrap {
  border: 2px dashed #9ca3af;
  border-radius: 10px;
  height: 150px;
  background: #fff;
  position: relative;
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sig-canvas-wrap canvas { width: 100%; height: 148px; touch-action: none; }
.sig-hint { position: absolute; top: 8px; right: 10px; font-size: 11px; color: #9ca3af; }

.sign-btns { display: flex; gap: 10px; flex-wrap: wrap; }
.sign-btns button {
  flex: 1; min-width: 120px; padding: 13px; border: none; border-radius: 8px;
  font-size: 15px; font-weight: 600; cursor: pointer;
}
#clearSignature { background: #f3f4f6; color: #374151; }
#signContract { background: var(--primary); color: #fff; flex: 2; }

.footer-ref {
  text-align: center;
  margin-top: 28px;
  font-size: 11px;
  color: #9ca3af;
}

.applicant-ref {
  display: inline-block;
  background: #e8f5e9;
  color: var(--primary);
  padding: 3px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  margin-left: 8px;
}
</style>
</head>
<body<?= $isSigned ? ' class="contract-signed"' : '' ?>>

<?php if ($isSigned): ?>
<div class="signed-banner">
  <h2>✓ Contract Signed Successfully</h2>
  <p>Your Francophonie Mobility Service Agreement has been recorded.<?php if ($referenceId): ?> Application Ref: <strong><?= htmlspecialchars($referenceId) ?></strong><?php endif; ?></p>
  <div class="signed-actions">
    <a class="btn-dl" href="download-fm-contract.php?token=<?= urlencode($token) ?>">Download Signed PDF</a>
    <a class="btn-view" href="download-fm-contract.php?token=<?= urlencode($token) ?>&amp;inline=1" target="_blank" rel="noopener">View PDF</a>
  </div>
</div>
<?php endif; ?>

<div class="contract-wrap">
  <div class="contract-header">
    <div class="badge">🍁 FRANCOPHONIE MOBILITY PROGRAM</div>
    <h1>SCHOLARSYNC GLOBAL CO. LTD.</h1>
    <h2>Francophonie Mobility (Mobilité Francophone) Service Agreement</h2>
  </div>

  <div class="contract-body">

    <?php if (!$isSigned): ?>
    <div class="notice">
      Please read this Agreement carefully. By signing electronically below, you acknowledge that you fully understand and voluntarily accept all terms and conditions contained herein.
    </div>
    <?php endif; ?>

    <p><strong>Agreement Date:</strong>
      <input type="date" id="agreement_date" value="<?= htmlspecialchars($agreementDate ?: $today) ?>" <?= $isSigned ? 'readonly' : '' ?> style="border:none;border-bottom:1px solid #ccc;padding:2px 6px;font-size:14px;">
    </p>

    <p>This Service Agreement ("Agreement") is entered into on the date indicated above between:</p>

    <p>
      <strong>SCHOLARSYNC GLOBAL CO. LTD.</strong><br>
      Represented by: Dr. Jean Pierre Twajamahoro, Managing Director<br>
      (Hereinafter referred to as "the Company")
    </p>

    <p><strong>AND</strong></p>

    <div class="client-form">
      <h3>CLIENT INFORMATION <?php if ($referenceId): ?><span class="applicant-ref"><?= htmlspecialchars($referenceId) ?></span><?php endif; ?></h3>

      <div class="form-row">
        <label for="client_email">Email</label>
        <input type="email" id="client_email" required autocomplete="email"
               value="<?= htmlspecialchars($displayClient['email']) ?>"
               placeholder="Enter your email address" <?= $isSigned ? 'readonly' : '' ?>>
        <p class="autofill-hint">Enter your email to auto-fill from your mobility application.</p>
        <div class="autofill-match" id="autofillMatch"></div>
      </div>

      <div class="form-row">
        <label for="client_name">Full Name</label>
        <input type="text" id="client_name" required autocomplete="name"
               value="<?= htmlspecialchars($displayClient['name']) ?>"
               placeholder="Full legal name" <?= $isSigned ? 'readonly' : '' ?>>
      </div>

      <div class="form-row">
        <label for="client_passport">Passport Number</label>
        <input type="text" id="client_passport" autocomplete="off"
               value="<?= htmlspecialchars($displayClient['passport']) ?>"
               placeholder="Passport number" <?= $isSigned ? 'readonly' : '' ?>>
      </div>

      <div class="form-row">
        <label for="client_nationality">Nationality</label>
        <input type="text" id="client_nationality" required autocomplete="country-name"
               value="<?= htmlspecialchars($displayClient['nationality']) ?>"
               placeholder="Nationality" <?= $isSigned ? 'readonly' : '' ?>>
      </div>

      <div class="form-row">
        <label for="client_dob">Date of Birth</label>
        <input type="date" id="client_dob" required autocomplete="bday"
               value="<?= htmlspecialchars($displayClient['dob']) ?>" <?= $isSigned ? 'readonly' : '' ?>>
      </div>

      <div class="form-row">
        <label for="client_address">Address</label>
        <textarea id="client_address" rows="2" placeholder="Full residential address"
                  <?= $isSigned ? 'readonly' : '' ?>><?= htmlspecialchars($displayClient['address']) ?></textarea>
      </div>

      <div class="form-row">
        <label for="client_phone">Telephone</label>
        <input type="tel" id="client_phone" required autocomplete="tel"
               value="<?= htmlspecialchars($displayClient['phone']) ?>"
               placeholder="Phone number" <?= $isSigned ? 'readonly' : '' ?>>
      </div>
    </div>

    <p>(Hereinafter referred to as "the Client")</p>
    <p>The Company and the Client shall collectively be referred to as "the Parties."</p>

    <div class="section-title">1. PURPOSE OF THE AGREEMENT</div>
    <p>The purpose of this Agreement is to establish the terms and conditions under which SCHOLARSYNC GLOBAL CO. LTD. shall provide professional recruitment, employment support, and immigration assistance to the Client for obtaining employment in Canada through the Francophone Mobility (Mobilité francophone) Program.</p>

    <div class="section-title">2. SCOPE OF SERVICES</div>
    <p>The Company agrees to provide the following professional services:</p>
    <ul class="contract-list">
      <li>Assessment of the Client's eligibility for the Francophone Mobility Program.</li>
      <li>Professional review and optimization of the Client's CV according to Canadian standards.</li>
      <li>Evaluation of the Client's qualifications and work experience.</li>
      <li>Registration of the Client's profile within the Company's recruitment network.</li>
      <li>Matching the Client with suitable Canadian employers.</li>
      <li>Communication and follow-up with prospective employers.</li>
      <li>Guidance regarding French language requirements.</li>
      <li>Assistance with interview preparation.</li>
      <li>Assistance with employment documentation.</li>
      <li>Guidance in preparing the Canadian work permit application.</li>
      <li>General support throughout the recruitment and immigration process.</li>
    </ul>

    <div class="section-title">3. CLIENT RESPONSIBILITIES</div>
    <p>The Client agrees to:</p>
    <ul class="contract-list">
      <li>Provide complete, truthful, and accurate information.</li>
      <li>Submit genuine and valid documents.</li>
      <li>Cooperate throughout the recruitment process.</li>
      <li>Attend interviews requested by employers.</li>
      <li>Complete any required French language assessment or training.</li>
      <li>Respond promptly to requests from the Company.</li>
      <li>Respect all payment obligations under this Agreement.</li>
      <li>Immediately inform the Company of any change affecting their application.</li>
    </ul>

    <div class="section-title">4. COMPANY RESPONSIBILITIES</div>
    <p>SCHOLARSYNC GLOBAL CO. LTD. agrees to:</p>
    <ul class="contract-list">
      <li>Act honestly, professionally, and ethically.</li>
      <li>Protect the confidentiality of the Client's information.</li>
      <li>Maintain communication with the Client throughout the process.</li>
      <li>Promote the Client's profile to suitable Canadian employers.</li>
      <li>Assist with employment documentation.</li>
      <li>Provide guidance during the work permit application process.</li>
      <li>Continue providing professional support until the agreed services under this Agreement have been completed.</li>
    </ul>

    <div class="section-title">5. SERVICE COMMITMENT &amp; PAYMENT TERMS</div>
    <p>SCHOLARSYNC GLOBAL CO. LTD. is committed to providing professional recruitment and immigration support services throughout the Client's participation in the Francophone Mobility (Mobilité francophone) Program.</p>
    <p>The Company shall use its professional expertise, recruitment network, and employer partnerships to assist the Client in securing a valid employment opportunity in Canada and to provide guidance through the work permit application process.</p>
    <p>The total professional service fee for this program is:</p>

    <div class="total-fee">Eight Thousand Canadian Dollars (CAD $8,000)</div>

    <p>The fee shall be paid in three (3) installments as follows:</p>

    <div class="fee-grid">
      <div class="fee-card">
        <div class="label">First Installment</div>
        <div class="amount">CAD $850</div>
        <p style="font-size:12px;margin:0;">Payable before the commencement of the recruitment process.</p>
        <ul>
          <li>File opening</li>
          <li>Eligibility assessment</li>
          <li>Professional CV review</li>
          <li>Canadian CV optimization</li>
          <li>Profile registration</li>
          <li>Interview preparation</li>
          <li>Recruitment process initiation</li>
          <li>Securing a valid Canadian job offer</li>
          <li>Work permit application preparation</li>
        </ul>
      </div>
      <div class="fee-card">
        <div class="label">Second Installment</div>
        <div class="amount">CAD $3,150</div>
        <p style="font-size:12px;margin:0;">Payable immediately once the Canadian Embassy requests you to complete a medical examination.</p>
        <p style="font-size:12px;margin:8px 0 0;"><strong>Note:</strong> In this case, the visa approval rate is over 98%</p>
        <ul>
          <li>Work permit application flow up</li>
          <li>Employer communication</li>
          <li>Employment documentation</li>
          <li>Employer support services</li>
        </ul>
      </div>
      <div class="fee-card">
        <div class="label">Third Installment</div>
        <div class="amount">CAD $4,000</div>
        <ul>
          <li>Payable after visa approval by IRCC.</li>
          <li>Must be paid before departure for Canada.</li>
          <li>Airport pickup upon arrival in Canada.</li>
          <li>Orientation and settlement support to help you become familiar with life in Canada.</li>
          <li>Assistance in finding suitable rental accommodation.</li>
        </ul>
      </div>
    </div>

    <p>Failure to pay any installment when due may result in suspension of services until payment has been received.</p>

    <div class="section-title">6. COMPANY COMMITMENT</div>
    <p>SCHOLARSYNC GLOBAL CO. LTD. commits to:</p>
    <ul class="contract-list">
      <li>Conducting a professional assessment of the Client's profile.</li>
      <li>Promoting the Client's qualifications to suitable Canadian employers.</li>
      <li>Assisting with interview preparation.</li>
      <li>Providing guidance regarding the Francophone Mobility Program.</li>
      <li>Assisting with employment-related documentation.</li>
      <li>Guiding the Client through the Canadian work permit application process after a valid employment offer has been obtained.</li>
      <li>Providing continuous professional support until the completion of the agreed services under this Agreement.</li>
    </ul>
    <p>The Client understands that hiring decisions are made solely by Canadian employers and that work permit decisions are made exclusively by Immigration, Refugees and Citizenship Canada (IRCC).</p>

    <div class="section-title">7. REFUND POLICY</div>
    <p>The Client acknowledges and agrees that:</p>
    <ul class="contract-list">
      <li>The CAD $850 first installment is non-refundable once recruitment services have commenced. (Are refundable when ScholarSync failed to get job offer for client)</li>
      <li>The CAD $3,150 second installment becomes payable immediately once the Canadian Embassy requests the Client to complete a medical examination.</li>
      <li>The CAD $4,000 final installment becomes payable only after approval of the Client's Canadian work permit.</li>
      <li>Government fees, biometrics fees, medical examination fees, language test fees, courier charges, translation costs, travel expenses, and any other third-party expenses are not included in this Agreement unless expressly stated.</li>
    </ul>

    <div class="section-title">8. TERMINATION</div>
    <p>Either Party may terminate this Agreement by providing written notice.</p>
    <p>Termination shall not affect payment obligations for services already rendered.</p>

    <div class="section-title">9. CONFIDENTIALITY</div>
    <p>The Company agrees to keep all personal information and documents confidential and shall not disclose such information to any third party except:</p>
    <ul class="contract-list">
      <li>Where required by law;</li>
      <li>With the Client's written consent;</li>
      <li>For legitimate recruitment or immigration processing purposes.</li>
    </ul>

    <div class="section-title">10. GOVERNING LAW</div>
    <p>This Agreement shall be governed by international laws and the applicable federal laws of Canada.</p>

    <div class="section-title">11. ENTIRE AGREEMENT</div>
    <p>This Agreement constitutes the complete understanding between the Parties and supersedes all prior discussions, representations, or agreements relating to the services described herein.</p>
    <p>Any amendment to this Agreement shall be made in writing and signed by both Parties.</p>

    <div class="section-title">DECLARATION</div>
    <p>The Client confirms that they have carefully read and understood this Agreement and voluntarily accept all of its terms and conditions.</p>

    <div class="sign-section">
      <h3>SIGNED BY THE CLIENT</h3>

      <div class="form-row">
        <label for="sig_client_name">Full Name</label>
        <input type="text" id="sig_client_name" placeholder="Full legal name" <?= $isSigned ? 'readonly value="' . htmlspecialchars($displayClient['name']) . '"' : '' ?>>
      </div>

      <div class="form-row">
        <label>Signature</label>
        <div class="sig-canvas-wrap">
          <?php if ($isSigned && $clientSignatureData): ?>
            <img src="<?= $clientSignatureData ?>" style="max-height:130px;" alt="Client Signature">
          <?php else: ?>
            <canvas class="signature-canvas"></canvas>
            <span class="sig-hint">Draw your signature</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <label for="sig_signed_date">Date</label>
        <input type="date" id="sig_signed_date" <?= $isSigned ? 'readonly' : '' ?>>
      </div>

      <div class="form-row">
        <label for="sig_passport">Passport Number</label>
        <input type="text" id="sig_passport" placeholder="Passport number"
               value="<?= htmlspecialchars($displayClient['passport']) ?>" <?= $isSigned ? 'readonly' : '' ?>>
      </div>

      <div class="sign-btns">
        <button id="clearSignature" type="button">Clear</button>
        <button id="signContract" type="button">Sign &amp; Submit Contract</button>
      </div>
      <input type="hidden" id="signatureData">
    </div>

    <div class="footer-ref">
      Contract Reference: <?= htmlspecialchars($contract['contract_token']) ?>
      <?php if ($referenceId): ?> | Application: <?= htmlspecialchars($referenceId) ?><?php endif; ?>
    </div>
  </div>
</div>

<script>
const CONTRACT_TOKEN = <?= json_encode($token) ?>;
const IS_SIGNED = <?= $isSigned ? 'true' : 'false' ?>;

<?php if (!$isSigned): ?>
const fmAutofill = {
  applicant: null,
  isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
  },
  apply(applicant) {
    if (!applicant) return;
    this.applicant = applicant;
    const emailField = document.getElementById('client_email');
    const matchBox = document.getElementById('autofillMatch');
    if (emailField && applicant.email) {
      emailField.value = applicant.email;
    }
    const set = (id, val) => {
      const el = document.getElementById(id);
      if (el && val !== undefined && val !== null && String(val).trim() !== '') {
        el.value = val;
      }
    };
    set('client_name', applicant.full_name);
    set('client_phone', applicant.phone);
    set('client_nationality', applicant.nationality);
    set('client_address', applicant.address);
    set('client_dob', applicant.date_of_birth);
    set('client_passport', applicant.passport_number);
    const sigName = document.getElementById('sig_client_name');
    const sigPassport = document.getElementById('sig_passport');
    const nameEl = document.getElementById('client_name');
    const passportEl = document.getElementById('client_passport');
    if (sigName && nameEl) sigName.value = nameEl.value;
    if (sigPassport && passportEl) sigPassport.value = passportEl.value;
    if (matchBox) {
      matchBox.innerHTML = '<span style="color:#15803d;">✓ Auto-filled from mobility application' +
        (applicant.reference_id ? ' (' + applicant.reference_id + ')' : '') + '.</span>';
      matchBox.classList.add('visible');
    }
  },
  clearFields() {
    this.applicant = null;
    ['client_name', 'client_phone', 'client_nationality', 'client_address', 'client_dob', 'client_passport']
      .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    const matchBox = document.getElementById('autofillMatch');
    if (matchBox) {
      matchBox.classList.remove('visible');
      matchBox.innerHTML = '';
    }
  },
  async lookup(query) {
    const q = String(query || '').trim();
    if (q.length < 3) return null;
    const res = await fetch('fm-autofill.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: q })
    });
    const data = await res.json();
    if (!data.possible_match || !data.applicant) return null;
    this.apply(data.applicant);
    return data.applicant;
  },
  async resolveEmailForSubmit() {
    const emailField = document.getElementById('client_email');
    let email = emailField?.value.trim() || '';
    if (this.isValidEmail(email)) return email;
    if (this.applicant?.email && this.isValidEmail(this.applicant.email)) {
      if (emailField) emailField.value = this.applicant.email;
      return this.applicant.email;
    }
    if (email.length >= 3) {
      const applicant = await this.lookup(email);
      if (applicant?.email && this.isValidEmail(applicant.email)) {
        return applicant.email;
      }
    }
    return null;
  }
};

(() => {
  const canvas = document.querySelector('.signature-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const btnClear = document.getElementById('clearSignature');
  const btnSubmit = document.getElementById('signContract');
  const inputSigName = document.getElementById('sig_client_name');
  const inputSigDate = document.getElementById('sig_signed_date');
  const inputSigPassport = document.getElementById('sig_passport');
  const mainName = document.getElementById('client_name');
  const mainPassport = document.getElementById('client_passport');
  const todayIso = new Date().toISOString().slice(0, 10);
  if (inputSigDate && !inputSigDate.value) inputSigDate.value = todayIso;
  if (mainName && inputSigName && !inputSigName.value) inputSigName.value = mainName.value.trim();
  if (mainPassport && inputSigPassport && !inputSigPassport.value) inputSigPassport.value = mainPassport.value.trim();
  mainName?.addEventListener('input', () => { inputSigName.value = mainName.value.trim(); });
  mainPassport?.addEventListener('input', () => { inputSigPassport.value = mainPassport.value.trim(); });

  let drawing = false;
  function resizeCanvas() {
    const ratio = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * ratio;
    canvas.height = rect.height * ratio;
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#000';
  }
  resizeCanvas();
  window.addEventListener('resize', resizeCanvas);

  function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    if (e.touches) return { x: e.touches[0].clientX - rect.left, y: e.touches[0].clientY - rect.top };
    return { x: e.offsetX, y: e.offsetY };
  }
  function startDraw(e) { e.preventDefault(); drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
  function draw(e) { if (!drawing) return; e.preventDefault(); const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); }
  function stopDraw() { drawing = false; }
  canvas.addEventListener('mousedown', startDraw);
  canvas.addEventListener('mousemove', draw);
  canvas.addEventListener('mouseup', stopDraw);
  canvas.addEventListener('mouseleave', stopDraw);
  canvas.addEventListener('touchstart', startDraw, { passive: false });
  canvas.addEventListener('touchmove', draw, { passive: false });
  canvas.addEventListener('touchend', stopDraw);

  function hasSignature() {
    const d = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
    for (let i = 3; i < d.length; i += 4) if (d[i] > 0) return true;
    return false;
  }

  btnClear?.addEventListener('click', () => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
  });

  btnSubmit?.addEventListener('click', async () => {
    const name = document.getElementById('client_name')?.value.trim();
    let email = document.getElementById('client_email')?.value.trim();
    const dob = document.getElementById('client_dob')?.value;
    const nationality = document.getElementById('client_nationality')?.value.trim();
    const phone = document.getElementById('client_phone')?.value.trim();
    const address = document.getElementById('client_address')?.value.trim();
    const passport = document.getElementById('client_passport')?.value.trim();
    const agreementDate = document.getElementById('agreement_date')?.value;
    const signedDate = inputSigDate?.value;
    const sigName = inputSigName?.value.trim() || name;

    if (!name || !dob || !nationality || !phone || !signedDate) {
      alert('Please complete all required client fields before signing.');
      return;
    }
    if (!hasSignature()) {
      alert('Please draw your signature before submitting.');
      return;
    }

    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Submitting...';

    if (!fmAutofill.isValidEmail(email)) {
      btnSubmit.textContent = 'Resolving email...';
      const resolved = await fmAutofill.resolveEmailForSubmit();
      if (!resolved) {
        btnSubmit.disabled = false;
        btnSubmit.textContent = 'Sign & Submit Contract';
        alert('Please enter a valid email address (type at least 3 characters to auto-fill from your application).');
        return;
      }
      email = resolved;
    }

    const payload = {
      token: CONTRACT_TOKEN,
      client_name: sigName,
      client_email: email,
      client_dob: dob,
      client_nationality: nationality,
      client_phone: phone,
      client_address: address,
      client_passport: passport || inputSigPassport?.value.trim(),
      agreement_date: agreementDate,
      signed_date: signedDate,
      signature: canvas.toDataURL('image/png')
    };

    fetch('submit-fm-signature.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert(data.pdf_error
          ? 'Contract signed, but PDF generation had an issue. Reload and try Download.'
          : 'Contract signed successfully! You can now download your signed agreement.');
        window.location.reload();
        return;
      }
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Sign & Submit Contract';
      alert(data.error || 'Submission failed.');
    })
    .catch(() => {
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Sign & Submit Contract';
      alert('Unable to submit. Please check your connection and try again.');
    });
  });
})();

(() => {
  const emailField = document.getElementById('client_email');
  if (!emailField) return;

  let debounce = null;
  let lastSearchQuery = '';

  emailField.addEventListener('input', () => {
    clearTimeout(debounce);
    const query = emailField.value.trim();

    if (query.length < 3) {
      fmAutofill.clearFields();
      lastSearchQuery = '';
      return;
    }

    const resolved = fmAutofill.applicant?.email || '';
    const stillSameLookup = resolved && (
      resolved.toLowerCase() === query.toLowerCase() ||
      resolved.toLowerCase().includes(query.toLowerCase())
    );

    if (!stillSameLookup && query !== lastSearchQuery) {
      fmAutofill.clearFields();
    }

    lastSearchQuery = query;
    debounce = setTimeout(async () => {
      const current = emailField.value.trim();
      if (current.length < 3) return;
      await fmAutofill.lookup(current);
    }, 400);
  });

  const initialEmail = emailField.value.trim();
  if (initialEmail.length >= 3) {
    fmAutofill.lookup(initialEmail);
  }
})();
<?php endif; ?>
</script>
</body>
</html>
