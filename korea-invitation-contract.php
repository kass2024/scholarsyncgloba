<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/korea_invitation_contract_schema.php';

kic_contract_ensure_schema($conn);

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    exit('Database connection error.');
}

if (!isset($_GET['token']) || trim($_GET['token']) === '') {
    http_response_code(400);
    exit('Invalid contract link.');
}

$token = trim($_GET['token']);

$stmt = $conn->prepare('SELECT * FROM korea_invitation_contracts WHERE contract_token = ? LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contract) {
    http_response_code(404);
    exit('This contract link is invalid or expired.');
}

$isSigned = ($contract['status'] === 'signed');

$clientSignatureData = null;
$signedClientName = '';
$signedDate = '';
if ($isSigned && !empty($contract['id'])) {
    $contractId = (int) $contract['id'];
    $stmt = $conn->prepare('
        SELECT client_name, signed_date, signature_image
        FROM korea_invitation_signatures
        WHERE contract_id = ?
        ORDER BY id DESC
        LIMIT 1
    ');
    $stmt->bind_param('i', $contractId);
    $stmt->execute();
    $sigRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($sigRow) {
        $signedClientName = $sigRow['client_name'] ?? '';
        $signedDate = $sigRow['signed_date'] ?? '';
        if (!empty($sigRow['signature_image'])) {
            $clientSignatureData = $sigRow['signature_image'];
        }
    }
}

$student = null;
if (!empty($contract['student_id']) && is_numeric($contract['student_id'])) {
    $studentId = (int) $contract['student_id'];
    $stmt = $conn->prepare('
        SELECT first_name, last_name, email, passport_number, phone_number
        FROM student_applications
        WHERE id = ?
        LIMIT 1
    ');
    if ($stmt) {
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$displayClient = [
    'name'                 => $contract['external_client_name'] ?? '',
    'email'                => $contract['external_client_email'] ?? '',
    'phone'                => $contract['external_client_phone'] ?? '',
    'passport'             => $contract['external_client_passport'] ?? '',
];

if ($student && !$isSigned) {
    if ($displayClient['name'] === '') {
        $displayClient['name'] = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
    }
    if ($displayClient['email'] === '') {
        $displayClient['email'] = $student['email'] ?? '';
    }
    if ($displayClient['phone'] === '') {
        $displayClient['phone'] = $student['phone_number'] ?? '';
    }
    if ($displayClient['passport'] === '') {
        $displayClient['passport'] = $student['passport_number'] ?? '';
    }
}

$agreementDate = $contract['agreement_date'] ?? date('Y-m-d');
$today = date('Y-m-d');
$ro = $isSigned ? 'readonly' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>South Korea Event Attendance Service Agreement | ScholarSync Global</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root {
  --primary: #427431;
  --primary-dark: #2d5a27;
  --primary-light: #5a9448;
  --ink: #1a2e1a;
  --muted: #4b5563;
  --border: #d4e4d4;
  --soft: #f0f7f0;
  --paper: #ffffff;
  --success: #15803d;
  --link: #427431;
  --radius-sm: 8px;
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
  box-shadow: 0 20px 60px rgba(66, 116, 49, 0.15);
  overflow: hidden;
  border-top: 5px solid var(--primary);
}

.contract-header {
  background: linear-gradient(135deg, var(--primary-dark), var(--primary));
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
  line-height: 1.4;
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
  text-transform: uppercase;
  letter-spacing: 0.3px;
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

.form-row input[readonly], .form-row textarea[readonly] { background: #f3f4f6; }

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

.fee-table-wrap { overflow-x: auto; margin: 16px 0; }

.fee-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  min-width: 480px;
}

.fee-table th, .fee-table td {
  border: 1px solid var(--border);
  padding: 12px 14px;
  vertical-align: top;
  text-align: left;
}

.fee-table th {
  background: var(--soft);
  color: var(--primary);
  font-weight: 700;
}

.fee-table .fee-amount {
  font-weight: 700;
  color: var(--primary);
  white-space: nowrap;
}

.fee-table ul {
  margin: 8px 0 0;
  padding-left: 18px;
}

.signature-canvas {
  width: 100%;
  height: 140px;
  border: none;
  background: #ffffff;
  cursor: crosshair;
  touch-action: none;
}

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

button {
  font-family: system-ui, sans-serif;
  font-size: 14px;
  font-weight: 600;
  padding: 10px 18px;
  border-radius: var(--radius-sm);
  border: none;
  cursor: pointer;
}

#clearSignature { background: #f3f4f6; color: var(--ink); }
#signContract { background: var(--primary); color: #ffffff; }
#signContract:disabled { background: #9ca3af; cursor: not-allowed; }

.footer-ref {
  text-align: center;
  margin-top: 28px;
  font-size: 11px;
  color: #9ca3af;
}
</style>
</head>
<body<?= $isSigned ? ' class="contract-signed"' : '' ?>>

<?php if ($isSigned): ?>
<div class="signed-banner">
  <h2>✓ Contract Signed Successfully</h2>
  <p>Your South Korea Event Attendance Service Agreement has been recorded.</p>
  <div class="signed-actions">
    <a class="btn-dl" href="download-korea-invitation-contract.php?token=<?= urlencode($token) ?>">Download Signed PDF</a>
    <a class="btn-view" href="download-korea-invitation-contract.php?token=<?= urlencode($token) ?>&amp;inline=1" target="_blank" rel="noopener">View PDF</a>
  </div>
</div>
<?php endif; ?>

<div class="contract-wrap">
  <div class="contract-header">
    <div class="badge">🇰🇷 SOUTH KOREA EVENT ATTENDANCE</div>
    <h1>SCHOLARSYNC GLOBAL CO. LTD.</h1>
    <h2>SOUTH KOREA EVENT ATTENDANCE<br>SERVICE AGREEMENT</h2>
  </div>

  <div class="contract-body">

    <?php if (!$isSigned): ?>
    <div class="notice">
      Please read this Agreement carefully. By signing electronically below, you acknowledge that you fully understand and voluntarily accept all terms and conditions contained herein.
    </div>
    <?php endif; ?>

    <p>This Agreement is made between ScholarSync Global Co. Ltd. ("the Company") and the Client named below. The purpose is to clearly explain the services, payments, and cooperation required for the Client's proposed attendance at an event in South Korea.</p>

    <div class="client-form">
      <h3>Client Details</h3>

      <div class="form-row">
        <label for="agreement_date">Agreement Date</label>
        <input type="date" id="agreement_date" required
               value="<?= htmlspecialchars($agreementDate ?: $today) ?>" <?= $ro ?>>
      </div>

      <div class="form-row">
        <label for="client_name">Client's Full Legal Name</label>
        <input type="text" id="client_name" required autocomplete="name"
               value="<?= htmlspecialchars($displayClient['name']) ?>"
               placeholder="Full legal name" <?= $ro ?>>
      </div>

      <div class="form-row">
        <label for="client_passport">Passport/ID Number</label>
        <input type="text" id="client_passport" required autocomplete="off"
               value="<?= htmlspecialchars($displayClient['passport']) ?>"
               placeholder="Passport or ID number" <?= $ro ?>>
      </div>

      <div class="form-row">
        <label for="client_phone">Telephone</label>
        <input type="tel" id="client_phone" required autocomplete="tel"
               value="<?= htmlspecialchars($displayClient['phone']) ?>"
               placeholder="Phone number" <?= $ro ?>>
      </div>

      <div class="form-row">
        <label for="client_email">Email</label>
        <input type="email" id="client_email" required autocomplete="email"
               value="<?= htmlspecialchars($displayClient['email']) ?>"
               placeholder="Email address" <?= $ro ?>>
      </div>
    </div>

    <div class="section-title">1. SERVICES PROVIDED</div>
    <p>ScholarSync Global Co. Ltd. will:</p>
    <ul class="contract-list">
      <li>Assist the Client with the event invitation-letter process.</li>
      <li>Follow up on and assist with the preparation, collection, review, organization, and completion of documents required for the South Korean visa application.</li>
      <li>Provide general administrative guidance throughout the visa-application process.</li>
      <li>Inform the Client about additional document requests received from the event organizer or another relevant party.</li>
      <li>Arrange airport reception in South Korea after visa approval and full payment.</li>
      <li>Provide basic arrival orientation and reasonable initial settlement assistance.</li>
    </ul>
    <p>ScholarSync Global Co. Ltd. will professionally support the Client throughout the contracted process and communicate important updates in a timely manner.</p>

    <div class="section-title">2. CONTRACT PRICE AND PAYMENT</div>
    <p>The total service fee is:</p>
    <div class="total-fee">Two Thousand United States Dollars (USD $2,000)</div>
    <p>The fee shall be paid in two (2) installments as follows:</p>

    <div class="fee-table-wrap">
      <table class="fee-table">
        <thead>
          <tr>
            <th style="width:24%;">Installment</th>
            <th style="width:18%;">Amount</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Installment 1</strong><br>Upon Signing</td>
            <td class="fee-amount">USD $500</td>
            <td>
              This installment covers:
              <ul>
                <li>Invitation-letter processing and follow-up; and</li>
                <li>Assistance with collecting, reviewing, organizing, and completing visa-application documents.</li>
              </ul>
            </td>
          </tr>
          <tr>
            <td><strong>Installment 2</strong><br>After Visa Approval</td>
            <td class="fee-amount">USD $1,500</td>
            <td>
              The Client will pay the remaining USD $1,500 after visa approval and before travelling to South Korea.<br><br>
              This installment covers:
              <ul>
                <li>The remaining service fees;</li>
                <li>Airport reception in South Korea; and</li>
                <li>Basic arrival orientation and initial settlement assistance.</li>
              </ul>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p>Payments must be made through a payment method officially approved by ScholarSync Global Co. Ltd. The Client will receive an official receipt for each payment.</p>

    <div class="section-title">3. OTHER EXPENSES</div>
    <p>Unless ScholarSync Global Co. Ltd. confirms otherwise in writing, the service fee does not include airline tickets, government or visa fees, event registration, accommodation, meals, insurance, medical examinations, police certificates, translation, notarization, legalization, courier charges, or other personal expenses.</p>
    <p>ScholarSync Global Co. Ltd. may assist the Client with information or coordination relating to these items when reasonably possible.</p>

    <div class="section-title">4. REFUND POLICY</div>
    <ul class="contract-list">
      <li>The USD $500 Installment 1 payment is refundable if ScholarSync Global Co. Ltd. is unable to obtain the event invitation letter for the Client.</li>
      <li>Any third-party charge deducted from a refund must have been explained to and approved by the Client before it was incurred. ScholarSync Global Co. Ltd. will provide available supporting information about the charge upon request.</li>
      <li>Once the invitation letter has been issued, the USD $500 covers services already completed and is therefore non-refundable.</li>
      <li>The USD $1,500 Installment 2 becomes payable after visa approval. If a paid service cannot be provided by ScholarSync Global Co. Ltd., the Company and Client will first try to reschedule, replace, or fairly refund the unprovided part of that service.</li>
    </ul>

    <div class="section-title">5. CLIENT COOPERATION</div>
    <p>To help ScholarSync Global Co. Ltd. provide the best possible service, the Client agrees to:</p>
    <ul class="contract-list">
      <li>Provide genuine, accurate, complete, and timely documents and information.</li>
      <li>Review forms and documents and promptly report any error.</li>
      <li>Attend required appointments and interviews.</li>
      <li>Pay agreed fees and personal expenses on time.</li>
      <li>Inform the Company about previous visa refusals or other important facts affecting the application.</li>
      <li>Use the invitation letter and visa for the stated lawful purpose.</li>
      <li>Respect the immigration laws and authorized period of stay in South Korea.</li>
      <li>Provide confirmed arrival information in advance for airport reception.</li>
    </ul>
    <p>ScholarSync Global Co. Ltd. will give the Client reasonable reminders and an opportunity to provide missing information whenever circumstances allow.</p>

    <div class="section-title">6. IMPORTANT NOTICE</div>
    <p>ScholarSync Global Co. Ltd. will professionally assist the Client throughout the invitation-letter and visa-application process. However, final invitation-letter decisions are made by the event organizer, and final visa and entry decisions are made by the relevant South Korean authorities. The Company cannot guarantee the outcome of an application.</p>
    <p>If requirements, fees, appointments, or processing times change, ScholarSync Global Co. Ltd. will provide reasonable guidance and help the Client adapt where possible.</p>

    <div class="section-title">7. AIRPORT RECEPTION AND ARRIVAL SUPPORT</div>
    <p>After visa approval, payment of the outstanding balance, and receipt of the confirmed arrival details, a ScholarSync staff member or authorized representative will receive the Client at the agreed airport in South Korea.</p>
    <p>Arrival support may include basic orientation, communication assistance, and reasonable help reaching accommodation arranged by or for the Client. Any additional service requested by the Client may be agreed separately.</p>

    <div class="section-title">8. PERSONAL INFORMATION AND COMMUNICATION</div>
    <ul class="contract-list">
      <li>The Client authorizes ScholarSync Global Co. Ltd. to collect, use, store, and share information and documents reasonably required to provide the contracted services and communicate with relevant event organizers, visa service providers, and authorities.</li>
      <li>ScholarSync Global Co. Ltd. will take reasonable steps to protect the Client's information and use it only for legitimate service, communication, and legal purposes.</li>
      <li>The Client agrees to receive updates through telephone, email, SMS, WhatsApp, ScholarSync MIS, or another contact method provided or approved by the Client.</li>
    </ul>

    <div class="section-title">9. FAIR RESOLUTION AND GENERAL CONDITIONS</div>
    <ul class="contract-list">
      <li>Both parties agree to communicate respectfully and try in good faith to resolve any concern through written communication and management review.</li>
      <li>Any change to this Agreement must be accepted by both parties in writing. If any part of the Agreement cannot legally be enforced, the remaining parts will continue to apply. Electronic signatures may be used where legally permitted.</li>
      <li>This Agreement shall be governed by the laws of Rwanda.</li>
    </ul>

    <div class="section-title">CLIENT ACKNOWLEDGMENT</div>
    <p>By signing, the Client confirms that the Client:</p>
    <ul class="contract-list">
      <li>Has read and understood this Agreement.</li>
      <li>Accepts the services, payment schedule, and refund policy.</li>
      <li>Confirms that submitted information and documents will be genuine and accurate.</li>
      <li>Understands that final invitation-letter, visa, and entry decisions are made by independent event organizers and South Korean authorities.</li>
      <li>Authorizes ScholarSync Global Co. Ltd. to provide the services described in this Agreement.</li>
      <li>Has received or will receive a copy of the signed Agreement.</li>
    </ul>

    <!-- SIGNATURES - TWO COLUMNS -->
    <div class="contract-section signature-section" style="margin-top:40px;">

      <h3 style="font-size:20px;font-weight:700;margin-bottom:32px;color:var(--primary);">
        SIGNATURES
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

          <div style="margin-bottom:28px;">
            <strong>Date:</strong>
            <div style="border-bottom:1px solid #000;height:24px;width:85%;margin-top:6px;padding-top:2px;" id="scholarsync_date">
              <?= htmlspecialchars($agreementDate ? date('Y/m/d', strtotime($agreementDate)) : date('Y/m/d')) ?>
            </div>
          </div>

          <p style="font-weight:700;margin-bottom:18px;font-size:15px;border-top:1px solid #e5e7eb;padding-top:20px;">
            For ScholarSync Global Co. Ltd — Authorized Office Representative
          </p>

          <div style="margin-bottom:18px;">
            <strong>Office:</strong>
            <div style="border-bottom:1px solid #000;min-height:24px;width:85%;margin-top:6px;padding-top:2px;">
              Kigali Office / Musanze Office
            </div>
          </div>

          <div style="margin-bottom:18px;">
            <strong>Full Name:</strong>
            <div style="border-bottom:1px solid #000;min-height:28px;width:85%;margin-top:6px;">&nbsp;</div>
          </div>

          <div style="margin-bottom:18px;">
            <strong>Position:</strong>
            <div style="border-bottom:1px solid #000;min-height:28px;width:85%;margin-top:6px;">&nbsp;</div>
          </div>

          <div style="margin-bottom:18px;">
            <strong>Signature:</strong>
            <div style="border-bottom:1px solid #000;min-height:70px;width:85%;margin-top:6px;">&nbsp;</div>
          </div>

          <div style="margin-bottom:18px;">
            <strong>Date:</strong>
            <div style="border-bottom:1px solid #000;min-height:28px;width:85%;margin-top:6px;">&nbsp;</div>
          </div>
        </div>

        <!-- RIGHT: Client details + e-sign -->
        <div class="signature-block" style="
          padding:20px;
          background:#f9fafb;
          border-radius:12px;
          border:1px solid #e5e7eb;
        ">

          <p style="font-weight:700;margin-bottom:20px;font-size:16px;">
            For the Client
          </p>

          <div style="margin-bottom:20px;">
            <label style="display:block;margin-bottom:8px;font-weight:600;color:#374151;">Full Name:</label>
            <input type="text" id="sig_client_name"
              style="
                width:100%;
                border:2px solid #e5e7eb;
                border-radius:6px;
                padding:12px 16px;
                font-size:16px;
                box-sizing:border-box;
                transition:border-color 0.2s ease;
              "
              placeholder="Enter your full legal name"
              value="<?= htmlspecialchars($isSigned ? $signedClientName : $displayClient['name']) ?>"
              <?= $ro ?>>
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

              <?php if ($isSigned && !empty($clientSignatureData)): ?>
              <img src="<?= $clientSignatureData ?>" style="max-height:130px;" alt="Client Signature">
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
              "
              value="<?= htmlspecialchars($isSigned ? $signedDate : '') ?>"
              <?= $ro ?>>
          </div>

          <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <button id="clearSignature" type="button" style="flex:1;background:#f3f4f6;color:#374151;border:none;">Clear Signature</button>
            <button id="signContract" type="button" style="flex:2;background:#427431;color:#ffffff;border:none;">Sign &amp; Submit Contract</button>
            <input type="hidden" id="signatureData">
          </div>

        </div>

      </div>

    </div>

    <div class="footer-ref">
      Contract Reference: <?= htmlspecialchars($contract['contract_token']) ?>
    </div>

  </div>
</div>

<script>
const CONTRACT_TOKEN = "<?= htmlspecialchars($token) ?>";

<?php if (!$isSigned): ?>
(() => {
  const canvas = document.querySelector('.signature-canvas');
  if (!canvas) {
    return;
  }
  const ctx = canvas.getContext('2d');

  const btnClear = document.getElementById('clearSignature');
  const btnSubmit = document.getElementById('signContract');

  const inputName = document.getElementById('sig_client_name');
  const inputDate = document.getElementById('sig_signed_date');
  const hiddenSignature = document.getElementById('signatureData');

  const mainClientName = document.getElementById('client_name');
  const todayIso = new Date().toISOString().slice(0, 10);
  if (inputDate && !inputDate.value) inputDate.value = todayIso;
  if (mainClientName && inputName && !inputName.value) inputName.value = (mainClientName.value || '').trim();
  if (mainClientName && inputName) {
    mainClientName.addEventListener('input', () => {
      inputName.value = (mainClientName.value || '').trim();
    });
  }

  let drawing = false;
  let points = [];

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

    if (points.length < 3) {
      ctx.lineTo(pos.x, pos.y);
      ctx.stroke();
      return;
    }

    const p0 = points[points.length - 3];
    const p1 = points[points.length - 2];
    const p2 = points[points.length - 1];

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

  canvas.addEventListener('mousedown', startDraw);
  canvas.addEventListener('mousemove', draw);
  canvas.addEventListener('mouseup', stopDraw);
  canvas.addEventListener('mouseleave', stopDraw);

  canvas.addEventListener('touchstart', startDraw, { passive: false });
  canvas.addEventListener('touchmove', draw, { passive: false });
  canvas.addEventListener('touchend', stopDraw);

  btnClear.addEventListener('click', () => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
  });

  function hasSignature() {
    const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
    return pixels.some(channel => channel !== 0);
  }

  btnSubmit.addEventListener('click', () => {
    if (!inputName || !inputDate || !canvas) {
      alert('Required signature fields are missing. Please reload the page.');
      return;
    }

    const clientName = (document.getElementById('client_name')?.value || '').trim();
    const clientEmail = (document.getElementById('client_email')?.value || '').trim();
    const clientPhone = (document.getElementById('client_phone')?.value || '').trim();
    const clientPassport = (document.getElementById('client_passport')?.value || '').trim();
    const agreementDate = (document.getElementById('agreement_date')?.value || '').trim();
    const sigName = inputName.value.trim();
    const signedDate = inputDate.value;

    if (!clientName || !clientEmail || !clientPhone || !clientPassport || !agreementDate) {
      alert('Please complete all client details before signing.');
      return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(clientEmail)) {
      alert('Please enter a valid email address.');
      document.getElementById('client_email')?.focus();
      return;
    }

    if (!sigName) {
      alert('Please enter your full name before signing.');
      inputName.focus();
      return;
    }

    if (!signedDate) {
      alert('Please select the signing date.');
      inputDate.focus();
      return;
    }

    if (!hasSignature()) {
      alert('Please draw your signature before submitting.');
      return;
    }

    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Submitting...';
    btnSubmit.style.background = '#6b7280';

    const signature = canvas.toDataURL('image/png');
    hiddenSignature.value = signature;

    const payload = {
      token: CONTRACT_TOKEN,
      client_name: sigName,
      client_email: clientEmail,
      client_phone: clientPhone,
      client_passport: clientPassport,
      agreement_date: agreementDate,
      signed_date: signedDate,
      signature: signature
    };

    fetch('submit-korea-invitation-signature.php', {
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
      btnSubmit.style.background = '#427431';
      alert(data.error || 'Submission failed.');
    })
    .catch(() => {
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Sign & Submit Contract';
      btnSubmit.style.background = '#427431';
      alert('Unable to submit. Please check your connection and try again.');
    });
  });
})();
<?php endif; ?>
</script>
</body>
</html>
