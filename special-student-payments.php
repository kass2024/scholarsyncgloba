<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';

pcvc_require_superadmin($conn, false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Credit Transfer & UPAFA Payments</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --primary: #427431;
      --secondary: #3661B9;
      --success: #2e7d32;
    }
    body { background: #f4f6f9; font-family: 'Segoe UI', system-ui, sans-serif; }
    .page-header {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff;
      padding: 1.25rem 1.5rem;
      border-radius: 12px;
      margin-bottom: 1.5rem;
    }
    .program-tabs .nav-link {
      font-weight: 600;
      color: #475569;
      border-radius: 8px 8px 0 0;
    }
    .program-tabs .nav-link.active {
      background: #fff;
      color: var(--primary);
      border-bottom-color: #fff;
    }
    .search-card, .results-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,.06);
      padding: 1.25rem;
    }
    .result-row { transition: background .15s; }
    .result-row:hover { background: #f8fafc; }
    #paymentModal .modal-header {
      background: linear-gradient(135deg, var(--success), #1b5e20);
      color: #fff;
    }
    #paymentModal .modal-dialog { max-width: 900px; }
    .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }
    .loading-overlay {
      position: fixed; inset: 0; background: rgba(255,255,255,.75);
      display: none; align-items: center; justify-content: center; z-index: 10000;
    }
    .badge-credit { background: #e8f5e9; color: #2e7d32; }
    .badge-upafa { background: #e3f2fd; color: #1565c0; }
    .search-hint { font-size: .8rem; color: #64748b; }
    .tier-card {
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      padding: .85rem 1rem;
      cursor: pointer;
      transition: border-color .15s, background .15s;
      display: block;
      margin-bottom: .5rem;
    }
    .tier-card:hover { border-color: #94a3b8; background: #f8fafc; }
    .tier-card.selected { border-color: var(--primary); background: #f0fdf4; }
    .tier-card input { margin-right: .5rem; }
    .tier-price { font-weight: 700; color: var(--primary); float: right; }
    #searchSpinner { display: none; }
    .results-loading { opacity: .55; pointer-events: none; }
  </style>
</head>
<body>
<div class="container-fluid py-4 px-3 px-md-4">

  <div class="page-header">
    <h4 class="mb-1 fw-bold"><i class="bi bi-cash-stack me-2"></i>Record Credit Transfer & UPAFA Payments</h4>
    <p class="mb-0 small opacity-90">Search an existing applicant or register a new one, then record what they paid. Receipt stays in dashboard — student is not emailed; admin is notified.</p>
  </div>

  <ul class="nav nav-tabs program-tabs mb-0" id="programTabs">
    <li class="nav-item">
      <button class="nav-link active" data-type="credit_transfer" type="button">
        <i class="bi bi-arrow-left-right me-1"></i> Credit Transfer
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-type="upafa" type="button">
        <i class="bi bi-mortarboard me-1"></i> UPAFA
      </button>
    </li>
  </ul>

  <div class="search-card border border-top-0 rounded-bottom mb-4">
    <div class="row g-3 align-items-end">
      <div class="col-md-8">
        <label class="form-label fw-semibold small text-muted">Search applicant <span class="search-hint">— results update as you type</span></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" id="searchInput" class="form-control" placeholder="Name, email, phone, user ID..." autocomplete="off">
          <span class="input-group-text" id="searchSpinner"><span class="spinner-border spinner-border-sm text-primary"></span></span>
        </div>
      </div>
      <div class="col-md-4 text-md-end">
        <button class="btn btn-outline-success w-100 w-md-auto" id="registerBtn" type="button">
          <i class="bi bi-person-plus me-1"></i> Register New Applicant
        </button>
      </div>
    </div>
  </div>

  <div class="results-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-bold mb-0">Search Results</h6>
      <span class="text-muted small" id="resultCount">—</span>
    </div>
    <div id="resultsEmpty" class="text-center text-muted py-5">
      <i class="bi bi-people display-6 d-block mb-2 opacity-50"></i>
      Start typing to filter applicants, or browse the recent list below.
    </div>
    <div class="table-responsive d-none" id="resultsTableWrap">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Reference</th>
            <th>Extra</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody id="resultsBody"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Register Modal -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="registerForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Register New Applicant</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted">Quick registration for payment recording. Full application can be completed later.</p>
        <div class="mb-3">
          <label class="form-label">Program</label>
          <input type="text" id="reg_program_label" class="form-control" readonly>
          <input type="hidden" id="reg_type" name="type">
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">First Name *</label>
            <input type="text" id="reg_first_name" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Last Name *</label>
            <input type="text" id="reg_last_name" class="form-control" required>
          </div>
          <div class="col-12">
            <label class="form-label">Email *</label>
            <input type="email" id="reg_email" class="form-control" required>
          </div>
          <div class="col-12">
            <label class="form-label">Phone</label>
            <input type="text" id="reg_phone" class="form-control">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success">Register & Continue</button>
      </div>
    </form>
  </div>
</div>

<!-- Payment Modal (from student management) -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <form id="paymentForm" action="javascript:void(0);" autocomplete="off" novalidate>
      <div class="modal-content shadow-lg rounded-4">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">💰 Record Payment</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body px-4 py-3">
          <input type="hidden" id="pay_student_id" name="student_id">
          <input type="hidden" id="pay_table" name="table">
          <input type="hidden" id="pay_package_id" name="package_id">

          <div class="row mb-3">
            <div class="col-md-4">
              <div class="small text-muted">Program</div>
              <div class="fw-semibold" id="pay_program">—</div>
            </div>
            <div class="col-md-4">
              <div class="small text-muted">Applicant</div>
              <div class="fw-semibold" id="pay_name">—</div>
            </div>
            <div class="col-md-4">
              <div class="small text-muted">Email</div>
              <div class="fw-semibold" id="pay_email">—</div>
            </div>
          </div>

          <hr class="my-3">

          <!-- All required fees (Credit Transfer & UPAFA) -->
          <div id="staticFeesBlock">
            <h6 class="fw-bold text-primary mb-2">All Required Fees</h6>
            <p class="small text-muted mb-2">Select the fee the student is paying. Fixed USD pricing — recorded to dashboard.</p>

            <div class="small fw-semibold text-muted mt-3 mb-1">Application Fees</div>
            <label class="tier-card fee-tier-card" data-tier="bachelor_application">
              <input type="radio" name="fee_tier" value="bachelor_application"> Bachelor Application Fees
              <span class="tier-price">USD 25.00</span>
            </label>
            <label class="tier-card fee-tier-card" data-tier="masters_application">
              <input type="radio" name="fee_tier" value="masters_application"> Masters Application Fees
              <span class="tier-price">USD 50.00</span>
            </label>
            <label class="tier-card fee-tier-card" data-tier="phd_application">
              <input type="radio" name="fee_tier" value="phd_application"> PhD Application Fees
              <span class="tier-price">USD 75.00</span>
            </label>

            <div class="small fw-semibold text-muted mt-3 mb-1">Credit Transfer Fees</div>
            <label class="tier-card fee-tier-card" data-tier="bachelor_credit_transfer">
              <input type="radio" name="fee_tier" value="bachelor_credit_transfer"> Bachelor Credit Transfer Fees
              <span class="tier-price">USD 125.00</span>
            </label>
            <label class="tier-card fee-tier-card" data-tier="masters_credit_transfer">
              <input type="radio" name="fee_tier" value="masters_credit_transfer"> Masters Credit Transfer Fees
              <span class="tier-price">USD 175.00</span>
            </label>
            <label class="tier-card fee-tier-card" data-tier="phd_credit_transfer">
              <input type="radio" name="fee_tier" value="phd_credit_transfer"> PhD Credit Transfer Fees
              <span class="tier-price">USD 500.00</span>
            </label>

            <div class="row g-3 mb-3 mt-2">
              <div class="col-md-4">
                <label class="form-label small text-muted">Expected Total</label>
                <input type="text" id="fee_expected_total" class="form-control fw-semibold" readonly>
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted">Paid So Far</label>
                <input type="text" id="fee_paid_total" class="form-control" readonly>
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted">Remaining Balance</label>
                <input type="text" id="fee_remaining_total" class="form-control fw-bold text-danger" readonly>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Amount Paying Now</label>
              <input type="number" id="fee_pay_amount" class="form-control" min="0.01" step="0.01" placeholder="0.00">
              <div class="form-text">Defaults to full remaining balance when you select a fee.</div>
            </div>

            <div class="row mt-3 g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Total Payment (This Entry)</label>
                <input type="text" id="static_grand_total" class="form-control fw-bold text-success" readonly value="USD 0.00">
              </div>
            </div>
          </div>

          <hr class="my-4">

          <h6 class="fw-bold text-primary mb-3">💳 Payment Details</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Payment Method</label>
              <select name="payment_method" class="form-select" required>
                <option value="Cash">Cash</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Mobile Money">Mobile Money</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Comment / Reference</label>
              <input type="text" name="comment" class="form-control" placeholder="Optional note or reference">
            </div>
          </div>

          <div id="paymentProgressWrapper" class="mt-4 d-none">
            <div class="small fw-semibold mb-1" id="paymentProgressText">Processing payment...</div>
            <div class="progress" style="height: 10px;">
              <div id="paymentProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light rounded-bottom-4">
          <button type="submit" class="btn btn-success px-4 fw-semibold">💾 Record Payment</button>
          <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="loading-overlay" id="loadingOverlay">
  <div class="spinner-border text-success" role="status"></div>
</div>

<div class="toast-container">
  <div id="successToast" class="toast" role="alert">
    <div class="toast-header bg-success text-white">
      <strong class="me-auto">Success</strong>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
    </div>
    <div class="toast-body"></div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  'use strict';

  let currentType = 'credit_transfer';
  let searchTimer = null;
  let currentPayMode = 'static_fees';
  let selectedTier = '';
  let staticRemaining = 0;
  let staticCurrency = 'USD';

  const typeLabels = {
    credit_transfer: 'Credit Transfer',
    upafa: 'UPAFA Registration'
  };

  const CREDIT_TIERS = {
    bachelor: { label: 'Bachelor', amount: 920 },
    masters:  { label: 'Masters',  amount: 1220 },
    phd:      { label: 'PhD',      amount: 1620 }
  };

  function showLoading() { $('#loadingOverlay').css('display', 'flex'); }
  function hideLoading() { $('#loadingOverlay').hide(); }

  function showToast(msg) {
    const toast = document.getElementById('successToast');
    if (!toast) return;
    toast.querySelector('.toast-body').textContent = msg;
    bootstrap.Toast.getOrCreateInstance(toast).show();
  }

  function parsePaymentResponse(text) {
    if (!text) return null;
    const trimmed = String(text).trim();
    try {
      return JSON.parse(trimmed);
    } catch (ignore) {
      const start = trimmed.indexOf('{"success"');
      if (start >= 0) {
        try {
          return JSON.parse(trimmed.slice(start));
        } catch (ignore2) {}
      }
    }
    return null;
  }

  function handlePaymentSuccess(resp) {
    isSubmitting = false;
    finishPaymentProgress(true);
    hideLoading();

    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    showToast('Payment recorded successfully. Receipt saved to dashboard.');

    if (resp && resp.receipt_no) {
      setTimeout(function () {
        const printUrl = 'printReceipt.php?receipt_no=' + encodeURIComponent(resp.receipt_no);
        const win = window.open(printUrl, '_blank');
        if (!win) {
          showToast('Allow popups to open the receipt.');
        }
      }, 400);
    }
  }

  function escHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function programBadge(type) {
    return type === 'upafa'
      ? '<span class="badge badge-upafa">UPAFA</span>'
      : '<span class="badge badge-credit">Credit Transfer</span>';
  }

  $('#programTabs .nav-link').on('click', function () {
    $('#programTabs .nav-link').removeClass('active');
    $(this).addClass('active');
    currentType = $(this).data('type');
    scheduleSearch();
  });

  function renderResults(rows) {
    if (!rows.length) {
      $('#resultsBody').empty();
      $('#resultsTableWrap').addClass('d-none');
      $('#resultsEmpty').removeClass('d-none').html('<div class="text-muted py-4">No applicants found. Try another search or register a new applicant.</div>');
      $('#resultCount').text('0 results');
      return;
    }

    let html = '';
    rows.forEach(function (r) {
      html += `<tr class="result-row">
        <td>${escHtml(r.full_name)}</td>
        <td>${escHtml(r.email)}</td>
        <td>${escHtml(r.phone)}</td>
        <td><code class="small">${escHtml(r.ref)}</code></td>
        <td>${escHtml(r.extra)}</td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-success btn-record-pay"
            data-id="${r.id}" data-table="${escHtml(r.table)}"
            data-name="${escHtml(r.full_name)}" data-email="${escHtml(r.email)}">
            <i class="bi bi-cash"></i> Record Payment
          </button>
        </td>
      </tr>`;
    });

    $('#resultsBody').html(html);
    $('#resultsTableWrap').removeClass('d-none');
    $('#resultsEmpty').addClass('d-none');
    $('#resultCount').text(rows.length + ' result(s)');
  }

  function setSearchLoading(on) {
    $('#searchSpinner').toggle(on);
    $('.results-card').toggleClass('results-loading', on);
  }

  function doSearch() {
    const q = ($('#searchInput').val() || '').trim();
    setSearchLoading(true);
    $.getJSON('search-special-students.php', { type: currentType, q: q })
      .done(function (data) {
        setSearchLoading(false);
        if (!data || !data.success) {
          return;
        }
        renderResults(data.results || []);
      })
      .fail(function () {
        setSearchLoading(false);
      });
  }

  function scheduleSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(doSearch, 280);
  }

  $('#searchInput').on('input', scheduleSearch);

  $('#registerBtn').on('click', function () {
    $('#reg_type').val(currentType);
    $('#reg_program_label').val(typeLabels[currentType]);
    $('#reg_first_name, #reg_last_name, #reg_email, #reg_phone').val('');
    new bootstrap.Modal(document.getElementById('registerModal')).show();
  });

  $('#registerForm').on('submit', function (e) {
    e.preventDefault();
    const payload = {
      type: $('#reg_type').val(),
      first_name: ($('#reg_first_name').val() || '').trim(),
      last_name: ($('#reg_last_name').val() || '').trim(),
      email: ($('#reg_email').val() || '').trim(),
      phone: ($('#reg_phone').val() || '').trim()
    };
    showLoading();
    $.ajax({
      url: 'register-special-student.php',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      dataType: 'json'
    }).done(function (data) {
      hideLoading();
      if (!data || !data.success) {
        alert(data?.message || 'Registration failed');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('registerModal')).hide();
      showToast(data.message || 'Registered successfully');
      scheduleSearch();
      setTimeout(function () { openPaymentModal(data); }, 400);
    }).fail(function (xhr) {
      hideLoading();
      let msg = 'Registration failed';
      try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
      alert(msg);
    });
  });

  function resetStaticTierUi() {
    selectedTier = '';
    staticRemaining = 0;
    staticCurrency = 'USD';
    $('input[name="fee_tier"]').prop('checked', false);
    $('.fee-tier-card').removeClass('selected');
    $('#fee_expected_total, #fee_paid_total, #fee_remaining_total, #fee_pay_amount').val('');
    $('#static_grand_total').val('USD 0.00');
  }

  function updateStaticGrandTotal() {
    let val = Number($('#fee_pay_amount').val() || 0);
    if (val > staticRemaining && staticRemaining > 0) {
      val = staticRemaining;
      $('#fee_pay_amount').val(val.toFixed(2));
    }
    $('#static_grand_total').val(`${staticCurrency} ${val.toFixed(2)}`);
  }

  function loadFeeTierInfo(tier) {
    const studentId = $('#pay_student_id').val();
    const sourceTable = $('#pay_table').val();
    if (!tier || !studentId) return;

    selectedTier = tier;
    $('.fee-tier-card').removeClass('selected');
    $(`.fee-tier-card[data-tier="${tier}"]`).addClass('selected');

    $.getJSON('get-upafa-payment-info.php', {
      student_id: studentId,
      tier: tier,
      table: sourceTable
    }).done(function (data) {
      if (!data || !data.success) {
        alert(data?.message || 'Could not load fee info');
        return;
      }
      staticCurrency = data.currency || 'USD';
      staticRemaining = Number(data.remaining || 0);
      $('#fee_expected_total').val(`${staticCurrency} ${Number(data.total).toFixed(2)}`);
      $('#fee_paid_total').val(`${staticCurrency} ${Number(data.paid).toFixed(2)}`);
      $('#fee_remaining_total').val(`${staticCurrency} ${staticRemaining.toFixed(2)}`);
      $('#fee_pay_amount').val(staticRemaining > 0 ? staticRemaining.toFixed(2) : '');
      updateStaticGrandTotal();
    }).fail(function () { alert('Failed to load fee pricing'); });
  }

  $(document).on('change', 'input[name="fee_tier"]', function () {
    loadFeeTierInfo(this.value);
  });

  $(document).on('input', '#fee_pay_amount', updateStaticGrandTotal);

  function openPaymentModal(student) {
    const isCredit = student.table === 'credit_transfer_applications';
    const programLabel = isCredit ? 'Credit Transfer' : 'UPAFA Registration';

    $('#pay_student_id').val(student.id);
    $('#pay_table').val(student.table);
    $('#pay_program').text(programLabel);
    $('#pay_name').text(student.full_name || '—');
    $('#pay_email').text(student.email || '—');

    resetStaticTierUi();
    currentPayMode = 'static_fees';

    new bootstrap.Modal(document.getElementById('paymentModal'), { backdrop: 'static', keyboard: false }).show();
  }

  $(document).on('click', '.btn-record-pay', function () {
    openPaymentModal({
      id: $(this).data('id'),
      table: $(this).data('table'),
      full_name: $(this).data('name'),
      email: $(this).data('email')
    });
  });

  /* ===== Payment modal ===== */
  let isSubmitting = false;
  const modalEl = document.getElementById('paymentModal');

  modalEl.addEventListener('hidden.bs.modal', function () {
    document.activeElement?.blur();
    resetStaticTierUi();
    currentPayMode = 'static_fees';
    $('select[name="payment_method"]').val('Cash');
    $('input[name="comment"]').val('');
    isSubmitting = false;
  });

  function startPaymentProgress() {
    $('#paymentProgressBar').css('width', '15%');
    $('#paymentProgressText').text('Recording payment...');
    $('#paymentProgressWrapper').removeClass('d-none');
  }

  function finishPaymentProgress(ok) {
    $('#paymentProgressBar').css('width', '100%');
    $('#paymentProgressText').text(ok ? 'Completed successfully' : 'Failed');
    setTimeout(function () {
      $('#paymentProgressWrapper').addClass('d-none');
      $('#paymentProgressBar').css('width', '0%');
    }, 2000);
  }

  $('#paymentForm').on('submit', function (e) {
    e.preventDefault();
    if (isSubmitting) return;

    isSubmitting = true;
    showLoading();
    startPaymentProgress();

    let payload;
    const base = {
      student_id: $('#pay_student_id').val(),
      table: $('#pay_table').val(),
      payment_method: $('select[name="payment_method"]').val(),
      comment: $('input[name="comment"]').val()
    };

    const tier = $('input[name="fee_tier"]:checked').val();
    const payAmt = Number($('#fee_pay_amount').val() || 0);
    if (!tier) {
      isSubmitting = false;
      hideLoading();
      finishPaymentProgress(false);
      alert('Please select a fee to record');
      return;
    }
    if (payAmt <= 0 || (staticRemaining > 0 && payAmt > staticRemaining)) {
      isSubmitting = false;
      hideLoading();
      finishPaymentProgress(false);
      alert(payAmt <= 0 ? 'Please enter the amount being paid' : 'Amount cannot exceed the remaining balance');
      return;
    }
    payload = Object.assign({}, base, { upafa_fee_tier: tier, pay_amount: payAmt });

    $.ajax({
      url: 'record-special-payment.php',
      method: 'POST',
      data: JSON.stringify(payload),
      contentType: 'application/json',
      dataType: 'json',
      success: function (resp) {
        if (resp && resp.success === true) {
          handlePaymentSuccess(resp);
          return;
        }
        isSubmitting = false;
        finishPaymentProgress(false);
        hideLoading();
        alert(resp?.message || 'Payment failed');
      },
      error: function (xhr) {
        const recovered = parsePaymentResponse(xhr.responseText || '');
        if (recovered && recovered.success === true) {
          handlePaymentSuccess(recovered);
          return;
        }
        isSubmitting = false;
        finishPaymentProgress(false);
        hideLoading();
        let msg = 'Server error. Please try again.';
        const fail = parsePaymentResponse(xhr.responseText || '');
        if (fail && fail.message) msg = fail.message;
        else if (fail && fail.error) msg = fail.error;
        alert(msg);
      }
    });
  });

  doSearch();
})();
</script>
</body>
</html>
