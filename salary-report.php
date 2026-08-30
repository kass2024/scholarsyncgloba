<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/company_branding.php';
require_once __DIR__ . '/includes/payroll_helpers.php';
require_once __DIR__ . '/helpers/secure_file.php';
$companyBrandName = PCVC_COMPANY_DISPLAY_NAME;
/* ===========================================================
   PERMISSION CHECK
=========================================================== */
if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    exit("Unauthorized access.");
}

$isSuperAdmin = ($_SESSION['role'] === "superadmin" || $_SESSION['role'] === "hr");

if (!$isSuperAdmin) {
    http_response_code(403);
    exit("You do not have permission to view salary requests.");
}

/* ===========================================================
   GET ALL STAFF FOR DROPDOWN
=========================================================== */
$staff_query = "SELECT id, full_name, position, profile_photo FROM admins WHERE role = 'staff' OR role = 'agent' ORDER BY full_name";
$staff_result = $conn->query($staff_query);
$staff_list = [];
while ($staff = $staff_result->fetch_assoc()) {
    $staff_list[] = $staff;
}

/* ===========================================================
   FILTERS & SEARCH - ENHANCED WITH MONTH FILTER
=========================================================== */
$filter_status = $_GET['status'] ?? '';
$filter_month = $_GET['month'] ?? '';
$search = $_GET['search'] ?? '';

// Debug: Log the filter values
error_log("Filter Status: " . $filter_status);
error_log("Filter Month: " . $filter_month);
error_log("Search: " . $search);

// Get unique months from salary_requests for filter dropdown
$months_query = "SELECT DISTINCT month FROM salary_requests ORDER BY month DESC";
$months_result = $conn->query($months_query);
$available_months = [];
while ($month_row = $months_result->fetch_assoc()) {
    $available_months[] = $month_row['month'];
}
$available_months = pcvc_payroll_month_range($available_months);

// Debug: Log available months
error_log("Available months: " . print_r($available_months, true));

// Build the query
$query = "
    SELECT sr.*, ad.full_name, ad.profile_photo, ad.position
    FROM salary_requests sr
    INNER JOIN admins ad ON sr.admin_id = ad.id
    WHERE 1=1
";

$params = [];
$types = "";

/* SEARCH FILTER */
if (!empty($search)) {
    $query .= " AND (ad.full_name LIKE ? OR sr.month LIKE ? OR sr.payment_method LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "sss";
}

/* STATUS FILTER */
if (!empty($filter_status)) {
    $query .= " AND sr.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

/* MONTH FILTER */
if (!empty($filter_month)) {
    $query .= " AND sr.month = ?";
    $params[] = $filter_month;
    $types .= "s";
}

$query .= " ORDER BY 
    CASE sr.status
        WHEN 'pending' THEN 1
        WHEN 'approved' THEN 2
        ELSE 3
    END,
    sr.requested_at DESC";

// Debug: Log the final query
error_log("Final Query: " . $query);
error_log("Params: " . print_r($params, true));

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Debug: Log number of rows returned
error_log("Number of rows returned: " . $result->num_rows);

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ScholarSync Global Visa — Salary Requests</title>
    
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
    
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
            content: '💰';
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

        /* ===== SEARCH & FILTERS ===== */
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
            min-width: 300px;
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

        #searchBox {
            width: 100%;
            padding: 10px 16px 10px 42px;
            border: 2px solid var(--border-light);
            border-radius: 8px;
            font-size: 14px;
        }

        #searchBox:focus {
            outline: none;
            border-color: var(--gold);
        }

        .filter-select {
            min-width: 150px;
            padding: 10px 12px;
            border: 2px solid var(--border-light);
            border-radius: 8px;
            font-size: 14px;
            background: var(--white);
            cursor: pointer;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--gold);
        }

        /* Month Picker Styles */
        .month-picker-container {
            position: relative;
            min-width: 180px;
        }

        .month-picker-input {
            width: 100%;
            padding: 10px 12px 10px 38px;
            border: 2px solid var(--border-light);
            border-radius: 8px;
            font-size: 14px;
            background: var(--white);
            cursor: pointer;
        }

        .month-picker-input:focus {
            outline: none;
            border-color: var(--gold);
        }

        .month-picker-input::placeholder {
            color: var(--text-muted);
            opacity: 0.7;
        }

        .month-picker-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gold);
            font-size: 16px;
            pointer-events: none;
        }

        .month-clear-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 18px;
            cursor: pointer;
            display: <?= !empty($filter_month) ? 'block' : 'none' ?>;
            padding: 0 4px;
            z-index: 10;
        }

        .month-clear-btn:hover {
            color: var(--danger);
        }

        /* Simple Month Dropdown */
        .month-dropdown {
            min-width: 180px;
            padding: 10px 12px;
            border: 2px solid var(--border-light);
            border-radius: 8px;
            font-size: 14px;
            background: var(--white);
            cursor: pointer;
        }

        .month-dropdown:focus {
            outline: none;
            border-color: var(--gold);
        }

        .btn-filter {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-filter:hover {
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--deep-navy) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(1, 47, 107, 0.2);
        }

        .btn-reset {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: var(--white);
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-reset:hover {
            background: linear-gradient(135deg, #5a6268 0%, #494f54 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);
        }

        .btn-export {
            background: linear-gradient(135deg, var(--success) 0%, #1b5e20 100%);
            color: var(--white);
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-export:hover {
            background: linear-gradient(135deg, #1b5e20 0%, #0a3622 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 125, 50, 0.2);
        }

        .btn-add-salary {
            background: linear-gradient(135deg, var(--gold) 0%, #e0913e 100%);
            color: var(--deep-navy);
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-add-salary:hover {
            background: linear-gradient(135deg, #e0913e 0%, #c47d32 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(242, 166, 90, 0.3);
            color: var(--deep-navy);
        }

        /* Active filter badge */
        .active-filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            flex-shrink: 0;
        }

        .filter-badge {
            background: linear-gradient(135deg, var(--gold) 0%, #e0913e 100%);
            color: var(--deep-navy);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .filter-badge i {
            cursor: pointer;
            font-size: 14px;
        }

        .filter-badge i:hover {
            color: var(--danger);
        }

        /* ===== MODAL STYLES ===== */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 40px rgba(1, 47, 107, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            border-bottom: 3px solid var(--gold);
            border-radius: 12px 12px 0 0;
            padding: 16px 24px;
        }

        .modal-title {
            font-weight: 700;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-title i {
            color: var(--gold);
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            border-top: 1px solid var(--border-light);
            padding: 16px 24px;
        }

        .form-label {
            font-weight: 600;
            color: var(--deep-navy);
            font-size: 0.9rem;
            margin-bottom: 6px;
        }

        .form-control, .form-select {
            border: 2px solid var(--border-light);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 0.2rem rgba(242, 166, 90, 0.25);
            outline: none;
        }

        .payment-fields {
            background: var(--light-bg);
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
            border: 1px solid var(--border-light);
        }

        .payment-fields h6 {
            color: var(--deep-navy);
            font-weight: 700;
            margin-bottom: 16px;
            font-size: 1rem;
        }

        .btn-save {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-save:hover {
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--deep-navy) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(1, 47, 107, 0.2);
        }

        .btn-cancel {
            background: #e9ecef;
            color: var(--text-dark);
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-cancel:hover {
            background: #dee2e6;
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

        /* Month badge */
        .month-badge {
            background: var(--light-bg);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            color: var(--deep-navy);
            border-left: 3px solid var(--gold);
        }

        /* Amount display */
        .amount-display {
            font-weight: 700;
            color: var(--deep-navy);
        }

        /* Payment method details */
        .method-box {
            background: var(--light-bg);
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 11px;
            line-height: 1.4;
            min-width: 180px;
        }

        .method-box strong {
            color: var(--deep-navy);
            display: block;
            margin-bottom: 4px;
        }

        .method-box i {
            color: var(--gold);
            margin-right: 4px;
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
            justify-content: flex-end;
        }

        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            border: none;
            transition: all 0.2s ease;
            white-space: nowrap;
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
            cursor: not-allowed;
            opacity: 0.6;
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
            .btn-export,
            .btn-filter,
            .btn-reset,
            .btn-add-salary,
            .month-picker-container {
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
            
            .filters-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-wrapper {
                min-width: 100%;
            }
            
            .month-picker-container {
                min-width: 100%;
            }
            
            .month-dropdown {
                min-width: 100%;
            }
        }
        
        /* Flatpickr month select customization */
        .flatpickr-monthSelect-month {
            border-radius: 4px;
        }
        
        .flatpickr-monthSelect-month:hover {
            background: var(--gold) !important;
            color: var(--deep-navy) !important;
        }
        
        .flatpickr-monthSelect-theme-light .flatpickr-monthSelect-month.selected {
            background: var(--deep-navy) !important;
            color: white !important;
        }
        
        /* Debug info (remove in production) */
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 10px;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
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
            <div class="logo-subtitle">Salary requests</div>
        </div>
    </div>
</div>

<div class="main-container">
    
    <!-- Page Header with Stats -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="bi bi-cash-stack"></i>
            Salary Requests Report
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
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success" id="successAlert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?= $_SESSION['success']; ?>
    </div>
    <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger" id="errorAlert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?= $_SESSION['error']; ?>
    </div>
    <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <!-- Debug Info (Remove in production) -->
    <?php if (isset($_GET['debug'])): ?>
    <div class="debug-info">
        <strong>Debug Info:</strong><br>
        Filter Month: <?= htmlspecialchars($filter_month) ?><br>
        Available Months: <?= implode(', ', $available_months) ?><br>
        Total Rows: <?= $totalRequests ?><br>
        SQL Query: <?= htmlspecialchars($query) ?><br>
        Parameters: <?= htmlspecialchars(print_r($params, true)) ?>
    </div>
    <?php endif; ?>
    
    <!-- Search and Filters -->
    <div class="filters-section">
        <div class="search-wrapper">
            <i class="bi bi-search"></i>
            <input type="text" id="searchBox" class="form-control" 
                   placeholder="Search by staff, month, payment method..."
                   value="<?= htmlspecialchars($search); ?>">
        </div>
        
        <select id="statusFilter" class="filter-select">
            <option value="">All Status</option>
            <option value="pending" <?= $filter_status == "pending" ? "selected" : "" ?>>Pending</option>
            <option value="approved" <?= $filter_status == "approved" ? "selected" : "" ?>>Approved</option>
            <option value="rejected" <?= $filter_status == "rejected" ? "selected" : "" ?>>Rejected</option>
        </select>
        
        <!-- Simple Month Dropdown (More reliable) -->
        <div class="month-picker-container">
            <i class="bi bi-calendar3 month-picker-icon"></i>
            <select id="monthDropdown" class="month-dropdown">
                <option value="">All Months</option>
                <?php foreach ($available_months as $month): ?>
                <option value="<?= htmlspecialchars($month) ?>" <?= $filter_month == $month ? 'selected' : '' ?>>
                    <?= date('F Y', strtotime($month . '-01')) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($filter_month)): ?>
            <button type="button" class="month-clear-btn" onclick="clearMonthFilter()" title="Clear month filter">
                <i class="bi bi-x-circle-fill"></i>
            </button>
            <?php endif; ?>
        </div>
        
        <button class="btn-filter" onclick="applyFilters()">
            <i class="bi bi-funnel"></i> Apply Filters
        </button>
        
        <button class="btn-reset" onclick="resetFilters()">
            <i class="bi bi-arrow-counterclockwise"></i> Reset
        </button>
        
        <button class="btn-export" onclick="window.print()">
            <i class="bi bi-printer"></i> Export / Print
        </button>
        
        <button class="btn-add-salary" data-bs-toggle="modal" data-bs-target="#addSalaryModal">
            <i class="bi bi-plus-circle"></i> Add Salary Request
        </button>
    </div>
    
    <!-- Active Filters Display -->
    <?php if (!empty($filter_status) || !empty($filter_month) || !empty($search)): ?>
    <div class="active-filters">
        <?php if (!empty($search)): ?>
        <span class="filter-badge">
            <i class="bi bi-search"></i> "<?= htmlspecialchars($search) ?>"
            <i class="bi bi-x-lg" onclick="removeFilter('search')"></i>
        </span>
        <?php endif; ?>
        
        <?php if (!empty($filter_status)): ?>
        <span class="filter-badge">
            <i class="bi bi-tag"></i> Status: <?= ucfirst($filter_status) ?>
            <i class="bi bi-x-lg" onclick="removeFilter('status')"></i>
        </span>
        <?php endif; ?>
        
        <?php if (!empty($filter_month)): ?>
        <span class="filter-badge">
            <i class="bi bi-calendar3"></i> Month: <?= date('F Y', strtotime($filter_month . '-01')) ?>
            <i class="bi bi-x-lg" onclick="removeFilter('month')"></i>
        </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Requests Table -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table" id="requestsTable">
                <thead>
                    <tr>
                        <th class="sortable" onclick="sortTable(0)" style="min-width: 60px;">#</th>
                        <th class="sortable" onclick="sortTable(1)" style="min-width: 250px;">Staff</th>
                        <th class="sortable" onclick="sortTable(2)" style="min-width: 100px;">Month</th>
                        <th class="sortable" onclick="sortTable(3)" style="min-width: 150px;">Amount (RWF)</th>
                        <th style="min-width: 250px;">Payment Method</th>
                        <th class="sortable" onclick="sortTable(5)" style="min-width: 100px;">Status</th>
                        <th class="sortable" onclick="sortTable(6)" style="min-width: 150px;">Requested At</th>
                        <th style="min-width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows == 0): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-icon">💰</div>
                                <div class="empty-title">No Salary Requests Found</div>
                                <div class="empty-text">
                                    Click "Add Salary Request" to create a new salary record
                                    <?php if (!empty($filter_month)): ?>
                                    <br><small>Current filter: Month = <?= date('F Y', strtotime($filter_month . '-01')) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $i = 1;
                        while ($row = $result->fetch_assoc()): 
                        ?>
                        <tr data-status="<?= $row['status'] ?>">
                            <td class="fw-bold"><?= $i++ ?></td>
                            
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
                                <span class="month-badge">
                                    <i class="bi bi-calendar3"></i>
                                    <?= date('M Y', strtotime($row['month'] . '-01')) ?>
                                </span>
                            </td>
                            
                            <td class="amount-display">
                                <?= number_format($row['total_salary_rwf']) ?> RWF
                            </td>
                            
                            <td>
                                <?php if ($row['payment_method'] === "bank"): ?>
                                <div class="method-box">
                                    <strong><i class="bi bi-bank"></i> BANK TRANSFER</strong>
                                    <div><i class="bi bi-building"></i> <?= htmlspecialchars($row['bank_name']) ?></div>
                                    <div><i class="bi bi-credit-card"></i> <?= htmlspecialchars($row['bank_account']) ?></div>
                                    <div><i class="bi bi-person"></i> <?= htmlspecialchars($row['bank_registered_names']) ?></div>
                                </div>

                                <?php elseif ($row['payment_method'] === "momo"): ?>
                                <div class="method-box">
                                    <strong><i class="bi bi-phone"></i> MOMO</strong>
                                    <div><i class="bi bi-telephone"></i> <?= htmlspecialchars($row['momo_number']) ?></div>
                                    <div><i class="bi bi-person"></i> <?= htmlspecialchars($row['momo_registered_names']) ?></div>
                                </div>

                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <span class="status-badge status-<?= $row['status'] ?>">
                                    <?= strtoupper($row['status']) ?>
                                </span>
                            </td>
                            
                            <td>
                                <div class="date-display">
                                    <span class="date-main"><?= date('M d, Y', strtotime($row['requested_at'])) ?></span>
                                    <span class="date-small"><?= date('H:i', strtotime($row['requested_at'])) ?></span>
                                </div>
                            </td>
                            
                            <td>
                                <div class="action-group">
                                    <?php if ($row['status'] === "pending"): ?>
                                    <a href="update-salary-status.php?id=<?= $row['id']; ?>&status=approved" 
                                       class="btn-action btn-approve"
                                       onclick="return confirmApprove()">
                                        <i class="bi bi-check-lg"></i> Approve
                                    </a>
                                    <a href="update-salary-status.php?id=<?= $row['id']; ?>&status=rejected" 
                                       class="btn-action btn-reject"
                                       onclick="return confirmReject()">
                                        <i class="bi bi-x-lg"></i> Reject
                                    </a>
                                    <?php else: ?>
                                    <span class="btn-disabled">
                                        <i class="bi bi-lock"></i> No Actions
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

<!-- Add Salary Request Modal -->
<div class="modal fade" id="addSalaryModal" tabindex="-1" aria-labelledby="addSalaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSalaryModalLabel">
                    <i class="bi bi-plus-circle"></i> Add New Salary Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process-salary-request.php" method="POST" onsubmit="return validateSalaryForm()">
                <div class="modal-body">
                    <!-- Staff Selection -->
                    <div class="mb-3">
                        <label for="admin_id" class="form-label">Select Staff <span class="text-danger">*</span></label>
                        <select class="form-select" id="admin_id" name="admin_id" required>
                            <option value="">-- Choose Staff Member --</option>
                            <?php foreach ($staff_list as $staff): ?>
                                <option value="<?= $staff['id'] ?>">
                                    <?= htmlspecialchars($staff['full_name']) ?> 
                                    <?= !empty($staff['position']) ? ' - ' . htmlspecialchars($staff['position']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Month and Amount -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="month" class="form-label">Month <span class="text-danger">*</span></label>
                            <input type="month" class="form-control" id="month" name="month" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="salary_rwf" class="form-label">Amount (RWF) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="salary_rwf" name="salary_rwf" 
                                   step="0.01" min="0" placeholder="0.00" required>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="mb-3">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" 
                                       id="payment_bank" value="bank" checked onclick="togglePaymentFields()">
                                <label class="form-check-label" for="payment_bank">
                                    <i class="bi bi-bank"></i> Bank Transfer
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" 
                                       id="payment_momo" value="momo" onclick="togglePaymentFields()">
                                <label class="form-check-label" for="payment_momo">
                                    <i class="bi bi-phone"></i> MoMo
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Fields -->
                    <div id="bank_fields" class="payment-fields">
                        <h6><i class="bi bi-bank"></i> Bank Account Details</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="bank_name" class="form-label">Bank Name</label>
                                <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                       placeholder="e.g., Bank of Kigali">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="bank_account" class="form-label">Account Number</label>
                                <input type="text" class="form-control" id="bank_account" name="bank_account" 
                                       placeholder="e.g., 0001234567">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="bank_registered_names" class="form-label">Account Holder Name</label>
                                <input type="text" class="form-control" id="bank_registered_names" 
                                       name="bank_registered_names" placeholder="Full name on account">
                            </div>
                        </div>
                    </div>

                    <!-- MoMo Fields -->
                    <div id="momo_fields" class="payment-fields" style="display: none;">
                        <h6><i class="bi bi-phone"></i> Mobile Money Details</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="momo_number" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="momo_number" name="momo_number" 
                                       placeholder="e.g., 0788XXXXXX">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="momo_registered_names" class="form-label">Registered Names</label>
                                <input type="text" class="form-control" id="momo_registered_names" 
                                       name="momo_registered_names" placeholder="Full name on MoMo account">
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> 
                        Default status will be <strong>Pending</strong>. You can approve or reject after creation.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                    <button type="submit" class="btn-save">
                        <i class="bi bi-check-lg"></i> Save Salary Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle payment fields
function togglePaymentFields() {
    let bankFields = document.getElementById('bank_fields');
    let momoFields = document.getElementById('momo_fields');
    let bankRadio = document.getElementById('payment_bank');
    let momoRadio = document.getElementById('payment_momo');
    
    if (bankRadio.checked) {
        bankFields.style.display = 'block';
        momoFields.style.display = 'none';
        
        // Make bank fields required
        document.getElementById('bank_name').required = true;
        document.getElementById('bank_account').required = true;
        document.getElementById('bank_registered_names').required = true;
        
        // Remove required from momo fields
        document.getElementById('momo_number').required = false;
        document.getElementById('momo_registered_names').required = false;
        
    } else if (momoRadio.checked) {
        bankFields.style.display = 'none';
        momoFields.style.display = 'block';
        
        // Make momo fields required
        document.getElementById('momo_number').required = true;
        document.getElementById('momo_registered_names').required = true;
        
        // Remove required from bank fields
        document.getElementById('bank_name').required = false;
        document.getElementById('bank_account').required = false;
        document.getElementById('bank_registered_names').required = false;
    }
}

// Validate form before submission
function validateSalaryForm() {
    let admin_id = document.getElementById('admin_id').value;
    let month = document.getElementById('month').value;
    let salary = document.getElementById('salary_rwf').value;
    let bankRadio = document.getElementById('payment_bank').checked;
    let momoRadio = document.getElementById('payment_momo').checked;
    
    if (!admin_id) {
        alert('Please select a staff member');
        return false;
    }
    
    if (!month) {
        alert('Please select a month');
        return false;
    }
    
    if (!salary || salary <= 0) {
        alert('Please enter a valid salary amount');
        return false;
    }
    
    if (bankRadio) {
        let bankName = document.getElementById('bank_name').value;
        let bankAccount = document.getElementById('bank_account').value;
        let bankNames = document.getElementById('bank_registered_names').value;
        
        if (!bankName || !bankAccount || !bankNames) {
            alert('Please fill all bank account fields');
            return false;
        }
    } else if (momoRadio) {
        let momoNumber = document.getElementById('momo_number').value;
        let momoNames = document.getElementById('momo_registered_names').value;
        
        if (!momoNumber || !momoNames) {
            alert('Please fill all MoMo fields');
            return false;
        }
    }
    
    document.getElementById('loadingOverlay').style.display = 'flex';
    return true;
}

// Apply filters
function applyFilters() {
    let search = document.getElementById('searchBox').value;
    let status = document.getElementById('statusFilter').value;
    let month = document.getElementById('monthDropdown').value;
    
    let params = new URLSearchParams();
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (month) params.append('month', month);
    
    window.location.href = window.location.pathname + '?' + params.toString();
}

// Reset all filters
function resetFilters() {
    window.location.href = window.location.pathname;
}

// Clear month filter
function clearMonthFilter() {
    document.getElementById('monthDropdown').value = '';
    applyFilters();
}

// Remove specific filter
function removeFilter(filterType) {
    let params = new URLSearchParams(window.location.search);
    
    if (filterType === 'search') {
        params.delete('search');
    } else if (filterType === 'status') {
        params.delete('status');
    } else if (filterType === 'month') {
        params.delete('month');
    }
    
    window.location.href = window.location.pathname + '?' + params.toString();
}

// Real-time search
document.getElementById('searchBox').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        applyFilters();
    }
});

// Sort table
let sortDirections = {};

function sortTable(columnIndex) {
    let table = document.getElementById('requestsTable');
    let tbody = table.querySelector('tbody');
    let rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
    
    if (rows.length === 0) return;
    
    // Toggle sort direction
    sortDirections[columnIndex] = !sortDirections[columnIndex];
    
    rows.sort((a, b) => {
        let aText, bText;
        
        // Handle different column types
        if (columnIndex === 1) {
            // Employee name
            aText = a.querySelector('.employee-name').textContent.trim().toLowerCase();
            bText = b.querySelector('.employee-name').textContent.trim().toLowerCase();
        } else if (columnIndex === 2) {
            // Month
            aText = a.querySelector('.month-badge').textContent.trim().toLowerCase();
            bText = b.querySelector('.month-badge').textContent.trim().toLowerCase();
        } else if (columnIndex === 3) {
            // Amount (convert to number)
            aText = parseFloat(a.cells[3].textContent.replace(/[^0-9.-]+/g, '')) || 0;
            bText = parseFloat(b.cells[3].textContent.replace(/[^0-9.-]+/g, '')) || 0;
            return sortDirections[columnIndex] ? aText - bText : bText - aText;
        } else if (columnIndex === 5) {
            // Status
            aText = a.querySelector('.status-badge').textContent.trim().toLowerCase();
            bText = b.querySelector('.status-badge').textContent.trim().toLowerCase();
        } else if (columnIndex === 6) {
            // Date
            let aDate = a.querySelector('.date-main')?.textContent.trim() || '';
            let bDate = b.querySelector('.date-main')?.textContent.trim() || '';
            aText = aDate ? new Date(aDate).getTime() : 0;
            bText = bDate ? new Date(bDate).getTime() : 0;
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

// Confirmation dialogs
function confirmApprove() {
    return confirm('✅ Approve this salary request?');
}

function confirmReject() {
    return confirm('❌ Reject this salary request?');
}

// Auto-hide alerts
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Keyboard shortcut for search
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('searchBox').focus();
    }
});

// Show loading on action clicks
document.querySelectorAll('.btn-approve, .btn-reject').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    });
});

// Initialize payment fields on page load
document.addEventListener('DOMContentLoaded', function() {
    togglePaymentFields();
    
    // If URL has month parameter, ensure dropdown shows it
    const urlParams = new URLSearchParams(window.location.search);
    const monthParam = urlParams.get('month');
    if (monthParam) {
        const dropdown = document.getElementById('monthDropdown');
        if (dropdown) {
            dropdown.value = monthParam;
        }
    }
});
</script>

</body>
</html>