<?php
require_once 'auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/secure_file.php';
// Function to parse JSON or comma-separated tags
function parseTags($value) {
    if(!$value || $value === 'null' || $value === 'NULL') return [];
    if (is_array($value)) return $value;
    
    // Check if it's JSON
    if ($value[0] === '[' || $value[0] === '{') {
        try {
            $parsed = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return is_array($parsed) ? $parsed : [$parsed];
            }
        } catch(Exception $e) {
            // Not valid JSON, fall through to CSV parsing
        }
    }
    
    // Parse as comma-separated or array-like string
    $value = trim($value, "[]\"'");
    if (strpos($value, '","') !== false || strpos($value, "','") !== false) {
        $value = str_replace(['"', "'"], '', $value);
        $items = explode(',', $value);
        return array_map('trim', $items);
    }
    
    // Single value
    return $value ? [trim($value)] : [];
}

// Function to clean filename
function cleanFilename($filename) {
    if (!$filename) return 'Unknown';
    $basename = basename($filename);
    // Remove uploads/ prefix if present
    $basename = str_replace('uploads/', '', $basename);
    // Remove hash prefixes (10+ alphanumeric chars followed by underscore)
    $cleanName = preg_replace('/^[a-f0-9]{10,}_/', '', $basename);
    $cleanName = preg_replace('/^[a-z0-9]{10,}[_-]/i', '', $cleanName);
    return $cleanName ?: $basename;
}

// Fetch applicants
$query = "SELECT * FROM master_loan_applications ORDER BY id DESC";
$result = $conn->query($query);
$applicants = [];
while ($row = $result->fetch_assoc()) {
    $applicants[] = $row;
}

// Brand color codes
$colors = [
    'navy' => '#427431',
    'secondary_blue' => '#3661B9',
    'dark_blue' => '#2f5a26',
    'gold' => '#E21D1E',
    'white' => '#FFFFFF',
    'light_gray' => '#F8F9FA',
    'border_gray' => '#E0E0E0'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Applicants - ScholarSync Global</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: <?= $colors['light_gray'] ?>;
            color: <?= $colors['navy'] ?>;
        }

        .dashboard-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Dashboard Header */
        .dashboard-header {
            background: <?= $colors['white'] ?>;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-left: 5px solid <?= $colors['gold'] ?>;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-title h1 {
            color: <?= $colors['navy'] ?>;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-title h1 i {
            color: <?= $colors['gold'] ?>;
        }

        .header-title p {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .header-stats {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .applicant-count {
            background: <?= $colors['navy'] ?>;
            color: <?= $colors['white'] ?>;
            padding: 8px 15px;
            border-radius: 6px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .export-btn {
            background: <?= $colors['gold'] ?>;
            color: <?= $colors['navy'] ?>;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .export-btn:hover {
            background: #e69542;
            transform: translateY(-2px);
        }

        /* Search Container */
        .search-container {
            background: <?= $colors['white'] ?>;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .search-form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 12px 20px;
            border: 2px solid <?= $colors['border_gray'] ?>;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: <?= $colors['gold'] ?>;
            box-shadow: 0 0 0 3px rgba(242, 166, 90, 0.2);
        }

        .search-btn {
            background: <?= $colors['navy'] ?>;
            color: <?= $colors['white'] ?>;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .search-btn:hover {
            background: <?= $colors['dark_blue'] ?>;
        }

        .clear-btn {
            background: <?= $colors['secondary_blue'] ?>;
            color: <?= $colors['white'] ?>;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .clear-btn:hover {
            background: <?= $colors['navy'] ?>;
        }

        /* Main Content - Cards Grid */
        .applicants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .applicants-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Applicant Card */
        .applicant-card {
            background: <?= $colors['white'] ?>;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            border: 1px solid <?= $colors['border_gray'] ?>;
            position: relative;
        }

        .applicant-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            background: linear-gradient(135deg, <?= $colors['navy'] ?> 0%, <?= $colors['dark_blue'] ?> 100%);
            color: <?= $colors['white'] ?>;
            padding: 15px;
            position: relative;
        }

        .applicant-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .applicant-id {
            font-size: 0.85rem;
            opacity: 0.8;
            font-family: monospace;
            background: rgba(255, 255, 255, 0.1);
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .card-body {
            padding: 15px;
        }

        .card-section {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid <?= $colors['light_gray'] ?>;
        }

        .card-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .info-label {
            width: 120px;
            font-weight: 600;
            color: <?= $colors['secondary_blue'] ?>;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .info-value {
            flex: 1;
            color: <?= $colors['navy'] ?>;
            font-size: 0.95rem;
            word-break: break-word;
        }

        .info-value i {
            margin-right: 8px;
            color: <?= $colors['gold'] ?>;
            width: 16px;
        }

        /* Tags */
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 5px;
        }

        .tag {
            background-color: #e0ecff;
            color: <?= $colors['navy'] ?>;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Documents Section */
        .documents-section {
            background: rgba(242, 166, 90, 0.05);
            padding: 12px;
            border-radius: 6px;
            border-left: 3px solid <?= $colors['gold'] ?>;
        }

        .documents-list {
            list-style: none;
            max-height: 150px;
            overflow-y: auto;
        }

        .document-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed <?= $colors['border_gray'] ?>;
        }

        .document-item:last-child {
            border-bottom: none;
        }

        .document-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }

        .document-info i {
            color: <?= $colors['gold'] ?>;
        }

        .document-name {
            font-size: 0.85rem;
            color: <?= $colors['navy'] ?>;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .document-actions {
            display: flex;
            gap: 5px;
        }

        .doc-btn {
            background: <?= $colors['secondary_blue'] ?>;
            color: <?= $colors['white'] ?>;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.8rem;
        }

        .doc-btn:hover {
            background: <?= $colors['navy'] ?>;
        }

        .doc-btn.download {
            background: <?= $colors['gold'] ?>;
            color: <?= $colors['navy'] ?>;
        }

        .doc-btn.download:hover {
            background: #e69542;
        }

        /* Card Footer */
        .card-footer {
            padding: 15px;
            background: <?= $colors['light_gray'] ?>;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid <?= $colors['border_gray'] ?>;
        }

        .applied-date {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .view-btn {
            background: <?= $colors['secondary_blue'] ?>;
            color: <?= $colors['white'] ?>;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .view-btn:hover {
            background: <?= $colors['navy'] ?>;
        }

        .contact-btn {
            background: <?= $colors['gold'] ?>;
            color: <?= $colors['navy'] ?>;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .contact-btn:hover {
            background: #e69542;
        }

        /* Unread Indicator */
        .unread-indicator {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 10px;
            height: 10px;
            background: <?= $colors['gold'] ?>;
            border-radius: 50%;
        }

        /* Search Info */
        .search-info {
            background: rgba(242, 166, 90, 0.1);
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border-left: 4px solid <?= $colors['gold'] ?>;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Empty State */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px 20px;
            background: <?= $colors['white'] ?>;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .empty-state i {
            font-size: 3rem;
            color: <?= $colors['border_gray'] ?>;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .empty-state p {
            color: #777;
            font-size: 0.95rem;
            margin-bottom: 20px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .modal-header {
            background: <?= $colors['navy'] ?>;
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .modal-section {
            background: <?= $colors['light_gray'] ?>;
            padding: 15px;
            border-radius: 8px;
        }

        .modal-section h4 {
            color: <?= $colors['navy'] ?>;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid <?= $colors['border_gray'] ?>;
            font-size: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            
            .header-stats {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .search-input {
                width: 100%;
            }
            
            .search-btn, .clear-btn {
                width: 100%;
                justify-content: center;
            }
            
            .info-row {
                flex-direction: column;
            }
            
            .info-label {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .card-footer {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
            
            .action-buttons {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .dashboard-container {
                padding: 10px;
            }
            
            .applicants-grid {
                gap: 15px;
            }
            
            .card-header, .card-body, .card-footer {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="header-title">
                <h1><i class="fas fa-file-invoice-dollar"></i> Loan Applicants Dashboard</h1>
                <p>Review and manage all loan applications and documents</p>
            </div>
            <div class="header-stats">
                <div class="applicant-count">
                    <i class="fas fa-users"></i>
                    <?php 
                        $total = count($applicants);
                        echo $total . ' Applicant' . ($total != 1 ? 's' : '');
                    ?>
                </div>
                <a href="#" class="export-btn" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export CSV
                </a>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-container">
            <div id="searchResults" style="display: none;"></div>
            
            <form class="search-form" onsubmit="return false;">
                <input type="text" 
                       id="searchInput" 
                       class="search-input" 
                       placeholder="Search by name, email, or program..." 
                       title="Search loan applicants">
                <button type="button" class="search-btn" onclick="performSearch()">
                    <i class="fas fa-search"></i> Search
                </button>
                <button type="button" class="clear-btn" onclick="clearSearch()" style="display: none;" id="clearSearchBtn">
                    <i class="fas fa-times"></i> Clear
                </button>
            </form>
        </div>

        <!-- Applicants Grid -->
        <div class="applicants-grid" id="applicantsGrid">
            <?php if(empty($applicants)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No loan applicants found</h3>
                    <p>No loan applications have been submitted yet.</p>
                </div>
            <?php else: ?>
                <?php foreach($applicants as $index => $applicant): 
                    // Format data
                    $fullName = htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']);
                    $appliedDate = date('M d, Y h:i A', strtotime($applicant['created_at']));
                    $address = htmlspecialchars(trim(($applicant['address1'] ?? '') . ', ' . ($applicant['city'] ?? '') . ', ' . ($applicant['state'] ?? ''), ', '));
                    
                    // Check if read
                    $isRead = isset($applicant['is_read']) && $applicant['is_read'] == 1;
                    
                    // Document fields
                    $documentFields = [
                        'acceptance_letter' => 'Acceptance Letter',
                        'bachelor_degree' => 'Bachelor Degree',
                        'bachelor_transcript' => 'Bachelor Transcript',
                        'cv' => 'CV',
                        'id_document' => 'ID Document',
                        'valid_passport' => 'Valid Passport',
                        'english_certificate' => 'English Certificate',
                        'admission_fees' => 'Admission Fees',
                        'scholarship_letter' => 'Scholarship Letter',
                        'bank_statement' => 'Bank Statement'
                    ];
                    
                    // Get uploaded documents
                    $documents = [];
                    foreach($documentFields as $field => $label) {
                        if(!empty($applicant[$field])) {
                            $filename = cleanFilename($applicant[$field]);
                            $documents[] = [
                                'type' => $label,
                                'path' => $applicant[$field],
                                'filename' => $filename
                            ];
                        }
                    }
                    
                    // Parse tags
                    $loanReasons = parseTags($applicant['loan_reason']);
                    $programs = parseTags($applicant['masters_program_name']);
                    $schools = parseTags($applicant['school_name']);
                    $degreeTypes = parseTags($applicant['degree_type']);
                    $intakes = parseTags($applicant['intake']);
                ?>
                <div class="applicant-card" data-index="<?= $index ?>" data-id="<?= $applicant['id'] ?>">
                    <?php if(!$isRead): ?>
                    <div class="unread-indicator"></div>
                    <?php endif; ?>
                    
                    <!-- Card Header -->
                    <div class="card-header">
                        <div class="applicant-name"><?= $fullName ?></div>
                        <div class="applicant-id">ID: <?= htmlspecialchars($applicant['id']) ?></div>
                    </div>
                    
                    <!-- Card Body -->
                    <div class="card-body">
                        <!-- Contact Info -->
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Contact</div>
                                <div class="info-value">
                                    <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($applicant['email'] ?? 'N/A') ?></div>
                                    <div><i class="fas fa-phone"></i> <?= htmlspecialchars($applicant['phone_number'] ?? 'N/A') ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Program Info -->
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Program</div>
                                <div class="info-value">
                                    <?php if(!empty($programs)): ?>
                                    <div class="tags-container">
                                        <?php foreach(array_slice($programs, 0, 3) as $program): ?>
                                        <span class="tag"><?= htmlspecialchars(trim($program)) ?></span>
                                        <?php endforeach; ?>
                                        <?php if(count($programs) > 3): ?>
                                        <span class="tag">+<?= count($programs) - 3 ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <span style="color: #666; font-style: italic;">Not specified</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Loan Details -->
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Loan Purpose</div>
                                <div class="info-value">
                                    <?php if(!empty($loanReasons)): ?>
                                    <div class="tags-container">
                                        <?php foreach(array_slice($loanReasons, 0, 2) as $reason): ?>
                                        <span class="tag"><?= htmlspecialchars(trim($reason)) ?></span>
                                        <?php endforeach; ?>
                                        <?php if(count($loanReasons) > 2): ?>
                                        <span class="tag">+<?= count($loanReasons) - 2 ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <span style="color: #666; font-style: italic;">Not specified</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Intake</div>
                                <div class="info-value">
                                    <?php if(!empty($intakes)): ?>
                                    <div class="tags-container">
                                        <?php foreach($intakes as $intake): ?>
                                        <span class="tag"><?= htmlspecialchars($intake) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="tag">N/A</span>
                                    <?php endif; ?>
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
                                            <?php foreach(array_slice($documents, 0, 3) as $doc): ?>
                                            <li class="document-item">
                                                <div class="document-info">
                                                    <i class="fas fa-file"></i>
                                                    <div class="document-name" title="<?= htmlspecialchars($doc['filename']) ?>">
                                                        <?= htmlspecialchars($doc['type']) ?>
                                                    </div>
                                                </div>
                                                <div class="document-actions">
                                                    <a href="<?= htmlspecialchars(pcvc_secure_file_url($doc['path'])) ?>" class="doc-btn download" download title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </li>
                                            <?php endforeach; ?>
                                            <?php if(count($documents) > 3): ?>
                                            <li class="document-item">
                                                <div class="document-info">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                    <div class="document-name">
                                                        <?= count($documents) - 3 ?> more documents
                                                    </div>
                                                </div>
                                            </li>
                                            <?php endif; ?>
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
                                        'id' => $applicant['id'],
                                        'name' => $fullName,
                                        'email' => $applicant['email'] ?? '',
                                        'phone' => $applicant['phone_number'] ?? '',
                                        'address' => $address,
                                        'dob' => $applicant['dob'] ?? '',
                                        'gender' => $applicant['gender'] ?? '',
                                        'applied' => $appliedDate,
                                        'loan_reason' => $loanReasons,
                                        'programs' => $programs,
                                        'schools' => $schools,
                                        'degree_type' => $degreeTypes,
                                        'application_type' => $applicant['application_type'] ?? '',
                                        'intake' => $intakes,
                                        'citizenship' => $applicant['citizenship_country'] ?? '',
                                        'has_visa' => $applicant['has_visa'] ?? '',
                                        'has_ssn' => $applicant['has_ssn'] ?? '',
                                        'ref_name' => trim(($applicant['ref_first_name'] ?? '') . ' ' . ($applicant['ref_last_name'] ?? '')),
                                        'ref_email' => $applicant['ref_email'] ?? '',
                                        'ref_phone' => $applicant['ref_phone'] ?? '',
                                        'ref_relationship' => $applicant['ref_relationship'] ?? '',
                                        'applicant_signed' => trim(($applicant['applicant_first_name'] ?? '') . ' ' . ($applicant['applicant_last_name'] ?? '')),
                                        'date_signed' => $applicant['date_signed'] ?? '',
                                        'documents' => $documents,
                                        'form_url' => $applicant['form_url'] ?? '',
                                        'user_id' => $applicant['user_id'] ?? ''
                                    ]), JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-invoice-dollar"></i> Loan Application Details</h3>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
    </div>

    <script>
        <?= pcvc_secure_file_js() ?>

        // Applicants data from PHP
        const applicants = <?= json_encode($applicants, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const searchInput = document.getElementById('searchInput');
        const clearBtn = document.getElementById('clearSearchBtn');
        const applicantsGrid = document.getElementById('applicantsGrid');
        const modal = document.getElementById('detailsModal');
        const modalContent = document.getElementById('modalContent');
        const closeModal = document.getElementById('closeModal');

        // Function to parse tags (similar to PHP function)
        function parseTags(value) {
            if(!value || value === 'null' || value === 'NULL' || value === 'N/A') return [];
            if (Array.isArray(value)) return value;
            
            try {
                // Check if it's JSON
                if (typeof value === 'string' && (value.startsWith('[') || value.startsWith('{'))) {
                    const parsed = JSON.parse(value);
                    return Array.isArray(parsed) ? parsed : [parsed];
                }
                
                // Try to parse as comma-separated
                value = value.trim().replace(/^\[|\]$/g, '').replace(/^"|"$/g, '');
                if (value.includes(',')) {
                    return value.split(',').map(item => item.trim().replace(/^"|"$/g, '')).filter(item => item);
                }
                
                return value ? [value] : [];
            } catch(e) {
                return value ? [value] : [];
            }
        }

        // Function to clean filename
        function cleanFilename(filename) {
            if (!filename) return 'Unknown';
            const basename = filename.split('/').pop();
            const cleanName = basename.replace(/^[a-f0-9]{10,}_/, '').replace(/^[a-z0-9]{10,}[_-]/i, '');
            return cleanName || basename;
        }

        // Perform search
        function performSearch() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            
            if (!searchTerm) {
                clearSearch();
                return;
            }
            
            // Show clear button
            clearBtn.style.display = 'flex';
            
            // Filter applicants
            const filtered = applicants.filter(applicant => {
                const searchText = `
                    ${applicant.first_name || ''}
                    ${applicant.last_name || ''}
                    ${applicant.email || ''}
                    ${applicant.phone_number || ''}
                    ${applicant.masters_program_name || ''}
                    ${applicant.school_name || ''}
                    ${applicant.degree_type || ''}
                `.toLowerCase();
                
                return searchText.includes(searchTerm);
            });
            
            // Update grid
            updateGrid(filtered, searchTerm);
        }

        // Clear search
        function clearSearch() {
            searchInput.value = '';
            clearBtn.style.display = 'none';
            updateGrid(applicants);
        }

        // Update grid with filtered applicants
        function updateGrid(filteredApplicants, searchTerm = '') {
            if (filteredApplicants.length === 0) {
                applicantsGrid.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No matching applicants found</h3>
                        <p>No loan applications match "${searchTerm}". Try a different search term.</p>
                        <button class="search-btn" onclick="clearSearch()" style="margin-top: 10px;">
                            <i class="fas fa-redo"></i> Show All Applicants
                        </button>
                    </div>
                `;
                return;
            }

            // Rebuild grid
            let html = '';
            filteredApplicants.forEach((applicant, index) => {
                // Format data
                const fullName = (applicant.first_name || '') + ' ' + (applicant.last_name || '');
                const appliedDate = new Date(applicant.created_at).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                // Get documents
                const documentFields = {
                    'acceptance_letter': 'Acceptance Letter',
                    'bachelor_degree': 'Bachelor Degree',
                    'bachelor_transcript': 'Bachelor Transcript',
                    'cv': 'CV',
                    'id_document': 'ID Document',
                    'valid_passport': 'Valid Passport',
                    'english_certificate': 'English Certificate',
                    'admission_fees': 'Admission Fees',
                    'scholarship_letter': 'Scholarship Letter',
                    'bank_statement': 'Bank Statement'
                };
                
                const documents = [];
                for (const [field, label] of Object.entries(documentFields)) {
                    if (applicant[field]) {
                        const filename = cleanFilename(applicant[field]);
                        documents.push({
                            type: label,
                            path: applicant[field],
                            filename: filename
                        });
                    }
                }
                
                // Parse tags
                const loanReasons = parseTags(applicant.loan_reason);
                const programs = parseTags(applicant.masters_program_name);
                const intakes = parseTags(applicant.intake);
                
                html += `
                <div class="applicant-card" data-index="${index}" data-id="${applicant.id}">
                    ${(applicant.is_read != 1) ? '<div class="unread-indicator"></div>' : ''}
                    
                    <div class="card-header">
                        <div class="applicant-name">${fullName}</div>
                        <div class="applicant-id">ID: ${applicant.id}</div>
                    </div>
                    
                    <div class="card-body">
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Contact</div>
                                <div class="info-value">
                                    <div><i class="fas fa-envelope"></i> ${applicant.email || 'N/A'}</div>
                                    <div><i class="fas fa-phone"></i> ${applicant.phone_number || 'N/A'}</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Program</div>
                                <div class="info-value">
                                    ${programs.length ? `
                                    <div class="tags-container">
                                        ${programs.slice(0, 3).map(p => `<span class="tag">${p.trim()}</span>`).join('')}
                                        ${programs.length > 3 ? `<span class="tag">+${programs.length - 3} more</span>` : ''}
                                    </div>` : `<span style="color: #666; font-style: italic;">Not specified</span>`}
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Loan Purpose</div>
                                <div class="info-value">
                                    ${loanReasons.length ? `
                                    <div class="tags-container">
                                        ${loanReasons.slice(0, 2).map(r => `<span class="tag">${r.trim()}</span>`).join('')}
                                        ${loanReasons.length > 2 ? `<span class="tag">+${loanReasons.length - 2} more</span>` : ''}
                                    </div>` : `<span style="color: #666; font-style: italic;">Not specified</span>`}
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Intake</div>
                                <div class="info-value">
                                    ${intakes.length ? `
                                    <div class="tags-container">
                                        ${intakes.map(intake => `<span class="tag">${intake}</span>`).join('')}
                                    </div>` : `<span class="tag">N/A</span>`}
                                </div>
                            </div>
                        </div>
                        
                        ${documents.length ? `
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Documents</div>
                                <div class="info-value">
                                    <div class="documents-section">
                                        <ul class="documents-list">
                                            ${documents.slice(0, 3).map(doc => `
                                            <li class="document-item">
                                                <div class="document-info">
                                                    <i class="fas fa-file"></i>
                                                    <div class="document-name" title="${doc.filename}">
                                                        ${doc.type}
                                                    </div>
                                                </div>
                                                <div class="document-actions">
                                                    <a href="${pcvcSecureFileUrl(doc.path)}" class="doc-btn download" download title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </li>
                                            `).join('')}
                                            ${documents.length > 3 ? `
                                            <li class="document-item">
                                                <div class="document-info">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                    <div class="document-name">
                                                        ${documents.length - 3} more documents
                                                    </div>
                                                </div>
                                            </li>` : ''}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>` : ''}
                    </div>
                    
                    <div class="card-footer">
                        <div class="applied-date">
                            <i class="fas fa-calendar-check"></i> ${appliedDate}
                        </div>
                        <div class="action-buttons">
                            <a href="mailto:${applicant.email || '#'}" class="contact-btn" title="Send Email" ${!applicant.email ? 'style="opacity:0.5; cursor:not-allowed;" onclick="return false;"' : ''}>
                                <i class="fas fa-envelope"></i> Contact
                            </a>
                            <button class="view-btn view-details-btn" 
                                    data-applicant='${JSON.stringify({
                                        id: applicant.id,
                                        name: fullName,
                                        email: applicant.email || '',
                                        phone: applicant.phone_number || '',
                                        address: (applicant.address1 || '') + ', ' + (applicant.city || '') + ', ' + (applicant.state || ''),
                                        dob: applicant.dob || '',
                                        gender: applicant.gender || '',
                                        applied: appliedDate,
                                        loan_reason: loanReasons,
                                        programs: programs,
                                        schools: parseTags(applicant.school_name),
                                        degree_type: parseTags(applicant.degree_type),
                                        application_type: applicant.application_type || '',
                                        intake: intakes,
                                        citizenship: applicant.citizenship_country || '',
                                        has_visa: applicant.has_visa || '',
                                        has_ssn: applicant.has_ssn || '',
                                        ref_name: (applicant.ref_first_name || '') + ' ' + (applicant.ref_last_name || ''),
                                        ref_email: applicant.ref_email || '',
                                        ref_phone: applicant.ref_phone || '',
                                        ref_relationship: applicant.ref_relationship || '',
                                        applicant_signed: (applicant.applicant_first_name || '') + ' ' + (applicant.applicant_last_name || ''),
                                        date_signed: applicant.date_signed || '',
                                        documents: documents,
                                        form_url: applicant.form_url || '',
                                        user_id: applicant.user_id || ''
                                    })}'>
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </div>
                    </div>
                </div>
                `;
            });
            
            applicantsGrid.innerHTML = html;
            
            // Reattach event listeners
            attachEventListeners();
        }

        // Show applicant details in modal
        function showApplicantDetails(data) {
            // Mark as read via AJAX
            markAsRead(data.id);
            
            // Build documents HTML
            let documentsHtml = '';
            if(data.documents && data.documents.length > 0) {
                documentsHtml = data.documents.map(doc => `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f8f9fa; border-radius: 6px; margin-bottom: 8px; border-left: 4px solid <?= $colors['gold'] ?>;">
                        <div style="flex: 1;">
                            <strong>${doc.type}</strong><br>
                            <small style="color: #666;">${doc.filename}</small>
                        </div>
                        <div>
                            <a href="${pcvcSecureFileUrl(doc.path)}" class="doc-btn download" download style="display: inline-flex; margin-right: 5px;">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <a href="${pcvcSecureFileUrl(doc.path, true)}" class="doc-btn" target="_blank" style="display: inline-flex;">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                `).join('');
            } else {
                documentsHtml = '<p style="color: #666; font-style: italic;">No documents uploaded</p>';
            }
            
            // Build tags HTML
            function buildTags(tags) {
                if(!tags || tags.length === 0) return 'N/A';
                return tags.map(tag => `<span class="tag">${tag}</span>`).join(' ');
            }
            
            modalContent.innerHTML = `
                <div class="modal-grid">
                    <div style="grid-column: 1 / -1; background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 15px; border-radius: 8px; text-align: center;">
                        <h4 style="margin: 0; color: <?= $colors['navy'] ?>;">${data.name}</h4>
                        <p style="margin: 5px 0 0 0; color: #666;">Applied on ${data.applied}</p>
                    </div>
                    
                    <div class="modal-section">
                        <h4><i class="fas fa-user-circle"></i> Personal Info</h4>
                        <div style="margin-top: 8px;">
                            <div><strong>User ID:</strong> ${data.user_id || 'N/A'}</div>
                            <div><strong>Email:</strong> ${data.email || 'N/A'}</div>
                            <div><strong>Phone:</strong> ${data.phone || 'N/A'}</div>
                            <div><strong>Date of Birth:</strong> ${data.dob || 'N/A'}</div>
                            <div><strong>Gender:</strong> ${data.gender || 'N/A'}</div>
                            <div><strong>Address:</strong> ${data.address || 'N/A'}</div>
                        </div>
                    </div>
                    
                    <div class="modal-section">
                        <h4><i class="fas fa-graduation-cap"></i> Education Details</h4>
                        <div style="margin-top: 8px;">
                            <div><strong>Program(s):</strong><br>${buildTags(data.programs)}</div>
                            <div><strong>School(s):</strong><br>${buildTags(data.schools)}</div>
                            <div><strong>Degree Type:</strong><br>${buildTags(data.degree_type)}</div>
                            <div><strong>Application Type:</strong> ${data.application_type || 'N/A'}</div>
                            <div><strong>Intake:</strong><br>${buildTags(data.intake)}</div>
                        </div>
                    </div>
                    
                    <div class="modal-section">
                        <h4><i class="fas fa-money-check-alt"></i> Loan Details</h4>
                        <div style="margin-top: 8px;">
                            <div><strong>Loan Purpose:</strong><br>${buildTags(data.loan_reason)}</div>
                            <div><strong>Citizenship:</strong> ${data.citizenship || 'N/A'}</div>
                            <div><strong>Has Visa:</strong> ${data.has_visa || 'N/A'}</div>
                            <div><strong>Has SSN:</strong> ${data.has_ssn || 'N/A'}</div>
                        </div>
                    </div>
                    
                    <div class="modal-section">
                        <h4><i class="fas fa-user-friends"></i> Reference</h4>
                        <div style="margin-top: 8px;">
                            <div><strong>Name:</strong> ${data.ref_name || 'N/A'}</div>
                            <div><strong>Email:</strong> ${data.ref_email || 'N/A'}</div>
                            <div><strong>Phone:</strong> ${data.ref_phone || 'N/A'}</div>
                            <div><strong>Relationship:</strong> ${data.ref_relationship || 'N/A'}</div>
                        </div>
                    </div>
                    
                    <div class="modal-section">
                        <h4><i class="fas fa-signature"></i> Declaration</h4>
                        <div style="margin-top: 8px;">
                            <div><strong>Signed By:</strong> ${data.applicant_signed || 'N/A'}</div>
                            <div><strong>Date Signed:</strong> ${data.date_signed || 'N/A'}</div>
                            <div><strong>Form URL:</strong> ${data.form_url || 'N/A'}</div>
                        </div>
                    </div>
                    
                    <div style="grid-column: 1 / -1;">
                        <h4><i class="fas fa-file-upload"></i> Uploaded Documents (${data.documents ? data.documents.length : 0})</h4>
                        ${documentsHtml}
                    </div>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                    ${data.email ? `<a href="mailto:${data.email}" class="contact-btn" style="display: inline-flex; text-decoration: none;">
                        <i class="fas fa-envelope"></i> Send Email
                    </a>` : ''}
                    <button class="view-btn" onclick="hideModal()" style="background: #666;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
            
            modal.style.display = 'flex';
        }

        // Mark as read via AJAX
        function markAsRead(applicantId) {
            fetch('mark_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'id=' + applicantId + '&table=master_loan_applications'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok') {
                    // Remove unread indicator from card
                    const card = document.querySelector(`.applicant-card[data-id="${applicantId}"]`);
                    if (card) {
                        const indicator = card.querySelector('.unread-indicator');
                        if (indicator) {
                            indicator.remove();
                        }
                    }
                }
            })
            .catch(error => console.error('Error marking as read:', error));
        }

        // Export to CSV
        function exportToCSV() {
            // Basic CSV implementation
            const headers = ['ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Program', 'School', 'Intake', 'Applied Date'];
            const csvRows = [headers.join(',')];
            
            applicants.forEach(applicant => {
                const row = [
                    applicant.id,
                    `"${applicant.first_name || ''}"`,
                    `"${applicant.last_name || ''}"`,
                    `"${applicant.email || ''}"`,
                    `"${applicant.phone_number || ''}"`,
                    `"${parseTags(applicant.masters_program_name).join('; ')}"`,
                    `"${parseTags(applicant.school_name).join('; ')}"`,
                    `"${parseTags(applicant.intake).join('; ')}"`,
                    `"${applicant.created_at}"`
                ];
                csvRows.push(row.join(','));
            });
            
            const csvContent = csvRows.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `loan-applicants-${new Date().toISOString().slice(0,10)}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // Hide modal
        function hideModal() {
            modal.style.display = 'none';
        }

        // Attach event listeners
        function attachEventListeners() {
            // View details buttons
            document.querySelectorAll('.view-details-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const applicantData = JSON.parse(this.getAttribute('data-applicant'));
                    showApplicantDetails(applicantData);
                });
            });
            
            // Search on Enter key
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });
            
            // Real-time search as user types (optional)
            searchInput.addEventListener('input', function() {
                if (this.value.trim().length >= 2) {
                    performSearch();
                } else if (this.value.trim() === '') {
                    clearSearch();
                }
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            attachEventListeners();
            
            // Modal close events
            closeModal.addEventListener('click', hideModal);
            modal.addEventListener('click', function(e) {
                if(e.target === modal) {
                    hideModal();
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if(e.key === 'Escape' && modal.style.display === 'flex') {
                    hideModal();
                }
            });
            
            // Initialize with all applicants
            updateGrid(applicants);
        });
    </script>
</body>
</html>