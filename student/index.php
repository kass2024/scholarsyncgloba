<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/student_portal_schema.php';
require_once __DIR__ . '/../helpers/urls.php';
require_once __DIR__ . '/auth.php';

pcvc_student_portal_ensure_schema($conn);

$pageTitle = 'Smart Dashboard';

$accountId = (int)($_SESSION['student_account_id'] ?? 0);
$email = strtolower(trim((string)($_SESSION['student_email'] ?? '')));

// 1) Full profile: latest student_applications row by email (if exists)
$student = null;
$appId = 0;
if ($email !== '') {
    $st = $conn->prepare("
        SELECT *
        FROM student_applications
        WHERE LOWER(TRIM(email)) = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    if ($st) {
        $st->bind_param('s', $email);
        $st->execute();
        $student = $st->get_result()->fetch_assoc();
        $st->close();
        if ($student) {
            $appId = (int)($student['id'] ?? 0);
            $_SESSION['student_application_id'] = $appId; // keep session aligned
        }
    }
}

// 2) Credit transfer: latest by email
$credit = null;
if ($email !== '') {
    $st = $conn->prepare("
        SELECT *
        FROM credit_transfer_applications
        WHERE LOWER(TRIM(email)) = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    if ($st) {
        $st->bind_param('s', $email);
        $st->execute();
        $credit = $st->get_result()->fetch_assoc();
        $st->close();
    }
}

// 3) Master loan: latest by email
$loan = null;
if ($email !== '') {
    $st = $conn->prepare("
        SELECT *
        FROM master_loan_applications
        WHERE LOWER(TRIM(email)) = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    if ($st) {
        $st->bind_param('s', $email);
        $st->execute();
        $loan = $st->get_result()->fetch_assoc();
        $st->close();
    }
}

// 4) Student contracts: by student_id when profile exists
$contracts = [];
if ($appId > 0) {
    $st = $conn->prepare("
        SELECT id, contract_token, status, signed_at, sent_at
        FROM student_contracts
        WHERE student_id = ?
        ORDER BY id DESC
        LIMIT 10
    ");
    if ($st) {
        $st->bind_param('i', $appId);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $contracts[] = $row;
        }
        $st->close();
    }
}

// 5) Study choices: all selections saved from student-application.php
$studyChoices = [];
if ($appId > 0) {
    $st = $conn->prepare("
        SELECT
            ascx.id,
            ascx.region_id,
            r.name AS region_name,
            ascx.university_id,
            u.name AS university_name,
            ctry.name AS country_name,
            ascx.program_level_id,
            pl.name AS program_level_name,
            ascx.program_id,
            p.program_name
        FROM application_study_choices ascx
        LEFT JOIN regions r ON r.id = ascx.region_id
        LEFT JOIN universities u ON u.id = ascx.university_id
        LEFT JOIN countries ctry ON ctry.id = u.country_id
        LEFT JOIN program_levels pl ON pl.id = ascx.program_level_id
        LEFT JOIN programs p ON p.id = ascx.program_id
        WHERE ascx.application_id = ?
        ORDER BY ascx.id ASC
    ");
    if ($st) {
        $st->bind_param('i', $appId);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $studyChoices[] = $row;
        }
        $st->close();
    }
}

function pcvc_current_stage(array $s): array
{
    // Highest priority first.
    $stages = [
        'visa_approved' => 'Visa approved',
        'enrolled' => 'Enrolled',
        'visa_scheduled' => 'Visa interview scheduled',
        'sevis_paid' => 'SEVIS paid',
        'i20_sent' => 'I-20 sent',
        'admit' => 'Admission',
        'app_paid' => 'Application paid',
        'submitted' => 'Submitted',
        'incomplete_app' => 'Incomplete application',
        'app_start' => 'Application started',
    ];
    if (!empty($s['deny'])) {
        return ['Visa denied', 'danger'];
    }
    if (!empty($s['addn_doc'])) {
        return ['Additional documents required', 'warning'];
    }
    foreach ($stages as $k => $label) {
        if (!empty($s[$k])) {
            return [$label, 'primary'];
        }
    }
    return ['In review', 'secondary'];
}

[$stageLabel, $stageTone] = $student ? pcvc_current_stage($student) : ['', 'secondary'];

$uploadsCount = 0;
$stmtU = $conn->prepare("SELECT COUNT(*) AS c FROM student_portal_uploads WHERE student_account_id = ?");
if ($stmtU) {
    $stmtU->bind_param('i', $accountId);
    $stmtU->execute();
    $row = $stmtU->get_result()->fetch_assoc();
    $stmtU->close();
    $uploadsCount = (int)($row['c'] ?? 0);
}

require_once __DIR__ . '/layout.php';
?>

<?php
$hasAny = (bool)$student || (bool)$credit || (bool)$loan || !empty($contracts);
?>

<?php if (!$hasAny): ?>
  <div class="card">
    <div class="card-body">
      <h1 class="h5 fw-bold mb-2">No records found</h1>
      <div class="muted">We couldn't find any application/loan/credit transfer records for your email.</div>
    </div>
  </div>
<?php else: ?>

  <?php if ($student): ?>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h1 class="h4 fw-bold mb-1">Student profile</h1>
        <div class="muted">Full profile from <code>student_applications</code>.</div>
      </div>
      <?php if ($stageLabel !== ''): ?>
        <span class="badge rounded-pill badge-stage px-3 py-2">
          Current stage: <span class="ms-1 fw-semibold text-<?= htmlspecialchars($stageTone, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($stageLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </span>
      <?php endif; ?>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-12 col-md-4"><div class="kpi"><div class="label">Application ID</div><div class="value"><?= htmlspecialchars((string)($student['application_id'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="col-12 col-md-4"><div class="kpi"><div class="label">Destination</div><div class="value"><?= htmlspecialchars((string)($student['destination'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
      <div class="col-12 col-md-4"><div class="kpi"><div class="label">Materials uploaded</div><div class="value"><?= (int)$uploadsCount ?></div></div></div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 fw-bold mb-0">My study choices</h2>
          <div class="small muted">From your application submission</div>
        </div>

        <?php if (empty($studyChoices)): ?>
          <div class="muted">No study choices found yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr class="text-muted small">
                  <th style="min-width:160px">University</th>
                  <th style="min-width:120px">Region</th>
                  <th style="min-width:140px">Country</th>
                  <th style="min-width:140px">Level</th>
                  <th style="min-width:220px">Program</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($studyChoices as $ch): ?>
                  <tr>
                    <td class="fw-semibold"><?= htmlspecialchars((string)($ch['university_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($ch['region_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($ch['country_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($ch['program_level_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($ch['program_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 fw-bold mb-0">Progress checklist</h2>
          <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(pcvc_url('/student/edit_profile.php'), ENT_QUOTES, 'UTF-8') ?>">Review / edit</a>
            <a class="btn btn-sm btn-success" href="<?= htmlspecialchars(pcvc_url('/student/materials.php'), ENT_QUOTES, 'UTF-8') ?>">Materials</a>
          </div>
        </div>
        <div class="small muted mb-3">Statuses are updated by your consultant.</div>
        <?php
          $items = [
            ['Submitted', !empty($student['submitted'])],
            ['Application paid', !empty($student['app_paid'])],
            ['Admission', !empty($student['admit'])],
            ['I-20 sent', !empty($student['i20_sent'])],
            ['SEVIS paid', !empty($student['sevis_paid'])],
            ['Visa interview scheduled', !empty($student['visa_scheduled'])],
            ['Visa approved', !empty($student['visa_approved'])],
            ['Enrolled', !empty($student['enrolled'])],
          ];
        ?>
        <div class="list-group list-group-flush">
          <?php foreach ($items as [$label, $done]): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div class="fw-semibold"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
              <span class="badge <?= $done ? 'text-bg-success' : 'text-bg-light text-secondary border' ?>"><?= $done ? 'Done' : 'Pending' ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($credit): ?>
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 fw-bold mb-0">Credit transfer application</h2>
          <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(pcvc_url('/student/edit_credit_transfer.php'), ENT_QUOTES, 'UTF-8') ?>">Edit / complete</a>
        </div>
        <div class="row g-2">
          <div class="col-md-4"><div class="muted small fw-semibold">University</div><div class="fw-semibold"><?= htmlspecialchars((string)($credit['university'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-4"><div class="muted small fw-semibold">Current program</div><div class="fw-semibold"><?= htmlspecialchars((string)($credit['current_program'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-4"><div class="muted small fw-semibold">Proposed program</div><div class="fw-semibold"><?= htmlspecialchars((string)($credit['proposed_program'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div></div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($loan): ?>
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 fw-bold mb-0">Master loan application</h2>
          <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(pcvc_url('/student/edit_master_loan.php'), ENT_QUOTES, 'UTF-8') ?>">Edit / complete</a>
        </div>
        <div class="row g-2">
          <div class="col-md-4"><div class="muted small fw-semibold">Provider ID</div><div class="fw-semibold"><?= htmlspecialchars((string)($loan['loan_provider_id'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-4"><div class="muted small fw-semibold">City</div><div class="fw-semibold"><?= htmlspecialchars((string)($loan['city'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div></div>
          <div class="col-md-4"><div class="muted small fw-semibold">Program</div><div class="fw-semibold"><?= htmlspecialchars((string)($loan['masters_program_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div></div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($contracts)): ?>
    <div class="card mb-3">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-2">Student contracts</h2>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>ID</th><th>Status</th><th>Sent</th><th>Signed</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($contracts as $c): ?>
                <tr>
                  <td class="fw-semibold">#<?= (int)$c['id'] ?></td>
                  <td><?= htmlspecialchars((string)$c['status'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="muted text-nowrap"><?= htmlspecialchars((string)($c['sent_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="muted text-nowrap"><?= htmlspecialchars((string)($c['signed_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="text-end">
                    <?php if (!empty($c['contract_token'])): ?>
                      <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= htmlspecialchars(pcvc_url('/student-contract.php'), ENT_QUOTES, 'UTF-8') ?>?token=<?= urlencode((string)$c['contract_token']) ?>">Open</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/layout_footer.php'; ?>

