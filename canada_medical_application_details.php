<?php
/**
 * canada_medical_application_details.php
 * AJAX endpoint for application details modal
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/secure_file.php';

// Check if admin is logged in
$admin_id = $_SESSION['id'] ?? null;
if (!$admin_id || !isset($_SESSION['role'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

// Get application ID
$application_id = intval($_GET['id'] ?? 0);
if ($application_id <= 0) {
    echo '<div class="alert alert-danger">Invalid application ID</div>';
    exit;
}

// Get application details
$sql = "SELECT * FROM canada_medical_exams_requests WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $application_id);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();

if (!$application) {
    echo '<div class="alert alert-danger">Application not found</div>';
    exit;
}

// Helper function to format file display
function displayFile($file_path, $label) {
    if (empty($file_path)) {
        return '<span class="text-muted">Not uploaded</span>';
    }
    
    // Debug: Log the file path
    error_log("displayFile called with path: " . $file_path . " for label: " . $label);
    
    // Check if file exists in uploads/canada_medical/ directory
    $canada_medical_path = __DIR__ . '/uploads/canada_medical/' . basename($file_path);
    $uploads_path = __DIR__ . '/uploads/' . basename($file_path);
    
    // Debug: Log the paths we're checking
    error_log("Checking paths - Canada Medical: " . $canada_medical_path . ", Uploads: " . $uploads_path);
    
    $canada_medical_exists = file_exists($canada_medical_path);
    $uploads_exists = file_exists($uploads_path);
    
    error_log("File exists - Canada Medical: " . ($canada_medical_exists ? "YES" : "NO") . ", Uploads: " . ($uploads_exists ? "YES" : "NO"));
    
    // Use secure proxy URL (direct /uploads/ access is blocked)
    $file_url = pcvc_secure_file_url(
        $canada_medical_exists
            ? 'uploads/canada_medical/' . basename($file_path)
            : 'uploads/' . basename($file_path)
    );
    
    error_log("Using file URL: " . $file_url);
    
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    $icon_class = match($extension) {
        'pdf' => 'fas fa-file-pdf text-danger',
        'doc', 'docx' => 'fas fa-file-word text-primary',
        'jpg', 'jpeg', 'png' => 'fas fa-file-image text-success',
        default => 'fas fa-file text-secondary'
    };
    
    // Check if file exists and provide both view and download options
    $file_exists = $canada_medical_exists || $uploads_exists;
    
    // Build the HTML properly with single return
    $html = "
        <div class='d-flex align-items-center justify-content-between p-2 border rounded'>
            <div class='d-flex align-items-center'>
                <i class='$icon_class me-2'></i>
                <span>$label</span>
            </div>";
    
    if ($file_exists) {
        $html .= "
            <div class='btn-group' role='group'>
                <a href='$file_url' target='_blank' class='btn btn-sm btn-outline-primary'>
                    <i class='fas fa-eye'></i> View
                </a>
                <a href='$file_url' download class='btn btn-sm btn-primary'>
                    <i class='fas fa-download'></i> Download
                </a>
            </div>";
    } else {
        $html .= "<span class='text-muted'>File not found</span>";
    }
    
    $html .= "</div>";
    
    error_log("Generated HTML: " . $html);
    
    return $html;
}
?>

<div class="application-details">
    <!-- Header Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h5 class="mb-3">Application Information</h5>
            <table class="table table-sm">
                <tr>
                    <td class="fw-bold" style="width: 40%;">Reference ID:</td>
                    <td><code><?php echo htmlspecialchars($application['reference_id']); ?></code></td>
                </tr>
                <tr>
                    <td class="fw-bold">Status:</td>
                    <td>
                        <span class="status-badge status-<?php echo $application['status']; ?>">
                            <?php echo ucwords(str_replace('_', ' ', $application['status'])); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="fw-bold">Submitted:</td>
                    <td><?php echo date('F j, Y \a\t g:i A', strtotime($application['created_at'])); ?></td>
                </tr>
                <tr>
                    <td class="fw-bold">Last Updated:</td>
                    <td><?php echo date('F j, Y \a\t g:i A', strtotime($application['updated_at'])); ?></td>
                </tr>
            </table>
        </div>
        
        <div class="col-md-6">
            <h5 class="mb-3">Quick Actions</h5>
            <div class="d-grid gap-2">
                <button class="btn btn-success" onclick="updateStatus(<?php echo $application['id']; ?>, 'approved')">
                    <i class="fas fa-check me-2"></i> Approve Application
                </button>
                <button class="btn btn-warning" onclick="updateStatus(<?php echo $application['id']; ?>, 'under_review')">
                    <i class="fas fa-clock me-2"></i> Mark as Under Review
                </button>
                <button class="btn btn-danger" onclick="updateStatus(<?php echo $application['id']; ?>, 'rejected')">
                    <i class="fas fa-times me-2"></i> Reject Application
                </button>
                <button class="btn btn-info" onclick="sendEmailNotification(<?php echo $application['id']; ?>)">
                    <i class="fas fa-envelope me-2"></i> Send Email Update
                </button>
            </div>
        </div>
    </div>
    
    <!-- Personal Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h5 class="mb-3"><i class="fas fa-user me-2"></i>Personal Information</h5>
            <table class="table table-sm">
                <tr>
                    <td class="fw-bold" style="width: 40%;">Name:</td>
                    <td><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></td>
                </tr>
                <tr>
                    <td class="fw-bold">Email:</td>
                    <td>
                        <a href="mailto:<?php echo htmlspecialchars($application['email']); ?>">
                            <?php echo htmlspecialchars($application['email']); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <td class="fw-bold">Phone:</td>
                    <td>
                        <a href="tel:+<?php echo htmlspecialchars($application['phone_area_code'] . $application['phone_number']); ?>">
                            +<?php echo htmlspecialchars($application['phone_area_code'] . ' ' . $application['phone_number']); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <td class="fw-bold">Address:</td>
                    <td><?php echo nl2br(htmlspecialchars($application['address'])); ?></td>
                </tr>
            </table>
        </div>
        
        <div class="col-md-6">
            <h5 class="mb-3"><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h5>
            <table class="table table-sm">
                <tr>
                    <td class="fw-bold" style="width: 40%;">Name:</td>
                    <td><?php echo htmlspecialchars($application['emergency_full_name']); ?></td>
                </tr>
                <tr>
                    <td class="fw-bold">Relationship:</td>
                    <td><?php echo htmlspecialchars($application['emergency_relationship']); ?></td>
                </tr>
                <tr>
                    <td class="fw-bold">Email:</td>
                    <td>
                        <a href="mailto:<?php echo htmlspecialchars($application['emergency_email']); ?>">
                            <?php echo htmlspecialchars($application['emergency_email']); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <td class="fw-bold">Phone:</td>
                    <td>
                        <a href="tel:+<?php echo htmlspecialchars($application['emergency_area_code'] . $application['emergency_phone_number']); ?>">
                            +<?php echo htmlspecialchars($application['emergency_area_code'] . ' ' . $application['emergency_phone_number']); ?>
                        </a>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Uploaded Documents -->
    <div class="row">
        <div class="col-12">
            <h5 class="mb-3"><i class="fas fa-file-upload me-2"></i>Uploaded Documents</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <?php echo displayFile($application['passport_file'], 'Passport'); ?>
                </div>
                <div class="col-md-6 mb-3">
                    <?php echo displayFile($application['cv_file'], 'Resume/CV'); ?>
                </div>
                <div class="col-md-6 mb-3">
                    <?php echo displayFile($application['payment_proof_file'], 'Payment Proof'); ?>
                </div>
                <div class="col-md-6 mb-3">
                    <?php echo displayFile($application['medical_report_form_file'], 'Medical Report Form'); ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payment Validation Status -->
    <?php if (!empty($application['ai_validation_result'])): ?>
    <div class="row mt-4">
        <div class="col-12">
            <h5 class="mb-3"><i class="fas fa-robot me-2"></i>AI Payment Validation</h5>
            <div class="alert <?php echo $application['payment_proof_validated'] ? 'alert-success' : 'alert-warning'; ?>">
                <div class="d-flex align-items-start">
                    <i class="fas <?php echo $application['payment_proof_validated'] ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2 mt-1"></i>
                    <div>
                        <strong>Validation Result:</strong> 
                        <?php echo $application['payment_proof_validated'] ? 'Payment information detected' : 'Limited payment information detected'; ?>
                        <div class="mt-2">
                            <small><?php echo htmlspecialchars($application['ai_validation_result']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Notes Section -->
    <div class="row mt-4">
        <div class="col-12">
            <h5 class="mb-3"><i class="fas fa-sticky-note me-2"></i>Admin Notes</h5>
            <textarea class="form-control" id="adminNotes" rows="3" placeholder="Add notes about this application..."><?php echo htmlspecialchars($application['admin_notes'] ?? ''); ?></textarea>
            <button class="btn btn-sm btn-primary mt-2" onclick="saveAdminNotes(<?php echo $application['id']; ?>)">
                <i class="fas fa-save me-1"></i> Save Notes
            </button>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusUpdateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Application Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">New Status:</label>
                    <div class="alert alert-info">
                        <span id="modalStatus" class="fw-bold"></span>
                    </div>
                </div>
                
                <div id="rejectionReasonWrap" style="display: none;" class="mb-3">
                    <label for="rejectionReason" class="form-label">Rejection Reason:</label>
                    <textarea id="rejectionReason" class="form-control" rows="3" maxlength="500" placeholder="Please provide a reason for rejection..."></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Notification Options:</label>
                    <div class="medical-notify-grid">
                        <button type="button" class="medical-notify-channel active" data-ne="0" data-nw="0">
                            <i class="fas fa-save"></i> Record only
                            <span>No notification</span>
                        </button>
                        <button type="button" class="medical-notify-channel" data-ne="1" data-nw="0">
                            <i class="fas fa-envelope"></i> Email
                            <span>Send email</span>
                        </button>
                        <button type="button" class="medical-notify-channel" data-ne="0" data-nw="1">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                            <span>Send WhatsApp</span>
                        </button>
                        <button type="button" class="medical-notify-channel" data-ne="1" data-nw="1">
                            <i class="fas fa-share-alt"></i> Both
                            <span>Email + WhatsApp</span>
                        </button>
                    </div>
                </div>
                
                <input type="hidden" id="modalApplicationId" value="">
                <input type="hidden" id="modalStatusValue" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmStatusUpdate()">
                    <i class="fas fa-check me-2"></i> Update Status
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.medical-notify-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 8px;
    margin-top: 8px;
}

.medical-notify-channel {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    background: #f8f9fa;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    color: #64748b;
}

.medical-notify-channel:hover {
    border-color: #2563eb;
    background: #f1f5f9;
    color: #2563eb;
}

.medical-notify-channel.active {
    border-color: #2563eb;
    background: #2563eb;
    color: white;
}

.medical-notify-channel i {
    margin-right: 4px;
}

.medical-notify-channel span {
    font-size: 0.75rem;
    display: block;
    margin-top: 2px;
}
</style>

<style>
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-under_review {
    background: #dbeafe;
    color: #1e40af;
}

.status-approved {
    background: #d1fae5;
    color: #065f46;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}
</style>

<script>
function sendEmailNotification(applicationId) {
    if (!confirm('Send status update email to applicant?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('application_id', applicationId);
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
    
    fetch('send_medical_application_email.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Email sent successfully!');
        } else {
            alert('Failed to send email: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error sending email: ' + error.message);
    });
}

function saveAdminNotes(applicationId) {
    const notes = document.getElementById('adminNotes').value;
    
    const formData = new FormData();
    formData.append('application_id', applicationId);
    formData.append('notes', notes);
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
    
    fetch('save_medical_application_notes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Notes saved successfully!');
        } else {
            alert('Failed to save notes: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error saving notes: ' + error.message);
    });
}

function updateStatus(applicationId, newStatus) {
    // Show confirmation modal
    const modal = document.getElementById('statusUpdateModal');
    const statusText = newStatus.replace('_', ' ');
    
    document.getElementById('modalStatus').textContent = statusText;
    document.getElementById('modalApplicationId').value = applicationId;
    document.getElementById('modalStatusValue').value = newStatus;
    
    // Show rejection reason field if status is rejected
    const reasonField = document.getElementById('rejectionReasonWrap');
    if (newStatus === 'rejected') {
        reasonField.style.display = 'block';
    } else {
        reasonField.style.display = 'none';
        document.getElementById('rejectionReason').value = '';
    }
    
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
}

function confirmStatusUpdate() {
    const applicationId = document.getElementById('modalApplicationId').value;
    const newStatus = document.getElementById('modalStatusValue').value;
    const rejectionReason = document.getElementById('rejectionReason').value;
    const notifyEmail = parseInt(document.querySelector('input[name="notify_email"]:checked').value) || 0;
    const notifyWhatsapp = parseInt(document.querySelector('input[name="notify_whatsapp"]:checked').value) || 0;
    
    const formData = new FormData();
    formData.append('application_id', applicationId);
    formData.append('status', newStatus);
    formData.append('rejection_reason', rejectionReason);
    formData.append('notify_email', notifyEmail);
    formData.append('notify_whatsapp', notifyWhatsapp);
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
    
    // Close modal
    const modal = document.getElementById('statusUpdateModal');
    bootstrap.Modal.getInstance(modal).hide();
    
    // Show loading
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'text-center p-3';
    loadingDiv.innerHTML = '<div class="spinner-border text-primary" role="status"></div><p class="mt-2">Updating status...</p>';
    document.body.appendChild(loadingDiv);
    
    fetch('update_canada_medical_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.body.removeChild(loadingDiv);
        
        if (data.success) {
            const message = data.notifications_sent ? 
                'Status updated successfully! Notifications sent.' : 
                'Status updated successfully!';
            alert(message);
            location.reload();
        } else {
            alert('Failed to update status: ' + data.message);
        }
    })
    .catch(error => {
        document.body.removeChild(loadingDiv);
        alert('Error updating status: ' + error.message);
    });
}
</script>
