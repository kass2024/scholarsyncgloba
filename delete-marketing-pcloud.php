<?php
/**
 * Marketing materials delete — superadmin only.
 * pCloud delete is performed server-side so the token is not exposed to non-privileged clients.
 */
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['id'], $_SESSION['role'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$admin_id = (int) $_SESSION['id'];
if ($admin_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$stmt = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row || ($row['role'] ?? '') !== 'superadmin') {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$fileid = isset($_POST['fileid']) ? (int) $_POST['fileid'] : 0;
if ($fileid <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid file']);
    exit;
}

$token = 'kqNT7Z8BpwhA0d4MFZVgju0kZbR12PpsX93VWhpTOL5i4jVefcDdX';
$url = 'https://api.pcloud.com/deletefile?fileid=' . $fileid . '&access_token=' . urlencode($token);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (is_array($data) && isset($data['result']) && (int) $data['result'] === 0) {
    echo json_encode(['success' => true]);
    exit;
}

$err = is_array($data) && isset($data['error']) ? $data['error'] : 'Delete failed';
echo json_encode(['success' => false, 'error' => $err]);
