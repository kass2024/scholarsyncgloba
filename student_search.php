<?php
header('Content-Type: application/json');
include 'db.php'; // mysqli connection

// Get search term from request
$term = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if ($term !== '') {
    // Prepare SQL to search first_name, last_name, or email
    $stmt = $conn->prepare("
        SELECT first_name, last_name, email
        FROM student_applications
        WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
        LIMIT 20
    ");
    $like = "%$term%";
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $fullName = trim($row['first_name'] . " " . $row['last_name']);
        $results[] = [
            "id"    => $row['email'],  // Select2 requires an "id"
            "text"  => $fullName,      // This will be shown in dropdown
            "email" => $row['email']   // Extra field we can use in JS
        ];
    }
    $stmt->close();
}

// Return JSON
echo json_encode($results);
