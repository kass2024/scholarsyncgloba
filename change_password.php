<?php
require_once __DIR__ . '/db.php';
session_start();

$user_id = $_SESSION['admin_id']; // ✅ matches your dashboard session

// Get form values safely
$new_password     = trim($_POST['new_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

// Validate inputs
if (empty($new_password) || empty($confirm_password)) {
    die("❌ Both password fields are required.");
}

if ($new_password !== $confirm_password) {
    die("❌ Passwords do not match.");
}

if (strlen($new_password) < 6) {
    die("❌ Password must be at least 6 characters.");
}

// Hash the new password
$password_hash = password_hash($new_password, PASSWORD_BCRYPT);

// Update query
$sql = "UPDATE admins SET password_hash = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $password_hash, $user_id);

// Execute
if ($stmt->execute()) {
    header("Location: admin-dashboard.php?success=password_changed");
    exit;
} else {
    echo "❌ Error changing password: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
