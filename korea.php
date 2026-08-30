<?php
session_start();
$_SESSION['user_id'] ??= 'user_' . bin2hex(random_bytes(6));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Student Application Form</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

<style>
/* =====================================================
   GLOBAL RESET & BASE
===================================================== */
* {
  box-sizing: border-box;
}

body {
  background-color: #f5f7fb;
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
  color: #212529;
}

/* =====================================================
   CARD & LAYOUT
===================================================== */
.card {
  border-radius: 14px;
  border: none;
}

.card-body {
  padding: 2rem;
}

/* =====================================================
   STEP VISIBILITY
===================================================== */
.step {
  display: none;
}

.step.active {
  display: block;
}

/* =====================================================
   PROGRESS BAR (CLEAN & MODERN)
===================================================== */
.progress-step {
  display: flex;
  gap: 8px;
  margin-bottom: 1.75rem;
}

.progress-step span {
  flex: 1;
  height: 6px;
  background: #dee2e6;
  border-radius: 999px;
  transition: background-color .3s ease;
}

.progress-step span.active {
  background: linear-gradient(90deg, #0d6efd, #4f8cff);
}

/* =====================================================
   LABELS
===================================================== */
.form-label {
  font-weight: 600;
  margin-bottom: 6px;
  font-size: 14px;
  color: #343a40;
}

/* =====================================================
   INPUTS & SELECTS (BASE)
===================================================== */
.form-control,
.form-select {
  min-height: 48px;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid #dfe3eb;
  background-color: #fff;
  font-size: 14px;
  transition: border-color .2s ease, box-shadow .2s ease;
}

.form-control::placeholder {
  color: #adb5bd;
}

.form-control:focus,
.form-select:focus {
  border-color: #0d6efd;
  box-shadow: 0 0 0 3px rgba(13,110,253,.15);
  outline: none;
}

/* Disabled */
.form-control:disabled,
.form-select:disabled {
  background-color: #f1f3f6;
  color: #6c757d;
  cursor: not-allowed;
}

/* =====================================================
   SELECT2 – CORE FIX (NO MORE CUT EDGES)
===================================================== */
.select2-container {
  width: 100% !important;
}

/* Main selection */
.select2-container--bootstrap-5 .select2-selection {
  min-height: 48px;
  padding: 6px 10px;
  border-radius: 10px;
  border: 1px solid #dfe3eb;
  display: flex;
  align-items: center;
  background-color: #fff;
}

/* Placeholder text */
.select2-container--bootstrap-5 .select2-selection__placeholder {
  color: #adb5bd;
  font-size: 14px;
}

/* Focus state */
.select2-container--bootstrap-5.select2-container--focus .select2-selection {
  border-color: #0d6efd;
  box-shadow: 0 0 0 3px rgba(13,110,253,.15);
}

/* Disabled Select2 */
.select2-container--bootstrap-5.select2-container--disabled .select2-selection {
  background-color: #f1f3f6;
  color: #6c757d;
}

/* =====================================================
   SELECT2 – MULTI SELECT (PROGRAMS FIX)
===================================================== */
.select2-container--bootstrap-5 .select2-selection--multiple {
  padding: 6px 8px;
  gap: 6px;
  align-items: center;
}

/* Selected chips */
.select2-container--bootstrap-5 .select2-selection__choice {
  background: linear-gradient(135deg, #0d6efd, #4f8cff);
  color: #fff;
  border: none;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 12px;
  display: flex;
  align-items: center;
}

/* Remove "x" spacing issue */
.select2-selection__choice__remove {
  margin-right: 6px;
  color: #fff;
  opacity: .8;
}

.select2-selection__choice__remove:hover {
  opacity: 1;
}

/* =====================================================
   SELECT2 DROPDOWN (CLEAN & ELEGANT)
===================================================== */
.select2-container--bootstrap-5 .select2-dropdown {
  border-radius: 12px;
  border: 1px solid #dfe3eb;
  box-shadow: 0 10px 30px rgba(0,0,0,.08);
  overflow: hidden;
}

/* Options list */
.select2-container--bootstrap-5 .select2-results__options {
  max-height: 240px;
  overflow-y: auto;
}

/* Option */
.select2-container--bootstrap-5 .select2-results__option {
  padding: 12px 16px;
  font-size: 14px;
  cursor: pointer;
}

/* Hover */
.select2-container--bootstrap-5 .select2-results__option--highlighted {
  background-color: #0d6efd;
  color: #fff;
}

/* =====================================================
   BUTTONS
===================================================== */
.btn {
  border-radius: 10px;
  padding: 10px 18px;
  font-weight: 600;
}

.btn-primary {
  background: linear-gradient(135deg, #0d6efd, #4f8cff);
  border: none;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #0b5ed7, #3f7be0);
}

.btn-secondary {
  background-color: #e9ecef;
  border: none;
  color: #343a40;
}

/* =====================================================
   FILE INPUTS
===================================================== */
.upload {
  border-radius: 10px;
}

/* =====================================================
   SMALL SCREENS
===================================================== */
@media (max-width: 768px) {
  .card-body {
    padding: 1.25rem;
  }
}
/* =====================================================
   FINAL FIX – SELECT2 PROGRAMS MULTI-SELECT
   (Fixes broken height, chips, cursor, overflow)
===================================================== */

/* Stop flex breaking the layout */
.select2-container--bootstrap-5 .select2-selection--multiple {
  display: block !important;
  min-height: 48px;
  padding: 8px 12px;
  line-height: 1.4;
  overflow: hidden;
}

/* Proper wrapping for selected items */
.select2-container--bootstrap-5
.select2-selection--multiple
.select2-selection__rendered {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  padding: 0;
  margin: 0;
}

/* Selected program chips */
.select2-container--bootstrap-5
.select2-selection__choice {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  font-size: 12px;
  border-radius: 999px;
  white-space: nowrap;
}

/* Remove button alignment */
.select2-container--bootstrap-5
.select2-selection__choice__remove {
  margin-right: 6px;
  font-weight: 600;
}

/* Inline search input FIX (this was the big problem) */
.select2-container--bootstrap-5
.select2-search--inline
.select2-search__field {
  min-width: 120px;
  height: 32px;
  margin: 0;
  padding: 0;
  line-height: 32px;
  border: none !important;
  outline: none;
  box-shadow: none !important;
}

/* Prevent giant height when many programs */
.select2-container--bootstrap-5
.select2-selection--multiple {
  max-height: 120px;
  overflow-y: auto;
}
/* =====================================================
   SMART ROUNDED FILE UPLOAD PROGRESS
===================================================== */

.upload-progress {
  width: 100%;
  height: 12px;
  background: #e9ecef;
  border-radius: 999px;
  overflow: hidden;
  display: none;
}

.upload-bar {
  height: 100%;
  width: 0%;
  background: linear-gradient(90deg, #0d6efd, #4f8cff);
  border-radius: 999px;
  transition: width .35s ease;
  position: relative;
}

.upload-bar span {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 10px;
  font-weight: 600;
  color: #fff;
  opacity: 0;
  transition: opacity .3s ease;
}

/* Show percentage when progress starts */
.upload-progress.active .upload-bar span {
  opacity: 1;
}
/* =====================================================
   UI DEPTH, CONTAINERS & VISUAL HIERARCHY (PRODUCTION)
   Paste at the END of your <style>
===================================================== */

/* Page background – soft, non-flat */
body {
  background: linear-gradient(180deg, #f3f6fb 0%, #eef2f7 100%);
}

/* Main application container (card) */
.card {
  background: #ffffff;
  border-radius: 18px;
  border: 1px solid #e6ebf2;
  box-shadow:
    0 10px 28px rgba(0, 0, 0, 0.04),
    0 4px 10px rgba(0, 0, 0, 0.025);
}

/* Inner spacing consistency */
.card-body {
  padding: 2rem;
}
/* =====================================================
   STEP CONTAINER – STRONG VISUAL SEPARATION
===================================================== */

.step-section {
  position: relative;
  background: #ffffff;
  border-radius: 18px;
  padding: 2.25rem;
  margin-bottom: 2rem;

  border: 1px solid #e2e8f0;

  box-shadow:
    0 12px 28px rgba(15, 23, 42, 0.08),
    0 4px 10px rgba(15, 23, 42, 0.04);
}

/* Accent bar on the left (PRO LOOK) */
.step-section::before {
  content: "";
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 6px;
  background: linear-gradient(180deg, #0d6efd, #4f8cff);
  border-radius: 18px 0 0 18px;
}

/* Step titles */
.step-section h5 {
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
}

/* Step description */
.step-section p {
  font-size: 13px;
  color: #64748b;
}

/* Form fields – subtle contrast improvement */
.form-control,
.form-select {
  background-color: #ffffff;
  border: 1px solid #dbe2ea;
}

/* Hover feedback */
.form-control:hover,
.form-select:hover {
  border-color: #c7d2e2;
}

/* Navigation separator (Back / Next area) */
.form-nav {
  border-top: 1px solid #edf1f7;
  padding-top: 1.25rem;
  margin-top: 2rem;
}

/* Mobile polish */
@media (max-width: 768px) {
  .container {
    padding-left: 12px;
    padding-right: 12px;
  }

  .card {
    border-radius: 14px;
  }

  .card-body {
    padding: 1.25rem;
  }
.card {
  background: #f8fafc;
}

  
}
/* =====================================================
   STUDY SELECTION – MULTI UNIVERSITY UI
===================================================== */

.study-choices {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

/* Empty state */
.study-empty {
  border: 1px dashed #c7d2e2;
  border-radius: 14px;
  padding: 1.75rem;
  text-align: center;
  color: #64748b;
  background: #f8fafc;
}

/* Study choice card */
.study-choice {
  border-radius: 16px;
  padding: 1.25rem 1.5rem;
  background: #ffffff;
  border: 1px solid #e2e8f0;

  box-shadow:
    0 10px 20px rgba(15, 23, 42, 0.06),
    0 3px 8px rgba(15, 23, 42, 0.04);

  position: relative;
}

/* Header row */
.study-choice-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

/* Region badge */
.region-badge {
  font-size: 12px;
  padding: 6px 10px;
  border-radius: 999px;
  background: linear-gradient(135deg, #0d6efd, #4f8cff);
}

/* Remove button */
.btn-remove {
  background: transparent;
  border: none;
  color: #dc3545;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
}

.btn-remove:hover {
  text-decoration: underline;
}

/* Select spacing consistency */
.study-choice .form-select {
  min-height: 46px;
}

/* Mobile polish */
@media (max-width: 768px) {
  .study-choice {
    padding: 1.1rem;
  }
}
/* ================================
   STUDY SELECTION – PRO UI
================================ */

.study-choices {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.study-choice {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow:
    0 6px 16px rgba(15, 23, 42, 0.05),
    0 2px 6px rgba(15, 23, 42, 0.03);
}

.study-choice-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.region-badge {
  background: linear-gradient(135deg, #2563eb, #4f46e5);
  font-size: 12px;
  font-weight: 600;
  padding: 6px 12px;
  border-radius: 999px;
}

.btn-remove {
  background: none;
  border: none;
  color: #ef4444;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-remove:hover {
  text-decoration: underline;
}

.study-empty {
  padding: 1.5rem;
  border: 1px dashed #cbd5e1;
  border-radius: 14px;
  text-align: center;
  color: #64748b;
  background: #f8fafc;
}
/* ===============================
   REGION CHIPS – SMART CLOSE
================================ */

.region-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: linear-gradient(135deg, #0d6efd, #4f8cff);
  color: #fff;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}

.region-close {
  cursor: pointer;
  font-size: 14px;
  line-height: 1;
  opacity: 0.85;
}

.region-close:hover {
  opacity: 1;
}

#agent_first_name[readonly],
#agent_last_name[readonly],
#agent_email[readonly] {
    background-color: #f1f3f6;
    cursor: not-allowed;
}

/* =====================================================
   DOCUMENT DROPZONE (STEP 7)
===================================================== */

.doc-dropzone {
  position: relative;
  border: 2px dashed #d1d5db;
  border-radius: 16px;
  padding: 1.4rem;
  background: #f8fafc;
  text-align: center;
  cursor: pointer;
  transition: all .25s ease;
}

.doc-dropzone.multi {
  border-color: #6366f1;
  background: #eef2ff;
}

.doc-dropzone:hover {
  background: #eef2ff;
}

.doc-dropzone.dragover {
  background: #e0e7ff;
  border-color: #4f46e5;
  box-shadow: 0 0 0 4px rgba(99,102,241,.18);
}

.doc-dropzone input[type="file"] {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

.dz-content strong {
  display: block;
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
}

.dz-content span {
  font-size: 12px;
  color: #64748b;
}

/* File preview chips */
.dz-files {
  list-style: none;
  padding: 0;
  margin-top: 10px;
}

.dz-files li {
  display: inline-block;
  margin: 4px 6px 0 0;
  padding: 4px 10px;
  font-size: 12px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  color: #334155;
}
/* ===============================
   GLOBAL UPLOAD PROGRESS (VISIBLE)
================================ */
#docProgressWrap {
  position: sticky;
  bottom: 20px;
  z-index: 9999;
  max-width: 680px;
  margin: 12px auto 0;
  box-shadow: 0 6px 16px rgba(0,0,0,.08);
}

</style>
<!-- ✅ Mobile-only overrides MUST be last -->
<link rel="stylesheet" href="mobile-study-selection.css">
</head>

<body >
<div class="container my-5">
<div class="card shadow-sm">
<div class="card-body">

<h4 class="fw-semibold mb-3">Student Application Form</h4>

<!-- ===============================
     STEP PROGRESS
=============================== -->
<div class="progress-step mb-4">
  <span class="active"></span>
  <span></span>
  <span></span>
  <span></span>
</div>

<form id="applicationForm" enctype="multipart/form-data">
<input type="hidden" name="user_id" value="<?=htmlspecialchars($_SESSION['user_id'])?>">

<!-- =====================================================
 STEP 1 : PERSONAL INFORMATION (FULLY VALIDATED – NO SKIPS)
===================================================== -->
<div class="step">

  <div class="step-section">

    <!-- ================= STEP HEADER ================= -->
    <div class="mb-4">
      <h5 class="fw-semibold mb-1">Personal Information</h5>
      <p class="text-muted small mb-0">
        Enter details exactly as shown on your passport.
      </p>
    </div>

    <!-- ================= PERSONAL DETAILS ================= -->
    <div class="row">

      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="first_name"
          placeholder="First Name"
          required
        >
      </div>

      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="last_name"
          placeholder="Last Name"
          required
        >
      </div>

      <!-- Email -->
      <div class="col-md-6 mb-3">
        <input
          type="email"
          class="form-control"
          name="email"
          placeholder="Email"
          required
        >
      </div>

      <!-- International Phone -->
      <div class="col-md-6 mb-3">
        <label class="form-label fw-semibold">Phone Number</label>

        <!-- Visible phone input -->
        <input
          type="tel"
          id="intl_phone"
          class="form-control"
          placeholder="Enter phone number"
          required
        >

        <!-- Hidden fields (BACKEND SAFE) -->
        <input
          type="hidden"
          name="area_code"
          id="area_code"
          required
        >
        <input
          type="hidden"
          name="phone_number"
          id="phone_number"
          required
        >

        <div class="form-text">
          Select country to auto-fill international code.
        </div>
      </div>

      <!-- Gender -->
      <div class="col-md-6 mb-3">
        <select
          class="form-select"
          name="gender"
          required
        >
          <option value="">Gender</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
        </select>
      </div>

      <!-- Date of Birth -->
      <div class="col-md-6 mb-3">
        <input
          type="date"
          class="form-control"
          name="dob"
          placeholder="Select date of birth"
          required
        >
      </div>

    </div>

    <!-- ================= IDENTITY & NATIONALITY ================= -->
    <div class="row">

      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="passport_number"
          placeholder="Passport Number"
          required
        >
      </div>

      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="student_national_id"
          placeholder="National ID"
          required
        >
      </div>

      <div class="col-md-4 mb-3">
        <select
          class="form-select country-select"
          name="country_of_birth"
          data-placeholder="Country of birth"
          required
        >
          <option value="">Select Country</option>
        </select>
      </div>

      <div class="col-md-4 mb-3">
        <input
          type="text"
          class="form-control"
          name="city_of_birth"
          placeholder="City of Birth"
          required
        >
      </div>

      <div class="col-md-4 mb-3">
        <select
          class="form-select country-select"
          name="nationality"
          data-placeholder="Nationality"
          required
        >
          <option value="">Select Nationality</option>
        </select>
      </div>

      <div class="col-md-6 mb-3">
        <select
          class="form-select country-select"
          name="second_nationality"
          data-placeholder="Second nationality"
          required
        >
          <option value="">Select Second Nationality</option>
        </select>
      </div>

    </div>

  </div>
</div>

<!-- =====================================================
 STEP 2 : ADDRESS & FAMILY (FULLY VALIDATED – NO SKIPS)
===================================================== -->
<div class="step">

  <div class="step-section">

    <!-- ================= HEADER ================= -->
    <div class="mb-4">
      <h5 class="fw-semibold mb-1">Address & Family</h5>
      <p class="text-muted small mb-0">
        Provide your current address and parent information.
      </p>
    </div>

    <!-- ================= ADDRESS ================= -->

    <input
      type="text"
      class="form-control mb-3"
      name="address_line1"
      placeholder="Address Line 1"
      required
    >

    <input
      type="text"
      class="form-control mb-3"
      name="address_line2"
      placeholder="Address Line 2 (optional)"
      autocomplete="address-line2"
    >

    <div class="row">

      <div class="col-md-4 mb-3">
        <input
          type="text"
          class="form-control"
          name="city"
          placeholder="City"
          required
        >
      </div>

      <div class="col-md-4 mb-3">
        <input
          type="text"
          class="form-control"
          name="state_province"
          placeholder="State / Province"
          required
        >
      </div>

      <div class="col-md-4 mb-3">
        <input
          type="text"
          class="form-control"
          name="postal_code"
          placeholder="Postal Code"
          required
        >
      </div>

    </div>

    <!-- ================= PARENTS ================= -->

    <h6 class="fw-semibold mt-4 mb-3">Parents Information</h6>

    <div class="row">

      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="father_first_name"
          placeholder="Father First Name"
          required
        >
      </div>

      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="father_last_name"
          placeholder="Father Last Name"
          required
        >
      </div>

      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="mother_first_name"
          placeholder="Mother First Name"
          required
        >
      </div>

      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="mother_last_name"
          placeholder="Mother Last Name"
          required
        >
      </div>

    </div>

  </div>
</div>

<!-- =====================================================
 STEP 3 : EMERGENCY CONTACT (FULLY VALIDATED – NO SKIPS)
===================================================== -->
<div class="step">

  <div class="step-section">

    <!-- ================= HEADER ================= -->
    <div class="mb-4">
      <h5 class="fw-semibold mb-1">Emergency Contact</h5>
      <p class="text-muted small mb-0">
        Provide details of a person we can contact in case of emergency.
      </p>
    </div>

    <div class="row">

      <!-- First Name -->
      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="emergency_first_name"
          placeholder="First Name"
          required
        >
      </div>

      <!-- Last Name -->
      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="emergency_last_name"
          placeholder="Last Name"
          required
        >
      </div>

      <!-- Email -->
      <div class="col-md-6 mb-3">
        <input
          type="email"
          class="form-control"
          name="emergency_email"
          placeholder="Email"
          required
        >
      </div>

      <!-- Emergency Phone -->
      <div class="col-md-6 mb-3">
        <label class="form-label">Emergency Phone</label>

        <!-- Visible phone input -->
        <input
          type="tel"
          id="emergency_phone"
          class="form-control"
          placeholder="Enter phone number"
          required
        >

        <!-- Hidden fields (KEEP DB STRUCTURE SAME) -->
        <input
          type="hidden"
          name="emergency_area_code"
          id="emergency_area_code"
          required
        >
        <input
          type="hidden"
          name="emergency_phone_number"
          id="emergency_phone_number"
          required
        >

        <div class="form-text">
          Select country to auto-fill code and validate number length.
        </div>
      </div>

      <!-- Relationship -->
      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="emergency_relationship"
          placeholder="Relationship"
          required
        >
      </div>

      <!-- Same Address -->
      <div class="col-md-6 mb-3">
        <select
          class="form-select"
          name="emergency_same_address"
          required
        >
          <option value="">Same Address?</option>
          <option value="Yes">Yes</option>
          <option value="No">No</option>
        </select>
      </div>

    </div>

  </div>
</div>

<!-- =====================================================
 STEP 4 : DOCUMENTS + AGENT (FULL DB-ALIGNED)
===================================================== -->
<div class="step">
  <div class="step-section">

    <!-- ================= HEADER ================= -->
    <div class="mb-4">
      <h5 class="fw-semibold mb-1">Required Documents</h5>
      <p class="text-muted small mb-0">
        Upload clear and readable documents. Supported formats: PDF, JPG, PNG.
        Documents are reviewed according to university and visa regulations.
      </p>
    </div>

    <!-- ================= DOCUMENT GRID ================= -->
    <div class="row g-4">

      <!-- 1. KOREAN PHOTO -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">
          Korean Standard Photo <span class="text-danger">*</span>
        </label>
        <div class="doc-dropzone" data-field="korean_photo_uploaded">
          <input type="file" accept=".jpg,.png">
          <div class="dz-content">
            <strong>Drop photo here</strong>
            <span>White background (Korean standard)</span>
          </div>
          <ul class="dz-files"></ul>
        </div>
        <textarea
          class="form-control mt-2"
          name="korean_photo_notes"
          placeholder="Photo notes (if any)"
          rows="2"
        ></textarea>
      </div>

      <!-- 2. PASSPORT UPLOAD -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">
          Passport Copy <span class="text-danger">*</span>
        </label>
        <div class="doc-dropzone" data-field="valid_passport">
          <input type="file" accept=".pdf,.jpg,.png">
          <div class="dz-content">
            <strong>Drop passport here</strong>
            <span>Clear scan of bio page</span>
          </div>
          <ul class="dz-files"></ul>
        </div>

        <div class="form-check mt-2">
          <input
            class="form-check-input"
            type="checkbox"
            name="passport_valid_6_months"
            id="passport_valid_6_months"
          >
          <label class="form-check-label" for="passport_valid_6_months">
            Passport valid for at least 6 months
          </label>
        </div>
      </div>

      <!-- 3. FINAL CERTIFICATE -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">
          Final Education Certificate <span class="text-danger">*</span>
        </label>
        <div class="doc-dropzone" data-field="final_certificate_uploaded">
          <input type="file" accept=".pdf,.jpg,.png">
          <div class="dz-content">
            <strong>Drop certificate</strong>
            <span>Original diploma</span>
          </div>
          <ul class="dz-files"></ul>
        </div>
      </div>

      <!-- 4. FINAL TRANSCRIPT -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">
          Final Academic Transcript <span class="text-danger">*</span>
        </label>
        <div class="doc-dropzone" data-field="final_transcript_uploaded">
          <input type="file" accept=".pdf,.jpg,.png">
          <div class="dz-content">
            <strong>Drop transcript</strong>
            <span>Complete academic record</span>
          </div>
          <ul class="dz-files"></ul>
        </div>
      </div>

      <!-- 5. EDUCATION LANGUAGE -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">Education Document Language</label>
        <select class="form-select" name="education_language">
          <option value="">Select</option>
          <option value="English">English</option>
          <option value="Korean">Korean</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <!-- 6. EDUCATION VERIFICATION -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">Education Verification</label>

        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="education_translation_notarized">
          <label class="form-check-label">Translation notarized</label>
        </div>

        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="apostille_or_consulate_verified">
          <label class="form-check-label">Apostille / Consulate verified</label>
        </div>

        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="china_chsi_cdgdc_verified">
          <label class="form-check-label">CHSI / CDGDC verified (China only)</label>
        </div>
      </div>

      <!-- 7. TRANSLATOR CONFIRMATION -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">Translator Confirmation</label>
        <div class="doc-dropzone" data-field="translator_confirmation_uploaded">
          <input type="file" accept=".pdf,.jpg,.png">
          <div class="dz-content">
            <strong>Drop confirmation</strong>
          </div>
          <ul class="dz-files"></ul>
        </div>
      </div>

      <!-- 8. FINANCIAL DOCUMENTS -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">Parent Income Statement</label>
        <div class="doc-dropzone" data-field="parent_income_statement_uploaded">
          <input type="file" accept=".pdf,.jpg,.png">
          <ul class="dz-files"></ul>
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold">Parent Employment Certificate</label>
        <div class="doc-dropzone" data-field="parent_employment_certificate_uploaded">
          <input type="file" accept=".pdf,.jpg,.png">
          <ul class="dz-files"></ul>
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold">Parent Business Certificate</label>
        <div class="doc-dropzone" data-field="parent_business_certificate_uploaded">
          <input type="file" accept=".pdf,.jpg,.png">
          <ul class="dz-files"></ul>
        </div>
      </div>

      <!-- 9. BANK BALANCE -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">
          Bank Balance Certificate <span class="text-danger">*</span>
        </label>
        <div class="doc-dropzone" data-field="bank_balance_certificate_uploaded">
          <input type="file" accept=".pdf,.jpg,.png">
          <ul class="dz-files"></ul>
        </div>

        <input
          type="number"
          class="form-control mt-2"
          name="bank_balance_amount"
          placeholder="Bank balance amount"
        >

        <input
          type="date"
          class="form-control mt-2"
          name="bank_balance_issue_date"
        >
      </div>

      <!-- 10. IDS -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">Applicant National ID</label>
        <div class="doc-dropzone" data-field="applicant_id_uploaded">
          <input type="file" accept=".pdf,.jpg,.png">
          <ul class="dz-files"></ul>
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold">Father National ID</label>
        <div class="doc-dropzone" data-field="father_id_uploaded">
          <input type="file" accept=".pdf,.jpg,.png">
          <ul class="dz-files"></ul>
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold">Mother National ID</label>
        <div class="doc-dropzone" data-field="mother_id_uploaded">
          <input type="file" accept=".pdf,.jpg,.png">
          <ul class="dz-files"></ul>
        </div>

        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="id_translation_notarized">
          <label class="form-check-label">ID translation notarized</label>
        </div>
      </div>

      <!-- 11. BIRTH CERTIFICATE -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">Translated Birth Certificate</label>
        <div class="doc-dropzone" data-field="birth_certificate_translated_uploaded">
          <input type="file" accept=".pdf,.jpg,.png">
          <ul class="dz-files"></ul>
        </div>
      </div>

      <!-- 12. OTHER DOCUMENTS -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">Self-Introduction Letter</label>
        <div class="doc-dropzone" data-field="self_introduction_letter_uploaded">
          <input type="file" accept=".pdf,.jpg,.png">
          <ul class="dz-files"></ul>
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold">
          Study Plan <span class="text-danger">*</span>
        </label>
        <div class="doc-dropzone" data-field="study_plan_uploaded">
          <input type="file" accept=".pdf,.jpg,.png">
          <ul class="dz-files"></ul>
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold">Personal Information Consent</label>
        <div class="doc-dropzone" data-field="personal_information_consent_uploaded">
          <input type="file" accept=".pdf,.jpg,.png">
          <ul class="dz-files"></ul>
        </div>
      </div>

    </div>

    <!-- ================= AGENT INFORMATION ================= -->
    <hr class="my-5">

    <h6 class="fw-semibold mb-2">
      Agent Information <span class="text-danger">*</span>
    </h6>

    <input
      type="text"
      id="agent_search"
      class="form-control mb-2"
      placeholder="Search agent by name or email"
      autocomplete="off"
      required
    >

    <div
      id="agentResults"
      class="list-group position-absolute w-100 d-none"
      style="z-index:1000"
    ></div>

    <div class="row mt-3">
      <div class="col-md-4 mb-3">
        <input class="form-control" id="agent_first_name" name="agent_first_name" readonly required>
      </div>
      <div class="col-md-4 mb-3">
        <input class="form-control" id="agent_last_name" name="agent_last_name" readonly required>
      </div>
      <div class="col-md-4 mb-3">
        <input class="form-control" id="agent_email" name="agent_email" readonly required>
      </div>
    </div>

    <!-- ================= COMMENTS ================= -->
    <label class="form-label fw-semibold">Additional Comments</label>
    <textarea
      class="form-control"
      name="comments"
      rows="4"
      placeholder="Additional comments, explanations, or missing document notes"
    ></textarea>

  </div>
</div>

<!-- ================= NAVIGATION ================= -->
<div class="d-flex justify-content-between mt-4">
  <button type="button" class="btn btn-secondary" id="prevBtn">Back</button>
  <button type="button" class="btn btn-primary" id="nextBtn">Next</button>
</div>

</form>
</div>
</div>
</div>
<link
  rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css"
/>

<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5.min.js"></script>

<!-- 3️⃣ Your application logic (UNCHANGED) -->
<script src="application-korea.js"></script>
<script src="study-search.js"></script>
<script>
"use strict";

/* =====================================================
   GLOBAL SAFETY (shared with application.js)
   Keeps track of uploaded files per field
===================================================== */
window.uploadStatus = window.uploadStatus || {};

/* =====================================================
   PROGRESS CONTROLLER (UI ONLY)
   Single global progress bar controller
===================================================== */
function createProgressController() {
  const wrap   = document.getElementById("docProgressWrap");
  const bar    = wrap.querySelector(".upload-bar");
  const text   = document.getElementById("docProgressText");
  const status = document.getElementById("docValidationStatus");

  /* ---------- Reset UI ---------- */
  bar.style.background = "";
  bar.style.width = "0%";
  text.textContent = "0%";
  status.textContent = "";

  wrap.style.display = "block";
  wrap.classList.add("active");

  return {
    set(percent, label) {
      percent = Math.max(0, Math.min(100, percent));
      bar.style.width = percent + "%";
      text.textContent = percent + "%";
      if (label) status.textContent = label;
    },

    success(message) {
      bar.style.width = "100%";
      text.textContent = "100%";
      status.textContent = message || "Document validated successfully";
    },

    error(message) {
      bar.style.width = "100%";
      bar.style.background = "#dc3545";
      text.textContent = "!";
      status.textContent = message || "Upload failed";
    },

    hide(delay = 1200) {
      setTimeout(() => {
        wrap.classList.remove("active");
        wrap.style.display = "none";
      }, delay);
    }
  };
}

/* =====================================================
   DROPZONE INITIALIZATION
   Works for single & multi-file zones
===================================================== */
document.querySelectorAll(".doc-dropzone").forEach(zone => {

  const input    = zone.querySelector('input[type="file"]');
  const list     = zone.querySelector(".dz-files");
  const field    = zone.dataset.field;
  const multiple = input.hasAttribute("multiple");

  /* ---------- Render selected files ---------- */
  function renderFiles(files) {
    list.innerHTML = "";
    [...files].forEach(file => {
      const li = document.createElement("li");
      li.textContent = file.name;
      list.appendChild(li);
    });
  }

  /* ---------- Drag & Drop UI ---------- */
  ["dragenter", "dragover"].forEach(evt =>
    zone.addEventListener(evt, e => {
      e.preventDefault();
      zone.classList.add("dragover");
    })
  );

  ["dragleave", "drop"].forEach(evt =>
    zone.addEventListener(evt, e => {
      e.preventDefault();
      zone.classList.remove("dragover");
    })
  );

  zone.addEventListener("drop", e => {
    if (!multiple && e.dataTransfer.files.length > 1) {
      alert("Only one file is allowed for this document.");
      return;
    }
    input.files = e.dataTransfer.files;
    input.dispatchEvent(new Event("change"));
  });

  /* ---------- File selection ---------- */
  input.addEventListener("change", async () => {
    if (!input.files || !input.files.length) return;

    if (!multiple && input.files.length > 1) {
      alert("Only one file allowed.");
      input.value = "";
      return;
    }

    renderFiles(input.files);

  /* ---------- Upload files sequentially ---------- */
for (const file of input.files) {
  await uploadSingleFile(field, file);
}

/* ---------- Lock ONLY single-file inputs ---------- */
if (!multiple) {
  input.disabled = true;
  input.classList.add("is-valid");
} else {
  // Allow multi uploads to continue
  input.value = ""; // reset so user can add more files
}

  });
});

/* =====================================================
   SINGLE FILE UPLOAD (CORE LOGIC)
   One file → one request → safe progress
===================================================== */
function uploadSingleFile(field, file) {

  return new Promise((resolve, reject) => {

    /* =====================================================
       PREVENT DUPLICATE UPLOADS (PER FIELD)
    ===================================================== */
    window.uploadStatus[field] = window.uploadStatus[field] || [];

    if (window.uploadStatus[field].includes(file.name)) {
      resolve();
      return;
    }

    /* =====================================================
       INIT PROGRESS UI
    ===================================================== */
    const progress = createProgressController();
    progress.set(0, "Starting upload…");

    /* =====================================================
       BUILD FORM DATA
    ===================================================== */
    const formData = new FormData();
    formData.append("file", file);
    formData.append("field", field);

    /* =====================================================
       INIT REQUEST
    ===================================================== */
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "upload_file_korea.php", true);

    /* =====================================================
       REAL UPLOAD PROGRESS (0 → 100)
    ===================================================== */
    xhr.upload.onprogress = e => {
      if (!e.lengthComputable) return;

      const percent = Math.round((e.loaded / e.total) * 100);
      progress.set(percent, `Uploading ${file.name}`);
    };

    /* =====================================================
       NETWORK ERROR
    ===================================================== */
    xhr.onerror = () => {
      progress.error("Network error during upload");
      reject();
    };

    /* =====================================================
       SERVER RESPONSE
    ===================================================== */
    xhr.onload = () => {

      if (xhr.status !== 200) {
        progress.error("Server error");
        reject();
        return;
      }

      let response;
      try {
        response = JSON.parse(xhr.responseText);
      } catch {
        progress.error("Invalid server response");
        reject();
        return;
      }

      if (response.status === "success") {
        progress.success("Upload completed");
        window.uploadStatus[field].push(file.name);
        progress.hide();
        resolve();
      } else {
        progress.error(response.message || "Upload failed");
        reject();
      }
    };

    /* =====================================================
       SEND REQUEST
    ===================================================== */
   /* 🔄 FORCE UI PAINT BEFORE UPLOAD */
requestAnimationFrame(() => {
  xhr.send(formData);
});
  });
}
</script>
<script>
const searchInput = document.getElementById('agent_search');
const resultsBox  = document.getElementById('agentResults');

searchInput.addEventListener('input', function () {
    const query = this.value.trim();

    if (query.length < 2) {
        resultsBox.classList.add('d-none');
        resultsBox.innerHTML = '';
        return;
    }

    fetch('searchAgents.php?q=' + encodeURIComponent(query))
        .then(res => res.json())
        .then(data => {
            resultsBox.innerHTML = '';

            if (data.length === 0) {
                resultsBox.classList.add('d-none');
                return;
            }

            data.forEach(agent => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `
                    <strong>${agent.full_name}</strong><br>
                    <small>${agent.email}</small>
                `;

                item.onclick = () => {
                    document.getElementById('agent_first_name').value = agent.first_name;
                    document.getElementById('agent_last_name').value  = agent.last_name;
                    document.getElementById('agent_email').value      = agent.email;
                    searchInput.value = agent.full_name;
                    resultsBox.classList.add('d-none');
                };

                resultsBox.appendChild(item);
            });

            resultsBox.classList.remove('d-none');
        });
});

// Close dropdown when clicking outside
document.addEventListener('click', e => {
    if (!e.target.closest('#agent_search')) {
        resultsBox.classList.add('d-none');
    }
});
</script>
<script>
(function () {
    const firstName = document.getElementById('agent_first_name');
    const lastName  = document.getElementById('agent_last_name');
    const email     = document.getElementById('agent_email');

    if (!firstName || !lastName || !email) return;

    function lockFields() {
        firstName.readOnly = true;
        lastName.readOnly  = true;
        email.readOnly     = true;
    }

    /* 🔒 Hard lock as soon as any value appears */
    function enforceLock() {
        if (
            firstName.value.trim() !== '' ||
            lastName.value.trim() !== '' ||
            email.value.trim() !== ''
        ) {
            lockFields();
        }
    }

    /* Catch ALL ways values can be set */
    ['input', 'change', 'keyup', 'paste'].forEach(evt => {
        firstName.addEventListener(evt, enforceLock);
        lastName.addEventListener(evt, enforceLock);
        email.addEventListener(evt, enforceLock);
    });

    /* Also enforce lock on page load (safety) */
    document.addEventListener('DOMContentLoaded', enforceLock);

})();
</script>
<script>
(function () {

  const loanSections   = document.querySelectorAll(".loan-section");
  const loanOptions    = document.querySelectorAll(".loan-option");
  const financeSelects = document.querySelectorAll(".finance-select");
  const studyChoices   = document.getElementById("studyChoices");

  function normalize(text) {
    return text.toLowerCase().replace(/[^a-z]/g, "");
  }

  function isMasterLevel(name) {
    const v = normalize(name);
    return [
      "master",
      "masters",
      "msc",
      "mba",
      "mphil",
      "mster"
    ].some(k => v.includes(k));
  }

  function clearLoanData() {
    document
      .querySelectorAll('input[name="destination_loan"], input[name="other_destination_loan"]')
      .forEach(i => i.value = "");

    financeSelects.forEach(select => {
      if (select.value === "Loan") {
        select.value = "";
      }
    });
  }

  function applyLoanPolicy() {
    let allowLoan = false;

    document.querySelectorAll(".study-choice .level").forEach(select => {
      const opt = select.selectedOptions[0];
      if (!opt) return;

      const levelName =
        opt.dataset?.name ||
        opt.textContent ||
        "";

      if (isMasterLevel(levelName)) {
        allowLoan = true;
      }
    });

    // Toggle loan destination fields
    loanSections.forEach(section => {
      section.style.display = allowLoan ? "" : "none";
    });

    // Toggle Loan option in finance dropdowns
    loanOptions.forEach(option => {
      option.style.display = allowLoan ? "" : "none";
      option.disabled = !allowLoan;
    });

    if (!allowLoan) {
      clearLoanData();
    }
  }

  // Observe dynamic program changes
  const observer = new MutationObserver(applyLoanPolicy);
  observer.observe(studyChoices, { childList: true, subtree: true });

  // Catch direct changes to level selects
  document.addEventListener("change", e => {
    if (e.target.classList.contains("level")) {
      applyLoanPolicy();
    }
  });

  document.addEventListener("DOMContentLoaded", applyLoanPolicy);

})();
</script>
<script>
(function () {

  const preferredDestination = document.getElementById("preferredDestination");
  const loanDestination      = document.getElementById("loanDestination");
  const loanSections         = document.querySelectorAll(".loan-section");
  const financeSelects       = document.querySelectorAll(".finance-select");
  const studyChoices         = document.getElementById("studyChoices");

  function normalize(text) {
    return text.toLowerCase().replace(/[^a-z]/g, "");
  }

  function isMasterLevel(name) {
    const v = normalize(name);
    return [
      "master",
      "masters",
      "msc",
      "mba",
      "mphil",
      "mster"
    ].some(k => v.includes(k));
  }

  function clearLoanData() {
    if (loanDestination) loanDestination.value = "";

    document
      .querySelectorAll('input[name="other_destination_loan"]')
      .forEach(i => i.value = "");

    financeSelects.forEach(select => {
      if (select.value === "Loan") {
        select.value = "";
      }
    });
  }

  function syncLoanDestination() {
    if (!loanDestination || !preferredDestination) return;

    loanDestination.value = preferredDestination.value || "";
  }

  function applyLoanPolicy() {
    let allowLoan = false;

    document.querySelectorAll(".study-choice .level").forEach(select => {
      const opt = select.selectedOptions[0];
      if (!opt) return;

      const levelName =
        opt.dataset?.name ||
        opt.textContent ||
        "";

      if (isMasterLevel(levelName)) {
        allowLoan = true;
      }
    });

    // Toggle loan destination section
    loanSections.forEach(section => {
      section.style.display = allowLoan ? "" : "none";
    });

    // Toggle Loan option in finance selects
    document.querySelectorAll(".loan-option").forEach(opt => {
      opt.disabled = !allowLoan;
      opt.style.display = allowLoan ? "" : "none";
    });

    if (allowLoan) {
      syncLoanDestination();
    } else {
      clearLoanData();
    }
  }

  /* ===============================
     WATCHERS
  =============================== */

  // When study programs change
  const observer = new MutationObserver(applyLoanPolicy);
  observer.observe(studyChoices, { childList: true, subtree: true });

  // When program level changes
  document.addEventListener("change", e => {
    if (e.target.classList.contains("level")) {
      applyLoanPolicy();
    }
  });

  // 🔁 When preferred destination changes → sync loan destination
  preferredDestination?.addEventListener("input", syncLoanDestination);
  preferredDestination?.addEventListener("change", syncLoanDestination);

  document.addEventListener("DOMContentLoaded", applyLoanPolicy);

})();
</script>
<script>
(function () {

  document.querySelectorAll('.conditional-select').forEach(select => {

    const targetName = select.dataset.followup;
    const field = document.querySelector(
      '.conditional-field[name="' + targetName + '"]'
    );

    if (!field) return;

    function toggle() {
      if (select.value === 'Yes') {
        field.style.display = 'block';
      } else {
        field.style.display = 'none';
        field.value = '';
      }
    }

    // Initial state
    toggle();

    // On change
    select.addEventListener('change', toggle);
  });

})();
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {

  const phoneInput = document.querySelector("#emergency_phone");
  const areaCode   = document.querySelector("#emergency_area_code");
  const phoneNum   = document.querySelector("#emergency_phone_number");

  if (!phoneInput) return;

  const iti = window.intlTelInput(phoneInput, {
    initialCountry: "auto",
    separateDialCode: true,
    nationalMode: true,
    utilsScript:
      "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
    geoIpLookup: function (callback) {
      fetch("https://ipapi.co/json/")
        .then(res => res.json())
        .then(data => callback(data.country_code))
        .catch(() => callback("US"));
    }
  });

  /* ===============================
     LIVE VALIDATION
  =============================== */
  phoneInput.addEventListener("blur", () => {
    if (phoneInput.value.trim() === "") return;

    if (!iti.isValidNumber()) {
      phoneInput.classList.add("is-invalid");
      phoneInput.classList.remove("is-valid");
    } else {
      phoneInput.classList.remove("is-invalid");
      phoneInput.classList.add("is-valid");
    }
  });

  /* ===============================
     SAVE VALUES FOR BACKEND
  =============================== */
  phoneInput.addEventListener("change", syncPhone);
  phoneInput.addEventListener("keyup", syncPhone);

  function syncPhone() {
    if (!iti.isValidNumber()) return;

    areaCode.value = "+" + iti.getSelectedCountryData().dialCode;
    phoneNum.value = iti.getNumber(
      window.intlTelInputUtils.numberFormat.NATIONAL
    );
  }

});
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {

  const phoneInput = document.querySelector("#intl_phone");
  if (!phoneInput) return;

  const iti = window.intlTelInput(phoneInput, {
    initialCountry: "auto",
    nationalMode: true,
    separateDialCode: true,
    autoPlaceholder: "polite",
    preferredCountries: ["us", "gb", "fr", "ca", "de", "rw"],
    geoIpLookup: callback => {
      fetch("https://ipapi.co/json/")
        .then(res => res.json())
        .then(data => callback(data.country_code))
        .catch(() => callback("us"));
    },
    utilsScript:
      "https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.7/build/js/utils.js"
  });

  const areaCodeInput  = document.getElementById("area_code");
  const phoneNumInput  = document.getElementById("phone_number");

  function syncPhoneFields() {
    if (!iti.isValidNumber()) {
      areaCodeInput.value = "";
      phoneNumInput.value = "";
      phoneInput.classList.add("is-invalid");
      return false;
    }

    const data = iti.getSelectedCountryData();

    areaCodeInput.value = `+${data.dialCode}`;
    phoneNumInput.value = phoneInput.value.replace(/\D/g, "");

    phoneInput.classList.remove("is-invalid");
    phoneInput.classList.add("is-valid");
    return true;
  }

  phoneInput.addEventListener("blur", syncPhoneFields);
  phoneInput.addEventListener("change", syncPhoneFields);
  phoneInput.addEventListener("keyup", syncPhoneFields);

  /* Prevent form submit if invalid */
  const form = phoneInput.closest("form");
  if (form) {
    form.addEventListener("submit", e => {
      if (!syncPhoneFields()) {
        e.preventDefault();
        alert("Please enter a valid phone number.");
      }
    });
  }

});
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  const box     = document.getElementById("regionStep");
  const select  = document.getElementById("regions");
  const hint    = document.getElementById("regionHint");
  const pointer = document.getElementById("regionPointer");

  if (!box || !select || !pointer) return;

  let active = true;
  let offset = 0;
  let direction = 1;

  /* ===============================
     START AFTER DELAY (NOT ON LOAD)
  =============================== */
  setTimeout(() => {
    if (!active) return;

    pointer.style.opacity = "1";

    /* Continuous pointing motion */
    const pointerLoop = setInterval(() => {
      if (!active) {
        clearInterval(pointerLoop);
        return;
      }

      offset += direction * 2;

      if (offset > 10 || offset < 0) {
        direction *= -1;
      }

      pointer.style.transform =
        `translateY(-50%) translateX(${offset}px)`;
    }, 80);

    /* Stop everything on interaction */
    function stopGuide() {
      if (!active) return;
      active = false;

      clearInterval(pointerLoop);

      pointer.style.opacity = "0";
      box.style.borderColor = "#d1d5db";
      select.style.borderColor = "#d1d5db";
      select.style.boxShadow = "none";
      hint?.remove();

      setTimeout(() => pointer.remove(), 300);
    }

    ["focus", "click", "change"].forEach(evt =>
      select.addEventListener(evt, stopGuide, { once: true })
    );

  }, 1000); // ⏱️ delay before starting animation
});
</script>

<!-- ===============================
     GLOBAL DOCUMENT UPLOAD PROGRESS
     (MUST BE OUTSIDE FORM & STEPS)
================================ -->
<div id="docProgressWrap" class="upload-progress">
  <div class="upload-bar">
    <span id="docProgressText">0%</span>
  </div>
</div>

<div
  id="docValidationStatus"
  class="small text-muted mt-2"
></div>
</body>
</html>
