<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
$receiptNo = $_POST['receipt_no'] ?? '';

if ($receiptNo !== '') {
    $stmt = $conn->prepare(
        "UPDATE payment_receipts SET status='CANCELED' WHERE receipt_no=?"
    );
    $stmt->bind_param("s", $receiptNo);
    $stmt->execute();
}

header("Location: receipt_viewer.php");
exit;
