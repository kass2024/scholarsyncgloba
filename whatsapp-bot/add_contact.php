<?php
declare(strict_types=1);

require_once "config.php";
session_start();

ini_set('display_errors', 0);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ==========================================================
   HELPERS
========================================================== */
function redirectWithMessage(string $msg, string $type = "success"): void {
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type'] = $type;
    $_SESSION['active_tab'] = 'addContact'; // stay on correct tab
    header("Location: broadcast_create.php");
    exit;
}

function logError(string $message): void {
    file_put_contents(
        __DIR__ . "/contact_errors.log",
        "[" . date("Y-m-d H:i:s") . "] " . $message . PHP_EOL,
        FILE_APPEND
    );
}

try {

    /* ======================================================
       REQUEST VALIDATION
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

    if (empty($_POST['name']) || empty($_POST['phone'])) {
        throw new Exception("Name and phone are required.");
    }

    $name    = trim($_POST['name']);
    $segment = trim($_POST['segment'] ?? 'general');

    if (strlen($name) > 100) {
        throw new Exception("Name too long.");
    }

    /* ======================================================
       NORMALIZE & VALIDATE PHONE
    ======================================================= */
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);

    if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        throw new Exception("Invalid phone format. Use 10–15 digits only.");
    }

    /* ======================================================
       DATABASE
    ======================================================= */
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
    $conn->begin_transaction();

    /* ======================================================
       CHECK DUPLICATE
    ======================================================= */
    $checkStmt = $conn->prepare("SELECT id FROM contacts WHERE phone = ?");
    $checkStmt->bind_param("s", $phone);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $checkStmt->close();
        $conn->rollback();
        redirectWithMessage("This phone number already exists.", "danger");
    }
    $checkStmt->close();

    /* ======================================================
       INSERT CONTACT
    ======================================================= */
    $insertStmt = $conn->prepare("
        INSERT INTO contacts
        (name, phone, segment, opted_in, status, created_at)
        VALUES (?, ?, ?, 1, 'active', NOW())
    ");

    $insertStmt->bind_param("sss", $name, $phone, $segment);
    $insertStmt->execute();
    $insertStmt->close();

    $conn->commit();
    $conn->close();

    redirectWithMessage("Contact added successfully ✔");

} catch (mysqli_sql_exception $dbError) {

    if (isset($conn) && $conn->errno === 0) {
        $conn->rollback();
    }

    if ($dbError->getCode() === 1062) {
        redirectWithMessage("This phone number already exists.", "danger");
    }

    logError("DB ERROR: " . $dbError->getMessage());
    redirectWithMessage("Database error occurred.", "danger");

} catch (Throwable $e) {

    if (isset($conn) && $conn->errno === 0) {
        $conn->rollback();
    }

    logError("GENERAL ERROR: " . $e->getMessage());
    redirectWithMessage($e->getMessage(), "danger");
}