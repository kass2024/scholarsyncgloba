<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';

header('Content-Type: text/plain; charset=utf-8');

if (!pcvc_current_user_is_staff_or_superadmin($conn)) {
    http_response_code(403);
    exit('unauthorized');
}

$id = (int) ($_POST['id'] ?? 0);
$field = trim((string) ($_POST['field'] ?? ''));
$value = trim((string) ($_POST['value'] ?? ''));
$source = trim((string) ($_POST['source'] ?? 'student_applications'));

$allowedSources = ['student_applications', 'malta_applications', 'turkey_applications'];
if ($id <= 0 || $field === '' || !in_array($source, $allowedSources, true)) {
    exit('invalid');
}

/**
 * @return array{0: string, 1: string}
 */
function pcvc_split_full_name(string $full): array
{
    $full = trim(preg_replace('/\s+/u', ' ', $full));
    if ($full === '') {
        return ['', ''];
    }

    $parts = preg_split('/\s+/u', $full) ?: [];
    if (count($parts) === 1) {
        return [$parts[0], ''];
    }

    $first = (string) array_shift($parts);
    $last = trim(implode(' ', $parts));

    return [$first, $last];
}

if ($field === 'full_name') {
    [$firstName, $lastName] = pcvc_split_full_name($value);

    if ($source === 'student_applications') {
        $stmt = $conn->prepare('UPDATE student_applications SET first_name = ?, last_name = ? WHERE id = ?');
        if (!$stmt) {
            exit('error');
        }
        $stmt->bind_param('ssi', $firstName, $lastName, $id);
        echo $stmt->execute() ? 'ok' : 'error';
        $stmt->close();
        exit;
    }

    if ($source === 'malta_applications') {
        $stmt = $conn->prepare('UPDATE malta_applications SET name = ?, surname = ? WHERE id = ?');
        if (!$stmt) {
            exit('error');
        }
        $stmt->bind_param('ssi', $firstName, $lastName, $id);
        echo $stmt->execute() ? 'ok' : 'error';
        $stmt->close();
        exit;
    }

    if ($source === 'turkey_applications') {
        $stmt = $conn->prepare('UPDATE turkey_applications SET first_name = ?, last_name = ? WHERE id = ?');
        if (!$stmt) {
            exit('error');
        }
        $stmt->bind_param('ssi', $firstName, $lastName, $id);
        echo $stmt->execute() ? 'ok' : 'error';
        $stmt->close();
        exit;
    }

    exit('invalid');
}

// Allowed fields per table
$allowed_fields = [
    'student_applications' => [
        'first_name', 'last_name', 'email', 'phone_number', 'gender', 'dob',
        'nationality', 'city', 'address_line1', 'masters_program',
        'destination', 'application_date',
    ],
    'malta_applications' => [
        'name', 'surname', 'email', 'contact_number', 'gender', 'dob',
        'nationality', 'birth_place', 'address', 'degree_program', 'created_at',
    ],
    'turkey_applications' => [
        'transfer_student', 'have_tc', 'blue_card',
        'first_name', 'last_name', 'passport_no', 'issue_date', 'expiry_date',
        'gender', 'dob', 'nationality', 'residence_country', 'student_id',
        'email', 'area_code', 'mobile', 'address', 'city', 'province',
        'postal_code', 'country', 'father_name', 'father_mobile',
        'father_occupation', 'mother_name', 'agent_first_name', 'agent_last_name',
        'agent_email', 'photo', 'degree', 'transcript', 'cv', 'valid_passport',
        'is_read', 'submitted_at', 'region_id', 'university_id',
    ],
];

// Mapping for malta_applications (UI → DB)
$malta_map = [
    'first_name' => 'name',
    'last_name' => 'surname',
    'phone_number' => 'contact_number',
    'city' => 'birth_place',
    'address_line1' => 'address',
    'masters_program' => 'degree_program',
    'application_date' => 'created_at',
];

// Mapping for turkey_applications (UI → DB)
$turkey_map = [
    'phone_number' => 'mobile',
    'address_line1' => 'address',
    'application_date' => 'submitted_at',
];

if ($source === 'malta_applications' && isset($malta_map[$field])) {
    $field = $malta_map[$field];
}

if ($source === 'turkey_applications' && isset($turkey_map[$field])) {
    $field = $turkey_map[$field];
}

if (!in_array($field, $allowed_fields[$source] ?? [], true)) {
    exit('invalid');
}

$stmt = $conn->prepare("UPDATE `$source` SET `$field` = ? WHERE id = ?");
if (!$stmt) {
    exit('error');
}

$stmt->bind_param('si', $value, $id);
echo $stmt->execute() ? 'ok' : 'error';
$stmt->close();
