<?php
require_once __DIR__ . '/db.php';
$id = $_POST['id'];
$school_name = $_POST['school_name'];
$school_website = $_POST['school_website']; // ⭐ NEW FIELD
$category = $_POST['category'];
$status = $_POST['status'];

$sql = "UPDATE schools 
        SET school_name='$school_name',
            school_website='$school_website',  /* ⭐ ADDED WEBSITE UPDATE */
            category='$category',
            status='$status'
        WHERE id='$id'";

if ($conn->query($sql) === TRUE) {
    echo "success";
} else {
    echo "error";
}
?>
