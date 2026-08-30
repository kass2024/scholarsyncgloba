<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

$q = "
  SELECT u.id, COUNT(p.id) AS program_count
  FROM universities u
  LEFT JOIN programs p ON p.university_id = u.id
  GROUP BY u.id
";

$res = mysqli_query($conn, $q);

$data = [];
while ($row = mysqli_fetch_assoc($res)) {
  $data[(int)$row['id']] = (int)$row['program_count'];
}

echo json_encode([
  'ok' => true,
  'counts' => $data
]);
