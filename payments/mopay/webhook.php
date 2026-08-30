<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../helpers/mopay_wallet_transactions.php';
$cfg = require __DIR__ . '/config.php';

// Ensure logs directory exists and is writable.
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}
$debugLog = $logsDir . '/webhook.log.jsonl';

// Quick routing test (browser ping).
// Visit: /payments/mopay/webhook.php?ping=1
// This should ALWAYS create a log line in `payments/mopay/logs/webhook.log.jsonl`.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['ping'])) {
    @file_put_contents($debugLog, json_encode([
        'type' => 'webhook_ping',
        'time' => date('c'),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'host' => $_SERVER['HTTP_HOST'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => true,
        'message' => 'webhook ping ok',
        'log_path' => $debugLog,
        'cwd' => getcwd(),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// Webhook receives a JWT string in the raw body (per the PDF).
$raw = trim((string)file_get_contents('php://input'));
if ($raw === '') {
    @file_put_contents($debugLog, json_encode([
        'type' => 'webhook_raw_received',
        'time' => date('c'),
        'ok' => false,
        'reason' => 'empty_body',
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'host' => $_SERVER['HTTP_HOST'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 400, 'message' => 'Empty webhook body', 'transactionId' => null]);
    exit;
}

// Some gateways might send JSON, but PDF says raw JWT. Support both.
$jwt = $raw;
// Handle optional "Bearer <jwt>" wrapper
if (stripos($jwt, 'bearer ') === 0) {
    $jwt = trim(substr($jwt, 7));
}

if (strpos($raw, '{') === 0) {
    $maybeJson = json_decode($raw, true);
    if (is_array($maybeJson)) {
        $jwt = $maybeJson['jwt'] ?? $maybeJson['token'] ?? $raw;
    }
}

// Always log that we received something (before any early exits).
@file_put_contents($debugLog, json_encode([
    'type' => 'webhook_raw_received',
    'time' => date('c'),
    'ok' => true,
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
    'host' => $_SERVER['HTTP_HOST'] ?? null,
    'uri' => $_SERVER['REQUEST_URI'] ?? null,
    'body_prefix' => substr($raw, 0, 80) . (strlen($raw) > 80 ? '...' : ''),
], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);

$secret = (string)($cfg['callback_signing_key'] ?? '');
if ($secret === '') {
    @file_put_contents($debugLog, json_encode([
        'type' => 'webhook_config_error',
        'time' => date('c'),
        'error' => 'missing_callback_signing_key',
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 500, 'message' => 'Webhook secret not configured', 'transactionId' => null]);
    exit;
}

$payload = null;
try {
    $payload = verify_jwt_hs256($jwt, $secret);
} catch (Throwable $e) {
    @file_put_contents($debugLog, json_encode([
        'type' => 'webhook_jwt_error',
        'time' => date('c'),
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 401, 'message' => 'JWT verification failed: ' . $e->getMessage(), 'transactionId' => null]);
    exit;
}

// MoPay sometimes wraps the actual object as a JSON string in `payload.data`.
// Normalize to an array when possible so downstream logic can work reliably.
$data = $payload['data'] ?? null;
if (is_string($data) && $data !== '' && (strpos(ltrim($data), '{') === 0 || strpos(ltrim($data), '[') === 0)) {
    $decodedData = json_decode($data, true);
    if (is_array($decodedData)) {
        $data = $decodedData;
        $payload['data'] = $decodedData;
    }
}

$transactionId = is_array($data) ? ($data['transactionId'] ?? ($data['referenceId'] ?? null)) : null;
$status = is_array($data) ? ($data['status'] ?? null) : null;
$amount = is_array($data) ? ($data['amount'] ?? null) : null;
$currency = is_array($data) ? ($data['currency'] ?? null) : null;

// MoPay tests the webhook during "save callback url".
// Their validation requires returning HTTP 404 when the transaction is not found.
// Since we don't have your DB wiring yet, we treat a transaction as "found"
// only if we've seen that transactionId in our local log file.
function mopay_transaction_known(string $transactionId): bool
{
    $logFile = __DIR__ . '/storage/transactions.jsonl';
    if (!file_exists($logFile)) {
        return false;
    }
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return false;
    }
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            continue;
        }
        if (isset($decoded['transactionId']) && (string)$decoded['transactionId'] === $transactionId) {
            return true;
        }
        if (isset($decoded['transferTransactionId']) && (string)$decoded['transferTransactionId'] === $transactionId) {
            return true;
        }
    }
    return false;
}

function mopay_is_success_status($status): bool
{
    // Gateway may send either a string status or a numeric 200-ish status.
    if (is_int($status) || (is_string($status) && ctype_digit(trim($status)))) {
        return (int)$status >= 200 && (int)$status < 300;
    }
    if (!is_string($status)) return false;
    $s = strtoupper(trim($status));
    return in_array($s, ['SUCCESS', 'SUCCESSFUL', 'SUCCEEDED', 'COMPLETED', 'PAID', 'APPROVED', 'OK'], true);
}

function mopay_find_fee_meta(string $transactionId): ?array
{
    $logFile = __DIR__ . '/storage/transactions.jsonl';
    if (!file_exists($logFile)) return null;
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return null;
    // Scan from end for latest metadata
    $lines = array_reverse($lines);
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) continue;
        if (($decoded['type'] ?? '') !== 'debit_initiate') continue;
        if ((string)($decoded['transactionId'] ?? '') !== $transactionId) continue;
        if (!isset($decoded['fee_checkout_meta']) || !is_array($decoded['fee_checkout_meta'])) continue;
        return $decoded['fee_checkout_meta'];
    }
    return null;
}

if (!function_exists('generateReceiptHtml')) {
    /**
     * Fallback receipt HTML generator for webhook finalization flow.
     * Keeps the same input shape used by existing code.
     */
    function generateReceiptHtml(array $data): string
    {
        $receiptNo = htmlspecialchars((string)($data['receipt_no'] ?? ''), ENT_QUOTES, 'UTF-8');
        $studentId = (int)($data['student_id'] ?? 0);
        $method = htmlspecialchars((string)($data['method'] ?? 'MOPAY'), ENT_QUOTES, 'UTF-8');
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $total = (float)($data['total'] ?? 0);

        $rows = '';
        foreach ($items as $idx => $item) {
            $label = htmlspecialchars((string)($item['label'] ?? ('Item ' . ($idx + 1))), ENT_QUOTES, 'UTF-8');
            $amount = number_format((float)($item['amount'] ?? 0), 2);
            $rows .= '<tr>'
                . '<td style="padding:8px;border-bottom:1px solid #e5e7eb;">' . ($idx + 1) . '</td>'
                . '<td style="padding:8px;border-bottom:1px solid #e5e7eb;">' . $label . '</td>'
                . '<td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">' . $amount . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="3" style="padding:8px;border-bottom:1px solid #e5e7eb;color:#6b7280;">No items</td></tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Receipt ' . $receiptNo . '</title></head><body style="font-family:Arial,sans-serif;color:#111827;">'
            . '<h2 style="margin:0 0 10px;">Payment Receipt</h2>'
            . '<p style="margin:0 0 6px;"><strong>Receipt No:</strong> ' . $receiptNo . '</p>'
            . '<p style="margin:0 0 6px;"><strong>Student ID:</strong> ' . $studentId . '</p>'
            . '<p style="margin:0 0 14px;"><strong>Payment Method:</strong> ' . $method . '</p>'
            . '<table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;">'
            . '<thead><tr style="background:#f8fafc;"><th style="padding:8px;text-align:left;">#</th><th style="padding:8px;text-align:left;">Item</th><th style="padding:8px;text-align:right;">Amount</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '<tfoot><tr style="background:#f8fafc;"><td colspan="2" style="padding:8px;text-align:right;"><strong>Total</strong></td><td style="padding:8px;text-align:right;"><strong>' . number_format($total, 2) . '</strong></td></tr></tfoot>'
            . '</table>'
            . '</body></html>';
    }
}

function mopay_finalize_fee_payment(mysqli $conn, string $transactionId, array $meta): ?string
{
    // Uses the existing record-payment logic shape, but runs inline.
    require_once __DIR__ . '/../../generateReceiptPdf.php';
    require_once __DIR__ . '/../../helpers/mailer.php';

    $studentId = (int)($meta['student_id'] ?? 0);
    $packageId = (int)($meta['package_id'] ?? 0);
    $items = $meta['items'] ?? [];
    if ($studentId <= 0 || $packageId <= 0 || !is_array($items) || empty($items)) {
        @file_put_contents(__DIR__ . '/logs/webhook.log.jsonl', json_encode([
            'type' => 'webhook_finalize_debug',
            'time' => date('c'),
            'transactionId' => $transactionId,
            'stage' => 'meta_validation_failed',
            'student_id' => $studentId,
            'package_id' => $packageId,
            'items_count' => is_array($items) ? count($items) : 0,
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        return null;
    }

    // Prevent double-finalization: if a receipt already exists for this transactionId, skip.
    // If your schema doesn't include gateway ids, we fall back to checking recent receipts.
    // (We store the transactionId in receipt_html for traceability.)

    $conn->begin_transaction();
    try {
        // Ensure package assignment exists.
        $sourceTable = 'student_applications';
        $stmt = $conn->prepare("SELECT id FROM application_packages WHERE application_id = ? AND source_table = ? AND package_id = ? LIMIT 1");
        $stmt->bind_param('isi', $studentId, $sourceTable, $packageId);
        $stmt->execute();
        $assigned = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$assigned) {
            $stmt = $conn->prepare("INSERT INTO application_packages (application_id, source_table, package_id, assigned_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param('isi', $studentId, $sourceTable, $packageId);
            $stmt->execute();
            $stmt->close();
        }

        $totalRecorded = 0.0;
        $receiptItems = [];
        $itemsMap = [];

        foreach ($items as $row) {
            if (!is_array($row)) continue;
            $feeItemId = (int)($row['id'] ?? 0);
            $amount = round((float)($row['amount'] ?? 0), 2); // amount in package currency (remaining)
            if ($feeItemId <= 0 || $amount <= 0) continue;
            $itemsMap[$feeItemId] = $amount;
        }
        if (empty($itemsMap)) {
            throw new RuntimeException('No payable items');
        }

        foreach ($itemsMap as $feeItemId => $amount) {
            // Validate fee item belongs to package
            $stmt = $conn->prepare("SELECT name, amount FROM fee_items WHERE id = ? AND package_id = ? LIMIT 1");
            $stmt->bind_param('ii', $feeItemId, $packageId);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$item) {
                throw new RuntimeException('Fee item not found');
            }

            // Overpayment protection (same as record-payment.php)
            $stmt = $conn->prepare("
              SELECT COALESCE(SUM(amount_paid),0)
              FROM application_payments
              WHERE application_id = ? AND source_table = ? AND fee_item_id = ? AND status = 'PAID'
            ");
            $stmt->bind_param('isi', $studentId, $sourceTable, $feeItemId);
            $stmt->execute();
            $stmt->bind_result($alreadyPaid);
            $stmt->fetch();
            $stmt->close();
            if (($alreadyPaid + $amount) > (float)$item['amount']) {
                throw new RuntimeException('Overpayment detected');
            }

            $method = 'MOPAY';
            $comment = 'MoPay txn: ' . $transactionId;
            $stmt = $conn->prepare("
              INSERT INTO application_payments
              (application_id, source_table, fee_item_id, amount_paid, payment_method, payment_comment, status, paid_at)
              VALUES (?, ?, ?, ?, ?, ?, 'PAID', NOW())
            ");
            $stmt->bind_param('isidss', $studentId, $sourceTable, $feeItemId, $amount, $method, $comment);
            $stmt->execute();
            $stmt->close();

            $totalRecorded += $amount;
            $receiptItems[] = [
                'label' => (string)($item['name'] ?? ('Item ' . $feeItemId)),
                'amount' => $amount,
            ];
        }

        $receiptNo = 'RCT-' . date('Ymd-His') . '-' . random_int(100, 999);
        $receiptHtml = generateReceiptHtml([
            'receipt_no' => $receiptNo,
            'student_id' => $studentId,
            'items' => $receiptItems,
            'total' => $totalRecorded,
            'method' => 'MOPAY',
        ]);
        // Add traceability
        $receiptHtml .= "\n<!-- mopay_transactionId: " . htmlspecialchars($transactionId, ENT_QUOTES, 'UTF-8') . " -->\n";

        $stmt = $conn->prepare("
          INSERT INTO payment_receipts
          (receipt_no, application_id, source_table, package_id, total_amount, payment_method, receipt_html)
          VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $paymentMethod = 'MOPAY';
        $stmt->bind_param('sisidss', $receiptNo, $studentId, $sourceTable, $packageId, $totalRecorded, $paymentMethod, $receiptHtml);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        // Generate PDF + email (same settings as sendReceiptEmail.php)
        $pdfHtml = $receiptHtml;
        generateReceiptPdf($receiptNo, $conn);

        // Send email directly (reuse same SMTP settings).
        $stmt = $conn->prepare("SELECT first_name, last_name, email FROM student_applications WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (is_array($student) && !empty($student['email'])) {
            $pdfPath = __DIR__ . '/../../receipts/' . $receiptNo . '.pdf';
            if (file_exists($pdfPath)) {
                $studentName = trim((string)$student['first_name'] . ' ' . (string)$student['last_name']);
                try {
                    $mail = app_mailer();
                    $mail->addAddress((string)$student['email'], $studentName);
                    $mail->Subject = "Payment Receipt – {$receiptNo}";
                    $paidAmount = number_format((float)$totalRecorded, 2);
                    $packageCurrency = strtoupper((string)($meta['package_currency'] ?? 'RWF'));
                    $mail->Body = "
                      <div style='font-family:Arial,sans-serif;font-size:14px;color:#111827'>
                        <p>Dear <strong>" . htmlspecialchars($studentName) . "</strong>,</p>
                        <p>Thank you for your payment. Your receipt is attached.</p>
                        <p><strong>Receipt No:</strong> " . htmlspecialchars($receiptNo) . "</p>
                        <p><strong>Amount Paid:</strong> " . htmlspecialchars($packageCurrency . ' ' . $paidAmount) . "</p>
                        <p><strong>Payment Method:</strong> MoPay</p>
                      </div>
                    ";
                    $mail->addAttachment($pdfPath, $receiptNo . '.pdf');
                    $mail->send();
                } catch (Throwable $mailErr) {
                    // Don't fail the DB commit if email sending fails; just log it.
                    file_put_contents(__DIR__ . '/logs/webhook.log.jsonl', json_encode([
                        'type' => 'receipt_email_failed',
                        'time' => date('c'),
                        'transactionId' => $transactionId,
                        'receipt_no' => $receiptNo,
                        'error' => $mailErr->getMessage(),
                    ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
                }
            }
        }

        return $receiptNo;
    } catch (Throwable $e) {
        $conn->rollback();
        @file_put_contents(__DIR__ . '/logs/webhook.log.jsonl', json_encode([
            'type' => 'webhook_finalize_exception',
            'time' => date('c'),
            'transactionId' => $transactionId,
            'student_id' => $studentId,
            'package_id' => $packageId,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        return null;
    }
}

if (!is_string($transactionId) || trim($transactionId) === '') {
    @file_put_contents($debugLog, json_encode([
        'type' => 'webhook_missing_transaction_id',
        'time' => date('c'),
        'status' => $status,
        'amount' => $amount,
        'currency' => $currency,
        'payload_data_type' => gettype($payload['data'] ?? null),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 404, 'message' => 'transaction not found', 'transactionId' => null]);
    exit;
}

if (!mopay_transaction_known($transactionId)) {
    // Helps debugging: we know MoPay called us but we couldn't match the transaction.
    file_put_contents(
        $debugLog,
        json_encode([
            'type' => 'webhook_unknown_transaction',
            'time' => date('c'),
            'transactionId_received' => $transactionId,
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 404, 'message' => 'transaction not found', 'transactionId' => $transactionId]);
    exit;
}

$debugLog = __DIR__ . '/logs/webhook.log.jsonl';

$logLine = [
    'type' => 'webhook_received',
    'time' => date('c'),
    'transactionId' => $transactionId,
    'status' => $status,
    'amount' => $amount,
    'currency' => $currency,
    'jwt_header_payload' => [
        // Do not log full JWT signature to avoid copying secrets around.
        'jwt_prefix' => substr($jwt, 0, 30) . '...',
    ],
    'decoded_payload' => $payload,
];
file_put_contents($debugLog, json_encode($logLine) . PHP_EOL, FILE_APPEND | LOCK_EX);

// Wallet audit: inbound (customer → merchant) on successful checkout callback.
$receiptNo = null;
if (mopay_is_success_status($status)) {
    $meta = mopay_find_fee_meta($transactionId);
    pcvc_mopay_wallet_tx_log_inbound_webhook(
        $conn,
        $transactionId,
        is_array($data) ? $data : [],
        $amount,
        $currency,
        is_array($meta) ? $meta : null
    );

    // Finalize fee checkout payments (if we have metadata).
    if (is_array($meta)) {
        $receiptNo = mopay_finalize_fee_payment($conn, $transactionId, $meta);
        if ($receiptNo) {
            file_put_contents($debugLog, json_encode([
                'type' => 'webhook_finalize_ok',
                'time' => date('c'),
                'transactionId' => $transactionId,
                'receipt_no' => $receiptNo,
            ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } else {
            file_put_contents($debugLog, json_encode([
                'type' => 'webhook_finalize_failed',
                'time' => date('c'),
                'transactionId' => $transactionId,
                'reason' => 'finalize_returned_null',
            ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    } else {
        file_put_contents($debugLog, json_encode([
            'type' => 'webhook_fee_meta_missing',
            'time' => date('c'),
            'transactionId' => $transactionId,
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

http_response_code(200);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['status' => 200, 'message' => 'OK', 'transactionId' => $transactionId, 'receipt_no' => $receiptNo]);
exit;

