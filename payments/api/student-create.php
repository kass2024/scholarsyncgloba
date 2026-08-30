<?php
declare(strict_types=1);

/**
 * Minimal student_applications row for checkout when search finds no match.
 */
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/includes/momo_phone.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$fn = trim((string) ($data['first_name'] ?? ''));
$ln = trim((string) ($data['last_name'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$phoneRaw = trim((string) ($data['phone'] ?? ''));

if ($fn === '' || $ln === '') {
    echo json_encode(['ok' => false, 'error' => 'First name and last name are required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($email === '') {
    echo json_encode(['ok' => false, 'error' => 'Email is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Enter a valid email address.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($fn) > 120 || strlen($ln) > 120) {
    echo json_encode(['ok' => false, 'error' => 'Name is too long.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$emailNorm = strtolower($email);
$dup = $conn->prepare('SELECT `id` FROM `student_applications` WHERE LOWER(TRIM(`email`)) = ? LIMIT 1');
if ($dup) {
    $dup->bind_param('s', $emailNorm);
    $dup->execute();
    $dupRes = $dup->get_result();
    $exists = $dupRes && $dupRes->fetch_assoc();
    $dup->close();
    if ($exists) {
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'error' => 'This email is already registered. Use “Find existing” to search by email, or enter a different email.',
            'code' => 'duplicate_email',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$msisdn = pcvc_normalize_rw_momo_msisdn($phoneRaw);
if ($msisdn === null) {
    echo json_encode([
        'ok' => false,
        'error' => 'Enter a valid Rwanda MTN MoMo number (e.g. 07XX XXX XXX or 2507XXXXXXXX).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$emailBind = $email;

$newId = 0;
$lastDbError = '';

$sqlMinimal = 'INSERT INTO `student_applications` (`first_name`, `last_name`, `email`, `phone_number`) VALUES (?, ?, ?, ?)';
$stmt = $conn->prepare($sqlMinimal);
if ($stmt) {
    $stmt->bind_param('ssss', $fn, $ln, $emailBind, $msisdn);
    if ($stmt->execute()) {
        $newId = (int) $stmt->insert_id;
    } else {
        $lastDbError = $stmt->error;
        // MySQL duplicate key on email index
        if (stripos($stmt->error, 'Duplicate') !== false || stripos($stmt->error, 'unique') !== false) {
            $stmt->close();
            http_response_code(409);
            echo json_encode([
                'ok' => false,
                'error' => 'This email is already in use. Search for the student or use another email.',
                'code' => 'duplicate_email',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    $stmt->close();
} else {
    $lastDbError = $conn->error;
}

if ($newId < 1) {
    $sessionId = 'co_' . bin2hex(random_bytes(12));
    $userId = 'guest_' . bin2hex(random_bytes(8));
    $sqlGuest = 'INSERT INTO `student_applications` (`session_id`, `user_id`, `first_name`, `last_name`, `email`, `phone_number`, `app_start`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())';
    $st2 = $conn->prepare($sqlGuest);
    if ($st2) {
        $st2->bind_param('ssssss', $sessionId, $userId, $fn, $ln, $emailBind, $msisdn);
        if ($st2->execute()) {
            $newId = (int) $st2->insert_id;
        } else {
            $lastDbError = $st2->error ?: $lastDbError;
            if (stripos($st2->error, 'Duplicate') !== false || stripos($st2->error, 'unique') !== false) {
                $st2->close();
                http_response_code(409);
                echo json_encode([
                    'ok' => false,
                    'error' => 'This email is already in use. Search for the student or use another email.',
                    'code' => 'duplicate_email',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        $st2->close();
    } else {
        $lastDbError = $conn->error ?: $lastDbError;
    }
}

if ($newId < 1) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Could not create student record. ' . ($lastDbError !== '' ? '(' . $lastDbError . ')' : 'Check database permissions.'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim($fn . ' ' . $ln);

echo json_encode([
    'ok' => true,
    'student' => [
        'id' => $newId,
        'name' => $name,
        'email' => $email,
        'phone' => $msisdn,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
