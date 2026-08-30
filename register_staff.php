<?php
require_once __DIR__ . '/db.php';
$first_name    = $_POST['first_name'];
$last_name     = $_POST['last_name'];
$username      = $_POST['username'];
$phone_number  = $_POST['phone_number'];
$email         = $_POST['email'];
$password      = $_POST['password'];
$role          = $_POST['role'];
$full_name     = $first_name . ' ' . $last_name;
$created_at    = date('Y-m-d H:i:s');
$password_hash = password_hash($password, PASSWORD_BCRYPT);

// Check for duplicate username
$checkUsername = $conn->prepare("SELECT id FROM admins WHERE username = ?");
$checkUsername->bind_param("s", $username);
$checkUsername->execute();
$checkUsername->store_result();
if ($checkUsername->num_rows > 0) {
    echo "❌ Error: This username already exists!";
    $checkUsername->close();
    $conn->close();
    exit;
}
$checkUsername->close();

// Check for duplicate email
$checkEmail = $conn->prepare("SELECT id FROM admins WHERE email = ?");
$checkEmail->bind_param("s", $email);
$checkEmail->execute();
$checkEmail->store_result();
if ($checkEmail->num_rows > 0) {
    echo "❌ Error: This email already exists!";
    $checkEmail->close();
    $conn->close();
    exit;
}
$checkEmail->close();

// Proceed to insert
$sql = "INSERT INTO admins (username, first_name, last_name, email, phone_number, password_hash, full_name, created_at, role)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssssss", 
    $username, 
    $first_name, 
    $last_name, 
    $email, 
    $phone_number, 
    $password_hash, 
    $full_name, 
    $created_at, 
    $role
);

if ($stmt->execute()) {
    // Redirect to main website on successful registration
    header('Location: admin-login.php');
    exit;
} else {
    echo "❌ Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>