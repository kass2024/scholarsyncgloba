<?php
// db.php

$host = 'localhost';  // Database host
$user = 'root';       // Database username
$pass = '';           // Database password (default: empty for XAMPP)
$dbname = 'scholarsyncglobal';   // Change this to your actual database name

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Set charset to avoid charset issues (utf8mb4 is best for supporting all characters)
$conn->set_charset("utf8mb4");

// Force UTC timezone for this session
$conn->query("SET time_zone = '+00:00'");
?>
