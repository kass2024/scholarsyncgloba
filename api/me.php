<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit;
}

$id = intval($_SESSION['id']);

$q = $conn->query("SELECT id, full_name, email, phone_number, username, role, office_id FROM admins WHERE id=$id");
$user = $q->fetch_assoc();

$office = null;
if ($user['office_id']) {
    $o = $conn->query("SELECT * FROM offices WHERE id={$user['office_id']}");
    $office = $o->fetch_assoc();
}

echo json_encode([
    "status" => "success",
    "user" => $user,
    "office" => $office
]);
?>
