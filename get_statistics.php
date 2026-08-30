<?php
// get_statistics.php
require_once __DIR__ . '/db.php';
$stats = [
    'total' => 0,
    'pending' => 0,
    'under_review' => 0,
    'accepted' => 0,
    'rejected' => 0,
    'with_files' => 0
];

// Get counts by status
$query = "SELECT application_status, COUNT(*) as count 
          FROM upafa_registrations 
          GROUP BY application_status";
$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    $stats[$row['application_status']] = $row['count'];
    $stats['total'] += $row['count'];
}

// Count applications with files
$query = "SELECT COUNT(DISTINCT r.id) as with_files
          FROM upafa_registrations r
          INNER JOIN upafa_registration_files f ON r.id = f.registration_id";
$result = $conn->query($query);
$stats['with_files'] = $result->fetch_assoc()['with_files'];

header('Content-Type: application/json');
echo json_encode($stats);
?>