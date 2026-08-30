<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/helpers/contract_signature_image.php';
require_once __DIR__ . '/helpers/employment_opportunities_schema.php';

function eoEsc(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function eoFormatDate(?string $date): string
{
    if ($date === null || trim($date) === '') {
        return '___________________________';
    }
    $ts = strtotime($date);
    return $ts ? date('F j, Y', $ts) : eoEsc($date);
}

function eoPdfEmbedImage(string $absolutePath): string
{
    if (!is_file($absolutePath)) {
        throw new RuntimeException('Image not found: ' . basename($absolutePath));
    }
    $mime = mime_content_type($absolutePath) ?: 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($absolutePath));
}

function eoPdfFieldRow(string $label, string $value, string $fallback = ''): string
{
    $display = trim($value) !== '' ? eoEsc($value) : eoEsc($fallback);
    return '<div class="field-row"><span class="field-label">' . eoEsc($label) . '</span>'
        . '<span class="field-value">' . $display . '</span></div>';
}

function generateEoContractPDF(int $contractId): string
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT
            c.contract_token,
            c.agreement_date,
            c.external_client_name,
            c.external_client_email,
            c.external_client_phone,
            c.external_client_passport,
            c.external_client_address,
            c.training_field,
            c.program_fee,
            c.payment_terms,
            c.application_id,
            sig.client_name,
            sig.client_email,
            sig.client_passport,
            sig.signed_date,
            sig.signature_image,
            a.reference_id
        FROM eo_employment_contracts c
        INNER JOIN eo_employment_signatures sig ON sig.contract_id = c.id
        LEFT JOIN employment_opportunities_applications a ON a.id = c.application_id
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

    $clientName     = $data['client_name'] ?: ($data['external_client_name'] ?? '');
    $clientEmail    = $data['client_email'] ?: ($data['external_client_email'] ?? '');
    $clientPassport = $data['client_passport'] ?: ($data['external_client_passport'] ?? '');
    $clientPhone    = $data['external_client_phone'] ?? '';
    $clientAddress  = $data['external_client_address'] ?? '';
    $agreementDate  = $data['agreement_date'] ?: ($data['signed_date'] ?? '');
    $signedDate     = $data['signed_date'] ?? '';
    $referenceId    = $data['reference_id'] ?? '';
    $trainingField  = eo_training_field_label((string) ($data['training_field'] ?? ''));
    $programFee     = trim((string) ($data['program_fee'] ?? ''));
    $paymentTerms   = trim((string) ($data['payment_terms'] ?? ''));

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
    $managerSignature = eoPdfEmbedImage($managerSigPath);
    $companyStamp = eoPdfEmbedImage($companyStampPath);

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4; margin: 2.54cm; }
body { font-family: "Times New Roman", Times, serif; font-size: 12pt; line-height: 1.6; color: #000; margin: 0; padding: 0; }
.doc-title { text-align: center; font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 0 0 4pt; }
.doc-subtitle { text-align: center; font-size: 13pt; font-weight: bold; text-transform: uppercase; margin: 0 0 18pt; }
p { margin: 0 0 10pt; text-align: justify; }
.section-title { font-size: 12pt; font-weight: bold; margin: 18pt 0 8pt; }
.client-section-title { font-weight: bold; margin: 14pt 0 10pt; font-size: 12pt; }
.field-row { margin: 0 0 8pt; line-height: 1.8; }
.field-value { display: inline-block; min-width: 68%; border-bottom: 1px solid #000; padding: 0 2pt 2pt 6pt; }
.party-block { margin: 12pt 0; }
ul.contract-list { margin: 6pt 0 12pt 0; padding-left: 22pt; }
ul.contract-list li { margin-bottom: 5pt; text-align: justify; }
.fee-total { text-align: center; font-weight: bold; font-size: 12pt; margin: 12pt 0; }
.sig-section { margin-top: 28pt; page-break-inside: avoid; }
.sig-heading { font-weight: bold; text-transform: uppercase; margin: 0 0 12pt; font-size: 12pt; }
.sig-name { font-weight: bold; margin: 0 0 2pt; }
.sig-role { margin: 0 0 14pt; }
.sig-field { margin: 0 0 12pt; }
.sig-field-label { display: block; margin-bottom: 4pt; }
.sig-line-box { border-bottom: 1px solid #000; min-height: 42pt; width: 72%; padding-top: 2pt; }
.sig-line-box img { max-height: 40pt; max-width: 220pt; }
.stamp-box { min-height: 70pt; width: 72%; padding-top: 4pt; }
.stamp-box img { max-height: 68pt; max-width: 180pt; }
.client-sig-block { margin-top: 36pt; page-break-inside: avoid; }
.footer-ref { margin-top: 24pt; text-align: center; font-size: 9pt; color: #444; border-top: 1px solid #ccc; padding-top: 8pt; }
</style>
</head>
<body>

<div class="doc-title">SCHOLARSYNC GLOBAL CO. LTD.</div>
<div class="doc-subtitle">EMPLOYMENT OPPORTUNITIES<br>(TRAINING &amp; WORK IN RUSSIA) SERVICE AGREEMENT</div>

<p><strong>Agreement Date:</strong> <?= eoFormatDate($agreementDate) ?></p>

<p>This Service Agreement ("Agreement") is entered into on the date indicated above between:</p>

<div class="party-block">
<p><strong>SCHOLARSYNC GLOBAL CO. LTD.</strong><br>
Represented by: Dr. Jean Pierre Twajamahoro, Managing Director<br>
(Hereinafter referred to as "the Company")</p>
</div>

<p><strong>AND</strong></p>

<div class="client-section-title">CLIENT INFORMATION</div>

<?= eoPdfFieldRow('Full Name:', $clientName, '_______________________________________') ?>
<?= eoPdfFieldRow('Passport Number:', $clientPassport, '_______________________________') ?>
<?= eoPdfFieldRow('Address:', $clientAddress, '_______________________________________') ?>
<?= eoPdfFieldRow('Telephone:', $clientPhone, '_____________________________________') ?>
<?= eoPdfFieldRow('Email:', $clientEmail, '_________________________________________') ?>
<?php if ($trainingField !== ''): ?>
<?= eoPdfFieldRow('Training Field:', $trainingField) ?>
<?php endif; ?>
<?php if ($referenceId !== ''): ?>
<?= eoPdfFieldRow('Application Ref:', $referenceId) ?>
<?php endif; ?>

<p>(Hereinafter referred to as "the Client")</p>
<p>The Company and the Client shall collectively be referred to as "the Parties."</p>

<div class="section-title">1. PURPOSE OF THE AGREEMENT</div>
<p>The purpose of this Agreement is to establish the terms and conditions under which SCHOLARSYNC GLOBAL CO. LTD. shall provide professional recruitment, training placement, and relocation support to enable the Client to undertake professional training together with Russian language study, and to be placed in a work-training position in the Russian Federation.</p>

<div class="section-title">2. SCOPE OF SERVICES</div>
<p>The Company agrees to provide the following professional services:</p>
<ul class="contract-list">
<li>Assessment of the Client's eligibility for the Employment Opportunities program.</li>
<li>Placement of the Client into a professional training and work position in one of the program's fields.</li>
<li>Coordination of Russian language training alongside practical work experience.</li>
<li>Registration of the Client's profile within the Company's recruitment network.</li>
<li>Communication and follow-up with the host employer or training provider.</li>
<li>Guidance regarding required documentation and travel arrangements.</li>
<li>General support throughout the recruitment, training, and relocation process.</li>
</ul>

<div class="section-title">3. CLIENT RESPONSIBILITIES</div>
<p>The Client agrees to:</p>
<ul class="contract-list">
<li>Provide complete, truthful, and accurate information.</li>
<li>Submit genuine and valid documents (including passport and academic documents).</li>
<li>Cooperate throughout the recruitment and training process.</li>
<li>Attend interviews and assessments requested by the Company or employer.</li>
<li>Participate in the required Russian language training.</li>
<li>Respond promptly to requests from the Company.</li>
<li>Respect all payment obligations under this Agreement.</li>
<li>Comply with the laws and regulations of the Russian Federation.</li>
</ul>

<div class="section-title">4. COMPANY RESPONSIBILITIES</div>
<p>SCHOLARSYNC GLOBAL CO. LTD. agrees to:</p>
<ul class="contract-list">
<li>Act honestly, professionally, and ethically.</li>
<li>Protect the confidentiality of the Client's information.</li>
<li>Maintain communication with the Client throughout the process.</li>
<li>Promote the Client's profile to suitable employers and training providers.</li>
<li>Assist with training placement and relocation documentation.</li>
<li>Provide continuous professional support until the agreed services are completed.</li>
</ul>

<div class="section-title">5. SERVICE COMMITMENT &amp; PAYMENT TERMS</div>
<p>SCHOLARSYNC GLOBAL CO. LTD. is committed to providing professional recruitment, training-placement, and relocation support services throughout the Client's participation in the Employment Opportunities program.</p>
<?php if ($programFee !== ''): ?>
<p>The total professional service fee for this program is:</p>
<p class="fee-total"><?= eoEsc($programFee) ?></p>
<?php else: ?>
<p>The professional service fee for this program and its payment schedule shall be as separately agreed in writing between the Parties.</p>
<?php endif; ?>
<?php if ($paymentTerms !== ''): ?>
<p><strong>Payment Terms:</strong><br><?= nl2br(eoEsc($paymentTerms)) ?></p>
<?php endif; ?>
<p>Failure to pay any amount when due may result in suspension of services until payment has been received.</p>

<div class="section-title">6. CONFIDENTIALITY</div>
<p>The Company agrees to keep all personal information and documents confidential and shall not disclose such information to any third party except where required by law, with the Client's written consent, or for legitimate recruitment, training, or relocation purposes.</p>

<div class="section-title">7. TERMINATION</div>
<p>Either Party may terminate this Agreement by providing written notice. Termination shall not affect payment obligations for services already rendered.</p>

<div class="section-title">8. ENTIRE AGREEMENT</div>
<p>This Agreement constitutes the complete understanding between the Parties and supersedes all prior discussions, representations, or agreements relating to the services described herein. Any amendment shall be made in writing and signed by both Parties.</p>

<div class="section-title">DECLARATION</div>
<p>The Client confirms that they have carefully read and understood this Agreement and voluntarily accept all of its terms and conditions.</p>

<div class="sig-section">
<div class="sig-heading">SIGNED FOR SCHOLARSYNC GLOBAL CO. LTD.</div>
<p class="sig-name">Dr. Jean Pierre Twajamahoro</p>
<p class="sig-role">Managing Director</p>

<div class="sig-field">
<span class="sig-field-label">Signature:</span>
<div class="sig-line-box"><img src="<?= $managerSignature ?>" alt="Managing Director Signature"></div>
</div>

<div class="sig-field">
<span class="sig-field-label">Date:</span>
<div class="sig-line-box" style="min-height:24pt;line-height:24pt;padding-top:6pt;"><?= eoFormatDate($agreementDate) ?></div>
</div>

<div class="sig-field">
<span class="sig-field-label">Company Stamp:</span>
<div class="stamp-box"><img src="<?= $companyStamp ?>" alt="Company Stamp"></div>
</div>
</div>

<div class="client-sig-block">
<div class="sig-heading">SIGNED BY THE CLIENT</div>

<div class="sig-field">
<span class="sig-field-label">Full Name:</span>
<div class="sig-line-box" style="min-height:24pt;line-height:24pt;padding-top:6pt;"><?= eoEsc($clientName) ?></div>
</div>

<div class="sig-field">
<span class="sig-field-label">Signature:</span>
<div class="sig-line-box"><img src="<?= is_string($clientSigSrc) && str_starts_with($clientSigSrc, 'data:') ? $clientSigSrc : str_replace('\\', '/', (string) $clientSigSrc) ?>" alt="Client Signature"></div>
</div>

<div class="sig-field">
<span class="sig-field-label">Date:</span>
<div class="sig-line-box" style="min-height:24pt;line-height:24pt;padding-top:6pt;"><?= eoFormatDate($signedDate) ?></div>
</div>

<div class="sig-field">
<span class="sig-field-label">Passport Number:</span>
<div class="sig-line-box" style="min-height:24pt;line-height:24pt;padding-top:6pt;"><?= eoEsc($clientPassport !== '' ? $clientPassport : '_______________________________') ?></div>
</div>
</div>

<div class="footer-ref">
Contract Reference: <?= eoEsc($data['contract_token']) ?>
<?php if ($referenceId !== ''): ?> | Application: <?= eoEsc($referenceId) ?><?php endif; ?>
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

    $dir = __DIR__ . '/uploads/eo_contracts';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filePath = $dir . '/eo_contract_' . $contractId . '.pdf';
    file_put_contents($filePath, $dompdf->output());

    return $filePath;
}
