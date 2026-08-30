"use strict";
window.uploadStatus = window.uploadStatus || {};
/* =====================================================
   CONFIG
===================================================== */
const API = "save_application_korea.php";
let currentApplicationId = null;

/* =====================================================
   REQUIRED DOCUMENT UPLOADS (FINAL – DO NOT RENAME)
   Must match data-field & backend columns exactly
===================================================== */
const REQUIRED_UPLOADS = [
  "final_transcript_uploaded",   // Final Academic Transcript
  "valid_passport",              // Passport Copy
  "study_plan_uploaded"          // Study Plan
];


// upload validation state (field => response)


/* =====================================================
   STEP NAVIGATION (UNCHANGED)
===================================================== */
let step = 0;
const steps = [...document.querySelectorAll(".step")];
const bars  = [...document.querySelectorAll(".progress-step span")];
const form  = document.getElementById("applicationForm");

function showStep(index) {
  steps.forEach(s => s.classList.remove("active"));
  bars.forEach(b => b.classList.remove("active"));

  steps[index]?.classList.add("active");
  bars[index]?.classList.add("active");

  document.getElementById("prevBtn").disabled = index === 0;
}
document.getElementById("nextBtn").addEventListener("click", async () => {

  // STEP 0 → study choices
  

  // STEPS 1 → 5 → required fields
  if (!validateSteps2to6()) return;

 // FINAL STEP (UPLOADS + AGENT)
if (step === steps.length - 1) {

  // ⛔ BLOCK if required documents missing
  if (!validateRequiredUploads()) return;

  // ⛔ BLOCK if agent not selected
  if (!validateRequiredAgent()) return;

  submitForm();
  return;
}


  try {
    await saveStep();   // ⛔ HARD STOP if save fails
    step++;
    showStep(step);
  } catch (err) {
    console.error(err);
    alert("Failed to save your data. Please try again.");
  }
});


document.getElementById("prevBtn").addEventListener("click", () => {
  if (step > 0) {
    step--;
    showStep(step);
  }
});

/* =====================================================
   ELEMENT REFERENCES (SAFE)
===================================================== */
const regionsSelect     = document.getElementById("regions");
const studyChoicesWrap  = document.getElementById("studyChoices");
const studyEmpty        = document.getElementById("studyEmpty");
const studyTemplate     = document.getElementById("studyChoiceTemplate");

/* OLD ELEMENTS (KEEP – DO NOT REMOVE) */
const regionSelect       = document.getElementById("region");
const universitySelect   = document.getElementById("universities");
const programLevelSelect = document.getElementById("programLevel");
const programsSelect     = document.getElementById("programs");
const countrySelects     = document.querySelectorAll(".country-select");

/* =====================================================
   COUNTRY PLACEHOLDER FIX (JS ONLY)
===================================================== */
function initCountryPlaceholders() {
  document.querySelectorAll(".country-select").forEach(select => {

    const placeholder =
      select.getAttribute("data-placeholder") || "Select country";

    // Destroy Select2 safely if already initialized
    if ($(select).hasClass("select2-hidden-accessible")) {
      $(select).select2("destroy");
    }

    // Remove ALL existing options (kills "Select Region First")
    select.innerHTML = "";

    // Real placeholder anchor
    select.add(new Option("", "", false, false));

    // Keep disabled until countries load
    select.disabled = true;

    // Init Select2 with correct placeholder
    $(select).select2({
      theme: "bootstrap-5",
      width: "100%",
      placeholder: placeholder,
      allowClear: true
    });
  });
}
/* =====================================================
   LOAD COUNTRIES (SAFE)
===================================================== */
async function loadCountries() {
  
  try {
    const res = await fetch(`${API}?action=countries`);
    const countries = await res.json();

    if (!Array.isArray(countries)) {
      throw new Error("Invalid countries response");
    }

    document.querySelectorAll(".country-select").forEach(select => {

      countries.forEach(c => {
        select.add(
          new Option(c.name || c.text, c.id || c.code)
        );
      });

      // Enable AFTER data exists
      select.disabled = false;

      // Refresh Select2
      $(select).trigger("change.select2");
    });

  } catch (err) {
    console.error("Failed to load countries:", err);
  }
}


/* =====================================================
   SELECT2 INITIALIZATION (GLOBAL SAFE)
===================================================== */
$(function () {

 /* =====================================================
   STUDY REGIONS – SMART MULTI SELECT (INDEPENDENT CLOSE)
===================================================== */
if (regionsSelect) {

  const $regions = $('#regions');

  /* ---------- Select2 init ---------- */
$regions.select2({
  theme: 'bootstrap-5',
  width: '100%',
  placeholder: 'Select one or more regions',
  closeOnSelect: false,
  allowClear: false,

  templateSelection: function (data) {
    if (!data.id) return data.text;

    return $(`
      <span class="region-chip" data-id="${data.id}">
        <span class="region-text">${data.text}</span>
        <span class="region-close" title="Remove region">×</span>
      </span>
    `);
  },

  escapeMarkup: function (m) {
    return m;
  }
});

  /* ---------- Independent region close ---------- */
  $(document)
    .off('click.regionClose')
    .on('click.regionClose', '.region-close', function (e) {
      e.stopPropagation();

      const regionId = String(
        $(this).closest('.region-chip').data('id')
      );

      // Remove only the clicked region
      const remaining = ($regions.val() || []).filter(
        id => id !== regionId
      );

      $regions.val(remaining).trigger('change');

      // Cleanup related study blocks
      removeRegionBlocks(regionId);
    });
}


  if (regionSelect) $('#region').select2({ theme: 'bootstrap-5', width: '100%' });
  if (universitySelect) $('#universities').select2({ theme: 'bootstrap-5', width: '100%' });
  if (programLevelSelect) $('#programLevel').select2({ theme: 'bootstrap-5', width: '100%' });
  if (programsSelect) $('#programs').select2({ theme: 'bootstrap-5', width: '100%' });
/* ✅ COUNTRY SELECTS – CORRECT PLACE */
if (countrySelects.length) {
  initCountryPlaceholders(); // placeholders FIRST
  loadCountries();           // data SECOND
}
});

/* =====================================================
   INITIAL LOAD – REGIONS ONLY
===================================================== */
(async function init() {
  try {
    const res = await fetch(`${API}?action=load_meta`);
    const data = await res.json();

    if (!Array.isArray(data.regions)) {
      throw new Error("Invalid meta response");
    }

    // New Step 1
    if (regionsSelect) {
      resetSelect(regionsSelect, "Select Regions", false);
      data.regions.forEach(r =>
        regionsSelect.add(new Option(r.name, r.id))
      );
      $('#regions').trigger("change.select2");
    }

    // Old Step 1 (kept for compatibility)
    if (regionSelect) {
      resetSelect(regionSelect, "Select Region", false);
      data.regions.forEach(r =>
        regionSelect.add(new Option(r.name, r.id))
      );
      $('#region').trigger("change.select2");
    }

    showStep(0);

  } catch (err) {
    alert("Failed to load application data. Please refresh.");
    console.error(err);
  }
})();

/* =====================================================
   STEP 1 – REGION SELECTION → STUDY BLOCKS
===================================================== */
if (regionsSelect && studyChoicesWrap && studyTemplate) {

  $('#regions').on('change', async function () {
    const regionIds = $(this).val() || [];

    studyChoicesWrap.innerHTML = "";
    studyEmpty.style.display = regionIds.length ? "none" : "block";

    for (const regionId of regionIds) {
      await loadUniversitiesForRegion(regionId);
    }
  });
}

/* =====================================================
   LOAD UNIVERSITIES PER REGION
===================================================== */
async function loadUniversitiesForRegion(regionId) {
  const universities = await fetch(
    `${API}?action=universities&region_id=${regionId}`
  ).then(r => r.json());

  universities.forEach(u => createStudyChoice(regionId, u));
}

/* =====================================================
   CREATE STUDY BLOCK (UNIVERSITY + LEVEL + PROGRAM)
===================================================== */
function createStudyChoice(regionId, university) {

  const block = studyTemplate.content.cloneNode(true).firstElementChild;

  const regionInput   = block.querySelector(".region-id");
  const regionBadge   = block.querySelector(".region-badge");
  const uniSelect     = block.querySelector(".university");
  const levelSelect   = block.querySelector(".level");
  const programSelect = block.querySelector(".program");
  const removeBtn     = block.querySelector(".btn-remove");

  // Region info
  regionInput.value = regionId;
  regionBadge.textContent =
    $('#regions option[value="' + regionId + '"]').text();

  // University (fixed but searchable)
  uniSelect.add(new Option(university.name, university.id, true, true));
  uniSelect.disabled = true;
  $(uniSelect).select2({
    theme: 'bootstrap-5',
    width: '100%',
    minimumResultsForSearch: 0
  });

  // Load program levels
  fetch(`${API}?action=program_levels&university_id=${university.id}`)
    .then(r => r.json())
    .then(levels => {
      resetSelect(levelSelect, "Select level", false);
      levels.forEach(l =>
        levelSelect.add(new Option(l.name, l.id))
      );

      $(levelSelect).select2({
        theme: 'bootstrap-5',
        width: '100%',
        minimumResultsForSearch: 0
      });
    });
/* =====================================================
   PROGRAM LEVEL → PROGRAMS (FIXED FOR SELECT2)
===================================================== */

$(levelSelect).off("change").on("change", async function () {

  const levelId = this.value;

  /* =============================
     RESET PROGRAM SELECT
  ============================== */

  // Destroy Select2 safely if exists
  if ($(programSelect).hasClass("select2-hidden-accessible")) {
    $(programSelect).select2("destroy");
  }

  // Clear & disable
  programSelect.innerHTML = "";
  programSelect.add(new Option("Loading programs…", ""));
  programSelect.disabled = true;

  if (!levelId) return;

  /* =============================
     LOAD PROGRAMS
  ============================== */

  let programs = [];

try {
  const res = await fetch(
    `${API}?action=programs&university_id=${university.id}&program_level_id=${levelId}`
  );

  const text = await res.text();

  if (!text) {
    throw new Error("Empty response");
  }

  try {
    programs = JSON.parse(text);
  } catch (e) {
    console.error("Non-JSON response from server:", text);
    throw new Error("Invalid JSON");
  }

  if (!Array.isArray(programs)) {
    throw new Error("Programs is not an array");
  }

} catch (err) {
  console.error("Failed to load programs", err);

  programSelect.innerHTML = "";
  programSelect.add(new Option("Failed to load programs", ""));
  programSelect.disabled = true;

  return;
}


  /* =============================
     REBUILD PROGRAM SELECT
  ============================== */

  programSelect.innerHTML = "";
  programSelect.add(new Option("Select program", ""));

  programs.forEach(p => {
    programSelect.add(new Option(p.program_name, p.id));
  });

  // Enable BEFORE Select2 init
  programSelect.disabled = false;

  /* =============================
     INIT SELECT2 (FRESH INSTANCE)
  ============================== */

  $(programSelect).select2({
    theme: "bootstrap-5",
    width: "100%",
    placeholder: "Select program",
    allowClear: true,
    minimumResultsForSearch: 0
  });

});

  // Remove single university block
  removeBtn.onclick = () => block.remove();

  studyChoicesWrap.appendChild(block);
}

/* =====================================================
   REMOVE ALL BLOCKS FOR A REGION
===================================================== */
function removeRegionBlocks(regionId) {
  [...studyChoicesWrap.children].forEach(block => {
    const input = block.querySelector(".region-id");
    if (input && input.value === regionId) {
      block.remove();
    }
  });

  if (!studyChoicesWrap.children.length) {
    studyEmpty.style.display = "block";
  }
}

/* =====================================================
   STEP 1 VALIDATION – AT LEAST ONE PROGRAM SELECTED
===================================================== */
function validateStudyChoices() {
  return true;
}
/* =====================================================
   COLLECT STUDY CHOICES (FOR SAVE)
===================================================== */
function collectStudyChoices() {
  const choices = [];

  document.querySelectorAll('.study-choice').forEach(block => {
    const regionId  = block.querySelector('.region-id')?.value;
    const universityId = block.querySelector('.university')?.value;
    const levelId   = block.querySelector('.level')?.value;
    const programId = block.querySelector('.program')?.value;

    if (regionId && universityId && levelId && programId) {
      choices.push({
        region_id: Number(regionId),
        university_id: Number(universityId),
        program_level_id: Number(levelId),
        program_id: Number(programId)
      });
    }
  });

  return choices;
}

/* =====================================================
   AUTOSAVE (UNCHANGED)
===================================================== */
async function saveStep() {
  try {
    const choices = collectStudyChoices();

    /* =============================
       CLIENT DEBUG
    ============================== */
    console.group("SAVE STEP DEBUG");
    console.log("Current step:", step);
    console.log("Study choices array:", choices);

   const fd = new FormData(form);
fd.append("step", step);
fd.append("study_choices", JSON.stringify(choices));

/* ✅ ATTACH APPLICATION ID */
if (currentApplicationId) {
  fd.append("application_id", currentApplicationId);
}

    console.log("FormData entries:");
    for (const [k, v] of fd.entries()) {
      console.log(k, v);
    }

    /* =============================
       FETCH
    ============================== */
    const res = await fetch(API, {
      method: "POST",
      body: fd
    });

    console.log("HTTP status:", res.status);

    const rawText = await res.text();
    console.log("Raw server response:", rawText);

    /* =============================
       PARSE RESPONSE
    ============================== */
    let data;
    try {
      data = JSON.parse(rawText);
    } catch (e) {
      console.error("JSON parse error", e);
      throw new Error("Server returned invalid JSON");
    }

    console.log("Parsed response:", data);
    console.groupEnd();

    /* =============================
       VALIDATE RESPONSE
    ============================== */
    if (!res.ok) {
      throw new Error(data.debug || data.message || "Server error");
    }

   if (data.status !== "success") {
  throw new Error(data.debug || data.message || "Save failed");
}

/* ✅ STORE APPLICATION ID */
if (data.application_id) {
  currentApplicationId = data.application_id;
}

return true;


  } catch (err) {
    console.groupEnd();
    console.error("SAVE STEP FAILED:", err);
    throw err;
  }
}


/* =====================================================
   FINAL SUBMIT (UNCHANGED)
===================================================== */
async function submitForm() {
  try {
   const fd = new FormData(form);
fd.append("final", "1");
fd.append("study_choices", JSON.stringify(collectStudyChoices()));

/* ✅ ATTACH APPLICATION ID */
if (currentApplicationId) {
  fd.append("application_id", currentApplicationId);
}


    const res = await fetch(API, { method: "POST", body: fd });
    const data = await res.json();

    if (data.status === "success") {
  alert("Application submitted successfully");

  /* ✅ RESET STATE FOR NEXT PERSON */
  currentApplicationId = null;

  location.reload();
}
 else {
      alert(data.message || "Submission failed");
    }
  } catch {
    alert("Submission error. Please try again.");
  }
}


/* =====================================================
   REQUIRED UPLOAD VALIDATION (UNCHANGED)
===================================================== */
function validateRequiredUploads() {
  const missing = REQUIRED_UPLOADS.filter(
    field =>
      !window.uploadStatus[field] ||
      window.uploadStatus[field].length === 0
  );

  if (missing.length) {
    alert(
      "Please upload the required documents:\n\n" +
      missing.join(", ")
    );
    return false;
  }

  return true;
}
/* =====================================================
   REQUIRED AGENT VALIDATION (FINAL STEP ONLY)
===================================================== */
function validateRequiredAgent() {

  const first = document.getElementById("agent_first_name");
  const last  = document.getElementById("agent_last_name");
  const email = document.getElementById("agent_email");

  // Safety: elements must exist
  if (!first || !last || !email) {
    alert("Agent information section is missing.");
    return false;
  }

  // Must be auto-filled (readonly + value)
  if (
    !first.value.trim() ||
    !last.value.trim() ||
    !email.value.trim()
  ) {
    alert(
      "Please select an authorized agent before submitting your application."
    );

    // UX: scroll user to agent section
    first.scrollIntoView({
      behavior: "smooth",
      block: "center"
    });

    return false;
  }

  return true;
}

/* =====================================================
   REQUIRED FIELD VALIDATION – STEPS 1 TO 5 ONLY
===================================================== */
function validateSteps2to6() {

  // step index reference:
  // 0 = study choices
  // 1 → 5 = required fields
  // 6 = uploads

 if (step < 0 || step >= steps.length - 1) {
  return true;
}

  const currentStep = steps[step];
  if (!currentStep) return true;

  let isValid = true;
  let firstInvalid = null;

  const fields = currentStep.querySelectorAll(
    "input, select, textarea"
  );

  fields.forEach(field => {

    if (field.disabled) return;
    if (!field.hasAttribute("required")) return;

    const value = field.value?.trim();

    if (!value) {
      isValid = false;
      field.classList.add("is-invalid");

      if (!firstInvalid) {
        firstInvalid = field;
      }
    } else {
      field.classList.remove("is-invalid");
    }
  });

  // 🔴 HARD STOP — NO ALERT, NO STEP CHANGE
  if (!isValid) {
    // Scroll to first invalid field
    if (firstInvalid) {
      firstInvalid.scrollIntoView({
        behavior: "smooth",
        block: "center"
      });

      // Open Select2 if needed
      if ($(firstInvalid).hasClass("select2-hidden-accessible")) {
        $(firstInvalid).select2("open");
      } else {
        firstInvalid.focus();
      }
    }

    return false;
  }

  return true;
}

/* =====================================================
   HELPERS (UNCHANGED)
===================================================== */
function resetSelect(select, placeholder, disabled = true) {
  if (!select) return;
  select.innerHTML = "";
  select.disabled = disabled;
  select.add(new Option(placeholder, ""));
  if ($(select).hasClass("select2-hidden-accessible")) {
    $(select).trigger("change.select2");
  }
}



/* =====================================================
   AUTO-FILL DESTINATIONS (UNCHANGED)
===================================================== */
(function () {
  if (!regionsSelect) return;

  const preferred = document.getElementById("preferredDestination");
  const loan = document.getElementById("loanDestination");

  $('#regions').on('change', function () {
    const names = $(this).select2('data').map(r => r.text).join(", ");
    if (preferred) preferred.value = names;
    if (loan) loan.value = names;
  });
})();
