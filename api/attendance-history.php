<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit;
}

$id = intval($_SESSION['id']);

$q = $conn->query("
    SELECT date, check_in_time, check_out_time, total_work_minutes, daily_salary_rwf
    FROM attendance
    WHERE admin_id=$id
    ORDER BY date DESC
    LIMIT 60
");

$history = [];
while ($row = $q->fetch_assoc()) {
    $history[] = $row;
}

echo json_encode([
    "status" => "success",
    "history" => $history
]);
?>
