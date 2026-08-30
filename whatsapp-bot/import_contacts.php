<?php
declare(strict_types=1);

require_once "config.php";
require_once __DIR__ . "/../vendor/autoload.php";
session_start();

use PhpOffice\PhpSpreadsheet\IOFactory;

ini_set('display_errors', 0);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ==========================================================
   CONFIG
========================================================== */
$MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
$ALLOWED_EXT   = ['xlsx', 'xls', 'csv'];

/* ==========================================================
   HELPERS
========================================================== */
function redirectWithMessage(string $msg, string $type = "success"): void {
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type']    = $type;
    $_SESSION['active_tab']    = 'bulkUpload'; // stay on correct tab
    header("Location: broadcast_create.php");
    exit;
}

function logImport(string $message): void {
    file_put_contents(
        __DIR__ . "/import.log",
        "[" . date("Y-m-d H:i:s") . "] " . $message . PHP_EOL,
        FILE_APPEND
    );
}

try {

    /* ======================================================
       VALIDATE REQUEST
    ======================================================= */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        throw new Exception("Security validation failed (CSRF).");
    }

    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload failed.");
    }

    if ($_FILES['excel_file']['size'] > $MAX_FILE_SIZE) {
        throw new Exception("File exceeds 5MB limit.");
    }

    $fileName = $_FILES['excel_file']['name'];
    $fileTmp  = $_FILES['excel_file']['tmp_name'];
    $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($ext, $ALLOWED_EXT, true)) {
        throw new Exception("Invalid file type. Allowed: xlsx, xls, csv.");
    }

    /* ======================================================
       LOAD FILE
    ======================================================= */
    $spreadsheet = IOFactory::load($fileTmp);
    $sheet = $spreadsheet->getActiveSheet();
    $rows  = $sheet->toArray(null, true, true, true);

    if (count($rows) < 2) {
        throw new Exception("Excel file is empty.");
    }

    /* ======================================================
       VALIDATE HEADER STRICTLY
    ======================================================= */
    $header = array_map('strtolower', array_map('trim', $rows[1]));

    if (($header['A'] ?? '') !== 'name' || ($header['B'] ?? '') !== 'phone') {
        throw new Exception("Invalid template. First row must be: name | phone | segment");
    }

    /* ======================================================
       DATABASE
    ======================================================= */
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
    $conn->begin_transaction();

    $checkStmt = $conn->prepare("SELECT id FROM contacts WHERE phone = ?");
    $insertStmt = $conn->prepare("
        INSERT INTO contacts
        (name, phone, segment, opted_in, status, created_at)
        VALUES (?, ?, ?, 1, 'active', NOW())
    ");

    $inserted   = 0;
    $duplicates = 0;
    $invalid    = 0;

    /* ======================================================
       PROCESS ROWS
    ======================================================= */
    foreach ($rows as $index => $row) {

        if ($index === 1) continue;

        $name    = trim((string)($row['A'] ?? ''));
        $phone   = preg_replace('/[^0-9]/', '', (string)($row['B'] ?? ''));
        $segment = trim((string)($row['C'] ?? 'general'));

        if (!$name || !preg_match('/^[0-9]{10,15}$/', $phone)) {
            $invalid++;
            continue;
        }

        /* ===== CHECK DUPLICATE ===== */
        $checkStmt->bind_param("s", $phone);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $duplicates++;
            continue;
        }

        /* ===== INSERT ===== */
        $insertStmt->bind_param("sss", $name, $phone, $segment);
        $insertStmt->execute();

        if ($insertStmt->affected_rows > 0) {
            $inserted++;
        }
    }

    $checkStmt->close();
    $insertStmt->close();
    $conn->commit();
    $conn->close();

    $message = "Imported: $inserted | Duplicates: $duplicates | Invalid: $invalid";
    logImport($message);

    redirectWithMessage($message);

} catch (mysqli_sql_exception $dbError) {

    if (isset($conn) && $conn->errno === 0) {
        $conn->rollback();
    }

    if ($dbError->getCode() === 1062) {
        redirectWithMessage("Duplicate phone detected in database.", "danger");
    }

    logImport("DB ERROR: " . $dbError->getMessage());
    redirectWithMessage("Database error occurred.", "danger");

} catch (Throwable $e) {

    if (isset($conn) && $conn->errno === 0) {
        $conn->rollback();
    }

    logImport("GENERAL ERROR: " . $e->getMessage());
    redirectWithMessage($e->getMessage(), "danger");
}