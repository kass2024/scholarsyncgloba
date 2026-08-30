<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/helpers/contract_signature_image.php';

function fmEsc(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function fmFormatDate(?string $date): string
{
    if ($date === null || trim($date) === '') {
        return '___________________________';
    }
    $ts = strtotime($date);
    return $ts ? date('F j, Y', $ts) : fmEsc($date);
}

function fmFormatDateShort(?string $date): string
{
    if ($date === null || trim($date) === '') {
        return '___________________________';
    }
    $ts = strtotime($date);
    return $ts ? date('m/d/Y', $ts) : fmEsc($date);
}

function fmPdfEmbedImage(string $absolutePath): string
{
    if (!is_file($absolutePath)) {
        throw new RuntimeException('Image not found: ' . basename($absolutePath));
    }
    $mime = mime_content_type($absolutePath) ?: 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($absolutePath));
}

function fmPdfFieldRow(string $label, string $value, string $fallback = ''): string
{
    $display = trim($value) !== '' ? fmEsc($value) : fmEsc($fallback);
    return '<div class="field-row">
        <span class="field-label">' . fmEsc($label) . '</span>
        <span class="field-value">' . $display . '</span>
    </div>';
}

function generateFmContractPDF(int $contractId): string
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT
            c.contract_token,
            c.agreement_date,
            c.external_client_name,
            c.external_client_email,
            c.external_client_phone,
            c.external_client_dob,
            c.external_client_nationality,
            c.external_client_passport,
            c.external_client_address,
            c.application_id,
            sig.client_name,
            sig.client_email,
            sig.client_passport,
            sig.signed_date,
            sig.signature_image,
            a.reference_id
        FROM fm_mobility_contracts c
        INNER JOIN fm_mobility_signatures sig ON sig.contract_id = c.id
        LEFT JOIN francophonie_mobility_applications a ON a.id = c.application_id
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

    $clientName        = $data['client_name'] ?: ($data['external_client_name'] ?? '');
    $clientEmail       = $data['client_email'] ?: ($data['external_client_email'] ?? '');
    $clientPassport    = $data['client_passport'] ?: ($data['external_client_passport'] ?? '');
    $clientNationality = $data['external_client_nationality'] ?? '';
    $clientDob         = $data['external_client_dob'] ?? '';
    $clientPhone       = $data['external_client_phone'] ?? '';
    $clientAddress     = $data['external_client_address'] ?? '';
    $agreementDate     = $data['agreement_date'] ?: ($data['signed_date'] ?? '');
    $signedDate        = $data['signed_date'] ?? '';
    $referenceId       = $data['reference_id'] ?? '';

    $clientSignature = $data['signature_image'];
    $clientSignatureFile = contract_signature_save_standard_png($contractId, $clientSignature);
    if ($clientSignatureFile !== null && is_file($clientSignatureFile)) {
        $clientSigSrc = str_replace('\\', '/', $clientSignatureFile);
    } else {
        $normalizedPng = contract_signature_to_display_png($clientSignature);
        $clientSigSrc = $normalizedPng !== null
            ? 'data:image/png;base64,' . base64_encode($normalizedPng)
            : $clientSignature;
    }

    $managerSigPath = __DIR__ . '/admin/signature-manager.png';
    $companyStampPath = __DIR__ . '/admin/employer-signature.png';
    $managerSignature = fmPdfEmbedImage($managerSigPath);
    $companyStamp = fmPdfEmbedImage($companyStampPath);

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
<div class="doc-subtitle">FRANCOPHONE MOBILITY (MOBILITÉ FRANCOPHONE)<br>SERVICE AGREEMENT</div>

<p><strong>Agreement Date:</strong> <?= fmFormatDate($agreementDate) ?></p>

<p>This Service Agreement ("Agreement") is entered into on the date indicated above between:</p>

<div class="party-block">
<p><strong>SCHOLARSYNC GLOBAL CO. LTD.</strong><br>
Represented by: Dr. Jean Pierre Twajamahoro, Managing Director<br>
(Hereinafter referred to as "the Company")</p>
</div>

<p><strong>AND</strong></p>

<div class="client-section-title">CLIENT INFORMATION</div>

<?= fmPdfFieldRow('Full Name:', $clientName, '_______________________________________') ?>
<?= fmPdfFieldRow('Passport Number:', $clientPassport, '_______________________________') ?>
<?= fmPdfFieldRow('Nationality:', $clientNationality, '______________________________________') ?>
<?= fmPdfFieldRow('Date of Birth:', $clientDob !== '' ? fmFormatDateShort($clientDob) : '', '__________________________________') ?>
<?= fmPdfFieldRow('Address:', $clientAddress, '_______________________________________') ?>
<?= fmPdfFieldRow('Telephone:', $clientPhone, '_____________________________________') ?>
<?= fmPdfFieldRow('Email:', $clientEmail, '_________________________________________') ?>
<?php if ($referenceId !== ''): ?>
<?= fmPdfFieldRow('Application Ref:', $referenceId) ?>
<?php endif; ?>

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
<p class="fee-total">Eight Thousand Canadian Dollars (CAD $8,000)</p>
<p>The fee shall be paid in three (3) installments as follows:</p>

<table class="fee-table">
<tr>
<th style="width:22%;">Installment</th>
<th style="width:18%;">Amount</th>
<th>Details</th>
</tr>
<tr>
<td><strong>First Installment</strong></td>
<td class="fee-amount">CAD $850</td>
<td>
Payable before the commencement of the recruitment process.<br><br>
This payment covers:
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
</td>
</tr>
<tr>
<td><strong>Second Installment</strong></td>
<td class="fee-amount">CAD $3,150</td>
<td>
Payable immediately once the Canadian Embassy requests you to complete a medical examination.<br><br>
<strong>Note:</strong> In this case, the visa approval rate is over 98%<br><br>
This installment covers:
<ul>
<li>Work permit application flow up</li>
<li>Employer communication</li>
<li>Employment documentation</li>
<li>Employer support services</li>
</ul>
</td>
</tr>
<tr>
<td><strong>Third Installment</strong></td>
<td class="fee-amount">CAD $4,000</td>
<td>
<ul>
<li>Payable after visa approval by IRCC.</li>
<li>Must be paid before departure for Canada.</li>
<li>Airport pickup upon arrival in Canada.</li>
<li>Orientation and settlement support to help you become familiar with life in Canada.</li>
<li>Assistance in finding suitable rental accommodation.</li>
</ul>
</td>
</tr>
</table>

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

<!-- Company signature block (Word format) -->
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
<?= fmFormatDate($agreementDate) ?>
</div>
</div>

<div class="sig-field">
<span class="sig-field-label">Company Stamp:</span>
<div class="stamp-box">
<img src="<?= $companyStamp ?>" alt="Company Stamp">
</div>
</div>
</div>

<!-- Client signature block (Word format) -->
<div class="client-sig-block">
<div class="sig-heading">SIGNED BY THE CLIENT</div>

<div class="sig-field">
<span class="sig-field-label">Full Name:</span>
<div class="sig-line-box" style="min-height:24pt;line-height:24pt;padding-top:6pt;">
<?= fmEsc($clientName) ?>
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
<?= fmFormatDate($signedDate) ?>
</div>
</div>

<div class="sig-field">
<span class="sig-field-label">Passport Number:</span>
<div class="sig-line-box" style="min-height:24pt;line-height:24pt;padding-top:6pt;">
<?= fmEsc($clientPassport !== '' ? $clientPassport : '_______________________________') ?>
</div>
</div>
</div>

<div class="footer-ref">
Contract Reference: <?= fmEsc($data['contract_token']) ?>
<?php if ($referenceId !== ''): ?> | Application: <?= fmEsc($referenceId) ?><?php endif; ?>
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

    $dir = __DIR__ . '/uploads/fm_contracts';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filePath = $dir . '/fm_contract_' . $contractId . '.pdf';
    file_put_contents($filePath, $dompdf->output());

    return $filePath;
}
