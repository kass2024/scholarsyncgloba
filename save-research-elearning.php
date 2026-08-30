<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/research_elearning_schema.php';

header('Content-Type: application/json; charset=utf-8');

$sourceId = (int) ($_POST['student_id'] ?? $_POST['credit_transfer_id'] ?? 0);
$sourceTable = pcvc_research_elearning_normalize_source((string) ($_POST['source_table'] ?? 'credit_transfer_applications'));
if ($sourceId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing student id']);
    exit;
}

$adminId = (int) ($_SESSION['id'] ?? $_SESSION['admin_id'] ?? 0);
$row = pcvc_research_elearning_get_or_create($conn, $sourceTable, $sourceId, $adminId > 0 ? $adminId : null);
if (!$row || empty($row['id'])) {
    echo json_encode(['success' => false, 'message' => 'Could not load research record']);
    exit;
}

$recordId = (int) $row['id'];
$allowedFields = array_keys(pcvc_research_elearning_doc_fields());
$allowedExtensions = ['pdf', 'doc', 'docx'];
$maxSize = 25 * 1024 * 1024;
$uploadDir = __DIR__ . '/uploads/research_elearning/' . date('Y/m');
$uploadRelBase = 'uploads/research_elearning/' . date('Y/m');

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

/**
 * @return array{ok: bool, path?: string, message?: string}
 */
function save_research_upload(string $inputName, string $uploadDir, string $uploadRelBase, array $allowedExtensions, int $maxSize): array
{
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'message' => 'No file'];
    }
    if ($_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Upload failed'];
    }

    $original = (string) ($_FILES[$inputName]['name'] ?? '');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        return ['ok' => false, 'message' => 'Only PDF and Word documents are allowed'];
    }
    if ((int) ($_FILES[$inputName]['size'] ?? 0) > $maxSize) {
        return ['ok' => false, 'message' => 'File exceeds 25MB limit'];
    }

    $safe = preg_replace('/[^A-Za-z0-9.\-_]/', '_', basename($original));
    $stored = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safe;
    $abs = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $stored;
    if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $abs)) {
        return ['ok' => false, 'message' => 'Could not save file'];
    }

    return ['ok' => true, 'path' => rtrim($uploadRelBase, '/') . '/' . $stored];
}

$updates = [];
$types = '';
$values = [];
$messages = [];
$uploadedFiles = [];

foreach ($allowedFields as $field) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    $saved = save_research_upload($field, $uploadDir, $uploadRelBase, $allowedExtensions, $maxSize);
    if (!$saved['ok']) {
        echo json_encode(['success' => false, 'message' => $saved['message'] ?? 'Upload failed']);
        exit;
    }

    $oldPath = trim((string) ($row[$field] ?? ''));
    if ($oldPath !== '') {
        $oldAbs = __DIR__ . '/' . ltrim($oldPath, '/');
        if (is_file($oldAbs)) {
            @unlink($oldAbs);
        }
    }

    $updates[] = "{$field} = ?";
    $types .= 's';
    $values[] = $saved['path'];
    $row[$field] = $saved['path'];
    $uploadedFiles[] = $field;
    $messages[] = pcvc_research_elearning_doc_fields()[$field] . ' saved';
}

$overallStatus = trim((string) ($_POST['overall_status'] ?? ''));
$allowedStatuses = ['not_started', 'in_progress', 'submitted', 'completed', 'on_hold'];

if (array_key_exists('admin_notes', $_POST)) {
    $updates[] = 'admin_notes = ?';
    $types .= 's';
    $values[] = trim((string) $_POST['admin_notes']);
}

$progressPreview = pcvc_research_elearning_compute_status($row);
$autoStatus = (string) ($progressPreview['auto_status'] ?? 'not_started');

if ($overallStatus !== '' && in_array($overallStatus, $allowedStatuses, true)) {
    $finalStatus = $overallStatus;
} else {
    $finalStatus = $autoStatus;
}

if ($uploadedFiles && $finalStatus === 'not_started') {
    $finalStatus = 'in_progress';
}
if ((int) ($progressPreview['uploaded_count'] ?? 0) >= (int) ($progressPreview['total_count'] ?? 6)) {
    $finalStatus = 'completed';
}

$updates[] = 'overall_status = ?';
$types .= 's';
$values[] = $finalStatus;

$updates[] = 'updated_by = NULLIF(?, 0)';
$types .= 'i';
$values[] = $adminId > 0 ? $adminId : 0;

$updates[] = 'last_status_check_at = NOW()';

$conn->begin_transaction();

try {
    $sql = 'UPDATE credit_transfer_research_elearning SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $types .= 'i';
    $values[] = $recordId;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Database error');
    }

    $stmt->bind_param($types, ...$values);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Save failed');
    }
    $stmt->close();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    foreach ($uploadedFiles as $field) {
        $path = trim((string) ($row[$field] ?? ''));
        if ($path !== '') {
            $abs = __DIR__ . '/' . ltrim($path, '/');
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$fresh = pcvc_research_elearning_get_or_create($conn, $sourceTable, $sourceId, $adminId > 0 ? $adminId : null);
$payload = $fresh ? pcvc_research_elearning_build_payload($fresh) : ['record' => [], 'documents' => [], 'progress' => []];
$listStatus = pcvc_research_elearning_list_status([
    'uploaded_count' => (int) ($payload['progress']['uploaded_count'] ?? 0),
    'total_count' => (int) ($payload['progress']['total_count'] ?? 6),
    'overall_status' => (string) ($payload['record']['overall_status'] ?? 'not_started'),
]);

$message = $messages
    ? implode('; ', $messages) . ' — saved to database.'
    : 'Status saved to database.';

echo json_encode([
    'success' => true,
    'message' => $message,
    'overall_status' => (string) ($payload['record']['overall_status'] ?? $finalStatus),
    'status_label' => $listStatus['label'],
    'status_badge' => $listStatus['badge'],
] + $payload);

$conn->close();
