<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
@set_time_limit(300);

ob_start();

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
        return;
    }
    if (ob_get_length()) {
        ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $err['message'] . ' (' . basename((string) $err['file']) . ':' . $err['line'] . ')',
    ]);
});

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/role.php';
require_once __DIR__ . '/../helpers/staff_contract_schema.php';

header('Content-Type: application/json; charset=utf-8');

function pcvc_upload_json_error(string $message, int $code = 500): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    pcvc_require_superadmin($conn, true);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        pcvc_upload_json_error('Invalid request', 405);
    }

    if (!class_exists('ZipArchive')) {
        pcvc_upload_json_error('PHP Zip extension (ext-zip) is required for contract uploads.');
    }

    $staffId = (int) ($_POST['staff_id'] ?? 0);
    if ($staffId <= 0) {
        pcvc_upload_json_error('Missing staff member', 400);
    }

    $stmt = $conn->prepare('SELECT id, full_name, role, email FROM admins WHERE id = ? LIMIT 1');
    if (!$stmt) {
        pcvc_upload_json_error('Database error');
    }
    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$staff) {
        pcvc_upload_json_error('Staff member not found', 404);
    }

    $fileKey = isset($_FILES['contract_docx']) ? 'contract_docx' : 'contract_pdf';
    if (!isset($_FILES[$fileKey])) {
        pcvc_upload_json_error('No file received. If the file is large, increase post_max_size and upload_max_filesize on the server.');
    }

    $uploadErr = (int) ($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadErr !== UPLOAD_ERR_OK) {
        pcvc_upload_json_error(pcvc_staff_contract_upload_error_message($uploadErr), 400);
    }

    $ext = strtolower(pathinfo((string) $_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
    if ($ext !== 'docx') {
        pcvc_upload_json_error('Only Word .docx contract templates are allowed', 400);
    }
    if ((int) $_FILES[$fileKey]['size'] > 25 * 1024 * 1024) {
        pcvc_upload_json_error('Contract file must be 25MB or less', 400);
    }

    pcvc_staff_contract_ensure_schema($conn);
    try {
        pcvc_staff_contract_assert_writable_dirs();
    } catch (Throwable $dirError) {
        pcvc_staff_contract_ensure_dirs();
    }

    $safeName = preg_replace('/[^A-Za-z0-9.\-_]+/', '_', basename((string) $_FILES[$fileKey]['name']));
    $stored = 'staff_' . $staffId . '_' . time() . '_' . $safeName;
    $docxRel = 'uploads/staff_contracts/source/' . $stored;
    $docxAbs = pcvc_staff_contract_abs_path($docxRel);

    if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $docxAbs)) {
        pcvc_upload_json_error('Could not save uploaded Word contract. Check uploads/staff_contracts folder permissions.');
    }

    $templateWarning = '';
    $wordHelper = __DIR__ . '/../helpers/staff_contract_word.php';
    if (!is_file($wordHelper)) {
        pcvc_upload_json_error('Contract helper missing on server. Deploy helpers/staff_contract_word.php');
    }
    require_once $wordHelper;
    $templateWarning = pcvc_staff_contract_ensure_rich_template($docxAbs);

    $title = trim((string) ($_POST['contract_title'] ?? ''));
    if ($title === '') {
        $title = pathinfo($safeName, PATHINFO_FILENAME);
    }

    $uploaderId = (int) ($_SESSION['id'] ?? $_SESSION['admin_id'] ?? 0);
    $existing = pcvc_staff_contract_for_admin($conn, $staffId);

    if ($existing) {
        pcvc_staff_contract_remove_files($existing);

        $sql = "UPDATE employment_contracts
                SET source_docx_path = ?, filled_docx_path = NULL, source_pdf_path = NULL,
                    signed_pdf_path = NULL, signed_docx_path = NULL, pdf_path = NULL,
                    contract_title = ?, status = 'pending_signature',
                    staff_typed_name = NULL, signature_file = NULL, field_layout = NULL,
                    signed_at = NULL, signed_ip = NULL,
                    uploaded_by = NULLIF(?, 0), uploaded_at = NOW()
                WHERE admin_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Database error');
        }
        $stmt->bind_param('ssii', $docxRel, $title, $uploaderId, $staffId);
        $stmt->execute();
        $stmt->close();
        $contract = pcvc_staff_contract_for_admin($conn, $staffId);
    } else {
        $sql = "INSERT INTO employment_contracts
                (admin_id, status, source_docx_path, contract_title, uploaded_by, uploaded_at)
                VALUES (?, 'pending_signature', ?, ?, NULLIF(?, 0), NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Database error');
        }
        $stmt->bind_param('issi', $staffId, $docxRel, $title, $uploaderId);
        $stmt->execute();
        $stmt->close();
        $contract = pcvc_staff_contract_for_admin($conn, $staffId);
    }

    if (!$contract) {
        throw new RuntimeException('Could not save contract record');
    }

    $message = 'Word contract uploaded for ' . ($staff['full_name'] ?? 'staff') . '.';
    if ($templateWarning !== '') {
        $message .= ' ' . $templateWarning;
    }

    $previewWarning = '';
    try {
        $preview = pcvc_staff_contract_generate_preview($conn, $staffId, $contract, null, false);
        $message .= ' Employee details were auto-filled into the Word contract.';
        $message .= ' A reminder email will be sent in the background when possible.';
        if (!empty($preview['position_warning'])) {
            $message .= $preview['position_warning'];
        }
    } catch (Throwable $previewError) {
        $previewWarning = $previewError->getMessage();
        $message .= ' Template saved, but auto-fill failed: ' . $previewWarning
            . ' Use Regenerate PDF after fixing the issue.';
    }

    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'preview_warning' => $previewWarning,
    ]);

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    $emailNote = '';
    try {
        require_once __DIR__ . '/../helpers/staff_contract_notify.php';
        $notify = pcvc_staff_contract_notify_staff_pending($conn, $staffId);
        if (!empty($notify['ok']) && empty($notify['skipped'])) {
            error_log('Staff contract upload: reminder email sent to ' . ($notify['email'] ?? 'staff'));
        } elseif (empty($notify['ok'])) {
            error_log('Staff contract upload: email reminder failed: ' . ($notify['error'] ?? 'unknown error'));
        }
    } catch (Throwable $notifyError) {
        error_log('Staff contract upload: email reminder failed: ' . $notifyError->getMessage());
    }
    exit;
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_clean();
    }
    pcvc_upload_json_error($e->getMessage());
}
