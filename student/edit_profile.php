<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/student_portal_schema.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/urls.php';
require_once __DIR__ . '/auth.php';

pcvc_student_portal_ensure_schema($conn);

$pageTitle = 'Edit my profile';
$email = strtolower(trim((string)($_SESSION['student_email'] ?? '')));
 $accountId = (int)($_SESSION['student_account_id'] ?? 0);

// Load latest student application by email (preferred) or session id fallback.
$student = null;
$appId = 0;
if ($email !== '') {
    $st = $conn->prepare("SELECT * FROM student_applications WHERE LOWER(TRIM(email)) = ? ORDER BY id DESC LIMIT 1");
    if ($st) {
        $st->bind_param('s', $email);
        $st->execute();
        $student = $st->get_result()->fetch_assoc();
        $st->close();
    }
}
if ($student) {
    $appId = (int)($student['id'] ?? 0);
    $_SESSION['student_application_id'] = $appId;
}

$flash_success = '';
$flash_error = '';

// Document mapping (same as materials page) to match student-application.php required docs.
$docMap = [
    'valid_passport' => ['label' => 'Valid Passport', 'multiple' => false],
    'degree_transcripts' => ['label' => 'Degree / Academic Transcripts', 'multiple' => true],
    'high_school_degree' => ['label' => 'High School Certificate', 'multiple' => false],
    'cv_resume' => ['label' => 'CV / Resume', 'multiple' => false],
    'recommendation_letters' => ['label' => 'Recommendation Letter(s)', 'multiple' => false],
    'personal_statement' => ['label' => 'Personal Statement / Motivation Letter', 'multiple' => false],
    'english_certificate' => ['label' => 'English Proficiency Certificate', 'multiple' => false],
    'birth_certificate' => ['label' => 'Birth Certificate', 'multiple' => false],
    'payment_proof' => ['label' => 'Application / Payment Proof', 'multiple' => false],
];

function pcvc_student_upload_dir_profile(int $accountId): string
{
    $root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'student_materials';
    $dir = $root . DIRECTORY_SEPARATOR . (string)$accountId;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function pcvc_safe_filename_profile(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/[^\w\-. ]+/u', '_', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    $name = trim($name, '. ');
    return $name === '' ? 'file' : $name;
}

// Allowlist of editable fields (safe updates only).
$editable = [
    'first_name','middle_name','last_name',
    'area_code','phone_number',
    'gender','dob',
    'nationality','second_nationality',
    'country_of_birth','city_of_birth',
    'passport_number','student_national_id',
    'address_line1','address_line2','city','state_province','postal_code',
    'father_first_name','father_last_name','mother_first_name','mother_last_name',
    'emergency_first_name','emergency_last_name','emergency_email','emergency_area_code','emergency_phone_number','emergency_relationship','emergency_same_address',
    'previous_institution_name','previous_institution_street','previous_institution_city','previous_institution_province','previous_institution_country','previous_institution_post_code',
    'language_of_instruction','previous_study_start','previous_study_graduation',
    'additional_secondary_school','additional_secondary_details',
    'study_gap','study_gap_details',
    'post_secondary','post_secondary_details',
    'criminal_history','criminal_history_details',
    'disability','disability_details',
    'visa_rejection','visa_rejection_details',
    'destination','other_destination',
    'paying_tuition_fees','paying_cost_living','paying_travel_expenses',
    'intended_study_level',
    'bachelor_program','masters_program','phd_program',
    'comments',
];

// "Important" fields to complete (for highlight + summary).
$required = [
    'first_name' => 'First name',
    'last_name' => 'Last name',
    'email' => 'Email',
    'phone_number' => 'Phone number',
    'gender' => 'Gender',
    'dob' => 'Date of birth',
    'nationality' => 'Nationality',
    'passport_number' => 'Passport number',
    'address_line1' => 'Address line 1',
    'city' => 'City',
    'destination' => 'Destination',
    'intended_study_level' => 'Intended study level',
];

function pcvc_is_blank($v): bool {
    if ($v === null) return true;
    if (is_string($v)) return trim($v) === '';
    return false;
}

function pcvc_missing_fields(array $student, array $required): array
{
    $missing = [];
    foreach ($required as $k => $label) {
        $val = $student[$k] ?? null;
        if (pcvc_is_blank($val)) $missing[$k] = $label;
    }

    // Program: require at least one program field.
    $program = trim((string)($student['masters_program'] ?? '')) . trim((string)($student['bachelor_program'] ?? '')) . trim((string)($student['phd_program'] ?? ''));
    if (trim($program) === '') {
        $missing['__program__'] = 'Program (Bachelor/Masters/PhD)';
    }

    return $missing;
}

// Load current doc status from student_applications (for checklist + replace).
$docStatus = [];
if ($student && $appId > 0) {
    foreach ($docMap as $key => $meta) {
        $val = $student[$key] ?? '';
        if ($meta['multiple']) {
            $arr = [];
            if (is_string($val) && trim($val) !== '') {
                $decoded = json_decode($val, true);
                if (is_array($decoded)) $arr = $decoded;
            }
            $docStatus[$key] = ['uploaded' => !empty($arr), 'value' => $arr];
        } else {
            $docStatus[$key] = ['uploaded' => is_string($val) && trim($val) !== '', 'value' => (string)$val];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!pcvc_csrf_validate_post()) {
        $flash_error = 'Security check failed.';
    } elseif (!$student || $appId <= 0) {
        $flash_error = 'No student application record found for your email.';
    } else {
        $action = (string)($_POST['action'] ?? 'save_profile');

        if ($action === 'upload_doc') {
            $docType = (string)($_POST['doc_type'] ?? '');
            if ($docType === '' || !isset($docMap[$docType])) {
                $flash_error = 'Please select a valid document type.';
            } elseif (empty($_FILES['material']) || !is_array($_FILES['material'])) {
                $flash_error = 'Please choose a file.';
            } else {
                $f = $_FILES['material'];
                if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $flash_error = 'Upload failed. Please try again.';
                } else {
                    $orig = (string)($f['name'] ?? '');
                    $tmp = (string)($f['tmp_name'] ?? '');
                    $size = (int)($f['size'] ?? 0);

                    if ($size <= 0 || $size > (20 * 1024 * 1024)) {
                        $flash_error = 'File too large. Max 20MB.';
                    } elseif (!is_uploaded_file($tmp)) {
                        $flash_error = 'Invalid upload.';
                    } else {
                        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                        $allowed = ['pdf'=>true,'jpg'=>true,'jpeg'=>true,'png'=>true,'doc'=>true,'docx'=>true];
                        if (!isset($allowed[$ext])) {
                            $flash_error = 'Unsupported file type. Allowed: PDF, JPG, PNG, DOC, DOCX.';
                        } else {
                            $finfo = new finfo(FILEINFO_MIME_TYPE);
                            $mime = $finfo->file($tmp) ?: 'application/octet-stream';

                            $dir = pcvc_student_upload_dir_profile($accountId);
                            $safeOrig = pcvc_safe_filename_profile($orig);
                            $stored = bin2hex(random_bytes(16)) . '.' . $ext;
                            $path = $dir . DIRECTORY_SEPARATOR . $stored;
                            if (!@move_uploaded_file($tmp, $path)) {
                                $flash_error = 'Could not save uploaded file.';
                            } else {
                                $relPath = 'uploads/student_materials/' . $accountId . '/' . $stored;

                                // Save portal upload row
                                $stmtUp = $conn->prepare("
                                  INSERT INTO student_portal_uploads
                                    (student_account_id, doc_type, original_name, stored_name, mime_type, size_bytes, storage_path)
                                  VALUES (?, ?, ?, ?, ?, ?, ?)
                                ");
                                if ($stmtUp) {
                                    $stmtUp->bind_param('issssis', $accountId, $docType, $safeOrig, $stored, $mime, $size, $relPath);
                                    $stmtUp->execute();
                                    $stmtUp->close();
                                }

                                // Update student_applications doc field
                                if (!empty($docMap[$docType]['multiple'])) {
                                    $cur = $docStatus[$docType]['value'] ?? [];
                                    if (!is_array($cur)) $cur = [];
                                    $cur[] = $relPath;
                                    $json = json_encode(array_values(array_unique($cur)), JSON_UNESCAPED_SLASHES);
                                    $stU = $conn->prepare("UPDATE student_applications SET degree_transcripts = ? WHERE id = ? LIMIT 1");
                                    if ($stU) {
                                        $stU->bind_param('si', $json, $appId);
                                        $stU->execute();
                                        $stU->close();
                                    }
                                } else {
                                    $col = $docType;
                                    $stU = $conn->prepare("UPDATE student_applications SET $col = ? WHERE id = ? LIMIT 1");
                                    if ($stU) {
                                        $stU->bind_param('si', $relPath, $appId);
                                        $stU->execute();
                                        $stU->close();
                                    }
                                }

                                $flash_success = 'Document uploaded.';
                            }
                        }
                    }
                }
            }
        } else {
            // Save profile fields
            $sets = [];
            $vals = [];
            $types = '';

            foreach ($editable as $col) {
                if (!array_key_exists($col, $_POST)) continue;
                $v = trim((string)$_POST[$col]);
                $sets[] = "$col = ?";
                $vals[] = $v;
                $types .= 's';
            }

            if (empty($sets)) {
                $flash_error = 'Nothing to update.';
            } else {
                $sql = "UPDATE student_applications SET " . implode(', ', $sets) . " WHERE id = ? LIMIT 1";
                $st = $conn->prepare($sql);
                if (!$st) {
                    $flash_error = 'Update failed. Please try again.';
                } else {
                    $types .= 'i';
                    $vals[] = $appId;
                    $st->bind_param($types, ...$vals);
                    $st->execute();
                    $st->close();
                    $flash_success = 'Profile saved.';
                }
            }
        }
    }
}

// Reload after save
if ($email !== '') {
    $st = $conn->prepare("SELECT * FROM student_applications WHERE LOWER(TRIM(email)) = ? ORDER BY id DESC LIMIT 1");
    if ($st) {
        $st->bind_param('s', $email);
        $st->execute();
        $student = $st->get_result()->fetch_assoc();
        $st->close();
    }
}

$missing = $student ? pcvc_missing_fields($student, $required) : [];

// Reload docStatus after save/upload.
$docStatus = [];
if ($student && $appId > 0) {
    foreach ($docMap as $key => $meta) {
        $val = $student[$key] ?? '';
        if ($meta['multiple']) {
            $arr = [];
            if (is_string($val) && trim($val) !== '') {
                $decoded = json_decode($val, true);
                if (is_array($decoded)) $arr = $decoded;
            }
            $docStatus[$key] = ['uploaded' => !empty($arr), 'value' => $arr];
        } else {
            $docStatus[$key] = ['uploaded' => is_string($val) && trim($val) !== '', 'value' => (string)$val];
        }
    }
}

function pcvc_input_class(array $missing, string $key): string
{
    return isset($missing[$key]) ? 'border-warning' : '';
}

require_once __DIR__ . '/layout.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 fw-bold mb-1">Edit my profile</h1>
    <div class="muted">Complete missing fields in your <code>student_applications</code> profile.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(pcvc_url('/student/index.php'), ENT_QUOTES, 'UTF-8') ?>">Back</a>
    <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(pcvc_url('/student/profile.php'), ENT_QUOTES, 'UTF-8') ?>">View read-only</a>
  </div>
</div>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-danger"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<?php if (!$student): ?>
  <div class="card"><div class="card-body">No student profile found for your email.</div></div>
<?php else: ?>

  <?php if (!empty($missing)): ?>
    <div class="alert alert-warning">
      <div class="fw-semibold mb-1">Missing fields</div>
      <div class="small">Highlighted inputs below need attention:</div>
      <ul class="mb-0">
        <?php foreach ($missing as $k => $label): ?>
          <li><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php else: ?>
    <div class="alert alert-success">Your profile looks complete.</div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-body">
      <h2 class="h6 fw-bold mb-2">Statuses (from DB)</h2>
      <div class="small muted mb-2">These are not editable. They come from the workflow flags in <code>student_applications</code>.</div>
      <?php
        $flags = [
          'incomplete_app' => 'Incomplete App',
          'submitted' => 'Submitted',
          'app_paid' => 'Application paid',
          'admit' => 'Admission',
          'i20_sent' => 'I-20 sent',
          'sevis_paid' => 'SEVIS paid',
          'visa_scheduled' => 'Visa interview scheduled',
          'visa_approved' => 'Visa approved',
          'enrolled' => 'Enrolled',
          'addn_doc' => 'Additional documents required',
          'deny' => 'Visa denied',
          'app_start' => 'Application started',
        ];
      ?>
      <div class="row g-2">
        <?php foreach ($flags as $k => $label): ?>
          <?php $on = !empty($student[$k]); ?>
          <div class="col-12 col-md-6 col-xl-4">
            <div class="border rounded-3 p-2 bg-white d-flex align-items-center justify-content-between">
              <div class="fw-semibold"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
              <span class="badge <?= $on ? 'text-bg-success' : 'text-bg-light text-secondary border' ?>"><?= $on ? 'Yes' : 'No' ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <h2 class="h6 fw-bold mb-2">Attachments (documents)</h2>
      <div class="small muted mb-3">Upload missing documents or re-upload to replace. This updates the document fields in <code>student_applications</code>.</div>

      <form method="post" enctype="multipart/form-data" class="mb-3">
        <?= pcvc_csrf_input() ?>
        <input type="hidden" name="action" value="upload_doc">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-md-4">
            <label class="form-label fw-semibold">Document type</label>
            <select class="form-select" name="doc_type" required>
              <option value="">-- Select --</option>
              <?php foreach ($docMap as $k => $meta): ?>
                <?php $isMissing = empty($docStatus[$k]['uploaded']); ?>
                <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?><?= $isMissing ? ' (missing)' : ' (uploaded)' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-5">
            <label class="form-label fw-semibold">Choose file</label>
            <input class="form-control" type="file" name="material" required>
          </div>
          <div class="col-12 col-md-3">
            <button class="btn btn-success w-100 fw-semibold" type="submit">Upload</button>
          </div>
        </div>
      </form>

      <div class="row g-2">
        <?php foreach ($docMap as $k => $meta): ?>
          <?php $up = !empty($docStatus[$k]['uploaded']); ?>
          <div class="col-12 col-md-6 col-xl-4">
            <div class="border rounded-3 p-3 bg-white">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="fw-semibold"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></div>
                <span class="badge <?= $up ? 'text-bg-success' : 'text-bg-warning' ?>"><?= $up ? 'Uploaded' : 'Missing' ?></span>
              </div>
              <?php if ($up): ?>
                <div class="small muted mt-1">
                  <?php if (!empty($meta['multiple'])): ?>
                    <?= count((array)$docStatus[$k]['value']) ?> file(s)
                  <?php else: ?>
                    <span class="text-success">Saved</span>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <div class="small muted mt-1">Please upload this document.</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <form method="post" class="card">
    <div class="card-body">
      <?= pcvc_csrf_input() ?>
      <input type="hidden" name="action" value="save_profile">

      <div class="row g-2">
        <div class="col-md-4">
          <label class="form-label fw-semibold">First name</label>
          <input class="form-control <?= pcvc_input_class($missing,'first_name') ?>" name="first_name" value="<?= htmlspecialchars((string)($student['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Middle name</label>
          <input class="form-control" name="middle_name" value="<?= htmlspecialchars((string)($student['middle_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Last name</label>
          <input class="form-control <?= pcvc_input_class($missing,'last_name') ?>" name="last_name" value="<?= htmlspecialchars((string)($student['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Email (read-only)</label>
          <input class="form-control" value="<?= htmlspecialchars((string)($student['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">Area code</label>
          <input class="form-control" name="area_code" value="<?= htmlspecialchars((string)($student['area_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Phone number</label>
          <input class="form-control <?= pcvc_input_class($missing,'phone_number') ?>" name="phone_number" value="<?= htmlspecialchars((string)($student['phone_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-semibold">Gender</label>
          <select class="form-select <?= pcvc_input_class($missing,'gender') ?>" name="gender">
            <?php $g = (string)($student['gender'] ?? ''); ?>
            <option value="" <?= $g===''?'selected':'' ?>>-- Select --</option>
            <option value="Male" <?= $g==='Male'?'selected':'' ?>>Male</option>
            <option value="Female" <?= $g==='Female'?'selected':'' ?>>Female</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Date of birth</label>
          <input class="form-control <?= pcvc_input_class($missing,'dob') ?>" type="date" name="dob" value="<?= htmlspecialchars((string)($student['dob'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Nationality</label>
          <input class="form-control <?= pcvc_input_class($missing,'nationality') ?>" name="nationality" value="<?= htmlspecialchars((string)($student['nationality'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-semibold">Second nationality</label>
          <input class="form-control" name="second_nationality" value="<?= htmlspecialchars((string)($student['second_nationality'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Passport number</label>
          <input class="form-control <?= pcvc_input_class($missing,'passport_number') ?>" name="passport_number" value="<?= htmlspecialchars((string)($student['passport_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">National ID</label>
          <input class="form-control" name="student_national_id" value="<?= htmlspecialchars((string)($student['student_national_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-12"><hr class="my-2"></div>
        <div class="col-12">
          <div class="fw-bold">Emergency contact</div>
          <div class="small muted">Step 4 in the application form.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">First name</label>
          <input class="form-control" name="emergency_first_name" value="<?= htmlspecialchars((string)($student['emergency_first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Last name</label>
          <input class="form-control" name="emergency_last_name" value="<?= htmlspecialchars((string)($student['emergency_last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Email</label>
          <input class="form-control" name="emergency_email" value="<?= htmlspecialchars((string)($student['emergency_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Area code</label>
          <input class="form-control" name="emergency_area_code" value="<?= htmlspecialchars((string)($student['emergency_area_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Phone</label>
          <input class="form-control" name="emergency_phone_number" value="<?= htmlspecialchars((string)($student['emergency_phone_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Relationship</label>
          <input class="form-control" name="emergency_relationship" value="<?= htmlspecialchars((string)($student['emergency_relationship'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Same address</label>
          <select class="form-select" name="emergency_same_address">
            <?php $esa = (string)($student['emergency_same_address'] ?? ''); ?>
            <option value="" <?= $esa===''?'selected':'' ?>>--</option>
            <option value="Yes" <?= $esa==='Yes'?'selected':'' ?>>Yes</option>
            <option value="No" <?= $esa==='No'?'selected':'' ?>>No</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label fw-semibold">Address line 1</label>
          <input class="form-control <?= pcvc_input_class($missing,'address_line1') ?>" name="address_line1" value="<?= htmlspecialchars((string)($student['address_line1'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Address line 2</label>
          <input class="form-control" name="address_line2" value="<?= htmlspecialchars((string)($student['address_line2'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">City</label>
          <input class="form-control <?= pcvc_input_class($missing,'city') ?>" name="city" value="<?= htmlspecialchars((string)($student['city'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">State / Province</label>
          <input class="form-control" name="state_province" value="<?= htmlspecialchars((string)($student['state_province'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Postal code</label>
          <input class="form-control" name="postal_code" value="<?= htmlspecialchars((string)($student['postal_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Destination</label>
          <input class="form-control <?= pcvc_input_class($missing,'destination') ?>" name="destination" value="<?= htmlspecialchars((string)($student['destination'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Intended study level</label>
          <input class="form-control <?= pcvc_input_class($missing,'intended_study_level') ?>" name="intended_study_level" value="<?= htmlspecialchars((string)($student['intended_study_level'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-semibold">Tuition fees paid by</label>
          <input class="form-control" name="paying_tuition_fees" value="<?= htmlspecialchars((string)($student['paying_tuition_fees'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Cost of living paid by</label>
          <input class="form-control" name="paying_cost_living" value="<?= htmlspecialchars((string)($student['paying_cost_living'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Travel expenses paid by</label>
          <input class="form-control" name="paying_travel_expenses" value="<?= htmlspecialchars((string)($student['paying_travel_expenses'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-semibold">Bachelor program</label>
          <input class="form-control <?= isset($missing['__program__']) ? 'border-warning' : '' ?>" name="bachelor_program" value="<?= htmlspecialchars((string)($student['bachelor_program'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Masters program</label>
          <input class="form-control <?= isset($missing['__program__']) ? 'border-warning' : '' ?>" name="masters_program" value="<?= htmlspecialchars((string)($student['masters_program'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">PhD program</label>
          <input class="form-control <?= isset($missing['__program__']) ? 'border-warning' : '' ?>" name="phd_program" value="<?= htmlspecialchars((string)($student['phd_program'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-12">
          <label class="form-label fw-semibold">Additional notes</label>
          <textarea class="form-control" name="comments" rows="3"><?= htmlspecialchars((string)($student['comments'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-success fw-semibold" type="submit">Save changes</button>
        <a class="btn btn-outline-primary" href="<?= htmlspecialchars(pcvc_url('/student/materials.php'), ENT_QUOTES, 'UTF-8') ?>">Upload materials</a>
      </div>
    </div>
  </form>

<?php endif; ?>

<?php require_once __DIR__ . '/layout_footer.php'; ?>

