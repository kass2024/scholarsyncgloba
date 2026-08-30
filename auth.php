<?php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['id'])) {
  header("Location: admin-login.php");
  exit;
}
?>
