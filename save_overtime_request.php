<?php
/**
 * save_overtime_request.php
 * Saves staff overtime request + sends email notification
 * INLINE SMTP (same logic as working PDF mail file)
 * PRODUCTION READY & DEBUGGABLE
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

/* PHPMailer – SAME STYLE AS WORKING FILE */
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

/* =====================================================
   HEADERS
===================================================== */
header('Content-Type: application/json; charset=utf-8');

/* =====================================================
   SECURITY
===================================================== */
if (!isset($_SESSION['admin_id'], $_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized or session expired'
    ]);
    exit;
}

$staffId = (int)$_SESSION['admin_id'];

/* =====================================================
   INPUT VALIDATION
===================================================== */
$date   = $_POST['date']   ?? null;
$reason = trim($_POST['reason'] ?? '');
$hours  = $_POST['hours'] ?? null;

if (!$date || $reason === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Date and tasks are required'
    ]);
    exit;
}

if ($hours !== null && $hours !== '') {
    if (!is_numeric($hours) || $hours <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Expected hours must be a valid number'
        ]);
        exit;
    }
    $hours = (float)$hours;
} else {
    $hours = null;
}

/* =====================================================
   PREVENT MULTIPLE PENDING REQUESTS
===================================================== */
$check = $conn->prepare("
    SELECT id
    FROM overtime_requests
    WHERE staff_id = ? AND status = 'pending'
    LIMIT 1
");
$check->bind_param("i", $staffId);
$check->execute();

if ($check->get_result()->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'You already have a pending overtime request'
    ]);
    exit;
}

/* =====================================================
   GET STAFF DETAILS
===================================================== */
$staffQ = $conn->prepare("
    SELECT full_name, email
    FROM admins
    WHERE id = ?
    LIMIT 1
");
$staffQ->bind_param("i", $staffId);
$staffQ->execute();
$staff = $staffQ->get_result()->fetch_assoc();

$staffName  = $staff['full_name'] ?? 'Staff';
$staffEmail = $staff['email'] ?? '';

/* =====================================================
   INSERT REQUEST
===================================================== */
$insert = $conn->prepare("
    INSERT INTO overtime_requests (
        staff_id,
        request_date,
        reason,
        expected_hours,
        status,
        created_at
    ) VALUES (?, ?, ?, ?, 'pending', NOW())
");

$insert->bind_param(
    "issd",
    $staffId,
    $date,
    $reason,
    $hours
);

if (!$insert->execute()) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save overtime request'
    ]);
    exit;
}

/* =====================================================
   SEND EMAIL (INLINE SMTP — SAME AS PDF FILE)
===================================================== */
try {
    $mail = new PHPMailer(true);
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';


    $mail->isSMTP();
    $mail->Host       = 'scholarsyncglobal.ca';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'infos@scholarsyncglobal.ca';
    $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    $mail->setFrom(
        'infos@scholarsyncglobal.ca',
        'Visa Consultant Canada'
    );

    /* RECIPIENTS */
    $recipients = [
        'infos@scholarsyncglobal.ca',
        'infos@scholarsyncglobal.ca',
        'twajepeeter@gmail.com',
        'twajeepeter@gmail.com'
    ];

    foreach ($recipients as $email) {
        $mail->addAddress($email);
    }

    $approvalLink = 'https://scholarsyncglobal.ca/admin-login.php';

    $mail->isHTML(true);
    $mail->Subject = "New Overtime Request – {$staffName}";

    $mail->Body = "
        <h3>New Overtime Request Submitted</h3>

        <p><strong>Staff Name:</strong> {$staffName}</p>
        <p><strong>Date:</strong> {$date}</p>
        <p><strong>Expected Hours:</strong> " . ($hours ?? 'N/A') . "</p>

        <p><strong>Tasks:</strong></p>
        <pre style='background:#f4f6f9;padding:12px;border-radius:8px;'>
" . htmlspecialchars($reason) . "
        </pre>

        <p>
            👉 <a href='{$approvalLink}' target='_blank'>
            Click here to login and approve the request
            </a>
        </p>

        <hr>
        <small>
            Generated automatically by ScholarSync MIS Overtime System
        </small>
    ";

    $mail->send();

} catch (Throwable $e) {
    // Email failure must NOT block saving
    error_log("Overtime mail error: " . $e->getMessage());
}

/* =====================================================
   FINAL RESPONSE
===================================================== */
echo json_encode([
    'success' => true,
    'message' => 'Overtime request submitted successfully'
]);
