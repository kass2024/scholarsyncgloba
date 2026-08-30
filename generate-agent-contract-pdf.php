<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/helpers/contract_signature_image.php';
require_once __DIR__ . '/helpers/agent_contract_schema.php';

function acPdfEsc(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function acPdfFormatDate(?string $date): string
{
    if ($date === null || trim($date) === '') {
        return '___________________________';
    }
    $ts = strtotime($date);
    return $ts ? date('F j, Y', $ts) : acPdfEsc($date);
}

function acPdfEmbedImage(string $absolutePath): string
{
    if (!is_file($absolutePath)) {
        throw new RuntimeException('Image not found: ' . basename($absolutePath));
    }
    $mime = mime_content_type($absolutePath) ?: 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($absolutePath));
}

function acPdfSaveSignature(int $contractId, string $clientSignature): string
{
    $dir = agent_contract_upload_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $png = contract_signature_to_display_png($clientSignature);
    if ($png === null) {
        $png = contract_signature_raw_bytes($clientSignature);
    }
    if ($png !== null && $png !== '') {
        $path = $dir . '/signature_' . $contractId . '.png';
        if (file_put_contents($path, $png) !== false) {
            return str_replace('\\', '/', $path);
        }
    }

    $normalizedPng = contract_signature_to_display_png($clientSignature);
    if ($normalizedPng !== null) {
        return 'data:image/png;base64,' . base64_encode($normalizedPng);
    }

    return $clientSignature;
}

function generateAgentContractPDF(int $contractId): string
{
    global $conn;

    agent_contract_ensure_schema($conn);

    $stmt = $conn->prepare("
        SELECT
            c.contract_token,
            c.effective_date,
            c.agent_name,
            c.agent_email,
            c.agent_phone,
            c.agent_address,
            c.agent_title,
            sig.agent_name AS sig_name,
            sig.agent_email AS sig_email,
            sig.agent_title AS sig_title,
            sig.signed_date,
            sig.signature_image
        FROM agent_contracts c
        INNER JOIN agent_signatures sig ON sig.contract_id = c.id
        WHERE c.id = ?
        ORDER BY sig.id DESC
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
        throw new RuntimeException('Invalid agent signature.');
    }

    $agentName    = $data['sig_name'] ?: ($data['agent_name'] ?? '');
    $agentEmail   = $data['sig_email'] ?: ($data['agent_email'] ?? '');
    $agentTitle   = $data['sig_title'] ?: ($data['agent_title'] ?? '');
    $agentAddress = $data['agent_address'] ?? '';
    $effectiveDate = $data['effective_date'] ?: ($data['signed_date'] ?? '');
    $signedDate   = $data['signed_date'] ?? '';

    $agentSigSrc = acPdfSaveSignature($contractId, $data['signature_image']);
    $managerSignature = acPdfEmbedImage(__DIR__ . '/admin/signature-manager.png');
    $companyStamp = acPdfEmbedImage(__DIR__ . '/admin/employer-signature.png');

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4; margin: 2.2cm 2.2cm 2.2cm 2.2cm; }
body { font-family: "Times New Roman", Times, serif; font-size: 11pt; line-height: 1.55; color: #000; margin: 0; }
.doc-title { text-align: center; font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 0 0 4pt; }
.doc-subtitle { text-align: center; font-size: 12pt; font-weight: bold; margin: 0 0 16pt; }
p { margin: 0 0 8pt; text-align: justify; }
.section-title { font-size: 11.5pt; font-weight: bold; margin: 14pt 0 6pt; }
ul { margin: 4pt 0 10pt; padding-left: 18pt; }
li { margin-bottom: 3pt; }
.fee-table { width: 100%; border-collapse: collapse; margin: 8pt 0 12pt; font-size: 10pt; }
.fee-table th, .fee-table td { border: 1px solid #333; padding: 5pt 7pt; text-align: left; }
.fee-table th { background: #f0f0f0; }
.sig-grid { width: 100%; margin-top: 18pt; }
.sig-col { width: 48%; vertical-align: top; display: inline-block; }
.sig-col.right { margin-left: 3%; }
.sig-heading { font-weight: bold; margin-bottom: 10pt; font-size: 11pt; }
.sig-field { margin-bottom: 10pt; }
.sig-field-label { font-weight: bold; display: block; margin-bottom: 3pt; }
.sig-line { border-bottom: 1px solid #000; min-height: 18pt; padding-top: 2pt; width: 90%; }
.sig-line img { max-height: 55pt; max-width: 200pt; }
.stamp-box img { max-height: 90pt; max-width: 160pt; }
.footer-ref { text-align: center; margin-top: 20pt; font-size: 9pt; color: #555; }
</style>
</head>
<body>

<div class="doc-title">Agent Referral and Commission Agreement</div>
<div class="doc-subtitle">ScholarSync Global Company Ltd.</div>

<p>This Agent Referral and Commission Agreement (the &ldquo;Agreement&rdquo;) is made effective as of
<strong><?= acPdfFormatDate($effectiveDate) ?></strong> between:</p>

<p><strong>Company:</strong> ScholarSync Global Company Ltd., with registered address at Gasanze Cell, Nduba Sector, Gasabo District, Kigali, Rwanda (the &ldquo;Company&rdquo;).<br>
Company email: infos@scholarsyncglobal.ca<br>
Company website: www.scholarsyncglobal.ca</p>

<p><strong>Agent:</strong> <?= acPdfEsc($agentName) ?>, of <?= acPdfEsc($agentAddress !== '' ? $agentAddress : '___________________________') ?> (the &ldquo;Agent&rdquo;).<br>
Agent email: <?= acPdfEsc($agentEmail) ?><?php if ($agentTitle !== ''): ?><br>Title: <?= acPdfEsc($agentTitle) ?><?php endif; ?></p>

<p>The Company and the Agent are each a &ldquo;Party&rdquo; and together the &ldquo;Parties.&rdquo;</p>

<div class="section-title">1. Purpose and Appointment</div>
<p>The Company appoints the Agent on a non-exclusive basis to identify and refer prospective students who may require admission, educational consulting, and/or visa-support services. The Agent accepts the appointment subject to this Agreement. Nothing in this Agreement grants the Agent authority to bind the Company, sign documents on the Company&rsquo;s behalf, make guarantees, collect money in the Company&rsquo;s name unless expressly authorized in writing, or represent that the Agent is an employee or legal representative of the Company.</p>

<div class="section-title">2. Term</div>
<p>This Agreement begins on the Effective Date and continues for an initial term of one year. It will renew automatically for successive one-year terms unless either Party gives written notice of non-renewal at least 30 days before the end of the current term, or the Agreement is terminated earlier under Section 15.</p>

<div class="section-title">3. Agent Responsibilities</div>
<p>The Agent shall: provide prospective students with accurate, current, and non-misleading information supplied or approved by the Company; refer only genuine applicants and conduct reasonable identity and document checks before submission; submit complete information and documents promptly and keep the Company informed of material developments; protect student information and obtain all consents required to share it with the Company, schools, government authorities, and service providers; avoid promises or guarantees of admission, scholarships, visas, processing times, employment, permanent residence, or any other outcome; avoid unauthorized immigration or legal advice and comply with all licensing requirements that apply to the Agent; use the Company&rsquo;s name, logo, materials, and pricing only as authorized in writing; and maintain professional conduct and comply with applicable anti-fraud, anti-bribery, sanctions, consumer-protection, privacy, advertising, education-recruitment, and immigration laws.</p>

<div class="section-title">4. Company Responsibilities</div>
<p>The Company shall: provide the Agent with current service information, approved marketing materials, document requirements, and fee information; review referred files and communicate material requirements or deficiencies within a reasonable time; maintain records sufficient to calculate fees and commissions under this Agreement; pay undisputed amounts owing to the Agent in accordance with this Agreement; and handle student applications with reasonable care, while recognizing that admission and visa decisions are made by independent institutions and government authorities.</p>

<div class="section-title">5. Referral Registration and Ownership</div>
<p>A referral is eligible only if the Agent identifies the student to the Company in writing before the student becomes an existing Company lead or client, and the Company confirms the referral. If more than one agent claims the same student, the Company&rsquo;s dated records and first confirmed referral will control, unless the Parties agree otherwise in writing. No commission is payable on an unconfirmed, duplicate, fraudulent, cancelled, or unpaid referral.</p>

<div class="section-title">6. Services and Benefits Fees</div>
<p>The applicable Benefits Fee depends on the service category selected by the student. All amounts are subject to applicable taxes and third-party charges, which are separate unless expressly stated otherwise.</p>
<p><strong>6.1 Full Admission and Visa Services</strong> (admission process through visa approval)</p>
<table class="fee-table">
<thead><tr><th>Region</th><th>Benefits Fee</th><th>Currency</th></tr></thead>
<tbody>
<tr><td>United States and Europe</td><td>$1,000</td><td>USD</td></tr>
<tr><td>Canada</td><td>$1,300</td><td>CAD</td></tr>
<tr><td>China</td><td>$800</td><td>USD</td></tr>
<tr><td>South Korea</td><td>$800</td><td>USD</td></tr>
</tbody>
</table>
<p><strong>6.2 Visa Services Only</strong> (student already has a valid admission letter)</p>
<table class="fee-table">
<thead><tr><th>Region</th><th>Benefits Fee</th><th>Currency</th></tr></thead>
<tbody>
<tr><td>United States and Europe</td><td>$500</td><td>USD</td></tr>
<tr><td>Canada</td><td>$650</td><td>CAD</td></tr>
<tr><td>China</td><td>$400</td><td>USD</td></tr>
<tr><td>South Korea</td><td>$400</td><td>USD</td></tr>
</tbody>
</table>

<div class="section-title">7. Benefits Fee Distribution</div>
<p>For every region and both service categories in Section 6, the Benefits Fee actually received and retained by the Company will be divided equally after authorized deductions, refunds, reversals, chargebacks, taxes collected, and unrecoverable payment-processing charges: Agent 50%; Company 50%.</p>

<div class="section-title">8. Additional Commission After Admission Letter Approval</div>
<p>In addition to the Agent&rsquo;s share under Section 7, and only after the student&rsquo;s admission letter has been approved and the applicable application fee has been paid in full and cleared, the Agent will receive:</p>
<table class="fee-table">
<thead><tr><th>Destination</th><th>Agent Rate</th><th>Commission Basis</th></tr></thead>
<tbody>
<tr><td>Canada</td><td>15%</td><td>Application fee paid for Canada</td></tr>
<tr><td>United States</td><td>50%</td><td>Application fee paid for the United States</td></tr>
<tr><td>South Korea</td><td>10%</td><td>Application fee paid for South Korea</td></tr>
<tr><td>China</td><td>10%</td><td>Application fee paid for China</td></tr>
<tr><td>Europe</td><td>10%</td><td>Application fee paid for Europe</td></tr>
</tbody>
</table>
<p>No additional commission becomes earned solely because an application was submitted. If an admission letter is withdrawn, cancelled, found to be based on inaccurate information, or the related payment is reversed before payment to the Agent, the additional commission is not payable.</p>

<div class="section-title">9. Payment Schedule, Statements, and Disputes</div>
<p>The Company will pay each undisputed Benefits Fee share and additional commission within one month after all applicable payment conditions have been satisfied. Payment will be made directly to the Agent&rsquo;s verified account. The Agent must notify the Company in writing of any calculation dispute within 30 days after receiving the statement.</p>

<div class="section-title">10. Refunds, Chargebacks, and Clawbacks</div>
<p>Commissions are calculated only on funds finally received and retained. Overpayments after refund, chargeback, reversal, fraud, or material misrepresentation become repayable within 15 days after written notice and may be offset against future payments.</p>

<div class="section-title">11. Taxes and Expenses</div>
<p>The Agent is responsible for all taxes, registrations, filings, insurance, banking charges, and business expenses arising from payments received under this Agreement.</p>

<div class="section-title">12. Confidentiality, Privacy, and Records</div>
<p>Each Party shall keep confidential all non-public business, pricing, student, financial, operational, and technical information received from the other Party and use it only to perform this Agreement. The Agent shall retain referral and payment-supporting records for at least two years, or longer if required by law. These obligations survive termination.</p>

<div class="section-title">13. Intellectual Property and Marketing</div>
<p>The Company retains all rights in its names, logos, forms, processes, websites, and materials. The Agent receives a limited, revocable, non-transferable right during the term to use Company-approved materials solely to perform this Agreement.</p>

<div class="section-title">14. Compliance, Conflicts, and Non-Circumvention</div>
<p>The Agent shall disclose conflicts of interest and shall not pay or accept undisclosed referral fees, bribes, kickbacks, or improper benefits. During the term, the Agent shall not knowingly bypass the Company for a confirmed referral after substantive work has begun, subject to the student&rsquo;s freedom of choice and material Company failure.</p>

<div class="section-title">15. Termination</div>
<p>Either Party may terminate without cause on 30 days&rsquo; written notice, or immediately for uncured material breach (10-day cure) or for fraud, unlawful conduct, misuse of funds or personal information, serious reputational harm, insolvency, loss of a required licence, or repeated material misrepresentation.</p>

<div class="section-title">16. Independent Contractor; No Guarantees</div>
<p>The Agent is an independent contractor and not an employee, partner, joint venturer, franchisee, or legal representative of the Company. The Company does not guarantee admission, visa approval, or other third-party outcomes.</p>

<div class="section-title">17. Limitation of Liability and Indemnity</div>
<p>To the maximum extent permitted by law, neither Party will be liable to the other for indirect, incidental, special, punitive, or consequential losses. Each Party remains responsible for direct losses caused by its own breach, negligence, fraud, wilful misconduct, or unlawful acts.</p>

<div class="section-title">18. Notices</div>
<p>Formal notices must be in writing. Company notice address: Gasanze Cell, Nduba Sector, Gasabo District, Kigali, Rwanda. Company notice email: infos@scholarsyncglobal.ca. Agent notice email: <?= acPdfEsc($agentEmail) ?>.</p>

<div class="section-title">19. Governing Law and Dispute Resolution</div>
<p>This Agreement is governed by the laws of the Republic of Rwanda. Disputes will first be discussed in good faith within 15 days; otherwise the courts of Kigali, Rwanda have exclusive jurisdiction unless mediation or arbitration is agreed in writing.</p>

<div class="section-title">20. General Provisions</div>
<p>This Agreement constitutes the entire agreement between the Parties. Amendments must be in writing and signed. Electronic signatures are permitted. Headings are for convenience only.</p>

<div class="section-title">21. Signatures</div>
<p>The Parties confirm that they have read, understood, and agreed to the terms of this Agreement and have had the opportunity to obtain independent legal advice.</p>

<table class="sig-grid" style="width:100%;border-collapse:collapse;">
<tr>
<td style="width:48%;vertical-align:top;padding-right:12pt;">
  <div class="sig-heading">SCHOLARSYNC GLOBAL COMPANY LTD.</div>
  <div class="sig-field"><span class="sig-field-label">Name:</span><div class="sig-line">Dr. Jean Pierre Twajamahoro</div></div>
  <div class="sig-field"><span class="sig-field-label">Title:</span><div class="sig-line">Owner &amp; Managing Director</div></div>
  <div class="sig-field"><span class="sig-field-label">Signature:</span><div class="sig-line"><img src="<?= $managerSignature ?>" alt="MD Signature"></div></div>
  <div class="sig-field"><span class="sig-field-label">Company Stamp:</span><div class="stamp-box"><img src="<?= $companyStamp ?>" alt="Stamp"></div></div>
  <div class="sig-field"><span class="sig-field-label">Date:</span><div class="sig-line"><?= acPdfFormatDate($effectiveDate) ?></div></div>
</td>
<td style="width:48%;vertical-align:top;padding-left:12pt;">
  <div class="sig-heading">AGENT / STAFF</div>
  <div class="sig-field"><span class="sig-field-label">Name:</span><div class="sig-line"><?= acPdfEsc($agentName) ?></div></div>
  <div class="sig-field"><span class="sig-field-label">Title:</span><div class="sig-line"><?= acPdfEsc($agentTitle !== '' ? $agentTitle : '___________________________') ?></div></div>
  <div class="sig-field"><span class="sig-field-label">Signature:</span><div class="sig-line"><img src="<?= is_string($agentSigSrc) && str_starts_with($agentSigSrc, 'data:') ? $agentSigSrc : str_replace('\\', '/', (string) $agentSigSrc) ?>" alt="Agent Signature"></div></div>
  <div class="sig-field"><span class="sig-field-label">Date:</span><div class="sig-line"><?= acPdfFormatDate($signedDate) ?></div></div>
</td>
</tr>
</table>

<div class="footer-ref">Contract Reference: <?= acPdfEsc($data['contract_token']) ?></div>

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

    $dir = agent_contract_upload_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filePath = $dir . '/agent_contract_' . $contractId . '.pdf';
    file_put_contents($filePath, $dompdf->output());

    return $filePath;
}
