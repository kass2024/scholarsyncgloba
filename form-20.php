<?php
session_start();
require_once __DIR__ . '/db.php';
// Handle user ID from GET or session
if (isset($_GET['id']) && !empty($_GET['id'])) {
  $userId = $_GET['id'];
  $_SESSION['user_id'] = $userId;
} else {
  $userId = $_SESSION['user_id'] ?? ('user-' . time() . '-' . rand(1000, 9999));
  $_SESSION['user_id'] = $userId;
}

// Fetch application data from database
$stmt = $conn->prepare("SELECT * FROM form_20_applications WHERE user_id = ?");
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc() ?? [];
$stmt->close();

// ✅ Always prefer the GET value for dynamic heading display
$universityName = isset($_GET['university']) && $_GET['university'] !== ''
  ? htmlspecialchars($_GET['university'])
  : (!empty($data['university_name']) ? htmlspecialchars($data['university_name']) : 'Your University');

// Helper to retrieve values safely
function val($key) {
  global $data;
  return htmlspecialchars($data[$key] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>I-20 Request...</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f5f7fb;
      padding: 40px;
      color: #333;
    }

    .form-container {
      background-color: #fff;
      max-width: 900px;
      margin: auto;
      padding: 30px 40px;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }

    h2, h3 {
      text-align: center;
      color: #0c3c78;
      margin-bottom: 20px;
    }

    label {
      font-weight: 500;
      margin-top: 20px;
      display: block;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    input[type="date"],
    select,
    textarea {
      width: 100%;
      padding: 10px 15px;
      margin-top: 5px;
      border-radius: 5px;
      border: 1px solid #ccc;
      font-size: 1rem;
    }

    textarea {
      resize: vertical;
    }

    .inline-inputs {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .inline-inputs input {
      flex: 1 1 100%;
    }

    .checkbox-group label {
      display: inline-block;
      margin-right: 20px;
    }

    .form-step {
      display: none;
    }

    .form-step.active {
      display: block;
    }

    .form-buttons {
      margin-top: 30px;
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
    }

    .form-buttons button {
      background-color: #0c3c78;
      color: #fff;
      padding: 12px 20px;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      cursor: pointer;
      flex: 1;
    }

    .form-buttons button:hover {
      background-color: #092a5c;
    }

    input[type="file"] {
      width: 100%;
      max-width: 1000px;
      border: 2px dashed #bbb;
      border-radius: 8px;
      padding: 20px;
      text-align: center;
      color: #777;
      margin-top: 10px;
    }

    a {
      color: #0c3c78;
      text-decoration: underline;
    }

    /* Responsive Enhancements */
    @media (max-width: 600px) {
      body {
        padding: 20px 10px;
      }

      .form-container {
        padding: 20px;
      }

      h2, h3 {
        font-size: 1.3rem;
      }

      input[type="text"],
      input[type="email"],
      input[type="password"],
      input[type="date"],
      select,
      textarea {
        font-size: 0.95rem;
        padding: 10px;
      }

      .form-buttons {
        flex-direction: column;
      }

      .form-buttons button {
        width: 100%;
      }

      .inline-inputs input {
        flex: 1 1 100%;
      }
    }

    /* ✅ Progress Bar Styling */
    #progress-wrapper {
      display: none;
      margin-top: 20px;
      width: 100%;
    }

    #progress-wrapper > div {
      background-color: #e0e0e0;
      border-radius: 20px;
      height: 20px;
      overflow: hidden;
    }

#progress-bar {
  height: 100%;
  width: 0%;
  background-color: #0c3c78;
  border-radius: 20px;
  transition: width 0.4s ease-in-out;
}
 
  </style>
</head>


<body>
<div class="form-container">
  <h2>I-20 Request </h2>
  <form id="applicationForm" data-save="save_form_20.php" method="POST" enctype="multipart/form-data" action="save_form_20.php">


    <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">
    <input type="hidden" name="university" value="<?= htmlspecialchars($universityName) ?>">

    <!-- Step 1 -->
    <div class="form-step active" id="step1">
      <h3>Step 1: Student & University Details</h3>

      <label>Student Name</label>
      <div class="inline-inputs">
        <input type="text" name="first_name" placeholder="First Name" value="<?= val('first_name') ?>" required>
        <input type="text" name="middle_name" placeholder="Middle Name" value="<?= val('middle_name') ?>">
        <input type="text" name="last_name" placeholder="Last Name" value="<?= val('last_name') ?>" required>
      </div>

  <label>Birth Date</label>
<div class="inline-inputs">
  <!-- Month -->
  <select name="birth_month" required>
    <option value="">Month</option>
    <?php
    $months = [
      'January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'
    ];
    foreach ($months as $index => $month) {
      $value = $index + 1;
      $selected = val('birth_month') == $value ? 'selected' : '';
      echo "<option value=\"$value\" $selected>$month</option>";
    }
    ?>
  </select>

  <!-- Day -->
  <select name="birth_day" required>
    <option value="">Day</option>
    <?php
    for ($d = 1; $d <= 31; $d++) {
      $selected = val('birth_day') == $d ? 'selected' : '';
      echo "<option value=\"$d\" $selected>$d</option>";
    }
    ?>
  </select>

  <!-- Year -->
  <select name="birth_year" required>
    <option value="">Year</option>
    <?php
    $currentYear = date('Y');
    for ($y = $currentYear - 10; $y >= 1950; $y--) {
      $selected = val('birth_year') == $y ? 'selected' : '';
      echo "<option value=\"$y\" $selected>$y</option>";
    }
    ?>
  </select>
</div>

      <label>Gender</label>
      <select name="gender" required>
        <option value="">Please Select</option>
        <option value="Male" <?= val('gender') === 'Male' ? 'selected' : '' ?>>Male</option>
        <option value="Female" <?= val('gender') === 'Female' ? 'selected' : '' ?>>Female</option>
      </select>

      <label>Address</label>
      <input type="text" name="street_address" placeholder="Street Address" value="<?= val('street_address') ?>" >
      <input type="text" name="street_address_2" placeholder="Street Address Line 2" value="<?= val('street_address_2') ?>">

      <div class="inline-inputs">
        <input type="text" name="city" placeholder="City" value="<?= val('city') ?>" required>
        <input type="text" name="state" placeholder="State / Province" value="<?= val('state') ?>" required>
      </div>
      <input type="text" name="postal_zip_code" placeholder="Postal / Zip Code" value="<?= val('postal_code') ?>" required>

      <label>Email</label>
      <input type="email" name="email" value="<?= val('email') ?>" required>

      <label>Mobile Number</label>
      <input type="text" name="mobile_number" value="<?= val('mobile_number') ?>" required>

      <label>Phone Number</label>
      <input type="text" name="phone_number" value="<?= val('phone_number') ?>">

      <label>Work Number</label>
      <input type="text" name="work_number" value="<?= val('work_number') ?>">

      <label>University</label>
        <input type="text" name="university_name" value="<?= val('university_name') ?>" required>

      <label>Program Admitted For</label>
      <input type="text" name="program_admitted_for" value="<?= val('program_admitted_for') ?>" required>

      <label>University Email / Username</label>
      <input type="text" name="university_email" value="<?= val('university_email') ?>" required>

      <label>University Password</label>
      <input type="text" name="university_password" value="<?= val('university_password') ?>" required>

      <label>Scholarship</label>
      <div class="checkbox-group">
        <input type="radio" name="has_scholarship" value="YES" <?= val('has_scholarship') === 'YES' ? 'checked' : '' ?>> YES
        <input type="radio" name="has_scholarship" value="NO" <?= val('has_scholarship') === 'NO' ? 'checked' : '' ?>> NO
      </div>
<input type="hidden" name="university_id" value="<?= val('university_id') ?: ($_GET['university_id'] ?? '') ?>">
<input type="hidden" name="region_id" value="<?= val('region_id') ?: ($_GET['region_id'] ?? '') ?>">

      <div class="form-buttons">
        <button type="button" class="next-btn" data-next="2">Save & Next</button>
      </div>
    </div>

<!-- Step 2 -->
<div class="form-step" id="step2">
  <h3>Step 2: Upload Supporting Documents</h3>

<!-- ✅ Working Progress Bar -->
<div id="progress-wrapper" style="display: none; margin-top: 20px;">
  <div style="background-color: #ccc; border-radius: 20px; height: 24px; width: 100%;">
    <div id="progress-bar"
         style="height: 100%; width: 0%; background-color: #28a745; color: #fff; font-weight: bold; font-size: 14px; text-align: center; line-height: 24px; border-radius: 20px;">
      0%
    </div>
  </div>
</div>


  <?php
  $files = [
    'acceptance_letter'      => 'Acceptance Letter *',
    'loan_approval_letter'   => 'Loan Approval Letter *',
    'loan_decision_letter'   => 'Letter Detailing MPOWER Loan Decision',
    'loan_contract'          => 'Loan Contract',
    'bank_statement'         => 'Bank Statement (if available)',
    'loan_payment_proof' => 'Payment Proof When Loan is Approved *'

  ];

  foreach ($files as $name => $label): ?>
    <label for="<?= $name ?>"><?= $label ?></label>
    <?php if (!empty($data[$name])): ?>
      <div><a href="<?= htmlspecialchars($data[$name]) ?>" target="_blank">View uploaded file</a></div>
    <?php endif; ?>
    <input type="file" name="<?= $name ?>" id="<?= $name ?>" <?= strpos($label, '*') !== false ? 'required' : '' ?>>
  <?php endforeach; ?>

  <label for="additional_comments">Additional Comments</label>
  <textarea name="additional_comments" id="additional_comments" rows="4"><?= val('additional_comments') ?></textarea>

  <!-- Hidden Inputs -->
  <input type="hidden" name="region_id" value="<?= val('region_id') ?: ($_GET['region_id'] ?? '') ?>">
  <input type="hidden" name="university_id" value="<?= val('university_id') ?: ($_GET['university_id'] ?? '') ?>">
  <input type="hidden" name="step" value="step2">

  <!-- Form Buttons -->
  <div class="form-buttons">
    <button type="button" class="prev-btn" data-prev="1">Previous</button>
    <button type="submit" class="submit-btn">Submit</button>
  </div>
</div>

  </form>
</div>
<script src="script.js"></script>
</body>
</html>
