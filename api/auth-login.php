<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

// ---------------------------------------------------
// 1. SANITIZE INPUTS
// ---------------------------------------------------
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Username and password required"
    ]);
    exit;
}

// ---------------------------------------------------
// 2. FETCH USER
// ---------------------------------------------------
$stmt = $conn->prepare("
    SELECT id, username, full_name, role, password_hash, office_id,
           email, phone_number, salary_per_minute, allowed_break_minutes,
           work_days_per_week, profile_photo
    FROM admins
    WHERE username = ?
    LIMIT 1
");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid username or password"
    ]);
    exit;
}

$stmt->bind_result(
    $id,
    $uname,
    $full_name,
    $role,
    $hash,
    $office_id,
    $email,
    $phone,
    $salary_per_minute,
    $break_minutes,
    $work_days,
    $profile_photo
);
$stmt->fetch();
$stmt->close();

// ---------------------------------------------------
// 3. VERIFY PASSWORD
// ---------------------------------------------------
if (!password_verify($password, $hash)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid username or password"
    ]);
    exit;
}

// ---------------------------------------------------
// 4. START SESSION
// ---------------------------------------------------
$_SESSION['id'] = $id;

// ---------------------------------------------------
// 5. FETCH OFFICE DATA
// ---------------------------------------------------
$office_obj = null;

if ($office_id > 0) {
    $o = $conn->prepare("
        SELECT id, office_name, country, latitude, longitude, radius_meters
        FROM offices
        WHERE id = ?
        LIMIT 1
    ");
    $o->bind_param("i", $office_id);
    $o->execute();
    $office_obj = $o->get_result()->fetch_assoc();
    $o->close();
}

// ---------------------------------------------------
// 6. SUCCESS RESPONSE (ANDROID FRIENDLY FORMAT)
// ---------------------------------------------------
echo json_encode([
    "status" => "success",
    "user" => [
        "id" => (int)$id,
        "username" => $uname,
        "full_name" => $full_name,
        "role" => $role,
        "email" => $email,
        "phone_number" => $phone,
        "profile_photo" => $profile_photo,
        "salary_per_minute" => (float)$salary_per_minute,
        "allowed_break_minutes" => (int)$break_minutes,
        "work_days_per_week" => (int)$work_days,
        "office_id" => (int)$office_id,

        // ⭐ Android expects office inside user object:
        "office" => $office_obj
    ]
]);
?>
