<?php
/**
 * send_medical_application_email.php
 * Send status update email for Canada Medical Exams application using SMTP
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/mail_smtp.php';

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
if ($application_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
    exit;
}

// Get application details
$sql = "SELECT * FROM canada_medical_exams_requests WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $application_id);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();

if (!$application) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit;
}

// Send status update email
try {
    sendStatusUpdateEmail($application);
    echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
} catch (Exception $e) {
    error_log("Failed to send medical application email: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()]);
}

function sendStatusUpdateEmail($application) {
    $to = $application['email'];
    $subject = "Canada Medical Exams Request Update - " . $application['reference_id'];
    
    $name = $application['first_name'] . ' ' . $application['last_name'];
    $status = $application['status'];
    $reference_id = $application['reference_id'];
    
    $statusLabels = [
        'pending' => 'Received',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected'
    ];
    
    $statusLabel = $statusLabels[$status] ?? $status;
    
    $body = "
    <html>
    <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='margin: 0; font-size: 28px;'>Canada Medical Exams Request</h1>
            <p style='margin: 10px 0 0 0; opacity: 0.9;'>ScholarSync Global</p>
        </div>
        
        <div style='background: white; padding: 30px; border: 1px solid #e0e0e0; border-radius: 0 0 10px 10px;'>
            <h2 style='color: #333; margin-top: 0;'>Status Update</h2>
            
            <p>Dear $name,</p>
            
            <p>Your Canada Medical Exams request has been updated to <strong>$statusLabel</strong>.</p>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2563eb;'>
                <h4 style='margin-top: 0; color: #2563eb;'>Application Details</h4>
                <p><strong>Reference ID:</strong> $reference_id</p>
                <p><strong>Current Status:</strong> $statusLabel</p>
                <p><strong>Last Updated:</strong> " . date('F j, Y \a\t g:i A') . "</p>
            </div>
            
            <h3 style='color: #333;'>What's Next?</h3>";
    
    // Add status-specific content
    switch ($status) {
        case 'under_review':
            $body .= "<p>Your application is currently under review by our medical examination team. We will contact you within 3-5 business days with further instructions.</p>";
            break;
        case 'approved':
            $body .= "<p>Congratulations! Your medical examination request has been approved. You will receive detailed instructions about your medical examination appointment shortly.</p>";
            break;
        case 'rejected':
            $body .= "<p>Unfortunately, your medical examination request could not be approved at this time. Please contact our office for more information about next steps.</p>";
            break;
        default:
            $body .= "<p>Your application is being processed. We will notify you of any updates or required actions.</p>";
    }
    
    $body .= "
            <div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                <p style='margin: 0; color: #1976d2; font-size: 14px;'>
                    <strong>Note:</strong> If you have any questions about your application, please don't hesitate to contact us.
                </p>
            </div>
            
            <p style='margin-bottom: 0;'>If you need immediate assistance, please contact our support team.</p>
            
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; text-align: center; color: #666; font-size: 12px;'>
                <p style='margin: 0;'> 2024 ScholarSync Global. All rights reserved.</p>
                <p style='margin: 5px 0 0 0;'>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>";
    
    try {
        // Send email using SMTP
        $result = sendSMTPMail($to, $subject, $body);
        
        if (!$result) {
            throw new Exception("Failed to send email via SMTP");
        }
        
        error_log("Medical status email sent successfully to: " . $to);
        return true;
        
    } catch (Exception $e) {
        error_log("SMTP Email failed for medical status: " . $e->getMessage());
        throw $e;
    }
}
?>
