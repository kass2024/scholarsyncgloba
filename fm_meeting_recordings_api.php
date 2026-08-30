<?php
/**
 * fm_meeting_recordings_api.php — List / delete Francophonie meeting cloud recordings.
 */
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/francophonie_meeting_recordings.php';

xander_load_env_file();
fm_meeting_ensure_schema($conn);

if (empty($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
pcvc_require_staff_or_superadmin($conn, true);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }

    $meetingNumber = preg_replace('/\D+/', '', (string) ($_POST['meeting_number'] ?? ''));
    $topic = trim((string) ($_POST['topic'] ?? 'this recording'));

    if ($meetingNumber === '') {
        echo json_encode(['success' => false, 'message' => 'Invalid meeting number']);
        exit;
    }

    $known = fm_meeting_invitations_by_zoom_number($conn);
    if (!isset($known[$meetingNumber])) {
        echo json_encode(['success' => false, 'message' => 'Recording is not linked to a Francophonie meeting invitation.']);
        exit;
    }

    try {
        zoom_api_delete_meeting_recordings($meetingNumber, 'delete');
        echo json_encode([
            'success' => true,
            'message' => 'Cloud recording deleted for "' . $topic . '".',
            'meeting_number' => $meetingNumber,
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

if ($dateFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = (new DateTime('now'))->modify('-180 days')->format('Y-m-d');
}
if ($dateTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = (new DateTime('now'))->format('Y-m-d');
}

if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

if (!zoom_api_is_configured()) {
    echo json_encode(['success' => false, 'message' => 'Zoom credentials missing in .env.']);
    exit;
}

try {
    $result = fm_meeting_fetch_cloud_recordings($conn, $dateFrom, $dateTo, $search);
    echo json_encode([
        'success' => true,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'q' => $search,
        'total' => $result['total'],
        'items' => $result['items'],
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
