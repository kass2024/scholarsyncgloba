<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers/role.php';
require_once dirname(__DIR__) . '/helpers/commission_requests_schema.php';
require_once dirname(__DIR__) . '/helpers/env_load.php';
require_once dirname(__DIR__) . '/helpers/student_status_notify.php';
require_once dirname(__DIR__) . '/includes/momo_phone.php';

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
if (!pcvc_is_superadmin_role((string) ($roleRow['role'] ?? ''))) {
    respond(['ok' => false, 'error' => 'Forbidden.'], 403);
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

$id = isset($input['id']) ? (int) $input['id'] : 0;
if ($id < 1) {
    respond(['ok' => false, 'error' => 'Invalid request.'], 400);
}

pcvc_ensure_commission_requests_schema($conn);

$q = $conn->prepare('SELECT id, phone FROM commission_requests WHERE id = ? LIMIT 1');
$q->bind_param('i', $id);
$q->execute();
$row = $q->get_result()->fetch_assoc();
$q->close();
if (!$row) {
    respond(['ok' => false, 'error' => 'Commission request not found.'], 404);
}

$phoneRaw = trim((string) ($row['phone'] ?? ''));
$momoMsisdn = pcvc_normalize_rw_momo_msisdn($phoneRaw);
$momoDisplay = pcvc_format_rw_momo_display($momoMsisdn);

$dcc = trim(xander_env_get('WHATSAPP_DEFAULT_COUNTRY_CODE'));
$defaultCc = $dcc !== '' ? $dcc : null;
$waE164 = $phoneRaw !== '' ? xander_format_phone_for_whatsapp_e164($phoneRaw, $defaultCc) : null;
// Rwanda commission flow: MoMo-valid MSISDN is a usable WhatsApp target when env CC is unset.
if (($waE164 === null || $waE164 === '') && $momoMsisdn !== null) {
    $waE164 = $momoMsisdn;
}
$waMe = null;
if ($waE164 !== null && $waE164 !== '') {
    $waMe = 'https://wa.me/' . rawurlencode($waE164);
}

respond([
    'ok' => true,
    'phone_raw' => $phoneRaw,
    'momo_msisdn' => $momoMsisdn,
    'momo_display' => $momoDisplay,
    'whatsapp_e164' => $waE164,
    'whatsapp_wa_me' => $waMe,
]);
