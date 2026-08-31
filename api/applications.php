<?php
session_start();
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/datetime_utc.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/role.php';
require_once __DIR__ . '/../includes/company_branding.php';
require_once __DIR__ . '/../helpers/application_filters.php';
require_once __DIR__ . '/../helpers/study_choice_admin_actions.php';
require_once __DIR__ . '/../helpers/related_program_suggestions.php';
require_once __DIR__ . '/../helpers/application_documents_admin.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/**
 * ======================================================
 * FILTER OPTIONS (staff list + status labels for dashboards)
 * ======================================================
 */
if ($action === 'filter_options') {
    $staff = [];
    $res = $conn->query("
        SELECT
            id,
            first_name,
            last_name,
            COALESCE(
                NULLIF(TRIM(full_name), ''),
                TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))
            ) AS display_name,
            email,
            phone_number
        FROM admins
        WHERE " . pcvc_sql_assignable_application_owner_condition() . "
        ORDER BY last_name ASC, first_name ASC, id ASC
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $label = trim((string) ($row['display_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            }
            $staff[] = [
                'id' => $id,
                'label' => $label !== '' ? $label : ('Staff #' . $id),
                'email' => trim((string) ($row['email'] ?? '')),
                'phone' => trim((string) ($row['phone_number'] ?? '')),
            ];
        }
    }

    $statuses = [];
    $labels = pcvc_application_status_labels();
    foreach (pcvc_application_status_priority() as $key) {
        $statuses[] = [
            'value' => $key,
            'label' => $labels[$key] ?? $key,
        ];
    }

    jsonResponse([
        'staff' => $staff,
        'statuses' => $statuses,
    ]);
    exit;
}

/**
 * ======================================================
 * ADD STUDY CHOICE (staff/admin — append row + optional jobs + student email)
 * ======================================================
 */
if ($action === 'add_study_choice' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $adminId = 0;
    if (!empty($_SESSION['id'])) {
        $adminId = (int) $_SESSION['id'];
    } elseif (!empty($_SESSION['admin_id'])) {
        $adminId = (int) $_SESSION['admin_id'];
    }
    if ($adminId <= 0) {
        jsonResponse('Unauthorized', false, 401);
    }

    $applicationId = (int) ($_POST['application_id'] ?? 0);
    $regionId = (int) ($_POST['region_id'] ?? 0);
    $universityId = (int) ($_POST['university_id'] ?? 0);
    $levelId = (int) ($_POST['program_level_id'] ?? 0);
    $programId = (int) ($_POST['program_id'] ?? 0);

    if ($applicationId <= 0) {
        jsonResponse('Invalid application id', false, 400);
    }

    $stmt = $conn->prepare('SELECT id FROM student_applications WHERE id = ? LIMIT 1');
    if (!$stmt) {
        jsonResponse('Server error', false, 500);
    }
    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$exists) {
        jsonResponse('Application not found', false, 404);
    }

    $relErr = pcvc_validate_study_choice_relations($conn, $regionId, $universityId, $levelId, $programId);
    if ($relErr !== null) {
        jsonResponse($relErr, false, 422);
    }

    $ins = pcvc_try_insert_application_study_choice(
        $conn,
        $applicationId,
        $regionId,
        $universityId,
        $levelId,
        $programId
    );

    if (!$ins['inserted'] && !$ins['duplicate']) {
        $msg = isset($ins['error']) && $ins['error'] !== ''
            ? $ins['error']
            : 'Could not save study choice.';
        jsonResponse($msg, false, 500);
    }

    $jobsCreated = 0;
    $notified = false;

    if ($ins['inserted']) {
        $assigneeId = 0;
        $stAssign = $conn->prepare(
            'SELECT assigned_to_admin_id FROM student_applications WHERE id = ? LIMIT 1'
        );
        if ($stAssign) {
            $stAssign->bind_param('i', $applicationId);
            $stAssign->execute();
            $assignRow = $stAssign->get_result()->fetch_assoc();
            $stAssign->close();
            if ($assignRow && $assignRow['assigned_to_admin_id'] !== null && $assignRow['assigned_to_admin_id'] !== '') {
                $assigneeId = (int) $assignRow['assigned_to_admin_id'];
            }
        }
        if ($assigneeId > 0) {
            $jobsCreated = pcvc_ensure_assignment_jobs_for_application($conn, $applicationId, $assigneeId);
        }
        $notified = pcvc_notify_student_study_choice_added(
            $conn,
            $applicationId,
            $regionId,
            $universityId,
            $levelId,
            $programId
        );
    }

    $studyChoices = pcvc_fetch_study_choices_for_admin_view($conn, $applicationId);

    // Also scan for related universities when staff manually adds a choice
    $related = ['suggestions' => 0, 'emails' => 0, 'triggered' => false];
    if ($ins['inserted']) {
        try {
            $related = pcvc_process_related_university_suggestions($conn, $applicationId);
        } catch (Throwable $e) {
            $related = ['suggestions' => 0, 'emails' => 0, 'triggered' => false, 'error' => $e->getMessage()];
        }
    }

    jsonResponse([
        'study_choices' => $studyChoices,
        'study_choice_suggestions' => pcvc_fetch_study_choice_suggestions($conn, $applicationId, 'pending'),
        'jobs_created' => $jobsCreated,
        'duplicate' => (bool) $ins['duplicate'],
        'student_notified' => $notified,
        'related_suggestions' => $related,
        'message' => $ins['duplicate']
            ? 'This study choice is already listed for this application.'
            : ($ins['inserted'] ? 'Study choice added.' : ''),
    ]);
    exit;
}

/**
 * ======================================================
 * APPROVE / REJECT related program suggestions
 * ======================================================
 */
if ($action === 'approve_study_choice_suggestion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = 0;
    if (!empty($_SESSION['id'])) {
        $adminId = (int) $_SESSION['id'];
    } elseif (!empty($_SESSION['admin_id'])) {
        $adminId = (int) $_SESSION['admin_id'];
    }
    if ($adminId <= 0) {
        jsonResponse('Unauthorized', false, 401);
    }

    $suggestionId = (int) ($_POST['suggestion_id'] ?? 0);
    if ($suggestionId <= 0) {
        jsonResponse('Invalid suggestion id', false, 400);
    }

    $notifyStudent = !isset($_POST['notify_student']) || (string) $_POST['notify_student'] !== '0';
    $out = pcvc_approve_study_choice_suggestion($conn, $suggestionId, $adminId, $notifyStudent);
    if (!$out['ok']) {
        jsonResponse($out['msg'], false, 422);
    }
    jsonResponse($out);
    exit;
}

if ($action === 'reject_study_choice_suggestion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = 0;
    if (!empty($_SESSION['id'])) {
        $adminId = (int) $_SESSION['id'];
    } elseif (!empty($_SESSION['admin_id'])) {
        $adminId = (int) $_SESSION['admin_id'];
    }
    if ($adminId <= 0) {
        jsonResponse('Unauthorized', false, 401);
    }

    $suggestionId = (int) ($_POST['suggestion_id'] ?? 0);
    if ($suggestionId <= 0) {
        jsonResponse('Invalid suggestion id', false, 400);
    }

    $out = pcvc_reject_study_choice_suggestion($conn, $suggestionId, $adminId);
    if (!$out['ok']) {
        jsonResponse($out['msg'], false, 422);
    }

    $applicationId = 0;
    $st = $conn->prepare('SELECT application_id FROM application_study_choice_suggestions WHERE id = ? LIMIT 1');
    if ($st) {
        $st->bind_param('i', $suggestionId);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        $applicationId = (int) ($r['application_id'] ?? 0);
    }

    jsonResponse([
        'message' => $out['msg'],
        'suggestions' => $applicationId > 0
            ? pcvc_fetch_study_choice_suggestions($conn, $applicationId, 'pending')
            : [],
    ]);
    exit;
}

/**
 * ======================================================
 * REPLACE / UPLOAD APPLICATION DOCUMENT (all admins)
 * ======================================================
 */
if ($action === 'replace_document' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = 0;
    if (!empty($_SESSION['id'])) {
        $adminId = (int) $_SESSION['id'];
    } elseif (!empty($_SESSION['admin_id'])) {
        $adminId = (int) $_SESSION['admin_id'];
    }
    if ($adminId <= 0) {
        jsonResponse('Unauthorized', false, 401);
    }

    $applicationId = (int) ($_POST['application_id'] ?? 0);
    $docKey        = trim((string) ($_POST['document_key'] ?? ''));
    if ($applicationId <= 0 || $docKey === '') {
        jsonResponse('Invalid request', false, 400);
    }
    if (empty($_FILES['file'])) {
        jsonResponse('No file uploaded', false, 400);
    }

    $result = pcvc_app_replace_document($conn, $applicationId, $docKey, $_FILES['file']);
    if (!$result['ok']) {
        jsonResponse($result['error'] ?? 'Upload failed', false, 422);
    }

    jsonResponse([
        'path'      => $result['path'] ?? '',
        'documents' => $result['documents'] ?? [],
        'message'   => 'Document saved.',
    ]);
    exit;
}

/**
 * ======================================================
 * NOTIFY STUDENT — MISSING DOCUMENTS (WhatsApp template + email)
 * ======================================================
 */
if ($action === 'notify_missing_documents' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = 0;
    if (!empty($_SESSION['id'])) {
        $adminId = (int) $_SESSION['id'];
    } elseif (!empty($_SESSION['admin_id'])) {
        $adminId = (int) $_SESSION['admin_id'];
    }
    if ($adminId <= 0) {
        jsonResponse('Unauthorized', false, 401);
    }

    $applicationId = (int) ($_POST['application_id'] ?? 0);
    $rawKeys         = $_POST['missing_keys'] ?? [];
    if (is_string($rawKeys)) {
        $decoded = json_decode($rawKeys, true);
        $rawKeys = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $rawKeys)));
    }
    if (!is_array($rawKeys)) {
        $rawKeys = [];
    }
    $missingKeys     = array_values(array_filter(array_map('strval', $rawKeys)));
    $customNote      = trim((string) ($_POST['custom_note'] ?? ''));
    $customMessage   = trim((string) ($_POST['custom_message'] ?? ''));
    $overridePhone   = trim((string) ($_POST['override_phone'] ?? ''));
    $overrideEmail   = trim((string) ($_POST['override_email'] ?? ''));
    $sendWa          = !isset($_POST['send_whatsapp']) || $_POST['send_whatsapp'] === '1' || $_POST['send_whatsapp'] === 'true';
    $sendEm          = !isset($_POST['send_email']) || $_POST['send_email'] === '1' || $_POST['send_email'] === 'true';

    if ($applicationId <= 0) {
        jsonResponse('Invalid application id', false, 400);
    }

    $result = pcvc_app_notify_missing_documents(
        $conn,
        $applicationId,
        $missingKeys,
        $customNote,
        $sendWa,
        $sendEm,
        $overridePhone,
        $overrideEmail,
        $customMessage
    );
    if (!$result['ok']) {
        jsonResponse([
            'error'    => $result['error'] ?? 'Send failed',
            'whatsapp' => $result['whatsapp'] ?? [],
            'email'    => $result['email'] ?? [],
        ], false, 422);
    }

    jsonResponse([
        'message'  => 'Notification sent.',
        'whatsapp' => $result['whatsapp'] ?? [],
        'email'    => $result['email'] ?? [],
    ]);
    exit;
}

/**
 * ======================================================
 * UPDATE ASSIGNED STAFF (superadmin only — Student Application Report)
 * ======================================================
 */
if ($action === 'update_assignment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../helpers/staff_assignment_notify.php';
    require_once __DIR__ . '/../helpers/task_assignment_data.php';

    $adminId = 0;
    if (!empty($_SESSION['id'])) {
        $adminId = (int) $_SESSION['id'];
    } elseif (!empty($_SESSION['admin_id'])) {
        $adminId = (int) $_SESSION['admin_id'];
    }
    if ($adminId <= 0) {
        jsonResponse('Unauthorized', false, 401);
    }

    $stmtRole = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
    if (!$stmtRole) {
        jsonResponse('Server error', false, 500);
    }
    $stmtRole->bind_param('i', $adminId);
    $stmtRole->execute();
    $roleRow = $stmtRole->get_result()->fetch_assoc();
    $stmtRole->close();

    if (!$roleRow) {
        jsonResponse('Unauthorized', false, 401);
    }

    $dbRole = (string) ($roleRow['role'] ?? '');
    $sessionRole = (string) ($_SESSION['role'] ?? '');
    if (
        !pcvc_is_superadmin_role($dbRole)
        && !pcvc_is_superadmin_role($sessionRole)
    ) {
        jsonResponse('Forbidden', false, 403);
    }

    $chkAssignCol = $conn->query("SHOW COLUMNS FROM student_applications LIKE 'assigned_to_admin_id'");
    if (!$chkAssignCol || $chkAssignCol->num_rows < 1) {
        jsonResponse('Assignment is not available in this database.', false, 400);
    }

    $applicationId = (int) ($_POST['application_id'] ?? 0);
    if ($applicationId <= 0) {
        jsonResponse('Invalid application id', false, 400);
    }

    $newAssigneeId = (int) ($_POST['assigned_to_admin_id'] ?? 0);

    if ($newAssigneeId > 0) {
        $stStaff = $conn->prepare(
            'SELECT id FROM admins WHERE id = ? AND ' . pcvc_sql_assignable_application_owner_condition() . ' LIMIT 1'
        );
        if (!$stStaff) {
            jsonResponse('Server error', false, 500);
        }
        $stStaff->bind_param('i', $newAssigneeId);
        $stStaff->execute();
        $stStaff->bind_result($staffRowId);
        $okStaff = $stStaff->fetch();
        $stStaff->close();
        if (!$okStaff || (int) $staffRowId !== $newAssigneeId) {
            jsonResponse('Selected assignee is not a valid staff or superadmin account.', false, 422);
        }
    }

    $stCur = $conn->prepare('SELECT assigned_to_admin_id FROM student_applications WHERE id = ? LIMIT 1');
    if (!$stCur) {
        jsonResponse('Server error', false, 500);
    }
    $stCur->bind_param('i', $applicationId);
    $stCur->execute();
    $curRow = $stCur->get_result()->fetch_assoc();
    $stCur->close();
    if (!$curRow) {
        jsonResponse('Application not found', false, 404);
    }

    $oldAssign = isset($curRow['assigned_to_admin_id']) && $curRow['assigned_to_admin_id'] !== null && $curRow['assigned_to_admin_id'] !== ''
        ? (int) $curRow['assigned_to_admin_id']
        : 0;

    if ($newAssigneeId !== $oldAssign) {
        if ($newAssigneeId > 0) {
            $upd = $conn->prepare('UPDATE student_applications SET assigned_to_admin_id = ? WHERE id = ? LIMIT 1');
            if (!$upd) {
                jsonResponse('Server error', false, 500);
            }
            $upd->bind_param('ii', $newAssigneeId, $applicationId);
            $upd->execute();
            $upd->close();
        } else {
            $upd = $conn->prepare('UPDATE student_applications SET assigned_to_admin_id = NULL WHERE id = ? LIMIT 1');
            if (!$upd) {
                jsonResponse('Server error', false, 500);
            }
            $upd->bind_param('i', $applicationId);
            $upd->execute();
            $upd->close();
        }
    }

    $jobsCreated = 0;
    if ($oldAssign > 0 && $oldAssign !== $newAssigneeId) {
        pcvc_remove_incomplete_assignment_jobs($conn, $applicationId, $oldAssign);
    }
    if ($newAssigneeId > 0 && $newAssigneeId !== $oldAssign) {
        $jobsCreated = pcvc_ensure_assignment_jobs_for_application($conn, $applicationId, $newAssigneeId);
    }

    $notified = false;
    if ($newAssigneeId > 0 && $newAssigneeId !== $oldAssign) {
        $senderAdminLine = 'Admin #' . $adminId;
        $stFrom = $conn->prepare('SELECT id, first_name, last_name, full_name FROM admins WHERE id = ? LIMIT 1');
        if ($stFrom) {
            $stFrom->bind_param('i', $adminId);
            $stFrom->execute();
            $fromRow = $stFrom->get_result()->fetch_assoc();
            $stFrom->close();
            if ($fromRow) {
                $senderAdminLine = pcvc_task_monitor_staff_display_name($fromRow) . ' #' . $adminId;
            }
        }
        $companySenderLine = PCVC_COMPANY_DISPLAY_NAME . ' — ' . $senderAdminLine;
        pcvc_notify_assignee_reassigned_from_dashboard($conn, $applicationId, $newAssigneeId, $companySenderLine);
        $notified = true;
    }

    $assignDisplay = PCVC_DEFAULT_ASSIGNED_PERSON_LABEL;
    if ($newAssigneeId > 0) {
        $stNm = $conn->prepare(
            "SELECT COALESCE(NULLIF(TRIM(full_name),''), TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')))) AS nm FROM admins WHERE id = ? LIMIT 1"
        );
        if ($stNm) {
            $stNm->bind_param('i', $newAssigneeId);
            $stNm->execute();
            $nr = $stNm->get_result()->fetch_assoc();
            $stNm->close();
            $nm = trim((string) ($nr['nm'] ?? ''));
            $assignDisplay = $nm !== '' ? $nm : ('Staff #' . $newAssigneeId);
        }
    }

    jsonResponse([
        'application_id' => $applicationId,
        'assigned_to_admin_id' => $newAssigneeId,
        'assigned_display' => $assignDisplay,
        'notified' => $notified,
        'jobs_created' => $jobsCreated,
    ]);
    exit;
}

/**
 * ======================================================
 * SEARCH STUDENTS (superadmin — change recruiting agent from dashboard)
 * ======================================================
 */
if ($action === 'search_students_for_agent' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $adminId = 0;
    if (!empty($_SESSION['id'])) {
        $adminId = (int) $_SESSION['id'];
    } elseif (!empty($_SESSION['admin_id'])) {
        $adminId = (int) $_SESSION['admin_id'];
    }
    if ($adminId <= 0) {
        jsonResponse('Unauthorized', false, 401);
    }
    $stmtRole = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
    if (!$stmtRole) {
        jsonResponse('Server error', false, 500);
    }
    $stmtRole->bind_param('i', $adminId);
    $stmtRole->execute();
    $roleRow = $stmtRole->get_result()->fetch_assoc();
    $stmtRole->close();
    if (!$roleRow) {
        jsonResponse('Unauthorized', false, 401);
    }
    $dbRole = (string) ($roleRow['role'] ?? '');
    $sessionRole = (string) ($_SESSION['role'] ?? '');
    if (
        !pcvc_is_superadmin_role($dbRole)
        && !pcvc_is_superadmin_role($sessionRole)
    ) {
        jsonResponse('Forbidden', false, 403);
    }

    $q = trim((string) ($_GET['q'] ?? ''));
    if (strlen($q) < 2) {
        jsonResponse(['students' => []]);
        exit;
    }

    $like = '%' . $q . '%';
    $likeAppId = '%' . $q . '%';
    $sql = "
        SELECT
            sa.id,
            sa.application_id,
            sa.first_name,
            sa.last_name,
            sa.email,
            sa.agent_email,
            TRIM(CONCAT(COALESCE(sa.agent_first_name, ''), ' ', COALESCE(sa.agent_last_name, ''))) AS agent_name_display
        FROM student_applications sa
        WHERE
            sa.first_name LIKE ?
            OR sa.last_name LIKE ?
            OR sa.email LIKE ?
            OR TRIM(CONCAT(COALESCE(sa.first_name, ''), ' ', COALESCE(sa.last_name, ''))) LIKE ?
            OR CAST(sa.application_id AS CHAR) LIKE ?
        ORDER BY sa.id DESC
        LIMIT 25
    ";
    $st = $conn->prepare($sql);
    if (!$st) {
        jsonResponse('Server error', false, 500);
    }
    $st->bind_param('sssss', $like, $like, $like, $like, $likeAppId);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $aid = trim((string) ($r['agent_name_display'] ?? ''));
        $em = trim((string) ($r['agent_email'] ?? ''));
        $curAgent = $aid !== '' ? $aid : ($em !== '' ? $em : '—');
        $rows[] = [
            'id' => (int) ($r['id'] ?? 0),
            'application_id' => $r['application_id'] ?? '',
            'first_name' => $r['first_name'] ?? '',
            'last_name' => $r['last_name'] ?? '',
            'email' => $r['email'] ?? '',
            'current_agent_label' => $curAgent,
            'current_agent_email' => $em,
        ];
    }
    $st->close();

    jsonResponse(['students' => $rows]);
    exit;
}

/**
 * ======================================================
 * UPDATE RECRUITING AGENT (superadmin — student_applications.agent_*)
 * ======================================================
 */
if ($action === 'update_recruiting_agent' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = 0;
    if (!empty($_SESSION['id'])) {
        $adminId = (int) $_SESSION['id'];
    } elseif (!empty($_SESSION['admin_id'])) {
        $adminId = (int) $_SESSION['admin_id'];
    }
    if ($adminId <= 0) {
        jsonResponse('Unauthorized', false, 401);
    }
    $stmtRole = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
    if (!$stmtRole) {
        jsonResponse('Server error', false, 500);
    }
    $stmtRole->bind_param('i', $adminId);
    $stmtRole->execute();
    $roleRow = $stmtRole->get_result()->fetch_assoc();
    $stmtRole->close();
    if (!$roleRow) {
        jsonResponse('Unauthorized', false, 401);
    }
    $dbRole = (string) ($roleRow['role'] ?? '');
    $sessionRole = (string) ($_SESSION['role'] ?? '');
    if (
        !pcvc_is_superadmin_role($dbRole)
        && !pcvc_is_superadmin_role($sessionRole)
    ) {
        jsonResponse('Forbidden', false, 403);
    }

    $applicationId = (int) ($_POST['application_id'] ?? 0);
    $newEmailNorm = strtolower(trim((string) ($_POST['agent_email'] ?? '')));
    if ($applicationId <= 0) {
        jsonResponse('Invalid application id', false, 400);
    }
    if ($newEmailNorm === '' || !filter_var($newEmailNorm, FILTER_VALIDATE_EMAIL)) {
        jsonResponse('Choose a valid agent email from the list.', false, 422);
    }

    $stA = $conn->prepare(
        'SELECT email, first_name, last_name FROM admins WHERE LOWER(TRIM(email)) = ? LIMIT 1'
    );
    if (!$stA) {
        jsonResponse('Server error', false, 500);
    }
    $stA->bind_param('s', $newEmailNorm);
    $stA->execute();
    $agentAdmin = $stA->get_result()->fetch_assoc();
    $stA->close();
    if (!$agentAdmin) {
        jsonResponse('That email is not a registered admin account. Pick an agent from the dropdown.', false, 422);
    }

    $agentEmailStore = trim((string) ($agentAdmin['email'] ?? ''));
    if ($agentEmailStore === '') {
        jsonResponse('Invalid agent record.', false, 422);
    }
    $fn = trim((string) ($agentAdmin['first_name'] ?? ''));
    $ln = trim((string) ($agentAdmin['last_name'] ?? ''));

    $stCheck = $conn->prepare('SELECT id FROM student_applications WHERE id = ? LIMIT 1');
    if (!$stCheck) {
        jsonResponse('Server error', false, 500);
    }
    $stCheck->bind_param('i', $applicationId);
    $stCheck->execute();
    $exists = (bool) $stCheck->get_result()->fetch_assoc();
    $stCheck->close();
    if (!$exists) {
        jsonResponse('Application not found.', false, 404);
    }

    $stU = $conn->prepare(
        'UPDATE student_applications SET agent_email = ?, agent_first_name = ?, agent_last_name = ? WHERE id = ? LIMIT 1'
    );
    if (!$stU) {
        jsonResponse('Server error', false, 500);
    }
    $stU->bind_param('sssi', $agentEmailStore, $fn, $ln, $applicationId);
    if (!$stU->execute()) {
        $err = $stU->error;
        $stU->close();
        jsonResponse('Update failed: ' . $err, false, 500);
    }
    $stU->close();

    jsonResponse([
        'application_id' => $applicationId,
        'agent_email' => $agentEmailStore,
        'agent_first_name' => $fn,
        'agent_last_name' => $ln,
    ]);
    exit;
}

/**
 * ======================================================
 * DELETE APPLICATION (superadmin only)
 * ======================================================
 */
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $adminId = 0;
    if (!empty($_SESSION['id'])) {
        $adminId = (int) $_SESSION['id'];
    } elseif (!empty($_SESSION['admin_id'])) {
        $adminId = (int) $_SESSION['admin_id'];
    }
    if ($adminId <= 0) {
        jsonResponse("Unauthorized", false, 401);
    }

    $stmtRole = $conn->prepare("SELECT role FROM admins WHERE id = ? LIMIT 1");
    if (!$stmtRole) {
        jsonResponse("Server error", false, 500);
    }
    $stmtRole->bind_param("i", $adminId);
    $stmtRole->execute();
    $roleRow = $stmtRole->get_result()->fetch_assoc();
    $stmtRole->close();

    if (!$roleRow) {
        jsonResponse("Unauthorized", false, 401);
    }

    $dbRole = (string) ($roleRow['role'] ?? '');
    $sessionRole = (string) ($_SESSION['role'] ?? '');
    if (
        !pcvc_is_superadmin_role($dbRole)
        && !pcvc_is_superadmin_role($sessionRole)
    ) {
        jsonResponse("Forbidden", false, 403);
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse("Invalid application id", false, 400);
    }

    /**
     * Force-delete application: clear all dependent rows (FK + application_id),
     * then parent. Uses INFORMATION_SCHEMA so new referencing tables do not
     * require code changes. FOREIGN_KEY_CHECKS disabled only for this operation.
     */
    $identOk = static function (string $name): bool {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $name);
    };

    $conn->begin_transaction();

    try {
        if (!$conn->query("SET FOREIGN_KEY_CHECKS = 0")) {
            throw new Exception($conn->error);
        }

        $dbRes = $conn->query("SELECT DATABASE() AS dbname");
        $dbRow = $dbRes ? $dbRes->fetch_assoc() : null;
        $schema = $dbRow['dbname'] ?? '';
        if ($schema === '') {
            throw new Exception("No database selected");
        }

        // 1) Every BASE TABLE column that formally references student_applications.id
        $fkSql = "
            SELECT DISTINCT k.TABLE_NAME, k.COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
            INNER JOIN INFORMATION_SCHEMA.TABLES t
                ON k.TABLE_SCHEMA = t.TABLE_SCHEMA
                AND k.TABLE_NAME = t.TABLE_NAME
            WHERE k.TABLE_SCHEMA = ?
              AND k.REFERENCED_TABLE_NAME = 'student_applications'
              AND k.REFERENCED_COLUMN_NAME = 'id'
              AND t.TABLE_TYPE = 'BASE TABLE'
              AND k.TABLE_NAME <> 'student_applications'
        ";
        $fkStmt = $conn->prepare($fkSql);
        if (!$fkStmt) {
            throw new Exception($conn->error);
        }
        $fkStmt->bind_param("s", $schema);
        $fkStmt->execute();
        $fkRows = $fkStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $fkStmt->close();

        foreach ($fkRows as $row) {
            $t = $row['TABLE_NAME'];
            $c = $row['COLUMN_NAME'];
            if (!$identOk($t) || !$identOk($c)) {
                continue;
            }
            $sqlDel = "DELETE FROM `{$t}` WHERE `{$c}` = " . $id;
            if (!$conn->query($sqlDel)) {
                throw new Exception($conn->error . " ({$t}.{$c})");
            }
        }

        // 2) Any BASE TABLE with an application_id column (logical refs without FK)
        $colSql = "
            SELECT DISTINCT c.TABLE_NAME
            FROM INFORMATION_SCHEMA.COLUMNS c
            INNER JOIN INFORMATION_SCHEMA.TABLES t
                ON c.TABLE_SCHEMA = t.TABLE_SCHEMA
                AND c.TABLE_NAME = t.TABLE_NAME
            WHERE c.TABLE_SCHEMA = ?
              AND c.COLUMN_NAME = 'application_id'
              AND t.TABLE_TYPE = 'BASE TABLE'
              AND c.TABLE_NAME <> 'student_applications'
        ";
        $colStmt = $conn->prepare($colSql);
        if (!$colStmt) {
            throw new Exception($conn->error);
        }
        $colStmt->bind_param("s", $schema);
        $colStmt->execute();
        $colRows = $colStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $colStmt->close();

        foreach ($colRows as $row) {
            $t = $row['TABLE_NAME'];
            if (!$identOk($t)) {
                continue;
            }
            $sqlDel = "DELETE FROM `{$t}` WHERE `application_id` = " . $id;
            if (!$conn->query($sqlDel)) {
                throw new Exception($conn->error . " ({$t}.application_id)");
            }
        }

        // 3) Parent row
        $st = $conn->prepare("DELETE FROM student_applications WHERE id = ? LIMIT 1");
        if (!$st) {
            throw new Exception($conn->error);
        }
        $st->bind_param("i", $id);
        if (!$st->execute()) {
            throw new Exception($st->error);
        }
        if ($st->affected_rows === 0) {
            $st->close();
            throw new Exception("NOT_FOUND");
        }
        $st->close();

        if (!$conn->query("SET FOREIGN_KEY_CHECKS = 1")) {
            throw new Exception($conn->error);
        }

        $conn->commit();
        jsonResponse(["deleted" => true, "id" => $id]);
    } catch (Throwable $e) {
        $conn->rollback();
        @ $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        if ($e->getMessage() === "NOT_FOUND") {
            jsonResponse("Application not found", false, 404);
        }
        error_log("applications force delete failed: " . $e->getMessage());
        jsonResponse("Delete failed", false, 500);
    }

    exit;
}

/**
 * ======================================================
 * LIST APPLICATIONS (SIDEBAR)
 * ======================================================
 */
if ($action === 'list') {

    $hasAssignedCol = false;
    $chkAssignCol = $conn->query("SHOW COLUMNS FROM student_applications LIKE 'assigned_to_admin_id'");
    if ($chkAssignCol && $chkAssignCol->num_rows > 0) {
        $hasAssignedCol = true;
    }

    $where  = [];
$types  = "";
$values = [];

/**
 * ======================================================
 * GLOBAL SEARCH (Student + University + Region + Country)
 * ======================================================
 */
if (!empty($_GET['q'])) {
    $q = '%' . trim($_GET['q']) . '%';

    $where[] = "(
        sa.first_name LIKE ?
        OR sa.last_name LIKE ?
        OR sa.email LIKE ?
        OR u.name LIKE ?
        OR r.name LIKE ?
        OR c.name LIKE ?
    )";

    $types  .= "ssssss";
    $values = array_merge($values, [$q, $q, $q, $q, $q, $q]);
}

/**
 * ======================================================
 * FILTERS (Exact Match)
 * ======================================================
 */
if (!empty($_GET['region_id'])) {
    $where[] = "ascx.region_id = ?";
    $types  .= "i";
    $values[] = (int)$_GET['region_id'];
}

if (!empty($_GET['university_id'])) {
    $where[] = "ascx.university_id = ?";
    $types  .= "i";
    $values[] = (int)$_GET['university_id'];
}

if (!empty($_GET['program_level_id'])) {
    $where[] = "ascx.program_level_id = ?";
    $types  .= "i";
    $values[] = (int)$_GET['program_level_id'];
}

if ($hasAssignedCol && isset($_GET['assigned_to']) && (string)$_GET['assigned_to'] !== '') {
    $ato = trim((string)$_GET['assigned_to']);
    if ($ato === '-1') {
        $where[] = 'sa.assigned_to_admin_id IS NULL';
    } else {
        $aid = (int)$ato;
        if ($aid > 0) {
            $where[] = 'sa.assigned_to_admin_id = ?';
            $types .= 'i';
            $values[] = $aid;
        }
    }
}

/** Filter by recruiting agent (student_applications.agent_email), case-insensitive */
if (!empty($_GET['agent_email'])) {
    $aeNorm = strtolower(trim((string) $_GET['agent_email']));
    if ($aeNorm !== '') {
        $where[] = 'LOWER(TRIM(COALESCE(sa.agent_email, \'\'))) = ?';
        $types .= 's';
        $values[] = $aeNorm;
    }
}

$allowedEffStatuses = pcvc_application_status_priority();
if (!empty($_GET['application_status']) && in_array((string)$_GET['application_status'], $allowedEffStatuses, true)) {
    $effExpr = pcvc_sql_case_effective_status('sa');
    $where[] = '(' . $effExpr . ') = ?';
    $types .= 's';
    $values[] = (string)$_GET['application_status'];
}

    /* ======================================================
       REQUIRE AT LEAST ONE UPLOADED DOCUMENT
       (HIDE EMPTY APPLICATIONS FROM SIDEBAR)
    ====================================================== */
    $where[] = "(
        (sa.degree_transcripts IS NOT NULL AND sa.degree_transcripts != '[]')
        OR sa.high_school_degree IS NOT NULL
        OR sa.valid_passport IS NOT NULL
        OR sa.cv_resume IS NOT NULL
        OR sa.personal_statement IS NOT NULL
        OR sa.recommendation_letters IS NOT NULL
        OR sa.english_certificate IS NOT NULL
        OR sa.birth_certificate IS NOT NULL
        OR sa.payment_proof IS NOT NULL
    )";

    $assignedSelectSql = $hasAssignedCol
        ? "MAX(COALESCE(
            NULLIF(TRIM(assign_ast.full_name), ''),
            TRIM(CONCAT(COALESCE(assign_ast.first_name, ''), ' ', COALESCE(assign_ast.last_name, '')))
        )) AS assigned_person_name"
        : "NULL AS assigned_person_name";

    $assignedJoinSql = $hasAssignedCol
        ? "LEFT JOIN admins assign_ast ON assign_ast.id = sa.assigned_to_admin_id
"
        : "";

    $maxEffSql = pcvc_sql_max_effective_status('sa');

$sql = "
    SELECT
        sa.id,
        sa.application_id,
        sa.first_name,
        sa.last_name,
        sa.email,
        sa.phone_number,
        sa.created_at,
        sa.is_read,
        {$assignedSelectSql},
        {$maxEffSql} AS effective_status,

    -- aggregated sidebar info (ONE row per application)
GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ' · ') AS universities,
GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ' · ') AS regions,
GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ' · ') AS countries


    FROM student_applications sa

    {$assignedJoinSql}
    LEFT JOIN application_study_choices ascx
        ON ascx.application_id = sa.id

    LEFT JOIN universities u
        ON u.id = ascx.university_id

    LEFT JOIN regions r
        ON r.id = ascx.region_id

    LEFT JOIN countries c
        ON c.id = u.country_id
";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= "
    GROUP BY sa.id
    ORDER BY sa.created_at DESC
";


    $stmt = $conn->prepare($sql);
    if ($values) {
        $stmt->bind_param($types, ...$values);
    }
    $stmt->execute();

   $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$data = array_map(function ($r) {
    $assignRaw = trim((string) ($r['assigned_person_name'] ?? ''));
    $assignDisplay = $assignRaw !== ''
        ? $assignRaw
        : PCVC_DEFAULT_ASSIGNED_PERSON_LABEL;

    return [
        "id" => (int)$r["id"],
        "application_id" => $r["application_id"],

        "bio" => [
            "first_name" => $r["first_name"],
            "last_name"  => $r["last_name"],
            "email"      => $r["email"]
        ],

       "study" => [
    "universities" => $r["universities"] ?: null,
    "regions"      => $r["regions"] ?: null,
    "countries"    => $r["countries"] ?: null
],


        "meta" => [
            "created_at" => pcvc_mysql_utc_to_iso8601_z($r["created_at"] ?? null) ?? ($r["created_at"] ?? null),
            "is_read"    => (bool)$r["is_read"],
            "assigned_display" => $assignDisplay,
            "effective_status" => isset($r["effective_status"]) && $r["effective_status"] !== null && $r["effective_status"] !== ''
                ? (string)$r["effective_status"]
                : null,
        ]
    ];
}, $rows);

jsonResponse($data);
exit;



}

/**
 * ======================================================
 * VIEW FULL APPLICATION (ALL TABLES)
 * ======================================================
 */
if ($action === 'view' && !empty($_GET['id'])) {

    $id = (int)$_GET['id'];

    // mark as read
    $stmt = $conn->prepare("UPDATE student_applications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    // Jobs are created when a superadmin assigns the application (not on view).
    $jobsCreated = 0;

    /**
     * ==================================================
     * MAIN APPLICATION (SAFE COUNTRY RESOLUTION)
     * ==================================================
     */
    $stmt = $conn->prepare("
        SELECT
            sa.*,

            -- SAFE: works if stored as name OR id
            COALESCE(cb.name, sa.country_of_birth)   AS birth_country_name,
            COALESCE(nat.name, sa.nationality)       AS nationality_name,
            COALESCE(pic.name, sa.previous_institution_country) AS prev_country_name

        FROM student_applications sa
        LEFT JOIN countries cb
            ON cb.id = sa.country_of_birth OR cb.name = sa.country_of_birth
        LEFT JOIN countries nat
            ON nat.id = sa.nationality OR nat.name = sa.nationality
        LEFT JOIN countries pic
            ON pic.id = sa.previous_institution_country
            OR pic.name = sa.previous_institution_country
        WHERE sa.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $app = $stmt->get_result()->fetch_assoc();

    if (!$app) {
        jsonResponse("Application not found", false, 404);
    }

    /**
     * ==================================================
     * STUDY CHOICES
     * ==================================================
     */
    $stmt = $conn->prepare("
        SELECT
            r.name  AS region,
            u.name  AS university,
            c.name  AS university_country,
            pl.name AS program_level,
            pl.abbreviation AS program_level_abbr,
            p.program_name AS program
        FROM application_study_choices ascx
        JOIN universities u    ON u.id = ascx.university_id
        JOIN regions r         ON r.id = ascx.region_id
        JOIN program_levels pl ON pl.id = ascx.program_level_id
        JOIN programs p        ON p.id = ascx.program_id
        LEFT JOIN countries c  ON c.id = u.country_id
        WHERE ascx.application_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $studyChoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $studyChoiceSuggestions = [];
    try {
        $studyChoiceSuggestions = pcvc_fetch_study_choice_suggestions($conn, $id, 'pending');
    } catch (Throwable $e) {
        $studyChoiceSuggestions = [];
    }

    /**
     * ==================================================
     * DOCUMENTS
     * ==================================================
     */
    $docPayload = pcvc_app_documents_for_view($app);
    $documents  = [
        "degree_transcripts"     => json_decode($app['degree_transcripts'] ?? "[]", true),
        "high_school_degree"     => $app['high_school_degree'],
        "passport"               => $app['valid_passport'],
        "cv_resume"              => $app['cv_resume'],
        "personal_statement"     => $app['personal_statement'],
        "recommendation_letters" => $app['recommendation_letters'],
        "english_certificate"    => $app['english_certificate'],
        "birth_certificate"      => $app['birth_certificate'],
        "payment_proof"          => $app['payment_proof']
    ];

    /**
     * ==================================================
     * SUPERADMIN DELETE (same rules as action=delete)
     * ==================================================
     */
    $canDeleteApplicationMeta = false;
    $adminId = 0;
    if (!empty($_SESSION['id'])) {
        $adminId = (int) $_SESSION['id'];
    } elseif (!empty($_SESSION['admin_id'])) {
        $adminId = (int) $_SESSION['admin_id'];
    }
    if ($adminId > 0) {
            $stmtRole = $conn->prepare("SELECT role FROM admins WHERE id = ? LIMIT 1");
            if ($stmtRole) {
                $stmtRole->bind_param("i", $adminId);
                $stmtRole->execute();
                $roleRow = $stmtRole->get_result()->fetch_assoc();
                $stmtRole->close();
                $dbRole = $roleRow ? (string) ($roleRow['role'] ?? '') : '';
                $sessionRole = (string) ($_SESSION['role'] ?? '');
                $canDeleteApplicationMeta =
                    pcvc_is_superadmin_role($dbRole)
                    || pcvc_is_superadmin_role($sessionRole);
            }
    }

    $hasAssignColForMeta = false;
    $chkAm = $conn->query("SHOW COLUMNS FROM student_applications LIKE 'assigned_to_admin_id'");
    if ($chkAm && $chkAm->num_rows > 0) {
        $hasAssignColForMeta = true;
    }
    $assignMetaId = 0;
    $assignMetaDisplay = PCVC_DEFAULT_ASSIGNED_PERSON_LABEL;
    if ($hasAssignColForMeta) {
        $rawAm = $app['assigned_to_admin_id'] ?? null;
        if ($rawAm !== null && (int) $rawAm > 0) {
            $assignMetaId = (int) $rawAm;
            $stNm = $conn->prepare(
                "SELECT COALESCE(NULLIF(TRIM(full_name),''), TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')))) AS nm FROM admins WHERE id = ? LIMIT 1"
            );
            if ($stNm) {
                $stNm->bind_param('i', $assignMetaId);
                $stNm->execute();
                $nr = $stNm->get_result()->fetch_assoc();
                $stNm->close();
                $nm = trim((string) ($nr['nm'] ?? ''));
                $assignMetaDisplay = $nm !== '' ? $nm : ('Staff #' . $assignMetaId);
            }
        }
    }
    $canEditAssignmentMeta = $hasAssignColForMeta && $canDeleteApplicationMeta;

    /**
     * ==================================================
     * FINAL RESPONSE (JS SAFE)
     * ==================================================
     */
    jsonResponse([
        "bio" => [
            "first_name" => $app['first_name'],
            "last_name"  => $app['last_name'],
            "gender"     => $app['gender'],
            "dob"        => $app['dob'],
            "email"      => $app['email'],
            "phone"      => trim($app['area_code'] . " " . $app['phone_number']),
            "nationality"=> $app['nationality_name'],
            "country_of_birth" => $app['birth_country_name'],
            "passport_number"  => $app['passport_number'],
            "national_id"      => $app['student_national_id']
        ],

        "address" => [
            "line1" => $app['address_line1'],
            "line2" => $app['address_line2'],
            "city"  => $app['city'],
            "state" => $app['state_province'],
            "postal_code" => $app['postal_code']
        ],

        "parents" => [
            "father" => trim($app['father_first_name'] . " " . $app['father_last_name']),
            "mother" => trim($app['mother_first_name'] . " " . $app['mother_last_name'])
        ],

        "emergency" => [
            "name"  => trim($app['emergency_first_name'] . " " . $app['emergency_last_name']),
            "email" => $app['emergency_email'],
            "phone" => trim($app['emergency_area_code'] . " " . $app['emergency_phone_number']),
            "relationship" => $app['emergency_relationship']
        ],

        "education" => [
            "institution" => $app['previous_institution_name'],
            "city"        => $app['previous_institution_city'],
            "country"     => $app['prev_country_name'],
            "start_date"  => $app['previous_study_start'],
            "graduation"  => $app['previous_study_graduation'],
            "language"    => $app['language_of_instruction'],
            "study_gap"   => $app['study_gap'],
            "study_gap_details" => $app['study_gap_details']
        ],

        "study_choices" => $studyChoices,
        "study_choice_suggestions" => $studyChoiceSuggestions,
        "agent" => [
            "name"  => trim($app['agent_first_name'] . " " . $app['agent_last_name']),
            "email" => $app['agent_email']
        ],
        "documents" => $documents,
        "document_items" => $docPayload['items'],

        "status" => [
            "submitted" => (bool)$app['submitted'],
            "admit"     => (bool)$app['admit'],
            "visa_scheduled" => (bool)$app['visa_scheduled'],
            "visa_approved"  => (bool)$app['visa_approved'],
            "enrolled" => (bool)$app['enrolled'],
            "denied"   => (bool)$app['deny'],
            "paid"     => (bool)$app['app_paid']
        ],

     "meta" => [
    "application_id" => $app['application_id'],
    "created_at" => pcvc_mysql_utc_to_iso8601_z($app['created_at'] ?? null) ?? ($app['created_at'] ?? null),
    "updated_at" => pcvc_mysql_utc_to_iso8601_z($app['updated_at'] ?? null) ?? ($app['updated_at'] ?? null),
    "is_read"    => 1,
    "jobs_created" => $jobsCreated, // 👈 ADD THIS
    "can_delete_application" => $canDeleteApplicationMeta,
    "can_edit_assignment" => $canEditAssignmentMeta,
    "assigned_to_admin_id" => $assignMetaId,
    "assigned_display" => $assignMetaDisplay,
]

    ]);
}
/**
 * ======================================================
 * APPLICATION JOURNEY (TRACK EVERYTHING)
 * ======================================================
 */
if ($action === 'journey' && !empty($_GET['id'])) {

    $applicationId = (int)$_GET['id'];

    $stmt = $conn->prepare("
        SELECT
            jl.id,
            jl.status,
            jl.created_at,
            jl.is_auto_created,

            u.name AS university_name,
            p.platform_name,
            a.full_name AS admin_name

        FROM job_list jl

        LEFT JOIN universities u
            ON u.id = jl.university_id

        LEFT JOIN platforms p
            ON p.id = jl.platform_id

        LEFT JOIN admins a
            ON a.id = jl.admin_id

        WHERE jl.application_id = ?
        ORDER BY jl.created_at ASC
    ");

    $stmt->bind_param("i", $applicationId);
    $stmt->execute();

    $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $jobs = array_map(static function (array $row): array {
        if (array_key_exists('created_at', $row)) {
            $iso = pcvc_mysql_utc_to_iso8601_z($row['created_at'] !== null ? (string) $row['created_at'] : null);
            if ($iso !== null) {
                $row['created_at'] = $iso;
            }
        }
        return $row;
    }, $jobs);

    jsonResponse($jobs);
    exit;
}
/**
 * ======================================================
 * UNIVERSITY → KOREA CHECK (AUTHORITATIVE)
 * South Korea country_id = 61
 * ======================================================
 */
if ($action === 'university_country' && !empty($_GET['university_id'])) {

    $universityId = (int) $_GET['university_id'];

    $stmt = $conn->prepare("
        SELECT
            u.country_id
        FROM universities u
        WHERE u.id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $universityId);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    $countryId = isset($row['country_id']) ? (int) $row['country_id'] : null;

    jsonResponse([
        "country_id" => $countryId,
        "is_korea"   => ($countryId === 61)
    ]);

    exit;
}


/**
 * ======================================================
 * FALLBACK
 * ======================================================
 */
jsonResponse("Invalid request", false, 400);
