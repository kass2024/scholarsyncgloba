<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/role.php';
require_once __DIR__ . '/../helpers/staff_contract_schema.php';
require_once __DIR__ . '/../helpers/staff_contract_word.php';

$viewerId = (int) ($_SESSION['id'] ?? $_SESSION['admin_id'] ?? 0);
if ($viewerId <= 0) {
    http_response_code(401);
    exit('Unauthorized');
}

$staffId = (int) ($_GET['staff_id'] ?? $viewerId);
$type = ($_GET['type'] ?? 'source') === 'signed' ? 'signed' : 'source';
$isSuper = pcvc_current_user_is_superadmin($conn);

if (!$isSuper && $staffId !== $viewerId) {
    http_response_code(403);
    exit('Forbidden');
}

$contract = pcvc_staff_contract_for_admin($conn, $staffId);
if (!$contract) {
    http_response_code(404);
    exit('Contract not found');
}

$rel = $type === 'signed'
    ? pcvc_staff_contract_signed_path($contract)
    : trim((string) ($contract['source_pdf_path'] ?? ''));

if ($rel === '' && pcvc_staff_contract_use_docx_preview()) {
    http_response_code(404);
    exit('PDF not used on this server — open the Word contract viewer instead.');
}

if ($rel === '' && $type === 'source' && trim((string) ($contract['source_docx_path'] ?? '')) !== '') {
    @set_time_limit(300);
    try {
        pcvc_staff_contract_generate_preview($conn, $staffId, $contract, null, true);
        $contract = pcvc_staff_contract_for_admin($conn, $staffId);
        if ($contract) {
            $rel = trim((string) ($contract['source_pdf_path'] ?? ''));
        }
    } catch (Throwable $e) {
        http_response_code(503);
        exit('PDF not ready: ' . $e->getMessage());
    }
}

if ($rel === '') {
    http_response_code(404);
    exit('PDF not available');
}

$abs = pcvc_staff_contract_abs_path($rel);
if (!is_file($abs)) {
    http_response_code(404);
    exit('PDF file missing');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="staff-contract.pdf"');
header('Content-Length: ' . (string) filesize($abs));
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($abs);
exit;
