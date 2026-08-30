<?php
/**
 * Debug script for Canada Medical file display
 */

require_once __DIR__ . '/db.php';

// Get a sample application
$sql = "SELECT * FROM canada_medical_exams_requests LIMIT 1";
$result = $conn->query($sql);
$application = $result->fetch_assoc();

if (!$application) {
    echo "No applications found in database";
    exit;
}

echo "<h2>Canada Medical Files Debug</h2>";
echo "<h3>Application ID: " . $application['id'] . "</h3>";
echo "<h3>Reference: " . $application['reference_id'] . "</h3>";

// Test each file
$files = [
    'passport_file' => 'Passport',
    'cv_file' => 'Resume/CV',
    'payment_proof_file' => 'Payment Proof',
    'medical_report_form_file' => 'Medical Report Form'
];

foreach ($files as $field => $label) {
    $file_path = $application[$field] ?? '';
    echo "<div style='margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;'>";
    echo "<h4>$label</h4>";
    echo "<p><strong>Database Path:</strong> " . htmlspecialchars($file_path) . "</p>";
    
    if (empty($file_path)) {
        echo "<p style='color: red;'>No file uploaded</p>";
        continue;
    }
    
    // Check different possible paths
    $canada_medical_path = __DIR__ . '/uploads/canada_medical/' . basename($file_path);
    $uploads_path = __DIR__ . '/uploads/' . basename($file_path);
    
    echo "<p><strong>Canada Medical Path:</strong> " . htmlspecialchars($canada_medical_path) . "</p>";
    echo "<p><strong>Uploads Path:</strong> " . htmlspecialchars($uploads_path) . "</p>";
    
    $canada_medical_exists = file_exists($canada_medical_path);
    $uploads_exists = file_exists($uploads_path);
    
    echo "<p><strong>Canada Medical Exists:</strong> " . ($canada_medical_exists ? "YES" : "NO") . "</p>";
    echo "<p><strong>Uploads Exists:</strong> " . ($uploads_exists ? "YES" : "NO") . "</p>";
    
    // Determine which URL to use
    $file_url = $canada_medical_exists ? 
        'uploads/canada_medical/' . basename($file_path) : 
        'uploads/' . basename($file_path);
    
    echo "<p><strong>File URL:</strong> " . htmlspecialchars($file_url) . "</p>";
    
    // Test the displayFile function
    function displayFile($file_path, $label) {
        if (empty($file_path)) {
            return '<span class="text-muted">Not uploaded</span>';
        }
        
        // Check if file exists in uploads/canada_medical/ directory
        $canada_medical_path = __DIR__ . '/uploads/canada_medical/' . basename($file_path);
        $uploads_path = __DIR__ . '/uploads/' . basename($file_path);
        
        // Use the correct path
        $file_url = file_exists($canada_medical_path) ? 
            'uploads/canada_medical/' . basename($file_path) : 
            'uploads/' . basename($file_path);
        
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        $icon_class = match($extension) {
            'pdf' => 'fas fa-file-pdf text-danger',
            'doc', 'docx' => 'fas fa-file-word text-primary',
            'jpg', 'jpeg', 'png' => 'fas fa-file-image text-success',
            default => 'fas fa-file text-secondary'
        };
        
        // Check if file exists and provide both view and download options
        $file_exists = file_exists($canada_medical_path) || file_exists($uploads_path);
        
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
        
        return $html;
    }
    
    echo "<h5>Display Function Output:</h5>";
    echo displayFile($file_path, $label);
    
    echo "</div>";
}

echo "<style>
.d-flex { display: flex; }
.align-items-center { align-items: center; }
.justify-content-between { justify-content: space-between; }
.p-2 { padding: 8px; }
.border { border: 1px solid #dee2e6; }
.rounded { border-radius: 4px; }
.me-2 { margin-right: 8px; }
.btn-group { display: inline-flex; }
.btn { padding: 6px 12px; font-size: 14px; border: 1px solid transparent; border-radius: 4px; text-decoration: none; }
.btn-sm { padding: 4px 8px; font-size: 12px; }
.btn-outline-primary { color: #007bff; border-color: #007bff; background: transparent; }
.btn-outline-primary:hover { background: #007bff; color: white; }
.btn-primary { color: white; background: #007bff; border-color: #007bff; }
.btn-primary:hover { background: #0056b3; }
.text-muted { color: #6c757d; }
</style>";

echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>";
?>
