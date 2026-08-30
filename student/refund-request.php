<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/refund_requests_schema.php';
require_once __DIR__ . '/../helpers/urls.php';
require_once __DIR__ . '/auth.php';

pcvc_ensure_refund_requests_schema($conn);

$pageTitle = 'Request Refund';
$accountId = (int) ($_SESSION['student_account_id'] ?? 0);
$email = strtolower(trim((string) ($_SESSION['student_email'] ?? '')));

$student = null;
$appId = (int) ($_SESSION['student_application_id'] ?? 0);
if ($email !== '') {
    $st = $conn->prepare('SELECT * FROM student_applications WHERE LOWER(TRIM(email)) = ? ORDER BY id DESC LIMIT 1');
    if ($st) {
        $st->bind_param('s', $email);
        $st->execute();
        $student = $st->get_result()->fetch_assoc();
        $st->close();
        if ($student) {
            $appId = (int) ($student['id'] ?? 0);
        }
    }
}

$fn = $student ? trim((string) ($student['first_name'] ?? '')) : '';
$ln = $student ? trim((string) ($student['last_name'] ?? '')) : '';
$ph = $student ? trim((string) ($student['area_code'] ?? '') . ' ' . (string) ($student['phone_number'] ?? '')) : '';
$appRef = $student ? trim((string) ($student['application_id'] ?? '')) : '';

$csrfToken = pcvc_csrf_token();
require_once __DIR__ . '/layout.php';
?>

<style>
.st-refund-card { border-radius: 16px; }
.st-refund-label { font-size: 0.82rem; font-weight: 600; color: var(--muted); }
.st-upload { border: 2px dashed var(--border); border-radius: 12px; padding: 1.25rem; text-align: center; cursor: pointer; background: #fafbfc; }
.st-upload:hover { border-color: var(--pcv-green); }
</style>

<div class="card st-refund-card p-3 p-md-4">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <h1 class="h4 fw-bold mb-1">Request a refund</h1>
      <p class="text-muted small mb-0">Your profile details are prefilled from your account. Attach proof of payment to submit.</p>
    </div>
    <a href="<?= htmlspecialchars(pcvc_url('/student/refund-requests.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary btn-sm">My refund requests</a>
  </div>

  <div id="stRefundAlert" class="alert d-none" role="alert"></div>

  <form id="stRefundForm" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="submitted_by" value="student_portal">
    <input type="hidden" name="student_portal_account_id" value="<?= (int) $accountId ?>">
    <input type="hidden" name="student_application_id" value="<?= (int) $appId ?>">
    <input type="hidden" name="is_existing_student" value="<?= $student ? '1' : '0' ?>">
    <input type="hidden" name="application_id" value="<?= htmlspecialchars($appRef, ENT_QUOTES, 'UTF-8') ?>">

    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="st-refund-label" for="stFirstName">First name</label>
        <input class="form-control" id="stFirstName" name="first_name" required value="<?= htmlspecialchars($fn !== '' ? $fn : explode(' ', trim((string) $_SESSION['student_name']))[0] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-md-6">
        <label class="st-refund-label" for="stLastName">Last name</label>
        <input class="form-control" id="stLastName" name="last_name" required value="<?= htmlspecialchars($ln, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-md-6">
        <label class="st-refund-label" for="stEmail">Email</label>
        <input class="form-control" id="stEmail" name="email" type="email" required readonly value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-md-6">
        <label class="st-refund-label" for="stPhone">Phone</label>
        <input class="form-control" id="stPhone" name="phone" value="<?= htmlspecialchars($ph, ENT_QUOTES, 'UTF-8') ?>">
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-12">
        <label class="st-refund-label" for="stService">Service paid for</label>
        <input class="form-control" id="stService" name="service_paid_for" required placeholder="e.g. Application fee, Visa consultation">
      </div>
      <div class="col-md-6">
        <label class="st-refund-label" for="stAmount">Amount</label>
        <input class="form-control" id="stAmount" name="amount" type="number" min="0.01" step="0.01" required>
      </div>
      <div class="col-md-6">
        <label class="st-refund-label" for="stCurrency">Currency</label>
        <select class="form-select" id="stCurrency" name="currency">
          <option value="USD">USD</option>
          <option value="RWF">RWF</option>
          <option value="CAD">CAD</option>
          <option value="EUR">EUR</option>
        </select>
      </div>
      <div class="col-12">
        <label class="st-refund-label" for="stReason">Reason for refund</label>
        <textarea class="form-control" id="stReason" name="reason" rows="4" required placeholder="Explain why you are requesting a refund…"></textarea>
      </div>
      <div class="col-12">
        <label class="st-refund-label">Proof of payment</label>
        <div class="st-upload" id="stUploadZone" tabindex="0" role="button">
          <i class="fas fa-cloud-upload-alt text-success fs-3"></i>
          <p class="small text-muted mb-0 mt-2">Click to upload PDF or image (max 8 MB)</p>
          <div class="small fw-semibold text-success mt-1" id="stFileName"></div>
        </div>
        <input type="file" id="stPaymentProof" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif" hidden required>
      </div>
    </div>

    <button type="submit" class="btn btn-success fw-semibold px-4" id="stSubmitBtn">Submit refund request</button>
  </form>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script>
(function () {
  const form = document.getElementById('stRefundForm');
  const alertEl = document.getElementById('stRefundAlert');
  const uploadZone = document.getElementById('stUploadZone');
  const fileInput = document.getElementById('stPaymentProof');
  const fileName = document.getElementById('stFileName');
  const submitBtn = document.getElementById('stSubmitBtn');

  function showAlert(msg, ok) {
    alertEl.textContent = msg;
    alertEl.className = 'alert alert-' + (ok ? 'success' : 'danger');
  }

  uploadZone.addEventListener('click', function () { fileInput.click(); });
  fileInput.addEventListener('change', function () {
    fileName.textContent = fileInput.files[0] ? fileInput.files[0].name : '';
  });

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!fileInput.files.length) { showAlert('Please attach proof of payment.', false); return; }
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting…';
    try {
      const res = await fetch('../save_refund_request.php', { method: 'POST', body: new FormData(form) });
      const data = await res.json();
      if (!data.ok) { showAlert(data.error || 'Failed.', false); return; }
      showAlert('Submitted! Reference: ' + (data.reference_id || ''), true);
      form.reset();
      fileName.textContent = '';
      setTimeout(function () { window.location.href = 'refund-requests.php'; }, 1800);
    } catch (err) {
      showAlert('Network error.', false);
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Submit refund request';
    }
  });
})();
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
