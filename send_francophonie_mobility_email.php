<?php
/**
 * send_francophonie_mobility_email.php — Resend status email to applicant (admin).
 */
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
require_once __DIR__ . '/helpers/francophonie_mobility_notify.php';
require_once __DIR__ . '/helpers/fm_email_worker.php';
require_once __DIR__ . '/helpers/env_load.php';

fm_ensure_schema($conn);
xander_load_env_file();

if (empty($_SESSION['id']) || !in_array($_SESSION['role'] ?? '', ['superadmin', 'staff'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (empty($_POST['csrf_token']) || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$id = (int) ($_POST['application_id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid application']);
    exit;
}

$st = $conn->prepare('SELECT * FROM francophonie_mobility_applications WHERE id = ? LIMIT 1');
$st->bind_param('i', $id);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

$action = trim((string) ($_POST['action'] ?? 'status'));
$note = trim((string) ($_POST['note'] ?? ''));

if ($action === 'approval_package') {
    $queued = fm_dispatch_approval_package($id);
    if ($queued) {
        echo json_encode([
            'success' => true,
            'message' => 'Approval package is being sent to ' . fm_approval_recipient_email(),
        ]);
        exit;
    }
    $ok = fm_send_approval_package($row);
    if ($ok) {
        $mark = $conn->prepare('UPDATE francophonie_mobility_applications SET approval_package_sent_at = NOW() WHERE id = ?');
        $mark->bind_param('i', $id);
        $mark->execute();
        $mark->close();
    }
    echo json_encode([
        'success' => $ok,
        'message' => $ok
            ? 'Approval package sent to ' . fm_approval_recipient_email()
            : 'Failed — set FRANCOPHONIE_MOBILITY_APPROVAL_EMAIL in .env',
    ]);
    exit;
}

$status = (string) ($row['status'] ?? 'pending');
$ok = fm_notify_applicant_status($row, $status, $note);
echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Email sent to applicant' : 'Failed to send email — check SMTP settings in .env',
]);
