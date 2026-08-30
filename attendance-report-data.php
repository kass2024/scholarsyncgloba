<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/includes/payroll_helpers.php';
session_start();

$admin_id = $_SESSION['admin_id'] ?? $_SESSION['id'] ?? null;
if (!$admin_id) {
    http_response_code(403);
    echo json_encode(['table' => [], 'kpi' => ['records' => 0, 'total_minutes' => 0, 'avg_minutes' => 0, 'total_salary' => '0'], 'chart' => ['labels' => [], 'values' => []]]);
    exit;
}

$roleQuery = $conn->prepare("SELECT role FROM admins WHERE id=?");
$roleQuery->bind_param("i", $admin_id);
$roleQuery->execute();
$roleQuery->bind_result($role);
$roleQuery->fetch();
$roleQuery->close();

$isSuperAdmin = pcvc_is_superadmin_role($role ?? '');

$filter = $_POST['filter'] ?? 'daily';
$date   = $_POST['date'] ?? date('Y-m-d');
$month  = $_POST['month'] ?? date('Y-m');
$staff  = $_POST['staff'] ?? '';

$weekYear = (int) date('Y');
$weekNum  = (int) date('W');
$weekRaw  = trim((string) ($_POST['week'] ?? ''));
if (preg_match('/^(\d{4})-W(\d{1,2})$/i', $weekRaw, $weekMatch)) {
    $weekYear = (int) $weekMatch[1];
    $weekNum  = (int) $weekMatch[2];
} elseif ($weekRaw !== '' && ctype_digit($weekRaw)) {
    $weekNum = (int) $weekRaw;
}

$sql = "SELECT a.full_name, att.*
        FROM attendance att
        JOIN admins a ON a.id = att.admin_id
        WHERE 1 ";

$params = [];
$bind = "";

// FILTER TYPES
if ($filter == "daily") {
    $sql .= " AND DATE(att.date) = ? ";
    $params[] = $date;
    $bind .= "s";
}
if ($filter == "weekly") {
    $sql .= " AND WEEK(att.date,1)=? AND YEAR(att.date)=? ";
    $params[] = $weekNum;
    $params[] = $weekYear;
    $bind .= "ii";
}
if ($filter == "monthly") {
    $sql .= " AND DATE_FORMAT(att.date,'%Y-%m')=? ";
    $params[] = $month;
    $bind .= "s";
}

// STAFF FILTER
if ($isSuperAdmin && !empty($staff)) {
    $sql .= " AND a.full_name LIKE ? ";
    $params[] = "%$staff%";
    $bind .= "s";
}

// NORMAL ADMIN CAN ONLY SEE HIS
if (!$isSuperAdmin) {
    $sql .= " AND a.id = ? ";
    $params[] = $admin_id;
    $bind .= "i";
}

$sql .= " ORDER BY att.date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($bind, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$data = [];
$chartLabels = [];
$chartValues = [];
$totalMinutes = 0;
$totalSalary = 0;

while ($row = $result->fetch_assoc()) {
    $minutes = pcvc_attendance_derived_work_minutes($row);
    $salary  = (int) ($row['daily_salary_rwf'] ?? 0);
    if ($salary <= 0 && $minutes > 0) {
        $salary = (int) ($row['total_payment_rwf'] ?? 0);
    }

    $data[] = [
        "name" => $row['full_name'],
        "date" => $row['date'],
        "check_in" => $row['check_in_time'],
        "check_out" => $row['check_out_time'],
        "minutes" => $minutes,
        "salary" => $salary
    ];

    $chartLabels[] = $row['date'];
    $chartValues[] = $minutes;
    $totalMinutes += $minutes;
    $totalSalary  += $salary;
}

$avgMinutes = count($data) ? round($totalMinutes / count($data)) : 0;

echo json_encode([
    "table" => $data,
    "kpi" => [
        "records" => count($data),
        "total_minutes" => $totalMinutes,
        "avg_minutes" => $avgMinutes,
        "total_salary" => number_format($totalSalary)
    ],
    "chart" => [
        "labels" => $chartLabels,
        "values" => $chartValues
    ]
]);
