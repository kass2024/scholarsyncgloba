<?php
declare(strict_types=1);

function pcvc_staff_contract_upload_dir(): string
{
    return dirname(__DIR__) . '/uploads/staff_contracts';
}

function pcvc_staff_contract_ensure_dirs(): void
{
    $base = pcvc_staff_contract_upload_dir();
    foreach ([$base, $base . '/source', $base . '/generated', $base . '/signed', $base . '/signatures'] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create staff contract directory.');
        }
    }
}

/**
 * @throws RuntimeException
 */
function pcvc_staff_contract_assert_writable_dirs(): void
{
    pcvc_staff_contract_ensure_dirs();
    $base = pcvc_staff_contract_upload_dir();
    foreach ([$base, $base . '/source', $base . '/generated', $base . '/signed', $base . '/signatures'] as $dir) {
        if (!is_writable($dir)) {
            throw new RuntimeException(
                'Contract upload folder is not writable: ' . $dir . '. Fix folder permissions on the server.'
            );
        }
    }
}

/**
 * Human-readable PHP upload error.
 */
function pcvc_staff_contract_upload_error_message(int $code): string
{
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return 'Contract file is too large for server limits (upload_max_filesize / post_max_size).';
    }
    if ($code === UPLOAD_ERR_PARTIAL) {
        return 'Contract upload was interrupted. Please try again.';
    }
    if ($code === UPLOAD_ERR_NO_FILE) {
        return 'Please choose a Word contract file (.docx).';
    }
    if ($code === UPLOAD_ERR_NO_TMP_DIR) {
        return 'Server temp folder missing. Contact hosting support.';
    }
    if ($code === UPLOAD_ERR_CANT_WRITE) {
        return 'Server could not write the uploaded file to disk.';
    }
    if ($code === UPLOAD_ERR_EXTENSION) {
        return 'Server blocked this upload type.';
    }
    return 'Contract upload failed (error code ' . $code . ').';
}

function pcvc_staff_contract_ensure_schema(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS employment_contracts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id INT UNSIGNED NOT NULL,
            template_id INT UNSIGNED NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending_signature',
            source_docx_path VARCHAR(500) NULL,
            filled_docx_path VARCHAR(500) NULL,
            source_pdf_path VARCHAR(500) NULL,
            signed_pdf_path VARCHAR(500) NULL,
            signed_docx_path VARCHAR(500) NULL,
            pdf_path VARCHAR(500) NULL,
            contract_title VARCHAR(255) NULL,
            staff_typed_name VARCHAR(255) NULL,
            signature_file VARCHAR(500) NULL,
            uploaded_by INT UNSIGNED NULL,
            uploaded_at DATETIME NULL,
            signed_at DATETIME NULL,
            signed_ip VARCHAR(64) NULL,
            field_layout LONGTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_employment_contract_admin (admin_id),
            KEY idx_employment_contract_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $columns = [
        'source_docx_path' => "VARCHAR(500) NULL AFTER status",
        'filled_docx_path' => "VARCHAR(500) NULL AFTER source_docx_path",
        'source_pdf_path' => "VARCHAR(500) NULL AFTER filled_docx_path",
        'signed_pdf_path' => "VARCHAR(500) NULL AFTER source_pdf_path",
        'signed_docx_path' => "VARCHAR(500) NULL AFTER signed_pdf_path",
        'contract_title' => "VARCHAR(255) NULL AFTER pdf_path",
        'staff_typed_name' => "VARCHAR(255) NULL AFTER contract_title",
        'signature_file' => "VARCHAR(500) NULL AFTER staff_typed_name",
        'uploaded_by' => "INT UNSIGNED NULL AFTER signature_file",
        'uploaded_at' => "DATETIME NULL AFTER uploaded_by",
        'field_layout' => "LONGTEXT NULL AFTER signed_ip",
        'updated_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    ];

    $existing = [];
    $res = $conn->query('SHOW COLUMNS FROM employment_contracts');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $existing[(string) ($row['Field'] ?? '')] = true;
        }
        $res->free();
    }

    foreach ($columns as $name => $definition) {
        if (!isset($existing[$name])) {
            $def = preg_replace('/\s+AFTER\s+[`\w]+/i', '', $definition);
            $conn->query("ALTER TABLE employment_contracts ADD COLUMN `{$name}` {$def}");
        }
    }
}

/**
 * @return array<string, mixed>|null
 */
function pcvc_staff_contract_for_admin(mysqli $conn, int $adminId): ?array
{
    pcvc_staff_contract_ensure_schema($conn);
    $stmt = $conn->prepare('SELECT * FROM employment_contracts WHERE admin_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * @return array<string, mixed>
 */
function pcvc_staff_contract_has_template(?array $row): bool
{
    if (!$row) {
        return false;
    }
    if (trim((string) ($row['source_docx_path'] ?? '')) !== '') {
        return true;
    }
    return trim((string) ($row['source_pdf_path'] ?? '')) !== '';
}

function pcvc_staff_contract_row_status(?array $row): array
{
    if (!pcvc_staff_contract_has_template($row)) {
        return ['code' => 'no_contract', 'label' => 'No contract uploaded', 'badge' => 'secondary'];
    }

    $signedDocx = trim((string) ($row['signed_docx_path'] ?? ''));
    $signedPdf = trim((string) ($row['signed_pdf_path'] ?? $row['pdf_path'] ?? ''));
    $signedAt = trim((string) ($row['signed_at'] ?? ''));
    $status = (string) ($row['status'] ?? '');

    if ($status === 'signed' || ($signedAt !== '' && ($signedDocx !== '' || $signedPdf !== ''))) {
        if ($signedDocx !== '' || $signedPdf !== '' || $signedAt !== '') {
            return ['code' => 'signed', 'label' => 'Signed', 'badge' => 'success'];
        }
    }

    return ['code' => 'pending_signature', 'label' => 'Awaiting signature', 'badge' => 'warning'];
}

/**
 * True when staff has an uploaded contract that still needs e-signature.
 */
function pcvc_staff_contract_is_awaiting_signature(?array $row): bool
{
    return pcvc_staff_contract_row_status($row)['code'] === 'pending_signature';
}

function pcvc_staff_contract_signed_path(array $row): string
{
    $path = trim((string) ($row['signed_pdf_path'] ?? ''));
    if ($path !== '') {
        return $path;
    }
    return trim((string) ($row['pdf_path'] ?? ''));
}

function pcvc_staff_contract_signed_docx_path(array $row): string
{
    return trim((string) ($row['signed_docx_path'] ?? ''));
}

function pcvc_staff_contract_preview_docx_path(array $row): string
{
    return trim((string) ($row['filled_docx_path'] ?? ''));
}

/**
 * Detect DOCX files truncated by the old signature-embed bug (only last page left).
 */
function pcvc_staff_contract_docx_is_corrupt(string $docxAbs): bool
{
    if (!is_file($docxAbs)) {
        return true;
    }
    $zip = new ZipArchive();
    if ($zip->open($docxAbs) !== true) {
        return true;
    }
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === '') {
        return true;
    }
    if (strlen($xml) < 50000) {
        return true;
    }
    if (strpos($xml, 'EMPLOYEE PROBATION AGREEMENT') === false) {
        return true;
    }
    return substr_count($xml, '<w:p ') < 100;
}

function pcvc_staff_contract_abs_path(string $relative): string
{
    return dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $relative), '/');
}

/**
 * Remove all PDF/signature files linked to a contract row.
 *
 * @param array<string, mixed>|null $contract
 */
function pcvc_staff_contract_remove_files(?array $contract): void
{
    if (!$contract) {
        return;
    }

    $paths = [
        trim((string) ($contract['source_docx_path'] ?? '')),
        trim((string) ($contract['filled_docx_path'] ?? '')),
        trim((string) ($contract['source_pdf_path'] ?? '')),
        trim((string) ($contract['signed_pdf_path'] ?? '')),
        trim((string) ($contract['signed_docx_path'] ?? '')),
        trim((string) ($contract['pdf_path'] ?? '')),
        trim((string) ($contract['signature_file'] ?? '')),
    ];

    foreach ($paths as $rel) {
        if ($rel === '') {
            continue;
        }
        $abs = pcvc_staff_contract_abs_path($rel);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}

/**
 * Delete contract files and remove the database row for a staff member.
 */
function pcvc_staff_contract_delete_for_admin(mysqli $conn, int $adminId): bool
{
    pcvc_staff_contract_ensure_schema($conn);
    $contract = pcvc_staff_contract_for_admin($conn, $adminId);
    if (!$contract) {
        return false;
    }

    pcvc_staff_contract_remove_files($contract);

    $stmt = $conn->prepare('DELETE FROM employment_contracts WHERE admin_id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Database error');
    }
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    return $deleted;
}
