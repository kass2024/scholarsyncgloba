<?php
declare(strict_types=1);

/* ===============================
   ABSOLUTE SAFETY NET
=============================== */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
session_start();

function respond(array $data): void {
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/* Convert PHP warnings/notices to exceptions */
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/helpers/phone_whatsapp_normalize.php';
    require_once __DIR__ . '/helpers/form17_application_status.php';
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }

    xander_ensure_form17_process_status_column($conn);
    xander_ensure_form17_submitted_at_column($conn);

    /* ===============================
       INPUT VALIDATION
    =============================== */
    $step   = $_POST['step'] ?? 'step1';
    $action = $_POST['action'] ?? 'save'; // 'save' or 'upload_file'
    $userId = $_POST['user_id'] ?? '';

    if ($userId === '' || !str_starts_with($userId, 'user-')) {
        $userId = 'user-' . time() . '-' . random_int(1000, 9999);
    }

    /* ===============================
       QUICK FILE UPLOAD (Background uploads)
    =============================== */
    if ($action === 'upload_file') {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded');
        }

        $field = $_POST['field'] ?? '';
        $allowedFields = [
            'passport_copy', 
            'academic_documents', 
            'old_visa_copy', 
            'passport_photo', 
            'cv', 
            'signature'
        ];

        if (!in_array($field, $allowedFields)) {
            throw new Exception('Invalid file field');
        }

        // File validation
        $file = $_FILES['file'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            throw new Exception('File too large (max 10MB)');
        }

        // Allowed extensions
        $allowed = [
            'passport_copy' => ['pdf', 'jpg', 'jpeg', 'png'],
            'academic_documents' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
            'old_visa_copy' => ['pdf', 'jpg', 'jpeg', 'png'],
            'passport_photo' => ['jpg', 'jpeg', 'png'],
            'cv' => ['pdf', 'doc', 'docx'],
            'signature' => ['jpg', 'jpeg', 'png', 'pdf']
        ];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed[$field])) {
            throw new Exception('Invalid file type for ' . $field);
        }

        // Upload directory
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $filename = $userId . '_' . $field . '_' . time() . '.' . $ext;
        $filepath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to save file');
        }

        // Store in database immediately
        $stmt = $conn->prepare("
            INSERT INTO form_17_applications (user_id, `$field`)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE `$field` = ?
        ");
        $relativePath = 'uploads/' . $filename;
        $stmt->bind_param("sss", $userId, $relativePath, $relativePath);
        $stmt->execute();
        $stmt->close();

        respond([
            'status' => 'success',
            'message' => 'File uploaded successfully',
            'file_path' => $relativePath,
            'field' => $field,
            'file_name' => $filename
        ]);
    }

    /* ===============================
       STEP 1 — PERSONAL INFO (Quick Save)
    =============================== */
    if ($step === 'step1') {

        $date            = $_POST['date'] ?? date('Y-m-d');
        $company         = $_POST['company'] ?? '';
        $prefix          = $_POST['prefix'] ?? '';
        $first_name      = $_POST['first_name'] ?? '';
        $middle_name     = $_POST['middle_name'] ?? '';
        $last_name       = $_POST['last_name'] ?? '';
        $email           = $_POST['email'] ?? '';
        $mobile          = xander_normalize_visa_mobile_storage((string) ($_POST['applicant_mobile'] ?? ''));
        $birthdate       = $_POST['birthdate'] ?? '';
        $gender          = $_POST['gender'] ?? '';
        $passport_number = $_POST['passport_number'] ?? '';
        $country_from    = $_POST['country_applying_from'] ?? '';
        $country_to      = $_POST['country_to_visit'] ?? '';
        $visa_type       = $_POST['visa_type'] ?? '';
        $region_id       = (int)($_POST['region_id'] ?? 0);
        $country_id      = (int)($_POST['country_id'] ?? 0);
        $form_url        = "visa.php?id=$userId";
        $is_read         = 0;

        // Quick validation - only essential fields
        if ($first_name === '' || $last_name === '' || $email === '') {
            throw new Exception('Missing required fields: First name, Last name, and Email are required');
        }
        if (strlen($mobile) < 8 || strlen($mobile) > 15) {
            throw new Exception('Enter a valid mobile number with country code (digits only, 8–15 characters for WhatsApp). Do not include +.');
        }
        $visa_type = trim((string) $visa_type);
        if ($visa_type === '') {
            throw new Exception('Please select a visa type.');
        }

        // Use INSERT IGNORE for faster operation
        $sql = "
            INSERT INTO form_17_applications
            (user_id, date, company, prefix, first_name, middle_name, last_name,
             email, applicant_mobile, birthdate, gender, passport_number,
             country_applying_from, country_to_visit, visa_type,
             region_id, country_id, form_url, is_read)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              date = VALUES(date),
              company = VALUES(company),
              prefix = VALUES(prefix),
              first_name = VALUES(first_name),
              middle_name = VALUES(middle_name),
              last_name = VALUES(last_name),
              email = VALUES(email),
              applicant_mobile = VALUES(applicant_mobile),
              birthdate = VALUES(birthdate),
              gender = VALUES(gender),
              passport_number = VALUES(passport_number),
              country_applying_from = VALUES(country_applying_from),
              country_to_visit = VALUES(country_to_visit),
              visa_type = VALUES(visa_type),
              region_id = VALUES(region_id),
              country_id = VALUES(country_id),
              form_url = VALUES(form_url)
        ";

        $stmt = $conn->prepare($sql);
        // 15 strings (through visa_type), then region_id, country_id, form_url, is_read — visa_type must be 's' not 'i' or it becomes 0
        $stmt->bind_param(
            str_repeat('s', 15) . 'iisi',
            $userId, $date, $company, $prefix, $first_name, $middle_name,
            $last_name, $email, $mobile, $birthdate, $gender,
            $passport_number, $country_from, $country_to, $visa_type,
            $region_id, $country_id, $form_url, $is_read
        );
        $stmt->execute();
        $stmt->close();

        respond([
            'status'  => 'success',
            'message' => 'Personal information saved',
            'user_id' => $userId,
            'next_step' => 'step2'
        ]);
    }

    /* ===============================
       STEP 2 — FINAL SUBMISSION (Just validation)
    =============================== */
    if ($step === 'step2') {

        if (empty($_POST['terms'])) {
            throw new Exception('You must agree to the terms and conditions');
        }

        // Check if all required files are uploaded
        $requiredFiles = [
            'passport_copy',
            'academic_documents',
            'old_visa_copy',
            'passport_photo',
            'cv'
        ];

        // DB uses process_status (workflow), not a "status" column. Final submit is tracked with submitted_at.
        $stmt = $conn->prepare("
            SELECT passport_copy, academic_documents, old_visa_copy, passport_photo, cv,
                   submitted_at, first_name, last_name, email, applicant_mobile, visa_type, country_to_visit
            FROM form_17_applications 
            WHERE user_id = ?
        ");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new Exception('No application found. Please complete Step 1 first.');
        }

        $alreadySubmittedAt = trim((string) ($row['submitted_at'] ?? ''));
        if ($alreadySubmittedAt !== '' && $alreadySubmittedAt !== '0000-00-00 00:00:00') {
            respond([
                'status'  => 'success',
                'message' => 'Application was already submitted.',
                'user_id' => $userId,
                'redirect' => 'form-17-confirmation.php?id=' . urlencode($userId)
            ]);
        }

        $missingFiles = [];
        foreach ($requiredFiles as $field) {
            if (empty($row[$field])) {
                $missingFiles[] = $field;
            }
        }

        if (!empty($missingFiles)) {
            throw new Exception('Missing required files: ' . implode(', ', $missingFiles));
        }

        // First successful final submit sets submitted_at; duplicate submits skip email.
        $stmt = $conn->prepare("
            UPDATE form_17_applications 
            SET submitted_at = NOW() 
            WHERE user_id = ? 
              AND (submitted_at IS NULL OR submitted_at = '0000-00-00 00:00:00')
        ");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $isFirstSubmit = $stmt->affected_rows > 0;
        $stmt->close();

        $reference = 'XGS-' . strtoupper(substr(hash('sha256', $userId), 0, 8));
        if ($isFirstSubmit) {
            require_once __DIR__ . '/helpers/application_confirmation_emails.php';
            try {
                xander_send_form17_visa_confirmation_emails($conn, $userId, $reference, $row);
            } catch (Throwable $e) {
                error_log('[save_visa] confirmation email: ' . $e->getMessage());
            }
        }

        respond([
            'status'  => 'success',
            'message' => 'Application submitted successfully!',
            'user_id' => $userId,
            'redirect' => 'form-17-confirmation.php?id=' . urlencode($userId)
        ]);
    }

    throw new Exception('Invalid request');

} catch (Throwable $e) {
    error_log('[VISA SAVE ERROR] ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    respond([
        'status'  => 'error',
        'message' => $e->getMessage(),
        'debug' => ($_SERVER['HTTP_HOST'] === 'localhost') ? [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ] : null
    ]);
}