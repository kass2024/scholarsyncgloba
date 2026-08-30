<?php
/**
 * Canada Medical Exams status notifications (email / WhatsApp).
 * Based on helpers/application_status_notify.php for job applications.
 *
 * Meta templates: set constants below to match approved template names.
 * If empty, only session messages (24h window) are used for WhatsApp.
 */
require_once __DIR__ . '/mail_smtp.php';
require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/student_status_notify.php';

/** Canada Medical WhatsApp template ({{1}} name, {{2}} status, {{3}} details) */
const XANDER_WHATSAPP_MEDICAL_TEMPLATE_NAME = '';
const XANDER_WHATSAPP_MEDICAL_TEMPLATE_LANG = 'en_US';
const XANDER_WHATSAPP_MEDICAL_TEMPLATE_PARAMS = 3;

/**
 * @return array<int, string>
 */
function xander_medical_notify_detail_lines(array $row): array
{
    $lines = [];
    $ref = trim((string) ($row['reference_id'] ?? ''));
    if ($ref !== '') {
        $lines[] = 'Reference: ' . $ref;
    }
    
    $addr = trim((string) ($row['address'] ?? ''));
    if ($addr !== '') {
        $lines[] = 'Address: ' . $addr;
    }
    
    $emergency = trim((string) ($row['emergency_full_name'] ?? ''));
    $rel = trim((string) ($row['emergency_relationship'] ?? ''));
    $ephone = trim((string) ($row['emergency_area_code'] ?? '') . ' ' . (string) ($row['emergency_phone_number'] ?? ''));
    if ($emergency !== '') {
        $lines[] = 'Emergency: ' . $emergency . ' (' . $rel . ')';
        if ($ephone !== '') {
            $lines[] = 'Emergency Phone: ' . $ephone;
        }
    }
    
    return $lines;
}

/**
 * Send email notification for Canada Medical Exams status change
 */
function xander_medical_notify_email(array $row, string $status, string $rejectionReason = ''): bool
{
    require_once __DIR__ . '/mail_smtp.php';
    
    $to = trim((string) ($row['email'] ?? ''));
    if ($to === '') {
        return false;
    }

    $name = trim((string) ($row['first_name'] . ' ' . $row['last_name']));
    $subject = "Canada Medical Exams Request Update - " . ($row['reference_id'] ?? 'Unknown');
    
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
            
            <p>Your Canada Medical Exams request has been updated to <strong>$statusLabel</strong>.</p>";
    
    // Add rejection reason if provided
    if ($rejectionReason !== '') {
        $body .= "
            <div style='background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 15px; margin: 20px 0;'>
                <h4 style='color: #dc2626; margin-top: 0;'>Reason for update:</h4>
                <p style='margin-bottom: 0;'>$rejectionReason</p>
            </div>";
    }
    
    $body .= "
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2563eb;'>
                <h4 style='margin-top: 0; color: #2563eb;'>Application Details</h4>
                <p><strong>Reference ID:</strong> " . ($row['reference_id'] ?? 'Unknown') . "</p>
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
                <p style='margin: 0;'>© 2024 ScholarSync Global. All rights reserved.</p>
                <p style='margin: 5px 0 0 0;'>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>";
    
    try {
        // Send email using SMTP
        $result = sendSMTPMail($to, $subject, $body);
        
        if (!$result) {
            error_log("Failed to send medical status email to: " . $to);
            return false;
        }
        
        error_log("Medical status email sent successfully to: " . $to);
        return true;
        
    } catch (Exception $e) {
        error_log("SMTP Email failed for medical status: " . $e->getMessage());
        return false;
    }
}

/**
 * Send WhatsApp notification for Canada Medical Exams status change
 */
function xander_medical_notify_whatsapp(array $row, string $status, string $rejectionReason = ''): bool
{
    $phone = trim((string) ($row['emergency_area_code'] ?? '') . (string) ($row['emergency_phone_number'] ?? ''));
    if ($phone === '') {
        return false;
    }
    
    // Remove any non-digit characters for WhatsApp
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    $name = trim((string) ($row['first_name'] . ' ' . $row['last_name']));
    $statusLabels = [
        'pending' => 'Received',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected'
    ];
    
    $statusLabel = $statusLabels[$status] ?? $status;
    
    // Prepare template parameters
    $templateBodyTexts = [
        $name ?: 'Applicant',
        $statusLabel,
        xander_medical_notify_detail_lines($row)
    ];
    
    // Add rejection reason if provided
    if ($rejectionReason !== '') {
        $templateBodyTexts[] = 'Reason: ' . $rejectionReason;
    }
    
    return xander_whatsapp_send_template_or_session(
        $phone,
        XANDER_WHATSAPP_MEDICAL_TEMPLATE_NAME,
        XANDER_WHATSAPP_MEDICAL_TEMPLATE_LANG,
        XANDER_WHATSAPP_MEDICAL_TEMPLATE_PARAMS,
        $templateBodyTexts
    );
}

/**
 * Main notification function - sends email and/or WhatsApp based on preferences
 */
function xander_medical_notify(array $row, string $newStatus, string $rejectionReason = '', int $notifyEmail = 0, int $notifyWhatsapp = 0): bool
{
    $success = true;
    
    // Send email notification
    if ($notifyEmail > 0) {
        $emailSuccess = xander_medical_notify_email($row, $newStatus, $rejectionReason);
        if (!$emailSuccess) {
            error_log("Failed to send medical email notification for reference: " . ($row['reference_id'] ?? 'unknown'));
            $success = false;
        }
    }
    
    // Send WhatsApp notification
    if ($notifyWhatsapp > 0) {
        $whatsappSuccess = xander_medical_notify_whatsapp($row, $newStatus, $rejectionReason);
        if (!$whatsappSuccess) {
            error_log("Failed to send medical WhatsApp notification for reference: " . ($row['reference_id'] ?? 'unknown'));
            $success = false;
        }
    }
    
    return $success;
}
?>
