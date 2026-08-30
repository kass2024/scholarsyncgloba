<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/student_portal_schema.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/urls.php';
require_once __DIR__ . '/../helpers/secure_file.php';
require_once __DIR__ . '/auth.php';

pcvc_student_portal_ensure_schema($conn);

$pageTitle = 'Materials';
$accountId = (int)($_SESSION['student_account_id'] ?? 0);
$appId = (int)($_SESSION['student_application_id'] ?? 0);

$flash_success = '';
$flash_error = '';

// Flash support (PRG) so checklist updates immediately after upload.
if (!empty($_SESSION['pcvc_flash_success_materials'])) {
    $flash_success = (string)$_SESSION['pcvc_flash_success_materials'];
    unset($_SESSION['pcvc_flash_success_materials']);
}
if (!empty($_SESSION['pcvc_flash_error_materials'])) {
    $flash_error = (string)$_SESSION['pcvc_flash_error_materials'];
    unset($_SESSION['pcvc_flash_error_materials']);
}

// Document mapping to real columns in student_applications.
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

function pcvc_student_upload_dir(int $accountId): string
{
    $root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'student_materials';
    $dir = $root . DIRECTORY_SEPARATOR . (string)$accountId;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function pcvc_safe_filename(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/[^\w\-. ]+/u', '_', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    $name = trim($name, '. ');
    if ($name === '') {
        return 'file';
    }
    return $name;
}

function pcvc_norm_rel_path(string $p): string
{
    $p = trim($p);
    if ($p === '') return '';
    $p = str_replace('\\', '/', $p);
    $p = ltrim($p, '/');
    return $p;
}

function pcvc_material_link_html(string $relPath, string $label = 'View'): string
{
    $relPath = pcvc_norm_rel_path($relPath);
    if ($relPath === '') return '';
    $url = pcvc_secure_file_url($relPath, ['inline' => true]);
    return '<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
}

// Load current doc status from student_applications.
$docStatus = [];
$stuDocs = [];
if ($appId > 0) {
    $cols = implode(',', array_keys($docMap));
    $stmtS = $conn->prepare("SELECT $cols FROM student_applications WHERE id = ? LIMIT 1");
    if ($stmtS) {
        $stmtS->bind_param('i', $appId);
        $stmtS->execute();
        $stuDocs = $stmtS->get_result()->fetch_assoc() ?: [];
        $stmtS->close();
    }
}
foreach ($docMap as $key => $meta) {
    $val = $stuDocs[$key] ?? '';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    if (!pcvc_csrf_validate_post()) {
        $_SESSION['pcvc_flash_error_materials'] = 'Security check failed. Please refresh and try again.';
        header('Location: ' . pcvc_url('/student/materials.php'));
        exit;
    } elseif ($appId <= 0) {
        $_SESSION['pcvc_flash_error_materials'] = 'Could not detect your application record.';
        header('Location: ' . pcvc_url('/student/materials.php'));
        exit;
    } elseif (empty($_FILES['material']) || !is_array($_FILES['material'])) {
        $_SESSION['pcvc_flash_error_materials'] = 'Please choose a file.';
        header('Location: ' . pcvc_url('/student/materials.php'));
        exit;
    } else {
        $docType = (string)($_POST['doc_type'] ?? '');
        if ($docType === '' || !isset($docMap[$docType])) {
            $_SESSION['pcvc_flash_error_materials'] = 'Please select a valid document type.';
            header('Location: ' . pcvc_url('/student/materials.php'));
            exit;
        } else {
        $f = $_FILES['material'];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $_SESSION['pcvc_flash_error_materials'] = 'Upload failed. Please try again.';
            header('Location: ' . pcvc_url('/student/materials.php'));
            exit;
        } else {
            $orig = (string)($f['name'] ?? '');
            $tmp = (string)($f['tmp_name'] ?? '');
            $size = (int)($f['size'] ?? 0);

            if ($size <= 0 || $size > (20 * 1024 * 1024)) {
                $_SESSION['pcvc_flash_error_materials'] = 'File too large. Max 20MB.';
                header('Location: ' . pcvc_url('/student/materials.php'));
                exit;
            } elseif (!is_uploaded_file($tmp)) {
                $_SESSION['pcvc_flash_error_materials'] = 'Invalid upload.';
                header('Location: ' . pcvc_url('/student/materials.php'));
                exit;
            } else {
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $allowed = [
                    'pdf' => 'application/pdf',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ];
                if (!isset($allowed[$ext])) {
                    $_SESSION['pcvc_flash_error_materials'] = 'Unsupported file type. Allowed: PDF, JPG, PNG, DOC, DOCX.';
                    header('Location: ' . pcvc_url('/student/materials.php'));
                    exit;
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($tmp) ?: 'application/octet-stream';

                    // Basic MIME allowlist to reduce spoofing. (Some doc/docx may vary slightly; allow prefix.)
                    $mimeOk = false;
                    if ($ext === 'pdf' && $mime === 'application/pdf') $mimeOk = true;
                    if (in_array($ext, ['jpg','jpeg'], true) && in_array($mime, ['image/jpeg','image/pjpeg'], true)) $mimeOk = true;
                    if ($ext === 'png' && $mime === 'image/png') $mimeOk = true;
                    if ($ext === 'doc' && (str_starts_with($mime, 'application/') || $mime === 'application/msword')) $mimeOk = true;
                    if ($ext === 'docx' && (str_starts_with($mime, 'application/') || str_starts_with($mime, 'application/vnd.'))) $mimeOk = true;

                    if (!$mimeOk) {
                        $_SESSION['pcvc_flash_error_materials'] = 'File content type not allowed.';
                        header('Location: ' . pcvc_url('/student/materials.php'));
                        exit;
                    } else {
                        $dir = pcvc_student_upload_dir($accountId);
                        $safeOrig = pcvc_safe_filename($orig);
                        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
                        $path = $dir . DIRECTORY_SEPARATOR . $stored;

                        if (!@move_uploaded_file($tmp, $path)) {
                            $_SESSION['pcvc_flash_error_materials'] = 'Could not save uploaded file.';
                            header('Location: ' . pcvc_url('/student/materials.php'));
                            exit;
                        } else {
                            $relPath = 'uploads/student_materials/' . $accountId . '/' . $stored;
                            $stmt = $conn->prepare("
                                INSERT INTO student_portal_uploads
                                    (student_account_id, doc_type, original_name, stored_name, mime_type, size_bytes, storage_path)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            if (!$stmt) {
                                @unlink($path);
                                $_SESSION['pcvc_flash_error_materials'] = 'Database error while saving upload.';
                                header('Location: ' . pcvc_url('/student/materials.php'));
                                exit;
                            } else {
                                $stmt->bind_param('issssis', $accountId, $docType, $safeOrig, $stored, $mime, $size, $relPath);
                                $stmt->execute();
                                $stmt->close();

                                // Update student_applications document field (so we can show missing vs uploaded).
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

                                $_SESSION['pcvc_flash_success_materials'] = 'File uploaded successfully.';
                                header('Location: ' . pcvc_url('/student/materials.php'));
                                exit;
                            }
                        }
                    }
                }
            }
        }
    }
        }
}

$uploads = [];
$stmtL = $conn->prepare("
    SELECT id, doc_type, original_name, mime_type, size_bytes, uploaded_at
    FROM student_portal_uploads
    WHERE student_account_id = ?
    ORDER BY uploaded_at DESC, id DESC
");
if ($stmtL) {
    $stmtL->bind_param('i', $accountId);
    $stmtL->execute();
    $res = $stmtL->get_result();
    while ($row = $res->fetch_assoc()) {
        $uploads[] = $row;
    }
    $stmtL->close();
}

function pcvc_human_bytes(int $bytes): string
{
    if ($bytes <= 0) return '0 B';
    $units = ['B','KB','MB','GB'];
    $i = (int) floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);
    $val = $bytes / (1024 ** $i);
    return rtrim(rtrim(number_format($val, 2), '0'), '.') . ' ' . $units[$i];
}

require_once __DIR__ . '/layout.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 fw-bold mb-1">Materials</h1>
    <div class="muted">Upload documents requested by your consultant.</div>
  </div>
  <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(pcvc_url('/student/index.php'), ENT_QUOTES, 'UTF-8') ?>">Back to overview</a>
</div>

<div class="card mb-3">
  <div class="card-body">
    <h2 class="h6 fw-bold mb-2">Required documents checklist</h2>
    <div class="small muted mb-3">Upload what is missing. If a document is already uploaded, you can re-upload to replace it.</div>
    <?php if ($flash_success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="mt-3">
      <?= pcvc_csrf_input() ?>
      <input type="hidden" name="action" value="upload">
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

    <hr class="my-3">
    <div class="row g-2">
      <?php foreach ($docMap as $k => $meta): ?>
        <?php $up = !empty($docStatus[$k]['uploaded']); ?>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="border rounded-3 p-3 bg-white">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="fw-semibold"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php if ($up): ?>
                <span class="badge text-bg-success">Uploaded</span>
              <?php else: ?>
                <span class="badge text-bg-warning">Missing</span>
              <?php endif; ?>
            </div>
            <?php if ($up): ?>
              <div class="small muted mt-1">
                <?php if (!empty($meta['multiple'])): ?>
                  <?= count((array)$docStatus[$k]['value']) ?> file(s) attached
                <?php else: ?>
                  Saved
                <?php endif; ?>
              </div>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <?php if (!empty($meta['multiple'])): ?>
                  <?php foreach ((array)($docStatus[$k]['value'] ?? []) as $i => $p): ?>
                    <?= pcvc_material_link_html((string)$p, 'View ' . ($i + 1)) ?>
                  <?php endforeach; ?>
                <?php else: ?>
                  <?= pcvc_material_link_html((string)($docStatus[$k]['value'] ?? ''), 'View') ?>
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

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h2 class="h6 fw-bold mb-0">Your uploads</h2>
      <span class="badge text-bg-light border"><?= count($uploads) ?></span>
    </div>

    <?php if (empty($uploads)): ?>
      <div class="text-center muted py-4">No uploads yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>File</th>
              <th class="text-nowrap">Doc type</th>
              <th class="text-nowrap">Size</th>
              <th class="text-nowrap">Uploaded</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($uploads as $u): ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars((string)$u['original_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="muted"><?= htmlspecialchars((string)($docMap[$u['doc_type']]['label'] ?? ($u['doc_type'] ?? '—')), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="muted text-nowrap"><?= htmlspecialchars(pcvc_human_bytes((int)$u['size_bytes']), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="muted text-nowrap"><?= htmlspecialchars((string)$u['uploaded_at'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(pcvc_url('/student/download.php'), ENT_QUOTES, 'UTF-8') ?>?id=<?= (int)$u['id'] ?>">Download</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>

