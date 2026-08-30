<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  $_SESSION['user_id'] = 'user-' . time() . '-' . rand(1000, 9999);
}
$userId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Application Form - USA</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="form-container">
  <h2>Student Registration Form (USA, Canada, Europe, Asia)</h2>
  <form id="applicationForm" enctype="multipart/form-data">
    <input type="hidden" name="user_id" value="<?php echo $userId; ?>">

    <!-- Step 1: Personal Information -->
    <div class="form-step active" id="step1">
      <h3>Step 1: Personal Information</h3>
      <label>Student Name *</label>
      <div class="inline-inputs">
        <input type="text" name="first_name" placeholder="First Name" required>
        <input type="text" name="last_name" placeholder="Last Name" required>
      </div>
      <label>Student Email *</label>
      <input type="email" name="email" placeholder="example@example.com" required>
      <label>Phone Number *</label>
      <div class="inline-inputs">
        <input type="text" name="area_code" placeholder="Area Code" required style="width: 80px;">
        <input type="text" name="phone_number" placeholder="Phone Number" required>
      </div>
      <label>Gender *</label>
      <div class="radio-group">
        <label><input type="radio" name="gender" value="Male" required> Male</label>
        <label><input type="radio" name="gender" value="Female" required> Female</label>
      </div>
      <label>Country of Birth *</label>
      <input type="text" name="country_of_birth" required>
      <label>Nationality/Citizen of *</label>
      <input type="text" name="nationality" required>
      <label>Second Nationality</label>
      <input type="text" name="second_nationality">
      <label>City of Birth *</label>
      <input type="text" name="city_of_birth" required>
      <label>Date of Birth *</label>
      <input type="date" name="dob" required>
      <label>Address *</label>
      <input type="text" name="address_line1" placeholder="Street Address" required>
      <input type="text" name="address_line2" placeholder="Street Address Line 2">
      <div class="inline-inputs">
        <input type="text" name="city" placeholder="City" required>
        <input type="text" name="state_province" placeholder="State/Province" required>
      </div>
      <input type="text" name="postal_code" placeholder="Postal/Zip Code" required>
      <label>Application Date *</label>
      <input type="date" name="application_date" required>
      <div class="form-buttons">
        <button type="button" class="next-btn" data-next="2">Save & Next</button>
      </div>
    </div>

    <!-- Step 2: Program and Destination Info -->
    <div class="form-step" id="step2">
      <h3>Step 2: Program and Destination</h3>
      <label>Bachelor Program</label>
      <select name="bachelor_program">
        <option value="">Please Select</option>
      </select>
      <label>Masters Program</label>
      <select name="masters_program">
        <option value="">Please Select</option>
      </select>
      <label>PhD Program</label>
      <select name="phd_program">
        <option value="">Please Select</option>
      </select>
      <label>Select Destination *</label>
      <div class="checkbox-group">
        <label><input type="checkbox" name="destination[]" value="USA"> USA</label>
        <label><input type="checkbox" name="destination[]" value="Canada"> Canada</label>
        <label><input type="checkbox" name="destination[]" value="Europe"> Europe</label>
      </div>
      <label>If other, please specify:</label>
      <textarea name="other_destination"></textarea>
      <div class="form-buttons">
        <button type="button" class="prev-btn" data-prev="1">Previous</button>
        <button type="button" class="next-btn" data-next="3">Save & Next</button>
      </div>
    </div>

    <!-- Step 3: Financial & Background -->
<div class="form-step" id="step3">
  <h3>Step 3: Financial & Background</h3>

  <label>Select Destination *</label>
  <div class="checkbox-group">
    <label><input type="checkbox" name="destination_loan[]" value="USA WHERE LOAN COVER TUITION FEES AND LIVING ALLOWANCE"> USA WHERE LOAN COVER TUITION FEES AND LIVING ALLOWANCE</label>
    <label><input type="checkbox" name="destination_loan[]" value="CANADA WHERE LOAN COVER TUITION FEES"> CANADA WHERE LOAN COVER TUITION FEES</label>
    <label><input type="checkbox" name="destination_loan[]" value="EUROPE WHERE LOAN COVER TUITION FEES"> EUROPE WHERE LOAN COVER TUITION FEES</label>
  </div>

  <label>If you select other, please specify:</label>
  <textarea name="other_destination_loan"></textarea>

  <label>Who will be paying the tuition fees? *</label>
  <select name="paying_tuition_fees" required>
    <option value="">Please Select</option>
    <option>Self</option>
    <option>Family</option>
    <option>Sponsor</option>
    <option>Loan</option>
  </select>

  <label>Who will be paying the cost of living? *</label>
  <select name="paying_cost_living" required>
    <option value="">Please Select</option>
    <option>Self</option>
    <option>Family</option>
    <option>Sponsor</option>
    <option>Loan</option>
  </select>

  <label>Who will be paying travel expenses? *</label>
  <select name="paying_travel_expenses" required>
    <option value="">Please Select</option>
    <option>Self</option>
    <option>Family</option>
    <option>Sponsor</option>
    <option>Loan</option>
  </select>

  <label>Do you have any suspended/criminal history? *</label>
  <div class="radio-group">
    <label><input type="radio" name="criminal_history" value="Yes" required> Yes</label>
    <label><input type="radio" name="criminal_history" value="No" required> No</label>
  </div>

  <label>Do you have any disability? *</label>
  <div class="radio-group">
    <label><input type="radio" name="disability" value="Yes" required> Yes</label>
    <label><input type="radio" name="disability" value="No" required> No</label>
  </div>

  <label>Names of Emergency Contact *</label>
  <div class="inline-inputs">
    <input type="text" name="emergency_first_name" placeholder="First Name" required>
    <input type="text" name="emergency_last_name" placeholder="Last Name" required>
  </div>

  <label>Email of Emergency Contact *</label>
  <input type="email" name="emergency_email" placeholder="example@example.com" required>

  <div class="form-buttons">
    <button type="button" class="prev-btn" data-prev="2">Previous</button>
    <button type="button" class="next-btn" data-next="4">Save & Next</button>
  </div>
</div>


 <!-- Step 4: Emergency Contact & Previous Institution -->
<div class="form-step" id="step4">
  <h3>Step 4: Emergency Contact & Previous Institution</h3>

  <label>Phone Number of Emergency Contact *</label>
  <div class="inline-inputs">
    <input type="text" name="emergency_area_code" placeholder="Area Code" required style="width: 80px;">
    <input type="text" name="emergency_phone_number" placeholder="Phone Number" required>
  </div>

  <label>Relationship *</label>
  <input type="text" name="emergency_relationship" required>

  <label>Is the emergency contact address the same as the applicant? *</label>
  <div class="radio-group">
    <label><input type="radio" name="emergency_same_address" value="Yes" required> Yes</label>
    <label><input type="radio" name="emergency_same_address" value="No" required> No</label>
  </div>

  <label>Intended Study Level/What educational level are you aiming for? *</label>
  <div class="checkbox-group">
    <label><input type="checkbox" name="intended_study_level[]" value="PhD"> PhD</label>
    <label><input type="checkbox" name="intended_study_level[]" value="Masters"> Masters</label>
    <label><input type="checkbox" name="intended_study_level[]" value="Bachelor"> Bachelor</label>
  </div>

  <label>Previous Institution Details/Name of Institution *</label>
  <input type="text" name="previous_institution_name" required>

  <label>Street of Institution *</label>
  <input type="text" name="previous_institution_street" required>

  <label>City of Institution *</label>
  <input type="text" name="previous_institution_city" required>

  <label>Province of Institution *</label>
  <input type="text" name="previous_institution_province" required>

  <label>Country of Institution *</label>
  <input type="text" name="previous_institution_country" required>

  <label>Post Code</label>
  <input type="text" name="previous_institution_post_code">

  <label>Language of Instruction *</label>
  <div class="radio-group">
    <label><input type="radio" name="language_of_instruction" value="English" required> ENGLISH</label>
    <label><input type="radio" name="language_of_instruction" value="French" required> FRENCH</label>
  </div>

  <div class="form-buttons">
    <button type="button" class="prev-btn" data-prev="3">Previous</button>
    <button type="button" class="next-btn" data-next="5">Save & Next</button>
  </div>
</div>


  <!-- Step 5: Previous Studies & Documents -->
<div class="form-step" id="step5">
  <h3>Step 5: Previous Studies & Documents</h3>

  <label>When did the applicant start previous studies? *</label>
  <input type="date" name="previous_study_start" required>

  <label>When did the applicant graduate from previous studies? *</label>
  <input type="date" name="previous_study_graduation" required>

  <label>Additional secondary school attendance? *</label>
  <div class="radio-group">
    <label><input type="radio" name="additional_secondary_school" value="Yes" required> Yes</label>
    <label><input type="radio" name="additional_secondary_school" value="No" required> No</label>
  </div>

  <label>Study Gap – Is there a gap of 3 months or more? *</label>
  <div class="radio-group">
    <label><input type="radio" name="study_gap" value="Yes" required> Yes</label>
    <label><input type="radio" name="study_gap" value="No" required> No</label>
  </div>

  <label>Has the student attended any post-secondary institutions? *</label>
  <div class="radio-group">
    <label><input type="radio" name="post_secondary" value="Yes" required> Yes</label>
    <label><input type="radio" name="post_secondary" value="No" required> No</label>
  </div>

  <label>Do you have a passport? *</label>
  <div class="radio-group">
    <label><input type="radio" name="passport" value="Yes" required> Yes</label>
    <label><input type="radio" name="passport" value="No" required> No</label>
  </div>

  <label>Have you ever had a visa rejection? *</label>
  <div class="radio-group">
    <label><input type="radio" name="visa_rejection" value="Yes" required> Yes</label>
    <label><input type="radio" name="visa_rejection" value="No" required> No</label>
  </div>

  <label>Degree and Transcripts *</label>
  <input type="file" name="degree_transcripts" required>

  <label>High School Degree *</label>
  <input type="file" name="high_school_degree" required>

  <label>Valid Passport *</label>
  <input type="file" name="valid_passport" required>

  <div class="form-buttons">
    <button type="button" class="prev-btn" data-prev="4">Previous</button>
    <button type="button" class="next-btn" data-next="6">Save & Next</button>
  </div>
</div>

   <!-- Step 6: Uploads & Additional Details -->
<div class="form-step" id="step6">
  <h3>Step 6: Uploads & Additional Details</h3>

  <label>Recommendation Letters</label>
  <input type="file" name="recommendation_letters">

  <label>Personal Statement</label>
  <input type="file" name="personal_statement">

  <label>CV/RESUME *</label>
  <input type="file" name="cv_resume" required>

  <label>English Certificate - The one of University is acceptable *</label>
  <input type="file" name="english_certificate" required>

  <label>Birth Certificate or National ID *</label>
  <input type="file" name="birth_certificate" required>

  <label>Agent Names</label>
  <div class="inline-inputs">
    <input type="text" name="agent_first_name" placeholder="First Name">
    <input type="text" name="agent_last_name" placeholder="Last Name">
  </div>

  <label>Agent Email</label>
  <input type="email" name="agent_email" placeholder="example@example.com">

  <label>Payment proof for of 150$ in USA, 450CAD in Canada and 250$ in Europe *</label>
  <input type="file" name="payment_proof" required>

  <label>Comments</label>
  <textarea name="comments"></textarea>

  <div class="form-buttons">
    <button type="button" class="prev-btn" data-prev="5">Previous</button>
    <button type="submit" class="submit-btn">Submit Application</button>
  </div>
</div>

<script src="script.js"></script>
</body>
</html>
