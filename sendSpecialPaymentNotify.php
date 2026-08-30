<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

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
require_once __DIR__ . '/helpers/special_payment_notify.php';

pcvc_special_payment_notify_log('HTTP notify endpoint hit', $receiptNo);

$result = pcvc_send_special_payment_notify($conn, $receiptNo);
echo json_encode($result, JSON_UNESCAPED_UNICODE);

$conn->close();
exit;
