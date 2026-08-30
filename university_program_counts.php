<?php
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

// TEMPORARY: comment auth while testing in browser
// if (!isset($_SESSION['role'])) {
//   http_response_code(403);
//   echo json_encode(['ok'=>false,'msg'=>'Unauthorized']);
//   exit;
// }

$sql = "
  SELECT university_id, COUNT(*) AS total
  FROM programs
  GROUP BY university_id
  ORDER BY university_id
";

$res = mysqli_query($conn, $sql);

if (!$res) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => mysqli_error($conn)
  ]);
  exit;
}

$counts = [];
while ($row = mysqli_fetch_assoc($res)) {
  $counts[(int)$row['university_id']] = (int)$row['total'];
}

echo json_encode([
  'ok' => true,
  'counts' => $counts
], JSON_PRETTY_PRINT);
