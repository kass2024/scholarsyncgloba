<?php
// applicant_cards.php
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
    
    // Full name
    $fullName = $applicant['first_name'] . ' ' . $applicant['last_name'];
    $phone = $applicant['phone_area_code'] . ' ' . $applicant['phone_number'];
?>
<div class="applicant-card">
    <!-- Card Header -->
    <div class="card-header">
        <div class="applicant-name"><?= htmlspecialchars($fullName) ?></div>
        <div class="applicant-id">ID: <?= htmlspecialchars($applicant['user_id']) ?></div>
    </div>
    
    <!-- Card Body -->
    <div class="card-body">
        <!-- Contact Info -->
        <div class="card-section">
            <div class="info-row">
                <div class="info-label">Contact</div>
                <div class="info-value">
                    <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($applicant['email']) ?></div>
                    <div><i class="fas fa-phone"></i> <?= htmlspecialchars($phone) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Location -->
        <div class="card-section">
            <div class="info-row">
                <div class="info-label">Location</div>
                <div class="info-value">
                    <div><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($address) ?></div>
                    <div style="font-size: 0.9rem; color: #666;"><?= htmlspecialchars($detailedArea) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Emergency Contact -->
        <div class="card-section">
            <div class="info-row">
                <div class="info-label">Emergency</div>
                <div class="info-value">
                    <div><i class="fas fa-user-shield"></i> <?= htmlspecialchars($emergencyContact) ?></div>
                    <div><i class="fas fa-phone-alt"></i> <?= htmlspecialchars($emergencyPhone) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Documents -->
        <?php if(!empty($documents)): ?>
        <div class="card-section">
            <div class="info-row">
                <div class="info-label">Documents</div>
                <div class="info-value">
                    <div class="documents-section">
                        <ul class="documents-list">
                            <?php foreach($documents as $doc): 
                                $fileName = basename($doc['path']);
                                $fileExtension = pathinfo($doc['path'], PATHINFO_EXTENSION);
                                $icon = ($fileExtension == 'pdf') ? 'fa-file-pdf' : 'fa-file';
                            ?>
                            <li class="document-item">
                                <div class="document-info">
                                    <i class="fas <?= $icon ?>"></i>
                                    <div class="document-name" title="<?= htmlspecialchars($doc['type']) ?>: <?= $fileName ?>">
                                        <?= htmlspecialchars($doc['type']) ?>: <?= $fileName ?>
                                    </div>
                                </div>
                                <div class="document-actions">
                                    <a href="<?= htmlspecialchars(pcvc_secure_file_url($doc['path'])) ?>" class="doc-btn download" download title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <a href="<?= htmlspecialchars(pcvc_secure_file_url($doc['path'], ['inline' => true])) ?>" class="doc-btn" target="_blank" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Card Footer -->
    <div class="card-footer">
        <div class="applied-date">
            <i class="fas fa-calendar-check"></i> <?= $appliedDate ?>
        </div>
        <div class="action-buttons">
            <a href="mailto:<?= htmlspecialchars($applicant['email']) ?>" class="contact-btn" title="Send Email">
                <i class="fas fa-envelope"></i> Contact
            </a>
            <button class="view-btn view-details-btn" 
                    data-applicant='<?= htmlspecialchars(json_encode([
                        'name' => $fullName,
                        'email' => $applicant['email'],
                        'phone' => $phone,
                        'address' => $address,
                        'emergency' => $emergencyContact,
                        'emergencyPhone' => $emergencyPhone,
                        'applied' => $appliedDate,
                        'documents' => $documents
                    ])) ?>'>
                <i class="fas fa-eye"></i> Details
            </button>
        </div>
    </div>
</div>
<?php endforeach; ?>