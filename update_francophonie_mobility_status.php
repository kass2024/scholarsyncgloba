<?php
/**
 * update_francophonie_mobility_status.php — email-only status updates + approval package.
 */
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
fm_ensure_schema($conn);
require_once __DIR__ . '/helpers/francophonie_mobility_notify.php';
require_once __DIR__ . '/helpers/fm_email_worker.php';
require_once __DIR__ . '/helpers/env_load.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id']) || empty($_POST['csrf_token'])
    || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$application_id = (int) ($_POST['application_id'] ?? 0);
$new_status = trim((string) ($_POST['status'] ?? ''));
$note = trim((string) ($_POST['note'] ?? $_POST['rejection_reason'] ?? ''));

if ($application_id <= 0 || $new_status === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$allowed = ['pending', 'under_review', 'approved', 'rejected'];
if (!in_array($new_status, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

$st = $conn->prepare('SELECT * FROM francophonie_mobility_applications WHERE id = ? LIMIT 1');
$st->bind_param('i', $application_id);
$st->execute();
$app = $st->get_result()->fetch_assoc();
$st->close();

if (!$app) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit;
}

$old_status = (string) ($app['status'] ?? '');

$conn->begin_transaction();
try {
    $up = $conn->prepare('UPDATE francophonie_mobility_applications SET status = ?, updated_at = NOW() WHERE id = ?');
    $up->bind_param('si', $new_status, $application_id);
    if (!$up->execute()) {
        throw new RuntimeException('Update failed');
    }
    $up->close();

    if ($note !== '') {
        $nu = $conn->prepare('UPDATE francophonie_mobility_applications SET admin_notes = ? WHERE id = ?');
        $nu->bind_param('si', $note, $application_id);
        $nu->execute();
        $nu->close();
        $app['admin_notes'] = $note;
    }

    $admin_id = (int) ($_SESSION['id'] ?? 0);
    $log = $conn->prepare('INSERT INTO francophonie_mobility_status_logs (application_id, old_status, new_status, admin_id, note) VALUES (?,?,?,?,?)');
    $log->bind_param('issis', $application_id, $old_status, $new_status, $admin_id, $note);
    $log->execute();
    $log->close();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$app['status'] = $new_status;
xander_load_env_file();
$emailOk = fm_notify_applicant_status($app, $new_status, $note);

$packageOk = null;
$approvalEmail = fm_approval_recipient_email();
if ($new_status === 'approved') {
    if ($approvalEmail === '') {
        $packageOk = false;
    } else {
        $packageOk = fm_dispatch_approval_package($application_id);
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Status updated',
    'email_sent' => $emailOk,
    'approval_package_sent' => $packageOk,
    'approval_email' => $approvalEmail !== '' ? $approvalEmail : null,
]);
