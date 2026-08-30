<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers/csrf.php';

function refund_lookup_respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    refund_lookup_respond(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if (mb_strlen($q) < 2) {
    refund_lookup_respond(['ok' => true, 'results' => [], 'message' => 'Type at least 2 characters to search.']);
}

// Live-search friendly throttle: min 280ms between DB queries; serve cache for repeat query
$nowMs = microtime(true);
$lastMs = (float) ($_SESSION['refund_lookup_last_ms'] ?? 0);
$lastQ = (string) ($_SESSION['refund_lookup_last_q'] ?? '');
$cached = $_SESSION['refund_lookup_cache'] ?? null;
if ($nowMs - $lastMs < 0.28) {
    if ($lastQ === $q && is_array($cached)) {
        refund_lookup_respond($cached);
    }
    refund_lookup_respond(['ok' => true, 'results' => [], 'count' => 0]);
}

$like = '%' . $q . '%';
$parts = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$firstLike = '%' . ($parts[0] ?? $q) . '%';
$lastLike = count($parts) > 1 ? '%' . end($parts) . '%' : $firstLike;

$sql = "
    SELECT id, application_id, first_name, middle_name, last_name, email,
           area_code, phone_number
    FROM student_applications
    WHERE (
        CONCAT(TRIM(first_name), ' ', TRIM(COALESCE(middle_name, '')), ' ', TRIM(last_name)) LIKE ?
        OR CONCAT(TRIM(first_name), ' ', TRIM(last_name)) LIKE ?
        OR TRIM(first_name) LIKE ?
        OR TRIM(last_name) LIKE ?
        OR LOWER(TRIM(email)) LIKE LOWER(?)
    )
    ORDER BY id DESC
    LIMIT 15
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    refund_lookup_respond(['ok' => false, 'error' => 'Database error.'], 500);
}
$emailLike = '%' . $q . '%';
$stmt->bind_param('sssss', $like, $like, $firstLike, $lastLike, $emailLike);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$results = [];
foreach ($rows as $r) {
    $fn = trim((string) ($r['first_name'] ?? ''));
    $mn = trim((string) ($r['middle_name'] ?? ''));
    $ln = trim((string) ($r['last_name'] ?? ''));
    $full = trim($fn . ($mn !== '' ? ' ' . $mn : '') . ' ' . $ln);
    $ac = trim((string) ($r['area_code'] ?? ''));
    $pn = trim((string) ($r['phone_number'] ?? ''));
    $phone = trim($ac . ' ' . $pn);
    $results[] = [
        'id' => (int) ($r['id'] ?? 0),
        'application_id' => trim((string) ($r['application_id'] ?? '')),
        'first_name' => $fn,
        'middle_name' => $mn,
        'last_name' => $ln,
        'full_name' => $full,
        'email' => trim((string) ($r['email'] ?? '')),
        'phone' => $phone,
        'label' => $full . ($r['application_id'] ? ' · ' . $r['application_id'] : '') . ' · ' . trim((string) ($r['email'] ?? '')),
    ];
}

$payload = ['ok' => true, 'results' => $results, 'count' => count($results)];
$_SESSION['refund_lookup_last_ms'] = $nowMs;
$_SESSION['refund_lookup_last_q'] = $q;
$_SESSION['refund_lookup_cache'] = $payload;
refund_lookup_respond($payload);
