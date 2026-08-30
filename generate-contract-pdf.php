<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/helpers/contract_price_highlight.php';
require_once __DIR__ . '/helpers/contract_signature_image.php';

/* =====================================================
   SAFE ESCAPE
===================================================== */
function esc(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function contractPdfPlainText(string $text): string
{
    $text = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $text) ?? $text;
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function contractPdfRenderFeeLine(string $line): string
{
    $parts = preg_split('/\s+—\s+/u', $line, 2) ?: [$line];
    $main = highlightContractPrices(trim($parts[0]));
    if (empty($parts[1])) {
        return $main;
    }

    return $main . '<span class="fee-desc">' . esc(trim($parts[1])) . '</span>';
}

/* =====================================================
   ARTICLE 7 – PACKAGE MAP (SINGLE SOURCE OF TRUTH)
===================================================== */
function getPackageDetails(string $code): array
{
    $packages = [

        /* =========================
           7.1 Partner Universities – Targeted Countries
        ========================== */
        'p70' => [
            'title' => '7.1 FEES PAID BY STUDENTS FOR OUR PARTNER UNIVERSITIES, COMPANIES, AND SOME TARGETED COUNTRIES',
            'lines' => [
                'USA – Registration and Application Fee: USD 150 (Paid once offer of admission is out)',
                'Canada – Registration and Application Fee: CAD 225 (Paid once offer of admission is out)',
                'Europe – Registration and Application Fee: USD 150 (Paid before starting application)',
                'South Korea – Registration and Application Fee: USD 250 (Paid before starting application)',
                'Note: Students are responsible for all additional costs associated with their visa application and immigration process, including any fees charged by embassies, visa application centers, government agencies, medical institutions, or other relevant authorities.',
            ],
            'total' => null,
        ],

        /* =========================
           7.2 USA – Loan Based
        ========================== */
        'p71' => [
            'title' => '7.2 Study in the USA (Loan-Based)',
            'lines' => [
                'Registration & Application Fee: USD 150 (Refundable if admission is not secured within 4 months)',
                'After Loan Approval: USD 1,200',
                'Mock Interview Preparation Fees: USD 150',
                'After Visa Approval: USD 1,500',
            ],
            'total' => 'USD 3,000',
        ],

        /* =========================
           7.3 USA – Without Loan
        ========================== */
        'p72' => [
            'title' => '7.3 Study in the USA (Without Loan)',
            'lines' => [
                'Registration & Application Fee: USD 150 (Refundable if admission is not secured within 4 months)',
                'Mock Interview Preparation Fees: USD 150',
                'After Visa Approval: USD 2,000',
            ],
            'total' => 'USD 2,300',
        ],

        /* =========================
           7.4 Europe – Without Loan
        ========================== */
        'p73' => [
            'title' => '7.4 Study in Europe (Without Loan)',
            'lines' => [
                'Registration & Application Fee: USD 250 (Refundable if admission is not secured within 4 months)',
                'Before Visa Application: USD 250',
                'After Visa Approval: USD 1,500',
            ],
            'total' => 'USD 2,000',
        ],

        /* =========================
           7.5 Canada – Loan Based
        ========================== */
        'p74' => [
            'title' => '7.5 Study in Canada (Loan-Based)',
            'lines' => [
                'Registration & Application Fee: CAD 450 (Refundable if admission is not secured within 4 months)',
                'After Visa Approval: CAD 3,050',
                'Note: Tuition deposit CAD 500–5,000 payable directly by the Student',
            ],
            'total' => 'CAD 3,500',
        ],

        /* =========================
           7.6 Canada – Without Loan
        ========================== */
        'p75' => [
            'title' => '7.6 Study in Canada (Without Loan)',
            'lines' => [
                'Registration & Application Fee: CAD 450 (Refundable if admission is not secured within 4 months)',
                'After Visa Approval: CAD 2,050',
            ],
            'total' => 'CAD 2,500',
        ],

        /* =========================
           7.7 Canada – High School (Loan)
        ========================== */
        'p76' => [
            'title' => '7.7 Canada – High School Graduate (Loan-Based)',
            'lines' => [
                'Registration & Application Fee: CAD 450',
                'Study Permit Fees (Embassy): CAD 150',
                'Biometrics Fees (Embassy): CAD 85',
                'CAQ Fees (Quebec Only): CAD 132',
                'Border Pass Fees (Lawyer): CAD 250',
                'Loan Processing Fees: CAD 1,000',
                'Service Fees After Visa Approval: CAD 1,933',
            ],
            'total' => 'CAD 4,000',
        ],

        /* =========================
           7.8 Canada – Own Admission Letter
        ========================== */
        'p77ca' => [
            'title' => '7.8 Study in Canada (With Your Own Admission Letter)',
            'lines' => [
                'Document Handling, Visa Application & Biometric Fees: CAD 735',
                'Service Fees (payable after visa approval): CAD 1,000',
            ],
            'total' => 'CAD 1,735',
        ],

        /* =========================
           7.9 South Korea – Study
        ========================== */
        'p77' => [
            'title' => '7.9 Study in South Korea (Self-Sponsored)',
            'lines' => [
                'Registration and Application Follow-up fees: USD 500 (Must be paid before starting the admission process; refundable if admission letter is not secured)',
                'Self-Sponsored Service Fees – Bachelor: USD 2,000 (Includes free Korean language training for 3 months & Pre-Departure Orientation)',
                'Self-Sponsored Service Fees – Master’s: USD 2,400 (Includes free Korean language training for 3 months & Pre-Departure Orientation)',
                'Self-Sponsored Service Fees – PhD: USD 2,800 (Includes free Korean language training for 3 months & Pre-Departure Orientation)',
                'Once the Final Acceptance Letter is approved, one-half (1/2) of the applicable service fees must be paid before the visa application',
                'Free Korean language training (3 months) and Pre-Departure Orientation are provided if needed',
            ],
            'total' => null,
        ],

        /* =========================
           7.10 South Korea – Visit
        ========================== */
        'p78' => [
            'title' => '7.10 🇰🇷 South Korea Visitor Visa',
            'lines' => [
                'Registration & Application Fee: USD 500',
                'Service Fee (Paid After Receiving the Invitation Letter and Guarantee Letter): USD 1,500',
                'Participation fees vary depending on the event organizer.',
            ],
            'total' => null,
        ],

        /* =========================
           7.11 Credit Transfer
        ========================== */
        'p79' => [
            'title' => '7.11 Credit Transfer (Bachelor, Masters, PhD)',
            'lines' => [
                'Bachelor Program: USD 920',
                'Masters Program: USD 1,220',
                'PhD Program: USD 1,620',
            ],
            'total' => null,
        ],

        /* =========================
           7.12 Canada Visit Visa
        ========================== */
        'p710' => [
            'title' => '7.12 Canada Visit Visa',
            'lines' => [
                'Documents & Invitation Letter: USD 1,000',
                'Visa Application Fees: CAD 100',
                'Biometrics Fees: CAD 85',
                'Service Fees (After Visa Approval): CAD 2,000',
            ],
            'total' => null,
        ],

        /* =========================
           7.13 Canada Visit Visa – With Invitation Letter
        ========================== */
        'p710b' => [
            'title' => '7.13 Canada Visit Visa – With Invitation Letter',
            'lines' => [
                'Invitation Letter: Already Provided by Applicant',
                'Document Preparation and Visa Application Screening: CAD 815',
                'Visa Application Fee: CAD 100',
                'Biometrics Fee: CAD 85',
                'Service Fee (After Visa Approval): CAD 2,000',
            ],
            'total' => 'CAD 3,000',
            'total_label' => 'Total Cost',
        ],

        /* =========================
           7.14 USA Visit Visa
        ========================== */
        'p711' => [
            'title' => '7.14 USA Visit Visa',
            'lines' => [
                'Documents & Invitation Letter: USD 1,000',
                'Visa Application Fees: USD 185',
                'Service Fees (After Visa Approval): USD 1,500',
            ],
            'total' => null,
        ],

        /* =========================
           7.15 Europe Visit Visa
        ========================== */
        'p712' => [
            'title' => '7.15 Europe Visit Visa',
            'lines' => [
                'Documents & Invitation Letter: €600',
                'Visa Application Fees: €85 – €500 (depending on country)',
                'Service Fees (After Visa Approval): €1,000',
            ],
            'total' => null,
        ],

        /* =========================
           7.16 Asia Visit Visa
        ========================== */
        'p713' => [
            'title' => '7.16 Asia Visit Visa',
            'lines' => [
                'Documents & Invitation Letter: USD 800',
                'Visa Application Fees: USD 85 – USD 500',
                'Service Fees (After Visa Approval): USD 1,500',
            ],
            'total' => null,
        ],

        /* =========================
           7.17 Short Courses - Canada
        ========================== */
        'p714' => [
            'title' => '7.17 SHORT COURSES-CANADA',
            'lines' => [
                'Registration & Application Fee: CAD 450 (Refundable if admission is not secured within 2 weeks)',
                'Registration & Application Fee for Family Member: CAD 200 (If applicable)',
                'Tuition Fees Deposit after getting offer letter: CAD 535 (Paid directly to school)',
                'Before starting Visa Application: CAD 100 for visit visa application (Paid to embassy)',
                'Biometrics: CAD 85 (Paid to embassy)',
                'After Visa Approval: CAD 2,500',
            ],
            'total' => 'CAD 3,670',
        ],

        /* =========================
           7.18 Study PhD in Multiple Destinations
        ========================== */
        'p715' => [
            'title' => '7.18 STUDY PhD IN CANADA-USA-EUROPE & ASIA',
            'lines' => [
                'Registration & Application Fee for Canada: CAD 500 (Refundable if admission is not secured within 9 months)',
                'Registration & Application Fee for USA, Europe & Asia: USD 350 (Refundable if admission is not secured within 9 months)',
                'Paper Publication Fee (Non-Refundable): USD 280 (If applicable)',
                'Assistance for PhD Research Proposal Writing (Non-Refundable): USD 300 (If applicable)',
                'Visa Application Fee for Family Member (Non-Refundable): Canada CAD 400 (If applicable)',
                'Visa Application Fee for Family Member (Non-Refundable): USA, Europe & Asia USD 250 (If applicable)',
                'Tuition Fees Deposit after getting offer letter for Canada: CAD 1,000 to CAD 5,000 (Paid directly to school after getting admission)',
                'Tuition Fees Deposit after getting offer letter for USA, Europe & Asia: USD 500 to USD 5,000 (Paid directly to school after getting admission)',
                'After Visa Approval for Canada: CAD 5,000',
                'After Visa Approval for USA, Europe & Asia: USD 4,500',
                'Note: All visa application fees must be paid to the embassy by the applicant. An additional fee of CAD 800 (Canada) or USD 500 (USA, Europe, and Asia) applies for each family member after visa approval.',
            ],
            'total' => null,
        ],

        /* =========================
           7.19 WES Evaluation – International Equivalence
        ========================== */
        'p716' => [
            'title' => '7.19 WES EVALUATION – INTERNATIONAL EQUIVALENCE',
            'lines' => [
                '1. Professional Service Fees: CAD 200 — The fee includes professional consultation, guidance, document preparation assistance, and personalized support throughout the WES evaluation process.',
                '2. Application & Processing Costs: CAD 300 — The amount covers application-related expenses, communication with institutions, document handling, and processing follow-up during the evaluation procedure.',
                '3. University & Verification Coordination: CAD 100 — The service involves contacting universities, registrars, and authorized offices to ensure transcripts and academic records are properly verified and submitted to WES.',
                '4. Document Shipping & Delivery Expenses: CAD 100 — The cost also includes courier charges, document shipping, electronic submission support, and tracking to ensure documents safely reach WES on time.',
                '5. Time, Administrative Work & Follow-up: CAD 200 — Considerable time and administrative effort are required for monitoring submissions, correcting issues, responding to updates, and supporting applicants until the evaluation process is completed successfully.',
            ],
            'total' => 'CAD 900',
        ],

        /* =========================
           7.20 Guaranteed Evaluation Support
        ========================== */
        'p717' => [
            'title' => '7.20 GUARANTEED EVALUATION SUPPORT!',
            'lines' => [
                '1. Professional Service Fees: CAD 200 — The fee includes professional consultation, guidance, document preparation assistance, and personalized support throughout the all evaluation process.',
                '2. Application & Processing Costs: CAD 300 — The amount covers application-related expenses, communication with institutions, document handling, and processing follow-up during the evaluation procedure.',
                '3. University & Verification Coordination: CAD 100 — The service involves contacting universities, registrars, and authorized offices to ensure transcripts and academic records are properly verified and submitted.',
                '4. Document Shipping & Delivery Expenses: CAD 100 — The cost also includes courier charges, document shipping, electronic submission support, and tracking to ensure documents safely reach on time.',
                '5. Time, Administrative Work & Follow-up: CAD 200 — Considerable time and administrative effort are required for monitoring submissions, correcting issues, responding to updates, and supporting applicants until the evaluation process is completed successfully.',
            ],
            'total' => 'CAD 900',
            'total_label' => 'Total packages',
        ],
    ];

    return $packages[$code] ?? [];
}

/* =====================================================
   GENERATE FINAL SIGNED CONTRACT PDF
===================================================== */
function generateContractPDF(int $contractId): string
{
    global $conn;

    /* =====================================================
       1. LOAD FULL CONTRACT + STUDENT + SIGNATURE
    ===================================================== */
$stmt = $conn->prepare("
    SELECT
        c.contract_token,
        c.selected_package_code,
        c.selected_package_label,

        COALESCE(
            NULLIF(TRIM(CONCAT_WS(' ',
                NULLIF(TRIM(s.first_name),  ''),
                NULLIF(TRIM(s.middle_name),  ''),
                NULLIF(TRIM(s.last_name),   '')
            )), ''),
            NULLIF(TRIM(sig.student_name), ''),
            'Student'
        ) AS full_name,

        COALESCE(NULLIF(TRIM(s.email), ''), NULLIF(TRIM(sig.student_email), '')) AS email,
        COALESCE(NULLIF(TRIM(s.dob), ''), '') AS dob,
        COALESCE(NULLIF(TRIM(nat.name), ''), NULLIF(TRIM(s.nationality), '')) AS nationality,
        COALESCE(NULLIF(TRIM(s.passport_number), ''), '') AS passport_number,
        COALESCE(NULLIF(TRIM(s.phone_number), ''), '') AS phone_number,

        sig.signed_date,
        sig.signature_image
    FROM student_contracts c
    INNER JOIN student_signatures sig ON sig.contract_id = c.id
    LEFT JOIN student_applications s ON s.id = c.student_id
    LEFT JOIN countries nat
           ON nat.id   = s.nationality
           OR nat.name = s.nationality
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

    /* =====================================================
       2. SIGNATURE SOURCES
    ===================================================== */
    if (
        empty($data['signature_image']) ||
        !str_starts_with($data['signature_image'], 'data:image')
    ) {
        throw new RuntimeException('Invalid student signature.');
    }

    $studentSignature = $data['signature_image'];
    $studentSignatureFile = contract_signature_save_standard_png($contractId, $studentSignature);
    if ($studentSignatureFile !== null && is_file($studentSignatureFile)) {
        $studentSignatureSrc = $studentSignatureFile;
    } else {
        $normalizedPng = contract_signature_to_display_png($studentSignature);
        $studentSignatureSrc = $normalizedPng !== null
            ? 'data:image/png;base64,' . base64_encode($normalizedPng)
            : $studentSignature;
    }

    $managerSigPath = __DIR__ . '/admin/signature-manager.png';
    $companyStampPath = __DIR__ . '/admin/employer-signature.png';
    if (!file_exists($managerSigPath)) {
        throw new RuntimeException('Managing director signature missing.');
    }
    if (!file_exists($companyStampPath)) {
        throw new RuntimeException('Company stamp missing.');
    }

    $managerSignature =
        'data:image/png;base64,' . base64_encode(file_get_contents($managerSigPath));
    $companyStamp =
        'data:image/png;base64,' . base64_encode(file_get_contents($companyStampPath));

    /* =====================================================
       3. ARTICLE 7 – SELECTED PACKAGE
    ===================================================== */
    $package = getPackageDetails($data['selected_package_code']);
    if (!$package) {
        throw new RuntimeException('Selected package not defined.');
    }

    /* =====================================================
       4. BUILD HTML (ALL ARTICLES INCLUDED)
    ===================================================== */
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
/* =========================
   PAGE SETUP (WORD A4)
========================= */
@page {
    size: A4;
    margin: 2.54cm 2.54cm 2.54cm 2.54cm; /* Word default margins */
}

/* =========================
   BASE BODY (WORD DEFAULT)
========================= */
body {
    font-family: "Times New Roman", Times, serif;
    font-size: 12pt;
    line-height: 1.6;
    color: #000;
}

/* =========================
   MAIN TITLE (WORD STYLE)
========================= */
h1 {
    text-align: center;
    font-size: 20pt;
    font-weight: bold;
    text-transform: uppercase;
    margin: 0 0 18pt 0;
}

/* =========================
   ARTICLE HEADINGS (1., 2., 3.)
========================= */
h2 {
    font-size: 14pt;
    font-weight: bold;
    text-transform: uppercase;
    margin: 22pt 0 10pt 0;
}

/* =========================
   SUB-HEADINGS (1.1, 1.2)
========================= */
h3 {
    font-size: 12pt;
    font-weight: bold;
    margin: 16pt 0 6pt 0;
}

/* =========================
   PARAGRAPHS (JUSTIFIED)
========================= */
p {
    text-align: justify;
    margin: 0 0 10pt 0;
}

/* =========================
   LISTS (WORD INDENTATION)
========================= */
ul, ol {
    margin: 0 0 12pt 32pt;
    padding: 0;
}

li {
    margin-bottom: 6pt;
}

/* =========================
   TABLES (SIGNATURE SECTION)
========================= */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 18pt;
}

td {
    vertical-align: top;
    padding: 10pt;
    font-size: 11.5pt;
}

/* =========================
   SIGNATURE BOX (WORD STYLE)
========================= */
.signature-box {
    width: 7cm;
    height: 4cm;
    margin-top: 8pt;
    margin-bottom: 6pt;
    border-bottom: 1px solid #000;
}

.signature-box img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.student-signature-line {
    border-bottom: 1px solid #000;
    height: 56px;
    width: 280px;
    position: relative;
    margin-top: 6pt;
    margin-bottom: 8pt;
}

.student-signature-line img {
    max-height: 52px;
    max-width: 270px;
    position: absolute;
    bottom: 2px;
    left: 0;
}

/* =========================
   FOOTER
========================= */
.footer {
    margin-top: 24pt;
    text-align: center;
    font-size: 10pt;
    color: #444;
}
/* =========================
   LINKS (EMAIL & WEBSITE)
========================= */
a {
    color: #0000EE;          /* Word default blue */
    text-decoration: underline;
}

/* =========================
   FEE AMOUNTS (PDF – subtle, inline only)
========================= */
.fee-price {
    font-weight: 700;
}

/* =========================
   SIGNATURES – keep on one page
========================= */
.signatures-section {
    page-break-inside: avoid;
    page-break-before: auto;
}

.signatures-section table,
.signatures-section tr,
.signatures-section td {
    page-break-inside: avoid;
}

.managing-director-block {
    margin-bottom: 18pt;
    page-break-inside: avoid;
}

.managing-director-block .manager-signature img {
    max-height: 52pt;
    max-width: 240pt;
}

.managing-director-block .company-stamp img {
    max-height: 110pt;
    max-width: 280pt;
}

.pricing-block {
    page-break-inside: avoid;
}

.selected-package-box {
    border: 1px solid #333;
    padding: 12pt 14pt;
    margin: 10pt 0 14pt 0;
    background: #fafafa;
    page-break-inside: avoid;
}

.selected-package-box .package-title {
    font-size: 12pt;
    font-weight: bold;
    margin: 0 0 10pt 0;
    line-height: 1.45;
    word-wrap: break-word;
}

.package-fees-list {
    margin: 0 0 10pt 22pt;
    padding: 0;
}

.package-fees-list li {
    margin-bottom: 8pt;
    text-align: justify;
    line-height: 1.55;
}

.package-fees-list .fee-desc {
    display: block;
    margin-top: 3pt;
    font-size: 11pt;
    font-weight: normal;
    color: #222;
}

.package-total {
    margin: 10pt 0 0 0;
    font-weight: bold;
}

</style>

</head>
<body>

<!-- =========================
     MAIN TITLE
========================= -->
<h1>
    INTERNATIONAL STUDENT ADMISSION<br>
    &amp; VISA CONSULTANCY AGREEMENT
</h1>

<!-- INTRO TEXT -->
<p>
    This <strong>International Student Admission and Visa Consultancy Agreement</strong>
    (“<strong>Agreement</strong>”) is made and entered into on the date of signature
    by and between:
</p>

<!-- DATE -->
<p>
    <strong>Date:</strong> <?= esc($data['signed_date']) ?>
</p>

<!-- =========================
     ARTICLE 1 – PARTIES
========================= -->
<h2>1. PARTIES</h2>

<!-- =========================
     1.1 CONSULTANT
========================= -->
<h3>1.1 The Consultant</h3>

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

<!-- =========================
     1.2 STUDENT
========================= -->
<h3>1.2 The Student</h3>

<p>
    <strong>Full Legal Name:</strong> <?= esc($data['full_name']) ?><br>
    <strong>Date of Birth:</strong> <?= esc($data['dob']) ?><br>
    <strong>Nationality:</strong> <?= esc($data['nationality']) ?><br>
    <strong>Passport Number:</strong> <?= esc($data['passport_number']) ?><br>
    <strong>Phone:</strong> <?= esc($data['phone_number']) ?><br>
    <strong>Email:</strong> <?= esc($data['email']) ?>
</p>

<p><em>(Hereinafter referred to as <strong>“The Student”</strong>)</em></p>

<!-- =========================
     ARTICLE 2 – PURPOSE OF AGREEMENT
========================= -->
<h2>2. PURPOSE OF AGREEMENT</h2>

<p>
This Agreement governs the provision of
<strong>international study admission, visa consultancy, and related advisory services</strong>
for the Student intending to study or visit foreign countries including
<strong>Canada, the United States of America, Europe, and South Korea</strong>.
The Student acknowledges that
<strong>final decisions rest solely with educational institutions, embassies,
immigration authorities, and third-party entities</strong>.
</p>

<!-- =========================
     ARTICLE 3 – SCOPE OF SERVICES
========================= -->
<h2>3. SCOPE OF SERVICES</h2>

<p>
The Consultant shall provide
<strong>admission guidance, visa application assistance, document preparation support,
interview preparation (where applicable), loan guidance (for loan-based programs),
job search assistance, accommodation search support, and pre-departure orientation</strong>,
subject to the specific service package selected by the Student under this Agreement.
</p>

<!-- =========================
     ARTICLE 4 – CONSULTANT’S OBLIGATIONS
========================= -->
<h2>4. CONSULTANT’S OBLIGATIONS</h2>

<p>The Consultant agrees to:</p>

<ol>
    <li>Provide services professionally and in good faith</li>
    <li>Follow official immigration and institutional guidelines</li>
    <li>Maintain confidentiality of Student information</li>
    <li>Communicate progress transparently</li>
    <li>Avoid falsification or misrepresentation</li>
</ol>

<!-- =========================
     ARTICLE 5 – STUDENT’S OBLIGATIONS
========================= -->
<h2>5. STUDENT’S OBLIGATIONS</h2>

<p>The Student agrees to:</p>

<ol>
    <li>Provide accurate, complete, and truthful information</li>
    <li>Submit genuine and verifiable documents</li>
    <li>Pay all required fees on time</li>
    <li>Cooperate fully throughout the process</li>
    <li>Accept responsibility for any consequences arising from false or incomplete information</li>
</ol>

<!-- =========================
     ARTICLE 6 – NO GUARANTEE DISCLAIMER
========================= -->
<h2>6. NO GUARANTEE DISCLAIMER</h2>

<p>The Student understands and agrees that:</p>

<ul>
    <li>Admission, visa approval, loan approval, and processing timelines are <strong>not guaranteed</strong></li>
    <li>Decisions are made solely by educational institutions, embassies, immigration authorities, and other third-party entities</li>
    <li>Refusal or delay does not constitute a breach of this Agreement by the Consultant</li>
</ul>
<!-- =========================
     ARTICLE 7 – FEES & PAYMENT TERMS
========================= -->
<h2>7. FEES &amp; PAYMENT TERMS (SELECTED PACKAGE)</h2>

<div class="selected-package-box">
<p class="package-title"><?= esc(contractPdfPlainText($package['title'])) ?></p>

<ul class="package-fees-list">
<?php foreach ($package['lines'] as $line): ?>
    <li><?= contractPdfRenderFeeLine($line) ?></li>
<?php endforeach; ?>
</ul>

<?php if (!empty($package['total'])): ?>
<p class="package-total"><?= esc($package['total_label'] ?? 'Total Package') ?>: <?= highlightContractPrices($package['total']) ?></p>
<?php endif; ?>
</div>
<h3>Additional Pricing Provisions (Without Loan &amp; Special Services)</h3>

<div class="pricing-block">
<p><strong>1. Spring, Winter, Summer, or Fall Short Courses (Worldwide)</strong></p>
<ul>
    <li><?= highlightContractPrices('Application and Registration Fees: EUR 250, refundable if approval is not secured within four (4) months') ?></li>
    <li><?= highlightContractPrices('Service Fees: EUR 2,000, payable only once the visa is approved') ?></li>
</ul>
</div>

<div class="pricing-block">
<p><strong>2. Canadian Immigration Lawyer – Visa Application (Canada Only)</strong></p>
<p>
Where the Student requests that the visa application be handled by a licensed
Canadian Immigration Lawyer, an additional charge of
<?= highlightContractPrices('CAD 300 per applicant') ?> shall apply.
</p>
</div>

<div class="pricing-block">
<p><strong>3. Canadian Immigration Lawyer – Legal Advice or Consultation (Canada Only)</strong></p>
<p>
Where the Student requires legal advice or consultation from a licensed
Canadian Immigration Lawyer, the Student shall pay a consultation fee of
<?= highlightContractPrices('CAD 300') ?>.
</p>
</div>

<p>
<strong>Important:</strong>
All government fees, embassy charges, biometric fees, SEVIS fees, tuition deposits,
lawyer fees, border pass fees, and third-party charges are paid separately by the Student
and are <strong>non-refundable once submitted</strong>.
</p>
<!-- =========================
     ARTICLE 8 – PAYMENT OF SERVICE FEES
========================= -->
<h2>8. PAYMENT OF SERVICE FEES</h2>

<p>
Where applicable, final service fees become immediately payable upon visa approval.
Once the visa is approved, the Student shall pay all outstanding service fees
<strong>no later than five (5) days</strong> from the date of approval.
Failure to make payment constitutes a <strong>material breach</strong> of this Agreement.
</p>

<!-- =========================
     ARTICLE 9 – REFUND POLICY
========================= -->
<h2>9. REFUND POLICY</h2>

<p>
Only registration fees are refundable strictly under the conditions expressly stated
in this Agreement. All other fees, including service fees, loan processing fees,
legal fees, and third-party charges, are <strong>non-refundable</strong>.
</p>

<!-- =========================
     ARTICLE 10 – TERMINATION
========================= -->
<h2>10. TERMINATION</h2>

<p>
The Consultant may terminate this Agreement immediately in the event of non-payment,
submission of fraudulent documents, or breach of any obligation by the Student.
Termination does not release the Student from outstanding payment obligations.
</p>

<!-- =========================
     ARTICLE 11 – LIMITATION OF LIABILITY
========================= -->
<h2>11. LIMITATION OF LIABILITY</h2>

<p>
The Consultant shall not be liable for decisions, delays, refusals, or outcomes issued
by embassies, educational institutions, or government authorities beyond its control.
</p>

<!-- =========================
     ARTICLE 12 – CONFIDENTIALITY
========================= -->
<h2>12. CONFIDENTIALITY</h2>

<p>
All information exchanged between the parties shall remain confidential except where
disclosure is required by law or competent authorities.
</p>

<!-- =========================
     ARTICLE 13 – GOVERNING LAW &amp; JURISDICTION
========================= -->
<h2>13. GOVERNING LAW &amp; JURISDICTION</h2>

<p>
This Agreement shall be governed by the laws of the <strong>Republic of Rwanda</strong>,
with exclusive jurisdiction vested in the competent courts of Rwanda.
</p>

<!-- =========================
     ARTICLE 14 – ENTIRE AGREEMENT
========================= -->
<h2>14. ENTIRE AGREEMENT</h2>

<p>
This Agreement constitutes the entire understanding between the parties and supersedes
all prior discussions. Any amendment must be in writing and signed by both parties.
</p>
<div class="signatures-section">
<h2>15. SIGNATURES</h2>

<table style="width:100%; border-collapse:collapse;">

<tr>
<!-- LEFT: ScholarSync / Dr. Twajamahoro -->
<td style="padding:10px 12px; vertical-align:top; width:50%;">

<strong>For ScholarSync Global Co. Ltd</strong><br><br>

Name: Dr. Jean Pierre Twajamahoro<br><br>

Title: Owner &amp; Managing Director<br><br>

Signature:<br>
<div class="manager-signature" style="border-bottom:1px solid #000; min-height:54pt; width:90%; padding-top:2pt;">
<img src="<?= $managerSignature ?>" alt="Managing Director Signature">
</div><br>

Company Stamp:<br>
<div class="company-stamp" style="min-height:112pt; width:90%; padding-top:4pt;">
<img src="<?= $companyStamp ?>" alt="Company Stamp">
</div><br>

Date: <?= esc($data['signed_date']) ?>

</td>

<!-- RIGHT: Student e-sign -->
<td style="padding:10px 12px; vertical-align:top; width:50%;">

<strong>For the Student</strong><br><br>

Name: <?= esc($data['full_name']) ?><br><br>

Signature:<br>

<div class="student-signature-line">

<?php if (!empty($studentSignatureSrc)): ?>
<img src="<?= str_replace('\\', '/', $studentSignatureSrc) ?>" alt="Student Signature">
<?php endif; ?>

</div>

Date: <?= esc($data['signed_date']) ?>

</td>
</tr>

</table>
<div class="footer">
Contract Reference: <?= esc($data['contract_token']) ?>
</div>
</div>

</body>
</html>
<?php
    $html = ob_get_clean();

    /* =====================================================
       5. RENDER PDF
    ===================================================== */
    $dompdf = new Dompdf(new Options([
        'isRemoteEnabled' => true,
        'chroot' => __DIR__,
    ]));
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $dir = __DIR__ . '/uploads/contracts';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $path = $dir . "/contract_{$contractId}.pdf";
    file_put_contents($path, $dompdf->output());

    return $path;
}
