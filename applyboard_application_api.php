<?php
declare(strict_types=1);

session_start();
if (empty($_SESSION['id']) && empty($_SESSION['admin_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/db.php';

$table = 'applyboard_students';

/** @return array<string, bool> */
function ab_fields(mysqli $conn, string $table): array
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
function ab_pick_col(array $fields, array $candidates): ?string
{
    foreach ($candidates as $c) {
        if (!empty($fields[$c])) {
            return $c;
        }
    }
    return null;
}

/**
 * @param array<string, bool> $fields
 * @param list<string> $substrings
 */
function ab_col_by_substrings(array $fields, array $substrings): ?string
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

/** @return list<string> */
function ab_split_bundle(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    $t = ltrim($raw);
    if (($t[0] ?? '') === '[' || ($t[0] ?? '') === '{') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $out = [];
            $i = 0;
            foreach ($decoded as $k => $v) {
                if ($k !== $i++) {
                    return [$raw];
                }
                if (is_scalar($v)) {
                    $out[] = trim((string) $v);
                }
            }
            return $out !== [] ? $out : [];
        }
    }
    $parts = preg_split('/\s*;\s*/', $raw);
    $parts = $parts ?: [];
    return array_values(array_map('trim', $parts));
}

function ab_join_bundle(array $parts, string $originalRaw): string
{
    $t = ltrim($originalRaw);
    if (($t[0] ?? '') === '[') {
        // Preserve JSON array format only when original was a JSON array.
        return json_encode(array_values($parts), JSON_UNESCAPED_UNICODE);
    }
    return implode('; ', array_values($parts));
}

function json_out(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    json_out(['ok' => false, 'error' => 'Invalid JSON'], 400);
    exit;
}

$action = (string) ($data['action'] ?? '');
$studentId = trim((string) ($data['student_id'] ?? ''));
$appIndex = (int) ($data['app_index'] ?? 0);
if ($studentId === '' || $appIndex < 1) {
    json_out(['ok' => false, 'error' => 'Missing student_id or app_index'], 400);
    exit;
}

$fields = ab_fields($conn, $table);
if ($fields === []) {
    json_out(['ok' => false, 'error' => 'Table not found'], 404);
    exit;
}

$idCol = ab_pick_col($fields, ['Student ID', 'student_id'])
    ?: ab_col_by_substrings($fields, ['student id', 'student_id', 'student']);
if (!$idCol) {
    json_out(['ok' => false, 'error' => 'Student ID column not found'], 500);
    exit;
}

$destCol = ab_pick_col($fields, ['Destination/Country', 'destination_country'])
    ?: ab_col_by_substrings($fields, ['destination', 'country']);

$bundleCols = [];
foreach ([
    ab_pick_col($fields, ['Target University', 'target_university']),
    ab_pick_col($fields, ['Target Program', 'target_program']),
    ab_pick_col($fields, ['Target Intake', 'target_intake']),
    ab_pick_col($fields, ['Status App', 'status_app']),
    $destCol,
] as $c) {
    if ($c && !in_array($c, $bundleCols, true)) {
        $bundleCols[] = $c;
    }
}

// Load the row
$escIdCol = '`' . str_replace('`', '``', $idCol) . '`';
$sql = "SELECT * FROM `$table` WHERE $escIdCol = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    json_out(['ok' => false, 'error' => 'DB prepare failed'], 500);
    exit;
}
$stmt->bind_param('s', $studentId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    json_out(['ok' => false, 'error' => 'Student not found'], 404);
    exit;
}

if ($action === 'update_destination') {
    if (!$destCol) {
        json_out(['ok' => false, 'error' => 'Destination column not found'], 500);
        exit;
    }
    $newDest = trim((string) ($data['destination_country'] ?? ''));
    if ($newDest === '') {
        json_out(['ok' => false, 'error' => 'Missing destination_country'], 400);
        exit;
    }

    $rawDest = (string) ($row[$destCol] ?? '');
    $parts = ab_split_bundle($rawDest);
    $idx0 = $appIndex - 1;
    if ($idx0 < 0) {
        json_out(['ok' => false, 'error' => 'Invalid app_index'], 400);
        exit;
    }
    while (count($parts) <= $idx0) {
        $parts[] = '';
    }
    $parts[$idx0] = $newDest;
    $updated = ab_join_bundle($parts, $rawDest);

    $escDestCol = '`' . str_replace('`', '``', $destCol) . '`';
    $sql = "UPDATE `$table` SET $escDestCol = ? WHERE $escIdCol = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_out(['ok' => false, 'error' => 'DB prepare failed'], 500);
        exit;
    }
    $stmt->bind_param('ss', $updated, $studentId);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        json_out(['ok' => false, 'error' => 'DB update failed'], 500);
        exit;
    }
    json_out(['ok' => true]);
    exit;
}

if ($action === 'delete_application') {
    if ($bundleCols === []) {
        json_out(['ok' => false, 'error' => 'No bundled columns found to delete from'], 500);
        exit;
    }
    $idx0 = $appIndex - 1;
    if ($idx0 < 0) {
        json_out(['ok' => false, 'error' => 'Invalid app_index'], 400);
        exit;
    }

    $updates = [];
    $params = [];
    $types = '';

    foreach ($bundleCols as $col) {
        $rawVal = (string) ($row[$col] ?? '');
        $parts = ab_split_bundle($rawVal);
        if ($parts === []) {
            // Nothing to delete in this column.
            continue;
        }
        if ($idx0 >= count($parts)) {
            // If the student has fewer apps than requested index, treat as no-op.
            continue;
        }
        array_splice($parts, $idx0, 1);
        $newVal = ab_join_bundle($parts, $rawVal);
        $updates[$col] = $newVal;
    }

    if ($updates === []) {
        json_out(['ok' => true]);
        exit;
    }

    $setClauses = [];
    foreach ($updates as $col => $val) {
        $esc = '`' . str_replace('`', '``', $col) . '`';
        $setClauses[] = "$esc = ?";
        $params[] = $val;
        $types .= 's';
    }
    $params[] = $studentId;
    $types .= 's';

    $sql = "UPDATE `$table` SET " . implode(', ', $setClauses) . " WHERE $escIdCol = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_out(['ok' => false, 'error' => 'DB prepare failed'], 500);
        exit;
    }
    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        json_out(['ok' => false, 'error' => 'DB delete-update failed'], 500);
        exit;
    }
    json_out(['ok' => true]);
    exit;
}

json_out(['ok' => false, 'error' => 'Unknown action'], 400);

