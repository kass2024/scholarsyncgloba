<?php
/**
 * korea-event-applications.php — Admin management for South Korea Event Participation.
 */
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/korea_event_schema.php';
require_once __DIR__ . '/helpers/korea_event_files.php';
require_once __DIR__ . '/helpers/korea_event_notify.php';
require_once __DIR__ . '/helpers/secure_file.php';
require_once __DIR__ . '/helpers/env_load.php';

kep_ensure_schema($conn);
xander_load_env_file();

$adminId = $_SESSION['id'] ?? $_SESSION['admin_id'] ?? null;
$roleRaw = trim((string) ($_SESSION['role'] ?? ''));
$roleKey = strtolower(preg_replace('/\s+/', ' ', $roleRaw) ?? $roleRaw);
$roleOk = in_array($roleKey, ['superadmin', 'staff'], true)
    || in_array($roleRaw, ['superadmin', 'staff'], true);

if (empty($adminId) || !$roleOk) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Please refresh and log in again.']);
        exit;
    }
    header('Location: admin-login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $respond = static function (bool $ok, array $extra = [], int $code = 200): void {
        http_response_code($code);
        echo json_encode(array_merge(['success' => $ok], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    };

    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])
        || !hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token'])) {
        $respond(false, ['message' => 'Invalid CSRF token. Refresh the page and try again.'], 403);
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'register_application') {
        $full_name = trim((string) ($_POST['full_name'] ?? ''));
        $date_of_birth = trim((string) ($_POST['date_of_birth'] ?? ''));
        $gender = strtolower(trim((string) ($_POST['gender'] ?? '')));
        $nationality = trim((string) ($_POST['nationality'] ?? ''));
        $country_of_residence = trim((string) ($_POST['country_of_residence'] ?? ''));
        $passport_number = strtoupper(trim((string) ($_POST['passport_number'] ?? '')));
        $phone_area_code = preg_replace('/\D+/', '', (string) ($_POST['phone_area_code'] ?? '')) ?? '';
        $phone_number = preg_replace('/\D+/', '', (string) ($_POST['phone_number'] ?? '')) ?? '';
        $messaging_app = strtolower(trim((string) ($_POST['messaging_app'] ?? 'whatsapp')));
        $occupation = trim((string) ($_POST['occupation'] ?? ''));
        $organization = trim((string) ($_POST['organization'] ?? ''));
        $event_name = trim((string) ($_POST['event_name'] ?? ''));
        if ($event_name === '') {
            $event_name = 'South Korea Event';
        }
        $participation_purpose = trim((string) ($_POST['participation_purpose'] ?? ''));
        $emailRaw = trim((string) ($_POST['email'] ?? ''));
        $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
        $passport_file = kep_normalize_rel_path((string) ($_POST['passport_file'] ?? ''));
        $cv_file = kep_normalize_rel_path((string) ($_POST['cv_file'] ?? ''));

        $allowedGenders = array_keys(kep_gender_options());
        $missing = [];
        if ($full_name === '') {
            $missing[] = 'Full Name';
        }
        if ($date_of_birth === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
            $missing[] = 'Date of Birth';
        }
        if (!in_array($gender, $allowedGenders, true)) {
            $missing[] = 'Gender';
        }
        if ($nationality === '') {
            $missing[] = 'Nationality';
        }
        if ($country_of_residence === '') {
            $missing[] = 'Country of Residence';
        }
        if ($passport_number === '') {
            $missing[] = 'Passport Number';
        }
        if (!$email) {
            $missing[] = 'Valid Email';
        }
        if ($phone_number === '') {
            $missing[] = 'Phone Number';
        }
        if (!in_array($messaging_app, ['whatsapp', 'telegram'], true)) {
            $missing[] = 'Telegram or WhatsApp';
        }
        if ($occupation === '') {
            $missing[] = 'Occupation';
        }
        if ($passport_file === '' || !kep_validate_stored_path($passport_file)) {
            $missing[] = 'Passport Scan';
        }
        if ($cv_file === '' || !kep_validate_stored_path($cv_file)) {
            $missing[] = 'CV / Resume';
        }
        if ($missing !== []) {
            $respond(false, [
                'message' => 'Please complete the required fields: ' . implode(', ', array_values(array_unique($missing))),
                'missing' => array_values(array_unique($missing)),
            ], 422);
        }

        $user_id = 'kep_admin_' . bin2hex(random_bytes(6)) . '_' . time();
        $reference_id = 'KEP' . date('Y') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $nameParts = preg_split('/\s+/', $full_name, 2) ?: [$full_name];
        $first_name = $nameParts[0] ?? $full_name;
        $last_name = $nameParts[1] ?? '';
        $emailStore = strtolower((string) $email);
        $source = 'admin';
        $created_by = (int) $adminId;
        $admin_notes = 'Registered from dashboard. No email sent.';

        $sql = 'INSERT INTO korea_event_applications (
            user_id, reference_id, full_name, first_name, last_name, date_of_birth, gender,
            nationality, country_of_residence, passport_number, email,
            phone_area_code, phone_number, messaging_app, occupation, organization,
            event_name, participation_purpose, passport_file, cv_file, status, source,
            created_by_admin_id, admin_notes, created_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"pending",?,?,?,NOW())';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $respond(false, ['message' => 'Database error'], 500);
        }
        $stmt->bind_param(
            'sssssssssssssssssssssis',
            $user_id,
            $reference_id,
            $full_name,
            $first_name,
            $last_name,
            $date_of_birth,
            $gender,
            $nationality,
            $country_of_residence,
            $passport_number,
            $emailStore,
            $phone_area_code,
            $phone_number,
            $messaging_app,
            $occupation,
            $organization,
            $event_name,
            $participation_purpose,
            $passport_file,
            $cv_file,
            $source,
            $created_by,
            $admin_notes
        );
        if (!$stmt->execute()) {
            $err = $conn->error;
            $stmt->close();
            $respond(false, ['message' => 'Could not save application' . ($err !== '' ? ': ' . $err : '')], 500);
        }
        $stmt->close();

        // Office registration: do not email the applicant or the office.
        $respond(true, [
            'message' => 'Applicant registered. No email was sent.',
            'reference_id' => $reference_id,
        ]);
    }

    $appId  = isset($_POST['application_id']) ? (int) $_POST['application_id'] : 0;
    if ($appId <= 0) {
        $respond(false, ['message' => 'Invalid application ID']);
    }

    $stmt = $conn->prepare('SELECT * FROM korea_event_applications WHERE id = ? LIMIT 1');
    if (!$stmt) {
        $respond(false, ['message' => 'Database error'], 500);
    }
    $stmt->bind_param('i', $appId);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$app) {
        $respond(false, ['message' => 'Application not found']);
    }

    if ($action === 'set_status') {
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['pending', 'under_review', 'approved', 'rejected'], true)) {
            $respond(false, ['message' => 'Invalid status']);
        }
        $note = trim((string) ($_POST['note'] ?? ''));

        $upd = $conn->prepare('UPDATE korea_event_applications SET status = ?, admin_notes = ? WHERE id = ?');
        if (!$upd) {
            $respond(false, ['message' => 'Could not prepare status update'], 500);
        }
        $upd->bind_param('ssi', $status, $note, $appId);
        if (!$upd->execute()) {
            $upd->close();
            $respond(false, ['message' => 'Could not update status: ' . $conn->error], 500);
        }
        $upd->close();
        $respond(true, ['message' => 'Status updated successfully.']);
    }

    if ($action === 'delete_application') {
        $typed = trim((string) ($_POST['confirm_reference'] ?? ''));
        if ($typed === '' || $typed !== (string) ($app['reference_id'] ?? '')) {
            $respond(false, ['message' => 'Reference ID does not match. Deletion cancelled.']);
        }

        foreach (['passport_file', 'cv_file'] as $col) {
            $abs = kep_abs_upload_path((string) ($app[$col] ?? ''));
            if ($abs !== null) {
                @unlink($abs);
            }
        }

        $del = $conn->prepare('DELETE FROM korea_event_applications WHERE id = ? LIMIT 1');
        if (!$del) {
            $respond(false, ['message' => 'Could not delete application'], 500);
        }
        $del->bind_param('i', $appId);
        $del->execute();
        $del->close();

        $respond(true, ['message' => 'Application deleted.']);
    }

    $respond(false, ['message' => 'Unknown action']);
}

$notifyEmail = kep_notify_recipient_email();
$notifyEmailOk = $notifyEmail !== '';

$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where = ['1=1'];
$params = [];
$types = '';

if ($status_filter !== 'all' && in_array($status_filter, ['pending', 'under_review', 'approved', 'rejected'], true)) {
    $where[] = 'status = ?';
    $params[] = $status_filter;
    $types .= 's';
}
if ($search !== '') {
    $where[] = '(full_name LIKE ? OR email LIKE ? OR reference_id LIKE ? OR passport_number LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}

$sql = 'SELECT * FROM korea_event_applications WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 300';
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$counts = ['pending' => 0, 'under_review' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
$cr = $conn->query('SELECT status, COUNT(*) c FROM korea_event_applications GROUP BY status');
if ($cr) {
    while ($r = $cr->fetch_assoc()) {
        if (isset($counts[$r['status']])) {
            $counts[$r['status']] = (int) $r['c'];
        }
        $counts['total'] += (int) $r['c'];
    }
}

function kep_status_badge(string $s): string
{
    return 'badge-' . preg_replace('/[^a-z_]/', '', $s);
}

$genders = kep_gender_options();

$viewModel = [];
foreach ($apps as $a) {
    $passportRel = pcvc_norm_upload_rel_path((string) ($a['passport_file'] ?? ''));
    $cvRel = pcvc_norm_upload_rel_path((string) ($a['cv_file'] ?? ''));
    $dob = (string) ($a['date_of_birth'] ?? '');
    if ($dob !== '' && $dob !== '0000-00-00') {
        $ts = strtotime($dob);
        $dob = $ts ? date('j M Y', $ts) : $dob;
    } else {
        $dob = '';
    }
    $viewModel[(int) $a['id']] = [
        'id'            => (int) $a['id'],
        'reference_id'  => $a['reference_id'] ?? '',
        'full_name'     => $a['full_name'] ?? '',
        'email'         => $a['email'] ?? '',
        'phone'         => trim('+' . ($a['phone_area_code'] ?? '') . ' ' . ($a['phone_number'] ?? '')),
        'messaging_app' => ucfirst((string) ($a['messaging_app'] ?? 'whatsapp')),
        'passport'      => $a['passport_number'] ?? '',
        'dob'           => $dob,
        'gender'        => kep_gender_label((string) ($a['gender'] ?? '')),
        'nationality'   => $a['nationality'] ?? '',
        'residence'     => $a['country_of_residence'] ?? '',
        'occupation'    => $a['occupation'] ?? '',
        'organization'  => $a['organization'] ?? '',
        'event_name'    => $a['event_name'] ?? '',
        'purpose'       => $a['participation_purpose'] ?? '',
        'status'        => $a['status'] ?? 'pending',
        'source'        => $a['source'] ?? 'public',
        'notes'         => $a['admin_notes'] ?? '',
        'created'       => !empty($a['created_at']) ? date('M j, Y H:i', strtotime($a['created_at'])) : '',
        'passport_view' => $passportRel !== '' ? pcvc_secure_file_url($passportRel, ['inline' => true]) : '',
        'passport_dl'   => $passportRel !== '' ? pcvc_secure_file_url($passportRel) : '',
        'cv_view'       => $cvRel !== '' ? pcvc_secure_file_url($cvRel, ['inline' => true]) : '',
        'cv_dl'         => $cvRel !== '' ? pcvc_secure_file_url($cvRel) : '',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>South Korea Event Participation Applications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --kep-red:#CD2E3A; --kep-blue:#0047A0; }
        body { background:#f4f6f8; -webkit-text-size-adjust:100%; }
        .page-head {
            background:linear-gradient(135deg,var(--kep-red),var(--kep-blue));
            color:#fff; padding:clamp(1rem,4vw,1.75rem) 0; margin-bottom:1rem;
        }
        .stat-card {
            background:#fff; border-radius:10px; padding:.85rem; text-align:center;
            border:1px solid #e2e8f0; height:100%;
        }
        .stat-card strong { font-size:clamp(1.25rem,4vw,1.5rem); color:var(--kep-blue); display:block; }
        .badge-pending { background:#fef3c7; color:#92400e; }
        .badge-under_review { background:#dbeafe; color:#1e40af; }
        .badge-approved { background:#d1fae5; color:#065f46; }
        .badge-rejected { background:#fee2e2; color:#991b1b; }
        .badge-admin { background:#e0e7ff; color:#3730a3; }
        .upload-zone {
            border: 2px dashed #cbd5e1; border-radius: 10px;
            padding: 0.85rem 0.7rem; text-align: center;
            cursor: pointer; background: #fafafa;
        }
        .upload-zone:hover, .upload-zone.dragover { border-color: var(--kep-blue); background: #f0f9ff; }
        .file-chip {
            display: flex; align-items: center; justify-content: space-between; gap: .5rem;
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: .5rem .7rem; margin-top: .45rem; font-size: .86rem;
        }
        .reg-label { font-weight: 600; font-size: .9rem; margin-bottom: .3rem; display: block; }
        .reg-label.required::after { content: " *"; color: var(--kep-red); }
        .app-card {
            background:#fff; border:1px solid #e2e8f0; border-radius:12px;
            padding:1rem; margin-bottom:.75rem; box-shadow:0 1px 4px rgba(0,0,0,.04);
        }
        .app-card .meta { font-size:.85rem; color:#64748b; }
        .table-desktop { display:none; }
        .cards-mobile { display:block; }
        @media (min-width: 768px) {
            .table-desktop { display:block; }
            .cards-mobile { display:none; }
        }
        @media (max-width: 575.98px) { .modal-dialog { margin:.5rem; } }
        .kv td { padding:6px 8px; border-bottom:1px solid #eef2f6; font-size:.9rem; vertical-align:top; }
        .kv td:first-child { color:#64748b; width:42%; }
    </style>
</head>
<body>
<div class="page-head">
    <div class="container-fluid px-3 px-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <h1 class="h4 h3-md mb-1"><i class="fas fa-flag me-2"></i>South Korea Event Participation</h1>
                <p class="mb-0 opacity-75 small">Review applications, passport scans, and CVs</p>
            </div>
            <button type="button" class="btn btn-light fw-semibold" data-bs-toggle="modal" data-bs-target="#registerModal">
                <i class="fas fa-user-plus me-1"></i> Register applicant
            </button>
        </div>
    </div>
</div>

<div class="container-fluid px-3 px-md-4 pb-5">
    <?php if (!$notifyEmailOk): ?>
    <div class="alert alert-warning py-2 small">
        <i class="fas fa-exclamation-triangle me-1"></i>
        Optional: set <code>KOREA_EVENT_NOTIFY_EMAIL</code> in <code>.env</code> to receive new applications with passport and CV attached.
    </div>
    <?php endif; ?>

    <p class="small text-muted mb-3">Office registration is saved to this list only. No confirmation email is sent to the applicant or the office.</p>

    <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-md-3"><div class="stat-card"><strong><?= $counts['total'] ?></strong><span class="small text-muted">Total</span></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><strong><?= $counts['pending'] ?></strong><span class="small text-muted">Pending</span></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><strong><?= $counts['under_review'] ?></strong><span class="small text-muted">Review</span></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><strong><?= $counts['approved'] ?></strong><span class="small text-muted">Approved</span></div></div>
    </div>

    <form class="row g-2 mb-3" method="get">
        <div class="col-12 col-md-4">
            <select name="status" class="form-select">
                <option value="all">All statuses</option>
                <?php foreach (['pending','under_review','approved','rejected'] as $s): ?>
                <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6">
            <input type="search" name="search" class="form-control" placeholder="Search name, email, reference, passport…" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-12 col-md-2">
            <button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Filter</button>
        </div>
    </form>

    <div class="cards-mobile">
        <?php if (!$apps): ?>
            <p class="text-center text-muted py-4">No applications found.</p>
        <?php else: foreach ($apps as $a): ?>
            <div class="app-card">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <div class="fw-bold"><?= htmlspecialchars((string) $a['full_name']) ?></div>
                        <code class="small"><?= htmlspecialchars((string) $a['reference_id']) ?></code>
                    </div>
                    <span class="badge <?= kep_status_badge((string) $a['status']) ?>"><?= ucwords(str_replace('_',' ',(string) $a['status'])) ?></span>
                </div>
                <div class="meta mb-2">
                    <?= htmlspecialchars((string) $a['email']) ?><br>
                    <?= htmlspecialchars((string) $a['event_name']) ?> · <?= date('M j, Y', strtotime((string) $a['created_at'])) ?>
                    <?php if (($a['source'] ?? '') === 'admin'): ?>
                        · <span class="badge badge-admin">Office</span>
                    <?php endif; ?>
                </div>
                <button class="btn btn-outline-primary btn-sm w-100" onclick="viewApp(<?= (int)$a['id'] ?>)">
                    <i class="fas fa-eye me-1"></i> View &amp; manage
                </button>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="card border-0 shadow-sm table-desktop">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Reference</th>
                        <th>Candidate</th>
                        <th>Event</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$apps): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No applications found.</td></tr>
                <?php else: foreach ($apps as $a): ?>
                    <tr>
                        <td><code><?= htmlspecialchars((string) $a['reference_id']) ?></code></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars((string) $a['full_name']) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) $a['email']) ?></div>
                            <?php if (($a['source'] ?? '') === 'admin'): ?>
                            <span class="badge badge-admin">Office</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= htmlspecialchars((string) $a['event_name']) ?></td>
                        <td><span class="badge <?= kep_status_badge((string) $a['status']) ?>"><?= ucwords(str_replace('_',' ',(string) $a['status'])) ?></span></td>
                        <td class="small"><?= date('M j, Y', strtotime((string) $a['created_at'])) ?></td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewApp(<?= (int)$a['id'] ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" title="Delete application"
                                    onclick="deleteApplication(<?= (int)$a['id'] ?>, <?= htmlspecialchars(json_encode($a['reference_id']), ENT_QUOTES) ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Register applicant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    This office registration is saved here only. <strong>No email is sent.</strong>
                </div>
                <form id="registerForm" novalidate>
                    <input type="hidden" name="passport_file" id="reg_passport_file" value="">
                    <input type="hidden" name="cv_file" id="reg_cv_file" value="">
                    <div class="mb-3">
                        <label class="reg-label required" for="reg_full_name">Full name</label>
                        <input type="text" class="form-control" id="reg_full_name" name="full_name" required maxlength="200" placeholder="As on passport">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="reg-label required" for="reg_dob">Date of birth</label>
                            <input type="date" class="form-control" id="reg_dob" name="date_of_birth" required>
                        </div>
                        <div class="col-md-6">
                            <label class="reg-label required" for="reg_passport_number">Passport number</label>
                            <input type="text" class="form-control" id="reg_passport_number" name="passport_number" required maxlength="64">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="reg-label required">Gender</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php $gi = 0; foreach ($genders as $key => $label): $gi++; ?>
                            <label class="border rounded px-3 py-2 mb-0" style="cursor:pointer">
                                <input type="radio" name="gender" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $gi === 1 ? 'checked' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="reg-label required" for="reg_nationality">Nationality</label>
                            <input type="text" class="form-control" id="reg_nationality" name="nationality" required maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="reg-label required" for="reg_residence">Country of residence</label>
                            <input type="text" class="form-control" id="reg_residence" name="country_of_residence" required maxlength="100">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="reg-label required" for="reg_email">Email</label>
                        <input type="email" class="form-control" id="reg_email" name="email" required maxlength="150" placeholder="For the record only — not emailed">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="reg-label required" for="reg_area">Country code</label>
                            <input type="text" class="form-control" id="reg_area" name="phone_area_code" required maxlength="6" value="250" placeholder="250">
                        </div>
                        <div class="col-md-8">
                            <label class="reg-label required" for="reg_phone">Phone number</label>
                            <input type="tel" class="form-control" id="reg_phone" name="phone_number" required maxlength="20" placeholder="Without country code">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="reg-label required">Preferred app</label>
                        <div class="d-flex gap-2">
                            <label class="border rounded px-3 py-2 mb-0" style="cursor:pointer">
                                <input type="radio" name="messaging_app" value="whatsapp" checked> WhatsApp
                            </label>
                            <label class="border rounded px-3 py-2 mb-0" style="cursor:pointer">
                                <input type="radio" name="messaging_app" value="telegram"> Telegram
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="reg-label required" for="reg_event">Event / program name</label>
                        <input type="text" class="form-control" id="reg_event" name="event_name" required maxlength="200" value="South Korea Event">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="reg-label required" for="reg_occupation">Occupation</label>
                            <input type="text" class="form-control" id="reg_occupation" name="occupation" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="reg-label" for="reg_org">Organization</label>
                            <input type="text" class="form-control" id="reg_org" name="organization" maxlength="150">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="reg-label" for="reg_purpose">Purpose of participation</label>
                        <textarea class="form-control" id="reg_purpose" name="participation_purpose" maxlength="1500" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="reg-label required">Passport scan</label>
                        <div class="upload-zone" id="regPassportZone" role="button" tabindex="0">
                            <div id="regPassportZoneInner">
                                <i class="fas fa-cloud-upload-alt mb-1 text-secondary"></i>
                                <div>Tap to upload passport</div>
                                <div class="small text-muted">PDF, JPG, PNG, WEBP, DOC — max 15MB</div>
                            </div>
                        </div>
                        <input type="file" id="regPassportInput" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,image/*,application/pdf" hidden>
                        <div id="regPassportPreview"></div>
                    </div>
                    <div class="mb-0">
                        <label class="reg-label required">CV / Resume</label>
                        <div class="upload-zone" id="regCvZone" role="button" tabindex="0">
                            <div id="regCvZoneInner">
                                <i class="fas fa-cloud-upload-alt mb-1 text-secondary"></i>
                                <div>Tap to upload CV</div>
                                <div class="small text-muted">PDF or Word preferred — max 15MB</div>
                            </div>
                        </div>
                        <input type="file" id="regCvInput" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,image/*,application/pdf" hidden>
                        <div id="regCvPreview"></div>
                    </div>
                    <div id="regError" class="text-danger small mt-3" style="display:none"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="regSubmitBtn">
                    <i class="fas fa-save me-1"></i> Save without sending email
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailBody"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF = <?= json_encode($_SESSION['csrf_token']) ?>;
const APPS = <?= json_encode($viewModel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const modal = new bootstrap.Modal(document.getElementById('detailModal'));

function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
}

function viewApp(id) {
    const a = APPS[id];
    if (!a) return;
    let docsHtml = '';
    if (a.passport_view) docsHtml += docRow('Passport Scan', a.passport_view, a.passport_dl);
    if (a.cv_view) docsHtml += docRow('CV / Resume', a.cv_view, a.cv_dl);
    if (!docsHtml) docsHtml = '<p class="text-muted small">No documents on file.</p>';

    const notes = a.notes ? '<tr><td>Admin Notes</td><td>' + esc(a.notes) + '</td></tr>' : '';
    const purpose = a.purpose ? '<tr><td>Purpose</td><td>' + esc(a.purpose) + '</td></tr>' : '';

    document.getElementById('detailBody').innerHTML =
        '<div class="mb-2"><span class="badge badge-' + esc(a.status) + '">' + esc(a.status.replace(/_/g,' ')) + '</span></div>'
        + '<table class="table table-sm kv"><tbody>'
        + '<tr><td>Full Name</td><td>' + esc(a.full_name) + '</td></tr>'
        + '<tr><td>Reference</td><td><code>' + esc(a.reference_id) + '</code></td></tr>'
        + '<tr><td>Date of Birth</td><td>' + esc(a.dob) + '</td></tr>'
        + '<tr><td>Gender</td><td>' + esc(a.gender) + '</td></tr>'
        + '<tr><td>Nationality</td><td>' + esc(a.nationality) + '</td></tr>'
        + '<tr><td>Residence</td><td>' + esc(a.residence) + '</td></tr>'
        + '<tr><td>Passport No.</td><td>' + esc(a.passport) + '</td></tr>'
        + '<tr><td>Phone</td><td>' + esc(a.phone) + ' (' + esc(a.messaging_app) + ')</td></tr>'
        + '<tr><td>Email</td><td>' + esc(a.email) + '</td></tr>'
        + '<tr><td>Occupation</td><td>' + esc(a.occupation) + '</td></tr>'
        + '<tr><td>Organization</td><td>' + esc(a.organization) + '</td></tr>'
        + '<tr><td>Event</td><td>' + esc(a.event_name) + '</td></tr>'
        + '<tr><td>Registered via</td><td>' + (a.source === 'admin' ? 'Office (no email)' : 'Public form') + '</td></tr>'
        + purpose
        + '<tr><td>Submitted</td><td>' + esc(a.created) + '</td></tr>'
        + notes
        + '</tbody></table>'
        + '<h6 class="mt-3">Documents</h6>' + docsHtml
        + '<h6 class="mt-3">Update Status</h6>'
        + '<div class="d-flex flex-wrap gap-2">'
        + statusBtn(a.id, 'under_review', 'Under Review', 'warning')
        + statusBtn(a.id, 'approved', 'Approve', 'success')
        + statusBtn(a.id, 'rejected', 'Reject', 'danger')
        + statusBtn(a.id, 'pending', 'Reset to Pending', 'secondary')
        + '</div>';
    modal.show();
}

function docRow(label, view, dl) {
    return '<div class="border rounded p-2 mb-2 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">'
        + '<span class="text-break"><i class="fas fa-file me-2"></i>' + esc(label) + '</span>'
        + '<span class="d-flex gap-1">'
        + '<a href="' + esc(view) + '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> View</a>'
        + '<a href="' + esc(dl) + '" class="btn btn-sm btn-primary"><i class="fas fa-download"></i> Download</a>'
        + '</span></div>';
}

function statusBtn(id, status, label, color) {
    return '<button class="btn btn-sm btn-' + color + '" onclick="setStatus(' + id + ',\'' + status + '\')">' + esc(label) + '</button>';
}

function setStatus(id, status) {
    let note = '';
    if (status === 'rejected' || status === 'under_review') {
        const typed = prompt('Optional internal note (saved on the application):', '');
        if (typed === null) return;
        note = typed;
    }
    if (!confirm('Set status to "' + status.replace(/_/g, ' ') + '"?')) return;

    postAction({ action: 'set_status', application_id: id, status: status, note: note }).then(d => {
        alert(d.message || 'Status updated successfully.');
        location.reload();
    }).catch(e => alert(e.message || 'Action failed'));
}

function deleteApplication(id, referenceId) {
    const typed = prompt('Delete application ' + referenceId + '?\n\nThis removes the application and its documents.\nType the reference ID to confirm:');
    if (typed === null) return;
    postAction({ action: 'delete_application', application_id: id, confirm_reference: typed }).then(d => {
        alert(d.message || 'Deleted');
        location.reload();
    }).catch(e => alert(e.message || 'Delete failed'));
}

function postAction(data) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    Object.keys(data).forEach(k => fd.append(k, data[k]));
    return fetch('korea-event-applications.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    })
        .then(async r => {
            const text = await r.text();
            let d;
            try {
                d = JSON.parse(text);
            } catch (e) {
                throw new Error(
                    r.status === 403 || /login/i.test(text)
                        ? 'Session expired. Refresh the dashboard and log in again.'
                        : 'Server returned an invalid response. Please refresh and try again.'
                );
            }
            if (!d.success) {
                let msg = d.message || 'Request failed';
                if (Array.isArray(d.missing) && d.missing.length) {
                    msg += ': ' + d.missing.join(', ');
                }
                throw new Error(msg);
            }
            return d;
        });
}

function uploadAdminFile(file, field, onProgress) {
    return new Promise((resolve, reject) => {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('field', field);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'korea_event_upload.php');
        xhr.timeout = 120000;
        if (xhr.upload && typeof onProgress === 'function') {
            xhr.upload.onprogress = (ev) => {
                if (ev.lengthComputable && ev.total > 0) onProgress(Math.round((ev.loaded / ev.total) * 100));
            };
        }
        xhr.onload = () => {
            let data;
            try { data = JSON.parse(xhr.responseText || '{}'); } catch (e) { reject(new Error('Upload failed')); return; }
            if (!data.success) { reject(new Error(data.message || 'Upload failed')); return; }
            resolve(data);
        };
        xhr.ontimeout = () => reject(new Error('Upload timed out'));
        xhr.onerror = () => reject(new Error('Network error during upload'));
        xhr.send(fd);
    });
}

function setRegFile(hiddenId, previewId, path, name) {
    document.getElementById(hiddenId).value = path || '';
    const box = document.getElementById(previewId);
    if (!path) { box.innerHTML = ''; return; }
    const clearId = 'clr_' + hiddenId;
    box.innerHTML = '<div class="file-chip"><span><i class="fas fa-file me-1"></i>' + esc(name || 'File') + '</span>'
        + '<button type="button" class="btn btn-sm btn-outline-danger" id="' + clearId + '">Remove</button></div>';
    document.getElementById(clearId)?.addEventListener('click', () => setRegFile(hiddenId, previewId, '', ''));
}

function wireRegZone(zoneId, inputId, field, hiddenId, previewId, innerId) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    const inner = document.getElementById(innerId);
    const open = () => input.click();
    zone.addEventListener('click', open);
    zone.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); }
    });
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer.files?.length) handle(e.dataTransfer.files[0]);
    });
    input.addEventListener('change', () => {
        if (input.files?.length) handle(input.files[0]);
        input.value = '';
    });
    async function handle(file) {
        if (!file) return;
        if (file.size > 15 * 1024 * 1024) {
            alert('File too large (max 15MB)');
            return;
        }
        const defaultHtml = inner.innerHTML;
        inner.innerHTML = '<span class="text-primary"><i class="fas fa-spinner fa-spin"></i> Uploading ' + esc(file.name) + '…</span>';
        try {
            const res = await uploadAdminFile(file, field, (pct) => {
                inner.innerHTML = '<span class="text-primary"><i class="fas fa-spinner fa-spin"></i> Uploading ' + esc(file.name) + '… ' + pct + '%</span>';
            });
            inner.innerHTML = defaultHtml;
            setRegFile(hiddenId, previewId, res.file_path, res.original_name || file.name);
        } catch (err) {
            inner.innerHTML = '<span class="text-danger">' + esc(err.message) + '</span>';
            setTimeout(() => { inner.innerHTML = defaultHtml; }, 3500);
        }
    }
}

wireRegZone('regPassportZone', 'regPassportInput', 'passport', 'reg_passport_file', 'regPassportPreview', 'regPassportZoneInner');
wireRegZone('regCvZone', 'regCvInput', 'cv', 'reg_cv_file', 'regCvPreview', 'regCvZoneInner');

if (new URLSearchParams(window.location.search).get('register') === '1') {
    new bootstrap.Modal(document.getElementById('registerModal')).show();
}

document.getElementById('regSubmitBtn').addEventListener('click', async () => {
    const errEl = document.getElementById('regError');
    const btn = document.getElementById('regSubmitBtn');
    errEl.style.display = 'none';
    const form = document.getElementById('registerForm');
    const email = String(document.getElementById('reg_email').value || '').trim();
    if (!form.full_name.value.trim()) { errEl.textContent = 'Please enter the full name.'; errEl.style.display = 'block'; return; }
    if (!document.getElementById('reg_dob').value) { errEl.textContent = 'Please enter date of birth.'; errEl.style.display = 'block'; return; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { errEl.textContent = 'Please enter a valid email (kept on file; not emailed).'; errEl.style.display = 'block'; return; }
    if (!document.getElementById('reg_phone').value.trim()) { errEl.textContent = 'Please enter a phone number.'; errEl.style.display = 'block'; return; }
    if (!document.getElementById('reg_passport_file').value) { errEl.textContent = 'Please upload the passport scan.'; errEl.style.display = 'block'; return; }
    if (!document.getElementById('reg_cv_file').value) { errEl.textContent = 'Please upload the CV.'; errEl.style.display = 'block'; return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving…';
    const fd = new FormData(form);
    fd.append('action', 'register_application');
    try {
        const d = await postAction(Object.fromEntries(fd.entries()));
        alert((d.message || 'Registered.') + (d.reference_id ? '\nReference: ' + d.reference_id : ''));
        location.reload();
    } catch (e) {
        errEl.textContent = e.message || 'Could not register applicant.';
        errEl.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-1"></i> Save without sending email';
    }
});
</script>
</body>
</html>
