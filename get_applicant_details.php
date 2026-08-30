<?php
// get_applicant_details.php
require_once __DIR__ . '/db.php';
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type
header('Content-Type: text/html; charset=UTF-8');

// Check if ID is provided
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No applicant ID provided.
          </div>';
    exit;
}

$id = intval($_POST['id']);

if ($id <= 0) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Invalid applicant ID.
          </div>';
    exit;
}

try {
    // Get applicant details
    $query = "SELECT * FROM upafa_registrations WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Error fetching applicant details: " . $conn->error);
    }
    
    $applicant = $result->fetch_assoc();
    $stmt->close();
    
    if (!$applicant) {
        echo '<div class="alert alert-warning">
                <i class="fas fa-user-times me-2"></i>
                Applicant not found in the database.
              </div>';
        exit;
    }
    
    // Get attachments
    $query = "SELECT * FROM upafa_registration_files WHERE registration_id = ? ORDER BY file_type";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $attachments = $stmt->get_result();
    
    // Helper function to safely display data
    function display($data, $default = 'N/A') {
        return !empty($data) ? htmlspecialchars($data) : $default;
    }
    
    // Helper function to format date
    function formatDateDisplay($dateString, $format = 'F j, Y') {
        if (empty($dateString) || $dateString == '0000-00-00') {
            return 'N/A';
        }
        return date($format, strtotime($dateString));
    }
    
    // Helper function to format currency
    function formatCurrency($amount) {
        return '$' . number_format(floatval($amount), 2);
    }
    
    // Generate HTML
    $html = '
    <div class="applicant-details">
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-user-circle me-2"></i>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-1">Full Name</label>
                                    <div class="fw-bold fs-5">' . display($applicant['last_name'] . ', ' . $applicant['first_name']) . '</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-1">Nationality</label>
                                    <div class="fw-bold">' . display($applicant['nationality']) . '</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-1">Place & Date of Birth</label>
                                    <div class="fw-bold">' . display($applicant['birth_place']) . ' - ' . formatDateDisplay($applicant['birth_date']) . '</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-1">Contact Information</label>
                                    <div>
                                        <i class="fas fa-envelope text-primary me-2"></i>
                                        <span class="fw-bold">' . display($applicant['email']) . '</span>
                                    </div>
                                    <div class="mt-2">
                                        <i class="fas fa-phone text-primary me-2"></i>
                                        <span class="fw-bold">' . display($applicant['telephone']) . '</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-1">Academic Year</label>
                                    <div class="fw-bold">' . display($applicant['academic_year']) . '</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-1">Application ID</label>
                                    <div class="fw-bold text-primary">#' . $applicant['id'] . '</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-graduation-cap me-2"></i>Educational Background</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-1">Highest Education Level</label>
                                    <div>
                                        <span class="badge bg-light text-dark fs-6">' . display($applicant['highest_education']) . '</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-1">Years Attended</label>
                                    <div class="fw-bold">' . display($applicant['year_from']) . ' - ' . display($applicant['year_to']) . '</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-1">Previous Institution</label>
                                    <div class="fw-bold">' . display($applicant['school_name_address']) . '</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-1">Department</label>
                                    <div class="fw-bold">' . display($applicant['department']) . '</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-file-alt me-2"></i>Application Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">Intended Degree</label>
                            <div class="fw-bold">' . display($applicant['intended_degree']) . '</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">Field of Study</label>
                            <div class="fw-bold">' . display($applicant['field_of_study']) . '</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">Fees</label>
                            <div class="d-flex justify-content-between">
                                <span>Registration:</span>
                                <span class="fw-bold">' . formatCurrency($applicant['registration_fees']) . '</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Tuition:</span>
                                <span class="fw-bold">' . formatCurrency($applicant['tuition_fees']) . '</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">Financial Information</label>
                            <div>Scholarship: <span class="fw-bold">' . display($applicant['scholarship']) . '</span></div>
                            ' . (!empty($applicant['scholarship_institution']) ? 
                                '<div class="small mt-1">Institution: ' . display($applicant['scholarship_institution']) . '</div>' : '') . '
                            <div class="mt-2">Referred: <span class="fw-bold">' . display($applicant['referred_by_parrot']) . '</span></div>
                            ' . (!empty($applicant['ref_institution']) ? 
                                '<div class="small mt-1">Referral: ' . display($applicant['ref_institution']) . '</div>' : '') . '
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">Application Timeline</label>
                            <div>Submitted: <span class="fw-bold">' . formatDateDisplay($applicant['created_at'], 'F j, Y H:i') . '</span></div>
                            <div>Last Updated: <span class="fw-bold">' . formatDateDisplay($applicant['updated_at'], 'F j, Y H:i') . '</span></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">Status</label>
                            <div>
                                <span class="status-badge ' . $applicant['application_status'] . ' fs-6">
                                    <i class="fas ' . getStatusIcon($applicant['application_status']) . ' me-1"></i>
                                    ' . ucfirst(str_replace('_', ' ', $applicant['application_status'])) . '
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0"><i class="fas fa-certificate me-2"></i>Commitment Declaration</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">Declaration</label>
                            <div class="small">
                                <p class="mb-1">I, <strong>' . display($applicant['commitment_name']) . '</strong>, hereby declare that all information provided is true and accurate.</p>
                                <p class="mb-1">Signed at: <strong>' . display($applicant['done_at']) . '</strong></p>
                                <p class="mb-0">Date: <strong>' . formatDateDisplay($applicant['done_date']) . '</strong></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-paperclip me-2"></i>Attachments
                    <span class="badge bg-light text-dark ms-2">' . $attachments->num_rows . ' files</span>
                </h5>
            </div>
            <div class="card-body">';
    
    if ($attachments->num_rows > 0) {
        // Group attachments by type
        $groupedAttachments = [];
        while ($file = $attachments->fetch_assoc()) {
            $fileType = $file['file_type'];
            if (!isset($groupedAttachments[$fileType])) {
                $groupedAttachments[$fileType] = [];
            }
            $groupedAttachments[$fileType][] = $file;
        }
        
        $html .= '<div class="row">';
        
        foreach ($groupedAttachments as $fileType => $files) {
            $fileTypeFormatted = ucwords(str_replace('_', ' ', $fileType));
            $fileCount = count($files);
            $icon = getFileIcon($files[0]['mime_type']);
            
            $html .= '
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h6 class="card-title mb-0">
                            <i class="' . $icon . ' me-2 text-primary"></i>
                            ' . $fileTypeFormatted . '
                            <span class="badge bg-primary rounded-pill ms-2">' . $fileCount . '</span>
                        </h6>
                    </div>
                    <div class="card-body">';
            
            foreach ($files as $index => $file) {
                $size = formatFileSize($file['size_bytes']);
                $uploadDate = formatDateDisplay($file['uploaded_at'], 'M j, Y H:i');
                $fileNumber = $fileCount > 1 ? ' (' . ($index + 1) . ')' : '';
                
                $html .= '
                        <div class="attachment-item mb-3">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="' . $icon . ' fa-2x text-primary"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">' . display($file['original_name']) . '</div>
                                    <div class="small text-muted">
                                        <span class="me-3"><i class="fas fa-hdd me-1"></i>' . $size . '</span>
                                        <span><i class="fas fa-calendar me-1"></i>' . $uploadDate . '</span>
                                    </div>
                                    <div class="small mt-1">
                                        <span class="badge bg-light text-dark">' . display($file['mime_type']) . '</span>
                                    </div>
                                </div>
                                <div class="ms-3">
                                    <a href="download_file.php?id=' . $file['id'] . '" 
                                       class="btn btn-sm btn-outline-primary" 
                                       target="_blank" 
                                       title="Download ' . display($file['original_name']) . '"
                                       download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>';
            }
            
            $html .= '
                    </div>
                </div>
            </div>';
        }
        
        $html .= '</div>';
        
        // Add total size calculation
        $stmt->data_seek(0); // Reset result pointer
        $totalSize = 0;
        while ($file = $attachments->fetch_assoc()) {
            $totalSize += $file['size_bytes'];
        }
        
        $html .= '
                <div class="mt-4 pt-3 border-top">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            <span class="text-muted">Total storage used:</span>
                            <span class="fw-bold ms-2">' . formatFileSize($totalSize) . '</span>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-secondary" onclick="downloadAllFiles(' . $id . ')" title="Download all files">
                                <i class="fas fa-download me-1"></i> Download All
                            </button>
                        </div>
                    </div>
                </div>';
        
    } else {
        $html .= '
                <div class="text-center py-5">
                    <i class="fas fa-folder-open fa-4x text-muted mb-4"></i>
                    <h5 class="text-muted">No attachments uploaded</h5>
                    <p class="text-muted small">This applicant has not uploaded any supporting documents.</p>
                </div>';
    }
    
    $html .= '
            </div>
        </div>
    </div>
    
    <script>
    function downloadAllFiles(applicantId) {
        if (confirm("Download all files for this applicant?")) {
            // This would typically trigger a server-side script to zip all files
            // For now, we\'ll show a message
            alert("This feature would normally download a ZIP file containing all attachments.");
            // In a real implementation: window.location.href = "download_all_files.php?id=" + applicantId;
        }
    }
    
    function downloadFile(fileId) {
        window.open("download_file.php?id=" + fileId, "_blank");
    }
    </script>';
    
    echo $html;
    $stmt->close();
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '
          </div>';
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

// Helper function for status icons
function getStatusIcon($status) {
    switch ($status) {
        case 'pending': return 'fa-clock';
        case 'under_review': return 'fa-search';
        case 'accepted': return 'fa-check-circle';
        case 'rejected': return 'fa-times-circle';
        default: return 'fa-question-circle';
    }
}

// Helper function for file icons
function getFileIcon($mimeType) {
    if (strpos($mimeType, 'image/') === 0) {
        return 'fas fa-image';
    } elseif (strpos($mimeType, 'application/pdf') === 0) {
        return 'fas fa-file-pdf';
    } elseif (strpos($mimeType, 'application/msword') === 0 || 
              strpos($mimeType, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') === 0) {
        return 'fas fa-file-word';
    } elseif (strpos($mimeType, 'application/vnd.ms-excel') === 0 ||
              strpos($mimeType, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') === 0) {
        return 'fas fa-file-excel';
    } elseif (strpos($mimeType, 'text/') === 0) {
        return 'fas fa-file-alt';
    } else {
        return 'fas fa-file';
    }
}

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>