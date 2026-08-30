<?php
require_once __DIR__ . '/db.php';
$school_name = $_POST['school_name'];
$school_website = $_POST['school_website']; // ⭐ NEW FIELD
$category = $_POST['category'];
$status = $_POST['status'];

$sql = "INSERT INTO schools (school_name, school_website, category, status)
        VALUES ('$school_name', '$school_website', '$category', '$status')";

if ($conn->query($sql) === TRUE) {
    echo "success";
} else {
    echo "error";
}
?>
