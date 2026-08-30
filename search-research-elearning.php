<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/research_elearning_schema.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string) ($_GET['q'] ?? ''));
$like = $q !== '' ? '%' . $q . '%' : null;
$results = [];

function research_push_result(array &$results, array $row): void
{
    $results[] = $row;
}

// Credit Transfer applications (all universities — no UPAFA-only filter)
if ($like !== null) {
    $sql = "SELECT id, user_id,
                   TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS full_name,
                   email, COALESCE(NULLIF(mobile_number, ''), phone_number) AS phone,
                   university, submitted_at
            FROM credit_transfer_applications
            WHERE first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR email LIKE ?
                  OR user_id LIKE ? OR mobile_number LIKE ? OR phone_number LIKE ?
                  OR university LIKE ?
            ORDER BY submitted_at DESC
            LIMIT 50";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ssssssss', $like, $like, $like, $like, $like, $like, $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            research_push_result($results, [
                'id'         => (int) $row['id'],
                'table'      => 'credit_transfer_applications',
                'program'    => 'Credit Transfer',
                'full_name'  => (string) ($row['full_name'] ?? ''),
                'email'      => (string) ($row['email'] ?? ''),
                'phone'      => (string) ($row['phone'] ?? ''),
                'ref'        => (string) ($row['user_id'] ?? ''),
                'extra'      => (string) ($row['university'] ?? ''),
                'created_at' => (string) ($row['submitted_at'] ?? ''),
            ]);
        }
        $stmt->close();
    }
} else {
    $sql = "SELECT id, user_id,
                   TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS full_name,
                   email, COALESCE(NULLIF(mobile_number, ''), phone_number) AS phone,
                   university, submitted_at
            FROM credit_transfer_applications
            ORDER BY submitted_at DESC
            LIMIT 25";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            research_push_result($results, [
                'id'         => (int) $row['id'],
                'table'      => 'credit_transfer_applications',
                'program'    => 'Credit Transfer',
                'full_name'  => (string) ($row['full_name'] ?? ''),
                'email'      => (string) ($row['email'] ?? ''),
                'phone'      => (string) ($row['phone'] ?? ''),
                'ref'        => (string) ($row['user_id'] ?? ''),
                'extra'      => (string) ($row['university'] ?? ''),
                'created_at' => (string) ($row['submitted_at'] ?? ''),
            ]);
        }
        $stmt->close();
    }
}

// UPAFA registrations
if ($like !== null) {
    $sql = "SELECT id,
                   TRIM(CONCAT_WS(' ', first_name, last_name)) AS full_name,
                   email, telephone AS phone, academic_year, field_of_study, department, created_at
            FROM upafa_registrations
            WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
                  OR telephone LIKE ? OR academic_year LIKE ?
                  OR field_of_study LIKE ? OR department LIKE ? OR school_name_address LIKE ?
            ORDER BY created_at DESC
            LIMIT 50";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ssssssss', $like, $like, $like, $like, $like, $like, $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            research_push_result($results, [
                'id'         => (int) $row['id'],
                'table'      => 'upafa_registrations',
                'program'    => 'UPAFA',
                'full_name'  => (string) ($row['full_name'] ?? ''),
                'email'      => (string) ($row['email'] ?? ''),
                'phone'      => (string) ($row['phone'] ?? ''),
                'ref'        => 'UPAFA-' . (int) $row['id'],
                'extra'      => trim((string) (($row['field_of_study'] ?? '') ?: ($row['department'] ?? ''))),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ]);
        }
        $stmt->close();
    }
} else {
    $sql = "SELECT id,
                   TRIM(CONCAT_WS(' ', first_name, last_name)) AS full_name,
                   email, telephone AS phone, academic_year, field_of_study, department, created_at
            FROM upafa_registrations
            ORDER BY created_at DESC
            LIMIT 25";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            research_push_result($results, [
                'id'         => (int) $row['id'],
                'table'      => 'upafa_registrations',
                'program'    => 'UPAFA',
                'full_name'  => (string) ($row['full_name'] ?? ''),
                'email'      => (string) ($row['email'] ?? ''),
                'phone'      => (string) ($row['phone'] ?? ''),
                'ref'        => 'UPAFA-' . (int) $row['id'],
                'extra'      => trim((string) (($row['field_of_study'] ?? '') ?: ($row['department'] ?? ''))),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ]);
        }
        $stmt->close();
    }
}

usort($results, static function (array $a, array $b): int {
    return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
});

if (count($results) > 50) {
    $results = array_slice($results, 0, 50);
}

$statusMap = pcvc_research_elearning_status_map($conn, $results);
foreach ($results as &$row) {
    $key = (string) ($row['table'] ?? '') . ':' . (int) ($row['id'] ?? 0);
    $status = $statusMap[$key] ?? null;
    $listStatus = pcvc_research_elearning_list_status($status);
    $row['uploaded_count'] = $listStatus['uploaded_count'];
    $row['total_count'] = $listStatus['total_count'];
    $row['status_label'] = $listStatus['label'];
    $row['status_badge'] = $listStatus['badge'];
    $row['overall_status'] = (string) ($status['overall_status'] ?? 'not_started');
}
unset($row);

echo json_encode(['success' => true, 'results' => $results]);
$conn->close();
