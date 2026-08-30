<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/research_elearning_schema.php';

header('Content-Type: application/json; charset=utf-8');

$sourceId = (int) ($_GET['student_id'] ?? $_GET['credit_transfer_id'] ?? 0);
$sourceTable = pcvc_research_elearning_normalize_source((string) ($_GET['source_table'] ?? 'credit_transfer_applications'));

if ($sourceId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing student id']);
    exit;
}

$adminId = (int) ($_SESSION['id'] ?? $_SESSION['admin_id'] ?? 0);
$row = pcvc_research_elearning_get_or_create($conn, $sourceTable, $sourceId, $adminId > 0 ? $adminId : null);
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit;
}

$payload = pcvc_research_elearning_build_payload($row);
echo json_encode(['success' => true] + $payload);

$conn->close();
