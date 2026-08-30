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

function pcvc_regenerate_json_error(string $message, int $code = 500): void
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
        pcvc_regenerate_json_error('Invalid request', 405);
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    $wordHelper = __DIR__ . '/../helpers/staff_contract_word.php';
    if (!is_file($wordHelper)) {
        pcvc_regenerate_json_error('Contract helper missing on server. Deploy helpers/staff_contract_word.php');
    }
    require_once $wordHelper;

    $needsComposer = !pcvc_staff_contract_use_docx_preview();
    if ($needsComposer) {
        if (!is_file($autoload)) {
            pcvc_regenerate_json_error('Composer dependencies missing on server. Run composer install in the project root.');
        }
        require_once $autoload;
    }

    if (!class_exists('ZipArchive')) {
        pcvc_regenerate_json_error('PHP Zip extension is required for contract regeneration.');
    }

    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $staffId = (int) ($data['staff_id'] ?? 0);
    $mode = (($data['mode'] ?? 'preview') === 'signed') ? 'signed' : 'preview';

    if ($staffId <= 0) {
        pcvc_regenerate_json_error('Missing staff member', 400);
    }

    $contract = pcvc_staff_contract_for_admin($conn, $staffId);
    if (!$contract || trim((string) ($contract['source_docx_path'] ?? '')) === '') {
        pcvc_regenerate_json_error('No Word contract template for this staff member', 404);
    }

    $docxAbs = pcvc_staff_contract_abs_path((string) $contract['source_docx_path']);
    if (!is_file($docxAbs)) {
        pcvc_regenerate_json_error('Stored Word template file is missing on the server. Re-upload the contract.');
    }

    $templateWarning = pcvc_staff_contract_ensure_rich_template($docxAbs);
    $result = pcvc_staff_contract_regenerate($conn, $staffId, $contract, $mode);
    $message = $result['message'];
    if ($templateWarning !== '') {
        $message .= ' ' . $templateWarning;
    }

    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode([
        'success' => true,
        'message' => $message,
    ]);
} catch (Throwable $e) {
    pcvc_regenerate_json_error($e->getMessage());
}
