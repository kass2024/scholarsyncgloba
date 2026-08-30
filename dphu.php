<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Multi-Step Registration Form</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <style>
    body { background: #f4f4f4; }
    .step-section { display: none; }
    .step-section.active { display: block; }
    .form-step-indicator {
      display: flex;
      justify-content: space-between;
      margin-bottom: 2rem;
    }
    .form-step-indicator div {
      flex: 1;
      text-align: center;
      position: relative;
      padding-bottom: 1rem;
    }
    .form-step-indicator div::after {
      content: "";
      height: 4px;
      width: 100%;
      background: #ccc;
      position: absolute;
      bottom: 0;
      left: 0;
    }
    .form-step-indicator div.active::after,
    .form-step-indicator div.completed::after {
      background: green;
    }
    .form-step-indicator div span {
      display: block;
      font-weight: bold;
      margin-top: 0.5rem;
    }
    .btn-group-nav { display: flex; justify-content: space-between; }
    #loadingOverlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.6);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.spinner-container {
  text-align: center;
  color: white;
}

  </style>
</head>
<body>

<div class="container mt-5 mb-5">
  <form id="multiStepForm">
    <input type="hidden" name="user_id" id="user_id" value="<?php echo $_SESSION['user_id'] ?? ''; ?>">

    <div class="form-step-indicator">
      <div class="step-indicator active">Student Informations</div>
      <div class="step-indicator">Occupation</div>
      <div class="step-indicator">Academic Background</div>
      <div class="step-indicator">Study Plan</div>
      <div class="step-indicator">Supporting Documents</div>
      <div class="step-indicator">Submit</div>
    </div>
     <!-- Step1 Student Informations -->
<div id="step1" class="step-section active">
  <h4 class="mb-4">Formulaire d’Inscription</h4>

  <div class="form-row">
    <div class="form-group col-md-2">
      <label for="prefix">Préfix</label>
      <select class="form-control" id="prefix" name="prefix">
        <option>Mr.</option>
        <option>Ms.</option>
        <option>Mrs.</option>
      </select>
    </div>
    <div class="form-group col-md-5">
      <label for="prenom">Prénom</label>
      <input type="text" class="form-control" id="prenom" name="prenom" placeholder="Par exemple. Jean">
      <small class="form-text text-muted">Comme sur votre pièce d'identité ou votre passeport</small>
    </div>
    <div class="form-group col-md-5">
      <label for="deuxiemenom">Deuxième nom</label>
      <input type="text" class="form-control" id="deuxiemenom" name="deuxiemenom">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group col-md-6">
      <label for="nomfamille">Nom de famille</label>
      <input type="text" class="form-control" id="nomfamille" name="nomfamille">
    </div>
    <div class="form-group col-md-3">
      <label for="sexe">Sexe</label>
      <select class="form-control" id="sexe" name="sexe">
        <option>Mâle</option>
        <option>Femelle</option>
        <option>Autre</option>
      </select>
    </div>
  </div>

  <div class="form-row">
    <label>Date de naissance</label>
    <div class="form-group col-md-3">
      <select class="form-control" id="birth-month" name="birth_month">
        <option></option>
        <option>Janvier</option><option>Février</option><option>Mars</option>
        <option>Avril</option><option>Mai</option><option>Juin</option>
        <option>Juillet</option><option>Août</option><option>Septembre</option>
        <option>Octobre</option><option>Novembre</option><option>Décembre</option>
      </select>
    </div>
    <div class="form-group col-md-3">
      <select class="form-control" id="birth-day" name="birth_day"></select>
    </div>
    <div class="form-group col-md-3">
      <select class="form-control" id="birth-year" name="birth_year"></select>
    </div>
  </div>

  <div class="form-group">
    <label for="adresse">Adresse</label>
    <input type="text" class="form-control" id="adresse" name="adresse">
  </div>

  <div class="form-row">
    <div class="form-group col-md-6">
      <label for="ville">Ville</label>
      <input type="text" class="form-control" id="ville" name="ville" placeholder="Par exemple. Bukavu">
    </div>
    <div class="form-group col-md-6">
      <label for="province">État/Province</label>
      <input type="text" class="form-control" id="province" name="province" placeholder="Par exemple. Mongala">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group col-md-4">
      <label for="postal">Zip / Code Postal</label>
      <input type="text" class="form-control" id="postal" name="postal" placeholder="Par exemple. 2000">
    </div>
    <div class="form-group col-md-4">
      <label for="pays">Pays</label>
      <select class="form-control" id="pays" name="pays"></select>
    </div>
    <div class="form-group col-md-4">
      <label for="email">Adresse E-mail</label>
      <input type="email" class="form-control" id="email" name="email" placeholder="Par exemple. Marie@yahoo.fr">
    </div>
  </div>

  <div class="form-group">
    <label for="telephone">Numéro de téléphone</label>
    <input type="text" class="form-control" id="telephone" name="telephone" placeholder="Par Exemple. +243 120 000 111">
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Fill day options for birth
  const birthDaySelect = document.getElementById('birth-day');
  birthDaySelect.innerHTML = '<option></option>';
  for (let i = 1; i <= 31; i++) {
    birthDaySelect.innerHTML += `<option value="${i}">${i}</option>`;
  }

  // Fill year options for birth
  const birthYearSelect = document.getElementById('birth-year');
  birthYearSelect.innerHTML = '<option></option>';
  for (let i = 0; i < 100; i++) {
    const year = new Date().getFullYear() - i;
    birthYearSelect.innerHTML += `<option value="${year}">${year}</option>`;
  }

  // Fill country list
  const countries = [
    "Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Argentina", "Armenia", "Australia", "Austria", "Azerbaijan",
    "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bhutan", "Bolivia",
    "Bosnia and Herzegovina", "Botswana", "Brazil", "Brunei", "Bulgaria", "Burkina Faso", "Burundi", "Cabo Verde", "Cambodia", "Cameroon",
    "Canada", "Central African Republic", "Chad", "Chile", "China", "Colombia", "Comoros", "Congo (Brazzaville)", "Congo (Kinshasa)", "Costa Rica",
    "Croatia", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "Ecuador", "Egypt",
    "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Eswatini", "Ethiopia", "Fiji", "Finland", "France", "Gabon",
    "Gambia", "Georgia", "Germany", "Ghana", "Greece", "Grenada", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana",
    "Haiti", "Honduras", "Hungary", "Iceland", "India", "Indonesia", "Iran", "Iraq", "Ireland", "Israel",
    "Italy", "Ivory Coast", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Korea (North)", "Korea (South)",
    "Kuwait", "Kyrgyzstan", "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein", "Lithuania",
    "Luxembourg", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Mauritania", "Mauritius",
    "Mexico", "Micronesia", "Moldova", "Monaco", "Mongolia", "Montenegro", "Morocco", "Mozambique", "Myanmar", "Namibia",
    "Nauru", "Nepal", "Netherlands", "New Zealand", "Nicaragua", "Niger", "Nigeria", "North Macedonia", "Norway", "Oman",
    "Pakistan", "Palau", "Palestine", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Poland", "Portugal",
    "Qatar", "Romania", "Russia", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent", "Samoa", "San Marino", "Sao Tome and Principe",
    "Saudi Arabia", "Senegal", "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Slovakia", "Slovenia", "Solomon Islands", "Somalia",
    "South Africa", "South Sudan", "Spain", "Sri Lanka", "Sudan", "Suriname", "Sweden", "Switzerland", "Syria", "Taiwan",
    "Tajikistan", "Tanzania", "Thailand", "Timor-Leste", "Togo", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan",
    "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City",
    "Venezuela", "Vietnam", "Yemen", "Zambia", "Zimbabwe"
  ];
  const countrySelect = document.getElementById('pays');
  countries.forEach(country => {
    const opt = document.createElement('option');
    opt.value = country;
    opt.textContent = country;
    countrySelect.appendChild(opt);
  });
});
</script>

<!--Step2 Occupation -->
<div id="step2" class="step-section">
  <h4 class="mb-4">Formulaire d’Inscription</h4>
  <div class="form-group">
    <label for="orgName">Nom et adresse de l’entreprise (pour les employés)/Nom de l’école/université actuelle (pour les étudiants)</label>
    <input type="text" class="form-control" id="orgName" name="orgName">
  </div>

  <div class="form-group">
    <label for="position">Position</label>
    <input type="text" class="form-control" id="position" name="position" placeholder="Ex : étudiant, gestionnaire comptable, ...">
  </div>

  <h5 class="mt-4">Organization Contact Information</h5>
  <div class="form-row">
    <div class="form-group col-md-6">
      <label for="orgTel">Tel</label>
      <input type="text" class="form-control" id="orgTel" name="orgTel" placeholder="E.g. +1 300 400 5000">
    </div>
    <div class="form-group col-md-6">
      <label for="orgEmail">Email Address</label>
      <input type="email" class="form-control" id="orgEmail" name="orgEmail">
    </div>
  </div>

  <div class="form-group">
    <label for="orgStreet">Street Address</label>
    <input type="text" class="form-control" id="orgStreet" name="orgStreet" placeholder="E.g. 42 Wallaby Way">
  </div>

  <div class="form-group">
    <label for="orgApt">Apartment, suite, etc</label>
    <input type="text" class="form-control" id="orgApt" name="orgApt">
  </div>

  <div class="form-row">
    <div class="form-group col-md-4">
      <label for="orgCity">City</label>
      <input type="text" class="form-control" id="orgCity" name="orgCity" placeholder="E.g. Sydney">
    </div>
    <div class="form-group col-md-4">
      <label for="orgState">State/Province</label>
      <input type="text" class="form-control" id="orgState" name="orgState" placeholder="E.g. New South Wales">
    </div>
    <div class="form-group col-md-2">
      <label for="orgZip">ZIP / Postal Code</label>
      <input type="text" class="form-control" id="orgZip" name="orgZip" placeholder="E.g. 2000">
    </div>
    <div class="form-group col-md-2">
      <label for="orgCountry">Country</label>
      <select class="form-control" id="orgCountry" name="orgCountry"></select>
    </div>
  </div>
</div>


  <!-- Step3: Academic Background -->
<div id="step3" class="step-section">
  <h4 class="mb-4">Formulaire d’Inscription</h4>

  <!-- Institution No1 -->
  <h5 class="text-success">Latest Institution<br><small>No1</small></h5>
  <div class="form-group">
    <label>School Name</label>
    <input type="text" class="form-control" name="school1_name">
  </div>
  <div class="form-row">
    <div class="form-group col-md-6">
      <label>Field of Study</label>
      <input type="text" class="form-control" name="school1_field">
    </div>
    <div class="form-group col-md-6">
      <label>Degree (Diploma) obtained</label>
      <input type="text" class="form-control" name="school1_degree">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group col-md-4">
      <label>From Month</label>
      <select class="form-control" name="school1_from_month">
        <option value="">-- Select Month --</option>
        <option>January</option><option>February</option><option>March</option>
        <option>April</option><option>May</option><option>June</option>
        <option>July</option><option>August</option><option>September</option>
        <option>October</option><option>November</option><option>December</option>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label>Day</label>
      <select class="form-control" id="school1_from_day" name="school1_from_day">
        <option value="">-- Select Day --</option>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label>Year</label>
      <select class="form-control" id="school1_from_year" name="school1_from_year">
        <option value="">-- Select Year --</option>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group col-md-4">
      <label>To Month</label>
      <select class="form-control" name="school1_to_month">
        <option value="">-- Select Month --</option>
        <option>January</option><option>February</option><option>March</option>
        <option>April</option><option>May</option><option>June</option>
        <option>July</option><option>August</option><option>September</option>
        <option>October</option><option>November</option><option>December</option>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label>Day</label>
      <select class="form-control" id="school1_to_day" name="school1_to_day">
        <option value="">-- Select Day --</option>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label>Year</label>
      <select class="form-control" id="school1_to_year" name="school1_to_year">
        <option value="">-- Select Year --</option>
      </select>
    </div>
  </div>

  <!-- Institution No2 -->
  <h5 class="text-success mt-5">Previous Institution<br><small>No2</small></h5>
  <div class="form-group">
    <label>School Name</label>
    <input type="text" class="form-control" name="school2_name">
  </div>
  <div class="form-row">
    <div class="form-group col-md-6">
      <label>Field of Study</label>
      <input type="text" class="form-control" name="school2_field">
    </div>
    <div class="form-group col-md-6">
      <label>Degree (Diploma) obtained</label>
      <input type="text" class="form-control" name="school2_degree">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group col-md-4">
      <label>From Month</label>
      <select class="form-control" name="school2_from_month">
        <option value="">-- Select Month --</option>
        <option>January</option><option>February</option><option>March</option>
        <option>April</option><option>May</option><option>June</option>
        <option>July</option><option>August</option><option>September</option>
        <option>October</option><option>November</option><option>December</option>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label>Day</label>
      <select class="form-control" id="school2_from_day" name="school2_from_day">
        <option value="">-- Select Day --</option>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label>Year</label>
      <select class="form-control" id="school2_from_year" name="school2_from_year">
        <option value="">-- Select Year --</option>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group col-md-4">
      <label>To Month</label>
      <select class="form-control" name="school2_to_month">
        <option value="">-- Select Month --</option>
        <option>January</option><option>February</option><option>March</option>
        <option>April</option><option>May</option><option>June</option>
        <option>July</option><option>August</option><option>September</option>
        <option>October</option><option>November</option><option>December</option>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label>Day</label>
      <select class="form-control" id="school2_to_day" name="school2_to_day">
        <option value="">-- Select Day --</option>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label>Year</label>
      <select class="form-control" id="school2_to_year" name="school2_to_year">
        <option value="">-- Select Year --</option>
      </select>
    </div>
  </div>

  <!-- Institution No3 -->
  <h5 class="text-success mt-5">Previous Institution<br><small>No3</small></h5>
  <div class="form-group">
    <label>School Name</label>
    <input type="text" class="form-control" name="school3_name">
  </div>
  <div class="form-row">
    <div class="form-group col-md-6">
      <label>Field of Study</label>
      <input type="text" class="form-control" name="school3_field">
    </div>
    <div class="form-group col-md-6">
      <label>Degree (Diploma) obtained</label>
      <input type="text" class="form-control" name="school3_degree">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group col-md-4">
      <label>From Month</label>
      <select class="form-control" name="school3_from_month">
        <option value="">-- Select Month --</option>
        <option>January</option><option>February</option><option>March</option>
        <option>April</option><option>May</option><option>June</option>
        <option>July</option><option>August</option><option>September</option>
        <option>October</option><option>November</option><option>December</option>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label>Day</label>
      <select class="form-control" id="school3_from_day" name="school3_from_day">
        <option value="">-- Select Day --</option>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label>Year</label>
      <select class="form-control" id="school3_from_year" name="school3_from_year">
        <option value="">-- Select Year --</option>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group col-md-4">
      <label>To Month</label>
      <select class="form-control" name="school3_to_month">
        <option value="">-- Select Month --</option>
        <option>January</option><option>February</option><option>March</option>
        <option>April</option><option>May</option><option>June</option>
        <option>July</option><option>August</option><option>September</option>
        <option>October</option><option>November</option><option>December</option>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label>Day</label>
      <select class="form-control" id="school3_to_day" name="school3_to_day">
        <option value="">-- Select Day --</option>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label>Year</label>
      <select class="form-control" id="school3_to_year" name="school3_to_year">
        <option value="">-- Select Year --</option>
      </select>
    </div>
  </div>
</div>


<!-- Step 4: Study Plan -->
<div id="step4" class="step-section">
  <h4 class="mb-4">Formulaire d’Inscription</h4>

  <div class="form-row">
    <div class="form-group col-md-6">
      <label>I intend to study towards degree:</label>
      <select class="form-control" name="study_degree">
        <option></option>
        <option>BSc</option>
        <option>MSc</option>
        <option>PhD</option>
        <option>Specialist</option>
      </select>
    </div>
  <div class="form-group col-md-6">
  <label>I am going to pass the course</label>
  <select class="form-control select2" name="study_course" id="study_course">
    <option></option>

    <optgroup label="Economics and Management">
      <option>MBA</option>
      <option>Transport and Logistics Management</option>
      <option>Human Resource Management</option>
      <option>Project Management</option>
      <option>Economic Development</option>
    </optgroup>

    <optgroup label="Law, Political and Administrative Sciences">
      <option>Information and Communications Technology</option>
      <option>International Criminal & Justice</option>
      <option>Land Administration and Management</option>
    </optgroup>

    <optgroup label="Psychological and Educational Sciences">
      <option>Open Distance Learning</option>
      <option>Psychology</option>
      <option>Administration, Planning and Policy & Studies</option>
      <option>Curriculum Design and Development</option>
      <option>Quality Management</option>
    </optgroup>

    <optgroup label="Science and Technology">
      <option>Environmental Studies – Health</option>
      <option>Environmental Studies – Management</option>
      <option>Environmental Studies – Sciences</option>
      <option>Computer Science</option>
      <option>Information Technology Management</option>
      <option>Biology</option>
      <option>Botany</option>
      <option>Chemistry</option>
      <option>Physics</option>
      <option>Human Nutrition</option>
      <option>Mathematics</option>
      <option>Information Communication Technology</option>
    </optgroup>

    <optgroup label="Agricultural and Environmental Sciences & Human and Social Sciences">
      <option>Social Work</option>
      <option>Economics</option>
      <option>Community Economic Development</option>
      <option>Tourism Studies</option>
      <option>Natural Resource Assessment and Management</option>
      <option>International Development and Cooperation</option>
      <option>Humanitarian Action, Cooperation & Development</option>
      <option>Governance and Leadership</option>
    </optgroup>

    <optgroup label="Arts and Literature">
      <option>Kiswahili</option>
      <option>Literature</option>
      <option>Linguistics</option>
      <option>Library and Information Management</option>
      <option>Monitoring and Evaluation</option>
      <option>Gender Studies</option>
      <option>Mass Communication</option>
      <option>Arts in Literature</option>
      <option>Geography</option>
      <option>History</option>
    </optgroup>

    <optgroup label="Approved Programmes with ESIMAD Academy - Business Management">
      <option>Accounting and Financial Sciences and Techniques</option>
      <option>Banking and Corporate Finance</option>
      <option>Human Resources Management</option>
      <option>Sales Management and International Marketing</option>
      <option>Administration and Management of Organizations</option>
      <option>Transport Logistics</option>
      <option>Management Information Systems</option>
      <option>Project Management</option>
      <option>Business Communication</option>
    </optgroup>

    <optgroup label="Approved Programmes with ESIMAD Academy - Law and Political Sciences">
      <option>Private Law</option>
      <option>Business Law</option>
      <option>Public Law</option>
      <option>International Humanitarian Law</option>
      <option>International Relations and Diplomacy</option>
      <option>Banking and Financial Law</option>
      <option>Insurance Law</option>
      <option>Corporate Tax Law</option>
      <option>Peace Administration</option>
      <option>International Governance and Sustainable Development</option>
    </optgroup>

    <optgroup label="Approved Programmes with ESIMAD Academy - Sciences and Technology">
      <option>Computer Networks and Telecommunications</option>
      <option>Civil Engineering – Public Works</option>
      <option>Electrical Engineering</option>
      <option>Mechanical Engineering</option>
    </optgroup>

    <optgroup label="Approved Programmes with ESIMAD Academy - Agriculture and Environment">
      <option>Rural and Environmental Engineering</option>
      <option>Livestock and Animal Production</option>
      <option>Agronomy – Plant Production</option>
      <option>Water and Environmental Management/Water and Forestry</option>
      <option>Socio-Economy & Rural Economy</option>
    </optgroup>

    <optgroup label="Approved Programmes with ESIMAD Academy - Health Sciences">
      <option>Sanitary and Environmental Engineering</option>
      <option>Human Nutrition and Nutrition Policy</option>
      <option>Epidemiology of Intervention</option>
      <option>Health Information Systems Engineering</option>
      <option>Nursing Sciences</option>
      <option>Obstetrical and Gynecological Sciences</option>
      <option>Mental Health (Psychiatric Care)</option>
      <option>Community Health Care</option>
      <option>Health psychpedagogy</option>
      <option>Emergency Care</option>
      <option>Health Care Administration</option>
      <option>Management of Health and Social Organizations</option>
      <option>Hospital Management</option>
      <option>Reproductive Health</option>
      <option>Management of Health Projects and Programs</option>
      <option>Monitoring & Evaluation of Health Projects and Programs</option>
    </optgroup>

  </select>
</div>

  </div>

  <div class="form-row">
    <div class="form-group col-md-6">
      <label>Field of Study</label>
      <select class="form-control" name="study_field">
        <option></option>
        <option>Economics and Business</option>
        <option>Agriculture and Veterinary Medicine</option>
        <option>Education</option>
        <option>Law</option>
        <option>Sciences</option>
        <option>Health Sciences</option>
        <option>Engineering</option>
        <option>Information Technology and Computing</option>
        <option>Others</option>
      </select>
    </div>
    <div class="form-group col-md-6">
      <label>My intended specialty</label>
      <input type="text" class="form-control" name="study_specialty" placeholder="e.g., Artificial Intelligence">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group col-md-6">
      <label>Language of study</label>
      <select class="form-control" name="study_language">
        <option></option>
        <option>English</option>
        <option>French</option>
      </select>
    </div>
    <div class="form-group col-md-6">
      <label>English Language Proficiency</label>
      <select class="form-control" name="english_proficiency">
        <option></option>
        <option>Beginner</option>
        <option>Upper Intermediate</option>
        <option>Lower Intermediate</option>
        <option>Intermediate</option>
         <option>Upper Intermediate</option>
      </select>
    </div>
  </div>

  <div class="form-group">
    <label>Additional information:</label>
    <textarea class="form-control" name="study_additional_info" placeholder="Add any information relevant to your study plan..." rows="4"></textarea>
  </div>
</div>

<!-- Step 5: Supporting Documents -->
<div id="step5" class="step-section">
  <h4 class="mb-4">Formulaire d’Inscription</h4>

  <!-- Passport Photo -->
  <div class="form-group mb-3">
    <label for="passport_photo"><strong>Passport Sized Photo</strong></label>
    <input type="file" name="passport_photo" id="passport_photo" class="form-control-file btn btn-success">
    <small class="form-text text-muted">A photo with a white or blue background</small>
  </div>

  <!-- National ID or Passport -->
  <div class="form-group mb-3">
    <label for="national_id_or_passport"><strong>National ID or Passport</strong></label>
    <input type="file" name="national_id_or_passport" id="national_id_or_passport" class="form-control-file btn btn-success">
  </div>

  <!-- Diploma/Certificate -->
  <div class="form-group mb-3">
    <label for="diploma_certificate"><strong>The Certified/Notarized Copy of Highest Degree’s Diploma / Certificate of Schooling</strong></label>
    <input type="file" name="diploma_certificate" id="diploma_certificate" class="form-control-file btn btn-success">
  </div>

  <!-- Academic Transcripts -->
  <div class="form-group mb-3">
    <label for="academic_transcripts"><strong>The Certified/Notarized Copy of Highest Academic Transcripts</strong></label>
    <input type="file" name="academic_transcripts" id="academic_transcripts" class="form-control-file btn btn-success">
  </div>

  <!-- Language Proficiency -->
  <div class="form-group mb-4">
    <label for="language_proof"><strong>Proof of Language Proficiency</strong></label>
    <input type="file" name="language_proof" id="language_proof" class="form-control-file btn btn-success">
  </div>

  <!-- Recommendation Letters -->
  <div class="form-group mb-4">
    <label><strong>Two Recommendation Letters</strong></label>
    <div class="border p-4 bg-light text-center" style="border-style: dashed;">
      <i class="fa fa-cloud-upload-alt fa-2x mb-2"></i>
      <p>Drag and Drop (or) <a href="#" id="recommendation-upload-link">Choose Files</a></p>
      <input type="file" id="recommendation-upload-input" name="recommendation_letters[]" class="d-none" multiple>
      <ul id="reco-file-list" class="mt-2 small text-left"></ul>
    </div>
  </div>

  <!-- Other Documents -->
  <div class="form-group mb-2">
    <label><strong>Other Documents</strong></label>
    <div class="border p-4 bg-light text-center" style="border-style: dashed;">
      <i class="fa fa-cloud-upload-alt fa-2x mb-2"></i>
      <p>Drag and Drop (or) <a href="#" id="otherdocs-upload-link">Choose Files</a></p>
      <input type="file" id="otherdocs-upload-input" name="other_documents[]" class="d-none" multiple>
      <ul id="other-file-list" class="mt-2 small text-left"></ul>
    </div>
  </div>

  <p class="text-danger mt-2">Upload a maximum of 5 files otherwise they won’t be considered.</p>
</div>

<!-- Step 6: Submit -->
<div id="step6" class="step-section">
  <h4 class="mb-4">Formulaire d’Inscription</h4>

  <div class="form-group mb-4">
    <div class="form-check">
      <input type="checkbox" class="form-check-input me-2" id="agreement" name="agreement" required>
      <label class="form-check-label fw-bold" for="agreement">
        Yes, I agree all information provided on the form to be valid and correct.
      </label>
    </div>
  </div>
</div>


<!-- ✅ Global Navigation Buttons (Place this just once, after all steps and inside the <form>) -->
<div class="btn-group-nav mt-4">
  <button type="button" class="btn btn-success" id="prevBtn">Previous</button>
  <button type="button" class="btn btn-primary" id="nextBtn">Next</button>
  <button type="submit" class="btn btn-primary d-none" id="submitBtn">Submit</button>
</div>
</form> <!-- ✅ Close form AFTER the buttons -->
<!-- Loading Spinner Overlay -->
<div id="loadingOverlay" style="display: none;">
  <div class="spinner-container">
    <div class="spinner-border text-primary" role="status">
      <span class="sr-only">Loading...</span>
    </div>
    <p class="text-white mt-3">Submitting your form. Please wait...</p>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById('multiStepForm');
  const steps = document.querySelectorAll('.step-section');
  const indicators = document.querySelectorAll('.step-indicator');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const submitBtn = document.getElementById('submitBtn');
  const loadingOverlay = document.getElementById('loadingOverlay');
  let currentStep = 0;

  // Step navigation
  function showStep(step) {
    steps.forEach((s, i) => s.classList.toggle('active', i === step));
    indicators.forEach((el, i) => {
      el.classList.remove('active', 'completed');
      if (i < step) el.classList.add('completed');
      else if (i === step) el.classList.add('active');
    });
    prevBtn.style.display = step === 0 ? 'none' : 'inline-block';
    nextBtn.classList.toggle('d-none', step === steps.length - 1);
    submitBtn.classList.toggle('d-none', step !== steps.length - 1);
  }

  // Save via AJAX
  async function saveStep(stepIndex) {
    const formData = new FormData(form);
    formData.append('step', stepIndex + 1);
    formData.append('user_id', document.getElementById('user_id')?.value || '');

    try {
      const res = await fetch('save-form-dphu.php', {
        method: 'POST',
        body: formData
      });
      const text = await res.text();
      console.log('Raw Response:', text);
      const result = JSON.parse(text);
      if (result.status === 'success') {
        if (result.user_id) {
          document.getElementById('user_id').value = result.user_id;
        }
        return true;
      } else {
        alert('❌ Save failed: ' + result.message);
        return false;
      }
    } catch (err) {
      console.error('Save Error:', err);
      alert('❌ Network or server error');
      return false;
    }
  }

 // Navigation events
nextBtn.addEventListener('click', async () => {
  // Step 4: skip saveStep because files are already uploaded
  if (currentStep === 4) {
    currentStep++;
    showStep(currentStep);
    return;
  }

  const saved = await saveStep(currentStep);
  if (saved && currentStep < steps.length - 1) {
    currentStep++;
    showStep(currentStep);
  }
});

prevBtn.addEventListener('click', () => {
  if (currentStep > 0) {
    currentStep--;
    showStep(currentStep);
  }
});

  submitBtn.addEventListener('click', async function (e) {
    e.preventDefault();
    submitBtn.disabled = true;
    loadingOverlay.style.display = 'flex';
    const success = await saveStep(currentStep);
    loadingOverlay.style.display = 'none';
    submitBtn.disabled = false;
    if (success) {
      alert('✅ Form submitted successfully!');
      form.reset();
      currentStep = 0;
      showStep(currentStep);
    }
  });

  // Fill dropdowns (birthdays and academic years)
  function populateRange(id, start, end) {
    const el = document.getElementById(id);
    if (el) {
      el.innerHTML = '<option value=""></option>';
      for (let i = start; i <= end; i++) {
        el.innerHTML += `<option value="${i}">${i}</option>`;
      }
    }
  }

  // Populate birthday
  populateRange('birth-day', 1, 31);
  populateRange('birth-year', new Date().getFullYear() - 99, new Date().getFullYear());

  // Populate school date ranges
  ['school1', 'school2', 'school3'].forEach(prefix => {
    populateRange(`${prefix}_from_day`, 1, 31);
    populateRange(`${prefix}_to_day`, 1, 31);
    populateRange(`${prefix}_from_year`, 1975, 2025);
    populateRange(`${prefix}_to_year`, 1975, 2025);
  });

  // Country list
  const countries = [...new Set([
    "Afghanistan","Albania","Algeria","Andorra","Angola","Argentina","Armenia","Australia","Austria","Azerbaijan",
    "Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan","Bolivia",
    "Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi","Cabo Verde","Cambodia","Cameroon",
    "Canada","Central African Republic","Chad","Chile","China","Colombia","Comoros","Congo (Brazzaville)","Congo (Kinshasa)","Costa Rica",
    "Croatia","Cuba","Cyprus","Czech Republic","Denmark","Djibouti","Dominica","Dominican Republic","Ecuador","Egypt",
    "El Salvador","Equatorial Guinea","Eritrea","Estonia","Eswatini","Ethiopia","Fiji","Finland","France","Gabon",
    "Gambia","Georgia","Germany","Ghana","Greece","Grenada","Guatemala","Guinea","Guinea-Bissau","Guyana",
    "Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq","Ireland","Israel",
    "Italy","Ivory Coast","Jamaica","Japan","Jordan","Kazakhstan","Kenya","Kiribati","Korea (North)","Korea (South)",
    "Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia","Libya","Liechtenstein","Lithuania",
    "Luxembourg","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta","Marshall Islands","Mauritania","Mauritius",
    "Mexico","Micronesia","Moldova","Monaco","Mongolia","Montenegro","Morocco","Mozambique","Myanmar","Namibia",
    "Nauru","Nepal","Netherlands","New Zealand","Nicaragua","Niger","Nigeria","North Macedonia","Norway","Oman",
    "Pakistan","Palau","Palestine","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Poland","Portugal",
    "Qatar","Romania","Russia","Rwanda","Saint Kitts and Nevis","Saint Lucia","Saint Vincent","Samoa","San Marino","Sao Tome and Principe",
    "Saudi Arabia","Senegal","Serbia","Seychelles","Sierra Leone","Singapore","Slovakia","Slovenia","Solomon Islands","Somalia",
    "South Africa","South Sudan","Spain","Sri Lanka","Sudan","Suriname","Sweden","Switzerland","Syria","Taiwan",
    "Tajikistan","Tanzania","Thailand","Timor-Leste","Togo","Tonga","Trinidad and Tobago","Tunisia","Turkey","Turkmenistan",
    "Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States","Uruguay","Uzbekistan","Vanuatu","Vatican City",
    "Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"
  ])];

  ['pays', 'orgCountry'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      countries.forEach(country => {
        const opt = document.createElement('option');
        opt.value = country;
        opt.textContent = country;
        el.appendChild(opt);
      });
    }
  });

  // Initialize Select2
  if (typeof $ !== 'undefined') {
    $('.select2').select2({
      placeholder: "Select a program",
      allowClear: true,
      width: '100%'
    });
  }

  // Setup AJAX file upload (single fields)
  const singleFileIds = [
    'passport_photo', 'national_id_or_passport', 'diploma_certificate',
    'academic_transcripts', 'language_proof'
  ];

  singleFileIds.forEach(id => {
    const input = document.getElementById(id);
    if (input) {
      input.addEventListener('change', () => uploadSingleFile(id));
    }
  });
function uploadMultiFiles(inputId, fieldName) {
  const input = document.getElementById(inputId);
  const userId = document.getElementById('user_id')?.value || '';
  if (!input || !input.files.length || !userId) return;

  const formData = new FormData();
  for (let i = 0; i < input.files.length; i++) {
    formData.append(fieldName + '[]', input.files[i]);
  }
  formData.append('user_id', userId);

  fetch('upload_multi.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      console.log(`✅ Uploaded ${data.paths.length} file(s) for ${fieldName}`);
      // No alert — silent success
    } else {
      console.error(`❌ Upload failed: ${data.message}`);
      // Optional: you can display a small inline error instead of alert
    }
  })
  .catch(err => {
    console.error(`❌ Error uploading ${fieldName}`, err);
    // Optional: log or show inline message
  });
}

  function uploadSingleFile(fieldId) {
  const input = document.getElementById(fieldId);
  const userId = document.getElementById('user_id')?.value || '';

  if (!input || !input.files.length) {
    alert(`❌ No file selected for ${fieldId}`);
    return;
  }
  if (!userId) {
    alert("❌ Missing user ID");
    return;
  }

  const formData = new FormData();
  formData.append(fieldId, input.files[0]); // 👈 File input must match PHP $_FILES
  formData.append('user_id', userId);

  fetch('upload_single.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      console.log(`✅ ${fieldId} uploaded to ${data.path}`);
    } else {
      alert(`❌ Failed to upload ${fieldId}: ${data.message}`);
    }
  })
  .catch(err => {
    console.error(`❌ JS error uploading ${fieldId}`, err);
    alert(`❌ Upload failed for ${fieldId}`);
  });
}


 const recoLink = document.getElementById('recommendation-upload-link');
const recoInput = document.getElementById('recommendation-upload-input');
const recoList = document.getElementById('reco-file-list');

const otherLink = document.getElementById('otherdocs-upload-link');
const otherInput = document.getElementById('otherdocs-upload-input');
const otherList = document.getElementById('other-file-list');

// Link to file trigger
if (recoLink && recoInput) {
  recoLink.addEventListener('click', e => {
    e.preventDefault();
    recoInput.click();
  });
}
if (otherLink && otherInput) {
  otherLink.addEventListener('click', e => {
    e.preventDefault();
    otherInput.click();
  });
}

// File selection triggers AJAX upload with correct input IDs
if (recoInput) {
  recoInput.addEventListener('change', () => {
    recoList.innerHTML = '';
    Array.from(recoInput.files).forEach(file => {
      const li = document.createElement('li');
      li.textContent = file.name;
      recoList.appendChild(li);
    });
    // Call with actual input ID and DB field name
    uploadMultiFiles('recommendation-upload-input', 'recommendation_letters');
  });
}

if (otherInput) {
  otherInput.addEventListener('change', () => {
    otherList.innerHTML = '';
    Array.from(otherInput.files).forEach(file => {
      const li = document.createElement('li');
      li.textContent = file.name;
      otherList.appendChild(li);
    });
    // Call with actual input ID and DB field name
    uploadMultiFiles('otherdocs-upload-input', 'other_documents');
  });
}



  // Initial render
  showStep(currentStep);
});
</script>


</body>
</html>
