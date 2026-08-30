<?php
declare(strict_types=1);

/**
 * Email staff when an employment contract is awaiting signature.
 */

require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/staff_contract_schema.php';
require_once __DIR__ . '/../includes/company_branding.php';

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * @return array{ok:bool, error?:string}
 */
function pcvc_staff_contract_send_awaiting_email(
    string $toEmail,
    string $staffName,
    string $contractTitle = 'Employment Contract'
): array {
    $toEmail = trim($toEmail);
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid staff email address'];
    }

    if (trim(xander_env_get('STAFF_CONTRACT_SMTP_PASSWORD')) === '') {
        return ['ok' => false, 'error' => 'STAFF_CONTRACT_SMTP_PASSWORD is not set in .env'];
    }

    $staffName = trim($staffName) !== '' ? trim($staffName) : 'Team member';
    $contractTitle = trim($contractTitle) !== '' ? trim($contractTitle) : 'Employment Contract';
    $baseUrl = pcvc_public_base_url();
    $contractUrl = rtrim($baseUrl, '/') . '/admin/contract.php';
    $loginUrl = rtrim($baseUrl, '/') . '/admin-login.php';
    $fromEmail = xander_env_get('STAFF_CONTRACT_SMTP_FROM_EMAIL') ?: 'infos@scholarsyncglobal.ca';

    $subject = 'Action required: sign your employment contract — ' . PCVC_COMPANY_DISPLAY_NAME;

    $safeName = htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars($contractTitle, ENT_QUOTES, 'UTF-8');
    $safeContractUrl = htmlspecialchars($contractUrl, ENT_QUOTES, 'UTF-8');
    $safeLoginUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

    $html = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:600px;color:#1e293b;line-height:1.5;">'
        . '<p>Dear ' . $safeName . ',</p>'
        . '<p>Your <strong>' . $safeTitle . '</strong> has been prepared in '
        . htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8')
        . ' MIS. Your details are already filled in — please review and e-sign.</p>'
        . '<p style="margin:24px 0;">'
        . '<a href="' . $safeContractUrl . '" style="display:inline-block;background:#427431;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;">Review &amp; sign contract</a>'
        . '</p>'
        . '<p style="font-size:14px;color:#475569;">If you are not logged in, sign in first: '
        . '<a href="' . $safeLoginUrl . '">' . $safeLoginUrl . '</a></p>'
        . '<p style="font-size:14px;color:#475569;">After signing you can print or save a PDF copy from the contract page.</p>'
        . '<p style="margin-top:28px;font-size:13px;color:#64748b;">'
        . htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') . '<br>'
        . htmlspecialchars($fromEmail, ENT_QUOTES, 'UTF-8')
        . '</p></div>';

    $text = "Dear {$staffName},\n\n"
        . "Your {$contractTitle} is ready for review and e-signature in ScholarSync MIS.\n\n"
        . "Open your contract: {$contractUrl}\n"
        . "Login: {$loginUrl}\n\n"
        . PCVC_COMPANY_DISPLAY_NAME . "\n{$fromEmail}";

    try {
        $mail = pcvc_staff_contract_smtp_mailer();
        $mail->addAddress($toEmail, $staffName);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text;
        $mail->send();

        return ['ok' => true];
    } catch (MailerException $e) {
        $info = ($mail instanceof PHPMailer) ? trim((string) ($mail->ErrorInfo ?? '')) : '';
        $msg = $info !== '' ? $info : $e->getMessage();

        return ['ok' => false, 'error' => $msg];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Notify one staff member if their contract is awaiting signature.
 *
 * @return array{ok:bool, skipped?:bool, error?:string, email?:string}
 */
function pcvc_staff_contract_notify_staff_pending(mysqli $conn, int $staffId): array
{
    $stmt = $conn->prepare(
        'SELECT a.id, a.full_name, a.email, c.contract_title, c.status, c.source_docx_path, c.source_pdf_path,
                c.signed_at, c.signed_docx_path, c.signed_pdf_path, c.pdf_path
         FROM admins a
         LEFT JOIN employment_contracts c ON c.admin_id = a.id
         WHERE a.id = ? LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Database error'];
    }
    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['ok' => false, 'error' => 'Staff member not found'];
    }

    $status = pcvc_staff_contract_row_status($row);
    if (!pcvc_staff_contract_is_awaiting_signature($row)) {
        return ['ok' => true, 'skipped' => true, 'error' => 'Contract is not awaiting signature'];
    }

    $email = trim((string) ($row['email'] ?? ''));
    $result = pcvc_staff_contract_send_awaiting_email(
        $email,
        (string) ($row['full_name'] ?? ''),
        (string) ($row['contract_title'] ?? 'Employment Contract')
    );
    if (!$result['ok']) {
        return $result;
    }

    return ['ok' => true, 'email' => $email];
}

/**
 * Notify all staff with contracts awaiting signature.
 *
 * @return array{sent:int, skipped:int, failed:int, errors:list<string>}
 */
function pcvc_staff_contract_notify_all_pending(mysqli $conn): array
{
    $sql = "
        SELECT a.id
        FROM admins a
        INNER JOIN employment_contracts c ON c.admin_id = a.id
        WHERE c.status = 'pending_signature'
          AND c.signed_at IS NULL
          AND TRIM(COALESCE(c.signed_docx_path, '')) = ''
          AND TRIM(COALESCE(c.signed_pdf_path, '')) = ''
          AND TRIM(COALESCE(c.pdf_path, '')) = ''
          AND (TRIM(COALESCE(c.source_docx_path, '')) <> '' OR TRIM(COALESCE(c.source_pdf_path, '')) <> '')
        ORDER BY a.full_name ASC
    ";
    $res = $conn->query($sql);
    $out = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
    if (!$res) {
        $out['errors'][] = 'Database query failed';
        $out['failed'] = 1;

        return $out;
    }

    while ($row = $res->fetch_assoc()) {
        $staffId = (int) ($row['id'] ?? 0);
        if ($staffId <= 0) {
            continue;
        }
        $one = pcvc_staff_contract_notify_staff_pending($conn, $staffId);
        if (!empty($one['skipped'])) {
            $out['skipped']++;
            continue;
        }
        if (!empty($one['ok'])) {
            $out['sent']++;
            continue;
        }
        $out['failed']++;
        $out['errors'][] = 'Staff #' . $staffId . ': ' . ($one['error'] ?? 'Send failed');
    }
    $res->free();

    return $out;
}
