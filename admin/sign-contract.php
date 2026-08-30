<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* =====================================================
   AUTH & METHOD CHECK
===================================================== */
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request');
}

$adminId = (int) $_SESSION['admin_id'];
$ip = $_SERVER['REMOTE_ADDR'];

/* =====================================================
   FETCH CONTRACT (LOCK TARGET)
===================================================== */
$sql = "SELECT * FROM employment_contracts WHERE admin_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();

if (!$contract) {
    http_response_code(404);
    exit('Contract not found');
}

if ($contract['status'] === 'signed') {
    exit('Contract already signed');
}

/* =====================================================
   FETCH ADMIN
===================================================== */
$sql = "SELECT * FROM admins WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin) {
    exit('Admin not found');
}

/* =====================================================
   FETCH RESPONSIBILITIES
===================================================== */
$tasks = [];
$sql = "SELECT task_name FROM staff_tasks WHERE staff_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}

/* =====================================================
   FETCH EMPLOYEE SIGNATURE (BASE64)
===================================================== */
$sql = "
    SELECT typed_name, signature_base64
    FROM admin_signatures
    WHERE admin_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$signature = $stmt->get_result()->fetch_assoc();

if (!$signature || empty($signature['signature_base64'])) {
    exit('Signature image required before signing');
}

/* =====================================================
   PREPARE SIGNATURE FOR TEMPLATE
===================================================== */
$signatureHtml = '
<img src="' . $signature['signature_base64'] . '"
     style="width:220px;height:auto;margin-top:10px;">
';

$hasSignature = true;
$signDate = date('Y-m-d');

/* =====================================================
   PDF MODE FLAG (IMPORTANT)
===================================================== */
$isPdf = true;

/* =====================================================
   RENDER CONTRACT HTML
===================================================== */
ob_start();
include __DIR__ . '/../contracts/contract_template.php';
$html = ob_get_clean();

/* =====================================================
   GENERATE PDF (DOMPDF)
===================================================== */
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

/* =====================================================
   SAVE PDF (IMMUTABLE)
===================================================== */
$pdfDir = $_SERVER['DOCUMENT_ROOT'] . '/contracts/signed/';
if (!is_dir($pdfDir)) {
    mkdir($pdfDir, 0755, true);
}

$pdfPath = '/contracts/signed/contract_' . $adminId . '.pdf';
file_put_contents($_SERVER['DOCUMENT_ROOT'] . $pdfPath, $dompdf->output());

/* =====================================================
   FINALIZE CONTRACT (LOCK)
===================================================== */
$sql = "
    UPDATE employment_contracts
    SET status = 'signed',
        signed_at = NOW(),
        signed_ip = ?,
        pdf_path = ?
    WHERE admin_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $ip, $pdfPath, $adminId);
$stmt->execute();

/* =====================================================
   AUDIT LOG
===================================================== */
$sql = "
    INSERT INTO contract_audit_logs (contract_id, action, ip_address)
    VALUES (?, 'SIGNED', ?)
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $contract['id'], $ip);
$stmt->execute();

/* =====================================================
   REDIRECT BACK
===================================================== */
header("Location: contract.php");
exit;
