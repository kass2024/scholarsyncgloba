<?php
// staff-management.php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/company_branding.php';
require_once __DIR__ . '/helpers/secure_file.php';
$companyBrandName = PCVC_COMPANY_DISPLAY_NAME;
/* ============================================================
   SECURITY CHECK 
============================================================ */
if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    exit('Access denied. Please log in.');
}

$isSuperAdmin = ($_SESSION['role'] === 'superadmin');
$currentUserId = $_SESSION['id'];

/* ============================================================
   DELETE STAFF (SUPERADMIN ONLY)
============================================================ */
if (isset($_GET['delete']) && $isSuperAdmin) {
    $id = intval($_GET['delete']);
    
    // Prevent self-deletion
    if ($id == $currentUserId) {
        $_SESSION['error'] = "You cannot delete your own account";
    } else {
        $conn->query("DELETE FROM admins WHERE id = $id");
        $_SESSION['success'] = "Staff member deleted successfully";
    }
    header("Location: staff-management.php");
    exit;
}

/* ============================================================
   UPDATE STAFF (SUPERADMIN ONLY)
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff']) && $isSuperAdmin) {

    $id        = intval($_POST['id']);
    $fullName  = trim((string) ($_POST['full_name'] ?? ''));
    $emailIn   = trim((string) ($_POST['email'] ?? ''));
    $phoneIn   = trim((string) ($_POST['phone_number'] ?? ''));
    $usernameIn = trim((string) ($_POST['username'] ?? ''));

    $salary    = floatval($_POST['salary_per_minute']);
    $monthly   = ($_POST['monthly_salary'] !== '' ? floatval($_POST['monthly_salary']) : null);
    $currency  = $_POST['salary_currency'] ?? 'USD';
    $break     = intval($_POST['allowed_break_minutes']);
    $days      = intval($_POST['work_days_per_week']);
    $sheet     = trim($_POST['sheet_id'] ?? '');
    $link      = trim($_POST['sheet_link'] ?? '');
    $officeId  = ($_POST['office_id'] !== '' ? intval($_POST['office_id']) : null);

    // Role update (superadmin only can change roles)
    $role = $_POST['role'] ?? 'staff';

    // HR / CONTRACT FIELDS
    $position     = trim($_POST['position'] ?? '');
    $empType      = trim($_POST['employment_type'] ?? '');
    $startDate    = $_POST['employment_start_date'] ?: null;
    $nid          = trim($_POST['national_id'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $dob          = $_POST['date_of_birth'] ?: null;
    $marital      = $_POST['marital_status'] ?? null;
    $nationality  = trim($_POST['nationality'] ?? '');
    $birthPlace   = trim($_POST['place_of_birth'] ?? '');

    if ($fullName === '' || $emailIn === '' || $usernameIn === '') {
        $_SESSION['error'] = 'Full name, email, and username are required.';
        header('Location: staff-management.php');
        exit;
    }
    if (!filter_var($emailIn, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Please enter a valid email address.';
        header('Location: staff-management.php');
        exit;
    }

    $nameParts = preg_split('/\s+/u', $fullName, 2, PREG_SPLIT_NO_EMPTY);
    $firstName = $nameParts[0] ?? '';
    $lastName = isset($nameParts[1]) ? trim((string) $nameParts[1]) : '';

    $chkUser = $conn->prepare('SELECT id FROM admins WHERE username = ? AND id != ? LIMIT 1');
    if ($chkUser) {
        $chkUser->bind_param('si', $usernameIn, $id);
        $chkUser->execute();
        $dupUser = $chkUser->get_result()->fetch_assoc();
        $chkUser->close();
        if ($dupUser) {
            $_SESSION['error'] = 'That username is already taken by another account.';
            header('Location: staff-management.php');
            exit;
        }
    }

    $chkMail = $conn->prepare('SELECT id FROM admins WHERE email = ? AND id != ? LIMIT 1');
    if ($chkMail) {
        $chkMail->bind_param('si', $emailIn, $id);
        $chkMail->execute();
        $dupMail = $chkMail->get_result()->fetch_assoc();
        $chkMail->close();
        if ($dupMail) {
            $_SESSION['error'] = 'That email is already used by another account.';
            header('Location: staff-management.php');
            exit;
        }
    }

    $stmt = $conn->prepare("
        UPDATE admins SET
            username = ?,
            first_name = ?,
            last_name = ?,
            full_name = ?,
            email = ?,
            phone_number = ?,
            role = ?,
            position = ?,
            employment_type = ?,
            employment_start_date = ?,
            national_id = ?,
            date_of_birth = ?,
            marital_status = ?,
            nationality = ?,
            place_of_birth = ?,
            address = ?,
            salary_per_minute = ?,
            monthly_salary = ?,
            salary_currency = ?,
            allowed_break_minutes = ?,
            work_days_per_week = ?,
            sheet_id = ?,
            sheet_link = ?,
            office_id = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        'ssssssssssssssssddsiissii',
        $usernameIn,
        $firstName,
        $lastName,
        $fullName,
        $emailIn,
        $phoneIn,
        $role,
        $position,
        $empType,
        $startDate,
        $nid,
        $dob,
        $marital,
        $nationality,
        $birthPlace,
        $address,
        $salary,
        $monthly,
        $currency,
        $break,
        $days,
        $sheet,
        $link,
        $officeId,
        $id
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Staff information updated successfully';

        if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed, true) && $_FILES['profile_photo']['size'] <= 2 * 1024 * 1024) {
                $oldStmt = $conn->prepare('SELECT profile_photo FROM admins WHERE id = ? LIMIT 1');
                if ($oldStmt) {
                    $oldStmt->bind_param('i', $id);
                    $oldStmt->execute();
                    $oldRow = $oldStmt->get_result()->fetch_assoc();
                    $oldStmt->close();
                    $photoName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadDir . $photoName)) {
                        $photoUpd = $conn->prepare('UPDATE admins SET profile_photo = ? WHERE id = ?');
                        if ($photoUpd) {
                            $photoUpd->bind_param('si', $photoName, $id);
                            $photoUpd->execute();
                            $photoUpd->close();
                            $oldPhoto = trim((string) ($oldRow['profile_photo'] ?? ''));
                            if ($oldPhoto !== '' && $oldPhoto !== 'default_avatar.png') {
                                $oldPath = $uploadDir . basename($oldPhoto);
                                if (is_file($oldPath)) {
                                    @unlink($oldPath);
                                }
                            }
                        }
                    }
                }
            }
        }
    } else {
        $_SESSION['error'] = 'Update failed: ' . $conn->error;
    }
    $stmt->close();

    header('Location: staff-management.php');
    exit;
}

/* ============================================================
   ADD STAFF (SUPERADMIN ONLY) — all fields optional
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff']) && $isSuperAdmin) {
    $fullName   = trim((string) ($_POST['full_name'] ?? ''));
    $emailIn    = trim((string) ($_POST['email'] ?? ''));
    $phoneIn    = trim((string) ($_POST['phone_number'] ?? ''));
    $usernameIn = trim((string) ($_POST['username'] ?? ''));
    $passwordIn = (string) ($_POST['password'] ?? '');
    $role       = trim((string) ($_POST['role'] ?? 'staff'));
    if (!in_array($role, ['superadmin', 'admin', 'staff', 'agent'], true)) {
        $role = 'staff';
    }

    $position    = trim((string) ($_POST['position'] ?? ''));
    $empType     = trim((string) ($_POST['employment_type'] ?? ''));
    $startDate   = trim((string) ($_POST['employment_start_date'] ?? '')) ?: null;
    $nid         = trim((string) ($_POST['national_id'] ?? ''));
    $address     = trim((string) ($_POST['address'] ?? ''));
    $dob         = trim((string) ($_POST['date_of_birth'] ?? '')) ?: null;
    $marital     = trim((string) ($_POST['marital_status'] ?? '')) ?: null;
    $nationality = trim((string) ($_POST['nationality'] ?? ''));
    $birthPlace  = trim((string) ($_POST['place_of_birth'] ?? ''));

    $salary   = ($_POST['salary_per_minute'] !== '' ? floatval($_POST['salary_per_minute']) : 0.0);
    $monthly  = ($_POST['monthly_salary'] !== '' ? floatval($_POST['monthly_salary']) : null);
    $currency = trim((string) ($_POST['salary_currency'] ?? 'USD'));
    if (!in_array($currency, ['KES', 'RWF', 'USD'], true)) {
        $currency = 'USD';
    }
    $break    = ($_POST['allowed_break_minutes'] !== '' ? intval($_POST['allowed_break_minutes']) : 60);
    $days     = ($_POST['work_days_per_week'] !== '' ? intval($_POST['work_days_per_week']) : 5);
    $sheet    = trim((string) ($_POST['sheet_id'] ?? ''));
    $link     = trim((string) ($_POST['sheet_link'] ?? ''));
    $officeId = (isset($_POST['office_id']) && $_POST['office_id'] !== '' ? intval($_POST['office_id']) : null);

    if ($empType !== '' && !in_array($empType, ['Full-time', 'Part-time', 'Contract'], true)) {
        $empType = '';
    }
    if ($marital !== null && !in_array($marital, ['Single', 'Married', 'Divorced', 'Widowed'], true)) {
        $marital = null;
    }

    if ($emailIn !== '' && !filter_var($emailIn, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Please enter a valid email address, or leave email blank.';
        header('Location: staff-management.php');
        exit;
    }

    // DB requires username + password_hash — auto-fill only when left blank
    if ($usernameIn === '') {
        $usernameIn = 'staff_' . strtolower(bin2hex(random_bytes(4)));
    }
    if ($passwordIn === '') {
        $passwordIn = 'ScholarSync@ChangeMe2026';
    }
    $passwordHash = password_hash($passwordIn, PASSWORD_DEFAULT);

    if ($fullName === '') {
        $fullName = $usernameIn;
    }
    $nameParts = preg_split('/\s+/u', $fullName, 2, PREG_SPLIT_NO_EMPTY);
    $firstName = $nameParts[0] ?? '';
    $lastName  = isset($nameParts[1]) ? trim((string) $nameParts[1]) : '';

    $chkUser = $conn->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
    if ($chkUser) {
        $chkUser->bind_param('s', $usernameIn);
        $chkUser->execute();
        $dupUser = $chkUser->get_result()->fetch_assoc();
        $chkUser->close();
        if ($dupUser) {
            $_SESSION['error'] = 'That username is already taken. Choose another or leave blank to auto-generate.';
            header('Location: staff-management.php');
            exit;
        }
    }

    if ($emailIn !== '') {
        $chkMail = $conn->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
        if ($chkMail) {
            $chkMail->bind_param('s', $emailIn);
            $chkMail->execute();
            $dupMail = $chkMail->get_result()->fetch_assoc();
            $chkMail->close();
            if ($dupMail) {
                $_SESSION['error'] = 'That email is already used by another account.';
                header('Location: staff-management.php');
                exit;
            }
        }
    }

    $empTypeDb = $empType !== '' ? $empType : null;

    $stmt = $conn->prepare("
        INSERT INTO admins (
            username, first_name, last_name, full_name, email, phone_number, password_hash, role,
            position, employment_type, employment_start_date, national_id, date_of_birth,
            marital_status, nationality, place_of_birth, address,
            salary_per_minute, monthly_salary, salary_currency, allowed_break_minutes, work_days_per_week,
            sheet_id, sheet_link, office_id
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    if (!$stmt) {
        $_SESSION['error'] = 'Could not prepare insert: ' . $conn->error;
        header('Location: staff-management.php');
        exit;
    }

    $stmt->bind_param(
        'sssssssssssssssssddsiissi',
        $usernameIn,
        $firstName,
        $lastName,
        $fullName,
        $emailIn,
        $phoneIn,
        $passwordHash,
        $role,
        $position,
        $empTypeDb,
        $startDate,
        $nid,
        $dob,
        $marital,
        $nationality,
        $birthPlace,
        $address,
        $salary,
        $monthly,
        $currency,
        $break,
        $days,
        $sheet,
        $link,
        $officeId
    );

    if ($stmt->execute()) {
        $newId = (int) $conn->insert_id;
        $msg = 'Staff member added successfully';
        if (trim((string) ($_POST['username'] ?? '')) === '') {
            $msg .= ' (username: ' . $usernameIn . ')';
        }
        if (trim((string) ($_POST['password'] ?? '')) === '') {
            $msg .= '. Temporary password: ScholarSync@ChangeMe2026 — ask them to change it after login.';
        }
        $_SESSION['success'] = $msg;

        if ($newId > 0 && !empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed, true) && $_FILES['profile_photo']['size'] <= 2 * 1024 * 1024) {
                $photoName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadDir . $photoName)) {
                    $photoUpd = $conn->prepare('UPDATE admins SET profile_photo = ? WHERE id = ?');
                    if ($photoUpd) {
                        $photoUpd->bind_param('si', $photoName, $newId);
                        $photoUpd->execute();
                        $photoUpd->close();
                    }
                }
            }
        }
    } else {
        $_SESSION['error'] = 'Could not add staff: ' . $stmt->error;
    }
    $stmt->close();

    header('Location: staff-management.php');
    exit;
}

/* ============================================================
   FETCH ADMINS + OFFICES
============================================================ */
$admins  = $conn->query("SELECT * FROM admins ORDER BY 
    CASE 
        WHEN role = 'superadmin' THEN 1
        WHEN role = 'admin' THEN 2
        ELSE 3
    END, created_at DESC");
    
$offices = $conn->query("SELECT id, office_name FROM offices ORDER BY office_name ASC");

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
    <title>ScholarSync Global Visa — Staff Management</title>
    
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
            content: '🎓';
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

        /* ===== MAIN CONTAINER - FULL HEIGHT ===== */
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
            flex: 1;
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

        .filter-badge {
            background: var(--light-bg);
            padding: 6px 12px;
            border-radius: 6px;
            color: var(--text-muted);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .filter-badge:hover {
            background: var(--border-light);
        }

        .filter-badge.active {
            background: var(--deep-navy);
            color: var(--white);
        }

        .btn-add-staff {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--deep-navy) 0%, #2f5a26 100%);
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(66, 116, 49, 0.25);
            white-space: nowrap;
        }

        .btn-add-staff:hover {
            color: #fff;
            filter: brightness(1.05);
        }

        #addStaffModal .modal-header {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            color: #fff;
            border-bottom: 3px solid var(--gold);
        }

        #addStaffModal .modal-title {
            font-weight: 700;
        }

        #addStaffModal .form-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        #addStaffModal .optional-hint {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* ===== TABLE CARD - FULL HEIGHT ===== */
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

        /* ===== TABLE RESPONSIVE - FIXED HEADER, SCROLLING BODY ===== */
        .table-responsive {
            flex: 1;
            overflow: auto;
            position: relative;
        }

        /* Improved table headers - LIGHTER BACKGROUND for better visibility */
        th {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f6 100%);
            color: var(--deep-navy);
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 8px;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
            border-bottom: 2px solid var(--gold);
            border-right: 1px solid #dee2e6;
            text-align: center;
            vertical-align: middle;
        }

        th:last-child {
            border-right: none;
        }

        th i {
            margin-left: 4px;
            color: var(--gold);
        }

        /* Table cells - NO WRAP, FULL WIDTH */
        td {
            padding: 10px 8px;
            border-bottom: 1px solid var(--border-light);
            border-right: 1px solid #f1f3f5;
            vertical-align: middle;
            background: var(--white);
            white-space: nowrap;
            min-width: 100px;
        }

        td:last-child {
            border-right: none;
        }

        /* Make all form elements fit in cells */
        .form-control-sm, .form-select-sm {
            padding: 6px 8px;
            font-size: 12px;
            height: 32px;
            width: 100%;
            min-width: 100px;
        }

        /* Profile image - fixed size */
        .profile-img-container {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--gold);
            margin: 0 auto;
        }

        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-placeholder {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-blue) 0%, var(--deep-navy) 100%);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            margin: 0 auto;
        }

        .profile-upload-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            margin: 0 auto;
        }

        .profile-upload-wrap .profile-img-container,
        .profile-upload-wrap .profile-placeholder {
            width: 36px;
            height: 36px;
        }

        .profile-upload-btn {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: rgba(30, 41, 59, 0.55);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.15s ease;
            font-size: 14px;
        }

        .profile-upload-wrap:hover .profile-upload-btn,
        .profile-upload-wrap:focus-within .profile-upload-btn {
            opacity: 1;
        }

        .profile-upload-wrap.is-uploading .profile-upload-btn {
            opacity: 1;
            pointer-events: none;
        }

        .profile-upload-wrap.is-uploading .profile-upload-btn i {
            animation: spin 0.8s linear infinite;
        }

        /* Role badges - compact */
        .role-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
            width: 100%;
            text-align: center;
        }

        .role-superadmin {
            background: linear-gradient(135deg, var(--gold) 0%, #e6953e 100%);
            color: var(--deep-navy);
        }

        .role-admin {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            color: var(--white);
        }

        .role-staff {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: var(--white);
        }

        .role-agent {
            background: linear-gradient(135deg, var(--info) 0%, #026aa7 100%);
            color: var(--white);
        }

        /* Action buttons - compact */
        .action-group {
            display: flex;
            gap: 4px;
            justify-content: center;
            min-width: 90px;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 4px;
            white-space: nowrap;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success) 0%, #1b5e20 100%);
            border: none;
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger) 0%, #8b1e1e 100%);
            border: none;
            color: white;
        }

        /* Table container - full width scroll */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        /* Empty state */
        .empty-state {
            padding: 40px 20px;
            text-align: center;
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

        /* Fix for number inputs */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            opacity: 0.3;
            height: 20px;
        }

        /* Make all cells fill available space */
        td:not(:last-child) {
            width: auto;
        }

        .staff-cell-input {
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 13px;
            width: 100%;
            min-width: 0;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            background: #fff;
        }

        .staff-cell-input:focus {
            outline: none;
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 3px rgba(54, 97, 185, 0.18);
        }

        .staff-cell-input:read-only {
            background: #f1f5f9;
            color: var(--text-muted);
        }

        tbody tr[data-role] {
            transition: background 0.15s ease;
        }

        tbody tr[data-role]:hover {
            background: #f8fafc !important;
        }

        tbody tr[data-role] td {
            background: transparent;
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
            <div class="logo-subtitle">Staff management</div>
        </div>
    </div>
</div>

<div class="main-container">
    
    <!-- Page Header with Stats - Simplified -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="bi bi-people-fill"></i>
            Staff Management
        </h1>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-people"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $admins->num_rows ?></h3>
                    <p>Total</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-star-fill"></i>
                </div>
                <div class="stat-info">
                    <h3>
                        <?php 
                        $admins->data_seek(0);
                        $superadminCount = 0;
                        while($row = $admins->fetch_assoc()) {
                            if($row['role'] === 'superadmin') $superadminCount++;
                        }
                        $admins->data_seek(0);
                        echo $superadminCount;
                        ?>
                    </h3>
                    <p>Super</p>
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
    
    <!-- Search and Filters - Compact -->
    <div class="filters-section">
        <div class="search-wrapper">
            <i class="bi bi-search"></i>
            <input type="text" id="searchBox" class="form-control" placeholder="Search...">
        </div>
        
        <div class="filter-badge active" onclick="filterByRole('all', this)">
            <i class="bi bi-people"></i> All
        </div>
        <div class="filter-badge" onclick="filterByRole('superadmin', this)">
            <i class="bi bi-star-fill"></i> Super
        </div>
        <div class="filter-badge" onclick="filterByRole('admin', this)">
            <i class="bi bi-shield"></i> Admin
        </div>
        <div class="filter-badge" onclick="filterByRole('staff', this)">
            <i class="bi bi-person-badge"></i> Staff
        </div>
        <div class="filter-badge" onclick="filterByRole('agent', this)">
            <i class="bi bi-person-workspace"></i> Agent
        </div>

        <?php if ($isSuperAdmin): ?>
        <button type="button" class="btn-add-staff" data-bs-toggle="modal" data-bs-target="#addStaffModal">
            <i class="bi bi-person-plus-fill"></i> Add Staff
        </button>
        <?php endif; ?>
    </div>
    
    <!-- Staff Table Card - Full Width -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table" id="staffTable">
                <thead>
                    <tr>
                        <th style="min-width: 40px;">#</th>
                        <th style="min-width: 60px;">Profile</th>
                        <th onclick="sortTable(2)" style="cursor: pointer; min-width: 150px;">
                            Name <i class="bi bi-arrow-down-up"></i>
                        </th>
                        <th style="min-width: 180px;">Email</th>
                        <th style="min-width: 120px;">Phone</th>
                        <th style="min-width: 120px;">Username</th>
                        <th style="min-width: 100px;">Role</th>
                        <th style="min-width: 120px;">Position</th>
                        <th style="min-width: 100px;">Emp Type</th>
                        <th style="min-width: 100px;">Start Date</th>
                        <th style="min-width: 100px;">DOB</th>
                        <th style="min-width: 80px;">Marital</th>
                        <th style="min-width: 120px;">Nationality</th>
                        <th style="min-width: 120px;">Birth Place</th>
                        <th style="min-width: 150px;">National ID</th>
                        <th style="min-width: 150px;">Address</th>
                        <th style="min-width: 120px;">Office</th>
                        <th style="min-width: 90px;">Salary/Min</th>
                        <th style="min-width: 100px;">Monthly</th>
                        <th style="min-width: 60px;">Curr</th>
                        <th style="min-width: 70px;">Break</th>
                        <th style="min-width: 70px;">Days</th>
                        <th style="min-width: 120px;">Sheet ID</th>
                        <th style="min-width: 150px;">Sheet Link</th>
                        <th style="min-width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    $admins->data_seek(0);
                    while($row = $admins->fetch_assoc()): 
                        $isOwnProfile = ($row['id'] == $currentUserId);
                        $displayName = trim((string) ($row['full_name'] ?? ''));
                        if ($displayName === '') {
                            $displayName = trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
                        }
                        if ($displayName === '') {
                            $displayName = (string) ($row['username'] ?? '');
                        }
                        $searchBlob = implode(' ', array_filter([
                            $displayName,
                            $row['first_name'] ?? '',
                            $row['last_name'] ?? '',
                            $row['email'] ?? '',
                            $row['phone_number'] ?? '',
                            $row['username'] ?? '',
                            $row['role'] ?? '',
                            $row['position'] ?? '',
                            $row['employment_type'] ?? '',
                            $row['nationality'] ?? '',
                            $row['place_of_birth'] ?? '',
                            $row['national_id'] ?? '',
                            $row['address'] ?? '',
                        ], static fn($v) => trim((string) $v) !== ''));
                    ?>
                    <tr class="staff-data-row" data-role="<?= htmlspecialchars($row['role'] ?? 'staff', ENT_QUOTES, 'UTF-8') ?>"
                        data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
                        <form method="post" enctype="multipart/form-data" class="staff-form" data-id="<?= $row['id'] ?>">
                            <td class="text-center"><?= $counter++ ?></td>
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            
                            <td class="text-center">
                                <div class="profile-upload-wrap" data-staff-id="<?= (int) $row['id'] ?>">
                                    <?php if (!empty($row['profile_photo'])): ?>
                                    <div class="profile-img-container">
                                        <img src="<?= htmlspecialchars(pcvc_profile_photo_url($row['profile_photo'] ?? '')) ?>"
                                             alt="Profile" class="profile-img profile-preview">
                                    </div>
                                    <?php else: ?>
                                    <div class="profile-placeholder profile-preview">
                                        <?= strtoupper(substr($row['first_name'] ?? $row['username'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($isSuperAdmin): ?>
                                    <label class="profile-upload-btn" title="Upload or change photo">
                                        <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/gif,image/webp"
                                               class="profile-photo-input" data-staff-id="<?= (int) $row['id'] ?>" hidden>
                                        <i class="bi bi-camera-fill"></i>
                                    </label>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td>
                                <?php if ($isSuperAdmin): ?>
                                <input type="text" name="full_name" class="staff-cell-input" required
                                       value="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="Full name" autocomplete="name">
                                <?php else: ?>
                                <?= htmlspecialchars($displayName !== '' ? $displayName : 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($isSuperAdmin): ?>
                                <input type="email" name="email" class="staff-cell-input" required
                                       value="<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="email@…" autocomplete="email">
                                <?php else: ?>
                                <?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isSuperAdmin): ?>
                                <input type="text" name="phone_number" class="staff-cell-input"
                                       value="<?= htmlspecialchars($row['phone_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="Phone / MoMo" inputmode="tel" autocomplete="tel">
                                <?php else: ?>
                                <?= htmlspecialchars($row['phone_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isSuperAdmin): ?>
                                <input type="text" name="username" class="staff-cell-input" required
                                       value="<?= htmlspecialchars($row['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="Username" autocomplete="username">
                                <?php else: ?>
                                <?= htmlspecialchars($row['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Role Selector -->
                            <td>
                                <?php if ($isSuperAdmin): ?>
                                <select name="role" class="form-select form-select-sm">
                                    <option value="superadmin" <?= ($row['role'] ?? '') == 'superadmin' ? 'selected' : '' ?> 
                                            <?= ($row['role'] ?? '') == 'superadmin' ? 'disabled' : '' ?>>Super</option>
                                    <option value="admin" <?= ($row['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="staff" <?= ($row['role'] ?? '') == 'staff' ? 'selected' : '' ?>>Staff</option>
                                    <option value="agent" <?= ($row['role'] ?? '') == 'agent' ? 'selected' : '' ?>>Agent</option>
                                </select>
                                <?php else: ?>
                                <span class="role-badge role-<?= $row['role'] ?? 'staff' ?>">
                                    <?= ucfirst($row['role'] ?? 'Staff') ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            
                            <td><input name="position" value="<?= htmlspecialchars($row['position'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       class="form-control form-control-sm staff-cell-input" <?= !$isSuperAdmin ? 'readonly' : '' ?>></td>
                            
                            <td>
                                <select name="employment_type" class="form-select form-select-sm" <?= !$isSuperAdmin ? 'disabled' : '' ?>>
                                    <option value="">--</option>
                                    <option <?= ($row['employment_type'] ?? '') == 'Full-time' ? 'selected' : '' ?>>Full-time</option>
                                    <option <?= ($row['employment_type'] ?? '') == 'Part-time' ? 'selected' : '' ?>>Part-time</option>
                                    <option <?= ($row['employment_type'] ?? '') == 'Contract' ? 'selected' : '' ?>>Contract</option>
                                </select>
                            </td>
                            
                            <td><input type="date" name="employment_start_date" 
                                       value="<?= htmlspecialchars($row['employment_start_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       class="form-control form-control-sm" <?= !$isSuperAdmin ? 'readonly' : '' ?>></td>
                            
                            <td><input type="date" name="date_of_birth" 
                                       value="<?= htmlspecialchars($row['date_of_birth'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       class="form-control form-control-sm" <?= !$isSuperAdmin ? 'readonly' : '' ?>></td>
                            
                            <td>
                                <select name="marital_status" class="form-select form-select-sm" <?= !$isSuperAdmin ? 'disabled' : '' ?>>
                                    <option value="">--</option>
                                    <?php foreach(['Single','Married','Divorced','Widowed'] as $m): ?>
                                    <option <?= ($row['marital_status'] ?? '') == $m ? 'selected' : '' ?>><?= $m ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            
                            <td><input name="nationality" value="<?= htmlspecialchars($row['nationality'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       class="form-control form-control-sm" <?= !$isSuperAdmin ? 'readonly' : '' ?>></td>
                            
                            <td><input name="place_of_birth" value="<?= htmlspecialchars($row['place_of_birth'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       class="form-control form-control-sm" <?= !$isSuperAdmin ? 'readonly' : '' ?>></td>
                            
                            <td><input name="national_id" value="<?= htmlspecialchars($row['national_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       class="form-control form-control-sm" <?= !$isSuperAdmin ? 'readonly' : '' ?>></td>
                            
                            <td><input name="address" value="<?= htmlspecialchars($row['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       class="form-control form-control-sm" <?= !$isSuperAdmin ? 'readonly' : '' ?>></td>
                            
                            <td>
                                <select name="office_id" class="form-select form-select-sm" <?= !$isSuperAdmin ? 'disabled' : '' ?>>
                                    <option value="">--</option>
                                    <?php 
                                    $offices->data_seek(0); 
                                    while($o = $offices->fetch_assoc()): 
                                    ?>
                                    <option value="<?= $o['id'] ?>" <?= ($row['office_id'] ?? '') == $o['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($o['office_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </td>
                            
                            <td><input type="number" step="0.01" name="salary_per_minute" 
                                       value="<?= htmlspecialchars($row['salary_per_minute'] ?? 8.33) ?>" 
                                       class="form-control form-control-sm" <?= !$isSuperAdmin ? 'readonly' : '' ?>></td>
                            
                            <td><input type="number" step="0.01" name="monthly_salary" 
                                       value="<?= htmlspecialchars($row['monthly_salary'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       class="form-control form-control-sm" <?= !$isSuperAdmin ? 'readonly' : '' ?>></td>
                            
                            <td>
                                <select name="salary_currency" class="form-select form-select-sm" <?= !$isSuperAdmin ? 'disabled' : '' ?>>
                                    <option <?= ($row['salary_currency'] ?? 'USD') == 'KES' ? 'selected' : '' ?>>KES</option>
                                    <option <?= ($row['salary_currency'] ?? 'USD') == 'RWF' ? 'selected' : '' ?>>RWF</option>
                                    <option <?= ($row['salary_currency'] ?? 'USD') == 'USD' ? 'selected' : '' ?>>USD</option>
                                </select>
                            </td>
                            
                            <td><input type="number" name="allowed_break_minutes" 
                                       value="<?= htmlspecialchars($row['allowed_break_minutes'] ?? 60) ?>" 
                                       class="form-control form-control-sm" <?= !$isSuperAdmin ? 'readonly' : '' ?>></td>
                            
                            <td><input type="number" name="work_days_per_week" 
                                       value="<?= htmlspecialchars($row['work_days_per_week'] ?? 6) ?>" 
                                       class="form-control form-control-sm" <?= !$isSuperAdmin ? 'readonly' : '' ?>></td>
                            
                            <td><input name="sheet_id" value="<?= htmlspecialchars($row['sheet_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       class="form-control form-control-sm" <?= !$isSuperAdmin ? 'readonly' : '' ?>></td>
                            
                            <td><input name="sheet_link" value="<?= htmlspecialchars($row['sheet_link'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       class="form-control form-control-sm" <?= !$isSuperAdmin ? 'readonly' : '' ?>></td>
                            
                            <td>
                                <div class="action-group">
                                    <?php if ($isSuperAdmin): ?>
                                    <button type="submit" name="update_staff" class="btn btn-success btn-sm" 
                                            onclick="return confirmUpdate()">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <?php if (!$isOwnProfile): ?>
                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm" 
                                       onclick="return confirmDelete()">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted" data-tooltip="View only">👁️</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </form>
                    </tr>
                    <?php endwhile; ?>
                    
                    <?php if ($admins->num_rows == 0): ?>
                    <tr>
                        <td colspan="26" class="text-center py-5">
                            <div class="empty-state">
                                <div class="empty-icon">👥</div>
                                <div class="empty-title">No Staff Members Found</div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($isSuperAdmin): ?>
<!-- Add Staff Modal — all fields optional -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data" id="addStaffForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStaffModalLabel">
                        <i class="bi bi-person-plus-fill me-2"></i>Add Staff Member
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="optional-hint mb-3">
                        All fields are optional. If username or password is left blank, a username and temporary password
                        (<code>ScholarSync@ChangeMe2026</code> will be created automatically.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Full name</label>
                            <input type="text" name="full_name" class="form-control" placeholder="Full name" autocomplete="name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="email@…" autocomplete="email">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone_number" class="form-control" placeholder="Phone / MoMo" inputmode="tel" autocomplete="tel">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="Auto if blank" autocomplete="username">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Password</label>
                            <input type="text" name="password" class="form-control" placeholder="Temp password if blank" autocomplete="new-password">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="staff" selected>Staff</option>
                                <option value="admin">Admin</option>
                                <option value="agent">Agent</option>
                                <option value="superadmin">Super</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Profile photo</label>
                            <input type="file" name="profile_photo" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Position</label>
                            <input type="text" name="position" class="form-control" placeholder="Position / title">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Employment type</label>
                            <select name="employment_type" class="form-select">
                                <option value="">--</option>
                                <option>Full-time</option>
                                <option>Part-time</option>
                                <option>Contract</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start date</label>
                            <input type="date" name="employment_start_date" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Date of birth</label>
                            <input type="date" name="date_of_birth" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Marital status</label>
                            <select name="marital_status" class="form-select">
                                <option value="">--</option>
                                <option>Single</option>
                                <option>Married</option>
                                <option>Divorced</option>
                                <option>Widowed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nationality</label>
                            <input type="text" name="nationality" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Place of birth</label>
                            <input type="text" name="place_of_birth" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">National ID</label>
                            <input type="text" name="national_id" class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Office</label>
                            <select name="office_id" class="form-select">
                                <option value="">--</option>
                                <?php
                                if ($offices) {
                                    $offices->data_seek(0);
                                    while ($o = $offices->fetch_assoc()):
                                ?>
                                <option value="<?= (int) $o['id'] ?>"><?= htmlspecialchars($o['office_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
                                <?php
                                    endwhile;
                                    $offices->data_seek(0);
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Salary / min</label>
                            <input type="number" step="0.01" name="salary_per_minute" class="form-control" placeholder="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Monthly</label>
                            <input type="number" step="0.01" name="monthly_salary" class="form-control" placeholder="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Currency</label>
                            <select name="salary_currency" class="form-select">
                                <option value="USD" selected>USD</option>
                                <option value="KES">KES</option>
                                <option value="RWF">RWF</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Break</label>
                            <input type="number" name="allowed_break_minutes" class="form-control" placeholder="60">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Days</label>
                            <input type="number" name="work_days_per_week" class="form-control" placeholder="5">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Sheet ID</label>
                            <input type="text" name="sheet_id" class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Sheet link</label>
                            <input type="text" name="sheet_link" class="form-control" placeholder="https://…">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_staff" value="1" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i> Save staff
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(function() {
    // Auto-hide alerts after 3 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 3000);
    
    // Show loading on form submit
    $('.staff-form').on('submit', function() {
        $('#loadingOverlay').fadeIn();
        return true;
    });

    // Instant profile photo upload (superadmin)
    $(document).on('change', '.profile-photo-input', function() {
        const input = this;
        const file = input.files && input.files[0];
        if (!file) return;

        const staffId = input.dataset.staffId;
        const wrap = input.closest('.profile-upload-wrap');
        const preview = wrap ? wrap.querySelector('.profile-preview') : null;

        const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (allowed.indexOf(file.type) === -1) {
            alert('Please choose a JPG, PNG, GIF, or WebP image.');
            input.value = '';
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            alert('Image must be 2MB or smaller.');
            input.value = '';
            return;
        }

        const fd = new FormData();
        fd.append('staff_id', staffId);
        fd.append('profile_photo', file);

        if (wrap) wrap.classList.add('is-uploading');

        fetch('api/staff-profile-photo.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                alert(data.error || 'Upload failed.');
                return;
            }
            const photoSrc = data.photo_url + (data.photo_url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
            if (preview && preview.tagName === 'IMG') {
                preview.src = photoSrc;
            } else if (preview && wrap) {
                const img = document.createElement('img');
                img.src = photoSrc;
                img.alt = 'Profile';
                img.className = 'profile-img profile-preview';
                preview.replaceWith(img);
            }
        })
        .catch(function() {
            alert('Upload failed. Please try again.');
        })
        .finally(function() {
            if (wrap) wrap.classList.remove('is-uploading');
            input.value = '';
        });
    });
});

// Smart live search + role filter (combined)
let currentRoleFilter = 'all';
let smartSearchTimer = null;

function pcvcNormalizeSearchStr(str) {
    if (!str) return '';
    try {
        return str
            .toLowerCase()
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '');
    } catch (e) {
        return str.toLowerCase();
    }
}

function applyStaffFilters() {
    const query = (document.getElementById('searchBox')?.value || '').trim();
    const norm = pcvcNormalizeSearchStr(query);
    const tokens = norm.split(/[^a-z0-9]+/i).filter(function(t) { return t.length > 0; });
    const rows = document.querySelectorAll('#staffTable tbody tr.staff-data-row');

    rows.forEach(function(row) {
        const rowRole = row.getAttribute('data-role') || '';
        const roleOk = currentRoleFilter === 'all' || rowRole === currentRoleFilter;

        let searchOk = true;
        if (tokens.length > 0) {
            let hay = row.getAttribute('data-search') || '';
            if (!hay) {
                hay = pcvcNormalizeSearchStr(row.textContent || '');
            } else {
                hay = pcvcNormalizeSearchStr(hay);
            }
            searchOk = tokens.every(function(t) { return hay.indexOf(t) !== -1; });
        }

        row.style.display = (roleOk && searchOk) ? '' : 'none';
    });
}

const searchBox = document.getElementById('searchBox');
if (searchBox) {
    searchBox.addEventListener('keyup', function() {
        clearTimeout(smartSearchTimer);
        smartSearchTimer = setTimeout(applyStaffFilters, 120);
    });
    searchBox.addEventListener('input', function() {
        clearTimeout(smartSearchTimer);
        smartSearchTimer = setTimeout(applyStaffFilters, 120);
    });
}

// Filter by role
function filterByRole(role, el) {
    currentRoleFilter = role;
    document.querySelectorAll('.filter-badge').forEach(function(badge) {
        badge.classList.remove('active');
    });
    if (el) {
        el.classList.add('active');
    }
    applyStaffFilters();
}

applyStaffFilters();

// Sort table by column
function sortTable(columnIndex) {
    let table = document.getElementById('staffTable');
    let tbody = table.querySelector('tbody');
    let rows = Array.from(tbody.querySelectorAll('tr.staff-data-row'));
    
    // Toggle sort order
    if (!table.sortDirection) {
        table.sortDirection = {};
    }
    table.sortDirection[columnIndex] = !table.sortDirection[columnIndex];
    
    rows.sort((a, b) => {
        let aText = a.cells[columnIndex].textContent.trim().toLowerCase();
        let bText = b.cells[columnIndex].textContent.trim().toLowerCase();
        
        if (table.sortDirection[columnIndex]) {
            return aText.localeCompare(bText);
        } else {
            return bText.localeCompare(aText);
        }
    });
    
    // Reorder rows
    rows.forEach(row => tbody.appendChild(row));
    applyStaffFilters();
}

// Confirm delete
function confirmDelete() {
    return confirm('⚠️ Delete this staff member?');
}

// Confirm update
function confirmUpdate() {
    return confirm('Save changes?');
}

// Keyboard shortcuts
$(document).on('keydown', function(e) {
    // Ctrl+F - Focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('searchBox').focus();
    }
});
</script>

</body>
</html>