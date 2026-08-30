<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/university_admins_schema.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

$universityId = (int) ($_GET['university_id'] ?? 0);
if ($universityId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid university']);
    exit;
}

$platformIds = [];
$stmt = $conn->prepare('SELECT platform_id FROM university_platforms WHERE university_id = ?');
if ($stmt) {
    $stmt->bind_param('i', $universityId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $platformIds[] = (int) $row['platform_id'];
    }
    $stmt->close();
}

pcvc_ensure_university_admins_schema($conn);
$adminIds = [];
$stmt = $conn->prepare('SELECT admin_id FROM university_admins WHERE university_id = ?');
if ($stmt) {
    $stmt->bind_param('i', $universityId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $adminIds[] = (int) $row['admin_id'];
    }
    $stmt->close();
}

echo json_encode([
    'ok' => true,
    'platform_ids' => $platformIds,
    'admin_ids' => $adminIds,
]);
