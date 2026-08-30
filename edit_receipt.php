<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/receipt_render.php';
/* =====================================================
   INPUT
===================================================== */
$receiptNo = trim($_GET['receipt_no'] ?? '');

if ($receiptNo === '') {
    http_response_code(400);
    exit('Receipt number is required');
}

/* =====================================================
   LOAD RECEIPT (UNCHANGED LOGIC)
===================================================== */
$stmt = $conn->prepare("
    SELECT
        receipt_no,
        application_id,
        source_table,
        package_id,
        total_amount,
        payment_method,
        created_at,
        status
    FROM payment_receipts
    WHERE receipt_no = ?
    LIMIT 1
");
$stmt->bind_param("s", $receiptNo);
$stmt->execute();
$receipt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$receipt) {
    http_response_code(404);
    exit('Receipt not found');
}

if ($receipt['status'] === 'CANCELED') {
    exit('This receipt is canceled and cannot be edited.');
}

/* =====================================================
   HELPERS
===================================================== */
function getReceiptItems(mysqli $conn, int $appId, string $sourceTable, int $packageId): array
{
    $stmt = $conn->prepare("
        SELECT
            fi.id,
            fi.name,
            fi.amount AS expected,
            COALESCE(SUM(ap.amount_paid),0) AS amount_paid
        FROM fee_items fi
        LEFT JOIN application_payments ap
          ON ap.fee_item_id = fi.id
         AND ap.application_id = ?
         AND ap.source_table = ?
         AND ap.status = 'PAID'
        WHERE fi.package_id = ?
        GROUP BY fi.id
        ORDER BY fi.id
    ");
    $stmt->bind_param("isi", $appId, $sourceTable, $packageId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $items;
}

/* =====================================================
   SAVE CHANGES (LIVE UPDATE SAME TABLE)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $conn->begin_transaction();

    try {

        $paymentMethod = trim($_POST['payment_method'] ?? '');
        $postedItems   = $_POST['items'] ?? [];

        $total = 0.0;

        foreach ($postedItems as $itemId => $amount) {

            $itemId = (int)$itemId;
            $amount = round((float)$amount, 2);

            $total += $amount;

            /* --- Check if payment row exists --- */
            $stmt = $conn->prepare("
                SELECT id
                FROM application_payments
                WHERE application_id = ?
                  AND fee_item_id = ?
                  AND status = 'PAID'
                LIMIT 1
            ");
            $stmt->bind_param("ii", $receipt['application_id'], $itemId);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existing) {
                /* --- UPDATE EXISTING PAYMENT --- */
                $stmt = $conn->prepare("
                    UPDATE application_payments
                    SET amount_paid = ?, payment_method = ?
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    "dsi",
                    $amount,
                    $paymentMethod,
                    $existing['id']
                );
                $stmt->execute();
                $stmt->close();

            } elseif ($amount > 0) {
                /* --- INSERT NEW PAYMENT (MATCHES ORIGINAL LOGIC) --- */
                $stmt = $conn->prepare("
                    INSERT INTO application_payments
                    (application_id, source_table, fee_item_id,
                     amount_paid, payment_method, status, paid_at)
                    VALUES (?, ?, ?, ?, ?, 'PAID', NOW())
                ");
                $stmt->bind_param(
                    "isids",
                    $receipt['application_id'],
                    $receipt['source_table'],
                    $itemId,
                    $amount,
                    $paymentMethod
                );
                $stmt->execute();
                $stmt->close();
            }
        }

        /* --- UPDATE RECEIPT HEADER --- */
        $stmt = $conn->prepare("
            UPDATE payment_receipts
            SET payment_method = ?, total_amount = ?
            WHERE receipt_no = ?
            LIMIT 1
        ");
        $stmt->bind_param("sds", $paymentMethod, $total, $receiptNo);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        header("Location: receipt_viewer.php");
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        exit('Update failed: '.$e->getMessage());
    }
}

/* =====================================================
   LOAD DATA FOR VIEW
===================================================== */
$customerName = pcvc_receipt_customer_name(
    $conn,
    (int) $receipt['application_id'],
    (string) ($receipt['source_table'] ?? 'student_applications')
);
$items = getReceiptItems(
    $conn,
    (int) $receipt['application_id'],
    (string) ($receipt['source_table'] ?? 'student_applications'),
    (int) $receipt['package_id']
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Receipt <?= htmlspecialchars($receiptNo) ?></title>

<style>
body{background:#f3eded;font-family:"Courier New",monospace;padding:20px}
.card{max-width:420px;margin:auto;background:#fff;padding:18px;border-radius:14px;box-shadow:0 4px 10px rgba(0,0,0,.12);font-size:13px}
.actions{display:flex;gap:8px;margin-bottom:12px}
.actions button,.actions a{padding:8px 12px;border-radius:8px;font-size:12px;text-decoration:none;color:#fff}
.save{background:#16a34a;border:none}
.back{background:#6b7280}
hr{border:none;border-top:1px dashed #000;margin:10px 0}
table{width:100%;border-collapse:collapse}
th{border-bottom:1px dashed #000;text-align:left}
td{padding:2px 0}
.amount{text-align:right}
input[type=number]{width:100%;text-align:right}
.total{border-top:1px dashed #000;margin-top:8px;padding-top:6px;font-weight:bold}
</style>
</head>

<body>

<form class="card" method="post">

<div class="actions">
    <button class="save">Save Changes</button>
    <a href="receipt_viewer.php" class="back">Back</a>
</div>

<strong>ScholarSync Global</strong><br>
Visa Consultant<br>
POS Receipt<br>
<?= htmlspecialchars($receipt['created_at']) ?>

<hr>

<div><strong>Receipt No:</strong> <?= htmlspecialchars($receiptNo) ?></div>
<div><strong>Customer:</strong> <?= htmlspecialchars($customerName) ?></div>

<label>Payment Method</label>
<select name="payment_method" required>
<?php foreach (['Cash','Bank','Card'] as $m): ?>
<option <?= $receipt['payment_method']===$m?'selected':'' ?>><?= $m ?></option>
<?php endforeach; ?>
</select>

<hr>

<table>
<tr>
<th>Product</th>
<th class="amount">Expected</th>
<th class="amount">Paid</th>
</tr>

<?php foreach ($items as $it): ?>
<tr>
<td><?= htmlspecialchars($it['name']) ?></td>
<td class="amount"><?= number_format((float)$it['expected'],2) ?></td>
<td class="amount">
<input type="number"
       step="0.01"
       min="0"
       max="<?= number_format((float)$it['expected'],2,'.','') ?>"
       name="items[<?= $it['id'] ?>]"
       value="<?= number_format((float)$it['amount_paid'],2,'.','') ?>">
</td>
</tr>
<?php endforeach; ?>
</table>

<div class="total">
Grand Total: <?= number_format((float)$receipt['total_amount'],2) ?><br>
Amount Paid: <?= number_format((float)$receipt['total_amount'],2) ?><br>
Balance: 0.00
</div>

</form>

</body>
</html>
