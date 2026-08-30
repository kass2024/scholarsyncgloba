<?php
session_start();
require_once __DIR__ . '/db.php';
// Set response to JSON
header('Content-Type: application/json');

// Get POST data
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Prepare SQL to find user
$stmt = $conn->prepare("SELECT id, password_hash, full_name FROM admins WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// Check password
if ($admin && password_verify($password, $admin['password_hash'])) {
    // Login success → return admin_id and full_name
    echo json_encode([
        'success' => true,
        'admin_id' => $admin['id'],
        'full_name' => $admin['full_name']
    ]);
} else {
    // Login failed
    echo json_encode([
        'success' => false,
        'error' => 'Invalid username or password'
    ]);
}
?>
