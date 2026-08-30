<?php
/**
 * save_medical_application_notes.php
 * Save admin notes for Canada Medical Exams Application
 */

session_start();
require_once __DIR__ . '/db.php';

// Security headers
header('Content-Type: application/json');

// Validate session and CSRF
if (!isset($_SESSION['id']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Validate input
$application_id = intval($_POST['application_id'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if ($application_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
    exit;
}

// Add admin_notes column if it doesn't exist
$check_column_sql = "SHOW COLUMNS FROM canada_medical_exams_requests LIKE 'admin_notes'";
$column_result = $conn->query($check_column_sql);
if ($column_result->num_rows === 0) {
    $alter_sql = "ALTER TABLE canada_medical_exams_requests ADD COLUMN admin_notes TEXT DEFAULT NULL AFTER ai_validation_result";
    $conn->query($alter_sql);
}

// Update notes
$sql = "UPDATE canada_medical_exams_requests SET admin_notes = ?, updated_at = NOW() WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $notes, $application_id);

if ($stmt->execute()) {
    // Log the note update
    $admin_id = $_SESSION['id'];
    $log_sql = "INSERT INTO canada_medical_status_logs (application_id, old_status, new_status, admin_id, created_at) 
                SELECT id, status, status, ?, NOW() FROM canada_medical_exams_requests WHERE id = ?";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("ii", $admin_id, $application_id);
    $log_stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Notes saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save notes']);
}
?>
