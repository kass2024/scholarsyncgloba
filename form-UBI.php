<?php
session_start();
require_once __DIR__ . '/db.php';
// Handle user_id from URL or session (MUST be first!)
if (isset($_GET['id']) && strpos($_GET['id'], 'user-') === 0) {
    $_SESSION['user_id'] = $_GET['id'];

    // Clean URL
    $cleanUrl = strtok($_SERVER["REQUEST_URI"], '?');
    $query = $_GET;
    unset($query['id']);
    if (!empty($query)) {
        $cleanUrl .= '?' . http_build_query($query);
    }
    header("Location: $cleanUrl");
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'user-' . time() . '-' . rand(1000, 9999);
}

$userId = $_SESSION['user_id'];

// Define accepted agent IDs
$agent_ids = [1, 2, 10,12,13,14,15,17,18,27,28,34,35,54,55,56,57,58,62,63,64,65,66];

// Query agents from admins table
$agent_query = "SELECT id, first_name, last_name, email FROM admins WHERE id IN (" . implode(',', $agent_ids) . ")";
$agent_result = mysqli_query($conn, $agent_query);

$agents = [];
while ($row = mysqli_fetch_assoc($agent_result)) {
    $agents[] = $row;
}

// Defaults
$universityName = 'Unknown University';
$regionName = 'Unknown Region';
$universityId = null;
$regionId = null;

// Capture university_id from URL
if (isset($_GET['university_id']) && is_numeric($_GET['university_id'])) {
    $universityId = (int) $_GET['university_id'];

    $stmt = $conn->prepare("
        SELECT u.name AS university_name, r.name AS region_name, r.id AS region_id
        FROM universities u
        JOIN regions r ON u.region_id = r.id
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $universityId);
    $stmt->execute();
    $stmt->bind_result($universityName, $regionName, $regionId);
    $stmt->fetch();
    $stmt->close();

    // Store in session
    $_SESSION['university_id'] = $universityId;
    $_SESSION['region_id'] = $regionId;
}

// Retrieve saved data SAFELY
$stmt = $conn->prepare("SELECT * FROM student_applications WHERE user_id = ?");
$stmt->bind_param("s", $userId);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    $studentData = $result->fetch_assoc() ?? [];
    $stmt->close();
} else {
    // Log error if needed
    error_log("Failed to load student_applications for user_id=$userId: " . $stmt->error);
    $studentData = []; // Fallback to empty array
}

// ✅ FIXED: If reloading saved application, but no university_id in URL — use saved values
if (!isset($_GET['university_id']) && isset($studentData['university_id']) && $studentData['university_id'] > 0) {
    $universityId = (int)$studentData['university_id'];
    $regionId = (int)$studentData['region_id'];

    $stmt = $conn->prepare("
        SELECT u.name AS university_name, r.name AS region_name, r.id AS region_id
        FROM universities u
        JOIN regions r ON u.region_id = r.id
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $universityId);
    $stmt->execute();
    $stmt->bind_result($universityName, $regionName, $regionId);
    $stmt->fetch();
    $stmt->close();
}

// Helper functions
function checked($field, $value) {
    global $studentData;
    return (isset($studentData[$field]) && $studentData[$field] == $value) ? 'checked' : '';
}

function selected($field, $value) {
    global $studentData;
    return (isset($studentData[$field]) && $studentData[$field] == $value) ? 'selected' : '';
}

function isChecked($field, $value) {
    global $studentData;
    return (isset($studentData[$field]) && strpos($studentData[$field], $value) !== false) ? 'checked' : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Application  - <?= htmlspecialchars($universityName) ?></title>

  <!-- Flatpickr and Select2 Styles -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.19/build/css/intlTelInput.css" />
<style>
  body {
    font-family: 'Segoe UI', sans-serif;
    background-color: #f5f7fb;
    padding: 30px 15px;
    color: #333;
  }

  .form-container {
    background-color: #fff;
    max-width: 950px;
    width: 100%;
    margin: auto;
    padding: 30px 40px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
  }

  h2, h3 {
    text-align: center;
    color: #0c3c78;
    margin-bottom: 20px;
    font-size: 1.5rem;
  }

  label {
    font-weight: 500;
    margin-top: 20px;
    display: block;
  }

  input[type="text"],
  input[type="email"],
  input[type="date"],
  select,
  textarea {
    width: 100%;
    padding: 10px 15px;
    margin-top: 5px;
    border-radius: 5px;
    border: 1px solid #ccc;
    font-size: 1rem;
    box-sizing: border-box;
  }

  input[type="file"] {
    display: block;
    margin-top: 5px;
    padding: 10px;
    border-radius: 8px;
    border: 2px dashed #bbb;
    background: #fafafa;
    color: #555;
    width: 100%;
    font-size: 0.95rem;
    box-sizing: border-box;
    overflow-wrap: break-word;
    word-break: break-word;
  }

  textarea {
    resize: vertical;
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
    flex-wrap: wrap;
    gap: 10px;
    justify-content: space-between;
  }

  .form-buttons button {
    background-color: #0c3c78;
    color: #fff;
    padding: 12px 20px;
    border: none;
    border-radius: 6px;
    font-size: 1rem;
    cursor: pointer;
    flex: 1 1 48%;
  }

  .form-buttons button:hover {
    background-color: #092a5c;
  }

  .radio-group,
  .checkbox-group {
    margin-top: 10px;
  }

  .radio-group label,
  .checkbox-group label {
    display: inline-block;
    margin-right: 20px;
  }

  /* Program selection horizontal layout (default) */
  .inline-inputs {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
  }

  .inline-inputs > div {
    flex: 1 1 30%;
    min-width: 200px;
  }

  .inline-inputs select {
    width: 100%;
  }

  /* Mobile Optimization */
  @media (max-width: 600px) {
    body {
      padding: 15px;
    }

    .form-container {
      padding: 20px;
    }

    h2, h3 {
      font-size: 1.3rem;
    }

    .inline-inputs {
      flex-direction: column;
    }

    .inline-inputs > div {
      flex: 1 1 100%;
      min-width: 100%;
    }

    .form-buttons {
      flex-direction: column;
      gap: 10px;
    }

    .form-buttons button {
      width: 100%;
      flex: 1 1 100%;
    }
  }
.intl-tel-input {
  width: 100%;
  font-family: 'Segoe UI', sans-serif;
}

.intl-tel-input .iti__flag-container {
  height: 100%;
}

.intl-tel-input .iti__selected-flag {
  background-color: #fff;
  border: 1px solid #ccc;
  border-right: none;
  height: 45px;
  border-radius: 5px 0 0 5px;
  padding: 0 12px;
  display: flex;
  align-items: center;
}

.intl-tel-input input[type="tel"] {
  height: 45px;
  border: 1px solid #ccc;
  border-left: none;
  font-size: 1rem;
  border-radius: 0 5px 5px 0;
  padding: 10px 15px;
  width: 100%;
  box-sizing: border-box;
}

.intl-tel-input input[type="tel"]:focus {
  outline: none;
  border-color: #0c3c78;
  box-shadow: 0 0 4px rgba(12, 60, 120, 0.2);
}

.iti--separate-dial-code .iti__selected-dial-code {
  margin-left: 8px;
  font-weight: 500;
  color: #333;
}
/* Chat Bubble */
#chat-bubble {
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 9999;
}

#chat-bubble button {
  padding: 12px 16px;
  background-color: #0c3c78;
  color: #fff;
  border: none;
  border-radius: 50px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.2);
  cursor: pointer;
  font-size: 16px;
  transition: background-color 0.3s ease;
  position: relative;
}

#chat-bubble button:hover {
  background-color: #092a5c;
}

#chat-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  background: red;
  color: #fff;
  font-size: 12px;
  padding: 2px 6px;
  border-radius: 50%;
}

/* Chat Window */
#chat-window {
  display: none;
  position: fixed;
  bottom: 80px;
  right: 20px;
  width: 320px;
  max-height: 450px;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 6px 15px rgba(0,0,0,0.3);
  z-index: 9999;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

#chat-window .chat-header {
  background-color: #0c3c78;
  color: #fff;
  padding: 12px 15px;
  font-weight: bold;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

#chat-window .chat-messages {
  flex: 1;
  padding: 10px;
  overflow-y: auto;
  font-size: 14px;
}

#chat-window .chat-input {
  display: flex;
  border-top: 1px solid #ddd;
}

#chat-window .chat-input textarea {
  flex: 1;
  padding: 10px;
  border: none;
  resize: none;
  font-size: 14px;
}

#chat-window .chat-input button {
  padding: 10px 15px;
  border: none;
  background: #0c3c78;
  color: #fff;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

#chat-window .chat-input button:hover {
  background: #092a5c;
}

</style>
</head>

<body>
<div class="form-container">
  <h2>Student Application Form – <?= htmlspecialchars($universityName) ?></h2>
<form id="applicationForm" method="POST" enctype="multipart/form-data" data-save="save_partial.php">

    <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">
<input type="hidden" name="university_id" value="<?= htmlspecialchars($universityId) ?>">
<input type="hidden" name="region_id" value="<?= htmlspecialchars($regionId) ?>">

    <!-- Step 1 -->
    <div class="form-step active" id="step1">
      <h3>Step 1: Personal Information</h3>
      <label>Student Name  </label>
      <div class="inline-inputs">
        <input type="text" name="first_name" placeholder="First Name" required value="<?php echo htmlspecialchars($studentData['first_name'] ?? ''); ?>">
        <input type="text" name="last_name" placeholder="Last Name" required value="<?php echo htmlspecialchars($studentData['last_name'] ?? ''); ?>">
      </div>
      <label>Student Email  </label>
      <input type="email" name="email" required value="<?php echo htmlspecialchars($studentData['email'] ?? ''); ?>">
     <!-- Phone Number -->
<?php
// Combine area code + phone number for display
$valuePhone = (!empty($studentData['area_code']) && !empty($studentData['phone_number']))
    ? $studentData['area_code'] . $studentData['phone_number']
    : ($studentData['phone_number'] ?? '');
?>

<div class="form-group" style="margin-bottom: 20px;">
  <label for="phone_number" class="form-label">Phone Number</label>
  <input
    type="tel"
    id="phone_number"
    name="phone_number_display"
    class="form-control phone-input"
    autocomplete="tel"
    required
    value="<?= htmlspecialchars($valuePhone) ?>"
  />
  <input type="hidden" id="area_code" name="area_code" value="<?= htmlspecialchars($studentData['area_code'] ?? '') ?>" required />
  <input type="hidden" id="phone_number_cleaned" name="phone_number" value="<?= htmlspecialchars($studentData['phone_number'] ?? '') ?>" required />
</div>




      <label>Gender  </label>
      <div class="radio-group">
        <label><input type="radio" name="gender" value="Male" <?php echo checked('gender', 'Male'); ?>> Male</label>
        <label><input type="radio" name="gender" value="Female" <?php echo checked('gender', 'Female'); ?>> Female</label>
      </div>
      <label>Country of Birth  </label>
<select name="country_of_birth" class="select2-country" required>
  <option value="">Select Country</option>
  <?php
  $countryQuery = $conn->query("SELECT name FROM countries ORDER BY name ASC");
  while ($row = $countryQuery->fetch_assoc()) {
      $name = htmlspecialchars($row['name']);
      $selected = ($studentData['country_of_birth'] ?? '') === $name ? 'selected' : '';
      echo "<option value=\"$name\" $selected>$name</option>";
  }
  ?>
</select>

<select name="nationality" class="select2-country" required>
  <option value="">Select Country</option>
  <?php
  $countryQuery = $conn->query("SELECT name FROM countries ORDER BY name ASC");
  while ($row = $countryQuery->fetch_assoc()) {
      $name = htmlspecialchars($row['name']);
      $selected = ($studentData['nationality'] ?? '') === $name ? 'selected' : '';
      echo "<option value=\"$name\" $selected>$name</option>";
  }
  ?>
</select>

</select>
      <label>Second Nationality</label>
      <input type="text" name="second_nationality" value="<?php echo htmlspecialchars($studentData['second_nationality'] ?? ''); ?>">
      <label>City of Birth  </label>
      <input type="text" name="city_of_birth" required value="<?php echo htmlspecialchars($studentData['city_of_birth'] ?? ''); ?>">
      <label>Date of Birth  </label>
      <input type="date" name="dob" required value="<?php echo htmlspecialchars($studentData['dob'] ?? ''); ?>">
      <label>Address  </label>
      <input type="text" name="address_line1" required placeholder="Street Address" value="<?php echo htmlspecialchars($studentData['address_line1'] ?? ''); ?>">
      <input type="text" name="address_line2" placeholder="Street Address Line 2" value="<?php echo htmlspecialchars($studentData['address_line2'] ?? ''); ?>">
      <div class="inline-inputs">
        <input type="text" name="city" placeholder="City" required value="<?php echo htmlspecialchars($studentData['city'] ?? ''); ?>">
        <input type="text" name="state_province" placeholder="State/Province" required value="<?php echo htmlspecialchars($studentData['state_province'] ?? ''); ?>">
      </div>
      <input type="text" name="postal_code" placeholder="Postal/Zip Code" required value="<?php echo htmlspecialchars($studentData['postal_code'] ?? ''); ?>">
      <label>Application Date  </label>
      <input type="date" name="application_date" required value="<?php echo htmlspecialchars($studentData['application_date'] ?? ''); ?>">
      <div class="form-buttons">
        <button type="button" class="next-btn" data-next="2">Save & Next</button>
      </div>
    </div>

    <!-- Step 2 -->
    <div class="form-step" id="step2">
  <h3>Step 2: Program and Destination</h3>

  <div class="inline-inputs">
    <div>
  <label>Bachelor Program</label>
  <select name="bachelor_program">
  <option value="">Please Select</option>
  <option value="Bachelor of Science (Hons.) Business">Bachelor of Science (Hons.) Business</option>
<option value="BSc (Hons) International Business Management">BSc (Hons) International Business Management</option>
<option value="BSc (Hons) Management of AI & Machine Learning">BSc (Hons) Management of AI & Machine Learning</option>
<option value="BSc (Hons) Management of Technology">BSc (Hons) Management of Technology</option>
<option value="BSc Business (Environmental, Social, and Governance Management)">BSc Business (Environmental, Social, and Governance Management)</option>
<option value="BSc Business (Management of Artificial Intelligence in E-commerce & Retail Marketing)">BSc Business (Management of Artificial Intelligence in E-commerce & Retail Marketing)</option>
<option value="BSc Business (Management of Sustainable Energy Transition)">BSc Business (Management of Sustainable Energy Transition)</option>

</select>

</div>

    <div>
  <label>Masters Program</label>
  <select name="masters_program" class="form-control select2">
    <option value="">Please Select</option>
   <option value="Management of Technology (MSc)">Management of Technology (MSc)</option>
<option value="Master in Management (MSc)">Master in Management (MSc)</option>
<option value="Master of Business Administration (MBA)">Master of Business Administration (MBA)</option>
<option value="MSc Management (AI in E-commerce & Retail Marketing)">MSc Management (AI in E-commerce & Retail Marketing)</option>
<option value="MSc Management (Business Transformation and Demographic Change)">MSc Management (Business Transformation and Demographic Change)</option>
<option value="MSc Management (CRM and Marketing Cloud)">MSc Management (CRM and Marketing Cloud)</option>
<option value="MSc Management (Digital Healthcare)">MSc Management (Digital Healthcare)</option>
<option value="MSc Management (Environmental, Social, and Governance – ESG)">MSc Management (Environmental, Social, and Governance – ESG)</option>
<option value="MSc Management (Sustainable Energy Transition)">MSc Management (Sustainable Energy Transition)</option>
<option value="MSc Management of AI & Machine Learning">MSc Management of AI & Machine Learning</option>



  </select>
</div>
<div>
  <label>PhD Program</label>
  <select name="phd_program">
    <option value="">Please Select</option>
    
  </select>
</div>


  </div>
      <label>Select Destination  </label>
      <div class="checkbox-group">
        <label><input type="checkbox" name="destination[]" value="USA" <?php echo isChecked('destination', 'USA'); ?>> USA</label>
        <label><input type="checkbox" name="destination[]" value="Canada" <?php echo isChecked('destination', 'Canada'); ?>> Canada</label>
        <label><input type="checkbox" name="destination[]" value="Europe" <?php echo isChecked('destination', 'Europe'); ?>> Europe</label>
        <label><input type="checkbox" name="destination[]" value="Asia" <?php echo isChecked('destination', 'Europe'); ?>> Asia</label>
      </div>
      <label>If other, please specify:</label>
      <textarea name="other_destination"><?php echo htmlspecialchars($studentData['other_destination'] ?? ''); ?></textarea>
      <div class="form-buttons">
        <button type="button" class="prev-btn" data-prev="1">Previous</button>
        <button type="button" class="next-btn" data-next="3">Save & Next</button>
      </div>
    </div>
<!-- Step 3 -->
<div class="form-step" id="step3">
  <h3>Step 3: Financial & Background</h3>

  <label>Select Destination  </label>
  <div class="checkbox-group">
    <label><input type="checkbox" name="destination_loan[]" value="USA WHERE LOAN COVER TUITION FEES AND LIVING ALLOWANCE" <?php echo isChecked('destination_loan', 'USA WHERE LOAN COVER TUITION FEES AND LIVING ALLOWANCE'); ?>> USA WHERE LOAN COVER TUITION FEES AND LIVING ALLOWANCE</label>
    <label><input type="checkbox" name="destination_loan[]" value="CANADA WHERE LOAN COVER TUITION FEES" <?php echo isChecked('destination_loan', 'CANADA WHERE LOAN COVER TUITION FEES'); ?>> CANADA WHERE LOAN COVER TUITION FEES</label>
    <label><input type="checkbox" name="destination_loan[]" value="EUROPE WHERE LOAN COVER TUITION FEES" <?php echo isChecked('destination_loan', 'EUROPE WHERE LOAN COVER TUITION FEES'); ?>> EUROPE WHERE LOAN COVER TUITION FEES</label>
    <label><input type="checkbox" name="destination_loan[]" value="ASIA SOUTH KOREA" <?php echo isChecked('destination_loan', 'EUROPE WHERE LOAN COVER TUITION FEES'); ?>> ASIA SOUTH KOREA</label>
  </div>

  <label>If you select other, please specify:</label>
  <textarea name="other_destination_loan"><?php echo htmlspecialchars($studentData['other_destination_loan'] ?? ''); ?></textarea>

  <label>Who will be paying the tuition fees?  </label>
  <select name="paying_tuition_fees" required>
    <option value="">Please Select</option>
    <option <?php echo selected('paying_tuition_fees', 'Self'); ?>>Self</option>
    <option <?php echo selected('paying_tuition_fees', 'Family'); ?>>Family</option>
    <option <?php echo selected('paying_tuition_fees', 'Sponsor'); ?>>Sponsor</option>
    <option <?php echo selected('paying_tuition_fees', 'Loan'); ?>>Loan</option>
  </select>

  <label>Who will be paying the cost of living?  </label>
  <select name="paying_cost_living" required>
    <option value="">Please Select</option>
    <option <?php echo selected('paying_cost_living', 'Self'); ?>>Self</option>
    <option <?php echo selected('paying_cost_living', 'Family'); ?>>Family</option>
    <option <?php echo selected('paying_cost_living', 'Sponsor'); ?>>Sponsor</option>
    <option <?php echo selected('paying_cost_living', 'Loan'); ?>>Loan</option>
  </select>

  <label>Who will be paying travel expenses?  </label>
  <select name="paying_travel_expenses" required>
    <option value="">Please Select</option>
    <option <?php echo selected('paying_travel_expenses', 'Self'); ?>>Self</option>
    <option <?php echo selected('paying_travel_expenses', 'Family'); ?>>Family</option>
    <option <?php echo selected('paying_travel_expenses', 'Sponsor'); ?>>Sponsor</option>
    <option <?php echo selected('paying_travel_expenses', 'Loan'); ?>>Loan</option>
  </select>

  <label>Do you have any suspended/criminal history?  </label>
  <div class="radio-group">
    <label><input type="radio" name="criminal_history" value="Yes" required <?php echo checked('criminal_history', 'Yes'); ?>> Yes</label>
    <label><input type="radio" name="criminal_history" value="No" required <?php echo checked('criminal_history', 'No'); ?>> No</label>
  </div>

  <label>Do you have any disability?  </label>
  <div class="radio-group">
    <label><input type="radio" name="disability" value="Yes" required <?php echo checked('disability', 'Yes'); ?>> Yes</label>
    <label><input type="radio" name="disability" value="No" required <?php echo checked('disability', 'No'); ?>> No</label>
  </div>

  <label>Names of Emergency Contact  </label>
  <div class="inline-inputs">
    <input type="text" name="emergency_first_name" placeholder="First Name" required value="<?php echo htmlspecialchars($studentData['emergency_first_name'] ?? ''); ?>">
    <input type="text" name="emergency_last_name" placeholder="Last Name" required value="<?php echo htmlspecialchars($studentData['emergency_last_name'] ?? ''); ?>">
  </div>

  <label>Email of Emergency Contact  </label>
  <input type="email" name="emergency_email" placeholder="example@example.com" required value="<?php echo htmlspecialchars($studentData['emergency_email'] ?? ''); ?>">

  <div class="form-buttons">
    <button type="button" class="prev-btn" data-prev="2">Previous</button>
    <button type="button" class="next-btn" data-next="4">Save & Next</button>
  </div>
</div>

 <!-- Step 4: Emergency Contact & Previous Institution -->
<div class="form-step" id="step4">
  <h3>Step 4: Emergency Contact & Previous Institution</h3>

  <div class="form-group" style="margin-bottom: 20px;">
  <label for="emergency_phone_number_input">Phone Number of Emergency Contact</label>
  <div class="inline-inputs">
    <input
      type="tel"
      id="emergency_phone_number_input"
      class="form-control"
      placeholder="Emergency Contact"
      autocomplete="tel"
      style="max-width: 300px;"
      value="<?php echo htmlspecialchars($studentData['emergency_full_phone'] ?? ''); ?>"
    />
    <input type="hidden" id="emergency_area_code" name="emergency_area_code" value="<?php echo htmlspecialchars($studentData['emergency_area_code'] ?? ''); ?>">
    <input type="hidden" id="emergency_phone_number" name="emergency_phone_number" value="<?php echo htmlspecialchars($studentData['emergency_phone_number'] ?? ''); ?>">
  </div>
</div>

  <label>Relationship  </label>
  <input type="text" name="emergency_relationship" required value="<?php echo htmlspecialchars($studentData['emergency_relationship'] ?? ''); ?>">

  <label>Is the emergency contact address the same as the applicant?  </label>
  <div class="radio-group">
    <label><input type="radio" name="emergency_same_address" value="Yes" required <?php echo checked('emergency_same_address', 'Yes'); ?>> Yes</label>
    <label><input type="radio" name="emergency_same_address" value="No" required <?php echo checked('emergency_same_address', 'No'); ?>> No</label>
  </div>

  <label>Intended Study Level/What educational level are you aiming for?  </label>
  <div class="checkbox-group">
    <label><input type="checkbox" name="intended_study_level[]" value="PhD" <?php echo isChecked('intended_study_level', 'PhD'); ?>> PhD</label>
    <label><input type="checkbox" name="intended_study_level[]" value="Masters" <?php echo isChecked('intended_study_level', 'Masters'); ?>> Masters</label>
    <label><input type="checkbox" name="intended_study_level[]" value="Bachelor" <?php echo isChecked('intended_study_level', 'Bachelor'); ?>> Bachelor</label>
  </div>

  <label>Previous Institution Details/Name of Institution  </label>
  <input type="text" name="previous_institution_name" required value="<?php echo htmlspecialchars($studentData['previous_institution_name'] ?? ''); ?>">

  <label>Street of Institution  </label>
  <input type="text" name="previous_institution_street" required value="<?php echo htmlspecialchars($studentData['previous_institution_street'] ?? ''); ?>">

  <label>City of Institution  </label>
  <input type="text" name="previous_institution_city" required value="<?php echo htmlspecialchars($studentData['previous_institution_city'] ?? ''); ?>">

  <label>Province of Institution  </label>
  <input type="text" name="previous_institution_province" required value="<?php echo htmlspecialchars($studentData['previous_institution_province'] ?? ''); ?>">

  <label>Country of Institution  </label>
  <input type="text" name="previous_institution_country" required value="<?php echo htmlspecialchars($studentData['previous_institution_country'] ?? ''); ?>">

  <label>Post Code</label>
  <input type="text" name="previous_institution_post_code" value="<?php echo htmlspecialchars($studentData['previous_institution_post_code'] ?? ''); ?>">

  <label>Language of Instruction  </label>
  <div class="radio-group">
    <label><input type="radio" name="language_of_instruction" value="English" required <?php echo checked('language_of_instruction', 'English'); ?>> ENGLISH</label>
    <label><input type="radio" name="language_of_instruction" value="French" required <?php echo checked('language_of_instruction', 'French'); ?>> FRENCH</label>
  </div>

  <div class="form-buttons">
    <button type="button" class="prev-btn" data-prev="3">Previous</button>
    <button type="button" class="next-btn" data-next="5">Save & Next</button>
  </div>
</div>
<!-- Step 5: Previous Studies & Documents -->
<div class="form-step" id="step5">
  <h3>Step 5: Previous Studies & Documents</h3>

  <label>When did the applicant start previous studies?</label>
  <input type="date" name="previous_study_start" required value="<?= htmlspecialchars($studentData['previous_study_start'] ?? '') ?>">

  <label>When did the applicant graduate from previous studies?</label>
  <input type="date" name="previous_study_graduation" required value="<?= htmlspecialchars($studentData['previous_study_graduation'] ?? '') ?>">

  <label>Additional secondary school attendance?</label>
  <div class="radio-group">
    <label><input type="radio" name="additional_secondary_school" value="Yes" required <?= checked('additional_secondary_school', 'Yes') ?>> Yes</label>
    <label><input type="radio" name="additional_secondary_school" value="No" required <?= checked('additional_secondary_school', 'No') ?>> No</label>
  </div>

  <label>Study Gap – Is there a gap of 3 months or more?</label>
  <div class="radio-group">
    <label><input type="radio" name="study_gap" value="Yes" required <?= checked('study_gap', 'Yes') ?>> Yes</label>
    <label><input type="radio" name="study_gap" value="No" required <?= checked('study_gap', 'No') ?>> No</label>
  </div>

  <label>Has the student attended any post-secondary institutions?</label>
  <div class="radio-group">
    <label><input type="radio" name="post_secondary" value="Yes" required <?= checked('post_secondary', 'Yes') ?>> Yes</label>
    <label><input type="radio" name="post_secondary" value="No" required <?= checked('post_secondary', 'No') ?>> No</label>
  </div>

  <label>Do you have a passport?</label>
  <div class="radio-group">
    <label><input type="radio" name="passport" value="Yes" required <?= checked('passport', 'Yes') ?>> Yes</label>
    <label><input type="radio" name="passport" value="No" required <?= checked('passport', 'No') ?>> No</label>
  </div>

  <label>Have you ever had a visa rejection?</label>
  <div class="radio-group">
    <label><input type="radio" name="visa_rejection" value="Yes" required <?= checked('visa_rejection', 'Yes') ?>> Yes</label>
    <label><input type="radio" name="visa_rejection" value="No" required <?= checked('visa_rejection', 'No') ?>> No</label>
  </div>

  <!-- Degree and Transcripts -->
  <label>Degree and Transcripts</label>
  <input type="file" id="degree_transcripts_file">
  <input type="hidden" name="degree_transcripts" value="<?= htmlspecialchars($studentData['degree_transcripts'] ?? '') ?>">
  <div id="degree_transcripts_view">
    <?php if (!empty($studentData['degree_transcripts'])): ?>
      <a href="<?= htmlspecialchars($studentData['degree_transcripts']) ?>" target="_blank">View File</a>
    <?php endif; ?>
  </div>

  <!-- High School Degree -->
  <label>High School Degree</label>
  <input type="file" id="high_school_degree_file">
  <input type="hidden" name="high_school_degree" value="<?= htmlspecialchars($studentData['high_school_degree'] ?? '') ?>">
  <div id="high_school_degree_view">
    <?php if (!empty($studentData['high_school_degree'])): ?>
      <a href="<?= htmlspecialchars($studentData['high_school_degree']) ?>" target="_blank">View File</a>
    <?php endif; ?>
  </div>

  <!-- Valid Passport -->
  <label>Valid Passport</label>
  <input type="file" id="valid_passport_file">
  <input type="hidden" name="valid_passport" value="<?= htmlspecialchars($studentData['valid_passport'] ?? '') ?>">
  <div id="valid_passport_view">
    <?php if (!empty($studentData['valid_passport'])): ?>
      <a href="<?= htmlspecialchars($studentData['valid_passport']) ?>" target="_blank">View File</a>
    <?php endif; ?>
  </div>

  <div class="form-buttons">
    <button type="button" class="prev-btn" data-prev="4">Previous</button>
    <button type="button" class="next-btn" data-next="6">Save & Next</button>
  </div>
</div>
<!-- Step 6: Uploads & Additional Details -->
<div class="form-step" id="step6">
  <h3>Step 6: Uploads & Additional Details</h3>

  <!-- Recommendation Letters -->
  <label>Recommendation Letters</label>
  <input type="file" id="recommendation_letters_file">
  <input type="hidden" name="recommendation_letters" value="<?= htmlspecialchars($studentData['recommendation_letters'] ?? '') ?>">
  <div id="recommendation_letters_view">
    <?php if (!empty($studentData['recommendation_letters'])): ?>
      <a href="<?= htmlspecialchars($studentData['recommendation_letters']) ?>" target="_blank">View File</a>
    <?php endif; ?>
  </div>

  <!-- Personal Statement -->
  <label>Personal Statement</label>
  <input type="file" id="personal_statement_file">
  <input type="hidden" name="personal_statement" value="<?= htmlspecialchars($studentData['personal_statement'] ?? '') ?>">
  <div id="personal_statement_view">
    <?php if (!empty($studentData['personal_statement'])): ?>
      <a href="<?= htmlspecialchars($studentData['personal_statement']) ?>" target="_blank">View File</a>
    <?php endif; ?>
  </div>

  <!-- CV / Resume -->
  <label>CV / Resume</label>
  <input type="file" id="cv_resume_file" required>
  <input type="hidden" name="cv_resume" value="<?= htmlspecialchars($studentData['cv_resume'] ?? '') ?>">
  <div id="cv_resume_view">
    <?php if (!empty($studentData['cv_resume'])): ?>
      <a href="<?= htmlspecialchars($studentData['cv_resume']) ?>" target="_blank">View File</a>
    <?php endif; ?>
  </div>

  <!-- English Certificate -->
  <label>English Certificate</label>
  <input type="file" id="english_certificate_file" required>
  <input type="hidden" name="english_certificate" value="<?= htmlspecialchars($studentData['english_certificate'] ?? '') ?>">
  <div id="english_certificate_view">
    <?php if (!empty($studentData['english_certificate'])): ?>
      <a href="<?= htmlspecialchars($studentData['english_certificate']) ?>" target="_blank">View File</a>
      <small style="display:block; margin-bottom:10px;">The one from University is acceptable</small>
    <?php endif; ?>
  </div>

  <!-- Birth Certificate / National ID -->
  <label>Birth Certificate or National ID</label>
  <input type="file" id="birth_certificate_file" required>
  <input type="hidden" name="birth_certificate" value="<?= htmlspecialchars($studentData['birth_certificate'] ?? '') ?>">
  <div id="birth_certificate_view">
    <?php if (!empty($studentData['birth_certificate'])): ?>
      <a href="<?= htmlspecialchars($studentData['birth_certificate']) ?>" target="_blank">View File</a>
    <?php endif; ?>
  </div>

  <!-- Payment Proof -->
  <label>Payment Proof</label>
  <input type="file" id="payment_proof_file" required>
  <input type="hidden" name="payment_proof" value="<?= htmlspecialchars($studentData['payment_proof'] ?? '') ?>">
  <div id="payment_proof_view">
    <?php if (!empty($studentData['payment_proof'])): ?>
      <a href="<?= htmlspecialchars($studentData['payment_proof']) ?>" target="_blank">View File</a>
      <small style="display:block; margin-bottom:10px;">150$ in USA, 450 CAD in Canada, 250$ in Europe</small>
    <?php endif; ?>
  </div>

  <!-- Dynamic agents -->
  <label>Agent (Select)</label>
  <select id="agent_select" class="select2-country">
    <option value="">-- Select Agent --</option>
    <?php foreach ($agents as $agent): ?>
      <option 
        value="<?= htmlspecialchars($agent['first_name']) ?>|<?= htmlspecialchars($agent['last_name']) ?>|<?= htmlspecialchars($agent['email']) ?>"
        <?= (isset($studentData['agent_email']) && $studentData['agent_email'] == $agent['email']) ? 'selected' : '' ?>>
        <?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?> (<?= htmlspecialchars($agent['email']) ?>)
      </option>
    <?php endforeach; ?>
  </select>

  <label>Agent Names</label>
  <div class="inline-inputs">
    <input type="text" id="agent_first_name" name="agent_first_name" placeholder="First Name" value="<?= htmlspecialchars($studentData['agent_first_name'] ?? '') ?>">
    <input type="text" id="agent_last_name" name="agent_last_name" placeholder="Last Name" value="<?= htmlspecialchars($studentData['agent_last_name'] ?? '') ?>">
  </div>

  <label>Agent Email</label>
  <input type="email" id="agent_email" name="agent_email" placeholder="example@example.com" value="<?= htmlspecialchars($studentData['agent_email'] ?? '') ?>">

  <label>Comments</label>
  <textarea name="comments" placeholder="Add any additional information..."><?= htmlspecialchars($studentData['comments'] ?? '') ?></textarea>

  <!-- Progress Bar (shown during final submit) -->
  <div id="progress-wrapper" style="display:none; margin-top:20px;">
    <label>Uploading... Please wait</label>
    <div style="background:#ddd; border-radius:5px; height:20px; overflow:hidden;">
      <div id="progress-bar" style="background:#0c3c78; width:0%; height:100%; transition: width 0.4s;"></div>
    </div>
  </div>

  <!-- Final Navigation Buttons -->
  <div class="form-buttons" style="margin-top:20px;">
    <button type="button" class="prev-btn" data-prev="5">Previous</button>
    <button type="submit" class="submit-btn">Submit Application</button>
  </div>
</div> <!-- end of step6 -->

  </form>
</div>
<!-- Chat Bubble -->
<div id="chat-bubble">
  <button onclick="openChatWindow()">💬 Chat with Us</button>
</div>

<!-- Chat Bubble -->
<div id="chat-bubble">
  <button>
    💬 Chat with Us <span id="chat-badge" style="display:none;">1</span>
  </button>
</div>

<!-- Chat Window -->
<div id="chat-window">
  <div class="chat-header">
    Chat Support
    <span style="cursor:pointer;" onclick="$('#chat-window').hide();">✖</span>
  </div>
  <div class="chat-messages" id="chat-messages">
    <!-- Chat messages will appear here -->
  </div>
  <div class="chat-input">
    <textarea id="chat-input" placeholder="Type your message..."></textarea>
    <button onclick="sendChatMessage();">Send</button>
  </div>
</div>

<!-- Optional: Only include script.js if you need its content -->
<script src="script.js"></script> 

<!-- Flatpickr Date Picker -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    flatpickr("input[type='date']", {
      dateFormat: "Y-m-d",
      altInput: true,
      altFormat: "F j, Y",
      maxDate: "today",
      defaultDate: null, // ✅ No default prefilled date
      allowInput: true
    });
  });
</script>
<!-- intl-tel-input JS + Utils -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const phoneInput = document.querySelector("#phone_number");
  const areaCodeInput = document.querySelector("#area_code");
  const nationalInput = document.querySelector("#phone_number_cleaned");

  if (!phoneInput) return;

  const iti = window.intlTelInput(phoneInput, {
    initialCountry: "auto",
    separateDialCode: true, // ✅ Show +code outside of input
    preferredCountries: ["rw", "ke", "ug", "us"],
    geoIpLookup: function (callback) {
      fetch("https://ipapi.co/json")
        .then((res) => res.json())
        .then((data) => callback(data.country_code || "rw"))
        .catch(() => callback("rw"));
    },
    utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.19/build/js/utils.js"
  });

  function updatePhoneFields() {
    if (iti && typeof intlTelInputUtils !== "undefined" && iti.isValidNumber()) {
      const fullNumber = iti.getNumber();
      const countryData = iti.getSelectedCountryData();
      areaCodeInput.value = `+${countryData.dialCode}`;
      nationalInput.value = fullNumber.replace(`+${countryData.dialCode}`, '').trim();
    }
  }

  phoneInput.addEventListener("blur", updatePhoneFields);
  phoneInput.addEventListener("change", updatePhoneFields);
  phoneInput.addEventListener("keyup", updatePhoneFields);

  const form = phoneInput.closest("form");
  if (form) {
    form.addEventListener("submit", function () {
      updatePhoneFields();
    });
  }

  setTimeout(updatePhoneFields, 500);
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  const emergencyPhoneInput = document.querySelector("#emergency_phone_number_input");
  const emergencyAreaCode = document.querySelector("#emergency_area_code");
  const emergencyPhoneClean = document.querySelector("#emergency_phone_number");

  if (!emergencyPhoneInput) return;

  const itiEmergency = window.intlTelInput(emergencyPhoneInput, {
    initialCountry: "auto",
    separateDialCode: true,
    preferredCountries: ["rw", "ke", "ug", "us"],
    geoIpLookup: function (callback) {
      fetch("https://ipapi.co/json")
        .then((res) => res.json())
        .then((data) => callback(data.country_code || "rw"))
        .catch(() => callback("rw"));
    },
    utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.19/build/js/utils.js"
  });

  function updateEmergencyPhoneFields() {
    if (itiEmergency && typeof intlTelInputUtils !== "undefined" && itiEmergency.isValidNumber()) {
      const fullNumber = itiEmergency.getNumber();
      const countryData = itiEmergency.getSelectedCountryData();
      emergencyAreaCode.value = `+${countryData.dialCode}`;
      emergencyPhoneClean.value = fullNumber.replace(`+${countryData.dialCode}`, '').trim();
    }
  }

  emergencyPhoneInput.addEventListener("blur", updateEmergencyPhoneFields);
  emergencyPhoneInput.addEventListener("change", updateEmergencyPhoneFields);
  emergencyPhoneInput.addEventListener("keyup", updateEmergencyPhoneFields);

  const form = emergencyPhoneInput.closest("form");
  if (form) {
    form.addEventListener("submit", function () {
      updateEmergencyPhoneFields();
    });
  }

  setTimeout(updateEmergencyPhoneFields, 500);
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const agentSelect = document.getElementById('agent_select');
  const agentFirstName = document.getElementById('agent_first_name');
  const agentLastName = document.getElementById('agent_last_name');
  const agentEmail = document.getElementById('agent_email');

  if (agentSelect) {
    agentSelect.addEventListener('change', function() {
      const parts = this.value.split('|');
      agentFirstName.value = parts[0] || '';
      agentLastName.value = parts[1] || '';
      agentEmail.value = parts[2] || '';
    });
  }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

  function setupLiveUpload(fieldName) {
    const fileInput = document.querySelector(`#${fieldName}_file`);
    const hiddenInput = document.querySelector(`input[name="${fieldName}"]`);
    const viewLink = document.getElementById(`${fieldName}_view`);

    if (!fileInput || !hiddenInput) return;

    fileInput.addEventListener("change", function () {
      const file = fileInput.files[0];
      if (!file) return;

      const formData = new FormData();
      formData.append("file", file);
      formData.append("field", fieldName);

      fileInput.disabled = true;
      fileInput.style.opacity = "0.6";

      fetch("upload_file.php", {
        method: "POST",
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        fileInput.disabled = false;
        fileInput.style.opacity = "1";

        if (data.status === "success") {
          hiddenInput.value = data.file_path;

          if (viewLink) {
            viewLink.innerHTML = `<a href="${data.file_path}" target="_blank">View File</a>`;
          }

        } else {
          alert("Upload failed: " + (data.message || "Unknown error"));
        }
      })
      .catch(error => {
        fileInput.disabled = false;
        fileInput.style.opacity = "1";
        alert("Upload error: " + error);
      });
    });
  }

  // STEP 5 uploads
  setupLiveUpload('degree_transcripts');
  setupLiveUpload('high_school_degree');
  setupLiveUpload('valid_passport');

  // STEP 6 uploads
  setupLiveUpload('recommendation_letters');
  setupLiveUpload('personal_statement');
  setupLiveUpload('cv_resume');
  setupLiveUpload('english_certificate');
  setupLiveUpload('birth_certificate');
  setupLiveUpload('payment_proof');

});
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('select[name="bachelor_program"], select[name="masters_program"], select[name="phd_program"]').select2({
    placeholder: "Please Select Program",
    width: '100%'
});

});
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$('#chat-bubble button').on('click', function() {
  $('#chat-window').toggle();
  $('#chat-badge').hide();
  loadChatMessages();
});

function loadChatMessages() {
  $.get('load_chat.php', { user_id: '<?= htmlspecialchars($userId) ?>' }, function(data) {
    let oldContent = $('#chat-messages').html();
    $('#chat-messages').html(data);
    if ($('#chat-messages').html() != oldContent && !$('#chat-window').is(':visible')) {
      $('#chat-badge').show();
    }
    $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
  });
}

function sendChatMessage() {
  var message = $('#chat-input').val().trim();
  if (!message) return;

  $.post('send_chat.php', { user_id: '<?= htmlspecialchars($userId) ?>', message: message }, function(response) {
    $('#chat-input').val('');
    loadChatMessages();
  });
}

// Auto-refresh every 5 seconds
setInterval(function() {
  loadChatMessages();
}, 5000);
</script>
</body>
</html>
