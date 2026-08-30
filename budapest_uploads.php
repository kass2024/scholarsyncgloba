<?php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['user_id'])) {
  $_SESSION['user_id'] = 'user-' . time() . '-' . rand(1000, 9999);
}
$userId = $_SESSION['user_id'];

// Load previous data if available
$stmt = $conn->prepare("SELECT * FROM student_applications WHERE user_id = ?");
$stmt->bind_param("s", $userId);
$stmt->execute();
$studentData = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>8-Days Winter School in Budapest – Document Upload</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f5f7fb;
      padding: 40px 20px;
    }
    .form-container {
      background: #fff;
      max-width: 650px;
      margin: auto;
      padding: 30px 40px;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    h2 {
      color: #0c3c78;
      text-align: center;
    }
    p.intro {
      text-align: center;
      font-size: 1rem;
      color: #444;
      margin-bottom: 25px;
    }
    label {
      display: block;
      font-weight: 500;
      margin-top: 15px;
    }
    input[type="file"] {
      width: 100%;
      margin-top: 5px;
      padding: 10px;
      border-radius: 8px;
      border: 2px dashed #bbb;
      background: #fafafa;
    }
    .form-buttons {
      text-align: center;
      margin-top: 25px;
    }
    .form-buttons button {
      background-color: #0c3c78;
      color: #fff;
      padding: 12px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 1rem;
    }
    .form-buttons button:hover {
      background-color: #092a5c;
    }
  </style>
</head>
<body>
<div class="form-container">
  <h2>8-Days Winter School – Budapest</h2>
  <p class="intro">
    Please upload the following required documents to complete your application for the 8-Days Budapest Winter School.
  </p>

  <form id="budapestForm" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">
    <input type="hidden" name="step" value="budapest_uploads">
    <input type="hidden" name="submitted" value="1">

    <!-- Valid Passport -->
    <label>1. Valid Passport</label>
    <input type="file" id="valid_passport_file" required>
    <input type="hidden" name="valid_passport" value="<?= htmlspecialchars($studentData['valid_passport'] ?? '') ?>">
    <div id="valid_passport_view">
      <?php if (!empty($studentData['valid_passport'])): ?>
        <a href="<?= htmlspecialchars($studentData['valid_passport']) ?>" target="_blank">View File</a>
      <?php endif; ?>
    </div>

    <!-- Degree Certificate -->
    <label>2. Degree Certificate</label>
    <input type="file" id="degree_transcripts_file" required>
    <input type="hidden" name="degree_transcripts" value="<?= htmlspecialchars($studentData['degree_transcripts'] ?? '') ?>">
    <div id="degree_transcripts_view">
      <?php if (!empty($studentData['degree_transcripts'])): ?>
        <a href="<?= htmlspecialchars($studentData['degree_transcripts']) ?>" target="_blank">View File</a>
      <?php endif; ?>
    </div>

    <!-- Reports or Transcripts -->
    <label>3. Reports or Transcripts</label>
    <input type="file" id="reports_file">
    <input type="hidden" name="reports" value="<?= htmlspecialchars($studentData['reports'] ?? '') ?>">
    <div id="reports_view">
      <?php if (!empty($studentData['reports'])): ?>
        <a href="<?= htmlspecialchars($studentData['reports']) ?>" target="_blank">View File</a>
      <?php endif; ?>
    </div>

    <!-- Curriculum Vitae -->
    <label>4. Curriculum Vitae (CV)</label>
    <input type="file" id="cv_resume_file" required>
    <input type="hidden" name="cv_resume" value="<?= htmlspecialchars($studentData['cv_resume'] ?? '') ?>">
    <div id="cv_resume_view">
      <?php if (!empty($studentData['cv_resume'])): ?>
        <a href="<?= htmlspecialchars($studentData['cv_resume']) ?>" target="_blank">View File</a>
      <?php endif; ?>
    </div>

    <!-- Passport-Size Photo -->
    <label>5. Passport-Size Photo</label>
    <input type="file" id="passport_photo_file" required>
    <input type="hidden" name="passport_photo" value="<?= htmlspecialchars($studentData['passport_photo'] ?? '') ?>">
    <div id="passport_photo_view">
      <?php if (!empty($studentData['passport_photo'])): ?>
        <a href="<?= htmlspecialchars($studentData['passport_photo']) ?>" target="_blank">View File</a>
      <?php endif; ?>
    </div>

    <!-- Payment Proof -->
    <label>6. Payment Proof of Application Fees (250 USD)</label>
    <input type="file" id="payment_proof_file" required>
    <input type="hidden" name="payment_proof" value="<?= htmlspecialchars($studentData['payment_proof'] ?? '') ?>">
    <div id="payment_proof_view">
      <?php if (!empty($studentData['payment_proof'])): ?>
        <a href="<?= htmlspecialchars($studentData['payment_proof']) ?>" target="_blank">View File</a>
      <?php endif; ?>
    </div>

    <div class="form-buttons">
      <button type="submit" class="submit-btn">Submit Documents</button>
    </div>

    <div id="progress-wrapper" style="display:none; margin-top:20px;">
      <label>Uploading... Please wait</label>
      <div style="background:#ddd; border-radius:5px; height:20px; overflow:hidden;">
        <div id="progress-bar" style="background:#0c3c78; width:0%; height:100%; transition: width 0.4s;"></div>
      </div>
    </div>
  </form>
</div>

<!-- Include JS setupLiveUpload from main form -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
<?= file_get_contents('inline_js_upload_logic.js') ?>
// If you don’t have that separate, copy over your setupLiveUpload(...) code block here
document.addEventListener("DOMContentLoaded", function () {
  setupLiveUpload('valid_passport');
  setupLiveUpload('degree_transcripts');
  setupLiveUpload('reports');
  setupLiveUpload('cv_resume');
  setupLiveUpload('passport_photo');
  setupLiveUpload('payment_proof');
});
</script>
<script>
$(document).ready(function () {
  // Helper to get URL parameter
  function getParam(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
  }

  const universityId = getParam("university_id");
  const regionId = getParam("region_id");

  // Check for 78 and 4
  if (universityId === "78" && regionId === "4") {
    // Hide everything initially
    $("form#budapestForm").children().hide();

    // Create dynamic intro section
    const introNote = `
      <div id="budapestIntro" style="text-align:center; padding:20px;">
        <h3 style="color:#0c3c78;">Welcome to the Budapest Winter School (Special Region)</h3>
        <p style="font-size:1rem; color:#444;">
          As part of your exclusive selection for the Budapest 8-Days Winter School, 
          you only need to upload your <b>Passport-size Photo</b> and <b>Proof of Payment</b> below 
          to finalize your submission.
        </p>
        <button id="continueUploads" style="background:#0c3c78;color:#fff;padding:10px 15px;border:none;border-radius:5px;cursor:pointer;">
          Continue to Upload
        </button>
      </div>
    `;
    $(".form-container").prepend(introNote);

    // When "Continue to Upload" is clicked
    $(document).on("click", "#continueUploads", function () {
      $("#budapestIntro").fadeOut(400, function () {
        // Show only steps 5 & 6
        $("label:contains('5. Passport-Size Photo'), #passport_photo_file, #passport_photo_view, [name='passport_photo']").fadeIn();
        $("label:contains('6. Payment Proof'), #payment_proof_file, #payment_proof_view, [name='payment_proof']").fadeIn();
        $(".form-buttons").fadeIn();
      });
    });

    // Optionally trigger AJAX fetch to confirm eligibility
    $.ajax({
      url: "checkEligibility.php",
      type: "POST",
      data: { university_id: universityId, region_id: regionId },
      success: function (res) {
        console.log("Eligibility confirmed:", res);
      },
      error: function () {
        console.warn("Eligibility check failed or not implemented.");
      }
    });
  }
});
</script>

</body>
</html>
