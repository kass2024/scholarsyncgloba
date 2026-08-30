<?php
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

// Generate or reuse user ID
$userId = $_SESSION['staff_user_id'] ?? ('staff-' . time() . '-' . rand(1000, 9999));
$_SESSION['staff_user_id'] = $userId;

$step = $_POST['step'] ?? null;
if (!$step) {
  echo json_encode(['status' => 'error', 'message' => 'Missing step.']);
  exit;
}

// File upload helper
function uploadFile($inputName, $folder = 'uploads/') {
  if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
    return null;
  }
  $filename = uniqid() . '_' . basename($_FILES[$inputName]['name']);
  $target = $folder . $filename;
  move_uploaded_file($_FILES[$inputName]['tmp_name'], $target);
  return $target;
}

// STEP 1: Insert or update step 1 data
if ($step === 'step1') {
  $job1Image = uploadFile('job1_image');

  $stmt = $conn->prepare("INSERT INTO staff_job_reports (
    user_id, first_name, last_name, email, phone_number, job_name, report_date,
    job1_start_time, job1_finish_time, job1_down_time, job1_description, job1_image
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    first_name=VALUES(first_name), last_name=VALUES(last_name), email=VALUES(email),
    phone_number=VALUES(phone_number), job_name=VALUES(job_name), report_date=VALUES(report_date),
    job1_start_time=VALUES(job1_start_time), job1_finish_time=VALUES(job1_finish_time),
    job1_down_time=VALUES(job1_down_time), job1_description=VALUES(job1_description), job1_image=VALUES(job1_image)");

  $stmt->bind_param("ssssssssssss",
    $userId,
    $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'],
    $_POST['job_name'], $_POST['job_date'],
    $_POST['job1_start'], $_POST['job1_finish'], $_POST['job1_down'], $_POST['job1_desc'],
    $job1Image
  );

  if ($stmt->execute()) {
    echo json_encode([
      'status' => 'success',
      'message' => 'Step 1 saved.',
      'user_id' => $userId
    ]);
  } else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
  }

  $stmt->close();
  $conn->close();
  exit;
}

// STEP 2: Update remaining job info
if ($step === 'step2') {
  $job2Image = uploadFile('job2_image');
  $job3Image = uploadFile('job3_image');

  $stmt = $conn->prepare("UPDATE staff_job_reports SET
    job2_start_time=?, job2_finish_time=?, job2_down_time=?, job2_description=?, job2_image=?,
    job3_start_time=?, job3_finish_time=?, job3_down_time=?, job3_description=?, job3_image=?,
    comments=?
    WHERE user_id=?");

  $stmt->bind_param("ssssssssssss",
    $_POST['job2_start'], $_POST['job2_finish'], $_POST['job2_down'], $_POST['job2_desc'], $job2Image,
    $_POST['job3_start'], $_POST['job3_finish'], $_POST['job3_down'], $_POST['job3_desc'], $job3Image,
    $_POST['comments'], $userId
  );

  if ($stmt->execute()) {
    echo json_encode([
      'status' => 'success',
      'message' => 'Final submission successful.',
      'user_id' => $userId
    ]);
    session_destroy(); // destroy session after echo
  } else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
  }

  $stmt->close();
  $conn->close();
  exit;
}

// Catch-all for unexpected steps
echo json_encode(['status' => 'error', 'message' => 'Unknown step']);
exit;
