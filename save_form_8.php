<?php
session_start();
require_once __DIR__ . '/db.php';
// Always output JSON
header('Content-Type: application/json');

// --- Get user_id and step ---
$userId = $_SESSION['user_id'] ?? null;
$step = $_POST['step'] ?? null;

if (!$userId || !$step) {
    echo json_encode(['status' => 'error', 'message' => 'Missing user ID or step.']);
    exit;
}

$fields = [];
$fileFields = [];

// --- Determine fields for each step ---
switch ($step) {
    case 'step1':
        $fields = [
            'first_name', 'middle_name', 'last_name',
            'birth_month', 'birth_day', 'birth_year',
            'gender', 'address1', 'address2', 'city',
            'state', 'postal_code', 'student_email',
            'mobile_number', 'phone_number', 'work_number',
            'form_url'
        ];
        $_POST['form_url'] = 'form-8.php'; // Save form URL
        break;

    case 'step2':
        $fields = [
            'university', 'program_admitted', 'university_email',
            'university_password', 'has_scholarship',
            'additional_comments'
        ];
        $fileFields = [
            'acceptance_letter', 'loan_approval_letter',
            'mpower_loan_decision', 'loan_contract',
            'bank_statement', 'payment_proof'
        ];

        // --- Handle file uploads ---
        foreach ($fileFields as $fileField) {
            if (isset($_FILES[$fileField]) && $_FILES[$fileField]['size'] > 0) {
                $filePath = 'uploads/' . basename($_FILES[$fileField]['name']);
                move_uploaded_file($_FILES[$fileField]['tmp_name'], $filePath);
                $_POST[$fileField] = $filePath;
            }
        }

        // Merge file fields into $fields
        $fields = array_merge($fields, $fileFields);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Unknown step.']);
        exit;
}

// --- Prepare insert & update ---
$placeholders = rtrim(str_repeat('?,', count($fields)), ',');
$updates = implode('=?, ', $fields) . '=?';

// --- Prepare parameters and types ---
$params = [];
$types = "";

foreach ($fields as $field) {
    $params[] = $_POST[$field] ?? null;
    $types .= "s";
}

$paramsForInsert = array_merge([$userId], $params);
$typesForInsert = "s" . $types;

$paramsForUpdate = array_merge($params, [$userId]);
$typesForUpdate = $types . "s";

// --- Insert or Update ---
$sql = "INSERT INTO form_8_applications (user_id, " . implode(",", $fields) . ")
        VALUES (?, $placeholders)
        ON DUPLICATE KEY UPDATE $updates";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param($typesForInsert . $types, ...$paramsForInsert, ...$params);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'user_id' => $userId]);
} else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
