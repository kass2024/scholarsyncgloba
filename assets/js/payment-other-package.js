/**
 * "Other" package flow for Record Application Payment modal.
 */
(function ($) {
  'use strict';

  const CUSTOM_ITEM_KEY = 'custom';
  let customItemNameEdited = false;
  let customPayAmountEdited = false;
  let lastProposedTotal = 0;

  function escHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function isOtherPackageSelected() {
    return $('#package_select').val() === 'other';
  }

  function syncPackageNameToItemField() {
    if (customItemNameEdited) return;
    const name = ($('#custom_package_name').val() || '').trim();
    $('#custom_item_name').val(name);
  }

  function getCustomPackageData() {
    syncPackageNameToItemField();
    const name = ($('#custom_package_name').val() || '').trim();
    const itemName = ($('#custom_item_name').val() || '').trim();
    return {
      name,
      itemName: itemName || name,
      currency: ($('#custom_package_currency').val() || 'CAD').trim(),
      total: Number($('#custom_package_amount').val() || 0)
    };
  }

  function getCustomPaymentInput() {
    return $('#feeItemsWrapper .item-payment-input[data-item-id="custom"]');
  }

  window.syncCustomItemPaymentsFromDom = function (itemPaymentsRef) {
    const $input = getCustomPaymentInput();
    if (!$input.length || !itemPaymentsRef) return;
    const val = Number($input.val() || 0);
    if (val > 0) {
      itemPaymentsRef[CUSTOM_ITEM_KEY] = val;
    } else {
      delete itemPaymentsRef[CUSTOM_ITEM_KEY];
    }
  };

  window.getCustomPaymentAmount = function (itemPaymentsRef) {
    window.syncCustomItemPaymentsFromDom(itemPaymentsRef);
    const fromMap = Number(itemPaymentsRef && itemPaymentsRef[CUSTOM_ITEM_KEY]) || 0;
    if (fromMap > 0) return fromMap;
    return Number(getCustomPaymentInput().val() || 0);
  };

  window.refreshCustomPackageUI = function (itemPaymentsRef, updateGrandTotal) {
    syncPackageNameToItemField();
    const data = getCustomPackageData();

    if (!data.name || data.total <= 0) {
      $('#expected_total, #paid_total, #remaining_total').val('');
      $('#feeItemsWrapper').html(
        '<div class="text-muted text-center py-4">Fill in package name and proposed price above</div>'
      );
      return '';
    }

    window.paymentCurrency = data.currency;
    $('#expected_total').val(`${data.currency} ${data.total.toFixed(2)}`);
    $('#paid_total').val(`${data.currency} 0.00`);
    $('#remaining_total').val(`${data.currency} ${data.total.toFixed(2)}`);

    const itemLabel = escHtml(data.itemName);
    const proposedChanged = data.total !== lastProposedTotal;
    let payVal = data.total;

    if (
      !proposedChanged &&
      customPayAmountEdited &&
      itemPaymentsRef &&
      Number(itemPaymentsRef[CUSTOM_ITEM_KEY]) > 0
    ) {
      payVal = Math.min(Number(itemPaymentsRef[CUSTOM_ITEM_KEY]), data.total);
    } else if (proposedChanged) {
      customPayAmountEdited = false;
    }

    lastProposedTotal = data.total;

    if (itemPaymentsRef) {
      itemPaymentsRef[CUSTOM_ITEM_KEY] = payVal;
    }

    const html = `
      <div class="list-group list-group-flush">
        <div class="list-group-item py-3">
          <div class="row align-items-center">
            <div class="col-md-5">
              <strong>${itemLabel}</strong><br>
              <small class="text-muted">Remaining: ${escHtml(data.currency)} ${data.total.toFixed(2)}</small>
            </div>
            <div class="col-md-4">
              <input type="number" class="form-control form-control-sm item-payment-input"
                min="0.01" max="${data.total}" step="0.01" data-item-id="custom" data-max="${data.total}"
                value="${Number(itemPaymentsRef[CUSTOM_ITEM_KEY]).toFixed(2)}">
            </div>
            <div class="col-md-3 text-end">
              <span class="badge bg-warning text-dark">Other</span>
            </div>
          </div>
        </div>
      </div>`;
    $('#feeItemsWrapper').html(html);

    if (typeof updateGrandTotal === 'function') {
      updateGrandTotal();
    }

    return data.currency;
  };

  window.resetCustomPackageFields = function () {
    customItemNameEdited = false;
    customPayAmountEdited = false;
    lastProposedTotal = 0;
    $('#customPackageFields').addClass('d-none');
    $('#custom_package_name, #custom_item_name, #custom_package_amount').val('');
    $('#custom_package_currency').val('CAD');
  };

  window.appendOtherPackageOption = function (pkgOptionsHtml) {
    return pkgOptionsHtml + '<option value="other">Other (not listed) — enter manually</option>';
  };

  window.buildCustomPaymentPayload = function (basePayload, itemPayments) {
    const data = getCustomPackageData();
    if (!data.name) {
      alert('Please enter a package / service name');
      return null;
    }
    if (data.total <= 0) {
      alert('Please enter a valid proposed total price');
      return null;
    }

    const pay = window.getCustomPaymentAmount(itemPayments);
    if (pay <= 0) {
      alert('Please enter the amount you are recording now');
      return null;
    }
    if (pay > data.total) {
      alert('Payment cannot exceed the proposed total price');
      return null;
    }

    basePayload.custom_package = true;
    basePayload.custom_title = data.name;
    basePayload.custom_item_name = data.itemName;
    basePayload.custom_currency = data.currency;
    basePayload.custom_amount = data.total;
    basePayload.package_id = 0;
    basePayload.items = { custom: pay };
    return basePayload;
  };

  window.isOtherPackageSelected = isOtherPackageSelected;

  $(document).on('input change', '#custom_package_name, #custom_package_currency, #custom_package_amount', function () {
    if (!isOtherPackageSelected()) return;
    if (typeof window._paymentOtherRefresh === 'function') {
      window._paymentOtherRefresh();
    }
  });

  $(document).on('input', '#feeItemsWrapper .item-payment-input[data-item-id="custom"]', function () {
    customPayAmountEdited = true;
  });

  $(document).on('input', '#custom_item_name', function () {
    customItemNameEdited = ($('#custom_item_name').val() || '').trim() !== ($('#custom_package_name').val() || '').trim();
    if (!isOtherPackageSelected()) return;
    if (typeof window._paymentOtherRefresh === 'function') {
      window._paymentOtherRefresh();
    }
  });
})(jQuery);
