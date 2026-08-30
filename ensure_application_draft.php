<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/application_draft.php';
require_once __DIR__ . '/helpers/study_choices.php';

function json_exit_draft(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    json_exit_draft(['status' => 'error', 'message' => 'Database connection not initialized'], 500);
}

if (empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'user_' . bin2hex(random_bytes(6));
}

$userId = trim((string)$_SESSION['user_id']);
$sessionId = session_id();

$postedAppId = (int)($_POST['application_id'] ?? 0);
if ($postedAppId <= 0 && isset($_POST['id'])) {
    $postedAppId = (int)$_POST['id'];
}

try {
    $result = pcvc_ensure_application_draft($conn, $sessionId, $userId, $postedAppId);
    $appId = (int)$result['application_id'];

    $rawChoices = $_POST['study_choices'] ?? '';
    if (is_string($rawChoices) && trim($rawChoices) !== '') {
        $choices = json_decode($rawChoices, true);
        if (is_array($choices) && $choices) {
            pcvc_ensure_study_choice_schema($conn);
            $choices = pcvc_normalize_study_choices($choices);
            $del = $conn->prepare('DELETE FROM application_study_choices WHERE application_id = ?');
            if ($del) {
                $del->bind_param('i', $appId);
                $del->execute();
                $del->close();
            }
            $ins = $conn->prepare(
                'INSERT INTO application_study_choices
                 (application_id, region_id, university_id, program_level_id, program_id)
                 VALUES (?, ?, ?, ?, ?)'
            );
            if ($ins) {
                foreach ($choices as $c) {
                    $regionId = (int)($c['region_id'] ?? 0);
                    $univId = (int)($c['university_id'] ?? 0);
                    $levelId = (int)($c['program_level_id'] ?? 0);
                    $programId = (int)($c['program_id'] ?? 0);
                    if ($regionId < 1 || $univId < 1 || $levelId < 1 || $programId < 1) {
                        continue;
                    }
                    $ins->bind_param('iiiii', $appId, $regionId, $univId, $levelId, $programId);
                    $ins->execute();
                }
                $ins->close();
            }
        }
    }

    json_exit_draft([
        'status' => 'success',
        'application_id' => $appId,
        'created' => !empty($result['created']),
    ]);
} catch (Throwable $e) {
    json_exit_draft([
        'status' => 'error',
        'message' => 'Could not prepare the application draft for document analysis.',
        'debug' => $e->getMessage(),
    ], 500);
}
