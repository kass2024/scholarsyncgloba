<?php
/**
 * francophonie-mobility-applications.php — Admin management (email-only workflow).
 */
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
require_once __DIR__ . '/helpers/env_load.php';
fm_ensure_schema($conn);
xander_load_env_file();

if (empty($_SESSION['id']) || !in_array($_SESSION['role'] ?? '', ['superadmin', 'staff'], true)) {
    header('Location: admin-login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$approvalEmail = trim(xander_env_get('FRANCOPHONIE_MOBILITY_APPROVAL_EMAIL'));
$approvalEmailOk = $approvalEmail !== '' && filter_var($approvalEmail, FILTER_VALIDATE_EMAIL);

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
    $where[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR reference_id LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}

$sql = 'SELECT * FROM francophonie_mobility_applications WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 200';
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$counts = ['pending' => 0, 'under_review' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
$cr = $conn->query('SELECT status, COUNT(*) c FROM francophonie_mobility_applications GROUP BY status');
if ($cr) {
    while ($r = $cr->fetch_assoc()) {
        $counts[$r['status']] = (int) $r['c'];
        $counts['total'] += (int) $r['c'];
    }
}

function fm_status_badge(string $s): string
{
    return 'badge-' . preg_replace('/[^a-z_]/', '', $s);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Francophonie Mobility Applications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --fm-green:#1e4d2b; --fm-blue:#3661B9; }
        body { background:#f4f6f3; -webkit-text-size-adjust:100%; }
        .page-head {
            background:linear-gradient(135deg,var(--fm-green),var(--fm-blue));
            color:#fff; padding:clamp(1rem,4vw,1.75rem) 0; margin-bottom:1rem;
        }
        .stat-card {
            background:#fff; border-radius:10px; padding:.85rem; text-align:center;
            border:1px solid #e2e8f0; height:100%;
        }
        .stat-card strong { font-size:clamp(1.25rem,4vw,1.5rem); color:var(--fm-blue); display:block; }
        .badge-pending { background:#fef3c7; color:#92400e; }
        .badge-under_review { background:#dbeafe; color:#1e40af; }
        .badge-approved { background:#d1fae5; color:#065f46; }
        .badge-rejected { background:#fee2e2; color:#991b1b; }
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
        .modal-fullscreen-sm-down { }
        @media (max-width: 575.98px) {
            .modal-dialog { margin:.5rem; }
        }
    </style>
</head>
<body>
<div class="page-head">
    <div class="container-fluid px-3 px-md-4">
        <h1 class="h4 h3-md mb-1"><i class="fas fa-maple-leaf me-2"></i>Francophonie Mobility</h1>
        <p class="mb-0 opacity-75 small">Review applications · Email-only communication · Approve to forward package</p>
    </div>
</div>

<div class="container-fluid px-3 px-md-4 pb-5">
    <?php if (!$approvalEmailOk): ?>
    <div class="alert alert-warning py-2 small">
        <i class="fas fa-exclamation-triangle me-1"></i>
        Set <code>FRANCOPHONIE_MOBILITY_APPROVAL_EMAIL</code> in <code>.env</code> to receive approved packages with documents.
    </div>
    <?php endif; ?>

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
            <input type="search" name="search" class="form-control" placeholder="Search name, email, reference…" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-12 col-md-2">
            <button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Filter</button>
        </div>
    </form>

    <!-- Mobile cards -->
    <div class="cards-mobile">
        <?php if (!$apps): ?>
            <p class="text-center text-muted py-4">No applications found.</p>
        <?php else: foreach ($apps as $a): ?>
            <div class="app-card">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <div class="fw-bold"><?= htmlspecialchars(trim($a['first_name'].' '.$a['last_name'])) ?></div>
                        <code class="small"><?= htmlspecialchars($a['reference_id']) ?></code>
                    </div>
                    <span class="badge <?= fm_status_badge($a['status']) ?>"><?= ucwords(str_replace('_',' ',$a['status'])) ?></span>
                </div>
                <div class="meta mb-2">
                    <?= htmlspecialchars($a['email']) ?><br>
                    <?= htmlspecialchars($a['profession']) ?> · <?= date('M j, Y', strtotime($a['created_at'])) ?>
                    <?php if (!empty($a['video_file']) || !empty($a['video_pcloud_link'])): ?>
                    <br><span class="badge text-bg-danger mt-1"><i class="fas fa-video me-1"></i>Video</span>
                    <?php endif; ?>
                </div>
                <button class="btn btn-outline-primary btn-sm w-100" onclick="viewApp(<?= (int)$a['id'] ?>)">
                    <i class="fas fa-eye me-1"></i> View &amp; manage
                </button>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Desktop table -->
    <div class="card border-0 shadow-sm table-desktop">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Reference</th>
                        <th>Candidate</th>
                        <th>Profession</th>
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
                        <td><code><?= htmlspecialchars($a['reference_id']) ?></code></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars(trim($a['first_name'].' '.$a['last_name'])) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($a['email']) ?></div>
                            <?php if (!empty($a['video_file']) || !empty($a['video_pcloud_link'])): ?>
                            <span class="badge text-bg-danger mt-1"><i class="fas fa-video me-1"></i>Video</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= htmlspecialchars($a['profession']) ?></td>
                        <td><span class="badge <?= fm_status_badge($a['status']) ?>"><?= ucwords(str_replace('_',' ',$a['status'])) ?></span></td>
                        <td class="small"><?= date('M j, Y', strtotime($a['created_at'])) ?></td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewApp(<?= (int)$a['id'] ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <?php if (empty($a['video_file']) && empty($a['video_pcloud_link'])): ?>
                            <button class="btn btn-sm btn-outline-danger" title="Video invite link"
                                    onclick="createVideoInvite(<?= (int)$a['id'] ?>)">
                                <i class="fas fa-video"></i>
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-secondary" title="Delete application"
                                    onclick="deleteApplication(<?= (int)$a['id'] ?>, <?= json_encode($a['reference_id']) ?>)">
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

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailBody">
                <div class="text-center py-4"><div class="spinner-border"></div></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF = <?= json_encode($_SESSION['csrf_token']) ?>;
const modal = new bootstrap.Modal(document.getElementById('detailModal'));

function copyFmText(text, btn) {
    const value = String(text || '');
    const markCopied = () => {
        if (!btn) return;
        const old = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check me-1"></i> Copied';
        setTimeout(() => { btn.innerHTML = old; }, 1600);
    };
    const fallback = () => {
        const ta = document.createElement('textarea');
        ta.value = value;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            markCopied();
        } catch (e) {
            prompt('Copy this:', value);
        }
        document.body.removeChild(ta);
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(value).then(markCopied).catch(fallback);
    } else {
        fallback();
    }
}

function copyFmFromBtn(btn) {
    copyFmText(btn.getAttribute('data-copy') || '', btn);
}

function copyFmInput(inputId, btn) {
    const input = document.getElementById(inputId);
    copyFmText(input ? input.value : '', btn);
}

function viewApp(id) {
    document.getElementById('detailBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border"></div></div>';
    modal.show();
    fetch('francophonie_mobility_application_details.php?id=' + id)
        .then(r => r.text())
        .then(html => { document.getElementById('detailBody').innerHTML = html; });
}

function setStatus(id, status) {
    let note = '';
    if (status === 'rejected') {
        note = prompt('Reason / message for applicant (sent by email):') || '';
        if (note === null) return;
    } else if (status === 'under_review') {
        note = prompt('Optional message for applicant (email):', '') || '';
    }
    const label = status.replace(/_/g, ' ');
    if (!confirm('Set status to "' + label + '"? Applicant will be notified by email.')) return;

    postAction('update_francophonie_mobility_status.php', {
        application_id: id, status: status, note: note
    }).then(d => {
        let msg = 'Status updated.\nApplicant email: ' + (d.email_sent ? 'sent' : 'FAILED — check SMTP in .env');
        if (d.approval_package_sent !== null && d.approval_package_sent !== undefined) {
            if (d.approval_package_sent) {
                msg += '\nApproval package: sending to ' + (d.approval_email || 'FRANCOPHONIE_MOBILITY_APPROVAL_EMAIL') + ' (form + attachments)';
            } else {
                msg += '\nApproval package: FAILED — set FRANCOPHONIE_MOBILITY_APPROVAL_EMAIL in .env';
            }
        }
        alert(msg);
        location.reload();
    }).catch(e => alert(e.message));
}

function resendEmail(id) {
    if (!confirm('Resend current status email to applicant?')) return;
    postAction('send_francophonie_mobility_email.php', { application_id: id, action: 'status' })
        .then(d => alert(d.message)).catch(e => alert(e.message));
}

function resendPackage(id) {
    if (!confirm('Resend approval package (form + all documents) to partner email?')) return;
    postAction('send_francophonie_mobility_email.php', { application_id: id, action: 'approval_package' })
        .then(d => alert(d.message)).catch(e => alert(e.message));
}

function createVideoInvite(id, regenerate) {
    const msg = regenerate
        ? 'Regenerate a new one-time video upload link? The old link will stop working.'
        : 'Create a one-time video upload/record link for this candidate?';
    if (!confirm(msg)) return;
    postAction('francophonie_mobility_admin_action.php', {
        application_id: id,
        action: 'create_video_invite'
    }).then(async d => {
        try {
            await navigator.clipboard.writeText(d.invite_url || '');
        } catch (e) {}
        const openWa = confirm(
            (d.message || 'Invite created') + '\n\n'
            + 'Reference: ' + (d.reference_id || '') + '\n'
            + 'Link copied to clipboard:\n' + (d.invite_url || '') + '\n\n'
            + 'Open WhatsApp with this message now?'
        );
        if (openWa && d.whatsapp_url) {
            window.open(d.whatsapp_url, '_blank', 'noopener');
        }
        viewApp(id);
    }).catch(e => alert(e.message));
}

function deleteApplication(id, referenceId) {
    const typed = prompt(
        'Delete FULL application ' + referenceId + '?\n\n'
        + 'This removes the application, status logs, and linked contracts.\n'
        + 'Type the reference ID to confirm:'
    );
    if (typed === null) return;
    postAction('francophonie_mobility_admin_action.php', {
        application_id: id,
        action: 'delete_application',
        confirm_reference: typed
    }).then(d => {
        alert(d.message || 'Deleted');
        location.reload();
    }).catch(e => alert(e.message));
}

function postAction(url, data) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    Object.keys(data).forEach(k => fd.append(k, data[k]));
    return fetch(url, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (!d.success) throw new Error(d.message || 'Request failed'); return d; });
}
</script>
</body>
</html>
