<?php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== "superadmin" && $_SESSION['role'] !== "hr")) {
    $_SESSION['error'] = "Unauthorized action.";
    header("Location: salary-report.php");
    exit;
}

$id = intval($_GET['id']);
$status = $_GET['status'];

if (!in_array($status, ['approved', 'rejected'])) {
    $_SESSION['error'] = "Invalid status.";
    header("Location: salary-report.php");
    exit;
}

$stmt = $conn->prepare("UPDATE salary_requests SET status=? WHERE id=?");
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Request updated to: $status.";
} else {
    $_SESSION['error'] = "Failed to update request.";
}

header("Location: salary-report.php");
exit;
