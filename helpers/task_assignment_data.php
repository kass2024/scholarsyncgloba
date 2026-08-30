<?php
declare(strict_types=1);

/**
 * Shared task-assignment summary (used by api/task_assignment_monitor.php and task-assignment-monitoring.php).
 */
require_once __DIR__ . '/application_filters.php';
require_once __DIR__ . '/role.php';

function pcvc_task_monitor_has_assigned_column(mysqli $conn): bool
{
    $r = $conn->query("SHOW COLUMNS FROM student_applications LIKE 'assigned_to_admin_id'");

    return $r && $r->num_rows > 0;
}

/**
 * @return list<string>
 */
function pcvc_task_monitor_existing_flag_columns(mysqli $conn): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $have = [];
    $r = $conn->query('SHOW COLUMNS FROM student_applications');
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $f = (string) ($row['Field'] ?? '');
            if ($f !== '') {
                $have[$f] = true;
            }
        }
    }
    $out = [];
    foreach (pcvc_application_status_priority() as $col) {
        if (isset($have[$col])) {
            $out[] = 'sa.`' . $col . '`';
        }
    }
    $cache = $out;

    return $out;
}

function pcvc_task_monitor_staff_display_name(array $r): string
{
    $fn = trim((string) ($r['first_name'] ?? ''));
    $ln = trim((string) ($r['last_name'] ?? ''));
    $full = trim((string) ($r['full_name'] ?? ''));
    if ($full !== '') {
        return $full;
    }
    $n = trim($fn . ' ' . $ln);

    return $n !== '' ? $n : ('Staff #' . (int) ($r['id'] ?? 0));
}

/** Short label for the role pill on task monitoring cards (CSS uppercases). */
function pcvc_task_monitor_role_label(?string $dbRole): string
{
    if (pcvc_is_superadmin_role((string) $dbRole)) {
        return 'Superadmin';
    }
    $lr = strtolower(trim((string) $dbRole));
    if ($lr === 'staff') {
        return 'Staff';
    }

    return $lr !== '' ? ucfirst($lr) : 'Assignee';
}

/**
 * Same shape as JSON "data" for ?action=summary.
 *
 * @return array<string, mixed>
 */
function pcvc_task_monitor_build_summary_payload(mysqli $conn, ?int $restrictStaffId, bool $isPrivileged): array
{
    $statusLabels = pcvc_application_status_labels();
    $statusPriority = pcvc_application_status_priority();

    if (!pcvc_task_monitor_has_assigned_column($conn)) {
        return [
            'assignments_enabled' => false,
            'message' => 'Database column assigned_to_admin_id is not present on student_applications.',
        ];
    }

    $flagSql = implode(', ', pcvc_task_monitor_existing_flag_columns($conn));
    if ($flagSql === '') {
        $flagSql = '0 AS pcvc_no_flags';
    }

    $sql = "
        SELECT
            sa.id,
            sa.assigned_to_admin_id,
            {$flagSql},
            a.id AS staff_table_id,
            a.first_name AS sf, a.last_name AS sl, a.full_name AS sfull,
            a.email AS staff_email, a.phone_number AS staff_phone
        FROM student_applications sa
        LEFT JOIN admins a ON a.id = sa.assigned_to_admin_id
    ";
    if ($restrictStaffId !== null) {
        $sql .= ' WHERE sa.assigned_to_admin_id = ' . (int) $restrictStaffId . ' ';
    }

    $res = $conn->query($sql);
    if (!$res) {
        throw new RuntimeException('Could not load applications for summary');
    }

    $byStaff = [];
    $unassigned = ['staff_id' => 0, 'name' => 'Unassigned', 'total' => 0, 'by_status' => array_fill_keys($statusPriority, 0), 'no_status' => 0];
    $grand = ['total' => 0, 'by_status' => array_fill_keys($statusPriority, 0), 'no_status' => 0];

    while ($row = $res->fetch_assoc()) {
        $sid = isset($row['assigned_to_admin_id']) ? (int) $row['assigned_to_admin_id'] : 0;
        $eff = pcvc_application_effective_status($row);

        $grand['total']++;
        if ($eff !== null && isset($grand['by_status'][$eff])) {
            $grand['by_status'][$eff]++;
        } else {
            $grand['no_status']++;
        }

        if ($sid <= 0) {
            $unassigned['total']++;
            if ($eff !== null && isset($unassigned['by_status'][$eff])) {
                $unassigned['by_status'][$eff]++;
            } else {
                $unassigned['no_status']++;
            }
            continue;
        }

        if (!isset($byStaff[$sid])) {
            $byStaff[$sid] = [
                'staff_id' => $sid,
                'name' => pcvc_task_monitor_staff_display_name([
                    'id' => $sid,
                    'first_name' => $row['sf'] ?? '',
                    'last_name' => $row['sl'] ?? '',
                    'full_name' => $row['sfull'] ?? '',
                ]),
                'email' => trim((string) ($row['staff_email'] ?? '')),
                'phone' => trim((string) ($row['staff_phone'] ?? '')),
                'total' => 0,
                'by_status' => array_fill_keys($statusPriority, 0),
                'no_status' => 0,
            ];
        }
        $byStaff[$sid]['total']++;
        if ($eff !== null && isset($byStaff[$sid]['by_status'][$eff])) {
            $byStaff[$sid]['by_status'][$eff]++;
        } else {
            $byStaff[$sid]['no_status']++;
        }
    }

    usort($byStaff, static function ($a, $b) {
        return $b['total'] <=> $a['total'] ?: strcmp((string) $a['name'], (string) $b['name']);
    });

    $byStaffAssoc = [];
    foreach ($byStaff as $rrow) {
        $sid = (int) ($rrow['staff_id'] ?? 0);
        if ($sid > 0) {
            $byStaffAssoc[$sid] = $rrow;
        }
    }

    $mergedStaff = [];
    $staffDirSeen = [];

    $dirSql = '
        SELECT id, first_name, last_name, full_name, email, phone_number, COALESCE(role, \'\') AS role
        FROM admins
        WHERE ' . pcvc_sql_assignable_application_owner_condition() . '
    ';
    if ($restrictStaffId !== null) {
        $dirSql .= ' AND id = ' . (int) $restrictStaffId;
    }
    $dirSql .= ' ORDER BY last_name ASC, first_name ASC, id ASC';

    $dirRes = $conn->query($dirSql);
    if ($dirRes) {
        while ($dr = $dirRes->fetch_assoc()) {
            $id = (int) ($dr['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $staffDirSeen[$id] = true;
            $fromApps = $byStaffAssoc[$id] ?? null;
            $mergedStaff[] = [
                'staff_id' => $id,
                'name' => pcvc_task_monitor_staff_display_name($dr),
                'email' => trim((string) ($dr['email'] ?? '')),
                'phone' => trim((string) ($dr['phone_number'] ?? '')),
                'role_label' => pcvc_task_monitor_role_label((string) ($dr['role'] ?? '')),
                'total' => $fromApps ? (int) $fromApps['total'] : 0,
                'by_status' => $fromApps ? $fromApps['by_status'] : array_fill_keys($statusPriority, 0),
                'no_status' => $fromApps ? (int) $fromApps['no_status'] : 0,
                'has_assignments' => $fromApps !== null && (int) $fromApps['total'] > 0,
            ];
        }
    }

    foreach ($byStaffAssoc as $id => $stats) {
        if (isset($staffDirSeen[$id])) {
            continue;
        }
        $st2 = $conn->prepare('SELECT id, first_name, last_name, full_name, email, phone_number, COALESCE(role, \'\') AS role FROM admins WHERE id = ? LIMIT 1');
        if (!$st2) {
            $mergedStaff[] = array_merge($stats, [
                'role_label' => 'Assignee',
                'has_assignments' => true,
            ]);
            continue;
        }
        $st2->bind_param('i', $id);
        $st2->execute();
        $xr = $st2->get_result()->fetch_assoc();
        $st2->close();
        $rl = 'Assignee';
        if ($xr) {
            $rl = pcvc_task_monitor_role_label((string) ($xr['role'] ?? ''));
            $stats['name'] = pcvc_task_monitor_staff_display_name($xr);
            if (trim((string) ($xr['email'] ?? '')) !== '') {
                $stats['email'] = trim((string) $xr['email']);
            }
            if (trim((string) ($xr['phone_number'] ?? '')) !== '') {
                $stats['phone'] = trim((string) $xr['phone_number']);
            }
        }
        $mergedStaff[] = array_merge($stats, [
            'role_label' => $rl,
            'has_assignments' => true,
        ]);
    }

    usort($mergedStaff, static function ($a, $b) {
        $ta = (int) ($a['total'] ?? 0);
        $tb = (int) ($b['total'] ?? 0);
        if ($tb !== $ta) {
            return $tb <=> $ta;
        }

        return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    $assignedApps = max(0, $grand['total'] - $unassigned['total']);
    $sumStatus = static function (array $g, array $keys): int {
        $n = 0;
        foreach ($keys as $k) {
            $n += (int) ($g['by_status'][$k] ?? 0);
        }

        return $n;
    };
    $earlyKeys = ['incomplete_app', 'app_start', 'submitted', 'addn_doc'];
    $midKeys = ['sent_to_platform', 'app_paid', 'admit', 'i20_sent', 'sevis_paid', 'visa_scheduled'];
    $wonKeys = ['visa_approved', 'enrolled'];

    $summaryMetrics = [
        'total_applications' => (int) $grand['total'],
        'assigned_applications' => $assignedApps,
        'unassigned_applications' => (int) $unassigned['total'],
        'staff_directory_count' => count($mergedStaff),
        'with_active_queue' => count(array_filter($mergedStaff, static function ($s) {
            return ((int) ($s['total'] ?? 0)) > 0;
        })),
        'early_stage' => $sumStatus($grand, $earlyKeys),
        'mid_pipeline' => $sumStatus($grand, $midKeys),
        'positive_outcomes' => $sumStatus($grand, $wonKeys),
        'rejections' => (int) ($grand['by_status']['deny'] ?? 0),
        'no_status' => (int) ($grand['no_status'] ?? 0),
    ];

    return [
        'assignments_enabled' => true,
        'status_labels' => $statusLabels,
        'status_order' => $statusPriority,
        'grand' => $grand,
        'unassigned' => $unassigned,
        'staff' => $mergedStaff,
        'summary_metrics' => $summaryMetrics,
        'viewer' => [
            'restricted_to_self' => $restrictStaffId !== null,
            'can_notify_others' => $isPrivileged,
        ],
    ];
}
