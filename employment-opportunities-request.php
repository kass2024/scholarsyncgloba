<?php
declare(strict_types=1);

ob_start();
session_name('EO_EMPLOYMENT_FORM');
session_start();

// Allow starting another application in the same browser (new session form id).
if (isset($_GET['new']) && (string) $_GET['new'] === '1') {
    $_SESSION['user_id'] = 'eo_' . bin2hex(random_bytes(6)) . '_' . time();
    header('Location: employment-opportunities-request.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'eo_' . bin2hex(random_bytes(6)) . '_' . time();
}
$user_id = $_SESSION['user_id'];

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/employment_opportunities_schema.php';
require_once __DIR__ . '/helpers/eo_contract_schema.php';
// Auto-migrate on every form visit (idempotent — safe on production).
eo_ensure_schema($conn);
eo_contract_ensure_schema($conn);

$already = false;
$existing_ref = '';
$st = $conn->prepare('SELECT reference_id, status FROM employment_opportunities_applications WHERE user_id = ? LIMIT 1');
if ($st) {
    $st->bind_param('s', $user_id);
    $st->execute();
    $existing = $st->get_result()->fetch_assoc();
    $st->close();
    if ($existing) {
        $already = true;
        $existing_ref = (string) $existing['reference_id'];
    }
}

$fields = eo_training_fields();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Employment Opportunities | ScholarSync Global</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --eo-green: #1e4d2b;
            --eo-blue: #3661B9;
            --eo-red: #c41e1e;
            --eo-bg: #f4f6f3;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            background: var(--eo-bg); color: #0f172a; margin: 0;
            overflow-x: hidden;
        }
        .eo-wrap {
            width: 100%;
            max-width: 820px;
            margin: 0 auto;
            padding: 0.75rem 0.75rem 2.5rem;
            padding-left: max(0.75rem, env(safe-area-inset-left));
            padding-right: max(0.75rem, env(safe-area-inset-right));
            padding-bottom: max(2.5rem, env(safe-area-inset-bottom));
        }
        .eo-hero {
            background: linear-gradient(135deg, var(--eo-green) 0%, var(--eo-blue) 100%);
            color: #fff; border-radius: 14px;
            padding: clamp(1rem, 4vw, 1.75rem);
            margin-bottom: 0.85rem;
        }
        .eo-hero h1 {
            font-size: clamp(1.15rem, 5vw, 1.7rem);
            font-weight: 700; margin: 0; line-height: 1.3;
            word-break: break-word;
        }
        .eo-hero .sub {
            opacity: .92; margin-top: .5rem;
            font-size: clamp(.85rem, 3.5vw, .95rem);
            line-height: 1.45;
        }
        .eo-section {
            background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;
            padding: clamp(0.9rem, 3vw, 1.35rem);
            margin-bottom: 0.85rem;
            box-shadow: 0 2px 12px rgba(30,77,43,.06);
            overflow: hidden;
        }
        .eo-section h2 {
            font-size: clamp(0.98rem, 3.8vw, 1.08rem);
            font-weight: 700; color: var(--eo-green);
            margin: 0 0 0.9rem; padding-bottom: .65rem;
            border-bottom: 2px solid #e2e8f0;
            line-height: 1.35;
        }
        .eo-label {
            display: block;
            font-weight: 600;
            font-size: 0.92rem;
            margin-bottom: 0.35rem;
        }
        .eo-label.required::after { content: " *"; color: var(--eo-red); }
        .form-control {
            min-height: 48px;
            font-size: 16px; /* prevents iOS zoom */
            border-radius: 10px;
            width: 100%;
        }
        .field-grid { display: grid; gap: .55rem; }
        .field-option {
            display: flex; align-items: flex-start; gap: .65rem;
            border: 1px solid #e2e8f0; border-radius: 10px;
            padding: 0.8rem 0.85rem;
            cursor: pointer; transition: .15s; background: #fafafa;
            -webkit-tap-highlight-color: transparent;
            min-height: 48px;
        }
        .field-option span {
            flex: 1;
            font-size: clamp(0.88rem, 3.6vw, 0.95rem);
            line-height: 1.4;
            word-break: break-word;
        }
        .field-option:hover { border-color: var(--eo-blue); background: #f0f7ff; }
        .field-option.active {
            border-color: var(--eo-green); background: #f0fdf4;
            box-shadow: 0 0 0 2px rgba(30,77,43,.12);
        }
        .field-option input { margin-top: .25rem; flex-shrink: 0; width: 1.1rem; height: 1.1rem; }
        .app-choice {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.55rem;
        }
        .app-choice label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            min-height: 48px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.65rem 0.5rem;
            background: #fafafa;
            margin: 0;
            cursor: pointer;
            font-size: 0.92rem;
            -webkit-tap-highlight-color: transparent;
        }
        .app-choice label:has(input:checked) {
            border-color: var(--eo-green);
            background: #f0fdf4;
            font-weight: 600;
        }
        .app-choice input { margin: 0; }
        .upload-zone {
            border: 2px dashed #cbd5e1; border-radius: 10px;
            padding: 1rem 0.75rem; text-align: center;
            cursor: pointer; transition: .2s; background: #fafafa;
            min-height: 96px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            -webkit-tap-highlight-color: transparent;
            width: 100%;
        }
        .upload-zone:hover, .upload-zone.dragover { border-color: var(--eo-blue); background: #f0f9ff; }
        .file-chip {
            display: flex; align-items: center; justify-content: space-between; gap: .5rem;
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: .55rem .7rem; margin-top: .5rem;
            font-size: .86rem;
            width: 100%;
        }
        .file-chip span {
            min-width: 0;
            overflow-wrap: anywhere;
            word-break: break-word;
            flex: 1;
        }
        .file-chip .btn { flex-shrink: 0; white-space: nowrap; }
        .btn-eo {
            background: linear-gradient(135deg, var(--eo-green), var(--eo-blue));
            border: 0; color: #fff;
            font-weight: 600;
            width: 100%;
            max-width: 100%;
            padding: 0.95rem 1.1rem;
            border-radius: 10px;
            min-height: 52px;
            font-size: 1rem;
        }
        .btn-eo:disabled { opacity: .65; }
        .submit-wrap { width: 100%; margin-top: 0.75rem; }
        .iti { width: 100% !important; display: block; }
        .iti__flag-container { z-index: 5; }
        .iti input.form-control {
            width: 100% !important;
            padding-left: 90px !important;
        }
        .hint { font-size: clamp(.78rem, 3.2vw, .84rem); color: #64748b; line-height: 1.4; }
        .done-box {
            background: #fff; border-radius: 12px; border: 1px solid #bbf7d0;
            padding: clamp(1rem, 4vw, 1.5rem); text-align: center;
        }
        .done-box .ref {
            font-family: ui-monospace, monospace;
            font-size: clamp(1rem, 4.5vw, 1.2rem);
            background: #f1f5f9;
            padding: .7rem .85rem;
            border-radius: 8px;
            display: block;
            margin: .75rem 0;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        #formError {
            text-align: left;
            padding: 0.75rem 0.85rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
        }
        @media (max-width: 480px) {
            .eo-wrap { padding-top: 0.55rem; }
            .app-choice { grid-template-columns: 1fr; }
            .field-option { padding: 0.75rem; }
            .upload-zone { min-height: 88px; padding: 0.85rem 0.6rem; }
        }
        @media (min-width: 641px) {
            .btn-eo { max-width: 360px; }
            .submit-wrap { text-align: center; }
            .submit-wrap .btn-eo { width: auto; min-width: 280px; }
        }
    </style>
</head>
<body>
<div class="eo-wrap">
    <div class="eo-hero">
        <h1>🇷🇺 Employment Opportunities</h1>
        <p class="sub mb-0">Professional training while studying the Russian language — apply and choose your placement field.</p>
    </div>

    <?php if ($already): ?>
        <div class="done-box">
            <h2 class="h5 text-success mb-2"><i class="fas fa-check-circle"></i> Application already submitted</h2>
            <p class="mb-1">Your reference ID:</p>
            <div class="ref"><?= htmlspecialchars($existing_ref, ENT_QUOTES, 'UTF-8') ?></div>
            <p class="hint mb-0">Our team will contact you by email and on WhatsApp or Telegram. Keep this reference for follow-up.</p>
            <a href="employment-opportunities-request.php?new=1" class="btn btn-eo mt-3 w-100">
                <i class="fas fa-plus-circle me-1"></i> Start new application
            </a>
            <a href="index.php" class="btn btn-outline-secondary mt-2 w-100">Back to home</a>
        </div>
    <?php else: ?>

    <div class="eo-section">
        <p class="mb-2" style="margin:0 0 .55rem;line-height:1.45;font-size:clamp(.9rem,3.5vw,.95rem)">Participants receive professional training while studying the Russian language and may be placed in one of the following fields.</p>
        <p class="hint mb-0">All positions combine practical work experience with Russian language training, allowing participants to develop valuable professional skills while becoming proficient in Russian.</p>
    </div>

    <form id="eoForm" novalidate>
        <input type="hidden" name="user_id" value="<?= htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="passport_file" id="passport_file" value="">
        <input type="hidden" name="academic_docs_file" id="academic_docs_file" value="">
        <input type="hidden" name="phone_area_code" id="phone_area_code" value="">
        <input type="hidden" name="phone_number" id="phone_number_hidden" value="">

        <div class="eo-section">
            <h2><span style="color:var(--eo-red)">1.</span> Personal details</h2>
            <div class="mb-3">
                <label class="eo-label required" for="full_name">Full name</label>
                <input type="text" class="form-control" id="full_name" name="full_name" required maxlength="200" autocomplete="name" placeholder="As on passport">
            </div>
            <div class="mb-3">
                <label class="eo-label required" for="passport_number">Passport number</label>
                <input type="text" class="form-control" id="passport_number" name="passport_number" required maxlength="64" autocomplete="off" placeholder="Passport number">
            </div>
            <div class="mb-3">
                <label class="eo-label required" for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" required maxlength="150" autocomplete="email" inputmode="email" placeholder="you@example.com">
            </div>
            <div class="mb-3">
                <label class="eo-label required" for="phone_input">Contact phone (Telegram or WhatsApp)</label>
                <input type="tel" class="form-control" id="phone_input" required autocomplete="tel">
                <div class="hint mt-1">Use the number linked to Telegram or WhatsApp.</div>
            </div>
            <div>
                <label class="eo-label required">Preferred app</label>
                <div class="app-choice">
                    <label>
                        <input type="radio" name="messaging_app" value="whatsapp" checked>
                        <i class="fab fa-whatsapp text-success"></i> WhatsApp
                    </label>
                    <label>
                        <input type="radio" name="messaging_app" value="telegram">
                        <i class="fab fa-telegram text-primary"></i> Telegram
                    </label>
                </div>
            </div>
        </div>

        <div class="eo-section">
            <h2><span style="color:var(--eo-red)">2.</span> Choose training field</h2>
            <div class="field-grid">
                <?php foreach ($fields as $key => $label): ?>
                <label class="field-option" data-field="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="radio" name="training_field" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" required>
                    <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="eo-section">
            <h2><span style="color:var(--eo-red)">3.</span> Attachments</h2>

            <div class="mb-4">
                <label class="eo-label required">Passport scan (PDF or image preferred)</label>
                <div class="upload-zone" id="passportZone" data-field="passport" role="button" tabindex="0">
                    <div id="passportZoneInner">
                        <i class="fas fa-cloud-upload-alt fa-lg mb-1 text-secondary"></i>
                        <div>Tap to upload passport scan</div>
                        <div class="hint">PDF, JPG, PNG, WEBP — max 15MB</div>
                    </div>
                </div>
                <input type="file" id="passportInput" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,image/*,application/pdf" hidden>
                <div id="passportPreview"></div>
            </div>

            <div>
                <label class="eo-label required">Academic documents</label>
                <p class="hint mb-2">Upload one or more (diploma, transcripts, certificates). Add files one by one or several at once.</p>
                <div class="upload-zone" id="academicZone" data-field="academic" role="button" tabindex="0">
                    <div id="academicZoneInner">
                        <i class="fas fa-cloud-upload-alt fa-lg mb-1 text-secondary"></i>
                        <div>Tap to upload academic documents</div>
                        <div class="hint">Multiple files allowed</div>
                    </div>
                </div>
                <input type="file" id="academicInput" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,image/*,application/pdf" multiple hidden>
                <div id="academicPreview"></div>
            </div>
        </div>

        <div class="submit-wrap">
            <button type="submit" class="btn btn-eo" id="submitBtn">
                <i class="fas fa-paper-plane me-1"></i> Submit application
            </button>
            <div id="formError" class="text-danger mt-3 small" style="display:none"></div>
        </div>
    </form>
    <!-- Outside the form so it stays visible after form is hidden -->
    <div id="formSuccess" class="done-box mt-3" style="display:none"></div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script>
(function () {
    const form = document.getElementById('eoForm');
    if (!form) return;

    const phoneInput = document.getElementById('phone_input');
    const iti = window.intlTelInput(phoneInput, {
        initialCountry: 'rw',
        preferredCountries: ['rw', 'ke', 'ug', 'bi', 'cd', 'tz'],
        utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js'
    });

    document.querySelectorAll('.field-option').forEach(el => {
        el.addEventListener('click', () => {
            document.querySelectorAll('.field-option').forEach(x => x.classList.remove('active'));
            el.classList.add('active');
            const radio = el.querySelector('input[type=radio]');
            if (radio) radio.checked = true;
        });
    });

    let academicFiles = [];

    function setPassport(path, name) {
        document.getElementById('passport_file').value = path || '';
        const box = document.getElementById('passportPreview');
        if (!path) { box.innerHTML = ''; return; }
        box.innerHTML = `<div class="file-chip"><span><i class="fas fa-file me-1"></i>${escapeHtml(name || 'Passport')}</span>
            <button type="button" class="btn btn-sm btn-outline-danger" id="clearPassport">Remove</button></div>`;
        document.getElementById('clearPassport')?.addEventListener('click', () => setPassport('', ''));
    }

    function renderAcademic() {
        document.getElementById('academic_docs_file').value = JSON.stringify(academicFiles.map(f => f.path));
        const box = document.getElementById('academicPreview');
        if (!academicFiles.length) { box.innerHTML = ''; return; }
        box.innerHTML = academicFiles.map((f, i) => `
            <div class="file-chip">
                <span><i class="fas fa-file me-1"></i>${escapeHtml(f.name)}</span>
                <button type="button" class="btn btn-sm btn-outline-danger" data-rm="${i}">Remove</button>
            </div>`).join('');
        box.querySelectorAll('[data-rm]').forEach(btn => {
            btn.addEventListener('click', () => {
                academicFiles.splice(parseInt(btn.dataset.rm, 10), 1);
                renderAcademic();
            });
        });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function uploadFile(file, field, onProgress) {
        return new Promise((resolve, reject) => {
            const fd = new FormData();
            fd.append('file', file);
            fd.append('field', field);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'eo_upload.php');
            xhr.timeout = 120000; // 2 minutes for large PDFs on slow mobile
            if (xhr.upload && typeof onProgress === 'function') {
                xhr.upload.onprogress = (ev) => {
                    if (ev.lengthComputable && ev.total > 0) {
                        onProgress(Math.round((ev.loaded / ev.total) * 100));
                    }
                };
            }
            xhr.onload = () => {
                let data;
                try { data = JSON.parse(xhr.responseText || '{}'); } catch (e) { reject(new Error('Upload failed')); return; }
                if (!data.success) { reject(new Error(data.message || 'Upload failed')); return; }
                resolve(data);
            };
            xhr.ontimeout = () => reject(new Error('Upload timed out. Try a smaller file or better connection.'));
            xhr.onerror = () => reject(new Error('Network error during upload'));
            xhr.send(fd);
        });
    }

    function wireZone(zoneId, inputId, onFiles) {
        const zone = document.getElementById(zoneId);
        const input = document.getElementById(inputId);
        const openPicker = () => input.click();
        zone.addEventListener('click', openPicker);
        zone.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPicker(); }
        });
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('dragover');
            if (e.dataTransfer.files?.length) onFiles(e.dataTransfer.files);
        });
        input.addEventListener('change', () => {
            if (input.files?.length) onFiles(input.files);
            input.value = '';
        });
    }

    wireZone('passportZone', 'passportInput', async (files) => {
        const file = files[0];
        if (!file) return;
        if (file.size > 15 * 1024 * 1024) {
            alert('File too large (max 15MB)');
            return;
        }
        const inner = document.getElementById('passportZoneInner');
        const defaultHtml = inner.innerHTML;
        const label = escapeHtml(file.name);
        inner.innerHTML = `<span class="text-primary"><i class="fas fa-spinner fa-spin"></i> Uploading ${label}… 0%</span>`;
        try {
            const res = await uploadFile(file, 'passport', (pct) => {
                inner.innerHTML = `<span class="text-primary"><i class="fas fa-spinner fa-spin"></i> Uploading ${label}… ${pct}%</span>`;
            });
            inner.innerHTML = defaultHtml;
            setPassport(res.file_path, res.original_name || file.name);
        } catch (err) {
            inner.innerHTML = `<span class="text-danger">${escapeHtml(err.message)}</span>`;
            setTimeout(() => { inner.innerHTML = defaultHtml; }, 3500);
        }
    });

    wireZone('academicZone', 'academicInput', async (files) => {
        const list = Array.from(files || []);
        const inner = document.getElementById('academicZoneInner');
        const defaultHtml = inner.innerHTML;
        for (const file of list) {
            if (file.size > 15 * 1024 * 1024) {
                alert(file.name + ' is too large (max 15MB)');
                continue;
            }
            const label = escapeHtml(file.name);
            inner.innerHTML = `<span class="text-primary"><i class="fas fa-spinner fa-spin"></i> Uploading ${label}… 0%</span>`;
            try {
                const res = await uploadFile(file, 'academic', (pct) => {
                    inner.innerHTML = `<span class="text-primary"><i class="fas fa-spinner fa-spin"></i> Uploading ${label}… ${pct}%</span>`;
                });
                academicFiles.push({ path: res.file_path, name: res.original_name || file.name });
                renderAcademic();
            } catch (err) {
                alert(err.message || 'Upload failed');
            }
        }
        inner.innerHTML = defaultHtml;
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const errEl = document.getElementById('formError');
        const okEl = document.getElementById('formSuccess');
        const btn = document.getElementById('submitBtn');
        errEl.style.display = 'none';
        okEl.style.display = 'none';

        const email = String(document.getElementById('email').value || '').trim();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errEl.textContent = 'Please enter a valid email address.';
            errEl.style.display = 'block';
            document.getElementById('email').focus();
            return;
        }

        if (!iti.isValidNumber()) {
            errEl.textContent = 'Please enter a valid phone number with country code.';
            errEl.style.display = 'block';
            phoneInput.focus();
            return;
        }
        const country = iti.getSelectedCountryData();
        document.getElementById('phone_area_code').value = country.dialCode || '';
        const full = iti.getNumber().replace(/\D/g, '');
        const dial = String(country.dialCode || '');
        let national = full;
        if (dial && full.startsWith(dial)) national = full.slice(dial.length);
        document.getElementById('phone_number_hidden').value = national;

        if (!document.getElementById('passport_file').value) {
            errEl.textContent = 'Please upload your passport scan.';
            errEl.style.display = 'block';
            return;
        }
        if (!academicFiles.length) {
            errEl.textContent = 'Please upload at least one academic document.';
            errEl.style.display = 'block';
            return;
        }
        if (!form.querySelector('input[name="training_field"]:checked')) {
            errEl.textContent = 'Please choose a training field.';
            errEl.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting…';

        const fd = new FormData(form);
        try {
            const r = await fetch('save_employment_opportunities_request.php', { method: 'POST', body: fd });
            const data = await r.json();
            if (!data.success) {
                let msg = data.message || 'Submission failed';
                if (Array.isArray(data.missing) && data.missing.length) {
                    msg += ': ' + data.missing.join(', ');
                }
                errEl.textContent = msg;
                errEl.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit application';
                return;
            }
            form.style.display = 'none';
            document.querySelectorAll('.eo-section').forEach(el => { el.style.display = 'none'; });
            okEl.style.display = 'block';
            const ref = escapeHtml(data.reference_id || '');
            const serverMsg = escapeHtml(data.message || 'Application submitted successfully.');
            okEl.innerHTML = `
                <div class="text-center py-2">
                    <div class="mb-3" style="font-size:2.5rem;color:#15803d;line-height:1"><i class="fas fa-check-circle"></i></div>
                    <h2 class="h5 text-success mb-2">Application submitted successfully</h2>
                    <p class="mb-2 text-muted">${serverMsg}</p>
                    <p class="mb-1 fw-semibold">Your reference ID</p>
                    <div class="ref">${ref}</div>
                    <p class="hint mt-3 mb-0">Please save this reference ID. A confirmation email is on its way, and our team will also contact you on WhatsApp or Telegram.</p>
                    <a href="employment-opportunities-request.php?new=1" class="btn btn-eo mt-3 w-100">
                        <i class="fas fa-plus-circle me-1"></i> Start new application
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary mt-2 w-100">Back to home</a>
                </div>`;
            document.querySelector('.eo-wrap')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (err) {
            errEl.textContent = 'Network error. Please try again.';
            errEl.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit application';
        }
    });
})();
</script>
</body>
</html>
