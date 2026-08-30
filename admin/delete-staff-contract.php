<?php

declare(strict_types=1);



session_start();

require_once __DIR__ . '/../db.php';

require_once __DIR__ . '/../helpers/role.php';

require_once __DIR__ . '/../helpers/staff_contract_schema.php';



header('Content-Type: application/json; charset=utf-8');



pcvc_require_superadmin($conn, true);



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode(['success' => false, 'message' => 'Invalid request']);

    exit;

}



$raw = file_get_contents('php://input');

$data = json_decode((string) $raw, true);

$staffId = (int) ($data['staff_id'] ?? $_POST['staff_id'] ?? 0);



if ($staffId <= 0) {

    echo json_encode(['success' => false, 'message' => 'Missing staff member']);

    exit;

}



$stmt = $conn->prepare('SELECT id, full_name, role FROM admins WHERE id = ? LIMIT 1');

if (!$stmt) {

    echo json_encode(['success' => false, 'message' => 'Database error']);

    exit;

}

$stmt->bind_param('i', $staffId);

$stmt->execute();

$staff = $stmt->get_result()->fetch_assoc();

$stmt->close();



if (!$staff) {

    echo json_encode(['success' => false, 'message' => 'Staff member not found']);

    exit;

}



$contract = pcvc_staff_contract_for_admin($conn, $staffId);

if (!$contract || !pcvc_staff_contract_has_template($contract)) {

    echo json_encode(['success' => false, 'message' => 'No contract file to delete']);

    exit;

}



try {

    $wasSigned = ($contract['status'] ?? '') === 'signed';

    $deleted = pcvc_staff_contract_delete_for_admin($conn, $staffId);

    if (!$deleted) {

        echo json_encode(['success' => false, 'message' => 'Could not delete contract']);

        exit;

    }



    $message = 'Contract deleted for ' . ($staff['full_name'] ?? 'staff') . '.';

    if ($wasSigned) {

        $message .= ' The signed copy was also removed.';

    }

    $message .= ' You can upload a new Word contract anytime.';



    echo json_encode(['success' => true, 'message' => $message]);

} catch (Throwable $e) {

    echo json_encode(['success' => false, 'message' => $e->getMessage()]);

}
