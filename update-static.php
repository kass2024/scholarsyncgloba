<?php
require_once __DIR__ . '/db.php';
$id       = $_POST['id'] ?? null;
$app_id   = $_POST['application_id'] ?? null;
$remarks  = $_POST['application_remarks'] ?? null;
$source   = $_POST['source'] ?? 'student_applications'; // Default fallback

if (!$id || !in_array($source, ['student_applications', 'malta_applications'])) {
  echo 'invalid';
  exit;
}

$response = 'ok';

// Update application_id
if ($app_id !== null) {
  $stmt = $conn->prepare("UPDATE `$source` SET application_id = ? WHERE id = ?");
  if (!$stmt) {
    echo 'error_prepare_id';
    exit;
  }
  $stmt->bind_param("si", $app_id, $id);
  if (!$stmt->execute()) {
    $response = 'error_exec_id';
  }
}

// Update application_remarks
if ($remarks !== null) {
  $stmt = $conn->prepare("UPDATE `$source` SET application_remarks = ? WHERE id = ?");
  if (!$stmt) {
    echo 'error_prepare_remarks';
    exit;
  }
  $stmt->bind_param("si", $remarks, $id);
  if (!$stmt->execute()) {
    $response = 'error_exec_remarks';
  }
}

echo $response;
?>
