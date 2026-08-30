<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/company_branding.php';
require_once __DIR__ . '/includes/payroll_helpers.php';
require_once __DIR__ . '/includes/momo_phone.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/secure_file.php';
date_default_timezone_set('Africa/Kigali');

$companyName = PCVC_COMPANY_DISPLAY_NAME;
$currency    = PCVC_PAYROLL_CURRENCY;

/* ===========================================================
   AUTHENTICATION & AUTHORIZATION
============================================================ */
$admin_id = $_SESSION['admin_id'] ?? $_SESSION['id'] ?? null;
if (!$admin_id) {
    http_response_code(403);
    exit("🔐 Access denied. Login required.");
}

pcvc_require_superadmin($conn);

$stmt = $conn->prepare("SELECT role, full_name, profile_photo, position FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($user_role, $user_name, $user_photo, $user_position);
$stmt->fetch();
$stmt->close();

$user_role = pcvc_normalize_role_string($user_role ?? '');

$isSuperAdmin = true;

$payrollMomoCsrf = '';
if ($isSuperAdmin) {
    if (empty($_SESSION['payroll_momo_csrf']) || !is_string($_SESSION['payroll_momo_csrf'])) {
        $_SESSION['payroll_momo_csrf'] = bin2hex(random_bytes(32));
    }
    $payrollMomoCsrf = $_SESSION['payroll_momo_csrf'];
}

/* ===========================================================
   DATE FILTERS
============================================================ */
$selected_month = $_GET['month'] ?? date('Y-m');
$start_month    = date('Y-m-01', strtotime($selected_month));
$end_month      = date('Y-m-t',  strtotime($selected_month));

/* Build array of weekdays in the month (Mon-Fri only) */
$all_dates = [];
$cursor = strtotime($start_month);
$endTs  = strtotime($end_month);
while ($cursor <= $endTs) {
    $isoDow = (int)date('N', $cursor);
    if ($isoDow <= 5) {
        $all_dates[] = date('Y-m-d', $cursor);
    }
    $cursor = strtotime('+1 day', $cursor);
}

/* ===========================================================
   LOAD PAYROLL EMPLOYEES (staff + superadmin only)
============================================================ */
$adminCol = [];
$ac = $conn->query('SHOW COLUMNS FROM `admins`');
if ($ac instanceof mysqli_result) {
    while ($r = $ac->fetch_assoc()) {
        if (isset($r['Field'])) {
            $adminCol[(string) $r['Field']] = true;
        }
    }
    $ac->free();
}
$extraAdminFields = [];
foreach (['email', 'phone', 'mobile', 'phone_number'] as $f) {
    if (isset($adminCol[$f])) {
        $extraAdminFields[] = $f;
    }
}

$sqlAdmins = 'SELECT `id`, `full_name`, `role`, `salary_per_minute`, `profile_photo`, `position`';
if ($extraAdminFields !== []) {
    $sqlAdmins .= ', `' . implode('`, `', $extraAdminFields) . '`';
}
$sqlAdmins .= ' FROM `admins` WHERE ' . pcvc_sql_is_payroll_employee_expr('`role`') . ' ORDER BY `full_name` ASC';

$admins = [];
$res = $conn->query($sqlAdmins);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $days = [];
        foreach ($all_dates as $d) {
            $days[$d] = [
                'minutes' => 0,
                'salary'  => 0.0,
                'check_in' => null,
                'check_out' => null,
                'break' => 0,
            ];
        }
        $admins[(int) $row['id']] = [
            'id'    => (int) $row['id'],
            'name'  => $row['full_name'],
            'role'  => $row['role'],
            'rate'  => (float) $row['salary_per_minute'],
            'photo' => $row['profile_photo'],
            'position' => $row['position'] ?? '—',
            'email' => isset($row['email']) ? trim((string) $row['email']) : '',
            'phone' => pcvc_admin_phone_raw_from_row($row),
            'total_minutes' => 0,
            'total_salary'  => 0.0,
            'days_present'  => 0,
            'days'  => $days,
        ];
    }
    $res->free();
}

foreach ($admins as &$admin) {
    $rawP = isset($admin['phone']) ? trim((string) $admin['phone']) : '';
    $admin['momo_msisdn'] = $rawP !== '' ? pcvc_normalize_rw_momo_msisdn($rawP) : null;
    $admin['momo_display'] = $admin['momo_msisdn'] !== null
        ? pcvc_format_rw_momo_display($admin['momo_msisdn'])
        : '—';
}
unset($admin);

/* ===========================================================
   LOAD ATTENDANCE (deduped by admin + date; includes clock times when present)
============================================================ */
$attByAdmin = pcvc_payroll_load_attendance_by_admin($conn, $start_month, $end_month);

foreach ($admins as $aid => &$admin) {
    if (!isset($attByAdmin[$aid])) {
        continue;
    }
    foreach ($attByAdmin[$aid] as $date => $rec) {
        $isoDow = (int) date('N', strtotime($date));
        if ($isoDow >= 6) {
            continue;
        }
        if (!isset($admin['days'][$date])) {
            continue;
        }
        $mins = (int) ($rec['minutes'] ?? 0);
        $stored = (float) ($rec['stored_daily_pay'] ?? 0);
        $rate = $admin['rate'];
        $calculated = (int) round($rate * $mins);
        // Prefer amount saved at checkout (matches attendance rules); else rate × minutes
        $salary = ((int) round($stored)) > 0 ? (int) round($stored) : $calculated;

        $admin['days'][$date]['minutes'] = $mins;
        $admin['days'][$date]['salary']  = (float) $salary;
        $admin['days'][$date]['check_in'] = $rec['check_in'] ?? null;
        $admin['days'][$date]['check_out'] = $rec['check_out'] ?? null;
        $admin['days'][$date]['break'] = (int) ($rec['break'] ?? 0);

        $admin['total_minutes'] += $mins;
        $admin['total_salary']  += $salary;
        if ($mins > 0 || $salary > 0) {
            $admin['days_present']++;
        }
    }
}
unset($admin);

/* ===========================================================
   CALCULATE TOTALS
============================================================ */
$grand_total_minutes = 0;
$grand_total_salary = 0;
foreach ($admins as $a) {
    $grand_total_minutes += $a['total_minutes'];
    $grand_total_salary += $a['total_salary'];
}

$payPeriodLabel = date('j M Y', strtotime($start_month)) . ' – ' . date('j M Y', strtotime($end_month));
$weekdaysInPeriod = count($all_dates);
$generatedAt        = date('Y-m-d H:i T');

$payrollEmployeeCount = count($admins);
$searchInitial        = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

/* ===========================================================
   HELPERS
============================================================ */
function rwf($n) { return number_format((float)$n, 0); }
function ratefmt($n) { return number_format((float)$n, 2); }

$payrollDefaultAvatarDataUri = 'data:image/svg+xml,' . rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" aria-hidden="true">'
    . '<circle cx="48" cy="48" r="48" fill="#e8eef5"/>'
    . '<circle cx="48" cy="34" r="15" fill="#94a3b8"/>'
    . '<ellipse cx="48" cy="78" rx="28" ry="20" fill="#94a3b8" fill-opacity=".45"/>'
    . '</svg>'
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($companyName) ?> — Payroll register</title>
    
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

        html {
            overflow-x: hidden;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(180deg, var(--white) 0%, #f0f4f8 100%);
            color: var(--text-dark);
            min-height: 100vh;
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* ===== Payroll header (company-branded) ===== */
        .payroll-brand-header {
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
            font-size: clamp(1.1rem, 2.2vw, 1.65rem);
            font-weight: 800;
            color: var(--white);
            letter-spacing: 0.5px;
            position: relative;
            display: inline-block;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            max-width: min(920px, 72vw);
            line-height: 1.2;
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
            min-height: min(100%, 100vh);
            padding: 20px 24px 32px 24px;
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

        .user-info {
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

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 700;
            color: var(--deep-navy);
            font-size: 16px;
        }

        .user-role {
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

        /* ===== TOOLBAR ===== */
        .toolbar {
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

        .month-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .month-input {
            padding: 10px 12px;
            border: 2px solid var(--border-light);
            border-radius: 8px;
            font-size: 14px;
        }

        .month-input:focus {
            outline: none;
            border-color: var(--gold);
        }

        .payroll-search-bar {
            background: var(--white);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(1, 47, 107, 0.08);
            border: 1px solid var(--border-light);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }

        .payroll-search-wrap {
            flex: 1;
            min-width: 220px;
            position: relative;
        }

        .payroll-search-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }

        .payroll-search-input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 2px solid var(--border-light);
            border-radius: 10px;
            font-size: 15px;
        }

        .payroll-search-input:focus {
            outline: none;
            border-color: var(--gold);
        }

        .payroll-search-meta {
            font-size: 13px;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .payroll-employee-card.payroll-search-hidden {
            display: none !important;
        }

        #payroll-no-matches {
            display: none;
            padding: 2rem;
            text-align: center;
            color: var(--text-muted);
            background: var(--light-bg);
            border-radius: 12px;
            border: 1px dashed var(--border-light);
        }

        #payroll-no-matches.visible {
            display: block;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--deep-navy) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(1, 47, 107, 0.2);
        }

        .btn-secondary {
            background: var(--light-bg);
            color: var(--text-muted);
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid var(--border-light);
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: var(--border-light);
            color: var(--text-dark);
        }

        .btn-group {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        /* ===== CARDS ===== (page scrolls; list grows so all employees are reachable) ===== */
        .cards-container {
            flex: 1 1 auto;
            min-height: 0;
            overflow: visible;
            padding-right: 4px;
            padding-bottom: 24px;
        }

        .card {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border-light);
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(1, 47, 107, 0.05);
            overflow: hidden;
        }

        .summary-card {
            background: linear-gradient(135deg, var(--white) 0%, var(--light-bg) 100%);
        }

        .card-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 16px 20px;
            border-bottom: 2px solid var(--gold);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .card-header:hover {
            background: linear-gradient(135deg, #f1f5f9 0%, #e9edf2 100%);
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-name {
            font-weight: 700;
            color: var(--deep-navy);
            font-size: 16px;
        }

        .employee-meta {
            display: flex;
            gap: 16px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .employee-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .employee-meta i {
            color: var(--gold);
        }

        /* Payroll rows: one horizontal line; long names ellipsis; meta scrolls */
        .payroll-employee-card .payroll-card-header {
            flex-wrap: nowrap;
            align-items: center;
            gap: 10px;
            min-height: 72px;
        }

        .payroll-employee-card .employee-info {
            flex: 1 1 0;
            min-width: 0;
            align-items: center;
        }

        .payroll-employee-card .payroll-identity-text {
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
        }

        .payroll-employee-card .employee-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .payroll-employee-card .payroll-employee-meta {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 12px 14px;
            overflow-x: auto;
            overflow-y: hidden;
            max-width: 100%;
            padding-bottom: 2px;
            scrollbar-width: thin;
        }

        .payroll-employee-card .payroll-employee-meta::-webkit-scrollbar {
            height: 4px;
        }

        .payroll-employee-card .payroll-employee-meta span {
            flex-shrink: 0;
            white-space: nowrap;
        }

        .payroll-employee-card .payroll-card-actions {
            flex-shrink: 0;
            flex-wrap: nowrap;
            align-items: center;
        }

        .salary-pill {
            background: linear-gradient(135deg, var(--success) 0%, #1b5e20 100%);
            color: var(--white);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .salary-pill i {
            font-size: 16px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            background: var(--white);
            border: 1px solid var(--border-light);
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-icon:hover {
            background: var(--border-light);
            color: var(--text-dark);
        }

        .btn-icon i {
            font-size: 14px;
        }

        .payroll-momo-cb {
            display: inline-flex;
            align-items: center;
            margin-right: 4px;
        }

        .payroll-momo-cb input {
            width: 18px;
            height: 18px;
            accent-color: var(--gold);
            cursor: pointer;
        }

        .payroll-momo-amt {
            width: 104px;
            padding: 6px 8px;
            border-radius: 8px;
            border: 2px solid var(--border-light);
            font-size: 13px;
            font-weight: 600;
            text-align: right;
        }

        .payroll-momo-amt:focus {
            outline: none;
            border-color: var(--gold);
        }

        .payroll-momo-one {
            background: var(--deep-navy) !important;
            color: var(--white) !important;
            border-color: transparent !important;
        }

        .payroll-momo-one:hover {
            opacity: 0.92;
        }

        /* ===== TABLES ===== */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f6 100%);
            color: var(--deep-navy);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 12px;
            border-bottom: 2px solid var(--gold);
        }

        td {
            padding: 12px;
            border-bottom: 1px solid var(--border-light);
        }

        tfoot td {
            background: #f8fafc;
            font-weight: 700;
            border-top: 2px solid var(--gold);
        }

        .text-right {
            text-align: right;
        }

        .text-success {
            color: var(--success);
            font-weight: 700;
        }

        /* Summary table specific */
        .summary-table th {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            color: var(--white);
        }

        .summary-table th:first-child {
            border-radius: 8px 0 0 0;
        }

        .summary-table th:last-child {
            border-radius: 0 8px 0 0;
        }

        /* Detail tables */
        .detail-table {
            margin: 16px;
            width: calc(100% - 32px);
        }

        .detail-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f6 100%);
        }

        /* Present badge */
        .present-badge {
            background: var(--high-bg, #dcfce7);
            color: var(--high-text, #166534);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
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

        /* Collapse/Expand */
        .collapse {
            display: none;
        }

        .collapse.open {
            display: block;
        }

        /* Print styles */
        @media print {
            .toolbar,
            .action-buttons,
            .btn-icon,
            .payroll-momo-cb,
            .payroll-momo-amt,
            .payroll-momo-toolbar {
                display: none !important;
            }
            
            .main-container {
                height: auto;
                padding: 0;
            }
            
            .card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            body.printing .card:not(.print-target) {
                display: none !important;
            }
            
            body.printing .print-target {
                display: block !important;
            }
            
            th {
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        /* Responsive */
        .pcvc-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 10050;
            background: rgba(15, 23, 42, 0.45);
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .pcvc-modal-overlay.open {
            display: flex;
        }

        .pcvc-modal-panel {
            background: var(--white);
            border-radius: 14px;
            max-width: 520px;
            width: 100%;
            max-height: 85vh;
            overflow: auto;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18);
            border: 1px solid var(--border-light);
        }

        .pcvc-modal-head {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border-light);
            font-weight: 700;
            color: var(--deep-navy);
            font-size: 17px;
        }

        .pcvc-modal-body {
            padding: 16px 20px;
            font-size: 14px;
            color: var(--text-dark);
            min-height: 120px;
        }

        .payroll-momo-intro {
            margin: 0 0 14px 0;
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.45;
        }

        .payroll-momo-recipient-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .payroll-momo-mini-card {
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 12px 14px;
            background: linear-gradient(180deg, #fafbfc 0%, #fff 100%);
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        }

        .payroll-momo-mini-card .payroll-momo-msisdn {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            color: var(--deep-navy);
            font-weight: 600;
        }

        .payroll-momo-mini-card .payroll-momo-amt-line {
            margin-top: 6px;
            font-size: 14px;
        }

        .payroll-momo-mini-card .payroll-momo-amt-line strong {
            color: var(--success);
        }

        .payroll-momo-modal-err {
            margin: 12px 0 0 0;
            padding: 10px 12px;
            border-radius: 10px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--danger);
            font-size: 13px;
        }

        .payroll-momo-busy {
            text-align: center;
            padding: 28px 16px 20px;
        }

        .payroll-momo-spinner {
            width: 48px;
            height: 48px;
            margin: 0 auto 16px;
            border: 3px solid var(--border-light);
            border-top-color: var(--deep-navy);
            border-radius: 50%;
            animation: payroll-momo-spin 0.85s linear infinite;
        }

        @keyframes payroll-momo-spin {
            to { transform: rotate(360deg); }
        }

        .payroll-momo-busy-text {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .payroll-momo-busy-hint {
            margin: 8px 0 0 0;
            font-size: 12px;
            color: var(--text-muted);
        }

        .payroll-momo-done {
            text-align: center;
            padding: 8px 0 4px;
        }

        .payroll-momo-done-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 12px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .payroll-momo-done-icon.ok {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
        }

        .payroll-momo-done-icon.fail {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
        }

        .payroll-momo-done-title {
            margin: 0 0 12px 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--deep-navy);
        }

        .payroll-momo-done-body {
            text-align: left;
            max-height: 220px;
            overflow-y: auto;
            font-size: 13px;
            line-height: 1.5;
            padding: 10px 12px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid var(--border-light);
        }

        .payroll-momo-done-line {
            padding: 6px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .payroll-momo-done-line:last-child {
            border-bottom: none;
        }

        .payroll-momo-done-line.ok {
            color: #166534;
        }

        .payroll-momo-done-line.fail {
            color: #991b1b;
        }

        .pcvc-modal-foot {
            padding: 14px 20px 18px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
            border-top: 1px solid var(--border-light);
            background: #fafafa;
        }

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
            
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-group {
                margin-left: 0;
            }
            
            .payroll-employee-card .payroll-card-header {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>
</head>

<body>

<div class="main-container payroll-cards-only">
    
    <div class="page-header" style="margin-bottom: 12px;">
        <div class="user-info">
            <div class="profile-img-container">
                <img src="<?= !empty($user_photo) ? htmlspecialchars(pcvc_profile_photo_url($user_photo)) : htmlspecialchars($payrollDefaultAvatarDataUri, ENT_QUOTES, 'UTF-8') ?>"
                     alt=""
                     class="profile-img"
                     width="44"
                     height="44"
                     loading="eager"
                     decoding="async"
                     <?php if (!empty($user_photo)): ?>
                     onerror="this.onerror=null;this.src='<?= htmlspecialchars($payrollDefaultAvatarDataUri, ENT_QUOTES, 'UTF-8') ?>'"
                     <?php endif; ?>>
            </div>
            <div class="user-details">
                <span class="page-title" style="margin:0;font-size:22px;">Payroll</span>
                <span class="user-role"><?= htmlspecialchars($companyName) ?> · <?= htmlspecialchars($user_name ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <p class="text-muted small mb-2" style="margin: 0 0 8px 0; font-size: 13px;">
        <strong>Pay period:</strong> <?= htmlspecialchars($payPeriodLabel) ?>
        &nbsp;·&nbsp; <strong>Weekdays in period:</strong> <?= (int) $weekdaysInPeriod ?>
        &nbsp;·&nbsp; <strong>Roles:</strong> staff &amp; superadmin (<?= (int) $payrollEmployeeCount ?>)
        &nbsp;·&nbsp; <strong>Currency:</strong> <?= htmlspecialchars($currency) ?>
        &nbsp;·&nbsp; <strong>Generated:</strong> <?= htmlspecialchars($generatedAt) ?>
    </p>

    <!-- Toolbar -->
    <div class="toolbar">
        <form method="get" class="month-selector" id="payrollMonthForm">
            <label for="month" class="fw-semibold">Select Month:</label>
            <input type="month" id="month" name="month" class="month-input" 
                   value="<?= htmlspecialchars($selected_month) ?>">
            <input type="hidden" name="q" id="payrollMonthHiddenQ" value="<?= htmlspecialchars($searchInitial, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn-primary">
                <i class="bi bi-funnel"></i> Filter
            </button>
        </form>
        
        <div class="btn-group align-items-center" style="gap:12px; flex-wrap:wrap;">
            <span id="payrollPeriodPill" class="salary-pill" style="white-space:nowrap;" title="All staff &amp; superadmin in this period (attendance)">
                <i class="bi bi-cash-stack"></i>
                Period total: <?= rwf($grand_total_salary) ?> <?= htmlspecialchars($currency) ?>
                · <?= number_format($grand_total_minutes) ?> min
            </span>
            <span id="payrollVisiblePill" class="salary-pill" style="display:none; white-space:nowrap; background:linear-gradient(135deg, #5c6bc0 0%, #3949ab 100%);" title="Filtered rows only">
                <i class="bi bi-funnel-fill"></i>
                <span id="payrollVisiblePillInner"></span>
            </span>
            <button type="button" class="btn-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print Page
            </button>
            <button type="button" class="btn-secondary" onclick="toggleAll(true)">
                <i class="bi bi-arrows-expand"></i> Expand All
            </button>
            <button type="button" class="btn-secondary" onclick="toggleAll(false)">
                <i class="bi bi-arrows-collapse"></i> Collapse All
            </button>
            <?php if ($isSuperAdmin): ?>
            <div class="payroll-momo-toolbar" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-left:8px;">
                <label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;color:var(--text-dark);cursor:pointer;user-select:none;">
                    <input type="checkbox" id="payrollSelectAllVisible" style="width:18px;height:18px;accent-color:var(--gold);" />
                    Select visible
                </label>
                <button type="button" class="btn-primary" id="payrollMomoPayNowBtn" title="Pay selected employees via MoMo (merchant wallet)">
                    <i class="bi bi-phone"></i> Pay now (MoMo)
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="payroll-search-bar">
        <div class="payroll-search-wrap">
            <i class="bi bi-search"></i>
            <input type="search"
                   id="payrollSearchInput"
                   class="payroll-search-input"
                   placeholder="Search by name, email, phone, employee ID, role, or position…"
                   value="<?= htmlspecialchars($searchInitial, ENT_QUOTES, 'UTF-8') ?>"
                   autocomplete="off"
                   aria-label="Search employees">
        </div>
        <span id="payrollSearchMeta" class="payroll-search-meta"></span>
        <button type="button" class="btn-secondary" id="payrollSearchClear" style="display:none;">Clear</button>
    </div>
    
    <!-- Employee cards (primary view) -->
    <div class="cards-container" id="payrollCardsContainer">

        <div id="payroll-no-matches">
            <i class="bi bi-search" style="font-size:2rem;opacity:.5;"></i>
            <p class="mb-0 mt-2">No employees match your search. Try another name, email, or ID.</p>
        </div>

        <!-- Per-Admin Detail Cards -->
        <?php foreach ($admins as $a): ?>
        <?php
        $searchBlob = strtolower(trim(
            ($a['name'] ?? '') . ' ' .
            ($a['email'] ?? '') . ' ' .
            ($a['phone'] ?? '') . ' ' .
            ($a['position'] ?? '') . ' ' .
            ($a['role'] ?? '') . ' ' .
            (string) ($a['id'] ?? '')
        ));
        ?>
        <div class="card payroll-employee-card"
             id="card-<?= $a['id'] ?>"
             data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>"
             data-salary="<?= (int) round((float) ($a['total_salary'] ?? 0)) ?>"
             data-minutes="<?= (int) ($a['total_minutes'] ?? 0) ?>"
             data-momo-ok="<?= ($a['momo_msisdn'] !== null) ? '1' : '0' ?>"
             data-momo-msisdn="<?= $a['momo_msisdn'] !== null ? htmlspecialchars((string) $a['momo_msisdn'], ENT_QUOTES, 'UTF-8') : '' ?>"
             data-admin-name="<?= htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="card-header payroll-card-header" onclick="toggleDetails(<?= $a['id'] ?>)">
                <div class="employee-info">
                    <?php if ($isSuperAdmin): ?>
                    <label class="payroll-momo-cb" onclick="event.stopPropagation();" title="Include in Pay now">
                        <input type="checkbox" class="payroll-momo-sel" data-admin-id="<?= (int) $a['id'] ?>" />
                    </label>
                    <?php endif; ?>
                    <div class="profile-img-container">
                        <img src="<?= !empty($a['photo']) ? htmlspecialchars(pcvc_profile_photo_url($a['photo'])) : htmlspecialchars($payrollDefaultAvatarDataUri, ENT_QUOTES, 'UTF-8') ?>"
                             alt=""
                             class="profile-img"
                             width="44"
                             height="44"
                             loading="lazy"
                             decoding="async"
                             <?php if (!empty($a['photo'])): ?>
                             onerror="this.onerror=null;this.src='<?= htmlspecialchars($payrollDefaultAvatarDataUri, ENT_QUOTES, 'UTF-8') ?>'"
                             <?php endif; ?>>
                    </div>
                    <div class="payroll-identity-text">
                        <div class="employee-name" title="<?= htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($a['name']) ?></div>
                        <div class="employee-meta payroll-employee-meta">
                            <?php if (!empty($a['email'])): ?>
                            <span title="Email"><i class="bi bi-envelope"></i> <?= htmlspecialchars($a['email']) ?></span>
                            <?php endif; ?>
                            <span title="MoMo number (Rwanda)"><i class="bi bi-phone"></i> <?= htmlspecialchars($a['momo_display']) ?></span>
                            <?php if ($a['momo_msisdn'] === null && !empty($a['phone'])): ?>
                            <span title="Stored phone" style="color:var(--warning);"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($a['phone']) ?> (fix to 250…)</span>
                            <?php elseif ($a['momo_msisdn'] === null): ?>
                            <span style="color:var(--text-muted);"><i class="bi bi-phone"></i> Add phone in profile</span>
                            <?php endif; ?>
                            <span><i class="bi bi-briefcase"></i> <?= htmlspecialchars((string) $a['position']) ?></span>
                            <span><i class="bi bi-person-badge"></i> <?= ucfirst($a['role']) ?></span>
                            <span><i class="bi bi-cash"></i> <?= ratefmt($a['rate']) ?> <?= htmlspecialchars($currency) ?>/min</span>
                            <span><i class="bi bi-clock"></i> <?= htmlspecialchars(pcvc_payroll_format_hm((int) $a['total_minutes'])) ?> (<?= number_format($a['total_minutes']) ?> min)</span>
                            <span><i class="bi bi-calendar-check"></i> <?= (int) ($a['days_present'] ?? 0) ?> days worked</span>
                        </div>
                    </div>
                </div>
                <div class="action-buttons payroll-card-actions" onclick="event.stopPropagation()">
                    <?php if ($isSuperAdmin): ?>
                    <input type="number"
                           class="payroll-momo-amt"
                           min="0"
                           step="1"
                           data-admin-id="<?= (int) $a['id'] ?>"
                           value="<?= (int) round((float) ($a['total_salary'] ?? 0)) ?>"
                           title="Amount to send (<?= htmlspecialchars($currency) ?>) — edit before Pay" />
                    <button type="button" class="btn-icon payroll-momo-one" data-admin-id="<?= (int) $a['id'] ?>" title="Pay this employee only">
                        <i class="bi bi-cash-coin"></i> Pay
                    </button>
                    <?php endif; ?>
                    <button class="btn-icon" onclick="toggleDetails(<?= $a['id'] ?>)">
                        <i class="bi bi-chevron-down"></i> Details
                    </button>
                    <button class="btn-icon" onclick="printDetails(<?= $a['id'] ?>)">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <span class="salary-pill">
                        <i class="bi bi-cash"></i> <?= rwf($a['total_salary']) ?> <?= htmlspecialchars($currency) ?>
                    </span>
                </div>
            </div>
            
            <div class="collapse" id="details-<?= $a['id'] ?>">
                <div class="print-scope">
                    <div style="padding: 16px 16px 0 16px;">
                        <h5 class="fw-bold" style="color: var(--deep-navy);">
                            <i class="bi bi-calendar-week"></i> 
                            Daily Breakdown - <?= date('F Y', strtotime($selected_month)) ?>
                        </h5>
                        <p class="text-muted small">
                            Weekdays only (Mon–Fri). Gross pay uses checkout amounts from attendance when saved; otherwise <strong>rate × work minutes</strong> (<?= htmlspecialchars($currency) ?>/min).
                        </p>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="detail-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Weekday</th>
                                    <th class="text-right">Check-in</th>
                                    <th class="text-right">Check-out</th>
                                    <th class="text-right">Break</th>
                                    <th class="text-right">Work (min)</th>
                                    <th class="text-right">Work (h:mm)</th>
                                    <th class="text-right">Daily pay (<?= htmlspecialchars($currency) ?>)</th>
                                    <th>Attendance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $hasData = false;
                                foreach ($all_dates as $d):
                                    $dayRow = $a['days'][$d] ?? ['minutes' => 0, 'salary' => 0, 'check_in' => null, 'check_out' => null, 'break' => 0];
                                    $mins   = (int) ($dayRow['minutes'] ?? 0);
                                    $salary = (float) ($dayRow['salary'] ?? 0);
                                    $brk    = (int) ($dayRow['break'] ?? 0);
                                    $cin    = $dayRow['check_in'] ?? null;
                                    $cout   = $dayRow['check_out'] ?? null;
                                    if ($mins > 0 || $salary > 0) {
                                        $hasData = true;
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-calendar3 me-2" style="color: var(--gold);"></i>
                                        <?= date('M j, Y', strtotime($d)) ?>
                                    </td>
                                    <td><?= date('l', strtotime($d)) ?></td>
                                    <td class="text-right font-monospace small"><?= htmlspecialchars(pcvc_payroll_format_clock($cin)) ?></td>
                                    <td class="text-right font-monospace small"><?= htmlspecialchars(pcvc_payroll_format_clock($cout)) ?></td>
                                    <td class="text-right"><?= $brk > 0 ? number_format($brk) : '—' ?></td>
                                    <td class="text-right"><?= number_format($mins) ?></td>
                                    <td class="text-right"><?= htmlspecialchars(pcvc_payroll_format_hm($mins)) ?></td>
                                    <td class="text-right <?= $salary > 0 ? 'text-success' : '' ?>">
                                        <?= $salary > 0 ? rwf($salary) : '—' ?>
                                    </td>
                                    <td>
                                        <?php if ($mins > 0 || $salary > 0): ?>
                                        <span class="present-badge">
                                            <i class="bi bi-check-circle"></i> Paid day
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">No paid time</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (!$hasData): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No attendance records for this period
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5"><strong>Period total</strong></td>
                                    <td class="text-right"><strong><?= number_format($a['total_minutes']) ?></strong></td>
                                    <td class="text-right"><strong><?= htmlspecialchars(pcvc_payroll_format_hm((int) $a['total_minutes'])) ?></strong></td>
                                    <td class="text-right text-success"><strong><?= rwf($a['total_salary']) ?></strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($admins)): ?>
        <div class="empty-state">
            <div class="empty-icon">💰</div>
            <div class="empty-title">No payroll employees</div>
            <div class="empty-text">There are no accounts with role <strong>staff</strong> or <strong>superadmin</strong> in the system.</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isSuperAdmin): ?>
<div id="payrollMomoModal" class="pcvc-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="payrollMomoModalTitle">
    <div class="pcvc-modal-panel">
        <div class="pcvc-modal-head" id="payrollMomoModalTitle">MoMo salary payment</div>
        <div class="pcvc-modal-body">
            <div id="payrollMomoStepConfirm">
                <p id="payrollMomoModalIntro" class="payroll-momo-intro"></p>
                <div id="payrollMomoModalList" class="payroll-momo-recipient-list"></div>
                <p id="payrollMomoModalErr" class="payroll-momo-modal-err" style="display:none;" role="alert"></p>
            </div>
            <div id="payrollMomoStepBusy" class="payroll-momo-busy" style="display:none;" aria-live="polite">
                <div class="payroll-momo-spinner" aria-hidden="true"></div>
                <p class="payroll-momo-busy-text">Sending to MoPay…</p>
                <p class="payroll-momo-busy-hint">Please keep this tab open.</p>
            </div>
            <div id="payrollMomoStepDone" class="payroll-momo-done" style="display:none;" aria-live="polite">
                <div id="payrollMomoDoneIcon" class="payroll-momo-done-icon" aria-hidden="true"></div>
                <h4 id="payrollMomoDoneTitle" class="payroll-momo-done-title"></h4>
                <div id="payrollMomoDoneBody" class="payroll-momo-done-body"></div>
            </div>
        </div>
        <div class="pcvc-modal-foot" id="payrollMomoModalFootConfirm">
            <button type="button" class="btn-secondary" id="payrollMomoModalCancel">Cancel</button>
            <button type="button" class="btn-primary" id="payrollMomoModalConfirm">
                <i class="bi bi-check2-circle"></i> Confirm
            </button>
        </div>
        <div class="pcvc-modal-foot" id="payrollMomoModalFootDone" style="display:none;">
            <button type="button" class="btn-primary" id="payrollMomoModalCloseDone">
                <i class="bi bi-check-lg"></i> Done
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    const PAYROLL_EMPLOYEE_COUNT = <?= (int) $payrollEmployeeCount ?>;
    const PAYROLL_CURRENCY = <?= json_encode($currency, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function formatNum(n) {
        return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(n);
    }

    function applyPayrollSearch() {
        const input = document.getElementById('payrollSearchInput');
        const meta = document.getElementById('payrollSearchMeta');
        const clearBtn = document.getElementById('payrollSearchClear');
        const noMatches = document.getElementById('payroll-no-matches');
        const visPill = document.getElementById('payrollVisiblePill');
        const visInner = document.getElementById('payrollVisiblePillInner');
        if (!input || !meta) return;

        const q = (input.value || '').trim().toLowerCase();
        const cards = document.querySelectorAll('.payroll-employee-card');
        let visible = 0;
        let visSalary = 0;
        let visMin = 0;

        cards.forEach(function (card) {
            const blob = (card.getAttribute('data-search') || '');
            const match = q === '' || blob.indexOf(q) !== -1;
            if (match) {
                card.classList.remove('payroll-search-hidden');
                visible++;
                visSalary += parseInt(card.getAttribute('data-salary') || '0', 10) || 0;
                visMin += parseInt(card.getAttribute('data-minutes') || '0', 10) || 0;
            } else {
                card.classList.add('payroll-search-hidden');
            }
        });

        if (clearBtn) {
            clearBtn.style.display = q ? 'inline-block' : 'none';
        }

        if (q) {
            meta.textContent = 'Showing ' + visible + ' of ' + PAYROLL_EMPLOYEE_COUNT + ' (staff & superadmin)';
            if (visPill && visInner) {
                visPill.style.display = 'inline-flex';
                visInner.textContent =
                    'Visible: ' + formatNum(visSalary) + ' ' + PAYROLL_CURRENCY +
                    ' · ' + formatNum(visMin) + ' min';
            }
        } else {
            meta.textContent =
                'Search ' + PAYROLL_EMPLOYEE_COUNT + ' employees (staff & superadmin) by name, email, ID, role, or position.';
            if (visPill) {
                visPill.style.display = 'none';
            }
        }

        if (noMatches) {
            if (visible === 0 && PAYROLL_EMPLOYEE_COUNT > 0) {
                noMatches.classList.add('visible');
            } else {
                noMatches.classList.remove('visible');
            }
        }

        const hid = document.getElementById('payrollMonthHiddenQ');
        if (hid) {
            hid.value = (input.value || '').trim();
        }

        try {
            const url = new URL(window.location.href);
            if (q) {
                url.searchParams.set('q', (input.value || '').trim());
            } else {
                url.searchParams.delete('q');
            }
            history.replaceState({}, '', url);
        } catch (e) { /* ignore */ }
    }

    window.applyPayrollSearch = applyPayrollSearch;

    window.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('payrollSearchInput');
        const clearBtn = document.getElementById('payrollSearchClear');
        applyPayrollSearch();
        if (input) {
            input.addEventListener('input', applyPayrollSearch);
            input.addEventListener('search', applyPayrollSearch);
        }
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (input) {
                    input.value = '';
                }
                applyPayrollSearch();
                if (input) {
                    input.focus();
                }
            });
        }
    });
})();

<?php if ($isSuperAdmin): ?>
(function () {
    const CSRF = <?= json_encode($payrollMomoCsrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const MONTH = <?= json_encode($selected_month, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const CUR = <?= json_encode($currency, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const modal = document.getElementById('payrollMomoModal');
    const modalList = document.getElementById('payrollMomoModalList');
    const modalIntro = document.getElementById('payrollMomoModalIntro');
    const modalErr = document.getElementById('payrollMomoModalErr');
    const modalConfirm = document.getElementById('payrollMomoModalConfirm');
    const modalCancel = document.getElementById('payrollMomoModalCancel');
    const stepConfirm = document.getElementById('payrollMomoStepConfirm');
    const stepBusy = document.getElementById('payrollMomoStepBusy');
    const stepDone = document.getElementById('payrollMomoStepDone');
    const footConfirm = document.getElementById('payrollMomoModalFootConfirm');
    const footDone = document.getElementById('payrollMomoModalFootDone');
    const doneIcon = document.getElementById('payrollMomoDoneIcon');
    const doneTitle = document.getElementById('payrollMomoDoneTitle');
    const doneBody = document.getElementById('payrollMomoDoneBody');
    const closeDoneBtn = document.getElementById('payrollMomoModalCloseDone');
    const modalTitle = document.getElementById('payrollMomoModalTitle');

    let pendingItems = [];
    let modalPhase = 'closed';

    function formatNum(n) {
        return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(n);
    }

    function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function setStep(phase) {
        modalPhase = phase;
        if (stepConfirm) stepConfirm.style.display = phase === 'confirm' ? '' : 'none';
        if (stepBusy) stepBusy.style.display = phase === 'busy' ? '' : 'none';
        if (stepDone) stepDone.style.display = phase === 'done' ? '' : 'none';
        if (footConfirm) footConfirm.style.display = phase === 'confirm' ? '' : 'none';
        if (footDone) footDone.style.display = phase === 'done' ? '' : 'none';
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('open');
        pendingItems = [];
        modalPhase = 'closed';
        setStep('confirm');
        if (modalErr) {
            modalErr.style.display = 'none';
            modalErr.textContent = '';
        }
        if (modalConfirm) modalConfirm.disabled = false;
        if (modalTitle) modalTitle.textContent = 'MoMo salary payment';
    }

    function readAmountForAdmin(adminId) {
        const inp = document.querySelector('.payroll-momo-amt[data-admin-id="' + adminId + '"]');
        if (!inp) return NaN;
        const v = parseInt(String(inp.value || '').replace(/\s+/g, ''), 10);
        return Number.isFinite(v) ? v : NaN;
    }

    function momoMsisdnFromCard(card) {
        if (!card) return '';
        return (card.getAttribute('data-momo-msisdn') || '').trim();
    }

    /** Format 250XXXXXXXXXXX → "250 XXX XXX XXX" for display */
    function formatMsisdnRw(m) {
        if (!m || m.length !== 12) return m || '';
        return m.substr(0, 3) + ' ' + m.substr(3, 3) + ' ' + m.substr(6, 3) + ' ' + m.substr(9, 3);
    }

    function buildItemsFromSelection() {
        const items = [];
        document.querySelectorAll('.payroll-employee-card:not(.payroll-search-hidden) .payroll-momo-sel:checked').forEach(function (cb) {
            const id = parseInt(cb.getAttribute('data-admin-id') || '0', 10);
            if (!id) return;
            const card = document.getElementById('card-' + id);
            const name = card ? (card.getAttribute('data-admin-name') || '') : '';
            const momoOk = card && card.getAttribute('data-momo-ok') === '1';
            const msisdn = momoMsisdnFromCard(card);
            const amt = readAmountForAdmin(id);
            items.push({ admin_id: id, amount: amt, name: name, momo_ok: momoOk, msisdn: msisdn });
        });
        return items;
    }

    function buildItemOne(adminId) {
        const card = document.getElementById('card-' + adminId);
        const name = card ? (card.getAttribute('data-admin-name') || '') : '';
        const momoOk = card && card.getAttribute('data-momo-ok') === '1';
        const msisdn = momoMsisdnFromCard(card);
        const amt = readAmountForAdmin(adminId);
        return [{ admin_id: adminId, amount: amt, name: name, momo_ok: momoOk, msisdn: msisdn }];
    }

    function validateItems(items) {
        const errs = [];
        if (!items.length) {
            errs.push('Select at least one employee, or use Pay on a row.');
            return errs;
        }
        items.forEach(function (it) {
            if (!it.momo_ok) {
                errs.push((it.name || 'Employee #' + it.admin_id) + ' has no valid 250… MoMo number.');
            }
            if (!Number.isFinite(it.amount) || it.amount < 1) {
                errs.push((it.name || '#' + it.admin_id) + ': amount must be at least 1 ' + CUR + '.');
            }
        });
        return errs;
    }

    function renderModal(items) {
        if (!modalList || !modalIntro) return;
        const n = items.length;
        modalIntro.textContent =
            n === 1
                ? 'Send ' + formatNum(items[0].amount) + ' ' + CUR + ' to the MoMo number on file for this person.'
                : 'Send ' + n + ' payments from your merchant wallet to the MoMo numbers below.';
        modalList.innerHTML = '';
        items.forEach(function (it) {
            const card = document.createElement('div');
            card.className = 'payroll-momo-mini-card';

            const top = document.createElement('div');
            const nameStrong = document.createElement('strong');
            nameStrong.textContent = it.name || ('ID ' + it.admin_id);
            top.appendChild(nameStrong);

            if (it.momo_ok && it.msisdn) {
                top.appendChild(document.createTextNode(' '));
                const numEl = document.createElement('span');
                numEl.className = 'payroll-momo-msisdn';
                numEl.textContent = formatMsisdnRw(it.msisdn);
                numEl.setAttribute('title', 'MSISDN: ' + it.msisdn);
                top.appendChild(numEl);
            } else {
                const bad = document.createElement('div');
                bad.style.color = 'var(--danger)';
                bad.style.fontSize = '12px';
                bad.style.marginTop = '4px';
                bad.textContent = 'No valid Rwanda MoMo number on file.';
                top.appendChild(bad);
            }

            const amtRow = document.createElement('div');
            amtRow.className = 'payroll-momo-amt-line';
            amtRow.appendChild(document.createTextNode('Amount: '));
            const amtStrong = document.createElement('strong');
            amtStrong.textContent = formatNum(it.amount) + ' ' + CUR;
            amtRow.appendChild(amtStrong);

            card.appendChild(top);
            card.appendChild(amtRow);
            modalList.appendChild(card);
        });
    }

    function openModal(items) {
        const errs = validateItems(items);
        if (modalErr) {
            modalErr.style.display = errs.length ? 'block' : 'none';
            modalErr.textContent = errs.join(' ');
        }
        pendingItems = items;
        renderModal(items);
        setStep('confirm');
        if (modalTitle) modalTitle.textContent = 'MoMo salary payment';
        if (modal) {
            modal.classList.add('open');
        }
        if (modalConfirm) {
            modalConfirm.disabled = errs.length > 0;
        }
    }

    function showResultView(j, res) {
        setStep('done');
        const httpBad = !res.http || res.http >= 400;
        const succeeded = parseInt(j.succeeded, 10) || 0;
        const processed = parseInt(j.processed, 10) || 0;
        const allOk = !httpBad && succeeded === processed && processed > 0;
        const anyOk = !httpBad && succeeded > 0;

        if (doneIcon) {
            doneIcon.className = 'payroll-momo-done-icon ' + (anyOk && !httpBad ? 'ok' : 'fail');
            doneIcon.innerHTML = anyOk && !httpBad
                ? '<i class="bi bi-check-lg"></i>'
                : '<i class="bi bi-exclamation-lg"></i>';
        }
        if (doneTitle) {
            if (httpBad) {
                doneTitle.textContent = 'Request failed';
            } else if (allOk) {
                doneTitle.textContent = 'Payment complete';
            } else if (anyOk) {
                doneTitle.textContent = 'Completed with issues';
            } else {
                doneTitle.textContent = 'Payment did not go through';
            }
        }
        if (doneBody) {
            const parts = [];
            if (httpBad) {
                parts.push('<div class="payroll-momo-done-line fail">' + escHtml(String(j.error || res.http || 'Unknown error')) + '</div>');
            } else {
                parts.push(
                    '<div class="payroll-momo-done-line ' + (succeeded === processed ? 'ok' : 'fail') + '">' +
                    '<strong>' + succeeded + '</strong> of <strong>' + processed + '</strong> sent successfully.' +
                    '</div>'
                );
                (j.results || []).forEach(function (r) {
                    const lineClass = r.ok ? 'ok' : 'fail';
                    const label = escHtml(String(r.name || ('#' + r.admin_id)));
                    const detail = r.ok
                        ? escHtml(formatNum(r.amount) + ' ' + CUR)
                        : escHtml(String(r.error || 'Error'));
                    parts.push(
                        '<div class="payroll-momo-done-line ' + lineClass + '">' +
                        (r.ok ? '✓ ' : '✕ ') + label + ' — ' + detail +
                        '</div>'
                    );
                });
            }
            doneBody.innerHTML = parts.join('');
        }
    }

    function postPay(items) {
        const payload = {
            csrf: CSRF,
            month: MONTH,
            items: items.map(function (it) {
                return { admin_id: it.admin_id, amount: it.amount };
            }),
        };
        return fetch('api/payroll-momo-pay.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'same-origin',
        }).then(function (r) {
            return r.json().then(function (j) {
                return { http: r.status, json: j };
            });
        });
    }

    window.payrollMomoPayOne = function (adminId) {
        openModal(buildItemOne(adminId));
    };

    window.addEventListener('DOMContentLoaded', function () {
        const selAll = document.getElementById('payrollSelectAllVisible');
        if (selAll) {
            selAll.addEventListener('change', function () {
                const on = selAll.checked;
                document.querySelectorAll('.payroll-employee-card:not(.payroll-search-hidden) .payroll-momo-sel').forEach(function (cb) {
                    cb.checked = on;
                });
            });
        }

        const payBtn = document.getElementById('payrollMomoPayNowBtn');
        if (payBtn) {
            payBtn.addEventListener('click', function () {
                openModal(buildItemsFromSelection());
            });
        }

        document.querySelectorAll('.payroll-momo-one').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                const id = parseInt(btn.getAttribute('data-admin-id') || '0', 10);
                if (id) {
                    window.payrollMomoPayOne(id);
                }
            });
        });

        if (modalCancel) {
            modalCancel.addEventListener('click', closeModal);
        }
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal && modalPhase === 'confirm') {
                    closeModal();
                }
            });
        }

        if (closeDoneBtn) {
            closeDoneBtn.addEventListener('click', closeModal);
        }

        if (modalConfirm) {
            modalConfirm.addEventListener('click', function () {
                const errs = validateItems(pendingItems);
                if (errs.length) {
                    return;
                }
                modalConfirm.disabled = true;
                setStep('busy');
                if (modalTitle) modalTitle.textContent = 'Processing…';
                postPay(pendingItems)
                    .then(function (res) {
                        modalConfirm.disabled = false;
                        const j = res.json || {};
                        showResultView(j, res);
                        if (modalTitle) modalTitle.textContent = 'Result';
                    })
                    .catch(function () {
                        modalConfirm.disabled = false;
                        showResultView({ error: 'Network error — check your connection and try again.' }, { http: 0, json: {} });
                        if (modalTitle) modalTitle.textContent = 'Result';
                    });
            });
        }
    });
})();
<?php endif; ?>

// Toggle details for a specific admin or summary
function toggleDetails(id) {
    let el = document.getElementById('details-' + id);
    if (el) {
        el.classList.toggle('open');
    }
}

// Toggle all details (visible cards only when search is active)
function toggleAll(open) {
    document.querySelectorAll('.payroll-employee-card:not(.payroll-search-hidden) .collapse').forEach(el => {
        if (open) {
            el.classList.add('open');
        } else {
            el.classList.remove('open');
        }
    });
}

// Print details for a specific admin
function printDetails(id) {
    const card = document.getElementById('card-' + id);
    const details = document.getElementById('details-' + id);
    
    if (!card || !details) return;
    
    // Ensure details are visible for printing
    if (!details.classList.contains('open')) {
        details.classList.add('open');
    }
    
    // Mark this card as the print target
    document.body.classList.add('printing');
    card.classList.add('print-target');
    
    // Trigger print
    window.print();
    
    // Cleanup after printing
    setTimeout(() => {
        document.body.classList.remove('printing');
        card.classList.remove('print-target');
    }, 500);
}

// Auto-expand if URL hash points to a specific admin
window.addEventListener('load', function() {
    if (window.location.hash) {
        let id = window.location.hash.replace('#', '');
        let details = document.getElementById('details-' + id);
        if (details) {
            details.classList.add('open');
        }
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Alt + E - Expand all
    if (e.altKey && e.key === 'e') {
        e.preventDefault();
        toggleAll(true);
    }
    // Alt + C - Collapse all
    if (e.altKey && e.key === 'c') {
        e.preventDefault();
        toggleAll(false);
    }
    // Ctrl + P - Print
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        window.print();
    }
});
</script>

</body>
</html>