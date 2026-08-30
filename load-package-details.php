<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| INPUT (STRICT)
|--------------------------------------------------------------------------
*/
$packageId = filter_input(INPUT_GET, 'package_id', FILTER_VALIDATE_INT);
$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
$sourceTable = trim((string) ($_GET['table'] ?? ''));

/*
|--------------------------------------------------------------------------
| HARD GUARD — NEVER RETURN PAID DATA WITHOUT PACKAGE
|--------------------------------------------------------------------------
*/
if (!$packageId || !$studentId) {
    echo json_encode([
        'total'     => 0,
        'paid'      => 0,
        'remaining' => 0,
        'currency'  => '',
        'items'     => []
    ], JSON_THROW_ON_ERROR);
    exit;
}

/*
|--------------------------------------------------------------------------
| 1. LOAD PACKAGE INFO
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT total_amount, currency
    FROM fee_packages
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $packageId);
$stmt->execute();
$pkg = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pkg) {
    echo json_encode([
        'total'     => 0,
        'paid'      => 0,
        'remaining' => 0,
        'currency'  => '',
        'items'     => []
    ], JSON_THROW_ON_ERROR);
    exit;
}

$packageTotal = (float) $pkg['total_amount'];
$currency     = (string) $pkg['currency'];

/*
|--------------------------------------------------------------------------
| 2. CALCULATE TOTAL PAID FOR PACKAGE (STRICT SCOPE)
|--------------------------------------------------------------------------
*/
if ($sourceTable !== '') {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(ap.amount_paid), 0) AS paid
        FROM application_payments ap
        INNER JOIN fee_items fi
            ON fi.id = ap.fee_item_id
           AND fi.package_id = ?
        WHERE ap.application_id = ?
          AND ap.source_table = ?
    ");
    $stmt->bind_param("iis", $packageId, $studentId, $sourceTable);
} else {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(ap.amount_paid), 0) AS paid
        FROM application_payments ap
        INNER JOIN fee_items fi
            ON fi.id = ap.fee_item_id
           AND fi.package_id = ?
        WHERE ap.application_id = ?
    ");
    $stmt->bind_param("ii", $packageId, $studentId);
}
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$packagePaid = min(
    (float) ($row['paid'] ?? 0),
    $packageTotal
);

/*
|--------------------------------------------------------------------------
| 3. LOAD FEE ITEMS + PAID PER ITEM (DEFENSIVE)
|--------------------------------------------------------------------------
*/
$items = [];

if ($sourceTable !== '') {
    $stmt = $conn->prepare("
        SELECT
            fi.id,
            fi.name,
            fi.amount,
            COALESCE(SUM(ap.amount_paid), 0) AS paid
        FROM fee_items fi
        LEFT JOIN application_payments ap
            ON ap.fee_item_id = fi.id
           AND ap.application_id = ?
           AND ap.source_table = ?
        WHERE fi.package_id = ?
        GROUP BY fi.id, fi.name, fi.amount
        ORDER BY fi.id ASC
    ");
    $stmt->bind_param("isi", $studentId, $sourceTable, $packageId);
} else {
    $stmt = $conn->prepare("
        SELECT
            fi.id,
            fi.name,
            fi.amount,
            COALESCE(SUM(ap.amount_paid), 0) AS paid
        FROM fee_items fi
        LEFT JOIN application_payments ap
            ON ap.fee_item_id = fi.id
           AND ap.application_id = ?
        WHERE fi.package_id = ?
        GROUP BY fi.id, fi.name, fi.amount
        ORDER BY fi.id ASC
    ");
    $stmt->bind_param("ii", $studentId, $packageId);
}
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $itemTotal = (float) $row['amount'];
    $itemPaid  = min((float) $row['paid'], $itemTotal);

    $items[] = [
        'id'        => (int) $row['id'],
        'name'      => (string) $row['name'],
        'amount'    => $itemTotal,
        'paid'      => $itemPaid,
        'remaining' => max(0, $itemTotal - $itemPaid)
    ];
}
$stmt->close();

/*
|--------------------------------------------------------------------------
| 4. FINAL SAFE RESPONSE
|--------------------------------------------------------------------------
*/
echo json_encode([
    'total'     => $packageTotal,
    'paid'      => $packagePaid,
    'remaining' => max(0, $packageTotal - $packagePaid),
    'currency'  => $currency,
    'items'     => $items
], JSON_THROW_ON_ERROR);
