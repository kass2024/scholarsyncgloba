<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../helpers/staff_contract_word.php';

$checks = [
    'php_version' => PHP_VERSION,
    'zip_extension' => class_exists('ZipArchive'),
    'vendor_autoload' => is_file(__DIR__ . '/../vendor/autoload.php'),
    'word_helper' => is_file(__DIR__ . '/../helpers/staff_contract_word.php'),
    'canonical_template' => is_file(__DIR__ . '/ScholarSync Contract for Mutware.docx'),
    'manager_signature' => is_file(__DIR__ . '/signature-manager.png'),
    'uploads_writable' => is_writable(__DIR__ . '/../uploads') || @mkdir(__DIR__ . '/../uploads/staff_contracts/source', 0775, true),
    'libreoffice_available' => pcvc_staff_contract_libreoffice_available(),
    'docx_preview_mode' => pcvc_staff_contract_use_docx_preview(),
];

$errors = [];
if (!$checks['zip_extension']) {
    $errors[] = 'Install PHP ext-zip';
}
if (!$checks['word_helper']) {
    $errors[] = 'Deploy helpers/staff_contract_word.php';
}
if (!$checks['canonical_template']) {
    $errors[] = 'Deploy admin/ScholarSync Contract for Mutware.docx (company stamp template)';
}
if (!$checks['manager_signature']) {
    $errors[] = 'Deploy admin/signature-manager.png (employer signature image)';
}

$notes = [];
if ($checks['docx_preview_mode']) {
    $notes[] = 'Shared hosting mode: contracts display as Word in the browser (no LibreOffice needed).';
} else {
    $notes[] = 'Server PDF mode: LibreOffice or Microsoft Word converts DOCX to PDF.';
}

echo json_encode([
    'ok' => $errors === [],
    'checks' => $checks,
    'errors' => $errors,
    'notes' => $notes,
]);
