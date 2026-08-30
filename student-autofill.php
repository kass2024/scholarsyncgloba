<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=UTF-8');

/* =====================================================
   1. READ & VALIDATE INPUT
===================================================== */
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode(['possible_match' => false]);
    exit;
}

$emailInput = trim($data['email'] ?? '');

/* =====================================================
   2. EMAIL-FIRST VALIDATION
   - Must exist
   - Must be long enough to avoid noise
===================================================== */
if ($emailInput === '' || strlen($emailInput) < 3) {
    echo json_encode(['possible_match' => false]);
    exit;
}

/* =====================================================
   3. QUERY STUDENT BY EMAIL
   - Partial match while typing
   - Exact match ranked first
   - Latest record preferred
===================================================== */
$sql = "
    SELECT
        s.id,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.email,
        s.dob,

        /* Country NAME whether stored as id or already as a name string */
        COALESCE(c.name, s.nationality) AS nationality,

        s.passport_number,
        s.phone_number
    FROM student_applications s
    LEFT JOIN countries c
           ON c.id   = s.nationality
           OR c.name = s.nationality
    WHERE s.email LIKE ?
    ORDER BY
        CASE WHEN s.email = ? THEN 0 ELSE 1 END,
        s.id DESC
    LIMIT 1
";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['possible_match' => false]);
    exit;
}

$likeEmail = '%' . $emailInput . '%';
$stmt->bind_param('ss', $likeEmail, $emailInput);
$stmt->execute();

$result  = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

/* =====================================================
   4. NO MATCH FOUND
===================================================== */
if (!$student) {
    echo json_encode(['possible_match' => false]);
    exit;
}

/* =====================================================
   5. RETURN DATA (MATCHES JS EXPECTATIONS EXACTLY)
===================================================== */
$fullName = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
    trim((string) ($student['first_name']  ?? '')),
    trim((string) ($student['middle_name'] ?? '')),
    trim((string) ($student['last_name']   ?? '')),
], static fn($v) => $v !== ''))));

echo json_encode([
    'possible_match' => true,
    'student' => [
        'id'              => (int) $student['id'],
        'first_name'      => $student['first_name'] ?? '',
        'middle_name'     => $student['middle_name'] ?? '',
        'last_name'       => $student['last_name'] ?? '',
        'full_name'       => $fullName,
        'email'           => $student['email'] ?? '',
        'dob'             => $student['dob'] ?? '',
        'nationality'     => $student['nationality'] ?? '',
        'passport_number' => $student['passport_number'] ?? '',
        'phone_number'    => $student['phone_number'] ?? ''
    ]
]);
