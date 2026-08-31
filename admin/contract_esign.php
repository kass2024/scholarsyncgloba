<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Serve a signed PhD contract PDF by database id.
 */
if (isset($_GET['download'])) {
    $id = (int) $_GET['download'];
    if ($id > 0) {
        $stmt = $conn->prepare('SELECT student_name, pdf_file FROM signed_contracts WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->bind_result($studentName, $pdfFile);
            if ($stmt->fetch()) {
                $stmt->close();
                $relative = ltrim(str_replace('\\', '/', (string) $pdfFile), '/');
                if (preg_match('#^admin/generated_contracts/signed_phd_contract_[\w.\-]+\.pdf$#i', $relative)) {
                    $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                    if (is_file($fullPath)) {
                        $safeName = preg_replace('/[^\w\- ]+/', '_', trim((string) $studentName));
                        if ($safeName === '') {
                            $safeName = 'signed_phd_contract';
                        }
                        $safeName .= '.pdf';

                        header('Content-Type: application/pdf');
                        header('Content-Disposition: attachment; filename="' . $safeName . '"');
                        header('Content-Length: ' . (string) filesize($fullPath));
                        header('Cache-Control: private, max-age=0, must-revalidate');
                        readfile($fullPath);
                        exit;
                    }
                }
            } else {
                $stmt->close();
            }
        }
    }

    http_response_code(404);
    exit('Contract PDF not found.');
}

$signedContracts = [];
$contractsResult = $conn->query(
    'SELECT id, student_name, student_title, contract_date, pdf_file, created_at
     FROM signed_contracts
     ORDER BY created_at DESC'
);
if ($contractsResult) {
    $signedContracts = $contractsResult->fetch_all(MYSQLI_ASSOC);
    $contractsResult->free();
}

$headerPath = __DIR__ . '/header.png';
$footerPath = __DIR__ . '/footer.png';
$employerSignaturePath = __DIR__ . '/employer-signature.png';

function clean($v) {
    return htmlspecialchars(trim($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function img64($path) {
    if (!file_exists($path)) return '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($path));
}

$headerImg = img64($headerPath);
$footerImg = img64($footerPath);
$employerSignature = img64($employerSignaturePath);

function buildContractHtml($data, $headerImg, $footerImg, $employerSignature, $studentSignature = '') {
return '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page {
    margin: 20px 25px 25px 25px;
}

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 11.8px;
    color: #111827;
    line-height: 1.45;
    margin: 0;
}

.header img,
.footer img {
    width: 100%;
    display: block;
}

.content {
    padding: 18px 28px;
}

.title {
    text-align: center;
    color: #073b77;
    font-size: 19px;
    font-weight: bold;
    text-transform: uppercase;
    margin: 16px 0 18px;
}

.intro {
    background: #eef5ff;
    border-left: 5px solid #073b77;
    padding: 10px 13px;
    border-radius: 6px;
    margin-bottom: 14px;
}

.section {
    margin-top: 11px;
}

.section-title {
    background: #073b77;
    color: white;
    font-weight: bold;
    padding: 7px 10px;
    border-radius: 5px;
    margin-bottom: 7px;
}

.sub-title {
    color: #073b77;
    font-weight: bold;
    margin: 8px 0 4px;
}

ul {
    margin: 4px 0 7px 18px;
    padding: 0;
}

li {
    margin-bottom: 3px;
}

.signature-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 12px 0;
    margin-top: 18px;
    page-break-inside: avoid;
}

.signature-box {
    width: 50%;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 13px;
    vertical-align: top;
    min-height: 175px;
    background: #fbfdff;
}

.sig-title {
    color: #073b77;
    font-weight: bold;
    text-transform: uppercase;
    margin-bottom: 10px;
}

.signature-img {
    height: 62px;
    max-width: 210px;
    margin: 8px 0;
    display: block;
}

.footer {
    margin-top: 18px;
}
</style>
</head>

<body>

<div class="header">'.($headerImg ? '<img src="'.$headerImg.'">' : '').'</div>

<div class="content">

<div class="title">Contract for Masters & PhD Application Support Services</div>

<div class="intro">
This Contract is made and entered into on <b>'.$data['contract_date'].'</b>, by and between:
</div>

<div class="section">
<div class="section-title">1. Parties</div>
<p>
<b>SCHOLARSYNC GLOBAL CO. LTD</b><br>
A registered consulting company, hereinafter referred to as the <b>“Consultant”</b>,
</p>

<p>
AND<br>
<b>'.$data['student_name'].'</b><br>
Hereinafter referred to as the <b>“PhD-Level Student / Applicant.”</b>
</p>

<p>
The Consultant and the PhD-Level Student / Applicant may collectively be referred to as the <b>“Parties.”</b>
</p>
</div>

<div class="section">
<div class="section-title">2. Purpose of the Contract</div>
<p>
The purpose of this Contract is to define the terms and conditions under which the PhD-Level Student / Applicant
shall receive support services related to MSc & PhD applications, including searching for MSc & PhD supervisors,
research proposal preparation, monitoring communications, preparing applications, and assisting until the MSc & PhD
supervisor and visa are successfully obtained.
</p>
</div>

<div class="section">
<div class="section-title">3. Scope of Services</div>

<p class="sub-title">3.1 MSc & PhD Supervisor Search</p>
<ul>
<li>Identify and search for suitable MSc & PhD supervisors in universities and research institutions in Canada, USA, Asia, and Europe.</li>
<li>Review university requirements and research opportunities relevant to the Student / Applicant.</li>
</ul>

<p class="sub-title">3.2 Research Proposal Preparation</p>
<ul>
<li>Assist the Student / Applicant in preparing and improving research proposals.</li>
<li>Support editing, formatting, and aligning proposals with university and supervisor requirements.</li>
</ul>

<p class="sub-title">3.3 Email Monitoring and Communication</p>
<ul>
<li>Monitor candidate-related emails and communications where authorized.</li>
<li>Respond to emails professionally on behalf of the Student / Applicant when authorized.</li>
<li>Follow up with professors, universities, and admissions offices.</li>
</ul>

<p class="sub-title">3.4 Application Support</p>
<ul>
<li>Assist in preparing and submitting MSc & PhD applications.</li>
<li>Upload and organize required documents.</li>
<li>Ensure applications are submitted within deadlines.</li>
</ul>

<p class="sub-title">3.5 Follow-Up Services</p>
<ul>
<li>Continue communication and follow-up until a MSc & PhD supervisor is secured.</li>
<li>Continue support until the visa process is completed successfully.</li>
</ul>

<p class="sub-title">3.6 Reporting</p>
<ul>
<li>Provide regular updates regarding progress, communications, and application status.</li>
</ul>
</div>

<div class="section">
<div class="section-title">4. Responsibilities of the Consultant</div>
<ul>
<li>Provide necessary guidance regarding application requirements.</li>
<li>Guide and supervise the application support process where necessary.</li>
<li>Ensure support services are delivered professionally.</li>
<li>Ensure compliance with legal and ethical standards in all applications.</li>
</ul>
</div>

<div class="section">
<div class="section-title">5. Responsibilities of the PhD-Level Student / Applicant</div>
<ul>
<li>Provide accurate personal, academic, and professional information.</li>
<li>Provide all required documents on time.</li>
<li>Cooperate with the Consultant during supervisor search, application preparation, and visa processing.</li>
<li>Review and approve application materials where required.</li>
<li>Maintain honest communication throughout the process.</li>
</ul>
</div>

<div class="section">
<div class="section-title">6. Compensation</div>
<ul>
<li>A non-refundable upfront service fee of <b>30,000 RWF</b> shall be paid before starting the application process for each person.</li>
<li>An additional success fee of <b>200 USD</b> shall be paid once a PhD supervisor and visa are successfully obtained.</li>
<li>These services apply to MSc & PhD opportunities in Canada, USA, Asia, and Europe.</li>
<li>All payments shall be made according to the agreed payment method between the Parties.</li>
</ul>
</div>

<div class="section">
<div class="section-title">7. Confidentiality</div>
<p>The Parties agree to maintain strict confidentiality regarding:</p>
<ul>
<li>Applicant information,</li>
<li>University communications,</li>
<li>Login credentials,</li>
<li>Personal and academic documents,</li>
<li>Any internal business information of the Consultant.</li>
</ul>
<p>
Confidential information shall not be disclosed to third parties without written authorization, except where required by law or necessary for application and visa processing.
</p>
</div>

<div class="section">
<div class="section-title">8. Term of Contract</div>
<p>This Contract shall commence on <b>'.$data['start_date'].'</b> and shall remain in effect until:</p>
<ul>
<li>A MSc & PhD supervisor is secured and visa processing is completed; or</li>
<li>The Contract is terminated by either Party in writing.</li>
</ul>
</div>

<div class="section">
<div class="section-title">9. Termination</div>
<p>Either Party may terminate this Contract by providing written notice of 30 days.</p>
<p>The Consultant reserves the right to terminate the Contract immediately in cases of:</p>
<ul>
<li>Misconduct,</li>
<li>Breach of confidentiality,</li>
<li>Negligence,</li>
<li>Fraudulent activities,</li>
<li>Failure to provide required information or documents,</li>
<li>Failure to respect agreed payment terms.</li>
</ul>
</div>

<div class="section">
<div class="section-title">10. Independent Service Relationship</div>
<p>
This Contract does not create an employment relationship between the Student / Applicant and SCHOLARSYNC GLOBAL CO. LTD.
The Consultant provides application support services, and the Student / Applicant remains responsible for the accuracy of all personal and academic information submitted.
</p>
</div>

<div class="section">
<div class="section-title">11. Governing Law</div>
<p>
This Contract shall be governed and interpreted in accordance with the laws of <b>'.$data['governing_law'].'</b>.
</p>
</div>

<div class="section">
<div class="section-title">12. Entire Agreement</div>
<p>
This Contract constitutes the entire agreement between the Parties and supersedes all prior discussions or agreements relating to the subject matter herein.
</p>
</div>

<div class="section">
<div class="section-title">13. Signatures</div>
<p>IN WITNESS WHEREOF, the Parties have executed this Contract on the date first written above.</p>

<table class="signature-table">
<tr>
<td class="signature-box">
<div class="sig-title">For ScholarSync Global Co. Ltd</div>
Name: Dr. Jean Pierre TWAJAMAHORO<br>
Title: OWNER AND MANAGING DIRECTOR<br>
Signature:<br>
'.($employerSignature ? '<img class="signature-img" src="'.$employerSignature.'">' : '____________________________').'
Date: '.$data['contract_date'].'
</td>

<td class="signature-box">
<div class="sig-title">PhD-Level Student / Applicant</div>
Name: '.$data['student_name'].'<br>
Title: '.$data['student_title'].'<br>
Signature:<br>
'.($studentSignature ? '<img class="signature-img" src="'.$studentSignature.'">' : '____________________________').'
Date: '.$data['contract_date'].'
</td>
</tr>
</table>

</div>

</div>

<div class="footer">'.($footerImg ? '<img src="'.$footerImg.'">' : '').'</div>

</body>
</html>';
}

$success = false;
$pdfLink = '';
$lastContractId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'student_name' => clean($_POST['student_name']),
        'student_title' => clean($_POST['student_title']),
        'contract_date' => clean($_POST['contract_date']),
        'start_date' => clean($_POST['start_date']),
        'governing_law' => clean($_POST['governing_law'])
    ];

    $studentSignature = $_POST['student_signature'] ?? '';

    if (!$studentSignature) {
        die('Student signature is required.');
    }

    $html = buildContractHtml($data, $headerImg, $footerImg, $employerSignature, $studentSignature);

    $folder = __DIR__ . '/generated_contracts/';
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $fileName = 'signed_phd_contract_' . date('Ymd_His') . '.pdf';
    $filePath = $folder . $fileName;
    $dbFilePath = 'admin/generated_contracts/' . $fileName;

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    file_put_contents($filePath, $dompdf->output());

    $stmt = $conn->prepare("
        INSERT INTO signed_contracts
        (student_name, student_title, contract_date, start_date, governing_law, pdf_file)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssssss",
        $data['student_name'],
        $data['student_title'],
        $data['contract_date'],
        $data['start_date'],
        $data['governing_law'],
        $dbFilePath
    );

    $stmt->execute();
    $lastContractId = (int) $conn->insert_id;

    $success = true;
    $pdfLink = 'contract_esign.php?download=' . $lastContractId;
}

$previewData = [
    'student_name' => 'Mr. Karangwa Emmanuel',
    'student_title' => 'PhD Candidate in Mechanical Engineering',
    'contract_date' => date('Y-m-d'),
    'start_date' => date('Y-m-d'),
    'governing_law' => 'Laws of Rwanda'
];

$previewHtml = buildContractHtml($previewData, $headerImg, $footerImg, $employerSignature, '');
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>ScholarSync Global Contract E-Sign</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #eef4fb;
    font-family: Arial, sans-serif;
    color: #111827;
}

.topbar {
    background: linear-gradient(135deg, #073b77, #0b5eb8);
    padding: 18px 32px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.topbar h1 {
    margin: 0;
    font-size: 22px;
}

.badge {
    background: white;
    color: #073b77;
    padding: 8px 15px;
    border-radius: 30px;
    font-weight: bold;
}

.layout {
    max-width: 1450px;
    margin: 28px auto;
    padding: 0 22px;
    display: grid;
    grid-template-columns: 420px 1fr;
    gap: 28px;
}

.card {
    background: white;
    border-radius: 18px;
    box-shadow: 0 12px 35px rgba(15, 23, 42, .12);
    overflow: hidden;
}

.form-card {
    position: sticky;
    top: 20px;
    height: fit-content;
}

.card-header {
    background: #073b77;
    color: white;
    padding: 20px 24px;
}

.card-header h2 {
    margin: 0;
    font-size: 20px;
}

.card-header p {
    margin: 6px 0 0;
    font-size: 13px;
    opacity: .9;
}

.form-body {
    padding: 24px;
}

label {
    font-weight: bold;
    display: block;
    margin-top: 14px;
    color: #334155;
}

input {
    width: 100%;
    padding: 13px;
    margin-top: 7px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    font-size: 14px;
}

input:focus {
    outline: none;
    border-color: #073b77;
    box-shadow: 0 0 0 3px rgba(7, 59, 119, .14);
}

.signature-panel {
    margin-top: 8px;
    border: 2px dashed #073b77;
    border-radius: 14px;
    padding: 10px;
    background: #f8fbff;
}

canvas {
    width: 100%;
    height: 175px;
    background: white;
    border-radius: 10px;
    cursor: crosshair;
}

button,
.download-btn {
    display: block;
    width: 100%;
    border: none;
    text-align: center;
    text-decoration: none;
    padding: 14px;
    border-radius: 12px;
    font-weight: bold;
    font-size: 15px;
    cursor: pointer;
    margin-top: 12px;
}

.btn-primary,
.download-btn {
    background: #073b77;
    color: white;
}

.btn-clear {
    background: #fee2e2;
    color: #991b1b;
}

.success {
    background: #dcfce7;
    color: #166534;
    padding: 14px;
    border-radius: 12px;
    margin-bottom: 16px;
    font-weight: bold;
}

.preview-toolbar {
    padding: 16px 22px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
}

.preview-toolbar strong {
    color: #073b77;
}

.contract-preview-inner {
    transform: scale(.92);
    transform-origin: top center;
}

.signed-list {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.signed-list h3 {
    margin: 0 0 12px;
    color: #073b77;
    font-size: 16px;
}

.signed-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.signed-table th,
.signed-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: top;
}

.signed-table th {
    color: #475569;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .03em;
}

.btn-download-sm {
    display: inline-block;
    width: auto;
    padding: 8px 12px;
    margin-top: 0;
    font-size: 13px;
    border-radius: 8px;
}

.empty-signed {
    color: #64748b;
    font-size: 13px;
}

@media(max-width: 1000px) {
    .layout {
        grid-template-columns: 1fr;
    }

    .form-card {
        position: relative;
    }
}
</style>
</head>

<body>

<div class="topbar">
    <h1>ScholarSync Global Contract E-Sign System</h1>
    <div class="badge">MSc & PhD Contract</div>
</div>

<div class="layout">

<div class="card form-card">
    <div class="card-header">
        <h2>Student Contract Details</h2>
        <p>Sign, save to database, then download the signed PDF.</p>
    </div>

    <div class="form-body">

        <?php if ($success): ?>
            <div class="success">Contract saved successfully.</div>
            <a class="download-btn" href="<?= $pdfLink ?>" download>Download Signed Contract</a>
            <a class="download-btn" href="contract_esign.php">Create New Contract</a>
        <?php else: ?>

        <form method="POST" onsubmit="return saveSignature();">
            <label>Student / Applicant Name</label>
            <input type="text" name="student_name" value="Mr. Karangwa Emmanuel" required>

            <label>Student / Applicant Title</label>
            <input type="text" name="student_title" value="PhD Candidate in Mechanical Engineering" required>

            <label>Contract Date</label>
            <input type="date" name="contract_date" value="<?= date('Y-m-d') ?>" required>

            <label>Start Date</label>
            <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>

            <label>Governing Law</label>
            <input type="text" name="governing_law" value="Laws of Rwanda" required>

            <label>Student / Applicant Signature</label>
            <div class="signature-panel">
                <canvas id="signaturePad"></canvas>
            </div>

            <input type="hidden" name="student_signature" id="student_signature">

            <button type="button" class="btn-clear" onclick="clearSignature()">Clear Signature</button>
            <button type="submit" class="btn-primary">Save Signed Contract</button>
        </form>

        <?php endif; ?>

        <div class="signed-list">
            <h3>Signed PhD Contracts</h3>
            <?php if ($signedContracts === []): ?>
                <div class="empty-signed">No signed contracts saved yet.</div>
            <?php else: ?>
                <table class="signed-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Date</th>
                            <th>Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($signedContracts as $contract): ?>
                            <tr>
                                <td>
                                    <strong><?= clean($contract['student_name']) ?></strong><br>
                                    <span style="color:#64748b;"><?= clean($contract['student_title']) ?></span>
                                </td>
                                <td><?= clean($contract['contract_date']) ?></td>
                                <td>
                                    <?php if (!empty($contract['pdf_file'])): ?>
                                        <a
                                            class="download-btn btn-download-sm"
                                            href="contract_esign.php?download=<?= (int) $contract['id'] ?>"
                                        >Download PDF</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<div class="card">
    <div class="preview-toolbar">
        <strong>Contract Preview</strong>
        <span>PDF includes header and footer</span>
    </div>

    <div class="contract-preview-inner">
        <?= $previewHtml ?>
    </div>
</div>

</div>

<script>
const canvas = document.getElementById('signaturePad');

if (canvas) {
    const ctx = canvas.getContext('2d');
    let drawing = false;
    let signed = false;

    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = 175 * ratio;
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineWidth = 2.4;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#111827';
    }

    resizeCanvas();

    function pos(e) {
        const rect = canvas.getBoundingClientRect();
        if (e.touches && e.touches.length > 0) {
            return {
                x: e.touches[0].clientX - rect.left,
                y: e.touches[0].clientY - rect.top
            };
        }
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
    }

    function start(e) {
        e.preventDefault();
        drawing = true;
        signed = true;
        const p = pos(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }

    function draw(e) {
        if (!drawing) return;
        e.preventDefault();
        const p = pos(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
    }

    function stop() {
        drawing = false;
        ctx.beginPath();
    }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stop);
    canvas.addEventListener('mouseleave', stop);

    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', stop);

    window.clearSignature = function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        signed = false;
    }

    window.saveSignature = function() {
        if (!signed) {
            alert('Please draw the Student / Applicant signature first.');
            return false;
        }

        document.getElementById('student_signature').value = canvas.toDataURL('image/png');
        return true;
    }
}
</script>

</body>
</html>