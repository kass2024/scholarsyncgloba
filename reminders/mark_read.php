<?php
// reminders/mark_read.php
// Marks a single notification (by id) as read for the logged-in admin,
// or marks all of their notifications as read if no id is provided.

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

// _util.php should enforce session, load $conn (mysqli), and define json_ok/json_fail.
require_once __DIR__ . '/_util.php';

// Fallbacks if _util.php didn't define helpers or $conn for any reason
if (!function_exists('json_fail')) {
  function json_fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok'=>false,'msg'=>$msg], JSON_UNESCAPED_SLASHES);
    exit;
  }
}
if (!function_exists('json_ok')) {
  function json_ok($data = []) {
    echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_SLASHES);
    exit;
  }
}
if (!isset($conn) || !($conn instanceof mysqli)) {
require_once __DIR__ . '/../db.php';
}

if (empty($_SESSION['id'])) {
  json_fail('Not authenticated', 401);
}
$adminId = (int)$_SESSION['id'];

// Accept both JSON and form-encoded
$in = $_POST;
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (!$in && stripos($ct, 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) $in = $decoded;
}

$id = isset($in['id']) ? (int)$in['id'] : 0;

// Mark one or all, strictly scoped to this admin
if ($id > 0) {
  if (!($stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND admin_id=?"))) {
    json_fail('DB error: '.$conn->error, 500);
  }
  $stmt->bind_param('ii', $id, $adminId);
  $stmt->execute();
  $stmt->close();
  $marked = 'one';
} else {
  if (!($stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE admin_id=?"))) {
    json_fail('DB error: '.$conn->error, 500);
  }
  $stmt->bind_param('i', $adminId);
  $stmt->execute();
  $stmt->close();
  $marked = 'all';
}

// Return fresh unread count for convenience
$unread = 0;
if ($stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE admin_id=? AND is_read=0")) {
  $stmt->bind_param('i', $adminId);
  if ($stmt->execute() && ($res = $stmt->get_result())) {
    if ($r = $res->fetch_assoc()) $unread = (int)$r['c'];
  }
  $stmt->close();
}

json_ok(['marked' => $marked, 'id' => ($id ?: null), 'unread' => $unread]);
