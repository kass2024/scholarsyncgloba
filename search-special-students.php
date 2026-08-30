<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';

pcvc_require_superadmin($conn, true);

header('Content-Type: application/json; charset=utf-8');

$type = trim((string) ($_GET['type'] ?? ''));
$q    = trim((string) ($_GET['q'] ?? ''));

$allowedTypes = ['credit_transfer', 'upafa'];
if (!in_array($type, $allowedTypes, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid program type', 'results' => []]);
    exit;
}

$like = $q !== '' ? '%' . $q . '%' : null;
$results = [];

if ($type === 'credit_transfer') {
    if ($like !== null) {
        $sql = "SELECT id, user_id,
                       TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS full_name,
                       email, mobile_number AS phone, university, submitted_at
                FROM credit_transfer_applications
                WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
                      OR user_id LIKE ? OR mobile_number LIKE ? OR phone_number LIKE ?
                ORDER BY submitted_at DESC
                LIMIT 50";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ssssss', $like, $like, $like, $like, $like, $like);
        }
    } else {
        $sql = "SELECT id, user_id,
                       TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS full_name,
                       email, mobile_number AS phone, university, submitted_at
                FROM credit_transfer_applications
                ORDER BY submitted_at DESC
                LIMIT 25";
        $stmt = $conn->prepare($sql);
    }
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $results[] = [
                'id'         => (int) $row['id'],
                'table'      => 'credit_transfer_applications',
                'full_name'  => (string) ($row['full_name'] ?? ''),
                'email'      => (string) ($row['email'] ?? ''),
                'phone'      => (string) ($row['phone'] ?? ''),
                'ref'        => (string) ($row['user_id'] ?? ''),
                'extra'      => (string) ($row['university'] ?? ''),
                'created_at' => (string) ($row['submitted_at'] ?? ''),
            ];
        }
        $stmt->close();
    }
} else {
    if ($like !== null) {
        $sql = "SELECT id,
                       TRIM(CONCAT_WS(' ', first_name, last_name)) AS full_name,
                       email, telephone AS phone, academic_year, application_status, created_at
                FROM upafa_registrations
                WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
                      OR telephone LIKE ? OR academic_year LIKE ?
                ORDER BY created_at DESC
                LIMIT 50";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('sssss', $like, $like, $like, $like, $like);
        }
    } else {
        $sql = "SELECT id,
                       TRIM(CONCAT_WS(' ', first_name, last_name)) AS full_name,
                       email, telephone AS phone, academic_year, application_status, created_at
                FROM upafa_registrations
                ORDER BY created_at DESC
                LIMIT 25";
        $stmt = $conn->prepare($sql);
    }
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $results[] = [
                'id'         => (int) $row['id'],
                'table'      => 'upafa_registrations',
                'full_name'  => (string) ($row['full_name'] ?? ''),
                'email'      => (string) ($row['email'] ?? ''),
                'phone'      => (string) ($row['phone'] ?? ''),
                'ref'        => (string) ($row['academic_year'] ?? ''),
                'extra'      => (string) ($row['application_status'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
        $stmt->close();
    }
}

echo json_encode(['success' => true, 'results' => $results]);
$conn->close();
