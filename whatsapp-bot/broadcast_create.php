<?php
require_once "config.php";
session_start();

/* ===============================
   CSRF
================================ */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ===============================
   FLASH
================================ */
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType    = $_SESSION['flash_type'] ?? "success";
$activeTab    = $_SESSION['active_tab'] ?? "broadcast";

unset($_SESSION['flash_message'], $_SESSION['flash_type'], $_SESSION['active_tab']);

/* ===============================
   FETCH SEGMENTS FROM DB
================================ */
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset("utf8mb4");

$segments = [];

$result = $conn->query("
    SELECT COLUMN_TYPE 
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'contacts'
    AND COLUMN_NAME = 'segment'
");

if ($row = $result->fetch_assoc()) {
    preg_match("/^enum\((.*)\)$/", $row['COLUMN_TYPE'], $matches);
    $enum = str_getcsv($matches[1], ',', "'");
    $segments = $enum;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>WhatsApp Broadcast Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#f4f6fb; }
.dashboard-card { max-width:900px; margin:auto; border-radius:15px; border:none; }
.nav-pills .nav-link.active { background:#198754; }
.preview-box { background:#e5ddd5; padding:15px; border-radius:10px; }
.whatsapp-message { background:#fff; padding:10px; border-radius:8px; max-width:75%; }
.preview-image { width:100%; border-radius:6px; margin-bottom:8px; display:none; }
</style>
</head>
<body>

<div class="container py-5">
<div class="card shadow dashboard-card p-4">

<h3 class="text-center mb-4">📲 WhatsApp Broadcast Dashboard</h3>

<?php if ($flashMessage): ?>
<div class="alert alert-<?= htmlspecialchars($flashType) ?> text-center">
<?= htmlspecialchars($flashMessage) ?>
</div>
<?php endif; ?>

<ul class="nav nav-pills justify-content-center mb-4">
<li class="nav-item">
<a class="nav-link <?= $activeTab === 'broadcast' ? 'active' : '' ?>" data-bs-toggle="pill" href="#broadcast">Broadcast</a>
</li>
<li class="nav-item">
<a class="nav-link <?= $activeTab === 'addContact' ? 'active' : '' ?>" data-bs-toggle="pill" href="#addContact">Add Contact</a>
</li>
<li class="nav-item">
<a class="nav-link <?= $activeTab === 'bulkUpload' ? 'active' : '' ?>" data-bs-toggle="pill" href="#bulkUpload">Bulk Upload</a>
</li>
</ul>

<div class="tab-content">

<!-- ================= BROADCAST ================= -->
<div class="tab-pane fade <?= $activeTab === 'broadcast' ? 'show active' : '' ?>" id="broadcast">

<form id="broadcastForm" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="mb-3">
<label>Campaign Name</label>
<input type="text" name="campaign_name" class="form-control" required>
</div>

<div class="mb-3">
<label>Target Segment</label>
<select name="segment" class="form-select">
<option value="">All Contacts</option>
<?php foreach ($segments as $seg): ?>
<option value="<?= htmlspecialchars($seg) ?>">
<?= ucfirst($seg) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-3">
<label>Upload Image (JPG/PNG max 2MB)</label>
<input type="file" name="image" id="imageInput" class="form-control" accept="image/jpeg,image/png" required>
</div>

<div class="mb-3">
<label>Message Text</label>
<textarea name="dynamic_text" id="messageText" class="form-control" rows="3" required></textarea>
</div>

<button type="submit" class="btn btn-success w-100">🚀 Launch Broadcast</button>
<div id="broadcastResult" class="mt-3"></div>
</form>

<hr class="my-4">

<h6>Live Preview</h6>
<div class="preview-box">
<div class="whatsapp-message">
<img id="previewImage" class="preview-image">
<div id="previewText">Your preview will appear here...</div>
</div>
</div>

</div>

<!-- ================= ADD CONTACT ================= -->
<div class="tab-pane fade <?= $activeTab === 'addContact' ? 'show active' : '' ?>" id="addContact">

<form method="POST" action="add_contact.php">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="mb-3">
<label>Name</label>
<input type="text" name="name" class="form-control" required>
</div>

<div class="mb-3">
<label>Phone (2547XXXXXXXX)</label>
<input type="text" name="phone" class="form-control" pattern="[0-9]{10,15}" required>
</div>

<div class="mb-3">
<label>Segment</label>
<select name="segment" class="form-select" required>
<?php foreach ($segments as $seg): ?>
<option value="<?= htmlspecialchars($seg) ?>">
<?= ucfirst($seg) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<button class="btn btn-primary w-100">Add Contact</button>
</form>

</div>

<!-- ================= BULK UPLOAD ================= -->
<div class="tab-pane fade <?= $activeTab === 'bulkUpload' ? 'show active' : '' ?>" id="bulkUpload">

<p>Download template and upload filled file.</p>

<a href="contact_template.xlsx" class="btn btn-outline-success mb-3">⬇ Download Excel Template</a>

<form method="POST" action="import_contacts.php" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="mb-3">
<input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
</div>

<button class="btn btn-warning w-100">Upload Contacts</button>
</form>

</div>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
const imageInput = document.getElementById("imageInput");
const previewImage = document.getElementById("previewImage");
const messageText = document.getElementById("messageText");
const previewText = document.getElementById("previewText");

imageInput.addEventListener("change", function(){
const file = this.files[0];
if(!file) return;

if(file.size > 2*1024*1024){
alert("Image must be under 2MB");
this.value="";
return;
}

if(!["image/jpeg","image/png"].includes(file.type)){
alert("Only JPG or PNG allowed");
this.value="";
return;
}

const reader = new FileReader();
reader.onload = e => {
previewImage.src = e.target.result;
previewImage.style.display = "block";
};
reader.readAsDataURL(file);
});

messageText.addEventListener("input", function(){
previewText.textContent = this.value || "Your preview will appear here...";
});

document.getElementById("broadcastForm").addEventListener("submit", async function(e){
e.preventDefault();
const resultDiv = document.getElementById("broadcastResult");
resultDiv.innerHTML = `<div class="alert alert-info">Processing...</div>`;

try{
const res = await fetch("create_campaign.php", {
method:"POST",
body:new FormData(this)
});
const data = await res.json();

if(data.status === "success"){
resultDiv.innerHTML =
`<div class="alert alert-success">
Campaign queued to ${data.total_recipients} contacts ✔
</div>`;
}else{
resultDiv.innerHTML =
`<div class="alert alert-danger">${data.message}</div>`;
}
}catch(err){
resultDiv.innerHTML =
`<div class="alert alert-danger">Server error. Please try again.</div>`;
}
});
</script>

</body>
</html>