<?php
declare(strict_types=1);

date_default_timezone_set('UTC');
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/refund_requests_schema.php';
require_once __DIR__ . '/helpers/secure_file.php';
require_once __DIR__ . '/includes/company_branding.php';

$adminId = (int) ($_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
if ($adminId < 1) {
    header('Location: admin-login.php');
    exit;
}

$st = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
$st->bind_param('i', $adminId);
$st->execute();
$roleRow = $st->get_result()->fetch_assoc();
$st->close();
$role = (string) ($roleRow['role'] ?? '');

if (!pcvc_is_superadmin_role($role)) {
    header('Location: admin-dashboard.php');
    exit;
}

pcvc_ensure_refund_requests_schema($conn);

$_SESSION['refund_admin_csrf'] = bin2hex(random_bytes(32));
$refundCsrf = $_SESSION['refund_admin_csrf'];

$statusLabels = [
    'pending' => 'Pending',
    'under_review' => 'Under review',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'paid' => 'Refund paid',
];

$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$sql = 'SELECT * FROM refund_requests ORDER BY id DESC';
$requests = [];
$q = $conn->query($sql);
if ($q) {
    while ($row = $q->fetch_assoc()) {
        if ($statusFilter !== '' && ($row['request_status'] ?? '') !== $statusFilter) {
            continue;
        }
        $requests[] = $row;
    }
    $q->free();
}

// Load status logs for modal
$logsByRequest = [];
$logQ = $conn->query('SELECT l.*, CONCAT(a.first_name, " ", a.last_name) AS admin_name FROM refund_request_status_logs l LEFT JOIN admins a ON a.id = l.admin_id ORDER BY l.id DESC');
if ($logQ) {
    while ($log = $logQ->fetch_assoc()) {
        $rid = (int) ($log['refund_request_id'] ?? 0);
        if (!isset($logsByRequest[$rid])) {
            $logsByRequest[$rid] = [];
        }
        $logsByRequest[$rid][] = $log;
    }
    $logQ->free();
}

$colors = ['navy' => '#427431', 'gold' => '#E21D1E', 'blue' => '#3661B9', 'white' => '#FFFFFF', 'light' => '#F8F9FA', 'border' => '#E0E0E0'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Refund Requests | <?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Tahoma, sans-serif; background: <?= $colors['light'] ?>; color: <?= $colors['navy'] ?>; min-height: 100vh; }
    .wrap { padding: 20px; max-width: 1400px; margin: 0 auto; }
    .hdr { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; border-left: 5px solid <?= $colors['gold'] ?>; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .hdr h1 { font-size: 1.4rem; display: flex; align-items: center; gap: 10px; }
    .hdr p { color: #666; font-size: 0.9rem; margin-top: 6px; }
    .filters { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; align-items: center; }
    .filters input, .filters select { padding: 10px 14px; border: 2px solid <?= $colors['border'] ?>; border-radius: 10px; font-size: 0.9rem; }
    .filters input { flex: 1; min-width: 200px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 18px; }
    .card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.07); border: 1px solid <?= $colors['border'] ?>; }
    .card-head { background: linear-gradient(135deg, <?= $colors['navy'] ?>, #2f5a26); color: #fff; padding: 14px 16px; }
    .card-name { font-weight: 700; font-size: 1.05rem; }
    .card-ref { font-size: 0.78rem; opacity: 0.9; font-family: monospace; margin-top: 4px; }
    .card-body { padding: 14px 16px; font-size: 0.88rem; }
    .row { margin-bottom: 10px; }
    .lbl { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .val { margin-top: 2px; font-weight: 600; }
    .pill { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
    .pill-pending { background: #fef3c7; color: #92400e; }
    .pill-under_review { background: #dbeafe; color: #1e40af; }
    .pill-approved { background: #d1fae5; color: #065f46; }
    .pill-rejected { background: #fee2e2; color: #991b1b; }
    .pill-paid { background: #ede9fe; color: #5b21b6; }
    .card-foot { padding: 12px 16px; border-top: 1px solid <?= $colors['border'] ?>; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
    .btn { padding: 8px 14px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; }
    .btn-manage { background: <?= $colors['navy'] ?>; color: #fff; }
    .btn-proof { background: <?= $colors['blue'] ?>; color: #fff; text-decoration: none; }
    .empty { text-align: center; padding: 60px 20px; color: #64748b; }
    .overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.55); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 16px; }
    .overlay.show { display: flex; }
    .modal { position: relative; background: #fff; border-radius: 16px; max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 24px 60px rgba(0,0,0,0.2); }
    .modal-saving {
      position: absolute; inset: 0; z-index: 10;
      background: rgba(255,255,255,0.88);
      display: none; align-items: center; justify-content: center; flex-direction: column; gap: 12px;
      border-radius: 16px; backdrop-filter: blur(2px);
    }
    .modal-saving.show { display: flex; }
    .modal-saving .spin {
      width: 44px; height: 44px;
      border: 4px solid rgba(66,116,49,0.2);
      border-top-color: <?= $colors['navy'] ?>;
      border-radius: 50%;
      animation: refundSpin 0.75s linear infinite;
    }
    @keyframes refundSpin { to { transform: rotate(360deg); } }
    .modal-saving p { margin: 0; font-weight: 700; color: <?= $colors['navy'] ?>; font-size: 0.95rem; }
    .save-feedback {
      margin-top: 10px; padding: 10px 12px; border-radius: 10px; font-size: 0.85rem; line-height: 1.45;
      display: none; max-height: 160px; overflow-y: auto; word-break: break-word;
    }
    .save-feedback.show { display: block; }
    .save-feedback.ok { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
    .save-feedback.warn { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; }
    .save-feedback.err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .btn-save:disabled { opacity: 0.65; cursor: not-allowed; }
    .contact-hint { font-size: 0.78rem; color: #64748b; margin-top: 4px; }
    .modal-head { padding: 18px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: flex-start; }
    .modal-head h3 { font-size: 1.1rem; }
    .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b; line-height: 1; }
    .modal-body { padding: 18px 20px; }
    .fld { margin-bottom: 14px; }
    .fld label { display: block; font-size: 0.78rem; font-weight: 700; color: #64748b; margin-bottom: 4px; }
    .fld select, .fld textarea, .fld input { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.9rem; font-family: inherit; }
    .notify-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px; }
    .notify-btn { padding: 10px; border: 2px solid #e2e8f0; border-radius: 10px; background: #fff; cursor: pointer; font-size: 0.82rem; font-weight: 700; text-align: center; }
    .notify-btn.active { border-color: <?= $colors['navy'] ?>; background: rgba(66,116,49,0.08); color: <?= $colors['navy'] ?>; }
    .notify-btn span { display: block; font-weight: 400; font-size: 0.72rem; color: #64748b; margin-top: 2px; }
    .modal-foot { padding: 14px 20px; border-top: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end; align-items: center; }
    .btn-save { background: <?= $colors['gold'] ?>; color: #fff; }
    .btn-cancel { background: #e2e8f0; color: #334155; }
    .log-list { margin-top: 12px; max-height: 160px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px; font-size: 0.8rem; }
    .log-item { padding: 8px; border-bottom: 1px solid #f1f5f9; }
    .log-item:last-child { border-bottom: none; }
    .toast { position: fixed; bottom: 24px; right: 24px; background: <?= $colors['navy'] ?>; color: #fff; padding: 12px 20px; border-radius: 10px; font-weight: 600; display: none; z-index: 10000; }
    .toast.show { display: block; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <h1><i class="fas fa-money-bill-wave" style="color:<?= $colors['gold'] ?>"></i> Refund Requests</h1>
    <p>Review student refund requests, add comments, and notify via email or WhatsApp template.</p>
  </div>

  <div class="filters">
    <input type="search" id="clientSearch" placeholder="Search name, email, reference…" aria-label="Search">
    <select id="statusFilter" onchange="location.href='refund-requests-report.php'+(this.value?'?status='+encodeURIComponent(this.value):'')">
      <option value="">All statuses</option>
      <?php foreach ($statusLabels as $k => $lab): ?>
        <option value="<?= htmlspecialchars($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
      <?php endforeach; ?>
    </select>
    <span style="font-weight:700;color:#64748b;"><?= count($requests) ?> request(s)</span>
  </div>

  <div class="grid" id="requestGrid">
    <?php if ($requests === []): ?>
      <div class="empty" style="grid-column:1/-1;"><i class="fas fa-inbox" style="font-size:2.5rem;margin-bottom:12px;display:block;"></i>No refund requests yet.</div>
    <?php else: ?>
      <?php foreach ($requests as $r):
        $rid = (int) ($r['id'] ?? 0);
        $st = (string) ($r['request_status'] ?? 'pending');
        $fullName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        $searchBlob = strtolower($fullName . ' ' . ($r['email'] ?? '') . ' ' . ($r['reference_id'] ?? '') . ' ' . ($r['service_paid_for'] ?? ''));
        $cur = strtoupper(trim((string) ($r['currency'] ?? 'USD')));
        $amt = number_format((float) ($r['amount'] ?? 0), 2) . ' ' . $cur;
        $proof = trim((string) ($r['payment_proof_file'] ?? ''));
        $payload = [
          'id' => $rid,
          'reference_id' => $r['reference_id'] ?? '',
          'name' => $fullName,
          'email' => $r['email'] ?? '',
          'phone' => $r['phone'] ?? '',
          'service' => $r['service_paid_for'] ?? '',
          'amount' => $amt,
          'reason' => $r['reason'] ?? '',
          'status' => $st,
          'admin_comment' => $r['admin_comment'] ?? '',
          'internal_note' => $r['internal_note'] ?? '',
          'submitted_by' => $r['submitted_by'] ?? 'public',
          'created_at' => $r['created_at'] ?? '',
          'logs' => $logsByRequest[$rid] ?? [],
        ];
        ?>
        <div class="card" data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
          <div class="card-head">
            <div class="card-name"><?= htmlspecialchars($fullName) ?></div>
            <div class="card-ref"><?= htmlspecialchars((string) ($r['reference_id'] ?? '')) ?></div>
          </div>
          <div class="card-body">
            <div class="row"><div class="lbl">Status</div><div class="val"><span class="pill pill-<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($statusLabels[$st] ?? $st) ?></span></div></div>
            <div class="row"><div class="lbl">Service</div><div class="val"><?= htmlspecialchars((string) ($r['service_paid_for'] ?? '')) ?></div></div>
            <div class="row"><div class="lbl">Amount</div><div class="val"><?= htmlspecialchars($amt) ?></div></div>
            <div class="row"><div class="lbl">Email</div><div class="val"><?= htmlspecialchars((string) ($r['email'] ?? '')) ?></div></div>
            <?php if (!empty($r['admin_comment'])): ?>
              <div class="row"><div class="lbl">Last comment</div><div class="val" style="font-weight:500;font-size:0.85rem;"><?= htmlspecialchars((string) $r['admin_comment']) ?></div></div>
            <?php endif; ?>
          </div>
          <div class="card-foot">
            <span style="font-size:0.78rem;color:#64748b;"><?= htmlspecialchars((string) ($r['created_at'] ?? '')) ?></span>
            <div style="display:flex;gap:6px;">
              <?php if ($proof !== ''): ?>
                <a class="btn btn-proof" href="<?= htmlspecialchars(pcvc_secure_file_url($proof, ['inline' => true])) ?>" target="_blank" rel="noopener"><i class="fas fa-file"></i> Proof</a>
              <?php endif; ?>
              <button type="button" class="btn btn-manage open-refund-modal" data-refund="<?= htmlspecialchars(json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                <i class="fas fa-cog"></i> Manage
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="overlay" id="refundModal">
  <div class="modal" role="dialog" aria-labelledby="modalTitle">
    <div class="modal-saving" id="modalSaving" aria-hidden="true">
      <div class="spin" aria-hidden="true"></div>
      <p id="modalSavingText">Saving &amp; sending notifications…</p>
    </div>
    <div class="modal-head">
      <div><h3 id="modalTitle">Manage refund</h3><p style="font-size:0.82rem;color:#64748b;margin-top:4px;" id="modalSub"></p><p class="contact-hint" id="modalContact"></p></div>
      <button type="button" class="modal-close" id="modalClose" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <div class="fld"><label>Reason (student submitted)</label><div id="modalReason" style="font-size:0.88rem;background:#f8fafc;padding:10px;border-radius:8px;"></div></div>
      <div class="fld">
        <label for="modalStatus">Status</label>
        <select id="modalStatus">
          <?php foreach ($statusLabels as $k => $lab): ?>
            <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($lab) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fld">
        <label for="modalComment">Comment to student</label>
        <textarea id="modalComment" rows="5" maxlength="2500" placeholder="Your message to the student — included in email and WhatsApp (long messages are split across two WhatsApp template fields)."></textarea>
        <p style="margin:6px 0 0;font-size:0.75rem;color:#64748b;">Up to ~1,800 characters sent via WhatsApp template; full text always included in email.</p>
      </div>
      <div class="fld">
        <label for="modalInternal">Internal note (staff only)</label>
        <textarea id="modalInternal" rows="2" maxlength="2000" placeholder="Not sent to student…"></textarea>
      </div>
      <div class="fld">
        <label>Notify student</label>
        <div class="notify-grid" id="notifyGrid">
          <button type="button" class="notify-btn active" data-ne="0" data-nw="0">Record only<span>No notification</span></button>
          <button type="button" class="notify-btn" data-ne="1" data-nw="0">Email<span>SMTP</span></button>
          <button type="button" class="notify-btn" data-ne="0" data-nw="1">WhatsApp<span>Template</span></button>
          <button type="button" class="notify-btn" data-ne="1" data-nw="1">Both<span>Email + WhatsApp</span></button>
        </div>
      </div>
      <div class="fld"><label>Activity log</label><div class="log-list" id="modalLogs"></div></div>
    </div>
    <div class="modal-foot">
      <div id="modalSaveFeedback" class="save-feedback" role="status" style="flex:1;margin:0;"></div>
      <button type="button" class="btn btn-cancel" id="modalCancel">Cancel</button>
      <button type="button" class="btn btn-save" id="modalSave"><i class="fas fa-save" id="modalSaveIcon"></i> <span id="modalSaveLabel">Save</span></button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
window.REFUND_CSRF = <?= json_encode($refundCsrf, JSON_UNESCAPED_UNICODE) ?>;
window.REFUND_STATUS_LABELS = <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>;
window.REFUND_API_URL = 'api/refund-admin-update.php';

let currentId = 0;
let notifyEmail = 0, notifyWhatsapp = 0;
let isSaving = false;
const overlay = document.getElementById('refundModal');
const modalSaving = document.getElementById('modalSaving');
const modalSaveFeedback = document.getElementById('modalSaveFeedback');
const modalSaveBtn = document.getElementById('modalSave');
const modalSaveLabel = document.getElementById('modalSaveLabel');
const modalSaveIcon = document.getElementById('modalSaveIcon');

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(function () { t.classList.remove('show'); }, 4500);
}

function setSaveFeedback(msg, type, htmlDetail) {
  modalSaveFeedback.className = 'save-feedback show ' + (type || 'ok');
  const main = esc(msg);
  modalSaveFeedback.innerHTML = htmlDetail ? (main + '<br><br>' + htmlDetail) : main;
}

function formatNotifyResult(notify, ne, nw) {
  if (!notify) return '';
  const lines = [];
  if (ne && notify.email) {
    const e = notify.email;
    lines.push('<strong>Email</strong> ' + esc(e.to || '(no address on record)'));
    if (e.sent) lines.push('✓ Sent successfully');
    else lines.push('✗ ' + esc(e.error || 'Send failed — check SMTP_* in .env'));
  }
  if (nw && notify.whatsapp) {
    const wa = notify.whatsapp;
    lines.push('<strong>WhatsApp</strong> ' + esc(wa.to || '(phone missing or invalid)'));
    if (wa.sent) lines.push('✓ Sent via ' + esc(wa.method || 'template'));
    else lines.push('✗ ' + esc(wa.error || 'Send failed — check WHATSAPP_* in .env'));
  }
  const anyFail = (ne && notify.email && !notify.email.sent) || (nw && notify.whatsapp && !notify.whatsapp.sent);
  if (anyFail && notify.env && typeof notify.env === 'object') {
    lines.push('<strong>.env configuration</strong>');
    Object.keys(notify.env).forEach(function (k) {
      lines.push(esc(k) + ': ' + esc(notify.env[k]));
    });
  }
  return lines.join('<br>');
}

function clearSaveFeedback() {
  modalSaveFeedback.className = 'save-feedback';
  modalSaveFeedback.innerHTML = '';
}

function setSaving(active, text) {
  isSaving = active;
  modalSaving.classList.toggle('show', active);
  if (text) document.getElementById('modalSavingText').textContent = text;
  modalSaveBtn.disabled = active;
  document.getElementById('modalCancel').disabled = active;
  document.getElementById('modalClose').disabled = active;
  modalSaveLabel.textContent = active ? 'Saving…' : 'Save';
  modalSaveIcon.className = active ? 'fas fa-spinner fa-spin' : 'fas fa-save';
}

async function parseJsonResponse(res) {
  const text = await res.text();
  try {
    return JSON.parse(text);
  } catch (e) {
    throw new Error('Server returned an invalid response (HTTP ' + res.status + '). Reload the page and try again.');
  }
}

document.getElementById('clientSearch').addEventListener('input', function () {
  const q = this.value.toLowerCase().trim();
  document.querySelectorAll('#requestGrid .card').forEach(function (c) {
    const blob = c.getAttribute('data-search') || '';
    c.style.display = (!q || blob.indexOf(q) !== -1) ? '' : 'none';
  });
});

document.querySelectorAll('.open-refund-modal').forEach(function (btn) {
  btn.addEventListener('click', function () {
    let data;
    try { data = JSON.parse(btn.getAttribute('data-refund')); } catch (e) { return; }
    currentId = data.id;
    clearSaveFeedback();
    setSaving(false);
    document.getElementById('modalTitle').textContent = data.name;
    document.getElementById('modalSub').textContent = data.reference_id + ' · ' + data.email;
    document.getElementById('modalContact').textContent =
      (data.phone ? 'Phone: ' + data.phone + ' · ' : '') +
      (data.email ? 'Email: ' + data.email : '');
    document.getElementById('modalReason').textContent = data.reason || '—';
    document.getElementById('modalStatus').value = data.status || 'pending';
    document.getElementById('modalComment').value = data.admin_comment || '';
    document.getElementById('modalInternal').value = data.internal_note || '';
    const logsEl = document.getElementById('modalLogs');
    const logs = data.logs || [];
    logsEl.innerHTML = logs.length ? logs.map(function (l) {
      return '<div class="log-item"><strong>' + esc(l.new_status) + '</strong> · ' + esc(l.created_at) +
        (l.comment ? '<br>' + esc(l.comment) : '') + '</div>';
    }).join('') : '<div style="padding:8px;color:#64748b;">No activity yet.</div>';
    notifyEmail = 0; notifyWhatsapp = 0;
    document.querySelectorAll('#notifyGrid .notify-btn').forEach(function (b, i) {
      b.classList.toggle('active', i === 0);
    });
    overlay.classList.add('show');
  });
});

document.querySelectorAll('#notifyGrid .notify-btn').forEach(function (b) {
  b.addEventListener('click', function () {
    if (isSaving) return;
    notifyEmail = parseInt(b.getAttribute('data-ne'), 10) || 0;
    notifyWhatsapp = parseInt(b.getAttribute('data-nw'), 10) || 0;
    document.querySelectorAll('#notifyGrid .notify-btn').forEach(function (x) { x.classList.remove('active'); });
    b.classList.add('active');
    clearSaveFeedback();
  });
});

function closeModal() {
  if (isSaving) return;
  overlay.classList.remove('show');
  currentId = 0;
  clearSaveFeedback();
}
document.getElementById('modalClose').addEventListener('click', closeModal);
document.getElementById('modalCancel').addEventListener('click', closeModal);
overlay.addEventListener('click', function (e) { if (e.target === overlay && !isSaving) closeModal(); });

modalSaveBtn.addEventListener('click', async function () {
  if (!currentId || isSaving) return;

  const ne = notifyEmail === 1;
  const nw = notifyWhatsapp === 1;
  const status = document.getElementById('modalStatus').value;
  const comment = document.getElementById('modalComment').value.trim();

  if (status === 'rejected' && (ne || nw) && comment === '') {
    setSaveFeedback('Enter a comment to the student before notifying about a rejection.', 'err');
    document.getElementById('modalComment').focus();
    return;
  }

  clearSaveFeedback();
  setSaving(true, ne || nw ? 'Saving & sending notifications…' : 'Saving…');

  try {
    const res = await fetch(window.REFUND_API_URL, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        csrf: window.REFUND_CSRF,
        id: currentId,
        request_status: status,
        admin_comment: comment,
        internal_note: document.getElementById('modalInternal').value.trim(),
        notify_email: ne,
        notify_whatsapp: nw,
        notify_message: comment
      })
    });

    const data = await parseJsonResponse(res);

    if (data.csrf) {
      window.REFUND_CSRF = data.csrf;
    }

    if (!data.ok) {
      setSaving(false);
      setSaveFeedback(data.error || 'Save failed.', 'err');
      return;
    }

    const notifyLine = formatNotifyResult(data.notify, ne, nw);
    const allOk = data.notify_all_ok !== false;
    const mainMsg = data.message || 'Saved successfully.';
    setSaveFeedback(mainMsg, allOk ? 'ok' : 'warn', notifyLine || '');
    showToast(mainMsg);

    setSaving(false);
    setTimeout(function () { location.reload(); }, allOk ? 1400 : 6000);
  } catch (e) {
    setSaving(false);
    const errMsg = (e && e.message) ? e.message : 'Network error';
    setSaveFeedback(
      'Could not reach api/refund-admin-update.php',
      'err',
      esc(errMsg) + '<br><br>If this says "Failed to fetch", the server may have timed out during email/WhatsApp. Check Apache/PHP error log, then try "Record only" to confirm save works.'
    );
  }
});

function esc(t) {
  const d = document.createElement('div');
  d.textContent = t || '';
  return d.innerHTML;
}
</script>
</body>
</html>
