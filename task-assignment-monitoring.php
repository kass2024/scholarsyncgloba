<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/includes/company_branding.php';

$adminPk = 0;
if (!empty($_SESSION['id'])) {
    $adminPk = (int) $_SESSION['id'];
} elseif (!empty($_SESSION['admin_id'])) {
    $adminPk = (int) $_SESSION['admin_id'];
}
if ($adminPk <= 0 || empty($_SESSION['role'])) {
    header('Location: admin-login.php');
    exit;
}

$dbRole = '';
$stRole = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
if ($stRole) {
    $stRole->bind_param('i', $adminPk);
    $stRole->execute();
    $rr = $stRole->get_result()->fetch_assoc();
    $stRole->close();
    if ($rr) {
        $dbRole = trim((string) ($rr['role'] ?? ''));
    }
}

$sessionRole = trim((string) ($_SESSION['role'] ?? ''));
$isPrivileged = pcvc_is_superadmin_role($dbRole)
    || pcvc_is_superadmin_role($sessionRole)
    || strcasecmp($dbRole, 'agent') === 0
    || strcasecmp($dbRole, 'standard') === 0;

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

require_once __DIR__ . '/helpers/task_assignment_data.php';
require_once __DIR__ . '/helpers/env_load.php';
$restrictStaffId = null;
if (!$isPrivileged && strcasecmp($dbRole, 'staff') === 0) {
    $restrictStaffId = $adminPk;
}

$taskSummary = null;
try {
    $taskSummary = pcvc_task_monitor_build_summary_payload($conn, $restrictStaffId, $isPrivileged);
} catch (Throwable $e) {
    error_log('[task-assignment-monitoring] ' . $e->getMessage());
    $taskSummary = [
        'assignments_enabled' => false,
        'message' => 'Could not load dashboard data.',
    ];
}

$appRoot = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$apiUrl = ($appRoot === '' ? '' : $appRoot) . '/api/task_assignment_monitor.php';

/** @param array<string, mixed> $row */
function pcvc_tm_h(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pcvc_tm_initials(string $name): string
{
    $p = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($p === []) {
        return '?';
    }
    if (count($p) === 1) {
        return strtoupper(mb_substr($p[0], 0, 2, 'UTF-8'));
    }

    return strtoupper(mb_substr($p[0], 0, 1, 'UTF-8') . mb_substr($p[count($p) - 1], 0, 1, 'UTF-8'));
}

function pcvc_tm_badge_class(string $key): string
{
    if ($key === 'deny') {
        return 'tm-badge tm-badge--deny';
    }
    if ($key === 'enrolled' || $key === 'visa_approved') {
        return 'tm-badge tm-badge--win';
    }
    if ($key === 'submitted' || $key === 'incomplete_app' || $key === 'app_start') {
        return 'tm-badge tm-badge--early';
    }

    return 'tm-badge tm-badge--mid';
}

/**
 * @param array<string, mixed> $s bucket (staff row, unassigned, or grand)
 * @return array{won:int,mid:int,early:int,deny:int,other:int,t:int}
 */
function pcvc_tm_segment_counts(array $s): array
{
    $b = isset($s['by_status']) && is_array($s['by_status']) ? $s['by_status'] : [];
    $won = (int) (($b['visa_approved'] ?? 0) + ($b['enrolled'] ?? 0));
    $mid = (int) (($b['sent_to_platform'] ?? 0) + ($b['app_paid'] ?? 0) + ($b['admit'] ?? 0) + ($b['i20_sent'] ?? 0) + ($b['sevis_paid'] ?? 0) + ($b['visa_scheduled'] ?? 0));
    $early = (int) (($b['incomplete_app'] ?? 0) + ($b['app_start'] ?? 0) + ($b['submitted'] ?? 0) + ($b['addn_doc'] ?? 0));
    $deny = (int) ($b['deny'] ?? 0);
    $other = (int) ($s['no_status'] ?? 0);
    $t = (int) ($s['total'] ?? 0);

    return ['won' => $won, 'mid' => $mid, 'early' => $early, 'deny' => $deny, 'other' => $other, 't' => $t];
}

function pcvc_tm_pipeline_bar_html(array $s): string
{
    $seg = pcvc_tm_segment_counts($s);
    $t = $seg['t'];
    if ($t <= 0) {
        return '<div class="tm-pipeline-bar" title="No applications"></div>';
    }
    $pct = static function (int $n) use ($t): int {
        return max(0, (int) round(100 * $n / $t));
    };
    $parts = [];
    if ($seg['won'] > 0) {
        $parts[] = '<div class="tm-seg tm-seg--won" style="width:' . $pct($seg['won']) . '%" title="Visa OK / Enrolled"></div>';
    }
    if ($seg['mid'] > 0) {
        $parts[] = '<div class="tm-seg tm-seg--mid" style="width:' . $pct($seg['mid']) . '%" title="Mid pipeline"></div>';
    }
    if ($seg['early'] > 0) {
        $parts[] = '<div class="tm-seg tm-seg--early" style="width:' . $pct($seg['early']) . '%" title="Early stage"></div>';
    }
    if ($seg['deny'] > 0) {
        $parts[] = '<div class="tm-seg tm-seg--deny" style="width:' . $pct($seg['deny']) . '%" title="Rejected"></div>';
    }
    if ($seg['other'] > 0) {
        $parts[] = '<div class="tm-seg tm-seg--other" style="width:' . $pct($seg['other']) . '%" title="Other"></div>';
    }

    return '<div class="tm-pipeline-bar">' . implode('', $parts) . '</div>';
}

/**
 * @param array<string, mixed> $s
 * @param list<string> $statusOrder
 * @param array<string, string> $statusLabels
 */
function pcvc_tm_top_chips_html(array $s, array $statusOrder, array $statusLabels, int $limit = 5): string
{
    $b = isset($s['by_status']) && is_array($s['by_status']) ? $s['by_status'] : [];
    $pairs = [];
    foreach ($statusOrder as $k) {
        $n = (int) ($b[$k] ?? 0);
        if ($n > 0) {
            $pairs[] = ['k' => $k, 'n' => $n];
        }
    }
    usort($pairs, static fn ($a, $b) => $b['n'] <=> $a['n']);
    $pairs = array_slice($pairs, 0, $limit);
    if ($pairs === []) {
        return '<span class="tm-muted">No status flags set</span>';
    }
    $out = [];
    foreach ($pairs as $x) {
        $out[] = '<span class="' . pcvc_tm_badge_class($x['k']) . '">' . pcvc_tm_h($statusLabels[$x['k']] ?? $x['k']) . ' · ' . (int) $x['n'] . '</span>';
    }

    return implode(' ', $out);
}

/**
 * @param array<string, mixed>|null $m
 */
function pcvc_tm_render_metric_tiles(?array $m): string
{
    $m = $m ?? [];
    $n = static function ($v): string {
        if ($v === null || $v === '') {
            return '0';
        }

        return (string) $v;
    };
    $tiles = [
        ['Applications', $n($m['total_applications'] ?? 0), 'in database', 'slate'],
        ['Assigned', $n($m['assigned_applications'] ?? 0), 'to a person', 'teal'],
        ['Unassigned', $n($m['unassigned_applications'] ?? 0), 'need owner', 'amber'],
        ['Team', $n($m['staff_directory_count'] ?? 0), 'on roster', 'indigo'],
        ['Wins', $n($m['positive_outcomes'] ?? 0), 'visa OK / enrolled', 'sky'],
        ['Rejected', $n($m['rejections'] ?? 0), 'denied', 'rose'],
    ];
    $html = '';
    foreach ($tiles as $t) {
        $html .= '<div class="metric-tile metric-tile--' . pcvc_tm_h($t[3]) . '">'
            . '<p class="metric-label">' . pcvc_tm_h($t[0]) . '</p>'
            . '<p class="metric-value">' . pcvc_tm_h($t[1]) . '</p>'
            . '<p class="metric-sub">' . pcvc_tm_h($t[2]) . '</p>'
            . '</div>';
    }

    return $html;
}

/**
 * @param array<string, mixed> $grand
 * @param list<string> $statusOrder
 * @param array<string, string> $statusLabels
 */
function pcvc_tm_render_pipeline_strip(array $grand, array $statusOrder, array $statusLabels): string
{
    $by = isset($grand['by_status']) && is_array($grand['by_status']) ? $grand['by_status'] : [];
    $chips = [];
    foreach ($statusOrder as $k) {
        $n = (int) ($by[$k] ?? 0);
        if ($n > 0) {
            $chips[] = ['k' => $k, 'n' => $n];
        }
    }
    usort($chips, static fn ($a, $b) => $b['n'] <=> $a['n']);
    $total = (int) ($grand['total'] ?? 0);
    $unset = (int) ($grand['no_status'] ?? 0);
    $head = '<div class="tm-pipeline-head">'
        . '<h3>Pipeline snapshot</h3>'
        . '<span class="tm-muted">' . $total . ' total · ' . $unset . ' unset</span></div>';
    if ($chips === []) {
        return $head . '<p class="tm-muted">No status flags recorded yet.</p>';
    }
    $row = '<div class="tm-chip-row">';
    foreach ($chips as $x) {
        $row .= '<span class="' . pcvc_tm_badge_class($x['k']) . '">' . pcvc_tm_h($statusLabels[$x['k']] ?? $x['k'])
            . '<span class="tm-chip-num">' . (int) $x['n'] . '</span></span>';
    }
    $row .= '</div>';

    return $head . $row;
}

/**
 * @param array<string, mixed> $u
 */
function pcvc_tm_render_unassigned_card(array $u, string $defaultLabel): string
{
    return '<article class="tm-unassigned" data-staff-id="0">'
        . '<div class="tm-unassigned-row">'
        . '<div><p class="tm-unassigned-kicker">Needs assignment</p>'
        . '<h3>' . pcvc_tm_h((string) ($u['name'] ?? 'Unassigned')) . '</h3>'
        . '<p class="tm-muted">' . pcvc_tm_h($defaultLabel) . '</p></div>'
        . '<div class="tm-unassigned-count"><p class="tm-big-num">' . (int) ($u['total'] ?? 0) . '</p>'
        . '<p class="tm-apps-label">Applications</p></div></div>'
        . pcvc_tm_pipeline_bar_html($u)
        . '<button type="button" class="tm-btn tm-btn-amber" data-view="0">View applications</button></article>';
}

/**
 * @param array<string, mixed> $s
 */
function pcvc_tm_render_staff_card(array $s, int $idx, bool $canNotify, array $statusOrder, array $statusLabels): string
{
    $sid = (int) ($s['staff_id'] ?? 0);
    $t = (int) ($s['total'] ?? 0);
    $hasA = !empty($s['has_assignments']);
    $dim = !$hasA && $t === 0;
    $hue = ($idx * 47) % 360;
    $hue2 = ($hue + 40) % 360;
    $name = (string) ($s['name'] ?? '');
    $email = trim((string) ($s['email'] ?? ''));
    $phone = trim((string) ($s['phone'] ?? ''));
    $roleLabel = (string) ($s['role_label'] ?? 'Staff');
    $search = mb_strtolower($name . ' ' . $email . ' ' . $roleLabel, 'UTF-8');
    $chips = pcvc_tm_top_chips_html($s, $statusOrder, $statusLabels, 5);
    $waNote = ($canNotify && $sid > 0 && $phone === '')
        ? '<p class="tm-wa-note">Add a phone number in Staff Management to enable WhatsApp.</p>' : '';
    $notifyBtn = ($canNotify && $sid > 0)
        ? '<button type="button" class="tm-btn tm-btn-outline" data-notify="' . $sid . '">Notify</button>' : '';

    return '<article class="tm-staff-card' . ($dim ? ' tm-staff-card--dim' : '') . '" data-staff-id="' . $sid . '" data-search="' . pcvc_tm_h($search) . '">'
        . '<div class="tm-staff-top">'
        . '<div class="tm-avatar" style="background:linear-gradient(135deg,hsl(' . $hue . ',55%,42%),hsl(' . $hue2 . ',60%,35%))">' . pcvc_tm_h(pcvc_tm_initials($name)) . '</div>'
        . '<div class="tm-staff-meta">'
        . '<div class="tm-name-row"><h3>' . pcvc_tm_h($name) . '</h3><span class="tm-role-pill">' . pcvc_tm_h($roleLabel) . '</span></div>'
        . '<p class="tm-email">' . ($email !== '' ? pcvc_tm_h($email) : '<em>No email</em>') . '</p></div>'
        . '<div class="tm-staff-count"><p class="tm-count-num">' . $t . '</p><p class="tm-apps-label">Apps</p></div></div>'
        . pcvc_tm_pipeline_bar_html($s)
        . '<div class="tm-chip-wrap">' . $chips . '</div>'
        . $waNote
        . '<div class="tm-btn-row">'
        . '<button type="button" class="tm-btn tm-btn-dark" data-view="' . $sid . '">View list</button>'
        . $notifyBtn
        . '<button type="button" class="tm-btn tm-btn-teal" data-filter="' . $sid . '">Applicants</button>'
        . '</div></article>';
}

$assignmentsOn = !empty($taskSummary['assignments_enabled']);
$statusLabels = ($assignmentsOn && isset($taskSummary['status_labels']) && is_array($taskSummary['status_labels']))
    ? $taskSummary['status_labels'] : [];
$statusOrder = ($assignmentsOn && isset($taskSummary['status_order']) && is_array($taskSummary['status_order']))
    ? array_values(array_filter($taskSummary['status_order'], 'is_string')) : [];
$summaryMetrics = ($assignmentsOn && isset($taskSummary['summary_metrics']) && is_array($taskSummary['summary_metrics']))
    ? $taskSummary['summary_metrics'] : null;
$grand = ($assignmentsOn && isset($taskSummary['grand']) && is_array($taskSummary['grand'])) ? $taskSummary['grand'] : [];
$unassigned = ($assignmentsOn && isset($taskSummary['unassigned']) && is_array($taskSummary['unassigned'])) ? $taskSummary['unassigned'] : null;
$staffList = ($assignmentsOn && isset($taskSummary['staff']) && is_array($taskSummary['staff'])) ? $taskSummary['staff'] : [];
$disabledMessage = (string) ($taskSummary['message'] ?? 'Assignments are not available.');
$generatedAt = date('M j, Y g:i A');
$waUrgentConfigured = true;

$tmMetaJson = json_encode(
    [
        'api' => $apiUrl,
        'canNotify' => $isPrivileged,
        'labels' => $statusLabels,
        'order' => $statusOrder,
        'waUrgentConfigured' => $waUrgentConfigured,
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
);
if ($tmMetaJson === false) {
    $tmMetaJson = '{"api":"","canNotify":false,"labels":{},"order":[],"waUrgentConfigured":false}';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f766e">
    <title>Task assignment monitoring | <?= pcvc_tm_h(PCVC_COMPANY_DISPLAY_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --safe-bottom: env(safe-area-inset-bottom, 0px);
            --teal: #0d9488;
            --teal-dark: #0f766e;
            --slate-900: #0f172a;
            --slate-600: #475569;
            --slate-500: #64748b;
            --border: rgba(148, 163, 184, 0.45);
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            padding-bottom: var(--safe-bottom);
            font-family: 'DM Sans', system-ui, sans-serif;
            color: var(--slate-900);
            -webkit-font-smoothing: antialiased;
            background-color: #f0fdfa;
            background-image:
                radial-gradient(ellipse 120% 80% at 100% -10%, rgba(13, 148, 136, 0.16), transparent 50%),
                radial-gradient(ellipse 80% 60% at -10% 100%, rgba(59, 130, 246, 0.06), transparent 45%),
                linear-gradient(180deg, #f8fafc 0%, #f0fdfa 45%, #ecfeff 100%);
            min-height: 100vh;
        }
        .tm-header {
            position: sticky;
            top: 0;
            z-index: 30;
            border-bottom: 1px solid rgba(153, 246, 228, 0.85);
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        }
        .tm-header-inner {
            max-width: 72rem;
            margin: 0 auto;
            padding: 0.85rem 1rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .tm-kicker { font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--teal-dark); margin: 0; opacity: 0.9; }
        .tm-title { margin: 0.15rem 0 0; font-size: 1.15rem; font-weight: 800; letter-spacing: -0.02em; }
        @media (min-width: 640px) { .tm-title { font-size: 1.35rem; } }
        .tm-sub { margin: 0.2rem 0 0; font-size: 0.8rem; color: var(--slate-500); max-width: 36rem; }
        .tm-btn-refresh {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.55rem 1rem;
            border-radius: 1rem;
            border: 1px solid rgba(45, 212, 191, 0.55);
            background: #fff;
            font-size: 0.875rem;
            font-weight: 600;
            color: #134e4a;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        }
        .tm-btn-refresh:hover { background: #f0fdfa; border-color: #5eead4; }
        .tm-main { max-width: 72rem; margin: 0 auto; padding: 1.25rem 1rem 2.5rem; }
        .tm-banner {
            margin-bottom: 1.1rem;
            padding: 0.85rem 1rem;
            border-radius: 1rem;
            border: 1px solid rgba(251, 191, 36, 0.65);
            background: #fffbeb;
            color: #422006;
            font-size: 0.9rem;
        }
        .tm-banner.tm-hidden { display: none; }
        .tm-dash {
            border-radius: 1.5rem;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.88);
            padding: 1.1rem 1.15rem 1.35rem;
            box-shadow: 0 4px 24px -4px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(15, 23, 42, 0.03);
        }
        .tm-grid-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.65rem;
        }
        @media (min-width: 640px) { .tm-grid-metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (min-width: 1024px) { .tm-grid-metrics { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
        .metric-tile {
            position: relative;
            overflow: hidden;
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 0.8rem 0.85rem;
            box-shadow: 0 4px 18px -4px rgba(15, 23, 42, 0.12);
            color: #fff;
            min-height: 5.25rem;
        }
        .metric-label { font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.92; margin: 0; }
        .metric-value { margin: 0.35rem 0 0; font-size: 1.55rem; font-weight: 900; line-height: 1; font-variant-numeric: tabular-nums; }
        @media (min-width: 640px) { .metric-value { font-size: 1.75rem; } }
        .metric-sub { margin: 0.25rem 0 0; font-size: 11px; font-weight: 600; opacity: 0.88; }
        .metric-tile--slate { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); }
        .metric-tile--teal { background: linear-gradient(135deg, #0d9488 0%, #059669 100%); }
        .metric-tile--amber { background: linear-gradient(135deg, #d97706 0%, #ea580c 100%); }
        .metric-tile--indigo { background: linear-gradient(135deg, #6366f1 0%, #7c3aed 100%); }
        .metric-tile--sky { background: linear-gradient(135deg, #0ea5e9 0%, #0891b2 100%); }
        .metric-tile--rose { background: linear-gradient(135deg, #e11d48 0%, #db2777 100%); }
        .tm-pipeline-box {
            margin-top: 1rem;
            padding: 1rem 1.1rem;
            border-radius: 1rem;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 12px -2px rgba(15, 23, 42, 0.06);
        }
        .tm-pipeline-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.65rem; }
        .tm-pipeline-head h3 { margin: 0; font-size: 0.9rem; font-weight: 800; }
        .tm-chip-row { display: flex; flex-wrap: wrap; gap: 0.45rem; }
        .tm-muted { margin: 0; font-size: 0.875rem; color: var(--slate-600); }
        .tm-chip-num { margin-left: 0.25rem; opacity: 0.85; font-variant-numeric: tabular-nums; }
        .tm-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 0.65rem;
            border: 1px solid transparent;
            padding: 0.2rem 0.45rem;
            font-size: 11px;
            font-weight: 700;
        }
        .tm-badge--deny { background: #ffe4e6; color: #9f1239; border-color: #fecdd3; }
        .tm-badge--win { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
        .tm-badge--early { background: #f1f5f9; color: #334155; border-color: #e2e8f0; }
        .tm-badge--mid { background: #eef2ff; color: #312e81; border-color: #e0e7ff; }
        .tm-pipeline-bar {
            margin-top: 0.65rem;
            display: flex;
            height: 0.55rem;
            overflow: hidden;
            border-radius: 9999px;
            background: #f1f5f9;
            box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.35);
        }
        .tm-seg { min-width: 0; height: 100%; }
        .tm-seg--won { background: #10b981; }
        .tm-seg--mid { background: #818cf8; }
        .tm-seg--early { background: #cbd5e1; }
        .tm-seg--deny { background: #f43f5e; }
        .tm-seg--other { background: #e2e8f0; }
        .tm-section-head { margin-top: 1.35rem; display: flex; flex-direction: column; gap: 0.65rem; }
        @media (min-width: 640px) {
            .tm-section-head { flex-direction: row; align-items: flex-end; justify-content: space-between; }
        }
        .tm-section-head h2 { margin: 0; font-size: 1.05rem; font-weight: 800; }
        .tm-section-head p { margin: 0.2rem 0 0; font-size: 0.8rem; color: var(--slate-500); }
        .tm-search label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--slate-500); margin-bottom: 0.25rem; }
        .tm-search input {
            width: 100%;
            max-width: 20rem;
            padding: 0.55rem 0.75rem;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
            font-size: 0.875rem;
        }
        .tm-updated { margin-top: 0.35rem; font-size: 0.7rem; color: #94a3b8; }
        .tm-staff-grid {
            margin-top: 1rem;
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 640px) { .tm-staff-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1280px) { .tm-staff-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .tm-staff-card {
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.45);
            background: #fff;
            padding: 1rem;
            box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .tm-staff-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 36px -12px rgba(15, 23, 42, 0.12), 0 0 0 1px rgba(13, 148, 136, 0.1);
        }
        .tm-staff-card--dim { opacity: 0.88; }
        .tm-staff-top { display: flex; gap: 0.75rem; align-items: flex-start; }
        .tm-avatar {
            flex-shrink: 0;
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
            box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.15);
        }
        .tm-staff-meta { flex: 1; min-width: 0; }
        .tm-name-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; }
        .tm-name-row h3 { margin: 0; font-size: 1rem; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .tm-role-pill {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 0.15rem 0.4rem;
            border-radius: 0.35rem;
            background: #f1f5f9;
            color: #475569;
        }
        .tm-email { margin: 0.15rem 0 0; font-size: 0.75rem; color: var(--slate-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tm-staff-count { text-align: right; flex-shrink: 0; }
        .tm-count-num { margin: 0; font-size: 1.65rem; font-weight: 900; color: var(--teal-dark); font-variant-numeric: tabular-nums; line-height: 1; }
        .tm-apps-label { margin: 0.1rem 0 0; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #94a3b8; }
        .tm-chip-wrap { margin-top: 0.65rem; display: flex; flex-wrap: wrap; gap: 0.35rem; min-height: 2.1rem; align-items: center; }
        .tm-wa-note { margin: 0.5rem 0 0; font-size: 11px; color: #b45309; }
        .tm-btn-row { margin-top: 0.85rem; display: flex; flex-wrap: wrap; gap: 0.45rem; }
        .tm-btn {
            font-family: inherit;
            cursor: pointer;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            font-weight: 700;
            padding: 0.55rem 0.85rem;
            border: none;
            text-align: center;
        }
        .tm-btn:active { transform: scale(0.98); }
        .tm-btn-dark { background: #0f172a; color: #fff; flex: 1; min-width: 6rem; }
        .tm-btn-dark:hover { background: #1e293b; }
        .tm-btn-outline { background: #fff; color: #0f172a; border: 1px solid #e2e8f0; }
        .tm-btn-outline:hover { background: #f8fafc; }
        .tm-btn-teal { background: rgba(240, 253, 250, 0.95); color: #134e4a; border: 1px solid rgba(45, 212, 191, 0.55); }
        .tm-btn-teal:hover { background: #ccfbf1; }
        .tm-btn-amber { width: 100%; margin-top: 0.85rem; background: #78350f; color: #fff; padding: 0.65rem; }
        .tm-btn-amber:hover { background: #451a03; }
        .tm-unassigned {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 1rem;
            border: 2px dashed rgba(251, 191, 36, 0.85);
            background: linear-gradient(135deg, #fffbeb 0%, #fff7ed 100%);
            box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.08);
        }
        .tm-unassigned-row { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.75rem; align-items: flex-start; }
        .tm-unassigned-kicker { margin: 0; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #92400e; }
        .tm-unassigned h3 { margin: 0.2rem 0 0; font-size: 1.1rem; font-weight: 900; color: #422006; }
        .tm-unassigned-count { text-align: right; }
        .tm-big-num { margin: 0; font-size: 2rem; font-weight: 900; color: #422006; font-variant-numeric: tabular-nums; }
        .tm-empty {
            margin-top: 1rem;
            text-align: center;
            padding: 2.5rem 1rem;
            border-radius: 1rem;
            border: 1px dashed #cbd5e1;
            background: rgba(255, 255, 255, 0.65);
            color: var(--slate-600);
            font-size: 0.9rem;
        }
        .tm-empty.tm-hidden { display: none; }
        .tm-disabled-placeholder {
            grid-column: 1 / -1;
            padding: 1rem;
            border-radius: 1rem;
            border: 1px solid var(--border);
            background: #f8fafc;
        }
        .tm-disabled-placeholder h4 { margin: 0 0 0.35rem; font-size: 0.95rem; }
        .tm-disabled-placeholder code { font-size: 0.75rem; background: #fff; padding: 0.1rem 0.35rem; border-radius: 0.25rem; border: 1px solid #e2e8f0; }
        #backdrop.tm-hidden, #drawer.tm-hidden-vis, #notifyModal.tm-hidden { display: none; }
        #backdrop {
            position: fixed;
            inset: 0;
            z-index: 40;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(2px);
        }
        #drawer {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            z-index: 50;
            width: 100%;
            max-width: 28rem;
            background: #fff;
            border-left: 1px solid #e2e8f0;
            box-shadow: -8px 0 32px rgba(15, 23, 42, 0.12);
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform 0.28s ease;
        }
        #drawer.is-open { transform: translateX(0); }
        .tm-drawer-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; padding: 1rem; border-bottom: 1px solid #f1f5f9; background: linear-gradient(90deg, #f0fdfa, #fff); }
        .tm-drawer-head h2 { margin: 0; font-size: 1.1rem; font-weight: 800; }
        .tm-drawer-head p { margin: 0.2rem 0 0; font-size: 0.75rem; color: var(--slate-500); }
        #drawerClose { border: none; background: transparent; font-size: 1.25rem; cursor: pointer; color: #64748b; border-radius: 0.5rem; padding: 0.25rem 0.45rem; }
        #drawerClose:hover { background: #f1f5f9; }
        #drawerBody { flex: 1; overflow-y: auto; padding: 1rem; }
        #drawerNotifyBar { border-top: 1px solid #f1f5f9; padding: 1rem; background: #fafafa; }
        #btnOpenNotify { width: 100%; padding: 0.75rem; border-radius: 1rem; border: none; background: var(--teal); color: #fff; font-weight: 700; font-size: 0.9rem; cursor: pointer; }
        #btnOpenNotify:hover { background: #0f766e; }
        #btnOpenNotify.tm-hidden { display: none; }
        #notifyModal {
            position: fixed;
            inset: 0;
            z-index: 60;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        #notifyModal.is-flex { display: flex; }
        .tm-modal-panel {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 32rem;
            background: #fff;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            padding: 1.25rem;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
        }
        .tm-modal-bg { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(3px); }
        @keyframes skel-shine {
            100% { transform: translateX(100%); }
        }
        .tm-skel { position: relative; overflow: hidden; background: #e2e8f0; border-radius: 0.65rem; min-height: 2.5rem; }
        .tm-skel::after {
            content: '';
            position: absolute;
            inset: 0;
            transform: translateX(-100%);
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.55), transparent);
            animation: skel-shine 1.2s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <header class="tm-header">
        <div class="tm-header-inner">
            <div>
                <p class="tm-kicker">Applications · Monitor</p>
                <h1 class="tm-title">Task assignment monitoring</h1>
                <p class="tm-sub">Live workload by assigned staff and superadmins, pipeline mix, and quick actions</p>
            </div>
            <button type="button" class="tm-btn-refresh" id="btnRefresh" title="Reload latest data from the server">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </header>

    <main class="tm-main">
        <div id="bannerDisabled" class="tm-banner<?= $assignmentsOn ? ' tm-hidden' : '' ?>"><?= $assignmentsOn ? '' : pcvc_tm_h($disabledMessage) ?></div>

        <div id="dashMain" class="tm-dash">
            <section class="tm-grid-metrics" id="summaryHero" aria-label="Summary statistics">
                <?php if ($assignmentsOn): ?>
                    <?= pcvc_tm_render_metric_tiles($summaryMetrics) ?>
                <?php else: ?>
                    <div class="tm-disabled-placeholder">
                        <h4>Dashboard needs assignment data</h4>
                        <p class="tm-muted"><?= pcvc_tm_h($disabledMessage) ?></p>
                        <p class="tm-muted" style="margin-top:0.75rem;font-size:0.8rem">Typical fix: add column <code>assigned_to_admin_id</code> on <code>student_applications</code>, then click Refresh.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="tm-pipeline-box" id="pipelineStrip" aria-label="Global pipeline">
                <?php if ($assignmentsOn): ?>
                    <?= pcvc_tm_render_pipeline_strip($grand, $statusOrder, $statusLabels) ?>
                <?php else: ?>
                    <p class="tm-muted" style="margin:0">Pipeline charts will appear here after assignment tracking is enabled.</p>
                <?php endif; ?>
            </section>

            <div id="unassignedSlot">
                <?php
                if ($assignmentsOn && $unassigned !== null && (int) ($unassigned['total'] ?? 0) > 0) {
                    echo pcvc_tm_render_unassigned_card($unassigned, PCVC_DEFAULT_ASSIGNED_PERSON_LABEL);
                }
                ?>
            </div>

            <div class="tm-section-head">
                <div>
                    <h2>Team workload</h2>
                    <p>Staff accounts · sorted by active applications</p>
                </div>
                <div class="tm-search">
                    <label for="staffSearch">Filter</label>
                    <input type="search" id="staffSearch" autocomplete="off" placeholder="Search by name or email…">
                </div>
            </div>
            <p class="tm-updated" id="lastUpdated">Loaded <?= pcvc_tm_h($generatedAt) ?> (server time)</p>

            <div class="tm-staff-grid" id="staffList">
                <?php
                if ($assignmentsOn) {
                    foreach ($staffList as $idx => $row) {
                        if (is_array($row)) {
                            echo pcvc_tm_render_staff_card($row, (int) $idx, $isPrivileged, $statusOrder, $statusLabels);
                        }
                    }
                }
                ?>
            </div>
            <p id="emptyState" class="tm-empty<?= ($assignmentsOn && $staffList !== []) ? ' tm-hidden' : '' ?>">
                <?php if ($assignmentsOn): ?>
                    No assignable accounts (staff or superadmin) were found. Add accounts in Staff Management, or assign applications from Applicants Management.
                <?php else: ?>
                    Enable assignments (see above) to list team workload here.
                <?php endif; ?>
            </p>
        </div>
    </main>

    <div id="backdrop" class="tm-hidden" aria-hidden="true"></div>
    <aside id="drawer" class="tm-hidden-vis" aria-hidden="true">
        <div class="tm-drawer-head">
            <div style="min-width:0">
                <h2 id="drawerTitle">Applications</h2>
                <p id="drawerSub"></p>
            </div>
            <button type="button" id="drawerClose" aria-label="Close">✕</button>
        </div>
        <div id="drawerBody"></div>
        <div id="drawerNotifyBar">
            <button type="button" id="btnOpenNotify" class="tm-hidden">Notify this staff…</button>
        </div>
    </aside>

    <div id="notifyModal" class="tm-hidden" aria-hidden="true">
        <div class="tm-modal-bg" data-close-notify></div>
        <div class="tm-modal-panel">
            <h3 style="margin:0;font-size:1.1rem;font-weight:800">Notify staff</h3>
            <p id="notifyTarget" style="margin:0.35rem 0 0;font-size:0.875rem;color:#475569"></p>
            <label for="notifyMessage" style="display:block;margin-top:1rem;font-size:0.75rem;font-weight:600;color:#475569">Message</label>
            <textarea id="notifyMessage" rows="5" placeholder="What should this staff member do?" style="width:100%;margin-top:0.35rem;padding:0.5rem 0.65rem;border-radius:0.65rem;border:1px solid #e2e8f0;font:inherit;resize:vertical"></textarea>
            <p style="margin:0.35rem 0 0;font-size:11px;color:#64748b;line-height:1.35">Email subject is fixed to &ldquo;general followup&rdquo;. WhatsApp uses the <strong>urgent</strong> template from your <code style="font-size:10px">.env</code> (or session message if unset).</p>
            <div style="margin-top:0.85rem;display:flex;gap:1.25rem;font-size:0.875rem">
                <label><input type="checkbox" id="chkEmail" checked> Email</label>
                <label><input type="checkbox" id="chkWa" checked> WhatsApp</label>
            </div>
            <div id="notifyStatus" style="margin-top:0.5rem;min-height:1.1rem;font-size:0.75rem;color:#475569"></div>
            <div style="margin-top:1rem;display:flex;gap:0.5rem;justify-content:flex-end;flex-wrap:wrap">
                <button type="button" class="tm-btn tm-btn-outline" data-close-notify>Cancel</button>
                <button type="button" id="btnSendNotify" class="tm-btn tm-btn-dark" style="flex:0 auto;min-width:5rem">Send</button>
            </div>
        </div>
    </div>

    <script type="application/json" id="pcvc-tm-meta"><?= $tmMetaJson ?></script>
    <script>
    (function () {
        'use strict';
        var TM = { api: '', canNotify: false, labels: {}, order: [] };
        try {
            var metaEl = document.getElementById('pcvc-tm-meta');
            if (metaEl) {
                TM = JSON.parse(metaEl.textContent || '{}');
                metaEl.remove();
            }
        } catch (e) { console.warn('[tm] meta', e); }

        function apiUrl() {
            try {
                return new URL('api/task_assignment_monitor.php', window.location.href).href;
            } catch (err) {
                return TM.api || 'api/task_assignment_monitor.php';
            }
        }

        function $(id) { return document.getElementById(id); }

        function esc(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function badgeClass(key) {
            if (key === 'deny') return 'tm-badge tm-badge--deny';
            if (key === 'enrolled' || key === 'visa_approved') return 'tm-badge tm-badge--win';
            if (key === 'submitted' || key === 'incomplete_app' || key === 'app_start') return 'tm-badge tm-badge--early';
            return 'tm-badge tm-badge--mid';
        }

        function openInAdmin(url, title) {
            try {
                if (window.parent && typeof window.parent.loadInFrame === 'function') {
                    window.parent.loadInFrame(url, title || 'Applicants');
                    return;
                }
            } catch (e) {}
            window.location.href = url;
        }

        document.getElementById('btnRefresh').addEventListener('click', function () {
            window.location.reload();
        });

        function filterStaffCards() {
            var q = (($('staffSearch') && $('staffSearch').value) || '').trim().toLowerCase();
            document.querySelectorAll('#staffList article').forEach(function (art) {
                var hay = (art.getAttribute('data-search') || '').toLowerCase();
                art.style.display = !q || hay.indexOf(q) >= 0 ? '' : 'none';
            });
        }
        var staffSearchEl = $('staffSearch');
        if (staffSearchEl) staffSearchEl.addEventListener('input', filterStaffCards);

        function fetchJson(url, opts) {
            return new Promise(function (resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.open((opts && opts.method) || 'GET', url, true);
                xhr.withCredentials = true;
                xhr.timeout = 45000;
                if (opts && opts.body) {
                    xhr.setRequestHeader('Content-Type', 'application/json');
                }
                xhr.onload = function () {
                    var text = xhr.responseText || '';
                    var j;
                    try { j = JSON.parse(text); } catch (parseErr) {
                        reject(new Error('Invalid JSON (HTTP ' + xhr.status + ')'));
                        return;
                    }
                    if (xhr.status < 200 || xhr.status >= 300) {
                        reject(new Error((j && j.message) ? j.message : ('HTTP ' + xhr.status)));
                        return;
                    }
                    resolve(j);
                };
                xhr.onerror = function () { reject(new Error('Network error')); };
                xhr.ontimeout = function () { reject(new Error('Request timed out')); };
                xhr.send((opts && opts.body) ? opts.body : null);
            });
        }

        function fetchApps(staffId) {
            var u = apiUrl() + (apiUrl().indexOf('?') >= 0 ? '&' : '?') + 'action=applications&staff_id=' + encodeURIComponent(String(staffId));
            return fetchJson(u).then(function (j) {
                if (!j.success) throw new Error(j.message || 'Load failed');
                return (j.data && j.data.applications) ? j.data.applications : [];
            });
        }

        function closeDrawer() {
            $('drawer').classList.remove('is-open');
            $('drawer').classList.add('tm-hidden-vis');
            $('backdrop').classList.add('tm-hidden');
            $('drawer').setAttribute('aria-hidden', 'true');
        }
        function openDrawer() {
            $('backdrop').classList.remove('tm-hidden');
            $('drawer').classList.remove('tm-hidden-vis');
            $('drawer').classList.add('is-open');
            $('drawer').setAttribute('aria-hidden', 'false');
        }

        var selectedStaff = null;

        function openStaffDrawer(staffId, name) {
            selectedStaff = { id: staffId, name: name };
            $('drawerTitle').textContent = name || (staffId === 0 ? 'Unassigned' : 'Staff');
            $('drawerSub').textContent = 'Loading…';
            $('drawerBody').innerHTML = '<div class="tm-skel" style="height:4rem;margin:1rem 0"></div><p class="tm-muted">Loading…</p>';
            $('btnOpenNotify').classList.toggle('tm-hidden', !TM.canNotify || !staffId);
            openDrawer();
            fetchApps(staffId).then(function (apps) {
                $('drawerSub').textContent = apps.length + ' application(s)';
                if (!apps.length) {
                    $('drawerBody').innerHTML = '<p class="tm-muted" style="text-align:center;padding:2rem 0">No applications in this bucket.</p>';
                    return;
                }
                $('drawerBody').innerHTML = apps.map(function (a) {
                    var st = esc(a.status_label || '—');
                    return '<div style="margin-bottom:0.5rem;padding:0.75rem;border-radius:0.75rem;border:1px solid #f1f5f9;background:#f8fafc">'
                        + '<div style="display:flex;justify-content:space-between;gap:0.5rem;flex-wrap:wrap">'
                        + '<div style="font-weight:700;min-width:0">' + esc(a.student_name) + '</div>'
                        + '<span class="' + badgeClass(a.status_key || '') + '">' + st + '</span></div>'
                        + '<div style="margin-top:0.35rem;font-size:0.75rem;color:#64748b">ID ' + esc(a.application_id || a.id) + ' · ' + esc(a.email || '') + '</div></div>';
                }).join('');
            }).catch(function (e) {
                $('drawerBody').innerHTML = '<p style="color:#e11d48;padding:1rem;font-size:0.875rem">' + esc(e.message) + '</p>';
            });
        }

        var staffListEl = $('staffList');
        if (staffListEl) {
            staffListEl.addEventListener('click', function (ev) {
                var b = ev.target.closest('button[data-view]');
                if (b) {
                    var sid = parseInt(b.getAttribute('data-view'), 10);
                    var art = b.closest('article');
                    var titleEl = art ? art.querySelector('h3') : null;
                    var title = titleEl ? titleEl.textContent.trim() : '';
                    openStaffDrawer(sid, title);
                    return;
                }
                var n = ev.target.closest('button[data-notify]');
                if (n) {
                    var art2 = n.closest('article');
                    var t2 = art2 && art2.querySelector('h3') ? art2.querySelector('h3').textContent.trim() : '';
                    openNotifyModal(parseInt(n.getAttribute('data-notify'), 10), t2);
                    return;
                }
                var f = ev.target.closest('button[data-filter]');
                if (f) {
                    var sf = f.getAttribute('data-filter');
                    openInAdmin('students-manage.php?filter_staff=' + encodeURIComponent(sf), 'Applicants Management');
                }
            });
        }

        var unassignedSlot = $('unassignedSlot');
        if (unassignedSlot) {
            unassignedSlot.addEventListener('click', function (ev) {
                var b = ev.target.closest('button[data-view]');
                if (!b) return;
                var art = b.closest('article');
                var titleEl = art ? art.querySelector('h3') : null;
                var title = titleEl ? titleEl.textContent.trim() : 'Unassigned';
                openStaffDrawer(0, title);
            });
        }

        $('drawerClose').addEventListener('click', closeDrawer);
        $('backdrop').addEventListener('click', closeDrawer);

        var notifyStaffId = 0;
        function openNotifyModal(sid, name) {
            notifyStaffId = sid;
            $('notifyTarget').textContent = (name || '') + (sid ? ' (#' + sid + ')' : '');
            $('notifyMessage').value = '';
            $('chkEmail').checked = true;
            $('chkWa').checked = true;
            $('notifyStatus').textContent = '';
            $('notifyModal').classList.remove('tm-hidden');
            $('notifyModal').classList.add('is-flex');
        }
        function closeNotifyModal() {
            $('notifyModal').classList.add('tm-hidden');
            $('notifyModal').classList.remove('is-flex');
        }
        document.querySelectorAll('[data-close-notify]').forEach(function (el) {
            el.addEventListener('click', closeNotifyModal);
        });

        $('btnOpenNotify').addEventListener('click', function () {
            if (selectedStaff && selectedStaff.id) {
                openNotifyModal(selectedStaff.id, selectedStaff.name);
            }
        });

        $('btnSendNotify').addEventListener('click', function () {
            var msg = ($('notifyMessage').value || '').trim();
            if (!msg) { $('notifyStatus').textContent = 'Please enter a message.'; return; }
            $('btnSendNotify').disabled = true;
            $('notifyStatus').textContent = 'Sending…';
            var payload = {
                staff_id: notifyStaffId,
                message: msg,
                send_email: $('chkEmail').checked,
                send_whatsapp: $('chkWa').checked
            };
            if ($('chkWa').checked) {
                payload.whatsapp_template = 'urgent';
            }
            var body = JSON.stringify(payload);
            fetchJson(apiUrl() + (apiUrl().indexOf('?') >= 0 ? '&' : '?') + 'action=notify', { method: 'POST', body: body })
                .then(function (j) {
                    if (j.success && j.data) {
                        var bits = [];
                        if (j.data.email_sent) bits.push('Email sent');
                        if (j.data.whatsapp_sent) {
                            var wm = j.data.whatsapp_method || '';
                            bits.push('WhatsApp sent' + (wm ? ' (' + wm + ')' : ''));
                        }
                        var err = (j.data.errors || []).join(' ');
                        $('notifyStatus').textContent = bits.join(' · ') + (err ? (' — ' + err) : '');
                        if (j.data.email_sent || j.data.whatsapp_sent) {
                            setTimeout(closeNotifyModal, 1200);
                        }
                    } else {
                        $('notifyStatus').textContent = j.message || 'Send failed';
                    }
                })
                .catch(function (e) {
                    $('notifyStatus').textContent = e.message || 'Error';
                })
                .finally(function () {
                    $('btnSendNotify').disabled = false;
                });
        });
    })();
    </script>
</body>
</html>
