<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/receipt_render.php';

$receiptNo = trim((string) ($_GET['receipt_no'] ?? ''));

if ($receiptNo === '') {
    http_response_code(400);
    exit('Receipt number is required');
}

$data = pcvc_load_receipt_data($conn, $receiptNo);

if (!$data) {
    http_response_code(404);
    exit('Receipt not found');
}

header('Content-Type: text/html; charset=utf-8');
echo pcvc_render_receipt_html($data, [
    'auto_print'           => true,
    'include_print_button' => true,
]);
