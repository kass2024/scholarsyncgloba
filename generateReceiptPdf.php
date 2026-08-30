<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/receipt_render.php';

/**
 * Generate an A4 PDF receipt (same layout as print + email attachment).
 *
 * @param string $receiptNo Receipt number stored in payment_receipts
 * @param mysqli|null $dbConn Optional DB connection (uses global $conn)
 */
function generateReceiptPdf(string $receiptNo, ?mysqli $dbConn = null): string
{
    global $conn;

    $db = $dbConn ?? $conn;
    if (!$db instanceof mysqli) {
        throw new RuntimeException('Database connection not available');
    }

    $receiptNo = trim($receiptNo);
    if ($receiptNo === '') {
        throw new RuntimeException('Receipt number is required');
    }

    $data = pcvc_load_receipt_data($db, $receiptNo);
    if (!$data) {
        throw new RuntimeException('Receipt not found: ' . $receiptNo);
    }

    $html = pcvc_render_receipt_html($data);

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('chroot', realpath(__DIR__) ?: __DIR__);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $dir = __DIR__ . '/receipts';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $file = $dir . '/' . $receiptNo . '.pdf';
    file_put_contents($file, $dompdf->output());

    return realpath($file) ?: $file;
}
