<?php
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

// Application id must come from the form (no orphan DB rows on page views).
$userId = trim((string)($_POST['user_id'] ?? ''));
if ($userId === '' || !preg_match('/^credit-[a-zA-Z0-9._-]+$/', $userId)) {
  echo json_encode(['status' => 'error', 'message' => 'Invalid or missing application id. Please reload the page.']);
  exit;
}
$_SESSION['credit_user_id'] = $userId;

// Step guard
$step = $_POST['step'] ?? null;
if (!$step) {
  echo json_encode(['status' => 'error', 'message' => 'Missing step']);
  exit;
}

// ✅ SIMPLE UPLOAD HELPER - SAME AS YOURS BUT WITH BASIC VALIDATION
function uploadFile($inputName) {
  if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
    return null;
  }
  
  // Basic security check
  $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
  $maxSize = 10 * 1024 * 1024; // 10MB
  
  $file = $_FILES[$inputName];
  $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  
  // Check extension
  if (!in_array($fileExt, $allowedExtensions)) {
    return null;
  }
  
  // Check size
  if ($file['size'] > $maxSize) {
    return null;
  }
  
  // Generate safe filename - SAME AS YOUR LOGIC
  $filename = uniqid('', true) . '_' . basename($_FILES[$inputName]['name']);
  $targetDir = __DIR__ . '/uploads/';
  
  // Ensure directory exists - SAME AS YOUR LOGIC
  if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0775, true);
  }
  
  $targetRel = 'uploads/' . $filename;
  $targetAbs = $targetDir . $filename;
  
  if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $targetAbs)) {
    return null;
  }
  
  return $targetRel;
}

/** Map French / alternate gender labels to form values (Male, Female, Other). */
function normalize_credit_transfer_gender(string $g): string
{
  $g = trim($g);
  if ($g === '') {
    return '';
  }
  $lower = mb_strtolower($g, 'UTF-8');
  if (in_array($lower, ['male', 'm', 'homme', 'masculin'], true)) {
    return 'Male';
  }
  if (in_array($lower, ['female', 'f', 'femme', 'féminin', 'feminin'], true)) {
    return 'Female';
  }
  if (in_array($lower, ['other', 'o', 'autre'], true)) {
    return 'Other';
  }
  return $g;
}

/** ✅ STEP 1: Personal info - KEEPING YOUR EXACT LOGIC **/
if ($step === 'step1') {
  $email = $_POST['email'] ?? '';
  
  // ✅ YOUR EXACT DUPLICATE EMAIL CHECK
  $checkStmt = $conn->prepare("SELECT COUNT(*) FROM credit_transfer_applications WHERE email = ? AND user_id != ?");
  $checkStmt->bind_param("ss", $email, $userId);
  $checkStmt->execute();
  $checkStmt->bind_result($emailCount);
  $checkStmt->fetch();
  $checkStmt->close();

  if ($emailCount > 0) {
    echo json_encode(['status' => 'error', 'message' => '❌ This email has already been used to apply.']);
    $conn->close();
    exit;
  }

  // ✅ YOUR EXACT FIELD MAPPING
  $fields = [
    'user_id', 'first_name', 'middle_name', 'last_name',
    'birth_month', 'birth_day', 'birth_year', 'gender',
    'street_address', 'address_line_2', 'city', 'state', 'postal_code',
    'email', 'mobile_number', 'phone_number', 'work_number', 'company'
    // NOTE: university is captured in Step 2 by design
  ];

  // ✅ YOUR EXACT VALUES EXTRACTION
  $values = [
    $userId,
    $_POST['first_name'] ?? '', $_POST['middle_name'] ?? '', $_POST['last_name'] ?? '',
    $_POST['birth_month'] ?? '', $_POST['birth_day'] ?? '', $_POST['birth_year'] ?? '', $_POST['gender'] ?? '',
    $_POST['street_address'] ?? '', $_POST['address_line_2'] ?? '', $_POST['city'] ?? '', $_POST['state'] ?? '', $_POST['postal_code'] ?? '',
    $_POST['email'] ?? '', $_POST['mobile_number'] ?? '', $_POST['phone_number'] ?? '', $_POST['work_number'] ?? '', $_POST['company'] ?? ''
  ];

  // ✅ YOUR EXACT SQL BUILDING
  $placeholders = implode(',', array_fill(0, count($fields), '?'));
  $updatePart  = implode(', ', array_map(fn($f) => "$f = VALUES($f)", array_slice($fields, 1)));

  $sql = "INSERT INTO credit_transfer_applications (" . implode(',', $fields) . ")
          VALUES ($placeholders)
          ON DUPLICATE KEY UPDATE $updatePart";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
  }

  $types = str_repeat('s', count($values));
  $stmt->bind_param($types, ...$values);

  if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'user_id' => $userId]);
  } else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
  }

  $stmt->close();
  $conn->close();
  exit;
}

/** ✅ STEP 2: Academic + Files (+ University) - KEEPING YOUR EXACT LOGIC **/
if ($step === 'step2') {
  $partialSubmit = isset($_POST['partial_submit']) && (string)$_POST['partial_submit'] === '1';

  // ✅ YOUR EXACT DATA PROCESSING
  $educationLevels     = isset($_POST['edu_level']) ? json_encode($_POST['edu_level']) : '';
  $certificationLevels = isset($_POST['cert_level']) ? json_encode($_POST['cert_level']) : '';
  $currentProgram      = $_POST['current_program'] ?? '';
  $comments            = $_POST['comments'] ?? '';

  // ✅ YOUR EXACT UNIVERSITY VALIDATION (with IST added to match your form)
  $university = $_POST['university'] ?? '';
  require_once __DIR__ . '/helpers/credit_transfer_programs.php';
  $allowedUniversities = pcvc_credit_transfer_university_codes();
  if (!in_array($university, $allowedUniversities, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid university. Choose UPAFA, DPHU, IST or USOJ.']);
    $conn->close();
    exit;
  }

  $proposedProgram = $_POST['proposed_program'] ?? '';

  $email = trim((string)($_POST['email'] ?? ''));
  if ($partialSubmit && $email === '') {
    $safeLocal = preg_replace('/[^a-zA-Z0-9._-]/', '-', $userId);
    $email = 'draft+' . $safeLocal . '@credit-transfer-draft.local';
  } elseif (!$partialSubmit && $email === '') {
    echo json_encode(['status' => 'error', 'message' => 'Email is required.']);
    $conn->close();
    exit;
  }

  $checkStmt = $conn->prepare('SELECT COUNT(*) FROM credit_transfer_applications WHERE email = ? AND user_id != ?');
  $checkStmt->bind_param('ss', $email, $userId);
  $checkStmt->execute();
  $checkStmt->bind_result($emailCount);
  $checkStmt->fetch();
  $checkStmt->close();
  if ($emailCount > 0) {
    echo json_encode(['status' => 'error', 'message' => '❌ This email has already been used to apply.']);
    $conn->close();
    exit;
  }

  if (!$partialSubmit) {
    $requiredPersonal = ['first_name', 'last_name', 'birth_month', 'birth_day', 'birth_year', 'gender'];
    foreach ($requiredPersonal as $rp) {
      if (!isset($_POST[$rp]) || trim((string)$_POST[$rp]) === '') {
        echo json_encode(['status' => 'error', 'message' => 'Please complete all required personal fields (name, birth date, gender).']);
        $conn->close();
        exit;
      }
    }
  }

  // ✅ YOUR EXACT FILE UPLOADS
  $degree     = uploadFile('current_degree');
  $transcript = uploadFile('current_transcripts');
  $passport   = uploadFile('passport_or_id');
  $cv         = uploadFile('academic_cv');
  $payment    = uploadFile('payment_proof');

  // Existing file paths (resume without re-uploading)
  $existingRow = null;
  $exStmt = $conn->prepare('SELECT current_degree, current_transcripts, passport_or_id, academic_cv, payment_proof FROM credit_transfer_applications WHERE user_id = ? LIMIT 1');
  if ($exStmt) {
    $exStmt->bind_param('s', $userId);
    $exStmt->execute();
    $existingRow = $exStmt->get_result()->fetch_assoc();
    $exStmt->close();
  }

  if (!$partialSubmit) {
    $requiredFiles = ['current_degree', 'current_transcripts', 'passport_or_id', 'academic_cv', 'payment_proof'];
    $fileErrors = [];

    foreach ($requiredFiles as $fileField) {
      $hasUpload = isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK;
      $hasExisting = $existingRow && !empty(trim((string)($existingRow[$fileField] ?? '')));
      if (!$hasUpload && !$hasExisting) {
        $fileErrors[] = ucfirst(str_replace('_', ' ', $fileField)) . ' is required';
      }
    }

    if (!empty($fileErrors)) {
      echo json_encode(['status' => 'error', 'message' => implode(', ', $fileErrors)]);
      $conn->close();
      exit;
    }
  }

  // ✅ YOUR EXACT UPDATE FIELDS BUILDING
  $updateFields = [
    'education_levels'     => $educationLevels,
    'certification_levels' => $certificationLevels,
    'current_program'      => $currentProgram,
    'university'           => $university,
    'proposed_program'     => $proposedProgram,
    'comments'             => $comments,
    'first_name'           => trim((string)($_POST['first_name'] ?? '')),
    'middle_name'          => trim((string)($_POST['middle_name'] ?? '')),
    'last_name'            => trim((string)($_POST['last_name'] ?? '')),
    'birth_month'          => trim((string)($_POST['birth_month'] ?? '')),
    'birth_day'            => trim((string)($_POST['birth_day'] ?? '')),
    'birth_year'           => trim((string)($_POST['birth_year'] ?? '')),
    'gender'               => normalize_credit_transfer_gender((string)($_POST['gender'] ?? '')),
    'street_address'       => trim((string)($_POST['street_address'] ?? '')),
    'address_line_2'       => trim((string)($_POST['address_line_2'] ?? '')),
    'city'                 => trim((string)($_POST['city'] ?? '')),
    'state'                => trim((string)($_POST['state'] ?? '')),
    'postal_code'          => trim((string)($_POST['postal_code'] ?? '')),
    'email'                => $email,
    'mobile_number'        => trim((string)($_POST['mobile_number'] ?? '')),
    'phone_number'         => trim((string)($_POST['phone_number'] ?? '')),
    'work_number'          => trim((string)($_POST['work_number'] ?? '')),
    'company'              => trim((string)($_POST['company'] ?? '')),
  ];

  // ✅ YOUR EXACT FILE FIELD ADDITION LOGIC
  if ($degree)     $updateFields['current_degree']     = $degree;
  if ($transcript) $updateFields['current_transcripts'] = $transcript;
  if ($passport)   $updateFields['passport_or_id']      = $passport;
  if ($cv)         $updateFields['academic_cv']         = $cv;
  if ($payment)    $updateFields['payment_proof']       = $payment;

  // ✅ INSERT new application row once, then UPDATE on later edits (no empty rows on page load)
  $rowExists = is_array($existingRow);

  if (!$rowExists) {
    $insertCols = array_merge(['user_id'], array_keys($updateFields));
    $quotedCols = array_map(static function ($c) {
      return '`' . str_replace('`', '', $c) . '`';
    }, $insertCols);
    $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
    $sql = 'INSERT INTO credit_transfer_applications (' . implode(',', $quotedCols) . ') VALUES (' . $placeholders . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
      exit;
    }
    $types = str_repeat('s', count($insertCols));
    $values = array_merge([$userId], array_values($updateFields));
    $stmt->bind_param($types, ...$values);
  } else {
    $setClause = implode(', ', array_map(fn($k) => "$k = ?", array_keys($updateFields)));
    $sql = "UPDATE credit_transfer_applications SET $setClause WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
      exit;
    }
    $types  = str_repeat('s', count($updateFields)) . 's';
    $values = array_values($updateFields);
    $values[] = $userId;
    $stmt->bind_param($types, ...$values);
  }

  if ($stmt->execute()) {
    // ✅ YOUR EXACT SUCCESS RESPONSE
    echo json_encode(['status' => 'success', 'message' => 'Submission complete.', 'user_id' => $userId]);

    // ✅ YOUR EXACT EMAIL BACKGROUND PROCESSING
    ignore_user_abort(true);

    // Finish response to client ASAP
    if (function_exists('fastcgi_finish_request')) {
      fastcgi_finish_request();
    } else {
      @ob_end_flush();
      @flush();
    }

    // ✅ YOUR EXACT EMAIL SENDER CALL
    $escapedUserId = escapeshellarg($userId);
    exec("php send_credit_email.php $escapedUserId > /dev/null 2>&1 &");

    // ✅ YOUR EXACT SESSION CLEANUP
    session_destroy();

  } else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
  }

  $stmt->close();
  $conn->close();
  exit;
}

// Fallback
echo json_encode(['status' => 'error', 'message' => 'Unknown step']);
exit;