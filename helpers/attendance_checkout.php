<?php
declare(strict_types=1);

const PCVC_CHECKOUT_MIN_JOBS_TODAY   = 10;
const PCVC_CHECKOUT_FULL_DAY_HOURS   = 7;
const PCVC_CHECKOUT_FULL_DAY_MINUTES = 480;

/**
 * Ensure job_list.completed_at exists (when a job was marked completed).
 */
function pcvc_ensure_job_list_completed_at(mysqli $conn): void
{
    $cols = $conn->query("SHOW COLUMNS FROM job_list LIKE 'completed_at'");
    if ($cols && $cols->num_rows === 0) {
        @$conn->query(
            'ALTER TABLE job_list ADD COLUMN completed_at DATETIME NULL DEFAULT NULL AFTER status'
        );
    }
    if ($cols) {
        $cols->free();
    }
}

/**
 * Best-effort completion timestamp when legacy rows lack completed_at.
 */
function pcvc_infer_job_completed_at(int $jobId, string $createdAt, string $screenshotPath): string
{
    $path = trim($screenshotPath);
    if ($path !== '' && preg_match('/job_' . $jobId . '_(\d{10,})_/', $path, $m)) {
        $ts = (int) $m[1];
        if ($ts > 0) {
            return date('Y-m-d H:i:s', $ts);
        }
    }

    $created = trim($createdAt);
    if ($created !== '') {
        return $created;
    }

    return date('Y-m-d H:i:s');
}

/**
 * Backfill completed_at for jobs marked completed before the column existed.
 */
function pcvc_backfill_job_list_completed_at(mysqli $conn): void
{
    pcvc_ensure_job_list_completed_at($conn);

    $res = $conn->query("
        SELECT id, created_at, screenshot_path
        FROM job_list
        WHERE status = 'completed' AND completed_at IS NULL
        ORDER BY id DESC
        LIMIT 1000
    ");
    if (!$res) {
        return;
    }

    $upd = $conn->prepare('UPDATE job_list SET completed_at = ? WHERE id = ? AND completed_at IS NULL');
    if (!$upd) {
        $res->free();

        return;
    }

    while ($row = $res->fetch_assoc()) {
        $jobId = (int) ($row['id'] ?? 0);
        if ($jobId <= 0) {
            continue;
        }
        $completedAt = pcvc_infer_job_completed_at(
            $jobId,
            (string) ($row['created_at'] ?? ''),
            (string) ($row['screenshot_path'] ?? '')
        );
        $upd->bind_param('si', $completedAt, $jobId);
        $upd->execute();
    }

    $upd->close();
    $res->free();
}

/**
 * Mark a job completed and always stamp completed_at.
 */
function pcvc_job_list_mark_completed(mysqli $conn, int $jobId, ?string $completedAt = null): void
{
    pcvc_ensure_job_list_completed_at($conn);
    $stamp = $completedAt ?: date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE job_list SET status = 'completed', completed_at = ? WHERE id = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('si', $stamp, $jobId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Jobs completed on a specific calendar day for one admin (not previous dates).
 */
function pcvc_count_jobs_completed_on_date(mysqli $conn, int $admin_id, string $date): int
{
    pcvc_ensure_job_list_completed_at($conn);
    pcvc_backfill_job_list_completed_at($conn);

    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM job_list
        WHERE admin_id = ?
          AND status = 'completed'
          AND completed_at IS NOT NULL
          AND DATE(completed_at) = ?
    ");
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('is', $admin_id, $date);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return (int) $count;
}

/**
 * Validate checkout: requires at least 10 jobs completed today.
 *
 * @return array{
 *   ok: bool,
 *   message: string,
 *   jobs_completed: int,
 *   elapsed_minutes: int,
 *   salary_eligible: bool
 * }
 */
function pcvc_validate_attendance_checkout(
    mysqli $conn,
    int $admin_id,
    string $check_in_time,
    string $now,
    string $today
): array {
    $jobsCompleted = pcvc_count_jobs_completed_on_date($conn, $admin_id, $today);
    $elapsedMinutes = max(0, (int) ceil((strtotime($now) - strtotime($check_in_time)) / 60));
    $salaryEligible = $jobsCompleted >= PCVC_CHECKOUT_MIN_JOBS_TODAY;

    if (!$salaryEligible) {
        return [
            'ok'              => false,
            'message'         => sprintf(
                'Complete at least %d jobs today before checking out. You have completed %d of %d.',
                PCVC_CHECKOUT_MIN_JOBS_TODAY,
                $jobsCompleted,
                PCVC_CHECKOUT_MIN_JOBS_TODAY
            ),
            'jobs_completed'  => $jobsCompleted,
            'elapsed_minutes' => $elapsedMinutes,
            'salary_eligible' => false,
        ];
    }

    $messages = [];
    $fullDayThreshold = PCVC_CHECKOUT_FULL_DAY_HOURS * 60;
    if ($elapsedMinutes >= $fullDayThreshold) {
        $messages[] = sprintf(
            '%d+ hours worked. Salary will be calculated for %d hours.',
            PCVC_CHECKOUT_FULL_DAY_HOURS,
            (int) (PCVC_CHECKOUT_FULL_DAY_MINUTES / 60)
        );
    }

    return [
        'ok'              => true,
        'message'         => implode(' ', $messages),
        'jobs_completed'  => $jobsCompleted,
        'elapsed_minutes' => $elapsedMinutes,
        'salary_eligible' => true,
    ];
}

/**
 * Checkout eligibility snapshot for the attendance UI.
 *
 * @return array<string, mixed>
 */
function pcvc_attendance_checkout_status(
    mysqli $conn,
    int $admin_id,
    string $today,
    string $now
): array {
    $jobsCompleted = pcvc_count_jobs_completed_on_date($conn, $admin_id, $today);

    $stmt = $conn->prepare("
        SELECT check_in_time, check_out_time
        FROM attendance
        WHERE admin_id = ? AND date = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return [
            'checked_in'       => false,
            'check_in_time'    => null,
            'check_out_time'   => null,
            'jobs_completed'   => $jobsCompleted,
            'jobs_required'    => PCVC_CHECKOUT_MIN_JOBS_TODAY,
            'elapsed_minutes'  => 0,
            'full_day_hours'   => PCVC_CHECKOUT_FULL_DAY_HOURS,
            'can_checkout'     => false,
            'block_reason'     => 'You must check in first.',
            'salary_eligible'  => false,
        ];
    }

    $stmt->bind_param('is', $admin_id, $today);
    $stmt->execute();
    $stmt->bind_result($checkIn, $checkOut);
    $hasRow = $stmt->fetch();
    $stmt->close();

    if (!$hasRow || empty($checkIn)) {
        return [
            'checked_in'       => false,
            'check_in_time'    => null,
            'check_out_time'   => null,
            'jobs_completed'   => $jobsCompleted,
            'jobs_required'    => PCVC_CHECKOUT_MIN_JOBS_TODAY,
            'elapsed_minutes'  => 0,
            'full_day_hours'   => PCVC_CHECKOUT_FULL_DAY_HOURS,
            'can_checkout'     => false,
            'block_reason'     => 'You must check in first.',
            'salary_eligible'  => false,
        ];
    }

    $validation = pcvc_validate_attendance_checkout($conn, $admin_id, $checkIn, $now, $today);

    return [
        'checked_in'       => true,
        'check_in_time'    => $checkIn,
        'check_out_time'   => $checkOut ?: null,
        'jobs_completed'   => $jobsCompleted,
        'jobs_required'    => PCVC_CHECKOUT_MIN_JOBS_TODAY,
        'elapsed_minutes'  => $validation['elapsed_minutes'],
        'full_day_hours'   => PCVC_CHECKOUT_FULL_DAY_HOURS,
        'can_checkout'     => $validation['ok'],
        'block_reason'     => $validation['message'],
        'salary_eligible'  => $validation['salary_eligible'],
    ];
}

/**
 * Billable minutes: < 7h actual time, >= 7h counts as 8h (480 min).
 */
function pcvc_attendance_billable_minutes(int $elapsed_minutes): int
{
    $threshold = PCVC_CHECKOUT_FULL_DAY_HOURS * 60;

    if ($elapsed_minutes >= $threshold) {
        return PCVC_CHECKOUT_FULL_DAY_MINUTES;
    }

    return max(0, $elapsed_minutes);
}

/**
 * Compute daily salary at checkout (weekend = 0, >= 7h → 8h pay, else actual time).
 *
 * @return array{
 *   minutes: int,
 *   salary: int,
 *   break_minutes: int,
 *   is_weekend: bool,
 *   salary_per_minute: float,
 *   work_label: string,
 *   salary_label: string
 * }
 */
function pcvc_attendance_compute_checkout_salary(
    mysqli $conn,
    int $admin_id,
    int $elapsed_minutes,
    string $date,
    bool $salary_eligible = true
): array {
    require_once __DIR__ . '/daily_attendance_notify.php';

    $dayOfWeek = (int) date('w', strtotime($date . ' 12:00:00'));
    $isWeekend = ($dayOfWeek === 0 || $dayOfWeek === 6);

    $actualMinutes = max(0, $elapsed_minutes);

    if ($isWeekend || !$salary_eligible) {
        return [
            'minutes'           => 0,
            'salary'            => 0,
            'break_minutes'     => 0,
            'is_weekend'        => $isWeekend,
            'salary_per_minute' => 0.0,
            'work_label'        => pcvc_daily_attendance_format_duration($actualMinutes),
            'salary_label'      => pcvc_daily_attendance_format_salary(0),
        ];
    }

    $salaryPerMinute = 8.33;
    $allowedBreak = 0;
    $stmt = $conn->prepare('SELECT salary_per_minute, allowed_break_minutes FROM admins WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $admin_id);
        $stmt->execute();
        $stmt->bind_result($dbRate, $dbBreak);
        if ($stmt->fetch()) {
            $salaryPerMinute = (float) ($dbRate ?: 8.33);
            $allowedBreak = (int) ($dbBreak ?? 0);
        }
        $stmt->close();
    }

    $effectiveMinutes = pcvc_attendance_billable_minutes($elapsed_minutes);
    $salary = (int) round($effectiveMinutes * $salaryPerMinute);

    return [
        'minutes'           => $effectiveMinutes,
        'salary'            => $salary,
        'break_minutes'     => $allowedBreak,
        'is_weekend'        => false,
        'salary_per_minute' => $salaryPerMinute,
        'work_label'        => pcvc_daily_attendance_format_duration($effectiveMinutes),
        'salary_label'      => pcvc_daily_attendance_format_salary($salary),
    ];
}

/**
 * Persist checkout times/salary and return payload for API + notifications.
 *
 * @return array<string, mixed>|null
 */
function pcvc_attendance_save_checkout(
    mysqli $conn,
    int $admin_id,
    int $attendance_id,
    string $check_in_time,
    string $now,
    string $today,
    string $location,
    float $lat,
    float $lng,
    int $elapsed_minutes,
    bool $salary_eligible = true
): ?array {
    $pay = pcvc_attendance_compute_checkout_salary(
        $conn,
        $admin_id,
        $elapsed_minutes,
        $today,
        $salary_eligible
    );

    $stmt = $conn->prepare("
        UPDATE attendance SET
            check_out_time = ?,
            check_out_location = ?,
            check_out_lat = ?,
            check_out_lng = ?,
            break_duration_minutes = ?,
            total_work_minutes = ?,
            total_payment_rwf = ?,
            daily_salary_rwf = ?
        WHERE id = ?
    ");
    if (!$stmt) {
        return null;
    }

    $minutes = $pay['minutes'];
    $salary = $pay['salary'];
    $break = $pay['break_minutes'];

    $stmt->bind_param(
        'ssddiiiii',
        $now,
        $location,
        $lat,
        $lng,
        $break,
        $minutes,
        $salary,
        $salary,
        $attendance_id
    );
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return null;
    }

    return [
        'attendance_id'     => $attendance_id,
        'check_in_time'     => $check_in_time,
        'check_out_time'    => $now,
        'date'              => $today,
        'worked_minutes'    => $minutes,
        'daily_salary_rwf'  => $salary,
        'work_label'        => $pay['work_label'],
        'salary_label'      => $pay['salary_label'],
        'is_weekend'        => $pay['is_weekend'],
        'salary_per_minute' => $pay['salary_per_minute'],
    ];
}
