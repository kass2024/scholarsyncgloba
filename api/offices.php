<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

$q = $conn->query("SELECT * FROM offices ORDER BY office_name ASC");

$offices = [];
while ($row = $q->fetch_assoc()) {
    $offices[] = $row;
}

echo json_encode([
    "status" => "success",
    "offices" => $offices
]);
?>
