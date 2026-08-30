<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start();
error_reporting(0);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers/role.php';

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
if ($userId < 1 && !empty($_SESSION['username'])) {
    $lu = $conn->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
    if ($lu) {
        $uname = (string) $_SESSION['username'];
        $lu->bind_param('s', $uname);
        $lu->execute();
        $lu->bind_result($foundId);
        if ($lu->fetch()) {
            $userId = (int) $foundId;
            $_SESSION['user_id'] = $userId;
        }
        $lu->close();
    }
}

$payload = ['results' => []];

if ($userId < 1) {
    $payload['error'] = 'Unauthorized';
    ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$items = [];

$role = '';
$agentEmail = '';
if ($st = $conn->prepare('SELECT role, email FROM admins WHERE id = ? LIMIT 1')) {
    $st->bind_param('i', $userId);
    $st->execute();
    $st->bind_result($roleCol, $agentEmailDb);
    if ($st->fetch()) {
        $role = (string) $roleCol;
        $agentEmail = (string) $agentEmailDb;
    }
    $st->close();
}

$isSuper = pcvc_is_superadmin_role($role);
$agentKey = strtolower(trim($agentEmail));

if (!$isSuper && $q === '') {
    ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['results' => []]);
    exit;
}

$like = $q !== '' ? '%' . $q . '%' : '%';

if ($isSuper && $q === '' && $agentKey !== '') {
    $stmt = $conn->prepare(
        'SELECT id, first_name, last_name, email FROM student_applications
         WHERE LOWER(TRIM(agent_email)) = ?
         ORDER BY id DESC LIMIT 120'
    );
    if ($stmt) {
        $stmt->bind_param('s', $agentKey);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            $items[] = [
                'id'   => 's_' . $row['id'],
                'text' => ($name !== '' ? $name : 'Student') . ' — ' . ($row['email'] ?? ''),
            ];
        }
        $stmt->close();
    }
} elseif ($isSuper) {
    $stmt = $conn->prepare(
        'SELECT id, first_name, last_name, email FROM student_applications
         WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
         OR CONCAT(COALESCE(first_name,""), " ", COALESCE(last_name,"")) LIKE ?
         ORDER BY id DESC LIMIT 30'
    );
    if ($stmt) {
        $stmt->bind_param('ssss', $like, $like, $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            $items[] = [
                'id'   => 's_' . $row['id'],
                'text' => ($name !== '' ? $name : 'Student') . ' — ' . ($row['email'] ?? ''),
            ];
        }
        $stmt->close();
    }
} else {
    $stmt = $conn->prepare(
        'SELECT id, first_name, last_name, email FROM student_applications
         WHERE LOWER(TRIM(agent_email)) = ?
         AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
         OR CONCAT(COALESCE(first_name,""), " ", COALESCE(last_name,"")) LIKE ?)
         ORDER BY id DESC LIMIT 30'
    );
    if ($stmt) {
        $stmt->bind_param('sssss', $agentKey, $like, $like, $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            $items[] = [
                'id'   => 's_' . $row['id'],
                'text' => ($name !== '' ? $name : 'Student') . ' — ' . ($row['email'] ?? ''),
            ];
        }
        $stmt->close();
    }
}

ob_end_clean();
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['results' => $items]);
