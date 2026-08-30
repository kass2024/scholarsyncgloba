<?php
if (!function_exists('pcvc_secure_file_url')) {
    require_once __DIR__ . '/helpers/secure_file.php';
}
foreach($applicants as $applicant): 
    // Parse documents
    $documents = [];
    if(!empty($applicant['documents'])) {
        $docItems = explode('|', $applicant['documents']);
        foreach($docItems as $item) {
            if(!empty($item)) {
                list($type, $path, $time, $docId) = explode(':', $item, 4);
                $documents[] = [
                    'type' => $type,
                    'path' => $path,
                    'time' => $time,
                    'docId' => $docId
                ];
            }
        }
    }
    
    // Format date
    $appliedDate = date('M d, Y h:i A', strtotime($applicant['created_at']));
    
    // Get full address
    $address = $applicant['province_state'] . ', ' . $applicant['district'];
    $detailedArea = $applicant['sector'] . ' / ' . $applicant['cell_ward'] . ' / ' . $applicant['village'];
    
    // Emergency contact info
    $emergencyContact = $applicant['emergency_full_name'] . ' (' . $applicant['emergency_relationship'] . ')';
    $emergencyPhone = $applicant['emergency_area_code'] . ' ' . $applicant['emergency_phone_number'];
    $emergencyEmail = $applicant['emergency_email'];
    
    // Full name for search highlighting
    $fullName = $applicant['first_name'] . ' ' . $applicant['last_name'];
?>
<div class="applicant-card" data-user-id="<?= htmlspecialchars($applicant['user_id']) ?>" 
     data-applicant-data='<?= json_encode([
        'name' => $fullName,
        'firstName' => $applicant['first_name'],
        'lastName' => $applicant['last_name'],
        'userId' => $applicant['user_id'],
        'email' => $applicant['email'],
        'phone' => $applicant['phone_area_code'] . ' ' . $applicant['phone_number'],
        'address' => $address,
        'detailedArea' => $detailedArea,
        'emergencyContact' => $emergencyContact,
        'emergencyPhone' => $emergencyPhone,
        'emergencyEmail' => $emergencyEmail,
        'applied' => $appliedDate,
        'documents' => $documents
    ]) ?>'>
    <div class="card-header">
        <div class="card-name"><?= htmlspecialchars($fullName) ?></div>
        <div class="card-actions">
            <button class="expand-btn mobile-view-details" data-user-id="<?= htmlspecialchars($applicant['user_id']) ?>">
                <i class="fas fa-eye"></i> View
            </button>
        </div>
    </div>
    
    <div class="card-section">
        <div class="card-label"><i class="fas fa-id-card"></i> User ID</div>
        <div class="card-value"><?= htmlspecialchars($applicant['user_id']) ?></div>
    </div>
    
    <div class="card-section">
        <div class="card-label"><i class="fas fa-envelope"></i> Contact</div>
        <div class="card-value">
            <div><?= htmlspecialchars($applicant['email']) ?></div>
            <div><?= htmlspecialchars($applicant['phone_area_code']) ?> <?= htmlspecialchars($applicant['phone_number']) ?></div>
        </div>
    </div>
    
    <div class="card-section">
        <div class="card-label"><i class="fas fa-map-marker-alt"></i> Location</div>
        <div class="card-value">
            <div><?= htmlspecialchars($address) ?></div>
            <div><?= htmlspecialchars($detailedArea) ?></div>
        </div>
    </div>
    
    <?php if(!empty($documents)): ?>
    <div class="card-section">
        <div class="card-label"><i class="fas fa-file-alt"></i> Documents</div>
        <div class="card-documents">
            <?php foreach($documents as $doc): 
                $fileName = basename($doc['path']);
                $fileExtension = pathinfo($doc['path'], PATHINFO_EXTENSION);
                $icon = ($fileExtension == 'pdf') ? 'fa-file-pdf' : 'fa-file';
            ?>
                <div class="document-mini">
                    <div class="document-mini-info">
                        <i class="fas <?= $icon ?>"></i>
                        <div class="document-mini-name" title="<?= htmlspecialchars($doc['type'] . ': ' . $fileName) ?>">
                            <?= htmlspecialchars($doc['type']) ?>: <?= $fileName ?>
                        </div>
                    </div>
                    <div class="document-mini-actions">
                        <a href="<?= htmlspecialchars(pcvc_secure_file_url($doc['path'])) ?>" class="mini-btn download" download title="Download">
                            <i class="fas fa-download"></i>
                        </a>
                        <a href="<?= htmlspecialchars(pcvc_secure_file_url($doc['path'], ['inline' => true])) ?>" class="mini-btn" target="_blank" title="View">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card-footer">
        <div class="card-label"><i class="fas fa-calendar"></i> Applied</div>
        <div class="card-value"><?= $appliedDate ?></div>
    </div>
</div>
<?php endforeach; ?>