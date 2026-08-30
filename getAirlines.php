<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "
  SELECT id,
         CONCAT(name,
           IF(iata_code IS NOT NULL AND iata_code != '',
              CONCAT(' (', iata_code, ')'),
              '')
         ) AS text
  FROM airlines
  WHERE active = 1
";

$params = [];
$types = '';

if ($q !== '') {
  $sql .= " AND (name LIKE ? OR iata_code LIKE ? OR country LIKE ?)";
  $search = "%$q%";
  $params = [$search, $search, $search];
  $types = 'sss';
}

$sql .= " ORDER BY name ASC LIMIT 50";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo json_encode([]);
  exit;
}

if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
  $data[] = [
    'id'   => $row['id'],
    'text' => $row['text']
  ];
}

echo json_encode($data);
