<?php
session_start();
require_once __DIR__ . '/db.php';
// JSON header
header("Content-Type: application/json");

// Disable output of PHP warnings/errors (prevents broken JSON)
error_reporting(0);
ini_set("display_errors", 0);

/* ===========================================================
   AUTH CHECK
=========================================================== */
if (!isset($_SESSION['id'])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$admin_id = intval($_SESSION['id']);

/* ===========================================================
   VALIDATE INPUT
=========================================================== */
if (empty($_POST['month'])) {
    echo json_encode(["error" => "Month is required"]);
    exit;
}

$month = $_POST['month']; // YYYY-MM

/* ===========================================================
   FETCH STAFF SALARY RATE
=========================================================== */
$stmt = $conn->prepare("
    SELECT salary_per_minute 
    FROM admins 
    WHERE id = ?
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($salary_per_minute);
$stmt->fetch();
$stmt->close();

if (!$salary_per_minute || $salary_per_minute <= 0) {
    echo json_encode([
        "total_minutes" => 0,
        "salary"        => "0",
        "raw_salary"    => 0
    ]);
    exit;
}

/* ===========================================================
   FETCH ATTENDANCE FOR THE SELECTED MONTH
=========================================================== */
$stmt2 = $conn->prepare("
    SELECT total_work_minutes, daily_salary_rwf
    FROM attendance
    WHERE admin_id = ?
      AND DATE_FORMAT(date, '%Y-%m') = ?
");
$stmt2->bind_param("is", $admin_id, $month);
$stmt2->execute();
$result = $stmt2->get_result();

$total_minutes = 0;
$total_salary  = 0;

/* ===========================================================
   SUM VALUES
=========================================================== */
while ($row = $result->fetch_assoc()) {

    if (isset($row['total_work_minutes'])) {
        $total_minutes += intval($row['total_work_minutes']);
    }

    if (isset($row['daily_salary_rwf'])) {
        $total_salary += intval($row['daily_salary_rwf']);
    }
}

$stmt2->close();

/* ===========================================================
   ALWAYS RETURN CLEAN JSON
=========================================================== */
$response = [
    "total_minutes" => $total_minutes,
    "salary"        => number_format($total_salary, 0, ".", ","), // display-friendly
    "raw_salary"    => $total_salary, // used for request insert
];

echo json_encode($response);
exit;
?>
