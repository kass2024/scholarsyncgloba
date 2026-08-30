<?php
declare(strict_types=1);

/* ======================================================
   BOOTSTRAP & HEADERS
   AI application processing: related program proposals only
   (platform recommendations removed).
====================================================== */
ob_start();
header("Content-Type: application/json; charset=UTF-8");

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/../helpers/db.php';
require_once "../helpers/response.php";
require_once __DIR__ . '/../helpers/env_bootstrap.php';
require_once __DIR__ . '/../helpers/related_program_suggestions.php';

/* ======================================================
   GLOBAL EXCEPTION HANDLER
====================================================== */
set_exception_handler(static function (Throwable $e): void {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Internal server error"
    ]);
    exit;
});

/* ======================================================
   INPUT VALIDATION
====================================================== */
$applicationId = filter_input(INPUT_GET, 'application_id', FILTER_VALIDATE_INT);
if (!$applicationId) {
    jsonResponse("Invalid application ID", false, 400);
}

/* ======================================================
   CONFIRM APPLICATION HAS STUDY CHOICES
====================================================== */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS cnt
    FROM application_study_choices
    WHERE application_id = ?
");
$stmt->bind_param("i", $applicationId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || (int) ($row['cnt'] ?? 0) <= 0) {
    jsonResponse("No study choices found for this application", false, 404);
}

/* ======================================================
   AI RELATED PROGRAM SCAN → APPROVAL QUEUE
====================================================== */
$relatedScan = ['suggestions' => 0, 'emails' => 0, 'triggered' => false];
$studyChoiceSuggestions = [];
try {
    $relatedScan = pcvc_process_related_university_suggestions($conn, (int) $applicationId);
    $studyChoiceSuggestions = pcvc_fetch_study_choice_suggestions($conn, (int) $applicationId, 'pending');
} catch (Throwable $e) {
    $relatedScan['error'] = $e->getMessage();
}

if (ob_get_length()) {
    ob_clean();
}

jsonResponse([
    "success" => true,
    "related_program_scan" => $relatedScan,
    "study_choice_suggestions" => $studyChoiceSuggestions,
    "pending_count" => count($studyChoiceSuggestions),
]);
