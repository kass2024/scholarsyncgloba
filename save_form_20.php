<?php
session_start();
require_once __DIR__ . '/db.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
$step = $_POST['step'] ?? null;

if (!$userId || !$step) {
  echo json_encode(['status' => 'error', 'message' => 'Missing user ID or step.']);
  exit;
}

$fields = [];
$fileFields = [];

switch ($step) {
  case 'step1':
    $fields = [
      'first_name', 'middle_name', 'last_name',
      'birth_month', 'birth_day', 'birth_year',
      'gender',
      'street_address', 'street_address_2',
      'city', 'state_province', 'postal_zip_code',
      'email', 'mobile_number', 'phone_number', 'work_number',
      'university_name', 'program_admitted_for',
      'university_email', 'university_password',
      'has_scholarship', 'form_url',
      'university_id', 'region_id'
    ];

    // Save to session
    $_SESSION['university_id'] = $_POST['university_id'] ?? null;
    $_SESSION['region_id'] = $_POST['region_id'] ?? null;

    $_POST['form_url'] = 'form-20.php';
    break;

  case 'step2':
    $fileFields = [
      'acceptance_letter',
      'loan_approval_letter',
      'loan_decision_letter',
      'loan_contract',
      'bank_statement',
      'loan_payment_proof'
    ];

    foreach ($fileFields as $fileField) {
      if (isset($_FILES[$fileField]) && $_FILES[$fileField]['size'] > 0) {
        $filePath = 'uploads/' . time() . '_' . basename($_FILES[$fileField]['name']);
        if (move_uploaded_file($_FILES[$fileField]['tmp_name'], $filePath)) {
          $_POST[$fileField] = $filePath;
        }
      }
    }

    $fields = array_merge($fileFields, ['additional_comments']);
    break;

  default:
    echo json_encode(['status' => 'error', 'message' => 'Unknown step.']);
    exit;
}

$placeholders = rtrim(str_repeat('?,', count($fields)), ',');
$updates = implode(' = ?, ', $fields) . ' = ?';

$params = [];
$types = '';

foreach ($fields as $field) {
  $val = trim($_POST[$field] ?? '');
  $params[] = $val !== '' ? $val : null;
  $types .= 's';
}

$sql = "INSERT INTO form_20_applications (user_id, " . implode(',', $fields) . ")
        VALUES (?, $placeholders)
        ON DUPLICATE KEY UPDATE $updates";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
  exit;
}

$bindTypes = 's' . $types . $types;
$bindParams = array_merge([$userId], $params, $params);
$stmt->bind_param($bindTypes, ...$bindParams);

if ($stmt->execute()) {
  if ($step === 'step2') {
    try {
      $stmtInfo = $conn->prepare("SELECT first_name, middle_name, last_name, email, university_name FROM form_20_applications WHERE user_id = ?");
      $stmtInfo->bind_param("s", $userId);
      $stmtInfo->execute();
      $stmtInfo->bind_result($firstName, $middleName, $lastName, $email, $universityName);
      $stmtInfo->fetch();
      $stmtInfo->close();

      $studentName = trim("$firstName $middleName $lastName");

      // --- ADMIN EMAIL ---
      $adminMail = new PHPMailer(true);
      $adminMail->isSMTP();
      $adminMail->Host = 'scholarsyncglobal.ca';
      $adminMail->SMTPAuth = true;
      $adminMail->Username = 'academic@scholarsyncglobal.ca
';
      $adminMail->Password = getenv('SMTP_PASSWORD') ?: '';
      $adminMail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
      $adminMail->Port = 465;
      $adminMail->setFrom('academic@scholarsyncglobal.ca
', 'ScholarSync Global');
      $adminMail->addAddress('academic@scholarsyncglobal.ca
');
      $adminMail->isHTML(true);
      $adminMail->Subject = "New I-20 Request Received";
      $adminMail->Body = "<p>A student has submitted a complete I-20 request.</p>
                          <p><strong>Name:</strong> $studentName<br>
                          <strong>Email:</strong> $email<br>
                          <strong>University:</strong> $universityName<br>
                          <strong>User ID:</strong> $userId</p>";
      $adminMail->send();

      // --- STUDENT EMAIL ---
      if (!empty($email)) {
        $studentMail = new PHPMailer(true);
        $studentMail->isSMTP();
        $studentMail->Host = 'scholarsyncglobal.ca';
        $studentMail->SMTPAuth = true;
        $studentMail->Username = 'academic@scholarsyncglobal.ca';
        $studentMail->Password = getenv('SMTP_PASSWORD') ?: '';
        $studentMail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $studentMail->Port = 465;
        $studentMail->setFrom('academic@scholarsyncglobal.ca', 'ScholarSync Global');
        $studentMail->addAddress($email, $studentName);
        $studentMail->isHTML(true);
        $studentMail->Subject = "Confirmation of I-20 Request Submission";
        $studentMail->Body = "<p>Dear $studentName,</p>
                              <p>We have received your I-20 request and will begin reviewing it shortly.</p>
                              <p>Thank you,<br>ScholarSync Global Team</p>";
        $studentMail->send();
      }
    } catch (Exception $e) {
      error_log("Email sending failed: " . $e->getMessage());
    }
  }

  echo json_encode(['status' => 'success', 'user_id' => $userId]);
} else {
  echo json_encode(['status' => 'error', 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
