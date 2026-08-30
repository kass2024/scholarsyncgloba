<?php
require_once __DIR__ . '/db.php';
// Set response header
header('Content-Type: application/json');

// Handle file uploads safely
function uploadFile($field, $targetDir = 'uploads/') {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $filename = basename($_FILES[$field]["name"]);
        $targetPath = $targetDir . uniqid() . "_" . $filename;
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        if (move_uploaded_file($_FILES[$field]["tmp_name"], $targetPath)) {
            return $targetPath;
        }
    }
    return null;
}

$fields = [
    'transfer_student', 'have_tc', 'blue_card', 'first_name', 'last_name',
    'passport_no', 'issue_date', 'expiry_date', 'gender', 'dob', 'nationality',
    'residence_country', 'student_id', 'email', 'area_code', 'mobile',
    'address', 'city', 'province', 'postal_code', 'country', 'father_name',
    'father_mobile', 'father_occupation', 'mother_name', 'agent_first_name',
    'agent_last_name', 'agent_email'
];

$data = [];
foreach ($fields as $field) {
    $data[$field] = mysqli_real_escape_string($conn, $_POST[$field] ?? '');
}

// Handle uploads
$photo = uploadFile('photo');
$degree = uploadFile('degree');
$transcript = uploadFile('transcript');
$cv = uploadFile('cv');
$passport = uploadFile('valid_passport');
// Check if the email already exists
$email = mysqli_real_escape_string($conn, $_POST['email']);
$checkQuery = "SELECT id FROM turkey_applications WHERE email = '$email' LIMIT 1";
$checkResult = mysqli_query($conn, $checkQuery);

if (mysqli_num_rows($checkResult) > 0) {
    echo json_encode(['success' => false, 'message' => 'This email has already been used to submit an application.']);
    exit;
}

$sql = "INSERT INTO turkey_applications (
    transfer_student, have_tc, blue_card, first_name, last_name, passport_no,
    issue_date, expiry_date, gender, dob, nationality, residence_country, student_id,
    email, area_code, mobile, address, city, province, postal_code, country,
    father_name, father_mobile, father_occupation, mother_name,
    agent_first_name, agent_last_name, agent_email,
    photo, degree, transcript, cv, valid_passport, is_read
) VALUES (
    '{$data['transfer_student']}', '{$data['have_tc']}', '{$data['blue_card']}',
    '{$data['first_name']}', '{$data['last_name']}', '{$data['passport_no']}',
    '{$data['issue_date']}', '{$data['expiry_date']}', '{$data['gender']}', '{$data['dob']}',
    '{$data['nationality']}', '{$data['residence_country']}', '{$data['student_id']}',
    '{$data['email']}', '{$data['area_code']}', '{$data['mobile']}',
    '{$data['address']}', '{$data['city']}', '{$data['province']}', '{$data['postal_code']}',
    '{$data['country']}', '{$data['father_name']}', '{$data['father_mobile']}',
    '{$data['father_occupation']}', '{$data['mother_name']}', '{$data['agent_first_name']}',
    '{$data['agent_last_name']}', '{$data['agent_email']}',
    '$photo', '$degree', '$transcript', '$cv', '$passport', 0
)";

if (mysqli_query($conn, $sql)) {
    echo json_encode(['success' => true, 'message' => 'Application submitted successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
}
?>
