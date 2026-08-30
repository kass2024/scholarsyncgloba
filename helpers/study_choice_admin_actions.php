<?php
declare(strict_types=1);

require_once __DIR__ . '/study_choices.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/../includes/company_branding.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Validate FK relationships for a study choice row (region ↔ university ↔ program).
 *
 * @return non-empty-string|null Error message, or null when valid.
 */
function pcvc_validate_study_choice_relations(
    mysqli $conn,
    int $regionId,
    int $universityId,
    int $levelId,
    int $programId
): ?string {
    if ($regionId <= 0 || $universityId <= 0 || $levelId <= 0 || $programId <= 0) {
        return 'Each of region, university, level, and program must be selected.';
    }

    $st = $conn->prepare('SELECT region_id FROM universities WHERE id = ? LIMIT 1');
    if (!$st) {
        return 'Database error (university lookup).';
    }
    $st->bind_param('i', $universityId);
    $st->execute();
    $ur = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$ur) {
        return 'University not found.';
    }
    if ((int) ($ur['region_id'] ?? 0) !== $regionId) {
        return 'University does not belong to the selected region.';
    }

    $st = $conn->prepare(
        'SELECT id FROM programs WHERE id = ? AND university_id = ? AND program_level_id = ? AND is_active = 1 LIMIT 1'
    );
    if (!$st) {
        return 'Database error (program lookup).';
    }
    $st->bind_param('iii', $programId, $universityId, $levelId);
    $st->execute();
    $ok = (bool) $st->get_result()->fetch_assoc();
    $st->close();

    if (!$ok) {
        return 'Program does not match this university and level (or is inactive).';
    }

    return null;
}

/**
 * Insert one study choice; detects duplicate unique key (errno 1062).
 *
 * @return array{inserted:bool,duplicate:bool}
 */
function pcvc_try_insert_application_study_choice(
    mysqli $conn,
    int $applicationId,
    int $regionId,
    int $universityId,
    int $levelId,
    int $programId
): array {
    pcvc_ensure_study_choice_schema($conn);

    $stmt = $conn->prepare(
        'INSERT INTO application_study_choices
            (application_id, region_id, university_id, program_level_id, program_id)
         VALUES (?,?,?,?,?)'
    );
    if (!$stmt) {
        return ['inserted' => false, 'duplicate' => false, 'error' => 'Could not prepare insert.'];
    }

    $stmt->bind_param('iiiii', $applicationId, $regionId, $universityId, $levelId, $programId);

    if (!$stmt->execute()) {
        $errno = (int) ($stmt->errno ?: $conn->errno);
        $err = (string) ($stmt->error ?: $conn->error);
        $stmt->close();
        if ($errno === 1062) {
            return ['inserted' => false, 'duplicate' => true, 'error' => ''];
        }
        return ['inserted' => false, 'duplicate' => false, 'error' => $err !== '' ? $err : 'Insert failed.'];
    }

    $stmt->close();
    return ['inserted' => true, 'duplicate' => false, 'error' => ''];
}

/**
 * Same rows as api/applications.php?view study_choices payload.
 *
 * @return list<array<string,mixed>>
 */
function pcvc_fetch_study_choices_for_admin_view(mysqli $conn, int $applicationId): array
{
    if ($applicationId <= 0) {
        return [];
    }

    $stmt = $conn->prepare(
        "
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
        ORDER BY ascx.id ASC
    "
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows ?: [];
}

/**
 * Remove incomplete assignment-based jobs for a staff member on an application.
 */
function pcvc_remove_incomplete_assignment_jobs(mysqli $conn, int $applicationId, int $adminId): void
{
    if ($applicationId <= 0 || $adminId <= 0) {
        return;
    }

    $stmt = $conn->prepare(
        'DELETE FROM job_list
         WHERE application_id = ?
           AND admin_id = ?
           AND is_auto_created = 1
           AND status = \'not_completed\'
           AND (platform_id IS NULL OR platform_id = 0)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ii', $applicationId, $adminId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Create Job Do List rows for the assigned staff member (one per study-choice university).
 */
function pcvc_ensure_assignment_jobs_for_application(mysqli $conn, int $applicationId, int $assigneeAdminId): int
{
    if ($applicationId <= 0 || $assigneeAdminId <= 0) {
        return 0;
    }

    $stmt = $conn->prepare('SELECT id FROM admins WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $assigneeAdminId);
    $stmt->execute();
    $assigneeOk = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$assigneeOk) {
        return 0;
    }

    $stmt = $conn->prepare(
        'SELECT first_name, last_name, email
         FROM student_applications
         WHERE id = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $basic = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $applicantName = '';
    $applicantEmail = '';
    if ($basic) {
        $applicantName = trim((string) ($basic['first_name'] ?? '') . ' ' . (string) ($basic['last_name'] ?? ''));
        $applicantEmail = trim((string) ($basic['email'] ?? ''));
    }

    $stmt = $conn->prepare(
        'SELECT DISTINCT
            ascx.university_id,
            u.name AS university_name,
            c.name AS country_name
         FROM application_study_choices ascx
         JOIN universities u ON u.id = ascx.university_id
         LEFT JOIN countries c ON c.id = u.country_id
         WHERE ascx.application_id = ?'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $choices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $checkJob = $conn->prepare(
        'SELECT id
         FROM job_list
         WHERE application_id = ?
           AND university_id <=> ?
           AND admin_id = ?
           AND (platform_id IS NULL OR platform_id = 0)
           AND is_auto_created = 1
         LIMIT 1'
    );
    $insertJob = $conn->prepare(
        'INSERT INTO job_list
            (admin_id, application_id, university_id, platform_id, title,
             applicant_name, applicant_email, job_type, status, is_auto_created)
         VALUES
            (?, ?, ?, NULL, ?, ?, ?, \'Student Admission Application\', \'not_completed\', 1)'
    );
    if (!$checkJob || !$insertJob) {
        $checkJob?->close();
        $insertJob?->close();
        return 0;
    }

    $jobsCreated = 0;

    if (!$choices) {
        $jobTitle = sprintf('Application #%d', $applicationId);
        $fallbackUniversityId = 0;
        $checkJob->bind_param('iii', $applicationId, $fallbackUniversityId, $assigneeAdminId);
        $checkJob->execute();
        $checkJob->store_result();
        if ($checkJob->num_rows === 0) {
            $insertJob->bind_param(
                'iiisss',
                $assigneeAdminId,
                $applicationId,
                $fallbackUniversityId,
                $jobTitle,
                $applicantName,
                $applicantEmail
            );
            if ($insertJob->execute()) {
                $jobsCreated++;
            }
        }
        $checkJob->close();
        $insertJob->close();
        return $jobsCreated;
    }

    foreach ($choices as $choice) {
        $universityId = (int) ($choice['university_id'] ?? 0);
        if ($universityId <= 0) {
            continue;
        }

        $jobTitle = sprintf(
            'Application #%d – %s (%s)',
            $applicationId,
            $choice['university_name'] ?? 'University',
            $choice['country_name'] ?? 'Unknown'
        );

        $checkJob->bind_param('iii', $applicationId, $universityId, $assigneeAdminId);
        $checkJob->execute();
        $checkJob->store_result();
        if ($checkJob->num_rows > 0) {
            continue;
        }

        $insertJob->bind_param(
            'iiisss',
            $assigneeAdminId,
            $applicationId,
            $universityId,
            $jobTitle,
            $applicantName,
            $applicantEmail
        );
        if ($insertJob->execute()) {
            $jobsCreated++;
        }
    }

    $checkJob->close();
    $insertJob->close();

    return $jobsCreated;
}

/**
 * @deprecated Platform auto-jobs disabled — use pcvc_ensure_assignment_jobs_for_application().
 */
function pcvc_ensure_auto_jobs_for_university(mysqli $conn, int $applicationId, int $universityId): int
{
    unset($applicationId, $universityId);
    return 0;
}

/**
 * Email the student that a study choice was added by staff (best-effort; errors swallowed).
 */
function pcvc_notify_student_study_choice_added(
    mysqli $conn,
    int $applicationId,
    int $regionId,
    int $universityId,
    int $levelId,
    int $programId
): bool {
    $stmt = $conn->prepare(
        '
        SELECT first_name, last_name, email
        FROM student_applications
        WHERE id = ?
        LIMIT 1
    '
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$app) {
        return false;
    }

    $email = trim((string) ($app['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $name = trim((string) ($app['first_name'] ?? '') . ' ' . (string) ($app['last_name'] ?? ''));
    if ($name === '') {
        $name = 'Applicant';
    }

    $stmt = $conn->prepare(
        '
        SELECT
            r.name AS region,
            u.name AS university,
            c.name AS university_country,
            pl.name AS program_level,
            pl.abbreviation AS program_level_abbr,
            p.program_name AS program
        FROM application_study_choices ascx
        JOIN universities u ON u.id = ascx.university_id
        JOIN regions r ON r.id = ascx.region_id
        JOIN program_levels pl ON pl.id = ascx.program_level_id
        JOIN programs p ON p.id = ascx.program_id
        LEFT JOIN countries c ON c.id = u.country_id
        WHERE ascx.application_id = ?
          AND ascx.region_id = ?
          AND ascx.university_id = ?
          AND ascx.program_level_id = ?
          AND ascx.program_id = ?
        LIMIT 1
    '
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iiiii', $applicationId, $regionId, $universityId, $levelId, $programId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return false;
    }

    $region = htmlspecialchars((string) ($row['region'] ?? ''), ENT_QUOTES, 'UTF-8');
    $uni = htmlspecialchars((string) ($row['university'] ?? ''), ENT_QUOTES, 'UTF-8');
    $country = htmlspecialchars((string) ($row['university_country'] ?? ''), ENT_QUOTES, 'UTF-8');
    $lvl = htmlspecialchars((string) ($row['program_level_abbr'] ?? $row['program_level'] ?? ''), ENT_QUOTES, 'UTF-8');
    $prog = htmlspecialchars((string) ($row['program'] ?? ''), ENT_QUOTES, 'UTF-8');

    try {
        /** @var PHPMailer $mail */
        $mail = app_mailer();
        $mail->clearAddresses();
        $mail->clearAttachments();
        $mail->setFrom(PCVC_COMPANY_SUPPORT_EMAIL, PCVC_COMPANY_DISPLAY_NAME);
        $mail->clearReplyTos();
        $mail->addReplyTo(PCVC_COMPANY_SUPPORT_EMAIL, PCVC_COMPANY_DISPLAY_NAME);
        $mail->addAddress($email, $name);
        $mail->Subject = PCVC_COMPANY_DISPLAY_NAME . ' — Study choice updated (application #' . $applicationId . ')';
        $mail->Body = '
<div style="font-family:Arial,sans-serif;line-height:1.55;color:#111;max-width:640px">
  <p>Hello <strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
  <p>Your application has been updated with an additional study choice:</p>
  <table style="border-collapse:collapse;width:100%;margin:14px 0;font-size:14px">
    <tr><th style="text-align:left;padding:8px;border:1px solid #e5e7eb;background:#f9fafb;width:140px">Region</th><td style="padding:8px;border:1px solid #e5e7eb">' . $region . '</td></tr>
    <tr><th style="text-align:left;padding:8px;border:1px solid #e5e7eb;background:#f9fafb">University</th><td style="padding:8px;border:1px solid #e5e7eb">' . $uni . '</td></tr>
    <tr><th style="text-align:left;padding:8px;border:1px solid #e5e7eb;background:#f9fafb">Country</th><td style="padding:8px;border:1px solid #e5e7eb">' . ($country !== '' ? $country : '—') . '</td></tr>
    <tr><th style="text-align:left;padding:8px;border:1px solid #e5e7eb;background:#f9fafb">Level</th><td style="padding:8px;border:1px solid #e5e7eb">' . $lvl . '</td></tr>
    <tr><th style="text-align:left;padding:8px;border:1px solid #e5e7eb;background:#f9fafb">Program</th><td style="padding:8px;border:1px solid #e5e7eb">' . $prog . '</td></tr>
  </table>
  <p style="color:#6b7280;font-size:13px">If you did not expect this message, please contact us at '
            . htmlspecialchars(PCVC_COMPANY_SUPPORT_EMAIL, ENT_QUOTES, 'UTF-8') . '.</p>
</div>';

        $mail->send();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
