<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
/* ---------------------------------------------------------
| BASIC SECURITY (adjust to your auth system)
---------------------------------------------------------- */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    // die('Access denied');
}

/* ---------------------------------------------------------
| SAFE OUTPUT HELPER
---------------------------------------------------------- */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* ---------------------------------------------------------
| FILTERS
---------------------------------------------------------- */
$search   = trim($_GET['search'] ?? '');
$tripType = trim($_GET['trip_type'] ?? '');
$cabin    = trim($_GET['cabin_class'] ?? '');
$payment  = trim($_GET['payment_method'] ?? '');

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = "(r.full_name LIKE ? OR r.email LIKE ? OR r.user_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}

if ($tripType !== '') {
    $where[] = "r.trip_type = ?";
    $params[] = $tripType;
    $types .= 's';
}

if ($cabin !== '') {
    $where[] = "r.cabin_class = ?";
    $params[] = $cabin;
    $types .= 's';
}

if ($payment !== '') {
    $where[] = "r.payment_method = ?";
    $params[] = $payment;
    $types .= 's';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ---------------------------------------------------------
| MAIN QUERY (REAL SCHEMA, REAL ROUTES)
---------------------------------------------------------- */
$sql = "
SELECT
    r.id,
    r.user_id,
    r.full_name,
    r.email,
    r.trip_type,

    dep.display_name AS dep_display,
    dest.display_name AS dest_display,

    r.departure_date,
    r.return_date,
    r.passengers,
    r.cabin_class,

    GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ', ') AS airline,

    r.payment_method,
    r.created_at
FROM air_reservations r

LEFT JOIN airports dep  ON dep.id  = r.departure_city
LEFT JOIN airports dest ON dest.id = r.destination_city

LEFT JOIN air_reservation_airlines ra ON ra.reservation_id = r.id
LEFT JOIN airlines a ON a.id = ra.airline_id

$whereSQL
GROUP BY r.id
ORDER BY r.created_at DESC
";


$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

/* ---------------------------------------------------------
| CABIN LABELS
---------------------------------------------------------- */
$cabinLabels = [
    'economy' => 'Economy',
    'premium_economy' => 'Premium Economy',
    'business' => 'Business',
    'first' => 'First Class'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Air Reservations Report</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

<style>
body{font-family:Inter;background:#f8fafc}
.card{border-radius:16px}
.badge-soft{
  background:#eef2ff;color:#3730a3;
  padding:.35rem .6rem;border-radius:10px;font-size:.75rem
}
.table th{white-space:nowrap}
</style>
</head>

<body>
<div class="container-fluid py-4">

<h2 class="fw-bold mb-4">✈️ Air Reservation Dashboard</h2>

<!-- FILTERS -->
<div class="card mb-4">
<div class="card-body">
<form class="row g-3">

  <div class="col-md-3">
    <input type="text" name="search" class="form-control"
      placeholder="Search name, email, user ID"
      value="<?= e($search) ?>">
  </div>

  <div class="col-md-2">
    <select name="trip_type" class="form-select">
      <option value="">Trip Type</option>
      <option value="one_way" <?= $tripType==='one_way'?'selected':'' ?>>One Way</option>
      <option value="round_trip" <?= $tripType==='round_trip'?'selected':'' ?>>Round Trip</option>
      <option value="multi_city" <?= $tripType==='multi_city'?'selected':'' ?>>Multi City</option>
    </select>
  </div>

  <div class="col-md-2">
    <select name="cabin_class" class="form-select">
      <option value="">Cabin</option>
      <?php foreach ($cabinLabels as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $cabin===$k?'selected':'' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-md-2">
    <select name="payment_method" class="form-select">
      <option value="">Payment</option>
      <option value="cash" <?= $payment==='cash'?'selected':'' ?>>Cash</option>
      <option value="mobile_money" <?= $payment==='mobile_money'?'selected':'' ?>>Mobile Money</option>
      <option value="bank_transfer" <?= $payment==='bank_transfer'?'selected':'' ?>>Bank Transfer</option>
    </select>
  </div>

  <div class="col-md-3 d-flex gap-2">
    <button class="btn btn-primary w-100">Filter</button>
    <a href="reservation-report.php" class="btn btn-outline-secondary w-100">Reset</a>
  </div>

</form>
</div>
</div>

<!-- TABLE -->
<div class="card">
<div class="card-body table-responsive">

<table class="table table-hover align-middle">
<thead class="table-light">
<tr>
  <th>#</th>
  <th>Passenger</th>
  <th>Route</th>
  <th>Trip</th>
  <th>Dates</th>
  <th>PAX</th>
  <th>Cabin</th>
  <th>Airline</th>
  <th>Payment</th>
  <th>Created</th>
</tr>
</thead>
<tbody>

<?php if ($result->num_rows === 0): ?>
<tr>
  <td colspan="10" class="text-center text-muted py-4">
    No reservations found
  </td>
</tr>
<?php endif; ?>

<?php while ($row = $result->fetch_assoc()): ?>
<tr>
  <td><?= (int)$row['id'] ?></td>

  <td>
    <strong><?= e($row['full_name']) ?></strong><br>
    <small class="text-muted"><?= e($row['email']) ?></small>
  </td>

<td>
  <span class="badge-soft"><?= e($row['dep_display']) ?></span>
  →
  <span class="badge-soft"><?= e($row['dest_display']) ?></span>
</td>


  <td><?= strtoupper(str_replace('_',' ', e($row['trip_type']))) ?></td>

  <td>
    <?php if ($row['departure_date'] !== '0000-00-00'): ?>
      <?= e($row['departure_date']) ?>
    <?php else: ?>
      <span class="text-muted">—</span>
    <?php endif; ?>

    <?php if (!empty($row['return_date'])): ?>
      <br><small class="text-muted">↩ <?= e($row['return_date']) ?></small>
    <?php endif; ?>
  </td>

  <td><?= (int)$row['passengers'] ?></td>

  <td><?= $cabinLabels[$row['cabin_class']] ?? '—' ?></td>

  <td><?= e($row['airline']) ?: '—' ?></td>

  <td><?= ucfirst(str_replace('_',' ', e($row['payment_method']))) ?></td>

  <td><small><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></small></td>
</tr>
<?php endwhile; ?>

</tbody>
</table>

</div>
</div>

</div>
</body>
</html>
