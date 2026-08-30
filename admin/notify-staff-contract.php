<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/role.php';
require_once __DIR__ . '/../helpers/staff_contract_notify.php';

header('Content-Type: application/json; charset=utf-8');

pcvc_require_superadmin($conn, true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$notifyAll = !empty($data['all_pending']);

if ($notifyAll) {
    $summary = pcvc_staff_contract_notify_all_pending($conn);
    $message = 'Emails sent: ' . $summary['sent'];
    if ($summary['skipped'] > 0) {
        $message .= ', skipped: ' . $summary['skipped'];
    }
    if ($summary['failed'] > 0) {
        $message .= ', failed: ' . $summary['failed'];
    }
    if ($summary['sent'] === 0 && $summary['failed'] === 0) {
        $message = 'No staff with contracts awaiting signature.';
    }

    echo json_encode([
        'success' => $summary['failed'] === 0,
        'message' => $message,
        'summary' => $summary,
    ]);
    exit;
}

$staffId = (int) ($data['staff_id'] ?? 0);
if ($staffId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing staff member']);
    exit;
}

$result = pcvc_staff_contract_notify_staff_pending($conn, $staffId);
if (!empty($result['skipped'])) {
    echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Nothing to notify']);
    exit;
}
if (empty($result['ok'])) {
    echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Email failed']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Contract reminder sent to ' . ($result['email'] ?? 'staff'),
]);
