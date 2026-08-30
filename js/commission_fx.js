/**
 * Commission form — live USD/CAD → RWF preview (same FX source as checkout).
 */
(function (global) {
  "use strict";

  var USD_FALLBACK = 1300;
  var CAD_FALLBACK = 1050;
  var rates = { usd: USD_FALLBACK, cad: CAD_FALLBACK };

  function parseRate(value, fallback) {
    var n = parseFloat(String(value || "").replace(",", "."));
    return isFinite(n) && n > 0 ? n : fallback;
  }

  function readRatesFromDom() {
    var root =
      document.getElementById("commissionAmountBlock") ||
      document.getElementById("commissionForm");
    if (!root || !root.dataset) {
      return;
    }
    rates.usd = parseRate(root.dataset.usdRwfRate, USD_FALLBACK);
    rates.cad = parseRate(root.dataset.cadRwfRate, CAD_FALLBACK);
  }

  function el(id) {
    return document.getElementById(id);
  }

  function getCurrency() {
    var sel = el("commissionCurrency");
    return String(sel && sel.value ? sel.value : "USD").toUpperCase();
  }

  function getRate() {
    return getCurrency() === "CAD" ? rates.cad : rates.usd;
  }

  function formatMoney(n) {
    try {
      return new Intl.NumberFormat().format(n);
    } catch (e) {
      return String(n);
    }
  }

  function updateAmountLabel() {
    var label = el("amountLabel");
    if (!label) {
      return;
    }
    var root = document.getElementById("commissionAmountBlock");
    var prefix =
      root && root.dataset && root.dataset.labelPrefix
        ? root.dataset.labelPrefix
        : "Amount requested";
    var suffix =
      root && root.dataset && root.dataset.labelSuffix !== undefined
        ? root.dataset.labelSuffix
        : " *";
    label.textContent = prefix + " (" + getCurrency() + ")" + suffix;
  }

  function updateRwfPreview() {
    var input = el("amountUsd");
    var out = el("rwfPreview");
    if (!input || !out) {
      return;
    }
    var v = parseFloat(String(input.value).replace(",", "."));
    if (!isFinite(v) || v <= 0) {
      out.textContent = "\u2014";
      return;
    }
    var rwf = Math.round(v * getRate());
    out.textContent = formatMoney(rwf) + " RWF";
  }

  function onCurrencyChange() {
    updateAmountLabel();
    updateRwfPreview();
  }

  function refreshLiveRates() {
    var base;
    try {
      base = new URL("payments/api/fx-rate.php", global.location.href);
    } catch (e) {
      return;
    }

    ["USD", "CAD"].forEach(function (cur) {
      var url = new URL(base.toString());
      url.searchParams.set("from", cur);
      fetch(url.toString(), { cache: "no-store", credentials: "same-origin" })
        .then(function (res) {
          return res.json();
        })
        .then(function (data) {
          if (!data || !data.ok) {
            return;
          }
          var r = parseFloat(data.rate);
          if (!isFinite(r) || r <= 50) {
            return;
          }
          if (cur === "USD") {
            rates.usd = r;
          }
          if (cur === "CAD") {
            rates.cad = r;
          }
          var fxUsd = el("fxUsdRate");
          var fxCad = el("fxCadRate");
          if (fxUsd && cur === "USD") {
            fxUsd.textContent = r.toFixed(2);
          }
          if (fxCad && cur === "CAD") {
            fxCad.textContent = r.toFixed(2);
          }
          updateRwfPreview();
        })
        .catch(function () {});
    });
  }

  function bindDelegatedEvents() {
    document.addEventListener(
      "input",
      function (e) {
        var t = e.target;
        if (!t) {
          return;
        }
        if (t.id === "amountUsd") {
          updateRwfPreview();
        }
        if (t.id === "commissionCurrency") {
          onCurrencyChange();
        }
      },
      true
    );

    document.addEventListener(
      "change",
      function (e) {
        var t = e.target;
        if (!t) {
          return;
        }
        if (t.id === "amountUsd") {
          updateRwfPreview();
        }
        if (t.id === "commissionCurrency") {
          onCurrencyChange();
        }
      },
      true
    );
  }

  function init() {
    readRatesFromDom();
    bindDelegatedEvents();
    updateAmountLabel();
    updateRwfPreview();
    refreshLiveRates();

    global.PCVC_USD_RWF_RATE = rates.usd;
    global.PCVC_CAD_RWF_RATE = rates.cad;
  }

  global.pcvcUpdateRwfPreview = updateRwfPreview;
  global.pcvcOnCurrencyChange = onCurrencyChange;
  global.pcvcGetSelectedCurrency = getCurrency;
  global.pcvcGetCommissionFxRate = getRate;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})(window);
