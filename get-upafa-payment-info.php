<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/upafa_static_pricing.php';

pcvc_require_superadmin($conn, true);

header('Content-Type: application/json; charset=utf-8');

$studentId   = (int) ($_GET['student_id'] ?? 0);
$tierKey     = trim((string) ($_GET['tier'] ?? ''));
$sourceTable = trim((string) ($_GET['table'] ?? 'upafa_registrations'));

$allowedTables = ['credit_transfer_applications', 'upafa_registrations'];
if (!in_array($sourceTable, $allowedTables, true)) {
    $sourceTable = 'upafa_registrations';
}

if ($studentId <= 0 || !pcvc_upafa_fee_tier($tierKey)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $pkg       = pcvc_ensure_upafa_fee_package($conn, $tierKey);
    $paid      = pcvc_upafa_fee_tier_paid(
        $conn,
        $studentId,
        $pkg['package_id'],
        $sourceTable,
        $pkg['fee_item_id']
    );
    $total     = (float) $pkg['total'];
    $remaining = max(0, round($total - $paid, 2));
    $tier      = pcvc_upafa_fee_tier($tierKey);

    echo json_encode([
        'success'    => true,
        'tier'       => $tierKey,
        'label'      => $tier['label'],
        'title'      => $pkg['title'],
        'currency'   => $pkg['currency'],
        'total'      => $total,
        'paid'       => $paid,
        'remaining'  => $remaining,
        'package_id' => $pkg['package_id'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
