<?php
// get_applicants.php - AJAX endpoint for DataTables
require_once __DIR__ . '/db.php';
// Get parameters from DataTables
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
$orderColumn = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
$orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'desc';

// Map DataTables columns to database columns
$columns = ['id', 'last_name', 'email', 'highest_education', 'intended_degree', 'application_status', 'created_at'];

// Build WHERE clause for search
$where = '';
$params = [];
$types = '';

if (!empty($searchValue)) {
    $where = "WHERE (last_name LIKE ? OR first_name LIKE ? OR email LIKE ? OR telephone LIKE ? 
              OR nationality LIKE ? OR field_of_study LIKE ?)";
    $searchParam = "%$searchValue%";
    $params = array_fill(0, 6, $searchParam);
    $types = str_repeat('s', 6);
}

// Get total records
$totalQuery = "SELECT COUNT(*) as total FROM upafa_registrations";
$totalResult = $conn->query($totalQuery);
$totalRecords = $totalResult->fetch_assoc()['total'];

// Get filtered records
$filteredQuery = "SELECT COUNT(*) as filtered FROM upafa_registrations $where";
if ($where) {
    $stmt = $conn->prepare($filteredQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $filteredRecords = $result->fetch_assoc()['filtered'];
    $stmt->close();
} else {
    $result = $conn->query($filteredQuery);
    $filteredRecords = $result->fetch_assoc()['filtered'];
}

// Get data
$orderBy = "ORDER BY " . $columns[$orderColumn] . " " . $orderDir;
$query = "SELECT * FROM upafa_registrations $where $orderBy LIMIT ? OFFSET ?";

if ($where) {
    $stmt = $conn->prepare($query);
    $params[] = $length;
    $params[] = $start;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $length, $start);
    $stmt->execute();
    $result = $stmt->get_result();
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Return JSON response
echo json_encode([
    'draw' => isset($_POST['draw']) ? intval($_POST['draw']) : 1,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $data
]);
?>