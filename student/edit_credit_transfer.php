<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/student_portal_schema.php';
require_once __DIR__ . '/../helpers/credit_transfer_programs.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/urls.php';
require_once __DIR__ . '/auth.php';

pcvc_student_portal_ensure_schema($conn);

$pageTitle = 'Edit credit transfer';
$email = strtolower(trim((string)($_SESSION['student_email'] ?? '')));
$accountId = (int)($_SESSION['student_account_id'] ?? 0);

// Load latest credit transfer record for this email.
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

$flash_success = '';
$flash_error = '';

// Flash support (PRG) so uploads/saves reflect immediately and avoid resubmit.
if (!empty($_SESSION['pcvc_flash_success_credit_transfer'])) {
    $flash_success = (string)$_SESSION['pcvc_flash_success_credit_transfer'];
    unset($_SESSION['pcvc_flash_success_credit_transfer']);
}
if (!empty($_SESSION['pcvc_flash_error_credit_transfer'])) {
    $flash_error = (string)$_SESSION['pcvc_flash_error_credit_transfer'];
    unset($_SESSION['pcvc_flash_error_credit_transfer']);
}

function pcvc_safe_filename_credit(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/[^\w\-. ]+/u', '_', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    $name = trim($name, '. ');
    return $name === '' ? 'file' : $name;
}

function pcvc_upload_student_file_ct(int $accountId, string $prefix, array $file): ?string
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > (20 * 1024 * 1024)) return null;
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['pdf'=>true,'jpg'=>true,'jpeg'=>true,'png'=>true,'doc'=>true,'docx'=>true];
    if (!isset($allowed[$ext])) return null;
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'student_materials' . DIRECTORY_SEPARATOR . (string)$accountId;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $stored = $prefix . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    $abs = $dir . DIRECTORY_SEPARATOR . $stored;
    if (!@move_uploaded_file((string)$file['tmp_name'], $abs)) return null;
    return 'uploads/student_materials/' . $accountId . '/' . $stored;
}

function pcvc_log_student_upload_ct(mysqli $conn, int $accountId, string $docType, array $file, string $relPath): void
{
    $orig = pcvc_safe_filename_credit((string)($file['name'] ?? 'file'));
    $size = (int)($file['size'] ?? 0);
    $stored = basename(str_replace('\\', '/', $relPath));
    $tmp = (string)($file['tmp_name'] ?? '');
    $mime = 'application/octet-stream';
    if ($tmp !== '' && class_exists('finfo')) {
        try {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp) ?: $mime;
        } catch (Throwable $e) {
            // ignore
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO student_portal_uploads
            (student_account_id, doc_type, original_name, stored_name, mime_type, size_bytes, storage_path)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) return;
    $stmt->bind_param('issssis', $accountId, $docType, $orig, $stored, $mime, $size, $relPath);
    $stmt->execute();
    $stmt->close();
}

$editable = [
    'first_name','middle_name','last_name','gender',
    'street_address','address_line_2','city','state','postal_code',
    'mobile_number','phone_number','work_number','company',
    'birth_month','birth_day','birth_year',
    'education_levels','certification_levels',
    'current_program','proposed_program','university','comments'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'save');
    if (!pcvc_csrf_validate_post()) {
        $_SESSION['pcvc_flash_error_credit_transfer'] = 'Security check failed.';
        header('Location: ' . pcvc_url('/student/edit_credit_transfer.php'));
        exit;
    } elseif (!$credit || empty($credit['id'])) {
        $_SESSION['pcvc_flash_error_credit_transfer'] = 'No credit transfer record found for your email.';
        header('Location: ' . pcvc_url('/student/edit_credit_transfer.php'));
        exit;
    } else {
        $id = (int)$credit['id'];
        // Allowed attachment fields (step2 in save_credit_transfer.php)
        $fileFields = ['current_degree','current_transcripts','passport_or_id','academic_cv','payment_proof'];

        if ($action === 'upload_doc') {
            $docType = (string)($_POST['doc_type'] ?? '');
            if ($docType === '' || !in_array($docType, $fileFields, true)) {
                $_SESSION['pcvc_flash_error_credit_transfer'] = 'Invalid document type.';
                header('Location: ' . pcvc_url('/student/edit_credit_transfer.php'));
                exit;
            }
            if (empty($_FILES['material']) || !is_array($_FILES['material'])) {
                $_SESSION['pcvc_flash_error_credit_transfer'] = 'Please choose a file.';
                header('Location: ' . pcvc_url('/student/edit_credit_transfer.php'));
                exit;
            }

            $rel = pcvc_upload_student_file_ct($accountId, 'credit_' . $docType, $_FILES['material']);
            if (!$rel) {
                $_SESSION['pcvc_flash_error_credit_transfer'] = 'Upload failed. Please try another file.';
                header('Location: ' . pcvc_url('/student/edit_credit_transfer.php'));
                exit;
            }

            $st = $conn->prepare("UPDATE credit_transfer_applications SET $docType = ? WHERE id = ? LIMIT 1");
            if ($st) {
                $st->bind_param('si', $rel, $id);
                $st->execute();
                $st->close();
            }
            try { pcvc_log_student_upload_ct($conn, $accountId, $docType, (array)$_FILES['material'], $rel); } catch (Throwable $e) {}

            $_SESSION['pcvc_flash_success_credit_transfer'] = 'Document uploaded.';
            header('Location: ' . pcvc_url('/student/edit_credit_transfer.php'));
            exit;
        }

        $sets = [];
        $vals = [];
        $types = '';

        foreach ($editable as $col) {
            if (!array_key_exists($col, $_POST)) continue;
            if ($col === 'education_levels') {
                $arr = $_POST['education_levels'] ?? [];
                if (!is_array($arr)) $arr = [];
                $arr = array_values(array_filter(array_map('trim', array_map('strval', $arr)), fn($x) => $x !== ''));
                $v = json_encode($arr, JSON_UNESCAPED_SLASHES);
            } elseif ($col === 'certification_levels') {
                $arr = $_POST['certification_levels'] ?? [];
                if (!is_array($arr)) $arr = [];
                $arr = array_values(array_filter(array_map('trim', array_map('strval', $arr)), fn($x) => $x !== ''));
                $v = json_encode($arr, JSON_UNESCAPED_SLASHES);
            } else {
                $v = trim((string)$_POST[$col]);
            }
            $sets[] = "$col = ?";
            $vals[] = $v;
            $types .= 's';
        }

        // Match credit_transfer.php / save_credit_transfer.php behavior
        $university = trim((string)($_POST['university'] ?? ''));
        $allowedUniversities = pcvc_credit_transfer_university_codes();
        if ($university !== '' && !in_array($university, $allowedUniversities, true)) {
            $_SESSION['pcvc_flash_error_credit_transfer'] = 'Invalid university. Choose UPAFA, DPHU, IST or USOJ.';
            header('Location: ' . pcvc_url('/student/edit_credit_transfer.php'));
            exit;
        }

        if (empty($sets)) {
            $_SESSION['pcvc_flash_error_credit_transfer'] = 'Nothing to update.';
            header('Location: ' . pcvc_url('/student/edit_credit_transfer.php'));
            exit;
        } else {
            $sql = "UPDATE credit_transfer_applications SET " . implode(', ', $sets) . " WHERE id = ? LIMIT 1";
            $st = $conn->prepare($sql);
            if (!$st) {
                $_SESSION['pcvc_flash_error_credit_transfer'] = 'Update failed.';
                header('Location: ' . pcvc_url('/student/edit_credit_transfer.php'));
                exit;
            } else {
                $types .= 'i';
                $vals[] = $id;
                $st->bind_param($types, ...$vals);
                $st->execute();
                $st->close();

                $_SESSION['pcvc_flash_success_credit_transfer'] = 'Saved.';
                header('Location: ' . pcvc_url('/student/edit_credit_transfer.php'));
                exit;
            }
        }
    }
}

// Reload
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

require_once __DIR__ . '/layout.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 fw-bold mb-1">Credit transfer</h1>
    <div class="muted">Complete missing fields then save.</div>
  </div>
  <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(pcvc_url('/student/index.php'), ENT_QUOTES, 'UTF-8') ?>">Back</a>
</div>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-danger"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<?php if (!$credit): ?>
  <div class="card"><div class="card-body">No record found for your email.</div></div>
<?php else: ?>
  <?php
    $eduSaved = [];
    $certSaved = [];
    $vEdu = (string)($credit['education_levels'] ?? '');
    $jEdu = json_decode($vEdu, true);
    if (is_array($jEdu)) $eduSaved = array_map('strval', $jEdu);
    $vCert = (string)($credit['certification_levels'] ?? '');
    $jCert = json_decode($vCert, true);
    if (is_array($jCert)) $certSaved = array_map('strval', $jCert);

    $eduOptions = [
      "High School Certificate",
      "Ordinary Diploma of 2 years",
      "Advanced Diploma of 3 years",
      "Bachelor without Degree",
      "Bachelor with Lower Division",
      "Bachelor with Upper Division",
      "Masters with Lower Division",
      "Masters with Upper Division",
    ];
    $certOptions = ["Bachelor","Masters","PhD"];
  ?>
  <div class="card">
    <div class="card-body">
      <form method="post" enctype="multipart/form-data">
        <?= pcvc_csrf_input() ?>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label fw-semibold">First name</label>
            <input class="form-control" name="first_name" value="<?= htmlspecialchars((string)($credit['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Middle name</label>
            <input class="form-control" name="middle_name" value="<?= htmlspecialchars((string)($credit['middle_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Last name</label>
            <input class="form-control" name="last_name" value="<?= htmlspecialchars((string)($credit['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">University</label>
            <select class="form-select" name="university" id="ct_university" required>
              <option value="" disabled <?= (trim((string)($credit['university'] ?? '')) === '') ? 'selected' : '' ?>>Choose your university</option>
              <option value="UPAFA" <?= ((string)($credit['university'] ?? '') === 'UPAFA') ? 'selected' : '' ?>>Université Africaine Franco-Arabe (UPAFA)</option>
              <option value="DPHU" <?= ((string)($credit['university'] ?? '') === 'DPHU') ? 'selected' : '' ?>>Distant Production house University (DPHU)</option>
              <option value="IST" <?= ((string)($credit['university'] ?? '') === 'IST') ? 'selected' : '' ?>>Institut Supérieur de Burkina Faso (IST)</option>
              <option value="USOJ" <?= ((string)($credit['university'] ?? '') === 'USOJ') ? 'selected' : '' ?>>University of Saint Joseph Mbarara (USOJ)</option>
            </select>
            <?php
              $upafaCatalogueRel = pcvc_credit_transfer_catalogue_path('UPAFA');
              $upafaCatalogueHref = $upafaCatalogueRel ? pcvc_url('/' . ltrim($upafaCatalogueRel, '/')) : '';
            ?>
            <?php if ($upafaCatalogueHref !== ''): ?>
              <a id="upafaCatalogueLink" class="small d-block mt-1" href="<?= htmlspecialchars($upafaCatalogueHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"<?= ((string)($credit['university'] ?? '') === 'UPAFA') ? '' : ' style="display:none;"' ?>>
                Download UPAFA 2025 program catalogue (PDF)
              </a>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Birth month</label>
            <input class="form-control" name="birth_month" value="<?= htmlspecialchars((string)($credit['birth_month'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="MM">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Birth day</label>
            <input class="form-control" name="birth_day" value="<?= htmlspecialchars((string)($credit['birth_day'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="DD">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Birth year</label>
            <input class="form-control" name="birth_year" value="<?= htmlspecialchars((string)($credit['birth_year'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="YYYY">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Mobile</label>
            <input class="form-control" name="mobile_number" value="<?= htmlspecialchars((string)($credit['mobile_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Phone</label>
            <input class="form-control" name="phone_number" value="<?= htmlspecialchars((string)($credit['phone_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Work number</label>
            <input class="form-control" name="work_number" value="<?= htmlspecialchars((string)($credit['work_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Company</label>
            <input class="form-control" name="company" value="<?= htmlspecialchars((string)($credit['company'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-12"><hr class="my-2"></div>
          <div class="col-12">
            <div class="fw-bold">Current Level of Education</div>
            <div class="small muted">Select all that apply.</div>
            <div class="row g-2 mt-1">
              <?php foreach ($eduOptions as $opt): ?>
                <div class="col-12 col-md-6">
                  <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 bg-white">
                    <input type="checkbox" name="education_levels[]" value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($opt, $eduSaved, true) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?></span>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-12 mt-2">
            <div class="fw-bold">Desired Certification Level</div>
            <div class="row g-2 mt-1">
              <?php foreach ($certOptions as $opt): ?>
                <div class="col-12 col-md-4">
                  <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 bg-white">
                    <input type="checkbox" name="certification_levels[]" value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($opt, $certSaved, true) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?></span>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Current program</label>
            <input class="form-control" name="current_program" value="<?= htmlspecialchars((string)($credit['current_program'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Proposed program</label>
            <input class="form-control" name="proposed_program" id="ct_proposed_program" list="ctProgramOptions" autocomplete="off"
                   placeholder="Select university first..." value="<?= htmlspecialchars((string)($credit['proposed_program'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
            <datalist id="ctProgramOptions"></datalist>
            <div class="small muted mt-1">First select a university, then start typing to search programs.</div>
          </div>
          <div class="col-md-12">
            <label class="form-label fw-semibold">Comments</label>
            <textarea class="form-control" name="comments" rows="3"><?= htmlspecialchars((string)($credit['comments'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>
        </div>

        <hr class="my-3">
        <h2 class="h6 fw-bold mb-2">Attachments</h2>
        <div class="small muted mb-2">Upload required documents (real-time). “Missing” becomes “View” after upload.</div>
        <?php
          $files = [
            'current_degree' => 'Current degree',
            'current_transcripts' => 'Current transcripts',
            'passport_or_id' => 'Passport or ID',
            'academic_cv' => 'Academic CV',
            'payment_proof' => 'Payment proof',
          ];
        ?>
        <div class="row g-2">
          <?php foreach ($files as $k => $label): ?>
            <?php $p = (string)($credit[$k] ?? ''); ?>
            <div class="col-12 col-md-6">
              <div class="border rounded-3 p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center gap-2">
                  <div class="fw-semibold"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
                  <?php if ($p !== ''): ?>
                    <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= htmlspecialchars(pcvc_url('/' . ltrim((string)$p, '/')), ENT_QUOTES, 'UTF-8') ?>">View</a>
                  <?php else: ?>
                    <span class="badge text-bg-warning">Missing</span>
                  <?php endif; ?>
                </div>
                <div class="mt-2">
                  <form method="post" enctype="multipart/form-data" class="pcvc-auto-upload">
                    <?= pcvc_csrf_input() ?>
                    <input type="hidden" name="action" value="upload_doc">
                    <input type="hidden" name="doc_type" value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>">
                    <input class="form-control" type="file" name="material" data-auto-upload="1" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="mt-3">
          <button class="btn btn-success fw-semibold" name="action" value="save">Save</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<script>
  (function () {
    document.querySelectorAll('input[data-auto-upload="1"]').forEach(function (inp) {
      inp.addEventListener('change', function () {
        if (!inp.files || !inp.files.length) return;
        var form = inp.closest('form');
        if (form) form.submit();
      });
    });
  })();
</script>

<script>
  // University -> Proposed program datalist (same idea as `credit_transfer.php`).
  // Static programmes from helpers/credit_transfer_programs.php (copied into MIS, not from e-learning API).
  const CT_PROGRAMS = <?= json_encode(pcvc_credit_transfer_programs_flat(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  function ctPopulatePrograms(university) {
    const datalist = document.getElementById('ctProgramOptions');
    const programInput = document.getElementById('ct_proposed_program');
    if (!datalist || !programInput) return;
    datalist.innerHTML = '';
    if (!university || !CT_PROGRAMS[university]) return;
    CT_PROGRAMS[university].forEach(p => {
      const opt = document.createElement('option');
      opt.value = p;
      datalist.appendChild(opt);
    });
  }

  (function initCtPrograms() {
    const uni = document.getElementById('ct_university');
    const programInput = document.getElementById('ct_proposed_program');
    if (!uni || !programInput) return;

    function rebuildDatalist() {
      const u = uni.value;
      const q = (programInput.value || '').trim().toLowerCase();
      const list = CT_PROGRAMS[u] || [];
      const datalist = document.getElementById('ctProgramOptions');
      if (!datalist) return;
      datalist.innerHTML = '';
      if (!u) return;
      const filtered = q === ''
        ? list
        : list.filter(p => String(p).toLowerCase().includes(q));
      filtered.slice(0, 60).forEach(p => {
        const opt = document.createElement('option');
        opt.value = p;
        datalist.appendChild(opt);
      });
    }

    uni.addEventListener('change', function () {
      programInput.value = '';
      rebuildDatalist();
      programInput.disabled = !uni.value;
      programInput.placeholder = uni.value ? 'Start typing to search programs...' : 'Select university first...';
      const cat = document.getElementById('upafaCatalogueLink');
      if (cat) cat.style.display = uni.value === 'UPAFA' ? '' : 'none';
      if (uni.value) programInput.focus();
    });
    programInput.addEventListener('input', rebuildDatalist);

    // initial
    programInput.disabled = !uni.value;
    programInput.placeholder = uni.value ? 'Start typing to search programs...' : 'Select university first...';
    rebuildDatalist();
  })();
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>

