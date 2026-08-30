<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/csrf.php';
require_once __DIR__ . '/helpers/refund_requests_schema.php';

pcvc_ensure_refund_requests_schema($conn);

$pageTitle = 'Request Refund — ScholarSync Global';
$pageDescription = 'Submit a refund request to ScholarSync Global. Search your student record, attach proof of payment, and track your request.';
include 'header.php';

$csrfToken = pcvc_csrf_token();
?>

<style>
.refund-page {
  --pcv-green: #427431;
  --pcv-green-dark: #2f5a26;
  --pcv-green-light: #5a9a48;
  --pcv-red: #E21D1E;
  --pcv-blue: #3661B9;
  --bg: #eef2f7;
  --card: #ffffff;
  --text: #0f172a;
  --muted: #64748b;
  --border: #e2e8f0;
  background: var(--bg);
  padding: 1.5rem 1rem 4rem;
  min-height: 65vh;
  position: relative;
  overflow: hidden;
}
.refund-page::before {
  content: '';
  position: absolute;
  top: -120px; right: -80px;
  width: 320px; height: 320px;
  background: radial-gradient(circle, rgba(66,116,49,0.12) 0%, transparent 70%);
  pointer-events: none;
}
.refund-wrap { max-width: 860px; margin: 0 auto; position: relative; z-index: 1; }

/* Hero */
.refund-hero {
  background: linear-gradient(135deg, var(--pcv-green) 0%, var(--pcv-green-dark) 55%, #1a3320 100%);
  color: #fff;
  border-radius: 24px;
  padding: 2rem 1.75rem 2.25rem;
  margin-bottom: 1.5rem;
  border-bottom: 4px solid var(--pcv-red);
  box-shadow: 0 20px 50px rgba(66, 116, 49, 0.28);
  position: relative;
  overflow: hidden;
}
.refund-hero::after {
  content: '';
  position: absolute;
  bottom: -40px; right: -40px;
  width: 180px; height: 180px;
  border-radius: 50%;
  background: rgba(255,255,255,0.06);
}
.refund-hero-icon {
  width: 52px; height: 52px;
  background: rgba(255,255,255,0.15);
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.4rem;
  margin-bottom: 1rem;
  backdrop-filter: blur(4px);
}
.refund-hero h1 {
  font-size: clamp(1.65rem, 4vw, 2.15rem);
  font-weight: 800;
  margin: 0 0 0.6rem;
  letter-spacing: -0.025em;
  line-height: 1.2;
}
.refund-hero p { margin: 0; opacity: 0.92; font-size: 1rem; line-height: 1.6; max-width: 520px; }
.refund-trust-row {
  display: flex; flex-wrap: wrap; gap: 12px 20px;
  margin-top: 1.25rem; padding-top: 1.25rem;
  border-top: 1px solid rgba(255,255,255,0.2);
  font-size: 0.82rem; opacity: 0.9;
}
.refund-trust-row span { display: inline-flex; align-items: center; gap: 6px; }

/* Stepper */
.refund-stepper {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0;
  margin-bottom: 1.5rem;
  padding: 0 0.5rem;
}
.refund-stepper-item {
  display: flex; flex-direction: column; align-items: center;
  flex: 1; max-width: 120px;
  position: relative;
}
.refund-stepper-item:not(:last-child)::after {
  content: '';
  position: absolute;
  top: 18px; left: calc(50% + 22px);
  width: calc(100% - 44px); height: 3px;
  background: var(--border);
  border-radius: 2px;
  z-index: 0;
}
.refund-stepper-item.done:not(:last-child)::after,
.refund-stepper-item.active:not(:last-child)::after { background: var(--pcv-green); opacity: 0.45; }
.refund-stepper-dot {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: var(--card);
  border: 2px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: 0.85rem;
  color: var(--muted);
  z-index: 1;
  transition: all 0.25s ease;
}
.refund-stepper-item.active .refund-stepper-dot {
  background: var(--pcv-green);
  border-color: var(--pcv-green);
  color: #fff;
  box-shadow: 0 4px 14px rgba(66,116,49,0.35);
}
.refund-stepper-item.done .refund-stepper-dot {
  background: rgba(66,116,49,0.12);
  border-color: var(--pcv-green);
  color: var(--pcv-green);
}
.refund-stepper-label {
  margin-top: 8px;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--muted);
  text-align: center;
}
.refund-stepper-item.active .refund-stepper-label { color: var(--pcv-green); }

/* Cards */
.refund-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 1.5rem 1.25rem;
  margin-bottom: 1.25rem;
  box-shadow: 0 4px 24px rgba(2, 6, 23, 0.05);
  transition: box-shadow 0.25s, border-color 0.25s;
}
.refund-card.highlight {
  border-color: rgba(66, 116, 49, 0.45);
  box-shadow: 0 8px 32px rgba(66, 116, 49, 0.12);
}
@media (min-width: 576px) { .refund-card { padding: 1.75rem 2rem; } }
.refund-step-badge {
  display: inline-flex; align-items: center; gap: 8px;
  font-size: 0.7rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.08em;
  color: var(--pcv-green);
  background: linear-gradient(135deg, rgba(66,116,49,0.1), rgba(54,97,185,0.06));
  border: 1px solid rgba(66, 116, 49, 0.18);
  padding: 6px 14px; border-radius: 999px;
  margin-bottom: 0.85rem;
}
.refund-card h2 { font-size: 1.2rem; font-weight: 800; margin: 0 0 0.35rem; color: var(--text); letter-spacing: -0.02em; }
.refund-card-desc { font-size: 0.88rem; color: var(--muted); margin: 0 0 1.15rem; line-height: 1.5; }

/* Inputs */
.refund-label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--muted); margin-bottom: 6px; }
.refund-field { position: relative; }
.refund-field > i.field-icon {
  position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
  color: var(--muted); font-size: 0.9rem; pointer-events: none;
  transition: color 0.2s;
}
.refund-field:focus-within > i.field-icon { color: var(--pcv-green); }
.refund-input, .refund-select, .refund-textarea {
  width: 100%;
  padding: 13px 14px 13px 42px;
  border: 2px solid var(--border);
  border-radius: 14px;
  font-size: 0.98rem;
  font-family: inherit;
  color: var(--text);
  background: #fafbfc;
  transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}
.refund-input.no-icon, .refund-select { padding-left: 14px; }
.refund-textarea { padding-left: 14px; min-height: 120px; resize: vertical; line-height: 1.55; }
.refund-input:focus, .refund-select:focus, .refund-textarea:focus {
  outline: none;
  border-color: var(--pcv-green);
  background: #fff;
  box-shadow: 0 0 0 4px rgba(66, 116, 49, 0.12);
}
.refund-input.prefilled { background: rgba(66,116,49,0.04); border-color: rgba(66,116,49,0.25); }
.refund-grid { display: grid; gap: 1rem; }
@media (min-width: 576px) { .refund-grid-2 { grid-template-columns: 1fr 1fr; } }

/* Live search */
.refund-search-wrap { position: relative; }
.refund-search-input {
  width: 100%;
  padding: 16px 48px 16px 48px;
  border: 2px solid var(--border);
  border-radius: 16px;
  font-size: 1.02rem;
  font-family: inherit;
  background: #fafbfc;
  transition: all 0.2s;
}
.refund-search-input:focus {
  outline: none;
  border-color: var(--pcv-green);
  background: #fff;
  box-shadow: 0 0 0 4px rgba(66, 116, 49, 0.12);
}
.refund-search-icon {
  position: absolute; left: 18px; top: 50%; transform: translateY(-50%);
  color: var(--pcv-green); font-size: 1rem; pointer-events: none;
}
.refund-search-status {
  position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
  font-size: 0.85rem; color: var(--muted);
}
.refund-search-status.loading { color: var(--pcv-green); }
.refund-live-hint {
  display: flex; align-items: center; gap: 8px;
  margin-top: 10px; font-size: 0.8rem; color: var(--muted);
}
.refund-live-hint i { color: var(--pcv-green); font-size: 0.75rem; }

/* Results dropdown */
.refund-results {
  margin-top: 12px;
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  display: none;
  box-shadow: 0 12px 32px rgba(2,6,23,0.08);
  animation: refundFadeIn 0.25s ease;
}
.refund-results.show { display: block; }
@keyframes refundFadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }
.refund-results-head {
  padding: 10px 16px;
  background: linear-gradient(135deg, rgba(66,116,49,0.08), rgba(54,97,185,0.05));
  font-size: 0.72rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--pcv-green);
  border-bottom: 1px solid var(--border);
}
.refund-result-item {
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
  cursor: pointer;
  transition: background 0.15s;
  display: flex; align-items: center; gap: 14px;
}
.refund-result-item:last-child { border-bottom: none; }
.refund-result-item:hover, .refund-result-item.selected {
  background: linear-gradient(90deg, rgba(66,116,49,0.09), transparent);
}
.refund-result-avatar {
  width: 42px; height: 42px; border-radius: 12px;
  background: linear-gradient(135deg, var(--pcv-green), var(--pcv-green-dark));
  color: #fff; display: flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: 0.85rem; flex-shrink: 0;
}
.refund-result-name { font-weight: 700; font-size: 0.95rem; color: var(--text); }
.refund-result-meta { font-size: 0.8rem; color: var(--muted); margin-top: 2px; }
.refund-result-pick { margin-left: auto; font-size: 0.75rem; font-weight: 700; color: var(--pcv-green); opacity: 0; transition: opacity 0.15s; }
.refund-result-item:hover .refund-result-pick { opacity: 1; }

/* Not found / manual */
.refund-not-found {
  display: none;
  margin-top: 14px;
  padding: 1.25rem 1.35rem;
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
  border: 1px solid #fcd34d;
  border-radius: 16px;
  animation: refundFadeIn 0.3s ease;
}
.refund-not-found.show { display: block; }
.refund-not-found strong { display: flex; align-items: center; gap: 8px; color: #92400e; font-size: 0.95rem; margin-bottom: 8px; }
.refund-not-found p { margin: 0 0 14px; font-size: 0.88rem; color: #78350f; line-height: 1.5; }

.refund-profile {
  display: none;
  margin-top: 14px;
  padding: 1.25rem;
  background: linear-gradient(135deg, rgba(66,116,49,0.07), rgba(54,97,185,0.04));
  border: 1px solid rgba(66, 116, 49, 0.22);
  border-radius: 16px;
  animation: refundFadeIn 0.3s ease;
}
.refund-profile.show { display: block; }
.refund-profile-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.refund-profile-head strong { color: var(--pcv-green); font-size: 0.92rem; display: flex; align-items: center; gap: 8px; }
.refund-profile-grid { display: grid; gap: 12px; font-size: 0.9rem; }
@media (min-width: 576px) { .refund-profile-grid { grid-template-columns: 1fr 1fr; } }
.refund-profile-grid > div {
  background: #fff; padding: 10px 12px; border-radius: 10px;
  border: 1px solid rgba(66,116,49,0.12);
}
.refund-profile-grid span { color: var(--muted); font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; }
.refund-profile-grid strong { display: block; color: var(--text); font-weight: 600; margin-top: 3px; }

/* Buttons */
.refund-btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  padding: 12px 22px; border: none; border-radius: 14px;
  font-weight: 700; font-size: 0.95rem; cursor: pointer; font-family: inherit;
  transition: transform 0.15s, box-shadow 0.2s, background 0.2s;
  text-decoration: none;
}
.refund-btn:active { transform: scale(0.98); }
.refund-btn-primary {
  background: linear-gradient(135deg, var(--pcv-green), var(--pcv-green-dark));
  color: #fff;
  box-shadow: 0 4px 16px rgba(66,116,49,0.3);
}
.refund-btn-primary:hover { box-shadow: 0 8px 24px rgba(66,116,49,0.4); }
.refund-btn-outline { background: #fff; color: var(--pcv-green); border: 2px solid var(--pcv-green); }
.refund-btn-amber {
  background: linear-gradient(135deg, #d97706, #b45309);
  color: #fff;
  box-shadow: 0 4px 14px rgba(217,119,6,0.3);
}
.refund-btn-sm { padding: 8px 14px; font-size: 0.82rem; border-radius: 10px; }
.refund-btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }
.refund-btn-block { width: 100%; }

/* Upload */
.refund-upload-zone {
  border: 2px dashed var(--border);
  border-radius: 16px;
  padding: 2rem 1.5rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  background: linear-gradient(180deg, #fafbfc, #fff);
}
.refund-upload-zone:hover, .refund-upload-zone.dragover {
  border-color: var(--pcv-green);
  background: rgba(66, 116, 49, 0.04);
  transform: translateY(-1px);
}
.refund-upload-zone.has-file {
  border-color: var(--pcv-green);
  border-style: solid;
  background: rgba(66,116,49,0.05);
}
.refund-upload-icon {
  width: 56px; height: 56px; margin: 0 auto 12px;
  background: rgba(66,116,49,0.1); border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: var(--pcv-green);
}
.refund-upload-zone p { margin: 0; font-size: 0.92rem; color: var(--text); font-weight: 600; }
.refund-upload-zone .file-name { margin-top: 10px; font-weight: 700; color: var(--pcv-green); font-size: 0.88rem; }

/* Alerts & success */
.refund-alert { padding: 14px 18px; border-radius: 14px; margin-bottom: 1rem; font-size: 0.92rem; display: none; }
.refund-alert.show { display: block; animation: refundFadeIn 0.25s ease; }
.refund-alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
.refund-alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
.refund-success-panel { display: none; text-align: center; padding: 2.5rem 1.5rem; }
.refund-success-panel.show { display: block; }
.refund-success-ring {
  width: 80px; height: 80px; margin: 0 auto 1.25rem;
  background: rgba(66,116,49,0.1); border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 2.5rem; color: var(--pcv-green);
}
.refund-ref-box {
  display: inline-block; margin: 1rem 0;
  padding: 14px 28px;
  background: linear-gradient(135deg, rgba(66,116,49,0.1), rgba(54,97,185,0.06));
  border: 1px solid rgba(66,116,49,0.2);
  border-radius: 14px;
  font-family: ui-monospace, monospace;
  font-size: 1.15rem; font-weight: 800; color: var(--pcv-green);
  letter-spacing: 0.04em;
}
.refund-form-section.hidden { display: none; }
.refund-hint { font-size: 0.78rem; color: var(--muted); margin-top: 6px; }
.refund-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 1.5rem; }
.refund-submit-bar {
  margin-top: 0.5rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--border);
}
@media (max-width: 575px) {
  .refund-stepper-label { font-size: 0.6rem; }
  .refund-stepper-dot { width: 32px; height: 32px; font-size: 0.78rem; }
}
</style>

<section class="refund-page">
  <div class="refund-wrap">
    <div class="refund-hero">
      <div class="refund-hero-icon"><i class="fas fa-undo-alt" aria-hidden="true"></i></div>
      <h1>Request a Refund</h1>
      <p>Start typing your name or email — we'll find your record instantly. No match? You can still submit with your details below.</p>
      <div class="refund-trust-row">
        <span><i class="fas fa-shield-alt"></i> Secure submission</span>
        <span><i class="fas fa-clock"></i> Team review</span>
        <span><i class="fas fa-file-invoice"></i> Proof of payment required</span>
      </div>
    </div>

    <div class="refund-stepper" aria-hidden="true">
      <div class="refund-stepper-item active" id="stepInd1"><div class="refund-stepper-dot">1</div><span class="refund-stepper-label">Find record</span></div>
      <div class="refund-stepper-item" id="stepInd2"><div class="refund-stepper-dot">2</div><span class="refund-stepper-label">Your info</span></div>
      <div class="refund-stepper-item" id="stepInd3"><div class="refund-stepper-dot">3</div><span class="refund-stepper-label">Refund</span></div>
    </div>

    <div id="refundAlert" class="refund-alert" role="alert"></div>

    <div id="refundSuccessPanel" class="refund-card refund-success-panel">
      <div class="refund-success-ring"><i class="fas fa-check" aria-hidden="true"></i></div>
      <h2 style="font-size:1.5rem;font-weight:800;margin-bottom:0.5rem;">Request submitted!</h2>
      <p class="refund-hint" style="font-size:0.95rem;">Save your reference number for follow-up.</p>
      <div class="refund-ref-box" id="refundRefDisplay">—</div>
      <p style="color:var(--muted);font-size:0.95rem;">We will review your request and contact you by email or phone.</p>
      <div class="refund-actions" style="justify-content:center;margin-top:1.5rem;">
        <a href="student-login.php" class="refund-btn refund-btn-outline"><i class="fas fa-user-graduate"></i> Student login</a>
        <a href="index.php" class="refund-btn refund-btn-primary"><i class="fas fa-home"></i> Back to home</a>
      </div>
    </div>

    <form id="refundForm" class="refund-form-section" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="submitted_by" value="public">
      <input type="hidden" name="student_application_id" id="studentAppId" value="0">
      <input type="hidden" name="is_existing_student" id="isExistingStudent" value="0">

      <!-- Step 1 -->
      <div class="refund-card" id="step1Card">
        <div class="refund-step-badge"><i class="fas fa-search"></i> Step 1 — Find your record</div>
        <h2>Live search</h2>
        <p class="refund-card-desc">Type at least 2 characters — results appear as you type. Select your name to autofill, or continue manually if you're not listed.</p>

        <div class="refund-search-wrap">
          <i class="fas fa-search refund-search-icon" aria-hidden="true"></i>
          <input type="text" id="searchQuery" class="refund-search-input" placeholder="Your full name or email address…" autocomplete="off" aria-label="Search student name or email" aria-autocomplete="list" aria-controls="searchResults">
          <span class="refund-search-status" id="searchStatus" aria-live="polite"></span>
        </div>
        <div class="refund-live-hint"><i class="fas fa-bolt"></i> Live search — no button needed</div>

        <div id="searchResults" class="refund-results" role="listbox" aria-label="Search results"></div>

        <div id="notFoundMsg" class="refund-not-found">
          <strong><i class="fas fa-user-plus"></i> No matching record found</strong>
          <p>That's okay — you can still request a refund. We'll use the details you enter in Step 2. Your request will be reviewed like any other.</p>
          <button type="button" class="refund-btn refund-btn-amber refund-btn-sm" id="manualEntryBtn">
            <i class="fas fa-pen"></i> Continue with my details
          </button>
        </div>

        <div id="selectedProfile" class="refund-profile">
          <div class="refund-profile-head">
            <strong><i class="fas fa-user-check"></i> Record matched</strong>
            <button type="button" class="refund-btn refund-btn-outline refund-btn-sm" id="clearSelection">Change</button>
          </div>
          <div class="refund-profile-grid" id="profileGrid"></div>
        </div>
      </div>

      <!-- Step 2 -->
      <div class="refund-card" id="step2Card">
        <div class="refund-step-badge"><i class="fas fa-user"></i> Step 2 — Your information</div>
        <h2>Contact details</h2>
        <p class="refund-card-desc" id="step2Desc">Fill in or confirm your information so we can reach you about your refund.</p>
        <div class="refund-grid refund-grid-2">
          <div class="refund-field">
            <i class="fas fa-user field-icon" aria-hidden="true"></i>
            <label class="refund-label" for="firstName">First name *</label>
            <input type="text" class="refund-input" id="firstName" name="first_name" required maxlength="100" placeholder="Jean">
          </div>
          <div class="refund-field">
            <i class="fas fa-user field-icon" aria-hidden="true"></i>
            <label class="refund-label" for="lastName">Last name *</label>
            <input type="text" class="refund-input" id="lastName" name="last_name" required maxlength="100" placeholder="Mukamana">
          </div>
          <div class="refund-field">
            <i class="fas fa-envelope field-icon" aria-hidden="true"></i>
            <label class="refund-label" for="email">Email *</label>
            <input type="email" class="refund-input" id="email" name="email" required maxlength="255" placeholder="you@example.com">
          </div>
          <div class="refund-field">
            <i class="fas fa-phone field-icon" aria-hidden="true"></i>
            <label class="refund-label" for="phone">Phone</label>
            <input type="tel" class="refund-input" id="phone" name="phone" maxlength="64" placeholder="+250 788 000 000">
          </div>
        </div>
        <input type="hidden" id="applicationId" name="application_id" value="">
      </div>

      <!-- Step 3 -->
      <div class="refund-card" id="step3Card">
        <div class="refund-step-badge"><i class="fas fa-file-invoice-dollar"></i> Step 3 — Refund details</div>
        <h2>Payment &amp; reason</h2>
        <p class="refund-card-desc">Tell us what you paid for, how much, and attach your proof of payment.</p>
        <div class="refund-grid">
          <div class="refund-field">
            <i class="fas fa-briefcase field-icon" aria-hidden="true"></i>
            <label class="refund-label" for="servicePaid">Service paid for *</label>
            <input type="text" class="refund-input" id="servicePaid" name="service_paid_for" required maxlength="255" placeholder="e.g. Application fee, Visa consultation">
          </div>
          <div class="refund-grid refund-grid-2">
            <div class="refund-field">
              <i class="fas fa-dollar-sign field-icon" aria-hidden="true"></i>
              <label class="refund-label" for="amount">Amount paid *</label>
              <input type="number" class="refund-input" id="amount" name="amount" required min="0.01" step="0.01" placeholder="0.00">
            </div>
            <div>
              <label class="refund-label" for="currency">Currency</label>
              <select class="refund-select no-icon" id="currency" name="currency">
                <option value="USD">USD — US Dollar</option>
                <option value="RWF">RWF — Rwandan Franc</option>
                <option value="CAD">CAD — Canadian Dollar</option>
                <option value="EUR">EUR — Euro</option>
              </select>
            </div>
          </div>
          <div>
            <label class="refund-label" for="reason">Reason for refund *</label>
            <textarea class="refund-textarea" id="reason" name="reason" required maxlength="5000" placeholder="Please explain why you are requesting a refund…"></textarea>
          </div>
          <div>
            <label class="refund-label">Proof of payment *</label>
            <div class="refund-upload-zone" id="uploadZone" tabindex="0" role="button" aria-label="Upload proof of payment">
              <div class="refund-upload-icon"><i class="fas fa-cloud-upload-alt" aria-hidden="true"></i></div>
              <p>Drag &amp; drop or tap to upload</p>
              <p class="refund-hint">PDF, JPG, PNG or WEBP — max 8 MB</p>
              <div class="file-name" id="fileName"></div>
            </div>
            <input type="file" id="paymentProof" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif" hidden required>
          </div>
        </div>
        <div class="refund-submit-bar">
          <button type="submit" class="refund-btn refund-btn-primary refund-btn-block" id="submitBtn">
            <i class="fas fa-paper-plane"></i> Submit refund request
          </button>
        </div>
      </div>
    </form>
  </div>
</section>

<script>
(function () {
  const searchQuery = document.getElementById('searchQuery');
  const searchStatus = document.getElementById('searchStatus');
  const searchResults = document.getElementById('searchResults');
  const notFoundMsg = document.getElementById('notFoundMsg');
  const selectedProfile = document.getElementById('selectedProfile');
  const profileGrid = document.getElementById('profileGrid');
  const clearSelection = document.getElementById('clearSelection');
  const manualEntryBtn = document.getElementById('manualEntryBtn');
  const studentAppId = document.getElementById('studentAppId');
  const isExistingStudent = document.getElementById('isExistingStudent');
  const step2Card = document.getElementById('step2Card');
  const step2Desc = document.getElementById('step2Desc');
  const refundForm = document.getElementById('refundForm');
  const refundAlert = document.getElementById('refundAlert');
  const refundSuccessPanel = document.getElementById('refundSuccessPanel');
  const refundRefDisplay = document.getElementById('refundRefDisplay');
  const uploadZone = document.getElementById('uploadZone');
  const paymentProof = document.getElementById('paymentProof');
  const fileName = document.getElementById('fileName');
  const submitBtn = document.getElementById('submitBtn');
  const stepInd1 = document.getElementById('stepInd1');
  const stepInd2 = document.getElementById('stepInd2');
  const stepInd3 = document.getElementById('stepInd3');

  let selectedStudent = null;
  let lastSearchResults = [];
  let searchTimer = null;
  let lastFetchId = 0;
  let manualMode = false;

  function showAlert(msg, type) {
    refundAlert.textContent = msg;
    refundAlert.className = 'refund-alert show refund-alert-' + (type || 'error');
    refundAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  function hideAlert() { refundAlert.className = 'refund-alert'; }

  function setStepper(step) {
    [stepInd1, stepInd2, stepInd3].forEach(function (el, i) {
      el.classList.remove('active', 'done');
      if (i + 1 < step) el.classList.add('done');
      else if (i + 1 === step) el.classList.add('active');
    });
  }

  function initials(name) {
    const p = (name || '').trim().split(/\s+/);
    return ((p[0] || '?')[0] + (p[1] || '')[0]).toUpperCase() || '?';
  }

  function esc(t) {
    const d = document.createElement('div');
    d.textContent = t || '';
    return d.innerHTML;
  }

  function markPrefilled(el, on) {
    if (!el) return;
    el.classList.toggle('prefilled', !!on);
  }

  function fillProfile(s) {
    selectedStudent = s;
    manualMode = false;
    document.getElementById('firstName').value = s.first_name || '';
    document.getElementById('lastName').value = s.last_name || '';
    document.getElementById('email').value = s.email || '';
    document.getElementById('phone').value = s.phone || '';
    document.getElementById('applicationId').value = s.application_id || '';
    studentAppId.value = s.id || 0;
    isExistingStudent.value = '1';
    ['firstName','lastName','email','phone'].forEach(function (id) { markPrefilled(document.getElementById(id), true); });
    profileGrid.innerHTML =
      '<div><span>Name</span><strong>' + esc(s.full_name) + '</strong></div>' +
      '<div><span>Email</span><strong>' + esc(s.email) + '</strong></div>' +
      '<div><span>Phone</span><strong>' + esc(s.phone || '—') + '</strong></div>' +
      '<div><span>Application ID</span><strong>' + esc(s.application_id || '—') + '</strong></div>';
    selectedProfile.classList.add('show');
    notFoundMsg.classList.remove('show');
    searchResults.classList.remove('show');
    step2Card.classList.remove('highlight');
    step2Desc.textContent = 'Your details were filled from our records. Confirm or edit if needed.';
    setStepper(2);
  }

  function clearProfile() {
    selectedStudent = null;
    studentAppId.value = '0';
    isExistingStudent.value = manualMode ? '0' : '0';
    selectedProfile.classList.remove('show');
    profileGrid.innerHTML = '';
    ['firstName','lastName','email','phone'].forEach(function (id) { markPrefilled(document.getElementById(id), false); });
  }

  function guessFromQuery(q) {
    q = (q || '').trim();
    if (!q) return;
    if (q.indexOf('@') !== -1) {
      document.getElementById('email').value = q;
      markPrefilled(document.getElementById('email'), true);
      return;
    }
    const parts = q.split(/\s+/).filter(Boolean);
    if (parts.length >= 1 && !document.getElementById('firstName').value) {
      document.getElementById('firstName').value = parts[0];
      markPrefilled(document.getElementById('firstName'), true);
    }
    if (parts.length >= 2 && !document.getElementById('lastName').value) {
      document.getElementById('lastName').value = parts.slice(1).join(' ');
      markPrefilled(document.getElementById('lastName'), true);
    }
  }

  function enableManualEntry(scroll) {
    manualMode = true;
    isExistingStudent.value = '0';
    studentAppId.value = '0';
    document.getElementById('applicationId').value = '';
    clearProfile();
    guessFromQuery(searchQuery.value);
    notFoundMsg.classList.remove('show');
    searchResults.classList.remove('show');
    step2Card.classList.add('highlight');
    step2Desc.textContent = 'Enter your details below — no existing record is required to submit a refund request.';
    setStepper(2);
    if (scroll !== false) {
      step2Card.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(function () { document.getElementById('firstName').focus(); }, 400);
    }
  }

  function renderResults(results) {
    if (!results.length) {
      searchResults.classList.remove('show');
      notFoundMsg.classList.add('show');
      guessFromQuery(searchQuery.value);
      return;
    }
    notFoundMsg.classList.remove('show');
    searchResults.innerHTML =
      '<div class="refund-results-head"><i class="fas fa-users"></i> ' + results.length + ' match' + (results.length > 1 ? 'es' : '') + ' — tap to select</div>' +
      results.map(function (r, idx) {
        return '<div class="refund-result-item" data-idx="' + idx + '" role="option" tabindex="0">' +
          '<div class="refund-result-avatar">' + esc(initials(r.full_name)) + '</div>' +
          '<div><div class="refund-result-name">' + esc(r.full_name) + '</div>' +
          '<div class="refund-result-meta">' + esc(r.email) + (r.application_id ? ' · ' + esc(r.application_id) : '') + '</div></div>' +
          '<span class="refund-result-pick">Select <i class="fas fa-chevron-right"></i></span></div>';
      }).join('');
    searchResults.classList.add('show');
    searchResults.querySelectorAll('.refund-result-item').forEach(function (el) {
      function pick() {
        const idx = parseInt(el.getAttribute('data-idx'), 10);
        const s = lastSearchResults[idx];
        if (s) {
          fillProfile(s);
          step2Card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
      el.addEventListener('click', pick);
      el.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(); }
      });
    });
  }

  async function doSearch(q) {
    q = (q || '').trim();
    if (q.length < 2) {
      searchStatus.textContent = '';
      searchResults.classList.remove('show');
      notFoundMsg.classList.remove('show');
      return;
    }
    const fetchId = ++lastFetchId;
    searchStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    searchStatus.classList.add('loading');
    try {
      const res = await fetch('api/refund-student-lookup.php?q=' + encodeURIComponent(q));
      const data = await res.json();
      if (fetchId !== lastFetchId) return;
      if (!data.ok) {
        searchStatus.textContent = '';
        searchStatus.classList.remove('loading');
        return;
      }
      lastSearchResults = data.results || [];
      searchStatus.classList.remove('loading');
      searchStatus.textContent = lastSearchResults.length ? lastSearchResults.length + ' found' : 'None';
      if (!selectedStudent) renderResults(lastSearchResults);
    } catch (e) {
      if (fetchId === lastFetchId) {
        searchStatus.textContent = '';
        searchStatus.classList.remove('loading');
      }
    }
  }

  function onSearchInput() {
    clearTimeout(searchTimer);
    const q = searchQuery.value.trim();
    if (selectedStudent) {
      clearSelection.click();
    }
    manualMode = false;
    step2Card.classList.remove('highlight');
    if (q.length < 2) {
      searchStatus.textContent = q.length ? 'Type more…' : '';
      searchResults.classList.remove('show');
      notFoundMsg.classList.remove('show');
      return;
    }
    searchStatus.textContent = 'Searching…';
    searchTimer = setTimeout(function () { doSearch(q); }, 320);
  }

  searchQuery.addEventListener('input', onSearchInput);
  searchQuery.addEventListener('focus', function () { setStepper(1); });

  clearSelection.addEventListener('click', function () {
    clearProfile();
    manualMode = false;
    step2Card.classList.remove('highlight');
    step2Desc.textContent = 'Fill in or confirm your information so we can reach you about your refund.';
    isExistingStudent.value = '0';
    searchQuery.focus();
    onSearchInput();
  });

  manualEntryBtn.addEventListener('click', function () { enableManualEntry(true); });

  ['firstName','lastName','email','phone','servicePaid','amount','reason'].forEach(function (id) {
    const el = document.getElementById(id);
    if (el) el.addEventListener('focus', function () {
      if (id === 'servicePaid' || id === 'amount' || id === 'reason') setStepper(3);
      else setStepper(2);
    });
  });

  uploadZone.addEventListener('click', function () { paymentProof.click(); });
  uploadZone.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); paymentProof.click(); }
  });
  paymentProof.addEventListener('change', function () {
    const f = paymentProof.files[0];
    fileName.textContent = f ? f.name : '';
    uploadZone.classList.toggle('has-file', !!f);
  });
  uploadZone.addEventListener('dragover', function (e) { e.preventDefault(); uploadZone.classList.add('dragover'); });
  uploadZone.addEventListener('dragleave', function () { uploadZone.classList.remove('dragover'); });
  uploadZone.addEventListener('drop', function (e) {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
      paymentProof.files = e.dataTransfer.files;
      fileName.textContent = e.dataTransfer.files[0].name;
      uploadZone.classList.add('has-file');
    }
  });

  refundForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    hideAlert();
    if (!paymentProof.files.length) {
      showAlert('Please attach proof of payment.');
      uploadZone.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
    try {
      const fd = new FormData(refundForm);
      const res = await fetch('save_refund_request.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (!data.ok) {
        showAlert(data.error || 'Submission failed.');
        return;
      }
      refundForm.classList.add('hidden');
      document.querySelector('.refund-stepper').style.display = 'none';
      refundRefDisplay.textContent = data.reference_id || '—';
      refundSuccessPanel.classList.add('show');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (err) {
      showAlert('Network error. Please try again.');
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit refund request';
    }
  });
})();
</script>

<?php include 'footer.php'; ?>
