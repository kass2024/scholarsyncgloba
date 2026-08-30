<?php
declare(strict_types=1);

/**
 * Normalize commission_requests.user_id (varchar or numeric) to int for comparison.
 */
function pcvc_commission_user_id_matches_row(string|int|null $rowUserId, int $sessionUserId): bool
{
    if ($sessionUserId < 1) {
        return false;
    }
    $raw = trim((string) ($rowUserId ?? ''));
    if ($raw === '') {
        return false;
    }
    if ((string) (int) $sessionUserId === $raw) {
        return true;
    }
    if (ctype_digit($raw) && (int) $raw === $sessionUserId) {
        return true;
    }

    return false;
}

/**
 * Resolve recruited_student_id (numeric in DB) to select value s_ID or a_ID for this agent.
 */
function pcvc_commission_resolve_student_key(
    mysqli $conn,
    mysqli $conn2,
    int $numericId,
    string $agentEmailKey
): string {
    if ($numericId < 1) {
        return '';
    }
    $stmt = $conn->prepare('SELECT id FROM student_applications WHERE id = ? AND LOWER(TRIM(agent_email)) = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('is', $numericId, $agentEmailKey);
        $stmt->execute();
        if ($stmt->get_result()->fetch_row()) {
            $stmt->close();

            return 's_' . $numericId;
        }
        $stmt->close();
    }
    $stmt2 = $conn2->prepare('SELECT id FROM applications WHERE id = ? AND LOWER(TRIM(agent_email)) = ? LIMIT 1');
    if ($stmt2) {
        $stmt2->bind_param('is', $numericId, $agentEmailKey);
        $stmt2->execute();
        if ($stmt2->get_result()->fetch_row()) {
            $stmt2->close();

            return 'a_' . $numericId;
        }
        $stmt2->close();
    }

    return 's_' . $numericId;
}
