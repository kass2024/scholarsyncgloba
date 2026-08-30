<?php
/**
 * save_canada_medical_request.php
 * Backend handler for Canada Medical Exams Request Form
 */

session_start();
require_once __DIR__ . '/db.php';

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Validate session
if (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Validate required fields
$required_fields = [
    'first_name', 'last_name', 'email', 'phone_area_code', 'phone_number',
    'address', 'emergency_full_name', 'emergency_relationship',
    'emergency_area_code', 'emergency_phone_number', 'emergency_email',
    'passport_file', 'cv_file', 'payment_proof_file', 'medical_report_form_file'
];

foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Sanitize inputs
$first_name = mysqli_real_escape_string($conn, trim($_POST['first_name']));
$last_name = mysqli_real_escape_string($conn, trim($_POST['last_name']));
$email = mysqli_real_escape_string($conn, trim($_POST['email']));
$phone_area_code = mysqli_real_escape_string($conn, trim($_POST['phone_area_code']));
$phone_number = mysqli_real_escape_string($conn, trim($_POST['phone_number']));
$address = mysqli_real_escape_string($conn, trim($_POST['address']));
$emergency_full_name = mysqli_real_escape_string($conn, trim($_POST['emergency_full_name']));
$emergency_relationship = mysqli_real_escape_string($conn, trim($_POST['emergency_relationship']));
$emergency_area_code = mysqli_real_escape_string($conn, trim($_POST['emergency_area_code']));
$emergency_phone_number = mysqli_real_escape_string($conn, trim($_POST['emergency_phone_number']));
$emergency_email = mysqli_real_escape_string($conn, trim($_POST['emergency_email']));
$user_id = mysqli_real_escape_string($conn, trim($_POST['user_id']));

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (!filter_var($emergency_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid emergency email format']);
    exit;
}

// Handle file uploads - move from temp to permanent location
$passport_file = moveUploadedFile($_POST['passport_file'], 'passport');
$cv_file = moveUploadedFile($_POST['cv_file'], 'cv');
$payment_proof_file = moveUploadedFile($_POST['payment_proof_file'], 'payment_proof');
$medical_report_form_file = moveUploadedFile($_POST['medical_report_form_file'], 'medical_report_form');

// Generate unique reference ID
$reference_id = 'CMED' . date('Y') . strtoupper(substr(uniqid(), -8));

// Check for duplicate application
$check_sql = "SELECT id FROM canada_medical_exams_requests WHERE user_id = ? OR email = ?";
$check_stmt = $conn->prepare($check_sql);
if ($check_stmt) {
    $check_stmt->bind_param("ss", $user_id, $email);
    $check_stmt->execute();
    $check_stmt->store_result();
    if ($check_stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already submitted a request']);
        exit;
    }
    $check_stmt->close();
}

// Start transaction
$conn->begin_transaction();

try {
    // Insert the request
    $sql = "INSERT INTO canada_medical_exams_requests (
        user_id, reference_id, first_name, last_name, email, 
        phone_area_code, phone_number, address,
        emergency_full_name, emergency_relationship, emergency_area_code, emergency_phone_number, emergency_email,
        passport_file, cv_file, payment_proof_file, medical_report_form_file,
        status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param(
        "sssssssssssssssss",
        $user_id, $reference_id, $first_name, $last_name, $email,
        $phone_area_code, $phone_number, $address,
        $emergency_full_name, $emergency_relationship, $emergency_area_code, $emergency_phone_number, $emergency_email,
        $passport_file, $cv_file, $payment_proof_file, $medical_report_form_file
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $request_id = $stmt->insert_id;
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Send notification email (async if possible)
    try {
        sendNotificationEmail($request_id, $reference_id, $first_name, $last_name, $email);
    } catch (Exception $e) {
        error_log("Failed to send notification email: " . $e->getMessage());
        // Don't fail the request if email fails
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Request submitted successfully',
        'reference_id' => $reference_id,
        'request_id' => $request_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Canada Medical Request save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to save request: ' . $e->getMessage()]);
}

function moveUploadedFile($tempPath, $field) {
    global $conn;
    
    if (empty($tempPath)) {
        throw new Exception("$field file is required");
    }
    
    // Clean the path
    $tempPath = trim($tempPath);
    
    // Security: Validate file path
    $baseDir = __DIR__ . '/uploads/tmp/';
    $fullTempPath = realpath(__DIR__ . '/' . $tempPath);
    
    if (!$fullTempPath || !file_exists($fullTempPath) || strpos($fullTempPath, realpath($baseDir)) !== 0) {
        throw new Exception("Invalid $field file path");
    }
    
    // Create permanent upload directory
    $permDir = __DIR__ . '/uploads/canada_medical/';
    if (!file_exists($permDir)) {
        mkdir($permDir, 0755, true);
    }
    
    // Generate permanent filename
    $extension = strtolower(pathinfo($fullTempPath, PATHINFO_EXTENSION));
    $filename = 'canada_medical_' . date('Y') . '_' . uniqid() . "_{$field}." . $extension;
    $permPath = $permDir . $filename;
    
    // Move file
    if (!rename($fullTempPath, $permPath)) {
        throw new Exception("Failed to move $field file");
    }
    
    // Return relative path for database
    return 'uploads/canada_medical/' . $filename;
}

function sendNotificationEmail($request_id, $reference_id, $first_name, $last_name, $email) {
    require_once __DIR__ . '/helpers/mail_smtp.php';
    
    // Get admin emails for notification
    $admin_sql = "SELECT email FROM admins WHERE role IN ('superadmin', 'staff') AND status = 'active'";
    $admin_result = mysqli_query($GLOBALS['conn'], $admin_sql);
    
    $admin_emails = [];
    while ($admin = mysqli_fetch_assoc($admin_result)) {
        $admin_emails[] = $admin['email'];
    }
    
    // Get form data for admin email
    $phone_area_code = $_POST['phone_area_code'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $address = $_POST['address'] ?? '';
    $emergency_full_name = $_POST['emergency_full_name'] ?? '';
    $emergency_relationship = $_POST['emergency_relationship'] ?? '';
    $emergency_email = $_POST['emergency_email'] ?? '';
    $emergency_area_code = $_POST['emergency_area_code'] ?? '';
    $emergency_phone_number = $_POST['emergency_phone_number'] ?? '';
    
    // Email to applicant
    $applicant_subject = "Canada Medical Exams Request Received - Reference: " . $reference_id;
    $applicant_body = "
    <html>
    <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='margin: 0; font-size: 28px;'>Canada Medical Exams Request</h1>
            <p style='margin: 10px 0 0 0; opacity: 0.9;'>ScholarSync Global</p>
        </div>
        
        <div style='background: white; padding: 30px; border: 1px solid #e0e0e0; border-radius: 0 0 10px 10px;'>
            <h2 style='color: #333; margin-top: 0;'>Request Received Successfully</h2>
            
            <p>Dear $first_name $last_name,</p>
            
            <p>Thank you for submitting your Canada Medical Exams request. We have received your application and it is currently under review.</p>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2563eb;'>
                <h3 style='margin-top: 0; color: #2563eb;'>Your Reference ID</h3>
                <p style='font-family: monospace; font-size: 18px; font-weight: bold; color: #2563eb; margin: 10px 0;'>$reference_id</p>
                <p style='margin-bottom: 0; font-size: 14px; color: #666;'>Please save this reference number for future communications</p>
            </div>
            
            <h3 style='color: #333;'>Next Steps:</h3>
            <ul style='color: #666; line-height: 1.6;'>
                <li>Our team will review your request within 3-5 business days</li>
                <li>We will contact you if any additional information is required</li>
                <li>You will receive updates via email regarding your request status</li>
            </ul>
            
            <div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                <p style='margin: 0; color: #1976d2; font-size: 14px;'>
                    <strong>Note:</strong> Please check your spam folder if you don't receive our emails within the expected timeframe.
                </p>
            </div>
            
            <p style='margin-bottom: 0;'>If you have any questions, please don't hesitate to contact us.</p>
            
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; text-align: center; color: #666; font-size: 12px;'>
                <p style='margin: 0;'>© 2024 ScholarSync Global. All rights reserved.</p>
                <p style='margin: 5px 0 0 0;'>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Email to admins
    $admin_subject = "New Canada Medical Exams Request - " . $reference_id;
    $admin_body = "
    <html>
    <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='margin: 0; font-size: 28px;'>New Request Received</h1>
            <p style='margin: 10px 0 0 0; opacity: 0.9;'>Canada Medical Exams</p>
        </div>
        
        <div style='background: white; padding: 30px; border: 1px solid #e0e0e0; border-radius: 0 0 10px 10px;'>
            <h2 style='color: #333; margin-top: 0;'>Request Details</h2>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2563eb;'>
                <h3 style='margin-top: 0; color: #2563eb;'>Reference ID: $reference_id</h3>
                <p style='margin-bottom: 0; font-size: 14px; color: #666;'>Submitted on: " . date('Y-m-d H:i:s') . "</p>
            </div>
            
            <h3 style='color: #333;'>Applicant Information:</h3>
            <ul style='color: #666; line-height: 1.6;'>
                <li><strong>Name:</strong> $first_name $last_name</li>
                <li><strong>Email:</strong> $email</li>
                <li><strong>Phone:</strong> +$phone_area_code $phone_number</li>
                <li><strong>Address:</strong> " . htmlspecialchars($address) . "</li>
            </ul>
            
            <h3 style='color: #333;'>Emergency Contact:</h3>
            <ul style='color: #666; line-height: 1.6;'>
                <li><strong>Name:</strong> $emergency_full_name ($emergency_relationship)</li>
                <li><strong>Email:</strong> $emergency_email</li>
                <li><strong>Phone:</strong> +$emergency_area_code $emergency_phone_number</li>
            </ul>
            
            <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                <p style='margin: 0; color: #856404; font-size: 14px;'>
                    <strong>Action Required:</strong> Please review this request in the admin panel and update the status accordingly.
                </p>
            </div>
            
            <p style='margin-bottom: 0;'>Log in to the admin panel to view and manage this request.</p>
        </div>
    </body>
    </html>
    ";
    
    try {
        // Send to applicant using SMTP
        $applicant_sent = sendSMTPMail($email, $applicant_subject, $applicant_body);
        
        if (!$applicant_sent) {
            throw new Exception("Failed to send email to applicant");
        }
        
        // Send to admins using SMTP
        $admin_sent_count = 0;
        foreach ($admin_emails as $admin_email) {
            if (sendSMTPMail($admin_email, $admin_subject, $admin_body)) {
                $admin_sent_count++;
            }
        }
        
        error_log("Medical request notification sent: Applicant=$applicant_sent, Admins=$admin_sent_count/" . count($admin_emails));
        
    } catch (Exception $e) {
        error_log("SMTP Email failed for medical request: " . $e->getMessage());
        throw $e;
    }
}
?>
