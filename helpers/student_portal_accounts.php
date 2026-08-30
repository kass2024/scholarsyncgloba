<?php
declare(strict_types=1);

require_once __DIR__ . '/student_portal_schema.php';

/**
 * Default password requested for auto-created accounts.
 */
const PCVC_STUDENT_DEFAULT_PASSWORD = 'ScholarSync@2026';

function pcvc_student_email_norm(string $email): string
{
    return strtolower(trim($email));
}

/**
 * Ensure a portal account exists for an email (may or may not have a student_applications record yet).
 * - If student_applications exists for the email, link to latest application id.
 * - Otherwise, student_application_id remains NULL.
 */
function pcvc_student_portal_ensure_account_for_email(mysqli $conn, string $email, string $defaultPassword = PCVC_STUDENT_DEFAULT_PASSWORD): void
{
    pcvc_student_portal_ensure_schema($conn);

    $email = pcvc_student_email_norm($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $applicationId = null;
    $st = $conn->prepare("
        SELECT id
        FROM student_applications
        WHERE LOWER(TRIM(email)) = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    if ($st) {
        $st->bind_param('s', $email);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        if ($r) $applicationId = (int)$r['id'];
    }

    $stmt2 = $conn->prepare("SELECT id FROM student_portal_accounts WHERE email = ? LIMIT 1");
    $existingId = 0;
    if ($stmt2) {
        $stmt2->bind_param('s', $email);
        $stmt2->execute();
        $acc = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        if ($acc) $existingId = (int)$acc['id'];
    }

    $hash = password_hash($defaultPassword, PASSWORD_DEFAULT);
    if ($existingId > 0) {
        if ($applicationId !== null) {
            $stU = $conn->prepare("UPDATE student_portal_accounts SET student_application_id = ?, password_hash = ?, status='active' WHERE id = ?");
            if ($stU) {
                $aid = $existingId;
                $app = (int)$applicationId;
                $stU->bind_param('isi', $app, $hash, $aid);
                $stU->execute();
                $stU->close();
            }
        } else {
            $stU = $conn->prepare("UPDATE student_portal_accounts SET password_hash = ?, status='active' WHERE id = ?");
            if ($stU) {
                $aid = $existingId;
                $stU->bind_param('si', $hash, $aid);
                $stU->execute();
                $stU->close();
            }
        }
        return;
    }

    if ($applicationId !== null) {
        $app = (int)$applicationId;
        $stmtI = $conn->prepare("INSERT INTO student_portal_accounts (student_application_id, email, password_hash) VALUES (?, ?, ?)");
        if ($stmtI) {
            $stmtI->bind_param('iss', $app, $email, $hash);
            $stmtI->execute();
            $stmtI->close();
        }
        return;
    }

    $stmtI = $conn->prepare("INSERT INTO student_portal_accounts (student_application_id, email, password_hash) VALUES (NULL, ?, ?)");
    if ($stmtI) {
        $stmtI->bind_param('ss', $email, $hash);
        $stmtI->execute();
        $stmtI->close();
    }
}

