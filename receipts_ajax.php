<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/payment_receipt_recorded_by.php';
require_once __DIR__ . '/helpers/receipt_render.php';

pcvc_ensure_payment_receipt_recorded_by_schema($conn);
/* =====================================================
   SAFETY (AJAX CONTEXT)
===================================================== */
error_reporting(0);
ini_set('display_errors', '0');

/* =====================================================
   INPUT
===================================================== */
$customer = trim($_GET['customer'] ?? '');
$range    = $_GET['range'] ?? '';
$doneBy   = trim($_GET['done_by'] ?? '');

$fromDate = '';
$toDate   = '';

switch ($range) {
    case 'today':
        $fromDate = $toDate = date('Y-m-d');
        break;

    case 'week':
        $fromDate = date('Y-m-d', strtotime('monday this week'));
        $toDate   = date('Y-m-d');
        break;

    case 'month':
        $fromDate = date('Y-m-01');
        $toDate   = date('Y-m-d');
        break;
}

/* =====================================================
   BASE QUERY
===================================================== */
$sql = "
SELECT
    pr.receipt_no,
    pr.application_id,
    pr.total_amount,
    pr.payment_method,
    pr.recorded_by,
    pr.recorded_by_name,
    pr.created_at,
    pr.status,
    fp.currency
FROM payment_receipts pr
LEFT JOIN fee_packages fp ON fp.id = pr.package_id
WHERE COALESCE(pr.status, 'ACTIVE') <> 'CANCELED'
";

$params = [];
$types  = '';

/* =====================================================
   CUSTOMER SEARCH
===================================================== */
if ($customer !== '') {
    $sql .= "
    AND pr.application_id IN (
        SELECT id FROM student_applications
        WHERE CONCAT(first_name,' ',last_name) LIKE ?
        UNION
        SELECT id FROM malta_applications
        WHERE CONCAT(name,' ',surname) LIKE ?
        UNION
        SELECT id FROM turkey_applications
        WHERE CONCAT(first_name,' ',last_name) LIKE ?
        UNION
        SELECT id FROM credit_transfer_applications
        WHERE CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?
           OR email LIKE ?
        UNION
        SELECT id FROM upafa_registrations
        WHERE CONCAT(first_name,' ',last_name) LIKE ?
           OR email LIKE ?
    )";

    $like   = "%{$customer}%";
    $params = [$like, $like, $like, $like, $like, $like, $like];
    $types  = 'sssssss';
}

/* =====================================================
   DATE FILTERS
===================================================== */
if ($fromDate !== '') {
    $sql .= " AND DATE(pr.created_at) >= ?";
    $params[] = $fromDate;
    $types   .= 's';
}

if ($toDate !== '') {
    $sql .= " AND DATE(pr.created_at) <= ?";
    $params[] = $toDate;
    $types   .= 's';
}

pcvc_receipt_recorded_by_apply_filter($doneBy, $sql, $params, $types);

$sql .= " ORDER BY pr.created_at DESC";

/* =====================================================
   EXECUTE QUERY
===================================================== */
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$receipts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* =====================================================
   HELPERS
===================================================== */

/* =====================================================
   EMPTY STATE
===================================================== */
if (empty($receipts)) {
    echo '<div class="state">No receipts found</div>';
    exit;
}

/* =====================================================
   OUTPUT (HTML FRAGMENT)
===================================================== */
foreach ($receipts as $r):

    $receiptData = pcvc_load_receipt_data($conn, (string) $r['receipt_no']);
    $name   = (string) ($receiptData['customer_name'] ?? 'Unknown');
    $items  = [];
    foreach (($receiptData['items'] ?? []) as $it) {
        $items[] = [
            'name'        => (string) ($it['name'] ?? ''),
            'amount_paid' => (float) ($it['amount'] ?? 0),
        ];
    }
    $cancel = ($r['status'] === 'CANCELED');
    $doneBy = pcvc_receipt_recorded_by_display(
        $conn,
        isset($r['recorded_by']) ? (int) $r['recorded_by'] : null,
        isset($r['recorded_by_name']) ? (string) $r['recorded_by_name'] : null
    );

    $total  = (float)$r['total_amount'];
    $curr   = htmlspecialchars((string)$r['currency']);
?>
<div class="receipt-card <?= $cancel ? 'canceled' : '' ?>">

    <div class="header">
        <div>
            <strong>ScholarSync Global</strong><br>
            Visa Consultant<br>
            POS Receipt<br>
            <?= date('Y-m-d H:i:s', strtotime($r['created_at'])) ?>
        </div>

        <div class="actions">
            <a class="btn-print" target="_blank"
               href="printReceipt.php?receipt_no=<?= urlencode($r['receipt_no']) ?>">
                Print
            </a>

            <a class="btn-edit"
               href="edit_receipt.php?receipt_no=<?= urlencode($r['receipt_no']) ?>">
                Edit
            </a>

            <form method="post" action="cancel_receipt.php" style="display:inline">
                <input type="hidden" name="receipt_no"
                       value="<?= htmlspecialchars($r['receipt_no']) ?>">
                <button class="btn-cancel" <?= $cancel ? 'disabled' : '' ?>>
                    Cancel
                </button>
            </form>
        </div>
    </div>

    <hr>

    <div><strong>Customer:</strong> <?= htmlspecialchars($name) ?></div>
    <div><strong>Payment:</strong> <?= htmlspecialchars($r['payment_method']) ?></div>
    <div class="done-by"><strong>Done by:</strong> <?= htmlspecialchars($doneBy) ?></div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th style="text-align:right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><?= htmlspecialchars($it['name']) ?></td>
                <td style="text-align:right">
                    <?= number_format((float)$it['amount_paid'], 2) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total">
        Grand Total: <?= number_format($total, 2) ?> <?= $curr ?><br>
        Amount Paid: <?= number_format($total, 2) ?> <?= $curr ?><br>
        Balance: <?= number_format(0, 2) ?> <?= $curr ?>
    </div>

</div>
<?php endforeach; ?>
