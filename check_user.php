<?php
require_once __DIR__ . '/db.php';
$type  = $_POST['type'];
$value = $_POST['value'];

if ($type === 'first_name') {
    $stmt = $conn->prepare("SELECT id FROM admins WHERE first_name = ?");
    $stmt->bind_param("s", $value);
} elseif ($type === 'email') {
    $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
    $stmt->bind_param("s", $value);
} else {
    echo 'invalid';
    exit;
}

$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo 'exists';
} else {
    echo 'ok';
}

$stmt->close();
$conn->close();
?>
