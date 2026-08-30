<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/role.php';
require_once __DIR__ . '/../helpers/staff_contract_schema.php';
require_once __DIR__ . '/../helpers/staff_contract_word.php';

pcvc_require_superadmin($conn);

pcvc_staff_contract_ensure_schema($conn);
$useDocxPreview = pcvc_staff_contract_use_docx_preview();

$sql = "
    SELECT
        a.id,
        a.full_name,
        a.email,
        a.role,
        a.position,
        c.id AS contract_id,
        c.status,
        c.contract_title,
        c.source_docx_path,
        c.source_pdf_path,
        c.signed_pdf_path,
        c.pdf_path,
        c.signed_docx_path,
        c.signed_at,
        c.uploaded_at
    FROM admins a
    LEFT JOIN employment_contracts c ON c.admin_id = a.id
    ORDER BY a.full_name ASC
";
$staffRows = $conn->query($sql)?->fetch_all(MYSQLI_ASSOC) ?? [];
$totalStaff = count($staffRows);
$awaitingCount = 0;
foreach ($staffRows as $row) {
    if (pcvc_staff_contract_is_awaiting_signature($row)) {
        $awaitingCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Employment Contracts</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root { --primary: #427431; --secondary: #3661B9; }
    body { background: #f4f6f9; font-family: 'Segoe UI', system-ui, sans-serif; }
    .page-header {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;
    }
    .card-panel { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 1.25rem; }
    .upload-row { border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem; margin-bottom: .75rem; }
    .upload-row.signed { border-color: #86efac; background: #f0fdf4; }
    .upload-row.pending { border-color: #fde68a; background: #fffbeb; }
    .upload-row.empty { border-color: #e2e8f0; background: #fafbfc; }
    .upload-row.search-hidden { display: none !important; }
    .search-wrap {
      position: relative; max-width: 520px;
    }
    .search-wrap .bi-search {
      position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
      color: #64748b; pointer-events: none;
    }
    .search-wrap input {
      padding-left: 2.25rem; border-radius: 10px; border: 1px solid #cbd5e1;
    }
    .search-wrap input:focus {
      border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(54, 97, 185, .15);
    }
    #searchEmpty { display: none; }
  </style>
</head>
<body>
<div class="container-fluid py-4 px-3 px-md-4">
  <div class="page-header">
    <h4 class="mb-1 fw-bold"><i class="bi bi-file-earmark-person me-2"></i>Staff Employment Contracts</h4>
    <p class="mb-0 small opacity-90">
      Upload a Word (.docx) contract per staff member. Details are auto-filled from their profile; staff review and e-sign.
    </p>
  </div>

  <div class="card-panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
      <h6 class="fw-bold mb-0">All staff contracts</h6>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnNotifyAllPending"
          data-awaiting-count="<?= (int) $awaitingCount ?>"
          title="Send reminder only to staff with contracts awaiting signature">
          <i class="bi bi-envelope"></i> Email <?= $awaitingCount ?> awaiting signature
        </button>
        <span class="text-muted small" id="staffCount"><?= $awaitingCount ?> awaiting · <?= $totalStaff ?> staff</span>
      </div>
    </div>

    <div class="search-wrap mb-3">
      <i class="bi bi-search"></i>
      <input type="search" id="staffSearch" class="form-control"
        placeholder="Search by name, email, role, position, or contract status…" autocomplete="off">
    </div>

    <?php if (!$staffRows): ?>
      <p class="text-muted mb-0">No staff accounts found.</p>
    <?php endif; ?>

    <div id="staffList">
    <?php foreach ($staffRows as $row):
      $status = pcvc_staff_contract_row_status($row);
      $rowClass = $status['code'] === 'signed' ? 'signed' : ($status['code'] === 'pending_signature' ? 'pending' : 'empty');
      $staffId = (int) $row['id'];
      $hasTemplate = !empty($row['source_docx_path']) || !empty($row['source_pdf_path']);
      $searchBlob = strtolower(implode(' ', [
        (string) ($row['full_name'] ?? ''),
        (string) ($row['email'] ?? ''),
        (string) ($row['role'] ?? ''),
        (string) ($row['position'] ?? ''),
        (string) ($row['contract_title'] ?? ''),
        $status['label'],
        $status['code'],
      ]));
    ?>
    <div class="upload-row <?= $rowClass ?>" data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES) ?>">
      <div class="row g-3 align-items-center">
        <div class="col-lg-3">
          <div class="fw-semibold"><?= htmlspecialchars((string) $row['full_name']) ?></div>
          <div class="small text-muted"><?= htmlspecialchars((string) $row['email']) ?></div>
          <div class="small text-muted"><?= htmlspecialchars((string) ($row['position'] ?: $row['role'])) ?></div>
        </div>
        <div class="col-lg-2">
          <span class="badge text-bg-<?= $status['badge'] ?>"><?= htmlspecialchars($status['label']) ?></span>
          <?php if ($status['code'] === 'signed' && !empty($row['signed_at'])): ?>
            <div class="small text-muted mt-1">Signed <?= htmlspecialchars(date('Y-m-d H:i', strtotime((string) $row['signed_at']))) ?></div>
          <?php elseif ($status['code'] === 'pending_signature' && !empty($row['uploaded_at'])): ?>
            <div class="small text-muted mt-1">Uploaded <?= htmlspecialchars(date('Y-m-d H:i', strtotime((string) $row['uploaded_at']))) ?></div>
          <?php endif; ?>
        </div>
        <div class="col-lg-4">
          <form class="upload-form" enctype="multipart/form-data">
            <input type="hidden" name="staff_id" value="<?= $staffId ?>">
            <div class="mb-2">
              <input type="text" class="form-control form-control-sm" name="contract_title"
                placeholder="Contract title (optional)"
                value="<?= htmlspecialchars((string) ($row['contract_title'] ?? '')) ?>">
            </div>
            <input type="file" class="form-control form-control-sm" name="contract_docx"
              accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
          </form>
        </div>
        <div class="col-lg-3 text-lg-end">
          <button type="button" class="btn btn-success btn-sm btn-upload mb-1" data-staff-id="<?= $staffId ?>">
            <i class="bi bi-cloud-upload"></i> Upload Word
          </button>
          <?php if ($hasTemplate): ?>
            <?php if ($status['code'] !== 'signed'): ?>
            <a class="btn btn-outline-primary btn-sm mb-1" target="_blank"
              href="<?= $useDocxPreview
                ? 'contract-docx-viewer.php?staff_id=' . $staffId . '&type=source&ts=' . time()
                : 'view-staff-contract-pdf.php?staff_id=' . $staffId . '&type=source&ts=' . time() ?>"
              title="Preview before staff signs (no employee signature)">View filled <?= $useDocxPreview ? 'Word' : 'PDF' ?></a>
            <?php endif; ?>
            <?php if ($status['code'] === 'signed' && $useDocxPreview): ?>
            <a class="btn btn-outline-success btn-sm mb-1" target="_blank"
              href="contract-docx-viewer.php?staff_id=<?= $staffId ?>&type=signed&ts=<?= time() ?>"
              title="Full signed contract with employee signature">View signed Word</a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary btn-sm mb-1 btn-regenerate-contract"
              data-staff-id="<?= $staffId ?>"
              data-mode="preview"
              title="Rebuild contract from staff profile (keeps Word formatting, bullets, stamp)">
              <i class="bi bi-arrow-clockwise"></i> Regenerate <?= $useDocxPreview ? 'Word' : 'PDF' ?>
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm mb-1 btn-delete-contract"
              data-staff-id="<?= $staffId ?>"
              data-staff-name="<?= htmlspecialchars((string) $row['full_name'], ENT_QUOTES) ?>"
              data-is-signed="<?= $status['code'] === 'signed' ? '1' : '0' ?>">
              <i class="bi bi-trash"></i> Delete
            </button>
          <?php endif; ?>
          <?php if ($status['code'] === 'pending_signature' && $hasTemplate): ?>
            <button type="button" class="btn btn-outline-info btn-sm mb-1 btn-notify-contract"
              data-staff-id="<?= $staffId ?>"
              data-staff-name="<?= htmlspecialchars((string) $row['full_name'], ENT_QUOTES) ?>">
              <i class="bi bi-envelope"></i> Email reminder
            </button>
          <?php endif; ?>
          <?php if ($status['code'] === 'signed'): ?>
            <button type="button" class="btn btn-outline-warning btn-sm mb-1 btn-regenerate-contract"
              data-staff-id="<?= $staffId ?>"
              data-mode="signed"
              title="Rebuild signed PDF with current profile data and saved signature">
              <i class="bi bi-arrow-repeat"></i> Regenerate signed
            </button>
            <a class="btn btn-primary btn-sm mb-1" target="_blank"
              href="download-staff-contract.php?staff_id=<?= $staffId ?>&type=signed<?= $useDocxPreview ? '&format=docx' : '' ?>">Download signed</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    </div>

    <p class="text-muted text-center py-4 mb-0" id="searchEmpty">
      <i class="bi bi-search me-1"></i> No staff match your search.
    </p>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function () {
  const total = <?= (int) $totalStaff ?>;
  const $search = $('#staffSearch');
  const $rows = $('#staffList .upload-row');
  const $count = $('#staffCount');
  const $empty = $('#searchEmpty');

  function runSearch() {
    const q = $search.val().trim().toLowerCase();
    const terms = q ? q.split(/\s+/).filter(Boolean) : [];
    let visible = 0;

    $rows.each(function () {
      const hay = String($(this).data('search') || '');
      const match = terms.length === 0 || terms.every(t => hay.indexOf(t) !== -1);
      $(this).toggleClass('search-hidden', !match);
      if (match) visible++;
    });

    if (terms.length === 0) {
      $count.text(total + ' staff');
    } else {
      $count.text(visible + ' of ' + total + ' staff');
    }
    $empty.toggle(terms.length > 0 && visible === 0);
  }

  let timer = null;
  $search.on('input', function () {
    clearTimeout(timer);
    timer = setTimeout(runSearch, 120);
  });
  runSearch();

  $('#btnNotifyAllPending').on('click', function () {
    const awaiting = $(this).data('awaiting-count') || 0;
    if (awaiting <= 0) {
      alert('No staff with contracts awaiting signature.');
      return;
    }
    if (!confirm('Send contract reminder emails to ' + awaiting + ' staff awaiting signature?')) {
      return;
    }
    const btn = $(this);
    const btnLabel = '<i class="bi bi-envelope"></i> Email ' + awaiting + ' awaiting signature';
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Sending…');
    $.ajax({
      url: 'notify-staff-contract.php',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ all_pending: true }),
      dataType: 'json'
    }).done(function (data) {
      btn.prop('disabled', false).html(btnLabel);
      alert(data?.message || (data?.success ? 'Done' : 'Failed'));
    }).fail(function (xhr) {
      btn.prop('disabled', false).html(btnLabel);
      let msg = 'Email failed';
      try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
      alert(msg);
    });
  });

  $('.btn-notify-contract').on('click', function () {
    const staffId = $(this).data('staff-id');
    const staffName = $(this).data('staff-name') || 'this staff member';
    if (!confirm('Send contract reminder email to ' + staffName + '?')) {
      return;
    }
    const btn = $(this);
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.ajax({
      url: 'notify-staff-contract.php',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ staff_id: staffId }),
      dataType: 'json'
    }).done(function (data) {
      btn.prop('disabled', false).html('<i class="bi bi-envelope"></i> Email reminder');
      alert(data?.message || (data?.success ? 'Sent' : 'Failed'));
    }).fail(function (xhr) {
      btn.prop('disabled', false).html('<i class="bi bi-envelope"></i> Email reminder');
      let msg = 'Email failed';
      try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
      alert(msg);
    });
  });

  $('.btn-upload').on('click', function () {
    const staffId = $(this).data('staff-id');
    const row = $(this).closest('.upload-row');
    const form = row.find('form.upload-form')[0];
    const fd = new FormData(form);
    const btn = $(this);
    btn.prop('disabled', true).text('Uploading…');

    $.ajax({
      url: 'upload-staff-contract.php',
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json',
      timeout: 300000,
      xhrFields: { withCredentials: true }
    }).done(function (data) {
      btn.prop('disabled', false).html('<i class="bi bi-cloud-upload"></i> Upload Word');
      if (!data || !data.success) {
        alert(data?.message || 'Upload failed');
        return;
      }
      alert(data.message || 'Uploaded');
      location.reload();
    }).fail(function (xhr, textStatus) {
      btn.prop('disabled', false).html('<i class="bi bi-cloud-upload"></i> Upload Word');
      let msg = 'Upload failed';
      if (textStatus === 'timeout') {
        msg = 'Upload timed out. Try again — if it keeps failing, ask hosting to raise PHP max_execution_time.';
      } else if (xhr.status === 404) {
        msg = 'Upload endpoint not found. Deploy admin/upload-staff-contract.php to the server.';
      } else {
        try {
          const parsed = JSON.parse(xhr.responseText || '');
          msg = parsed.message || msg;
        } catch (e) {
          const raw = (xhr.responseText || '').replace(/\s+/g, ' ').trim();
          if (raw) {
            msg = raw.substring(0, 220);
          } else if (xhr.status) {
            msg += ' (HTTP ' + xhr.status + ')';
          }
        }
      }
      alert(msg);
    });
  });

  $('.btn-regenerate-contract').on('click', function () {
    const staffId = $(this).data('staff-id');
    const mode = $(this).data('mode') || 'preview';
    const btn = $(this);
    const label = mode === 'signed' ? 'Regenerate signed' : 'Regenerate Word';
    if (!confirm('Rebuild the ' + (mode === 'signed' ? 'signed ' : 'filled ') + 'contract for this staff member using current profile data?')) {
      return;
    }
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

    $.ajax({
      url: 'regenerate-staff-contract.php',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ staff_id: staffId, mode: mode }),
      dataType: 'json',
      timeout: 300000,
      xhrFields: { withCredentials: true }
    }).done(function (data) {
      btn.prop('disabled', false).html('<i class="bi bi-arrow-' + (mode === 'signed' ? 'repeat' : 'clockwise') + '"></i> ' + label);
      if (!data || !data.success) {
        alert(data?.message || 'Regenerate failed');
        return;
      }
      alert(data.message || 'Regenerated');
      location.reload();
    }).fail(function (xhr, textStatus) {
      btn.prop('disabled', false).html('<i class="bi bi-arrow-' + (mode === 'signed' ? 'repeat' : 'clockwise') + '"></i> ' + label);
      let errMsg = 'Regenerate failed';
      if (textStatus === 'timeout') {
        errMsg = 'Regenerate timed out. Try again — if it keeps failing, ask hosting to raise PHP max_execution_time.';
      } else if (xhr.status === 404) {
        errMsg = 'Regenerate endpoint not found. Deploy admin/regenerate-staff-contract.php to the server.';
      } else {
        try {
          const parsed = JSON.parse(xhr.responseText || '');
          errMsg = parsed.message || errMsg;
        } catch (e) {
          if (xhr.responseText) {
            errMsg += ' (HTTP ' + xhr.status + ')';
          }
        }
      }
      alert(errMsg);
    });
  });

  $('.btn-delete-contract').on('click', function () {
    const staffId = $(this).data('staff-id');
    const staffName = $(this).data('staff-name') || 'this staff member';
    const isSigned = String($(this).data('is-signed')) === '1';
    let msg = 'Delete the contract for ' + staffName + '?';
    if (isSigned) {
      msg += '\n\nThis contract is already signed. The signed PDF and signature will also be permanently removed.';
    } else {
      msg += '\n\nThe Word template and generated files will be permanently removed.';
    }
    if (!confirm(msg)) return;

    const btn = $(this);
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

    $.ajax({
      url: 'delete-staff-contract.php',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ staff_id: staffId }),
      dataType: 'json'
    }).done(function (data) {
      if (!data || !data.success) {
        btn.prop('disabled', false).html('<i class="bi bi-trash"></i> Delete');
        alert(data?.message || 'Delete failed');
        return;
      }
      alert(data.message || 'Deleted');
      location.reload();
    }).fail(function (xhr) {
      btn.prop('disabled', false).html('<i class="bi bi-trash"></i> Delete');
      let errMsg = 'Delete failed';
      try { errMsg = JSON.parse(xhr.responseText).message || errMsg; } catch (e) {}
      alert(errMsg);
    });
  });
})();
</script>
</body>
</html>
