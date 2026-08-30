<?php
// jobs-api.php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "Not authenticated"]);
    exit;
}

$admin_id = $_SESSION['admin_id'];
$date_filter = $_GET['range'] ?? 'today';

switch ($date_filter) {
    case 'week':
        $start = date('Y-m-d', strtotime('monday this week'));
        $end = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $start = date('Y-m-01');
        $end = date('Y-m-t');
        break;
    default:
        $start = $end = date('Y-m-d');
}

$stmt = $conn->prepare("SELECT * FROM jobs WHERE admin_id = ? AND DATE(created_at) BETWEEN ? AND ? GROUP BY id ORDER BY created_at DESC, id DESC");
$stmt->bind_param("iss", $admin_id, $start, $end);
$stmt->execute();
$result = $stmt->get_result();

$jobs = [];
while ($row = $result->fetch_assoc()) {
    $row['hours_spent'] = $row['end_time'] ? round((strtotime($row['end_time']) - strtotime($row['created_at'])) / 3600, 2) : 0;
    $jobs[] = $row;
}

header('Content-Type: application/json');
echo json_encode($jobs);
