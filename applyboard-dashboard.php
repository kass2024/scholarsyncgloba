<?php
/**
 * ApplyBoard applications dashboard — reads from `applyboard_students` (Python scraper).
 * Supports Excel-style column names (Target University, Student ID, …) and legacy snake_case.
 */
declare(strict_types=1);

session_start();
if (empty($_SESSION['id']) && empty($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/company_branding.php';

$table = 'applyboard_students';

/** @return array<string, bool> */
function applyboard_fields(mysqli $conn, string $table): array
{
    $out = [];
    $esc = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '$esc'");
    if (!$r || $r->num_rows === 0) {
        return $out;
    }
    $r = $conn->query("SHOW COLUMNS FROM `$table`");
    if (!$r) {
        return $out;
    }
    while ($row = $r->fetch_assoc()) {
        $out[$row['Field']] = true;
    }
    return $out;
}

/**
 * @param array<string, bool> $fields
 * @param list<string> $candidates
 */
function applyboard_pick_col(array $fields, array $candidates): ?string
{
    foreach ($candidates as $c) {
        if (!empty($fields[$c])) {
            return $c;
        }
    }
    return null;
}

/**
 * Match a column when scraper/DB uses a slightly different name (spacing, legacy).
 *
 * @param array<string, bool> $fields
 * @param list<string> $substrings case-insensitive substring to find in field name
 */
function applyboard_column_by_name_substrings(array $fields, array $substrings): ?string
{
    foreach ($substrings as $needle) {
        $n = strtolower($needle);
        foreach (array_keys($fields) as $col) {
            if (strtolower($col) === $n) {
                return $col;
            }
        }
    }
    foreach ($substrings as $needle) {
        $n = strtolower($needle);
        foreach (array_keys($fields) as $col) {
            if (strpos(strtolower($col), $n) !== false) {
                return $col;
            }
        }
    }
    return null;
}

/** DB columns excluded from this dashboard UI only (data remains in MySQL). */
const APPLYBOARD_UI_HIDDEN_COLUMNS = ['City', 'city', 'In-take', 'intake_label', 'Graduation / Notes', 'graduation_notes'];

function applyboard_is_hidden_ui_column(string $col): bool
{
    return in_array($col, APPLYBOARD_UI_HIDDEN_COLUMNS, true);
}

/** @param array<string, bool> $fields */
function applyboard_display_order(array $fields): array
{
    $preferred = [
        'Student ID', 'student_id',
        'Applicant Name', 'applicant_name',
        'Registration Date', 'registration_date',
        'Applicant Email', 'applicant_email',
        'Education Level', 'education_level',
        'Destination/Country', 'destination_country',
        'Target University', 'target_university',
        'Target Program', 'target_program',
        'Target Intake', 'target_intake',
        'WhatsApp Num', 'whatsapp_num',
        'Country', 'country',
        'Status App', 'status_app',
        'updated_at',
    ];
    $ordered = [];
    foreach ($preferred as $p) {
        if (!empty($fields[$p]) && !applyboard_is_hidden_ui_column($p)) {
            $ordered[] = $p;
        }
    }
    foreach (array_keys($fields) as $f) {
        if (applyboard_is_hidden_ui_column($f)) {
            continue;
        }
        if (!in_array($f, $ordered, true)) {
            $ordered[] = $f;
        }
    }
    return $ordered;
}

function applyboard_registration_year(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    // Unix timestamp (string of digits)
    if (preg_match('/^\d{10,13}$/', $raw)) {
        $ts = (int) $raw;
        if ($ts > 946684800 && $ts < 4102444800) { // 2000–2100 sanity
            return date('Y', $ts);
        }
    }
    // ISO / MySQL datetime: 2026-04-30 / 2026-04-30 19:25:17
    if (preg_match('/^(\d{4})-\d{2}-\d{2}/', $raw, $m)) {
        return $m[1];
    }
    if (preg_match('/\b(19|20)\d{2}\b/', $raw, $m)) {
        return $m[0];
    }
    // d/m/Y or m/d/Y — try both via strtotime with normalized separators
    $norm = str_replace(['/', '.'], '-', $raw);
    $ts = strtotime($norm);
    if ($ts) {
        return date('Y', $ts);
    }
    $ts = strtotime($raw);
    return $ts ? date('Y', $ts) : '';
}

/**
 * Best-effort year for filters: registration column first, then updated_at.
 *
 * @param array<string, mixed> $r
 */
function applyboard_row_year_for_filter(array $r, ?string $regCol, ?string $updCol): string
{
    if ($regCol) {
        $y = applyboard_registration_year((string) ($r[$regCol] ?? ''));
        if ($y !== '') {
            return $y;
        }
    }
    if ($updCol) {
        $y = applyboard_registration_year((string) ($r[$updCol] ?? ''));
        if ($y !== '') {
            return $y;
        }
    }
    return '';
}

function applyboard_normalize_column_name(string $name): string
{
    $normalized = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($name)));
    return trim((string) ($normalized ?? strtolower(trim($name))), '_');
}

/**
 * @param array<string, bool> $fields
 * @param list<string> $candidates
 * @param list<string> $substrings
 */
function applyboard_pick_flexible_col(array $fields, array $candidates, array $substrings = []): ?string
{
    $exact = applyboard_pick_col($fields, $candidates);
    if ($exact) {
        return $exact;
    }

    $normalizedCandidates = [];
    foreach ($candidates as $candidate) {
        $normalizedCandidates[applyboard_normalize_column_name($candidate)] = true;
    }
    foreach (array_keys($fields) as $col) {
        if (!empty($normalizedCandidates[applyboard_normalize_column_name($col)])) {
            return $col;
        }
    }

    foreach ($substrings as $needle) {
        $normalizedNeedle = applyboard_normalize_column_name($needle);
        if ($normalizedNeedle === '') {
            continue;
        }
        foreach (array_keys($fields) as $col) {
            if (str_contains(applyboard_normalize_column_name($col), $normalizedNeedle)) {
                return $col;
            }
        }
    }

    return null;
}

function applyboard_parse_sort_timestamp(mixed $value): ?int
{
    if ($value === null) {
        return null;
    }

    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    if (preg_match('/^\d{10,13}$/', $raw)) {
        $ts = (int) $raw;
        if (strlen($raw) === 13) {
            $ts = (int) floor($ts / 1000);
        }
        if ($ts > 946684800 && $ts < 4102444800) {
            return $ts;
        }
    }

    $normalized = str_replace(['/', '.'], '-', $raw);
    $ts = strtotime($normalized);
    if ($ts !== false) {
        return $ts;
    }

    $ts = strtotime($raw);
    return $ts !== false ? $ts : null;
}

function applyboard_parse_sort_numeric_id(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    if (preg_match('/^-?\d+(?:\.\d+)?$/', $raw)) {
        $raw = preg_replace('/\..*$/', '', $raw) ?? $raw;
        $raw = ltrim($raw, '+-');
    } else {
        $raw = preg_replace('/\D+/', '', $raw) ?? '';
        if ($raw === '') {
            return null;
        }
    }

    $raw = ltrim($raw, '0');
    return $raw === '' ? '0' : $raw;
}

function applyboard_compare_sort_numeric_id(?string $a, ?string $b): int
{
    if ($a === $b) {
        return 0;
    }
    if ($a === null) {
        return -1;
    }
    if ($b === null) {
        return 1;
    }

    $lenCmp = strlen($a) <=> strlen($b);
    if ($lenCmp !== 0) {
        return $lenCmp;
    }

    return strcmp($a, $b);
}

/**
 * @param array<string, bool> $fields
 * @return array{
 *   created:?string,
 *   id:?string,
 *   updated:?string,
 *   registration:?string,
 *   student_id:?string,
 *   order:list<string>
 * }
 */
function applyboard_sort_columns(array $fields): array
{
    $createdCol = applyboard_pick_flexible_col(
        $fields,
        ['created_at', 'createdAt', 'created_on', 'created date', 'date_created', 'creation_date'],
        ['created_at', 'created_on', 'date_created', 'creation', 'created']
    );
    $idCol = applyboard_pick_flexible_col($fields, ['id', 'ID']);
    $updatedCol = applyboard_pick_flexible_col(
        $fields,
        ['updated_at', 'updatedAt', 'updated_on', 'modified_at', 'last_updated'],
        ['updated_at', 'updated_on', 'last_updated', 'updated', 'modified']
    );
    $registrationCol = applyboard_pick_flexible_col(
        $fields,
        ['Registration Date', 'registration_date', 'registered_at', 'registered_on'],
        ['registration_date', 'registration', 'registered', 'reg_date']
    );
    $studentIdCol = applyboard_pick_flexible_col(
        $fields,
        ['Student ID', 'student_id', 'studentid'],
        ['student_id', 'student id']
    );

    $order = [];
    // Match the ApplyBoard / DB list view: start from the last DB record first.
    // In this table the real primary record order is Student ID descending.
    foreach ([$studentIdCol, $idCol, $updatedCol, $createdCol, $registrationCol] as $col) {
        if ($col && !in_array($col, $order, true)) {
            $order[] = $col;
        }
    }

    return [
        'created' => $createdCol,
        'id' => $idCol,
        'updated' => $updatedCol,
        'registration' => $registrationCol,
        'student_id' => $studentIdCol,
        'order' => $order,
    ];
}

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function applyboard_attach_sort_metadata(
    array $rows,
    ?string $createdCol,
    ?string $idCol,
    ?string $updatedCol,
    ?string $regCol,
    ?string $studentIdCol
): array {
    foreach ($rows as $idx => &$row) {
        $createdTs = $createdCol ? applyboard_parse_sort_timestamp($row[$createdCol] ?? null) : null;
        $updatedTs = $updatedCol ? applyboard_parse_sort_timestamp($row[$updatedCol] ?? null) : null;
        $regTs = $regCol ? applyboard_parse_sort_timestamp($row[$regCol] ?? null) : null;
        $latestTs = null;
        foreach ([$updatedTs, $createdTs, $regTs] as $ts) {
            if ($ts !== null && ($latestTs === null || $ts > $latestTs)) {
                $latestTs = $ts;
            }
        }

        $row['__sort_latest_ts'] = $latestTs;
        $row['__sort_created_ts'] = $createdTs;
        $row['__sort_id_num'] = $idCol ? applyboard_parse_sort_numeric_id($row[$idCol] ?? null) : null;
        $row['__sort_updated_ts'] = $updatedTs;
        $row['__sort_reg_ts'] = $regTs;
        $row['__sort_student_id_num'] = $studentIdCol ? applyboard_parse_sort_numeric_id($row[$studentIdCol] ?? null) : null;
        $row['__sort_source_pos'] = $idx;
    }
    unset($row);

    return $rows;
}

/** @param array<string,mixed> $a @param array<string,mixed> $b */
function applyboard_compare_rows_latest_first(array $a, array $b): int
{
    foreach (['__sort_latest_ts', '__sort_updated_ts', '__sort_created_ts', '__sort_reg_ts', '__sort_id_num', '__sort_student_id_num'] as $key) {
        if ($key === '__sort_id_num' || $key === '__sort_student_id_num') {
            $cmp = applyboard_compare_sort_numeric_id(
                isset($b[$key]) ? (is_string($b[$key]) ? $b[$key] : (string) $b[$key]) : null,
                isset($a[$key]) ? (is_string($a[$key]) ? $a[$key] : (string) $a[$key]) : null
            );
            if ($cmp !== 0) {
                return $cmp;
            }
            continue;
        }

        $aVal = isset($a[$key]) && $a[$key] !== null ? (int) $a[$key] : -1;
        $bVal = isset($b[$key]) && $b[$key] !== null ? (int) $b[$key] : -1;
        if ($aVal !== $bVal) {
            return $bVal <=> $aVal;
        }
    }

    $srcCmp = ((int) ($a['__sort_source_pos'] ?? PHP_INT_MAX)) <=> ((int) ($b['__sort_source_pos'] ?? PHP_INT_MAX));
    if ($srcCmp !== 0) {
        return $srcCmp;
    }

    return ((int) ($a['__app_index'] ?? 1)) <=> ((int) ($b['__app_index'] ?? 1));
}

/** @param array<mixed> $a */
function applyboard_is_list_array(array $a): bool
{
    $i = 0;
    foreach ($a as $k => $_) {
        if ($k !== $i++) {
            return false;
        }
    }

    return true;
}

/**
 * Split one scraper field into segments (semicolon lists or JSON arrays of strings).
 *
 * @return list<string>
 */
function applyboard_split_bundle_field(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    $t = ltrim($raw);
    if (($t[0] ?? '') === '[' || ($t[0] ?? '') === '{') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            if (applyboard_is_list_array($decoded)) {
                $out = [];
                foreach ($decoded as $v) {
                    if (is_scalar($v)) {
                        $out[] = trim((string) $v);
                    }
                }
                if ($out !== []) {
                    return $out;
                }
            }
            return [$raw];
        }
    }
    $parts = preg_split('/\s*;\s*/', $raw);
    return array_map('trim', $parts ?: []);
}

/**
 * Pick segment i; if the field only had one value and this row was expanded to n apps, repeat that value.
 */
function applyboard_segment_at(array $parts, int $i, int $n): string
{
    if ($parts === []) {
        return '';
    }
    if (count($parts) === 1 && $n > 1) {
        return $parts[0];
    }

    return trim((string) ($parts[$i] ?? ''));
}

/**
 * Expand bundled ApplyBoard rows into one row per application (aligned semicolon / JSON lists).
 *
 * @param list<array<string,mixed>> $rows
 * @param array<string,bool> $fields
 * @return list<array<string,mixed>>
 */
function applyboard_explode_application_rows(array $rows, array $fields): array
{
    $univCol = applyboard_pick_col($fields, ['Target University', 'target_university']);
    $progCol = applyboard_pick_col($fields, ['Target Program', 'target_program']);
    $intakeCol = applyboard_pick_col($fields, ['Target Intake', 'target_intake']);
    $statusCol = applyboard_pick_col($fields, ['Status App', 'status_app'])
        ?: applyboard_column_by_name_substrings($fields, ['status', 'application status']);
    $destCol = applyboard_pick_col($fields, ['Destination/Country', 'destination_country']);

    $keys = array_filter([$univCol, $progCol, $intakeCol, $statusCol, $destCol], static fn ($c) => (bool) $c);
    if ($keys === []) {
        $out = [];
        foreach ($rows as $r) {
            $copy = $r;
            $copy['__app_index'] = 1;
            $copy['__app_of'] = 1;
            $out[] = $copy;
        }

        return $out;
    }

    $out = [];
    foreach ($rows as $r) {
        $splits = [];
        foreach ($keys as $col) {
            $splits[$col] = applyboard_split_bundle_field((string) ($r[$col] ?? ''));
        }
        $n = 0;
        foreach ($splits as $parts) {
            $n = max($n, count($parts));
        }
        if ($n === 0) {
            $n = 1;
        }

        for ($i = 0; $i < $n; $i++) {
            $copy = $r;
            $copy['__app_index'] = $i + 1;
            $copy['__app_of'] = $n;
            foreach ($keys as $col) {
                $copy[$col] = applyboard_segment_at($splits[$col], $i, $n);
            }
            $out[] = $copy;
        }
    }

    return $out;
}

function applyboard_status_badge_html(string $status): string
{
    $s = trim($status);
    if ($s === '') {
        return '<span class="ab-badge ab-badge-muted">—</span>';
    }
    $low = strtolower($s);
    $cls = 'ab-badge-muted';
    if (str_contains($low, 'process')) {
        $cls = 'ab-badge-info';
    } elseif (str_contains($low, 'accept')) {
        $cls = 'ab-badge-success';
    } elseif (str_contains($low, 'reject')) {
        $cls = 'ab-badge-danger';
    } elseif (str_contains($low, 'cancel')) {
        $cls = 'ab-badge-warn';
    } elseif (str_contains($low, 'unpaid') || str_contains($low, 'draft')) {
        $cls = 'ab-badge-warn';
    } elseif (str_contains($low, 'pend') || str_contains($low, 'defer')) {
        $cls = 'ab-badge-info';
    } elseif (str_contains($low, 'wait')) {
        $cls = 'ab-badge-muted';
    }

    return '<span class="ab-badge ' . $cls . '">' . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . '</span>';
}

$fields = applyboard_fields($conn, $table);
$tableMissing = $fields === [];

$destCol = applyboard_pick_col($fields, ['Destination/Country', 'destination_country']);
$univCol = applyboard_pick_col($fields, ['Target University', 'target_university']);
$statusCol = applyboard_pick_col($fields, ['Status App', 'status_app'])
    ?: applyboard_column_by_name_substrings($fields, ['status', 'application status']);
$sortCols = applyboard_sort_columns($fields);
$createdCol = $sortCols['created'];
$idCol = $sortCols['id'];
$updCol = $sortCols['updated'];
$regCol = $sortCols['registration'];
$studentIdCol = $sortCols['student_id'];
$orderCols = $sortCols['order'];

$rows = [];
$stats = ['total' => 0, 'student_records' => 0, 'by_dest' => []];

if (!$tableMissing && $orderCols !== []) {
    $sqlOrderParts = [];
    foreach ($orderCols as $orderCol) {
        $sqlOrderParts[] = '`' . str_replace('`', '``', $orderCol) . '` DESC';
    }
    $sql = "SELECT * FROM `$table` ORDER BY " . implode(', ', $sqlOrderParts) . " LIMIT 8000";
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
} elseif (!$tableMissing) {
    // Table exists but no known sort column — still load rows.
    $sql = "SELECT * FROM `$table` LIMIT 8000";
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
}

if (!$tableMissing) {
    $rows = applyboard_attach_sort_metadata($rows, $createdCol, $idCol, $updCol, $regCol, $studentIdCol);
    $stats['student_records'] = count($rows);
    $rows = applyboard_explode_application_rows($rows, $fields);
    // Keep the SQL/DB order as loaded so the dashboard starts with the last DB record.
    $stats['total'] = count($rows);
}

if (!$tableMissing && $destCol && $stats['total'] > 0) {
    $counts = [];
    foreach ($rows as $r) {
        $d = trim((string) ($r[$destCol] ?? ''));
        if ($d === '') {
            $d = 'Without Applications';
        }
        // Exclude Malaysia from dashboard stats (per request)
        $low = strtolower(trim($d));
        if ($low === 'malaysia' || $low === 'malasia') {
            continue;
        }
        $counts[$d] = ($counts[$d] ?? 0) + 1;
    }
    arsort($counts);
    $stats['by_dest'] = array_slice($counts, 0, 12, true);
}

/** Distinct years (registration or updated_at) — used only for debug/stats if needed */
$yearsOpts = [];
/** Individual status tokens (split on ';') so filters match Processing, Unpaid, Accepted per application */
$statusTokensSorted = [];
if (!$tableMissing && $stats['total'] > 0) {
    foreach ($rows as $r) {
        $y = applyboard_row_year_for_filter($r, $regCol, $updCol);
        if ($y !== '') {
            $yearsOpts[$y] = true;
        }
    }
    krsort($yearsOpts);

    $statusTokenSet = [];
    if ($statusCol) {
        foreach ($rows as $r) {
            $seg = trim((string) ($r[$statusCol] ?? ''));
            if ($seg !== '') {
                $statusTokenSet[$seg] = true;
            }
        }
    }
    $priority = [
        'unpaid' => 0,
        'processing' => 1,
        'accepted' => 2,
        'rejected' => 3,
        'canceled' => 4,
        'cancelled' => 5,
        'submitted' => 6,
        'draft' => 7,
        'pending' => 8,
        'deferred' => 9,
        'withdrawn' => 10,
        'waitlisted' => 11,
    ];
    $toks = array_keys($statusTokenSet);
    usort(
        $toks,
        static function (string $a, string $b) use ($priority): int {
            $la = strtolower($a);
            $lb = strtolower($b);
            $oa = $priority[$la] ?? 100;
            $ob = $priority[$lb] ?? 100;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }
            return strcasecmp($a, $b);
        }
    );
    $statusTokensSorted = $toks;
}

$uniqUniv = [];
if (!$tableMissing && $univCol && ($stats['total'] ?? 0) > 0) {
    foreach ($rows as $r) {
        $u = trim((string) ($r[$univCol] ?? ''));
        if ($u !== '') {
            $uniqUniv[$u] = true;
        }
    }
    ksort($uniqUniv);
}

$displayCols = $tableMissing ? [] : applyboard_display_order($fields);
if (!$tableMissing) {
    array_unshift($displayCols, 'App #');
    $displayCols[] = 'Action';
}

$pageTitle = 'ApplyBoard Applications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --green: #1e5c4a;
            --green-light: #2d7a62;
            --bg: #e8eeea;
            --card: #fff;
            --border: #c5d4cd;
            --text: #0f1f1a;
            --muted: #4a5c55;
            --td-alt: #f3f8f5;
        }
        * { box-sizing: border-box; }
        html { height: 100%; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            font-size: 15px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        /**
         * Dashboard with data: no page scroll — only the table panel scrolls.
         * Horizontal scrollbar uses overflow-x: scroll so the track stays reserved.
         */
        body.ab-page {
            height: 100%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        body.ab-page .ab-wrap {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            max-width: 100%;
            padding: 20px 22px 12px;
        }
        body.ab-page .ab-sticky {
            flex-shrink: 0;
        }
        body.ab-page .ab-table-dock {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            margin-top: 4px;
        }
        .wrap { max-width: 100%; padding: 24px 22px 48px; }
        body.ab-page .wrap { padding-bottom: 12px; }
        h1 {
            margin: 0 0 8px;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.02em;
        }
        h1 i { color: var(--green); font-size: 1.35rem; }

        .kpis {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .kpi {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: 0 4px 16px rgba(15, 31, 26, .06);
        }
        .kpi .lab { font-size: 0.72rem; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); font-weight: 600; }
        .kpi .val { font-size: 1.65rem; font-weight: 800; color: var(--green); margin-top: 6px; letter-spacing: -0.03em; }

        .filter-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 18px 18px;
            margin-bottom: 16px;
            box-shadow: 0 4px 18px rgba(15, 31, 26, .07);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px 16px;
            align-items: end;
        }
        .filter-field label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .filter-field input, .filter-field select {
            width: 100%;
            padding: 11px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            font-size: 14px;
            background: #fafdfc;
            color: var(--text);
        }
        .filter-field input:focus, .filter-field select:focus {
            outline: none;
            border-color: var(--green-light);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(30, 92, 74, .18);
        }
        .filter-meta {
            margin-top: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 999px;
            background: linear-gradient(180deg, #e8f2ee 0%, #dceae4 100%);
            color: var(--text);
            border: 1px solid #c5d9d0;
        }

        /* Single scroll region: vertical + horizontal; x-scroll keeps H bar track visible */
        body.ab-page .table-scroll {
            flex: 1;
            min-height: 0;
            max-height: none;
        }
        .table-scroll {
            overflow-x: scroll;
            overflow-y: auto;
            overscroll-behavior: contain;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--card);
            box-shadow: 0 8px 28px rgba(15, 31, 26, .08);
            scrollbar-gutter: stable both-edges;
        }
        /* Fallback when not using ab-page (e.g. table missing): reasonable height */
        body:not(.ab-page) .table-scroll {
            min-height: 420px;
            max-height: min(78vh, 920px);
        }
        .table-scroll::-webkit-scrollbar {
            height: 14px;
            width: 12px;
        }
        .table-scroll::-webkit-scrollbar-thumb {
            background: #8fb3a4;
            border-radius: 8px;
            border: 2px solid var(--card);
        }
        .table-scroll::-webkit-scrollbar-track {
            background: #e8f0ec;
            border-radius: 8px;
        }
        /* separate + spacing:0 helps thead position:sticky inside .table-scroll */
        table.ab-data-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            font-size: 14px;
        }
        th {
            position: sticky;
            top: 0;
            z-index: 4;
            background: linear-gradient(180deg, #236b56 0%, var(--green) 55%, #174a3c 100%);
            background-clip: padding-box;
            color: #fff;
            text-align: left;
            padding: 12px 14px;
            white-space: nowrap;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            box-shadow: 0 2px 6px rgba(0,0,0,.15);
            border-bottom: 1px solid #0f3d32;
        }
        td {
            padding: 11px 14px;
            border-bottom: 1px solid #dfe9e4;
            vertical-align: top;
            max-width: 280px;
            word-break: break-word;
            color: #1a2e26;
        }
        tr:nth-child(even) td { background: var(--td-alt); }
        tr:nth-child(odd) td { background: #fff; }
        tr[data-hidden="1"] { display: none; }
        .muted-box {
            background: #fff8e6;
            border: 1px solid #f0e0b2;
            border-radius: 12px;
            padding: 16px;
            color: #5c4d20;
        }
        .dest-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .dest-tags span {
            font-size: 12px;
            font-weight: 600;
            padding: 6px 11px;
            border-radius: 8px;
            background: #e4f0eb;
            border: 1px solid #bfd9cc;
            cursor: pointer;
            color: var(--text);
        }
        .dest-tags span:hover { background: #d0e8df; border-color: var(--green-light); }

        /* must stay sticky — do not set position:relative (it overrides sticky) */
        th.th-sort {
            cursor: pointer;
            user-select: none;
            padding-right: 22px;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        th.th-sort:hover { filter: brightness(1.05); }
        th.th-sort::after {
            content: "↕";
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.45;
            font-size: 11px;
        }
        th.th-sort.sorted-asc::after { content: "↑"; opacity: 0.95; }
        th.th-sort.sorted-desc::after { content: "↓"; opacity: 0.95; }

        .ab-badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            line-height: 1.3;
            max-width: 100%;
            white-space: normal;
            word-break: break-word;
        }
        .ab-badge-muted { background: #e8ece9; color: #3d4a44; }
        .ab-badge-info { background: #d5e8f7; color: #0b4f7a; }
        .ab-badge-success { background: #d1f0e0; color: #145a3a; }
        .ab-badge-danger { background: #fde2e0; color: #8a1c15; }
        .ab-badge-warn { background: #fff1d6; color: #6a4a00; }

        .ab-app-col { white-space: nowrap; width: 1%; }
        .ab-row-ord {
            font-weight: 800;
            color: var(--green);
            font-variant-numeric: tabular-nums;
        }

        /* Sticky right "Action" column (fixed during horizontal scroll) */
        th.ab-action-col,
        td.ab-action-col {
            position: sticky;
            right: 0;
            z-index: 6;
            width: 1%;
            white-space: nowrap;
            box-shadow: -10px 0 18px rgba(15, 31, 26, .08);
        }
        th.ab-action-col {
            z-index: 7;
        }
        tr:nth-child(even) td.ab-action-col { background: var(--td-alt); }
        tr:nth-child(odd) td.ab-action-col { background: #fff; }

        .ab-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            padding: 7px 10px;
            border-radius: 10px;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .ab-btn:hover { border-color: var(--green-light); box-shadow: 0 0 0 3px rgba(30, 92, 74, .12); }
        .ab-btn-danger { border-color: #f0c7c4; background: #fff5f5; color: #8a1c15; }
        .ab-btn-danger:hover { border-color: #e6a9a4; box-shadow: 0 0 0 3px rgba(138, 28, 21, .12); }

        .ab-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 31, 26, .45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 50;
            padding: 18px;
        }
        .ab-modal {
            width: min(520px, 100%);
            background: #fff;
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,.08);
            box-shadow: 0 18px 60px rgba(0,0,0,.25);
            overflow: hidden;
        }
        .ab-modal .hd {
            padding: 14px 16px;
            background: linear-gradient(180deg, #236b56 0%, var(--green) 100%);
            color: #fff;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ab-modal .bd { padding: 16px; }
        .ab-modal .ft {
            padding: 14px 16px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #e7efea;
            background: #fbfdfc;
        }
        .ab-modal .x {
            border: none;
            background: rgba(255,255,255,.18);
            color: #fff;
            border-radius: 10px;
            padding: 6px 10px;
            cursor: pointer;
            font-weight: 900;
        }
        .ab-modal .x:hover { background: rgba(255,255,255,.28); }
        .ab-help {
            font-size: 13px;
            color: var(--muted);
            margin-top: 6px;
        }
        .ab-toast {
            position: fixed;
            left: 18px;
            bottom: 18px;
            z-index: 60;
            display: none;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 14px;
            box-shadow: 0 10px 30px rgba(15, 31, 26, .16);
            max-width: min(520px, calc(100vw - 36px));
        }
    </style>
</head>
<body class="<?= !$tableMissing ? 'ab-page' : '' ?>" data-total-records="<?= (int) ($stats['total'] ?? 0) ?>" data-student-records="<?= (int) ($stats['student_records'] ?? 0) ?>">
<div class="wrap<?= !$tableMissing ? ' ab-wrap' : '' ?>">
    <?php if ($tableMissing): ?>
        <h1><i class="bi bi-columns-gap"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="muted-box">
            <strong>Table not found.</strong> Create it by running the ApplyBoard scraper with MySQL sync, or create <code><?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?></code> in database <code><?= htmlspecialchars($dbname ?? 'visawgnz_scholarsyncglobal', ENT_QUOTES, 'UTF-8') ?></code>.
        </div>
    <?php else: ?>

    <div class="ab-sticky">
    <h1><i class="bi bi-columns-gap"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="kpis">
        <div class="kpi">
            <div class="lab">Applications (rows)</div>
            <div class="val"><?= (int) ($stats['total'] ?? 0) ?></div>
        </div>
        <div class="kpi">
            <div class="lab">Student records (DB)</div>
            <div class="val"><?= (int) ($stats['student_records'] ?? 0) ?></div>
        </div>
        <?php if ($destCol && $stats['by_dest']): ?>
        <div class="kpi" style="grid-column: span 2;">
            <div class="lab">Top destinations</div>
            <div class="dest-tags" id="destQuick">
                <?php foreach ($stats['by_dest'] as $d => $c): ?>
                    <span data-dest="<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?>" title="Click to filter"><?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?> (<?= (int) $c ?>)</span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="filter-card">
        <div class="filter-grid">
            <div class="filter-field" style="grid-column: span 2; min-width: 260px;">
                <label for="searchInput">Search</label>
                <input type="search" id="searchInput" placeholder="Name, student ID, email, university, program…" autocomplete="off">
            </div>
            <?php if ($destCol): ?>
            <div class="filter-field">
                <label for="destFilter">Destination</label>
                <select id="destFilter">
                    <option value="">All destinations</option>
                    <option value="__EMPTY__">Without Applications</option>
                    <?php
                    $uniqDest = [];
                    foreach ($rows as $r) {
                        $d = trim((string) ($r[$destCol] ?? ''));
                        if ($d !== '') {
                            $uniqDest[$d] = true;
                        }
                    }
                    ksort($uniqDest);
                    foreach (array_keys($uniqDest) as $d):
                    ?>
                        <option value="<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if ($regCol || $updCol): ?>
            <?php
            $yearPickMin = 2015;
            $yearPickMax = max((int) date('Y') + 4, 2030);
            ?>
            <div class="filter-field">
                <label for="yearFilter">Registration year</label>
                <select id="yearFilter" title="Pick calendar year (registration / updated date)">
                    <option value="">All years</option>
                    <?php for ($yy = $yearPickMax; $yy >= $yearPickMin; $yy--): ?>
                        <option value="<?= (string) $yy ?>"><?= (string) $yy ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if ($statusCol): ?>
            <div class="filter-field">
                <label for="statusFilter">Status</label>
                <select id="statusFilter">
                    <option value="">All statuses</option>
                    <option value="__STATUS_EMPTY__">No status</option>
                    <?php foreach ($statusTokensSorted as $st): ?>
                        <?php
                        $stDisp = $st;
                        if (strlen($st) > 72) {
                            $stDisp = substr($st, 0, 69) . '…';
                        }
                        ?>
                        <option value="<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($stDisp, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if ($univCol && $uniqUniv): ?>
            <div class="filter-field">
                <label for="univFilter">Target university</label>
                <select id="univFilter">
                    <option value="">All universities</option>
                    <?php foreach (array_keys($uniqUniv) as $uu): ?>
                        <option value="<?= htmlspecialchars($uu, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(strlen($uu) > 80 ? (substr($uu, 0, 77) . '…') : $uu, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="filter-meta">
            <span class="pill" id="visibleCount"><?= (int) $stats['total'] ?> rows shown</span>
            <button type="button" id="btnResetFilters" class="pill" style="cursor:pointer;border:none;font:inherit;">Reset filters</button>
        </div>
        <div id="filterEmptyHint" class="muted-box" style="display:none;margin-top:12px;">
            <strong>No rows match these filters.</strong> Try <strong>Reset filters</strong>, or clear the university / status / destination pickers. Each row is one application.
        </div>
    </div>

    <?php if (!empty($_GET['debug'])): ?>
    <div class="muted-box" style="margin-bottom:16px;background:#eef6ff;border-color:#b8d4f0;color:#153450;">
        <strong>Debug mode</strong> — remove <code>?debug=1</code> from the URL when finished.
        <ul style="margin:8px 0 0 18px;font-size:13px;">
            <li>Application rows (exploded): <code><?= (int) ($stats['total'] ?? 0) ?></code></li>
            <li>Student records (DB): <code><?= (int) ($stats['student_records'] ?? 0) ?></code></li>
            <li>Display columns: <code><?= count($displayCols) ?></code> — <?= htmlspecialchars(implode(', ', array_slice($displayCols, 0, 12)), ENT_QUOTES, 'UTF-8') ?><?= count($displayCols) > 12 ? '…' : '' ?></li>
            <li><code>Destination/Country</code> column: <code><?= htmlspecialchars((string) ($destCol ?? '—'), ENT_QUOTES, 'UTF-8') ?></code></li>
            <li><code>Registration Date</code> column: <code><?= htmlspecialchars((string) ($regCol ?? '—'), ENT_QUOTES, 'UTF-8') ?></code></li>
            <li><code>updated_at</code> column: <code><?= htmlspecialchars((string) ($updCol ?? '—'), ENT_QUOTES, 'UTF-8') ?></code></li>
            <li><code>Status App</code> column: <code><?= htmlspecialchars((string) ($statusCol ?? '—'), ENT_QUOTES, 'UTF-8') ?></code></li>
            <li>Sample destinations (raw): <code><?php
                $sd = [];
                foreach (array_slice($rows, 0, 5) as $sr) {
                    if ($destCol) {
                        $sd[] = trim((string) ($sr[$destCol] ?? ''));
                    }
                }
                echo htmlspecialchars(implode(' | ', $sd), ENT_QUOTES, 'UTF-8');
            ?></code></li>
        </ul>
    </div>
    <?php endif; ?>

    </div><!-- .ab-sticky: KPIs + filters + optional debug -->

    <div class="ab-table-dock">
    <div class="table-scroll" id="tableScroll">
        <table class="ab-data-table">
            <thead>
                <tr>
                    <?php foreach ($displayCols as $ci => $col): ?>
                        <th
                            class="<?= $col === 'Action' ? 'ab-action-col' : 'th-sort' ?>"
                            data-col-index="<?= (int) $ci ?>"
                            scope="col"
                        ><?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody id="tbody">
                <?php
                $listOrd = 0;
                foreach ($rows as $r):
                    $listOrd++;
                ?>
                    <?php
                    $destVal = $destCol ? trim((string) ($r[$destCol] ?? '')) : '';
                    $hay = strtolower(implode(' ', array_map(
                        static fn (string $col): string => (string) ($r[$col] ?? ''),
                        array_keys($fields)
                    )));
                    $regYear = applyboard_row_year_for_filter($r, $regCol, $updCol);
                    $statusVal = ($statusCol !== null) ? trim((string) ($r[$statusCol] ?? '')) : '';
                    $univVal = $univCol ? trim((string) ($r[$univCol] ?? '')) : '';
                    $studentId = $studentIdCol ? trim((string) ($r[$studentIdCol] ?? '')) : '';
                    $appIndex = (int) ($r['__app_index'] ?? 1);
                    $appOf = (int) ($r['__app_of'] ?? 1);
                    ?>
                    <tr
                        data-search="<?= htmlspecialchars($hay, ENT_QUOTES, 'UTF-8') ?>"
                        <?= $destCol ? 'data-dest="' . htmlspecialchars($destVal, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                        <?= $univCol ? 'data-univ="' . htmlspecialchars($univVal, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                        <?= ($regCol || $updCol) ? 'data-reg-year="' . htmlspecialchars($regYear, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                        <?= $statusCol ? 'data-status="' . htmlspecialchars($statusVal, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                        <?= $studentId !== '' ? 'data-student-id="' . htmlspecialchars($studentId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                        data-app-index="<?= (int) $appIndex ?>"
                        data-app-of="<?= (int) $appOf ?>"
                    >
                        <?php foreach ($displayCols as $col): ?>
                            <?php if ($col === 'App #'): ?>
                                <td class="ab-app-col"><span class="ab-row-ord"><?= (int) $listOrd ?></span></td>
                            <?php elseif ($col === 'Action'): ?>
                                <td class="ab-action-col">
                                    <button
                                        type="button"
                                        class="ab-btn ab-btn-edit"
                                        <?= $studentId === '' ? 'disabled title="Missing Student ID column — cannot edit"' : '' ?>
                                    ><i class="bi bi-pencil"></i> Edit</button>
                                    <button
                                        type="button"
                                        class="ab-btn ab-btn-danger ab-btn-del"
                                        <?= $studentId === '' ? 'disabled title="Missing Student ID column — cannot delete"' : '' ?>
                                    ><i class="bi bi-trash"></i> Delete</button>
                                </td>
                            <?php elseif ($statusCol && $col === $statusCol): ?>
                                <td><?= applyboard_status_badge_html((string) ($r[$col] ?? '')) ?></td>
                            <?php else: ?>
                                <td<?= ($destCol && $col === $destCol) ? ' data-col-dest="1"' : '' ?>><?= htmlspecialchars((string) ($r[$col] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    </div><!-- .ab-table-dock -->
    <?php endif; ?>
</div>

<?php if (!$tableMissing): ?>
<?php
// Destination edit list (fixed 5 as per screenshot)
$destEditOpts = ['Canada', 'United States', 'United Kingdom', 'Germany', 'Ireland'];
?>
<div class="ab-modal-backdrop" id="abModal">
    <div class="ab-modal" role="dialog" aria-modal="true" aria-labelledby="abModalTitle">
        <div class="hd">
            <div id="abModalTitle">Edit application</div>
            <button type="button" class="x" id="abModalClose" aria-label="Close">✕</button>
        </div>
        <div class="bd">
            <div class="filter-field">
                <label for="abEditDest">Destination country</label>
                <select id="abEditDest">
                    <?php foreach ($destEditOpts as $d): ?>
                        <option value="<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="ab-help">Only the 5 existing destination countries (from “Top destinations”).</div>
            </div>
        </div>
        <div class="ft">
            <button type="button" class="ab-btn" id="abEditCancel">Cancel</button>
            <button type="button" class="ab-btn" id="abEditSave"><i class="bi bi-check2"></i> Save</button>
        </div>
    </div>
</div>
<div class="ab-toast" id="abToast"></div>
<?php endif; ?>

<script>
(function () {
    var tbody = document.getElementById('tbody');
    var searchInput = document.getElementById('searchInput');
    var destFilter = document.getElementById('destFilter');
    var yearFilter = document.getElementById('yearFilter');
    var statusFilter = document.getElementById('statusFilter');
    var univFilter = document.getElementById('univFilter');
    var visibleCount = document.getElementById('visibleCount');
    var filterEmptyHint = document.getElementById('filterEmptyHint');
    var btnReset = document.getElementById('btnResetFilters');
    if (!tbody || !searchInput) return;

    var totalInDb = parseInt(document.body.getAttribute('data-total-records') || '0', 10) || 0;

    /** Legacy: bundled destinations — still match one segment */
    function destMatchesRow(rowDest, selected) {
        if (!selected) return true;
        var d = (rowDest || '').trim();
        if (selected === '__EMPTY__') return d === '';
        if (d === selected) return true;
        var parts = d.split(';');
        for (var i = 0; i < parts.length; i++) {
            if (parts[i].trim() === selected) return true;
        }
        return false;
    }

    /** One status per application row */
    function statusMatchesRow(rawStatus, selected) {
        if (!selected) return true;
        var r = (rawStatus || '').trim();
        if (selected === '__STATUS_EMPTY__') return r === '';
        return r === selected;
    }

    function univMatchesRow(rowUniv, selected) {
        if (!selected) return true;
        return ((rowUniv || '').trim()) === selected;
    }

    /** Sequential # in App column for visible rows only (1, 2, 3…) after filter/sort */
    function renumberRowOrd() {
        var n = 0;
        tbody.querySelectorAll('tr').forEach(function (tr) {
            if (tr.getAttribute('data-hidden') === '1') return;
            n++;
            var el = tr.querySelector('td.ab-app-col .ab-row-ord');
            if (el) el.textContent = String(n);
        });
    }

    function applyFilters() {
        var q = (searchInput.value || '').toLowerCase().trim();
        var dest = destFilter ? (destFilter.value || '').trim() : '';
        var year = yearFilter ? String(yearFilter.value || '').trim() : '';
        if (year && (isNaN(parseInt(year, 10)) || parseInt(year, 10) < 2000 || parseInt(year, 10) > 2100)) {
            year = '';
        }
        var st = statusFilter ? (statusFilter.value || '').trim() : '';
        var uv = univFilter ? (univFilter.value || '').trim() : '';
        var n = 0;
        tbody.querySelectorAll('tr').forEach(function (tr) {
            var ok = true;
            if (q && (tr.getAttribute('data-search') || '').indexOf(q) === -1) ok = false;
            if (ok && dest) {
                var d = tr.getAttribute('data-dest') || '';
                if (!destMatchesRow(d, dest)) ok = false;
            }
            if (ok && year) {
                var ry = tr.getAttribute('data-reg-year') || '';
                if (String(ry) !== String(year)) ok = false;
            }
            if (ok && st) {
                var raw = tr.getAttribute('data-status') || '';
                if (!statusMatchesRow(raw, st)) ok = false;
            }
            if (ok && uv) {
                var u = tr.getAttribute('data-univ') || '';
                if (!univMatchesRow(u, uv)) ok = false;
            }
            tr.setAttribute('data-hidden', ok ? '0' : '1');
            if (ok) n++;
        });
        if (visibleCount) visibleCount.textContent = n + ' application rows shown';
        if (filterEmptyHint) {
            filterEmptyHint.style.display = (n === 0 && totalInDb > 0) ? 'block' : 'none';
        }
        renumberRowOrd();
    }

    function clearSortClasses() {
        document.querySelectorAll('th.th-sort').forEach(function (th) {
            th.classList.remove('sorted-asc', 'sorted-desc');
        });
    }

    function sortColumn(colIndex, dir) {
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        rows.sort(function (a, b) {
            var ta = '';
            var tb = '';
            var ca = a.children[colIndex];
            var cb = b.children[colIndex];
            if (ca) ta = (ca.innerText || ca.textContent || '').trim();
            if (cb) tb = (cb.innerText || cb.textContent || '').trim();
            if (colIndex === 0) {
                var ma = /^(\d+)/.exec(ta);
                var mb = /^(\d+)/.exec(tb);
                if (ma && mb) {
                    return dir * (parseInt(ma[1], 10) - parseInt(mb[1], 10));
                }
            }
            return dir * String(ta).localeCompare(String(tb), undefined, { numeric: true, sensitivity: 'base' });
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
        renumberRowOrd();
    }

    searchInput.addEventListener('input', applyFilters);
    if (destFilter) destFilter.addEventListener('change', applyFilters);
    if (yearFilter) {
        yearFilter.addEventListener('change', applyFilters);
    }
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);
    if (univFilter) univFilter.addEventListener('change', applyFilters);

    document.querySelectorAll('th.th-sort').forEach(function (th) {
        th.addEventListener('click', function () {
            var idx = parseInt(th.getAttribute('data-col-index') || '-1', 10);
            if (isNaN(idx) || idx < 0) return;
            var asc = !th.classList.contains('sorted-asc');
            clearSortClasses();
            th.classList.add(asc ? 'sorted-asc' : 'sorted-desc');
            sortColumn(idx, asc ? 1 : -1);
        });
    });

    // --- Sticky Action column: Edit + Delete ---
    var modal = document.getElementById('abModal');
    var modalClose = document.getElementById('abModalClose');
    var btnCancel = document.getElementById('abEditCancel');
    var btnSave = document.getElementById('abEditSave');
    var selDest = document.getElementById('abEditDest');
    var toast = document.getElementById('abToast');
    var currentRow = null;

    function showToast(msg, isErr) {
        if (!toast) return;
        toast.textContent = msg;
        toast.style.display = 'block';
        toast.style.borderColor = isErr ? '#f0c7c4' : '#c5d4cd';
        toast.style.background = isErr ? '#fff5f5' : '#fff';
        setTimeout(function () { toast.style.display = 'none'; }, 3200);
    }

    function openModalForRow(tr) {
        if (!modal || !selDest) return;
        currentRow = tr;
        // preselect current destination if it exists in the 5 options
        var cur = (tr.getAttribute('data-dest') || '').trim();
        if (cur) selDest.value = cur;
        modal.style.display = 'flex';
    }

    function closeModal() {
        if (!modal) return;
        modal.style.display = 'none';
        currentRow = null;
    }

    function rowStudentId(tr) {
        return (tr.getAttribute('data-student-id') || '').trim();
    }
    function rowAppIndex(tr) {
        return parseInt(tr.getAttribute('data-app-index') || '1', 10) || 1;
    }

    function updateDestinationInRowDom(tr, newDest) {
        tr.setAttribute('data-dest', newDest);
        // update the visible td for destination column
        var td = tr.querySelector('td[data-col-dest="1"]');
        if (td) td.textContent = newDest;
    }

    async function postJson(url, payload) {
        var res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        var txt = await res.text();
        var data = null;
        try { data = JSON.parse(txt); } catch (e) { data = { ok: false, error: txt || 'Bad response' }; }
        if (!res.ok) {
            throw new Error((data && data.error) ? data.error : ('HTTP ' + res.status));
        }
        return data;
    }

    function onTbodyClick(e) {
        var t = e.target;
        if (!t) return;
        var btn = t.closest ? t.closest('button') : null;
        if (!btn) return;
        var tr = btn.closest ? btn.closest('tr') : null;
        if (!tr) return;

        if (btn.classList.contains('ab-btn-edit')) {
            openModalForRow(tr);
            return;
        }
        if (btn.classList.contains('ab-btn-del')) {
            var sid = rowStudentId(tr);
            if (!sid) return;
            var idx = rowAppIndex(tr);
            if (!confirm('Delete this application row? (Student ID: ' + sid + ', App #' + idx + ')')) return;

            postJson('applyboard_application_api.php', {
                action: 'delete_application',
                student_id: sid,
                app_index: idx
            }).then(function (r) {
                if (!r || !r.ok) throw new Error((r && r.error) ? r.error : 'Delete failed');
                tr.remove();
                applyFilters();
                showToast('Deleted.');
                recomputeTopDestinationsFromDom();
            }).catch(function (err) {
                showToast('Delete failed: ' + (err && err.message ? err.message : String(err)), true);
            });
        }
    }

    if (tbody) tbody.addEventListener('click', onTbodyClick);
    if (modalClose) modalClose.addEventListener('click', closeModal);
    if (btnCancel) btnCancel.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    if (btnSave) btnSave.addEventListener('click', function () {
        if (!currentRow || !selDest) return;
        var sid = rowStudentId(currentRow);
        if (!sid) return;
        var idx = rowAppIndex(currentRow);
        var newDest = (selDest.value || '').trim();
        if (!newDest) return;

        btnSave.disabled = true;
        postJson('applyboard_application_api.php', {
            action: 'update_destination',
            student_id: sid,
            app_index: idx,
            destination_country: newDest
        }).then(function (r) {
            if (!r || !r.ok) throw new Error((r && r.error) ? r.error : 'Update failed');
            updateDestinationInRowDom(currentRow, newDest);
            applyFilters();
            showToast('Saved.');
            closeModal();
            recomputeTopDestinationsFromDom();
        }).catch(function (err) {
            showToast('Save failed: ' + (err && err.message ? err.message : String(err)), true);
        }).finally(function () {
            btnSave.disabled = false;
        });
    });

    if (btnReset) {
        btnReset.addEventListener('click', function () {
            searchInput.value = '';
            if (destFilter) destFilter.value = '';
            if (yearFilter) yearFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            if (univFilter) univFilter.value = '';
            clearSortClasses();
            applyFilters();
        });
    }

    var dq = document.getElementById('destQuick');
    if (dq && destFilter) dq.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.getAttribute) return;
        var d = t.getAttribute('data-dest');
        if (d === null || d === undefined) return;
        if (d === 'Without Applications') {
            destFilter.value = '__EMPTY__';
        } else {
            destFilter.value = d;
        }
        applyFilters();
    });

    function recomputeTopDestinationsFromDom() {
        var dqEl = document.getElementById('destQuick');
        if (!dqEl) return;
        var counts = {};
        tbody.querySelectorAll('tr').forEach(function (tr) {
            var d = (tr.getAttribute('data-dest') || '').trim();
            if (!d) d = 'Without Applications';
            var low = d.toLowerCase();
            if (low === 'malaysia' || low === 'malasia') return;
            counts[d] = (counts[d] || 0) + 1;
        });
        // sort desc by count
        var items = Object.keys(counts).map(function (k) { return [k, counts[k]]; });
        items.sort(function (a, b) { return (b[1] - a[1]) || String(a[0]).localeCompare(String(b[0])); });
        items = items.slice(0, 12);
        dqEl.innerHTML = items.map(function (it) {
            var name = it[0];
            var c = it[1];
            var safeName = String(name).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
            return '<span data-dest="' + safeName + '" title="Click to filter">' + safeName + ' (' + c + ')</span>';
        }).join('');
    }

    applyFilters();
    recomputeTopDestinationsFromDom();
})();
</script>
</body>
</html>
