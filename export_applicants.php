<?php
// export_applicants.php
require_once __DIR__ . '/db.php';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=job_applicants_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'ID', 'User ID', 'First Name', 'Last Name', 'Email', 'Phone', 
    'Work Country ID', 'Address Country ID', 'Province/State', 'District',
    'Sector', 'Cell/Ward', 'Village', 'Emergency Contact', 'Relationship',
    'Emergency Email', 'Emergency Phone', 'Application Date'
]);

// Fetch data
$sql = "SELECT * FROM job_applications ORDER BY created_at DESC";
$result = $conn->query($sql);

while($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['user_id'],
        $row['first_name'],
        $row['last_name'],
        $row['email'],
        $row['phone_area_code'] . ' ' . $row['phone_number'],
        $row['work_country_id'],
        $row['address_country_id'],
        $row['province_state'],
        $row['district'],
        $row['sector'],
        $row['cell_ward'],
        $row['village'],
        $row['emergency_full_name'],
        $row['emergency_relationship'],
        $row['emergency_email'],
        $row['emergency_area_code'] . ' ' . $row['emergency_phone_number'],
        $row['created_at']
    ]);
}

fclose($output);
$conn->close();
exit();
?>