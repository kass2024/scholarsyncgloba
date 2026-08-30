<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/company_branding.php';
require_once __DIR__ . '/includes/payroll_helpers.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/vendor/autoload.php'; // Dompdf autoload

use Dompdf\Dompdf;
use Dompdf\Options;

date_default_timezone_set('Africa/Kigali');

/* ----------------------- Auth / Role ----------------------- */
$viewer_id = $_SESSION['admin_id'] ?? $_SESSION['id'] ?? null;
if (!$viewer_id) {
    http_response_code(403);
    exit("🔐 Access denied. Login required.");
}

pcvc_require_superadmin($conn);

$stmt = $conn->prepare("SELECT role, full_name FROM admins WHERE id = ?");
$stmt->bind_param("i", $viewer_id);
$stmt->execute();
$stmt->bind_result($viewer_role, $viewer_name);
$stmt->fetch();
$stmt->close();

/* ----------------------- Inputs ----------------------- */
$target_admin_id = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
$selected_month  = $_GET['month'] ?? date('Y-m');

if ($target_admin_id <= 0) {
    http_response_code(400);
    exit("Invalid admin_id.");
}

/* Load admin info (staff & superadmin payroll export only) */
$payrollRoleSql = pcvc_sql_is_payroll_employee_expr('role');
$stmt = $conn->prepare("SELECT full_name, role, salary_per_minute FROM admins WHERE id = ? AND {$payrollRoleSql}");
$stmt->bind_param("i", $target_admin_id);
$stmt->execute();
$stmt->bind_result($name, $role, $rate);
if (!$stmt->fetch()) {
    $stmt->close();
    http_response_code(404);
    exit('Employee not found or not in staff/superadmin payroll.');
}
$stmt->close();
$rate = (float)$rate;

/* ----------------------- Build weekdays of month ----------------------- */
$start_month = date('Y-m-01', strtotime($selected_month));
$end_month   = date('Y-m-t',  strtotime($selected_month));

$dates = [];
$cur = strtotime($start_month);
$end = strtotime($end_month);
while ($cur <= $end) {
    $iso = (int)date('N', $cur);
    if ($iso <= 5) $dates[] = date('Y-m-d', $cur);
    $cur = strtotime('+1 day', $cur);
}

/* ----------------------- Load attendance for that admin (deduped) ----------------------- */
$byDay = [];
foreach ($dates as $d) {
    $byDay[$d] = ['minutes' => 0, 'salary' => 0, 'check_in' => null, 'check_out' => null, 'break' => 0];
}
$totalMins = 0;
$totalSalary = 0;

$allAtt = pcvc_payroll_load_attendance_by_admin($conn, $start_month, $end_month);
$rows = $allAtt[$target_admin_id] ?? [];

foreach ($rows as $d => $rec) {
    $iso = (int) date('N', strtotime($d));
    if ($iso >= 6) {
        continue;
    }
    if (!isset($byDay[$d])) {
        continue;
    }
    $mins = (int) ($rec['minutes'] ?? 0);
    $stored = (float) ($rec['stored_daily_pay'] ?? 0);
    $calculated = (int) round($rate * $mins);
    $salary = ((int) round($stored)) > 0 ? (int) round($stored) : $calculated;
    $byDay[$d]['minutes'] = $mins;
    $byDay[$d]['salary'] = $salary;
    $byDay[$d]['check_in'] = $rec['check_in'] ?? null;
    $byDay[$d]['check_out'] = $rec['check_out'] ?? null;
    $byDay[$d]['break'] = (int) ($rec['break'] ?? 0);
    $totalMins += $mins;
    $totalSalary += $salary;
}

/* ----------------------- Render HTML ----------------------- */
function rwf($n){ return number_format((float)$n, 0); }
function ratefmt($n){ return number_format((float)$n, 2); }

$monthLabel = date('F Y', strtotime($selected_month));

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME) ?> — Payroll — <?= htmlspecialchars($name) ?> — <?= htmlspecialchars($monthLabel) ?></title>
<style>
  @page { margin: 28mm 14mm 22mm 14mm; }
  body { font-family: Arial, sans-serif; font-size: 12px; color:#111; }
  h1,h2,h3 { margin:0 0 6px 0; }
  .muted{ color:#777; }
  .header { margin-bottom: 14px; }
  .box { border:1px solid #ddd; border-radius:8px; padding:10px 12px; margin-bottom:12px; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #e5e5e5; padding: 8px; text-align: left; }
  thead th { background: #f3f6fb; }
  tfoot td { background: #fafbff; font-weight: bold; }
  .right { text-align:right; }
  .title { font-size: 18px; font-weight: bold; }
</style>
</head>
<body>
  <div class="header">
    <div class="title"><?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME) ?></div>
    <div class="muted">Payroll detail — <?= htmlspecialchars($monthLabel) ?> (weekdays)</div>
  </div>

  <div class="box">
    <strong>Employee:</strong> <?= htmlspecialchars($name) ?><br>
    <strong>Role:</strong> <?= htmlspecialchars($role) ?><br>
    <strong>Rate:</strong> <?= ratefmt($rate) ?> <?= htmlspecialchars(PCVC_PAYROLL_CURRENCY) ?> / minute
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:18%;">Date</th>
        <th style="width:10%;">Weekday</th>
        <th style="width:12%;" class="right">In</th>
        <th style="width:12%;" class="right">Out</th>
        <th style="width:10%;" class="right">Break</th>
        <th style="width:12%;" class="right">Minutes</th>
        <th style="width:14%;" class="right">Pay (<?= htmlspecialchars(PCVC_PAYROLL_CURRENCY) ?>)</th>
        <th style="width:12%;">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($byDay as $d => $row): ?>
        <tr>
          <td><?= htmlspecialchars($d) ?></td>
          <td><?= htmlspecialchars(date('l', strtotime($d))) ?></td>
          <td class="right"><?= htmlspecialchars(pcvc_payroll_format_clock($row['check_in'] ?? null)) ?></td>
          <td class="right"><?= htmlspecialchars(pcvc_payroll_format_clock($row['check_out'] ?? null)) ?></td>
          <td class="right"><?= ((int) ($row['break'] ?? 0)) > 0 ? number_format((int) $row['break']) : '—' ?></td>
          <td class="right"><?= number_format($row['minutes']) ?></td>
          <td class="right"><?= rwf($row['salary']) ?></td>
          <td><?= $row['minutes'] > 0 ? 'Paid' : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5">Total</td>
        <td class="right"><?= number_format($totalMins) ?></td>
        <td class="right"><?= rwf($totalSalary) ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
</body>
</html>
<?php
$html = ob_get_clean();

/* ----------------------- Dompdf Output ----------------------- */
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Payroll_' . preg_replace('/\s+/', '_', $name) . '_' . str_replace('-', '_', $selected_month) . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]); // open in browser
exit;
