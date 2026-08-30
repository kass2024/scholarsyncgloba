<?php
declare(strict_types=1);

ob_start();
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/helpers/commission_currency.php';
require_once dirname(__DIR__) . '/helpers/commission_requests_schema.php';
require_once dirname(__DIR__) . '/helpers/commission_request_owner.php';

function respond(array $data, int $code = 200): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
if ($userId < 1) {
    respond(['ok' => false, 'error' => 'Not logged in.'], 401);
}

$csrfPost = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$csrfSess = (string) ($_SESSION['csrf_token'] ?? '');
if ($csrfSess === '' || !hash_equals($csrfSess, $csrfPost)) {
    respond(['ok' => false, 'error' => 'Invalid security token. Reload the page.'], 403);
}

$id = isset($_POST['commission_request_id']) ? (int) $_POST['commission_request_id'] : 0;
if ($id < 1) {
    respond(['ok' => false, 'error' => 'Invalid request.'], 400);
}

pcvc_ensure_commission_requests_schema($conn);

$stmt = $conn->prepare('SELECT * FROM commission_requests WHERE id = ? LIMIT 1');
if (!$stmt) {
    respond(['ok' => false, 'error' => 'Database error.'], 500);
}
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    respond(['ok' => false, 'error' => 'Request not found.'], 404);
}

if (!pcvc_commission_user_id_matches_row($row['user_id'] ?? null, $userId)) {
    respond(['ok' => false, 'error' => 'You can only update your own commission requests.'], 403);
}

$st = trim((string) ($row['request_status'] ?? 'pending'));
if ($st === '') {
    $st = 'pending';
}
if ($st === 'under_review') {
    respond(['ok' => false, 'error' => 'This request is under review and cannot be edited.'], 403);
}

$required = ['recruited_student_id', 'date', 'signature', 'amount_usd'];
foreach ($required as $f) {
    if (!isset($_POST[$f]) || trim((string) $_POST[$f]) === '') {
        respond(['ok' => false, 'error' => "Missing: {$f}"], 400);
    }
}

$amountEntered = (float) str_replace(',', '', (string) $_POST['amount_usd']);
if ($amountEntered <= 0) {
    respond(['ok' => false, 'error' => 'Commission amount must be greater than zero.'], 400);
}
$commissionCurrency = pcvc_normalize_commission_currency((string) ($_POST['commission_currency'] ?? 'USD'));
$conv = pcvc_currency_to_rwf_conversion($commissionCurrency, $amountEntered);
$amountRwf = (float) $conv['rwf'];
$fxRate = (float) $conv['rate'];
$amountUsd = $amountEntered;

$studentKey = trim((string) $_POST['recruited_student_id']);
$prefix = substr($studentKey, 0, 2);
$studentId = (int) substr($studentKey, 2);
$recruited_name = '';
$recruited_phone = '';

if ($prefix === 's_') {
    $st2 = $conn->prepare('SELECT CONCAT(first_name, " ", last_name) AS name, phone_number FROM student_applications WHERE id = ?');
    $st2->bind_param('i', $studentId);
} elseif ($prefix === 'a_') {
    $st2 = $conn2->prepare('SELECT name FROM applications WHERE id = ?');
    $st2->bind_param('i', $studentId);
} else {
    respond(['ok' => false, 'error' => 'Invalid student reference.'], 400);
}

if (!$st2->execute()) {
    respond(['ok' => false, 'error' => 'Student lookup failed.'], 500);
}
$res = $st2->get_result();
if ($r2 = $res->fetch_assoc()) {
    $recruited_name = trim((string) ($r2['name'] ?? ''));
    $recruited_phone = trim((string) ($r2['phone_number'] ?? ''));
} else {
    $st2->close();
    respond(['ok' => false, 'error' => 'Student not found.'], 400);
}
$st2->close();

$first_name = (string) ($row['first_name'] ?? '');
$last_name = (string) ($row['last_name'] ?? '');
$email = (string) ($row['email'] ?? '');
$phone = (string) ($row['phone'] ?? '');
$street_address = (string) ($_POST['street_address'] ?? '');
$address_line_2 = (string) ($_POST['address_line_2'] ?? '');
$city = (string) ($_POST['city'] ?? '');
$state = (string) ($_POST['state'] ?? '');
$postal_code = (string) ($_POST['postal_code'] ?? '');
$country_applied = (string) ($_POST['country_applied'] ?? '');
$loan_status = (string) ($_POST['loan_status'] ?? '');
$visa_status = (string) ($_POST['visa_status'] ?? '');
$contract_signed = (string) ($_POST['contract_signed'] ?? '');
$comments = (string) ($_POST['comments'] ?? '');
$submission_date = (string) $_POST['date'];
$signature = (string) $_POST['signature'];

$sql = 'UPDATE commission_requests SET
    street_address = ?, address_line_2 = ?, city = ?, state = ?, postal_code = ?,
    recruited_name = ?, recruited_phone = ?, country_applied = ?,
    loan_status = ?, visa_status = ?, contract_signed = ?,
    comments = ?, submission_date = ?, signature = ?, recruited_student_id = ?,
    amount_usd = ?, amount_rwf = ?, fx_rate_used = ?, commission_currency = ?
    WHERE id = ? LIMIT 1';

$upd = $conn->prepare($sql);
if (!$upd) {
    respond(['ok' => false, 'error' => 'Prepare failed.'], 500);
}

$types = str_repeat('s', 14) . 'idddsi';
$upd->bind_param(
    $types,
    $street_address,
    $address_line_2,
    $city,
    $state,
    $postal_code,
    $recruited_name,
    $recruited_phone,
    $country_applied,
    $loan_status,
    $visa_status,
    $contract_signed,
    $comments,
    $submission_date,
    $signature,
    $studentId,
    $amountUsd,
    $amountRwf,
    $fxRate,
    $commissionCurrency,
    $id
);

if (!$upd->execute()) {
    $upd->close();
    respond(['ok' => false, 'error' => 'Update failed.'], 500);
}
$upd->close();

respond([
    'ok' => true,
    'message' => 'Commission request updated.',
    'amount_usd' => $amountUsd,
    'amount_rwf' => $amountRwf,
]);
