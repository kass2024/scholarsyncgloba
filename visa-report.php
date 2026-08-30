<?php
require_once 'auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/job_application_status.php';
require_once __DIR__ . '/helpers/form17_application_status.php';

xander_ensure_form17_process_status_column($conn);

$sessionRole = isset($_SESSION['role']) ? trim((string) $_SESSION['role']) : '';
$dbRole = '';
$adminPk = 0;
if (!empty($_SESSION['admin_id'])) {
    $adminPk = (int) $_SESSION['admin_id'];
} elseif (!empty($_SESSION['id'])) {
    $adminPk = (int) $_SESSION['id'];
}
if ($adminPk > 0) {
    $stAd = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
    if ($stAd) {
        $stAd->bind_param('i', $adminPk);
        $stAd->execute();
        $rowAd = $stAd->get_result()->fetch_assoc();
        $stAd->close();
        if ($rowAd) {
            $dbRole = trim((string) ($rowAd['role'] ?? ''));
        }
    }
}
$canEditVisaProcess = xander_is_superadmin_role($dbRole) || xander_is_superadmin_role($sessionRole);
$canDeleteVisaApplication = $canEditVisaProcess;

$JOB_PROCESS_STATUSES = xander_job_application_process_statuses();
$JOB_PROCESS_ORDER = xander_job_application_status_keys_in_order();

// Initialize search + optional process filter (sidebar: Rejected, etc.)
$searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$processStatusFilter = isset($_GET['process_status']) ? trim((string) $_GET['process_status']) : '';
$visaProcessFilterActive = $processStatusFilter !== '' && xander_is_valid_job_process_status($processStatusFilter);
$visaFilterLabel = $visaProcessFilterActive ? ($JOB_PROCESS_STATUSES[$processStatusFilter] ?? $processStatusFilter) : '';

$whereParts = [];
$params = [];
$paramTypes = '';

if ($searchTerm !== '') {
    $searchTermLike = '%' . $searchTerm . '%';
    $whereParts[] = '(f.first_name LIKE ? OR f.last_name LIKE ? OR f.email LIKE ? OR f.passport_number LIKE ? OR f.user_id LIKE ?)';
    array_push($params, $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike);
    $paramTypes .= 'sssss';
}
if ($visaProcessFilterActive) {
    $whereParts[] = 'f.process_status = ?';
    $params[] = $processStatusFilter;
    $paramTypes .= 's';
}
$whereClause = $whereParts === [] ? '' : 'WHERE ' . implode(' AND ', $whereParts);

// Fetch visa applications
$query = "
SELECT f.*, r.name AS region_name, c.name AS country_name
FROM form_17_applications f
LEFT JOIN regions r ON f.region_id = r.id
LEFT JOIN countries c ON f.country_id = c.id
$whereClause
ORDER BY f.user_id DESC";

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
    'passport_copy' => 'Passport Copy',
    'academic_documents' => 'Academic Documents',
    'old_visa_copy' => 'Old Visa Copy',
    'passport_photo' => 'Passport Photo',
    'cv' => 'CV',
    'signature' => 'Signature'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visa Applications - ScholarSync Global</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        html {
            height: 100%;
            overflow: hidden;
        }

        body {
            background-color: <?= $colors['light_gray'] ?>;
            color: <?= $colors['navy'] ?>;
            height: 100%;
            max-height: 100%;
            min-height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .dashboard-container {
            padding: 20px;
            max-width: min(1600px, 98vw);
            margin: 0 auto;
            flex: 1;
            min-height: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        .visa-main-scroll {
            flex: 1;
            min-height: 0;
            overflow: auto;
            scrollbar-gutter: stable;
            -webkit-overflow-scrolling: touch;
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
            flex-shrink: 0;
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
            flex-shrink: 0;
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

        /* Tags for status */
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

        .status-tag.visa {
            background: #d4edda;
            color: #155724;
        }

        .status-tag.passport {
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

        .btn-delete-visa {
            background: #dc2626;
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-delete-visa:hover {
            background: #b91c1c;
        }

        /* Application process (same workflow as job applications) */
        .process-panel {
            background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
            border-bottom: 1px solid <?= $colors['border_gray'] ?>;
            padding: 14px 15px 16px;
        }

        .process-panel-title {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: <?= $colors['secondary_blue'] ?>;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .process-panel-title i { color: <?= $colors['gold'] ?>; }

        .process-tracker {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 6px;
            margin-bottom: 14px;
            overflow-x: auto;
            padding-bottom: 6px;
        }

        .process-step {
            flex: 1;
            min-width: 72px;
            text-align: center;
            position: relative;
        }

        .process-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 11px;
            left: calc(50% + 14px);
            right: calc(-50% + 14px);
            height: 3px;
            background: #e2e8f0;
            z-index: 0;
        }

        .process-step.done:not(:last-child)::after {
            background: linear-gradient(90deg, <?= $colors['gold'] ?>, #e2e8f0);
        }

        .process-step-dot {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            margin: 0 auto 6px;
            position: relative;
            z-index: 1;
            background: #e2e8f0;
            border: 3px solid #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .process-step.done .process-step-dot { background: <?= $colors['gold'] ?>; }
        .process-step.current .process-step-dot {
            background: <?= $colors['navy'] ?>;
            border-color: <?= $colors['gold'] ?>;
            box-shadow: 0 0 0 3px rgba(242, 166, 90, 0.35);
        }

        .process-step-label {
            display: block;
            font-size: 0.65rem;
            line-height: 1.25;
            color: #64748b;
            font-weight: 600;
        }

        .process-step.done .process-step-label,
        .process-step.current .process-step-label { color: <?= $colors['navy'] ?>; }

        .process-status-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            justify-content: space-between;
        }

        .status-pill-v {
            display: inline-flex;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            background: #e0e7ff;
            color: <?= $colors['navy'] ?>;
        }

        .status-pill-v--submitted { background: #f1f5f9; color: #475569; }
        .status-pill-v--under_review { background: #fef3c7; color: #92400e; }
        .status-pill-v--waiting_decision { background: #dbeafe; color: #1e40af; }
        .status-pill-v--final_decision { background: #d1fae5; color: #065f46; }
        .status-pill-v--closed { background: #e2e8f0; color: #334155; }
        .status-pill-v--rejected { background: #fee2e2; color: #991b1b; }

        .visa-process-select {
            flex: 1;
            min-width: 200px;
            max-width: 100%;
            padding: 8px 12px;
            border-radius: 8px;
            border: 2px solid <?= $colors['border_gray'] ?>;
            font-size: 0.85rem;
            font-weight: 600;
            color: <?= $colors['navy'] ?>;
            background: #fff;
            cursor: pointer;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            border: 0;
        }

        .process-toast-v {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 2000;
            background: <?= $colors['navy'] ?>;
            color: #fff;
            padding: 12px 18px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            display: none;
            align-items: center;
            gap: 10px;
        }

        .process-toast-v.show { display: flex; }

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

        #visaStatusNotifyModal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        #visaStatusNotifyModal.show-flex { display: flex !important; }
        .visa-notify-dialog {
            background: #fff;
            border-radius: 16px;
            max-width: 640px;
            width: 100%;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .visa-notify-header {
            background: linear-gradient(135deg, #427431 0%, #3661B9 100%);
            color: #fff;
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }
        .visa-notify-header h3 { margin: 0; font-size: 1.15rem; font-weight: 600; }
        .visa-notify-header p { margin: 6px 0 0; font-size: 0.85rem; opacity: 0.9; }
        .visa-notify-close { background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer; line-height: 1; }
        .visa-notify-body { padding: 20px; }
        .visa-notify-preview { text-align: center; margin-bottom: 16px; }
        .visa-notify-badge {
            display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 600;
            background: #e8eef9; color: #427431; margin-bottom: 8px;
        }
        .visa-notify-label-text { font-size: 1.35rem; font-weight: 700; color: #0f172a; }
        .visa-notify-hint { text-align: center; font-size: 0.85rem; color: #64748b; margin-bottom: 14px; }
        .visa-notify-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;
        }
        @media (min-width: 768px) { .visa-notify-grid { grid-template-columns: repeat(4, 1fr); } }
        .visa-notify-channel {
            border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px 10px; background: #fff;
            cursor: pointer; font-size: 0.9rem; font-weight: 600; color: #334155;
        }
        .visa-notify-channel.active { border-color: #427431; color: #427431; box-shadow: 0 0 0 1px #427431; }
        .visa-notify-channel span { display: block; font-size: 0.75rem; font-weight: 400; opacity: 0.85; margin-top: 4px; }
        .visa-notify-footer {
            padding: 14px 20px 18px; background: #f8fafc; display: flex; justify-content: flex-end; gap: 10px;
        }
        .visa-notify-footer button { padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; }
        .visa-notify-cancel { background: #fff; border: 1px solid #cbd5e1 !important; color: #475569; }
        .visa-notify-confirm { background: linear-gradient(135deg, #427431, #3661B9); color: #fff; }
        .process-toast-v.warn { background: linear-gradient(135deg, #b45309, #d97706); }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="header-title">
                <h1><i class="fas fa-passport"></i> Visa Applications</h1>
                <p>Review and manage all visa applications and documents<?php if ($visaProcessFilterActive): ?> — <strong style="color:#b91c1c;"><?= htmlspecialchars($visaFilterLabel) ?></strong><?php endif; ?></p>
                <?php if ($visaProcessFilterActive): ?>
                <p class="small" style="margin-top:8px;"><a href="visa-report.php" style="color:#3661B9;font-weight:600;"><i class="fas fa-list"></i> View all applicants</a></p>
                <?php endif; ?>
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
                <?php if ($visaProcessFilterActive): ?>
                <input type="hidden" name="process_status" value="<?= htmlspecialchars($processStatusFilter) ?>">
                <?php endif; ?>
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="Search by name, email, passport number, or ID..." 
                       value="<?= htmlspecialchars($searchTerm) ?>"
                       title="Search by applicant's name, email, passport number, or ID">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if(!empty($searchTerm)): ?>
                <a href="<?= $visaProcessFilterActive ? htmlspecialchars('?' . http_build_query(['process_status' => $processStatusFilter])) : '?' ?>" class="clear-btn">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Applications Grid (scroll area keeps horizontal scrollbar in view) -->
        <div class="visa-main-scroll">
        <div class="applicants-grid">
            <?php if(empty($applicants)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No applications found</h3>
                    <?php if(!empty($searchTerm)): ?>
                        <p>No applications match your search criteria. Try a different search term.</p>
                        <a href="<?= $visaProcessFilterActive ? htmlspecialchars('?' . http_build_query(['process_status' => $processStatusFilter])) : '?' ?>" class="search-btn" style="display: inline-flex;">
                            <i class="fas fa-redo"></i> Show All Applications
                        </a>
                    <?php elseif ($visaProcessFilterActive): ?>
                        <p>No applications currently have status &ldquo;<?= htmlspecialchars($visaFilterLabel) ?>&rdquo;.</p>
                        <a href="visa-report.php" class="search-btn" style="display: inline-flex;">
                            <i class="fas fa-redo"></i> View all applications
                        </a>
                    <?php else: ?>
                        <p>No visa applications have been submitted yet.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach($applicants as $applicant): 
                    // Full name with prefix
                    $fullName = '';
                    if (!empty($applicant['prefix'])) {
                        $fullName .= $applicant['prefix'] . ' ';
                    }
                    $fullName .= $applicant['first_name'];
                    if (!empty($applicant['middle_name'])) {
                        $fullName .= ' ' . $applicant['middle_name'];
                    }
                    $fullName .= ' ' . $applicant['last_name'];
                    
                    // Format date
                    $appliedDate = !empty($applicant['date']) ? date('M d, Y', strtotime($applicant['date'])) : 'Not specified';
                    
                    // Check if unread
                    $isUnread = $applicant['is_read'] == 0;
                    
                    // Format passport info
                    $passportInfo = $applicant['passport_number'];
                    if (!empty($applicant['passport_expiry'])) {
                        $passportInfo .= ' (Exp: ' . $applicant['passport_expiry'] . ')';
                    }

                    $procKey = xander_normalize_job_process_status($applicant['process_status'] ?? null);
                    $procLabel = $JOB_PROCESS_STATUSES[$procKey];
                    $curIdx = array_search($procKey, $JOB_PROCESS_ORDER, true);
                    if ($curIdx === false) {
                        $curIdx = 0;
                    }
                ?>
                <div class="applicant-card <?= $isUnread ? 'unread' : '' ?>" data-id="<?= htmlspecialchars($applicant['user_id']) ?>" data-user-id="<?= htmlspecialchars($applicant['user_id']) ?>">
                    <!-- Unread dot -->
                    <div class="unread-dot" title="Unread"></div>
                    
                    <!-- Card Header -->
                    <div class="card-header">
                        <div class="applicant-name"><?= htmlspecialchars($fullName) ?></div>
                        <div class="applicant-id">ID: <?= htmlspecialchars($applicant['user_id']) ?></div>
                    </div>

                    <div class="process-panel">
                        <div class="process-panel-title">
                            <i class="fas fa-route"></i> Application process
                        </div>
                        <div class="process-tracker" role="list">
                            <?php foreach ($JOB_PROCESS_ORDER as $i => $stepKey):
                                $st = $i < $curIdx ? 'done' : ($i === $curIdx ? 'current' : 'pending');
                                $stepLabel = $JOB_PROCESS_STATUSES[$stepKey];
                            ?>
                            <div class="process-step <?= $st ?>" role="listitem">
                                <span class="process-step-dot" aria-hidden="true"></span>
                                <span class="process-step-label"><?= htmlspecialchars($stepLabel) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="process-status-row">
                            <span class="status-pill-v status-pill-v--<?= htmlspecialchars($procKey) ?>"><?= htmlspecialchars($procLabel) ?></span>
                            <?php if ($canEditVisaProcess): ?>
                            <label class="sr-only" for="visa-status-<?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]/', '_', $applicant['user_id'])) ?>">Set application status</label>
                            <select id="visa-status-<?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]/', '_', $applicant['user_id'])) ?>"
                                    class="visa-process-select"
                                    data-user-id="<?= htmlspecialchars($applicant['user_id']) ?>"
                                    title="Superadmin only">
                                <?php foreach ($JOB_PROCESS_STATUSES as $k => $lab): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= $k === $procKey ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Card Body -->
                    <div class="card-body">
                        <!-- Contact Info -->
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Contact</div>
                                <div class="info-value">
                                    <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($applicant['email']) ?></div>
                                    <div><i class="fas fa-phone"></i> <?= htmlspecialchars($applicant['applicant_mobile']) ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Personal Info -->
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Personal</div>
                                <div class="info-value">
                                    <div><i class="fas fa-birthday-cake"></i> <?= htmlspecialchars($applicant['birthdate']) ?> (<?= htmlspecialchars($applicant['gender']) ?>)</div>
                                    <div><i class="fas fa-globe"></i> <?= htmlspecialchars($applicant['country_name'] ?? 'Not specified') ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Passport & Visa Info -->
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Passport & Visa</div>
                                <div class="info-value">
                                    <div><i class="fas fa-passport"></i> <?= htmlspecialchars($passportInfo) ?></div>
                                    <div><i class="fas fa-plane-departure"></i> From: <?= htmlspecialchars($applicant['country_applying_from']) ?></div>
                                    <div><i class="fas fa-plane-arrival"></i> To: <?= htmlspecialchars($applicant['country_to_visit']) ?></div>
                                    <div><i class="fas fa-file-alt"></i> Visa Type: <span class="status-tag visa"><?= htmlspecialchars($applicant['visa_type']) ?></span></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Region & Terms -->
                        <div class="card-section">
                            <div class="info-row">
                                <div class="info-label">Region & Terms</div>
                                <div class="info-value">
                                    <div><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($applicant['region_name'] ?? 'Not specified') ?></div>
                                    <div><i class="fas fa-check-circle"></i> Terms: <?= !empty($applicant['terms']) ? 'Accepted' : 'Not accepted' ?></div>
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
                    </div>
                    
                    <!-- Card Footer -->
                    <div class="card-footer">
                        <div class="applied-date">
                            <i class="fas fa-calendar-check"></i> <?= $appliedDate ?>
                        </div>
                        <div class="action-buttons" style="flex-wrap: wrap;">
                            <a href="mailto:<?= htmlspecialchars($applicant['email']) ?>" class="contact-btn" title="Send Email">
                                <i class="fas fa-envelope"></i> Contact
                            </a>
                            <button class="view-btn view-details-btn" 
                                    data-applicant='<?= htmlspecialchars(json_encode([
                                        'name' => $fullName,
                                        'id' => $applicant['user_id'],
                                        'email' => $applicant['email'],
                                        'mobile' => $applicant['applicant_mobile'],
                                        'birthdate' => $applicant['birthdate'],
                                        'gender' => $applicant['gender'],
                                        'passport' => $passportInfo,
                                        'country_from' => $applicant['country_applying_from'],
                                        'country_to' => $applicant['country_to_visit'],
                                        'visa_type' => $applicant['visa_type'],
                                        'region' => $applicant['region_name'],
                                        'country' => $applicant['country_name'],
                                        'terms' => $applicant['terms'],
                                        'applied' => $appliedDate,
                                        'is_read' => $applicant['is_read'],
                                        'processStatus' => $procKey,
                                        'processLabel' => $procLabel,
                                    ])) ?>'>
                                <i class="fas fa-eye"></i> Details
                            </button>
                            <?php if ($canDeleteVisaApplication): ?>
                            <button type="button" class="btn-delete-visa btn-delete-visa-app" data-user-id="<?= htmlspecialchars($applicant['user_id']) ?>" title="Delete this application (Superadmin)">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: white; border-radius: 10px; max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div style="background: <?= $colors['navy'] ?>; color: white; padding: 20px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.2rem;"><i class="fas fa-passport"></i> Visa Application Details</h3>
                <button id="closeModal" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div style="padding: 20px;" id="modalContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <div id="visaStatusNotifyModal" aria-hidden="true">
        <div class="visa-notify-dialog">
            <div class="visa-notify-header">
                <div>
                    <h3><i class="fas fa-bell"></i> Save status</h3>
                    <p>Notify the applicant (visit or study visa)</p>
                </div>
                <button type="button" class="visa-notify-close" id="visaNotifyCloseX" aria-label="Close">&times;</button>
            </div>
            <div class="visa-notify-body">
                <div class="visa-notify-preview">
                    <div class="visa-notify-badge">New status</div>
                    <div class="visa-notify-label-text" id="visaNotifyStatusLabel">—</div>
                </div>
                <div id="visaRejectReasonWrap" style="display:none;margin-bottom:14px;">
                    <label for="visaRejectReason" style="display:block;font-weight:600;font-size:0.85rem;color:#b91c1c;margin-bottom:6px;">Reason for rejection</label>
                    <textarea id="visaRejectReason" rows="3" maxlength="2000" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:0.9rem;resize:vertical;" placeholder="Required if you send email or WhatsApp"></textarea>
                    <p style="margin:6px 0 0;font-size:0.78rem;color:#64748b;">Included in the message to the applicant when you notify.</p>
                </div>
                <p class="visa-notify-hint">Choose one — you can save without a notification.</p>
                <div class="visa-notify-grid">
                    <button type="button" class="visa-notify-channel active" data-ne="0" data-nw="0">Record only<span>No notification</span></button>
                    <button type="button" class="visa-notify-channel" data-ne="1" data-nw="0">Email<span>Send email</span></button>
                    <button type="button" class="visa-notify-channel" data-ne="0" data-nw="1">WhatsApp<span>Send WhatsApp</span></button>
                    <button type="button" class="visa-notify-channel" data-ne="1" data-nw="1">Both<span>Email + WhatsApp</span></button>
                </div>
            </div>
            <div class="visa-notify-footer">
                <button type="button" class="visa-notify-cancel" id="visaNotifyCancel">Cancel</button>
                <button type="button" class="visa-notify-confirm" id="visaNotifyConfirm">Save</button>
            </div>
        </div>
    </div>

    <div id="processToastV" class="process-toast-v" role="status" aria-live="polite">
        <i class="fas fa-check-circle"></i>
        <span id="processToastMsgV">Saved</span>
    </div>

    <script>
        window.JOB_PROCESS_ORDER = <?= json_encode($JOB_PROCESS_ORDER, JSON_UNESCAPED_UNICODE) ?>;
        window.JOB_PROCESS_LABELS = <?= json_encode($JOB_PROCESS_STATUSES, JSON_UNESCAPED_UNICODE) ?>;
        window.CAN_DELETE_VISA = <?= json_encode($canDeleteVisaApplication) ?>;
        window.CAN_EDIT_VISA_PROCESS = <?= json_encode($canEditVisaProcess) ?>;
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.visa-process-select').forEach(function(sel) {
                sel.setAttribute('data-prev-status', sel.value);
            });

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
                const procLabel = data.processLabel || (window.JOB_PROCESS_LABELS && data.processStatus ? window.JOB_PROCESS_LABELS[data.processStatus] : '') || '—';
                modalContent.innerHTML = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                        <div style="grid-column: 1 / -1; background: linear-gradient(135deg, #f8f9fa, #e9ecef); padding: 15px; border-radius: 8px; text-align: center;">
                            <h4 style="margin: 0; color: <?= $colors['navy'] ?>;">${data.name}</h4>
                            <p style="margin: 5px 0 0 0; color: #666;">ID: ${data.id} | Applied on ${data.applied}</p>
                        </div>
                        <div style="grid-column: 1 / -1; background: rgba(37, 77, 129, 0.08); padding: 12px 14px; border-radius: 8px; border-left: 4px solid <?= $colors['gold'] ?>;">
                            <strong style="color: <?= $colors['secondary_blue'] ?>; font-size: 0.85rem;">Application process</strong>
                            <div style="margin-top: 6px; font-weight: 700; color: <?= $colors['navy'] ?>;">${procLabel}</div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px;">
                            <strong style="color: <?= $colors['secondary_blue'] ?>;">Contact Information</strong>
                            <div style="margin-top: 8px;">
                                <div><i class="fas fa-envelope" style="color: <?= $colors['gold'] ?>;"></i> ${data.email}</div>
                                <div><i class="fas fa-mobile-alt" style="color: <?= $colors['gold'] ?>;"></i> ${data.mobile}</div>
                            </div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px;">
                            <strong style="color: <?= $colors['secondary_blue'] ?>;">Personal Details</strong>
                            <div style="margin-top: 8px;">
                                <div><i class="fas fa-birthday-cake" style="color: <?= $colors['gold'] ?>;"></i> ${data.birthdate}</div>
                                <div><i class="fas fa-venus-mars" style="color: <?= $colors['gold'] ?>;"></i> ${data.gender}</div>
                            </div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px;">
                            <strong style="color: <?= $colors['secondary_blue'] ?>;">Passport Information</strong>
                            <div style="margin-top: 8px;">
                                <div><i class="fas fa-passport" style="color: <?= $colors['gold'] ?>;"></i> ${data.passport}</div>
                            </div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px;">
                            <strong style="color: <?= $colors['secondary_blue'] ?>;">Travel Information</strong>
                            <div style="margin-top: 8px;">
                                <div><i class="fas fa-plane-departure" style="color: <?= $colors['gold'] ?>;"></i> From: ${data.country_from}</div>
                                <div><i class="fas fa-plane-arrival" style="color: <?= $colors['gold'] ?>;"></i> To: ${data.country_to}</div>
                                <div><i class="fas fa-file-alt" style="color: <?= $colors['gold'] ?>;"></i> Visa Type: <span class="status-tag visa">${data.visa_type}</span></div>
                            </div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 6px;">
                            <strong style="color: <?= $colors['secondary_blue'] ?>;">Region & Terms</strong>
                            <div style="margin-top: 8px;">
                                <div><i class="fas fa-map-marker-alt" style="color: <?= $colors['gold'] ?>;"></i> Region: ${data.region || 'Not specified'}</div>
                                <div><i class="fas fa-globe" style="color: <?= $colors['gold'] ?>;"></i> Country: ${data.country || 'Not specified'}</div>
                                <div><i class="fas fa-check-circle" style="color: <?= $colors['gold'] ?>;"></i> Terms: ${data.terms ? 'Accepted' : 'Not accepted'}</div>
                            </div>
                        </div>
                        
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
                    body: 'id=' + encodeURIComponent(userId) + '&table=form_17_applications'
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

            function showProcessToastV(msg) {
                const t = document.getElementById('processToastV');
                const m = document.getElementById('processToastMsgV');
                if (!t || !m) return;
                m.textContent = msg;
                t.classList.add('show');
                clearTimeout(window._processToastTimerV);
                window._processToastTimerV = setTimeout(function() { t.classList.remove('show'); }, 2800);
            }

            function updateCardVisaProcessUI(card, statusKey) {
                if (!card || !window.JOB_PROCESS_ORDER) return;
                const order = window.JOB_PROCESS_ORDER;
                const labels = window.JOB_PROCESS_LABELS || {};
                const curIdx = order.indexOf(statusKey);
                const idx = curIdx === -1 ? 0 : curIdx;
                card.querySelectorAll('.process-step').forEach(function(step, i) {
                    step.classList.remove('done', 'current', 'pending');
                    var st = i < idx ? 'done' : (i === idx ? 'current' : 'pending');
                    step.classList.add(st);
                });
                var pill = card.querySelector('.status-pill-v');
                if (pill) {
                    pill.className = 'status-pill-v status-pill-v--' + statusKey;
                    pill.textContent = labels[statusKey] || statusKey;
                }
                var sel = card.querySelector('.visa-process-select');
                if (sel && sel.querySelector('option[value="' + statusKey + '"]')) {
                    sel.value = statusKey;
                }
            }

            var visaNotifyModal = document.getElementById('visaStatusNotifyModal');
            var visaNotifyPending = null;

            document.querySelectorAll('.visa-notify-channel').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.visa-notify-channel').forEach(function(b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                });
            });

            function openVisaNotifyModal() {
                if (!visaNotifyModal) return;
                visaNotifyModal.classList.add('show-flex');
                visaNotifyModal.style.display = 'flex';
                visaNotifyModal.setAttribute('aria-hidden', 'false');
            }
            function closeVisaNotifyModal() {
                if (!visaNotifyModal) return;
                visaNotifyModal.classList.remove('show-flex');
                visaNotifyModal.style.display = 'none';
                visaNotifyModal.setAttribute('aria-hidden', 'true');
                visaNotifyPending = null;
                var vri = document.getElementById('visaRejectReason');
                var vrw = document.getElementById('visaRejectReasonWrap');
                if (vri) vri.value = '';
                if (vrw) vrw.style.display = 'none';
            }

            document.getElementById('visaNotifyCancel').addEventListener('click', closeVisaNotifyModal);
            document.getElementById('visaNotifyCloseX').addEventListener('click', closeVisaNotifyModal);
            visaNotifyModal.addEventListener('click', function(ev) {
                if (ev.target === visaNotifyModal) closeVisaNotifyModal();
            });

            document.getElementById('visaNotifyConfirm').addEventListener('click', function() {
                var p = visaNotifyPending;
                if (!p || !p.sel) return;
                var active = document.querySelector('.visa-notify-channel.active');
                var ne = active ? parseInt(active.getAttribute('data-ne'), 10) || 0 : 0;
                var nw = active ? parseInt(active.getAttribute('data-nw'), 10) || 0 : 0;
                var sel = p.sel;
                var statusKey = p.newKey;
                var userId = p.userId;
                var vrEl = document.getElementById('visaRejectReason');
                var rejectReason = vrEl ? (vrEl.value || '').trim() : '';
                if (statusKey === 'rejected' && (ne || nw) && rejectReason === '') {
                    alert('Please enter a rejection reason before sending email or WhatsApp.');
                    return;
                }
                closeVisaNotifyModal();
                sel.disabled = true;
                var fd = new FormData();
                fd.append('user_id', userId);
                fd.append('process_status', statusKey);
                fd.append('notify_email', ne ? '1' : '0');
                fd.append('notify_whatsapp', nw ? '1' : '0');
                fd.append('rejection_reason', rejectReason);
                fetch('api/form17-application-status.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                .then(function(r) { return r.json(); })
                .then(function(json) {
                    sel.disabled = false;
                    if (!json.success) {
                        alert(json.message || 'Could not update status');
                        sel.value = p.prevKey;
                        return;
                    }
                    sel.value = statusKey;
                    sel.setAttribute('data-prev-status', statusKey);
                    var card = sel.closest('.applicant-card');
                    updateCardVisaProcessUI(card, statusKey);
                    var label = (json.data && json.data.label) ? json.data.label : ((window.JOB_PROCESS_LABELS && window.JOB_PROCESS_LABELS[statusKey]) || statusKey);
                    var n = json.data && json.data.notify;
                    var parts = ['Status saved'];
                    var anyFail = false;
                    if ((ne || nw) && !n) {
                        anyFail = true;
                        parts.push('Notifications failed (server error).');
                    }
                    if (n && n.email && n.email.requested) {
                        if (n.email.sent) parts.push('Email sent');
                        else { anyFail = true; parts.push('Email failed' + (n.email.error ? ': ' + n.email.error : '')); }
                    }
                    if (n && n.whatsapp && n.whatsapp.requested) {
                        if (n.whatsapp.sent) {
                            parts.push(n.whatsapp.method === 'text' ? 'WhatsApp sent (session)' : 'WhatsApp sent');
                        } else {
                            anyFail = true;
                            parts.push('WhatsApp failed' + (n.whatsapp.error ? ': ' + n.whatsapp.error : ''));
                        }
                    }
                    if (!ne && !nw) {
                        parts.length = 1;
                        parts[0] = 'Status saved (no notification)';
                    }
                    var msg = parts.join(' · ');
                    var toast = document.getElementById('processToastV');
                    var toastMsg = document.getElementById('processToastMsgV');
                    if (toast && toastMsg) {
                        toast.classList.toggle('warn', anyFail);
                        toastMsg.textContent = msg;
                        toast.classList.add('show');
                        clearTimeout(window._visaToastTimer);
                        window._visaToastTimer = setTimeout(function() { toast.classList.remove('show'); toast.classList.remove('warn'); }, 3800);
                    } else {
                        alert(msg);
                    }
                    var viewBtn = card ? card.querySelector('.view-details-btn') : null;
                    if (viewBtn) {
                        try {
                            var d = JSON.parse(viewBtn.getAttribute('data-applicant'));
                            d.processStatus = statusKey;
                            d.processLabel = label;
                            viewBtn.setAttribute('data-applicant', JSON.stringify(d));
                        } catch (err2) {}
                    }
                })
                .catch(function(err) {
                    sel.disabled = false;
                    sel.value = p.prevKey;
                    console.error(err);
                    alert('Network error while saving status.');
                });
            });

            document.addEventListener('focusin', function(e) {
                var t = e.target;
                if (t.classList && t.classList.contains('visa-process-select')) {
                    t.setAttribute('data-prev-status', t.value);
                }
            });

            document.addEventListener('change', function(e) {
                var sel = e.target;
                if (!sel.classList || !sel.classList.contains('visa-process-select')) return;
                var userId = sel.getAttribute('data-user-id');
                var newKey = sel.value;
                var prevKey = sel.getAttribute('data-prev-status');
                if (prevKey === null || prevKey === '') return;
                if (!userId || newKey === prevKey) return;
                sel.value = prevKey;
                var labels = window.JOB_PROCESS_LABELS || {};
                document.getElementById('visaNotifyStatusLabel').textContent = labels[newKey] || newKey;
                document.querySelectorAll('.visa-notify-channel').forEach(function(b, i) {
                    b.classList.toggle('active', i === 0);
                });
                var vrr = document.getElementById('visaRejectReasonWrap');
                var vri = document.getElementById('visaRejectReason');
                if (vrr && vri) {
                    vrr.style.display = newKey === 'rejected' ? 'block' : 'none';
                    vri.value = '';
                }
                visaNotifyPending = { sel: sel, userId: userId, newKey: newKey, prevKey: prevKey };
                openVisaNotifyModal();
            });

            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.btn-delete-visa-app');
                if (!btn) return;
                if (!window.CAN_DELETE_VISA) {
                    alert('Only Superadmin can delete applications.');
                    return;
                }
                var userId = btn.getAttribute('data-user-id');
                if (!userId) return;
                if (!confirm('Permanently delete this visa application? This cannot be undone.')) return;
                var fd = new FormData();
                fd.append('user_id', userId);
                btn.disabled = true;
                fetch('api/form17-application-delete.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                .then(function(r) { return r.json(); })
                .then(function(json) {
                    btn.disabled = false;
                    if (!json.success) {
                        alert(json.message || 'Delete failed');
                        return;
                    }
                    var card = btn.closest('.applicant-card');
                    if (card) {
                        card.style.opacity = '0.5';
                        setTimeout(function() { card.remove(); }, 200);
                    }
                    showProcessToastV('Application deleted');
                })
                .catch(function(err) {
                    btn.disabled = false;
                    console.error(err);
                    alert('Delete failed.');
                });
            });
        });
        
        function exportToCSV() {
            // Simple CSV export functionality
            alert('CSV export functionality would be implemented here.\nThis would generate a file with all visa application data.');
            // In a real implementation, you would make an AJAX call to an export script
        }
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>