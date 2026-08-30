<?php
require_once 'auth.php';
require_once __DIR__ . '/db.php';
// Initialize search variables
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = '';
$params = [];
$paramTypes = '';

// Build search query
if (!empty($searchTerm)) {
    $searchTermLike = '%' . $searchTerm . '%';
    $whereClause = "WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR university_name LIKE ? OR user_id LIKE ?";
    $params = [$searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike];
    $paramTypes = 'sssss';
}

// Fetch form 20 applications
$query = "SELECT * FROM form_20_applications $whereClause ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $applicants = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    die("Error preparing query: " . $conn->error);
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

// Document type mapping
$fileFields = [
    'acceptance_letter' => 'Acceptance Letter',
    'loan_approval_letter' => 'Loan Approval Letter',
    'loan_decision_letter' => 'Loan Decision Letter',
    'loan_contract' => 'Loan Contract',
    'bank_statement' => 'Bank Statement',
    'loan_payment_proof' => 'Payment Proof'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I-20 Applications - ScholarSync Global</title>
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

        /* Unread Dot */
        .unread-dot {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 12px;
            height: 12px;
            background: <?= $colors['gold'] ?>;
            border-radius: 50%;
            display: none;
        }

        .applicant-card.unread .unread-dot {
            display: block;
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
            padding-right: 20px;
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
            width: 140px;
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

        /* Status tags */
        .status-tag {
            background: #e6f0ff;
            color: #007bff;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-block;
            margin-right: 6px;
            margin-bottom: 4px;
        }

        .status-tag.scholarship {
            background: #d4edda;
            color: #155724;
        }

        .status-tag.university {
            background: #fff3cd;
            color: #856404;
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
            font-size: 0.9rem;
            color: <?= $colors['navy'] ?>;
        }

        .document-actions {
            display: flex;
            gap: 5px;
        }

        .doc-btn {
            background: <?= $colors['secondary_blue'] ?>;
            color: <?= $colors['white'] ?>;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
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
                <h1><i class="fas fa-file-contract"></i> I-20 Applications</h1>
                <p>Review and manage all I-20/Form 20 applications and documents</p>
            </div>
            <div class="header-stats">
                <div class="applicant-count">
                    <i class="fas fa-users"></i>
                    <?php 
                        $total = count($applicants);
                        echo $total . ' Application' . ($total != 1 ? 's' : '');
                    ?>
                </div>
                <button class="export-btn" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-container">
            <?php if(!empty($searchTerm)): ?>
            <div class="search-info">
                <i class="fas fa-search"></i>
                Searching for: <strong><?= htmlspecialchars($searchTerm) ?></strong>
            </div>
            <?php endif; ?>
            
            <form method="GET" action="" class="search-form">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="Search by name, email, university, or ID..." 
                       value="<?= htmlspecialchars($searchTerm) ?>"
                       title="Search by applicant's name, email, university, or ID">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if(!empty($searchTerm)): ?>
                <a href="?" class="clear-btn">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Applications Grid -->
        <div class="applicants-grid">
            <?php if(empty($applicants)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No applications found</h3>
                    <?php if(!empty($searchTerm)): ?>
                        <p>No applications match your search criteria. Try a different search term.</p>
                        <a href="?" class="search-btn" style="display: inline-flex;">
                            <i class="fas fa-redo"></i> Show All Applications
                        </a>
                    <?php else: ?>
                        <p>No I-20 applications have been submitted yet.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach($applicants as $applicant): 
                    // Full name
                    $fullName = $applicant['first_name'];
                    if (!empty($applicant['middle_name'])) {
                        $fullName .= ' ' . $applicant['middle_name'];
                    }
                    $fullName .= ' ' . $applicant['last_name'];
                    
                    // Format date
                    $appliedDate = date('M d, Y h:i A', strtotime($applicant['created_at']));
                    
                    // Format DOB
                    $dob = $applicant['birth_month'] . '/' . $applicant['birth_day'] . '/' . $applicant['birth_year'];
                    
                    // Format address
                    $address = $applicant['street_address'] . ', ' . $applicant['city'] . ', ' . $applicant['state_province'] . ' ' . $applicant['postal_zip_code'];
                    
                    // Check if unread
                    $isUnread = $applicant['is_read'] == 0;
                    
                    // Scholarship status
                    $hasScholarship = strtolower($applicant['has_scholarship']) == 'yes' ? 'Has Scholarship' : 'No Scholarship';
                ?>
                <div class="applicant-card <?= $isUnread ? 'unread' : '' ?>" data-id="<?= $applicant['user_id'] ?>">
                    <!-- Unread dot -->
                    <div class="unread-dot" title="Unread"></div>
                    
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
                                    <div><i class="fas fa-mobile-alt"></i> <?= htmlspecialchars($applicant['mobile_number']) ?></div>
                                    <?php if (!empty($applicant['phone_number'])): ?>
                                    <div><i class="fas fa-phone"></i> <?= htmlspecialchars($applicant['phone_number']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($applicant['work_number'])): ?>
                                    <div><i class="fas fa-briefcase"></i> <?= htmlspecialchars($applicant['work_number']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Personal Info -->
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Personal</div>
                                <div class="info-value">
                                    <div><i class="fas fa-user"></i> <?= htmlspecialchars($applicant['gender']) ?></div>
                                    <div><i class="fas fa-birthday-cake"></i> <?= htmlspecialchars($dob) ?></div>
                                    <div><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($address) ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- University Info -->
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">University</div>
                                <div class="info-value">
                                    <div><i class="fas fa-university"></i> <?= htmlspecialchars($applicant['university_name']) ?></div>
                                    <div><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($applicant['program_admitted_for']) ?></div>
                                    <div><i class="fas fa-envelope-open"></i> <?= htmlspecialchars($applicant['university_email']) ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Financial Info -->
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Financial</div>
                                <div class="info-value">
                                    <div><i class="fas fa-award"></i> 
                                        <span class="status-tag scholarship"><?= htmlspecialchars($hasScholarship) ?></span>
                                    </div>
                                    <div><i class="fas fa-file-invoice-dollar"></i> Loan documents uploaded</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Documents -->
                        <?php 
                        $hasDocuments = false;
                        foreach ($fileFields as $field => $label) {
                            if (!empty($applicant[$field])) {
                                $hasDocuments = true;
                                break;
                            }
                        }
                        ?>
                        
                        <?php if($hasDocuments): ?>
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Documents</div>
                                <div class="info-value">
                                    <div class="documents-section">
                                        <ul class="documents-list">
                                            <?php foreach($fileFields as $field => $label): 
                                                if (!empty($applicant[$field])):
                                                    $filePath = $applicant[$field];
                                                    $fileName = basename($filePath);
                                                    $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
                                                    $icon = ($fileExtension == 'pdf') ? 'fa-file-pdf' : 'fa-file';
                                            ?>
                                            <li class="document-item">
                                                <div class="document-info">
                                                    <i class="fas <?= $icon ?>"></i>
                                                    <div class="document-name">
                                                        <?= htmlspecialchars($label) ?>: <?= $fileName ?>
                                                    </div>
                                                </div>
                                                <div class="document-actions">
                                                    <a href="<?= htmlspecialchars($filePath) ?>" class="doc-btn download" download title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <a href="<?= htmlspecialchars($filePath) ?>" class="doc-btn" target="_blank" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </li>
                                            <?php endif; endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Comments -->
                        <?php if (!empty($applicant['additional_comments'])): ?>
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Comments</div>
                                <div class="info-value">
                                    <i class="fas fa-comment"></i> <?= htmlspecialchars($applicant['additional_comments']) ?>
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
                                        'id' => $applicant['user_id'],
                                        'email' => $applicant['email'],
                                        'mobile' => $applicant['mobile_number'],
                                        'phone' => $applicant['phone_number'],
                                        'work' => $applicant['work_number'],
                                        'gender' => $applicant['gender'],
                                        'dob' => $dob,
                                        'address' => $address,
                                        'university' => $applicant['university_name'],
                                        'program' => $applicant['program_admitted_for'],
                                        'university_email' => $applicant['university_email'],
                                        'university_password' => $applicant['university_password'],
                                        'scholarship' => $applicant['has_scholarship'],
                                        'comments' => $applicant['additional_comments'],
                                        'applied' => $appliedDate,
                                        'is_read' => $applicant['is_read']
                                    ])) ?>'>
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
    <div id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: white; border-radius: 10px; max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div style="background: <?= $colors['navy'] ?>; color: white; padding: 20px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.2rem;"><i class="fas fa-file-contract"></i> I-20 Application Details</h3>
                <button id="closeModal" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div style="padding: 20px;" id="modalContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('detailsModal');
            const modalContent = document.getElementById('modalContent');
            const closeModal = document.getElementById('closeModal');
            
            // View details button click
            document.querySelectorAll('.view-details-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const applicantData = JSON.parse(this.getAttribute('data-applicant'));
                    showApplicantDetails(applicantData);
                    
                    // Mark as read
                    const card = this.closest('.applicant-card');
                    const userId = card.getAttribute('data-id');
                    markAsRead(userId, card);
                });
            });
            
            // Show applicant details in modal
            function showApplicantDetails(data) {
                modalContent.innerHTML = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                        <div style="grid-column: 1 / -1; background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 15px; border-radius: 8px; text-align: center;">
                            <h4 style="margin: 0; color: <?= $colors['navy'] ?>;">${data.name}</h4>
                            <p style="margin: 5px 0 0 0; color: #666;">ID: ${data.id} | Applied on ${data.applied}</p>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px;">
                            <strong style="color: <?= $colors['secondary_blue'] ?>;">Contact Information</strong>
                            <div style="margin-top: 8px;">
                                <div><i class="fas fa-envelope" style="color: <?= $colors['gold'] ?>;"></i> ${data.email}</div>
                                <div><i class="fas fa-mobile-alt" style="color: <?= $colors['gold'] ?>;"></i> ${data.mobile}</div>
                                ${data.phone ? `<div><i class="fas fa-phone" style="color: <?= $colors['gold'] ?>;"></i> ${data.phone}</div>` : ''}
                                ${data.work ? `<div><i class="fas fa-briefcase" style="color: <?= $colors['gold'] ?>;"></i> ${data.work}</div>` : ''}
                            </div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px;">
                            <strong style="color: <?= $colors['secondary_blue'] ?>;">Personal Details</strong>
                            <div style="margin-top: 8px;">
                                <div><i class="fas fa-user" style="color: <?= $colors['gold'] ?>;"></i> ${data.gender}</div>
                                <div><i class="fas fa-birthday-cake" style="color: <?= $colors['gold'] ?>;"></i> ${data.dob}</div>
                                <div><i class="fas fa-map-marker-alt" style="color: <?= $colors['gold'] ?>;"></i> ${data.address}</div>
                            </div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px;">
                            <strong style="color: <?= $colors['secondary_blue'] ?>;">University Information</strong>
                            <div style="margin-top: 8px;">
                                <div><i class="fas fa-university" style="color: <?= $colors['gold'] ?>;"></i> ${data.university}</div>
                                <div><i class="fas fa-graduation-cap" style="color: <?= $colors['gold'] ?>;"></i> ${data.program}</div>
                                <div><i class="fas fa-envelope-open" style="color: <?= $colors['gold'] ?>;"></i> ${data.university_email}</div>
                            </div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px;">
                            <strong style="color: <?= $colors['secondary_blue'] ?>;">Financial Information</strong>
                            <div style="margin-top: 8px;">
                                <div><i class="fas fa-award" style="color: <?= $colors['gold'] ?>;"></i> Scholarship: 
                                    <span class="status-tag scholarship">${data.scholarship}</span>
                                </div>
                                <div><i class="fas fa-key" style="color: <?= $colors['gold'] ?>;"></i> University Password: ${data.university_password || 'Not provided'}</div>
                            </div>
                        </div>
                        
                        ${data.comments ? `
                        <div style="grid-column: 1 / -1; background: rgba(242, 166, 90, 0.1); padding: 12px; border-radius: 6px; border-left: 4px solid <?= $colors['gold'] ?>;">
                            <strong style="color: <?= $colors['secondary_blue'] ?>;">Additional Comments</strong>
                            <div style="margin-top: 8px; font-style: italic;">${data.comments}</div>
                        </div>
                        ` : ''}
                        
                        <div style="grid-column: 1 / -1; background: rgba(242, 166, 90, 0.1); padding: 12px; border-radius: 6px; border-left: 4px solid <?= $colors['gold'] ?>;">
                            <strong style="color: <?= $colors['secondary_blue'] ?>;">Application Status</strong>
                            <div style="margin-top: 8px;">
                                <div><i class="fas fa-info-circle" style="color: <?= $colors['gold'] ?>;"></i> Application ID: ${data.id}</div>
                                <div><i class="fas fa-calendar" style="color: <?= $colors['gold'] ?>;"></i> Submitted: ${data.applied}</div>
                                <div><i class="fas fa-eye" style="color: <?= $colors['gold'] ?>;"></i> Read Status: ${data.is_read == 0 ? 'Unread' : 'Read'}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                        <a href="mailto:${data.email}" class="contact-btn" style="display: inline-flex; text-decoration: none;">
                            <i class="fas fa-envelope"></i> Send Email
                        </a>
                        <button id="closeDetails" class="search-btn" style="background: #666;">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                `;
                
                modal.style.display = 'flex';
                
                // Close modal events
                closeModal.addEventListener('click', hideModal);
                document.getElementById('closeDetails').addEventListener('click', hideModal);
                
                // Close on outside click
                modal.addEventListener('click', function(e) {
                    if(e.target === modal) {
                        hideModal();
                    }
                });
            }
            
            function hideModal() {
                modal.style.display = 'none';
            }
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if(e.key === 'Escape' && modal.style.display === 'flex') {
                    hideModal();
                }
            });
            
            // Mark as read function
            function markAsRead(userId, cardElement) {
                if (!cardElement.classList.contains('unread')) return;
                
                fetch('mark_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(userId) + '&table=form_20_applications'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
                        cardElement.classList.remove('unread');
                        const dot = cardElement.querySelector('.unread-dot');
                        if (dot) dot.style.display = 'none';
                    }
                })
                .catch(err => console.error('Error marking as read:', err));
            }
            
            // Mark all as read button (optional - add if needed)
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('mark-all-read')) {
                    document.querySelectorAll('.applicant-card.unread').forEach(card => {
                        const userId = card.getAttribute('data-id');
                        markAsRead(userId, card);
                    });
                }
            });
        });
        
        function exportToCSV() {
            // Simple CSV export functionality
            alert('CSV export functionality would be implemented here.\nThis would generate a file with all I-20 application data.');
            // In a real implementation, you would make an AJAX call to an export script
        }
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>