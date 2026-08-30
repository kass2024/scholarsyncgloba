<?php
// staff-warning-letters.php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/includes/company_branding.php';

$sessionRole = isset($_SESSION['role']) ? trim((string) $_SESSION['role']) : '';
$dbRole = '';
$adminPk = !empty($_SESSION['id']) ? (int) $_SESSION['id'] : (!empty($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0);
if ($adminPk > 0) {
    if ($st = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1')) {
        $st->bind_param('i', $adminPk);
        $st->execute();
        if ($r = $st->get_result()->fetch_assoc()) {
            $dbRole = trim((string) ($r['role'] ?? ''));
        }
        $st->close();
    }
}
$isSuperadmin = xander_is_superadmin_role($dbRole) || xander_is_superadmin_role($sessionRole);
if (!$isSuperadmin) {
    http_response_code(403);
    exit('Access denied — superadmin only.');
}

$appRoot = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Warning Letters | <?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Tailwind -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- jQuery + Select2 -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- TinyMCE (community CDN, no key required for basic use) -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js"></script>

<style>
    body { background:#f1f5f9; font-family: 'Segoe UI', system-ui, sans-serif; }
    .card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
    .label { font-size: 12px; font-weight: 600; color:#475569; margin-bottom: 4px; display:block; text-transform: uppercase; letter-spacing: .04em; }
    .input { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 14px; }
    .input:focus { outline:none; border-color:#16a34a; box-shadow: 0 0 0 3px rgba(22,163,74,.15); }
    .btn { display:inline-flex; align-items:center; gap:.4rem; border-radius:8px; padding:9px 16px; font-weight:600; font-size:14px; cursor:pointer; transition:.15s; }
    .btn-primary { background:#16a34a; color:#fff; border:1px solid #15803d; }
    .btn-primary:hover { background:#15803d; }
    .btn-primary:disabled { opacity:.6; cursor:not-allowed; }
    .btn-secondary { background:#fff; color:#0f172a; border:1px solid #cbd5e1; }
    .btn-secondary:hover { background:#f8fafc; }
    .btn-danger { background:#dc2626; color:#fff; border:1px solid #b91c1c; }
    .pill { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 9999px; }
    .pill-ok { background:#dcfce7; color:#166534; }
    .pill-fail { background:#fee2e2; color:#991b1b; }
    .pill-skip { background:#fef3c7; color:#92400e; }
    .select2-container--default .select2-selection--single { height: 40px; border:1px solid #cbd5e1; border-radius: 8px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; padding-left: 12px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; }
    .templates-grid button { text-align: left; }
</style>
</head>
<body class="text-slate-900">

<div class="max-w-6xl mx-auto p-6">

    <!-- Header -->
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-rose-700">Staff Warning Letters</h1>
            <p class="text-sm text-slate-600">Issue an official warning letter with letterhead. WhatsApp sends the notice text, then the PDF below it. CC: infos@scholarsyncglobal.ca and +1 (438) 290-6688.</p>
        </div>
        <button id="btnRefreshHistory" type="button" class="btn btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M4 4v5h.582m15.418 7v-5h-.581M5.59 9.001A7.5 7.5 0 0 1 17.5 11M14.41 14.999A7.5 7.5 0 0 1 2.5 13" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
            Refresh history
        </button>
    </div>

    <!-- Compose Card -->
    <div class="card p-6 mb-6">
        <h2 class="text-base font-bold mb-4">New warning letter</h2>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <div class="lg:col-span-2">
                <label class="label" for="staffPicker">Staff</label>
                <select id="staffPicker" class="input"><option></option></select>
                <p id="staffPreview" class="text-xs text-slate-500 mt-1">Select a staff to load contact details.</p>
            </div>
            <div>
                <label class="label" for="warningSubject">Subject</label>
                <input id="warningSubject" type="text" class="input" placeholder="e.g. Repeated lateness">
            </div>
        </div>

        <!-- Smart templates -->
        <div class="mb-3">
            <span class="label">Quick templates</span>
            <div class="templates-grid grid grid-cols-2 md:grid-cols-4 gap-2">
                <button type="button" data-tpl="lateness" class="btn btn-secondary text-xs">Lateness / Absenteeism</button>
                <button type="button" data-tpl="performance" class="btn btn-secondary text-xs">Poor performance</button>
                <button type="button" data-tpl="conduct" class="btn btn-secondary text-xs">Misconduct</button>
                <button type="button" data-tpl="policy" class="btn btn-secondary text-xs">Policy violation</button>
            </div>
        </div>

        <div class="mb-4">
            <label class="label">Letter body</label>
            <textarea id="warningEditor"></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="label" for="overrideEmail">Email (editable)</label>
                <input id="overrideEmail" type="email" class="input" placeholder="staff@example.com">
            </div>
            <div>
                <label class="label" for="overridePhone">Phone — WhatsApp (editable)</label>
                <input id="overridePhone" type="tel" class="input" placeholder="+254711807646">
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4 mb-4">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" id="sendEmail" checked> Send via Email</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" id="sendWhatsapp" checked> Send via WhatsApp</label>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button id="btnPreview" type="button" class="btn btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 4C5 4 1.7 7.3 1 10c.7 2.7 4 6 9 6s8.3-3.3 9-6c-.7-2.7-4-6-9-6Zm0 10a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                Preview PDF
            </button>
            <button id="btnSend" type="button" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M2.94 2.06a1 1 0 0 1 1.06-.23l13 6a1 1 0 0 1 0 1.81l-13 6A1 1 0 0 1 2.6 14.4l1.8-3.9L10 9 4.4 7.5 2.6 3.6a1 1 0 0 1 .34-1.54Z"/></svg>
                Send notification
            </button>
            <div id="sendStatus" class="text-sm ml-2"></div>
        </div>
    </div>

    <!-- History Card -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-bold">Recent warning letters</h2>
            <input id="historySearch" type="search" class="input" style="max-width:280px;" placeholder="Search by staff or subject…">
        </div>
        <div id="historyList" class="space-y-2"></div>
        <p id="historyEmpty" class="text-sm text-slate-500 hidden">No warning letters yet.</p>
    </div>

</div>

<!-- Sending overlay -->
<div id="sendingOverlay" class="hidden fixed inset-0 bg-slate-900/55 z-[200] flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl p-8 w-full max-w-md text-center">
        <svg class="animate-spin h-10 w-10 mx-auto text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"></circle>
            <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round" fill="none"></path>
        </svg>
        <div id="sendingTitle" class="mt-3 text-base font-bold text-slate-900">Working…</div>
        <div id="sendingHint" class="mt-1 text-xs text-slate-600">This usually takes a few seconds.</div>
        <div class="flex gap-1.5 justify-center mt-3">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-bounce" style="animation-delay:0ms"></span>
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-bounce" style="animation-delay:150ms"></span>
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-bounce" style="animation-delay:300ms"></span>
        </div>
    </div>
</div>

<script>
window.APP_ROOT = <?= json_encode($appRoot, JSON_UNESCAPED_SLASHES) ?>;

function projectApiPath(rel) {
    const base = (window.APP_ROOT || "").replace(/\/$/, "");
    return base ? `${base}/${rel.replace(/^\//, "")}` : rel;
}

/* ============ TinyMCE ============ */
tinymce.init({
    selector: '#warningEditor',
    height: 360,
    menubar: false,
    plugins: 'lists link table paste',
    toolbar: 'undo redo | styles bold italic underline forecolor | bullist numlist | alignleft aligncenter alignright | link table | removeformat',
    branding: false,
    statusbar: false,
    paste_as_text: false,
    content_style: 'body{font-family:Segoe UI,Arial,sans-serif;font-size:14px;line-height:1.6;color:#1e293b;}'
});

/* ============ Spinner ============ */
const overlay = document.getElementById('sendingOverlay');
const oTitle  = document.getElementById('sendingTitle');
const oHint   = document.getElementById('sendingHint');
let stageTimer = null;
function showSpinner(stages){
    overlay.classList.remove('hidden');
    let i = 0;
    if (stages && stages.length){
        oTitle.textContent = stages[0].title;
        oHint.textContent  = stages[0].hint;
        clearInterval(stageTimer);
        stageTimer = setInterval(()=>{ i = (i+1)%stages.length; oTitle.textContent = stages[i].title; oHint.textContent = stages[i].hint; }, 1300);
    }
}
function hideSpinner(){
    overlay.classList.add('hidden');
    clearInterval(stageTimer); stageTimer = null;
}

/* ============ Staff Select2 ============ */
$('#staffPicker').select2({
    placeholder: 'Search a staff member…',
    minimumInputLength: 0,
    ajax: {
        url: projectApiPath('api/staff-warning-letter.php?action=search'),
        dataType: 'json',
        delay: 150,
        data: params => ({ q: params.term || '' }),
        processResults: data => ({ results: (data?.data?.items || []).map(it => ({ id: it.id, text: it.text, raw: it })) })
    }
});

let currentStaff = null;
$('#staffPicker').on('select2:select', function(e){
    const raw = e.params.data.raw || {};
    currentStaff = raw;
    document.getElementById('overrideEmail').value = raw.email || '';
    document.getElementById('overridePhone').value = raw.phone || '';
    const preview = document.getElementById('staffPreview');
    preview.textContent = (raw.position ? `${raw.position} • ` : '') + (raw.email || '—') + (raw.phone ? ` • ${raw.phone}` : '');
});

/* ============ Smart templates ============ */
const TEMPLATES = {
    lateness: (n) => `<p>Dear ${n||'Colleague'},</p><p>This letter serves as an <strong>official warning</strong> regarding your repeated lateness/absenteeism, which is in breach of our attendance policy.</p><p>You are expected to report to work on time as per the agreed schedule, and to notify your supervisor in advance whenever you are unable to do so. Continued violation of attendance rules may lead to further disciplinary action, including suspension or termination of employment.</p><p>Kindly take this notice seriously and improve immediately.</p>`,
    performance: (n) => `<p>Dear ${n||'Colleague'},</p><p>This is an <strong>official warning</strong> regarding the consistent under-performance observed in your assigned duties over recent weeks.</p><p>You are required to meet the agreed performance standards. We will schedule a follow-up review in 30 days to evaluate progress. Failure to improve may result in further disciplinary measures.</p><p>We trust you will take immediate corrective action.</p>`,
    conduct: (n) => `<p>Dear ${n||'Colleague'},</p><p>This letter is to formally <strong>warn you</strong> about the recent incident of misconduct in the workplace, which is contrary to our company code of conduct.</p><p>All staff are expected to maintain professional behaviour, respect colleagues, clients, and visitors at all times. Any further misconduct will lead to stronger disciplinary action.</p><p>You are kindly requested to acknowledge this letter and adjust your behaviour accordingly.</p>`,
    policy: (n) => `<p>Dear ${n||'Colleague'},</p><p>This is an <strong>official warning</strong> regarding a violation of company policy as observed by management.</p><p>You are expected to adhere strictly to all internal policies and procedures. A repeat of the same offence may result in suspension or termination of employment as per the staff handbook.</p><p>Kindly take this notice seriously.</p>`,
};
document.querySelectorAll('[data-tpl]').forEach(btn => {
    btn.addEventListener('click', () => {
        const key = btn.dataset.tpl;
        const fn = TEMPLATES[key];
        if (!fn) return;
        const staffName = currentStaff?.name || '';
        const html = fn(staffName);
        tinymce.get('warningEditor').setContent(html);
    });
});

/* ============ Validation helper ============ */
function readForm(){
    const sid = parseInt($('#staffPicker').val() || 0, 10) || 0;
    const subj = document.getElementById('warningSubject').value.trim();
    const content = tinymce.get('warningEditor').getContent({format:'html'}).trim();
    const sendEm = document.getElementById('sendEmail').checked;
    const sendWa = document.getElementById('sendWhatsapp').checked;
    const overEm = document.getElementById('overrideEmail').value.trim();
    const overPh = document.getElementById('overridePhone').value.trim();
    return { sid, subj, content, sendEm, sendWa, overEm, overPh };
}

/* ============ Preview ============ */
document.getElementById('btnPreview').addEventListener('click', async () => {
    const f = readForm();
    if (!f.sid){ alert('Pick a staff member first.'); return; }
    if (!f.subj){ alert('Enter a subject.'); return; }
    if (!f.content || f.content.replace(/<[^>]+>/g,'').trim()===''){ alert('Write the letter body.'); return; }

    showSpinner([
        {title:'Building letter…', hint:'Embedding letterhead and footer'},
        {title:'Rendering PDF…', hint:'Please wait'},
    ]);
    try {
        const fd = new FormData();
        fd.append('staff_id', String(f.sid));
        fd.append('subject', f.subj);
        fd.append('content_html', f.content);
        const res = await fetch(projectApiPath('api/staff-warning-letter.php?action=preview'), { method:'POST', credentials:'same-origin', body: fd });
        const data = await res.json();
        if (!res.ok || !data.success){ throw new Error(data.message || 'Preview failed'); }
        window.open(data.data.pdf_url, '_blank');
    } catch (e) { alert(e.message || 'Preview failed.'); }
    finally { hideSpinner(); }
});

/* ============ Send ============ */
document.getElementById('btnSend').addEventListener('click', async () => {
    const f = readForm();
    const status = document.getElementById('sendStatus');
    status.textContent = '';
    if (!f.sid){ alert('Pick a staff member first.'); return; }
    if (!f.subj){ alert('Enter a subject.'); return; }
    if (!f.content || f.content.replace(/<[^>]+>/g,'').trim()===''){ alert('Write the letter body.'); return; }
    if (!f.sendEm && !f.sendWa){ alert('Select Email and/or WhatsApp.'); return; }
    if (f.sendEm && !f.overEm){ alert('Staff email is required.'); return; }
    if (f.sendWa && !f.overPh){ alert('Staff phone is required for WhatsApp.'); return; }

    const stages = [{title:'Preparing letter…', hint:'Embedding letterhead and footer'}, {title:'Rendering PDF…', hint:'Creating attachment'}];
    if (f.sendEm) stages.push({title:'Sending email…', hint:'Dispatching to ' + f.overEm});
    if (f.sendWa) stages.push({title:'Checking WhatsApp number…', hint:'Verifying ' + f.overPh + ' is on WhatsApp'}, {title:'Sending WhatsApp text…', hint:'Delivering notice template'}, {title:'Sending PDF…', hint:'Attaching warning letter file'});
    stages.push({title:'Finalising…', hint:'Just a moment'});
    showSpinner(stages);

    try {
        const fd = new FormData();
        fd.append('staff_id', String(f.sid));
        fd.append('subject', f.subj);
        fd.append('content_html', f.content);
        fd.append('override_email', f.overEm);
        fd.append('override_phone', f.overPh);
        fd.append('send_email', f.sendEm ? '1' : '0');
        fd.append('send_whatsapp', f.sendWa ? '1' : '0');
        const res = await fetch(projectApiPath('api/staff-warning-letter.php?action=send'), { method:'POST', credentials:'same-origin', body: fd });
        const data = await res.json();
        if (!res.ok || !data.success){ throw new Error(data.message || 'Send failed'); }

        const em = data.data.email || {};
        const wa = data.data.whatsapp || {};
        const waCc = wa.cc || {};
        const ok=[], skip=[];
        if (em.sent) ok.push('Email (CC: infos@scholarsyncglobal.ca)'); else if (f.sendEm) skip.push('Email failed: ' + (em.error||''));
        if (wa.sent) {
            if (wa.pdf_attached) ok.push('WhatsApp: text + PDF sent');
            else ok.push('WhatsApp: text sent (PDF failed)');
        } else if (f.sendWa){
            if (wa.not_on_whatsapp) skip.push('WhatsApp skipped — number not on WhatsApp');
            else skip.push('WhatsApp failed: ' + (wa.error||'') + (wa.detail ? ' [' + wa.detail + ']' : ''));
        }
        if (f.sendWa && wa.sent && !wa.pdf_attached) skip.push(wa.error || 'PDF was not attached on WhatsApp');
        if (f.sendWa && waCc.sent) ok.push('WhatsApp CC');
        else if (f.sendWa && wa.sent && !waCc.sent && waCc.to) skip.push('WhatsApp CC failed: ' + (waCc.error||''));
        let tone = 'text-emerald-700';
        let msg = '';
        if (ok.length && skip.length){ tone='text-amber-700'; msg = `Sent via ${ok.join(' and ')}. ${skip.join('. ')}.`; }
        else if (ok.length){ msg = `Sent via ${ok.join(' and ')}.`; }
        else if (skip.length){ tone='text-red-600'; msg = skip.join('. ') + '.'; }
        else { msg = 'Notification processed.'; }
        status.className = 'text-sm ml-2 ' + tone;
        status.textContent = msg;
        loadHistory();
    } catch (e) {
        status.className = 'text-sm ml-2 text-red-600';
        status.textContent = e.message || 'Send failed.';
    } finally {
        hideSpinner();
    }
});

/* ============ History ============ */
async function loadHistory(){
    const q = document.getElementById('historySearch').value.trim();
    const list = document.getElementById('historyList');
    const empty = document.getElementById('historyEmpty');
    list.innerHTML = '';
    try {
        const res = await fetch(projectApiPath('api/staff-warning-letter.php?action=list&q=' + encodeURIComponent(q)), { credentials:'same-origin' });
        const data = await res.json();
        const items = data?.data?.items || [];
        if (!items.length){ empty.classList.remove('hidden'); return; }
        empty.classList.add('hidden');
        items.forEach(it => {
            const div = document.createElement('div');
            div.className = 'flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl border border-slate-200 p-3';
            const emPill = it.email_sent==1 ? '<span class="pill pill-ok">Email sent</span>' : '<span class="pill pill-fail">Email —</span>';
            const waPill = it.whatsapp_sent==1 ? `<span class="pill pill-ok">WhatsApp (${it.whatsapp_method||'sent'})</span>` : '<span class="pill pill-fail">WhatsApp —</span>';
            const created = new Date((it.created_at||'').replace(' ', 'T')).toLocaleString();
            div.innerHTML = `
                <div class="min-w-0">
                    <div class="font-semibold text-slate-900 truncate">${(it.staff_name||'—').replace(/</g,'&lt;')}</div>
                    <div class="text-xs text-slate-500 truncate">${(it.subject||'').replace(/</g,'&lt;')}</div>
                    <div class="text-[11px] text-slate-400 mt-1">${created}</div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    ${emPill} ${waPill}
                    ${it.pdf_url ? `<a href="${it.pdf_url}" target="_blank" rel="noopener" class="btn btn-secondary text-xs">View PDF</a>` : ''}
                    <button type="button" data-del="${it.id}" class="btn btn-danger text-xs">Delete</button>
                </div>`;
            list.appendChild(div);
        });
        list.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', async (e)=>{
            const id = e.currentTarget.dataset.del;
            if (!confirm('Delete this warning letter record?')) return;
            const fd = new FormData(); fd.append('id', id);
            await fetch(projectApiPath('api/staff-warning-letter.php?action=delete'), {method:'POST', credentials:'same-origin', body: fd});
            loadHistory();
        }));
    } catch (e) { console.error(e); }
}
document.getElementById('btnRefreshHistory').addEventListener('click', loadHistory);
let histTimer; document.getElementById('historySearch').addEventListener('input', () => { clearTimeout(histTimer); histTimer = setTimeout(loadHistory, 250); });
loadHistory();
</script>
</body>
</html>
