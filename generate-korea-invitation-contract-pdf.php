<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/helpers/contract_signature_image.php';
require_once __DIR__ . '/helpers/korea_invitation_contract_schema.php';

function kicPdfEsc(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function kicPdfFormatDate(?string $date): string
{
    if ($date === null || trim($date) === '') {
        return '___________________________';
    }
    $ts = strtotime($date);
    return $ts ? date('F j, Y', $ts) : kicPdfEsc($date);
}

function kicPdfEmbedImage(string $absolutePath): string
{
    if (!is_file($absolutePath)) {
        throw new RuntimeException('Image not found: ' . basename($absolutePath));
    }
    $mime = mime_content_type($absolutePath) ?: 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($absolutePath));
}

function kicPdfFieldRow(string $label, string $value, string $fallback = ''): string
{
    $display = trim($value) !== '' ? kicPdfEsc($value) : kicPdfEsc($fallback);
    return '<div class="field-row">
        <span class="field-label">' . kicPdfEsc($label) . '</span>
        <span class="field-value">' . $display . '</span>
    </div>';
}

function kicPdfClientSignatureSrc(int $contractId, string $clientSignature): string
{
    $clientSignatureFile = contract_signature_save_standard_png($contractId, $clientSignature);
    if ($clientSignatureFile !== null && is_file($clientSignatureFile)) {
        return str_replace('\\', '/', $clientSignatureFile);
    }

    $normalizedPng = contract_signature_to_display_png($clientSignature);
    if ($normalizedPng !== null) {
        return 'data:image/png;base64,' . base64_encode($normalizedPng);
    }

    return $clientSignature;
}

function generateKoreaInvitationContractPDF(int $contractId): string
{
    global $conn;

    kic_contract_ensure_schema($conn);

    $stmt = $conn->prepare("
        SELECT
            c.contract_token,
            c.agreement_date,
            c.external_client_name,
            c.external_client_email,
            c.external_client_phone,
            c.external_client_passport,
            sig.client_name,
            sig.client_email,
            sig.client_passport,
            sig.signed_date,
            sig.signature_image
        FROM korea_invitation_contracts c
        INNER JOIN korea_invitation_signatures sig ON sig.contract_id = c.id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $contractId);
    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to load contract data: ' . $stmt->error);
    }
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$data) {
        throw new RuntimeException('Contract not found.');
    }

    if (empty($data['signature_image']) || !str_starts_with($data['signature_image'], 'data:image')) {
        throw new RuntimeException('Invalid client signature.');
    }

    $clientName           = $data['client_name'] ?: ($data['external_client_name'] ?? '');
    $clientEmail          = $data['client_email'] ?: ($data['external_client_email'] ?? '');
    $clientPassport       = $data['client_passport'] ?: ($data['external_client_passport'] ?? '');
    $clientPhone          = $data['external_client_phone'] ?? '';
    $agreementDate        = $data['agreement_date'] ?: ($data['signed_date'] ?? '');
    $signedDate           = $data['signed_date'] ?? '';

    $clientSigSrc = kicPdfClientSignatureSrc($contractId, $data['signature_image']);

    $managerSigPath = __DIR__ . '/admin/signature-manager.png';
    $companyStampPath = __DIR__ . '/admin/employer-signature.png';
    $managerSignature = kicPdfEmbedImage($managerSigPath);
    $companyStamp = kicPdfEmbedImage($companyStampPath);

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page {
    size: A4;
    margin: 2.54cm 2.54cm 2.54cm 2.54cm;
}

body {
    font-family: "Times New Roman", Times, serif;
    font-size: 12pt;
    line-height: 1.6;
    color: #000;
    margin: 0;
    padding: 0;
}

.doc-title {
    text-align: center;
    font-size: 14pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin: 0 0 4pt;
}

.doc-subtitle {
    text-align: center;
    font-size: 13pt;
    font-weight: bold;
    text-transform: uppercase;
    margin: 0 0 18pt;
    color: #1a1a1a;
}

p { margin: 0 0 10pt; text-align: justify; }

.section-title {
    font-size: 12pt;
    font-weight: bold;
    margin: 18pt 0 8pt;
}

.client-section-title {
    font-weight: bold;
    margin: 14pt 0 10pt;
    font-size: 12pt;
}

.field-row {
    margin: 0 0 8pt;
    line-height: 1.8;
}

.field-label {
    font-weight: normal;
}

.field-value {
    display: inline-block;
    min-width: 68%;
    border-bottom: 1px solid #000;
    padding: 0 2pt 2pt 6pt;
}

.party-block { margin: 12pt 0; }

ul.contract-list {
    margin: 6pt 0 12pt 0;
    padding-left: 22pt;
}

ul.contract-list li {
    margin-bottom: 5pt;
    text-align: justify;
}

.fee-total {
    text-align: center;
    font-weight: bold;
    font-size: 12pt;
    margin: 12pt 0;
}

.fee-table {
    width: 100%;
    border-collapse: collapse;
    margin: 10pt 0 14pt;
    font-size: 11pt;
}

.fee-table th,
.fee-table td {
    border: 1px solid #000;
    padding: 8pt 10pt;
    vertical-align: top;
    text-align: left;
}

.fee-table th {
    font-weight: bold;
    background: #f5f5f5;
}

.fee-amount {
    font-weight: bold;
    white-space: nowrap;
}

.fee-table ul {
    margin: 6pt 0 0 0;
    padding-left: 16pt;
}

.sig-section {
    margin-top: 28pt;
    page-break-inside: avoid;
}

.sig-heading {
    font-weight: bold;
    text-transform: uppercase;
    margin: 0 0 12pt;
    font-size: 12pt;
}

.sig-name {
    font-weight: bold;
    margin: 0 0 2pt;
}

.sig-role {
    margin: 0 0 14pt;
}

.sig-field {
    margin: 0 0 12pt;
}

.sig-field-label {
    display: block;
    margin-bottom: 4pt;
}

.sig-line-box {
    border-bottom: 1px solid #000;
    min-height: 42pt;
    width: 72%;
    padding-top: 2pt;
}

.sig-line-box.sig-blank {
    min-height: 22pt;
}

.sig-line-box.sig-sign-space {
    min-height: 56pt;
}

.office-rep-section {
    margin-top: 20pt;
    page-break-inside: avoid;
}

.sig-line-box img {
    max-height: 40pt;
    max-width: 220pt;
}

.stamp-box {
    min-height: 70pt;
    width: 72%;
    padding-top: 4pt;
}

.stamp-box img {
    max-height: 68pt;
    max-width: 180pt;
}

.client-sig-block {
    margin-top: 36pt;
    page-break-inside: avoid;
}

.footer-ref {
    margin-top: 24pt;
    text-align: center;
    font-size: 9pt;
    color: #444;
    border-top: 1px solid #ccc;
    padding-top: 8pt;
}
</style>
</head>
<body>

<div class="doc-title">SCHOLARSYNC GLOBAL CO. LTD.</div>
<div class="doc-subtitle">SOUTH KOREA EVENT ATTENDANCE<br>SERVICE AGREEMENT</div>

<p>This Agreement is made between ScholarSync Global Co. Ltd. ("the Company") and the Client named below. The purpose is to clearly explain the services, payments, and cooperation required for the Client's proposed attendance at an event in South Korea.</p>

<div class="client-section-title">CLIENT DETAILS</div>

<?= kicPdfFieldRow('Agreement Date:', kicPdfFormatDate($agreementDate), '_______________________________________________') ?>
<?= kicPdfFieldRow('Client\'s Full Legal Name:', $clientName, '_______________________________________________') ?>
<?= kicPdfFieldRow('Passport/ID Number:', $clientPassport, '_______________________________________________') ?>
<?= kicPdfFieldRow('Telephone:', $clientPhone, '_______________________________________________') ?>
<?= kicPdfFieldRow('Email:', $clientEmail, '_______________________________________________') ?>

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
<p class="fee-total">Two Thousand United States Dollars (USD $2,000)</p>
<p>The fee shall be paid in two (2) installments as follows:</p>

<table class="fee-table">
<tr>
<th style="width:22%;">Installment</th>
<th style="width:18%;">Amount</th>
<th>Details</th>
</tr>
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
</table>

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

<!-- Company signature block -->
<div class="sig-section">
<div class="sig-heading">SIGNED FOR SCHOLARSYNC GLOBAL CO. LTD.</div>
<p class="sig-name">Dr. Jean Pierre Twajamahoro</p>
<p class="sig-role">Managing Director</p>

<div class="sig-field">
<span class="sig-field-label">Signature:</span>
<div class="sig-line-box">
<img src="<?= $managerSignature ?>" alt="Managing Director Signature">
</div>
</div>

<div class="sig-field">
<span class="sig-field-label">Date:</span>
<div class="sig-line-box" style="min-height:24pt;line-height:24pt;padding-top:6pt;">
<?= kicPdfFormatDate($agreementDate) ?>
</div>
</div>

<div class="sig-field">
<span class="sig-field-label">Company Stamp:</span>
<div class="stamp-box">
<img src="<?= $companyStamp ?>" alt="Company Stamp">
</div>
</div>
</div>

<!-- Authorized office representative signing block -->
<div class="sig-section office-rep-section">
<div class="sig-heading">FOR SCHOLARSYNC GLOBAL CO. LTD. — AUTHORIZED OFFICE REPRESENTATIVE</div>

<div class="sig-field">
<span class="sig-field-label">Office:</span>
<div class="sig-line-box" style="min-height:24pt;line-height:24pt;padding-top:6pt;">
Kigali Office / Musanze Office
</div>
</div>

<div class="sig-field">
<span class="sig-field-label">Full Name:</span>
<div class="sig-line-box sig-blank">&nbsp;</div>
</div>

<div class="sig-field">
<span class="sig-field-label">Position:</span>
<div class="sig-line-box sig-blank">&nbsp;</div>
</div>

<div class="sig-field">
<span class="sig-field-label">Signature:</span>
<div class="sig-line-box sig-sign-space">&nbsp;</div>
</div>

<div class="sig-field">
<span class="sig-field-label">Date:</span>
<div class="sig-line-box sig-blank">&nbsp;</div>
</div>
</div>

<!-- Client signature block -->
<div class="client-sig-block">
<div class="sig-heading">SIGNED BY THE CLIENT</div>

<div class="sig-field">
<span class="sig-field-label">Full Legal Name:</span>
<div class="sig-line-box" style="min-height:24pt;line-height:24pt;padding-top:6pt;">
<?= kicPdfEsc($clientName) ?>
</div>
</div>

<div class="sig-field">
<span class="sig-field-label">Passport/ID Number:</span>
<div class="sig-line-box" style="min-height:24pt;line-height:24pt;padding-top:6pt;">
<?= kicPdfEsc($clientPassport !== '' ? $clientPassport : '_______________________________________________') ?>
</div>
</div>

<div class="sig-field">
<span class="sig-field-label">Signature:</span>
<div class="sig-line-box">
<img src="<?= is_string($clientSigSrc) && str_starts_with($clientSigSrc, 'data:') ? $clientSigSrc : str_replace('\\', '/', (string) $clientSigSrc) ?>" alt="Client Signature">
</div>
</div>

<div class="sig-field">
<span class="sig-field-label">Date:</span>
<div class="sig-line-box" style="min-height:24pt;line-height:24pt;padding-top:6pt;">
<?= kicPdfFormatDate($signedDate) ?>
</div>
</div>
</div>

<div class="footer-ref">
Contract Reference: <?= kicPdfEsc($data['contract_token']) ?>
</div>

</body>
</html>
    <?php
    $html = ob_get_clean();

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'Times New Roman');
    $options->set('chroot', __DIR__);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $dir = __DIR__ . '/uploads/korea_invitation_contracts';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filePath = $dir . '/korea_invitation_contract_' . $contractId . '.pdf';
    file_put_contents($filePath, $dompdf->output());

    return $filePath;
}
