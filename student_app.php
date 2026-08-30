<?php
session_start();
require_once __DIR__ . '/db.php';
/* ======================================================
   AUTH CHECK
====================================================== */
if (!isset($_SESSION['id'], $_SESSION['role'])) {
    header("Location: admin-login.php");
    exit;
}

/* ======================================================
   VALIDATE INPUT
====================================================== */
if (empty($_GET['job_id']) || !ctype_digit($_GET['job_id'])) {
    die("Invalid access: job not specified.");
}
$job_id = (int)$_GET['job_id'];

/* ======================================================
   LOAD JOB
====================================================== */
$stmt = $conn->prepare("
    SELECT jl.*, 
           u.name AS university_name,
           c.name AS destination_country,
           p.platform_name
    FROM job_list jl
    LEFT JOIN universities u ON u.id = jl.university_id
    LEFT JOIN countries c ON c.id = u.country_id
    LEFT JOIN platforms p ON p.id = jl.platform_id
    WHERE jl.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job || $job['status'] === 'completed') {
    header("Location: job_todo_list.php");
    exit;
}

$isAdmission = ($job['job_type'] === 'Student Admission Application');
$universities = $conn->query("SELECT id, name FROM universities ORDER BY name ASC");
$platforms    = $conn->query("SELECT id, platform_name FROM platforms WHERE status='Active'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($job['job_type']) ?></title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

<style>
body{background:#f9fdf9}
.excel-header{background:#198754;color:#fff;font-weight:700;text-align:center}
.excel-cell{border:1px solid #ccc;padding:6px;min-width:180px}
.excel-form{border:2px solid #198754;background:#fff}
.readonly{background:#e9ecef}

/* Screenshot Area */
.paste-zone{
    border:2px dashed #198754;
    border-radius:8px;
    padding:30px;
    text-align:center;
    background:#f6fffa;
    cursor:pointer;
}
.paste-zone img{
    max-width:100%;
    margin-top:15px;
    border-radius:6px;
}
.error-text{
    color:#dc3545;
    font-weight:600;
}
</style>
</head>

<body>
<div class="container-fluid my-4">

<div class="d-flex justify-content-between mb-3">
  <h4 class="text-success">📋 <?= htmlspecialchars($job['job_type']) ?></h4>
  <a href="job_todo_list.php" class="btn btn-secondary">⬅ Back</a>
</div>

<div class="alert alert-info">
🔒 This job was auto-created. A screenshot is <strong>required</strong> before submission.
</div>

<form id="studentAppForm" enctype="multipart/form-data">
<input type="hidden" name="job_id" value="<?= $job_id ?>">

<!-- ================= TABLE ================= -->
<div class="table-responsive">
<table class="table table-bordered excel-form">
<thead>
<tr>
<?php
$headers=[
 'Applicant','Application ID','Email','Platform','Applied Country',
 'University','WhatsApp','Country','City','Status','Remarks','Subagent'
];
foreach($headers as $h){
    echo "<th class='excel-header'>$h</th>";
}
?>
</tr>
</thead>
<tbody>
<tr>

<td class="excel-cell">
<input class="form-control readonly" value="<?= htmlspecialchars($job['applicant_name']) ?>" readonly>
<input type="hidden" name="applicant_name" value="<?= htmlspecialchars($job['applicant_name']) ?>">
</td>

<td class="excel-cell">
<input name="application_id" class="form-control" placeholder="APP-XXXX (optional)">
</td>

<td class="excel-cell">
<input class="form-control readonly" value="<?= htmlspecialchars($job['applicant_email']) ?>" readonly>
<input type="hidden" name="email" value="<?= htmlspecialchars($job['applicant_email']) ?>">
</td>

<td class="excel-cell">
<?php if ($isAdmission): ?>
<input class="form-control readonly" value="<?= htmlspecialchars($job['platform_name']) ?>" readonly>
<input type="hidden" name="platform_id" value="<?= (int)$job['platform_id'] ?>">
<?php else: ?>
<select name="platform_id" class="form-select select2">
<option value="">-- Select Platform --</option>
<?php while($p=$platforms->fetch_assoc()): ?>
<option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['platform_name']) ?></option>
<?php endwhile; ?>
</select>
<?php endif; ?>
</td>

<td class="excel-cell">
<input class="form-control readonly" value="<?= htmlspecialchars($job['destination_country'] ?? '') ?>" readonly>
</td>

<td class="excel-cell">
<select name="university_id" class="form-select select2">
<option value="">-- Select University --</option>
<?php while($u=$universities->fetch_assoc()): ?>
<option value="<?= $u['id'] ?>" <?= $u['id']==$job['university_id']?'selected':'' ?>>
<?= htmlspecialchars($u['name']) ?>
</option>
<?php endwhile; ?>
</select>
</td>

<td class="excel-cell"><input name="phone_number" class="form-control" placeholder="+250..."></td>
<td class="excel-cell"><input name="country" class="form-control" value="Rwanda"></td>
<td class="excel-cell"><input name="city" class="form-control" value="Kigali"></td>

<td class="excel-cell">
<select name="status" class="form-select">
<option>Submitted</option>
<option>Processing</option>
<option>Additional Documents Required</option>
<option>No Documents</option>
</select>
</td>

<td class="excel-cell"><input name="application_remarks" class="form-control"></td>
<td class="excel-cell"><input name="subagent" class="form-control"></td>

</tr>
</tbody>
</table>
</div>

<!-- ================= SCREENSHOT (REQUIRED) ================= -->
<div class="mt-4">
<div class="paste-zone" id="pasteZone">
<h5 class="text-success">📸 Paste or Upload Screenshot (Required)</h5>
<p class="mb-1">Press <strong>CTRL + V</strong> or click to upload</p>
<small class="text-muted">PNG / JPG only</small>
<img id="screenshotPreview" style="display:none;">
<p id="shotError" class="error-text mt-2 d-none">⚠ Screenshot is required</p>
</div>
<input type="file" id="fileInput" name="screenshot" accept="image/png,image/jpeg" hidden>
</div>

<!-- ================= SUBMIT ================= -->
<div class="text-center mt-4">
<button class="btn btn-success btn-lg px-5">
✅ Submit & Complete Job
</button>
</div>

</form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$('.select2').select2({width:'100%'});

const pasteZone = document.getElementById('pasteZone');
const preview   = document.getElementById('screenshotPreview');
const fileInput = document.getElementById('fileInput');
const errorMsg  = document.getElementById('shotError');

let screenshotFile = null;

/* CTRL + V (clipboard paste) */
document.addEventListener('paste', e => {
    for (const item of e.clipboardData.items) {
        if (item.type.startsWith('image/')) {
            screenshotFile = item.getAsFile();
            preview.src = URL.createObjectURL(screenshotFile);
            preview.style.display = 'block';
            errorMsg.classList.add('d-none');
        }
    }
});

/* Click to upload fallback */
pasteZone.addEventListener('click', () => fileInput.click());

fileInput.addEventListener('change', () => {
    if (fileInput.files.length) {
        screenshotFile = fileInput.files[0];
        preview.src = URL.createObjectURL(screenshotFile);
        preview.style.display = 'block';
        errorMsg.classList.add('d-none');
    }
});

/* Submit validation */
$('#studentAppForm').on('submit', function(e){
    e.preventDefault();

    if (!screenshotFile) {
        errorMsg.classList.remove('d-none');
        return;
    }

    const formData = new FormData(this);
    formData.append('screenshot', screenshotFile);

    const btn = $(this).find('button');
    btn.prop('disabled', true).text('Submitting...');

    $.ajax({
        url: 'submit_student_app.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: res => {
            if (res.trim() === 'success') {
                location.href = 'job_todo_list.php';
            } else {
                alert(res);
                btn.prop('disabled', false).text('✅ Submit & Complete Job');
            }
        },
        error: () => {
            alert('Server error');
            btn.prop('disabled', false).text('✅ Submit & Complete Job');
        }
    });
});
</script>

</body>
</html>
