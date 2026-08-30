<?php
// monthly-salary-api.php
require_once __DIR__ . '/../db.php';
header("Content-Type: application/json");

// ------------------------------------
// 1. Validate Input
// ------------------------------------
$admin_id = intval($_POST['admin_id'] ?? 0);
$month    = trim($_POST['month'] ?? '');   // Format YYYY-MM

if ($admin_id <= 0 || $month === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Missing or invalid admin_id or month"
    ]);
    exit;
}

// ------------------------------------
// 2. Fetch Monthly Attendance Summary
// ------------------------------------
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_days,
        SUM(total_work_minutes) AS total_minutes,

        -- Use fallback logic:
        -- If daily_salary_rwf is NULL or 0, use total_payment_rwf
        SUM(
            CASE 
                WHEN daily_salary_rwf > 0 
                    THEN daily_salary_rwf
                WHEN total_payment_rwf > 0
                    THEN total_payment_rwf
                ELSE 0
            END
        ) AS total_salary

    FROM attendance
    WHERE admin_id = ?
      AND DATE_FORMAT(date, '%Y-%m') = ?
");
$stmt->bind_param("is", $admin_id, $month);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// ------------------------------------
// 3. Clean & Convert Values
// ------------------------------------
$total_days     = intval($summary['total_days']);
$total_minutes  = intval($summary['total_minutes']);
$total_salary   = intval($summary['total_salary']); // ⭐ Safe final salary

$total_hours = round($total_minutes / 60);

// ------------------------------------
// 4. Build JSON Response
// ------------------------------------
echo json_encode([
    "status"        => "success",
    "message"       => "Monthly summary loaded",
    "total_days"    => $total_days,
    "total_minutes" => $total_minutes,
    "total_hours"   => $total_hours,
    "total_salary"  => $total_salary,

    // Optional placeholders
    "late_count"    => 0,
    "missed_days"   => 0
]);
?>
