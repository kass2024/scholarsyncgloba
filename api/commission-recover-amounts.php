<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers/role.php';
require_once dirname(__DIR__) . '/helpers/commission_requests_schema.php';
require_once dirname(__DIR__) . '/helpers/commission_recover_from_comments.php';

function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$adminId = (int) ($_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
if ($adminId < 1) {
    respond(['ok' => false, 'error' => 'Not authenticated.'], 401);
}

$st = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
if (!$st) {
    respond(['ok' => false, 'error' => 'Database error.'], 500);
}
$st->bind_param('i', $adminId);
$st->execute();
$roleRow = $st->get_result()->fetch_assoc();
$st->close();
$role = (string) ($roleRow['role'] ?? '');
if (!pcvc_is_superadmin_role($role)) {
    respond(['ok' => false, 'error' => 'Only superadmin can run recovery.'], 403);
}

$raw = file_get_contents('php://input');
$input = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($input)) {
    respond(['ok' => false, 'error' => 'Invalid JSON.'], 400);
}

$csrf = isset($input['csrf']) && is_string($input['csrf']) ? $input['csrf'] : '';
$sess = $_SESSION['commission_admin_csrf'] ?? '';
if ($sess === '' || !hash_equals((string) $sess, $csrf)) {
    respond(['ok' => false, 'error' => 'Invalid security token. Reload the page.'], 403);
}

$dryRun = !empty($input['dry_run']);

pcvc_ensure_commission_requests_schema($conn);

$result = pcvc_recover_commission_amounts_from_comments($conn, $dryRun);

respond([
    'ok' => true,
    'dry_run' => $dryRun,
    'preview' => $result['preview'],
    'eligible' => count($result['preview']),
    'updated' => $result['updated'],
    'skipped_unparsed' => $result['skipped'],
]);
