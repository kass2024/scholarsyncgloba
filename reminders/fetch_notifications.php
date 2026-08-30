<?php
// reminders/fetch_notifications.php
// Returns { ok:true, unread:<int>, items:[...] } for the logged-in admin.

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

// _util.php should enforce session, load db ($conn), and define json_ok/json_fail.
// We also add small fallbacks in case _util.php doesn't define them.
require_once __DIR__ . '/_util.php';

if (!function_exists('json_fail')) {
  function json_fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'msg' => $msg], JSON_UNESCAPED_SLASHES);
    exit;
  }
}
if (!function_exists('json_ok')) {
  function json_ok($data = []) {
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_SLASHES);
    exit;
  }
}
if (!isset($conn) || !($conn instanceof mysqli)) {
  // Fallback if _util.php didn't include db connection for some reason
require_once __DIR__ . '/../db.php';
}

if (empty($_SESSION['id'])) {
  json_fail('Not authenticated', 401);
}
$adminId = (int)$_SESSION['id'];

// Sanitize & clamp limit (1..20)
$limit = $_GET['limit'] ?? 10;
$limit = is_numeric($limit) ? (int)$limit : 10;
$limit = max(1, min(20, $limit));

// --- Unread count ---
$unread = 0;
if ($stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE admin_id=? AND is_read=0")) {
  $stmt->bind_param('i', $adminId);
  if ($stmt->execute() && ($res = $stmt->get_result())) {
    if ($row = $res->fetch_assoc()) $unread = (int)$row['c'];
  }
  $stmt->close();
} else {
  json_fail('DB error: '.$conn->error, 500);
}

// --- Latest items (DESC) ---
// COALESCE handles schemas without created_at column values.
$items = [];
if ($stmt = $conn->prepare("
  SELECT id, title, body, link_url, is_read, COALESCE(created_at, UTC_TIMESTAMP()) AS created_at
    FROM notifications
   WHERE admin_id=?
   ORDER BY id DESC
   LIMIT ?
")) {
  $stmt->bind_param('ii', $adminId, $limit);
  if ($stmt->execute() && ($res = $stmt->get_result())) {
    while ($r = $res->fetch_assoc()) {
      // Normalize types
      $r['id']      = (int)$r['id'];
      $r['is_read'] = (int)$r['is_read'];
      // Leave title/body/link_url/created_at as strings
      $items[] = $r;
    }
  }
  $stmt->close();
} else {
  json_fail('DB error: '.$conn->error, 500);
}

json_ok(['unread' => $unread, 'items' => $items]);
