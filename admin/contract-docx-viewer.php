<?php
declare(strict_types=1);

/**
 * Embeddable Word contract viewer (docx-preview) for shared hosting.
 *
 * Query: type=source|signed, staff_id optional for superadmin.
 */
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/staff_contract_schema.php';
require_once __DIR__ . '/../helpers/staff_contract_word.php';

$viewerId = (int) ($_SESSION['id'] ?? $_SESSION['admin_id'] ?? 0);
if ($viewerId <= 0) {
    http_response_code(401);
    exit('Unauthorized');
}

$staffId = (int) ($_GET['staff_id'] ?? $viewerId);
$type = ($_GET['type'] ?? 'source') === 'signed' ? 'signed' : 'source';
$expectedPages = 9;

$contract = pcvc_staff_contract_for_admin($conn, $staffId);
if ($contract) {
    if ($type === 'source' && pcvc_staff_contract_row_status($contract)['code'] === 'signed') {
        $type = 'signed';
    }
    try {
        $rel = pcvc_staff_contract_ensure_valid_docx($conn, $staffId, $contract, $type);
        if ($rel !== '') {
            $abs = pcvc_staff_contract_abs_path($rel);
            if (is_file($abs)) {
                $expectedPages = pcvc_staff_contract_expected_page_count($abs);
            }
        }
    } catch (Throwable $e) {
        // Viewer still attempts client render; status bar may show mismatch only.
    }
}

$docxUrl = 'view-staff-contract-docx.php?type=' . rawurlencode($type);
if ($staffId !== $viewerId) {
    $docxUrl .= '&staff_id=' . $staffId;
}
$docxUrl .= '&ts=' . time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contract preview</title>
  <style>
    html, body {
      margin: 0; padding: 0; min-height: 100%;
      background: #d7dee8;
      font-family: 'Times New Roman', Times, serif;
    }
    #toolbar {
      position: sticky;
      top: 0;
      z-index: 100;
      display: flex;
      justify-content: flex-end;
      align-items: center;
      gap: 12px;
      padding: 10px 16px;
      background: #fff;
      border-bottom: 1px solid #dbe3ef;
      font: 14px/1.4 'Segoe UI', system-ui, sans-serif;
    }
    #toolbar button {
      border: 1px solid #3661B9;
      background: #fff;
      color: #3661B9;
      border-radius: 6px;
      padding: 6px 14px;
      font: inherit;
      cursor: pointer;
    }
    #toolbar button:hover {
      background: #eff6ff;
    }
    #status {
      font: 14px/1.4 'Segoe UI', system-ui, sans-serif;
      color: #475569; padding: .5rem 1rem; text-align: center;
    }
    #docx-container {
      padding: 16px 12px 32px;
      box-sizing: border-box;
    }
    #docx-container .docx-wrapper {
      background: transparent;
      margin: 0 auto;
      padding: 0;
    }
    #docx-container .docx-wrapper > section.docx {
      background: #fff;
      margin: 0 auto 24px;
      box-shadow: 0 2px 14px rgba(15, 23, 42, 0.12);
      box-sizing: border-box;
      position: relative;
      font-family: 'Times New Roman', Times, serif !important;
      line-height: 1.15;
      overflow: hidden;
    }
    #docx-container .docx-wrapper > section.docx > header,
    #docx-container .docx-wrapper > section.docx > footer {
      display: none !important;
    }
    #docx-container .docx-page-number {
      position: absolute;
      right: 72px;
      bottom: 42px;
      font: 11px 'Times New Roman', Times, serif;
      color: #334155;
      pointer-events: none;
      z-index: 5;
      line-height: 1;
    }
    #docx-container .docx,
    #docx-container .docx * {
      font-family: 'Times New Roman', Times, serif !important;
    }
    #docx-container p,
    #docx-container span,
    #docx-container li {
      line-height: inherit;
    }
    #docx-container table {
      border-collapse: collapse;
    }
    #docx-container img {
      max-width: 100%;
      height: auto;
    }
    @media print {
      html, body {
        background: #fff !important;
        margin: 0;
        padding: 0;
      }
      #toolbar,
      #status {
        display: none !important;
      }
      #docx-container {
        padding: 0 !important;
      }
      #docx-container .docx-wrapper > section.docx {
        box-shadow: none !important;
        margin: 0 auto !important;
        page-break-after: always;
        break-after: page;
      }
      #docx-container .docx-wrapper > section.docx > header,
      #docx-container .docx-wrapper > section.docx > footer {
        display: none !important;
      }
      #docx-container .docx-page-number {
        right: 72px;
        bottom: 42px;
      }
      #docx-container .docx-wrapper > section.docx:last-child {
        page-break-after: auto;
        break-after: auto;
      }
    }
  </style>
</head>
<body>
  <div id="toolbar">
    <button type="button" id="printBtn" title="Print or save as PDF">Print / Save as PDF</button>
  </div>
  <div id="status">Loading contract…</div>
  <div id="docx-container"></div>
  <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/docx-preview@0.3.3/dist/docx-preview.min.js"></script>
  <script>
  (function () {
    const status = document.getElementById('status');
    const container = document.getElementById('docx-container');
    const expectedPages = <?= (int) ($_GET['expected_pages'] ?? 0) ?>;

    function addPageNumbers(pages) {
      pages.forEach(function (page, idx) {
        page.querySelectorAll('.docx-page-number').forEach(function (el) {
          el.remove();
        });
        const num = document.createElement('div');
        num.className = 'docx-page-number';
        num.textContent = String(idx + 1);
        page.appendChild(num);
      });
      return pages.length;
    }

    fetch(<?= json_encode($docxUrl, JSON_UNESCAPED_SLASHES) ?>, { credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) {
          return res.text().then(function (t) {
            throw new Error(t || ('HTTP ' + res.status));
          });
        }
        return res.blob();
      })
      .then(function (blob) {
        status.style.display = 'none';
        return docx.renderAsync(blob, container, null, {
          className: 'docx',
          inWrapper: true,
          ignoreWidth: false,
          ignoreHeight: false,
          ignoreFonts: false,
          breakPages: true,
          ignoreLastRenderedPageBreak: true,
          renderHeaders: false,
          renderFooters: false,
          renderFootnotes: true,
          renderEndnotes: true,
          useBase64URL: true,
          experimental: true
        });
      })
      .then(function () {
        const pages = container.querySelectorAll('.docx-wrapper > section.docx');
        const rendered = addPageNumbers(pages);
        if (rendered > 0) {
          status.style.display = 'block';
          status.style.color = '#64748b';
          let msg = rendered + ' page(s)';
          if (expectedPages > 0 && rendered !== expectedPages) {
            msg += ' (expected ' + expectedPages + ')';
          }
          status.textContent = msg;
        }
      })
      .catch(function (err) {
        status.style.display = 'block';
        status.textContent = 'Could not load contract: ' + (err.message || 'Unknown error');
        status.style.color = '#b45309';
      });

    window.printContract = function () {
      window.focus();
      window.print();
    };

    document.getElementById('printBtn').addEventListener('click', function () {
      window.printContract();
    });
  })();
  </script>
</body>
</html>
