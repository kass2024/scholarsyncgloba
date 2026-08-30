<?php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'superadmin') {
  header("Location: login.php");
  exit;
}

$leave_id = $_POST['id'] ?? null;
$action = $_POST['action'] ?? '';
$status = ($action === 'approve') ? 'approved' : 'rejected';

$stmt = $conn->prepare("
  UPDATE leave_requests
  SET status = ?, reviewed_by = ?, reviewed_at = NOW()
  WHERE id = ?
");
$stmt->bind_param("sii", $status, $_SESSION['admin_id'], $leave_id);
$stmt->execute();
$stmt->close();

header("Location: leave-approvals.php");
exit;
