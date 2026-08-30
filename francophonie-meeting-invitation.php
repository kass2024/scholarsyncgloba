<?php
/**
 * francophonie-meeting-invitation.php — Schedule Zoom meetings & invite FM + CEAK students.
 */
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/francophonie_meeting_invitation_schema.php';
require_once __DIR__ . '/helpers/zoom_meeting_api.php';
require_once __DIR__ . '/helpers/zoom_meeting_sdk.php';
require_once __DIR__ . '/helpers/francophonie_meeting_attendance.php';

xander_load_env_file();
fm_meeting_ensure_schema($conn);

if (empty($_SESSION['id'])) {
    header('Location: admin-login.php');
    exit;
}
pcvc_require_staff_or_superadmin($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$zoomStatus = zoom_api_connection_status();
$zoomOk = !empty($zoomStatus['ok']);
$zoomSdkAssetsOk = fm_zoom_sdk_assets_installed();
$zoomEmbedOk = zoom_sdk_is_configured();

$fmStatus = $_GET['fm_status'] ?? 'all';
$fmSearch = trim($_GET['fm_search'] ?? '');
$studentSearch = trim($_GET['student_search'] ?? '');
$universityFilter = (int) ($_GET['university_id'] ?? 0);
$ceakOnly = isset($_GET['ceak']) && $_GET['ceak'] === '1';

$fmWhere = ['email IS NOT NULL', "TRIM(email) <> ''"];
$fmParams = [];
$fmTypes = '';

if ($fmStatus !== 'all' && in_array($fmStatus, ['pending', 'under_review', 'approved', 'rejected'], true)) {
    $fmWhere[] = 'status = ?';
    $fmParams[] = $fmStatus;
    $fmTypes .= 's';
}
if ($fmSearch !== '') {
    $fmWhere[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR reference_id LIKE ?)';
    $like = '%' . $fmSearch . '%';
    array_push($fmParams, $like, $like, $like, $like);
    $fmTypes .= 'ssss';
}

$fmSql = 'SELECT id, reference_id, first_name, last_name, email, status, university_name, created_at
          FROM francophonie_mobility_applications WHERE ' . implode(' AND ', $fmWhere) . '
          ORDER BY created_at DESC LIMIT 300';
$fmStmt = $conn->prepare($fmSql);
if ($fmParams) {
    $fmStmt->bind_param($fmTypes, ...$fmParams);
}
$fmStmt->execute();
$fmApplicants = $fmStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$fmStmt->close();

$universities = [];
$uniRes = $conn->query('SELECT id, name FROM universities ORDER BY name');
if ($uniRes) {
    while ($u = $uniRes->fetch_assoc()) {
        $universities[] = $u;
    }
}

$ceakUniversityIds = [];
foreach ($universities as $u) {
    if (stripos((string) $u['name'], 'CEAK') !== false) {
        $ceakUniversityIds[] = (int) $u['id'];
    }
}

if ($ceakOnly && $ceakUniversityIds !== []) {
    $universityFilter = $ceakUniversityIds[0];
}

$studentWhere = ["sa.email IS NOT NULL", "TRIM(sa.email) <> ''"];
$studentParams = [];
$studentTypes = '';

if ($studentSearch !== '') {
    $studentWhere[] = '(sa.first_name LIKE ? OR sa.last_name LIKE ? OR sa.email LIKE ? OR sa.application_id LIKE ?)';
    $like = '%' . $studentSearch . '%';
    array_push($studentParams, $like, $like, $like, $like);
    $studentTypes .= 'ssss';
}

if ($universityFilter > 0) {
    $studentWhere[] = '(sa.university_id = ? OR EXISTS (
        SELECT 1 FROM application_study_choices sc
        WHERE sc.application_id = sa.id AND sc.university_id = ?
    ))';
    $studentParams[] = $universityFilter;
    $studentParams[] = $universityFilter;
    $studentTypes .= 'ii';
} elseif ($ceakOnly) {
    $studentWhere[] = "(COALESCE(u.name, '') LIKE '%CEAK%' OR COALESCE(sa.college_name, '') LIKE '%CEAK%')";
}

$studentSql = "SELECT sa.id, sa.first_name, sa.last_name, sa.email, sa.application_id,
               COALESCE(u.name, sa.college_name, '') AS university_label,
               sa.submitted, sa.created_at
               FROM student_applications sa
               LEFT JOIN universities u ON u.id = sa.university_id
               WHERE " . implode(' AND ', $studentWhere) . "
               ORDER BY sa.created_at DESC LIMIT 300";
$studentStmt = $conn->prepare($studentSql);
if ($studentParams) {
    $studentStmt->bind_param($studentTypes, ...$studentParams);
}
$studentStmt->execute();
$studentApplicants = $studentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$studentStmt->close();

$history = [];
$histRes = $conn->query(
    'SELECT id, topic, start_time, duration_minutes, recipient_count, emails_sent, emails_failed,
            zoom_meeting_number, zoom_password, guest_join_token, created_at
     FROM francophonie_mobility_meeting_invitations ORDER BY created_at DESC LIMIT 50'
);
if ($histRes) {
    $history = $histRes->fetch_all(MYSQLI_ASSOC);
}

$defaultTopic = 'Mobilité Francophone — Information Session';
$defaultStart = (new DateTime('now', new DateTimeZone('America/Toronto')))
    ->modify('+1 day')->setTime(10, 0)->format('Y-m-d\TH:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Invitation — Francophonie Mobility</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --fm-green:#1e4d2b; --fm-blue:#3661B9; --fm-bg:#f4f6f3; }
        body { background:var(--fm-bg); font-size:.95rem; }
        .hero {
            background:linear-gradient(135deg,var(--fm-green),var(--fm-blue));
            color:#fff; padding:1.25rem 0 1rem; margin-bottom:1rem;
        }
        .panel {
            background:#fff; border:1px solid #e2e8f0; border-radius:14px;
            box-shadow:0 2px 12px rgba(0,0,0,.04); height:100%;
        }
        .panel-head {
            padding:.85rem 1rem; border-bottom:1px solid #e2e8f0;
            font-weight:600; display:flex; align-items:center; gap:.5rem;
        }
        .panel-body { padding:1rem; }
        .recipient-list {
            max-height:420px; overflow-y:auto; border:1px solid #e2e8f0;
            border-radius:10px; background:#fafbfc;
        }
        .recipient-row {
            display:flex; align-items:flex-start; gap:.65rem; padding:.65rem .75rem;
            border-bottom:1px solid #edf2f7; cursor:pointer; transition:background .15s;
        }
        .recipient-row:hover { background:#f0fdf4; }
        .recipient-row:last-child { border-bottom:0; }
        .recipient-row .meta { font-size:.78rem; color:#64748b; }
        .recipient-row input { margin-top:.2rem; flex-shrink:0; width:1.1rem; height:1.1rem; }
        .badge-pending { background:#fef3c7; color:#92400e; }
        .badge-under_review { background:#dbeafe; color:#1e40af; }
        .badge-approved { background:#d1fae5; color:#065f46; }
        .badge-rejected { background:#fee2e2; color:#991b1b; }
        .sticky-actions {
            position:sticky; bottom:0; background:#fff; border-top:1px solid #e2e8f0;
            padding:.85rem 1rem; border-radius:0 0 14px 14px; z-index:5;
        }
        .selected-pill {
            display:inline-flex; align-items:center; gap:.35rem;
            background:#ecfdf5; color:#065f46; border-radius:999px;
            padding:.2rem .65rem; font-size:.8rem; font-weight:600;
        }
        .nav-pills .nav-link { color:#334155; border-radius:8px; font-weight:500; }
        .nav-pills .nav-link.active { background:var(--fm-green); }
        .history-card { font-size:.85rem; border-left:4px solid var(--fm-blue); }
        .history-table-wrap { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
        .history-table { margin:0; font-size:.88rem; }
        .history-table th { background:#f8fafc; font-weight:600; white-space:nowrap; }
        .history-table td { vertical-align:middle; }
        #sendResult { display:none; }
        .zoom-badge { font-size:.75rem; }
        @media (max-width: 991.98px) {
            .recipient-list { max-height:320px; }
        }
    </style>
</head>
<body>

<div class="hero">
    <div class="container-fluid px-3 px-lg-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <h1 class="h4 mb-1"><i class="fas fa-video me-2"></i>Meeting Invitation</h1>
                <p class="mb-0 opacity-75 small">Create a Zoom meeting and email join links to Francophonie Mobility applicants &amp; CEAK student applications</p>
            </div>
            <div class="text-end">
                <?php if ($zoomOk): ?>
                <span class="badge bg-success zoom-badge"><i class="fas fa-check-circle me-1"></i>Zoom API connected</span>
                <?php else: ?>
                <span class="badge bg-danger zoom-badge"><i class="fas fa-exclamation-triangle me-1"></i>Zoom API not ready</span>
                <?php endif; ?>
                <?php if ($zoomSdkAssetsOk): ?>
                <span class="badge bg-success zoom-badge ms-1"><i class="fas fa-box me-1"></i>SDK installed</span>
                <?php else: ?>
                <span class="badge bg-warning text-dark zoom-badge ms-1"><i class="fas fa-download me-1"></i>Run npm install</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-3 px-lg-4 pb-5">
    <?php if (!$zoomSdkAssetsOk): ?>
    <div class="alert alert-warning py-2 small mb-3">
        <i class="fas fa-terminal me-1"></i>
        Zoom Meeting SDK browser files are missing. In the <code>scholarsyncglobal</code> folder run: <code>npm install</code>
    </div>
    <?php endif; ?>
    <?php if (!$zoomEmbedOk): ?>
    <div class="alert alert-warning py-2 small mb-3">
        <i class="fas fa-key me-1"></i>
        Set <code>ZOOM_EMBED_CLIENT_ID</code> and <code>ZOOM_EMBED_CLIENT_SECRET</code> in .env for in-browser meetings.
    </div>
    <?php endif; ?>
    <?php if (!$zoomOk): ?>
    <div class="alert alert-danger py-2 small mb-3">
        <i class="fas fa-plug me-1"></i>
        <?= htmlspecialchars($zoomStatus['message'] ?? 'Configure ZOOM_ACCOUNT_ID, ZOOM_CLIENT_ID, ZOOM_CLIENT_SECRET, and ZOOM_HOST_USER_ID in .env.', ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <div id="sendResult" class="alert mb-3" role="alert"></div>

    <form method="get" id="fmFilterForm" class="d-none" aria-hidden="true">
        <?php if ($studentSearch || $universityFilter || $ceakOnly): ?>
        <input type="hidden" name="student_search" value="<?= htmlspecialchars($studentSearch, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="university_id" value="<?= $universityFilter ?>">
        <?php if ($ceakOnly): ?><input type="hidden" name="ceak" value="1"><?php endif; ?>
        <?php endif; ?>
    </form>
    <form method="get" id="studentFilterForm" class="d-none" aria-hidden="true">
        <?php if ($fmSearch || $fmStatus !== 'all'): ?>
        <input type="hidden" name="fm_search" value="<?= htmlspecialchars($fmSearch, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="fm_status" value="<?= htmlspecialchars($fmStatus, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
    </form>

    <form id="inviteForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="panel">
                    <div class="panel-head"><i class="fas fa-calendar-alt text-success"></i> Meeting details</div>
                    <div class="panel-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="topic">Topic</label>
                            <input type="text" class="form-control" id="topic" name="topic" required value="<?= htmlspecialchars($defaultTopic, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="start_time">Date &amp; time</label>
                            <input type="datetime-local" class="form-control" id="start_time" name="start_time" required value="<?= htmlspecialchars($defaultStart, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold" for="duration">Duration (min)</label>
                                <select class="form-select" id="duration" name="duration">
                                    <?php foreach ([30, 45, 60, 90, 120] as $d): ?>
                                    <option value="<?= $d ?>" <?= $d === 60 ? 'selected' : '' ?>><?= $d ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold" for="timezone">Timezone</label>
                                <select class="form-select" id="timezone" name="timezone">
                                    <option value="America/Toronto" selected>America/Toronto</option>
                                    <option value="America/Montreal">America/Montreal</option>
                                    <option value="America/Vancouver">America/Vancouver</option>
                                    <option value="Africa/Kigali">Africa/Kigali</option>
                                    <option value="UTC">UTC</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="agenda">Agenda <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea class="form-control" id="agenda" name="agenda" rows="2" placeholder="Brief agenda for the meeting…"></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold" for="custom_message">Personal note in email <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea class="form-control" id="custom_message" name="custom_message" rows="3" placeholder="Add a short message for recipients…"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="panel">
                    <div class="panel-head d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span><i class="fas fa-users text-primary"></i> Select recipients</span>
                        <span class="selected-pill"><i class="fas fa-user-check"></i> <span id="selectedCount">0</span> selected</span>
                    </div>
                    <div class="panel-body pb-0">
                        <ul class="nav nav-pills mb-3 gap-1" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tabFm" type="button">
                                    Francophonie Mobility <span class="badge bg-light text-dark ms-1"><?= count($fmApplicants) ?></span>
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabStudents" type="button">
                                    Student applications (CEAK) <span class="badge bg-light text-dark ms-1"><?= count($studentApplicants) ?></span>
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="tabFm">
                                <div class="row g-2 mb-2">
                                    <div class="col-md-4">
                                        <select class="form-select form-select-sm" id="fmStatusQuick" form="fmFilterForm" name="fm_status" onchange="document.getElementById('fmFilterForm').submit()">
                                            <option value="all" <?= $fmStatus === 'all' ? 'selected' : '' ?>>All statuses</option>
                                            <option value="pending" <?= $fmStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="under_review" <?= $fmStatus === 'under_review' ? 'selected' : '' ?>>Under review</option>
                                            <option value="approved" <?= $fmStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                                            <option value="rejected" <?= $fmStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="search" class="form-control form-control-sm" placeholder="Search name, email, reference…" value="<?= htmlspecialchars($fmSearch, ENT_QUOTES, 'UTF-8') ?>" form="fmFilterForm" name="fm_search">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100" form="fmFilterForm">Filter</button>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAllFm">
                                        <label class="form-check-label small" for="selectAllFm">Select all visible</label>
                                    </div>
                                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="clearFm">Clear</button>
                                </div>
                                <div class="recipient-list" id="fmList">
                                    <?php if ($fmApplicants === []): ?>
                                    <div class="p-3 text-muted small text-center">No Francophonie Mobility applicants match your filters.</div>
                                    <?php else: ?>
                                    <?php foreach ($fmApplicants as $app): ?>
                                    <?php
                                    $name = trim($app['first_name'] . ' ' . $app['last_name']);
                                    $status = (string) ($app['status'] ?? 'pending');
                                    ?>
                                    <label class="recipient-row w-100 mb-0">
                                        <input type="checkbox" class="fm-check recipient-check" name="fm_ids[]" value="<?= (int) $app['id'] ?>">
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="meta"><?= htmlspecialchars((string) $app['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="meta">
                                                <span class="badge badge-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></span>
                                                · <?= htmlspecialchars((string) $app['reference_id'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php if (!empty($app['university_name'])): ?>
                                                · <?= htmlspecialchars((string) $app['university_name'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="tabStudents">
                                <div class="row g-2 mb-2">
                                    <div class="col-md-4">
                                        <select name="university_id" form="studentFilterForm" class="form-select form-select-sm">
                                            <option value="0">All universities</option>
                                            <?php foreach ($universities as $u): ?>
                                            <option value="<?= (int) $u['id'] ?>" <?= $universityFilter === (int) $u['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string) $u['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <input type="search" name="student_search" form="studentFilterForm" class="form-control form-control-sm" placeholder="Search student name, email, application ID…" value="<?= htmlspecialchars($studentSearch, ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div class="col-md-3 d-flex gap-1">
                                        <button type="submit" form="studentFilterForm" class="btn btn-sm btn-outline-secondary flex-grow-1">Filter</button>
                                        <a href="?ceak=1<?= $fmSearch ? '&fm_search=' . urlencode($fmSearch) : '' ?><?= $fmStatus !== 'all' ? '&fm_status=' . urlencode($fmStatus) : '' ?>" class="btn btn-sm <?= $ceakOnly ? 'btn-success' : 'btn-outline-success' ?>" title="CEAK university preset">CEAK</a>
                                    </div>
                                </div>
                                <?php if ($ceakOnly && $ceakUniversityIds === []): ?>
                                <div class="alert alert-warning py-2 small">No university named CEAK found — showing students with CEAK in college/university name.</div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAllStudents">
                                        <label class="form-check-label small" for="selectAllStudents">Select all visible</label>
                                    </div>
                                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="clearStudents">Clear</button>
                                </div>
                                <div class="recipient-list" id="studentList">
                                    <?php if ($studentApplicants === []): ?>
                                    <div class="p-3 text-muted small text-center">No student applications match your filters. Try CEAK preset or pick a university.</div>
                                    <?php else: ?>
                                    <?php foreach ($studentApplicants as $app): ?>
                                    <?php $name = trim($app['first_name'] . ' ' . $app['last_name']); ?>
                                    <label class="recipient-row w-100 mb-0">
                                        <input type="checkbox" class="student-check recipient-check" name="student_ids[]" value="<?= (int) $app['id'] ?>">
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="meta"><?= htmlspecialchars((string) $app['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="meta">
                                                <?= htmlspecialchars((string) ($app['application_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                <?php if (!empty($app['university_label'])): ?>
                                                · <?= htmlspecialchars((string) $app['university_label'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php endif; ?>
                                                <?php if (!empty($app['submitted'])): ?>
                                                · <span class="text-success">Submitted</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sticky-actions d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="small text-muted" id="sendHint">Recipients receive a branded email with a browser join link (no Zoom app required).</div>
                        <button type="submit" class="btn btn-success px-4" id="sendBtn" <?= $zoomOk ? '' : 'disabled' ?>>
                            <i class="fas fa-paper-plane me-1"></i> Create meeting &amp; send invitations
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <?php if ($history !== []): ?>
    <div class="mt-4" id="historySection">
        <h2 class="h6 text-muted mb-2" id="historyTitle"><i class="fas fa-history me-1"></i> Previous meetings (<?= count($history) ?>)</h2>
        <div class="history-table-wrap table-responsive" id="historyTableWrap">
            <table class="table history-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Scheduled</th>
                        <th>Meeting ID</th>
                        <th>Emails</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                    <?php foreach ($history as $h):
                        $hid = (int) $h['id'];
                        $guestTok = trim((string) ($h['guest_join_token'] ?? ''));
                        if ($guestTok === '') {
                            $guestTok = fm_meeting_ensure_guest_join_token($conn, $hid);
                        }
                    ?>
                    <tr id="meeting-row-<?= (int) $h['id'] ?>">
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars((string) $h['topic'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-muted small"><?= (int) $h['duration_minutes'] ?> min · <?= (int) $h['recipient_count'] ?> invited</div>
                        </td>
                        <td class="small text-nowrap">
                            <?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $h['start_time'])), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="small font-monospace">
                            <?= htmlspecialchars((string) ($h['zoom_meeting_number'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($h['zoom_password'])): ?>
                            <div class="text-muted">pwd: <?= htmlspecialchars((string) $h['zoom_password'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <span class="text-success"><?= (int) $h['emails_sent'] ?> sent</span>
                            <?php if ((int) $h['emails_failed'] > 0): ?>
                            <span class="text-danger"> · <?= (int) $h['emails_failed'] ?> failed</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="francophonie-meeting-host.php?invitation_id=<?= $hid ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success" title="Start as Zoom host">
                                <i class="fas fa-play"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-copy-guest-link" data-url="<?= htmlspecialchars(fm_meeting_guest_join_url($hid, $guestTok), ENT_QUOTES, 'UTF-8') ?>" title="Copy external guest link">
                                <i class="fas fa-user-plus"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-attendance-report" data-id="<?= $hid ?>" title="Attendance report">
                                <i class="fas fa-clipboard-list"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-meeting" data-id="<?= (int) $h['id'] ?>" data-topic="<?= htmlspecialchars((string) $h['topic'], ENT_QUOTES, 'UTF-8') ?>" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3 text-muted small <?= $history === [] ? '' : 'd-none' ?>" id="historyEmpty">
            <i class="fas fa-info-circle me-1"></i>No previous meetings yet.
        </div>
    </div>
    <?php else: ?>
    <div class="mt-4" id="historySection">
        <h2 class="h6 text-muted mb-2" id="historyTitle"><i class="fas fa-history me-1"></i> Previous meetings (0)</h2>
        <div class="history-table-wrap table-responsive d-none" id="historyTableWrap">
            <table class="table history-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Scheduled</th>
                        <th>Meeting ID</th>
                        <th>Emails</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody"></tbody>
            </table>
        </div>
        <div class="text-muted small" id="historyEmpty"><i class="fas fa-info-circle me-1"></i>No previous meetings yet.</div>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="attendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Attendance report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="attendanceModalBody">
                <div class="text-muted small">Loading…</div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const form = document.getElementById('inviteForm');
    const sendBtn = document.getElementById('sendBtn');
    const sendResult = document.getElementById('sendResult');
    const selectedCount = document.getElementById('selectedCount');
    const csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '', JSON_THROW_ON_ERROR) ?>;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function updateHistoryCount() {
        const title = document.getElementById('historyTitle');
        const body = document.getElementById('historyTableBody');
        if (!title || !body) return;
        const count = body.querySelectorAll('tr').length;
        title.innerHTML = '<i class="fas fa-history me-1"></i> Previous meetings (' + count + ')';
    }

    function buildMeetingRowHtml(meeting) {
        const id = meeting.id;
        const topic = escapeHtml(meeting.topic || '');
        const topicAttr = escapeHtml(meeting.topic || '');
        const duration = Number(meeting.duration_minutes || 0);
        const recipients = Number(meeting.recipient_count || 0);
        const startDisplay = escapeHtml(meeting.start_time_display || meeting.start_time || '');
        const meetingNumber = escapeHtml(meeting.zoom_meeting_number || '—');
        const password = meeting.zoom_password ? String(meeting.zoom_password) : '';
        const sent = Number(meeting.emails_sent || 0);
        const failed = Number(meeting.emails_failed || 0);
        const guestUrl = escapeHtml(meeting.guest_join_url || '');
        const hostUrl = 'francophonie-meeting-host.php?invitation_id=' + encodeURIComponent(id);

        let html = '<tr id="meeting-row-' + id + '">';
        html += '<td><div class="fw-semibold">' + topic + '</div>';
        html += '<div class="text-muted small">' + duration + ' min · ' + recipients + ' invited</div></td>';
        html += '<td class="small text-nowrap">' + startDisplay + '</td>';
        html += '<td class="small font-monospace">' + meetingNumber;
        if (password) {
            html += '<div class="text-muted">pwd: ' + escapeHtml(password) + '</div>';
        }
        html += '</td><td class="small"><span class="text-success">' + sent + ' sent</span>';
        if (failed > 0) {
            html += '<span class="text-danger"> · ' + failed + ' failed</span>';
        }
        html += '</td><td class="text-end text-nowrap">';
        html += '<a href="' + hostUrl + '" target="_blank" rel="noopener" class="btn btn-sm btn-success" title="Start as Zoom host"><i class="fas fa-play"></i></a> ';
        html += '<button type="button" class="btn btn-sm btn-outline-primary btn-copy-guest-link" data-url="' + guestUrl + '" title="Copy external guest link"><i class="fas fa-user-plus"></i></button> ';
        html += '<button type="button" class="btn btn-sm btn-outline-secondary btn-attendance-report" data-id="' + id + '" title="Attendance report"><i class="fas fa-clipboard-list"></i></button> ';
        html += '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-meeting" data-id="' + id + '" data-topic="' + topicAttr + '" title="Delete"><i class="fas fa-trash"></i></button>';
        html += '</td></tr>';
        return html;
    }

    function prependMeetingRow(meeting) {
        if (!meeting || !meeting.id) return;
        const body = document.getElementById('historyTableBody');
        const wrap = document.getElementById('historyTableWrap');
        const empty = document.getElementById('historyEmpty');
        if (!body) return;

        if (document.getElementById('meeting-row-' + meeting.id)) {
            return;
        }

        body.insertAdjacentHTML('afterbegin', buildMeetingRowHtml(meeting));
        wrap?.classList.remove('d-none');
        empty?.classList.add('d-none');
        updateHistoryCount();
    }

    function updateCount() {
        const n = document.querySelectorAll('.recipient-check:checked').length;
        selectedCount.textContent = String(n);
    }

    document.querySelectorAll('.recipient-check').forEach(cb => cb.addEventListener('change', updateCount));

    function toggleAll(selector, checked) {
        document.querySelectorAll(selector).forEach(cb => { cb.checked = checked; });
        updateCount();
    }

    document.getElementById('selectAllFm')?.addEventListener('change', e => toggleAll('.fm-check', e.target.checked));
    document.getElementById('selectAllStudents')?.addEventListener('change', e => toggleAll('.student-check', e.target.checked));
    document.getElementById('clearFm')?.addEventListener('click', () => toggleAll('.fm-check', false));
    document.getElementById('clearStudents')?.addEventListener('click', () => toggleAll('.student-check', false));

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const checked = document.querySelectorAll('.recipient-check:checked').length;
        if (checked === 0) {
            alert('Please select at least one recipient.');
            return;
        }
        if (!confirm('Create the Zoom meeting and send ' + checked + ' invitation email(s)?')) {
            return;
        }

        sendBtn.disabled = true;
        sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending…';

        try {
            const fd = new FormData(form);
            const res = await fetch('send_francophonie_meeting_invitation.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();
            sendResult.style.display = 'block';
            const alertType = data.emails_ok === false ? 'alert-warning' : 'alert-success';
            sendResult.className = 'alert mb-3 ' + alertType;
            let html = '<strong>' + (data.message || 'Done') + '</strong>';
            if (data.host_room_url) {
                html += '<div class="mt-3 d-flex flex-wrap gap-2 align-items-center">';
                html += '<a href="' + data.host_room_url + '" target="_blank" rel="noopener" class="btn btn-success btn-sm">';
                html += '<i class="fas fa-video me-1"></i> Start meeting in browser</a>';
                if (data.zoom && data.zoom.start_url) {
                    html += '<a href="' + data.zoom.start_url + '" target="_blank" rel="noopener" class="btn btn-outline-dark btn-sm">Open in Zoom app</a>';
                }
                html += '</div>';
            }
            if (data.guest_join_url) {
                html += '<div class="small mt-2"><strong>External guest link</strong> (share with people not in the list): ';
                html += '<a href="' + data.guest_join_url + '" target="_blank" rel="noopener" class="alert-link">' + data.guest_join_url + '</a></div>';
            }
            if (data.zoom && data.zoom.password) {
                html += '<div class="small mt-1">Passcode: <code>' + data.zoom.password + '</code></div>';
            }
            if (data.failed_emails && data.failed_emails.length) {
                html += '<br><span class="small">Failed: ' + data.failed_emails.join(', ') + '</span>';
            }
            sendResult.innerHTML = html;
            sendResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            if (data.success) {
                form.querySelectorAll('.recipient-check:checked').forEach(cb => { cb.checked = false; });
                updateCount();
                if (data.meeting) {
                    prependMeetingRow(data.meeting);
                }
            }
        } catch (err) {
            sendResult.style.display = 'block';
            sendResult.className = 'alert alert-danger mb-3';
            sendResult.textContent = 'Request failed. Check your connection and try again.';
        } finally {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Create meeting &amp; send invitations';
        }
    });

    updateCount();

    const attendanceModal = document.getElementById('attendanceModal');
    const attendanceBody = document.getElementById('attendanceModalBody');

    document.getElementById('historySection')?.addEventListener('click', async function (e) {
        const copyBtn = e.target.closest('.btn-copy-guest-link');
        if (copyBtn) {
            const url = copyBtn.getAttribute('data-url') || '';
            if (!url) return;
            try {
                await navigator.clipboard.writeText(url);
                alert('External guest link copied. Share it with people not on the invite list.');
            } catch (err) {
                prompt('Copy this external guest link:', url);
            }
            return;
        }

        const attendanceBtn = e.target.closest('.btn-attendance-report');
        if (attendanceBtn) {
            const id = attendanceBtn.getAttribute('data-id');
            if (!id || !attendanceModal || !attendanceBody) return;
            attendanceBody.innerHTML = '<div class="text-muted small">Loading…</div>';
            bootstrap.Modal.getOrCreateInstance(attendanceModal).show();
            try {
                const res = await fetch('fm_meeting_attendance_report.php?invitation_id=' + encodeURIComponent(id), { credentials: 'same-origin' });
                const data = await res.json();
                if (!data.ok) {
                    attendanceBody.innerHTML = '<div class="text-danger">' + (data.message || 'Failed to load') + '</div>';
                    return;
                }
                let html = '<p class="small text-muted mb-3">' + (data.meeting.topic || '') + ' · Guest link: <a href="' + data.meeting.guest_join_url + '" target="_blank" rel="noopener">open</a></p>';
                html += '<h6 class="mb-2">Invited recipients</h6>';
                html += '<div class="table-responsive mb-3"><table class="table table-sm table-bordered"><thead><tr><th>Name</th><th>Email</th><th>Email sent</th><th>Joined</th><th>Join count</th></tr></thead><tbody>';
                (data.invitees || []).forEach(function (row) {
                    html += '<tr><td>' + (row.recipient_name || '') + '</td><td>' + (row.recipient_email || '') + '</td>';
                    html += '<td>' + (row.email_sent == 1 ? 'Yes' : 'No') + '</td>';
                    html += '<td>' + (row.joined_at || '—') + '</td><td>' + (row.join_count || 0) + '</td></tr>';
                });
                html += '</tbody></table></div>';
                html += '<h6 class="mb-2">Join log</h6>';
                html += '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Name</th><th>Email</th><th>Type</th><th>Joined</th><th>Left</th></tr></thead><tbody>';
                (data.attendance_log || []).forEach(function (row) {
                    html += '<tr><td>' + (row.participant_name || '') + '</td><td>' + (row.participant_email || '—') + '</td>';
                    html += '<td>' + (row.participant_type || '') + '</td><td>' + (row.joined_at || '') + '</td><td>' + (row.left_at || '—') + '</td></tr>';
                });
                html += '</tbody></table></div>';
                attendanceBody.innerHTML = html;
            } catch (err) {
                attendanceBody.innerHTML = '<div class="text-danger">Could not load attendance report.</div>';
            }
            return;
        }

        const deleteBtn = e.target.closest('.btn-delete-meeting');
        if (deleteBtn) {
            const id = deleteBtn.getAttribute('data-id');
            const topic = deleteBtn.getAttribute('data-topic') || 'this meeting';
            if (!id) return;
            if (!confirm('Delete "' + topic + '" from the list and remove the Zoom meeting?')) {
                return;
            }
            deleteBtn.disabled = true;
            try {
                const fd = new FormData();
                fd.append('csrf_token', csrfToken);
                fd.append('invitation_id', id);
                fd.append('delete_zoom', '1');
                const res = await fetch('delete_francophonie_meeting_invitation.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (!data.success) {
                    alert(data.message || 'Delete failed');
                    deleteBtn.disabled = false;
                    return;
                }
                document.getElementById('meeting-row-' + id)?.remove();
                updateHistoryCount();
                const body = document.getElementById('historyTableBody');
                const empty = document.getElementById('historyEmpty');
                const wrap = document.getElementById('historyTableWrap');
                if (body && body.querySelectorAll('tr').length === 0) {
                    wrap?.classList.add('d-none');
                    empty?.classList.remove('d-none');
                }
            } catch (err) {
                alert('Delete request failed.');
                deleteBtn.disabled = false;
            }
        }
    });
})();
</script>
</body>
</html>
