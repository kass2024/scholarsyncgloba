<?php
/* =====================================================
   SESSION & DB
===================================================== */
session_start();
require_once __DIR__ . '/db.php';
/* =====================================================
   AUTH CHECK
===================================================== */
if (!isset($_SESSION['id'], $_SESSION['role'])) {
    header("Location: admin-login.php");
    exit;
}

$admin_id = (int) $_SESSION['id'];

/* =====================================================
   GET JOB ID
===================================================== */
$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
$jobTitle = '';
$jobType  = '';
$jobStatus = '';

if ($jobId <= 0) {
    header("Location: job_todo_list.php");
    exit;
}

/* =====================================================
   LOAD JOB (ONLY OTHER JOB ALLOWED)
===================================================== */
$stmt = $conn->prepare("
    SELECT title, job_type, status
    FROM job_list
    WHERE id = ?
");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$stmt->bind_result($jobTitle, $jobType, $jobStatus);
$stmt->fetch();
$stmt->close();

/* =====================================================
   VALIDATION
===================================================== */
if ($jobType !== 'Other Job') {
    header("Location: job_todo_list.php");
    exit;
}

if ($jobStatus === 'completed') {
    header("Location: job_todo_list.php");
    exit;
}

/* =====================================================
   SANITIZE OUTPUT
===================================================== */
$jobTitleSafe = htmlspecialchars($jobTitle, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>🛠 Job Entry</title>

<style>
body{
  font-family: "Segoe UI", sans-serif;
  background:#f4f7fc;
  padding:20px;
  margin:0;
}
.container{
  max-width:650px;
  margin:auto;
  background:#fff;
  padding:25px;
  border-radius:10px;
  box-shadow:0 6px 18px rgba(0,0,0,.1);
}
h2{
  text-align:center;
  color:#2563eb;
}
label{
  display:block;
  margin-top:15px;
  font-weight:600;
}
input,textarea{
  width:100%;
  padding:10px;
  margin-top:6px;
  border-radius:6px;
  border:1px solid #ccc;
  box-sizing:border-box;
}
input[readonly]{
  background:#f2f2f2;
}
button{
  margin-top:22px;
  width:100%;
  padding:14px;
  border:none;
  border-radius:8px;
  background:#2563eb;
  color:#fff;
  font-weight:700;
  font-size:16px;
  cursor:pointer;
}
button:hover{
  background:#1e40af;
}
button:disabled{
  opacity:.7;
  cursor:not-allowed;
}
.back-btn{
  display:block;
  text-align:center;
  margin-top:20px;
  color:#2563eb;
  font-weight:600;
  text-decoration:none;
}
.paste-zone{
  margin-top:8px;
  padding:18px;
  border:2px dashed #86efac;
  border-radius:10px;
  background:#f0fdf4;
  text-align:center;
  cursor:pointer;
}
.paste-zone h5{
  margin:0 0 6px;
  color:#166534;
  font-size:15px;
}
.paste-zone p{
  margin:0 0 4px;
  color:#374151;
  font-size:14px;
}
.paste-zone small{
  color:#6b7280;
}
#screenshotPreview{
  display:none;
  max-width:100%;
  margin-top:12px;
  border-radius:8px;
  border:1px solid #d1d5db;
}
.error-text{
  color:#dc2626;
  font-size:13px;
  font-weight:600;
  margin-top:10px;
}
.error-text.hidden{
  display:none;
}
</style>
</head>

<body>
<div class="container">

<h2>🛠 Other Job Entry</h2>

<form action="submit-job.php" method="POST" id="jobForm" enctype="multipart/form-data">

  <input type="hidden" name="job_id" value="<?= $jobId ?>">

  <label for="job_title">Job Title</label>
  <input type="text"
         name="job_title"
         id="job_title"
         value="<?= $jobTitleSafe ?>"
         readonly>

  <label for="job_description">Job Description</label>
  <textarea name="job_description"
            id="job_description"
            rows="4"
            required
            placeholder="Describe what was done..."></textarea>

  <label>📸 Screenshot (required)</label>
  <div class="paste-zone" id="pasteZone">
    <h5>Paste or upload a screenshot</h5>
    <p>Press <strong>Ctrl + V</strong> or click to choose a file</p>
    <small>PNG or JPG only, max 5 MB</small>
    <img id="screenshotPreview" alt="Screenshot preview">
    <p id="shotError" class="error-text hidden">Screenshot is required before you can save.</p>
  </div>
  <input type="file" id="fileInput" name="screenshot" accept="image/png,image/jpeg" hidden>

  <input type="hidden" name="hours_spent" value="0">
  <input type="hidden" name="productivity_score" value="0">
  <input type="hidden" name="remarks" value="">

  <button type="submit" id="submitBtn">✅ Save & Complete Job</button>
</form>

<a href="job_todo_list.php" class="back-btn">⬅ Back to Job List</a>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const pasteZone = document.getElementById("pasteZone");
  const preview = document.getElementById("screenshotPreview");
  const fileInput = document.getElementById("fileInput");
  const errorMsg = document.getElementById("shotError");
  const form = document.getElementById("jobForm");
  const submitBtn = document.getElementById("submitBtn");

  let screenshotFile = null;

  function showPreview(file) {
    if (!file) return;
    screenshotFile = file;
    preview.src = URL.createObjectURL(file);
    preview.style.display = "block";
    errorMsg.classList.add("hidden");
  }

  document.addEventListener("paste", function (e) {
    const items = e.clipboardData ? e.clipboardData.items : [];
    for (const item of items) {
      if (item.type && item.type.indexOf("image/") === 0) {
        showPreview(item.getAsFile());
        break;
      }
    }
  });

  pasteZone.addEventListener("click", function () {
    fileInput.click();
  });

  fileInput.addEventListener("change", function () {
    if (fileInput.files && fileInput.files.length) {
      showPreview(fileInput.files[0]);
    }
  });

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    if (!screenshotFile && (!fileInput.files || !fileInput.files.length)) {
      errorMsg.classList.remove("hidden");
      return;
    }

    const formData = new FormData(form);
    if (screenshotFile) {
      formData.set("screenshot", screenshotFile);
    }

    submitBtn.disabled = true;
    submitBtn.textContent = "Saving…";

    fetch("submit-job.php", {
      method: "POST",
      body: formData
    })
    .then(function (res) {
      return res.text();
    })
    .then(function (text) {
      const trimmed = text.trim();
      if (trimmed === "success") {
        window.location.href = "job_todo_list.php";
        return;
      }
      alert(trimmed || "Could not save job.");
    })
    .catch(function () {
      alert("Could not save job. Please try again.");
    })
    .finally(function () {
      submitBtn.disabled = false;
      submitBtn.textContent = "✅ Save & Complete Job";
    });
  });
});
</script>

</body>
</html>
