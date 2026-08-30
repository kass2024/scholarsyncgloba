<?php
/**
 * canada-medical-exams-request.php
 * Canada Medical Exams Request Form for ScholarSync Global
 */

// ============================================
// SECURITY & SESSION INITIALIZATION
// ============================================

// Start session with simple configuration for localhost
session_start();

// Generate unique user ID if not exists
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'MED_' . uniqid() . '_' . time();
    // Also set compatibility variable for upload script
    $_SESSION['MED_USER_ID'] = $_SESSION['user_id'];
} else {
    // Ensure compatibility variable is set
    $_SESSION['MED_USER_ID'] = $_SESSION['user_id'];
}

$user_id = $_SESSION['user_id'];
$_SESSION['session_start'] = time();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include database connection
require_once __DIR__ . '/db.php';

// Check if already applied
$already_applied = false;
$check_sql = "SELECT id FROM canada_medical_exams_requests WHERE user_id = ?";
$check_stmt = $conn->prepare($check_sql);
if ($check_stmt) {
    $check_stmt->bind_param("s", $user_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    if ($check_stmt->num_rows > 0) {
        $already_applied = true;
    }
    $check_stmt->close();
}

// If already applied, show message
if ($already_applied) {
    $redirect_url = "canada-medical-already-applied.php?id=" . urlencode($user_id);
    header("Location: $redirect_url");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canada Medical Exams Request | ScholarSync Global</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-700: #374151;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #f8fafc;
            color: var(--gray-700);
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .application-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 2rem;
            padding: 2rem 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .header-section h1 {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .header-section .subtitle {
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            opacity: 0.9;
            padding: 0 1rem;
        }
        
        .form-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-200);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-100);
        }
        
        .section-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
            flex-shrink: 0;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-700);
            margin: 0;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-label.required::after {
            content: " *";
            color: var(--danger-color);
        }
        
        .form-control, .form-select {
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 1rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        
        .form-control.is-invalid, .form-select.is-invalid {
            border-color: var(--danger-color);
        }
        
        .invalid-feedback {
            font-size: 0.875rem;
            color: var(--danger-color);
            margin-top: 0.25rem;
        }
        
        .file-upload-area {
            border: 2px dashed var(--gray-300);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            background: var(--gray-100);
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .file-upload-area:hover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }
        
        .file-upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.1);
        }
        
        .file-upload-icon {
            font-size: 1.75rem;
            color: var(--gray-400);
            margin-bottom: 0.75rem;
        }
        
        .file-preview-container {
            margin-top: 0.5rem;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .file-preview {
            background: var(--gray-100);
            border-radius: 6px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid var(--gray-200);
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
            min-width: 0;
        }
        
        .file-icon {
            font-size: 1.25rem;
            color: var(--gray-500);
            flex-shrink: 0;
        }
        
        .file-name {
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
            min-width: 0;
        }
        
        .file-size {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-top: 0.125rem;
        }
        
        .file-remove {
            background: none;
            border: none;
            color: var(--danger-color);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: background-color 0.2s;
            flex-shrink: 0;
        }
        
        .file-remove:hover {
            background: rgba(239, 68, 68, 0.1);
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.875rem 2rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            color: white;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 1rem 1.25rem;
        }
        
        .session-timer {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            z-index: 1000;
            display: flex;
            align-items: center;
        }
        
        .progress-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .progress-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        
        .validation-summary {
            display: none;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        /* Success Message Styles */
        .success-container {
            display: none;
            max-width: 800px;
            margin: 2rem auto;
            text-align: center;
        }
        
        .success-card {
            background: white;
            border-radius: 15px;
            padding: 3rem 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
            font-size: 2.5rem;
        }
        
        .success-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-700);
            margin-bottom: 1rem;
        }
        
        .success-message {
            font-size: 1.125rem;
            color: var(--gray-500);
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .reference-card {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px dashed var(--primary-color);
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem auto;
            max-width: 500px;
        }
        
        .reference-id {
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            background: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            display: inline-block;
            margin: 0.5rem 0;
            word-break: break-all;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .application-container {
                padding: 0.5rem;
            }
            
            .header-section {
                padding: 1.5rem 1rem;
                margin-bottom: 1.5rem;
            }
            
            .form-section {
                padding: 1.25rem;
                margin-bottom: 1rem;
            }
            
            .file-upload-area {
                padding: 1.25rem;
                min-height: 130px;
            }
            
            .section-icon {
                width: 36px;
                height: 36px;
                margin-right: 0.75rem;
            }
            
            .section-title {
                font-size: 1.125rem;
            }
            
            .success-card {
                padding: 2rem 1rem;
            }
            
            .success-title {
                font-size: 1.5rem;
            }
            
            .reference-id {
                font-size: 1.25rem;
                padding: 0.5rem 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .btn-primary {
                width: 100%;
                padding: 0.875rem 1.5rem;
            }
            
            .session-timer {
                bottom: 10px;
                right: 10px;
                font-size: 0.75rem;
                padding: 0.375rem 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Session Timer -->
    <div class="session-timer" id="sessionTimer">
        <i class="fas fa-clock me-2"></i>Session: <span id="timer">59:59</span>
    </div>
    
    <!-- Progress Overlay -->
    <div class="progress-overlay" id="progressOverlay">
        <div class="progress-card">
            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h4>Submitting Request</h4>
            <p id="progressText">Please wait while we process your request...</p>
            <div class="progress mt-3">
                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
            </div>
        </div>
    </div>
    
    <!-- Success Message Container (Initially Hidden) -->
    <div class="success-container" id="successContainer">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="success-title">Request Submitted Successfully</h1>
            <p class="success-message">
                Your Canada Medical Exams request has been received and is under review. We'll contact you 
                within 3-5 business days with an update. Please keep your reference number 
                for future communications.
            </p>
            
            <div class="reference-card">
                <h5 class="mb-2">Your Reference ID:</h5>
                <div class="reference-id" id="referenceId">Loading...</div>
                <p class="small text-muted mt-2">
                    <i class="fas fa-info-circle me-1"></i>
                    Save this number for tracking your request
                </p>
            </div>
            
            <div class="alert alert-info mb-4">
                <div class="d-flex align-items-start">
                    <i class="fas fa-envelope me-3 mt-1"></i>
                    <div>
                        <strong>Next Steps:</strong>
                        <ul class="mb-0 mt-2">
                            <li>A confirmation email has been sent to your registered email address</li>
                            <li>Our team will review your request within 3-5 business days</li>
                            <li>Check your spam folder if you don't see our email</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <button type="button" class="btn btn-primary" id="submitAnotherBtn">
                    <i class="fas fa-plus-circle me-2"></i> Submit Another Request
                </button>
                <button type="button" class="btn btn-outline-primary" id="printBtn">
                    <i class="fas fa-print me-2"></i> Print Confirmation
                </button>
            </div>
        </div>
    </div>
    
    <!-- Database Warning -->
    <?php if ($conn->connect_error): ?>
    <div class="container mt-3">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Note:</strong> System check unavailable. You can still submit your request.
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Main Application Container (Visible Initially) -->
    <div class="application-container" id="applicationContainer">
        <!-- Header -->
        <div class="header-section">
            <h1>Canada Medical Exams Request</h1>
            <p class="subtitle">Apply for Canada medical examinations with guidance from <strong>ScholarSync Global</strong>.</p>
            <div class="mt-3">
                <span class="badge bg-light text-dark me-2">
                    <i class="fas fa-shield-alt me-1"></i> Secure
                </span>
                <span class="badge bg-light text-dark">
                    <i class="fas fa-lock me-1"></i> Encrypted
                </span>
            </div>
        </div>
        
        <!-- Validation Summary -->
        <div class="validation-summary" id="validationSummary">
            <div class="d-flex align-items-start">
                <i class="fas fa-exclamation-circle text-danger me-2 mt-1"></i>
                <div>
                    <h6 class="mb-2">Please correct the following errors:</h6>
                    <ul id="validationList" class="mb-0 ps-3"></ul>
                </div>
            </div>
        </div>
        
        <!-- Main Form -->
        <form id="medicalForm" enctype="multipart/form-data" novalidate>
            <!-- CSRF Protection -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
            
            <!-- Personal Information Section -->
            <section class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <h2 class="section-title">Personal Information</h2>
                </div>
                
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="first_name" class="form-label required">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" 
                               placeholder="Enter your first name" required maxlength="100">
                        <div class="invalid-feedback">Please enter your first name</div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <label for="last_name" class="form-label required">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" 
                               placeholder="Enter your last name" required maxlength="100">
                        <div class="invalid-feedback">Please enter your last name</div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <label for="email" class="form-label required">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="example@email.com" required maxlength="150">
                        <div class="invalid-feedback">Please enter a valid email address</div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <label for="phone" class="form-label required">Phone Number</label>
                        <div class="form-text mb-2" style="font-size: 0.85rem;">Use the country flag dropdown, then your number. We store <strong>digits only</strong> (country code + number, no +) so WhatsApp can message you.</div>
                        <input type="tel" class="form-control" id="phone" required>
                        <input type="hidden" name="phone_area_code" id="phone_area_code">
                        <input type="hidden" name="phone_number" id="phone_number">
                        <div class="invalid-feedback">Please enter a valid phone number</div>
                        <div class="form-text">Include country code</div>
                    </div>
                    
                    <div class="col-12">
                        <label for="address" class="form-label required">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3" 
                                  placeholder="Enter your complete address" required maxlength="500"></textarea>
                        <div class="invalid-feedback">Please enter your address</div>
                    </div>
                </div>
            </section>
            
            <!-- Emergency Contact Section -->
            <section class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <h2 class="section-title">Emergency Contact</h2>
                </div>
                
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="emergency_full_name" class="form-label required">Full Name</label>
                        <input type="text" class="form-control" id="emergency_full_name" name="emergency_full_name" 
                               placeholder="Enter full name" required maxlength="150">
                        <div class="invalid-feedback">Please enter emergency contact name</div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <label for="emergency_relationship" class="form-label required">Relationship</label>
                        <input type="text" class="form-control" id="emergency_relationship" name="emergency_relationship" 
                               placeholder="e.g., Father, Sister, Spouse" required maxlength="100">
                        <div class="invalid-feedback">Please enter relationship</div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <label for="emergency_phone" class="form-label required">Phone Number</label>
                        <div class="form-text mb-2" style="font-size: 0.85rem;">Same as above: country code + number, saved as digits only (no +) for WhatsApp.</div>
                        <input type="tel" class="form-control" id="emergency_phone" required>
                        <input type="hidden" name="emergency_area_code" id="emergency_area_code">
                        <input type="hidden" name="emergency_phone_number" id="emergency_phone_number">
                        <div class="invalid-feedback">Please enter a valid emergency phone number</div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <label for="emergency_email" class="form-label required">Email Address</label>
                        <input type="email" class="form-control" id="emergency_email" name="emergency_email" 
                               placeholder="emergency@email.com" required maxlength="150">
                        <div class="invalid-feedback">Please enter a valid emergency email</div>
                    </div>
                </div>
            </section>
            
            <!-- Documents Upload Section -->
            <section class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-file-upload"></i>
                    </div>
                    <h2 class="section-title">Required Documents</h2>
                </div>
                
                <div class="row g-3">
                    <!-- Passport -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="file-upload-area" onclick="document.getElementById('passport').click()">
                            <div class="file-upload-icon">
                                <i class="fas fa-passport"></i>
                            </div>
                            <h6>Passport <span class="text-danger">*</span></h6>
                            <p class="small mb-2">Clear scan of passport page</p>
                            <div class="file-upload-stats small">
                                <span class="me-3"><i class="fas fa-file"></i> PDF, JPG, PNG</span>
                                <span><i class="fas fa-weight-hanging"></i> 15MB max</span>
                            </div>
                            <input type="file" class="d-none" id="passport" name="passport" 
                                   accept=".pdf,.jpg,.jpeg,.png" required data-max-size="15728640">
                        </div>
                        <div class="file-preview-container" id="passport-preview"></div>
                        <div class="invalid-feedback">Passport is required</div>
                    </div>
                    
                    <!-- CV -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="file-upload-area" onclick="document.getElementById('cv').click()">
                            <div class="file-upload-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h6>Resume / CV <span class="text-danger">*</span></h6>
                            <p class="small mb-2">Your professional resume</p>
                            <div class="file-upload-stats small">
                                <span class="me-3"><i class="fas fa-file"></i> PDF, DOC, DOCX</span>
                                <span><i class="fas fa-weight-hanging"></i> 15MB max</span>
                            </div>
                            <input type="file" class="d-none" id="cv" name="cv" 
                                   accept=".pdf,.doc,.docx" required data-max-size="15728640">
                        </div>
                        <div class="file-preview-container" id="cv-preview"></div>
                        <div class="invalid-feedback">CV is required</div>
                    </div>
                    
                    <!-- Service Payment Proof -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="file-upload-area" onclick="document.getElementById('payment_proof').click()">
                            <div class="file-upload-icon">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <h6>Service Payment Proof <span class="text-danger">*</span></h6>
                            <p class="small mb-2">Payment receipt or transaction proof</p>
                            <div class="file-upload-stats small">
                                <span class="me-3"><i class="fas fa-file"></i> PDF, JPG, PNG</span>
                                <span><i class="fas fa-weight-hanging"></i> 15MB max</span>
                            </div>
                            <input type="file" class="d-none" id="payment_proof" name="payment_proof" 
                                   accept=".pdf,.jpg,.jpeg,.png" required data-max-size="15728640">
                        </div>
                        <div class="file-preview-container" id="payment_proof-preview"></div>
                        <div class="invalid-feedback">Payment proof is required</div>
                    </div>
                    
                    <!-- Medical Report Request Form -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="file-upload-area" onclick="document.getElementById('medical_report_form').click()">
                            <div class="file-upload-icon">
                                <i class="fas fa-file-medical"></i>
                            </div>
                            <h6>Medical Report Request Form <span class="text-danger">*</span></h6>
                            <p class="small mb-2">Completed medical examination form</p>
                            <div class="file-upload-stats small">
                                <span class="me-3"><i class="fas fa-file"></i> PDF, JPG, PNG</span>
                                <span><i class="fas fa-weight-hanging"></i> 15MB max</span>
                            </div>
                            <input type="file" class="d-none" id="medical_report_form" name="medical_report_form" 
                                   accept=".pdf,.jpg,.jpeg,.png" required data-max-size="15728640">
                        </div>
                        <div class="file-preview-container" id="medical_report_form-preview"></div>
                        <div class="invalid-feedback">Medical report form is required</div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <div class="alert alert-info">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-info-circle me-3 mt-1"></i>
                            <div>
                                <strong>Document Requirements:</strong>
                                <ul class="mb-0 mt-1">
                                    <li>Maximum file size: <strong>15MB per file</strong></li>
                                    <li>All documents are <strong>required</strong></li>
                                    <li>All files must be single files (no zipped/combined files)</li>
                                    <li>Accepted formats: PDF, JPG, JPEG, PNG, DOC, DOCX</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Submit Button -->
            <div class="d-flex justify-content-between align-items-center mt-4 py-3">
                <div class="form-text">
                    <i class="fas fa-shield-alt me-1"></i> Your data is protected with SSL encryption
                </div>
                <button type="submit" class="btn btn-primary btn-lg px-4" id="submitBtn">
                    <i class="fas fa-paper-plane me-2"></i> Submit Request
                </button>
            </div>
        </form>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
    // Application configuration
    const CONFIG = {
        userId: '<?php echo $user_id; ?>',
        csrfToken: '<?php echo $_SESSION['csrf_token']; ?>',
        endpoints: {
            save: 'save_canada_medical_request.php',
            upload: 'basic_upload.php'
        },
        maxFileSize: 15728640, // 15MB in bytes
        sessionTimeout: 7200, // 2 hours in seconds
        allowedExtensions: ['.pdf', '.jpg', '.jpeg', '.png', '.doc', '.docx'],
        fileIcons: {
            'pdf': 'fas fa-file-pdf text-danger',
            'jpg': 'fas fa-file-image text-success',
            'jpeg': 'fas fa-file-image text-success',
            'png': 'fas fa-file-image text-success',
            'doc': 'fas fa-file-word text-primary',
            'docx': 'fas fa-file-word text-primary'
        }
    };
    
    // File management
    const uploadedFiles = {};
    
    // Initialize phone inputs
    const phoneInput = intlTelInput(document.querySelector("#phone"), {
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
        separateDialCode: true,
        preferredCountries: ["ca", "us", "gb"],
        hiddenInput: "phone_number"
    });
    
    const emergencyPhoneInput = intlTelInput(document.querySelector("#emergency_phone"), {
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
        separateDialCode: true,
        preferredCountries: ["ca", "us", "gb"],
        hiddenInput: "emergency_phone_number"
    });
    
    // Session timer
    let sessionTimer = CONFIG.sessionTimeout;
    const timerElement = document.getElementById('timer');
    
    function updateTimer() {
        const minutes = Math.floor(sessionTimer / 60);
        const seconds = sessionTimer % 60;
        timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        if (sessionTimer <= 0) {
            clearInterval(timerInterval);
            alert('Your session has expired. Please start a new request.');
            window.location.reload();
        }
        sessionTimer--;
    }
    
    const timerInterval = setInterval(updateTimer, 1000);
    
    // File upload handling
    function setupFileUpload(inputId, previewId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > CONFIG.maxFileSize) {
                    alert('File size exceeds 15MB limit');
                    input.value = '';
                    return;
                }
                
                const extension = '.' + file.name.split('.').pop().toLowerCase();
                if (!CONFIG.allowedExtensions.includes(extension)) {
                    alert('Invalid file type. Allowed types: ' + CONFIG.allowedExtensions.join(', '));
                    input.value = '';
                    return;
                }
                
                // Upload file
                uploadFile(file, inputId, preview);
            }
        });
        
        // Drag and drop
        const uploadArea = input.closest('.file-upload-area');
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                input.files = files;
                const event = new Event('change', { bubbles: true });
                input.dispatchEvent(event);
            }
        });
    }
    
    function uploadFile(file, inputId, previewContainer) {
        console.log('Starting upload for:', inputId, file.name, file.size);
        
        const formData = new FormData();
        formData.append('file', file);
        formData.append('field', inputId);
        formData.append('csrf_token', CONFIG.csrfToken);
        
        // Show loading state
        previewContainer.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm me-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span>Uploading...</span>
            </div>
        `;
        
        fetch(CONFIG.endpoints.upload, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Upload response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Upload response data:', data);
            if (data.success) {
                uploadedFiles[inputId] = data.file_path;
                displayFilePreview(file, data.file_path, previewContainer, inputId);
            } else {
                throw new Error(data.message || 'Upload failed');
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            previewContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Upload failed: ${error.message}
                </div>
            `;
        });
    }
    
    function displayFilePreview(file, filePath, container, inputId) {
        const extension = '.' + file.name.split('.').pop().toLowerCase();
        const iconClass = CONFIG.fileIcons[extension] || 'fas fa-file';
        const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
        
        container.innerHTML = `
            <div class="file-preview">
                <div class="file-info">
                    <div class="file-icon">
                        <i class="${iconClass}"></i>
                    </div>
                    <div>
                        <div class="file-name">${file.name}</div>
                        <div class="file-size">${fileSize}</div>
                    </div>
                </div>
                <button type="button" class="file-remove" onclick="removeFile('${inputId}')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
    }
    
    function removeFile(inputId) {
        delete uploadedFiles[inputId];
        document.getElementById(inputId).value = '';
        document.getElementById(inputId + '-preview').innerHTML = '';
    }
    
        
    // Form validation and submission
    document.getElementById('medicalForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (validateForm()) {
            submitForm();
        }
    });
    
    function validateForm() {
        const form = document.getElementById('medicalForm');
        const errors = [];
        
        // Reset validation state
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        document.getElementById('validationSummary').style.display = 'none';
        document.getElementById('validationList').innerHTML = '';
        
        // Check required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                const label = field.closest('.col-md-6, .col-12').querySelector('.form-label');
                errors.push(`${label.textContent.replace(' *', '')} is required`);
            }
        });
        
        // Check phone numbers
        if (!phoneInput.isValidNumber()) {
            document.getElementById('phone').classList.add('is-invalid');
            errors.push('Please enter a valid phone number');
        }
        
        if (!emergencyPhoneInput.isValidNumber()) {
            document.getElementById('emergency_phone').classList.add('is-invalid');
            errors.push('Please enter a valid emergency phone number');
        }
        
        // Check email format
        const email = document.getElementById('email');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email.value)) {
            email.classList.add('is-invalid');
            errors.push('Please enter a valid email address');
        }
        
        const emergencyEmail = document.getElementById('emergency_email');
        if (!emailRegex.test(emergencyEmail.value)) {
            emergencyEmail.classList.add('is-invalid');
            errors.push('Please enter a valid emergency email address');
        }
        
        // Check file uploads
        const requiredFiles = ['passport', 'cv', 'payment_proof', 'medical_report_form'];
        requiredFiles.forEach(fileId => {
            if (!uploadedFiles[fileId]) {
                errors.push(`${fileId.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())} is required`);
            }
        });
        
        if (errors.length > 0) {
            showValidationErrors(errors);
            return false;
        }
        
        return true;
    }
    
    function showValidationErrors(errors) {
        const validationSummary = document.getElementById('validationSummary');
        const validationList = document.getElementById('validationList');
        
        validationList.innerHTML = errors.map(error => `<li>${error}</li>`).join('');
        validationSummary.style.display = 'block';
        
        // Scroll to top of form
        document.getElementById('applicationContainer').scrollIntoView({ behavior: 'smooth' });
    }
    
    function submitForm() {
        const form = document.getElementById('medicalForm');
        const formData = new FormData(form);
        
        // Add phone information
        formData.set('phone_area_code', phoneInput.getSelectedCountryData().dialCode);
        formData.set('phone_number', phoneInput.getNumber());
        formData.set('emergency_area_code', emergencyPhoneInput.getSelectedCountryData().dialCode);
        formData.set('emergency_phone_number', emergencyPhoneInput.getNumber());
        
        // Add uploaded file paths
        Object.keys(uploadedFiles).forEach(fileId => {
            formData.set(fileId + '_file', uploadedFiles[fileId]);
        });
        
        // Debug: Log uploaded files
        console.log('Uploaded files:', uploadedFiles);
        console.log('FormData entries:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        // Show progress overlay
        document.getElementById('progressOverlay').style.display = 'flex';
        updateProgress(10, 'Validating your information...');
        
        fetch(CONFIG.endpoints.save, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                updateProgress(100, 'Request submitted successfully!');
                setTimeout(() => {
                    showSuccess(data.reference_id);
                }, 1000);
            } else {
                throw new Error(data.message || 'Submission failed');
            }
        })
        .catch(error => {
            console.error('Submission error:', error);
            document.getElementById('progressOverlay').style.display = 'none';
            alert('Submission failed: ' + error.message);
        });
    }
    
    function updateProgress(percent, message) {
        document.getElementById('progressBar').style.width = percent + '%';
        document.getElementById('progressText').textContent = message;
    }
    
    function showSuccess(referenceId) {
        document.getElementById('progressOverlay').style.display = 'none';
        document.getElementById('applicationContainer').style.display = 'none';
        document.getElementById('successContainer').style.display = 'block';
        document.getElementById('referenceId').textContent = referenceId;
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    // Initialize file uploads
    setupFileUpload('passport', 'passport-preview');
    setupFileUpload('cv', 'cv-preview');
    setupFileUpload('payment_proof', 'payment_proof-preview');
    setupFileUpload('medical_report_form', 'medical_report_form-preview');
    
    // Button handlers
    document.getElementById('submitAnotherBtn').addEventListener('click', function() {
        window.location.reload();
    });
    
    document.getElementById('printBtn').addEventListener('click', function() {
        window.print();
    });
    </script>
</body>
</html>
