<?php
require_once __DIR__ . '/db.php';
$agent_ids = [1, 2, 10,12,13,14,15,17,18,27,28,34,35,54,55,56,57,58,62];
$agent_query = "SELECT * FROM admins WHERE id IN (" . implode(",", $agent_ids) . ")";
$agent_result = mysqli_query($conn, $agent_query);
$agents = [];
if ($agent_result && mysqli_num_rows($agent_result) > 0) {
    while ($row = mysqli_fetch_assoc($agent_result)) {
        $agents[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal Turkey Application Form</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css" />
  <style>
    * {
      box-sizing: border-box;
    }
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 20px;
      background: #f9f9f9;
    }
    form {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      max-width: 960px;
      margin: auto;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h1 {
      text-align: center;
      color: #004080;
    }
    fieldset {
      margin-bottom: 20px;
      border: 1px solid #ddd;
      padding: 15px;
      border-radius: 5px;
    }
    legend {
      font-weight: bold;
      padding: 0 10px;
    }
    label {
      display: block;
      margin: 10px 0 5px;
    }
    input, select, textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    input[type="radio"] {
      width: auto;
    }
    .radio-group label {
      display: inline-block;
      margin-right: 20px;
    }
    button {
      padding: 10px 20px;
      background: #004080;
      color: #fff;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      margin-right: 10px;
    }
    button:hover {
      background: #003366;
    }
    .inline-inputs {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }
    @media (max-width: 600px) {
      .inline-inputs {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
<form action="submit.php" method="post" enctype="multipart/form-data" onsubmit="return validateForm()">
  <h1>Portal Turkey</h1>

  <fieldset>
    <legend>General Information</legend>
    <div class="radio-group">
      <label>Transfer student? *</label>
      <label><input type="radio" name="transfer_student" value="Yes" required> Yes</label>
      <label><input type="radio" name="transfer_student" value="No"> No</label>
    </div>
    <div class="radio-group">
      <label>Have T.C *</label>
      <label><input type="radio" name="have_tc" value="Yes" required> Yes</label>
      <label><input type="radio" name="have_tc" value="No"> No</label>
    </div>
    <div class="radio-group">
      <label>Blue Card *</label>
      <label><input type="radio" name="blue_card" value="Yes" required> Yes</label>
      <label><input type="radio" name="blue_card" value="No"> No</label>
    </div>
  </fieldset>

  <fieldset>
    <legend>Student Information</legend>
    <input type="text" name="first_name" placeholder="First Name *" required>
    <input type="text" name="last_name" placeholder="Last Name *" required>
    <input type="text" name="passport_no" placeholder="Passport No *" required>
    <label>Issue Date: <input type="text" class="flatpickr" name="issue_date" required></label>
    <label>Expiry Date: <input type="text" class="flatpickr" name="expiry_date" required></label>
    <select name="gender" required>
      <option value="">-Select Gender-</option>
      <option value="Male">Male</option>
      <option value="Female">Female</option>
      <option value="Other">Other</option>
    </select>
    <label>Date of Birth: <input type="text" class="flatpickr" name="dob" required></label>
    <label for="nationality">Nationality *</label>
<select name="nationality" id="nationality" class="select2-country" required></select>

<label for="residence_country">Country of Residence *</label>
<select name="residence_country" id="residence_country" class="select2-country" required></select>

    <input type="text" name="student_id" placeholder="Student ID">
  </fieldset>

  <fieldset>
    <legend>Student Communication</legend>
    <input type="email" name="email" placeholder="Email *" required>
    <label for="phone_number">Mobile *</label>
    <input type="tel" id="phone_number" name="phone_number_display" required>
    <input type="hidden" id="area_code" name="area_code">
    <input type="hidden" id="phone_number_cleaned" name="mobile">
    <input type="text" name="address" placeholder="Address Line 1">
    <input type="text" name="city" placeholder="City / District">
    <input type="text" name="province" placeholder="State / Province">
    <input type="text" name="postal_code" placeholder="Postal Code">
    <select name="country" class="select2-country"></select>
  </fieldset>

  <fieldset>
    <legend>Parents Information</legend>
    <input type="text" name="father_name" placeholder="Father Name *" required>
    <input type="text" name="father_mobile" placeholder="Father Mobile *" required>
    <input type="text" name="father_occupation" placeholder="Father Occupation">
    <input type="text" name="mother_name" placeholder="Mother Name">
  </fieldset>

  <fieldset>
    <legend>Upload Photo</legend>
    <input type="file" name="photo" accept="image/*">
  </fieldset>

  <fieldset>
    <legend>Documents Upload</legend>
    <label>Degree: <input type="file" name="degree" accept=".pdf,.jpg,.png" required></label>
    <label>Transcript: <input type="file" name="transcript" accept=".pdf,.jpg,.png" required></label>
    <label>CV: <input type="file" name="cv" accept=".pdf,.doc,.docx" required></label>
    <label>Valid Passport: <input type="file" name="valid_passport" accept=".pdf,.jpg,.png" required></label>
  </fieldset>

  <fieldset>
  <legend>Agent Information</legend>

  <label for="agent_select">Select Agent *</label>
  <select id="agent_select" class="select2-agent" required>
    <option value="">-- Select Agent --</option>
    <?php foreach ($agents as $agent): ?>
      <option value="<?= htmlspecialchars($agent['first_name']) ?>|<?= htmlspecialchars($agent['last_name']) ?>|<?= htmlspecialchars($agent['email']) ?>">
        <?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?> (<?= htmlspecialchars($agent['email']) ?>)
      </option>
    <?php endforeach; ?>
  </select>

  <div class="inline-inputs">
    <label for="agent_first_name">Agent First Name *</label>
    <input type="text" id="agent_first_name" name="agent_first_name" placeholder="Agent First Name" required>

    <label for="agent_last_name">Agent Last Name *</label>
    <input type="text" id="agent_last_name" name="agent_last_name" placeholder="Agent Last Name" required>
  </div>

  <label for="agent_email">Agent Email *</label>
  <input type="email" id="agent_email" name="agent_email" placeholder="Agent Email" required>
</fieldset>



  <div style="text-align: center;">
    <button type="submit">Submit</button>
    <button type="reset">Reset</button>
  </div>
</form>

<!-- JS & Plugin Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js"></script>

<script>
$(document).ready(function () {
  // Initialize flatpickr for date fields
  $('.flatpickr').flatpickr({ dateFormat: "Y-m-d" });

  // Initialize select2 for country dropdowns
  $('.select2-country').select2({ width: '100%' });

  // Initialize select2 for agent dropdown only
  $('#agent_select').select2({ width: '100%' });

  // When agent is selected, split name/email into fields
  $('#agent_select').on('change', function () {
    const [first, last, email] = this.value.split('|');
    $('#agent_first_name').val(first || '');
    $('#agent_last_name').val(last || '');
    $('#agent_email').val(email || '');
  });

  // Phone input with intl-tel-input
  const input = document.querySelector("#phone_number");
  const iti = window.intlTelInput(input, {
    initialCountry: "auto",
    separateDialCode: true,
    preferredCountries: ["rw", "ke", "ug", "us"],
    geoIpLookup: function (callback) {
      fetch("https://ipapi.co/json")
        .then(res => res.json())
        .then(data => callback(data.country_code))
        .catch(() => callback("rw"));
    },
    utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js"
  });

  // Update hidden phone fields
  function updatePhoneFields() {
    if (iti.isValidNumber()) {
      const countryData = iti.getSelectedCountryData();
      document.querySelector("#area_code").value = `+${countryData.dialCode}`;
      document.querySelector("#phone_number_cleaned").value = iti.getNumber(intlTelInputUtils.numberFormat.NATIONAL);
    }
  }
  input.addEventListener("blur", updatePhoneFields);
  input.addEventListener("change", updatePhoneFields);
  input.addEventListener("keyup", updatePhoneFields);
  document.querySelector("form").addEventListener("submit", updatePhoneFields);

  // AJAX form submission
  $("form").submit(function (e) {
    e.preventDefault();
    if (!validateForm()) return;

    const formData = new FormData(this);

    $.ajax({
      url: 'save-form-turkey.php',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      beforeSend: function () {
        $('button[type=submit]').text('Submitting...').prop('disabled', true);
      },
      success: function (response) {
        if (response.success) {
          alert(response.message);
          $('form')[0].reset();
          $('#agent_select').val('').trigger('change');
        } else {
          alert("Submission failed: " + response.message);
        }
      },
      error: function () {
        alert("An unexpected error occurred.");
      },
      complete: function () {
        $('button[type=submit]').text('Submit').prop('disabled', false);
      }
    });
  });

  // Populate countries into select2-country fields
  const countries = [
    "Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Argentina", "Armenia", "Australia",
    "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium",
    "Belize", "Benin", "Bhutan", "Bolivia", "Bosnia and Herzegovina", "Botswana", "Brazil",
    "Brunei", "Bulgaria", "Burkina Faso", "Burundi", "Cambodia", "Cameroon", "Canada", "Chad",
    "Chile", "China", "Colombia", "Comoros", "Costa Rica", "Croatia", "Cuba", "Cyprus",
    "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "Ecuador", "Egypt",
    "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Eswatini", "Ethiopia", "Fiji",
    "Finland", "France", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Greece", "Grenada",
    "Guatemala", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Honduras", "Hungary", "Iceland",
    "India", "Indonesia", "Iran", "Iraq", "Ireland", "Israel", "Italy", "Ivory Coast", "Jamaica",
    "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Kuwait", "Kyrgyzstan", "Laos",
    "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein", "Lithuania",
    "Luxembourg", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Mauritania",
    "Mauritius", "Mexico", "Moldova", "Monaco", "Mongolia", "Montenegro", "Morocco", "Mozambique",
    "Myanmar", "Namibia", "Nepal", "Netherlands", "New Zealand", "Nicaragua", "Niger", "Nigeria",
    "North Korea", "North Macedonia", "Norway", "Oman", "Pakistan", "Palau", "Panama", "Papua New Guinea",
    "Paraguay", "Peru", "Philippines", "Poland", "Portugal", "Qatar", "Romania", "Russia", "Rwanda",
    "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino",
    "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Serbia", "Seychelles", "Sierra Leone",
    "Singapore", "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Korea",
    "South Sudan", "Spain", "Sri Lanka", "Sudan", "Suriname", "Sweden", "Switzerland", "Syria",
    "Taiwan", "Tajikistan", "Tanzania", "Thailand", "Togo", "Tonga", "Trinidad and Tobago", "Tunisia",
    "Turkey", "Turkmenistan", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom",
    "United States", "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City", "Venezuela", "Vietnam",
    "Yemen", "Zambia", "Zimbabwe"
  ];

  $('.select2-country').each(function () {
    const select = $(this);
    select.empty(); // Clear any existing options
    select.append('<option value="">-- Select Country --</option>');
    countries.forEach(function (country) {
      const option = new Option(country, country, false, false);
      select.append(option);
    });
  });
});

// Validation function
function validateForm() {
  const requiredFields = document.querySelectorAll('input[required], select[required]');
  for (let field of requiredFields) {
    if (!field.value) {
      alert("Please fill all required fields.");
      field.focus();
      return false;
    }
  }
  return true;
}
</script>


</body>
</html>
