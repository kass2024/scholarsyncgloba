<?php
require_once __DIR__ . '/../includes/brand_logo.php';

$cfg = require __DIR__ . '/config.php';
$defaultProvider = $cfg['default_provider'] ?? 'mopay';

// Production checkout: don't show/provider-switching UI.
$provider = in_array($defaultProvider, ['mopay', 'stripe'], true) ? $defaultProvider : 'mopay';

// Currency shown to the customer (amount entry is always integer).
$mopay = $cfg['mopay'];
$stripe = $cfg['stripe'];

$defaultCurrency = $provider === 'stripe'
    ? ($stripe['default_currency'] ?: 'usd')
    : ($mopay['default_currency'] ?: 'RWF');

$subtitle = $provider === 'mopay'
    ? 'Pay with MTN Mobile Money (MTN Rwanda)'
    : 'Pay with Card (Stripe)';

$logoSrc = scholarsync_brand_logo_href(__DIR__);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Secure Payment</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    :root{
      --pcvc-green:#427431;
      --pcvc-green-dark:#2f5a26;
      --pcvc-blue:#3661B9;
      --brand-green:#2d6a3e;
      --brand-red:#E21D1E;
      --brand-dark:#0f172a;
      --brand-muted:#64748b;
      --bg:#f1f5f9;
      --card:#ffffff;
      --border:rgba(15,23,42,.1);
      --shadow: 0 20px 50px rgba(15, 23, 42, .08), 0 4px 16px rgba(66, 116, 49, .06);
      --radius: 20px;
      --touch: 48px;
    }
    *{ box-sizing:border-box; }
    body {
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Liberation Sans", sans-serif;
      background:
        radial-gradient(900px 480px at 10% -5%, rgba(66, 116, 49, .16), transparent 55%),
        radial-gradient(800px 420px at 100% 0%, rgba(226, 29, 30, .1), transparent 50%),
        radial-gradient(600px 300px at 50% 100%, rgba(54, 97, 185, .08), transparent 45%),
        var(--bg);
      margin: 0;
      min-height: 100vh;
      min-height: 100dvh;
      display:flex;
      align-items:flex-start;
      justify-content:center;
      padding: max(1rem, env(safe-area-inset-top)) max(1rem, env(safe-area-inset-right)) max(1.25rem, env(safe-area-inset-bottom)) max(1rem, env(safe-area-inset-left));
      color: var(--brand-dark);
    }
    @media (min-width: 640px) {
      body { align-items: center; padding: 2rem 1.25rem; }
    }
    .wrap { width: 100%; max-width: 640px; margin: 0 auto; }
    .panel {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: clamp(1rem, 4vw, 1.5rem);
      box-shadow: var(--shadow);
      border-top: 4px solid var(--pcvc-green);
    }
    .title { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.5rem; text-align:center; padding: .25rem .25rem 1rem; border-bottom: 1px solid rgba(15,23,42,.08); margin-bottom: .25rem; }
    .title h2 { margin: 0; font-size: clamp(1.05rem, 3.5vw, 1.2rem); letter-spacing: -.02em; color: var(--pcvc-green-dark); }
    .subtitle { color: var(--brand-muted); margin-top:.05rem; font-size: clamp(.88rem, 2.8vw, .95rem); }
    .tabs { display:flex; gap:.5rem; margin-top: 1rem; flex-wrap:wrap; }
    .tab { border: 1px solid #e5e7eb; background:#fff; border-radius: 12px; padding: .65rem .85rem; cursor:pointer; font-weight:700; color:#111827; display:flex; gap:.5rem; align-items:center; }
    .tab.active { border-color: #E21D1E; background: rgba(226, 29, 30, 0.1); }
    .grid { display:flex; gap: 1rem; flex-wrap:wrap; margin-top: .5rem; }
    .col { flex: 1; min-width: 0; max-width: 100%; }
    .form { margin-top: .25rem; }
    .fields { display:grid; grid-template-columns: 1fr; gap: .9rem; margin-top: 1rem; }
    @media (min-width: 720px) {
      .wrap{ max-width: 720px; }
      .fields{ grid-template-columns: 1.25fr .9fr; }
    }
    label { display:block; margin-bottom:.4rem; font-weight:800; font-size:.86rem; color: rgba(15,23,42,.85); letter-spacing:.01em; }
    input, select {
      width:100%;
      padding:.85rem .9rem;
      border:1px solid rgba(15,23,42,.16);
      border-radius: 14px;
      font-size: 1rem;
      outline: none;
      background: #fff;
      transition: border-color .15s ease, box-shadow .15s ease;
    }
    input:focus, select:focus{
      border-color: var(--pcvc-green);
      box-shadow: 0 0 0 3px rgba(66, 116, 49, .18);
    }
    .btn {
      width: 100%;
      min-height: var(--touch);
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:.55rem;
      padding: .85rem 1rem;
      border-radius: 14px;
      border:0;
      background: linear-gradient(135deg, var(--pcvc-green) 0%, var(--pcvc-green-dark) 45%, #c41e3a 100%);
      color:#fff;
      font-weight:800;
      font-size: clamp(.95rem, 2.8vw, 1rem);
      cursor:pointer;
      text-decoration:none;
      box-shadow: 0 10px 28px rgba(66, 116, 49, .25);
      transition: transform .08s ease, filter .15s ease, box-shadow .15s ease;
    }
    .btn:hover{ filter: brightness(1.03); box-shadow: 0 12px 32px rgba(66, 116, 49, .3); }
    .btn:active{ transform: translateY(1px); }
    .btn:disabled { opacity: .55; cursor: not-allowed; transform: none; }
    .btn.secondary {
      background: linear-gradient(180deg, #334155 0%, #1e293b 100%);
      box-shadow: 0 8px 20px rgba(15, 23, 42, .2);
    }
    .btn.secondary:hover { filter: brightness(1.05); }
    .hint { color: var(--brand-muted); font-size:.9rem; margin-top:.5rem; line-height: 1.35; }
    .micro { color: rgba(15,23,42,.55); font-size: .82rem; margin-top: .45rem; }
    .pill {
      display:inline-flex; align-items:flex-start; gap:.5rem;
      padding:.65rem .85rem;
      border-radius: 12px;
      border:1px solid rgba(66, 116, 49, .22);
      background: linear-gradient(135deg, rgba(66, 116, 49, .08), rgba(54, 97, 185, .06));
      color: rgba(15,23,42,.78);
      font-size: clamp(.82rem, 2.5vw, .88rem);
      margin-top: .9rem;
      line-height: 1.4;
      width: 100%;
    }
    /* Keep "Pay now" input fixed on the right (desktop/tablet) */
    .item-row {
      border:1px solid rgba(15,23,42,.10);
      border-radius:16px;
      padding: .75rem .85rem;
      display:grid;
      grid-template-columns: minmax(0, 1fr) 240px;
      gap: .9rem;
      align-items:start;
    }
    .item-left { min-width: 0; }
    .item-right { width: 240px; justify-self: end; }
    @media (max-width: 560px) {
      /* On small screens, stack naturally */
      .item-row { grid-template-columns: 1fr; }
      .item-right { width: 100%; justify-self: stretch; }
    }
    .item-name { font-weight: 900; }
    .item-sub { color: rgba(15,23,42,.55); font-size: .82rem; margin-top: .18rem; line-height:1.25; }
    .err { color: var(--brand-red); font-weight: 900; font-size: .82rem; margin-top: .35rem; display:none; }
    .preview { color: rgba(31,138,76,.95); font-weight: 900; font-size: .82rem; margin-top: .35rem; display:none; }
    details { margin-top: .9rem; }
    pre { background:#f3f4f6; border:1px solid #e5e7eb; padding: .75rem; border-radius: 10px; overflow:auto; }
    .checkout-logo{width:clamp(72px, 18vw, 88px);height:clamp(72px, 18vw, 88px);object-fit:contain;}
    #card-element { background: #fff; border: 1px solid #d1d5db; border-radius: 10px; padding: .65rem; }
    .mt-1 { margin-top: 1rem; }
    .student-block { margin-top: .75rem; }
    .student-mode-tabs {
      display: flex;
      gap: 0;
      margin-bottom: 1rem;
      border-radius: 14px;
      padding: 4px;
      background: #e8eef3;
      border: 1px solid rgba(15,23,42,.08);
    }
    .student-mode-tab {
      flex: 1;
      min-height: 44px;
      border: 0;
      border-radius: 11px;
      background: transparent;
      color: var(--brand-muted);
      font-weight: 800;
      font-size: clamp(.8rem, 2.6vw, .9rem);
      cursor: pointer;
      padding: .5rem .6rem;
      transition: background .2s, color .2s, box-shadow .2s;
      -webkit-tap-highlight-color: transparent;
    }
    .student-mode-tab.active {
      background: #fff;
      color: var(--pcvc-green-dark);
      box-shadow: 0 2px 10px rgba(15,23,42,.08);
    }
    .student-mode-tab:focus-visible {
      outline: 2px solid var(--pcvc-green);
      outline-offset: 2px;
    }
    .student-panel { display: none; }
    .student-panel.active { display: block; }
    .student-empty-hint {
      color: #b45309;
      font-size: clamp(.8rem, 2.5vw, .86rem);
      margin-top: .5rem;
      font-weight: 700;
      display: none;
      align-items: flex-start;
      gap: .5rem;
      padding: .6rem .75rem;
      border-radius: 10px;
      background: rgba(245, 158, 11, .12);
      border: 1px solid rgba(245, 158, 11, .35);
      line-height: 1.35;
    }
    .student-empty-hint.visible { display: flex; }
    .new-student-card {
      border: 2px solid rgba(66, 116, 49, .25);
      border-radius: 16px;
      padding: clamp(.85rem, 3vw, 1.1rem);
      background: linear-gradient(180deg, rgba(66, 116, 49, .06) 0%, rgba(255,255,255,.9) 100%);
    }
    .new-student-card .new-student-head {
      font-weight: 900;
      color: var(--pcvc-green-dark);
      font-size: clamp(.9rem, 2.8vw, .98rem);
      margin: 0 0 .75rem;
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    .new-student-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: .75rem;
    }
    .ns-span-2 { grid-column: 1 / -1; }
    @media (max-width: 520px) {
      .new-student-grid { grid-template-columns: 1fr; }
    }
    .ns-err { color: var(--brand-red); font-weight: 700; font-size: .82rem; margin-top: .5rem; display: none; padding: .5rem .65rem; border-radius: 8px; background: rgba(226, 29, 30, .08); border: 1px solid rgba(226, 29, 30, .2); }
    .ns-err.visible { display: block; }
    .label-req::after { content: ' *'; color: var(--brand-red); font-weight: 900; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="panel">
      <div class="title">
        <img src="<?= htmlspecialchars($logoSrc) ?>" class="checkout-logo" alt="ScholarSync Global" width="88" height="88" decoding="async" />
        <h2><i class="fas fa-lock" style="color:var(--brand-red);margin-right:.4rem;"></i>Secure Payment</h2>
        <div class="subtitle"><?= htmlspecialchars($subtitle) ?></div>
      </div>

      <?php if ($provider === 'mopay'): ?>
        <div class="grid">
          <div class="col">
            <div class="student-block">
              <div class="student-mode-tabs" role="tablist" aria-label="Student lookup">
                <button type="button" class="student-mode-tab active" id="tab-search" role="tab" aria-selected="true" aria-controls="panel-search" data-mode="search">
                  <i class="fas fa-search" style="opacity:.85;margin-right:.35rem;"></i>Find existing
                </button>
                <button type="button" class="student-mode-tab" id="tab-new" role="tab" aria-selected="false" aria-controls="panel-new" data-mode="new">
                  <i class="fas fa-user-plus" style="opacity:.85;margin-right:.35rem;"></i>New student
                </button>
              </div>

              <div id="panel-search" class="student-panel active" role="tabpanel" aria-labelledby="tab-search" aria-hidden="false">
                <label for="student-q">Student</label>
                <input id="student-q" placeholder="Search name, email, or phone…" autocomplete="off" />
                <div id="student-results" style="position:relative;"></div>
                <div id="student-empty-hint" class="student-empty-hint">
                  <i class="fas fa-info-circle" style="margin-top:.15rem;flex-shrink:0;"></i>
                  <span>No match. Switch to <strong>New student</strong> to register, or check your spelling.</span>
                </div>
                <div class="micro" id="student-selected" style="display:none;margin-top:.5rem;"></div>
              </div>

              <div id="panel-new" class="student-panel" role="tabpanel" aria-labelledby="tab-new" aria-hidden="true">
                <div class="new-student-card">
                  <p class="new-student-head">
                    <i class="fas fa-id-card" style="color:var(--pcvc-green);"></i>
                    Register &amp; pay — first time here?
                  </p>
                  <div class="new-student-grid">
                    <div>
                      <label for="ns-first" class="label-req">First name</label>
                      <input id="ns-first" type="text" autocomplete="given-name" placeholder="Given name" required />
                    </div>
                    <div>
                      <label for="ns-last" class="label-req">Last name</label>
                      <input id="ns-last" type="text" autocomplete="family-name" placeholder="Family name" required />
                    </div>
                    <div class="ns-span-2">
                      <label for="ns-email" class="label-req">Email</label>
                      <input id="ns-email" type="email" autocomplete="email" placeholder="you@example.com" required />
                      <div class="micro">Must be unique — not already used by another student.</div>
                    </div>
                    <div class="ns-span-2">
                      <label for="ns-phone" class="label-req">MoMo phone</label>
                      <input id="ns-phone" inputmode="numeric" autocomplete="tel" placeholder="2507XXXXXXXX or 07XXXXXXXX" required />
                      <div class="micro">Rwanda MTN. Digits only; <strong>250</strong> is added if missing.</div>
                    </div>
                    <div class="ns-span-2">
                      <button type="button" class="btn secondary" id="ns-create-btn">
                        <i class="fas fa-arrow-right"></i> Create &amp; load fee packages
                      </button>
                      <div id="ns-err" class="ns-err" role="alert"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div style="margin-top:1rem;">
              <label>Fee package</label>
              <select id="package" disabled>
                <option value="">Select a package…</option>
              </select>
              <div class="micro" id="pkg-currency" style="display:none;"></div>
            </div>

            <div id="items-box" style="margin-top:1rem; display:none;">
              <label>Fee items (pay partially)</label>
              <div id="items" style="display:grid; gap:.6rem;"></div>
              <div class="pill" id="total-pill" style="display:none;">
                <i class="fas fa-receipt" style="color:var(--pcvc-green)"></i>
                Total: <span id="total-base" style="font-weight:900;margin-left:.35rem;"></span>
                <span id="total-base-cur" style="font-weight:900;"></span>
                <span style="color:rgba(15,23,42,.55);font-weight:900;margin-left:.35rem;">
                  (≈ <span id="total-rwf"></span> RWF)
                </span>
              </div>
            </div>

            <form method="post" action="mopay/start.php" class="form" autocomplete="on" id="pay-form" style="margin-top:.8rem;">
              <div class="fields">
                <div>
                  <label>Phone number</label>
                  <input name="phone" inputmode="numeric" autocomplete="tel" required placeholder="2507XXXXXXXX" />
                  <div class="micro">Digits only (include country code).</div>
                </div>
                <div>
                  <label>Amount (RWF)</label>
                  <input name="amount" type="number" min="1" step="1" inputmode="numeric" required placeholder="0" readonly />
                  <div class="micro" id="amount-equivalent" style="display:none;"></div>
                </div>
              </div>

              <div class="pill">
                <i class="fas fa-bolt" style="color:var(--pcvc-green)"></i>
                Mobile prompt will be sent after you continue.
              </div>

              <div style="margin-top:1rem;">
                <button class="btn" type="submit" id="pay-btn" disabled>
                  <i class="fas fa-wallet"></i> Continue
                </button>
              </div>

              <div class="hint">
                Didn’t get the prompt? Dial <span style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">*182*7*1momo PIN#</span>.
              </div>

              <input type="hidden" name="student_id" id="student_id" value="" />
              <input type="hidden" name="package_id" id="package_id" value="" />
              <input type="hidden" name="items" id="items_input" value="" />
            </form>
          </div>
        </div>

        <script>
          (function() {
            const q = document.getElementById('student-q');
            const resultsWrap = document.getElementById('student-results');
            const selectedEl = document.getElementById('student-selected');
            const packageSel = document.getElementById('package');
            const pkgCurrencyEl = document.getElementById('pkg-currency');
            const itemsBox = document.getElementById('items-box');
            const itemsEl = document.getElementById('items');
            const totalPill = document.getElementById('total-pill');
            const totalRwfEl = document.getElementById('total-rwf');
            const totalBaseEl = document.getElementById('total-base');
            const totalBaseCurEl = document.getElementById('total-base-cur');

            const form = document.getElementById('pay-form');
            const payBtn = document.getElementById('pay-btn');
            const amountInput = form.querySelector('input[name="amount"]');
            const phoneInput = form.querySelector('input[name="phone"]');
            const amountEquivalentEl = document.getElementById('amount-equivalent');

            const studentIdInput = document.getElementById('student_id');
            const packageIdInput = document.getElementById('package_id');
            const itemsInput = document.getElementById('items_input');

            const studentEmptyHint = document.getElementById('student-empty-hint');
            const panelSearch = document.getElementById('panel-search');
            const panelNew = document.getElementById('panel-new');
            const tabSearch = document.getElementById('tab-search');
            const tabNew = document.getElementById('tab-new');
            const nsFirst = document.getElementById('ns-first');
            const nsLast = document.getElementById('ns-last');
            const nsEmail = document.getElementById('ns-email');
            const nsPhone = document.getElementById('ns-phone');
            const nsCreateBtn = document.getElementById('ns-create-btn');
            const nsErr = document.getElementById('ns-err');

            let selectedStudent = null;
            let selectedPackageId = null;
            let feeItems = [];
            let fxRateToRwf = 1;
            let pkgCurrencyCode = 'RWF';
            let amountIsDecimal = false;
            let amountStep = 1;

            function clamp2(n) { return Math.round((Number(n) || 0) * 100) / 100; }
            function formatBaseMoney(n) {
              const num = Number(n || 0);
              const maxFrac = amountIsDecimal ? 2 : 0;
              return num.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: maxFrac });
            }

            function debounce(fn, ms) {
              let t = null;
              return function(...args) {
                if (t) clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), ms);
              };
            }

            function escapeHtml(s) {
              return String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
            }

            function clearResults() { resultsWrap.innerHTML = ''; }

            function setStudentEmptyHintVisible(on) {
              if (!studentEmptyHint) return;
              if (on) studentEmptyHint.classList.add('visible');
              else studentEmptyHint.classList.remove('visible');
            }

            function setNsErr(msg) {
              if (!nsErr) return;
              nsErr.textContent = msg || '';
              if (msg) nsErr.classList.add('visible');
              else nsErr.classList.remove('visible');
            }

            function setStudentMode(mode) {
              const isSearch = mode === 'search';
              if (panelSearch) {
                panelSearch.classList.toggle('active', isSearch);
                panelSearch.setAttribute('aria-hidden', isSearch ? 'false' : 'true');
              }
              if (panelNew) {
                panelNew.classList.toggle('active', !isSearch);
                panelNew.setAttribute('aria-hidden', isSearch ? 'true' : 'false');
              }
              if (tabSearch) {
                tabSearch.classList.toggle('active', isSearch);
                tabSearch.setAttribute('aria-selected', isSearch ? 'true' : 'false');
              }
              if (tabNew) {
                tabNew.classList.toggle('active', !isSearch);
                tabNew.setAttribute('aria-selected', !isSearch ? 'true' : 'false');
              }
              if (!isSearch) setStudentEmptyHintVisible(false);
            }

            if (tabSearch && tabNew) {
              tabSearch.addEventListener('click', function () { setStudentMode('search'); });
              tabNew.addEventListener('click', function () { setStudentMode('new'); });
            }

            function renderResults(list) {
              clearResults();
              if (!list.length) return;
              const box = document.createElement('div');
              box.style.position = 'absolute';
              box.style.zIndex = '20';
              box.style.width = '100%';
              box.style.marginTop = '.4rem';
              box.style.border = '1px solid rgba(15,23,42,.14)';
              box.style.borderRadius = '14px';
              box.style.background = '#fff';
              box.style.boxShadow = '0 16px 40px rgba(2,6,23,.10)';
              box.style.overflow = 'hidden';
              list.forEach((s) => {
                const row = document.createElement('button');
                row.type = 'button';
                row.style.display = 'block';
                row.style.width = '100%';
                row.style.textAlign = 'left';
                row.style.padding = '.75rem .9rem';
                row.style.border = '0';
                row.style.background = 'transparent';
                row.style.cursor = 'pointer';
                row.innerHTML = `
                  <div style="font-weight:900;">${escapeHtml(s.name)} <span style="color:rgba(15,23,42,.5);font-weight:800;">#${s.id}</span></div>
                  <div style="color:rgba(15,23,42,.55);font-size:.85rem;margin-top:.15rem;">${escapeHtml(s.email || '')}${s.phone ? ' · ' + escapeHtml(s.phone) : ''}</div>
                `;
                row.addEventListener('click', () => selectStudent(s));
                box.appendChild(row);
              });
              resultsWrap.appendChild(box);
            }

            async function searchStudents(query) {
              const res = await fetch('api/student-search.php?q=' + encodeURIComponent(query));
              const data = await res.json().catch(() => null);
              if (!data || !data.ok) return [];
              return data.results || [];
            }

            async function loadPackages(studentId) {
              const res = await fetch('../load-payment-info.php?student_id=' + encodeURIComponent(studentId));
              const data = await res.json().catch(() => null);
              return data && data.packages ? data.packages : [];
            }

            async function loadPackageDetails(studentId, packageId) {
              const res = await fetch('../load-package-details.php?student_id=' + encodeURIComponent(studentId) + '&package_id=' + encodeURIComponent(packageId));
              const data = await res.json().catch(() => null);
              return data;
            }

            async function loadFxRate(currency) {
              const res = await fetch('api/fx-rate.php?from=' + encodeURIComponent(currency));
              const data = await res.json().catch(() => null);
              if (!data || !data.ok) return 1;
              return Number(data.rate || 1) || 1;
            }

            async function selectStudent(s) {
              selectedStudent = s;
              studentIdInput.value = String(s.id);
              selectedEl.style.display = 'block';
              selectedEl.textContent = 'Selected: ' + s.name + ' (#' + s.id + ')';
              clearResults();
              setStudentEmptyHintVisible(false);
              q.value = s.name;

              if (s.phone && !phoneInput.value) phoneInput.value = s.phone;

              packageSel.disabled = true;
              packageSel.innerHTML = '<option value="">Loading packages…</option>';
              itemsBox.style.display = 'none';
              payBtn.disabled = true;
              amountInput.value = '';
              itemsInput.value = '';
              packageIdInput.value = '';
              selectedPackageId = null;

              const pkgs = await loadPackages(s.id);
              packageSel.innerHTML = '<option value="">Select a package…</option>';
              pkgs.forEach((p) => {
                const opt = document.createElement('option');
                opt.value = String(p.id);
                opt.textContent = p.name;
                packageSel.appendChild(opt);
              });
              packageSel.disabled = false;
            }

            function computeSelection() {
              const selectedItems = [];
              let totalBase = 0;
              let hasInvalid = false;

              feeItems.forEach((it) => {
                const cb = document.getElementById('fee_' + it.id);
                if (!cb || !cb.checked) return;

                const remaining = Number(it.remaining || 0);
                const input = document.getElementById('fee_amt_' + it.id);
                const raw = input ? input.value : '';
                let val = Number(raw);

                if (!isFinite(val) || val <= 0) {
                  hasInvalid = true;
                  return;
                }

                val = amountIsDecimal ? clamp2(val) : Math.round(val);

                if (val > remaining + 1e-9) {
                  hasInvalid = true;
                  return;
                }

                selectedItems.push({ id: it.id, amount: val });
                totalBase += val;
              });

              const totalRwf = Math.ceil(totalBase * fxRateToRwf);
              amountInput.value = totalRwf > 0 ? String(totalRwf) : '';

              const ok = selectedItems.length && totalRwf > 0 && !hasInvalid;
              totalPill.style.display = ok ? 'inline-flex' : 'none';
              totalBaseEl.textContent = ok ? formatBaseMoney(totalBase) : '';
              totalBaseCurEl.textContent = ok ? pkgCurrencyCode : '';
              totalRwfEl.textContent = ok ? totalRwf.toLocaleString() : '';

              amountEquivalentEl.style.display = ok ? 'block' : 'none';
              amountEquivalentEl.textContent = ok
                ? ('≈ ' + formatBaseMoney(totalBase) + ' ' + pkgCurrencyCode + ' (selected total)')
                : '';

              itemsInput.value = ok ? JSON.stringify(selectedItems) : '';
              packageIdInput.value = selectedPackageId ? String(selectedPackageId) : '';

              payBtn.disabled = !(
                selectedStudent &&
                selectedPackageId &&
                selectedItems.length &&
                totalRwf > 0 &&
                phoneInput.value.trim().length &&
                !hasInvalid
              );
            }

            async function selectPackage(packageId) {
              selectedPackageId = packageId ? Number(packageId) : null;
              itemsInput.value = '';
              amountInput.value = '';
              payBtn.disabled = true;
              pkgCurrencyEl.style.display = 'none';
              pkgCurrencyEl.textContent = '';

              if (!selectedStudent || !selectedPackageId) {
                itemsBox.style.display = 'none';
                return;
              }

              itemsBox.style.display = 'block';
              itemsEl.innerHTML = '<div class="micro">Loading items…</div>';

              const details = await loadPackageDetails(selectedStudent.id, selectedPackageId);
              if (!details || !Array.isArray(details.items)) {
                itemsEl.innerHTML = '<div class="micro">No items found.</div>';
                return;
              }

              const pkgCurrency = details.currency || 'RWF';
              pkgCurrencyCode = String(pkgCurrency).toUpperCase();
              amountIsDecimal = pkgCurrencyCode !== 'RWF';
              amountStep = amountIsDecimal ? 0.01 : 1;
              fxRateToRwf = await loadFxRate(pkgCurrencyCode);
              pkgCurrencyEl.style.display = 'block';
              pkgCurrencyEl.textContent = pkgCurrencyCode === 'RWF' ? 'Currency: RWF' : ('Currency: ' + pkgCurrencyCode + ' · Converted to RWF');

              feeItems = details.items;
              itemsEl.innerHTML = '';
              feeItems.forEach((it) => {
                const remaining = Number(it.remaining || 0);
                const disabled = remaining <= 0;
                const remainingRwf = Math.ceil(remaining * fxRateToRwf);
                const remainingBaseDisplay = formatBaseMoney(remaining);
                const maxValue = amountIsDecimal ? clamp2(remaining) : Math.round(remaining);
                const maxValueStr = String(maxValue);

                const row = document.createElement('div');
                row.className = 'item-row';
                row.innerHTML = `
                  <div class="item-left">
                    <label style="display:flex;align-items:flex-start;gap:.6rem;margin:0;cursor:${disabled ? 'not-allowed' : 'pointer'};">
                      <input type="checkbox" id="fee_${it.id}" ${disabled ? 'disabled' : ''} />
                      <div>
                        <div class="item-name">${escapeHtml(it.name || ('Item ' + it.id))}</div>
                        <div class="item-sub">${disabled ? 'Paid' : ('Remaining: ' + remainingBaseDisplay + ' ' + escapeHtml(pkgCurrencyCode))}</div>
                        <div class="item-sub">${disabled ? '' : ('Converted: ≈ ' + remainingRwf.toLocaleString() + ' RWF')}</div>
                      </div>
                    </label>
                  </div>

                  <div class="item-right">
                    <label style="display:block; margin-bottom:.25rem; font-weight:900; font-size:.82rem; color: rgba(15,23,42,.85);">
                      Pay now (${escapeHtml(pkgCurrencyCode)})
                    </label>
                    <input
                      type="number"
                      id="fee_amt_${it.id}"
                      min="0"
                      step="${amountStep}"
                      inputmode="${amountIsDecimal ? 'decimal' : 'numeric'}"
                      value="${maxValueStr}"
                      disabled
                    />
                    <div id="fee_err_${it.id}" class="err"></div>
                    <div id="fee_preview_${it.id}" class="preview"></div>
                  </div>
                `;
                itemsEl.appendChild(row);

                const cb = row.querySelector('input[type="checkbox"]');
                const amtInput = document.getElementById('fee_amt_' + it.id);
                const errEl = document.getElementById('fee_err_' + it.id);
                const previewEl = document.getElementById('fee_preview_' + it.id);

                function showError(msg) {
                  errEl.textContent = msg;
                  errEl.style.display = 'block';
                  previewEl.style.display = 'none';
                }

                function hideError() {
                  errEl.style.display = 'none';
                  errEl.textContent = '';
                }

                function updatePreview() {
                  if (!cb || !cb.checked) {
                    hideError();
                    previewEl.style.display = 'none';
                    return;
                  }
                  const raw = amtInput ? amtInput.value : '';
                  let val = Number(raw);
                  if (!isFinite(val) || val <= 0) {
                    showError('Enter an amount greater than 0.');
                    return;
                  }

                  val = amountIsDecimal ? clamp2(val) : Math.round(val);
                  const max = remaining;
                  if (val > max + 1e-9) {
                    showError('Max: ' + formatBaseMoney(max) + ' ' + pkgCurrencyCode + '.');
                    return;
                  }

                  hideError();
                  const after = max - val;
                  const approxRwf = Math.ceil(val * fxRateToRwf);
                  const afterRwf = Math.ceil(after * fxRateToRwf);
                  previewEl.textContent =
                    'You pay ' + formatBaseMoney(val) + ' ' + pkgCurrencyCode +
                    ' (≈ ' + approxRwf.toLocaleString() + ' RWF) · After: ' +
                    formatBaseMoney(after) + ' ' + pkgCurrencyCode + ' (≈ ' + afterRwf.toLocaleString() + ' RWF)';
                  previewEl.style.display = 'block';
                }

                if (cb) {
                  cb.addEventListener('change', () => {
                    if (!amtInput) return;
                    amtInput.disabled = disabled || !cb.checked;
                    if (cb.checked && (!amtInput.value || Number(amtInput.value) <= 0)) {
                      amtInput.value = maxValueStr;
                    }
                    updatePreview();
                    computeSelection();
                  });
                }

                if (amtInput) {
                  amtInput.addEventListener('input', () => {
                    updatePreview();
                    computeSelection();
                  });
                }
              });

              computeSelection();
            }

            q.addEventListener('input', debounce(async function() {
              const query = q.value.trim();
              if (query.length < 2) {
                clearResults();
                setStudentEmptyHintVisible(false);
                return;
              }
              const list = await searchStudents(query);
              renderResults(list);
              const onSearchTab = panelSearch && panelSearch.classList.contains('active');
              setStudentEmptyHintVisible(onSearchTab && list.length === 0);
            }, 250));

            function isValidEmail(s) {
              return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(s || '').trim());
            }

            async function createStudentFromForm() {
              setNsErr('');
              if (!nsFirst || !nsLast || !nsPhone || !nsEmail) return;

              const payload = {
                first_name: (nsFirst.value || '').trim(),
                last_name: (nsLast.value || '').trim(),
                email: (nsEmail.value || '').trim(),
                phone: (nsPhone.value || '').trim()
              };

              if (!payload.first_name || !payload.last_name) {
                setNsErr('Enter first and last name.');
                return;
              }
              if (!payload.email) {
                setNsErr('Email is required.');
                return;
              }
              if (!isValidEmail(payload.email)) {
                setNsErr('Enter a valid email address.');
                return;
              }
              if (!payload.phone) {
                setNsErr('Enter a MoMo phone number.');
                return;
              }

              nsCreateBtn.disabled = true;
              nsCreateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';

              try {
                const res = await fetch('api/student-create.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify(payload)
                });
                const data = await res.json().catch(() => null);
                if (!data || !data.ok) {
                  setNsErr((data && data.error) ? data.error : 'Could not create student.');
                  return;
                }
                if (data.student) {
                  setStudentMode('search');
                  await selectStudent(data.student);
                  if (packageSel && packageSel.focus) packageSel.focus();
                }
              } catch (e) {
                setNsErr('Network error. Try again.');
              } finally {
                nsCreateBtn.disabled = false;
                nsCreateBtn.innerHTML = '<i class="fas fa-arrow-right"></i> Create & load fee packages';
              }
            }

            if (nsCreateBtn) {
              nsCreateBtn.addEventListener('click', createStudentFromForm);
            }
            [nsFirst, nsLast, nsEmail, nsPhone].forEach(function (el) {
              if (el) el.addEventListener('input', function () { setNsErr(''); });
            });

            document.addEventListener('click', (e) => {
              if (!resultsWrap.contains(e.target) && e.target !== q) clearResults();
            });

            packageSel.addEventListener('change', (e) => selectPackage(e.target.value));
            phoneInput.addEventListener('input', computeSelection);

          })();
        </script>

      <?php else: ?>
        <div class="grid">
          <div class="col">
            <form id="stripe-form" class="form" autocomplete="on">
              <div class="fields">
                <div>
                  <label>Amount (<?= htmlspecialchars($defaultCurrency) ?>)</label>
                  <input id="stripe-amount" type="number" min="1" step="1" inputmode="numeric" required placeholder="20" />
                </div>
                <div>
                  <label>Email (optional)</label>
                  <input id="stripe-email" type="email" autocomplete="email" placeholder="you@email.com" />
                </div>
              </div>

              <!-- Production checkout: no test mode toggle -->

              <div class="mt-1">
                <button id="stripe-pay-btn" class="btn" type="submit">
                  <i class="fas fa-credit-card"></i> Continue
                </button>
              </div>
            </form>

            <div class="mt-1">
              <label>Card Details</label>
              <div id="card-element"></div>
              <div id="stripe-errors" class="hint" style="color:#dc2626;margin-top:.6rem;"></div>
              <div id="stripe-success" class="hint" style="color:#059669;margin-top:.6rem;"></div>
            </div>
          </div>
        </div>

        <script src="https://js.stripe.com/v3/"></script>
        <script>
          (function() {
            const publishableKey = <?= json_encode($stripe['publishable_key'] ?? '') ?>;
            const stripeConfigMissing = !publishableKey;

            const errorsEl = document.getElementById('stripe-errors');
            const successEl = document.getElementById('stripe-success');
            const form = document.getElementById('stripe-form');
            const payBtn = document.getElementById('stripe-pay-btn');

            function setMessage(el, msg) { el.textContent = msg || ''; }

            let stripe = null;
            let elements = null;
            let card = null;

            if (stripeConfigMissing) {
              setMessage(errorsEl, 'STRIPE_PUBLISHABLE_KEY is missing in .env.');
            } else {
              stripe = Stripe(publishableKey);
              elements = stripe.elements();
              card = elements.create('card');
              card.mount('#card-element');
            }

            form.addEventListener('submit', async function(e) {
              e.preventDefault();
              setMessage(errorsEl, '');
              setMessage(successEl, '');

              if (stripeConfigMissing) {
                setMessage(errorsEl, 'Cannot start: STRIPE_PUBLISHABLE_KEY missing.');
                return;
              }

              const amount = document.getElementById('stripe-amount').value;
              const email = document.getElementById('stripe-email').value;

              if (!amount || Number(amount) <= 0) {
                setMessage(errorsEl, 'Enter a valid amount.');
                return;
              }

              payBtn.disabled = true;
              payBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

              try {
                const res = await fetch('stripe/create-intent.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                  body: new URLSearchParams({
                    amount: amount,
                    currency: <?= json_encode($stripe['default_currency'] ?: 'usd') ?>,
                    reference: '',
                    email: email || ''
                  })
                });

                const data = await res.json().catch(() => null);
                if (!data) {
                  setMessage(errorsEl, 'Unexpected response from server.');
                  return;
                }
                if (!data.ok) {
                  setMessage(errorsEl, data.error || 'Stripe error.');
                  return;
                }

                if (!data.client_secret) {
                  setMessage(errorsEl, 'Missing client_secret.');
                  return;
                }

                const billingDetails = email ? { email: email } : {};
                const result = await stripe.confirmCardPayment(data.client_secret, {
                  payment_method: {
                    card: card,
                    billing_details: billingDetails
                  }
                });

                if (result.error) {
                  setMessage(errorsEl, result.error.message || 'Card payment failed.');
                } else {
                  setMessage(successEl, 'Payment confirmed. PaymentIntent status: ' + result.paymentIntent.status);
                }
              } catch (err) {
                setMessage(errorsEl, err && err.message ? err.message : 'Request failed.');
              } finally {
                payBtn.disabled = false;
                payBtn.innerHTML = '<i class="fas fa-credit-card"></i> Pay with Card';
              }
            });
          })();
        </script>
      <?php endif; ?>

    </div>
  </div>
</body>
</html>

