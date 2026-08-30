<?php
declare(strict_types=1);

/**
 * Unified A4 receipt renderer for ScholarSync MIS.
 * Used by browser print (printReceipt.php), PDF generator (generateReceiptPdf.php),
 * and email body (sendReceiptEmail.php) so all three look identical.
 */

if (!function_exists('pcvc_receipt_image_data_uri')) {
    function pcvc_receipt_image_data_uri(string $absPath): string
    {
        if ($absPath === '' || !is_file($absPath)) {
            return '';
        }
        $bytes = @file_get_contents($absPath);
        if ($bytes === false) {
            return '';
        }
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'svg'  => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };
        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
}

if (!function_exists('pcvc_receipt_brand_logo_path')) {
    function pcvc_receipt_brand_logo_path(): string
    {
        $root = dirname(__DIR__);
        $candidates = [
            $root . DIRECTORY_SEPARATOR . 'scholarsync-global-logo.jpg',
            $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'brand' . DIRECTORY_SEPARATOR . 'scholarsync-mark.png',
            $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'brand' . DIRECTORY_SEPARATOR . 'scholarsync-mark.svg',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return '';
    }
}

if (!function_exists('pcvc_receipt_stamp_path')) {
    function pcvc_receipt_stamp_path(): string
    {
        $root = dirname(__DIR__);
        $candidates = [
            $root . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'employer-signature.png',
            $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'employer-signature.png',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return '';
    }
}

/**
 * Resolve applicant display name from application id + source table.
 */
function pcvc_receipt_customer_name(mysqli $conn, int $applicationId, string $sourceTable): string
{
    $allowedTables = [
        'student_applications',
        'malta_applications',
        'turkey_applications',
        'credit_transfer_applications',
        'upafa_registrations',
    ];
    if (!in_array($sourceTable, $allowedTables, true)) {
        $sourceTable = 'student_applications';
    }

    switch ($sourceTable) {
        case 'malta_applications':
            $sql = "SELECT TRIM(CONCAT_WS(' ', name, surname)) AS n FROM malta_applications WHERE id = ? LIMIT 1";
            break;
        case 'turkey_applications':
            $sql = "SELECT TRIM(CONCAT_WS(' ', first_name, last_name)) AS n FROM turkey_applications WHERE id = ? LIMIT 1";
            break;
        case 'credit_transfer_applications':
            $sql = "SELECT TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS n FROM credit_transfer_applications WHERE id = ? LIMIT 1";
            break;
        case 'upafa_registrations':
            $sql = "SELECT TRIM(CONCAT_WS(' ', first_name, last_name)) AS n FROM upafa_registrations WHERE id = ? LIMIT 1";
            break;
        default:
            $sql = "SELECT TRIM(CONCAT_WS(' ', first_name, last_name)) AS n FROM student_applications WHERE id = ? LIMIT 1";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 'Unknown';
    }
    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $name = trim((string) ($row['n'] ?? ''));
    return $name !== '' ? $name : 'Unknown';
}

/**
 * Load all receipt data from DB. Returns array on success, null on miss.
 */
function pcvc_load_receipt_data(mysqli $conn, string $receiptNo): ?array
{
    $receiptNo = trim($receiptNo);
    if ($receiptNo === '') {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT receipt_no, application_id, source_table, package_id,
                total_amount, payment_method, created_at
         FROM payment_receipts
         WHERE TRIM(receipt_no) = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $receiptNo);
    $stmt->execute();
    $receipt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$receipt) {
        return null;
    }

    $applicationId = (int) $receipt['application_id'];
    $packageId     = (int) $receipt['package_id'];
    $sourceTable   = (string) $receipt['source_table'];

    $allowedTables = [
        'student_applications',
        'malta_applications',
        'turkey_applications',
        'credit_transfer_applications',
        'upafa_registrations',
    ];
    if (!in_array($sourceTable, $allowedTables, true)) {
        $sourceTable = 'student_applications';
    }

    switch ($sourceTable) {
        case 'malta_applications':
            $sql = "SELECT TRIM(CONCAT_WS(' ', name, surname)) AS customer_name,
                           NULLIF(TRIM(email), '') AS customer_email
                    FROM malta_applications WHERE id = ? LIMIT 1";
            break;
        case 'turkey_applications':
            $sql = "SELECT TRIM(CONCAT_WS(' ', first_name, last_name)) AS customer_name,
                           NULLIF(TRIM(email), '') AS customer_email
                    FROM turkey_applications WHERE id = ? LIMIT 1";
            break;
        case 'credit_transfer_applications':
            $sql = "SELECT TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS customer_name,
                           NULLIF(TRIM(email), '') AS customer_email
                    FROM credit_transfer_applications WHERE id = ? LIMIT 1";
            break;
        case 'upafa_registrations':
            $sql = "SELECT TRIM(CONCAT_WS(' ', first_name, last_name)) AS customer_name,
                           NULLIF(TRIM(email), '') AS customer_email
                    FROM upafa_registrations WHERE id = ? LIMIT 1";
            break;
        default:
            $sql = "SELECT TRIM(CONCAT_WS(' ', first_name, last_name)) AS customer_name,
                           NULLIF(TRIM(email), '') AS customer_email
                    FROM student_applications WHERE id = ? LIMIT 1";
    }

    $customerName  = 'Unknown';
    $customerEmail = '';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $customerName  = $row['customer_name'] !== '' ? $row['customer_name'] : 'Unknown';
            $customerEmail = (string) ($row['customer_email'] ?? '');
        }
        $stmt->close();
    }

    $packageTitle = 'N/A';
    $currency     = '';
    $stmt = $conn->prepare("SELECT title, currency FROM fee_packages WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $packageTitle = (string) ($row['title'] ?? 'N/A');
            $currency     = (string) ($row['currency'] ?? '');
        }
        $stmt->close();
    }

    $items = [];
    $stmt = $conn->prepare(
        "SELECT fi.name AS item_name, ap.amount_paid, ap.payment_comment
         FROM application_payments ap
         JOIN fee_items fi ON fi.id = ap.fee_item_id
         WHERE ap.application_id = ?
           AND ap.source_table = ?
           AND ap.status = 'PAID'
           AND fi.package_id = ?
           AND ap.paid_at BETWEEN DATE_SUB(?, INTERVAL 30 SECOND)
                              AND DATE_ADD(?, INTERVAL 30 SECOND)
         ORDER BY ap.id ASC"
    );
    if ($stmt) {
        $stmt->bind_param('isiss', $applicationId, $sourceTable, $packageId, $receipt['created_at'], $receipt['created_at']);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();
    }

    if (!$items) {
        $items[] = [
            'item_name'       => $packageTitle,
            'amount_paid'     => $receipt['total_amount'],
            'payment_comment' => '',
        ];
    }

    return [
        'receipt_no'     => (string) $receipt['receipt_no'],
        'application_id' => $applicationId,
        'source_table'   => $sourceTable,
        'customer_name'  => $customerName,
        'customer_email' => $customerEmail,
        'package_title'  => $packageTitle,
        'currency'       => $currency,
        'payment_method' => (string) $receipt['payment_method'],
        'created_at'     => (string) $receipt['created_at'],
        'total_amount'   => (float) $receipt['total_amount'],
        'items'          => array_map(static function ($item) {
            return [
                'name'    => (string) ($item['item_name'] ?? ''),
                'amount'  => (float)  ($item['amount_paid'] ?? 0),
                'comment' => (string) ($item['payment_comment'] ?? ''),
            ];
        }, $items),
    ];
}

/**
 * Render the unified A4 receipt as a complete HTML document.
 *
 * Options:
 *   auto_print (bool)        — trigger window.print() on load (web only)
 *   include_print_button (bool) — show a print button (web only)
 *   company_name (string)
 *   company_sub  (string)
 *   support_email (string)
 *   support_phone (string)
 *   support_website (string)
 */
function pcvc_render_receipt_html(array $data, array $opts = []): string
{
    $opts += [
        'auto_print'           => false,
        'include_print_button' => false,
        'embed_images'         => true,
        'company_name'         => 'SCHOLARSYNC GLOBAL',
        'company_sub'          => 'Official Receipt of Payment',
        'support_email'        => 'infos@scholarsyncglobal.ca',
        'support_phone'        => '',
        'support_website'      => 'scholarsyncglobal.ca',
    ];

    if (!empty($opts['embed_images'])) {
        $logoUri  = pcvc_receipt_image_data_uri(pcvc_receipt_brand_logo_path());
        $stampUri = pcvc_receipt_image_data_uri(pcvc_receipt_stamp_path());
    } else {
        $logoUri  = '';
        $stampUri = '';
    }

    $h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

    $receiptNo     = $h($data['receipt_no'] ?? '');
    $customerName  = $h($data['customer_name'] ?? 'Customer');
    $applicationId = $h((string) ($data['application_id'] ?? ''));
    $packageTitle  = $h($data['package_title'] ?? 'N/A');
    $currency      = $h($data['currency'] ?? '');
    $paymentMethod = $h($data['payment_method'] ?? 'Cash');
    $createdAt     = $h(date('F j, Y · h:i A', strtotime((string) ($data['created_at'] ?? 'now'))));
    $totalAmount   = number_format((float) ($data['total_amount'] ?? 0), 2);

    $itemsHtml = '';
    $i = 1;
    foreach (($data['items'] ?? []) as $item) {
        $amount = number_format((float) ($item['amount'] ?? 0), 2);
        $name   = $h($item['name'] ?? '');
        $note   = trim((string) ($item['comment'] ?? ''));
        $noteHtml = $note !== ''
            ? '<div class="item-note">' . $h($note) . '</div>'
            : '';
        $itemsHtml .= '<tr>'
            . '<td class="col-no">' . $i++ . '</td>'
            . '<td class="col-desc"><div class="item-name">' . $name . '</div>' . $noteHtml . '</td>'
            . '<td class="col-amt">' . $currency . ' ' . $amount . '</td>'
            . '</tr>';
    }

    $companyName    = $h($opts['company_name']);
    $companySub     = $h($opts['company_sub']);
    $supportEmail   = $h($opts['support_email']);
    $supportPhone   = $h($opts['support_phone']);
    $supportWebsite = $h($opts['support_website']);

    $logoTag = $logoUri !== ''
        ? '<img src="' . $h($logoUri) . '" alt="Logo" class="logo">'
        : '<div class="logo logo-placeholder">PC</div>';

    $stampBlock = $stampUri !== ''
        ? '<img src="' . $h($stampUri) . '" alt="Official stamp" class="stamp-img">'
        : '';

    $printButton = $opts['include_print_button']
        ? '<div class="no-print print-bar">
             <button type="button" onclick="window.print()" class="btn-print">🖨 Print this receipt</button>
             <button type="button" onclick="window.close()" class="btn-close">Close</button>
           </div>'
        : '';

    $autoPrintScript = $opts['auto_print']
        ? '<script>window.addEventListener("load", function(){ setTimeout(function(){ window.print(); }, 350); });
           window.onafterprint = function(){ setTimeout(function(){ window.close(); }, 400); };</script>'
        : '';

    $contactLine = $supportEmail . ' · ' . $supportWebsite;

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt {$receiptNo}</title>
<style>
@page { size: A4 portrait; margin: 10mm; }

* { box-sizing: border-box; }

html, body {
    margin: 0;
    padding: 0;
    background: #eef2f6;
    color: #1f2933;
    font-family: "DejaVu Sans", "Helvetica Neue", Arial, sans-serif;
    font-size: 11px;
    line-height: 1.35;
}

.page {
    width: 190mm;
    max-width: 190mm;
    margin: 12px auto;
    padding: 0;
    background: #fff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.1);
}

.no-print.print-bar {
    max-width: 190mm;
    margin: 12px auto 0;
    text-align: right;
}
.btn-print, .btn-close {
    border: 0;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 700;
    cursor: pointer;
    margin-left: 6px;
}
.btn-print { background: #0b3c5d; color: #fff; }
.btn-close { background: #e5e7eb; color: #1f2933; }

.frame {
    border: 2px solid #0b3c5d;
    border-radius: 8px;
    padding: 8mm 9mm 7mm;
    page-break-inside: avoid;
}

.header {
    display: table;
    width: 100%;
    border-bottom: 2px solid #0b3c5d;
    padding-bottom: 6px;
    margin-bottom: 8px;
}
.header .col-left, .header .col-right {
    display: table-cell;
    vertical-align: middle;
}
.header .col-right { text-align: right; width: 42%; }

.logo {
    width: 48px;
    height: 48px;
    object-fit: contain;
    vertical-align: middle;
    margin-right: 8px;
}
.logo-placeholder {
    display: inline-block;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #0b3c5d;
    color: #fff;
    text-align: center;
    line-height: 48px;
    font-weight: 800;
    font-size: 16px;
    margin-right: 8px;
    vertical-align: middle;
}

.company-block { display: inline-block; vertical-align: middle; }
.company-name {
    font-size: 14px;
    font-weight: 800;
    color: #0b3c5d;
    margin: 0;
    line-height: 1.2;
}
.receipt-title {
    font-size: 10px;
    font-weight: 700;
    color: #fff;
    background: #0b3c5d;
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    margin-top: 3px;
    letter-spacing: 0.5px;
}
.receipt-no {
    font-size: 13px;
    font-weight: 800;
    color: #0b3c5d;
    margin: 0;
}
.receipt-date {
    font-size: 10px;
    color: #6b7280;
    margin-top: 2px;
}

.summary {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    padding: 6px 8px;
    margin-bottom: 8px;
    font-size: 10.5px;
}
.summary span { display: inline-block; margin-right: 14px; }
.summary b { color: #475569; font-weight: 600; }

.items {
    width: 100%;
    border-collapse: collapse;
}
.items th {
    background: #0b3c5d;
    color: #fff;
    text-align: left;
    padding: 5px 6px;
    font-size: 10px;
}
.items th.col-amt { text-align: right; }
.items td {
    padding: 5px 6px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 10.5px;
}
.items td.col-no { width: 28px; color: #6b7280; }
.items td.col-amt { text-align: right; font-weight: 700; color: #0b3c5d; white-space: nowrap; }

.bottom-row {
    display: table;
    width: 100%;
    margin-top: 8px;
    border-top: 2px solid #0b3c5d;
    padding-top: 6px;
}
.bottom-row .col-left, .bottom-row .col-right {
    display: table-cell;
    vertical-align: middle;
}
.bottom-row .col-right { text-align: right; width: 38%; }

.total-line {
    font-size: 13px;
    font-weight: 800;
    color: #0b3c5d;
}
.total-line small {
    display: block;
    font-size: 10px;
    font-weight: 600;
    color: #64748b;
    margin-top: 2px;
}

.stamp-img {
    max-width: 120px;
    max-height: 72px;
    object-fit: contain;
}

.footer-contact {
    text-align: center;
    font-size: 9px;
    color: #94a3b8;
    margin-top: 6px;
}

@media print {
    html, body { background: #fff; height: auto; }
    .no-print { display: none !important; }
    .page {
        margin: 0 auto;
        box-shadow: none;
        width: 100%;
        max-width: 100%;
    }
    .frame {
        border-radius: 6px;
        page-break-inside: avoid;
        page-break-after: avoid;
    }
}
</style>
</head>
<body>

{$printButton}

<div class="page">
    <div class="frame">
        <div class="header">
            <div class="col-left">
                {$logoTag}
                <div class="company-block">
                    <div class="company-name">{$companyName}</div>
                    <div class="receipt-title">OFFICIAL PAYMENT RECEIPT</div>
                </div>
            </div>
            <div class="col-right">
                <div class="receipt-no">{$receiptNo}</div>
                <div class="receipt-date">{$createdAt}</div>
            </div>
        </div>

        <div class="summary">
            <span><b>Customer:</b> {$customerName}</span>
            <span><b>Package:</b> {$packageTitle}</span>
            <span><b>Method:</b> {$paymentMethod}</span>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th class="col-no">#</th>
                    <th>Item</th>
                    <th class="col-amt">Amount</th>
                </tr>
            </thead>
            <tbody>
                {$itemsHtml}
            </tbody>
        </table>

        <div class="bottom-row">
            <div class="col-left">
                <div class="total-line">
                    TOTAL PAID: {$currency} {$totalAmount}
                    <small>Ref #{$applicationId}</small>
                </div>
            </div>
            <div class="col-right">
                {$stampBlock}
            </div>
        </div>

        <div class="footer-contact">{$contactLine}</div>
    </div>
</div>

{$autoPrintScript}
</body>
</html>
HTML;
}

/**
 * Compact receipt HTML for DB storage (no embedded base64 images).
 */
function pcvc_render_receipt_html_for_storage(array $data): string
{
    return pcvc_render_receipt_html($data, ['embed_images' => false]);
}
