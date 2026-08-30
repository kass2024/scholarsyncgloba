<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/study_choices.php';
require_once __DIR__ . '/helpers/application_draft.php';

function json_exit(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalize_text(?string $value): string
{
    $value = trim((string)$value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim((string)$value);
}

function normalize_email(?string $value): string
{
    $value = strtolower(normalize_text($value));
    return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
}

function normalize_area_code(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $digits = preg_replace('/\D+/', '', $value);
    return ($digits === null || $digits === '') ? '' : ('+' . $digits);
}

function normalize_phone_digits(?string $value): string
{
    $digits = preg_replace('/\D+/', '', (string)$value);
    return $digits ?? '';
}

function is_valid_phone_pair(string $areaCode, string $phoneDigits): bool
{
    if ($areaCode === '' || $phoneDigits === '') return false;
    if (!preg_match('/^\+\d{1,4}$/', $areaCode)) return false;
    if (!preg_match('/^\d{6,15}$/', $phoneDigits)) return false;
    return true;
}

if (empty($_SESSION['user_id'])) {
    json_exit([
        'status' => 'error',
        'message' => 'User session is missing.'
    ], 401);
}

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    json_exit([
        'status' => 'error',
        'message' => 'Invalid JSON payload.'
    ], 400);
}

$applicationId = (int)($data['application_id'] ?? 0);
$fields = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : [];
$studyChoices = isset($data['study_choices']) && is_array($data['study_choices']) ? $data['study_choices'] : [];
$sessionId = session_id();
$userId = (string)($_SESSION['user_id'] ?? '');

try {
    $ensured = pcvc_ensure_application_draft($conn, $sessionId, $userId, $applicationId);
    $applicationId = (int)$ensured['application_id'];
} catch (Throwable $e) {
    json_exit([
        'status' => 'error',
        'message' => 'Draft application not found.',
        'debug' => $e->getMessage()
    ], 404);
}

if ($applicationId <= 0) {
    json_exit([
        'status' => 'error',
        'message' => 'Missing application id.'
    ], 400);
}

$allowed = [
    'first_name','last_name','email',
    'area_code','phone_number','gender',
    'country_of_birth','city_of_birth','dob',
    'nationality','second_nationality',
    'passport_number','student_national_id',
    'address_line1','address_line2',
    'city','state_province','postal_code',
    'previous_institution_name',
    'previous_institution_street',
    'previous_institution_city',
    'previous_institution_province',
    'previous_institution_country',
    'previous_institution_post_code',
    'language_of_instruction',
    'previous_study_start',
    'previous_study_graduation',
    'father_first_name','father_last_name',
    'mother_first_name','mother_last_name'
];

$normalized = [];
foreach ($allowed as $field) {
    if (!array_key_exists($field, $fields)) {
        continue;
    }

    $value = $fields[$field];
    if ($field === 'email') {
        $value = normalize_email((string)$value);
    } elseif ($field === 'area_code') {
        $value = normalize_area_code((string)$value);
    } elseif ($field === 'phone_number') {
        $value = normalize_phone_digits((string)$value);
    } else {
        $value = normalize_text((string)$value);
    }

    if ($value !== '') {
        $normalized[$field] = $value;
    }
}

if (
    isset($normalized['area_code'], $normalized['phone_number']) &&
    !is_valid_phone_pair($normalized['area_code'], $normalized['phone_number'])
) {
    unset($normalized['area_code'], $normalized['phone_number']);
}

$conn->begin_transaction();

try {
    if (!empty($normalized)) {
        $set = ['session_id = ?'];
        $vals = [$sessionId];
        $types = 's';

        foreach ($normalized as $field => $value) {
            $set[] = "{$field} = ?";
            $vals[] = $value;
            $types .= 's';
        }

        $vals[] = $applicationId;
        $types .= 'i';

        $sql = "UPDATE student_applications SET " . implode(', ', $set) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $params = [];
        $params[] = &$types;
        foreach ($vals as $k => $v) {
            $params[] = &$vals[$k];
        }

        call_user_func_array([$stmt, 'bind_param'], $params);
        if (!$stmt->execute()) {
            throw new Exception('Autofill draft update failed: ' . $stmt->error);
        }
        $stmt->close();
    }

    if (!empty($studyChoices)) {
        pcvc_ensure_study_choice_schema($conn);
        $studyChoices = pcvc_normalize_study_choices($studyChoices);

        $stmt = $conn->prepare("DELETE FROM application_study_choices WHERE application_id = ?");
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "INSERT INTO application_study_choices
             (application_id, region_id, university_id, program_level_id, program_id)
             VALUES (?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            throw new Exception($conn->error);
        }

        foreach ($studyChoices as $choice) {
            $regionId = (int)($choice['region_id'] ?? 0);
            $universityId = (int)($choice['university_id'] ?? 0);
            $levelId = (int)($choice['program_level_id'] ?? 0);
            $programId = (int)($choice['program_id'] ?? 0);
            if ($regionId <= 0 || $universityId <= 0 || $levelId <= 0 || $programId <= 0) {
                continue;
            }

            $stmt->bind_param('iiiii', $applicationId, $regionId, $universityId, $levelId, $programId);
            if (!$stmt->execute()) {
                throw new Exception('Study choice insert failed: ' . $stmt->error);
            }
        }
        $stmt->close();
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    json_exit([
        'status' => 'error',
        'message' => 'Failed to save autofill draft.',
        'debug' => $e->getMessage()
    ], 500);
}

json_exit([
    'status' => 'success',
    'application_id' => $applicationId,
    'saved_fields' => array_keys($normalized)
]);
