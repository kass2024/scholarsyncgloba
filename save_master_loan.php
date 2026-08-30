<?php
ob_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
require_once __DIR__ . '/db.php';
$userId = $_POST['user_id'] ?? null;
$step   = $_POST['step'] ?? null;

if (!$userId || !$step) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Missing user ID or step.']);
    exit;
}

$fields = [];
$fileFields = [];
$allowedFileFields = [
    'acceptance_letter', 'bachelor_degree', 'bachelor_transcript',
    'cv', 'id_document', 'valid_passport', 'english_certificate',
    'admission_fees', 'scholarship_letter', 'bank_statement'
];

// STEP-SPECIFIC FIELD LOGIC
switch ($step) {
    case 'step1':
        $fields = ['first_name', 'last_name', 'gender', 'dob', 'phone_number', 'email', 'address1', 'address2', 'city', 'state', 'postal_code', 'form_url'];
        $_POST['form_url'] = 'master-loan.php';

        // Check for duplicate by email + provider
        $email = $_POST['email'] ?? '';
        $loan_provider_id = $_POST['loan_provider_id'] ?? '';
        if (!empty($email) && !empty($loan_provider_id)) {
            $check = $conn->prepare("SELECT COUNT(*) FROM master_loan_applications WHERE email = ? AND loan_provider_id = ? AND user_id != ?");
            $check->bind_param("sss", $email, $loan_provider_id, $userId);
            $check->execute();
            $check->bind_result($count);
            $check->fetch();
            $check->close();

            if ($count > 0) {
                ob_end_clean();
                echo json_encode(['status' => 'error', 'message' => 'You have already submitted an application for this loan provider.']);
                exit;
            }
        }
        break;

    case 'step2':
        $fields = ['loan_reason', 'masters_program_name', 'school_name', 'degree_type', 'application_type', 'intake'];
        foreach ($fields as $field) {
            $_POST[$field] = json_encode((array)($_POST[$field] ?? []));
        }
        break;

    case 'step3':
        $fields = ['citizenship_country', 'has_visa', 'has_ssn', 'ref_first_name', 'ref_last_name', 'ref_email', 'ref_phone', 'ref_relationship'];
        break;

    case 'step4':
        $fields = [];
        foreach ($allowedFileFields as $fileField) {
            if (!empty($_FILES[$fileField]['tmp_name']) && $_FILES[$fileField]['size'] > 0) {
                $filePath = 'uploads/' . uniqid() . '_' . basename($_FILES[$fileField]['name']);
                move_uploaded_file($_FILES[$fileField]['tmp_name'], $filePath);
                $_POST[$fileField] = $filePath;
                $fields[] = $fileField;
            }
        }
        foreach (['applicant_first_name', 'applicant_last_name', 'date_signed'] as $cf) {
            if (array_key_exists($cf, $_POST) && trim((string)($_POST[$cf] ?? '')) !== '') {
                $fields[] = $cf;
            }
        }
        break;

    default:
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Unknown step.']);
        exit;
}

if ($step === 'step4' && empty($fields)) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'No documents or certification fields to save for this step.']);
    exit;
}

// Add loan provider ID if it exists
if (isset($_POST['loan_provider_id']) && trim((string)$_POST['loan_provider_id']) !== '') {
    $fields[] = 'loan_provider_id';
}

// Prepare SQL
$placeholders = rtrim(str_repeat('?,', count($fields)), ',');
$updates = implode(' = ?, ', $fields) . ' = ?';

$params = [];
$types = '';

foreach ($fields as $field) {
    $params[] = $_POST[$field] ?? null;
    $types .= 's';
}

$paramsForInsert = array_merge([$userId], $params);
$typesForInsert = 's' . $types;

$sql = "INSERT INTO master_loan_applications (user_id, " . implode(',', $fields) . ")
        VALUES (?, $placeholders)
        ON DUPLICATE KEY UPDATE $updates";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param($typesForInsert . $types, ...$paramsForInsert, ...$params);

if ($stmt->execute()) {
    ob_end_clean();
    echo json_encode([
        'status' => 'success',
        'user_id' => $userId
    ]);
} else {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
exit;
?>
