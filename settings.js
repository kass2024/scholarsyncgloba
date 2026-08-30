/* =====================================================
   SETTINGS.JS â€” FINAL, SAFE, PRODUCTION
   (Universities, Program Levels, Programs)
===================================================== */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  /* ===============================
     Helpers
  =============================== */
  const $ = (id) => document.getElementById(id);
  const exists = (id) => !!document.getElementById(id);

  let universityModal   = null;
  let levelModalInst    = null;
  let programModalInst  = null;

  /* =====================================================
   UNIVERSITIES — FINAL, SAFE, PRODUCTION (REBUILT)
===================================================== */

/**
 * Opens the Add / Edit University modal
 * @param {Object|null} data  University object for edit mode
 */
window.openUniversityModal = function (data = null) {

  /* ===============================
     DOM REFERENCES
  =============================== */
  const modalEl   = document.getElementById('universityModal');
  const form      = document.getElementById('universityForm');
  const titleEl   = document.getElementById('uniModalTitle');
  const idInput   = document.getElementById('uni_id');
  const nameInput = document.getElementById('uni_name');
  const regionSel = document.getElementById('uni_region');
  const countrySel= document.getElementById('uni_country');
  const platformSel = document.getElementById('uni_platforms');
  const adminSel = document.getElementById('uni_admins');

  /* ===============================
     HARD GUARD (DOM MUST EXIST)
  =============================== */
  if (!modalEl || !form || !titleEl || !idInput || !nameInput || !regionSel || !countrySel) {
    console.error('[settings.js] University modal DOM incomplete');
    return;
  }

  /* ===============================
     RESET FORM (ADD MODE DEFAULT)
  =============================== */
  form.reset();
  idInput.value = '';
  titleEl.textContent = 'Add University';

  if (platformSel) {
    Array.from(platformSel.options).forEach(opt => {
      opt.selected = false;
    });
  }
  if (adminSel) {
    Array.from(adminSel.options).forEach(opt => {
      opt.selected = false;
    });
  }
  document.querySelectorAll('#universityModal .ss-ms-search').forEach((el) => {
    el.value = '';
    el.dispatchEvent(new Event('input', { bubbles: true }));
  });
  if (typeof window.pcvcSsSyncUniPickers === 'function') {
    window.pcvcSsSyncUniPickers();
  }

  /* ===============================
     EDIT MODE (IF DATA PROVIDED)
  =============================== */
  if (data && typeof data === 'object') {

    titleEl.textContent = 'Edit University';

    idInput.value   = data.id ?? '';
    nameInput.value = data.name ?? '';
    regionSel.value = data.region_id ?? '';
    countrySel.value= data.country_id ?? '';

    if (data.id) {
      loadUniversityPlatforms(data.id);
    }
  }

  /* ===============================
     MODAL INSTANCE (SINGLETON)
     IMPORTANT: LOCAL VARIABLE ONLY
  =============================== */
  if (!universityModal) {
    universityModal = new bootstrap.Modal(modalEl, {
      backdrop: 'static',
      keyboard: false
    });
  }

  universityModal.show();
};

/* =====================================================
   SAVE UNIVERSITY (AJAX)
===================================================== */
(function () {

  const form = document.getElementById('universityForm');
  if (!form) return;

  /* Prevent duplicate listener binding */
  if (form.dataset.bound === '1') return;
  form.dataset.bound = '1';

  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

    try {
      const response = await fetch('settings-handler.php', {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin'
      });

      if (!response.ok) {
        const text = await response.text();
        throw new Error(`HTTP ${response.status}: ${text}`);
      }

      const json = await response.json();
      if (!json.ok) {
        throw new Error(json.msg || 'Save failed');
      }

      if (typeof showToast === 'function') {
        showToast('Saved', 'University saved successfully', false, 'success');
      }

      const modalInstance =
        bootstrap.Modal.getInstance(document.getElementById('universityModal'));

      modalInstance?.hide();

      setTimeout(() => location.reload(), 500);

    } catch (err) {
      console.error('[settings.js] University save failed:', err);
      alert(err.message || 'Server error');
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  });

})();

/* =====================================================
   DELETE UNIVERSITY
===================================================== */
window.deleteUniversity = async function (universityId, universityName) {
  const id = Number(universityId || 0);
  if (!id) return;

  const label = universityName ? String(universityName) : ('#' + id);
  const ok = confirm(
    'Delete university "' + label + '"?\n\n' +
    'This also removes its programs, platforms, and admin assignments.\n' +
    'Universities already used on student applications cannot be deleted.'
  );
  if (!ok) return;

  try {
    const fd = new FormData();
    fd.append('action', 'delete_university');
    fd.append('id', String(id));

    const response = await fetch('settings-handler.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
    });

    const json = await response.json();
    if (!json || !json.ok) {
      throw new Error((json && json.msg) || 'Delete failed');
    }

    if (typeof showToast === 'function') {
      showToast('Deleted', 'University removed', false, 'success');
    } else {
      alert('University deleted');
    }

    setTimeout(() => location.reload(), 400);
  } catch (err) {
    console.error('[settings.js] University delete failed:', err);
    alert(err.message || 'Could not delete university');
  }
};

/* =====================================================
   LOAD UNIVERSITY PLATFORMS (EDIT MODE)
===================================================== */

/**
 * Loads platform IDs for a university and selects them
 * @param {number} universityId
 */
async function loadUniversityPlatforms(universityId) {
  if (!universityId) return;

  try {
    const response = await fetch(
      `get_university_platforms.php?university_id=${encodeURIComponent(universityId)}`,
      { credentials: 'same-origin' }
    );

    if (!response.ok) return;

    const json = await response.json();
    if (!json.ok) return;

    const platformSelect = document.getElementById('uni_platforms');
    if (platformSelect && Array.isArray(json.platform_ids)) {
      Array.from(platformSelect.options).forEach(opt => {
      opt.selected = json.platform_ids.includes(Number(opt.value));
    });
    }

    const adminSelect = document.getElementById('uni_admins');
    if (adminSelect && Array.isArray(json.admin_ids)) {
      Array.from(adminSelect.options).forEach(opt => {
        opt.selected = json.admin_ids.includes(Number(opt.value));
      });
    }

    if (typeof window.pcvcSsSyncUniPickers === 'function') {
      window.pcvcSsSyncUniPickers();
    }

  } catch (err) {
    console.error('[settings.js] Failed to load platforms/admins:', err);
  }
}


  /* =====================================================
     PROGRAM LEVELS
  ===================================================== */

  window.openLevelModal = function (data = null) {
    const form  = $('levelForm');
    const title = $('levelModalTitle');
    const idFld = $('level_id');
    const code  = $('level_abbreviation');
    const name  = $('level_name');

    if (!form || !title || !idFld || !code || !name) {
      console.warn('[settings.js] Level modal DOM missing');
      return;
    }

    form.reset();
    idFld.value = '';
    title.textContent = 'Add Level';

    if (data) {
      title.textContent = 'Edit Level';
      idFld.value = data.id ?? '';
      code.value  = data.abbreviation ?? '';
      name.value  = data.name ?? '';
    }

    if (!levelModalInst) {
      levelModalInst = new bootstrap.Modal($('levelModal'), {
        backdrop: 'static',
        keyboard: false
      });
    }

    levelModalInst.show();
  };

  const levelForm = $('levelForm');
  if (levelForm) {
    levelForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        const res  = await fetch('settings-handler.php', {
          method: 'POST',
          body: new FormData(levelForm),
          credentials: 'same-origin'
        });
        const json = await res.json();
        json.ok ? location.reload() : alert(json.msg || 'Failed to save level');
      } catch (err) {
        console.error('[settings.js] Level save error', err);
        alert('Server error');
      }
    });
  }

  /* =====================================================
     PROGRAMS â€” BACKWARD COMPATIBLE
  ===================================================== */

  window.openProgramModal = function (data = null) {
  const title = $('programModalTitle');
  const uni   = $('program_university');
  const level = $('program_level');
  const input = $('program_input');
  const list  = $('program_list');

  if (!title || !uni || !level || !input || !list) {
    console.warn('[settings.js] Program modal DOM missing');
    return;
  }

  // ðŸ”¥ RESET STATE
  title.textContent = data ? 'Edit Program' : 'Add Program(s)';
  uni.value = '';
  level.value = '';
  input.value = '';
  list.innerHTML = '';
  delete list.dataset.editId;

  if (data) {
    // âœ… EDIT MODE
    uni.value   = data.university_id;
    level.value = data.program_level_id;

    // ðŸ”‘ CREATE CHIP FROM EXISTING PROGRAM
    const badge = document.createElement('span');
    badge.className = 'badge bg-primary d-flex align-items-center gap-1';
    badge.dataset.value = data.program_name.toLowerCase();
    badge.innerHTML = `
      ${data.program_name}
      <button type="button"
              class="btn-close btn-close-white btn-sm ms-1"
              aria-label="Remove"></button>
    `;

    badge.querySelector('button').addEventListener('click', () => {
      badge.remove();
    });

    list.appendChild(badge);

    // ðŸ”’ STORE ID FOR UPDATE
    list.dataset.editId = data.id;
  }

  if (!programModalInst) {
    programModalInst = new bootstrap.Modal($('programModal'), {
      backdrop: 'static',
      keyboard: false
    });
  }

  programModalInst.show();
};

  /* =====================================================
     PROGRAM INPUT â†’ CHIP HANDLER (ENTER KEY)
     SAFE ADD-ON (NO EXISTING LOGIC REMOVED)
  ===================================================== */

  const programInput = $('program_input');
  const programList  = $('program_list');

  if (programInput && programList) {

    programInput.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter') return;
      e.preventDefault();

      const value = programInput.value.trim();
      if (!value) return;

      const lower = value.toLowerCase();

      // prevent duplicates
      const duplicate = [...programList.querySelectorAll('.badge')]
        .some(b => b.dataset.value === lower);

      if (duplicate) {
        programInput.value = '';
        return;
      }

      const badge = document.createElement('span');
      badge.className = 'badge bg-primary d-flex align-items-center gap-1';
      badge.dataset.value = lower;
      badge.innerHTML = `
        ${value}
        <button type="button"
                class="btn-close btn-close-white btn-sm ms-1"
                aria-label="Remove"></button>
      `;

      badge.querySelector('button').addEventListener('click', () => {
        badge.remove();
      });

      programList.appendChild(badge);
      programInput.value = '';
    });
  }
/* =====================================================
   SAVE PROGRAM(S)
===================================================== */

const saveBtn = $('saveProgramsBtn');
if (saveBtn) {
  saveBtn.addEventListener('click', async () => {

    const uni   = $('program_university')?.value;
    const level = $('program_level')?.value;
    const mode  =
      document.querySelector('input[name="program_mode"]:checked')?.value || 'manual';

    const input = $('program_input');
    const list  = $('program_list');

    /* ===============================
       COLLECT PROGRAMS
    =============================== */
    const programs = [];

    // Auto-convert typed input → chip
    if (input && list && input.value.trim()) {
      const value = input.value.trim();
      const lower = value.toLowerCase();

      const duplicate = [...list.querySelectorAll('.badge')]
        .some(b => b.dataset.value === lower);

      if (!duplicate) {
        const badge = document.createElement('span');
        badge.className = 'badge bg-primary d-flex align-items-center gap-1';
        badge.dataset.value = lower;
        badge.innerHTML = `
          ${value}
          <button type="button"
                  class="btn-close btn-close-white btn-sm ms-1"></button>
        `;
        badge.querySelector('button').onclick = () => badge.remove();
        list.appendChild(badge);
      }
      input.value = '';
    }

    // Read chips
    list?.querySelectorAll('.badge').forEach(b => {
      programs.push(b.textContent.replace('×', '').trim());
    });

    /* ===============================
       VALIDATION
    =============================== */
    if (!uni) {
      alert('Please select a university');
      return;
    }

    if (!programs.length) {
      alert('Please add at least one program');
      return;
    }

    if (mode === 'manual' && !level) {
      alert('Please select a program level');
      return;
    }

    saveBtn.disabled = true;

    try {

      /* ===============================
         MANUAL MODE → FormData
      =============================== */
      if (mode === 'manual') {

        const fd = new FormData();

        if (list?.dataset.editId) {
          fd.append('action', 'update_program');
          fd.append('id', list.dataset.editId);
        } else {
          fd.append('action', 'save_program');
        }

        fd.append('university_id', uni);
        fd.append('level_id', level);
        programs.forEach(p => fd.append('programs[]', p));

        const res  = await fetch('settings-handler.php', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        });

        const json = await res.json();
        if (!json.ok) throw new Error(json.msg || 'Save failed');

      }

      /* ===============================
         AI MODE → JSON
      =============================== */
      else {

        const res = await fetch('ai_save_programs.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            university_id: uni,
            programs
          })
        });

        const json = await res.json();
        if (!json.ok) {
          console.error('Rejected programs:', json.invalid);
          throw new Error(json.msg || 'AI save failed');
        }

      }

      location.reload();

    } catch (err) {
      console.error('[settings.js] Program save error', err);
      alert(err.message || 'Server error');
    } finally {
      saveBtn.disabled = false;
    }
  });
}


});
