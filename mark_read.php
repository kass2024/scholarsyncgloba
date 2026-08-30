<?php
require_once 'auth.php';
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $table = $_POST['table'] ?? null;

    // Map each table to its primary key
$allowedTables = [
    'master_loan_applications' => 'id',
    'student_applications' => 'id',
    'form_17_applications' => 'user_id',
    'form_20_applications' => 'user_id',
    'commission_requests' => 'id',
    'credit_transfer_applications' => 'id',
    'staff_job_reports' => 'id',
    'malta_applications' => 'id',
    'turkey_applications' => 'id',
    'georgia_applications' => 'id',
];


    if ($id && $table && isset($allowedTables[$table])) {
        $primaryKey = $allowedTables[$table];

        $stmt = $conn->prepare("UPDATE `$table` SET is_read = 1 WHERE `$primaryKey` = ?");
        $paramType = is_numeric($id) ? 'i' : 's'; // support int and string
        $stmt->bind_param($paramType, $id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Execution failed']);
        }

        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    }

    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
