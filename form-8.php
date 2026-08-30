<?php
session_start();
require_once __DIR__ . '/db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_GET['id'])) {
  $_SESSION['user_id'] = $_GET['id'];
}
if (!isset($_SESSION['user_id'])) {
  $_SESSION['user_id'] = 'user-' . time() . '-' . rand(1000, 9999);
}
$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM form_8_applications WHERE user_id = ?");
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc() ?? [];
$stmt->close();
$conn->close();

function selected($field, $value) {
  global $data;
  return (isset($data[$field]) && $data[$field] == $value) ? 'selected' : '';
}
function checked($field, $value) {
  global $data;
  return (isset($data[$field]) && $data[$field] == $value) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Registration Form</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="form-container">
  <h2>I 20 Registration Form</h2>
  <form id="applicationForm" data-save="save_form_8.php" enctype="multipart/form-data">
    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($userId); ?>">

    <!-- Step 1 -->
    <div class="form-step active" id="step1">
      <h3>Step 1: Personal & Contact Information</h3>
      <div class="inline-inputs">
        <input type="text" name="first_name" placeholder="First Name" required value="<?php echo htmlspecialchars($data['first_name'] ?? ''); ?>">
        <input type="text" name="middle_name" placeholder="Middle Name" value="<?php echo htmlspecialchars($data['middle_name'] ?? ''); ?>">
        <input type="text" name="last_name" placeholder="Last Name" required value="<?php echo htmlspecialchars($data['last_name'] ?? ''); ?>">
      </div>
      <div class="inline-inputs">
        <select name="birth_month" required>
          <option value="">Month</option>
          <?php for ($m=1; $m<=12; $m++) echo "<option value='$m' ".selected('birth_month', $m).">$m</option>"; ?>
        </select>
        <select name="birth_day" required>
          <option value="">Day</option>
          <?php for ($d=1; $d<=31; $d++) echo "<option value='$d' ".selected('birth_day', $d).">$d</option>"; ?>
        </select>
        <select name="birth_year" required>
          <option value="">Year</option>
          <?php for ($y=date('Y'); $y>=1900; $y--) echo "<option value='$y' ".selected('birth_year', $y).">$y</option>"; ?>
        </select>
      </div>
      <label>Gender *</label>
      <select name="gender" required>
        <option value="">Please Select</option>
        <option <?php echo selected('gender', 'Male'); ?>>Male</option>
        <option <?php echo selected('gender', 'Female'); ?>>Female</option>
        <option <?php echo selected('gender', 'Other'); ?>>Other</option>
      </select>
      <input type="text" name="address1" placeholder="Street Address" value="<?php echo htmlspecialchars($data['address1'] ?? ''); ?>">
      <input type="text" name="address2" placeholder="Street Address Line 2" value="<?php echo htmlspecialchars($data['address2'] ?? ''); ?>">
      <div class="inline-inputs">
        <input type="text" name="city" placeholder="City" value="<?php echo htmlspecialchars($data['city'] ?? ''); ?>">
        <input type="text" name="state" placeholder="State / Province" value="<?php echo htmlspecialchars($data['state'] ?? ''); ?>">
      </div>
      <input type="text" name="postal_code" placeholder="Postal / Zip Code" value="<?php echo htmlspecialchars($data['postal_code'] ?? ''); ?>">
      <div class="inline-inputs">
        <input type="email" name="student_email" placeholder="Student Email" required value="<?php echo htmlspecialchars($data['student_email'] ?? ''); ?>">
        <input type="text" name="mobile_number" placeholder="Mobile Number" required value="<?php echo htmlspecialchars($data['mobile_number'] ?? ''); ?>">
      </div>
      <div class="inline-inputs">
        <input type="text" name="phone_number" placeholder="Phone Number" value="<?php echo htmlspecialchars($data['phone_number'] ?? ''); ?>">
        <input type="text" name="work_number" placeholder="Work Number" value="<?php echo htmlspecialchars($data['work_number'] ?? ''); ?>">
      </div>
      <div class="form-buttons">
        <button type="button" class="next-btn" data-next="2">Save & Next</button>
      </div>
    </div>

    <!-- Step 2 -->
    <div class="form-step" id="step2">
      <h3>Step 2: University, Program & Documents</h3>
      <input type="text" name="university" placeholder="University Which Admitted You" required value="<?php echo htmlspecialchars($data['university'] ?? ''); ?>">
      <input type="text" name="program_admitted" placeholder="Program Admitted For" required value="<?php echo htmlspecialchars($data['program_admitted'] ?? ''); ?>">
      <div class="inline-inputs">
        <input type="text" name="university_email" placeholder="University Username or Email" required value="<?php echo htmlspecialchars($data['university_email'] ?? ''); ?>">
        <input type="text" name="university_password" placeholder="University Password" required value="<?php echo htmlspecialchars($data['university_password'] ?? ''); ?>">
      </div>
      <label>Do You Have Scholarship?</label>
      <div class="checkbox-group">
        <label><input type="checkbox" name="has_scholarship" value="YES" <?php echo checked('has_scholarship', 'YES'); ?>> YES</label>
        <label><input type="checkbox" name="has_scholarship" value="NO" <?php echo checked('has_scholarship', 'NO'); ?>> NO</label>
      </div>
      <label>Acceptance Letter *</label>
      <input type="file" name="acceptance_letter" required>
      <label>Loan Approval Letter *</label>
      <input type="file" name="loan_approval_letter" required>
      <label>Letter Detailing MPOWER Loan Decision</label>
      <input type="file" name="mpower_loan_decision">
      <label>Loan Contract</label>
      <input type="file" name="loan_contract">
      <label>Bank Statement if Available</label>
      <input type="file" name="bank_statement">
      <label>Payment Proof When Loan is Approved *</label>
      <input type="file" name="payment_proof" required>
      <label>Additional Comments</label>
      <textarea name="additional_comments" placeholder="Additional Comments"><?php echo htmlspecialchars($data['additional_comments'] ?? ''); ?></textarea>
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
