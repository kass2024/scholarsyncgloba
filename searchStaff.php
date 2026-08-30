<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
$initial = isset($_GET['initial']) && (string)$_GET['initial'] === '1';

$baseSelect = "
   SELECT
    id,
    first_name,
    last_name,
    email,
    username,
    COALESCE(
      NULLIF(TRIM(full_name), ''),
      TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))
    ) AS full_name
FROM admins
WHERE LOWER(TRIM(COALESCE(role, ''))) = 'staff'
";

/* First suggestions (no query) — at least 5 when that many exist */
if ($initial && strlen($q) < 2) {
    $sql = $baseSelect . "
ORDER BY last_name ASC, first_name ASC, id ASC
LIMIT 5
";

    $res = $conn->query($sql);
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    echo json_encode($rows);
    exit;
}

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$search = '%' . $q . '%';

$sql = $baseSelect . "
  AND (
        full_name LIKE ?
     OR first_name LIKE ?
     OR last_name LIKE ?
     OR email LIKE ?
     OR username LIKE ?
  )
ORDER BY last_name ASC, first_name ASC, id ASC
LIMIT 50
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([]);
    exit;
}

$stmt->bind_param('sssss', $search, $search, $search, $search, $search);
$stmt->execute();

$result = $stmt->get_result();
$rows = [];

while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$stmt->close();

echo json_encode($rows);
