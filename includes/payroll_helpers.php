<?php
/**
 * Shared formatting and attendance loading for payroll views.
 */
declare(strict_types=1);

/**
 * @return array<string, true> Field name => true
 */
function pcvc_attendance_table_columns(mysqli $conn): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $r = $conn->query('SHOW COLUMNS FROM `attendance`');
    if ($r instanceof mysqli_result) {
        while ($row = $r->fetch_assoc()) {
            if (isset($row['Field'])) {
                $cache[(string) $row['Field']] = true;
            }
        }
        $r->free();
    }

    return $cache;
}

/**
 * Derive billable minutes from an attendance row when total_work_minutes was not saved.
 */
function pcvc_attendance_derived_work_minutes(array $row): int
{
    $stored = (int) ($row['total_work_minutes'] ?? 0);
    if ($stored > 0) {
        return $stored;
    }

    $checkIn  = trim((string) ($row['check_in_time'] ?? ''));
    $checkOut = trim((string) ($row['check_out_time'] ?? ''));
    if ($checkIn === '' || $checkOut === '') {
        return 0;
    }

    $inTs  = strtotime($checkIn);
    $outTs = strtotime($checkOut);
    if ($inTs === false || $outTs === false || $outTs <= $inTs) {
        return 0;
    }

    $break = (int) ($row['break_duration_minutes'] ?? 0);

    return max(0, (int) floor(($outTs - $inTs) / 60) - $break);
}

/**
 * SQL expression for billable minutes (stored value, else check-in/out delta minus break).
 */
function pcvc_sql_attendance_work_minutes_expr(): string
{
    return "CASE
        WHEN COALESCE(`total_work_minutes`, 0) > 0 THEN COALESCE(`total_work_minutes`, 0)
        WHEN `check_in_time` IS NOT NULL AND `check_out_time` IS NOT NULL
          THEN GREATEST(
            0,
            TIMESTAMPDIFF(MINUTE, `check_in_time`, `check_out_time`)
              - COALESCE(`break_duration_minutes`, 0)
          )
        ELSE 0
      END";
}

/**
 * Load one row per (admin_id, date) with optional clock times (dedupes duplicate rows).
 * Prefers checkout-stored pay (daily_salary_rwf / total_payment_rwf) when present so payroll
 * matches the same rules as attendance (e.g. full-day rules), else falls back to rate × minutes.
 *
 * @return array<int, array<string, array{minutes:int, stored_daily_pay:float, check_in:?string, check_out:?string, break:int}>>
 */
function pcvc_payroll_load_attendance_by_admin(
    mysqli $conn,
    string $startMonth,
    string $endMonth
): array {
    $cols = pcvc_attendance_table_columns($conn);
    $hasBreak = isset($cols['break_duration_minutes']);
    $hasIn    = isset($cols['check_in_time']);
    $hasOut   = isset($cols['check_out_time']);
    $hasDaily = isset($cols['daily_salary_rwf']);
    $hasTotal = isset($cols['total_payment_rwf']);

    $breakExpr = $hasBreak ? 'SUM(COALESCE(`break_duration_minutes`,0))' : '0';
    $inExpr    = $hasIn ? 'MIN(`check_in_time`)' : 'NULL';
    $outExpr   = $hasOut ? 'MAX(`check_out_time`)' : 'NULL';

    if ($hasDaily && $hasTotal) {
        $payExpr = 'SUM(COALESCE(`daily_salary_rwf`, `total_payment_rwf`, 0))';
    } elseif ($hasDaily) {
        $payExpr = 'SUM(COALESCE(`daily_salary_rwf`, 0))';
    } elseif ($hasTotal) {
        $payExpr = 'SUM(COALESCE(`total_payment_rwf`, 0))';
    } else {
        $payExpr = '0';
    }

    $minutesExpr = pcvc_sql_attendance_work_minutes_expr();

    // DATE() so DATETIME columns still match the selected calendar month
    $sql = "SELECT `admin_id`, DATE(`date`) AS `work_date`,
              SUM({$minutesExpr}) AS `total_work_minutes`,
              {$payExpr} AS `stored_daily_pay`,
              {$inExpr} AS `check_in_time`,
              {$outExpr} AS `check_out_time`,
              {$breakExpr} AS `break_duration_minutes`
            FROM `attendance`
            WHERE DATE(`date`) BETWEEN ? AND ?
            GROUP BY `admin_id`, DATE(`date`)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ss', $startMonth, $endMonth);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $aid = (int) $row['admin_id'];
        $d   = (string) $row['work_date'];
        if (!isset($out[$aid])) {
            $out[$aid] = [];
        }
        $storedPay = (float) ($row['stored_daily_pay'] ?? 0);
        $out[$aid][$d] = [
            'minutes' => (int) $row['total_work_minutes'],
            'stored_daily_pay' => $storedPay,
            'check_in' => isset($row['check_in_time']) && $row['check_in_time'] !== null && $row['check_in_time'] !== ''
                ? (string) $row['check_in_time'] : null,
            'check_out' => isset($row['check_out_time']) && $row['check_out_time'] !== null && $row['check_out_time'] !== ''
                ? (string) $row['check_out_time'] : null,
            'break' => (int) ($row['break_duration_minutes'] ?? 0),
        ];
    }
    $stmt->close();

    return $out;
}

function pcvc_payroll_format_hm(int $minutes): string
{
    if ($minutes < 0) {
        $minutes = 0;
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;

    return sprintf('%d:%02d', $h, $m);
}

function pcvc_payroll_format_clock(?string $mysqlDateTime): string
{
    if ($mysqlDateTime === null || $mysqlDateTime === '') {
        return '—';
    }
    $ts = strtotime($mysqlDateTime);

    return $ts ? date('H:i', $ts) : '—';
}

/**
 * Fill gaps between earliest month in data and the through month (default: current month).
 *
 * @param list<string> $monthsFromDb
 * @return list<string>
 */
function pcvc_payroll_month_range(array $monthsFromDb, ?string $throughMonth = null): array
{
    $throughMonth = $throughMonth ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $throughMonth)) {
        $throughMonth = date('Y-m');
    }

    $valid = [];
    foreach ($monthsFromDb as $month) {
        $month = trim((string) $month);
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $valid[$month] = true;
        }
    }

    if ($valid === []) {
        $startMonth = date('Y-01', strtotime($throughMonth . '-01'));
    } else {
        $keys = array_keys($valid);
        sort($keys);
        $startMonth = $keys[0];
    }

    $endMonth = $throughMonth;
    if ($valid !== []) {
        $keys = array_keys($valid);
        sort($keys);
        $dbMax = end($keys);
        if ($dbMax > $endMonth) {
            $endMonth = $dbMax;
        }
    }

    $cursor = strtotime($startMonth . '-01');
    $endTs  = strtotime($endMonth . '-01');
    $range  = [];
    while ($cursor <= $endTs) {
        $range[] = date('Y-m', $cursor);
        $cursor  = strtotime('+1 month', $cursor);
    }

    rsort($range);

    return $range;
}
