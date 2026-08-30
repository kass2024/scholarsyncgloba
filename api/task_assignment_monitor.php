<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/role.php';
require_once __DIR__ . '/../helpers/application_filters.php';
require_once __DIR__ . '/../helpers/task_assignment_data.php';
require_once __DIR__ . '/../includes/company_branding.php';
require_once __DIR__ . '/../helpers/mailer.php';
require_once __DIR__ . '/../helpers/student_status_notify.php';
require_once __DIR__ . '/../helpers/task_staff_whatsapp_notify.php';

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';

/**
 * @return array{id:int, role:string}|null
 */
function pcvc_task_monitor_admin(mysqli $conn): ?array
{
    $adminPk = 0;
    if (!empty($_SESSION['id'])) {
        $adminPk = (int) $_SESSION['id'];
    } elseif (!empty($_SESSION['admin_id'])) {
        $adminPk = (int) $_SESSION['admin_id'];
    }
    if ($adminPk <= 0) {
        return null;
    }
    $st = $conn->prepare('SELECT id, COALESCE(role, \'\') AS role FROM admins WHERE id = ? LIMIT 1');
    if (!$st) {
        return null;
    }
    $st->bind_param('i', $adminPk);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) {
        return null;
    }

    return ['id' => (int) $row['id'], 'role' => trim((string) ($row['role'] ?? ''))];
}

$ctx = pcvc_task_monitor_admin($conn);
if ($ctx === null) {
    jsonResponse('Unauthorized', false, 401);
}

$sessionRole = trim((string) ($_SESSION['role'] ?? ''));
$dbRole = $ctx['role'];
$isPrivileged = pcvc_is_superadmin_role($dbRole)
    || pcvc_is_superadmin_role($sessionRole)
    || strcasecmp($dbRole, 'agent') === 0
    || strcasecmp($dbRole, 'standard') === 0;

/**
 * Staff-only users see only their own assignment bucket (still useful on mobile).
 */
$restrictStaffId = null;
if (!$isPrivileged && strcasecmp($dbRole, 'staff') === 0) {
    $restrictStaffId = $ctx['id'];
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$hasAssign = pcvc_task_monitor_has_assigned_column($conn);
if (!$hasAssign && in_array($action, ['summary', 'applications'], true)) {
    jsonResponse([
        'assignments_enabled' => false,
        'message' => 'Database column assigned_to_admin_id is not present on student_applications.',
    ], true, 200);
}

$statusLabels = pcvc_application_status_labels();
$statusPriority = pcvc_application_status_priority();

if ($action === 'summary') {
    try {
        jsonResponse(pcvc_task_monitor_build_summary_payload($conn, $restrictStaffId, $isPrivileged));
    } catch (Throwable $e) {
        error_log('[task_assignment_monitor] summary: ' . $e->getMessage());
        jsonResponse('Could not load applications', false, 500);
    }
}

if ($action === 'applications') {

    $staffKey = isset($_GET['staff_id']) ? trim((string) $_GET['staff_id']) : '';
    $statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';

    if ($staffKey === '' || ($staffKey !== '0' && (int) $staffKey < 0)) {
        jsonResponse('Missing or invalid staff_id (use 0 for unassigned)', false, 400);
    }
    $staffId = (int) $staffKey;

    if ($restrictStaffId !== null) {
        if ($staffId === 0 || $staffId !== $restrictStaffId) {
            jsonResponse('Forbidden', false, 403);
        }
    }

    $flagSql2 = implode(', ', pcvc_task_monitor_existing_flag_columns($conn));
    if ($flagSql2 === '') {
        $flagSql2 = '0 AS pcvc_no_flags';
    }

    $sql = "
        SELECT
            sa.id,
            sa.application_id,
            sa.first_name,
            sa.last_name,
            sa.email,
            sa.created_at,
            sa.assigned_to_admin_id,
            {$flagSql2}
        FROM student_applications sa
        WHERE 1=1
    ";
    if ($staffId === 0) {
        $sql .= ' AND (sa.assigned_to_admin_id IS NULL OR sa.assigned_to_admin_id = 0) ';
    } else {
        $sql .= ' AND sa.assigned_to_admin_id = ' . $staffId . ' ';
    }

    $res = $conn->query($sql);
    if (!$res) {
        jsonResponse('Could not load applications', false, 500);
    }

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $eff = pcvc_application_effective_status($row);
        if ($statusFilter !== '' && in_array($statusFilter, $statusPriority, true)) {
            if ($eff !== $statusFilter) {
                continue;
            }
        }
        $student = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        $rows[] = [
            'id' => (int) $row['id'],
            'application_id' => (string) ($row['application_id'] ?? ''),
            'student_name' => $student !== '' ? $student : 'Applicant',
            'email' => (string) ($row['email'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'status_key' => $eff,
            'status_label' => $eff !== null ? ($statusLabels[$eff] ?? $eff) : '—',
        ];
    }

    usort($rows, static function ($a, $b) {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });

    jsonResponse(['applications' => $rows]);
}

if ($action === 'notify' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$hasAssign) {
        jsonResponse('Assignments are not enabled in the database.', false, 400);
    }

    if (!$isPrivileged) {
        jsonResponse('Only supervisors can notify other staff.', false, 403);
    }

    $raw = file_get_contents('php://input');
    $in = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($in)) {
        jsonResponse('Invalid JSON body', false, 400);
    }

    $targetId = (int) ($in['staff_id'] ?? 0);
    if ($targetId <= 0) {
        jsonResponse('Invalid staff_id', false, 400);
    }

    $message = trim((string) ($in['message'] ?? ''));
    $sendEmail = !empty($in['send_email']);
    $sendWa = !empty($in['send_whatsapp']);
    /** Fixed product policy: email subject and WhatsApp template variant are not user-editable. */
    $emailSubject = 'general followup';
    $waVariant = 'urgent';

    if ($message === '') {
        jsonResponse('Message is required', false, 400);
    }
    if (!$sendEmail && !$sendWa) {
        jsonResponse('Choose at least one of email or WhatsApp', false, 400);
    }

    $st = $conn->prepare('SELECT id, first_name, last_name, full_name, email, phone_number, role FROM admins WHERE id = ? LIMIT 1');
    if (!$st) {
        jsonResponse('Server error', false, 500);
    }
    $st->bind_param('i', $targetId);
    $st->execute();
    $trow = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$trow) {
        jsonResponse('Staff not found', false, 404);
    }

    $name = pcvc_task_monitor_staff_display_name($trow);
    $email = trim((string) ($trow['email'] ?? ''));
    $phone = trim((string) ($trow['phone_number'] ?? ''));

    $senderAdminLine = 'Admin #' . (int) $ctx['id'];
    $stFrom = $conn->prepare('SELECT id, first_name, last_name, full_name FROM admins WHERE id = ? LIMIT 1');
    if ($stFrom) {
        $aid = (int) $ctx['id'];
        $stFrom->bind_param('i', $aid);
        $stFrom->execute();
        $fromRow = $stFrom->get_result()->fetch_assoc();
        $stFrom->close();
        if ($fromRow) {
            $senderAdminLine = pcvc_task_monitor_staff_display_name($fromRow) . ' #' . $aid;
        }
    }
    $companySenderLine = PCVC_COMPANY_DISPLAY_NAME . ' — ' . $senderAdminLine;

    $emailOk = false;
    $waOk = false;
    $waMethod = '';
    $errors = [];

    if ($sendEmail) {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Staff has no valid email on file.';
        } else {
            $safeMsg = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
            $safeCo = htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8');
            $safeSender = htmlspecialchars($senderAdminLine, ENT_QUOTES, 'UTF-8');
            $html = '<div style="font-family:system-ui,sans-serif;line-height:1.5;color:#111;max-width:640px">'
                . '<p>Hello <strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
                . '<p style="margin:10px 0 0;font-size:14px;color:#334155">' . $safeCo
                . ' <span style="color:#94a3b8">·</span> ' . $safeSender . '</p>'
                . '<div style="margin:16px 0">' . $safeMsg . '</div>'
                . '<p style="color:#64748b;font-size:13px">Sent from the task assignment monitor · '
                . $safeCo . '</p></div>';
            try {
                $mail = app_mailer();
                $mail->clearAddresses();
                $mail->addAddress($email, $name);
                $mail->Subject = $emailSubject;
                $mail->Body = $html;
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));
                $emailOk = $mail->send();
                if (!$emailOk) {
                    $errors[] = 'Email could not be sent.';
                }
            } catch (Throwable $e) {
                error_log('[task_assignment_monitor] email: ' . $e->getMessage());
                $errors[] = 'Email error: ' . $e->getMessage();
            }
        }
    }

    if ($sendWa) {
        if ($phone === '') {
            $errors[] = 'Staff has no phone number on file for WhatsApp.';
        } else {
            $waResult = pcvc_task_monitor_send_staff_whatsapp(
                $phone,
                $name,
                $companySenderLine,
                $message,
                $waVariant
            );
            $waOk = $waResult['sent'];
            $waMethod = (string) ($waResult['method'] ?? '');
            if (!$waOk) {
                $errWa = trim((string) ($waResult['error'] ?? ''));
                $errors[] = 'WhatsApp: ' . ($errWa !== '' ? $errWa : 'Send failed');
            }
        }
    }

    $any = $emailOk || $waOk;
    if ($any) {
        jsonResponse([
            'email_sent' => $emailOk,
            'whatsapp_sent' => $waOk,
            'whatsapp_method' => $waMethod,
            'errors' => $errors,
        ], true, 200);
    }
    $msg = trim(implode(' ', $errors));
    jsonResponse($msg !== '' ? $msg : 'Could not send notification.', false, 422);
}

jsonResponse('Unknown action', false, 400);
