<?php
/**
 * Update job_applications.process_status — Superadmin only
 */
session_start();
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/role.php';
require_once __DIR__ . '/../helpers/job_application_status.php';
require_once __DIR__ . '/../helpers/rejection_reason_column.php';

xander_ensure_job_applications_process_status_column($conn);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('Method not allowed', false, 405);
}

$adminId = 0;
if (!empty($_SESSION['id'])) {
    $adminId = (int) $_SESSION['id'];
} elseif (!empty($_SESSION['admin_id'])) {
    $adminId = (int) $_SESSION['admin_id'];
}

if ($adminId <= 0) {
    jsonResponse('Unauthorized', false, 401);
}

$stmtRole = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
if (!$stmtRole) {
    jsonResponse('Server error', false, 500);
}
$stmtRole->bind_param('i', $adminId);
$stmtRole->execute();
$roleRow = $stmtRole->get_result()->fetch_assoc();
$stmtRole->close();

if (!$roleRow) {
    jsonResponse('Unauthorized', false, 401);
}

$dbRole = (string) ($roleRow['role'] ?? '');
$sessionRole = (string) ($_SESSION['role'] ?? '');
if (
    !xander_is_superadmin_role($dbRole)
    && !xander_is_superadmin_role($sessionRole)
) {
    jsonResponse('Forbidden', false, 403);
}

$applicationId = (int) ($_POST['application_id'] ?? $_POST['id'] ?? 0);
$statusKey = trim((string) ($_POST['process_status'] ?? ''));

if ($applicationId <= 0) {
    jsonResponse('Invalid application id', false, 400);
}

if (!xander_is_valid_job_process_status($statusKey)) {
    jsonResponse('Invalid status', false, 400);
}

xander_ensure_rejection_reason_column($conn, 'job_applications');

$rejectionReasonPosted = trim((string) ($_POST['rejection_reason'] ?? ''));
$notifyEmail = isset($_POST['notify_email']) && (string) $_POST['notify_email'] === '1';
$notifyWhatsapp = isset($_POST['notify_whatsapp']) && (string) $_POST['notify_whatsapp'] === '1';

if ($statusKey === 'rejected' && ($notifyEmail || $notifyWhatsapp) && $rejectionReasonPosted === '') {
    jsonResponse('Please enter a reason for the rejection before sending email or WhatsApp.', false, 400);
}

$chk = $conn->prepare('SELECT id FROM job_applications WHERE id = ? LIMIT 1');
if (!$chk) {
    jsonResponse('Server error', false, 500);
}
$chk->bind_param('i', $applicationId);
$chk->execute();
if (!$chk->get_result()->fetch_assoc()) {
    $chk->close();
    jsonResponse('Application not found', false, 404);
}
$chk->close();

if ($statusKey === 'rejected') {
    $st = $conn->prepare('UPDATE job_applications SET process_status = ?, rejection_reason = ? WHERE id = ? LIMIT 1');
    if (!$st) {
        jsonResponse('Server error', false, 500);
    }
    $rejStore = $rejectionReasonPosted !== '' ? $rejectionReasonPosted : null;
    $st->bind_param('ssi', $statusKey, $rejStore, $applicationId);
} else {
    $st = $conn->prepare('UPDATE job_applications SET process_status = ?, rejection_reason = NULL WHERE id = ? LIMIT 1');
    if (!$st) {
        jsonResponse('Server error', false, 500);
    }
    $st->bind_param('si', $statusKey, $applicationId);
}
$st->execute();
$st->close();

$notifyResult = null;
if ($notifyEmail || $notifyWhatsapp) {
    try {
        require_once __DIR__ . '/../helpers/application_status_notify.php';
        $reasonForNotify = ($statusKey === 'rejected') ? $rejectionReasonPosted : '';
        $notifyResult = xander_notify_job_application_change($conn, $applicationId, $statusKey, $notifyEmail, $notifyWhatsapp, $reasonForNotify);
    } catch (Throwable $e) {
        error_log('[job-application-status] notify: ' . $e->getMessage());
    }
}

$labels = xander_job_application_process_statuses();
$data = [
    'application_id' => $applicationId,
    'process_status' => $statusKey,
    'label' => $labels[$statusKey] ?? $statusKey,
];
if ($notifyResult !== null) {
    $data['notify'] = $notifyResult;
}
jsonResponse($data, true);
