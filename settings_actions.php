<?php
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
  echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'delete_program') {
  $id = (int)($data['id'] ?? 0);
  if (!$id) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid ID']);
    exit;
  }

  $stmt = mysqli_prepare($conn, "DELETE FROM programs WHERE id = ?");
  mysqli_stmt_bind_param($stmt, 'i', $id);
  mysqli_stmt_execute($stmt);

  echo json_encode(['ok' => true]);
  exit;
}

echo json_encode(['ok' => false, 'msg' => 'Invalid action']);
