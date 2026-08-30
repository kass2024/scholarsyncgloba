<?php
require_once __DIR__ . '/db.php';
$deviceToken = $_POST['device_token'] ?? '';

if ($deviceToken) {
    // Save token — avoid duplicates
    $stmt = $conn->prepare("INSERT IGNORE INTO admin_fcm_tokens (token) VALUES (?)");
    $stmt->bind_param('s', $deviceToken);
    $stmt->execute();
    $stmt->close();

    echo '✅ Token saved';
} else {
    echo '❌ No token';
}
