/**
 * System Settings — smart search, filters, searchable multi-selects
 */
(function () {
  'use strict';

  function norm(s) {
    return String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .trim();
  }

  function tokensMatch(haystack, query) {
    const h = norm(haystack);
    const q = norm(query);
    if (!q) return true;
    return q.split(/\s+/).every((t) => t && h.includes(t));
  }

  function bindSearchClear(input, wrap) {
    if (!input || !wrap) return;
    const clearBtn = wrap.querySelector('.ss-search-clear');
    const sync = () => {
      wrap.classList.toggle('has-value', !!input.value.trim());
    };
    input.addEventListener('input', sync);
    sync();
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        input.value = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.focus();
      });
    }
  }

  /* ---------- Universities ---------- */
  function initUniversitySmartList() {
    const pane = document.getElementById('tab-universities');
    if (!pane) return;

    const input = document.getElementById('uniSmartSearch');
    const meta = document.getElementById('uniSmartMeta');
    const empty = document.getElementById('uniSmartEmpty');
    const rows = Array.from(pane.querySelectorAll('tbody tr[data-uni-row]'));
    const chips = Array.from(pane.querySelectorAll('.ss-chip[data-filter]'));
    let activeFilter = 'all';

    bindSearchClear(input, input?.closest('.ss-search-wrap'));

    chips.forEach((chip) => {
      chip.addEventListener('click', () => {
        chips.forEach((c) => c.classList.remove('active'));
        chip.classList.add('active');
        activeFilter = chip.dataset.filter || 'all';
        apply();
      });
    });

    function apply() {
      const q = input ? input.value : '';
      let shown = 0;
      rows.forEach((row, idx) => {
        const blob = row.dataset.search || '';
        const region = row.dataset.region || '';
        const hasAdmin = row.dataset.hasAdmin === '1';
        const hasPlatform = row.dataset.hasPlatform === '1';

        let ok = tokensMatch(blob, q);
        if (ok && activeFilter === 'no-admin') ok = !hasAdmin;
        if (ok && activeFilter === 'has-admin') ok = hasAdmin;
        if (ok && activeFilter === 'no-platform') ok = !hasPlatform;
        if (ok && activeFilter.startsWith('region:')) {
          ok = norm(region) === norm(activeFilter.slice(7));
        }

        row.classList.toggle('is-hidden', !ok);
        if (ok) {
          shown += 1;
          const numCell = row.querySelector('[data-row-num]');
          if (numCell) numCell.textContent = String(shown);
        }
      });

      if (meta) {
        meta.innerHTML = `Showing <strong>${shown}</strong> of <strong>${rows.length}</strong> universities`;
      }
      if (empty) empty.classList.toggle('show', shown === 0);
    }

    if (input) input.addEventListener('input', apply);
    apply();
  }

  /* ---------- Program levels ---------- */
  function initLevelSmartList() {
    const pane = document.getElementById('tab-levels');
    if (!pane) return;
    const input = document.getElementById('levelSmartSearch');
    const meta = document.getElementById('levelSmartMeta');
    const empty = document.getElementById('levelSmartEmpty');
    const rows = Array.from(pane.querySelectorAll('tbody tr[data-level-row]'));

    bindSearchClear(input, input?.closest('.ss-search-wrap'));

    function apply() {
      const q = input ? input.value : '';
      let shown = 0;
      rows.forEach((row) => {
        const ok = tokensMatch(row.dataset.search || '', q);
        row.classList.toggle('is-hidden', !ok);
        if (ok) {
          shown += 1;
          const numCell = row.querySelector('[data-row-num]');
          if (numCell) numCell.textContent = String(shown);
        }
      });
      if (meta) {
        meta.innerHTML = `Showing <strong>${shown}</strong> of <strong>${rows.length}</strong> levels`;
      }
      if (empty) empty.classList.toggle('show', shown === 0);
    }

    if (input) input.addEventListener('input', apply);
    apply();
  }

  /* ---------- Programs (enhanced) ---------- */
  function initProgramSmartList() {
    const pane = document.getElementById('tab-programs');
    if (!pane) return;
    const input = document.getElementById('programSearch');
    const meta = document.getElementById('programSmartMeta');
    const empty = document.getElementById('programSmartEmpty');
    const cards = Array.from(pane.querySelectorAll('[data-uni-card]'));

    bindSearchClear(input, input?.closest('.ss-search-wrap'));

    function apply() {
      const q = input ? input.value : '';
      let shownPrograms = 0;
      let shownCards = 0;
      let totalPrograms = 0;

      cards.forEach((card) => {
        const items = Array.from(card.querySelectorAll('[data-program]'));
        totalPrograms += items.length;
        let visibleInCard = 0;
        items.forEach((item) => {
          const text =
            (item.dataset.name || '') +
            ' ' +
            (item.dataset.university || '') +
            ' ' +
            (item.dataset.level || '');
          const ok = tokensMatch(text, q);
          item.style.display = ok ? '' : 'none';
          if (ok) {
            visibleInCard += 1;
            shownPrograms += 1;
          }
        });
        const hide = visibleInCard === 0;
        card.classList.toggle('is-hidden', hide);
        if (!hide) shownCards += 1;
      });

      if (meta) {
        meta.innerHTML = `Showing <strong>${shownPrograms}</strong> programs across <strong>${shownCards}</strong> universities`;
      }
      if (empty) empty.classList.toggle('show', shownPrograms === 0 && totalPrograms > 0);
    }

    if (input) input.addEventListener('input', apply);
    apply();
  }

  /* ---------- Searchable multi-select ---------- */
  function enhanceMultiSelect(selectEl, opts) {
    if (!selectEl || selectEl.dataset.ssEnhanced === '1') return;
    selectEl.dataset.ssEnhanced = '1';
    selectEl.classList.add('d-none');

    const wrap = document.createElement('div');
    wrap.className = 'ss-ms';
    wrap.innerHTML = `
      <div class="ss-ms-search-wrap">
        <i class="bi bi-search"></i>
        <input type="text" class="ss-ms-search" placeholder="${opts.placeholder || 'Search…'}" autocomplete="off">
      </div>
      <div class="ss-ms-list"></div>
      <div class="ss-ms-selected"></div>
    `;
    selectEl.parentNode.insertBefore(wrap, selectEl.nextSibling);

    const list = wrap.querySelector('.ss-ms-list');
    const selectedBox = wrap.querySelector('.ss-ms-selected');
    const search = wrap.querySelector('.ss-ms-search');

    function rebuildList() {
      list.innerHTML = '';
      Array.from(selectEl.options).forEach((opt) => {
        const label = document.createElement('label');
        label.className = 'ss-ms-item';
        label.dataset.search = norm(opt.textContent);
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.value = opt.value;
        cb.checked = opt.selected;
        const span = document.createElement('span');
        span.textContent = opt.textContent;
        label.appendChild(cb);
        label.appendChild(span);
        cb.addEventListener('change', () => {
          opt.selected = cb.checked;
          renderSelected();
          selectEl.dispatchEvent(new Event('change', { bubbles: true }));
        });
        list.appendChild(label);
      });
      renderSelected();
    }

    function renderSelected() {
      selectedBox.innerHTML = '';
      Array.from(selectEl.selectedOptions).forEach((opt) => {
        const pill = document.createElement('span');
        pill.className = 'ss-ms-pill';
        const text = document.createElement('span');
        text.textContent = opt.textContent;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('aria-label', 'Remove');
        btn.innerHTML = '&times;';
        btn.addEventListener('click', () => {
          opt.selected = false;
          const cb = Array.from(list.querySelectorAll('input[type="checkbox"]')).find(
            (el) => String(el.value) === String(opt.value)
          );
          if (cb) cb.checked = false;
          renderSelected();
          selectEl.dispatchEvent(new Event('change', { bubbles: true }));
        });
        pill.appendChild(text);
        pill.appendChild(btn);
        selectedBox.appendChild(pill);
      });
    }

    search.addEventListener('input', () => {
      const q = search.value;
      list.querySelectorAll('.ss-ms-item').forEach((item) => {
        item.classList.toggle('is-hidden', !tokensMatch(item.dataset.search || '', q));
      });
    });

    // Keep UI in sync when options are programmatically selected (edit load)
    const syncFromSelect = () => {
      list.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
        const opt = Array.from(selectEl.options).find((o) => String(o.value) === String(cb.value));
        if (opt) cb.checked = opt.selected;
      });
      renderSelected();
    };

    selectEl.addEventListener('ss:refresh', () => {
      rebuildList();
    });
    selectEl.addEventListener('ss:sync', syncFromSelect);

    rebuildList();
  }

  function enhanceSearchableSelect(selectEl, placeholder) {
    if (!selectEl || selectEl.dataset.ssSearchable === '1') return;
    selectEl.dataset.ssSearchable = '1';

    const wrap = document.createElement('div');
    wrap.className = 'ss-ms mb-0';
    wrap.innerHTML = `
      <div class="ss-ms-search-wrap">
        <i class="bi bi-search"></i>
        <input type="text" class="ss-ms-search" placeholder="${placeholder || 'Search…'}" autocomplete="off">
      </div>
    `;
    selectEl.parentNode.insertBefore(wrap, selectEl);
    const search = wrap.querySelector('.ss-ms-search');
    const optionsCache = Array.from(selectEl.options).map((o) => ({
      value: o.value,
      text: o.textContent,
      selected: o.selected,
    }));

    function rebuild(filter) {
      const current = selectEl.value;
      selectEl.innerHTML = '';
      optionsCache.forEach((o) => {
        if (o.value === '' || tokensMatch(o.text, filter || '')) {
          const opt = document.createElement('option');
          opt.value = o.value;
          opt.textContent = o.text;
          selectEl.appendChild(opt);
        }
      });
      if ([...selectEl.options].some((o) => o.value === current)) {
        selectEl.value = current;
      }
    }

    search.addEventListener('input', () => rebuild(search.value));
  }

  function initUniversityModalPickers() {
    const platforms = document.getElementById('uni_platforms');
    const admins = document.getElementById('uni_admins');
    enhanceMultiSelect(platforms, { placeholder: 'Search platforms…' });
    enhanceMultiSelect(admins, { placeholder: 'Search staff by name or email…' });
    enhanceSearchableSelect(document.getElementById('program_university'), 'Filter universities…');
    enhanceSearchableSelect(document.getElementById('program_level'), 'Filter levels…');
    enhanceSearchableSelect(document.getElementById('uni_region'), 'Filter regions…');
    enhanceSearchableSelect(document.getElementById('uni_country'), 'Filter countries…');
  }

  document.addEventListener('DOMContentLoaded', () => {
    initUniversitySmartList();
    initLevelSmartList();
    initProgramSmartList();
    initUniversityModalPickers();

    const uniModal = document.getElementById('universityModal');
    if (uniModal) {
      uniModal.addEventListener('shown.bs.modal', () => {
        setTimeout(() => {
          document.getElementById('uni_platforms')?.dispatchEvent(new Event('ss:sync'));
          document.getElementById('uni_admins')?.dispatchEvent(new Event('ss:sync'));
        }, 120);
        setTimeout(() => {
          document.getElementById('uni_platforms')?.dispatchEvent(new Event('ss:sync'));
          document.getElementById('uni_admins')?.dispatchEvent(new Event('ss:sync'));
        }, 400);
      });
    }
  });

  window.pcvcSsSyncUniPickers = function () {
    document.getElementById('uni_platforms')?.dispatchEvent(new Event('ss:sync'));
    document.getElementById('uni_admins')?.dispatchEvent(new Event('ss:sync'));
  };
})();
