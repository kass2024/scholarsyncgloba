<?php
declare(strict_types=1);

/**
 * Secured candidate view-details page (full form + attachments + video).
 * Usage: fm-view-details.php?t=TOKEN&s=SECRET
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
require_once __DIR__ . '/helpers/francophonie_mobility_notify.php';
require_once __DIR__ . '/helpers/fm_public_share.php';

fm_ensure_schema($conn);
fm_public_noindex_headers();

$token = trim((string) ($_GET['t'] ?? ''));
$secret = trim((string) ($_GET['s'] ?? ''));
$row = fm_public_load_by_tokens($conn, $token, $secret);

$ownerName = $row ? trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) : '';
$hasVideo = $row && (
    trim((string) ($row['video_pcloud_link'] ?? '')) !== ''
    || trim((string) ($row['video_file'] ?? '')) !== ''
);
$detailsUrl = ($row && $token !== '' && $secret !== '')
    ? fm_public_details_url($token, $secret)
    : '';
$videoUrl = ($row && $hasVideo) ? fm_public_video_url($token, $secret) : '';
$attachments = $row ? fm_public_attachment_list($row) : [];
$copyText = $row ? fm_public_copy_bundle($row, $detailsUrl) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive">
<title><?= $row ? htmlspecialchars($ownerName . ' — View details', ENT_QUOTES, 'UTF-8') : 'Link not found' ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{background:#f4f6f3;font-family:Segoe UI,system-ui,sans-serif}
.hero{background:linear-gradient(135deg,#1e4d2b,#3661B9);color:#fff;padding:1.5rem 0;margin-bottom:1.25rem}
.card{border:0;border-radius:14px;box-shadow:0 8px 24px rgba(15,23,42,.08)}
.doc-row{background:#fff}
.secure-badge{font-size:.75rem;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);border-radius:999px;padding:.2rem .7rem}
</style>
</head>
<body>
<div class="hero">
  <div class="container" style="max-width:920px">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <div class="small opacity-75">Canada Francophonie Mobility</div>
        <h1 class="h4 mb-0">View details</h1>
      </div>
      <?php if ($row): ?>
      <span class="secure-badge"><i class="fas fa-lock me-1"></i> Secured link</span>
      <?php endif; ?>
    </div>
  </div>
</div>
<div class="container pb-5" style="max-width:920px">
<?php if (!$row): ?>
  <div class="alert alert-warning">
    This secured link is invalid or incomplete.
    Ask the office for a new <strong>View details</strong> link (token + secret required).
  </div>
<?php else: ?>
  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-3">
        <div>
          <h2 class="h5 mb-1"><?= htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8') ?></h2>
          <div class="text-muted small">Reference <code><?= htmlspecialchars((string) $row['reference_id'], ENT_QUOTES, 'UTF-8') ?></code></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <?php if ($videoUrl !== ''): ?>
          <a class="btn btn-danger btn-sm" href="<?= htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
            <i class="fas fa-play me-1"></i> Open video
          </a>
          <?php endif; ?>
          <button type="button" class="btn btn-outline-primary btn-sm" id="copyDetailsBtn"
                  data-copy="<?= htmlspecialchars($copyText, ENT_QUOTES, 'UTF-8') ?>">
            <i class="fas fa-copy me-1"></i> Copy owner + link
          </button>
        </div>
      </div>
      <?= fm_build_form_summary_html($row) ?>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <h3 class="h6 mb-3"><i class="fas fa-paperclip me-2 text-primary"></i>Attachments</h3>
      <?php if ($attachments === []): ?>
        <div class="text-muted small">No documents uploaded.</div>
      <?php else: foreach ($attachments as $doc):
        $viewUrl = fm_public_file_url($token, $secret, $doc['key'], true);
        $dlUrl = fm_public_file_url($token, $secret, $doc['key'], false);
      ?>
        <div class="doc-row border rounded p-2 mb-2 d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2">
          <span class="text-break"><i class="fas fa-file me-2"></i><?= htmlspecialchars($doc['label'], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="d-flex flex-wrap gap-1">
            <a href="<?= htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> View</a>
            <a href="<?= htmlspecialchars($dlUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-primary"><i class="fas fa-download"></i> Download</a>
          </span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h3 class="h6 mb-2"><i class="fas fa-video me-2 text-danger"></i>Introduction video</h3>
      <?php if ($videoUrl !== ''): ?>
        <p class="small text-muted mb-2">
          Source: <strong><?= htmlspecialchars(ucfirst((string) ($row['video_source'] ?: 'upload')), ENT_QUOTES, 'UTF-8') ?></strong>
          · opened through secured MIS link (storage credentials not shared)
        </p>
        <a class="btn btn-sm btn-danger" href="<?= htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
          <i class="fas fa-external-link-alt me-1"></i> Open video
        </a>
      <?php else: ?>
        <div class="alert alert-secondary mb-0">No introduction video on file yet.</div>
      <?php endif; ?>
    </div>
  </div>

<script>
(function () {
  const btn = document.getElementById('copyDetailsBtn');
  if (!btn) return;
  btn.addEventListener('click', function () {
    const text = this.getAttribute('data-copy') || '';
    const mark = () => {
      const old = this.innerHTML;
      this.innerHTML = '<i class="fas fa-check me-1"></i> Copied';
      setTimeout(() => { this.innerHTML = old; }, 1800);
    };
    const fallback = () => {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); mark(); }
      catch (e) { prompt('Copy this:', text); }
      document.body.removeChild(ta);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(mark).catch(fallback);
    } else {
      fallback();
    }
  });
})();
</script>
<?php endif; ?>
</div>
</body>
</html>
