<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| PURPOSE
|--------------------------------------------------------------------------
| 1. Load ALL fee packages (once)
| 2. Load TOTAL amount paid for an application
| 3. NO auto assignment
| 4. NO fee items here
|--------------------------------------------------------------------------
|
| JS sends: student_id
| DB uses: application_id
|--------------------------------------------------------------------------
*/

$applicationId = isset($_GET['student_id'])
  ? (int) $_GET['student_id']
  : 0;

$sourceTable = trim((string) ($_GET['table'] ?? ''));

if ($applicationId <= 0) {
  echo json_encode([
    'packages' => [],
    'paid'     => 0
  ]);
  exit;
}

/*
|--------------------------------------------------------------------------
| 1. LOAD ALL PACKAGES (FOR DROPDOWN)
|--------------------------------------------------------------------------
*/
$packages = [];

$sql = "
  SELECT id, title, total_amount, currency
  FROM fee_packages
  ORDER BY id ASC
";

$result = $conn->query($sql);

if ($result) {
  while ($row = $result->fetch_assoc()) {
    $packages[] = [
      'id'           => (int) $row['id'],
      'name'         => $row['title'],     // 👈 JS uses `name`
      'total_amount' => (float) $row['total_amount'],
      'currency'     => $row['currency']
    ];
  }
}

/*
|--------------------------------------------------------------------------
| 2. LOAD TOTAL PAID (APPLICATION-WIDE)
|--------------------------------------------------------------------------
*/
$paid = 0.0;

if ($sourceTable !== '') {
  $stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount_paid), 0)
    FROM application_payments
    WHERE application_id = ? AND source_table = ?
  ");
  if ($stmt) {
    $stmt->bind_param("is", $applicationId, $sourceTable);
    $stmt->execute();
    $stmt->bind_result($paid);
    $stmt->fetch();
    $stmt->close();
  }
} else {
  $stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount_paid), 0)
    FROM application_payments
    WHERE application_id = ?
  ");

  if ($stmt) {
    $stmt->bind_param("i", $applicationId);
    $stmt->execute();
    $stmt->bind_result($paid);
    $stmt->fetch();
    $stmt->close();
  }
}

/*
|--------------------------------------------------------------------------
| 3. FINAL RESPONSE
|--------------------------------------------------------------------------
*/
echo json_encode([
  'packages' => $packages,
  'paid'     => (float) $paid
]);
