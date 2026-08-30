<?php
require 'db.php'; // must define $conn = new mysqli(...)

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$search = "%{$q}%";

$sql = "
   SELECT 
    id,
    first_name,
    last_name,
    email,
    username,
    COALESCE(
      NULLIF(TRIM(full_name), ''),
      TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))
    ) AS full_name,
    COALESCE(role, '') AS role
FROM admins
WHERE (
        full_name LIKE ?
     OR first_name LIKE ?
     OR last_name LIKE ?
     OR email LIKE ?
     OR username LIKE ?
     OR role LIKE ?
  )
ORDER BY last_name ASC, first_name ASC, id ASC
LIMIT 50

";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([]);
    exit;
}

$stmt->bind_param("ssssss", $search, $search, $search, $search, $search, $search);
$stmt->execute();

$result = $stmt->get_result();
$agents = [];

while ($row = $result->fetch_assoc()) {
    $agents[] = $row;
}

$stmt->close();

header('Content-Type: application/json');
echo json_encode($agents);
