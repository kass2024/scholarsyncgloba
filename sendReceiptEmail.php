<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$LOG = $logDir . '/receipt_email.log';

function logMsg(string $msg, $data = null): void
{
    global $LOG;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data !== null) {
        $line .= ' :: ' . (is_scalar($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    @file_put_contents($LOG, $line . PHP_EOL, FILE_APPEND);
}

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err !== null) {
        logMsg('FATAL ERROR', $err);
    }
});

logMsg('========== EMAIL ENDPOINT START ==========');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'reason' => 'method']);
    exit;
}

$secret = $_POST['secret'] ?? '';
if ($secret !== 'RCP_9fA8kKx_2026_SECURE') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'reason' => 'secret']);
    exit;
}

$receiptNo = trim((string) ($_POST['receipt_no'] ?? ''));
if ($receiptNo === '') {
    echo json_encode(['status' => 'error', 'reason' => 'receipt_no']);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/receipt_render.php';
require_once __DIR__ . '/generateReceiptPdf.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

$data = pcvc_load_receipt_data($conn, $receiptNo);
if (!$data || empty($data['customer_email'])) {
    logMsg('Receipt or email not found', $receiptNo);
    echo json_encode(['status' => 'error', 'reason' => 'not_found']);
    exit;
}

// Credit Transfer / UPAFA receipts are admin-notify only — never email the student.
$sourceTable = (string) ($data['source_table'] ?? '');
if (in_array($sourceTable, ['credit_transfer_applications', 'upafa_registrations'], true)) {
    logMsg('Blocked student email for special program receipt', [
        'receipt' => $receiptNo,
        'table'   => $sourceTable,
    ]);
    echo json_encode(['status' => 'skipped', 'reason' => 'special_program_no_student_email']);
    exit;
}

$pdfPath = __DIR__ . '/receipts/' . $receiptNo . '.pdf';
if (!is_file($pdfPath)) {
    try {
        $pdfPath = generateReceiptPdf($receiptNo, $conn);
    } catch (Throwable $e) {
        logMsg('PDF generation failed', $e->getMessage());
        echo json_encode(['status' => 'error', 'reason' => 'pdf_missing']);
        exit;
    }
}

if (!is_file($pdfPath)) {
    echo json_encode(['status' => 'error', 'reason' => 'pdf_missing']);
    exit;
}

$studentName = $data['customer_name'];
$currency    = $data['currency'] ?: '';
$totalPaid   = number_format((float) $data['total_amount'], 2);
$method      = htmlspecialchars($data['payment_method'], ENT_QUOTES, 'UTF-8');
$package     = htmlspecialchars($data['package_title'], ENT_QUOTES, 'UTF-8');
$receiptEsc  = htmlspecialchars($receiptNo, ENT_QUOTES, 'UTF-8');
$nameEsc     = htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8');

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'scholarsyncglobal.ca';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'infos@scholarsyncglobal.ca';
    $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';
    $mail->isHTML(true);

    $mail->setFrom(
        'infos@scholarsyncglobal.ca',
        'ScholarSync Global – Finance'
    );
    $mail->addAddress($data['customer_email'], $studentName);

    $mail->Subject = 'Official Payment Receipt – ' . $receiptNo;
    $mail->Body = '
    <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1f2933;max-width:640px;margin:0 auto;">
      <div style="background:linear-gradient(135deg,#0b3c5d,#14629c);color:#fff;padding:18px 22px;border-radius:10px 10px 0 0;">
        <div style="font-size:11px;letter-spacing:1.5px;text-transform:uppercase;opacity:.85;">ScholarSync Global</div>
        <div style="font-size:20px;font-weight:800;margin-top:6px;">Official Payment Receipt</div>
      </div>
      <div style="border:1px solid #e5e7eb;border-top:0;padding:22px;border-radius:0 0 10px 10px;background:#fff;">
        <p>Dear <strong>' . $nameEsc . '</strong>,</p>
        <p>Thank you for your payment. Your official receipt is attached as a PDF (A4 format) and matches the printed copy issued by our office.</p>
        <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;">
          <tr><td style="padding:8px 0;color:#6b7280;width:140px;"><strong>Receipt No.</strong></td><td style="padding:8px 0;">' . $receiptEsc . '</td></tr>
          <tr><td style="padding:8px 0;color:#6b7280;"><strong>Package</strong></td><td style="padding:8px 0;">' . $package . '</td></tr>
          <tr><td style="padding:8px 0;color:#6b7280;"><strong>Amount Paid</strong></td><td style="padding:8px 0;font-weight:700;color:#0b3c5d;">' . htmlspecialchars($currency . ' ' . $totalPaid, ENT_QUOTES, 'UTF-8') . '</td></tr>
          <tr><td style="padding:8px 0;color:#6b7280;"><strong>Payment Method</strong></td><td style="padding:8px 0;">' . $method . '</td></tr>
        </table>
        <p style="font-size:12px;color:#6b7280;line-height:1.5;">
          Please keep this receipt for your records. The attached document includes our authorized signature stamp.
          For questions, reply to this email or contact <a href="mailto:infos@scholarsyncglobal.ca">infos@scholarsyncglobal.ca</a>.
        </p>
        <p style="margin-top:20px;font-size:12px;color:#9ca3af;">
          ScholarSync Global · Finance Office<br>
          scholarsyncglobal.ca
        </p>
      </div>
    </div>';

    $mail->addAttachment($pdfPath, $receiptNo . '.pdf');
    $mail->send();

    logMsg('Email sent', $receiptNo);
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    logMsg('Email exception', $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'reason' => 'mail']);
}

$conn->close();
exit;
