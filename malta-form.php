<?php
require_once __DIR__ . '/includes/brand_logo.php';
$brandLogo = scholarsync_brand_logo_href(__DIR__);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Malta Campus Application Form</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f2f2f2;
      padding: 20px;
      margin: 0;
    }

    form {
      max-width: 900px;
      background: #fff;
      padding: 30px;
      margin: auto;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    h2 {
      color: #003366;
      border-bottom: 2px solid #003366;
      padding-bottom: 5px;
      margin-top: 30px;
      font-size: 1.3rem;
    }

    label {
      display: block;
      margin-top: 14px;
      font-weight: bold;
      font-size: 0.95rem;
    }

    input[type="text"], input[type="date"], input[type="email"], input[type="file"] {
      width: 100%;
      padding: 12px;
      margin-top: 6px;
      font-size: 1rem;
      border: 1px solid #ccc;
      border-radius: 4px;
    }

    input::placeholder {
      font-size: 1rem;
      color: #999;
    }

    .checkbox-group, .radio-group {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 8px;
    }

    .radio-group label, .checkbox-group label {
      font-weight: normal;
    }

    button {
      margin-top: 30px;
      padding: 14px 25px;
      background: #003366;
      color: white;
      border: none;
      border-radius: 5px;
      font-size: 1rem;
      cursor: pointer;
    }

    button:hover {
      background: #0052a3;
    }

    @media screen and (max-width: 600px) {
      form {
        padding: 20px;
      }

      h2 {
        font-size: 1.15rem;
      }

      input[type="text"], input[type="date"], input[type="email"], input[type="file"] {
        padding: 10px;
        font-size: 1rem;
      }

      button {
        width: 100%;
        padding: 14px;
      }
    }
 /* === Loading Overlay for PDF Generation === */
#loadingOverlay {
  position: fixed;
  top: 0; left: 0;
  width: 100vw; height: 100vh;
  background: rgba(255,255,255,0.96);
  z-index: 9999;
  display: none;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  text-align: center;
  padding: 20px;
  box-sizing: border-box;
}

#loadingOverlay h2 {
  color: #28a745;
  font-size: 1.5rem;
  margin-bottom: 12px;
}

#loadingOverlay p {
  color: #555;
  font-size: 1rem;
  margin-top: 8px;
}

#progressBarContainer {
  width: 80%;
  max-width: 400px;
  height: 18px;
  background: #e0e0e0;
  border-radius: 12px;
  overflow: hidden;
  margin: 20px auto;
}

#progressBar {
  height: 100%;
  width: 0%;
  background: #28a745;
  animation: fillBar 2.8s ease-in-out forwards;
}

@keyframes fillBar {
  from { width: 0%; }
  to   { width: 100%; }
}

@media screen and (max-width: 480px) {
  #loadingOverlay h2 {
    font-size: 1.3rem;
  }

  #loadingOverlay p {
    font-size: 0.95rem;
  }

  #progressBarContainer {
    width: 90%;
    height: 16px;
  }
}

select {
  width: 100%;
  padding: 12px;
  margin-top: 6px;
  font-size: 1rem;
  border: 1px solid #ccc;
  border-radius: 4px;
  background: white;
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%204%205'%3E%3Cpath%20fill='gray'%20d='M2%200L0%202h4L2%200zM2%205l2-2H0l2%202z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.7rem center;
  background-size: 12px 15px;
}

select:focus {
  outline: none;
  border-color: #003366;
  box-shadow: 0 0 3px rgba(0, 51, 102, 0.3);
}

@media screen and (max-width: 600px) {
  select {
    padding: 10px;
    font-size: 1rem;
  }
}

  </style>
</head>
<body>

<form method="POST" action="save-form-malta.php" enctype="multipart/form-data">

    <!-- Header: Logo + Title + Session -->
<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-bottom: 20px;">
  <!-- Logo -->
  <div style="flex: 1 1 200px;">
    <img src="<?php echo htmlspecialchars($brandLogo); ?>" alt="ScholarSync Global" style="max-width: 180px; height: auto;" width="180" height="180" decoding="async" />
  </div>

  <!-- Title -->
  <div style="flex: 2 1 300px; text-align: center;">
    <h1 style="font-size: 1.8rem; color: #003366; margin-bottom: 5px;">APPLICATION FORM</h1>
    <label style="font-weight: bold;">20<input type="text" name="session_from" maxlength="2" style="width: 30px; text-align: center;"> / 20<input type="text" name="session_to" maxlength="2" style="width: 30px; text-align: center;"> SESSION</label>
  </div>
</div>

  <h2>About the Program of Your Interest</h2>

  <label>Degree Program:</label>
  <div class="checkbox-group">
    <label><input type="checkbox" name="degree[]" value="Bachelor"> Bachelor's</label>
    <label><input type="checkbox" name="degree[]" value="Master"> Master's</label>
    <label><input type="checkbox" name="degree[]" value="Postgraduate"> Postgraduate</label>
  </div>

 <label>Specialty / Field of Study:</label>
<select name="specialty" required>
  <option value="">-- Select Field of Study --</option>
  <option value="Masters of Medicine">Master’s of Medicine</option>
  <option value="Bachelors in Management">Bachelor’s in Management</option>
  <option value="Masters in Management and Business Administration">Master’s in Management and Business Administration</option>
</select>


  <label>1st Alternative:</label>
  <input type="text" name="alt1">

  <label>2nd Alternative:</label>
  <input type="text" name="alt2">

  <label>Mode of Study:</label>
  <div class="checkbox-group">
    <label><input type="checkbox" name="mode[]" value="Online"> Online</label>
    <label><input type="checkbox" name="mode[]" value="Offline"> Offline</label>
    <label><input type="checkbox" name="mode[]" value="Not yet decided"> Not yet decided</label>
  </div>

  <h2>About the Applicant</h2>

  <label>Full Name:</label>
  <input type="text" name="surname" placeholder="Surname">
  <input type="text" name="name" placeholder="Name">
  <input type="text" name="middle" placeholder="Middle Name">

  <label>Gender:</label>
  <div class="radio-group">
    <label><input type="radio" name="gender" value="male"> Male</label>
    <label><input type="radio" name="gender" value="female"> Female</label>
    <label><input type="radio" name="gender" value="other"> Other</label>
  </div>

  <label>Marital Status:</label>
  <input type="text" name="marital_status">

  <label>Date of Birth:</label>
  <input type="date" name="dob">

  <label>Place of Birth:</label>
  <input type="text" name="birth_place">

  <label>Nationality:</label>
  <input type="text" name="nationality">

  <label>National Passport No.:</label>
  <input type="text" name="passport_no">

  <label>Date of Issue:</label>
  <input type="date" name="issue_date">

  <label>Date of Expiry:</label>
  <input type="date" name="expiry_date">

  <label>Permanent Address:</label>
  <input type="text" name="address" placeholder="City, Town, Street, House No.">

  <label>Contact Number:</label>
  <input type="text" name="contact_number">

  <label>Contact Email:</label>
  <input type="email" name="email">

  <label>Country of application for Maltese visa:</label>
  <input type="text" name="visa_country">

  <h2>Previous Education</h2>

  <label>School Name:</label>
  <input type="text" name="school_name">

  <label>School Address:</label>
  <input type="text" name="school_address">

  <label>Attended Since:</label>
  <input type="text" name="school_from">

  <label>Till:</label>
  <input type="text" name="school_to">

  <label>Received Certificate:</label>
  <input type="text" name="school_certificate">

  <h2>College / University (if any)</h2>

  <label>College Name:</label>
  <input type="text" name="college_name">

  <label>College Address:</label>
  <input type="text" name="college_address">

  <label>Attended Since:</label>
  <input type="text" name="college_from">

  <label>Till:</label>
  <input type="text" name="college_to">

  <label>Received Certificate:</label>
  <input type="text" name="college_certificate">

  <label>Have you ever studied in Malta before?</label>
  <div class="radio-group">
    <label><input type="radio" name="studied_malta" value="Yes"> Yes</label>
    <label><input type="radio" name="studied_malta" value="No"> No</label>
  </div>

  <label>If Yes, specify year, course, university name:</label>
  <input type="text" name="studied_malta_info">

  <label>Have you ever studied Malta language?</label>
  <div class="radio-group">
    <label><input type="radio" name="malta_lang" value="Yes"> Yes</label>
    <label><input type="radio" name="malta_lang" value="No"> No</label>
  </div>

  <label>If Yes, when and where:</label>
  <input type="text" name="malta_lang_info">
<h2>Applicant Should Attach the Following Documents</h2>

<label>1. Copy of Passport:</label>
<input type="file" name="passport_copy" accept=".pdf,.jpg,.jpeg,.png"><br><br>

<label>2. Educational Certificates (upload multiple):</label>
<input type="file" name="certificates[]" multiple accept=".pdf,.jpg,.jpeg,.png"><br><br>

<label>3. Transcript (optional):</label>
<input type="file" name="transcript" accept=".pdf,.jpg,.jpeg,.png"><br><br>

<label>I confirm that the information given in the form is correct.</label><br><br>

<label>DATE:</label>
<input type="date" name="signed_date">

<label>APPLICANT'S SIGNATURE (type your full name):</label>
<input type="text" name="signature">

  <button type="submit">Submit Form</button>
</form>
<div id="loadingOverlay">
  <h2>📄 Generating Your Application PDF...</h2>
  <div id="progressBarContainer">
    <div id="progressBar"></div>
  </div>
  <p style="font-size: 0.9rem; color: gray; margin-top: 10px;">Please wait. Do not refresh the page.</p>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
  const form = document.querySelector("form");
  form.addEventListener("submit", function () {
    document.getElementById("loadingOverlay").style.display = "flex";
  });
});
</script>

</body>
</html>
