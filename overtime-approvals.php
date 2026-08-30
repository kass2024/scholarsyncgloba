<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/company_branding.php';
require_once __DIR__ . '/helpers/secure_file.php';
$companyBrandName = PCVC_COMPANY_DISPLAY_NAME;

/* ===========================================================
   SECURITY CHECK
============================================================ */
if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    exit('Unauthorized access. Please log in as superadmin.');
}

$currentUserId = $_SESSION['id'];

/* ===========================================================
   FETCH DATA WITH STATS
============================================================ */
$sql = "
SELECT 
    o.id AS request_id,
    o.request_date,
    o.reason,
    o.expected_hours,
    o.status AS request_status,
    o.created_at,

    a.id AS staff_id,
    a.full_name AS staff_name,
    a.salary_per_minute,
    a.profile_photo,
    a.position,

    sess.total_minutes,
    sess.session_status,

    p.id AS payment_id,
    p.amount AS paid_amount,
    p.currency AS paid_currency,
    p.paid_at

FROM overtime_requests o
JOIN admins a ON a.id = o.staff_id
LEFT JOIN (
    SELECT request_id,
           COALESCE(SUM(total_minutes), 0) AS total_minutes,
           MAX(status) AS session_status
    FROM overtime_sessions
    GROUP BY request_id
) sess ON sess.request_id = o.id
LEFT JOIN overtime_payments p ON p.overtime_request_id = o.id
ORDER BY 
    CASE o.status
        WHEN 'pending' THEN 1
        WHEN 'approved' THEN 2
        WHEN 'completed' THEN 3
        ELSE 4
    END,
    o.created_at DESC
";

$result = $conn->query($sql);

// Calculate stats
$totalRequests = 0;
$pendingCount = 0;
$approvedCount = 0;
$completedCount = 0;
$rejectedCount = 0;
$paidCount = 0;
$totalPaidAmount = 0;

$requests = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
        $totalRequests++;
        
        switch ($row['request_status']) {
            case 'pending': $pendingCount++; break;
            case 'approved': $approvedCount++; break;
            case 'completed': $completedCount++; break;
            case 'rejected': $rejectedCount++; break;
        }
        
        if ($row['payment_id']) {
            $paidCount++;
            $totalPaidAmount += floatval($row['paid_amount'] ?? 0);
        }
    }
}

$totalCalculatedOvertime = 0;
foreach ($requests as $r) {
    $m = (int) ($r['total_minutes'] ?? 0);
    $rate = (float) ($r['salary_per_minute'] ?? 0);
    $totalCalculatedOvertime += (int) round($m * $rate);
}

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
    <title>ScholarSync Global Visa — Overtime & Payments</title>
    
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
            content: '⏰';
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
            flex-wrap: wrap;
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

        .search-wrapper {
            flex: 2;
            min-width: 250px;
            position: relative;
        }

        .search-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 16px;
        }

        #searchInput {
            width: 100%;
            padding: 10px 16px 10px 42px;
            border: 2px solid var(--border-light);
            border-radius: 8px;
            font-size: 14px;
        }

        #searchInput:focus {
            outline: none;
            border-color: var(--gold);
        }

        .filter-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid var(--border-light);
            background: var(--light-bg);
            color: var(--text-muted);
            transition: all 0.2s ease;
            cursor: pointer;
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

        /* ===== OVERTIME CARDS CONTAINER ===== */
        .overtime-container {
            flex: 1;
            overflow-y: auto;
            padding-right: 4px;
        }

        /* ===== OVERTIME CARD ===== */
        .overtime-card {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border-light);
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(1, 47, 107, 0.05);
            transition: all 0.2s ease;
        }

        .overtime-card:hover {
            box-shadow: 0 4px 12px rgba(1, 47, 107, 0.15);
            transform: translateY(-2px);
        }

        .overtime-card.hidden {
            display: none;
        }

        .card-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 2px solid var(--gold);
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .profile-img-container {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--gold);
        }

        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-placeholder {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-blue) 0%, var(--deep-navy) 100%);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 18px;
        }

        .employee-details {
            display: flex;
            flex-direction: column;
        }

        .employee-name {
            font-weight: 700;
            color: var(--deep-navy);
            font-size: 16px;
        }

        .employee-position {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .status-pending {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            color: #856404;
            border-left: 3px solid var(--warning);
        }

        .status-approved {
            background: linear-gradient(135deg, #cfe2ff 0%, #b6d4fe 100%);
            color: #052c65;
            border-left: 3px solid var(--info);
        }

        .status-completed {
            background: linear-gradient(135deg, #e2e3e5 0%, #d3d4d5 100%);
            color: #41464b;
            border-left: 3px solid var(--text-muted);
        }

        .status-rejected {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #58151c;
            border-left: 3px solid var(--danger);
        }

        .status-paid {
            background: linear-gradient(135deg, #d1e7dd 0%, #c3e6cb 100%);
            color: #0a3622;
            border-left: 3px solid var(--success);
        }

        .paid-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, var(--success) 0%, #1b5e20 100%);
            color: var(--white);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }

        .paid-badge i {
            font-size: 14px;
        }

        /* Card body */
        .card-body {
            padding: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .info-value i {
            color: var(--gold);
            margin-right: 6px;
        }

        .date-display {
            display: flex;
            flex-direction: column;
        }

        .date-main {
            font-weight: 600;
            color: var(--text-dark);
        }

        .date-small {
            font-size: 11px;
            color: var(--text-muted);
        }

        .amount-highlight {
            font-size: 18px;
            font-weight: 700;
            color: var(--deep-navy);
        }

        .reason-box {
            background: var(--light-bg);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            border-left: 3px solid var(--gold);
            font-size: 14px;
            line-height: 1.6;
            max-height: 100px;
            overflow-y: auto;
        }

        /* Action buttons */
        .action-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-approve {
            background: linear-gradient(135deg, var(--info) 0%, #026aa7 100%);
            color: var(--white);
        }

        .btn-approve:hover {
            background: linear-gradient(135deg, #026aa7 0%, #01497c 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(2, 106, 167, 0.2);
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

        .btn-pay {
            background: linear-gradient(135deg, var(--success) 0%, #1b5e20 100%);
            color: var(--white);
        }

        .btn-pay:hover {
            background: linear-gradient(135deg, #1b5e20 0%, #0a3622 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 125, 50, 0.2);
        }

        .btn-disabled {
            background: #e9ecef;
            color: #6c757d;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Empty state */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border-light);
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

        /* Payment modal */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 40px rgba(1, 47, 107, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            border-radius: 16px 16px 0 0;
            padding: 16px 20px;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 16px 24px 24px;
            border-top: none;
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
            
            .overtime-card {
                break-inside: avoid;
                box-shadow: none;
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
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .action-group {
                width: 100%;
            }
            
            .btn-action {
                flex: 1;
                text-align: center;
                justify-content: center;
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
            <div class="logo-subtitle">Overtime &amp; payments</div>
        </div>
    </div>
</div>

<div class="main-container">
    
    <!-- Page Header with Stats -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="bi bi-clock-history"></i>
            Overtime Management
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
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $paidCount ?></h3>
                    <p>Paid</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($totalPaidAmount, 0) ?></h3>
                    <p>Total Paid</p>
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
        <div class="search-wrapper">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" placeholder="Search by staff name...">
        </div>
        
        <div class="filter-buttons">
            <button class="filter-btn active" onclick="filterDate('all')">All</button>
            <button class="filter-btn" onclick="filterDate('today')">Today</button>
            <button class="filter-btn" onclick="filterDate('week')">This Week</button>
            <button class="filter-btn" onclick="filterDate('month')">This Month</button>
        </div>
        
        <div class="d-flex align-items-center gap-2 flex-wrap" style="margin-left:auto;">
            <span class="badge rounded-pill bg-success-subtle text-dark border" style="font-size:0.95rem;padding:0.5rem 0.85rem;">
                <i class="bi bi-calculator me-1"></i>
                Calculated total: <?= number_format($totalCalculatedOvertime, 0) ?> RWF
            </span>
            <span class="badge rounded-pill bg-primary-subtle text-dark border" style="font-size:0.95rem;padding:0.5rem 0.85rem;">
                <i class="bi bi-cash-coin me-1"></i>
                Paid total: <?= number_format($totalPaidAmount, 0) ?> RWF
            </span>
            <button class="btn-export" type="button" onclick="window.print()">
                <i class="bi bi-printer"></i> Export / Print
            </button>
        </div>
    </div>
    
    <!-- Overtime Cards Container -->
    <div class="overtime-container" id="overtimeContainer">
        <?php if (empty($requests)): ?>
        <div class="empty-state">
            <div class="empty-icon">⏰</div>
            <div class="empty-title">No Overtime Requests Found</div>
            <div class="empty-text">Overtime requests will appear here once submitted by staff</div>
        </div>
        <?php else: ?>
            <?php foreach ($requests as $row):
                $status = $row['request_status'];
                $minutes = (int) ($row['total_minutes'] ?? 0);
                $rate = (float) ($row['salary_per_minute'] ?? 0);
                $calculatedAmount = (int) round($minutes * $rate);
                $isPaid = !empty($row['payment_id']);
                $displayAmount = $isPaid ? (float) ($row['paid_amount'] ?? 0) : $calculatedAmount;
            ?>
            <div class="overtime-card" 
                 data-name="<?= strtolower($row['staff_name'] ?? '') ?>"
                 data-date="<?= substr($row['created_at'] ?? '', 0, 10) ?>">
                
                <div class="card-header">
                    <div class="employee-info">
                        <?php if (!empty($row['profile_photo'])): ?>
                        <div class="profile-img-container">
                            <img src="<?= htmlspecialchars(pcvc_profile_photo_url($row['profile_photo'] ?? '')) ?>" 
                                 alt="Profile" class="profile-img">
                        </div>
                        <?php else: ?>
                        <div class="profile-placeholder">
                            <?= strtoupper(substr($row['staff_name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <?php endif; ?>
                        <div class="employee-details">
                            <span class="employee-name"><?= htmlspecialchars($row['staff_name'] ?? 'N/A') ?></span>
                            <span class="employee-position"><?= htmlspecialchars($row['position'] ?? 'No position') ?></span>
                        </div>
                    </div>
                    
                    <?php if ($isPaid): ?>
                    <div class="paid-badge">
                        <i class="bi bi-check-circle-fill"></i>
                        PAID (<?= $row['paid_currency'] ?? 'RWF' ?> <?= number_format($row['paid_amount'] ?? 0, 0) ?>)
                    </div>
                    <?php else: ?>
                    <div class="status-badge status-<?= $status ?>">
                        <?= strtoupper($status ?? 'PENDING') ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Request Date</span>
                            <span class="info-value">
                                <i class="bi bi-calendar3"></i>
                                <?= htmlspecialchars($row['request_date'] ?? 'N/A') ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Minutes Worked</span>
                            <span class="info-value">
                                <i class="bi bi-clock"></i>
                                <?= $minutes ?> minutes (<?= round($minutes/60, 1) ?> hours)
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Rate per Minute</span>
                            <span class="info-value">
                                <i class="bi bi-currency-dollar"></i>
                                <?= number_format($rate, 2) ?> RWF
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label"><?= $isPaid ? 'Recorded payment' : 'Calculated amount' ?></span>
                            <span class="amount-highlight">
                                <?= number_format($displayAmount, 0) ?> RWF
                            </span>
                        </div>
                        <?php if ($isPaid && (int) round($calculatedAmount) !== (int) round((float) ($row['paid_amount'] ?? 0))): ?>
                        <div class="info-item">
                            <span class="info-label">From minutes × rate</span>
                            <span class="info-value text-muted small"><?= number_format($calculatedAmount, 0) ?> RWF</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <span class="info-label">Submitted On</span>
                            <div class="date-display">
                                <span class="date-main"><?= date('M d, Y', strtotime($row['created_at'] ?? '')) ?></span>
                                <span class="date-small"><?= date('H:i', strtotime($row['created_at'] ?? '')) ?></span>
                            </div>
                        </div>
                        
                        <?php if ($row['paid_at']): ?>
                        <div class="info-item">
                            <span class="info-label">Paid On</span>
                            <div class="date-display">
                                <span class="date-main"><?= date('M d, Y', strtotime($row['paid_at'])) ?></span>
                                <span class="date-small"><?= date('H:i', strtotime($row['paid_at'])) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="reason-box">
                        <?= nl2br(htmlspecialchars($row['reason'] ?? 'No reason provided')) ?>
                    </div>
                    
                    <div class="action-group">
                        <?php if ($status === 'pending'): ?>
                        <button class="btn-action btn-approve" onclick="updateStatus(<?= $row['request_id'] ?>, 'approved')">
                            <i class="bi bi-check-lg"></i> Approve
                        </button>
                        <button class="btn-action btn-reject" onclick="updateStatus(<?= $row['request_id'] ?>, 'rejected')">
                            <i class="bi bi-x-lg"></i> Reject
                        </button>
                        
                        <?php elseif ($status === 'completed' && !$isPaid): ?>
                        <button class="btn-action btn-pay" onclick="openPayment(<?= $row['request_id'] ?>, <?= $calculatedAmount ?>)">
                            <i class="bi bi-cash"></i> Pay Overtime
                        </button>
                        
                        <?php elseif ($status === 'approved'): ?>
                        <span class="btn-disabled">
                            <i class="bi bi-hourglass"></i> Awaiting Completion
                        </span>
                        
                        <?php elseif ($status === 'rejected'): ?>
                        <span class="btn-disabled">
                            <i class="bi bi-x-circle"></i> Request Rejected
                        </span>
                        
                        <?php elseif ($isPaid): ?>
                        <span class="btn-disabled">
                            <i class="bi bi-check-circle"></i> Payment Completed
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-cash-stack me-2"></i>
                    Process Overtime Payment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="payRequestId">
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Amount (RWF)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">RWF</span>
                        <input type="number" id="payAmount" class="form-control" step="0.01" readonly>
                    </div>
                    <small class="text-muted">Amount is calculated based on minutes worked</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Payment Method</label>
                    <select id="payMethod" class="form-select">
                        <option value="bank">Bank Transfer</option>
                        <option value="momo">Mobile Money</option>
                        <option value="cash">Cash</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Reference / Notes</label>
                    <input type="text" id="payReference" class="form-control" 
                           placeholder="Transaction ID or notes">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitPayment()">
                    <i class="bi bi-check-circle"></i> Confirm Payment
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    let value = this.value.toLowerCase();
    let cards = document.querySelectorAll('.overtime-card');
    
    cards.forEach(card => {
        let name = card.dataset.name || '';
        card.classList.toggle('hidden', !name.includes(value));
    });
});

// Date filter
function filterDate(type) {
    // Update active filter button
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    let today = new Date();
    let cards = document.querySelectorAll('.overtime-card');
    
    cards.forEach(card => {
        let cardDate = new Date(card.dataset.date);
        let show = true;
        
        switch(type) {
            case 'today':
                show = cardDate.toDateString() === today.toDateString();
                break;
            case 'week':
                let weekAgo = new Date();
                weekAgo.setDate(today.getDate() - 7);
                show = cardDate >= weekAgo;
                break;
            case 'month':
                show = cardDate.getMonth() === today.getMonth() && 
                       cardDate.getFullYear() === today.getFullYear();
                break;
            default:
                show = true;
        }
        
        card.classList.toggle('hidden', !show);
    });
}

// Update status
async function updateStatus(id, status) {
    if (status === 'rejected' && !confirm('❌ Reject this overtime request?')) return;
    if (status === 'approved' && !confirm('✅ Approve this overtime request?')) return;
    
    document.getElementById('loadingOverlay').style.display = 'flex';
    
    try {
        const response = await fetch('update_overtime_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, status })
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
            document.getElementById('loadingOverlay').style.display = 'none';
        }
    } catch (error) {
        alert('Network error occurred');
        document.getElementById('loadingOverlay').style.display = 'none';
    }
}

// Open payment modal
let paymentModal;
document.addEventListener('DOMContentLoaded', function() {
    paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
});

function openPayment(id, amount) {
    document.getElementById('payRequestId').value = id;
    document.getElementById('payAmount').value = amount.toFixed(0);
    document.getElementById('payMethod').value = 'bank';
    document.getElementById('payReference').value = '';
    paymentModal.show();
}

// Submit payment
async function submitPayment() {
    const request_id = document.getElementById('payRequestId').value;
    const amount = document.getElementById('payAmount').value;
    const method = document.getElementById('payMethod').value;
    const reference = document.getElementById('payReference').value;
    
    if (!confirm(`💰 Confirm payment of ${amount} RWF?`)) return;
    
    document.getElementById('loadingOverlay').style.display = 'flex';
    paymentModal.hide();
    
    try {
        const response = await fetch('mark_overtime_paid.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                request_id, 
                amount, 
                currency: 'RWF',
                method,
                reference
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
            document.getElementById('loadingOverlay').style.display = 'none';
        }
    } catch (error) {
        alert('Network error occurred');
        document.getElementById('loadingOverlay').style.display = 'none';
    }
}

// Auto-hide alerts
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Alt + 1-4 for filters
    if (e.altKey) {
        switch(e.key) {
            case '1': filterDate('all'); break;
            case '2': filterDate('today'); break;
            case '3': filterDate('week'); break;
            case '4': filterDate('month'); break;
        }
    }
    
    // Ctrl+F for search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('searchInput').focus();
    }
});
</script>

</body>
</html>