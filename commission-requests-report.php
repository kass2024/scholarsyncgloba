<?php
declare(strict_types=1);

date_default_timezone_set('UTC');
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/commission_requests_schema.php';
require_once __DIR__ . '/includes/company_branding.php';

$adminId = (int) ($_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
if ($adminId < 1) {
    header('Location: admin-login.php');
    exit;
}

$st = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
$st->bind_param('i', $adminId);
$st->execute();
$roleRow = $st->get_result()->fetch_assoc();
$st->close();
$role = (string) ($roleRow['role'] ?? '');

if (!pcvc_is_superadmin_role($role)) {
    header('Location: Commission-Request.php');
    exit;
}

pcvc_ensure_commission_requests_schema($conn);

$_SESSION['commission_admin_csrf'] = bin2hex(random_bytes(32));
$commissionCsrf = $_SESSION['commission_admin_csrf'];

$statusLabels = [
    'pending' => 'Pending',
    'under_review' => 'Under review',
    'approved' => 'Approved',
    'paid_partial' => 'Paid (partial)',
    'paid_full' => 'Paid in full',
    'rejected' => 'Rejected',
];

$commissionStepOrder = ['pending', 'under_review', 'approved', 'paid_partial', 'paid_full'];
$commissionStepLabels = [
    'pending' => 'Pending',
    'under_review' => 'Review',
    'approved' => 'Approved',
    'paid_partial' => 'Partial',
    'paid_full' => 'Paid',
];

/** Prefill live filters from URL (all rows load; filtering is client-side). */
$searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$statusFilter = isset($_GET['commission_status']) ? trim((string) $_GET['commission_status']) : '';
$statusFilterValid = $statusFilter !== '' && isset($statusLabels[$statusFilter]);

$sql = 'SELECT * FROM commission_requests ORDER BY id DESC';
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->execute();
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $requests = [];
}

$totalCount = count($requests);

$colors = [
    'navy' => '#427431',
    'secondary_blue' => '#3661B9',
    'dark_blue' => '#2f5a26',
    'gold' => '#E21D1E',
    'white' => '#FFFFFF',
    'light_gray' => '#F8F9FA',
    'border_gray' => '#E0E0E0',
];

function pcvc_commission_step_index(string $status, array $order): int
{
    if ($status === 'rejected') {
        return -1;
    }
    $i = array_search($status, $order, true);

    return $i === false ? 0 : (int) $i;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Commission requests | <?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: <?= $colors['light_gray'] ?>;
      color: <?= $colors['navy'] ?>;
      min-height: 100vh;
    }
    .dashboard-container { padding: 20px; max-width: 1400px; margin: 0 auto; }
    .dashboard-header {
      background: <?= $colors['white'] ?>;
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      border-left: 5px solid <?= $colors['gold'] ?>;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 15px;
    }
    .header-title h1 {
      font-size: 1.45rem;
      display: flex;
      align-items: center;
      gap: 10px;
      color: <?= $colors['navy'] ?>;
    }
    .header-title h1 i { color: <?= $colors['gold'] ?>; }
    .header-title p { color: #666; font-size: 0.9rem; margin-top: 6px; }
    .header-stats { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
    .stat-pill {
      background: linear-gradient(135deg, <?= $colors['navy'] ?>, <?= $colors['dark_blue'] ?>);
      color: #fff;
      padding: 8px 16px;
      border-radius: 999px;
      font-weight: 700;
      font-size: 0.88rem;
    }
    .search-container {
      background: <?= $colors['white'] ?>;
      padding: 16px 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .search-form { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .search-input {
      flex: 1;
      min-width: 200px;
      padding: 12px 16px;
      border: 2px solid <?= $colors['border_gray'] ?>;
      border-radius: 10px;
      font-size: 0.95rem;
    }
    .search-input:focus {
      outline: none;
      border-color: <?= $colors['gold'] ?>;
      box-shadow: 0 0 0 3px rgba(226, 29, 30, 0.15);
    }
    .filter-select {
      padding: 12px 14px;
      border-radius: 10px;
      border: 2px solid <?= $colors['border_gray'] ?>;
      font-weight: 600;
      color: <?= $colors['navy'] ?>;
      min-width: 180px;
    }
    .search-btn, .clear-btn {
      padding: 12px 20px;
      border-radius: 10px;
      border: none;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      font-size: 0.9rem;
    }
    .search-btn {
      background: <?= $colors['navy'] ?>;
      color: #fff;
    }
    .clear-btn {
      background: <?= $colors['secondary_blue'] ?>;
      color: #fff;
    }
    .search-info {
      background: rgba(242, 166, 90, 0.12);
      padding: 10px 14px;
      border-radius: 8px;
      margin-bottom: 12px;
      border-left: 4px solid <?= $colors['gold'] ?>;
      font-size: 0.88rem;
    }
    .applicants-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
      gap: 20px;
    }
    .applicant-card {
      background: <?= $colors['white'] ?>;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(0,0,0,0.07);
      border: 1px solid <?= $colors['border_gray'] ?>;
      transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .applicant-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 28px rgba(0,0,0,0.1);
    }
    .card-header {
      background: linear-gradient(135deg, <?= $colors['navy'] ?> 0%, <?= $colors['dark_blue'] ?> 100%);
      color: #fff;
      padding: 14px 16px;
    }
    .applicant-name { font-weight: 700; font-size: 1.05rem; }
    .applicant-id { font-size: 0.78rem; opacity: 0.9; margin-top: 4px; font-family: monospace; }
    .process-panel {
      background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
      border-bottom: 1px solid <?= $colors['border_gray'] ?>;
      padding: 12px 14px 14px;
    }
    .process-panel-title {
      font-size: 0.7rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      color: <?= $colors['secondary_blue'] ?>;
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .process-panel-title i { color: <?= $colors['gold'] ?>; }
    .process-tracker {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 4px;
      margin-bottom: 12px;
      overflow-x: auto;
      padding-bottom: 4px;
    }
    .process-step {
      flex: 1;
      min-width: 56px;
      text-align: center;
      position: relative;
    }
    .process-step:not(:last-child)::after {
      content: '';
      position: absolute;
      top: 10px;
      left: calc(50% + 12px);
      right: calc(-50% + 12px);
      height: 3px;
      background: #e2e8f0;
      z-index: 0;
    }
    .process-step.done:not(:last-child)::after {
      background: linear-gradient(90deg, <?= $colors['gold'] ?>, #e2e8f0);
    }
    .process-step-dot {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      margin: 0 auto 5px;
      position: relative;
      z-index: 1;
      background: #e2e8f0;
      border: 2px solid #fff;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .process-step.done .process-step-dot { background: <?= $colors['gold'] ?>; }
    .process-step.current .process-step-dot {
      background: <?= $colors['navy'] ?>;
      border-color: <?= $colors['gold'] ?>;
      box-shadow: 0 0 0 3px rgba(226, 29, 30, 0.25);
    }
    .process-step-label {
      font-size: 0.62rem;
      line-height: 1.2;
      color: #64748b;
      font-weight: 700;
    }
    .process-step.done .process-step-label,
    .process-step.current .process-step-label { color: <?= $colors['navy'] ?>; }
    .process-status-row {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      justify-content: space-between;
    }
    .status-pill {
      display: inline-flex;
      padding: 5px 10px;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 800;
      background: #e2e8f0;
      color: #334155;
    }
    .status-pill.rejected { background: #fee2e2; color: #991b1b; }
    .commission-status-select {
      flex: 1;
      min-width: 140px;
      max-width: 100%;
      padding: 7px 10px;
      border-radius: 8px;
      border: 2px solid <?= $colors['border_gray'] ?>;
      font-size: 0.82rem;
      font-weight: 700;
      color: <?= $colors['navy'] ?>;
      cursor: pointer;
    }
    .commission-status-select:focus {
      outline: none;
      border-color: <?= $colors['gold'] ?>;
    }
    .card-body { padding: 14px 16px; }
    .info-row { display: flex; gap: 10px; margin-bottom: 10px; font-size: 0.88rem; }
    .info-label { width: 96px; flex-shrink: 0; font-weight: 700; color: <?= $colors['secondary_blue'] ?>; }
    .info-value { flex: 1; color: #334155; word-break: break-word; }
    .info-value i { color: <?= $colors['gold'] ?>; width: 16px; margin-right: 6px; }
    .money-line { font-weight: 800; color: <?= $colors['navy'] ?>; }
    .card-footer {
      padding: 12px 16px;
      background: #f8fafc;
      border-top: 1px solid <?= $colors['border_gray'] ?>;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
    }
    .view-btn, .contact-btn {
      padding: 8px 14px;
      border-radius: 8px;
      border: none;
      font-weight: 700;
      font-size: 0.85rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
    }
    .view-btn {
      background: <?= $colors['secondary_blue'] ?>;
      color: #fff;
    }
    .contact-btn {
      background: <?= $colors['gold'] ?>;
      color: #1e293b;
    }
    .empty-state {
      grid-column: 1 / -1;
      text-align: center;
      padding: 48px 20px;
      background: <?= $colors['white'] ?>;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .empty-state i { font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px; }
    #detailsModal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.5);
      z-index: 10000;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    #detailsModal.show-flex { display: flex !important; }
    .modal-inner {
      background: #fff;
      border-radius: 16px;
      max-width: 640px;
      width: 100%;
      max-height: 92vh;
      overflow-y: auto;
      box-shadow: 0 24px 60px rgba(0,0,0,0.2);
    }
    .modal-head {
      background: linear-gradient(135deg, <?= $colors['navy'] ?>, <?= $colors['secondary_blue'] ?>);
      color: #fff;
      padding: 16px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }
    .modal-head h3 { font-size: 1.1rem; margin: 0; }
    .modal-close {
      background: rgba(255,255,255,0.2);
      border: none;
      color: #fff;
      width: 36px;
      height: 36px;
      border-radius: 10px;
      font-size: 1.4rem;
      cursor: pointer;
      line-height: 1;
    }
    .modal-body { padding: 20px; }
    .modal-section {
      margin-bottom: 18px;
      padding-bottom: 16px;
      border-bottom: 1px solid #f1f5f9;
    }
    .modal-section h4 {
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: <?= $colors['secondary_blue'] ?>;
      margin-bottom: 10px;
    }
    .modal-grid { display: grid; gap: 8px; font-size: 0.9rem; }
    .modal-row { display: flex; gap: 10px; }
    .modal-row strong { width: 130px; flex-shrink: 0; color: #475569; }
    .admin-box {
      background: linear-gradient(180deg, #f8fafc, #fff);
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 16px;
      margin-top: 8px;
    }
    .admin-box h4 { margin-bottom: 12px; color: <?= $colors['navy'] ?>; text-transform: none; letter-spacing: 0; font-size: 1rem; }
    .form-fld { margin-bottom: 12px; }
    .form-fld label { display: block; font-size: 0.78rem; font-weight: 700; color: #64748b; margin-bottom: 4px; }
    .form-fld input, .form-fld select, .form-fld textarea {
      width: 100%;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid #cbd5e1;
      font-size: 0.9rem;
    }
    .form-fld textarea { min-height: 64px; resize: vertical; }
    .btn-momo {
      background: <?= $colors['gold'] ?>;
      color: #1e293b;
      border: none;
      padding: 10px 18px;
      border-radius: 10px;
      font-weight: 800;
      cursor: pointer;
      margin-top: 8px;
    }
    .process-toast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 10001;
      background: <?= $colors['navy'] ?>;
      color: #fff;
      padding: 12px 18px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.9rem;
      box-shadow: 0 8px 24px rgba(0,0,0,0.2);
      display: none;
      align-items: center;
      gap: 10px;
    }
    .process-toast.show { display: flex; }
    .commission-rejected-banner { margin-bottom: 10px; }
    .commission-notify-overlay {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 100020;
      background: rgba(15, 23, 42, 0.55);
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    .commission-notify-overlay.show-flex { display: flex !important; }
    .commission-notify-dialog {
      background: #fff;
      border-radius: 16px;
      max-width: 520px;
      width: 100%;
      box-shadow: 0 24px 60px rgba(0,0,0,0.25);
      overflow: hidden;
    }
    .commission-notify-header {
      background: linear-gradient(135deg, <?= $colors['navy'] ?>, <?= $colors['secondary_blue'] ?>);
      color: #fff;
      padding: 18px 20px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
    }
    .commission-notify-header h3 { margin: 0; font-size: 1.1rem; font-weight: 700; }
    .commission-notify-header p { margin: 6px 0 0; font-size: 0.85rem; opacity: 0.92; }
    .commission-notify-close {
      background: rgba(255,255,255,0.2);
      border: none;
      color: #fff;
      width: 36px;
      height: 36px;
      border-radius: 10px;
      font-size: 1.4rem;
      cursor: pointer;
      line-height: 1;
    }
    .commission-notify-body { padding: 18px 20px; }
    .commission-notify-preview {
      background: #f8fafc;
      border-radius: 12px;
      padding: 12px 14px;
      margin-bottom: 14px;
      border: 1px solid #e2e8f0;
    }
    .commission-notify-badge {
      display: inline-block;
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: <?= $colors['secondary_blue'] ?>;
      margin-bottom: 6px;
    }
    .commission-notify-label-text { font-weight: 800; color: <?= $colors['navy'] ?>; font-size: 1rem; }
    .commission-notify-hint { font-size: 0.82rem; color: #64748b; margin-bottom: 10px; }
    .commission-notify-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 8px;
    }
    @media (min-width: 520px) {
      .commission-notify-grid { grid-template-columns: repeat(4, 1fr); }
    }
    .commission-notify-channel {
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      padding: 10px 8px;
      background: #fff;
      cursor: pointer;
      font-size: 0.78rem;
      font-weight: 700;
      color: #334155;
      text-align: center;
      transition: border-color 0.2s, background 0.2s;
    }
    .commission-notify-channel:hover { border-color: #cbd5e1; }
    .commission-notify-channel.active {
      border-color: <?= $colors['navy'] ?>;
      background: rgba(66, 116, 49, 0.08);
      color: <?= $colors['navy'] ?>;
    }
    .commission-notify-channel span { display: block; font-size: 0.68rem; font-weight: 500; opacity: 0.85; margin-top: 4px; }
    .commission-notify-footer {
      padding: 14px 20px 18px;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      border-top: 1px solid #f1f5f9;
      background: #fafafa;
    }
    .commission-notify-cancel {
      background: #fff;
      border: 1px solid #cbd5e1;
      color: #475569;
      padding: 10px 18px;
      border-radius: 10px;
      font-weight: 700;
      cursor: pointer;
    }
    .commission-notify-confirm {
      background: linear-gradient(135deg, <?= $colors['navy'] ?>, <?= $colors['secondary_blue'] ?>);
      color: #fff;
      border: none;
      padding: 10px 22px;
      border-radius: 10px;
      font-weight: 700;
      cursor: pointer;
    }
    .commission-notify-confirm:disabled,
    .commission-notify-cancel:disabled {
      opacity: 0.65;
      cursor: not-allowed;
    }
    .commission-save-overlay {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 100050;
      background: rgba(15, 23, 42, 0.5);
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    .commission-save-overlay.show-flex { display: flex !important; }
    .commission-save-card {
      background: #fff;
      padding: 28px 36px;
      border-radius: 16px;
      text-align: center;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
      min-width: 220px;
    }
    .commission-save-spin {
      width: 42px;
      height: 42px;
      border: 3px solid #e2e8f0;
      border-top-color: <?= $colors['navy'] ?>;
      border-radius: 50%;
      animation: commissionSaveSpin 0.75s linear infinite;
      margin: 0 auto 14px;
    }
    @keyframes commissionSaveSpin { to { transform: rotate(360deg); } }
    .commission-save-card p { margin: 0; font-weight: 700; color: <?= $colors['navy'] ?>; font-size: 0.95rem; }
    #mSaveStatus:disabled { opacity: 0.65; cursor: not-allowed; }
    #momoPhonePreviewOverlay {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 100022;
      background: rgba(15, 23, 42, 0.55);
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    #momoPhonePreviewOverlay.show-flex { display: flex !important; }
    .momo-phone-preview-dialog {
      background: #fff;
      border-radius: 16px;
      max-width: 480px;
      width: 100%;
      box-shadow: 0 24px 60px rgba(0,0,0,0.25);
      overflow: hidden;
    }
    .momo-phone-preview-head {
      background: linear-gradient(135deg, <?= $colors['navy'] ?>, <?= $colors['secondary_blue'] ?>);
      color: #fff;
      padding: 16px 18px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 10px;
    }
    .momo-phone-preview-head h3 { margin: 0; font-size: 1.05rem; font-weight: 700; }
    .momo-phone-preview-head p { margin: 6px 0 0; font-size: 0.82rem; opacity: 0.92; }
    .momo-phone-preview-body { padding: 16px 18px; font-size: 0.9rem; }
    .momo-phone-preview-row { margin-bottom: 12px; }
    .momo-phone-preview-row strong { display: block; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 4px; }
    .momo-phone-preview-row span, .momo-phone-preview-row a { color: #1e293b; word-break: break-all; }
    .momo-phone-preview-actions { padding: 12px 18px 16px; border-top: 1px solid #f1f5f9; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
    .momo-phone-preview-close {
      background: rgba(255,255,255,0.2);
      border: none;
      color: #fff;
      width: 36px;
      height: 36px;
      border-radius: 10px;
      font-size: 1.4rem;
      cursor: pointer;
      line-height: 1;
    }
    @media (max-width: 768px) {
      .applicants-grid { grid-template-columns: 1fr; }
      .search-form { flex-direction: column; align-items: stretch; }
      .search-btn, .clear-btn { justify-content: center; }
    }
  </style>
</head>
<body>

<div class="dashboard-container">
  <div class="dashboard-header">
    <div class="header-title">
      <h1><i class="fas fa-hand-holding-usd"></i> Commission requests</h1>
      <p>Same workflow view as job applications · <?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="header-stats">
      <span class="stat-pill" id="commissionShownPill"><i class="fas fa-list"></i> <?= (int) $totalCount ?> shown</span>
    </div>
  </div>

  <div class="search-container">
    <div class="search-form" id="commissionLiveFilterBar">
      <input type="text" id="commissionLiveSearch" class="search-input" placeholder="Search name, email, recruited student…" value="<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
      <select id="commissionLiveStatus" class="filter-select" title="Filter by status">
        <option value="">All statuses</option>
        <?php foreach ($statusLabels as $k => $lab): ?>
          <option value="<?= htmlspecialchars($k) ?>" <?= $statusFilterValid && $statusFilter === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="button" class="clear-btn" id="commissionLiveClear" style="display:none;"><i class="fas fa-times"></i> Clear</button>
    </div>
  </div>

  <div class="applicants-grid" id="commissionApplicantsGrid">
    <?php if ($requests === []): ?>
      <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>No commission requests</h3>
        <p>Agents have not submitted any requests yet.</p>
      </div>
    <?php else: ?>
      <div class="empty-state" id="commissionNoMatches" style="display:none;">
        <i class="fas fa-search"></i>
        <h3>No matching requests</h3>
        <p>Try a different search or status.</p>
      </div>
      <?php foreach ($requests as $r):
        $rid = (int) $r['id'];
        $fullName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        $st = trim((string) ($r['request_status'] ?? ''));
        if ($st === '') {
            $st = 'pending';
        }
        $searchRaw = trim(
            $fullName . ' '
            . ($r['email'] ?? '') . ' '
            . ($r['phone'] ?? '') . ' '
            . ($r['recruited_name'] ?? '') . ' '
            . ($r['recruited_phone'] ?? '')
            . ' #' . $rid
        );
        $searchBlob = function_exists('mb_strtolower')
            ? mb_strtolower($searchRaw, 'UTF-8')
            : strtolower($searchRaw);
        $isRejected = ($st === 'rejected');
        $curIdx = pcvc_commission_step_index($st, $commissionStepOrder);
        $usd = isset($r['amount_usd']) ? (float) $r['amount_usd'] : 0.0;
        $rwf = isset($r['amount_rwf']) ? (float) $r['amount_rwf'] : 0.0;
        $paid = isset($r['paid_rwf_total']) ? (float) $r['paid_rwf_total'] : 0.0;
        $rem = max(0.0, $rwf - $paid);
        $created = $r['created_at'] ?? $r['submission_date'] ?? '';
        $modalPayload = [
          'id' => $rid,
          'name' => $fullName,
          'email' => $r['email'] ?? '',
          'phone' => $r['phone'] ?? '',
          'city' => $r['city'] ?? '',
          'country_applied' => $r['country_applied'] ?? '',
          'loan_status' => $r['loan_status'] ?? '',
          'visa_status' => $r['visa_status'] ?? '',
          'contract_signed' => $r['contract_signed'] ?? '',
          'recruited_name' => $r['recruited_name'] ?? '',
          'recruited_phone' => $r['recruited_phone'] ?? '',
          'submission_date' => $r['submission_date'] ?? '',
          'comments' => $r['comments'] ?? '',
          'internal_note' => $r['internal_note'] ?? '',
          'rejection_reason' => $r['rejection_reason'] ?? '',
          'amount_usd' => $usd,
          'commission_currency' => strtoupper(trim((string) ($r['commission_currency'] ?? 'USD'))) ?: 'USD',
          'amount_rwf' => $rwf,
          'fx_rate_used' => $r['fx_rate_used'] ?? '',
          'paid_rwf_total' => $paid,
          'remaining_rwf' => $rem,
          'request_status' => $st,
        ];
        ?>
        <div class="applicant-card" data-commission-id="<?= $rid ?>" data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>" data-status="<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>">
          <div class="card-header">
            <div class="applicant-name"><?= htmlspecialchars($fullName) ?></div>
            <div class="applicant-id">Request #<?= $rid ?></div>
          </div>

          <div class="process-panel">
            <div class="process-panel-title"><i class="fas fa-route"></i> Request progress</div>
            <div class="commission-rejected-banner" style="display: <?= $isRejected ? 'block' : 'none' ?>;">
              <div class="status-pill rejected" style="margin-bottom:10px;"><i class="fas fa-times-circle"></i> Rejected</div>
              <?php if ($isRejected && trim((string) ($r['rejection_reason'] ?? '')) !== ''): ?>
                <p style="font-size:0.82rem;color:#64748b;margin:0;line-height:1.45;"><strong>Reason:</strong> <?= htmlspecialchars((string) $r['rejection_reason'], ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
            </div>
            <div class="process-tracker" style="display: <?= $isRejected ? 'none' : 'flex' ?>;">
                <?php foreach ($commissionStepOrder as $i => $stepKey):
                  $cls = $isRejected ? 'pending' : ($i < $curIdx ? 'done' : ($i === $curIdx ? 'current' : 'pending'));
                  $slab = $commissionStepLabels[$stepKey] ?? $stepKey;
                  ?>
                  <div class="process-step <?= $cls ?>">
                    <span class="process-step-dot"></span>
                    <span class="process-step-label"><?= htmlspecialchars($slab) ?></span>
                  </div>
                <?php endforeach; ?>
            </div>
            <div class="process-status-row">
              <span class="status-pill <?= $isRejected ? 'rejected' : '' ?>"><?= htmlspecialchars($statusLabels[$st] ?? $st) ?></span>
              <select class="commission-status-select" data-commission-id="<?= $rid ?>" title="Change status — choose whether to notify">
                <?php foreach ($statusLabels as $k => $lab): ?>
                  <option value="<?= htmlspecialchars($k) ?>" <?= $k === $st ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="card-body">
            <div class="info-row">
              <div class="info-label">Contact</div>
              <div class="info-value">
                <div><i class="fas fa-envelope"></i><?= htmlspecialchars((string) ($r['email'] ?? '')) ?></div>
                <div><i class="fas fa-phone"></i><?= htmlspecialchars((string) ($r['phone'] ?? '')) ?></div>
              </div>
            </div>
            <div class="info-row">
              <div class="info-label">Amounts</div>
              <div class="info-value money-line">
                USD <?= number_format($usd, 2) ?> · RWF <?= number_format($rwf, 0) ?>
                <div style="font-size:0.8rem;font-weight:600;color:#64748b;margin-top:4px;">Paid RWF <?= number_format($paid, 0) ?> · Rem. <?= number_format($rem, 0) ?></div>
              </div>
            </div>
            <div class="info-row">
              <div class="info-label">Student</div>
              <div class="info-value"><i class="fas fa-user-graduate"></i><?= htmlspecialchars((string) ($r['recruited_name'] ?? '—')) ?></div>
            </div>
          </div>

          <div class="card-footer">
            <span style="font-size:0.8rem;color:#64748b;"><i class="fas fa-clock"></i> <?= htmlspecialchars((string) $created) ?></span>
            <div style="display:flex;gap:8px;">
              <a class="contact-btn" href="mailto:<?= htmlspecialchars((string) ($r['email'] ?? '')) ?>"><i class="fas fa-envelope"></i> Email</a>
              <button type="button" class="view-btn open-commission-modal" data-commission="<?= htmlspecialchars(json_encode($modalPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                <i class="fas fa-cog"></i> Manage
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div id="commissionStatusNotifyModal" class="commission-notify-overlay" aria-hidden="true">
  <div class="commission-notify-dialog" role="dialog" aria-labelledby="commissionNotifyTitle">
    <div class="commission-notify-header">
      <div>
        <h3 id="commissionNotifyTitle"><i class="fas fa-bell"></i> Save status</h3>
        <p>Notify the agent about this update (optional)</p>
      </div>
      <button type="button" class="commission-notify-close" id="commissionNotifyCloseX" aria-label="Close">&times;</button>
    </div>
    <div class="commission-notify-body">
      <div class="commission-notify-preview">
        <div class="commission-notify-badge">New status</div>
        <div class="commission-notify-label-text" id="commissionNotifyStatusLabel">—</div>
      </div>
      <p class="commission-notify-hint">Choose one — you can save without sending anything.</p>
      <div class="commission-notify-grid" id="commissionInlineNotifyGrid">
        <button type="button" class="commission-notify-channel active" data-ne="0" data-nw="0">Record only<span>No notification</span></button>
        <button type="button" class="commission-notify-channel" data-ne="1" data-nw="0">Email<span>Send email</span></button>
        <button type="button" class="commission-notify-channel" data-ne="0" data-nw="1">WhatsApp<span>Send WhatsApp</span></button>
        <button type="button" class="commission-notify-channel" data-ne="1" data-nw="1">Both<span>Email + WhatsApp</span></button>
      </div>
      <div class="form-fld" id="commissionNotifyMessageWrap" style="display:none;margin-top:14px;margin-bottom:4px;">
        <label style="display:block;font-size:0.78rem;font-weight:700;color:#64748b;margin-bottom:4px;">Rejection reason</label>
        <textarea id="commissionNotifyMessage" rows="3" maxlength="2000" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:0.9rem;resize:vertical;" placeholder="Explain why this commission request was rejected…"></textarea>
        <p style="margin:6px 0 0;font-size:0.75rem;color:#64748b;">Required when you choose <strong>Email</strong>, <strong>WhatsApp</strong>, or <strong>Both</strong>. Saved on the request even with “Record only”.</p>
        <p style="margin:6px 0 0;font-size:0.72rem;color:#94a3b8;">WhatsApp uses Meta template <strong>pcvc_commission_update</strong> (4 variables) — see .env.example.</p>
      </div>
    </div>
    <div class="commission-notify-footer">
      <button type="button" class="commission-notify-cancel" id="commissionNotifyCancel">Cancel</button>
      <button type="button" class="commission-notify-confirm" id="commissionNotifyConfirm"><i class="fas fa-save" id="commissionNotifyConfirmIcon"></i> <span id="commissionNotifyConfirmLabel">Save</span></button>
    </div>
  </div>
</div>

<div id="commissionSaveOverlay" class="commission-save-overlay" aria-hidden="true">
  <div class="commission-save-card">
    <div class="commission-save-spin" aria-hidden="true"></div>
    <p id="commissionSaveOverlayText">Saving…</p>
  </div>
</div>

<div id="momoPhonePreviewOverlay" aria-hidden="true">
  <div class="momo-phone-preview-dialog" role="dialog" aria-labelledby="momoPhonePreviewTitle">
    <div class="momo-phone-preview-head">
      <div>
        <h3 id="momoPhonePreviewTitle"><i class="fas fa-mobile-alt"></i> MoMo payout — phone check</h3>
        <p id="momoPhonePreviewSub">Confirm the number on file before sending.</p>
      </div>
      <button type="button" class="momo-phone-preview-close" id="momoPhonePreviewCloseX" aria-label="Close">&times;</button>
    </div>
    <div class="momo-phone-preview-body" id="momoPhonePreviewBody">
      <p style="color:#64748b;">Loading…</p>
    </div>
    <div class="momo-phone-preview-actions">
      <button type="button" class="clear-btn" id="momoPhonePreviewCancel" style="border:none;">Cancel</button>
      <button type="button" class="search-btn" id="momoPhonePreviewConfirm" style="display:none;border:none;"><i class="fas fa-paper-plane"></i> Send MoMo payment</button>
    </div>
  </div>
</div>

<div id="detailsModal" aria-hidden="true">
  <div class="modal-inner">
    <div class="modal-head">
      <h3><i class="fas fa-file-invoice-dollar"></i> <span id="mTitle">Commission</span></h3>
      <button type="button" class="modal-close" id="modalCloseX" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body" id="modalBody"></div>
  </div>
</div>

<div id="processToast" class="process-toast" role="status"><i class="fas fa-check-circle"></i><span id="processToastMsg">Saved</span></div>

<script>
window.COMMISSION_CSRF = <?= json_encode($commissionCsrf, JSON_UNESCAPED_UNICODE) ?>;
window.COMMISSION_STATUS_LABELS = <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>;
window.COMMISSION_STEP_ORDER = <?= json_encode($commissionStepOrder, JSON_UNESCAPED_UNICODE) ?>;
window.COMMISSION_STEP_LABELS_STEPS = <?= json_encode($commissionStepLabels, JSON_UNESCAPED_UNICODE) ?>;

function showToast(msg, longMs) {
  const t = document.getElementById('processToast');
  const m = document.getElementById('processToastMsg');
  m.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), longMs || 3200);
}

function fmtMoney(n, cur) {
  const x = Number(n);
  if (!isFinite(x)) return '—';
  return new Intl.NumberFormat(undefined, { maximumFractionDigits: cur === 'USD' ? 2 : 0 }).format(x) + (cur ? ' ' + cur : '');
}

function updateCommissionCardProcessUI(card, statusKey) {
  if (!card) return;
  const order = window.COMMISSION_STEP_ORDER || [];
  const labels = window.COMMISSION_STATUS_LABELS || {};
  const stepLabels = window.COMMISSION_STEP_LABELS_STEPS || {};
  const isRej = statusKey === 'rejected';
  const banner = card.querySelector('.commission-rejected-banner');
  const tracker = card.querySelector('.process-tracker');
  if (banner) banner.style.display = isRej ? 'block' : 'none';
  if (tracker) {
    tracker.style.display = isRej ? 'none' : 'flex';
    const steps = tracker.querySelectorAll('.process-step');
    const curIdx = order.indexOf(statusKey);
    const idx = isRej ? -1 : (curIdx === -1 ? 0 : curIdx);
    steps.forEach((step, i) => {
      step.classList.remove('done', 'current', 'pending');
      if (isRej) {
        step.classList.add('pending');
        return;
      }
      const st = i < idx ? 'done' : (i === idx ? 'current' : 'pending');
      step.classList.add(st);
    });
  }
  const pill = card.querySelector('.process-status-row > .status-pill');
  if (pill) {
    pill.textContent = labels[statusKey] || statusKey;
    pill.classList.toggle('rejected', isRej);
  }
}

function commissionNotifyToastFromResponse(j, ne, nw) {
  const parts = ['Status saved'];
  let anyFail = false;
  const n = j.notify;
  if ((ne || nw) && !n) {
    anyFail = true;
    parts.push('Notifications failed (server error).');
  }
  if (n && n.email && n.email.requested) {
    if (n.email.sent) parts.push('Email sent');
    else { anyFail = true; parts.push('Email failed' + (n.email.error ? ': ' + n.email.error : '')); }
  }
  if (n && n.whatsapp && n.whatsapp.requested) {
    if (n.whatsapp.sent) {
      parts.push(n.whatsapp.method === 'template'
        ? 'WhatsApp sent (pcvc_commission_update)'
        : (n.whatsapp.method === 'text' ? 'WhatsApp sent (session)' : 'WhatsApp sent'));
    } else {
      anyFail = true;
      let waLine = 'WhatsApp failed' + (n.whatsapp.error ? ': ' + n.whatsapp.error : '');
      if (n.whatsapp.template) {
        waLine += ' [' + n.whatsapp.template + ', ' + (n.whatsapp.template_params || 4) + ' vars]';
      }
      parts.push(waLine);
    }
  }
  if (!ne && !nw) {
    parts.length = 1;
    parts[0] = 'Status saved (no notification)';
  }
  showToast(parts.join(' · '), anyFail ? 5200 : 3200);
}

let commissionNotifyPending = null;
let commissionStatusSaving = false;

function setCommissionStatusSaving(active, text) {
  commissionStatusSaving = active;
  const ov = document.getElementById('commissionSaveOverlay');
  const txt = document.getElementById('commissionSaveOverlayText');
  if (ov) {
    ov.classList.toggle('show-flex', active);
    ov.style.display = active ? 'flex' : 'none';
    ov.setAttribute('aria-hidden', active ? 'false' : 'true');
  }
  if (txt && text) txt.textContent = text;
  ['commissionNotifyConfirm', 'commissionNotifyCancel', 'commissionNotifyCloseX'].forEach(function (id) {
    const el = document.getElementById(id);
    if (el) el.disabled = active;
  });
  const icon = document.getElementById('commissionNotifyConfirmIcon');
  const label = document.getElementById('commissionNotifyConfirmLabel');
  if (icon) icon.className = active ? 'fas fa-spinner fa-spin' : 'fas fa-save';
  if (label) label.textContent = active ? 'Saving…' : 'Save';
}

function setManageSaveButtonSaving(btn, active) {
  if (!btn) return;
  btn.disabled = active;
  btn.innerHTML = active
    ? '<i class="fas fa-spinner fa-spin"></i> Saving…'
    : '<i class="fas fa-save"></i> Save status';
}

function syncCommissionNotifyMessageWrap() {
  const wrap = document.getElementById('commissionNotifyMessageWrap');
  if (!wrap) return;
  const p = commissionNotifyPending;
  const show = p && p.newKey === 'rejected';
  wrap.style.display = show ? 'block' : 'none';
  if (!show) {
    const ta = document.getElementById('commissionNotifyMessage');
    if (ta) ta.value = '';
  }
}

function openCommissionNotifyModal() {
  const el = document.getElementById('commissionStatusNotifyModal');
  if (!el) return;
  el.classList.add('show-flex');
  el.style.display = 'flex';
  el.setAttribute('aria-hidden', 'false');
  syncCommissionNotifyMessageWrap();
}
function closeCommissionNotifyModal() {
  if (commissionStatusSaving) return;
  const el = document.getElementById('commissionStatusNotifyModal');
  if (!el) return;
  el.classList.remove('show-flex');
  el.style.display = 'none';
  el.setAttribute('aria-hidden', 'true');
  commissionNotifyPending = null;
  const ta = document.getElementById('commissionNotifyMessage');
  if (ta) ta.value = '';
  document.querySelectorAll('#commissionInlineNotifyGrid .commission-notify-channel').forEach((b, i) => b.classList.toggle('active', i === 0));
  const wrap = document.getElementById('commissionNotifyMessageWrap');
  if (wrap) wrap.style.display = 'none';
}

async function commissionSubmitStatusChange(sel, id, statusKey, prevKey, ne, nw, msg) {
  sel.disabled = true;
  try {
    const r = await fetch('api/commission-admin-update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        csrf: window.COMMISSION_CSRF,
        id,
        request_status: statusKey,
        notify_email: !!ne,
        notify_whatsapp: !!nw,
        notify_message: msg || ''
      })
    });
    const j = await r.json();
    if (!j.ok) {
      alert(j.error || 'Update failed');
      sel.value = prevKey;
      return j;
    }
    sel.value = statusKey;
    sel.dataset.prev = statusKey;
    const card = sel.closest('.applicant-card');
    updateCommissionCardProcessUI(card, statusKey);
    if (statusKey === 'rejected' && msg) {
      const banner = card ? card.querySelector('.commission-rejected-banner') : null;
      if (banner) {
        let reasonEl = banner.querySelector('.commission-rejection-reason');
        if (!reasonEl && msg.trim() !== '') {
          reasonEl = document.createElement('p');
          reasonEl.className = 'commission-rejection-reason';
          reasonEl.style.cssText = 'font-size:0.82rem;color:#64748b;margin:0;line-height:1.45;';
          banner.appendChild(reasonEl);
        }
        if (reasonEl) {
          if (msg.trim() !== '') {
            reasonEl.innerHTML = '<strong>Reason:</strong> ' + escapeHtml(msg.trim());
            reasonEl.style.display = '';
          } else {
            reasonEl.style.display = 'none';
          }
        }
      }
    }
    commissionNotifyToastFromResponse(j, ne, nw);
    const btn = card.querySelector('.open-commission-modal');
    if (btn) {
      try {
        const d = JSON.parse(btn.getAttribute('data-commission'));
        d.request_status = statusKey;
        if (statusKey === 'rejected') d.rejection_reason = msg || '';
        btn.setAttribute('data-commission', JSON.stringify(d));
      } catch (e2) {}
    }
    return j;
  } catch (e) {
    sel.value = prevKey;
    throw e;
  } finally {
    sel.disabled = false;
  }
}

(function wireInlineCommissionNotifyGrid() {
  const grid = document.getElementById('commissionInlineNotifyGrid');
  if (!grid) return;
  grid.querySelectorAll('.commission-notify-channel').forEach(btn => {
    btn.addEventListener('click', function() {
      grid.querySelectorAll('.commission-notify-channel').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      syncCommissionNotifyMessageWrap();
    });
  });
})();

document.getElementById('commissionNotifyCancel').addEventListener('click', closeCommissionNotifyModal);
document.getElementById('commissionNotifyCloseX').addEventListener('click', closeCommissionNotifyModal);
document.getElementById('commissionStatusNotifyModal').addEventListener('click', function(ev) {
  if (ev.target.id === 'commissionStatusNotifyModal') closeCommissionNotifyModal();
});

document.getElementById('commissionNotifyConfirm').addEventListener('click', async function() {
  if (commissionStatusSaving) return;
  const p = commissionNotifyPending;
  if (!p || !p.sel) return;
  const active = document.querySelector('#commissionInlineNotifyGrid .commission-notify-channel.active');
  const ne = active ? parseInt(active.getAttribute('data-ne'), 10) || 0 : 0;
  const nw = active ? parseInt(active.getAttribute('data-nw'), 10) || 0 : 0;
  const msg = (document.getElementById('commissionNotifyMessage') || {}).value || '';
  const msgTrim = String(msg).trim();
  if (p.newKey === 'rejected' && (ne || nw) && msgTrim === '') {
    alert('Please enter a rejection reason before sending Email or WhatsApp.');
    return;
  }
  const payloadMsg = p.newKey === 'rejected' ? msgTrim : '';
  const savingText = (ne || nw) ? 'Saving & sending notifications…' : 'Saving status…';
  setCommissionStatusSaving(true, savingText);
  try {
    const j = await commissionSubmitStatusChange(p.sel, p.id, p.newKey, p.prevKey, ne, nw, payloadMsg);
    if (j && j.ok) closeCommissionNotifyModal();
  } catch (e) {
    alert('Network error — could not save. Try again.');
  } finally {
    setCommissionStatusSaving(false);
  }
});

document.addEventListener('focusin', function(e) {
  const t = e.target;
  if (t.classList && t.classList.contains('commission-status-select')) {
    t.dataset.prev = t.value;
  }
});

document.addEventListener('change', function(e) {
  const sel = e.target;
  if (!sel.classList || !sel.classList.contains('commission-status-select')) return;
  const id = parseInt(sel.dataset.commissionId, 10);
  const newKey = sel.value;
  const prevKey = sel.dataset.prev != null ? sel.dataset.prev : newKey;
  if (!id || newKey === prevKey) return;
  sel.value = prevKey;
  const labels = window.COMMISSION_STATUS_LABELS || {};
  document.getElementById('commissionNotifyStatusLabel').textContent = labels[newKey] || newKey;
  document.querySelectorAll('#commissionInlineNotifyGrid .commission-notify-channel').forEach((b, i) => b.classList.toggle('active', i === 0));
  const ta = document.getElementById('commissionNotifyMessage');
  if (ta) {
    const card = sel.closest('.applicant-card');
    const btn = card ? card.querySelector('.open-commission-modal') : null;
    let existingReason = '';
    if (btn) {
      try {
        const d = JSON.parse(btn.getAttribute('data-commission'));
        existingReason = (d.rejection_reason || '').trim();
      } catch (e2) {}
    }
    ta.value = newKey === 'rejected' ? existingReason : '';
  }
  commissionNotifyPending = { sel, id, newKey, prevKey };
  openCommissionNotifyModal();
  syncCommissionNotifyMessageWrap();
});

document.querySelectorAll('.commission-status-select').forEach(sel => {
  sel.dataset.prev = sel.value;
});

function openCommissionModal(data) {
  const modal = document.getElementById('detailsModal');
  const body = document.getElementById('modalBody');
  document.getElementById('mTitle').textContent = data.name + ' · #' + data.id;

  body.innerHTML = `
    <div class="modal-section">
      <h4>Applicant</h4>
      <div class="modal-grid">
        <div class="modal-row"><strong>Email</strong><span>${escapeHtml(data.email)}</span></div>
        <div class="modal-row"><strong>Phone</strong><span>${escapeHtml(data.phone)}</span></div>
        <div class="modal-row"><strong>City</strong><span>${escapeHtml(data.city)}</span></div>
        <div class="modal-row"><strong>Country</strong><span>${escapeHtml(data.country_applied)}</span></div>
        <div class="modal-row"><strong>Loan</strong><span>${escapeHtml(data.loan_status)}</span></div>
        <div class="modal-row"><strong>Visa</strong><span>${escapeHtml(data.visa_status)}</span></div>
        <div class="modal-row"><strong>Contract</strong><span>${escapeHtml(data.contract_signed)}</span></div>
        <div class="modal-row"><strong>Recruited</strong><span>${escapeHtml(data.recruited_name)} ${escapeHtml(data.recruited_phone)}</span></div>
        <div class="modal-row"><strong>Submitted</strong><span>${escapeHtml(data.submission_date)}</span></div>
        <div class="modal-row"><strong>Comments</strong><span>${escapeHtml(data.comments)}</span></div>
      </div>
    </div>
    <div class="modal-section">
      <h4>Finance</h4>
      <div class="modal-grid">
        <div class="modal-row"><strong>Amount</strong><span>${fmtMoney(data.amount_usd, data.commission_currency || 'USD')}</span></div>
        <div class="modal-row"><strong>RWF due</strong><span>${fmtMoney(data.amount_rwf, 'RWF')}</span></div>
        <div class="modal-row"><strong>FX rate</strong><span>${escapeHtml(String(data.fx_rate_used ?? ''))}</span></div>
        <div class="modal-row"><strong>Paid</strong><span>${fmtMoney(data.paid_rwf_total, 'RWF')}</span></div>
        <div class="modal-row"><strong>Remaining</strong><span>${fmtMoney(data.remaining_rwf, 'RWF')}</span></div>
      </div>
    </div>
    <div class="admin-box">
      <h4><i class="fas fa-user-shield"></i> Review & notify</h4>
      <div class="form-fld">
        <label>Status</label>
        <select id="mStatus">${Object.keys(window.COMMISSION_STATUS_LABELS).map(k =>
          '<option value="' + escapeAttr(k) + '"' + (k === data.request_status ? ' selected' : '') + '>' + escapeHtml(window.COMMISSION_STATUS_LABELS[k]) + '</option>'
        ).join('')}</select>
      </div>
      <div class="form-fld">
        <label>Internal note</label>
        <textarea id="mNote">${escapeHtml(data.internal_note || '')}</textarea>
      </div>
      <p class="commission-notify-hint" style="margin-bottom:8px;">Notification channel (email / WhatsApp use the same Meta session-or-template logic as job applications)</p>
      <div class="commission-notify-grid" id="mNotifyGrid">
        <button type="button" class="commission-notify-channel active" data-ne="0" data-nw="0">Record only<span>No notification</span></button>
        <button type="button" class="commission-notify-channel" data-ne="1" data-nw="0">Email<span>Send email</span></button>
        <button type="button" class="commission-notify-channel" data-ne="0" data-nw="1">WhatsApp<span>Send WhatsApp</span></button>
        <button type="button" class="commission-notify-channel" data-ne="1" data-nw="1">Both<span>Email + WhatsApp</span></button>
      </div>
      <div class="form-fld" id="mNotifyRejectionWrap" style="display:none;margin-top:12px;">
        <label style="display:block;font-size:0.78rem;font-weight:700;color:#64748b;margin-bottom:4px;">Rejection reason</label>
        <textarea id="mNotifyMsg" rows="3" maxlength="2000" placeholder="Explain why this commission request was rejected…">${escapeHtml(data.rejection_reason || '')}</textarea>
        <p style="margin:6px 0 0;font-size:0.75rem;color:#64748b;">Required when status is <strong>Rejected</strong> and you send Email or WhatsApp. WhatsApp template: <strong>pcvc_commission_update</strong>.</p>
      </div>
      <button type="button" class="search-btn" id="mSaveStatus" style="border:none;margin-top:14px;"><i class="fas fa-save"></i> Save status</button>
      <p id="mSaveMsg" style="margin-top:10px;font-size:0.85rem;color:#64748b;"></p>
    </div>
    <div class="admin-box">
      <h4><i class="fas fa-mobile-alt"></i> MoMo payout</h4>
      <p style="font-size:0.82rem;color:#64748b;margin-bottom:8px;">Remaining: <strong>${fmtMoney(data.remaining_rwf, 'RWF')}</strong> · uses phone on file</p>
      <div class="form-fld">
        <label>Amount (RWF)</label>
        <input type="number" id="mMomoAmt" min="1" step="1" placeholder="e.g. ${Math.floor(Number(data.remaining_rwf) || 0)}">
      </div>
      <button type="button" class="btn-momo" id="mMomoPay"><i class="fas fa-paper-plane"></i> Pay via MoMo</button>
      <p id="mMomoMsg" style="margin-top:10px;font-size:0.85rem;"></p>
    </div>
  `;

  function syncManageRejectionNote() {
    const wrap = document.getElementById('mNotifyRejectionWrap');
    const st = document.getElementById('mStatus').value;
    const show = st === 'rejected';
    if (wrap) wrap.style.display = show ? 'block' : 'none';
    if (!show) {
      const ta = document.getElementById('mNotifyMsg');
      if (ta) ta.value = '';
    }
  }

  document.querySelectorAll('#mNotifyGrid .commission-notify-channel').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('#mNotifyGrid .commission-notify-channel').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      syncManageRejectionNote();
    });
  });

  document.getElementById('mStatus').addEventListener('change', syncManageRejectionNote);
  syncManageRejectionNote();

  document.getElementById('mSaveStatus').onclick = async () => {
    if (commissionStatusSaving) return;
    const saveBtn = document.getElementById('mSaveStatus');
    const msg = document.getElementById('mSaveMsg');
    msg.textContent = '';
    const st = document.getElementById('mStatus').value;
    const active = document.querySelector('#mNotifyGrid .commission-notify-channel.active');
    const ne = active ? parseInt(active.getAttribute('data-ne'), 10) || 0 : 0;
    const nw = active ? parseInt(active.getAttribute('data-nw'), 10) || 0 : 0;
    const nmsgRaw = (document.getElementById('mNotifyMsg').value || '').trim();
    const nmsg = st === 'rejected' ? nmsgRaw : '';
    if (st === 'rejected' && (ne || nw) && nmsgRaw === '') {
      msg.textContent = 'Enter a rejection reason before sending Email or WhatsApp.';
      msg.style.color = '#b91c1c';
      return;
    }
    const savingText = (ne || nw) ? 'Saving & sending notifications…' : 'Saving status…';
    setCommissionStatusSaving(true, savingText);
    setManageSaveButtonSaving(saveBtn, true);
    msg.textContent = 'Saving…';
    msg.style.color = '#64748b';
    try {
      const r = await fetch('api/commission-admin-update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          csrf: window.COMMISSION_CSRF,
          id: data.id,
          request_status: st,
          internal_note: document.getElementById('mNote').value,
          notify_email: !!ne,
          notify_whatsapp: !!nw,
          notify_message: nmsg
        })
      });
      const j = await r.json();
      if (j.ok) {
        data.request_status = j.request_status;
        if (st === 'rejected') data.rejection_reason = nmsgRaw;
        msg.textContent = 'Saved.';
        msg.style.color = '#065f46';
        commissionNotifyToastFromResponse(j, ne, nw);
        const card = document.querySelector('.applicant-card[data-commission-id="' + data.id + '"]');
        const sel = card ? card.querySelector('.commission-status-select') : null;
        if (sel) { sel.value = j.request_status; sel.dataset.prev = j.request_status; }
        updateCommissionCardProcessUI(card, j.request_status);
      } else {
        msg.textContent = j.error || 'Failed';
        msg.style.color = '#b91c1c';
      }
    } catch (e) {
      msg.textContent = 'Network error';
      msg.style.color = '#b91c1c';
    } finally {
      setCommissionStatusSaving(false);
      setManageSaveButtonSaving(saveBtn, false);
    }
  };

  document.getElementById('mMomoPay').onclick = () => {
    const out = document.getElementById('mMomoMsg');
    const amt = parseInt(document.getElementById('mMomoAmt').value, 10);
    if (!isFinite(amt) || amt < 1) {
      out.textContent = 'Enter a valid RWF amount.';
      out.style.color = '#b91c1c';
      return;
    }
    out.textContent = '';
    window.pcvcOpenMomoPhonePreviewForPay({ id: data.id, amountRwf: amt, dataRef: data, msgEl: out });
  };

  modal.classList.add('show-flex');
  modal.setAttribute('aria-hidden', 'false');
}

function escapeHtml(s) {
  if (s == null) return '';
  const d = document.createElement('div');
  d.textContent = String(s);
  return d.innerHTML;
}
function escapeAttr(s) {
  return String(s).replace(/"/g, '&quot;');
}

let pcvcMomoPayPending = null;

function pcvcCloseMomoPhonePreview() {
  const ov = document.getElementById('momoPhonePreviewOverlay');
  if (ov) {
    ov.classList.remove('show-flex');
    ov.setAttribute('aria-hidden', 'true');
  }
  pcvcMomoPayPending = null;
}

window.pcvcOpenMomoPhonePreviewForPay = function(ctx) {
  const ov = document.getElementById('momoPhonePreviewOverlay');
  const body = document.getElementById('momoPhonePreviewBody');
  const sub = document.getElementById('momoPhonePreviewSub');
  const btn = document.getElementById('momoPhonePreviewConfirm');
  if (!ov || !body || !btn) return;
  pcvcMomoPayPending = ctx;
  btn.style.display = 'none';
  body.innerHTML = '<p style="color:#64748b;">Loading…</p>';
  if (sub) {
    sub.textContent = 'RWF ' + (ctx.amountRwf || 0) + ' will be sent to the MoMo wallet below (if valid).';
  }
  ov.classList.add('show-flex');
  ov.setAttribute('aria-hidden', 'false');

  fetch('api/commission-momo-phone-preview.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ csrf: window.COMMISSION_CSRF, id: ctx.id })
  })
    .then(r => r.json())
    .then(j => {
      if (!j.ok) {
        body.innerHTML = '<p style="color:#b91c1c;">' + escapeHtml(j.error || 'Failed') + '</p>';
        return;
      }
      let html = '';
      html += '<div class="momo-phone-preview-row"><strong>On file</strong><span>' + escapeHtml(j.phone_raw || '—') + '</span></div>';
      if (j.momo_msisdn) {
        html += '<div class="momo-phone-preview-row"><strong>MoMo (normalized)</strong><span>' + escapeHtml(j.momo_display && j.momo_display !== '—' ? j.momo_display : j.momo_msisdn) + '</span></div>';
        btn.style.display = 'inline-block';
      } else {
        html += '<div class="momo-phone-preview-row"><strong>MoMo (normalized)</strong><span style="color:#b91c1c;">Not a valid Rwanda MoMo number — update the record or pay manually.</span></div>';
      }
      if (j.whatsapp_wa_me) {
        html += '<div class="momo-phone-preview-row"><strong>WhatsApp</strong><a href="' + escapeAttr(j.whatsapp_wa_me) + '" target="_blank" rel="noopener">' + escapeHtml(j.whatsapp_e164 || '') + ' — open chat</a></div>';
      } else if (j.whatsapp_e164) {
        html += '<div class="momo-phone-preview-row"><strong>WhatsApp (E.164)</strong><span>' + escapeHtml(j.whatsapp_e164) + '</span></div>';
      } else {
        html += '<div class="momo-phone-preview-row"><strong>WhatsApp</strong><span style="color:#64748b;">Could not build wa.me link — check number or WHATSAPP_DEFAULT_COUNTRY_CODE in .env.</span></div>';
      }
      body.innerHTML = html;
    })
    .catch(() => {
      body.innerHTML = '<p style="color:#b91c1c;">Network error.</p>';
    });
};

(function wireMomoPhonePreviewOverlay() {
  const ov = document.getElementById('momoPhonePreviewOverlay');
  const close = () => pcvcCloseMomoPhonePreview();
  document.getElementById('momoPhonePreviewCloseX')?.addEventListener('click', close);
  document.getElementById('momoPhonePreviewCancel')?.addEventListener('click', close);
  ov?.addEventListener('click', (e) => { if (e.target === ov) close(); });
  document.getElementById('momoPhonePreviewConfirm')?.addEventListener('click', async () => {
    const p = pcvcMomoPayPending;
    if (!p || !p.dataRef) return;
    const out = p.msgEl || document.getElementById('mMomoMsg');
    const amt = parseInt(String(p.amountRwf), 10);
    if (!isFinite(amt) || amt < 1) {
      if (out) { out.textContent = 'Invalid amount.'; out.style.color = '#b91c1c'; }
      return;
    }
    if (out) { out.textContent = 'Processing…'; out.style.color = '#64748b'; }
    try {
      const r = await fetch('api/commission-momo-pay.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: window.COMMISSION_CSRF, id: p.id, amount_rwf: amt })
      });
      const j = await r.json();
      if (j.ok) {
        p.dataRef.paid_rwf_total = j.paid_rwf_total;
        p.dataRef.request_status = j.request_status;
        p.dataRef.remaining_rwf = Math.max(0, Number(p.dataRef.amount_rwf) - Number(p.dataRef.paid_rwf_total));
        if (out) {
          out.innerHTML = 'Sent. Tx: <code>' + escapeHtml(j.transactionId || '') + '</code>';
          out.style.color = '#065f46';
        }
        showToast('MoMo payment sent');
        pcvcCloseMomoPhonePreview();
        openCommissionModal(p.dataRef);
      } else {
        if (out) { out.textContent = j.error || 'Failed'; out.style.color = '#b91c1c'; }
      }
    } catch (e) {
      if (out) { out.textContent = 'Network error'; out.style.color = '#b91c1c'; }
    }
  });
})();

(function commissionLiveFilter() {
  const grid = document.getElementById('commissionApplicantsGrid');
  const input = document.getElementById('commissionLiveSearch');
  const sel = document.getElementById('commissionLiveStatus');
  const clearBtn = document.getElementById('commissionLiveClear');
  const pill = document.getElementById('commissionShownPill');
  const noMatch = document.getElementById('commissionNoMatches');
  if (!grid || !input || !sel) return;
  const cards = () => Array.from(grid.querySelectorAll('.applicant-card'));
  const total = cards().length;
  let t = null;
  function updateClearVisibility() {
    if (!clearBtn) return;
    const show = (input.value || '').trim() !== '' || (sel.value || '') !== '';
    clearBtn.style.display = show ? 'inline-flex' : 'none';
  }
  function apply() {
    const q = (input.value || '').trim().toLowerCase();
    const st = sel.value || '';
    let shown = 0;
    cards().forEach(c => {
      const blob = (c.getAttribute('data-search') || '');
      const cs = c.getAttribute('data-status') || '';
      const okQ = !q || blob.indexOf(q) !== -1;
      const okS = !st || cs === st;
      const vis = okQ && okS;
      c.style.display = vis ? '' : 'none';
      if (vis) shown++;
    });
    if (pill) {
      const extra = shown !== total ? ' of ' + total : '';
      pill.innerHTML = '<i class="fas fa-list"></i> ' + shown + extra + ' shown';
    }
    if (noMatch) {
      noMatch.style.display = total > 0 && shown === 0 ? 'block' : 'none';
    }
    updateClearVisibility();
  }
  input.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(apply, 120);
  });
  sel.addEventListener('change', apply);
  clearBtn?.addEventListener('click', () => {
    input.value = '';
    sel.value = '';
    apply();
    input.focus();
  });
  apply();
})();

document.querySelectorAll('.open-commission-modal').forEach(btn => {
  btn.addEventListener('click', () => {
    try {
      const data = JSON.parse(btn.getAttribute('data-commission'));
      openCommissionModal(data);
    } catch (e) { alert('Invalid data'); }
  });
});

function hideModal() {
  const modal = document.getElementById('detailsModal');
  modal.classList.remove('show-flex');
  modal.setAttribute('aria-hidden', 'true');
}
document.getElementById('modalCloseX').addEventListener('click', hideModal);
document.getElementById('detailsModal').addEventListener('click', (e) => {
  if (e.target.id === 'detailsModal') hideModal();
});

</script>
</body>
</html>
