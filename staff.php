<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff Daily Job Form</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
      box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    h2 {
      text-align: center;
      color: #0c3c78;
      margin-bottom: 20px;
    }
    label {
      font-weight: 500;
      margin-top: 20px;
      display: block;
    }
    input[type="text"], input[type="email"], select, textarea {
      width: 100%;
      padding: 10px 15px;
      margin-top: 5px;
      border-radius: 5px;
      border: 1px solid #ccc;
      font-size: 1rem;
    }
    .inline-inputs {
      display: flex;
      gap: 10px;
    }
    .inline-inputs input {
      flex: 1;
    }
    textarea {
      resize: vertical;
    }
    .upload-box {
      border: 2px dashed #bbb;
      border-radius: 8px;
      padding: 20px;
      text-align: center;
      color: #777;
      margin-top: 10px;
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
    }
    .form-buttons button {
      background-color: #0c3c78;
      color: #fff;
      padding: 12px 20px;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      cursor: pointer;
    }
    .form-buttons button:hover {
      background-color: #092a5c;
    }
  </style>
</head>
<body>
<div class="form-container">
  <h2>Daily Staff Job Report</h2>

  <form action="save_staff.php" method="post" enctype="multipart/form-data" id="staffForm">
    <input type="hidden" name="step" id="step" value="step1">

    <!-- Step 1 -->
    <div class="form-step active" id="step1">
      <label>Staff Name *</label>
      <div class="inline-inputs">
        <input type="text" name="first_name" placeholder="First Name" required>
        <input type="text" name="last_name" placeholder="Last Name" required>
      </div>

      <label>Staff Email *</label>
      <input type="email" name="email" required>

      <label>Staff Phone Number *</label>
      <input type="text" name="phone" required>

      <label>Job Name *</label>
      <input type="text" name="job_name" required>

      <label>Date *</label>
      <input type="text" name="job_date" id="job_date" required placeholder="mm/dd/yyyy">

      <h3>Job 1</h3>
      <label>Start Time *</label>
      <input type="text" name="job1_start" class="timepicker" required placeholder="HH:MM AM/PM">
      <label>Finish Time *</label>
      <input type="text" name="job1_finish" class="timepicker" required placeholder="HH:MM AM/PM">
      <label>Down Time</label>
      <input type="text" name="job1_down" class="timepicker" placeholder="HH:MM AM/PM">
      <label>Description of Work & Material</label>
      <textarea name="job1_desc"></textarea>
      <label>Image Upload</label>
      <input type="file" name="job1_image" class="upload-box">

      <div class="form-buttons">
        <button type="button" onclick="submitStep1()">Save & Next</button>
      </div>
    </div>

    <!-- Step 2 -->
    <div class="form-step" id="step2">
      <h3>Job 2</h3>
      <label>Start Time</label>
      <input type="text" name="job2_start" class="timepicker" placeholder="HH:MM AM/PM">
      <label>Finish Time</label>
      <input type="text" name="job2_finish" class="timepicker" placeholder="HH:MM AM/PM">
      <label>Down Time</label>
      <input type="text" name="job2_down" class="timepicker" placeholder="HH:MM AM/PM">
      <label>Description of Work & Material</label>
      <textarea name="job2_desc"></textarea>
      <label>Image Upload</label>
      <input type="file" name="job2_image" class="upload-box">

      <h3>Job 3</h3>
      <label>Start Time</label>
      <input type="text" name="job3_start" class="timepicker" placeholder="HH:MM AM/PM">
      <label>Finish Time</label>
      <input type="text" name="job3_finish" class="timepicker" placeholder="HH:MM AM/PM">
      <label>Down Time</label>
      <input type="text" name="job3_down" class="timepicker" placeholder="HH:MM AM/PM">
      <label>Description of Work & Material</label>
      <textarea name="job3_desc"></textarea>
      <label>Image Upload</label>
      <input type="file" name="job3_image" class="upload-box">

      <label>Additional Comments</label>
      <textarea name="comments" rows="4"></textarea>

      <div class="form-buttons">
        <button type="button" onclick="prevStep()">Previous</button>
        <button type="submit">Submit</button>
      </div>
    </div>
  </form>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  // Flatpickr configuration
  document.addEventListener('DOMContentLoaded', function () {
    flatpickr("#job_date", {
      dateFormat: "m/d/Y",
      altInput: true,
      altFormat: "F j, Y",
      maxDate: "today"
    });

    flatpickr(".timepicker", {
      enableTime: true,
      noCalendar: true,
      dateFormat: "h:i K",
      time_24hr: false
    });
  });

  let currentStep = 1;

  function showStep(step) {
    document.querySelectorAll('.form-step').forEach((el, idx) => {
      el.classList.toggle('active', idx === step - 1);
    });
    currentStep = step;
  }

  function nextStep() {
    document.getElementById('step').value = 'step2';
    showStep(currentStep + 1);
  }

  function prevStep() {
    document.getElementById('step').value = 'step1';
    showStep(currentStep - 1);
  }

  function submitStep1() {
    const form = document.getElementById('staffForm');
    const formData = new FormData(form);
    formData.set('step', 'step1');

    fetch('save_staff.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        document.getElementById('step').value = 'step2';
        showStep(2);
      } else {
        alert('Error saving Step 1: ' + data.message);
      }
    })
    .catch(err => alert('Error submitting Step 1: ' + err));
  }

  document.getElementById('staffForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.set('step', 'step2');

    fetch('save_staff.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        alert(`✅ ${data.message}\n🆔 Your User ID: ${data.user_id || 'N/A'}`);
        window.location.href = 'index.php';
      } else {
        alert('❌ Error saving Step 2: ' + data.message);
      }
    })
    .catch(err => {
      alert('❌ Error submitting Step 2: ' + err);
    });
  });
</script>
</body>
</html>
