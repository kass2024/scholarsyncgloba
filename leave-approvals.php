<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/company_branding.php';
require_once __DIR__ . '/helpers/secure_file.php';
$companyBrandName = PCVC_COMPANY_DISPLAY_NAME;
/* ===========================================================
   PERMISSION CHECK
=========================================================== */
if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    exit('Unauthorized access. Please log in as superadmin.');
}

$currentUserId = $_SESSION['id'];

/* ===========================================================
   HANDLE STATUS UPDATE
=========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
    
    $stmt = $conn->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $action, $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Leave request " . $action . " successfully.";
    } else {
        $_SESSION['error'] = "Failed to update leave request.";
    }
    
    // Redirect to remove POST data and show message
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
    exit;
}

/* ===========================================================
   FILTERING
=========================================================== */
$filter = $_GET['filter'] ?? 'all';
$where = "";

switch ($filter) {
    case 'today':
        $where = "WHERE DATE(lr.requested_at) = CURDATE()";
        break;
    case 'week':
        $where = "WHERE YEARWEEK(lr.requested_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'month':
        $where = "WHERE MONTH(lr.requested_at) = MONTH(CURDATE()) AND YEAR(lr.requested_at) = YEAR(CURDATE())";
        break;
    default:
        $where = "";
        break;
}

$query = "
    SELECT lr.id, lr.leave_date, lr.reason, lr.status, lr.requested_at,
           a.full_name, a.profile_photo, a.position
    FROM leave_requests lr
    JOIN admins a ON lr.admin_id = a.id
    $where
    ORDER BY 
        CASE lr.status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            ELSE 3
        END,
        lr.requested_at DESC
";
$result = $conn->query($query);

// Get counts for stats
$totalRequests = $result->num_rows;
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

$result->data_seek(0);
while ($row = $result->fetch_assoc()) {
    switch ($row['status']) {
        case 'pending': $pendingCount++; break;
        case 'approved': $approvedCount++; break;
        case 'rejected': $rejectedCount++; break;
    }
}
$result->data_seek(0);

// Get success/error messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ScholarSync Global Visa — Leave Requests</title>
    
    <!-- Brand color variables -->
    <style>
        :root {
            --deep-navy: #427431;
            --secondary-blue: #3661B9;
            --dark-blue: #2f5a26;
            --gold: #E21D1E;
            --white: #FFFFFF;
            --light-bg: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --success: #2e7d32;
            --danger: #c62828;
            --warning: #ed6c02;
            --info: #0288d1;
            --border-light: #e2e8f0;
        }
    </style>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(180deg, var(--white) 0%, #f0f4f8 100%);
            color: var(--text-dark);
            min-height: 100vh;
            overflow: hidden;
        }

        /* ===== Brand header ===== */
        .brand-payroll-header {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            padding: 12px 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 3px solid var(--gold);
        }

        .header-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 0 24px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo-main {
            font-size: 2rem;
            font-weight: 800;
            color: var(--white);
            letter-spacing: 1px;
            position: relative;
            display: inline-block;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .logo-main::after {
            content: '🏖️';
            position: absolute;
            top: -8px;
            right: -35px;
            font-size: 1.8rem;
            filter: drop-shadow(2px 2px 2px rgba(0,0,0,0.3));
        }

        .logo-subtitle {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--gold);
            letter-spacing: 1px;
            border-left: 3px solid var(--gold);
            padding-left: 20px;
            text-transform: uppercase;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }

        /* ===== MAIN CONTAINER ===== */
        .main-container {
            height: calc(100vh - 80px);
            padding: 20px 24px 0 24px;
            display: flex;
            flex-direction: column;
        }

        /* ===== PAGE HEADER ===== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-shrink: 0;
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--deep-navy);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .page-title i {
            color: var(--gold);
            font-size: 28px;
        }

        .stats-container {
            display: flex;
            gap: 15px;
        }

        .stat-card {
            background: var(--white);
            padding: 10px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(1, 47, 107, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid var(--border-light);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 20px;
        }

        .stat-info h3 {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            color: var(--deep-navy);
            line-height: 1.2;
        }

        .stat-info p {
            margin: 0;
            color: var(--text-muted);
            font-size: 12px;
        }

        /* ===== ALERTS ===== */
        .alert {
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 16px;
            border: none;
            font-weight: 500;
            flex-shrink: 0;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1e7dd 0%, #c3e6cb 100%);
            color: #0a3622;
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #58151c;
            border-left: 4px solid var(--danger);
        }

        /* ===== FILTERS SECTION ===== */
        .filters-section {
            background: var(--white);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(1, 47, 107, 0.08);
            border: 1px solid var(--border-light);
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
            flex-shrink: 0;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--light-bg);
            color: var(--text-muted);
            border: 1px solid var(--border-light);
        }

        .filter-btn i {
            font-size: 14px;
        }

        .filter-btn:hover {
            background: var(--border-light);
            color: var(--text-dark);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            border: none;
        }

        .filter-btn.active i {
            color: var(--gold);
        }

        .btn-export {
            background: linear-gradient(135deg, var(--success) 0%, #1b5e20 100%);
            color: var(--white);
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            transition: all 0.2s ease;
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-export:hover {
            background: linear-gradient(135deg, #1b5e20 0%, #0a3622 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 125, 50, 0.2);
        }

        /* ===== TABLE CARD ===== */
        .table-card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(1, 47, 107, 0.1);
            border: 1px solid var(--border-light);
            overflow: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .table-responsive {
            flex: 1;
            overflow: auto;
            position: relative;
        }

        /* Table headers */
        th {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f6 100%);
            color: var(--deep-navy);
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 12px;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
            border-bottom: 2px solid var(--gold);
            border-right: 1px solid #dee2e6;
        }

        th:last-child {
            border-right: none;
        }

        th.sortable {
            cursor: pointer;
            transition: background 0.2s ease;
        }

        th.sortable:hover {
            background: linear-gradient(135deg, #eef2f6 0%, #e2e8f0 100%);
        }

        th i {
            margin-left: 4px;
            color: var(--gold);
        }

        /* Table cells */
        td {
            padding: 12px 12px;
            border-bottom: 1px solid var(--border-light);
            border-right: 1px solid #f1f3f5;
            vertical-align: middle;
            background: var(--white);
            white-space: nowrap;
        }

        td:last-child {
            border-right: none;
        }

        tr:hover td {
            background: rgba(242, 166, 90, 0.02);
        }

        /* Employee info */
        .employee-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-img-container {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--gold);
            flex-shrink: 0;
        }

        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-placeholder {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-blue) 0%, var(--deep-navy) 100%);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .employee-details {
            display: flex;
            flex-direction: column;
        }

        .employee-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .employee-position {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* Date badge */
        .date-badge {
            background: var(--light-bg);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            color: var(--deep-navy);
            border-left: 3px solid var(--gold);
            white-space: nowrap;
        }

        .date-badge i {
            margin-right: 4px;
            color: var(--gold);
        }

        /* Reason cell */
        .reason-cell {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-width: 90px;
            text-align: center;
        }

        .status-approved {
            background: linear-gradient(135deg, #d1e7dd 0%, #c3e6cb 100%);
            color: #0a3622;
            border-left: 3px solid var(--success);
        }

        .status-pending {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            color: #856404;
            border-left: 3px solid var(--warning);
        }

        .status-rejected {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #58151c;
            border-left: 3px solid var(--danger);
        }

        /* Date display */
        .date-display {
            display: flex;
            flex-direction: column;
        }

        .date-main {
            font-weight: 500;
            color: var(--text-dark);
        }

        .date-small {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* Action buttons */
        .action-group {
            display: flex;
            gap: 6px;
        }

        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            border: none;
            transition: all 0.2s ease;
            white-space: nowrap;
            cursor: pointer;
        }

        .btn-approve {
            background: linear-gradient(135deg, var(--success) 0%, #1b5e20 100%);
            color: var(--white);
        }

        .btn-approve:hover {
            background: linear-gradient(135deg, #1b5e20 0%, #0a3622 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 125, 50, 0.2);
        }

        .btn-reject {
            background: linear-gradient(135deg, var(--danger) 0%, #8b1e1e 100%);
            color: var(--white);
        }

        .btn-reject:hover {
            background: linear-gradient(135deg, #8b1e1e 0%, #58151c 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(198, 40, 40, 0.2);
        }

        .btn-disabled {
            background: #e9ecef;
            color: #6c757d;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }

        /* Empty state */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
            color: var(--text-muted);
        }

        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .empty-text {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            display: none;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border-light);
            border-radius: 50%;
            border-top-color: var(--deep-navy);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Print styles */
        @media print {
            .brand-payroll-header,
            .filters-section,
            .action-group,
            .btn-export {
                display: none !important;
            }
            
            .main-container {
                height: auto;
                padding: 0;
            }
            
            .table-card {
                box-shadow: none;
            }
            
            th {
                background: #f8fafc !important;
                color: black !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .status-badge {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-container {
                padding: 0 16px;
            }
            
            .logo-main {
                font-size: 1.5rem;
            }
            
            .logo-main::after {
                right: -25px;
                font-size: 1.3rem;
            }
            
            .logo-subtitle {
                font-size: 1rem;
                padding-left: 12px;
            }
            
            .stats-container {
                gap: 8px;
            }
            
            .stat-card {
                padding: 8px 12px;
            }
            
            .stat-icon {
                width: 32px;
                height: 32px;
                font-size: 16px;
            }
            
            .stat-info h3 {
                font-size: 16px;
            }
            
            .stat-info p {
                font-size: 10px;
            }
            
            .filter-buttons {
                width: 100%;
            }
            
            .btn-export {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>

<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<!-- Company header -->
<div class="brand-payroll-header">
    <div class="header-container">
        <div class="logo-container">
            <div class="logo-main"><?= htmlspecialchars($companyBrandName) ?></div>
            <div class="logo-subtitle">LEAVE REQUESTS</div>
        </div>
    </div>
</div>

<div class="main-container">
    
    <!-- Page Header with Stats -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="bi bi-calendar-check"></i>
            Leave Requests Management
        </h1>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-file-text"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $totalRequests ?></h3>
                    <p>Total</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $pendingCount ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $approvedCount ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $rejectedCount ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <?php if ($success): ?>
    <div class="alert alert-success" id="successAlert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger" id="errorAlert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    
    <!-- Filters Section -->
    <div class="filters-section">
        <div class="filter-buttons">
            <a href="?filter=all" class="filter-btn <?= ($filter === 'all') ? 'active' : '' ?>">
                <i class="bi bi-files"></i> All
            </a>
            <a href="?filter=today" class="filter-btn <?= ($filter === 'today') ? 'active' : '' ?>">
                <i class="bi bi-calendar-day"></i> Today
            </a>
            <a href="?filter=week" class="filter-btn <?= ($filter === 'week') ? 'active' : '' ?>">
                <i class="bi bi-calendar-week"></i> This Week
            </a>
            <a href="?filter=month" class="filter-btn <?= ($filter === 'month') ? 'active' : '' ?>">
                <i class="bi bi-calendar-month"></i> This Month
            </a>
        </div>
        
        <button class="btn-export" onclick="window.print()">
            <i class="bi bi-printer"></i> Export / Print
        </button>
    </div>
    
    <!-- Leave Requests Table -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table" id="leaveTable">
                <thead>
                    <tr>
                        <th class="sortable" onclick="sortTable(0)" style="min-width: 250px;">Staff</th>
                        <th class="sortable" onclick="sortTable(1)" style="min-width: 120px;">Date(s)</th>
                        <th style="min-width: 250px;">Reason</th>
                        <th class="sortable" onclick="sortTable(3)" style="min-width: 150px;">Requested At</th>
                        <th class="sortable" onclick="sortTable(4)" style="min-width: 120px;">Status</th>
                        <th style="min-width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($totalRequests == 0): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-icon">🏖️</div>
                                <div class="empty-title">No Leave Requests Found</div>
                                <div class="empty-text">Leave requests will appear here once submitted by staff</div>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr data-status="<?= $row['status'] ?>">
                            <td>
                                <div class="employee-info">
                                    <?php if (!empty($row['profile_photo'])): ?>
                                    <div class="profile-img-container">
                                        <img src="<?= htmlspecialchars(pcvc_profile_photo_url($row['profile_photo'] ?? '')) ?>" 
                                             alt="Profile" class="profile-img">
                                    </div>
                                    <?php else: ?>
                                    <div class="profile-placeholder">
                                        <?= strtoupper(substr($row['full_name'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="employee-details">
                                        <span class="employee-name"><?= htmlspecialchars($row['full_name'] ?? 'N/A') ?></span>
                                        <span class="employee-position"><?= htmlspecialchars($row['position'] ?? 'No position') ?></span>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <span class="date-badge">
                                    <i class="bi bi-calendar3"></i>
                                    <?= htmlspecialchars($row['leave_date']) ?>
                                </span>
                            </td>
                            
                            <td class="reason-cell" title="<?= htmlspecialchars($row['reason']) ?>">
                                <?= htmlspecialchars($row['reason']) ?>
                            </td>
                            
                            <td>
                                <div class="date-display">
                                    <span class="date-main"><?= date('M d, Y', strtotime($row['requested_at'])) ?></span>
                                    <span class="date-small"><?= date('H:i', strtotime($row['requested_at'])) ?></span>
                                </div>
                            </td>
                            
                            <td>
                                <span class="status-badge status-<?= $row['status'] ?>">
                                    <?= strtoupper($row['status']) ?>
                                </span>
                            </td>
                            
                            <td>
                                <div class="action-group">
                                    <?php if ($row['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmAction('approve')">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-action btn-approve">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmAction('reject')">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn-action btn-reject">
                                            <i class="bi bi-x-lg"></i> Reject
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="btn-disabled">
                                        <i class="bi bi-lock"></i> Processed
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Confirmation dialogs
function confirmAction(action) {
    if (action === 'approve') {
        return confirm('✅ Approve this leave request?');
    } else {
        return confirm('❌ Reject this leave request?');
    }
}

// Sort table
let sortDirections = {};

function sortTable(columnIndex) {
    let table = document.getElementById('leaveTable');
    let tbody = table.querySelector('tbody');
    let rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
    
    if (rows.length === 0) return;
    
    // Toggle sort direction
    sortDirections[columnIndex] = !sortDirections[columnIndex];
    
    rows.sort((a, b) => {
        let aText, bText;
        
        // Handle different column types
        if (columnIndex === 0) {
            // Employee name
            aText = a.querySelector('.employee-name').textContent.trim().toLowerCase();
            bText = b.querySelector('.employee-name').textContent.trim().toLowerCase();
        } else if (columnIndex === 1) {
            // Date (leave date)
            aText = a.cells[1].textContent.trim();
            bText = b.cells[1].textContent.trim();
        } else if (columnIndex === 3) {
            // Requested date
            let aDate = a.querySelector('.date-main')?.textContent.trim() || '';
            let bDate = b.querySelector('.date-main')?.textContent.trim() || '';
            aText = aDate ? new Date(aDate).getTime() : 0;
            bText = bDate ? new Date(bDate).getTime() : 0;
            return sortDirections[columnIndex] ? aText - bText : bText - aText;
        } else if (columnIndex === 4) {
            // Status
            aText = a.querySelector('.status-badge').textContent.trim().toLowerCase();
            bText = b.querySelector('.status-badge').textContent.trim().toLowerCase();
            
            // Custom status order: pending, approved, rejected
            const statusOrder = { 'pending': 1, 'approved': 2, 'rejected': 3 };
            aText = statusOrder[aText] || 0;
            bText = statusOrder[bText] || 0;
            return sortDirections[columnIndex] ? aText - bText : bText - aText;
        } else {
            aText = a.cells[columnIndex]?.textContent.trim().toLowerCase() || '';
            bText = b.cells[columnIndex]?.textContent.trim().toLowerCase() || '';
        }
        
        if (sortDirections[columnIndex]) {
            return aText.localeCompare(bText);
        } else {
            return bText.localeCompare(aText);
        }
    });
    
    // Reorder rows
    rows.forEach(row => tbody.appendChild(row));
}

// Auto-hide alerts
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Show loading on form submit
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        if (this.querySelector('button[type="submit"]')) {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
    });
});

// Keyboard shortcut
document.addEventListener('keydown', function(e) {
    // Alt + 1-4 for filters
    if (e.altKey) {
        switch(e.key) {
            case '1': window.location.href = '?filter=all'; break;
            case '2': window.location.href = '?filter=today'; break;
            case '3': window.location.href = '?filter=week'; break;
            case '4': window.location.href = '?filter=month'; break;
        }
    }
});
</script>

</body>
</html>