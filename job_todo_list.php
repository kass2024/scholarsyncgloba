<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';
if (!isset($_SESSION['id'], $_SESSION['role'])) {
    header("Location: admin-login.php");
    exit;
}

$admin_id = (int)$_SESSION['id'];
$role = $_SESSION['role'];
$isSuperadmin = pcvc_current_user_is_superadmin($conn);

/* ================= AJAX: DELETE JOB (superadmin — any assignee/role) ================= */
if (isset($_POST['ajax']) && $_POST['ajax'] === 'delete_job') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$isSuperadmin) {
        echo json_encode(['success' => false, 'message' => 'Only superadmin can delete jobs.']);
        exit;
    }

    $jobId = (int) ($_POST['job_id'] ?? 0);
    if ($jobId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid job.']);
        exit;
    }

    $stmt = $conn->prepare('SELECT screenshot_path FROM job_list WHERE id = ? LIMIT 1');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit;
    }
    $stmt->bind_param('i', $jobId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM job_list WHERE id = ? LIMIT 1');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit;
    }
    $stmt->bind_param('i', $jobId);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$deleted) {
        echo json_encode(['success' => false, 'message' => 'Job not found or already deleted.']);
        exit;
    }

    $shot = trim((string) ($row['screenshot_path'] ?? ''));
    if ($shot !== '' && is_file(__DIR__ . '/' . ltrim($shot, '/'))) {
        @unlink(__DIR__ . '/' . ltrim($shot, '/'));
    }

    echo json_encode(['success' => true, 'message' => 'Job deleted.']);
    exit;
}

/* ================= AJAX: SAVE COMMENT ================= */
if (isset($_POST['ajax']) && $_POST['ajax'] === 'save_comment') {

    $job_id  = (int)$_POST['job_id'];
    $comment = trim($_POST['comment'] ?? '');

    mysqli_query($conn, "
        UPDATE job_list 
        SET comment = '".mysqli_real_escape_string($conn,$comment)."'
        WHERE id = $job_id
    ");

    echo json_encode(['success'=>true]);
    exit;
}
/* ================= AJAX: FETCH JOBS ================= */
if (isset($_POST['ajax']) && $_POST['ajax'] === 'fetch_jobs') {

    $status   = $_POST['status'] ?? '';
    $period   = $_POST['period'] ?? '';
    $search   = strtolower(trim($_POST['search'] ?? ''));
    $staffRaw = $_POST['staff'] ?? '0';
    $staff_id = ($staffRaw === '' || $staffRaw === null) ? 0 : (int) $staffRaw;

    $where = [];

    if ($isSuperadmin) {
        if ($staff_id > 0) {
            $where[] = "j.admin_id = $staff_id";
        } elseif ($staff_id === -1) {
            // All jobs (every role / assignee)
        } else {
            $where[] = "j.admin_id = $admin_id";
        }
    } else {
        $where[] = "j.admin_id = $admin_id";
    }

    if ($status === 'completed') {
        $where[] = "j.status = 'completed'";
    } elseif ($status === 'not_completed') {
        $where[] = "j.status = 'not_completed'";
    }

    if ($period === 'today') {
        $where[] = "DATE(j.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $where[] = "YEARWEEK(j.created_at) = YEARWEEK(CURDATE())";
    } elseif ($period === 'month') {
        $where[] = "MONTH(j.created_at) = MONTH(CURDATE())
                    AND YEAR(j.created_at) = YEAR(CURDATE())";
    }

    if ($search !== '') {
        $s = mysqli_real_escape_string($conn, $search);
        $where[] = "(
            LOWER(j.title) LIKE '%$s%' OR
            LOWER(j.applicant_name) LIKE '%$s%' OR
            LOWER(j.applicant_email) LIKE '%$s%'
        )";
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $query = "
        SELECT j.*
        FROM job_list j
        $where_sql
        ORDER BY j.id DESC
    ";

    $jobs = mysqli_query($conn, $query);

    if (!$jobs || mysqli_num_rows($jobs) === 0) {
        echo '<p class="empty">No jobs found.</p>';
        exit;
    }

    while ($job = mysqli_fetch_assoc($jobs)) {

        $isAdmission = ($job['job_type'] === 'Student Admission Application');
        $disabled    = ($job['status'] === 'completed') ? 'disabled' : '';

        echo '<div class="job-card'.($isAdmission ? ' admission' : '').'">';

            /* LEFT SIDE — JOB INFO */
            echo '<div class="job-left">';
                echo '<div class="job-title">'.htmlspecialchars($job['title']).'</div>';
                echo '<div class="job-sub">'
                        .htmlspecialchars($job['applicant_name'])
                        .' · '
                        .htmlspecialchars($job['applicant_email'])
                     .'</div>';
            echo '</div>';

            /* RIGHT SIDE — STATUS + COMMENT */
            echo '<div class="job-actions">';

                if ($job['status'] === 'not_completed') {
                    echo '
                    <span class="status-badge not_completed open-report"
                          data-job-id="'.$job['id'].'"
                          data-job-type="'.$job['job_type'].'">
                          Not Completed ✕
                    </span>';
                } else {
                    echo '
                    <span class="status-badge completed">
                          Completed ✓
                    </span>';
                }

                echo '
                <textarea
                  class="job-comment"
                  data-id="'.$job['id'].'"
                  placeholder="Add comment…"
                  '.$disabled.'
                >'.htmlspecialchars($job['comment'] ?? '').'</textarea>
                ';

                if ($isSuperadmin) {
                    echo '
                    <button type="button" class="btn-delete-job" data-job-id="'.(int)$job['id'].'"
                      title="Delete job" aria-label="Delete job">🗑</button>
                    ';
                }

            echo '</div>'; // job-actions

        echo '</div>'; // job-card
    }

    exit;
}

/* ================= ADD JOB ================= */
if (isset($_POST['add_job'])) {
    $title = trim($_POST['title']);
    $name  = trim($_POST['applicant_name']);
    $email = trim($_POST['applicant_email']);
    $type  = trim($_POST['job_type']);

    if ($title && $name && $email && $type) {
        mysqli_query($conn,"
            INSERT INTO job_list (title, applicant_name, applicant_email, job_type, admin_id)
            VALUES (
              '".mysqli_real_escape_string($conn,$title)."',
              '".mysqli_real_escape_string($conn,$name)."',
              '".mysqli_real_escape_string($conn,$email)."',
              '".mysqli_real_escape_string($conn,$type)."',
              $admin_id
            )
        ");
    }
    header("Location: job_todo_list.php");
    exit;
}

$staffs = $isSuperadmin
    ? mysqli_query($conn, "
        SELECT id,
               COALESCE(NULLIF(TRIM(full_name), ''),
                        TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')))) AS full_name,
               role
        FROM admins
        ORDER BY full_name ASC, id ASC
      ")
    : null;

$jobSaveSuccess = '';
if (!empty($_SESSION['job_save_success'])) {
    $jobSaveSuccess = trim((string) $_SESSION['job_save_success']);
    unset($_SESSION['job_save_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Job To-Do List</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

<style>
:root{
  --primary:#2563eb;
  --danger:#dc2626;
  --success:#16a34a;
  --bg:#f5f7fb;
  --card:#ffffff;
  --border:#e5e7eb;
  --text:#1f2937;
  --muted:#6b7280;
}

body{
  background:linear-gradient(180deg,#f8fafc,#eef2ff);
  font-family:Inter,"Segoe UI",sans-serif;
  margin:0;
  padding:24px;
  color:var(--text);
}

.container{max-width:1200px;margin:auto;}

.header{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:24px;
}

.job-list{
  background:var(--card);
  border-radius:20px;
  padding:24px;
  box-shadow:0 25px 60px rgba(0,0,0,.08);
}

.add-job,.search-bar{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
  gap:14px;
  margin-bottom:20px;
}

input,select,button{
  padding:14px;
  border-radius:12px;
  border:1px solid var(--border);
  font-size:14px;
}

button{
  background:linear-gradient(135deg,#2563eb,#3b82f6);
  color:#fff;
  border:none;
  cursor:pointer;
  font-weight:600;
}

.job-card{
  display:flex;
  justify-content:space-between;
  align-items:center;
  padding:18px;
  border-bottom:1px solid var(--border);
  transition:.2s ease;
}

.job-card:hover{
  background:#f8fafc;
}

.job-title{
  font-weight:600;
  font-size:16px;
}

.job-sub{
  font-size:14px;
  color:var(--muted);
}

.status-badge{
  padding:10px 18px;
  border-radius:999px;
  font-weight:700;
  font-size:13px;
  cursor:pointer;
}

.not_completed{
  background:#fee2e2;
  color:var(--danger);
}

.completed{
  background:#dcfce7;
  color:var(--success);
  cursor:default;
}

/* 🔥 STUDENT ADMISSION */
.admission{
  background:linear-gradient(90deg,#eef2ff,#ffffff);
  border-left:6px solid var(--primary);
}

.locked{
  background:#eef2ff;
  border:2px dashed var(--primary);
}

.locked input:disabled,
.locked select:disabled{
  background:#e5e7eb;
  cursor:not-allowed;
}

.empty{text-align:center;color:#888;}
.job-actions{
  display:flex;
  align-items:center;
  gap:14px;
}

.job-comment{
  width:260px;
  min-height:42px;
  padding:10px 14px;
  border-radius:12px;
  border:1px solid var(--border);
  font-size:13px;
  font-family:inherit;
  background:#f9fafb;
  color:var(--text);
  resize:vertical;
  transition:all .2s ease;
}

.job-comment::placeholder{
  color:#9ca3af;
}

.job-comment:focus{
  outline:none;
  background:#ffffff;
  border-color:var(--primary);
  box-shadow:0 0 0 3px rgba(37,99,235,.12);
}

.job-comment:disabled{
  background:#f1f5f9;
  color:#64748b;
  cursor:not-allowed;
}

.btn-delete-job{
  padding:8px 12px;
  border-radius:10px;
  border:1px solid #fecaca;
  background:#fff;
  color:var(--danger);
  font-size:16px;
  line-height:1;
  cursor:pointer;
  flex-shrink:0;
}

.btn-delete-job:hover{
  background:#fee2e2;
  border-color:#fca5a5;
}

.success-banner{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:12px;
  margin-bottom:18px;
  padding:14px 16px;
  border-radius:12px;
  border:1px solid #86efac;
  background:#ecfdf5;
  color:#166534;
  font-size:14px;
  font-weight:600;
  line-height:1.45;
}

.success-banner button{
  margin:0;
  width:auto;
  padding:4px 10px;
  border-radius:8px;
  background:transparent;
  color:#166534;
  border:1px solid #86efac;
  font-size:18px;
  line-height:1;
}

.success-banner button:hover{
  background:#dcfce7;
}

</style>
</head>

<body>
<div class="container">

<div class="header">
  <h2>📋 Job To-Do List</h2>
  <a href="admin-dashboard.php">⬅ Dashboard</a>
</div>

<div class="job-list">

<?php if ($jobSaveSuccess !== ''): ?>
<div class="success-banner" id="jobSaveSuccess" role="status">
  <span>✅ <?= htmlspecialchars($jobSaveSuccess, ENT_QUOTES, 'UTF-8') ?></span>
  <button type="button" id="dismissJobSuccess" aria-label="Dismiss">×</button>
</div>
<?php endif; ?>

<form method="post" class="add-job" id="addJobForm">
  <input id="job_title" name="title" readonly required placeholder="Job Title">
  <select id="studentSearch"></select>
  <input id="applicant_name" name="applicant_name" required placeholder="Lead Name">
  <input id="applicant_email" name="applicant_email" required placeholder="Lead Email">

  <select id="job_type" name="job_type" required>
    <option value="">Select Job Type</option>
    <option>Student Admission Application</option>
    <option>Student Loan Application</option>
    <option>Student I-20 Application</option>
    <option>Student DS-160 Application</option>
    <option>Credit Transfer Application</option>
    <option>Student CAQ (Québec Acceptance Certificate) Application</option>
    <option>Student PAL (Provincial Attestation Letter) Application</option>
    <option>Student Visa Application</option>
    <option>Student PGWP (Post-Graduation Work Permit) Application</option>
    <option>Other Job</option>
  </select>

  <button name="add_job">＋ Add Job</button>
</form>

<div class="search-bar">
  <input id="searchTitle" placeholder="Search…">
  <select id="statusFilter"><option value="">All</option><option value="completed">Completed</option><option value="not_completed">Not Completed</option></select>
  <select id="timeFilter"><option value="">All Time</option><option value="today">Today</option><option value="week">Week</option><option value="month">Month</option></select>
  <?php if ($isSuperadmin): ?>
  <select id="staffFilter">
    <option value="-1">All jobs</option>
    <option value="0">My jobs</option>
    <?php while ($s = mysqli_fetch_assoc($staffs)): ?>
      <?php
        $staffLabel = trim((string) ($s['full_name'] ?? ''));
        if ($staffLabel === '') {
            $staffLabel = 'Admin #' . (int) $s['id'];
        }
        $staffRole = trim((string) ($s['role'] ?? ''));
        if ($staffRole !== '') {
            $staffLabel .= ' (' . $staffRole . ')';
        }
      ?>
      <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($staffLabel) ?></option>
    <?php endwhile; ?>
  </select>
  <?php endif; ?>
</div>

<div id="jobsContainer"></div>

</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(function(){

  const $successBanner = $('#jobSaveSuccess');
  if ($successBanner.length) {
    $('#dismissJobSuccess').on('click', function () {
      $successBanner.fadeOut(200, function () { $(this).remove(); });
    });
    setTimeout(function () {
      if ($successBanner.length) {
        $successBanner.fadeOut(400, function () { $(this).remove(); });
      }
    }, 6000);
  }

  function fetchJobs(){
    $.post('',{
      ajax:'fetch_jobs',
      status:$('#statusFilter').val(),
      period:$('#timeFilter').val(),
      search:$('#searchTitle').val(),
      staff:$('#staffFilter').val()
    },res=>$('#jobsContainer').html(res));
  }

  fetchJobs();
  $('#statusFilter,#timeFilter,#staffFilter').on('change',fetchJobs);
  $('#searchTitle').on('keyup',fetchJobs);

  $('#studentSearch').select2({
    ajax:{
      url:'student_search.php',
      dataType:'json',
      delay:250,
      data:p=>({q:p.term}),
      processResults:d=>({results:d})
    }
  }).on('select2:select',e=>{
    $('#applicant_name').val(e.params.data.text);
    $('#applicant_email').val(e.params.data.email);
  });

$('#job_type').on('change', function () {

  const jobType = $(this).val();
  const isAdmission = jobType === 'Student Admission Application';
  const isOtherJob  = jobType === 'Other Job';

  if (isOtherJob) {
    // 🔓 Allow manual job title
    $('#job_title')
      .val('')
      .prop('readonly', false)
      .attr('placeholder', 'Enter Job Title');
  } else {
    // 🔒 Auto-fill job title
    $('#job_title')
      .val(jobType)
      .prop('readonly', true);
  }

  // Admission-specific locking
  $('#studentSearch,#applicant_name,#applicant_email')
    .prop('disabled', isAdmission);

  $('#addJobForm').toggleClass('locked', isAdmission);

  if (isAdmission) {
    $('#studentSearch').val(null).trigger('change');
    $('#applicant_name,#applicant_email').val('');
  }
});


  $(document).on('click', '.btn-delete-job', function () {
    const jobId = $(this).data('job-id');
    const title = $(this).closest('.job-card').find('.job-title').text().trim() || 'this job';
    if (!confirm('Delete "' + title + '" permanently?\n\nThis cannot be undone.')) {
      return;
    }
    const btn = $(this);
    btn.prop('disabled', true);
    $.ajax({
      url: '',
      type: 'POST',
      dataType: 'json',
      data: { ajax: 'delete_job', job_id: jobId }
    }).done(function (res) {
      if (res && res.success) {
        fetchJobs();
      } else {
        alert((res && res.message) ? res.message : 'Could not delete job.');
        btn.prop('disabled', false);
      }
    }).fail(function () {
      alert('Could not delete job.');
      btn.prop('disabled', false);
    });
  });

  /* 🔥 CLICK EVENT RESTORED */
  $(document).on('click','.open-report',function(){
    const id = $(this).data('job-id');
    const type = $(this).data('job-type');
    window.location.href = (type==='Other Job')
      ? 'job-entry.php?job_id='+id
      : 'student_app.php?job_id='+id;
  });

});
/* ================= SAVE COMMENT (AUTO) ================= */
let commentTimer = null;

$(document).on('input', '.job-comment', function () {

  const textarea = $(this);
  const jobId = textarea.data('id');

  clearTimeout(commentTimer);

  commentTimer = setTimeout(function () {
    $.ajax({
      url: '',
      type: 'POST',
      dataType: 'json',
      data: {
        ajax: 'save_comment',
        job_id: jobId,
        comment: textarea.val()
      }
    });
  }, 600); // save after typing stops
});

</script>
</body>
</html>
