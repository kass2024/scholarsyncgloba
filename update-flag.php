<?php
/**
 * Set exactly one status flag on applicant row (students-manage, catholic-manage, agent-student-manage).
 * POST json=1 → JSON { ok, notify? }; otherwise plain text ok | invalid | db_error | error (legacy).
 */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/rejection_reason_column.php';
require_once __DIR__ . '/helpers/caq_status.php';

/* =====================================================
   INPUT & VALIDATION
===================================================== */

$id    = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$flag  = $_POST['flag']  ?? '';
$table = $_POST['table'] ?? 'student_applications';

$notifyEmail = isset($_POST['notify_email']) && (string) $_POST['notify_email'] === '1';
$notifyWhatsapp = isset($_POST['notify_whatsapp']) && (string) $_POST['notify_whatsapp'] === '1';

/** JSON notify breakdown (students-manage). Other callers omit → plain-text ok / invalid / … */
$wantJson = isset($_POST['json']) && (string) $_POST['json'] === '1';

$allowed_tables = [
    'student_applications',
    'malta_applications',
    'turkey_applications',
];

$allowed_flags = [
    'incomplete_app',
    'submitted',
    'sent_to_platform',
    'app_paid',
    'admit',
    'caq',
    'i20_sent',
    'sevis_paid',
    'visa_scheduled',
    'visa_approved',
    'enrolled',
    'addn_doc',
    'deny',
    'app_start',
];

/**
 * @param array<string, mixed>|null $notify
 */
function xander_update_flag_respond(bool $wantJson, bool $ok, ?string $errorKey = null, $notify = null): void
{
    if ($wantJson) {
        header('Content-Type: application/json; charset=UTF-8');
        $out = ['ok' => $ok];
        if ($errorKey !== null) {
            $out['error'] = $errorKey;
        }
        if ($notify !== null || $ok) {
            $out['notify'] = $notify;
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: text/plain; charset=UTF-8');
    if (!$ok) {
        echo $errorKey !== null && $errorKey !== '' ? $errorKey : 'error';
        exit;
    }
    echo 'ok';
    exit;
}

if (
    $id <= 0 ||
    !in_array($table, $allowed_tables, true) ||
    !in_array($flag, $allowed_flags, true)
) {
    xander_update_flag_respond($wantJson, false, 'invalid');
}

if ($flag === 'caq') {
    if ($table !== 'student_applications') {
        xander_update_flag_respond($wantJson, false, 'caq_canada_only');
    }
    pcvc_ensure_caq_column($conn);
    $dst = $conn->prepare('SELECT destination FROM student_applications WHERE id = ? LIMIT 1');
    if ($dst) {
        $dst->bind_param('i', $id);
        $dst->execute();
        $drow = $dst->get_result()->fetch_assoc();
        $dst->close();
        if (!$drow || !pcvc_destination_is_canada((string) ($drow['destination'] ?? ''))) {
            xander_update_flag_respond($wantJson, false, 'caq_canada_only');
        }
    }
}

$flagColumnCheck = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($flag) . "'");
if (!$flagColumnCheck || $flagColumnCheck->num_rows === 0) {
    xander_update_flag_respond($wantJson, false, 'missing_column');
}

xander_ensure_rejection_reason_column($conn, $table);

$rejectionReasonPosted = trim((string) ($_POST['rejection_reason'] ?? ''));
if ($flag === 'deny' && ($notifyEmail || $notifyWhatsapp) && $rejectionReasonPosted === '') {
    xander_update_flag_respond($wantJson, false, 'rejection_reason_required');
}

/* =====================================================
  UPDATE STATUS (MILESTONE MODE)
  - Keep previous flags as-is (do NOT reset others to 0)
  - Set selected flag to 1
===================================================== */

try {
    $setParts = ["`$flag` = 1"];

    if ($flag === 'deny') {
        $setParts[] = '`rejection_reason` = ?';
    } else {
        $setParts[] = '`rejection_reason` = NULL';
    }

    $setQuery = implode(', ', $setParts);

    $sql = "
        UPDATE `$table`
        SET $setQuery
        WHERE id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    if ($flag === 'deny') {
        $rejStore = $rejectionReasonPosted !== '' ? $rejectionReasonPosted : null;
        $stmt->bind_param('si', $rejStore, $id);
    } else {
        $stmt->bind_param('i', $id);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        xander_update_flag_respond($wantJson, false, 'db_error');
    }

    $stmt->close();

    $notifyResult = null;
    if ($notifyEmail || $notifyWhatsapp) {
        try {
            require_once __DIR__ . '/helpers/student_status_notify.php';
            $reasonForNotify = ($flag === 'deny') ? $rejectionReasonPosted : '';
            $notifyResult = xander_notify_student_status_change($conn, $table, $id, $flag, $notifyEmail, $notifyWhatsapp, $reasonForNotify);
        } catch (Throwable $e) {
            error_log('[update-flag] notify: ' . $e->getMessage());
            $notifyResult = [
                'email' => [
                    'requested' => $notifyEmail,
                    'sent' => $notifyEmail ? false : null,
                    'error' => $notifyEmail ? 'Notification failed (server error).' : '',
                ],
                'whatsapp' => [
                    'requested' => $notifyWhatsapp,
                    'sent' => $notifyWhatsapp ? false : null,
                    'method' => '',
                    'error' => $notifyWhatsapp ? 'Notification failed (server error).' : '',
                ],
            ];
        }
    }

    xander_update_flag_respond($wantJson, true, null, $notifyResult);
} catch (Throwable $e) {
    file_put_contents(
        __DIR__ . '/flag_error.log',
        date('Y-m-d H:i:s')
        . " | Table: $table | ID: $id | Flag: $flag | Error: "
        . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );
    xander_update_flag_respond($wantJson, false, 'error');
}
