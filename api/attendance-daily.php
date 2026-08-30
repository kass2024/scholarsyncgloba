<?php
// daily-salary-api.php
require_once __DIR__ . '/../db.php';
header("Content-Type: application/json");

// ------------------------------
// 1. Validate Required Input
// ------------------------------
$admin_id = intval($_POST['admin_id'] ?? 0);
$date     = trim($_POST['date'] ?? '');

if ($admin_id <= 0 || $date === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Missing or invalid admin_id or date"
    ]);
    exit;
}

// ------------------------------
// 2. Fetch Attendance Row
// ------------------------------
$stmt = $conn->prepare("
    SELECT 
        check_in_time,
        check_out_time,
        total_work_minutes,
        daily_salary_rwf,
        total_payment_rwf
    FROM attendance
    WHERE admin_id = ? AND date = ?
    LIMIT 1
");
$stmt->bind_param("is", $admin_id, $date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "No attendance found for selected date"
    ]);
    exit;
}

$row = $result->fetch_assoc();

// ------------------------------
// 3. Build Safe Salary Value
// ------------------------------
$salary = 0;

// Priority 1: daily_salary_rwf
if (!empty($row['daily_salary_rwf']) && $row['daily_salary_rwf'] > 0) {
    $salary = intval($row['daily_salary_rwf']);
}
// Priority 2: fallback to total_payment_rwf
else if (!empty($row['total_payment_rwf']) && $row['total_payment_rwf'] > 0) {
    $salary = intval($row['total_payment_rwf']);
}
// Priority 3: salary stays 0 (weekends, missing checkout, job-hours < requirements)

// ------------------------------
// 4. Successful Output
// ------------------------------
echo json_encode([
    "status"          => "success",
    "message"         => "Daily salary report loaded",
    "check_in_time"   => $row['check_in_time'],
    "check_out_time"  => $row['check_out_time'],
    "worked_minutes"  => intval($row['total_work_minutes']),
    "salary"          => $salary       // ⭐ ALWAYS RETURNS A VALID SALARY
]);

?>
