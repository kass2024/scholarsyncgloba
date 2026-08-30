<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php'; // must define $conn (mysqli)

header('Content-Type: application/json; charset=utf-8');

// ========================
// INPUT VALIDATION
// ========================

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode([
        'ok' => true,
        'results' => []
    ]);
    exit;
}

// ========================
// QUERY PREPARATION
// ========================

$like = '%' . $q . '%';

$sql = "
SELECT
    id,
    first_name,
    last_name,
    email,
    phone_number
FROM student_applications
WHERE
    CONCAT(first_name,' ',last_name) LIKE ?
    OR first_name LIKE ?
    OR last_name LIKE ?
    OR email LIKE ?
    OR phone_number LIKE ?
ORDER BY id DESC
LIMIT 10
";

// ========================
// PREPARE
// ========================

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Database prepare failed'
    ]);
    exit;
}

// ========================
// BIND + EXECUTE
// ========================

$stmt->bind_param('sssss', $like, $like, $like, $like, $like);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Database execute failed'
    ]);
    exit;
}

$result = $stmt->get_result();

// ========================
// FORMAT OUTPUT
// ========================

$students = [];

while ($row = $result->fetch_assoc()) {

    $first = $row['first_name'] ?? '';
    $last  = $row['last_name'] ?? '';

    $name = trim($first . ' ' . $last);
    if ($name === '') {
        $name = 'Student #' . (int)$row['id'];
    }

    $students[] = [
        'id'    => (int)$row['id'],
        'name'  => $name,
        'email' => $row['email'] ?? '',
        'phone' => $row['phone_number'] ?? ''
    ];
}

$stmt->close();

// ========================
// RESPONSE (CLEAN JSON)
// ========================

echo json_encode([
    'ok' => true,
    'results' => $students
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

exit;