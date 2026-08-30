<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/research_elearning_schema.php';

pcvc_ensure_research_elearning_schema($conn);

$docFields = pcvc_research_elearning_doc_fields();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Research Project for E-Learning</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root { --primary: #427431; --secondary: #3661B9; }
    body { background: #f4f6f9; font-family: 'Segoe UI', system-ui, sans-serif; }
    .page-header {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff; padding: 1.25rem 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;
    }
    .search-card, .results-card {
      background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 1.25rem;
    }
    .result-row:hover { background: #f8fafc; }
    #manageModal .modal-header { background: linear-gradient(135deg, var(--primary), #2d5a27); color: #fff; }
    #manageModal .modal-dialog { max-width: 960px; }
    .doc-card {
      border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem; margin-bottom: .75rem;
      background: #fafbfc;
    }
    .doc-card.uploaded { border-color: #86efac; background: #f0fdf4; }
    .doc-card.missing { border-color: #fecaca; background: #fff7f7; }
    .status-pill { font-size: .75rem; font-weight: 600; }
    .search-hint { font-size: .8rem; color: #64748b; }
    #searchSpinner { display: none; }
    .results-loading { opacity: .55; pointer-events: none; }
    .progress-label { font-size: .85rem; color: #475569; }
    .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }
    .loading-overlay {
      position: fixed; inset: 0; background: rgba(255,255,255,.75);
      display: none; align-items: center; justify-content: center; z-index: 10000;
    }
  </style>
</head>
<body>
<div class="container-fluid py-4 px-3 px-md-4">

  <div class="page-header">
    <h4 class="mb-1 fw-bold"><i class="bi bi-journal-bookmark me-2"></i>Research Project for E-Learning</h4>
    <p class="mb-0 small opacity-90">Search credit transfer and UPAFA students, upload research deliverables (PDF/Word), and track completion status.</p>
  </div>

  <div class="search-card mb-4">
    <div class="row g-3 align-items-end">
      <div class="col-md-12">
        <label class="form-label fw-semibold small text-muted">Search student <span class="search-hint">— live results as you type</span></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" id="searchInput" class="form-control" placeholder="Name, email, phone, user ID..." autocomplete="off">
          <span class="input-group-text" id="searchSpinner"><span class="spinner-border spinner-border-sm text-primary"></span></span>
        </div>
      </div>
    </div>
  </div>

  <div class="results-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-bold mb-0">Credit Transfer & UPAFA Students</h6>
      <span class="text-muted small" id="resultCount">—</span>
    </div>
    <div id="resultsEmpty" class="text-center text-muted py-5">
      <i class="bi bi-people display-6 d-block mb-2 opacity-50"></i>
      Start typing to search students, or browse recent applicants.
    </div>
    <div class="table-responsive d-none" id="resultsTableWrap">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Status</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody id="resultsBody"></tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="manageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <form id="manageForm" class="modal-content" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-folder2-open me-2"></i>Research Deliverables</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body px-4 py-3">
        <input type="hidden" id="manage_student_id" name="student_id">
        <input type="hidden" id="manage_source_table" name="source_table">

        <div class="row mb-3">
          <div class="col-md-6">
            <div class="small text-muted">Student</div>
            <div class="fw-semibold" id="manage_student_name">—</div>
          </div>
          <div class="col-md-2">
            <div class="small text-muted">Program</div>
            <div class="fw-semibold" id="manage_program">—</div>
          </div>
          <div class="col-md-2">
            <div class="small text-muted">Reference</div>
            <div><code id="manage_user_id">—</code></div>
          </div>
          <div class="col-md-2">
            <div class="small text-muted">Email</div>
            <div id="manage_student_email">—</div>
          </div>
        </div>

        <div class="card border-0 bg-light mb-3">
          <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="progress-label fw-semibold">Document completion</span>
              <span id="progressText" class="badge text-bg-secondary status-pill">0 / 6</span>
            </div>
            <div class="progress" style="height: 10px;">
              <div id="progressBar" class="progress-bar bg-success" style="width: 0%"></div>
            </div>
            <div id="missingList" class="small text-danger mt-2"></div>
            <div id="trackingNote" class="small text-muted mt-2"></div>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Overall status</label>
            <select class="form-select" id="overall_status" name="overall_status">
              <option value="not_started">Not started</option>
              <option value="in_progress">In progress</option>
              <option value="submitted">Submitted</option>
              <option value="completed">Completed</option>
              <option value="on_hold">On hold</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label fw-semibold small">Admin notes / tracking</label>
            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="2" placeholder="Track follow-ups, deadlines, or why documents are missing…"></textarea>
          </div>
        </div>

        <h6 class="fw-bold mb-3">Upload documents <span class="text-muted fw-normal small">(PDF or Word, max 25MB each)</span></h6>
        <div id="docFieldsWrap">
          <?php foreach ($docFields as $key => $label): ?>
          <div class="doc-card missing" data-doc-key="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
              <div>
                <div class="fw-semibold"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="small doc-status-text text-muted">Not uploaded yet</div>
                <div class="small doc-file-link mt-1"></div>
              </div>
              <div class="text-end" style="min-width: 220px;">
                <input type="file" class="form-control form-control-sm" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-success" id="saveAllBtn"><i class="bi bi-cloud-upload me-1"></i>Save all</button>
      </div>
    </form>
  </div>
</div>

<div class="loading-overlay" id="loadingOverlay">
  <div class="spinner-border text-success" role="status"></div>
</div>
<div class="toast-container" id="toastWrap"></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  let searchTimer = null;
  const statusLabels = {
    not_started: 'Not started',
    in_progress: 'In progress',
    submitted: 'Submitted',
    completed: 'Completed',
    on_hold: 'On hold'
  };

  function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  function showToast(msg, type = 'success') {
    const id = 't' + Date.now();
    const bg = type === 'success' ? 'text-bg-success' : 'text-bg-danger';
    $('#toastWrap').append(`
      <div id="${id}" class="toast align-items-center ${bg} border-0" role="alert">
        <div class="d-flex"><div class="toast-body">${escHtml(msg)}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>
      </div>`);
    const el = document.getElementById(id);
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
  }

  function setSearchLoading(on) {
    $('#searchSpinner').toggle(on);
    $('.results-card').toggleClass('results-loading', on);
  }

  function statusBadgeHtml(r) {
    const badge = r.status_badge || 'secondary';
    const label = r.status_label || 'Not started';
    const uploaded = Number(r.uploaded_count || 0);
    const icon = uploaded > 0 ? 'bi-check-circle-fill' : 'bi-circle';
    return `<span class="badge text-bg-${escHtml(badge)} status-pill"><i class="bi ${icon} me-1"></i>${escHtml(label)}</span>`;
  }

  function renderResults(rows) {
    if (!rows.length) {
      $('#resultsTableWrap').addClass('d-none');
      $('#resultsEmpty').removeClass('d-none').html('<div class="text-muted py-4">No students matched your search.</div>');
      $('#resultCount').text('0 results');
      return;
    }

    let html = '';
    rows.forEach(r => {
      const program = r.program || (r.table === 'upafa_registrations' ? 'UPAFA' : 'Credit Transfer');
      html += `<tr class="result-row" data-student-key="${escHtml(r.table)}:${r.id}">
        <td class="fw-semibold">${escHtml(r.full_name)}</td>
        <td>${escHtml(r.email)}</td>
        <td>${escHtml(r.phone)}</td>
        <td class="research-status-cell">${statusBadgeHtml(r)}</td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-success btn-manage"
            data-id="${r.id}" data-table="${escHtml(r.table)}"
            data-program="${escHtml(program)}"
            data-name="${escHtml(r.full_name)}"
            data-email="${escHtml(r.email)}" data-ref="${escHtml(r.ref)}">
            <i class="bi bi-folder-plus"></i> Manage Research
          </button>
        </td>
      </tr>`;
    });

    $('#resultsBody').html(html);
    $('#resultsTableWrap').removeClass('d-none');
    $('#resultsEmpty').addClass('d-none');
    $('#resultCount').text(rows.length + ' result(s)');
  }

  function doSearch() {
    const q = ($('#searchInput').val() || '').trim();
    setSearchLoading(true);
    $.getJSON('search-research-elearning.php', { q })
      .done(data => {
        setSearchLoading(false);
        if (!data || !data.success) return;
        renderResults(data.results || []);
      })
      .fail(() => setSearchLoading(false));
  }

  function scheduleSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(doSearch, 280);
  }

  function applyProgress(progress, record) {
    const uploaded = Number(progress?.uploaded_count || 0);
    const total = Number(progress?.total_count || 6);
    const pct = Number(progress?.completion_pct || 0);
    $('#progressText').text(`${uploaded} / ${total} uploaded`);
    $('#progressBar').css('width', pct + '%');

    const missing = (progress?.missing || []).map(m => m.label);
    if (missing.length) {
      $('#missingList').text('Missing: ' + missing.join(', '));
    } else {
      $('#missingList').text('All required documents are on file.');
    }

    let track = '';
    if (record?.last_status_check_at) {
      track += 'Last checked: ' + record.last_status_check_at;
    }
    if (record?.updated_at) {
      track += (track ? ' · ' : '') + 'Updated: ' + record.updated_at;
    }
    if (!uploaded) {
      track += (track ? ' · ' : '') + 'Status tracked — no documents uploaded yet.';
    }
    $('#trackingNote').text(track);
  }

  function applyDocuments(documents) {
    (documents || []).forEach(doc => {
      const card = $(`.doc-card[data-doc-key="${doc.key}"]`);
      const statusEl = card.find('.doc-status-text');
      const linkEl = card.find('.doc-file-link');
      card.toggleClass('uploaded', !!doc.uploaded).toggleClass('missing', !doc.uploaded);
      if (doc.uploaded) {
        statusEl.text('On file');
        linkEl.html(`<a href="${escHtml(doc.path)}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> ${escHtml(doc.file_name)}</a>`);
      } else {
        statusEl.text('Not uploaded yet');
        linkEl.empty();
      }
      card.find('input[type="file"]').val('');
    });
  }

  function openManageModal(student) {
    $('#manage_student_id').val(student.id);
    $('#manage_source_table').val(student.table || 'credit_transfer_applications');
    $('#manage_student_name').text(student.name || '—');
    $('#manage_student_email').text(student.email || '—');
    $('#manage_user_id').text(student.ref || '—');
    $('#manage_program').text(student.program || '—');
    $('#admin_notes').val('');
    $('#overall_status').val('not_started');
    applyDocuments([]);
    applyProgress({ uploaded_count: 0, total_count: 6, completion_pct: 0, missing: [] }, {});

    $.getJSON('get-research-elearning.php', {
      student_id: student.id,
      source_table: student.table || 'credit_transfer_applications'
    })
      .done(data => {
        if (!data || !data.success) {
          alert(data?.message || 'Could not load research record');
          return;
        }
        $('#overall_status').val(data.record?.overall_status || 'not_started');
        $('#admin_notes').val(data.record?.admin_notes || '');
        applyDocuments(data.documents || []);
        applyProgress(data.progress || {}, data.record || {});
        new bootstrap.Modal(document.getElementById('manageModal')).show();
      })
      .fail(() => alert('Failed to load research record'));
  }

  $('#searchInput').on('input', scheduleSearch);

  $(document).on('click', '.btn-manage', function () {
    openManageModal({
      id: $(this).data('id'),
      table: $(this).data('table'),
      program: $(this).data('program'),
      name: $(this).data('name'),
      email: $(this).data('email'),
      ref: $(this).data('ref')
    });
  });

  function updateListStatusFromSave(data) {
    const key = `${$('#manage_source_table').val()}:${$('#manage_student_id').val()}`;
    const cell = $(`tr[data-student-key="${key}"] .research-status-cell`);
    if (!cell.length || !data) return;
    cell.html(statusBadgeHtml({
      status_badge: data.status_badge,
      status_label: data.status_label,
      uploaded_count: data.progress?.uploaded_count || 0
    }));
  }

  $('#manageForm').on('submit', function (e) {
    e.preventDefault();
    const form = this;
    const fd = new FormData(form);
    const $saveBtn = $('#saveAllBtn');
    $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving…');
    $('#loadingOverlay').show();

    $.ajax({
      url: 'save-research-elearning.php',
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json'
    }).done(data => {
      $('#loadingOverlay').hide();
      $saveBtn.prop('disabled', false).html('<i class="bi bi-cloud-upload me-1"></i>Save all');
      if (!data || !data.success) {
        alert(data?.message || 'Save failed');
        return;
      }
      showToast(data.message || 'Saved to database');
      if (data.overall_status) {
        $('#overall_status').val(data.overall_status);
      }
      applyDocuments(data.documents || []);
      applyProgress(data.progress || {}, data.record || {});
      updateListStatusFromSave(data);
      form.querySelectorAll('input[type="file"]').forEach(inp => { inp.value = ''; });
    }).fail(xhr => {
      $('#loadingOverlay').hide();
      $saveBtn.prop('disabled', false).html('<i class="bi bi-cloud-upload me-1"></i>Save all');
      let msg = 'Save failed';
      try { msg = JSON.parse(xhr.responseText).message || msg; } catch (err) {}
      alert(msg);
    });
  });

  doSearch();
})();
</script>
</body>
</html>
