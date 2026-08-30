<?php
declare(strict_types=1);

/**
 * Get or create an unsubmitted student_applications draft for Smart AI / uploads.
 *
 * @return array{application_id:int, created:bool}
 */
function pcvc_ensure_application_draft(
    mysqli $conn,
    string $sessionId,
    string $userId,
    int $postedAppId = 0
): array {
    $sessionId = trim($sessionId);
    $userId = trim($userId);

    if ($postedAppId > 0) {
        $stmt = $conn->prepare(
            "SELECT id
             FROM student_applications
             WHERE id = ?
               AND submitted = 0
               AND deny = 0
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('i', $postedAppId);
            $stmt->execute();
            $stmt->bind_result($foundId);
            $ok = $stmt->fetch();
            $stmt->close();
            if ($ok && (int)$foundId === $postedAppId) {
                pcvc_touch_application_draft_session($conn, $postedAppId, $sessionId, $userId);
                return ['application_id' => $postedAppId, 'created' => false];
            }
        }
    }

    if ($userId !== '') {
        $stmt = $conn->prepare(
            "SELECT id
             FROM student_applications
             WHERE user_id = ?
               AND submitted = 0
               AND deny = 0
             ORDER BY id DESC
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('s', $userId);
            $stmt->execute();
            $stmt->bind_result($foundId);
            $ok = $stmt->fetch();
            $stmt->close();
            if ($ok && (int)$foundId > 0) {
                $appId = (int)$foundId;
                pcvc_touch_application_draft_session($conn, $appId, $sessionId, $userId);
                return ['application_id' => $appId, 'created' => false];
            }
        }
    }

    if ($sessionId !== '') {
        $stmt = $conn->prepare(
            "SELECT id
             FROM student_applications
             WHERE session_id = ?
               AND submitted = 0
               AND deny = 0
             ORDER BY id DESC
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('s', $sessionId);
            $stmt->execute();
            $stmt->bind_result($foundId);
            $ok = $stmt->fetch();
            $stmt->close();
            if ($ok && (int)$foundId > 0) {
                $appId = (int)$foundId;
                pcvc_touch_application_draft_session($conn, $appId, $sessionId, $userId);
                return ['application_id' => $appId, 'created' => false];
            }
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO student_applications (session_id, user_id, app_start, created_at)
         VALUES (?, ?, 1, NOW())"
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare draft insert: ' . $conn->error);
    }
    $stmt->bind_param('ss', $sessionId, $userId);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Could not create application draft: ' . $err);
    }
    $appId = (int)$conn->insert_id;
    $stmt->close();
    if ($appId < 1) {
        throw new RuntimeException('Application draft was not created.');
    }

    return ['application_id' => $appId, 'created' => true];
}

function pcvc_touch_application_draft_session(
    mysqli $conn,
    int $appId,
    string $sessionId,
    string $userId
): void {
    if ($appId < 1) {
        return;
    }
    $sets = [];
    $types = '';
    $vals = [];
    if ($sessionId !== '') {
        $sets[] = 'session_id = ?';
        $types .= 's';
        $vals[] = $sessionId;
    }
    if ($userId !== '') {
        $sets[] = 'user_id = COALESCE(NULLIF(user_id, \'\'), ?)';
        $types .= 's';
        $vals[] = $userId;
    }
    if ($sets === []) {
        return;
    }
    $vals[] = $appId;
    $types .= 'i';
    $sql = 'UPDATE student_applications SET ' . implode(', ', $sets) . ' WHERE id = ? AND submitted = 0';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $stmt->close();
}
