<?php
declare(strict_types=1);

/**
 * Track which admin recorded a payment receipt (viewer only — not on print/PDF).
 */

function pcvc_payment_receipts_has_column(mysqli $conn, string $column): bool
{
    $stmt = $conn->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $table = 'payment_receipts';
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (bool) $row;
}

/**
 * Auto-migrate payment_receipts when columns are missing (no manual SQL needed).
 */
function pcvc_ensure_payment_receipt_recorded_by_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    if (!pcvc_payment_receipts_has_column($conn, 'recorded_by')) {
        @$conn->query(
            'ALTER TABLE payment_receipts
             ADD COLUMN recorded_by INT UNSIGNED NULL DEFAULT NULL
             AFTER payment_method'
        );
    }

    if (!pcvc_payment_receipts_has_column($conn, 'recorded_by_name')) {
        @$conn->query(
            'ALTER TABLE payment_receipts
             ADD COLUMN recorded_by_name VARCHAR(120) NULL DEFAULT NULL
             AFTER recorded_by'
        );
    }

    $done = true;
}

/**
 * Dropdown options: admins who recorded at least one active receipt.
 *
 * @return list<array{id: string, label: string}>
 */
function pcvc_receipt_recorded_by_filter_options(mysqli $conn): array
{
    pcvc_ensure_payment_receipt_recorded_by_schema($conn);
    pcvc_payment_receipt_repair_recorded_by($conn);

    $options = [
        ['id' => '', 'label' => 'All — done by'],
    ];

    $sql = "
        SELECT DISTINCT
            pr.recorded_by AS admin_id,
            TRIM(pr.recorded_by_name) AS stored_name
        FROM payment_receipts pr
        WHERE COALESCE(pr.status, 'ACTIVE') <> 'CANCELED'
          AND (
            (pr.recorded_by IS NOT NULL AND pr.recorded_by > 0)
            OR (pr.recorded_by_name IS NOT NULL AND TRIM(pr.recorded_by_name) <> '')
          )
        ORDER BY stored_name ASC, admin_id ASC
    ";

    $res = $conn->query($sql);
    if (!$res) {
        return $options;
    }

    $seen = [];
    while ($row = $res->fetch_assoc()) {
        $adminId = isset($row['admin_id']) ? (int) $row['admin_id'] : 0;
        $label   = pcvc_receipt_recorded_by_display(
            $conn,
            $adminId > 0 ? $adminId : null,
            isset($row['stored_name']) ? (string) $row['stored_name'] : null
        );
        if ($label === '—') {
            continue;
        }

        $key = $adminId > 0 ? 'id:' . $adminId : 'name:' . strtolower($label);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $options[] = [
            'id'    => $adminId > 0 ? (string) $adminId : 'name:' . rawurlencode($label),
            'label' => $label,
        ];
    }
    $res->free();

    $unknownSql = "
        SELECT COUNT(*) AS c
        FROM payment_receipts pr
        WHERE COALESCE(pr.status, 'ACTIVE') <> 'CANCELED'
          AND (pr.recorded_by IS NULL OR pr.recorded_by = 0)
          AND (pr.recorded_by_name IS NULL OR TRIM(pr.recorded_by_name) = '')
    ";
    $unknownRes = $conn->query($unknownSql);
    if ($unknownRes) {
        $unknownCount = (int) ($unknownRes->fetch_assoc()['c'] ?? 0);
        $unknownRes->free();
        if ($unknownCount > 0) {
            $options[] = ['id' => 'unknown', 'label' => 'Unknown / not set'];
        }
    }

    return $options;
}

/**
 * Append SQL fragment for done-by filter. Mutates $params and $types.
 */
function pcvc_receipt_recorded_by_apply_filter(
    string $doneBy,
    string &$sql,
    array &$params,
    string &$types
): void {
    $doneBy = trim($doneBy);
    if ($doneBy === '') {
        return;
    }

    if ($doneBy === 'unknown') {
        $sql .= "
          AND (pr.recorded_by IS NULL OR pr.recorded_by = 0)
          AND (pr.recorded_by_name IS NULL OR TRIM(pr.recorded_by_name) = '')";

        return;
    }

    if (str_starts_with($doneBy, 'name:')) {
        $name = rawurldecode(substr($doneBy, 5));
        if ($name === '') {
            return;
        }
        $sql .= ' AND TRIM(pr.recorded_by_name) = ?';
        $params[] = $name;
        $types   .= 's';

        return;
    }

    $adminId = (int) $doneBy;
    if ($adminId <= 0) {
        return;
    }

    $sql .= ' AND pr.recorded_by = ?';
    $params[] = $adminId;
    $types   .= 'i';
}

/**
 * @return array{id: int, name: string}
 */
function pcvc_receipt_admin_from_session(mysqli $conn): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $adminId = (int) ($_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
    if ($adminId <= 0) {
        return ['id' => 0, 'name' => ''];
    }

    $stmt = $conn->prepare(
        'SELECT full_name, first_name, last_name FROM admins WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        return ['id' => $adminId, 'name' => ''];
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['id' => $adminId, 'name' => ''];
    }

    $name = trim((string) ($row['full_name'] ?? ''));
    if ($name === '') {
        $name = trim(trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? '')));
    }

    return ['id' => $adminId, 'name' => $name];
}

function pcvc_receipt_recorded_by_display(mysqli $conn, ?int $adminId, ?string $storedName): string
{
    $name = trim((string) $storedName);
    if ($name !== '' && !ctype_digit($name)) {
        return $name;
    }

    $adminId = (int) $adminId;
    if ($adminId <= 0 && ctype_digit($name)) {
        $adminId = (int) $name;
    }
    if ($adminId <= 0) {
        return '—';
    }

    $stmt = $conn->prepare('SELECT full_name, first_name, last_name FROM admins WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return '—';
    }
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return '—';
    }

    $resolved = trim((string) ($row['full_name'] ?? ''));
    if ($resolved === '') {
        $resolved = trim(trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? '')));
    }

    return $resolved !== '' ? $resolved : '—';
}

/**
 * One-time data fix: previous inserts swapped bind types so admin IDs were
 * stored in recorded_by_name as digits, and recorded_by was empty / cast.
 * This rewrites those rows so recorded_by holds the ID and the name is resolved.
 */
function pcvc_payment_receipt_repair_recorded_by(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    pcvc_ensure_payment_receipt_recorded_by_schema($conn);

    $res = @$conn->query(
        "SELECT id, recorded_by, recorded_by_name
         FROM payment_receipts
         WHERE recorded_by_name REGEXP '^[0-9]+$'
            OR (recorded_by IS NULL AND recorded_by_name REGEXP '^[0-9]+$')"
    );
    if (!$res) {
        return;
    }

    $update = $conn->prepare(
        'UPDATE payment_receipts
         SET recorded_by = ?, recorded_by_name = ?
         WHERE id = ?'
    );
    if (!$update) {
        $res->free();
        return;
    }

    while ($row = $res->fetch_assoc()) {
        $rowId    = (int) $row['id'];
        $stored   = trim((string) ($row['recorded_by_name'] ?? ''));
        $adminId  = (int) ($row['recorded_by'] ?? 0);

        if ($adminId <= 0 && ctype_digit($stored)) {
            $adminId = (int) $stored;
        }
        if ($adminId <= 0) {
            continue;
        }

        $resolved = pcvc_receipt_recorded_by_display($conn, $adminId, null);
        if ($resolved === '—') {
            $resolvedToSave = null;
        } else {
            $resolvedToSave = $resolved;
        }

        $update->bind_param('isi', $adminId, $resolvedToSave, $rowId);
        $update->execute();
    }
    $update->close();
    $res->free();
}
