<?php
session_start();
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/role.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('Method not allowed', false, 405);
}

$adminId = 0;
if (!empty($_SESSION['id'])) {
    $adminId = (int) $_SESSION['id'];
} elseif (!empty($_SESSION['admin_id'])) {
    $adminId = (int) $_SESSION['admin_id'];
}

if ($adminId <= 0) {
    jsonResponse('Unauthorized', false, 401);
}

$stmtRole = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
if (!$stmtRole) {
    jsonResponse('Server error', false, 500);
}
$stmtRole->bind_param('i', $adminId);
$stmtRole->execute();
$roleRow = $stmtRole->get_result()->fetch_assoc();
$stmtRole->close();

if (!$roleRow) {
    jsonResponse('Unauthorized', false, 401);
}

$dbRole = (string) ($roleRow['role'] ?? '');
$sessionRole = (string) ($_SESSION['role'] ?? '');
if (
    !xander_is_superadmin_role($dbRole)
    && !xander_is_superadmin_role($sessionRole)
) {
    jsonResponse('Forbidden', false, 403);
}

$userId = trim((string) ($_POST['user_id'] ?? ''));
if ($userId === '') {
    jsonResponse('Invalid user id', false, 400);
}

$st = $conn->prepare('DELETE FROM form_17_applications WHERE user_id = ? LIMIT 1');
if (!$st) {
    jsonResponse('Server error', false, 500);
}
$st->bind_param('s', $userId);
$st->execute();
if ($st->affected_rows === 0) {
    $st->close();
    jsonResponse('Application not found', false, 404);
}
$st->close();

jsonResponse(['deleted' => true, 'user_id' => $userId]);
