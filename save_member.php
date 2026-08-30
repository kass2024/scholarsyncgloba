<?php
require_once __DIR__ . '/db.php';
$fullname = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$country = $_POST['country'] ?? '';
$membership = $_POST['membership'] ?? '';
$status = $_POST['status'] ?? '';
$appointment_date = $_POST['appointment_date'] ?? '';

if ($fullname && $email && $phone) {
    $stmt = $conn->prepare("INSERT INTO members (fullname, email, phone, country, membership, status, appointment_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $fullname, $email, $phone, $country, $membership, $status, $appointment_date);

    echo $stmt->execute() ? "success" : "error";
    $stmt->close();
} else {
    echo "missing_fields";
}
?>
