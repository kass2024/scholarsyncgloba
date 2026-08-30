<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/student_portal_schema.php';
require_once __DIR__ . '/../helpers/urls.php';
require_once __DIR__ . '/auth.php';

pcvc_student_portal_ensure_schema($conn);

$pageTitle = 'My info';
$appId = (int)($_SESSION['student_application_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT id, application_id, first_name, middle_name, last_name, email, area_code, phone_number, gender, dob,
           nationality, city, address_line1, passport_number,
           destination, intended_study_level, bachelor_program, masters_program, phd_program,
           created_at, updated_at
    FROM student_applications
    WHERE id = ?
    LIMIT 1
");
$student = null;
if ($stmt) {
    $stmt->bind_param('i', $appId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$student) {
    $flash_error = 'Could not load your application record.';
}

require_once __DIR__ . '/layout.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 fw-bold mb-1">My information</h1>
    <div class="muted">Read-only view. Use Edit to complete missing fields.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(pcvc_url('/student/edit_profile.php'), ENT_QUOTES, 'UTF-8') ?>">Edit</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(pcvc_url('/student/index.php'), ENT_QUOTES, 'UTF-8') ?>">Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3">Identity</h2>
        <dl class="row mb-0">
          <dt class="col-5 muted">Application ID</dt>
          <dd class="col-7 fw-semibold"><?= htmlspecialchars((string)($student['application_id'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>

          <dt class="col-5 muted">Full name</dt>
          <dd class="col-7 fw-semibold">
            <?= htmlspecialchars(trim((string)($student['first_name'] ?? '') . ' ' . (string)($student['middle_name'] ?? '') . ' ' . (string)($student['last_name'] ?? '')) ?: '—', ENT_QUOTES, 'UTF-8') ?>
          </dd>

          <dt class="col-5 muted">Email</dt>
          <dd class="col-7"><?= htmlspecialchars((string)($student['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>

          <dt class="col-5 muted">Phone</dt>
          <dd class="col-7"><?= htmlspecialchars(trim((string)($student['area_code'] ?? '') . ' ' . (string)($student['phone_number'] ?? '')) ?: '—', ENT_QUOTES, 'UTF-8') ?></dd>

          <dt class="col-5 muted">DOB</dt>
          <dd class="col-7"><?= htmlspecialchars((string)($student['dob'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>

          <dt class="col-5 muted">Gender</dt>
          <dd class="col-7"><?= htmlspecialchars((string)($student['gender'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
        </dl>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3">Address & documents</h2>
        <dl class="row mb-0">
          <dt class="col-5 muted">Nationality</dt>
          <dd class="col-7"><?= htmlspecialchars((string)($student['nationality'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>

          <dt class="col-5 muted">City</dt>
          <dd class="col-7"><?= htmlspecialchars((string)($student['city'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>

          <dt class="col-5 muted">Address</dt>
          <dd class="col-7"><?= htmlspecialchars((string)($student['address_line1'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>

          <dt class="col-5 muted">Passport #</dt>
          <dd class="col-7"><?= htmlspecialchars((string)($student['passport_number'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
        </dl>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3">Study plan</h2>
        <div class="row g-3">
          <div class="col-md-4">
            <div class="muted small fw-semibold">Destination</div>
            <div class="fw-semibold"><?= htmlspecialchars((string)($student['destination'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="col-md-4">
            <div class="muted small fw-semibold">Intended level</div>
            <div class="fw-semibold"><?= htmlspecialchars((string)($student['intended_study_level'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="col-md-4">
            <div class="muted small fw-semibold">Program</div>
            <div class="fw-semibold">
              <?= htmlspecialchars((string)($student['masters_program'] ?: ($student['bachelor_program'] ?: ($student['phd_program'] ?: '—'))), ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>
        </div>
        <hr class="my-3">
        <div class="small muted">
          Last updated: <?= htmlspecialchars((string)($student['updated_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>

