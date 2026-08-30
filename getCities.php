<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) < 2) {
  echo json_encode([]);
  exit;
}

$sql = "
  SELECT
    c.id,
    CONCAT(
      c.city_name, ' - ',
      co.name, ' (', c.airport_code, ')'
    ) AS text
  FROM cities c
  JOIN countries co ON co.id = c.country_id
  WHERE c.is_active = 1
    AND (
      c.city_name LIKE CONCAT('%', ?, '%')
      OR c.airport_code LIKE CONCAT('%', ?, '%')
      OR co.name LIKE CONCAT('%', ?, '%')
    )
  ORDER BY c.city_name
  LIMIT 20
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $q, $q, $q);
$stmt->execute();

$res = $stmt->get_result();
$data = [];

while ($row = $res->fetch_assoc()) {
  $data[] = [
    'id'   => $row['id'],
    'text' => $row['text']
  ];
}

echo json_encode($data);
