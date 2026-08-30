<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit;
}

$id = intval($_SESSION['id']);
$today = date('Y-m-d');

$stmt = $conn->prepare("SELECT * FROM attendance WHERE admin_id=? AND date=? LIMIT 1");
$stmt->bind_param("is", $id, $today);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

echo json_encode([
    "status" => "success",
    "today" => $res
]);
?>
