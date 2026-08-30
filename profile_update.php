<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/profile_photo_upload.php';
session_start();

/* ============================
   AUTH CHECK
============================ */
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

$user_id = (int) $_SESSION['admin_id'];

/* ============================
   COLLECT & SANITIZE INPUT
============================ */
$first_name  = trim($_POST['first_name'] ?? '');
$last_name   = trim($_POST['last_name'] ?? '');
$phone       = trim($_POST['phone_number'] ?? '');
$email       = trim($_POST['email'] ?? '');

$national_id            = trim($_POST['national_id'] ?? null);
$date_of_birth          = $_POST['date_of_birth'] ?? null;
$marital_status          = $_POST['marital_status'] ?? null;
$nationality             = trim($_POST['nationality'] ?? null);
$place_of_birth          = trim($_POST['place_of_birth'] ?? null);
$address                 = trim($_POST['address'] ?? null);
$position                = trim($_POST['position'] ?? null);
$employment_type          = $_POST['employment_type'] ?? 'Full-time';
$employment_start_date   = $_POST['employment_start_date'] ?? null;

/* ============================
   BASIC VALIDATION
============================ */
if ($first_name === '' || $last_name === '' || $phone === '' || $email === '') {
    die("❌ Required fields missing.");
}

$full_name = trim($first_name . ' ' . $last_name);

/* ============================
   PROFILE PHOTO UPLOAD
============================ */
$photo_sql = "";
$photo_param = null;

if (!empty($_FILES['profile_photo']['name'])) {
    $stored = pcvc_profile_photo_store($_FILES['profile_photo'], __DIR__ . '/uploads/');
    if (!$stored['ok']) {
        die('❌ ' . ($stored['error'] ?? 'Failed to upload image.'));
    }
    $photo_sql = ", profile_photo = ?";
    $photo_param = $stored['filename'];
}

/* ============================
   UPDATE QUERY (NO SALARY)
============================ */
$sql = "
UPDATE admins SET
    first_name = ?,
    last_name = ?,
    full_name = ?,
    phone_number = ?,
    email = ?,
    national_id = ?,
    date_of_birth = ?,
    marital_status = ?,
    nationality = ?,
    place_of_birth = ?,
    address = ?,
    position = ?,
    employment_type = ?,
    employment_start_date = ?
    $photo_sql
WHERE id = ?
";

$stmt = $conn->prepare($sql);

/* ============================
   BIND PARAMETERS
============================ */
if ($photo_param) {
    $stmt->bind_param(
        "sssssssssssssssi",
        $first_name,
        $last_name,
        $full_name,
        $phone,
        $email,
        $national_id,
        $date_of_birth,
        $marital_status,
        $nationality,
        $place_of_birth,
        $address,
        $position,
        $employment_type,
        $employment_start_date,
        $photo_param,
        $user_id
    );
} else {
    $stmt->bind_param(
        "ssssssssssssssi",
        $first_name,
        $last_name,
        $full_name,
        $phone,
        $email,
        $national_id,
        $date_of_birth,
        $marital_status,
        $nationality,
        $place_of_birth,
        $address,
        $position,
        $employment_type,
        $employment_start_date,
        $user_id
    );
}

/* ============================
   EXECUTE
============================ */
if (!$stmt->execute()) {
    die("❌ Profile update failed: " . $stmt->error);
}

$stmt->close();
$conn->close();

/* ============================
   REDIRECT BACK
============================ */
header("Location: admin-dashboard.php?success=profile_updated");
exit;
