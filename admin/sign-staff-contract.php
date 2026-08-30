<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/staff_contract_schema.php';
require_once __DIR__ . '/../helpers/staff_contract_word.php';
require_once __DIR__ . '/../helpers/staff_contract_pdf.php';
require_once __DIR__ . '/../helpers/contract_signature_image.php';

header('Content-Type: application/json; charset=utf-8');

pcvc_staff_contract_ensure_schema($conn);

$adminId = (int) ($_SESSION['id'] ?? $_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$typedName = trim((string) ($data['typed_name'] ?? ''));
$signature = trim((string) ($data['signature'] ?? ''));
$signedDate = trim((string) ($data['signed_date'] ?? date('Y-m-d')));

if ($typedName === '' || strlen($typedName) < 2) {
    echo json_encode(['success' => false, 'message' => 'Please enter your full name']);
    exit;
}
if ($signature === '' || contract_signature_raw_bytes($signature) === null) {
    echo json_encode(['success' => false, 'message' => 'Please draw your signature']);
    exit;
}
if ($signedDate === '') {
    echo json_encode(['success' => false, 'message' => 'Please choose the signing date']);
    exit;
}

$contract = pcvc_staff_contract_for_admin($conn, $adminId);
if (!$contract) {
    echo json_encode(['success' => false, 'message' => 'No contract assigned to you yet']);
    exit;
}
if (($contract['status'] ?? '') === 'signed') {
    echo json_encode(['success' => false, 'message' => 'Contract already signed']);
    exit;
}

try {
    pcvc_staff_contract_ensure_dirs();

    $hasDocx = trim((string) ($contract['source_docx_path'] ?? '')) !== '';
    if ($hasDocx) {
        $signed = pcvc_staff_contract_generate_signed(
            $conn,
            $adminId,
            $contract,
            $signature,
            $typedName,
            $signedDate
        );
        $signedRel = $signed['pdf'] ?? '';
        $signedDocxRel = $signed['docx'];
    } else {
        $sourceRel = trim((string) ($contract['source_pdf_path'] ?? ''));
        if ($sourceRel === '') {
            echo json_encode(['success' => false, 'message' => 'Contract is not ready yet']);
            exit;
        }
        $sourceAbs = pcvc_staff_contract_abs_path($sourceRel);
        $signedName = 'signed_staff_' . $adminId . '_' . time() . '.pdf';
        $signedRel = 'uploads/staff_contracts/signed/' . $signedName;
        $signedAbs = pcvc_staff_contract_abs_path($signedRel);
        pcvc_staff_contract_build_signed_pdf($sourceAbs, $signature, $typedName, $signedDate, $signedAbs);
        $signedDocxRel = '';
    }

    $sigRel = 'uploads/staff_contracts/signatures/signature_' . $adminId . '_' . time() . '.png';
    $sigAbs = pcvc_staff_contract_abs_path($sigRel);
    $sigPng = contract_signature_to_display_png($signature) ?? contract_signature_raw_bytes($signature);
    if ($sigPng) {
        file_put_contents($sigAbs, $sigPng);
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $contractId = (int) ($contract['id'] ?? 0);
    $stmt = $conn->prepare(
        "UPDATE employment_contracts
         SET status = 'signed',
             signed_docx_path = ?,
             signed_pdf_path = ?,
             pdf_path = ?,
             staff_typed_name = ?,
             signature_file = ?,
             field_layout = NULL,
             signed_at = NOW(),
             signed_ip = ?
         WHERE admin_id = ? AND id = ?"
    );
    if (!$stmt) {
        throw new RuntimeException('Database error');
    }
    $signedDocxRel = $signedDocxRel ?? '';
    $stmt->bind_param('ssssssii', $signedDocxRel, $signedRel, $signedRel, $typedName, $sigRel, $ip, $adminId, $contractId);
    $stmt->execute();
    $stmt->close();

    if (table_exists($conn, 'contract_audit_logs')) {
        $log = $conn->prepare("INSERT INTO contract_audit_logs (contract_id, action, ip_address) VALUES (?, 'SIGNED', ?)");
        if ($log) {
            $log->bind_param('is', $contractId, $ip);
            $log->execute();
            $log->close();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Contract signed successfully. You can download your signed copy now.',
        'download_url' => 'download-staff-contract.php?type=signed'
            . (pcvc_staff_contract_use_docx_preview() ? '&format=docx' : ''),
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    return $ok;
}
