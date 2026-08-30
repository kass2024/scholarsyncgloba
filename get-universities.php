<?php
require 'db.php'; // Uses $conn from db.php
header('Content-Type: application/json');

$regionId = isset($_GET['region_id']) ? intval($_GET['region_id']) : 0;

if ($regionId > 0) {
    $stmt = $conn->prepare("SELECT id, name FROM universities WHERE region_id = ?");
    $stmt->bind_param("i", $regionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $universities = [];
    while ($row = $result->fetch_assoc()) {
        $universities[] = $row;
    }
    echo json_encode($universities);
} else {
    echo json_encode([]);
}
