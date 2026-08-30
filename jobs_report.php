<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/company_branding.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/secure_file.php';
$companyBrandName = PCVC_COMPANY_DISPLAY_NAME;
/* ===========================================================
   AUTHENTICATION & AUTHORIZATION
============================================================ */
if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    exit('Access denied. Please log in.');
}

$current_user_id = (int)$_SESSION['id'];
$role = pcvc_normalize_role_string($_SESSION['role'] ?? 'staff');
$isSuperAdmin = pcvc_is_superadmin_role($role);

/* ===========================================================
   ACTIVE ADMIN VIEW (for superadmin)
============================================================ */
$active_admin_id = ($isSuperAdmin && isset($_GET['view_admin_id']) && ctype_digit($_GET['view_admin_id']))
    ? (int)$_GET['view_admin_id']
    : $current_user_id;

/* ===========================================================
   RESOLVE JOB TYPE FOR REPORTING
============================================================ */
function pcvc_report_job_type_options(): array
{
    return [
        'Student Admission Application',
        'Student Loan Application',
        'Student I-20 Application',
        'Student DS-160 Application',
        'Credit Transfer Application',
        'Student CAQ (Québec Acceptance Certificate) Application',
        'Student PAL (Provincial Attestation Letter) Application',
        'Student Visa Application',
        'Student PGWP (Post-Graduation Work Permit) Application',
        'Other Job',
    ];
}

function pcvc_resolve_report_job_type(?string $fromJobList, string $jobTitle): string
{
    if ($fromJobList !== null && trim($fromJobList) !== '') {
        $type = trim($fromJobList);
        if (strcasecmp($type, 'Student Application') === 0) {
            return 'Student Admission Application';
        }

        return $type;
    }

    $title = trim($jobTitle);
    foreach (pcvc_report_known_job_types() as $type) {
        if (strcasecmp($title, $type) === 0) {
            return $type;
        }
    }

    return 'Other Job';
}

function pcvc_report_known_job_types(): array
{
    return array_merge(pcvc_report_job_type_options(), ['Student Application']);
}

function pcvc_report_title_is_generic(string $title, string $jobType): bool
{
    $title = trim($title);
    $jobType = trim($jobType);

    if ($title === '' || strcasecmp($title, $jobType) === 0) {
        return true;
    }

    foreach (pcvc_report_known_job_types() as $type) {
        if (strcasecmp($title, $type) === 0) {
            return true;
        }
    }

    return false;
}

function pcvc_report_display_title(array $row): string
{
    $jobType = pcvc_resolve_report_job_type($row['list_job_type'] ?? null, $row['job_title'] ?? '');
    $storedTitle = trim((string) ($row['job_title'] ?? ''));
    $applicant = trim((string) ($row['applicant_name'] ?? ''));

    if (pcvc_report_title_is_generic($storedTitle, $jobType)) {
        if ($applicant !== '') {
            return $applicant;
        }
    }

    return $storedTitle !== '' ? $storedTitle : ($applicant !== '' ? $applicant : 'N/A');
}

function pcvc_report_job_description(array $row, string $displayTitle = ''): string
{
    $desc = trim((string) ($row['job_description'] ?? ''));
    if ($desc !== '') {
        return $desc;
    }

    $parts = [];
    $applicant = trim((string) ($row['applicant_name'] ?? ''));
    $email = trim((string) ($row['applicant_email'] ?? ''));
    $comment = trim((string) ($row['comment'] ?? ''));

    if ($applicant !== '' && strcasecmp($applicant, $displayTitle) !== 0) {
        $parts[] = 'Applicant: ' . $applicant;
    }
    if ($email !== '') {
        $parts[] = 'Email: ' . $email;
    }
    if ($comment !== '') {
        $parts[] = $comment;
    }

    return implode("\n", $parts);
}

function pcvc_report_job_status(array $row): array
{
    $raw = strtolower(trim((string) ($row['job_status'] ?? '')));

    if ($raw === 'completed' || ((int) ($row['list_id'] ?? 0) === 0 && $raw === '')) {
        return [
            'key' => 'completed',
            'label' => 'Completed',
            'class' => 'status-completed',
        ];
    }

    return [
        'key' => 'not_completed',
        'label' => 'Not Completed',
        'class' => 'status-not_completed',
    ];
}

/* ===========================================================
   FETCH ADMIN INFO
============================================================ */
$stmt = $conn->prepare("SELECT full_name, profile_photo, position, sheet_link FROM admins WHERE id = ?");
$stmt->bind_param("i", $active_admin_id);
$stmt->execute();
$admin_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ===========================================================
   FETCH ADMIN LIST FOR SUPERADMIN
============================================================ */
$admin_list = [];
if ($isSuperAdmin) {
    $res = $conn->query("SELECT id, full_name, profile_photo FROM admins ORDER BY full_name");
    while ($r = $res->fetch_assoc()) {
        $admin_list[] = $r;
    }
}

/* ===========================================================
   FETCH JOB REPORT DATA (all job_list + legacy jobs)
============================================================ */
$stmt = $conn->prepare("
    SELECT *
    FROM (
        SELECT
            jl.id AS list_id,
            COALESCE(j.id, 0) AS jobs_id,
            jl.title AS job_title,
            jl.job_type AS list_job_type,
            jl.status AS job_status,
            jl.applicant_name,
            jl.applicant_email,
            jl.comment,
            COALESCE(NULLIF(j.job_description, ''), NULLIF(jl.comment, ''), '') AS job_description,
            COALESCE(j.hours_spent, 0) AS hours_spent,
            COALESCE(j.productivity_score, 0) AS productivity_score,
            COALESCE(j.remarks, '') AS remarks,
            COALESCE(j.ai_suggestions, '') AS ai_suggestions,
            jl.screenshot_path,
            COALESCE(jl.completed_at, jl.created_at) AS created_at,
            admins.full_name,
            admins.profile_photo
        FROM job_list jl
        JOIN admins ON admins.id = jl.admin_id
        LEFT JOIN jobs j ON j.admin_id = jl.admin_id AND j.created_at = jl.completed_at
        WHERE jl.admin_id = ?

        UNION ALL

        SELECT
            0 AS list_id,
            j.id AS jobs_id,
            j.job_title,
            NULL AS list_job_type,
            'completed' AS job_status,
            '' AS applicant_name,
            '' AS applicant_email,
            '' AS comment,
            j.job_description,
            j.hours_spent,
            j.productivity_score,
            j.remarks,
            j.ai_suggestions,
            '' AS screenshot_path,
            j.created_at,
            admins.full_name,
            admins.profile_photo
        FROM jobs j
        JOIN admins ON admins.id = j.admin_id
        LEFT JOIN job_list jl ON jl.admin_id = j.admin_id AND jl.completed_at = j.created_at
        WHERE j.admin_id = ?
          AND jl.id IS NULL
    ) AS report_rows
    ORDER BY created_at DESC
");
$stmt->bind_param('ii', $active_admin_id, $active_admin_id);
$stmt->execute();
$result = $stmt->get_result();

$reportRows = [];
$highCount = 0;
$midCount = 0;
$lowCount = 0;
$uniqueGroups = [];

while ($row = $result->fetch_assoc()) {
    $reportRows[] = $row;

    $score = (int) ($row['productivity_score'] ?? 0);
    if ($score >= 75) {
        $highCount++;
    } elseif ($score >= 40) {
        $midCount++;
    } else {
        $lowCount++;
    }

    $g = pcvc_resolve_report_job_type($row['list_job_type'] ?? null, $row['job_title'] ?? '');
    $uniqueGroups[$g] = true;
}
$stmt->close();

$totalRecords = count($reportRows);
$typeFilterList = pcvc_report_job_type_options();

$tableColCount = $isSuperAdmin ? 9 : 8;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ScholarSync Global Visa — Job Report</title>
    
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
            --low-bg: #fee2e2;
            --low-text: #991b1b;
            --mid-bg: #fef3c7;
            --mid-text: #92400e;
            --high-bg: #dcfce7;
            --high-text: #166534;
        }
    </style>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
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
            content: '📊';
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

        .admin-info {
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

        .admin-details {
            display: flex;
            flex-direction: column;
        }

        .admin-name {
            font-weight: 700;
            color: var(--deep-navy);
            font-size: 16px;
        }

        .admin-position {
            font-size: 12px;
            color: var(--text-muted);
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

        /* ===== SHEET BUTTON ===== */
        .sheet-btn {
            background: linear-gradient(135deg, var(--success) 0%, #1b5e20 100%);
            color: var(--white);
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .sheet-btn:hover {
            background: linear-gradient(135deg, #1b5e20 0%, #0a3622 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 125, 50, 0.2);
            color: var(--white);
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

        .admin-select-wrap {
            min-width: 280px;
        }

        .admin-select-wrap .select2-container--default .select2-selection--single {
            height: 42px;
            border: 2px solid var(--border-light);
            border-radius: 8px;
            padding: 6px 10px;
        }

        .admin-select-wrap .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 26px;
            color: var(--text-dark);
            font-size: 14px;
        }

        .admin-select-wrap .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }

        .admin-select-wrap .select2-container--default.select2-container--open .select2-selection--single,
        .admin-select-wrap .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: var(--gold);
        }

        .view-switch {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        .view-btn {
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .view-btn-jobs {
            background: var(--light-bg);
            color: var(--text-muted);
            border: 1px solid var(--border-light);
        }

        .view-btn-apps {
            background: var(--light-bg);
            color: var(--text-muted);
            border: 1px solid var(--border-light);
        }

        .view-btn.active {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            border: none;
        }

        .view-btn.active i {
            color: var(--gold);
        }

        /* ===== SEARCH BAR ===== */
        .search-wrapper {
            flex: 1;
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

        .period-filter, .title-filter {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }

        .period-btn {
            padding: 8px 14px;
            border-radius: 20px;
            border: 1px solid var(--border-light);
            background: var(--white);
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .period-btn:hover {
            border-color: var(--gold);
            color: var(--deep-navy);
        }

        .period-btn.active {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            border-color: transparent;
        }

        .title-select {
            min-width: 200px;
            padding: 8px 12px;
            border: 2px solid var(--border-light);
            border-radius: 8px;
            font-size: 13px;
            background: var(--white);
        }

        .group-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--deep-navy);
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }

        .filter-meta {
            font-size: 12px;
            color: var(--text-muted);
            margin-left: auto;
        }

        .job-group-header {
            background: linear-gradient(135deg, #f0f7ff 0%, #e8f4fc 100%);
            cursor: pointer;
            border-left: 4px solid var(--gold);
        }

        .job-group-header td {
            padding: 12px 16px !important;
            background: transparent !important;
            font-weight: 700;
            color: var(--deep-navy);
        }

        .job-group-header .group-meta {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            margin-left: 12px;
        }

        .job-group-header i.group-chevron {
            color: var(--gold);
            margin-right: 8px;
            transition: transform 0.2s ease;
        }

        .job-group-header.collapsed i.group-chevron {
            transform: rotate(-90deg);
        }

        tr.report-row.hidden-by-filter {
            display: none;
        }

        tr.report-row.hidden-by-group {
            display: none;
        }

        mark.search-hit {
            background: #fef08a;
            color: inherit;
            padding: 0 2px;
            border-radius: 2px;
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
            padding: 14px 12px;
            border-bottom: 1px solid var(--border-light);
            border-right: 1px solid #f1f3f5;
            vertical-align: middle;
            background: var(--white);
        }

        td:last-child {
            border-right: none;
        }

        tr:hover td {
            background: rgba(242, 166, 90, 0.02);
        }

        /* Admin badge in table */
        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--light-bg);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: var(--deep-navy);
        }

        .admin-badge i {
            color: var(--gold);
        }

        /* Score badges */
        .score-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            min-width: 60px;
            text-align: center;
        }

        .score-high {
            background: var(--high-bg);
            color: var(--high-text);
            border-left: 3px solid var(--success);
        }

        .score-mid {
            background: var(--mid-bg);
            color: var(--mid-text);
            border-left: 3px solid var(--warning);
        }

        .score-low {
            background: var(--low-bg);
            color: var(--low-text);
            border-left: 3px solid var(--danger);
        }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: var(--high-bg);
            color: var(--high-text);
        }

        .status-pending {
            background: var(--mid-bg);
            color: var(--mid-text);
        }

        .status-closed {
            background: #e9ecef;
            color: var(--text-muted);
        }

        .status-completed {
            background: #dcfce7;
            color: var(--success);
        }

        .status-not_completed {
            background: #fee2e2;
            color: var(--danger);
        }

        /* Thumbnail */
        .thumb-container {
            width: 48px;
            height: 48px;
            border-radius: 6px;
            overflow: hidden;
            border: 2px solid var(--gold);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .thumb-container:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(1, 47, 107, 0.2);
        }

        .thumb {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .thumb-placeholder {
            width: 48px;
            height: 48px;
            background: var(--light-bg);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 20px;
        }

        /* Description cell */
        .description-cell {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Hours display */
        .hours-display {
            font-weight: 600;
            color: var(--deep-navy);
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

        /* Back link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            margin-top: 16px;
            transition: all 0.2s ease;
        }

        .back-link:hover {
            color: var(--deep-navy);
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
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .stats-container {
                width: 100%;
            }
            
            .filters-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .view-switch {
                margin-left: 0;
            }
            
            .admin-select {
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
            <div class="logo-subtitle">Job report</div>
        </div>
    </div>
</div>

<div class="main-container">
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="admin-info">
            <?php if (!empty($admin_info['profile_photo'])): ?>
            <div class="profile-img-container">
                <img src="<?= htmlspecialchars(pcvc_profile_photo_url($admin_info['profile_photo'] ?? '')) ?>" 
                     alt="Profile" class="profile-img">
            </div>
            <?php else: ?>
            <div class="profile-placeholder">
                <?= strtoupper(substr($admin_info['full_name'] ?? 'U', 0, 1)) ?>
            </div>
            <?php endif; ?>
            <div class="admin-details">
                <span class="admin-name"><?= htmlspecialchars($admin_info['full_name'] ?? 'N/A') ?></span>
                <span class="admin-position"><?= htmlspecialchars($admin_info['position'] ?? 'Staff Member') ?></span>
            </div>
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-file-text"></i>
                </div>
                <div class="stat-info">
                    <h3 id="statTotal"><?= $totalRecords ?></h3>
                    <p>Total Records</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-collection"></i>
                </div>
                <div class="stat-info">
                    <h3 id="statGroups"><?= count($uniqueGroups) ?></h3>
                    <p>Job Types</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-arrow-up-circle"></i>
                </div>
                <div class="stat-info">
                    <h3 id="statHigh"><?= $highCount ?></h3>
                    <p>High Score</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-dash-circle"></i>
                </div>
                <div class="stat-info">
                    <h3 id="statMid"><?= $midCount ?></h3>
                    <p>Medium</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-arrow-down-circle"></i>
                </div>
                <div class="stat-info">
                    <h3 id="statLow"><?= $lowCount ?></h3>
                    <p>Low</p>
                </div>
            </div>
            
            <?php if (!empty($admin_info['sheet_link'])): ?>
            <a class="sheet-btn" href="<?= htmlspecialchars($admin_info['sheet_link']) ?>" target="_blank">
                <i class="bi bi-google"></i> Google Sheet
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Filters Section -->
    <div class="filters-section">
        <?php if ($isSuperAdmin): ?>
        <form method="GET" id="adminFilterForm" class="admin-select-wrap">
            <select name="view_admin_id" id="adminStaffSelect" class="admin-select">
                <?php foreach ($admin_list as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $a['id'] == $active_admin_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['full_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>

        <div class="period-filter" id="periodFilter">
            <button type="button" class="period-btn active" data-period="all">All time</button>
            <button type="button" class="period-btn" data-period="day">Today</button>
            <button type="button" class="period-btn" data-period="week">This week</button>
            <button type="button" class="period-btn" data-period="month">This month</button>
        </div>

        <div class="period-filter" id="statusFilter">
            <button type="button" class="period-btn active" data-status="all">All status</button>
            <button type="button" class="period-btn" data-status="completed">Completed</button>
            <button type="button" class="period-btn" data-status="not_completed">Not completed</button>
        </div>

        <select id="typeFilter" class="title-select" title="Job type">
            <option value="all" selected>All job types</option>
            <?php foreach ($typeFilterList as $gType): ?>
            <option value="<?= htmlspecialchars($gType, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($gType) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <label class="group-toggle">
            <input type="checkbox" id="groupByType" checked>
            Group by job type
        </label>

        <span class="filter-meta" id="filterMeta">Showing <?= $totalRecords ?> records</span>
    </div>
    
    <!-- Table Card -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table" id="reportTable">
                <thead>
                    <tr>
                        <?php if ($isSuperAdmin): ?>
                        <th class="sortable" onclick="sortTable(0)" style="min-width: 150px;">
                            Admin <i class="bi bi-arrow-down-up"></i>
                        </th>
                        <?php endif; ?>
                        
                        <th class="sortable" onclick="sortTable(<?= $isSuperAdmin ? 1 : 0 ?>)" style="min-width: 180px;">
                            Job Type <i class="bi bi-arrow-down-up"></i>
                        </th>
                        <th class="sortable" onclick="sortTable(<?= $isSuperAdmin ? 2 : 1 ?>)" style="min-width: 200px;">
                            Job Title <i class="bi bi-arrow-down-up"></i>
                        </th>
                        <th style="min-width: 250px;">Description</th>
                        <th class="sortable" onclick="sortTable(<?= $isSuperAdmin ? 4 : 3 ?>)" style="min-width: 100px;">
                            Hours <i class="bi bi-arrow-down-up"></i>
                        </th>
                        <th class="sortable" onclick="sortTable(<?= $isSuperAdmin ? 5 : 4 ?>)" style="min-width: 100px;">
                            Score <i class="bi bi-arrow-down-up"></i>
                        </th>
                        <th class="sortable" onclick="sortTable(<?= $isSuperAdmin ? 6 : 5 ?>)" style="min-width: 120px;">
                            Status <i class="bi bi-arrow-down-up"></i>
                        </th>
                        <th style="min-width: 80px;">Screenshot</th>
                        <th class="sortable" onclick="sortTable(<?= $isSuperAdmin ? 8 : 7 ?>)" style="min-width: 150px;">
                            Date <i class="bi bi-arrow-down-up"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="reportBody">
                    <?php if ($totalRecords > 0): ?>
                        <?php foreach ($reportRows as $r): ?>
                        <?php
                            $createdRaw = $r['created_at'] ?? '';
                            $createdTs = $createdRaw ? strtotime($createdRaw) : 0;
                            $dayKey = $createdTs ? date('Y-m-d', $createdTs) : '';
                            $groupKey = pcvc_resolve_report_job_type($r['list_job_type'] ?? null, $r['job_title'] ?? '');
                            $displayTitle = pcvc_report_display_title($r);
                            $descriptionText = pcvc_report_job_description($r, $displayTitle);
                            $statusInfo = pcvc_report_job_status($r);
                            $searchBlob = strtolower(implode(' ', [
                                $groupKey,
                                $displayTitle,
                                $statusInfo['label'],
                                $r['job_title'] ?? '',
                                $descriptionText,
                                $r['applicant_name'] ?? '',
                                $r['applicant_email'] ?? '',
                                $r['remarks'] ?? '',
                                $r['ai_suggestions'] ?? '',
                                (string) ($r['hours_spent'] ?? ''),
                                (string) ($r['productivity_score'] ?? ''),
                                $createdRaw,
                            ]));
                            $scoreVal = (int) ($r['productivity_score'] ?? 0);
                        ?>
                        <tr class="report-row"
                            data-group="<?= htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8') ?>"
                            data-status="<?= htmlspecialchars($statusInfo['key'], ENT_QUOTES, 'UTF-8') ?>"
                            data-day="<?= htmlspecialchars($dayKey, ENT_QUOTES, 'UTF-8') ?>"
                            data-ts="<?= (int) $createdTs ?>"
                            data-score="<?= $scoreVal ?>"
                            data-hours="<?= (float) ($r['hours_spent'] ?? 0) ?>"
                            data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($isSuperAdmin): ?>
                            <td>
                                <span class="admin-badge">
                                    <i class="bi bi-person-circle"></i>
                                    <?= htmlspecialchars($r['full_name'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <?php endif; ?>
                            
                            <?php
                                $score = (int) ($r['productivity_score'] ?? 0);
                                $scoreClass = $score >= 75 ? 'score-high' : ($score >= 40 ? 'score-mid' : 'score-low');
                            ?>
                            <td>
                                <span class="badge bg-light text-dark">
                                    <?= htmlspecialchars($groupKey) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($displayTitle) ?></strong>
                            </td>
                            <td class="description-cell" title="<?= htmlspecialchars($descriptionText) ?>">
                                <?= nl2br(htmlspecialchars($descriptionText)) ?>
                            </td>
                            <td>
                                <span class="hours-display">
                                    <?= number_format($r['hours_spent'] ?? 0, 2) ?>
                                </span>
                            </td>
                            <td>
                                <span class="score-badge <?= $scoreClass ?>">
                                    <?= $score ?>%
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?= htmlspecialchars($statusInfo['class']) ?>">
                                    <?= htmlspecialchars($statusInfo['label']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($r['screenshot_path'])): ?>
                                <div class="thumb-container" onclick="window.open('<?= htmlspecialchars($r['screenshot_path'], ENT_QUOTES, 'UTF-8') ?>', '_blank')">
                                    <img src="<?= htmlspecialchars($r['screenshot_path'], ENT_QUOTES, 'UTF-8') ?>"
                                         alt="Completion screenshot" class="thumb">
                                </div>
                                <?php else: ?>
                                <div class="thumb-placeholder" title="No screenshot uploaded">
                                    <i class="bi bi-image"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="date-display">
                                    <span class="date-main"><?= date('M d, Y', strtotime($r['created_at'] ?? '')) ?></span>
                                    <span class="date-small"><?= date('H:i', strtotime($r['created_at'] ?? '')) ?></span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="<?= $tableColCount ?>">
                            <div class="empty-state">
                                <div class="empty-icon">📊</div>
                                <div class="empty-title">No Records Found</div>
                                <div class="empty-text">No job records available for this staff member</div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Back Link -->
    <a href="admin-dashboard.php" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
    const tbody = document.getElementById('reportBody');
    if (!tbody) return;

    const typeFilter = document.getElementById('typeFilter');
    const groupByTypeEl = document.getElementById('groupByType');
    const periodFilter = document.getElementById('periodFilter');
    const statusFilter = document.getElementById('statusFilter');
    const filterMeta = document.getElementById('filterMeta');
    const colSpan = <?= (int) $tableColCount ?>;

    let currentPeriod = 'all';
    let currentStatus = 'all';
    const collapsedGroups = new Set();
    const sortDirections = {};

    function getDataRows() {
        return Array.from(tbody.querySelectorAll('tr.report-row'));
    }

    function getPeriodBounds(period) {
        const now = new Date();
        const start = new Date(now);
        const end = new Date(now);
        end.setHours(23, 59, 59, 999);

        if (period === 'day') {
            start.setHours(0, 0, 0, 0);
        } else if (period === 'week') {
            const day = now.getDay();
            const diff = day === 0 ? 6 : day - 1;
            start.setDate(now.getDate() - diff);
            start.setHours(0, 0, 0, 0);
        } else if (period === 'month') {
            start.setDate(1);
            start.setHours(0, 0, 0, 0);
        } else {
            return null;
        }

        return { start: Math.floor(start.getTime() / 1000), end: Math.floor(end.getTime() / 1000) };
    }

    function rowInPeriod(row, period) {
        if (period === 'all') return true;
        const bounds = getPeriodBounds(period);
        if (!bounds) return true;
        const ts = parseInt(row.dataset.ts || '0', 10);
        return ts >= bounds.start && ts <= bounds.end;
    }

    function rowMatchesTitle(row, title) {
        if (!title || title === 'all') return true;
        return (row.dataset.group || '') === title;
    }

    function rowMatchesStatus(row, status) {
        if (!status || status === 'all') return true;
        return (row.dataset.status || '') === status;
    }

    function getSelectedJobType() {
        if (!typeFilter) return 'all';
        const value = typeFilter.value;
        return value === '' ? 'all' : value;
    }

    function removeGroupHeaders() {
        tbody.querySelectorAll('tr.job-group-header').forEach(h => h.remove());
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function createGroupHeader(groupName, count, totalHours, avgScore) {
        const tr = document.createElement('tr');
        tr.className = 'job-group-header' + (collapsedGroups.has(groupName) ? ' collapsed' : '');
        tr.dataset.groupHeader = groupName;

        const meta = [count + ' record' + (count !== 1 ? 's' : '')];
        meta.push(totalHours.toFixed(2) + ' hrs');
        if (avgScore !== null) meta.push('avg ' + Math.round(avgScore) + '%');

        tr.innerHTML =
            '<td colspan="' + colSpan + '">' +
            '<i class="bi bi-chevron-down group-chevron"></i>' +
            '<span class="group-title">' + escapeHtml(groupName) + '</span>' +
            '<span class="group-meta">' + meta.join(' · ') + '</span>' +
            '</td>';

        tr.addEventListener('click', function () {
            if (collapsedGroups.has(groupName)) {
                collapsedGroups.delete(groupName);
                tr.classList.remove('collapsed');
            } else {
                collapsedGroups.add(groupName);
                tr.classList.add('collapsed');
            }
            applyGroupingVisibility();
        });

        return tr;
    }

    function applyGroupingVisibility() {
        tbody.querySelectorAll('tr.job-group-header').forEach(function (header) {
            const group = header.dataset.groupHeader;
            const collapsed = collapsedGroups.has(group);
            let next = header.nextElementSibling;
            while (next && !next.classList.contains('job-group-header')) {
                if (next.classList.contains('report-row') && !next.classList.contains('hidden-by-filter')) {
                    next.classList.toggle('hidden-by-group', collapsed);
                }
                next = next.nextElementSibling;
            }
        });
    }

    function rebuildGroups(visibleRows) {
        removeGroupHeaders();

        if (!groupByTypeEl || !groupByTypeEl.checked) {
            visibleRows.forEach(function (row) {
                row.classList.remove('hidden-by-group');
                tbody.appendChild(row);
            });
            return;
        }

        const groups = new Map();
        visibleRows.forEach(function (row) {
            const g = row.dataset.group || 'Untitled';
            if (!groups.has(g)) groups.set(g, []);
            groups.get(g).push(row);
        });

        const sortedKeys = Array.from(groups.keys()).sort(function (a, b) {
            return a.localeCompare(b, undefined, { sensitivity: 'base' });
        });

        sortedKeys.forEach(function (groupName) {
            const rows = groups.get(groupName);
            let totalHours = 0;
            let scoreSum = 0;

            rows.forEach(function (r) {
                totalHours += parseFloat(r.dataset.hours || '0');
                scoreSum += parseInt(r.dataset.score || '0', 10);
            });

            const avgScore = rows.length ? scoreSum / rows.length : null;
            tbody.appendChild(createGroupHeader(groupName, rows.length, totalHours, avgScore));

            rows.forEach(function (r) {
                r.classList.toggle('hidden-by-group', collapsedGroups.has(groupName));
                tbody.appendChild(r);
            });
        });
    }

    function updateStats(visibleRows) {
        const total = visibleRows.length;
        const statTotal = document.getElementById('statTotal');
        if (statTotal) statTotal.textContent = total;

        const groups = new Set(visibleRows.map(function (r) { return r.dataset.group; }));
        const statGroups = document.getElementById('statGroups');
        if (statGroups) statGroups.textContent = groups.size;

        let high = 0, mid = 0, low = 0;
        visibleRows.forEach(function (r) {
            const sc = parseInt(r.dataset.score || '0', 10);
            if (sc >= 75) high++;
            else if (sc >= 40) mid++;
            else low++;
        });
        const elHigh = document.getElementById('statHigh');
        const elMid = document.getElementById('statMid');
        const elLow = document.getElementById('statLow');
        if (elHigh) elHigh.textContent = high;
        if (elMid) elMid.textContent = mid;
        if (elLow) elLow.textContent = low;

        const periodLabels = { all: 'all time', day: 'today', week: 'this week', month: 'this month' };
        const statusLabels = { all: '', completed: 'completed', not_completed: 'not completed' };
        let meta = 'Showing ' + total + ' record' + (total !== 1 ? 's' : '');
        if (currentPeriod !== 'all') meta += ' (' + (periodLabels[currentPeriod] || currentPeriod) + ')';
        if (currentStatus !== 'all') meta += ' · ' + (statusLabels[currentStatus] || currentStatus);
        const selectedType = getSelectedJobType();
        if (selectedType && selectedType !== 'all') meta += ' · ' + selectedType;
        if (filterMeta) filterMeta.textContent = meta;
    }

    function getFilteredRows() {
        const jobType = getSelectedJobType();
        const visibleRows = [];

        getDataRows().forEach(function (row) {
            const visible = rowInPeriod(row, currentPeriod) &&
                rowMatchesTitle(row, jobType) &&
                rowMatchesStatus(row, currentStatus);

            row.classList.toggle('hidden-by-filter', !visible);
            if (!visible) row.classList.remove('hidden-by-group');
            if (visible) visibleRows.push(row);
        });

        return visibleRows;
    }

    function applyFilters() {
        const visibleRows = getFilteredRows();
        rebuildGroups(visibleRows);
        updateStats(visibleRows);
    }

    function compareRows(a, b, columnIndex, asc) {
        let aVal, bVal;
        const aCell = a.cells[columnIndex];
        const bCell = b.cells[columnIndex];
        const headers = document.querySelectorAll('#reportTable thead th');
        const headerText = (headers[columnIndex]?.textContent || '').toLowerCase();

        if (headerText.includes('hour')) {
            aVal = parseFloat(a.dataset.hours || '0');
            bVal = parseFloat(b.dataset.hours || '0');
            return asc ? aVal - bVal : bVal - aVal;
        }
        if (headerText.includes('score')) {
            aVal = parseInt(a.dataset.score || '0', 10);
            bVal = parseInt(b.dataset.score || '0', 10);
            return asc ? aVal - bVal : bVal - aVal;
        }
        if (headerText.includes('date')) {
            aVal = parseInt(a.dataset.ts || '0', 10);
            bVal = parseInt(b.dataset.ts || '0', 10);
            return asc ? aVal - bVal : bVal - aVal;
        }
        if (headerText.includes('status')) {
            aVal = a.dataset.status || '';
            bVal = b.dataset.status || '';
            return asc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        }

        aVal = (aCell?.textContent || '').trim().toLowerCase();
        bVal = (bCell?.textContent || '').trim().toLowerCase();
        return asc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    }

    window.sortTable = function (columnIndex) {
        const visibleRows = getFilteredRows();
        if (!visibleRows.length) return;

        sortDirections[columnIndex] = !sortDirections[columnIndex];
        visibleRows.sort(function (a, b) {
            return compareRows(a, b, columnIndex, !!sortDirections[columnIndex]);
        });

        rebuildGroups(visibleRows);
    };

    if (periodFilter) {
        periodFilter.querySelectorAll('.period-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                periodFilter.querySelectorAll('.period-btn').forEach(function (b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                currentPeriod = btn.dataset.period || 'all';
                applyFilters();
            });
        });
    }

    if (statusFilter) {
        statusFilter.querySelectorAll('.period-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                statusFilter.querySelectorAll('.period-btn').forEach(function (b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                currentStatus = btn.dataset.status || 'all';
                applyFilters();
            });
        });
    }

    if (typeFilter) typeFilter.addEventListener('change', applyFilters);
    if (groupByTypeEl) groupByTypeEl.addEventListener('change', applyFilters);

    window.pcvcApplyJobReportFilters = applyFilters;
    applyFilters();
})();

$(function () {
<?php if ($isSuperAdmin): ?>
    const $adminSelect = $('#adminStaffSelect');
    if ($adminSelect.length) {
        $adminSelect.select2({
            width: '100%',
            placeholder: 'Select staff member',
            allowClear: false
        });

        $adminSelect.on('change', function () {
            $('#adminFilterForm').submit();
        });
    }
<?php endif; ?>

    const $typeFilter = $('#typeFilter');
    if ($typeFilter.length) {
        $typeFilter.select2({
            width: '220px',
            minimumResultsForSearch: Infinity
        });

        if (!$typeFilter.val()) {
            $typeFilter.val('all');
        }

        $typeFilter.on('change', function () {
            if (typeof window.pcvcApplyJobReportFilters === 'function') {
                window.pcvcApplyJobReportFilters();
            }
        });
    }

    if (typeof window.pcvcApplyJobReportFilters === 'function') {
        window.pcvcApplyJobReportFilters();
    }
});
</script>

</body>
</html>