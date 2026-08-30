<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/role.php';
require_once __DIR__ . '/../includes/company_branding.php';

/**
 * Attachment columns on student_applications (paths relative to project root or uploads/).
 */
function pcvc_student_application_attachment_columns(): array
{
    return [
        'degree_transcripts',
        'high_school_degree',
        'valid_passport',
        'recommendation_letters',
        'personal_statement',
        'cv_resume',
        'english_certificate',
        'birth_certificate',
        'payment_proof',
    ];
}

/**
 * Collect absolute file paths from application row (JSON arrays or single path strings).
 *
 * @return array<int, array{path:string, label:string}>
 */
function pcvc_collect_application_attachment_files(array $applicationRow): array
{
    $root = realpath(__DIR__ . '/..');
    if ($root === false) {
        $root = __DIR__ . '/..';
    }
    $root = rtrim(str_replace('\\', '/', $root), '/');

    $out = [];
    $seen = [];

    foreach (pcvc_student_application_attachment_columns() as $col) {
        if (!array_key_exists($col, $applicationRow)) {
            continue;
        }
        $raw = $applicationRow[$col];
        if ($raw === null || $raw === '') {
            continue;
        }

        $paths = [];
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $p) {
                if (is_string($p) && $p !== '') {
                    $paths[] = $p;
                }
            }
        } else {
            $paths[] = (string)$raw;
        }

        foreach ($paths as $rel) {
            $rel = str_replace('\\', '/', trim($rel));
            if ($rel === '') {
                continue;
            }
            $abs = $root . '/' . ltrim($rel, '/');
            $absReal = realpath($abs);
            if ($absReal === false || !is_file($absReal)) {
                continue;
            }
            $key = strtolower($absReal);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $base = basename($absReal);
            $out[] = [
                'path' => $absReal,
                'label' => $col . '_' . $base,
            ];
        }
    }

    return $out;
}

function pcvc_get_application_study_choice_summaries_for_notify(mysqli $conn, int $applicationId): array
{
    if ($applicationId <= 0) {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT
            r.name AS region_name,
            u.name AS university_name,
            pl.name AS level_name,
            p.program_name
        FROM application_study_choices sc
        JOIN regions r ON r.id = sc.region_id
        JOIN universities u ON u.id = sc.university_id
        JOIN program_levels pl ON pl.id = sc.program_level_id
        JOIN programs p ON p.id = sc.program_id
        WHERE sc.application_id = ?
        ORDER BY sc.id ASC
    ");

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $res = $stmt->get_result();

    $choices = [];
    while ($row = $res->fetch_assoc()) {
        $parts = array_filter([
            trim((string)($row['program_name'] ?? '')),
            trim((string)($row['level_name'] ?? '')),
            trim((string)($row['university_name'] ?? '')),
            trim((string)($row['region_name'] ?? '')),
        ], static fn($v) => $v !== '');

        if ($parts) {
            $choices[] = implode(' — ', $parts);
        }
    }

    $stmt->close();
    return $choices;
}

/**
 * After final submit: email assigned staff (role staff) with summary + attachments.
 */
function pcvc_notify_assigned_staff_application_submitted(mysqli $conn, int $applicationId): void
{
    if ($applicationId <= 0) {
        return;
    }

    $stmt = $conn->prepare('
        SELECT sa.*,
               a.email AS staff_email,
               a.first_name AS staff_first,
               a.last_name AS staff_last
        FROM student_applications sa
        INNER JOIN admins a ON a.id = sa.assigned_to_admin_id
        WHERE sa.id = ?
          AND LOWER(TRIM(COALESCE(a.role, \'\'))) = \'staff\'
        LIMIT 1
    ');

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['staff_email']) || !filter_var($row['staff_email'], FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $study = pcvc_get_application_study_choice_summaries_for_notify($conn, $applicationId);
    $files = pcvc_collect_application_attachment_files($row);

    $studentName = trim(
        (string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? '')
    );
    if ($studentName === '') {
        $studentName = 'Applicant';
    }

    $lines = [
        ['Application ID', (string)$applicationId],
        ['Student name', $studentName],
        ['Email', (string)($row['email'] ?? '')],
        ['Phone', trim((string)($row['area_code'] ?? '') . ' ' . (string)($row['phone_number'] ?? ''))],
        ['Passport', (string)($row['passport_number'] ?? '')],
        ['Nationality', (string)($row['nationality'] ?? '')],
        ['DOB', (string)($row['dob'] ?? '')],
        ['Referral', (string)($row['referral_source'] ?? '')],
        ['Agent', trim((string)($row['agent_first_name'] ?? '') . ' ' . (string)($row['agent_last_name'] ?? '') . ' <' . (string)($row['agent_email'] ?? '') . '>')],
    ];

    $rowsHtml = '';
    foreach ($lines as [$k, $v]) {
        if ($v === '' || $v === '<>') {
            continue;
        }
        $rowsHtml .= '<tr><th style="text-align:left;padding:6px 10px;border:1px solid #e5e7eb;background:#f9fafb;width:180px">'
            . htmlspecialchars($k, ENT_QUOTES, 'UTF-8')
            . '</th><td style="padding:6px 10px;border:1px solid #e5e7eb">'
            . nl2br(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'))
            . '</td></tr>';
    }

    $studyHtml = '';
    if ($study) {
        $studyHtml .= '<p style="margin:16px 0 8px 0;font-weight:700">Study choices</p><ul style="margin:0;padding-left:20px">';
        foreach ($study as $c) {
            $studyHtml .= '<li style="margin:4px 0">' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $studyHtml .= '</ul>';
    }

    $attachNote = $files
        ? '<p style="margin-top:12px"><strong>' . count($files) . '</strong> file(s) are attached from the application.</p>'
        : '<p style="margin-top:12px;color:#6b7280">No document files were found on disk for this application (paths empty or missing).</p>';

    $subject = 'New application assigned to you — #' . $applicationId . ' — ' . PCVC_COMPANY_DISPLAY_NAME;

    $body = '
<div style="font-family:Arial,sans-serif;line-height:1.55;color:#111;max-width:720px">
  <h2 style="margin:0 0 12px 0">Application submitted</h2>
  <p>Hello <strong>' . htmlspecialchars(trim((string)($row['staff_first'] ?? '') . ' ' . (string)($row['staff_last'] ?? '')), ENT_QUOTES, 'UTF-8') . '</strong>,</p>
  <p>An application was submitted with <strong>you</strong> as the assigned staff contact. Summary below.</p>
  <table style="border-collapse:collapse;width:100%;margin:14px 0">' . $rowsHtml . '</table>
  ' . $studyHtml . '
  ' . $attachNote . '
  <p style="margin-top:18px;color:#6b7280;font-size:13px">This message was sent automatically when the student finalized their application.</p>
</div>';

    try {
        $mail = app_mailer();
        $mail->clearAddresses();
        $mail->clearAttachments();
        $mail->setFrom(PCVC_COMPANY_SUPPORT_EMAIL, PCVC_COMPANY_DISPLAY_NAME . ' — Applications');
        $mail->clearReplyTos();
        $mail->addReplyTo(PCVC_COMPANY_SUPPORT_EMAIL, PCVC_COMPANY_DISPLAY_NAME);
        $mail->addAddress($row['staff_email'], trim((string)($row['staff_first'] ?? '') . ' ' . (string)($row['staff_last'] ?? '')));
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        foreach ($files as $f) {
            try {
                $mail->addAttachment($f['path'], $f['label']);
            } catch (Throwable $e) {
                // Skip individual attachment failures (size / type)
            }
        }

        $mail->send();
    } catch (Throwable $e) {
        @file_put_contents(
            __DIR__ . '/../email_debug.log',
            '[' . date('Y-m-d H:i:s') . '] STAFF ASSIGNMENT NOTIFY FAILED :: ' . $e->getMessage() . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

/**
 * After a superadmin changes assignee on the Student Application Report, notify the new owner (staff or superadmin).
 * Email is always attempted when the staff has a valid address; WhatsApp is attempted if configured + phone on file.
 */
function pcvc_notify_assignee_reassigned_from_dashboard(
    mysqli $conn,
    int $applicationDbId,
    int $newAssigneeStaffId,
    string $actorCompanySenderLine
): void {
    if ($applicationDbId <= 0 || $newAssigneeStaffId <= 0) {
        return;
    }

    require_once __DIR__ . '/task_assignment_data.php';

    $st = $conn->prepare(
        'SELECT id, first_name, last_name, full_name, email, phone_number FROM admins WHERE id = ? AND '
        . pcvc_sql_assignable_application_owner_condition()
        . ' LIMIT 1'
    );
    if (!$st) {
        return;
    }
    $st->bind_param('i', $newAssigneeStaffId);
    $st->execute();
    $staff = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$staff) {
        return;
    }

    $st2 = $conn->prepare('SELECT id, application_id, first_name, last_name, email FROM student_applications WHERE id = ? LIMIT 1');
    if (!$st2) {
        return;
    }
    $st2->bind_param('i', $applicationDbId);
    $st2->execute();
    $app = $st2->get_result()->fetch_assoc();
    $st2->close();
    if (!$app) {
        return;
    }

    $studentName = trim((string) ($app['first_name'] ?? '') . ' ' . (string) ($app['last_name'] ?? ''));
    if ($studentName === '') {
        $studentName = 'Applicant';
    }
    $appRef = trim((string) ($app['application_id'] ?? ''));
    if ($appRef === '') {
        $appRef = 'ID ' . (string) $applicationDbId;
    }

    $staffName = pcvc_task_monitor_staff_display_name($staff);
    $toEmail = trim((string) ($staff['email'] ?? ''));
    $subject = 'Application assigned to you — ' . $appRef . ' — ' . PCVC_COMPANY_DISPLAY_NAME;

    $safeStudent = htmlspecialchars($studentName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeRef = htmlspecialchars($appRef, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeActor = htmlspecialchars($actorCompanySenderLine, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeCo = htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $body = '<div style="font-family:system-ui,sans-serif;line-height:1.55;color:#111;max-width:640px">'
        . '<p>Hello <strong>' . htmlspecialchars($staffName, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</strong>,</p>'
        . '<p>An application has been assigned to you in <strong>' . $safeCo . '</strong>.</p>'
        . '<ul style="margin:12px 0;padding-left:20px">'
        . '<li><strong>Application ref:</strong> ' . $safeRef . '</li>'
        . '<li><strong>Student:</strong> ' . $safeStudent . '</li>'
        . '<li><strong>Assigned by:</strong> ' . $safeActor . '</li>'
        . '</ul>'
        . '<p style="color:#64748b;font-size:13px">Open <strong>Student Application Report</strong> in the MIS to review this file.</p>'
        . '</div>';

    if ($toEmail !== '' && filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        try {
            $mail = app_mailer();
            $mail->clearAddresses();
            $mail->addAddress($toEmail, $staffName);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
            $mail->send();
        } catch (Throwable $e) {
            @file_put_contents(
                __DIR__ . '/../email_debug.log',
                '[' . date('Y-m-d H:i:s') . '] REASSIGN NOTIFY EMAIL FAILED :: ' . $e->getMessage() . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        }
    }

    $phone = trim((string) ($staff['phone_number'] ?? ''));
    if ($phone !== '' && is_file(__DIR__ . '/task_staff_whatsapp_notify.php')) {
        require_once __DIR__ . '/task_staff_whatsapp_notify.php';
        $msg = 'You were assigned application ' . $appRef . '. Student: ' . $studentName . '. Open Student Application Report in the MIS to review.';
        try {
            pcvc_task_monitor_send_staff_whatsapp($phone, $staffName, $actorCompanySenderLine, $msg, 'default');
        } catch (Throwable $e) {
            @file_put_contents(
                __DIR__ . '/../email_debug.log',
                '[' . date('Y-m-d H:i:s') . '] REASSIGN NOTIFY WA FAILED :: ' . $e->getMessage() . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        }
    }
}
