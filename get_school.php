<?php
require_once __DIR__ . '/db.php';
$id = $_GET['id'];

$sql = "SELECT * FROM schools WHERE id = '$id' LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {

    // Fetch row containing school_name, school_website, category, status, etc.
    $school = $result->fetch_assoc();

    echo json_encode($school);

} else {
    echo json_encode([]);
}
?>
