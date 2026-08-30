<?php
declare(strict_types=1);

ob_start();
session_start();
header('Content-Type: application/json; charset=UTF-8');
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/commission_currency.php';
require_once __DIR__ . '/helpers/commission_requests_schema.php';
require_once __DIR__ . '/helpers/commission_cyprus_db.php';
require_once __DIR__ . '/includes/company_branding.php';
require_once __DIR__ . '/includes/commission_mail_helper.php';

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/commission_debug.log';

function logCommissionError(string $msg): void
{
    global $logFile;
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] {$msg}\n", FILE_APPEND);
}

$response = ['status' => 'error', 'message' => 'Unknown error occurred'];

try {
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
    if ($userId < 1) {
        throw new RuntimeException('Agent not logged in. Please refresh the page and log in again.');
    }

    pcvc_ensure_commission_requests_schema($conn);

    $required = ['first_name', 'last_name', 'email', 'phone', 'recruited_student_id', 'date', 'signature', 'amount_usd'];
    foreach ($required as $f) {
        if (!isset($_POST[$f]) || trim((string) $_POST[$f]) === '') {
            throw new RuntimeException("Missing required field: {$f}");
        }
    }

    $amountEntered = (float) str_replace(',', '', (string) $_POST['amount_usd']);
    if ($amountEntered <= 0) {
        throw new RuntimeException('Commission amount must be greater than zero.');
    }

    $commissionCurrency = pcvc_normalize_commission_currency((string) ($_POST['commission_currency'] ?? 'USD'));
    $conv = pcvc_currency_to_rwf_conversion($commissionCurrency, $amountEntered);
    $amountRwf = (float) $conv['rwf'];
    $fxRate = (float) $conv['rate'];
    $amountUsd = $amountEntered;

    $studentKey = trim((string) $_POST['recruited_student_id']);
    $prefix = substr($studentKey, 0, 2);
    $studentId = (int) substr($studentKey, 2);
    $recruited_name = '';
    $recruited_phone = '';

    if ($prefix === 's_') {
        $stmt = $conn->prepare('SELECT CONCAT(first_name, " ", last_name) AS name, phone_number FROM student_applications WHERE id = ?');
        $stmt->bind_param('i', $studentId);
    } elseif ($prefix === 'a_') {
        $conn2 = pcvc_commission_cyprus_mysqli();
        if (!$conn2) {
            throw new RuntimeException('Cyprus student database is unavailable. Choose a student from the main list or try again later.');
        }
        $stmt = $conn2->prepare('SELECT name FROM applications WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException('Student lookup failed.');
        }
        $stmt->bind_param('i', $studentId);
    } else {
        throw new RuntimeException('Invalid student reference.');
    }

    if (!$stmt->execute()) {
        throw new RuntimeException('Student lookup failed: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $recruited_name = trim((string) ($row['name'] ?? ''));
        $recruited_phone = trim((string) ($row['phone_number'] ?? ''));
    } else {
        $stmt->close();
        throw new RuntimeException('Student not found.');
    }
    $stmt->close();

    $first_name = (string) $_POST['first_name'];
    $last_name = (string) $_POST['last_name'];
    $email = (string) $_POST['email'];
    $phone = (string) $_POST['phone'];
    $street_address = (string) ($_POST['street_address'] ?? '');
    $address_line_2 = (string) ($_POST['address_line_2'] ?? '');
    $city = (string) ($_POST['city'] ?? '');
    $state = (string) ($_POST['state'] ?? '');
    $postal_code = (string) ($_POST['postal_code'] ?? '');
    $country_applied = (string) ($_POST['country_applied'] ?? '');
    $loan_status = (string) ($_POST['loan_status'] ?? '');
    $visa_status = (string) ($_POST['visa_status'] ?? '');
    $contract_signed = (string) ($_POST['contract_signed'] ?? '');
    $comments = (string) ($_POST['comments'] ?? '');
    $submission_date = (string) $_POST['date'];
    $signature = (string) $_POST['signature'];

    $requestStatus = 'pending';
    $paidZero = 0.0;

    $sql = 'INSERT INTO commission_requests (
        user_id, first_name, last_name, email, phone,
        street_address, address_line_2, city, state, postal_code,
        recruited_name, recruited_phone, country_applied,
        loan_status, visa_status, contract_signed,
        comments, submission_date, signature, recruited_student_id,
        amount_usd, amount_rwf, fx_rate_used, commission_currency, request_status, paid_rwf_total
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }

    $types = 'i' . str_repeat('s', 18) . 'idddssd';
    $stmt->bind_param(
        $types,
        $userId,
        $first_name,
        $last_name,
        $email,
        $phone,
        $street_address,
        $address_line_2,
        $city,
        $state,
        $postal_code,
        $recruited_name,
        $recruited_phone,
        $country_applied,
        $loan_status,
        $visa_status,
        $contract_signed,
        $comments,
        $submission_date,
        $signature,
        $studentId,
        $amountUsd,
        $amountRwf,
        $fxRate,
        $commissionCurrency,
        $requestStatus,
        $paidZero
    );

    if (!$stmt->execute()) {
        throw new RuntimeException('Insert failed: ' . $stmt->error);
    }
    $newId = (int) $stmt->insert_id;
    $stmt->close();

    $admins = pcvc_superadmin_emails($conn);
    $agentName = trim($first_name . ' ' . $last_name);
    $safeName = htmlspecialchars($agentName, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

    $subj = 'New commission request #' . $newId . ' — ' . $agentName;
    $body = '<p><strong>New commission request submitted.</strong></p>'
        . '<p><strong>ID:</strong> ' . htmlspecialchars((string) $newId, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Agent:</strong> ' . $safeName
        . ' &lt;' . $safeEmail . '&gt;</p>'
        . '<p><strong>Amount:</strong> ' . htmlspecialchars($commissionCurrency, ENT_QUOTES, 'UTF-8') . ' '
        . htmlspecialchars(number_format($amountUsd, 2), ENT_QUOTES, 'UTF-8')
        . ' → RWF ' . htmlspecialchars(number_format($amountRwf, 0), ENT_QUOTES, 'UTF-8')
        . ' (rate ' . htmlspecialchars((string) $fxRate, ENT_QUOTES, 'UTF-8') . ')</p>'
        . '<p><strong>Recruited student:</strong> ' . htmlspecialchars($recruited_name, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>Open <strong>All Commission Requests</strong> in the admin dashboard to review.</p>';

    $adminMailOk = pcvc_send_commission_html_mail($admins, $subj, $body);
    if (!$adminMailOk) {
        if ($admins === []) {
            logCommissionError('Commission notify: no superadmin emails found (admins.role must match superadmin).');
        } else {
            logCommissionError('Commission notify: superadmin mail failed — check SMTP_* in .env and logs/commission_debug.log.');
        }
    }

    $agentMailOk = false;
    if (filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
        $agentSubj = 'Commission request received — #' . $newId;
        $agentBody = '<p>Hello ' . htmlspecialchars(trim($first_name), ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>We received your <strong>commission request #' . (int) $newId . '</strong>. Our team will review it and you will be notified when the status changes.</p>'
            . '<p><strong>Amount:</strong> USD ' . htmlspecialchars(number_format($amountUsd, 2), ENT_QUOTES, 'UTF-8')
            . ' (≈ RWF ' . htmlspecialchars(number_format($amountRwf, 0), ENT_QUOTES, 'UTF-8') . ')</p>'
            . '<p><strong>Recruited student:</strong> ' . htmlspecialchars($recruited_name, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>Thank you,<br>' . htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') . '</p>';
        $agentMailOk = pcvc_send_commission_html_mail([trim($email)], $agentSubj, $agentBody);
        if (!$agentMailOk) {
            logCommissionError('Commission confirmation email to agent failed: ' . trim($email));
        }
    }

    $response = [
        'status' => 'success',
        'message' => 'Commission request submitted.'
            . ($agentMailOk ? ' A confirmation email was sent to you.' : '')
            . ($adminMailOk ? ' Administrators have been notified.' : ''),
        'id' => $newId,
        'amount_rwf' => $amountRwf,
    ];
} catch (Throwable $e) {
    logCommissionError($e->getMessage());
    $response = ['status' => 'error', 'message' => $e->getMessage()];
} finally {
    /* Keep default connection open for shutdown; avoid closing shared $conn from db.php */
}

if (ob_get_level() > 0) {
    ob_end_clean();
}
echo json_encode($response);
exit;
