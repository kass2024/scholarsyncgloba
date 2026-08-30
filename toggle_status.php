<?php
require_once __DIR__ . '/db.php';
$id = $_POST['id'] ?? 0;

if ($id) {
  $query = "UPDATE members 
            SET status = IF(status='Active', 'Inactive', 'Active') 
            WHERE id='$id'";
  echo mysqli_query($conn, $query) ? "success" : "error";
}
?>
