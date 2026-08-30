<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/company_branding.php';
session_start();

$adminPk = 0;
if (!empty($_SESSION['id'])) {
    $adminPk = (int) $_SESSION['id'];
} elseif (!empty($_SESSION['admin_id'])) {
    $adminPk = (int) $_SESSION['admin_id'];
}
if ($adminPk < 1) {
    header('Location: admin-login.php');
    exit;
}

$stAdmin = $conn->prepare('SELECT id, email, full_name, first_name, last_name FROM admins WHERE id = ? LIMIT 1');
if (!$stAdmin) {
    exit('Database error.');
}
$stAdmin->bind_param('i', $adminPk);
$stAdmin->execute();
$adminRow = $stAdmin->get_result()->fetch_assoc();
$stAdmin->close();
if (!$adminRow) {
    header('Location: admin-login.php');
    exit;
}

$agentEmail = strtolower(trim((string) ($adminRow['email'] ?? '')));
$agentLabel = trim((string) ($adminRow['full_name'] ?? ''));
if ($agentLabel === '') {
    $agentLabel = trim((string) (($adminRow['first_name'] ?? '') . ' ' . ($adminRow['last_name'] ?? '')));
}
if ($agentLabel === '') {
    $agentLabel = 'Agent';
}

$canDeleteApplication = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new']) && $agentEmail !== '') {
    $stmt = $conn->prepare('INSERT INTO student_applications (
        first_name, last_name, email, phone_number, gender, dob, nationality, city, address_line1,
        masters_program, destination, application_date, agent_email
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param(
        'sssssssssssss',
        $_POST['first_name'],
        $_POST['last_name'],
        $_POST['email'],
        $_POST['phone_number'],
        $_POST['gender'],
        $_POST['dob'],
        $_POST['nationality'],
        $_POST['city'],
        $_POST['address_line1'],
        $_POST['masters_program'],
        $_POST['destination'],
        $_POST['application_date'],
        $agentEmail
    );
    $stmt->execute();
    $stmt->close();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

function pcvc_table_has_column(mysqli $conn, string $table, string $column): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table === '' || $column === '') {
        return false;
    }
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $r && $r->num_rows > 0;
}

$all_applicants = [];

if ($agentEmail !== '') {
    $sql1 = "
    SELECT 
        sa.id,
        sa.first_name,
        sa.last_name,
        sa.email,
        CONCAT(sa.area_code, ' ', sa.phone_number) as phone_number,
        sa.gender,
        sa.dob,
        COALESCE(c.name, sa.nationality) as nationality,
        sa.city,
        sa.address_line1,
        COALESCE(sa.masters_program, sa.bachelor_program, sa.phd_program) as masters_program,
        sa.destination,
        sa.application_date,
        sa.application_id,
        sa.application_remarks,
        sa.incomplete_app,
        sa.submitted,
        sa.app_paid,
        sa.admit,
        sa.i20_sent,
        sa.sevis_paid,
        sa.visa_scheduled,
        sa.visa_approved,
        sa.enrolled,
        sa.addn_doc,
        sa.deny,
        sa.app_start
    FROM student_applications sa
    LEFT JOIN countries c 
        ON sa.nationality = c.id 
        OR sa.nationality = c.name
    WHERE LOWER(TRIM(sa.agent_email)) = ?
    ORDER BY 
        sa.visa_approved DESC,
        sa.admit DESC,
        sa.deny DESC,
        sa.submitted DESC,
        sa.id DESC
";
    $st1 = $conn->prepare($sql1);
    if ($st1) {
        $st1->bind_param('s', $agentEmail);
        $st1->execute();
        $res1 = $st1->get_result();
        if ($res1) {
            while ($row = $res1->fetch_assoc()) {
                $row['source'] = 'student_applications';
                $all_applicants[] = $row;
            }
        }
        $st1->close();
    }

    try {
        $chkMalta = $conn->query("SHOW TABLES LIKE 'malta_applications'");
        if ($chkMalta && $chkMalta->num_rows > 0 && pcvc_table_has_column($conn, 'malta_applications', 'agent_email')) {
            $sqlMalta = "
            SELECT 
                ma.id,
                ma.name AS first_name,
                ma.surname AS last_name,
                ma.email,
                ma.contact_number AS phone_number,
                ma.gender,
                ma.dob,
                COALESCE(c.name, ma.nationality) as nationality,
                ma.birth_place AS city,
                ma.address AS address_line1,
                ma.degree_program AS masters_program,
                'Malta' AS destination,
                ma.created_at AS application_date,
                ma.application_id,
                ma.application_remarks,
                ma.incomplete_app,
                ma.submitted,
                ma.app_paid,
                ma.admit,
                ma.i20_sent,
                ma.sevis_paid,
                ma.visa_scheduled,
                ma.visa_approved,
                ma.enrolled,
                ma.addn_doc,
                ma.deny,
                ma.app_start
            FROM malta_applications ma
            LEFT JOIN countries c ON ma.nationality = c.id OR ma.nationality = c.name
            WHERE LOWER(TRIM(ma.agent_email)) = ?
            ORDER BY 
                ma.visa_approved DESC,
                ma.admit DESC,
                ma.deny DESC,
                ma.submitted DESC,
                ma.id DESC
        ";
            $stM = $conn->prepare($sqlMalta);
            if ($stM) {
                $stM->bind_param('s', $agentEmail);
                $stM->execute();
                $resM = $stM->get_result();
                if ($resM) {
                    while ($row = $resM->fetch_assoc()) {
                        $row['source'] = 'malta_applications';
                        $all_applicants[] = $row;
                    }
                }
                $stM->close();
            }
        }
    } catch (Exception $e) {
        // ignore
    }

    try {
        $chkTurkey = $conn->query("SHOW TABLES LIKE 'turkey_applications'");
        if ($chkTurkey && $chkTurkey->num_rows > 0 && pcvc_table_has_column($conn, 'turkey_applications', 'agent_email')) {
            $sqlTurkey = "
            SELECT 
                ta.id,
                ta.first_name,
                ta.last_name,
                ta.email,
                ta.mobile AS phone_number,
                ta.gender,
                ta.dob,
                COALESCE(c.name, ta.nationality) as nationality,
                ta.city,
                ta.address AS address_line1,
                NULL AS masters_program,
                'Turkey' AS destination,
                ta.submitted_at AS application_date,
                ta.application_id,
                ta.application_remarks,
                ta.incomplete_app,
                ta.submitted,
                ta.app_paid,
                ta.admit,
                ta.i20_sent,
                ta.sevis_paid,
                ta.visa_scheduled,
                ta.visa_approved,
                ta.enrolled,
                ta.addn_doc,
                ta.deny,
                ta.app_start
            FROM turkey_applications ta
            LEFT JOIN countries c ON ta.nationality = c.id OR ta.nationality = c.name
            WHERE LOWER(TRIM(ta.agent_email)) = ?
            ORDER BY ta.submitted_at DESC
        ";
            $stT = $conn->prepare($sqlTurkey);
            if ($stT) {
                $stT->bind_param('s', $agentEmail);
                $stT->execute();
                $resT = $stT->get_result();
                if ($resT) {
                    while ($row = $resT->fetch_assoc()) {
                        $row['source'] = 'turkey_applications';
                        $all_applicants[] = $row;
                    }
                }
                $stT->close();
            }
        }
    } catch (Exception $e) {
        // ignore
    }
}

// Filter out empty rows and ensure we have proper data
$all_applicants = array_filter($all_applicants, function($app) {
    return !empty($app['first_name']) || !empty($app['email']);
});

// Sort by ID descending to show newest first
usort($all_applicants, function($a, $b) {
    return ($b['id'] ?? 0) - ($a['id'] ?? 0);
});

$universities_for_admission = [];
$uq = @$conn->query('SELECT id, name FROM universities ORDER BY name ASC');
if ($uq) {
    while ($ur = $uq->fetch_assoc()) {
        $universities_for_admission[] = $ur;
    }
}

$applicantCount = count($all_applicants);

// Debug: Check what data we have
// echo "<pre>Total applicants found: " . count($all_applicants) . "</pre>";
// echo "<pre>";
// print_r($all_applicants);
// echo "</pre>";

// HTML starts here
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
  <title>ScholarSync Global Visa — My students</title>
  
  <!-- Brand color variables (ScholarSync Global logo) -->
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
    }
  </style>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    /* Lock page scroll — only the table inner panel scrolls (horizontal bar always at bottom of panel) */
    html {
      height: 100%;
      overflow: hidden;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(180deg, var(--white) 0%, #f0f4f8 100%);
      color: var(--text-dark);
      height: 100%;
      max-height: 100%;
      min-height: 100%;
      min-height: 100vh; /* fallback when parent height is unknown (e.g. some iframe layouts) */
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    /* ===== Brand banner (PCVC_COMPANY_DISPLAY_NAME) ===== */
    .pcvc-brand-banner {
      background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
      padding: 20px 16px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(0, 39, 101, 0.15);
      flex-shrink: 0;
    }

    .logo-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
    }

    .logo-main {
      font-size: clamp(1.15rem, 3.5vw, 2.5rem);
      font-weight: 800;
      color: var(--white);
      letter-spacing: 1px;
      position: relative;
      display: inline-block;
      max-width: min(100%, 56rem);
      line-height: 1.2;
    }

    .logo-main::after {
      content: '🎓';
      position: absolute;
      top: -5px;
      right: -35px;
      font-size: 1.8rem;
    }

    .logo-subtitle {
      font-size: 1.1rem;
      font-weight: 500;
      color: var(--gold);
      letter-spacing: 0.5px;
    }

    /* ===== MAIN CONTAINER ===== */
    .main-container {
      max-width: min(1600px, 98vw);
      margin: 0 auto;
      padding: 0 clamp(12px, 2vw, 24px);
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 0;
      overflow: hidden;
    }

    /* ===== PAGE HEADER ===== */
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      flex-wrap: wrap;
      gap: 15px;
      flex-shrink: 0;
    }

    .page-title {
      font-size: 28px;
      font-weight: 700;
      color: var(--deep-navy);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .page-title::before {
      content: '🎓';
      font-size: 30px;
    }

    .page-title-agent::before {
      content: '👥';
      font-size: 30px;
    }

    .agent-meta-row {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 10px;
      margin-top: 8px;
    }

    .meta-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 700;
    }

    .meta-pill-green {
      background: rgba(66, 116, 49, 0.12);
      color: var(--deep-navy);
    }

    .meta-pill-blue {
      background: rgba(54, 97, 185, 0.12);
      color: var(--secondary-blue);
    }

    .agent-manage-header {
      align-items: flex-start;
    }

    .page-header-left {
      flex: 1;
      min-width: 200px;
    }

    .agent-header-tools {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      gap: 12px;
      flex: 1;
      justify-content: flex-end;
      max-width: 760px;
    }

    .agent-search-inline {
      margin-bottom: 0 !important;
      flex: 1;
      min-width: 220px;
      max-width: 440px;
    }

    .btn-add-applicant {
      white-space: nowrap;
      border-radius: 12px;
      align-self: center;
    }

    .logo-agent-line {
      font-size: 0.85rem;
      font-weight: 500;
      color: rgba(255, 255, 255, 0.92);
      margin-top: 4px;
    }

    .logo-agent-line a {
      color: inherit;
    }

    /* ===== BUTTONS ===== */
    .btn-primary {
      background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
      border: none;
      border-radius: 999px;
      padding: 10px 24px;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, var(--dark-blue) 0%, var(--deep-navy) 100%);
      transform: translateY(-2px);
      box-shadow: 0 8px 16px rgba(1, 47, 107, 0.2);
    }

    .btn-warning {
      background: linear-gradient(135deg, var(--gold) 0%, #e6953e 100%);
      border: none;
      border-radius: 999px;
      padding: 10px 24px;
      font-weight: 600;
      color: var(--deep-navy);
    }

    .btn-warning:hover {
      background: linear-gradient(135deg, #e6953e 0%, #d68938 100%);
      transform: translateY(-2px);
      box-shadow: 0 8px 16px rgba(242, 166, 90, 0.2);
    }

    /* ===== SEARCH BAR ===== */
    .search-container {
      position: relative;
      margin-bottom: 25px;
      flex-shrink: 0;
    }

    .search-box {
      width: 100%;
      padding: 14px 50px 14px 20px;
      border: 2px solid rgba(1, 47, 107, 0.2);
      border-radius: 12px;
      font-size: 16px;
      background: var(--white);
      box-shadow: 0 4px 12px rgba(1, 47, 107, 0.08);
      transition: all 0.3s ease;
    }

    .search-box:focus {
      outline: none;
      border-color: var(--gold);
      box-shadow: 0 4px 16px rgba(242, 166, 90, 0.2);
    }

    .search-icon {
      position: absolute;
      right: 20px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      font-size: 20px;
    }

    /* ===== TABLE CONTAINER ===== */
    .table-container {
      background: var(--white);
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(1, 47, 107, 0.1);
      border: 1px solid rgba(1, 47, 107, 0.08);
      margin-bottom: 12px;
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 0;
      overflow: hidden;
    }

    /* Outer: fills remaining height, does not scroll */
    .table-viewport {
      flex: 1;
      min-height: 0;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      border-radius: 0 0 16px 16px;
    }

    /*
      Inner: constrained height + overflow:auto → vertical AND horizontal scrollbars
      stay at the bottom/right of this visible box (not below thousands of rows).
    */
    .table-viewport-inner {
      flex: 1;
      min-height: 0;
      width: 100%;
      overflow: auto;
      scrollbar-gutter: stable;
      -webkit-overflow-scrolling: touch;
    }

    .table {
      margin: 0;
      width: max-content;
      min-width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }


    .table thead th {
      background: linear-gradient(135deg, var(--secondary-blue) 0%, var(--dark-blue) 100%);
      color: var(--white);
      border: none;
      padding: 14px 12px;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 11px;
      letter-spacing: 0.06em;
      position: sticky;
      top: 0;
      z-index: 10;
      text-align: center;
      white-space: nowrap;
      box-shadow: 0 1px 0 rgba(255,255,255,0.12);
    }

    .table thead th:first-child {
      border-radius: 0;
    }

    .col-actions {
      min-width: 110px;
      width: 110px;
      position: sticky;
      right: 0;
      z-index: 11;
      background: linear-gradient(135deg, #1a4a7a 0%, var(--dark-blue) 100%);
      box-shadow: -6px 0 12px rgba(0,0,0,0.12);
    }

    .table tbody td.col-actions {
      background: var(--white);
      position: sticky;
      right: 0;
      z-index: 5;
      box-shadow: -4px 0 8px rgba(0,0,0,0.04);
    }

    .table tbody tr:nth-child(even) td.col-actions {
      background: #f8fafc;
    }

    .table tbody tr:hover td.col-actions {
      background: rgba(242, 166, 90, 0.08);
    }

    .btn-delete-app {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.35rem;
      padding: 0.4rem 0.65rem;
      font-size: 0.75rem;
      font-weight: 600;
      color: #fff;
      background: #dc2626;
      border: 1px solid #b91c1c;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.2s, transform 0.15s;
    }

    .btn-delete-app:hover:not(:disabled) {
      background: #b91c1c;
      transform: translateY(-1px);
    }

    .btn-delete-app:disabled {
      opacity: 0.45;
      cursor: not-allowed;
    }

    .table tbody tr {
      transition: background-color 0.2s ease;
      border-bottom: 1px solid rgba(1, 47, 107, 0.05);
    }

    .table tbody tr:hover {
      background-color: rgba(242, 166, 90, 0.05);
    }

    .table td {
      padding: 14px 12px;
      vertical-align: middle;
      border: none;
      font-size: 14px;
      text-align: center;
    }

    /* ===== STATUS DROPDOWN COLUMN ===== */
    .status-column {
      min-width: 160px;
      max-width: 180px;
    }

    .status-dropdown {
      width: 100%;
      position: relative;
    }

    .status-dropdown-toggle {
      width: 100%;
      padding: 8px 12px;
      background: var(--white);
      border: 2px solid rgba(1, 47, 107, 0.2);
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      text-align: left;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      justify-content: space-between;
      align-items: center;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .status-dropdown-toggle:hover {
      border-color: var(--gold);
      background: rgba(242, 166, 90, 0.05);
    }

    .status-dropdown-toggle:focus {
      outline: none;
      border-color: var(--gold);
      box-shadow: 0 0 0 3px rgba(242, 166, 90, 0.2);
    }

    .status-dropdown-menu {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: var(--white);
      border: 1px solid rgba(1, 47, 107, 0.15);
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      z-index: 1000;
      max-height: 300px;
      overflow-y: auto;
      display: none;
      margin-top: 4px;
    }

    .status-dropdown-item {
      padding: 8px 12px;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: space-between;
      white-space: nowrap;
    }

    .status-dropdown-item:hover {
      background: rgba(242, 166, 90, 0.1);
    }

    .status-check {
      color: var(--success);
      font-weight: bold;
      font-size: 16px;
      display: none;
    }

    .status-check.active {
      display: inline-block;
    }

    /* Status colors */
    .status-incomplete_app { color: #212529; }
    .status-submitted { color: #6c757d; }
    .status-app_paid { color: var(--success); }
    .status-admit { color: var(--deep-navy); }
    .status-i20_sent { color: var(--info); }
    .status-sevis_paid { color: #6c757d; }
    .status-visa_scheduled { color: var(--warning); }
    .status-visa_approved { color: var(--success); }
    .status-enrolled { color: var(--success); }
    .status-addn_doc { color: #343a40; }
    .status-deny { color: var(--danger); }
    .status-app_start { color: #6c757d; }

    /* ===== EDITABLE CELLS ===== */
    .editable-cell {
      cursor: pointer;
      transition: background-color 0.2s ease;
      border-radius: 4px;
      padding: 4px 8px;
    }

    .editable-cell:hover {
      background-color: rgba(242, 166, 90, 0.1);
    }

    .editable-cell:focus {
      outline: none;
      background-color: rgba(242, 166, 90, 0.15);
    }

    /* ===== FORM CONTROLS ===== */
    .form-control-sm {
      font-size: 14px;
      padding: 6px 10px;
      border-radius: 6px;
      border: 1px solid rgba(1, 47, 107, 0.2);
      transition: border-color 0.3s ease;
    }

    .form-control-sm:focus {
      border-color: var(--gold);
      box-shadow: 0 0 0 0.25rem rgba(242, 166, 90, 0.25);
    }

    textarea.form-control-sm {
      min-height: 50px;
      resize: vertical;
    }

    /* ===== MODAL STYLES ===== */
    .modal-content {
      border-radius: 12px;
      border: none;
      box-shadow: 0 8px 32px rgba(1, 47, 107, 0.2);
    }

    .modal-header {
      background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
      color: var(--white);
      border-radius: 12px 12px 0 0;
      padding: 20px 30px;
    }

    .modal-header .btn-close {
      filter: invert(1);
    }

    .notify-channel-btn {
      border: 2px solid #e2e8f0 !important;
      background: #fff;
      font-weight: 600;
      color: #334155;
      transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
    }
    .notify-channel-btn:hover {
      border-color: #94a3b8 !important;
    }
    .notify-channel-btn.active {
      border-color: #427431 !important;
      background: #eff6ff !important;
      color: #427431 !important;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 1200px) {
      .main-container {
        max-width: 100%;
        padding: 0 15px;
      }
    }

    @media (max-width: 768px) {
      .page-header {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .logo-main {
        font-size: 2rem;
      }
      
      .logo-subtitle {
        font-size: 1rem;
      }
      
      .page-title {
        font-size: 24px;
      }
      
      .status-column {
        min-width: 140px;
      }
    }

    /* ===== SCROLLBAR STYLING ===== */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    ::-webkit-scrollbar-track {
      background: rgba(1, 47, 107, 0.1);
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
      background: var(--secondary-blue);
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: var(--deep-navy);
    }

    /* ===== PAYMENT MODAL STYLES ===== */
    #paymentModal .modal-dialog {
      max-width: 900px;
    }

    #paymentModal .modal-header {
      background: linear-gradient(135deg, var(--success) 0%, #1b5e20 100%);
    }

    /* ===== TOAST STYLES ===== */
    .toast-container {
      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 9999;
    }

    .toast {
      background: var(--white);
      border: 1px solid rgba(1, 47, 107, 0.1);
      box-shadow: 0 4px 12px rgba(1, 47, 107, 0.15);
      border-radius: 8px;
      overflow: hidden;
    }

    .toast-header {
      background: linear-gradient(135deg, var(--success) 0%, #1b5e20 100%);
      color: white;
      border: none;
    }

    .toast-header .btn-close {
      filter: invert(1);
    }

    /* ===== LOADING INDICATOR ===== */
    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(255, 255, 255, 0.8);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      display: none;
    }

    .spinner {
      width: 50px;
      height: 50px;
      border: 5px solid rgba(1, 47, 107, 0.1);
      border-radius: 50%;
      border-top-color: var(--deep-navy);
      animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  </style>
</head>

<body>
<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
  <div class="spinner"></div>
</div>

<!-- Admin header -->
<div class="pcvc-brand-banner">
  <div class="logo-container">
    <div class="logo-main"><?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="logo-subtitle">Applicants</div>
    <div class="logo-agent-line">
      <?= htmlspecialchars($agentLabel, ENT_QUOTES, 'UTF-8') ?>
      · <a href="admin-dashboard.php" target="_parent">Dashboard</a>
    </div>
  </div>
</div>

<div class="main-container">
  <div class="page-header agent-manage-header">
    <div class="page-header-left">
      <div class="page-title page-title-agent">My students</div>
      <div class="agent-meta-row">
        <?php if ($agentEmail !== ''): ?>
          <span class="meta-pill meta-pill-green"># <?= (int) $applicantCount ?> allocated</span>
          <span class="meta-pill meta-pill-blue">✉ <?= htmlspecialchars($agentEmail, ENT_QUOTES, 'UTF-8') ?></span>
        <?php else: ?>
          <span class="text-muted small">Add a work email to your admin profile to register applicants and see allocations.</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="page-header-right agent-header-tools">
      <div class="search-container agent-search-inline">
        <input type="text" id="searchInput" class="search-box" placeholder="🔍 Search Name, Email, Program, Destination..." <?= $agentEmail === '' ? 'disabled' : '' ?>>
        <span class="search-icon">🔍</span>
      </div>
      <?php if ($agentEmail !== ''): ?>
        <button type="button" class="btn btn-primary btn-add-applicant" data-bs-toggle="modal" data-bs-target="#addRecordModal">+ Add applicant</button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Table: inner scroll area so horizontal scrollbar stays at bottom of visible panel -->
  <div class="table-container">
    <div class="table-viewport" id="applicantTableViewport">
      <div class="table-viewport-inner" id="applicantTableViewportInner">
      <table class="table table-bordered table-hover table-striped mb-0" id="applicantTable">

        <thead class="text-center">
          <tr>
            <th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Gender</th><th>DOB</th>
            <th>Nationality</th><th>City</th><th>Address</th><th>Program</th><th>Destination</th>
            <th>Applied On</th><th>Status</th><th>App ID</th><th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $counter = 1; 
          $statusOptions = [
            'incomplete_app' => 'Incomplete App',
            'submitted' => 'Submitted',
            'sent_to_platform' => 'Sent to Platform',
            'app_paid' => 'App Paid',
            'admit' => 'Admit',
            'i20_sent' => 'I-20 Sent',
            'sevis_paid' => 'Sevis Paid',
            'visa_scheduled' => 'Visa Sch.',
            'visa_approved' => 'Visa OK',
            'enrolled' => 'Enrolled',
            'addn_doc' => 'Add Doc',
            'deny' => 'Rejected',
            'app_start' => 'App Start'
          ];
          $statusDisplayPriority = [
            'deny',
            'enrolled',
            'visa_approved',
            'visa_scheduled',
            'sevis_paid',
            'i20_sent',
            'admit',
            'app_paid',
            'sent_to_platform',
            'submitted',
            'addn_doc',
            'incomplete_app',
            'app_start'
          ];
          
          foreach ($all_applicants as $s): 
            $currentStatus = null;
            $currentStatusText = 'Select Status';
            foreach ($statusDisplayPriority as $key) {
              if (!empty($s[$key]) && (int)$s[$key] === 1) {
                $currentStatus = $key;
                $currentStatusText = $statusOptions[$key] ?? 'Select Status';
                break;
              }
            }
            
            // Format phone number
            $phone = $s['phone_number'] ?? '';
            if (!empty($s['area_code']) && !empty($s['phone_number']) && strpos($phone, $s['area_code']) === false) {
                $phone = $s['area_code'] . ' ' . $s['phone_number'];
            }
          ?>
          <tr data-row-id="<?= $s['id'] ?>" data-source="<?= $s['source'] ?>">
            <td><?= $counter++ ?></td>

            <!-- Name (first + last) -->
            <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>" data-field="first_name">
              <?= htmlspecialchars(ucfirst((string) ($s['first_name'] ?? '')) . ' ' . ucfirst((string) ($s['last_name'] ?? ''))) ?>
            </td>

            <!-- Email -->
            <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>" data-field="email">
              <?= htmlspecialchars($s['email'] ?? '') ?>
            </td>

            <!-- Phone Number -->
            <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>"
                data-field="<?= $s['source'] === 'malta_applications' ? 'contact_number' : ($s['source'] === 'turkey_applications' ? 'mobile' : 'phone_number') ?>">
              <?= htmlspecialchars($phone) ?>
            </td>

            <!-- Gender -->
            <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>" data-field="gender">
              <?= htmlspecialchars($s['gender'] ?? '') ?>
            </td>

            <!-- DOB -->
            <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>" data-field="dob">
              <?= htmlspecialchars($s['dob'] ?? '') ?>
            </td>

            <!-- Nationality -->
            <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>" data-field="nationality">
              <?= htmlspecialchars($s['nationality'] ?? '') ?>
            </td>

            <!-- City / Birthplace -->
            <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>"
                data-field="<?= $s['source'] === 'malta_applications' ? 'birth_place' : 'city' ?>">
              <?= htmlspecialchars($s['city'] ?? '') ?>
            </td>

            <!-- Address -->
            <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>"
                data-field="<?= $s['source'] === 'malta_applications' ? 'address' : 'address_line1' ?>">
              <?= htmlspecialchars($s['address_line1'] ?? '') ?>
            </td>

            <!-- Master's Program -->
            <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>"
                data-field="<?= $s['source'] === 'malta_applications' ? 'degree_program' : 'masters_program' ?>">
              <?= htmlspecialchars($s['masters_program'] ?? '') ?>
            </td>

            <!-- Destination -->
            <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>" data-field="destination">
              <?= htmlspecialchars($s['destination'] ?? '') ?>
            </td>

            <!-- Application Date -->
            <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>"
                data-field="<?= $s['source'] === 'malta_applications' ? 'created_at' : ($s['source'] === 'turkey_applications' ? 'submitted_at' : 'application_date') ?>">
              <?= htmlspecialchars($s['application_date'] ?? '') ?>
            </td>

            <!-- Status Dropdown -->
            <td class="status-column">
              <div class="status-dropdown" data-id="<?= $s['id'] ?>" data-table="<?= $s['source'] ?>">
                <button type="button" class="status-dropdown-toggle">
                  <span class="status-text"><?= htmlspecialchars($currentStatusText) ?></span>
                  <span class="dropdown-arrow">▼</span>
                </button>
                <div class="status-dropdown-menu">
                  <?php foreach ($statusOptions as $key => $label): ?>
                  <div class="status-dropdown-item status-<?= $key ?>" data-flag="<?= $key ?>">
                    <span><?= htmlspecialchars($label) ?></span>
                    <span class="status-check <?= (!empty($s[$key]) && (int)$s[$key] === 1) ? 'active' : '' ?>">✓</span>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </td>

            <!-- Application ID -->
            <td>
              <input type="text" class="form-control form-control-sm live-app-id" data-id="<?= $s['id'] ?>" 
                     value="<?= htmlspecialchars($s['application_id'] ?? '') ?>">
            </td>

            <!-- Remarks -->
            <td>
              <textarea class="form-control form-control-sm live-app-remarks" data-id="<?= $s['id'] ?>">
                <?= htmlspecialchars($s['application_remarks'] ?? '') ?>
              </textarea>
            </td>

          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>

<?php if ($agentEmail !== ''): ?>
<div class="modal fade" id="addRecordModal" tabindex="-1" aria-labelledby="addRecordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
      <form method="post">
        <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,var(--deep-navy),var(--secondary-blue));border-radius:16px 16px 0 0;">
          <h5 class="modal-title fw-bold" id="addRecordModalLabel">New applicant</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label small fw-semibold">First name</label><input type="text" name="first_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Last name</label><input type="text" name="last_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Email</label><input type="email" name="email" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Phone</label><input type="text" name="phone_number" class="form-control" required></div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Gender</label>
              <select name="gender" class="form-select" required>
                <option value="" selected disabled>Select</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Date of birth</label><input type="text" name="dob" class="form-control datepicker" placeholder="YYYY-MM-DD" required></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Nationality</label><input type="text" name="nationality" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">City</label><input type="text" name="city" class="form-control" required></div>
            <div class="col-12"><label class="form-label small fw-semibold">Address</label><input type="text" name="address_line1" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Program</label><input type="text" name="masters_program" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Destination</label><input type="text" name="destination" class="form-control" required></div>
            <div class="col-12"><label class="form-label small fw-semibold">Application date</label><input type="text" name="application_date" class="form-control datepicker" required></div>
          </div>
        </div>
        <div class="modal-footer border-0 bg-light" style="border-radius:0 0 16px 16px;">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_new" value="1" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Admission Letter Modal (multi-university, one email) -->
<div class="modal fade" id="admissionModal" tabindex="-1" aria-labelledby="admissionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form id="admissionForm" enctype="multipart/form-data" autocomplete="off" novalidate>
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="admissionModalLabel">Send Admission Letter(s)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="student_id" id="modal_student_id">
          <input type="hidden" name="table" id="modal_table">

          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="modal_email" class="form-control" readonly>
          </div>

          <p class="small text-muted mb-2">Add one row per university. All letters are sent in a single email.</p>

          <div id="admissionRows" class="mb-2"></div>

          <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="btnAddAdmissionRow">+ Add another university</button>

          <!-- Progress Indicator -->
          <div id="sendingProgress" style="display:none;" class="text-info fw-bold mt-2">
            ⏳ Sending email... Please wait.
          </div>

          <!-- Result Message -->
          <div id="sendResult" class="mt-2 fw-semibold"></div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success">📧 Send all letters</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<template id="admissionRowTpl">
  <div class="admission-row border rounded p-2 mb-2 bg-light">
    <div class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label small mb-0">University</label>
        <select name="university_id[]" class="form-select form-select-sm admission-uni-select">
          <option value="">Select university…</option>
          <?php foreach ($universities_for_admission as $uu): ?>
            <option value="<?= (int) $uu['id'] ?>"><?= htmlspecialchars((string) $uu['name'], ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <label class="form-label small mb-0">Admission letter (PDF)</label>
        <input type="file" name="letters[]" class="form-control form-control-sm admission-letter-file" accept=".pdf,application/pdf">
      </div>
      <div class="col-md-1 text-end pb-1">
        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-admission-row d-none" title="Remove row">×</button>
      </div>
    </div>
  </div>
</template>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <form id="paymentForm" action="javascript:void(0);" autocomplete="off" novalidate>
      <div class="modal-content shadow-lg rounded-4">
        <!-- Header -->
        <div class="modal-header">
          <h5 class="modal-title fw-bold">
            💰 Record Application Payment
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <!-- Body -->
        <div class="modal-body px-4 py-3">
          <!-- Hidden Fields -->
          <input type="hidden" id="pay_student_id" name="student_id">
          <input type="hidden" id="pay_table" name="table">
          <input type="hidden" id="pay_package_id" name="package_id">

          <!-- Student Info -->
          <div class="row mb-3">
            <div class="col-md-6">
              <div class="small text-muted">Applicant</div>
              <div class="fw-semibold" id="pay_name">—</div>
            </div>
            <div class="col-md-6">
              <div class="small text-muted">Email</div>
              <div class="fw-semibold" id="pay_email">—</div>
            </div>
          </div>

          <hr class="my-3">

          <!-- Package Section -->
          <h6 class="fw-bold text-primary mb-3">📦 Package Details</h6>

          <!-- Package Select -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Select Package</label>
            <select id="package_select" class="form-select" required>
              <option value="" disabled selected>Select Package</option>
              <!-- loaded dynamically -->
            </select>
          </div>

          <div id="customPackageFields" class="d-none border border-warning border-2 rounded-3 bg-white p-3 mb-3">
            <p class="small text-muted mb-2">Package or fee item not in the list? Enter details below — saved to the same package &amp; payment tables.</p>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold" for="custom_package_name">Package / Service Name</label>
                <input type="text" id="custom_package_name" class="form-control" maxlength="255" placeholder="e.g. Special visa service">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold" for="custom_item_name">Fee Item Description</label>
                <input type="text" id="custom_item_name" class="form-control" maxlength="255" placeholder="Optional — defaults to package name">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold" for="custom_package_currency">Currency</label>
                <select id="custom_package_currency" class="form-select">
                  <option value="CAD">CAD</option>
                  <option value="USD">USD</option>
                  <option value="EUR">EUR</option>
                  <option value="GBP">GBP</option>
                  <option value="GHS">GHS</option>
                  <option value="RWF">RWF</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold" for="custom_package_amount">Proposed Total Price</label>
                <input type="number" id="custom_package_amount" class="form-control" min="0.01" step="0.01" placeholder="0.00">
              </div>
            </div>
          </div>

          <!-- Package Totals -->
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label small text-muted">Expected Total</label>
              <input type="text" id="expected_total" class="form-control fw-semibold" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label small text-muted">Paid So Far</label>
              <input type="text" id="paid_total" class="form-control" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label small text-muted">Remaining Balance</label>
              <input type="text" id="remaining_total" class="form-control fw-bold text-danger" readonly>
            </div>
          </div>

          <hr class="my-4">

          <!-- Fee Items -->
          <h6 class="fw-bold text-primary mb-3">🧾 Fee Items — Pay Per Item</h6>
          <div id="feeItemsWrapper" class="border rounded-3 bg-light p-3" style="min-height: 140px;">
            <div class="text-muted text-center py-4">Select a package to load fee items</div>
          </div>

          <!-- Payment Summary -->
          <div class="row mt-4 g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Total Payment (This Entry)</label>
              <input type="text" id="payment_grand_total" class="form-control fw-bold text-success" readonly value="0.00">
            </div>
          </div>

          <hr class="my-4">

          <!-- Payment Meta -->
          <h6 class="fw-bold text-primary mb-3">💳 Payment Details</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Payment Method</label>
              <select name="payment_method" class="form-select" required>
                <option value="Cash">Cash</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Mobile Money">Mobile Money</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Comment / Reference</label>
              <input type="text" name="comment" class="form-control" placeholder="Optional note or reference">
            </div>
          </div>

          <!-- Payment Progress -->
          <div id="paymentProgressWrapper" class="mt-4 d-none">
            <div class="small fw-semibold mb-1" id="paymentProgressText">Processing payment...</div>
            <div class="progress" style="height: 10px;">
              <div id="paymentProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%"></div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer bg-light rounded-bottom-4">
          <button type="submit" class="btn btn-success px-4 fw-semibold">💾 Record Payment</button>
          <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Status update + optional channels (one tap) -->
<div class="modal fade" id="statusNotifyModal" tabindex="-1" aria-labelledby="statusNotifyModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header text-white py-3" style="background: linear-gradient(135deg, #427431 0%, #3661B9 100%);">
        <div>
          <h5 class="modal-title fw-semibold mb-0" id="statusNotifyModalLabel">Save status</h5>
          <div class="small opacity-75 mt-1">Choose whether to notify the applicant</div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 pt-3">
        <div class="text-center mb-4">
          <div class="d-inline-block px-3 py-1 rounded-pill small fw-semibold mb-2" style="background:#e8eef9;color:#427431;">New status</div>
          <div class="fs-4 fw-bold text-dark" id="notify_status_label_display">—</div>
        </div>
        <div id="statusRejectReasonWrap" class="d-none mb-3">
          <label for="statusRejectReason" class="form-label small fw-semibold text-danger mb-1">Reason for rejection</label>
          <textarea id="statusRejectReason" class="form-control" rows="3" maxlength="2000" placeholder="Required if you choose Email, WhatsApp, or both"></textarea>
          <div class="form-text small text-muted">This message is included when you notify the applicant. Optional if you save with &quot;Record only&quot;.</div>
        </div>
        <p class="text-center text-muted small mb-3">Tap one option — you can update the record without sending anything.</p>
        <div class="row g-2 g-md-3">
          <div class="col-6 col-lg-3">
            <button type="button" class="btn notify-channel-btn w-100 py-3 rounded-3 shadow-sm active" data-ne="0" data-nw="0" title="Save only">Record<br><span class="small fw-normal opacity-75">no alert</span></button>
          </div>
          <div class="col-6 col-lg-3">
            <button type="button" class="btn notify-channel-btn w-100 py-3 rounded-3 shadow-sm" data-ne="1" data-nw="0" title="Email">✉ Email</button>
          </div>
          <div class="col-6 col-lg-3">
            <button type="button" class="btn notify-channel-btn w-100 py-3 rounded-3 shadow-sm" data-ne="0" data-nw="1" title="WhatsApp">WhatsApp</button>
          </div>
          <div class="col-6 col-lg-3">
            <button type="button" class="btn notify-channel-btn w-100 py-3 rounded-3 shadow-sm" data-ne="1" data-nw="1" title="Both">Email +<br>WhatsApp</button>
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light border-0 pt-2">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="statusNotifyCancel">Cancel</button>
        <button type="button" class="btn fw-semibold text-white px-4" style="background: linear-gradient(135deg, #427431, #3661B9);" id="statusNotifyConfirm">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Success Toast -->
<div class="toast-container">
  <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto">✅ Success</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
    </div>
    <div class="toast-body">Operation completed successfully.</div>
  </div>
  <div id="warningToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header bg-warning text-dark">
      <strong class="me-auto">⚠ Notice</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
    </div>
    <div class="toast-body">Something needs attention.</div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="assets/js/payment-other-package.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  window.CAN_DELETE_STUDENT_APP = <?= json_encode($canDeleteApplication) ?>;
</script>

<script>
$(function() {
  window.showLoading = function () {
    $('#loadingOverlay').fadeIn();
  };

  window.hideLoading = function () {
    $('#loadingOverlay').fadeOut();
  };

  const showLoading = window.showLoading;
  const hideLoading = window.hideLoading;

  // SEARCH
  $('#searchInput').on('keyup', function(){
    const value = $(this).val().toLowerCase();
    $('#applicantTable tbody tr').filter(function(){
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
    });
  });

  // DELETE APPLICATION (Superadmin · api/applications.php?action=delete — main student_applications only)
  $(document).on('click', '.btn-delete-app', function (e) {
    e.preventDefault();
    e.stopPropagation();
    if (!window.CAN_DELETE_STUDENT_APP) {
      alert('Only Superadmin can delete applications.');
      return;
    }
    const id = $(this).data('delete-id');
    if (!id) return;
    if (!confirm('Permanently delete this application and related jobs? This cannot be undone.')) {
      return;
    }
    const fd = new FormData();
    fd.append('id', String(id));
    showLoading();
    fetch('api/applications.php?action=delete', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    })
      .then(function (r) { return r.text(); })
      .then(function (text) {
        hideLoading();
        var json;
        try {
          json = JSON.parse(text);
        } catch (err) {
          alert('Delete failed: invalid server response.');
          return;
        }
        if (!json.success) {
          alert(json.message || 'Delete failed.');
          return;
        }
        if (typeof window.showSuccessToast === 'function') {
          window.showSuccessToast('Application deleted');
        } else {
          alert('Application deleted.');
        }
        var $row = $('.btn-delete-app[data-delete-id="' + id + '"]').closest('tr');
        $row.fadeOut(280, function () { $(this).remove(); });
      })
      .catch(function (err) {
        hideLoading();
        console.error(err);
        alert('Delete failed. Check your connection and try again.');
      });
  });

  // STATUS DROPDOWN TOGGLE
  $(document).on('click', '.status-dropdown-toggle', function(e) {
    e.stopPropagation();
    const dropdown = $(this).closest('.status-dropdown');
    const menu = dropdown.find('.status-dropdown-menu');
    
    // Close other open dropdowns
    $('.status-dropdown-menu').not(menu).hide();
    
    // Toggle current dropdown
    menu.toggle();
  });

  // STATUS DROPDOWN ITEM SELECTION
  $(document).on('click', '.status-dropdown-item', function(e) {
    e.stopPropagation();
    const item = $(this);
    const flag = item.data('flag');
    const dropdown = item.closest('.status-dropdown');
    const id = dropdown.data('id');
    const table = dropdown.data('table');
    const toggle = dropdown.find('.status-dropdown-toggle');
    const statusText = toggle.find('.status-text');
    const originalStatus = statusText.text();
    const label = item.find('span:first').text();

    dropdown.find('.status-dropdown-menu').hide();

    // Special handling for Admit (letter flow — keep existing behavior)
    if (flag === 'admit') {
      showLoading();
      const row = dropdown.closest('tr');
      const email = row.find('td[data-field="email"]').text().trim();
      $('#modal_student_id').val(id);
      $('#modal_table').val(table);
      $('#modal_email').val(email);
      hideLoading();
      new bootstrap.Modal(
        document.getElementById('admissionModal'),
        { backdrop: 'static', keyboard: false }
      ).show();
      return;
    }

    // Special handling for App Paid (payment modal)
    if (flag === 'app_paid') {
      showLoading();
      const row = dropdown.closest('tr');
      const fullName = row.find('td').eq(1).text().trim();
      const email = row.find('td[data-field="email"]').text().trim();
      $('#pay_student_id').val(id);
      $('#pay_table').val(table);
      $('#pay_name').text(fullName);
      $('#pay_email').text(email);
      hideLoading();
      $('#expected_total').val('Loading...');
      $('#paid_total').val('Loading...');
      $('#remaining_total').val('Loading...');
      const paymentModal = new bootstrap.Modal(
        document.getElementById('paymentModal'),
        { backdrop: 'static', keyboard: false }
      );
      paymentModal.show();
      $('#package_select')
        .prop('disabled', false)
        .html('<option disabled selected>Loading packages...</option>');
      $('#expected_total').val('');
      $('#paid_total').val('');
      $('#remaining_total').val('');
      $.getJSON('load-payment-info.php', { student_id: id })
        .done(function (data) {
          if (!data || typeof data !== 'object') {
            alert('Invalid payment response from server');
            return;
          }
          if (data.error) {
            alert(data.error);
            return;
          }
          if (!Array.isArray(data.packages)) {
            alert('No packages found');
            return;
          }
          let pkgOptions = '<option value="" disabled selected>Select Package</option>';
          data.packages.forEach(pkg => {
            pkgOptions += '<option value="' + pkg.id + '">' + pkg.name + ' (' + pkg.currency + ' ' + Number(pkg.total_amount).toFixed(2) + ')</option>';
          });
          $('#package_select').html(window.appendOtherPackageOption(pkgOptions));
        })
        .fail(function (xhr) {
          console.error(xhr.responseText);
          alert('Failed to load payment packages');
        });
      return;
    }

    // Normal statuses: choose Email / WhatsApp / both / none, then save
    window._statusNotifyPending = {
      dropdown: dropdown,
      id: id,
      table: table,
      flag: flag,
      label: label,
      originalStatus: originalStatus,
      statusText: statusText,
      item: item
    };
    $('#notify_status_label_display').text(label);
    $('.notify-channel-btn').removeClass('active');
    $('.notify-channel-btn[data-ne="0"][data-nw="0"]').addClass('active');
    if (flag === 'deny') {
      $('#statusRejectReasonWrap').removeClass('d-none');
    } else {
      $('#statusRejectReasonWrap').addClass('d-none');
      $('#statusRejectReason').val('');
    }
    new bootstrap.Modal(document.getElementById('statusNotifyModal')).show();
  });

  $(document).on('click', '.notify-channel-btn', function() {
    $('.notify-channel-btn').removeClass('active');
    $(this).addClass('active');
  });

  $('#statusNotifyConfirm').on('click', function() {
    const p = window._statusNotifyPending;
    if (!p) return;
    const $sel = $('.notify-channel-btn.active');
    const ne = $sel.length ? parseInt($sel.data('ne'), 10) || 0 : 0;
    const nw = $sel.length ? parseInt($sel.data('nw'), 10) || 0 : 0;
    const rejectReason = ($('#statusRejectReason').val() || '').trim();
    if (p.flag === 'deny' && (ne || nw) && rejectReason === '') {
      alert('Please enter a rejection reason before sending email or WhatsApp.');
      return;
    }
    window._statusNotifyPending = null;
    const modalEl = document.getElementById('statusNotifyModal');
    const inst = bootstrap.Modal.getInstance(modalEl);
    if (inst) inst.hide();

    const $btn = $(this).prop('disabled', true);
    showLoading();
    $.ajax({
      url: 'update-flag.php',
      method: 'POST',
      dataType: 'json',
      data: {
        id: p.id,
        flag: p.flag,
        table: p.table,
        notify_email: ne,
        notify_whatsapp: nw,
        rejection_reason: rejectReason,
        json: 1
      },
      success: function(data) {
        hideLoading();
        $btn.prop('disabled', false);
        if (!data || data.ok !== true) {
          var errMsg = data && data.error ? data.error : 'unknown';
          if (errMsg === 'rejection_reason_required') {
            errMsg = 'A rejection reason is required when sending email or WhatsApp.';
          }
          alert('Failed to update status: ' + errMsg);
          return;
        }
        p.statusText.text(p.label);
        p.item.find('.status-check').addClass('active');

        const n = data.notify;
        const parts = ['Status saved'];
        let anyFail = false;

        if ((ne || nw) && !n) {
          anyFail = true;
          parts.push('Notifications failed (server error).');
        }

        if (n && n.email && n.email.requested) {
          if (n.email.sent) {
            parts.push('Email sent');
          } else {
            anyFail = true;
            parts.push('Email failed' + (n.email.error ? ': ' + n.email.error : ''));
          }
        }
        if (n && n.whatsapp && n.whatsapp.requested) {
          if (n.whatsapp.sent) {
            if (n.whatsapp.method === 'text') {
              parts.push('WhatsApp sent (session message)');
            } else {
              parts.push('WhatsApp sent');
            }
          } else {
            anyFail = true;
            parts.push('WhatsApp failed' + (n.whatsapp.error ? ': ' + n.whatsapp.error : ''));
          }
        }
        if (!ne && !nw) {
          parts.length = 1;
          parts[0] = 'Status saved (no notification)';
        }

        const msg = parts.join(' · ');
        if (anyFail && typeof window.showWarningToast === 'function') {
          window.showWarningToast(msg);
        } else {
          showSuccessToast(msg);
        }
      },
      error: function(xhr, status, error) {
        hideLoading();
        $btn.prop('disabled', false);
        let detail = error;
        try {
          const j = xhr.responseJSON;
          if (j && j.error) detail = j.error;
        } catch (e) { /* ignore */ }
        alert('Network error: ' + detail);
        console.error('AJAX Error:', status, error, xhr.responseText);
      }
    });
  });

  $('#statusNotifyModal').on('hidden.bs.modal', function() {
    if (window._statusNotifyPending) {
      window._statusNotifyPending = null;
    }
    $('#statusRejectReason').val('');
    $('#statusRejectReasonWrap').addClass('d-none');
  });

  // CLOSE DROPDOWNS WHEN CLICKING OUTSIDE
  $(document).on('click', function() {
    $('.status-dropdown-menu').hide();
  });

  // Application ID live update
  $(document).on('input', '.live-app-id', function(){
    const id = $(this).data('id');
    const value = $(this).val();
    const source = $(this).closest('tr').data('source') || 'student_applications';
    $.post("update-static.php", { id, application_id: value, source }, function(resp){
      console.log(resp);
    });
  });

  // Remarks live update
  $(document).on('input', '.live-app-remarks', function(){
    const id = $(this).data('id');
    const value = $(this).val();
    const source = $(this).closest('tr').data('source') || 'student_applications';
    $.post("update-static.php", { id, application_remarks: value, source }, function(resp){
      console.log(resp);
    });
  });

  // Editable fields update
  $(document).on('blur', '.editable-cell', function() {
    const cell = $(this);
    const id = cell.data('id');
    const field = cell.data('field');
    const value = cell.text().trim();
    const source = cell.closest('tr').data('source') || 'student_applications';

    showLoading();
    $.post('update-field.php', { id, field, value, source }, function(resp) {
      hideLoading();
      if (resp !== 'ok') {
        alert('Failed to save field');
      }
    }).fail(function() {
      hideLoading();
      alert('Network error while saving field');
    });
  });

  // DATE PICKER (if any datepickers exist)
  if ($('.datepicker').length) {
    flatpickr(".datepicker", {
      altInput: true,
      altFormat: "F j, Y",
      dateFormat: "Y-m-d",
      maxDate: "today"
    });
  }

  function updateAdmissionRemoveButtons() {
    const rows = document.querySelectorAll('#admissionRows .admission-row');
    rows.forEach(function (row) {
      const b = row.querySelector('.btn-remove-admission-row');
      if (!b) return;
      if (rows.length > 1) {
        b.classList.remove('d-none');
      } else {
        b.classList.add('d-none');
      }
    });
  }

  function addAdmissionRow() {
    const tpl = document.getElementById('admissionRowTpl');
    const container = document.getElementById('admissionRows');
    if (!tpl || !container || !tpl.content) return;
    container.appendChild(document.importNode(tpl.content, true));
    updateAdmissionRemoveButtons();
  }

  function resetAdmissionRows() {
    const container = document.getElementById('admissionRows');
    if (!container) return;
    container.innerHTML = '';
    addAdmissionRow();
  }

  $('#admissionModal').on('show.bs.modal', function () {
    $('#sendResult').text('').removeClass('text-success text-danger fw-bold');
    $('#sendingProgress').hide();
    resetAdmissionRows();
  });

  $('#btnAddAdmissionRow').on('click', function () {
    addAdmissionRow();
  });

  $(document).on('click', '.btn-remove-admission-row', function () {
    const rows = document.querySelectorAll('#admissionRows .admission-row');
    if (rows.length <= 1) return;
    $(this).closest('.admission-row').remove();
    updateAdmissionRemoveButtons();
  });

  $('#admissionForm').on('submit', function(e){
    e.preventDefault();
    $('#sendResult').text('').removeClass('text-success text-danger fw-bold');

    const email = ($('#modal_email').val() || '').trim();
    if (!email) {
      $('#sendResult').text('❌ Applicant email is missing. Edit the email cell in the table, then try again.').addClass('text-danger fw-bold');
      return;
    }

    let completeRows = 0;
    let brokenRow = false;
    $('#admissionRows .admission-row').each(function () {
      const uni = ($(this).find('.admission-uni-select').val() || '').trim();
      const f = $(this).find('.admission-letter-file')[0];
      const hasFile = f && f.files && f.files.length > 0;
      if (!uni && !hasFile) {
        return;
      }
      if (uni && hasFile) {
        completeRows++;
        return;
      }
      brokenRow = true;
      return false;
    });

    if (brokenRow) {
      $('#sendResult').text('❌ Each row needs both a university and a PDF (or leave the row empty).').addClass('text-danger fw-bold');
      return;
    }
    if (completeRows < 1) {
      $('#sendResult').text('❌ Add at least one university and attach its PDF letter.').addClass('text-danger fw-bold');
      return;
    }

    const formData = new FormData(this);

    showLoading();
    $('#sendingProgress').show();

    $.ajax({
      url: 'send_admission.php',
      method: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(resp) {
        hideLoading();
        $('#sendingProgress').hide();
        if (resp.trim() === 'ok') {
          $('#sendResult').text('✅ Admission email sent successfully!').addClass('text-success fw-bold');
          showSuccessToast('Admission letter(s) sent successfully');

          setTimeout(() => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('admissionModal'));
            modal.hide();
            $('#admissionForm')[0].reset();
            $('#sendResult').text('');
            resetAdmissionRows();
          }, 2000);
        } else {
          $('#sendResult').text('❌ Failed to send: ' + resp).addClass('text-danger fw-bold');
        }
      },
      error: function(xhr, status, error) {
        hideLoading();
        $('#sendingProgress').hide();
        $('#sendResult').text('❌ Network error: ' + error).addClass('text-danger fw-bold');
        console.error('Send admission error:', status, error);
      }
    });
  });

  $('#admissionModal').on('hidden.bs.modal', function () {
    $('#admissionForm')[0].reset();
    $('#sendResult').text('').removeClass('text-success text-danger fw-bold');
    resetAdmissionRows();
  });
});

/* =========================================================
   PAYMENT MODAL — PER ITEM PAYMENT (FINAL / ERROR-PROOF)
========================================================= */
(() => {
  let paymentCurrency = '';
  let itemPayments = {};
  let isSubmitting = false;

  const modalEl = document.getElementById('paymentModal');

  /* =====================================================
     RESET MODAL
  ===================================================== */
  modalEl.addEventListener('hidden.bs.modal', () => {
    document.activeElement?.blur();

    $('#package_select').html('<option value="" disabled selected>Select Package</option>');
    $('#feeItemsWrapper').html('<div class="text-muted text-center py-4">Select a package to load fee items</div>');
    $('#expected_total, #paid_total, #remaining_total').val('');
    $('#payment_grand_total').val('0.00');
    $('#pay_package_id').val('');
    $('select[name="payment_method"]').val('Cash');
    $('input[name="comment"]').val('');
    window.resetCustomPackageFields();
    itemPayments = {};
    isSubmitting = false;
  });

  window._paymentOtherRefresh = function () {
    paymentCurrency = window.refreshCustomPackageUI(itemPayments, updateGrandTotal) || paymentCurrency;
  };

  /* =====================================================
     PACKAGE SELECT
  ===================================================== */
  $(document).on('change', '#package_select', function () {
    const packageId = this.value;
    const studentId = $('#pay_student_id').val();
    if (!packageId || !studentId) return;

    $('#pay_package_id').val(packageId);
    itemPayments = {};

    if (packageId === 'other') {
      window.resetCustomPackageFields();
      $('#customPackageFields').removeClass('d-none');
      $('#expected_total, #paid_total, #remaining_total').val('');
      $('#payment_grand_total').val('0.00');
      window.refreshCustomPackageUI(itemPayments, updateGrandTotal);
      return;
    }

    window.resetCustomPackageFields();

    $('#feeItemsWrapper').html('<div class="text-muted text-center py-4">Loading fee items…</div>');

    $.getJSON('load-package-details.php', {
      package_id: packageId,
      student_id: studentId
    })
    .done(data => {
      if (!data || typeof data !== 'object') return;

      paymentCurrency = data.currency || '';
      const total = Number(data.total || 0);
      const paid  = Number(data.paid || 0);
      const remaining = Math.max(0, total - paid);

      $('#expected_total').val(`${paymentCurrency} ${total.toFixed(2)}`);
      $('#paid_total').val(`${paymentCurrency} ${paid.toFixed(2)}`);
      $('#remaining_total').val(`${paymentCurrency} ${remaining.toFixed(2)}`);

      if (!Array.isArray(data.items)) return;

      let html = '<div class="list-group list-group-flush">';
      data.items.forEach(item => {
        const left = Math.max(0, Number(item.amount || 0) - Number(item.paid || 0));
        if (left <= 0) return;

        html += `
          <div class="list-group-item py-3">
            <div class="row align-items-center">
              <div class="col-md-5">
                <strong>${item.name}</strong><br>
                <small class="text-muted">Remaining: ${paymentCurrency} ${left.toFixed(2)}</small>
              </div>
              <div class="col-md-4">
                <input type="number" class="form-control form-control-sm item-payment-input"
                  min="0" max="${left}" step="0.01" data-item-id="${item.id}" data-max="${left}"
                  placeholder="0.00">
              </div>
              <div class="col-md-3 text-end">
                <span class="badge bg-light text-dark">${paymentCurrency}</span>
              </div>
            </div>
          </div>
        `;
      });
      html += '</div>';
      $('#feeItemsWrapper').html(html);
      updateGrandTotal();
    })
    .fail(function() {
      $('#feeItemsWrapper').html('<div class="text-danger text-center py-4">Failed to load fee items</div>');
    });
  });

  /* =====================================================
     ITEM INPUT
  ===================================================== */
  function paymentShowLoading() {
    if (typeof window.showLoading === 'function') window.showLoading();
    else $('#loadingOverlay').fadeIn();
  }

  function paymentHideLoading() {
    if (typeof window.hideLoading === 'function') window.hideLoading();
    else $('#loadingOverlay').fadeOut();
  }

  function parsePaymentResponse(text) {
    if (!text) return null;
    try {
      return JSON.parse(text);
    } catch (ignore) {
      const match = text.match(/\{"success"\s*:\s*true[\s\S]*?\}/);
      if (match) {
        try {
          return JSON.parse(match[0]);
        } catch (ignore2) {}
      }
    }
    return null;
  }

  function refreshApplicantStatusAfterPayment(resp) {
    const studentId = String($('#pay_student_id').val() || resp?.application_id || '');
    const table = String($('#pay_table').val() || resp?.source_table || '');
    if (!studentId) return;

    const dropdown = $('.status-dropdown').filter(function () {
      return String($(this).data('id')) === studentId
        && (!table || String($(this).data('table')) === table);
    }).first();
    if (!dropdown.length) return;

    dropdown.find('.status-text').text('App Paid');
    dropdown.find('.status-dropdown-item[data-flag="app_paid"] .status-check').addClass('active');
  }

  function handlePaymentSuccess(resp) {
    updatePaymentProgress(60, 'Generating receipt & sending email...');
    isSubmitting = false;
    setTimeout(() => {
      finishPaymentProgress(true);
      paymentHideLoading();
      refreshApplicantStatusAfterPayment(resp);
      showSuccessToast(resp.message || 'Payment recorded successfully');
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
    }, 800);
  }

  $(document).on('input', '.item-payment-input', function () {
    const id  = $(this).attr('data-item-id');
    const max = Number($(this).attr('data-max'));
    let val   = Number(this.value || 0);

    if (val > max) {
      val = max;
      this.value = max.toFixed(2);
    }

    if (val > 0) itemPayments[id] = val;
    else delete itemPayments[id];

    updateGrandTotal();
  });

  function updateGrandTotal() {
    const sum = Object.values(itemPayments).reduce((a, b) => a + b, 0);
    $('#payment_grand_total').val(`${paymentCurrency} ${sum.toFixed(2)}`);
  }

  /* =========================================================
   SUBMIT PAYMENT
  ========================================================= */
  $('#paymentForm').on('submit', function (e) {
    e.preventDefault();

    if (isSubmitting) return;

    const isOther = window.isOtherPackageSelected && window.isOtherPackageSelected();
    if (isOther && typeof window.syncCustomItemPaymentsFromDom === 'function') {
      window.syncCustomItemPaymentsFromDom(itemPayments);
    }

    if (!isOther && !Object.keys(itemPayments).length) {
      alert('Please enter at least one item payment');
      return;
    }

    isSubmitting = true;
    paymentShowLoading();
    startPaymentProgress();

    try {
      let payload = {
        student_id: $('#pay_student_id').val(),
        table: $('#pay_table').val(),
        package_id: $('#pay_package_id').val(),
        payment_method: $('select[name="payment_method"]').val(),
        comment: $('input[name="comment"]').val(),
        items: itemPayments
      };

      if (isOther) {
        if (typeof window.buildCustomPaymentPayload !== 'function') {
          throw new Error('Payment script failed to load. Refresh the page and try again.');
        }
        payload = window.buildCustomPaymentPayload(payload, itemPayments);
        if (!payload) {
          isSubmitting = false;
          paymentHideLoading();
          return;
        }
      }

      $.ajax({
        url: 'record-payment.php',
        method: 'POST',
        data: JSON.stringify(payload),
        contentType: 'application/json',
        dataType: 'json',
        success: function (resp) {
          if (resp && resp.success === true) {
            handlePaymentSuccess(resp);
            return;
          }
          isSubmitting = false;
          finishPaymentProgress(false);
          paymentHideLoading();
          alert(resp?.message || 'Payment failed');
        },
        error: function (xhr) {
          const recovered = parsePaymentResponse(xhr.responseText || '');
          if (recovered && recovered.success === true) {
            handlePaymentSuccess(recovered);
            return;
          }
          isSubmitting = false;
          finishPaymentProgress(false);
          paymentHideLoading();
          let msg = 'Server error. Please try again.';
          try {
            const err = JSON.parse(xhr.responseText || '{}');
            if (err.message) msg = err.message;
          } catch (ignore) {}
          console.error('Payment error:', xhr.responseText);
          alert(msg);
        }
      });
    } catch (err) {
      isSubmitting = false;
      finishPaymentProgress(false);
      paymentHideLoading();
      alert(err.message || 'Could not start payment.');
    }
  });

  /* =====================================================
     SUCCESS TOAST
  ===================================================== */
  window.showSuccessToast = function (msg) {
    const toast = document.getElementById('successToast');
    if (!toast) return alert(msg);
    toast.querySelector('.toast-body').innerText = '✅ ' + msg;
    new bootstrap.Toast(toast).show();
  };

  window.showWarningToast = function (msg) {
    const toast = document.getElementById('warningToast');
    if (!toast) return alert(msg);
    toast.querySelector('.toast-body').innerText = msg;
    new bootstrap.Toast(toast).show();
  };
})();

/* =====================================================
   AUTO RECEIPT PRINT TRIGGER
===================================================== */
(function () {
  $(document).ajaxSuccess(function (event, xhr, settings, response) {
    if (!settings.url || !settings.url.includes('record-payment.php')) return;
    if (!response || response.success !== true || !response.receipt_no) return;

    setTimeout(function () {
      const printUrl = 'printReceipt.php?receipt_no=' + encodeURIComponent(response.receipt_no);
      const win = window.open(printUrl, '_blank');
      if (!win) alert('⚠️ Please allow popups to print the receipt.');
    }, 300);
  });
})();

function startPaymentProgress() {
  const wrapper = $('#paymentProgressWrapper');
  const bar     = $('#paymentProgressBar');
  const text    = $('#paymentProgressText');

  if (!wrapper.length || !bar.length || !text.length) {
    console.warn('Payment progress elements not found');
    return;
  }

  bar.stop(true, true).removeClass('bg-danger')
     .addClass('bg-success progress-bar-striped progress-bar-animated')
     .css('width', '0%');
  text.text('Initializing payment...');
  wrapper.removeClass('d-none');

  setTimeout(() => {
    bar.css('width', '15%');
    text.text('Recording payment...');
  }, 120);
}

function updatePaymentProgress(percent, text) {
  $('#paymentProgressBar').css('width', percent + '%');
  $('#paymentProgressText').text(text);
}

function finishPaymentProgress(success = true) {
  updatePaymentProgress(100, success ? 'Completed successfully' : 'Failed');
  $('#paymentProgressBar').removeClass('bg-success').addClass(success ? 'bg-success' : 'bg-danger');

  setTimeout(() => {
    $('#paymentProgressWrapper').addClass('d-none');
    $('#paymentProgressBar').css('width', '0%').removeClass('bg-danger').addClass('bg-success');
  }, 2000);
}

// Duplicate search script for compatibility
document.getElementById("searchInput").addEventListener("keyup", function() {
  const value = this.value.toLowerCase();
  const rows = document.querySelectorAll("#applicantTable tbody tr");

  rows.forEach(row => {
    const rowText = row.textContent.toLowerCase();
    row.style.display = rowText.includes(value) ? "" : "none";
  });
});

// Handle escape key to close dropdowns
$(document).on('keydown', function(e) {
  if (e.key === 'Escape') {
    $('.status-dropdown-menu').hide();
  }
});
</script>

</body>
</html>