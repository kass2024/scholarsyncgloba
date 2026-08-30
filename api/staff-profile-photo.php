<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers/secure_file.php';
require_once dirname(__DIR__) . '/helpers/profile_photo_upload.php';

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied. Superadmin only.']);
    exit;
}

$staffId = (int) ($_POST['staff_id'] ?? 0);
if ($staffId < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid staff ID.']);
    exit;
}

if (!isset($_FILES['profile_photo'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No valid image uploaded.']);
    exit;
}

$stmt = $conn->prepare('SELECT profile_photo FROM admins WHERE id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error.']);
    exit;
}
$stmt->bind_param('i', $staffId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Staff member not found.']);
    exit;
}

$stored = pcvc_profile_photo_store($_FILES['profile_photo'], dirname(__DIR__) . '/uploads/');
if (!$stored['ok']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $stored['error'] ?? 'Failed to save image.']);
    exit;
}

$photoName = $stored['filename'];
$uploadDir = dirname(__DIR__) . '/uploads/';

$upd = $conn->prepare('UPDATE admins SET profile_photo = ? WHERE id = ?');
if (!$upd) {
    @unlink($uploadDir . $photoName);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error.']);
    exit;
}
$upd->bind_param('si', $photoName, $staffId);

if (!$upd->execute()) {
    @unlink($uploadDir . $photoName);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to update profile photo.']);
    exit;
}
$upd->close();

$oldPhoto = trim((string) ($row['profile_photo'] ?? ''));
if ($oldPhoto !== '' && $oldPhoto !== 'default_avatar.png') {
    $oldPath = $uploadDir . basename($oldPhoto);
    if (is_file($oldPath)) {
        @unlink($oldPath);
    }
}

echo json_encode([
    'ok' => true,
    'photo' => $photoName,
    'photo_url' => pcvc_profile_photo_url($photoName),
]);
