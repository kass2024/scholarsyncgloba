<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

$session = $_GET['session'] ?? '';

$stmt = $conn->prepare("
  SELECT id, message
  FROM chat_messages
  WHERE session_id = ?
    AND sender = 'agent'
    AND delivered = 0
  ORDER BY created_at ASC
  LIMIT 1
");
$stmt->bind_param('s', $session);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row) {
    // Mark as delivered
    $stmt = $conn->prepare("
      UPDATE chat_messages SET delivered = 1 WHERE id = ?
    ");
    $stmt->bind_param('i', $row['id']);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['message' => nl2br(htmlspecialchars($row['message']))]);
    exit;
}

echo json_encode(['message' => null]);
