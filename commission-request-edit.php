<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/company_branding.php';
require_once __DIR__ . '/helpers/commission_currency.php';
require_once __DIR__ . '/helpers/commission_requests_schema.php';
require_once __DIR__ . '/helpers/commission_request_owner.php';

if (empty($_SESSION['username']) && empty($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0);
if ($userId < 1) {
    header('Location: admin-login.php');
    exit;
}

$agentUsername = $_SESSION['username'] ?? '';
$agentInfo = null;
if ($agentUsername) {
    $stmt = $conn->prepare('SELECT id, first_name, last_name, email, phone_number FROM admins WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $agentUsername);
    $stmt->execute();
    $stmt->bind_result($id, $first, $last, $email, $phone);
    if ($stmt->fetch()) {
        $_SESSION['user_id'] = (int) $id;
        $_SESSION['agent_email'] = $email;
        $agentInfo = [
            'first_name' => trim((string) $first),
            'last_name'  => trim((string) $last),
            'email'      => trim((string) $email),
            'phone'      => trim((string) $phone),
        ];
    }
    $stmt->close();
}
if (!$agentInfo && !empty($_SESSION['admin_id'])) {
    $aid = (int) $_SESSION['admin_id'];
    $st2 = $conn->prepare('SELECT id, first_name, last_name, email, phone_number FROM admins WHERE id = ? LIMIT 1');
    if ($st2) {
        $st2->bind_param('i', $aid);
        $st2->execute();
        $st2->bind_result($id2, $f2, $l2, $e2, $p2);
        if ($st2->fetch()) {
            $_SESSION['user_id'] = (int) $id2;
            $_SESSION['agent_email'] = $e2;
            $agentInfo = [
                'first_name' => trim((string) $f2),
                'last_name'  => trim((string) $l2),
                'email'      => trim((string) $e2),
                'phone'      => trim((string) $p2),
            ];
        }
        $st2->close();
    }
}
if (!$agentInfo) {
    header('Location: admin-login.php');
    exit;
}

$reqId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($reqId < 1) {
    header('Location: commission-my-requests.php');
    exit;
}

pcvc_ensure_commission_requests_schema($conn);

$st = $conn->prepare('SELECT * FROM commission_requests WHERE id = ? LIMIT 1');
$st->bind_param('i', $reqId);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row || !pcvc_commission_user_id_matches_row($row['user_id'] ?? null, $userId)) {
    header('Location: commission-my-requests.php');
    exit;
}

$status = trim((string) ($row['request_status'] ?? 'pending')) ?: 'pending';
$locked = ($status === 'under_review');

$agentEmail = $_SESSION['agent_email'] ?? '';
$agentEmailKey = strtolower(trim((string) $agentEmail));
$students = [];
if ($agentEmailKey !== '') {
    $stmt1 = $conn->prepare('SELECT id, first_name, last_name, email FROM student_applications WHERE LOWER(TRIM(agent_email)) = ? ORDER BY created_at DESC');
    $stmt1->bind_param('s', $agentEmailKey);
    $stmt1->execute();
    $r1 = $stmt1->get_result();
    while ($x = $r1->fetch_assoc()) {
        $students[] = ['id' => 's_' . $x['id'], 'name' => trim($x['first_name'] . ' ' . $x['last_name']), 'email' => $x['email']];
    }
    $stmt1->close();
    $stmt2 = $conn2->prepare('SELECT id, name, email FROM applications WHERE LOWER(TRIM(agent_email)) = ? ORDER BY created_at DESC');
    $stmt2->bind_param('s', $agentEmailKey);
    $stmt2->execute();
    $r2 = $stmt2->get_result();
    while ($x = $r2->fetch_assoc()) {
        $students[] = ['id' => 'a_' . $x['id'], 'name' => $x['name'], 'email' => $x['email']];
    }
    $stmt2->close();
}

$ridNum = (int) ($row['recruited_student_id'] ?? 0);
$studentSelectValue = pcvc_commission_resolve_student_key($conn, $conn2, $ridNum, $agentEmailKey);

$pcvcFxSample = pcvc_usd_to_rwf_conversion(1.0);
$pcvcFxRate = $pcvcFxSample['rate'];
$pcvcCadSample = pcvc_cad_to_rwf_conversion(1.0);
$pcvcCadFxRate = $pcvcCadSample['rate'];
$editCurrency = pcvc_normalize_commission_currency((string) ($row['commission_currency'] ?? 'USD'));

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$ro = $locked ? 'readonly' : '';
$dis = $locked ? 'disabled' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit commission #<?= (int) $reqId ?> | <?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5.min.css" rel="stylesheet">
  <style>
    body { background: #f8fafc; font-family: Inter, system-ui, sans-serif; padding: 20px; }
    .form-section { background: #fff; border-radius: 16px; padding: 24px; margin-bottom: 20px; border: 1px solid #e2e8f0; position: relative; overflow: visible; }
    .form-section-student-select.select2-section-open { z-index: 100; }
    .select2-container--open { z-index: 2000 !important; }
    .select2-dropdown { z-index: 2001 !important; box-shadow: 0 14px 32px rgba(15, 23, 42, 0.16); }
    .rwf-preview-box { padding: 12px 16px; border-radius: 10px; background: rgba(66,116,49,0.08); border: 1px solid rgba(66,116,49,0.2); font-weight: 600; }
  </style>
  <script src="js/commission_fx.js?v=2"></script>
</head>
<body>
<div class="container" style="max-width: 900px;">
  <?php if ($locked): ?>
    <div class="alert alert-warning border border-warning">
      <strong>Under review.</strong> This request is being reviewed by administration. You can view details but cannot change them until review is complete.
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Commission request #<?= (int) $reqId ?></h1>
    <a href="commission-my-requests.php" class="btn btn-outline-secondary btn-sm">Back to list</a>
  </div>

  <form id="commissionEditForm" <?= $locked ? 'onsubmit="return false"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="commission_request_id" value="<?= (int) $reqId ?>">

    <div class="form-section">
      <h3 class="h5">Agent (read-only)</h3>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">First name</label><input class="form-control" name="first_name" value="<?= htmlspecialchars($agentInfo['first_name']) ?>" readonly></div>
        <div class="col-md-6"><label class="form-label">Last name</label><input class="form-control" name="last_name" value="<?= htmlspecialchars($agentInfo['last_name']) ?>" readonly></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" value="<?= htmlspecialchars($agentInfo['email']) ?>" readonly></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= htmlspecialchars($agentInfo['phone']) ?>" readonly></div>
      </div>
    </div>

    <div class="form-section form-section-student-select" id="studentSelectionSection">
      <h3 class="h5">Student *</h3>
      <select id="studentSelect" class="form-select" name="recruited_student_id" required <?= $dis ?>>
        <option value="">— Select —</option>
        <?php
        $haveKey = false;
        foreach ($students as $s) {
            if ($s['id'] === $studentSelectValue) {
                $haveKey = true;
                break;
            }
        }
        if ($studentSelectValue !== '' && !$haveKey):
            ?>
          <option value="<?= htmlspecialchars($studentSelectValue, ENT_QUOTES, 'UTF-8') ?>" selected>
            <?= htmlspecialchars((string) ($row['recruited_name'] ?? 'Saved student'), ENT_QUOTES, 'UTF-8') ?> (on file)
          </option>
        <?php endif; ?>
        <?php foreach ($students as $s): ?>
          <option value="<?= htmlspecialchars($s['id']) ?>" <?= $s['id'] === $studentSelectValue ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['name']) ?> — <?= htmlspecialchars($s['email']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-section" id="commissionAmountBlock"
      data-usd-rwf-rate="<?= htmlspecialchars(number_format((float) $pcvcFxRate, 6, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
      data-cad-rwf-rate="<?= htmlspecialchars(number_format((float) $pcvcCadFxRate, 6, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
      data-label-prefix="Amount"
      data-label-suffix="">
      <h3 class="h5">Amount *</h3>
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label small" for="commissionCurrency">Currency</label>
          <select class="form-select" name="commission_currency" id="commissionCurrency" required <?= $ro ?>
            onchange="if(window.pcvcOnCurrencyChange){window.pcvcOnCurrencyChange();}">
            <option value="USD" <?= $editCurrency === 'USD' ? 'selected' : '' ?>>USD — US Dollar</option>
            <option value="CAD" <?= $editCurrency === 'CAD' ? 'selected' : '' ?>>CAD — Canadian Dollar</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small" id="amountLabel" for="amountUsd">Amount (<?= htmlspecialchars($editCurrency, ENT_QUOTES, 'UTF-8') ?>)</label>
          <input type="number" class="form-control" name="amount_usd" id="amountUsd" min="0.01" step="0.01" required
            value="<?= htmlspecialchars((string) ($row['amount_usd'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $ro ?>
            oninput="if(window.pcvcUpdateRwfPreview){window.pcvcUpdateRwfPreview();}"
            onchange="if(window.pcvcUpdateRwfPreview){window.pcvcUpdateRwfPreview();}">
        </div>
        <div class="col-md-4"><div class="rwf-preview-box">Estimated RWF: <span class="num" id="rwfPreview">—</span></div></div>
      </div>
    </div>

    <div class="form-section">
      <h3 class="h5">Payment address</h3>
      <div class="row g-3">
        <div class="col-12"><input class="form-control" name="street_address" placeholder="Street" value="<?= htmlspecialchars((string) ($row['street_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $ro ?>></div>
        <div class="col-12"><input class="form-control" name="address_line_2" placeholder="Line 2" value="<?= htmlspecialchars((string) ($row['address_line_2'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $ro ?>></div>
        <div class="col-md-6"><input class="form-control" name="city" placeholder="City" value="<?= htmlspecialchars((string) ($row['city'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $ro ?>></div>
        <div class="col-md-3"><input class="form-control" name="state" placeholder="State" value="<?= htmlspecialchars((string) ($row['state'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $ro ?>></div>
        <div class="col-md-3"><input class="form-control" name="postal_code" placeholder="Postal" value="<?= htmlspecialchars((string) ($row['postal_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $ro ?>></div>
      </div>
    </div>

    <div class="form-section">
      <h3 class="h5">Application</h3>
      <div class="row g-3">
        <div class="col-md-6"><input class="form-control" name="country_applied" value="<?= htmlspecialchars((string) ($row['country_applied'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $ro ?>></div>
        <div class="col-md-6"><input type="date" class="form-control" name="date" required value="<?= htmlspecialchars((string) ($row['submission_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $ro ?>></div>
      </div>
      <div class="mt-3">
        <?php
        $loan = (string) ($row['loan_status'] ?? '');
        $visa = (string) ($row['visa_status'] ?? '');
        $ctr = (string) ($row['contract_signed'] ?? '');
        ?>
        <p class="small text-muted mb-1">Loan / Visa / Contract (required when editing)</p>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label small">Loan</label>
            <select class="form-select form-select-sm" name="loan_status" required <?= $dis ?>>
              <?php foreach (['APPROVED', 'DENIED', 'NOT APPLICABLE'] as $v): ?>
                <option value="<?= $v ?>" <?= $loan === $v ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Visa</label>
            <select class="form-select form-select-sm" name="visa_status" required <?= $dis ?>>
              <?php foreach (['APPROVED', 'DENIED', 'NOT APPLICABLE'] as $v): ?>
                <option value="<?= $v ?>" <?= $visa === $v ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Contract</label>
            <select class="form-select form-select-sm" name="contract_signed" required <?= $dis ?>>
              <?php foreach (['YES', 'NO'] as $v): ?>
                <option value="<?= $v ?>" <?= $ctr === $v ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h3 class="h5">Comments & signature *</h3>
      <textarea class="form-control mb-3" name="comments" rows="4" <?= $ro ?>><?= htmlspecialchars((string) ($row['comments'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
      <input class="form-control" name="signature" required value="<?= htmlspecialchars((string) ($row['signature'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $ro ?>>
    </div>

    <?php if (!$locked): ?>
      <button type="submit" class="btn btn-primary btn-lg w-100 mb-4" id="saveBtn">Save changes</button>
    <?php endif; ?>
    <div id="editMsg" class="small text-muted mb-4"></div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const LOCKED = <?= $locked ? 'true' : 'false' ?>;

if (!LOCKED) {
  const $studentSelect = $('#studentSelect');
  $studentSelect.select2({
    theme: 'bootstrap-5',
    width: '100%',
    dropdownParent: $(document.body)
  });
  const studentSection = document.getElementById('studentSelectionSection');
  $studentSelect.on('select2:open', function () {
    if (studentSection) studentSection.classList.add('select2-section-open');
  });
  $studentSelect.on('select2:close', function () {
    if (studentSection) studentSection.classList.remove('select2-section-open');
  });
  document.getElementById('commissionEditForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveBtn');
    const msg = document.getElementById('editMsg');
    btn.disabled = true;
    msg.textContent = 'Saving…';
    const fd = new FormData(this);
    fetch('api/commission-agent-update.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(j => {
        btn.disabled = false;
        if (j.ok) {
          msg.textContent = j.message || 'Saved.';
          msg.className = 'small text-success mb-4';
        } else {
          msg.textContent = j.error || 'Failed';
          msg.className = 'small text-danger mb-4';
        }
      })
      .catch(() => {
        btn.disabled = false;
        msg.textContent = 'Network error';
        msg.className = 'small text-danger mb-4';
      });
  });
}
</script>
</body>
</html>
