<?php
/**
 * update_canada_medical_status.php
 * Update application status endpoint with notifications
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/canada_medical_status_notify.php';

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
$new_status = $_POST['status'] ?? '';
$rejection_reason = $_POST['rejection_reason'] ?? '';
$notify_email = intval($_POST['notify_email'] ?? 0);
$notify_whatsapp = intval($_POST['notify_whatsapp'] ?? 0);

if ($application_id <= 0 || empty($new_status)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Validate status
$allowed_statuses = ['pending', 'under_review', 'approved', 'rejected'];
if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Get current application details for notification
$app_sql = "SELECT * FROM canada_medical_exams_requests WHERE id = ?";
$app_stmt = $conn->prepare($app_sql);
$app_stmt->bind_param("i", $application_id);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Update status
    $sql = "UPDATE canada_medical_exams_requests SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_status, $application_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update status");
    }
    
    // Log the status change
    $admin_id = $_SESSION['id'];
    $log_sql = "INSERT INTO canada_medical_status_logs (application_id, old_status, new_status, admin_id, created_at) 
                SELECT id, status, ?, ?, NOW() FROM canada_medical_exams_requests WHERE id = ?";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("sii", $new_status, $admin_id, $application_id);
    $log_stmt->execute();
    
    // Send notifications
    if ($notify_email > 0 || $notify_whatsapp > 0) {
        $notification_success = xander_medical_notify(
            $application, 
            $new_status, 
            $rejection_reason, 
            $notify_email, 
            $notify_whatsapp
        );
        
        if (!$notification_success) {
            error_log("Notification failed for medical application {$application_id}");
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Status updated successfully',
        'notifications_sent' => ($notify_email > 0 || $notify_whatsapp > 0)
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Canada Medical status update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $e->getMessage()]);
}
?>
