<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/student_portal_schema.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/urls.php';
require_once __DIR__ . '/auth.php';

pcvc_student_portal_ensure_schema($conn);

$pageTitle = 'Edit master loan';
$email = strtolower(trim((string)($_SESSION['student_email'] ?? '')));
$accountId = (int)($_SESSION['student_account_id'] ?? 0);

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

$flash_success = '';
$flash_error = '';

// Flash support (PRG) so uploads/saves reflect immediately and avoid resubmit.
if (!empty($_SESSION['pcvc_flash_success_master_loan'])) {
    $flash_success = (string)$_SESSION['pcvc_flash_success_master_loan'];
    unset($_SESSION['pcvc_flash_success_master_loan']);
}
if (!empty($_SESSION['pcvc_flash_error_master_loan'])) {
    $flash_error = (string)$_SESSION['pcvc_flash_error_master_loan'];
    unset($_SESSION['pcvc_flash_error_master_loan']);
}

function pcvc_safe_filename_loan(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/[^\w\-. ]+/u', '_', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    $name = trim($name, '. ');
    return $name === '' ? 'file' : $name;
}

function pcvc_decode_json_array_to_text($v): string
{
    if (!is_string($v)) return '';
    $t = trim($v);
    if ($t === '') return '';
    $j = json_decode($t, true);
    if (is_array($j)) {
        $parts = array_values(array_filter(array_map('strval', $j), fn($x) => trim($x) !== ''));
        return implode(', ', $parts);
    }
    return $t;
}

function pcvc_encode_text_to_json_array(string $t): string
{
    $t = trim($t);
    if ($t === '') return json_encode([], JSON_UNESCAPED_SLASHES);
    // split by comma
    $parts = array_values(array_filter(array_map('trim', explode(',', $t)), fn($x) => $x !== ''));
    if (empty($parts)) $parts = [$t];
    return json_encode($parts, JSON_UNESCAPED_SLASHES);
}

function pcvc_upload_student_file(int $accountId, string $prefix, array $file): ?string
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

function pcvc_log_student_upload(mysqli $conn, int $accountId, string $docType, array $file, string $relPath): void
{
    $orig = pcvc_safe_filename_loan((string)($file['name'] ?? 'file'));
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
    // step1
    'first_name','last_name','gender','dob','phone_number','address1','address2','city','state','postal_code',
    'loan_provider_id',
    // step2 (stored as json arrays except application_type is string)
    'loan_reason','masters_program_name','school_name','degree_type','application_type','intake',
    // step3
    'citizenship_country','has_visa','has_ssn','ref_first_name','ref_last_name','ref_email','ref_phone','ref_relationship',
    // step4 cert
    'applicant_first_name','applicant_last_name','date_signed'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'save');
    if (!pcvc_csrf_validate_post()) {
        $_SESSION['pcvc_flash_error_master_loan'] = 'Security check failed.';
        header('Location: ' . pcvc_url('/student/edit_master_loan.php'));
        exit;
    } elseif (!$loan || empty($loan['id'])) {
        $_SESSION['pcvc_flash_error_master_loan'] = 'No loan record found for your email.';
        header('Location: ' . pcvc_url('/student/edit_master_loan.php'));
        exit;
    } else {
        $id = (int)$loan['id'];
        // Allowed attachment fields (step4 in save_master_loan.php)
        $fileFields = [
            'acceptance_letter','bachelor_degree','bachelor_transcript',
            'cv','id_document','valid_passport','english_certificate',
            'admission_fees','scholarship_letter','bank_statement'
        ];

        if ($action === 'upload_doc') {
            $docType = (string)($_POST['doc_type'] ?? '');
            if ($docType === '' || !in_array($docType, $fileFields, true)) {
                $_SESSION['pcvc_flash_error_master_loan'] = 'Invalid document type.';
                header('Location: ' . pcvc_url('/student/edit_master_loan.php'));
                exit;
            }
            if (empty($_FILES['material']) || !is_array($_FILES['material'])) {
                $_SESSION['pcvc_flash_error_master_loan'] = 'Please choose a file.';
                header('Location: ' . pcvc_url('/student/edit_master_loan.php'));
                exit;
            }

            $rel = pcvc_upload_student_file($accountId, 'loan_' . $docType, $_FILES['material']);
            if (!$rel) {
                $_SESSION['pcvc_flash_error_master_loan'] = 'Upload failed. Please try another file.';
                header('Location: ' . pcvc_url('/student/edit_master_loan.php'));
                exit;
            }

            $st = $conn->prepare("UPDATE master_loan_applications SET $docType = ? WHERE id = ? LIMIT 1");
            if ($st) {
                $st->bind_param('si', $rel, $id);
                $st->execute();
                $st->close();
            }
            try { pcvc_log_student_upload($conn, $accountId, $docType, (array)$_FILES['material'], $rel); } catch (Throwable $e) {}

            $_SESSION['pcvc_flash_success_master_loan'] = 'Document uploaded.';
            header('Location: ' . pcvc_url('/student/edit_master_loan.php'));
            exit;
        }

        // Normal save (fields only; attachments are uploaded instantly above)
        $sets = [];
        $vals = [];
        $types = '';
        foreach ($editable as $col) {
            if (!array_key_exists($col, $_POST)) continue;
            if ($col === 'email') continue; // email used for lookup; do not allow changing here
            $v = trim((string)$_POST[$col]);
            if (in_array($col, ['loan_reason','masters_program_name','school_name','degree_type','intake'], true)) {
                $v = pcvc_encode_text_to_json_array($v);
            }
            $sets[] = "$col = ?";
            $vals[] = $v;
            $types .= 's';
        }

        if (empty($sets)) {
            $_SESSION['pcvc_flash_error_master_loan'] = 'Nothing to update.';
            header('Location: ' . pcvc_url('/student/edit_master_loan.php'));
            exit;
        }

        $sql = "UPDATE master_loan_applications SET " . implode(', ', $sets) . " WHERE id = ? LIMIT 1";
        $st = $conn->prepare($sql);
        if (!$st) {
            $_SESSION['pcvc_flash_error_master_loan'] = 'Update failed.';
            header('Location: ' . pcvc_url('/student/edit_master_loan.php'));
            exit;
        }
        $types .= 'i';
        $vals[] = $id;
        $st->bind_param($types, ...$vals);
        $st->execute();
        $st->close();

        $_SESSION['pcvc_flash_success_master_loan'] = 'Saved.';
        header('Location: ' . pcvc_url('/student/edit_master_loan.php'));
        exit;
    }
}

// Reload
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

require_once __DIR__ . '/layout.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 fw-bold mb-1">Master loan</h1>
    <div class="muted">Complete missing fields then save.</div>
  </div>
  <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(pcvc_url('/student/index.php'), ENT_QUOTES, 'UTF-8') ?>">Back</a>
</div>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-danger"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<?php if (!$loan): ?>
  <div class="card"><div class="card-body">No record found for your email.</div></div>
<?php else: ?>
  <?php
    $loanReasonText = pcvc_decode_json_array_to_text((string)($loan['loan_reason'] ?? ''));
    $mastersProgramText = pcvc_decode_json_array_to_text((string)($loan['masters_program_name'] ?? ''));
    $schoolNameText = pcvc_decode_json_array_to_text((string)($loan['school_name'] ?? ''));
    $degreeTypeText = pcvc_decode_json_array_to_text((string)($loan['degree_type'] ?? ''));
    $intakeText = pcvc_decode_json_array_to_text((string)($loan['intake'] ?? ''));
  ?>
  <div class="card">
    <div class="card-body">
      <form method="post" enctype="multipart/form-data">
        <?= pcvc_csrf_input() ?>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label fw-semibold">First name</label>
            <input class="form-control" name="first_name" value="<?= htmlspecialchars((string)($loan['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Last name</label>
            <input class="form-control" name="last_name" value="<?= htmlspecialchars((string)($loan['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Phone</label>
            <input class="form-control" name="phone_number" value="<?= htmlspecialchars((string)($loan['phone_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Loan provider ID</label>
            <input class="form-control" name="loan_provider_id" value="<?= htmlspecialchars((string)($loan['loan_provider_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Loan purpose / reason</label>
            <input class="form-control" name="loan_reason" value="<?= htmlspecialchars($loanReasonText, ENT_QUOTES, 'UTF-8') ?>" placeholder="Comma separated values">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Program name</label>
            <input class="form-control" name="masters_program_name" value="<?= htmlspecialchars($mastersProgramText, ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">School name</label>
            <input class="form-control" name="school_name" value="<?= htmlspecialchars($schoolNameText, ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Degree type</label>
            <input class="form-control" name="degree_type" value="<?= htmlspecialchars($degreeTypeText, ENT_QUOTES, 'UTF-8') ?>" placeholder="Comma separated values">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Application type</label>
            <input class="form-control" name="application_type" value="<?= htmlspecialchars((string)($loan['application_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Intake</label>
            <input class="form-control" name="intake" value="<?= htmlspecialchars($intakeText, ENT_QUOTES, 'UTF-8') ?>" placeholder="Comma separated values">
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold">City</label>
            <input class="form-control" name="city" value="<?= htmlspecialchars((string)($loan['city'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>

          <div class="col-12"><hr class="my-2"></div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Citizenship country</label>
            <input class="form-control" name="citizenship_country" value="<?= htmlspecialchars((string)($loan['citizenship_country'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Has visa</label>
            <input class="form-control" name="has_visa" value="<?= htmlspecialchars((string)($loan['has_visa'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Has SSN</label>
            <input class="form-control" name="has_ssn" value="<?= htmlspecialchars((string)($loan['has_ssn'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>

          <div class="col-12"><div class="fw-bold mt-2">Reference</div></div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Ref first name</label>
            <input class="form-control" name="ref_first_name" value="<?= htmlspecialchars((string)($loan['ref_first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Ref last name</label>
            <input class="form-control" name="ref_last_name" value="<?= htmlspecialchars((string)($loan['ref_last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Ref email</label>
            <input class="form-control" name="ref_email" value="<?= htmlspecialchars((string)($loan['ref_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Ref phone</label>
            <input class="form-control" name="ref_phone" value="<?= htmlspecialchars((string)($loan['ref_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Relationship</label>
            <input class="form-control" name="ref_relationship" value="<?= htmlspecialchars((string)($loan['ref_relationship'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
        </div>

        <hr class="my-3">
        <h2 class="h6 fw-bold mb-2">Attachments</h2>
        <div class="small muted mb-2">If a file exists, it will be shown. Choose a file to replace it.</div>
        <?php
          $files = [
            'acceptance_letter' => 'Acceptance letter',
            'bachelor_degree' => 'Bachelor degree',
            'bachelor_transcript' => 'Bachelor transcript',
            'cv' => 'CV',
            'id_document' => 'ID document',
            'valid_passport' => 'Valid passport',
            'english_certificate' => 'English certificate',
            'admission_fees' => 'Admission fees proof',
            'scholarship_letter' => 'Scholarship letter',
            'bank_statement' => 'Bank statement',
          ];
        ?>
        <div class="row g-2">
          <?php foreach ($files as $k => $label): ?>
            <?php $p = (string)($loan[$k] ?? ''); ?>
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

        <hr class="my-3">
        <h2 class="h6 fw-bold mb-2">Certification</h2>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Applicant first name</label>
            <input class="form-control" name="applicant_first_name" value="<?= htmlspecialchars((string)($loan['applicant_first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Applicant last name</label>
            <input class="form-control" name="applicant_last_name" value="<?= htmlspecialchars((string)($loan['applicant_last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Date signed</label>
            <input class="form-control" name="date_signed" value="<?= htmlspecialchars((string)($loan['date_signed'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
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

<?php require_once __DIR__ . '/layout_footer.php'; ?>

