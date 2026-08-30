<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/employment_opportunities_schema.php';
require_once __DIR__ . '/helpers/eo_contract_schema.php';

header('Content-Type: application/json; charset=UTF-8');

eo_ensure_schema($conn);
eo_contract_ensure_schema($conn);

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode(['possible_match' => false]);
    exit;
}

$emailInput = trim($data['email'] ?? '');

if ($emailInput === '' || strlen($emailInput) < 3) {
    echo json_encode(['possible_match' => false]);
    exit;
}

$sql = "
    SELECT id, reference_id, full_name, email, phone_area_code, phone_number,
           passport_number, training_field, status
    FROM employment_opportunities_applications
    WHERE email LIKE ?
    ORDER BY CASE WHEN LOWER(email) = LOWER(?) THEN 0 ELSE 1 END, id DESC
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['possible_match' => false]);
    exit;
}

$likeEmail = '%' . $emailInput . '%';
$stmt->bind_param('ss', $likeEmail, $emailInput);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) {
    echo json_encode(['possible_match' => false]);
    exit;
}

$phone = '';
if (!empty($app['phone_area_code']) || !empty($app['phone_number'])) {
    $phone = trim('+' . ltrim((string) ($app['phone_area_code'] ?? ''), '+') . ' ' . trim((string) ($app['phone_number'] ?? '')));
}

echo json_encode([
    'possible_match' => true,
    'applicant' => [
        'id'              => (int) $app['id'],
        'reference_id'    => $app['reference_id'] ?? '',
        'full_name'       => $app['full_name'] ?? '',
        'email'           => $app['email'] ?? '',
        'phone'           => $phone,
        'passport_number' => $app['passport_number'] ?? '',
        'training_field'  => eo_training_field_label((string) ($app['training_field'] ?? '')),
        'status'          => $app['status'] ?? '',
    ],
]);
