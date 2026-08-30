<?php
declare(strict_types=1);

/**
 * Personal activity snapshot for non–super-admin dashboard home.
 * Queries are defensive: missing tables/columns must not break the page.
 *
 * @return array<string, int|string>
 */
function pcvc_staff_dashboard_stats(mysqli $conn, int $adminId): array
{
    $out = [
        'leave_pending' => 0,
        'leave_last_30d' => 0,
        'commission_total' => 0,
        'commission_last_90d' => 0,
        'salary_pending' => 0,
        'salary_total' => 0,
        'attendance_days_month' => 0,
        'attendance_minutes_month' => 0,
        'overtime_pending' => 0,
        'overtime_open_approved' => 0,
        'month_label' => date('F Y'),
    ];

    if ($adminId < 1) {
        return $out;
    }

    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');

    if ($stmt = @$conn->prepare("SELECT COUNT(*) AS c FROM leave_requests WHERE admin_id = ? AND LOWER(TRIM(status)) = 'pending'")) {
        $stmt->bind_param('i', $adminId);
        if ($stmt->execute()) {
            $r = $stmt->get_result()->fetch_assoc();
            $out['leave_pending'] = (int) ($r['c'] ?? 0);
        }
        $stmt->close();
    }

    if ($stmt = @$conn->prepare("SELECT COUNT(*) AS c FROM leave_requests WHERE admin_id = ? AND requested_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")) {
        $stmt->bind_param('i', $adminId);
        if ($stmt->execute()) {
            $r = $stmt->get_result()->fetch_assoc();
            $out['leave_last_30d'] = (int) ($r['c'] ?? 0);
        }
        $stmt->close();
    }

    if ($stmt = @$conn->prepare('SELECT COUNT(*) AS c FROM commission_requests WHERE user_id = ?')) {
        $stmt->bind_param('i', $adminId);
        if ($stmt->execute()) {
            $r = $stmt->get_result()->fetch_assoc();
            $out['commission_total'] = (int) ($r['c'] ?? 0);
        }
        $stmt->close();
    }

    if ($stmt = @$conn->prepare("SELECT COUNT(*) AS c FROM commission_requests WHERE user_id = ? AND submission_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)")) {
        $stmt->bind_param('i', $adminId);
        if ($stmt->execute()) {
            $r = $stmt->get_result()->fetch_assoc();
            $out['commission_last_90d'] = (int) ($r['c'] ?? 0);
        }
        $stmt->close();
    }

    if ($stmt = @$conn->prepare("SELECT COUNT(*) AS c FROM salary_requests WHERE admin_id = ? AND LOWER(TRIM(status)) = 'pending'")) {
        $stmt->bind_param('i', $adminId);
        if ($stmt->execute()) {
            $r = $stmt->get_result()->fetch_assoc();
            $out['salary_pending'] = (int) ($r['c'] ?? 0);
        }
        $stmt->close();
    }

    if ($stmt = @$conn->prepare('SELECT COUNT(*) AS c FROM salary_requests WHERE admin_id = ?')) {
        $stmt->bind_param('i', $adminId);
        if ($stmt->execute()) {
            $r = $stmt->get_result()->fetch_assoc();
            $out['salary_total'] = (int) ($r['c'] ?? 0);
        }
        $stmt->close();
    }

    if ($stmt = @$conn->prepare("SELECT COUNT(DISTINCT DATE(`date`)) AS days, COALESCE(SUM(total_work_minutes),0) AS mins FROM attendance WHERE admin_id = ? AND DATE(`date`) BETWEEN ? AND ?")) {
        $stmt->bind_param('iss', $adminId, $monthStart, $monthEnd);
        if ($stmt->execute()) {
            $r = $stmt->get_result()->fetch_assoc();
            $out['attendance_days_month'] = (int) ($r['days'] ?? 0);
            $out['attendance_minutes_month'] = (int) ($r['mins'] ?? 0);
        }
        $stmt->close();
    }

    if ($stmt = @$conn->prepare("SELECT COUNT(*) AS c FROM overtime_requests WHERE staff_id = ? AND LOWER(TRIM(status)) = 'pending'")) {
        $stmt->bind_param('i', $adminId);
        if ($stmt->execute()) {
            $r = $stmt->get_result()->fetch_assoc();
            $out['overtime_pending'] = (int) ($r['c'] ?? 0);
        }
        $stmt->close();
    }

    if ($stmt = @$conn->prepare("SELECT COUNT(*) AS c FROM overtime_requests WHERE staff_id = ? AND LOWER(TRIM(status)) = 'approved'")) {
        $stmt->bind_param('i', $adminId);
        if ($stmt->execute()) {
            $r = $stmt->get_result()->fetch_assoc();
            $out['overtime_open_approved'] = (int) ($r['c'] ?? 0);
        }
        $stmt->close();
    }

    return $out;
}
