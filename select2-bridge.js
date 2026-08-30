(function () {
  'use strict';

  function applySelect2(select, isMultiple = false) {
    if (!window.jQuery || !jQuery.fn.select2 || !select) return;

    // destroy if already initialized
    if ($(select).hasClass('select2-hidden-accessible')) {
      $(select).select2('destroy');
    }

    $(select).select2({
      theme: 'bootstrap-5',
      width: '100%',
      placeholder:
        select.dataset.placeholder ||
        select.options[0]?.text ||
        'Select',
      allowClear: !isMultiple,
      closeOnSelect: !isMultiple
    });

    // sync disabled state
    $(select).prop('disabled', select.disabled);
  }

  function initAll() {
    document
      .querySelectorAll('.select-smart, .country-select')
      .forEach(el => {
        applySelect2(el, el.multiple);
      });
  }

  /* ===============================
     INITIAL LOAD
  =============================== */
  document.addEventListener('DOMContentLoaded', initAll);

  /* ===============================
     RE-APPLY AFTER DYNAMIC CHANGES
     (application.js resets selects)
  =============================== */
  ['region', 'universities', 'programLevel'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;

    el.addEventListener('change', () => {
      setTimeout(initAll, 0);
    });
  });
})();
