<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/staff_contract_schema.php';
require_once __DIR__ . '/../helpers/staff_contract_word.php';

$adminId = (int) ($_SESSION['id'] ?? $_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    http_response_code(401);
    exit('Unauthorized');
}

$stmt = $conn->prepare('SELECT id, full_name, email, role, position FROM admins WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    http_response_code(404);
    exit('Account not found');
}

$contract = pcvc_staff_contract_for_admin($conn, $adminId);
$status = pcvc_staff_contract_row_status($contract);
$isSigned = $status['code'] === 'signed';
$hasContract = $status['code'] !== 'no_contract';
$title = trim((string) ($contract['contract_title'] ?? 'Employment Contract'));
$previewError = '';
$useDocxPreview = pcvc_staff_contract_use_docx_preview();

if ($hasContract && !$isSigned && trim((string) ($contract['source_docx_path'] ?? '')) !== '') {
    $filledRel = pcvc_staff_contract_preview_docx_path($contract);
    $filledAbs = $filledRel !== '' ? pcvc_staff_contract_abs_path($filledRel) : '';
    $needsPreview = $filledRel === ''
        || !is_file($filledAbs)
        || pcvc_staff_contract_docx_is_corrupt($filledAbs);
    if ($needsPreview) {
        try {
            pcvc_staff_contract_generate_preview($conn, $adminId, $contract, null, !$useDocxPreview);
            $contract = pcvc_staff_contract_for_admin($conn, $adminId);
        } catch (Throwable $e) {
            $previewError = $e->getMessage();
        }
    }
} elseif ($hasContract && $isSigned && $useDocxPreview) {
    $signedRel = pcvc_staff_contract_signed_docx_path($contract);
    $signedAbs = $signedRel !== '' ? pcvc_staff_contract_abs_path($signedRel) : '';
    if ($signedRel === '' || !is_file($signedAbs) || pcvc_staff_contract_docx_is_corrupt($signedAbs)) {
        try {
            pcvc_staff_contract_regenerate($conn, $adminId, $contract, 'signed');
            $contract = pcvc_staff_contract_for_admin($conn, $adminId);
        } catch (Throwable $e) {
            $previewError = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Employment Contract</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root { --primary: #073b77; --accent: #427431; }
    body { background: #f4f6f9; font-family: 'Segoe UI', system-ui, sans-serif; }
    .page-header {
      background: linear-gradient(135deg, var(--accent), var(--primary));
      color: #fff; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem;
    }
    .panel { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 1.25rem; }
    .pdf-frame { width: 100%; min-height: 70vh; border: 1px solid #dbe3ef; border-radius: 10px; }
    .docx-frame { width: 100%; min-height: 85vh; height: 85vh; border: 1px solid #dbe3ef; border-radius: 10px; background: #e8edf3; }
    #signaturePad {
      width: 100%; height: 180px; border: 2px dashed #94a3b8; border-radius: 10px;
      background: #fff; touch-action: none; cursor: crosshair;
    }
    .sign-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; }
    .autofill-note { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; }
    .contract-toolbar { display: flex; justify-content: flex-end; gap: .5rem; margin-bottom: .75rem; }
  </style>
</head>
<body>
<div class="container-fluid py-4 px-3 px-md-4">
  <div class="page-header">
    <h4 class="mb-1 fw-bold"><i class="bi bi-file-earmark-text me-2"></i>My Employment Contract</h4>
    <p class="mb-0 small opacity-90">
      <?= htmlspecialchars((string) $admin['full_name']) ?> · <?= htmlspecialchars($title) ?>
    </p>
  </div>

  <?php if (!$hasContract): ?>
  <div class="panel text-center py-5">
    <i class="bi bi-hourglass-split display-4 text-muted"></i>
    <h5 class="mt-3">Your contract is not ready yet</h5>
    <p class="text-muted mb-0">Superadmin will upload your Word contract template. Your details will be filled in automatically when it is ready.</p>
  </div>
  <?php elseif ($isSigned): ?>
  <div class="panel">
    <div class="alert alert-success d-flex align-items-center gap-2">
      <i class="bi bi-check-circle-fill fs-4"></i>
      <div>
        <strong>Contract signed</strong>
        <?php if (!empty($contract['signed_at'])): ?>
          <div class="small">Signed on <?= htmlspecialchars(date('F j, Y \a\t H:i', strtotime((string) $contract['signed_at']))) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($useDocxPreview): ?>
    <div class="contract-toolbar">
      <button type="button" class="btn btn-outline-primary" id="printContractBtn">
        <i class="bi bi-printer me-1"></i> Print / Save as PDF
      </button>
    </div>
    <?php endif; ?>
    <iframe id="contractFrame" class="<?= $useDocxPreview ? 'docx-frame' : 'pdf-frame' ?>" scrolling="yes"
      src="<?= $useDocxPreview ? 'contract-docx-viewer.php?type=signed&ts=' . time() : 'view-staff-contract-pdf.php?type=signed#toolbar=1' ?>"></iframe>
    <div class="mt-3 text-end">
      <a href="download-staff-contract.php?type=signed<?= $useDocxPreview ? '&format=docx' : '' ?>" target="_blank"
        class="btn btn-primary btn-lg">
        <i class="bi bi-download me-1"></i> Download signed contract
      </a>
    </div>
  </div>
  <?php else: ?>
  <?php if ($previewError !== ''): ?>
  <div class="alert alert-warning"><?= htmlspecialchars($previewError) ?></div>
  <?php endif; ?>
  <?php if (trim((string) ($admin['position'] ?? '')) === ''): ?>
  <div class="alert alert-warning mb-3">
    <strong>Position missing.</strong> Ask superadmin to set your <em>Position</em> in Staff Management, save your row, then reopen this page so the contract PDF can be regenerated.
  </div>
  <?php endif; ?>
  <div class="autofill-note mb-3">
    <i class="bi bi-magic me-1"></i>
    <strong>Auto-filled from your profile.</strong>
    Your name, phone, address, and other details were inserted into the contract automatically.
    Review the <?= $useDocxPreview ? 'Word document' : 'PDF' ?>, then sign below — no dragging required.
  </div>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="panel">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="fw-bold mb-0">Your contract (auto-filled)</h6>
          <div class="d-flex align-items-center gap-2">
            <?php if ($useDocxPreview): ?>
            <button type="button" class="btn btn-sm btn-outline-primary" id="printContractBtn">
              <i class="bi bi-printer me-1"></i> Print / Save as PDF
            </button>
            <?php endif; ?>
            <span class="badge text-bg-warning">Signature required</span>
          </div>
        </div>
        <iframe id="contractFrame" class="<?= $useDocxPreview ? 'docx-frame' : 'pdf-frame' ?>" scrolling="yes"
          src="<?= $useDocxPreview
            ? 'contract-docx-viewer.php?type=source&ts=' . time()
            : 'view-staff-contract-pdf.php?type=source&ts=' . time() . '#toolbar=1' ?>"></iframe>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="panel sign-box">
        <h6 class="fw-bold mb-3"><i class="bi bi-pen me-1"></i> E-Sign contract</h6>
        <p class="small text-muted">Read the auto-filled contract on the left, then sign below.</p>

        <label class="form-label small fw-semibold">Full legal name</label>
        <input type="text" id="typedName" class="form-control mb-3"
          value="<?= htmlspecialchars((string) $admin['full_name']) ?>" placeholder="Your full name">

        <label class="form-label small fw-semibold">Draw signature</label>
        <canvas id="signaturePad"></canvas>
        <div class="d-flex gap-2 mt-2 mb-3">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="clearSig">Clear</button>
        </div>

        <label class="form-label small fw-semibold">Signing date</label>
        <input type="date" id="signedDate" class="form-control mb-3" value="<?= date('Y-m-d') ?>">

        <button type="button" class="btn btn-success w-100 btn-lg" id="signBtn">
          <i class="bi bi-check2-circle me-1"></i> Sign & save contract
        </button>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if ($hasContract && $useDocxPreview): ?>
<script>
(function () {
  const btn = document.getElementById('printContractBtn');
  const frame = document.getElementById('contractFrame');
  if (!btn || !frame) {
    return;
  }
  btn.addEventListener('click', function () {
    try {
      if (frame.contentWindow && typeof frame.contentWindow.printContract === 'function') {
        frame.contentWindow.printContract();
        return;
      }
      frame.contentWindow.focus();
      frame.contentWindow.print();
    } catch (e) {
      alert('Contract is still loading. Please wait a moment and try again.');
    }
  });
})();
</script>
<?php endif; ?>

<?php if ($hasContract && !$isSigned): ?>
<script>
(function () {
  const canvas = document.getElementById('signaturePad');
  const ctx = canvas.getContext('2d');
  let drawing = false;
  let hasInk = false;

  function resizeCanvas() {
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const rect = canvas.getBoundingClientRect();
    canvas.width = Math.floor(rect.width * ratio);
    canvas.height = Math.floor(rect.height * ratio);
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 2.2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#111827';
  }

  function pos(e) {
    const rect = canvas.getBoundingClientRect();
    const src = e.touches ? e.touches[0] : e;
    return { x: src.clientX - rect.left, y: src.clientY - rect.top };
  }

  function start(e) { drawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
  function move(e) {
    if (!drawing) return;
    const p = pos(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    hasInk = true;
    e.preventDefault();
  }
  function end() { drawing = false; }

  resizeCanvas();
  window.addEventListener('resize', resizeCanvas);
  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  canvas.addEventListener('mouseup', end);
  canvas.addEventListener('mouseleave', end);
  canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('touchmove', move, { passive: false });
  canvas.addEventListener('touchend', end);

  document.getElementById('clearSig').addEventListener('click', function () {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasInk = false;
  });

  document.getElementById('signBtn').addEventListener('click', function () {
    const typedName = document.getElementById('typedName').value.trim();
    const signedDate = document.getElementById('signedDate').value;
    if (!typedName || typedName.length < 2) {
      alert('Please enter your full name.');
      return;
    }
    if (!hasInk) {
      alert('Please draw your signature.');
      return;
    }
    if (!signedDate) {
      alert('Please choose the signing date.');
      return;
    }

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Signing…';

    fetch('sign-staff-contract.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        typed_name: typedName,
        signed_date: signedDate,
        signature: canvas.toDataURL('image/png')
      })
    })
    .then(r => r.json())
    .then(data => {
      if (!data || !data.success) {
        throw new Error(data?.message || 'Signing failed');
      }
      alert(data.message || 'Signed successfully');
      location.reload();
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Sign & save contract';
      alert(err.message || 'Signing failed');
    });
  });
})();
</script>
<?php endif; ?>
</body>
</html>
