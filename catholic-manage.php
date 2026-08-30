<?php
require_once __DIR__ . '/db.php';
// Handle new record submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new'])) {
  $destination = strtolower(trim($_POST['destination']));

  if ($destination === 'malta') {
    // Insert into malta_applications
    $stmt = $conn->prepare("INSERT INTO malta_applications (
      name, surname, email, contact_number, gender, dob, nationality, birth_place, address,
      degree_program, session_from, session_to, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    $stmt->bind_param("ssssssssssss",
      $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone_number'],
      $_POST['gender'], $_POST['dob'], $_POST['nationality'], $_POST['city'],
      $_POST['address_line1'], $_POST['masters_program'],
      $_POST['application_date'], $_POST['application_date']
    );
  } elseif ($destination === 'turkey') {
    // Insert into turkey_applications
    $stmt = $conn->prepare("INSERT INTO turkey_applications (
      first_name, last_name, email, mobile, gender, dob, nationality, city, address
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("sssssssss",
      $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone_number'],
      $_POST['gender'], $_POST['dob'], $_POST['nationality'], $_POST['city'], $_POST['address_line1']
    );
  } else {
    // Default to student_applications
    $stmt = $conn->prepare("INSERT INTO student_applications (
      first_name, last_name, email, phone_number, gender, dob, nationality, city, address_line1,
      masters_program, destination, application_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssssssssss",
      $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone_number'],
      $_POST['gender'], $_POST['dob'], $_POST['nationality'], $_POST['city'], $_POST['address_line1'],
      $_POST['masters_program'], $_POST['destination'], $_POST['application_date']
    );
  }

  $stmt->execute();
  $stmt->close();
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

// Fetch from student_applications
$query = $conn->query("SELECT id, first_name, last_name, email, phone_number, gender, dob, nationality, city, address_line1,
  masters_program, destination, application_date, application_id, application_remarks,
  incomplete_app, submitted,
  admit, i20_sent, sevis_paid, visa_scheduled, visa_approved,
  enrolled, addn_doc, deny, app_start 
  FROM student_applications 
  WHERE university_id = 1 AND region_id = 1
  ORDER BY 
    visa_approved DESC,
    admit DESC,
    deny DESC,
    submitted DESC,
    id DESC");

$students = $query->fetch_all(MYSQLI_ASSOC);



// Tag each row with its source table
foreach ($students as &$s) $s['source'] = 'student_applications';

// Combine all applicants
$all_applicants = $students;

$universities_for_admission = [];
$uq = @$conn->query('SELECT id, name FROM universities ORDER BY name ASC');
if ($uq) {
    while ($ur = $uq->fetch_assoc()) {
        $universities_for_admission[] = $ur;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
  <title>CUA | Applicants Management Portal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
html, body {
  height: 100%; /* 🔥 KEY ADDITION */
}

body {
  background: #eef2f7;
  font-family: 'Segoe UI', 'Helvetica Neue', sans-serif;
  font-size: 16px;
  color: #212529;
  margin: 0;
  padding: 20px;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

h3 {
  font-weight: 700;
  font-size: 28px;
  color: #003366;
  display: flex;
  align-items: center;
  margin-bottom: 20px;
}

h3::before {
  content: '🎓';
  margin-right: 12px;
  font-size: 30px;
}

.search-box {
  max-width: 450px;
  margin-bottom: 20px;
  padding: 10px 15px;
  border-left: 5px solid #0d6efd;
  border-radius: 5px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  font-size: 16px;
}

.scrollable-table-wrapper {
  flex: 1 1 auto;
  overflow-y: auto;
  overflow-x: auto;
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  min-height: 0; /* 🔥 KEY ADDITION */
}

.scrollable-table-wrapper table {
  width: max-content;
  min-width: 100%; /* 🔥 KEY ADDITION */
  table-layout: auto;
}

.scrollable-table-wrapper thead th {
  position: sticky;
  top: 0;
  background-color: #003366 !important;
  color: #fff !important;
  z-index: 11;
  text-align: center;
  font-size: 14px;
  text-transform: uppercase;
}

/* === ADD COLUMN WIDTHS TO PREVENT CUTTING === */
.scrollable-table-wrapper table th:nth-child(9),
.scrollable-table-wrapper table th:nth-child(10),
.scrollable-table-wrapper table th:nth-child(11),
.scrollable-table-wrapper table th:nth-child(12),
.scrollable-table-wrapper table th:nth-child(13),
.scrollable-table-wrapper table th:nth-child(14),
.scrollable-table-wrapper table th:nth-child(15) {
  min-width: 240px;
  max-width: 400px;
}

table {
  margin: 0;
  background: #fff;
  border-collapse: collapse;
}

.table td {
  text-align: center;
  vertical-align: middle;
  font-size: 15px;
  padding: 12px 14px;
  white-space: normal;
  word-wrap: break-word;
}

.editable-cell {
  cursor: pointer;
}

.btn-flag {
  margin: 2px 0;
  width: 100%;
  font-size: 12px;
  font-weight: 500;
  padding: 5px 8px;
  border-radius: 5px;
  white-space: nowrap;
}

.btn-flag:disabled {
  font-weight: bold;
  opacity: 0.9;
  color: #fff;
}

.flag-column {
  min-width: 110px;
  max-width: 115px;
}

.form-control-sm {
  font-size: 14px;
  padding: 6px 10px;
  border-radius: 6px;
}

textarea.form-control-sm {
  min-height: 50px;
}

.saving-text {
  font-size: 12px;
  color: #6c757d;
  font-style: italic;
  margin-top: 4px;
}

.nowrap {
  white-space: nowrap;
}

.btn-outline-light {
  border: 1px solid #ccc;
  color: #555;
  background-color: #f8f9fa;
}

.btn-light:disabled {
  color: #000 !important;
  background-color: #f0f0f0 !important;
  border: 1px solid #bbb !important;
}

/* Responsive */
@media (max-width: 1200px) {
  .table td, .scrollable-table-wrapper thead th { font-size: 14px; padding: 10px 10px; }
}

@media (max-width: 992px) {
  .table td, .scrollable-table-wrapper thead th { font-size: 13px; padding: 8px 8px; }
  .btn-flag { font-size: 11px; }
  h3 { font-size: 22px; }
  .form-control-sm { font-size: 13px; }
}

@media (max-width: 768px) {
  .table td, .scrollable-table-wrapper thead th { font-size: 12px; padding: 6px 6px; }
  .btn-flag { font-size: 10px; padding: 4px 6px; }
  h3 { font-size: 20px; }
  .search-box { max-width: 100%; font-size: 14px; padding: 8px 12px; }
}

@media (max-width: 576px) {
  body { padding: 10px; }
  h3 { font-size: 18px; margin-bottom: 15px; }
  .search-box { margin-bottom: 15px; }
  .btn { font-size: 14px; padding: 6px 10px; }
}
</style>


</head>

<body>
<h3>CUA Applicants Management Portal</h3>

<div class="d-flex mb-3">
  <a href="admin-dashboard.php" class="btn btn-warning me-2">🏠 Back to Admin Dashboard</a>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRecordModal">Add New Record</button>
</div>

<!-- 🔍 Search Bar -->
<input type="text" id="searchInput" class="form-control search-box" placeholder="🔍 Search Name, Email, Program, Destination...">

<!-- Add Record Modal -->
<div class="modal fade" id="addRecordModal" tabindex="-1" aria-labelledby="addRecordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-4 shadow-lg">
      <form method="post">
        <div class="modal-header bg-primary text-white rounded-top-4">
          <h5 class="modal-title" id="addRecordModalLabel">Add New Applicant</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-6"><input type="text" name="first_name" class="form-control" placeholder="Enter First Name" required></div>
            <div class="col-md-6"><input type="text" name="last_name" class="form-control" placeholder="Enter Last Name" required></div>
            <div class="col-md-6"><input type="email" name="email" class="form-control" placeholder="Enter Email Address" required></div>
            <div class="col-md-6"><input type="text" name="phone_number" class="form-control" placeholder="Enter Phone Number" required></div>
            <div class="col-md-6">
              <select name="gender" class="form-select" required>
                <option value="" selected disabled>Select Gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-md-6"><input type="text" name="dob" class="form-control datepicker" placeholder="Date of Birth" required></div>
            <div class="col-md-6"><input type="text" name="nationality" class="form-control" placeholder="Enter Nationality" required></div>
            <div class="col-md-6"><input type="text" name="city" class="form-control" placeholder="Enter City" required></div>
            <div class="col-12"><input type="text" name="address_line1" class="form-control" placeholder="Enter Full Address" required></div>
            <div class="col-md-6"><input type="text" name="masters_program" class="form-control" placeholder="Intended Master's Program" required></div>
            <div class="col-md-6"><input type="text" name="destination" class="form-control" placeholder="Study Destination (e.g., Canada, USA)" required></div>
            <div class="col-12"><input type="text" name="application_date" class="form-control datepicker" placeholder="Application Submission Date" required></div>
          </div>
        </div>
        <div class="modal-footer bg-light rounded-bottom-4">
          <button type="submit" name="add_new" class="btn btn-success px-4">💾 Save</button>
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Table Section -->
<div class="table-responsive scrollable-table-wrapper mt-3">
  <table class="table table-bordered table-hover table-striped bg-white" id="applicantTable">
    <thead class="text-center">
      <tr>
        <th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Gender</th><th>DOB</th>
        <th>Nationality</th><th>City</th><th>Address</th><th>Program</th><th>Destination</th>
        <th>Applied On</th><th>Status</th><th>App ID</th><th>Remarks</th>
      </tr>
    </thead>
    <tbody>
<?php $counter = 1; foreach ($all_applicants as $s): ?>
  <tr data-row-id="<?= $s['id'] ?>" data-source="<?= $s['source'] ?>">
    <td><?= $counter++ ?></td>

    <!-- Name (first + last) -->
    <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>" data-field="first_name">
      <?= ucfirst((string) $s['first_name']) . ' ' . ucfirst((string) $s['last_name']) ?>
    </td>

    <!-- Email -->
    <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>" data-field="email"><?= $s['email'] ?? '' ?></td>

    <!-- Phone Number -->
    <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>"
        data-field="<?= $s['source'] === 'malta_applications' ? 'contact_number' : ($s['source'] === 'turkey_applications' ? 'mobile' : 'phone_number') ?>">
      <?= $s['phone_number'] ?? '' ?>
    </td>

    <!-- Gender -->
    <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>" data-field="gender"><?= $s['gender'] ?? '' ?></td>

    <!-- DOB -->
    <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>" data-field="dob"><?= $s['dob'] ?? '' ?></td>

    <!-- Nationality -->
    <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>" data-field="nationality"><?= $s['nationality'] ?? '' ?></td>

    <!-- City / Birthplace -->
    <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>"
        data-field="<?= $s['source'] === 'malta_applications' ? 'birth_place' : 'city' ?>">
      <?= $s['city'] ?? '' ?>
    </td>

    <!-- Address -->
    <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>"
        data-field="<?= $s['source'] === 'malta_applications' ? 'address' : 'address_line1' ?>">
      <?= $s['address_line1'] ?? '' ?>
    </td>

    <!-- Master's Program -->
    <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>"
        data-field="<?= $s['source'] === 'malta_applications' ? 'degree_program' : 'masters_program' ?>">
      <?= $s['masters_program'] ?? '' ?>
    </td>

    <!-- Destination -->
    <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>" data-field="destination"><?= $s['destination'] ?? '' ?></td>

    <!-- Application Date -->
    <td contenteditable="true" class="editable-cell" data-id="<?= $s['id'] ?>"
        data-field="<?= $s['source'] === 'malta_applications' ? 'created_at' : ($s['source'] === 'turkey_applications' ? 'submitted_at' : 'application_date') ?>">
      <?= $s['application_date'] ?? '' ?>
    </td>

    <!-- Status -->
    <td class="flag-column text-center">
      <div class="flag-wrapper" data-id="<?= $s['id'] ?>">
        <?php
          $table = $s['source'] ?? 'student_applications';
          $flags = [
            'incomplete_app' => ['Incomplete App', 'light'],
            'submitted' => ['Submitted', 'secondary'],
            'admit' => ['Admit', 'primary'],
            'i20_sent' => ['I-20 Sent', 'info'],
            'sevis_paid' => ['Sevis Paid', 'secondary'],
            'visa_scheduled' => ['Visa Sch.', 'warning'],
            'visa_approved' => ['Visa OK', 'success'],
            'enrolled' => ['Enrolled', 'success'],
            'addn_doc' => ['Add Doc', 'dark'],
            'deny' => ['Deny', 'danger'],
            'app_start' => ['App Start', 'secondary']
          ];
          foreach ($flags as $key => [$label, $color]) {
            $status = $s[$key] ?? 0;
            $btnClass = $status ? "btn-$color" : "btn-outline-$color";
            $text = $status ? '✔ ' . $label : $label;
            $disabled = $status ? 'disabled' : '';
            echo "<button class='btn btn-sm $btnClass btn-flag' data-id='{$s['id']}' data-flag='$key' data-table='$table' $disabled>$text</button>";
          }
        ?>
      </div>
    </td>

    <!-- Application ID -->
    <td>
      <input type="text" class="form-control form-control-sm live-app-id" data-id="<?= $s['id'] ?>" value="<?= htmlspecialchars($s['application_id'] ?? '') ?>">
    </td>

    <!-- Remarks -->
    <td>
      <textarea class="form-control form-control-sm live-app-remarks" data-id="<?= $s['id'] ?>"><?= htmlspecialchars($s['application_remarks'] ?? '') ?></textarea>
    </td>
  </tr>
<?php endforeach; ?>
</tbody>

  </table>
</div>
<!-- Admission Letter Modal (multi-university, one email) -->
<div class="modal fade" id="admissionModal" tabindex="-1" aria-labelledby="admissionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form id="admissionForm" enctype="multipart/form-data" autocomplete="off" novalidate>
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="admissionModalLabel">Send Admission Letter(s)</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="student_id" id="modal_student_id">
          <input type="hidden" name="table" id="modal_table">

          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="modal_email" class="form-control" readonly>
          </div>

          <p class="small text-muted mb-2">Add one row per university. All letters are sent in a single email.</p>

          <div id="admissionRows" class="mb-2"></div>

          <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="btnAddAdmissionRow">+ Add another university</button>

          <div id="sendingProgress" style="display:none;" class="text-info fw-bold mt-2">
            ⏳ Sending email... Please wait.
          </div>

          <div id="sendResult" class="mt-2 fw-semibold"></div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success">📧 Send all letters</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<template id="admissionRowTpl">
  <div class="admission-row border rounded p-2 mb-2 bg-light">
    <div class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label small mb-0">University</label>
        <select name="university_id[]" class="form-select form-select-sm admission-uni-select">
          <option value="">Select university…</option>
          <?php foreach ($universities_for_admission as $uu): ?>
            <option value="<?= (int) $uu['id'] ?>"><?= htmlspecialchars((string) $uu['name'], ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <label class="form-label small mb-0">Admission letter (PDF)</label>
        <input type="file" name="letters[]" class="form-control form-control-sm admission-letter-file" accept=".pdf,application/pdf">
      </div>
      <div class="col-md-1 text-end pb-1">
        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-admission-row d-none" title="Remove row">×</button>
      </div>
    </div>
  </div>
</template>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
$(function() {
  // SEARCH
  $('#searchInput').on('keyup', function(){
    const value = $(this).val().toLowerCase();
    $('#applicantTable tbody tr').filter(function(){
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
    });
  });

  // FLAGS CLICK (with table support)
  $(document).on('click', '.btn-flag', function(){
    const btn = $(this);
    const id = btn.data('id');
    const flag = btn.data('flag');
    const table = btn.closest('tr').data('source');

    // If "Admit" button is clicked and it's not yet active
    if (flag === 'admit' && !btn.prop('disabled')) {
      const row = btn.closest('tr');
      const email = row.find('td[data-field="email"]').text().trim();

      $('#modal_student_id').val(id);
      $('#modal_table').val(table);
      $('#modal_email').val(email);

      const admEl = document.getElementById('admissionModal');
      if (admEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(admEl, { backdrop: 'static', keyboard: false }).show();
      }
      return;
    }

    // Otherwise: normal flag update
    $.post("update-flag.php", { id, flag, table }, function(resp){
      if(resp === 'ok'){
        $.get('render-flags.php', { id, table }, function(html){
          $('.flag-wrapper[data-id="' + id + '"]').html(html);
        });
      } else {
        alert('Failed to update flag');
      }
    });
  });

  // Application ID live update
  $('.live-app-id').on('input', function(){
    const id = $(this).data('id');
    const value = $(this).val();
    $.post("update-static.php", { id, application_id: value }, function(resp){
      console.log(resp);
    });
  });

  // Remarks live update
  $('.live-app-remarks').on('input', function(){
    const id = $(this).data('id');
    const value = $(this).val();
    $.post("update-static.php", { id, application_remarks: value }, function(resp){
      console.log(resp);
    });
  });

  // Editable fields update
  $(document).on('blur', '.editable-cell', function() {
    const cell = $(this);
    const id = cell.data('id');
    const field = cell.data('field');
    const value = cell.text().trim();

    $.post('update-field.php', { id, field, value }, function(resp) {
      if (resp !== 'ok') {
        alert('Failed to save field');
      }
    });
  });

  // DATE PICKER
  flatpickr(".datepicker", {
    altInput: true,
    altFormat: "F j, Y",
    dateFormat: "Y-m-d",
    maxDate: "today"
  });

  function updateAdmissionRemoveButtons() {
    const rows = document.querySelectorAll('#admissionRows .admission-row');
    rows.forEach(function (row) {
      const b = row.querySelector('.btn-remove-admission-row');
      if (!b) return;
      if (rows.length > 1) {
        b.classList.remove('d-none');
      } else {
        b.classList.add('d-none');
      }
    });
  }

  function addAdmissionRow() {
    const tpl = document.getElementById('admissionRowTpl');
    const container = document.getElementById('admissionRows');
    if (!tpl || !container || !tpl.content) return;
    container.appendChild(document.importNode(tpl.content, true));
    updateAdmissionRemoveButtons();
  }

  function resetAdmissionRows() {
    const container = document.getElementById('admissionRows');
    if (!container) return;
    container.innerHTML = '';
    addAdmissionRow();
  }

  $('#admissionModal').on('show.bs.modal', function () {
    $('#sendResult').text('').removeClass('text-success text-danger fw-bold');
    $('#sendingProgress').hide();
    resetAdmissionRows();
  });

  $('#btnAddAdmissionRow').on('click', function () {
    addAdmissionRow();
  });

  $(document).on('click', '.btn-remove-admission-row', function () {
    const rows = document.querySelectorAll('#admissionRows .admission-row');
    if (rows.length <= 1) return;
    $(this).closest('.admission-row').remove();
    updateAdmissionRemoveButtons();
  });

  $('#admissionForm').on('submit', function(e){
    e.preventDefault();
    $('#sendResult').text('').removeClass('text-success text-danger fw-bold');

    const email = ($('#modal_email').val() || '').trim();
    if (!email) {
      $('#sendResult').text('❌ Applicant email is missing.').addClass('text-danger fw-bold');
      return;
    }

    let completeRows = 0;
    let brokenRow = false;
    $('#admissionRows .admission-row').each(function () {
      const uni = ($(this).find('.admission-uni-select').val() || '').trim();
      const f = $(this).find('.admission-letter-file')[0];
      const hasFile = f && f.files && f.files.length > 0;
      if (!uni && !hasFile) return;
      if (uni && hasFile) {
        completeRows++;
        return;
      }
      brokenRow = true;
      return false;
    });

    if (brokenRow) {
      $('#sendResult').text('❌ Each row needs both a university and a PDF (or leave the row empty).').addClass('text-danger fw-bold');
      return;
    }
    if (completeRows < 1) {
      $('#sendResult').text('❌ Add at least one university and attach its PDF letter.').addClass('text-danger fw-bold');
      return;
    }

    const formData = new FormData(this);
    const sid = $('#modal_student_id').val();
    const tbl = $('#modal_table').val();

    $('#sendingProgress').show();

    $.ajax({
      url: 'send_admission.php',
      method: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(resp) {
        $('#sendingProgress').hide();
        if (resp.trim() === 'ok') {
          $('#sendResult').text('✅ Admission email sent successfully!').addClass('text-success fw-bold');
          $.get('render-flags.php', { id: sid, table: tbl }, function(html){
            $('.flag-wrapper[data-id="' + sid + '"]').html(html);
          });
          setTimeout(() => {
            const inst = bootstrap.Modal.getInstance(document.getElementById('admissionModal'));
            if (inst) inst.hide();
            $('#admissionForm')[0].reset();
            $('#sendResult').text('');
            resetAdmissionRows();
          }, 2000);
        } else {
          $('#sendResult').text('❌ Failed to send: ' + resp).addClass('text-danger fw-bold');
        }
      },
      error: function(xhr, status, error) {
        $('#sendingProgress').hide();
        $('#sendResult').text('❌ Network error: ' + error).addClass('text-danger fw-bold');
      }
    });
  });

  $('#admissionModal').on('hidden.bs.modal', function () {
    $('#admissionForm')[0].reset();
    $('#sendResult').text('').removeClass('text-success text-danger fw-bold');
    resetAdmissionRows();
  });
});
</script>


</body>
</html>
