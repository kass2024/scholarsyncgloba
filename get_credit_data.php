<?php
require_once __DIR__ . '/db.php';
$user_id = $_GET['user_id'] ?? '';
if (!$user_id) {
  echo json_encode(['status' => 'error', 'message' => 'Missing user_id']);
  exit;
}

$query = $conn->prepare("SELECT * FROM credit_transfer_applications WHERE user_id = ?");
$query->bind_param("s", $user_id);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
  echo json_encode(['status' => 'empty']);
  exit;
}

$row = $result->fetch_assoc();

// Decode checkboxes
$checkboxFields = ['education_levels', 'certification_levels'];
foreach ($checkboxFields as $field) {
  $row[$field] = json_decode($row[$field] ?? '[]', true);
}

echo json_encode([
  'status' => 'success',
  'values' => $row
]);
