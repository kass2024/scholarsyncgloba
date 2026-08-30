<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Not logged in"
    ]);
    exit;
}

$id = intval($_SESSION['id']);
$month = date('Y-m');

$q = $conn->query("
    SELECT 
        SUM(total_payment_rwf) AS total_salary,
        SUM(total_work_minutes) AS total_minutes
    FROM attendance
    WHERE admin_id=$id
    AND date LIKE '$month%'
");

$rows = $q->fetch_assoc();

echo json_encode([
    "status" => "success",
    "month" => $month,
    "total_salary" => intval($rows['total_salary'] ?? 0),
    "total_work_minutes" => intval($rows['total_minutes'] ?? 0)
]);
?>
