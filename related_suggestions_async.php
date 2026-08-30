<?php
declare(strict_types=1);

/**
 * Background worker: related program matching + admin digest emails.
 * Invoked fire-and-forget from final submit so the student UI is not blocked.
 */
ignore_user_abort(true);
@set_time_limit(180);
@ini_set('max_execution_time', '180');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/helpers/db.php';
require_once __DIR__ . '/helpers/env_bootstrap.php';
require_once __DIR__ . '/helpers/related_program_suggestions.php';
require_once __DIR__ . '/helpers/staff_assignment_notify.php';
require_once __DIR__ . '/helpers/study_choice_admin_actions.php';

$applicationId = (int) ($_POST['application_id'] ?? $_GET['application_id'] ?? 0);
$forceRenotify = ((string) ($_POST['force_renotify'] ?? $_GET['force_renotify'] ?? '1')) === '1';
$useAi = ((string) ($_POST['use_ai'] ?? $_GET['use_ai'] ?? '1')) !== '0';
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');

if ($applicationId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid application_id']);
    exit;
}

$expected = pcvc_related_suggestions_async_token($applicationId);
if ($token === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

// Acknowledge quickly so the parent curl can time out cleanly
echo json_encode(['ok' => true, 'accepted' => true, 'application_id' => $applicationId]);
if (function_exists('fastcgi_finish_request')) {
    @fastcgi_finish_request();
} else {
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }
    @ob_end_flush();
    @flush();
}

$result = ['assignee_id' => 0, 'related' => null, 'staff_notified' => false];

try {
    $result['assignee_id'] = pcvc_ensure_application_assignee_from_university_admins($conn, $applicationId);

    try {
        pcvc_notify_assigned_staff_application_submitted($conn, $applicationId);
        $result['staff_notified'] = true;
    } catch (Throwable $e) {
        $result['staff_error'] = $e->getMessage();
    }

    if ($result['assignee_id'] > 0) {
        try {
            pcvc_ensure_assignment_jobs_for_application($conn, $applicationId, $result['assignee_id']);
        } catch (Throwable $e) {
            $result['jobs_error'] = $e->getMessage();
        }
    }

    $result['related'] = pcvc_process_related_university_suggestions(
        $conn,
        $applicationId,
        $forceRenotify,
        $useAi
    );
} catch (Throwable $e) {
    $result['error'] = $e->getMessage();
}

if (function_exists('debug_log')) {
    debug_log('RELATED SUGGESTIONS ASYNC DONE', $result);
}

exit;
