<?php
header('Content-Type: application/json');
require_once 'db.php'; // MUST create $conn (mysqli)

/* ===============================
   INPUT
=============================== */
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

/* ===============================
   QUERY
   - City is primary keyword
   - Airport name + IATA also searchable
=============================== */
$sql = "
    SELECT 
        id,
        display_name AS text
    FROM airports
    WHERE is_active = 1
      AND (
          city LIKE ?
          OR airport_name LIKE ?
          OR iata_code LIKE ?
      )
    ORDER BY city ASC
    LIMIT 25
";

$like = "%{$q}%";

/* ===============================
   PREPARE & EXECUTE
=============================== */
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([]);
    exit;
}

$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();

/* ===============================
   OUTPUT FOR SELECT2
=============================== */
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id'   => $row['id'],
        'text' => $row['text']
    ];
}

echo json_encode($data);
