<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';

pcvc_require_superadmin($conn, true);

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw !== false && $raw !== '' ? $raw : '', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$type      = trim((string) ($data['type'] ?? ''));
$firstName = trim((string) ($data['first_name'] ?? ''));
$lastName  = trim((string) ($data['last_name'] ?? ''));
$email     = trim((string) ($data['email'] ?? ''));
$phone     = trim((string) ($data['phone'] ?? ''));

if (!in_array($type, ['credit_transfer', 'upafa'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid program type']);
    exit;
}

if ($firstName === '' || $lastName === '' || $email === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'First name, last name, and email are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

/**
 * @return int duplicate row count
 */
function rsp_email_count(mysqli $conn, string $table, string $email): int
{
    $allowed = ['credit_transfer_applications', 'upafa_registrations'];
    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    $sql = "SELECT COUNT(*) AS c FROM `{$table}` WHERE email = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Database prepare failed');
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['c'] ?? 0);
}

try {
    if ($type === 'credit_transfer') {
        if (rsp_email_count($conn, 'credit_transfer_applications', $email) > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This email is already registered for credit transfer']);
            exit;
        }

        $userId = 'credit-' . time() . '-' . random_int(1000, 9999);

        $stmt = $conn->prepare(
            'INSERT INTO credit_transfer_applications
             (user_id, first_name, last_name, email, mobile_number, submitted_at, is_read)
             VALUES (?, ?, ?, ?, ?, NOW(), 0)'
        );
        if (!$stmt) {
            throw new RuntimeException('Database prepare failed');
        }

        $stmt->bind_param('sssss', $userId, $firstName, $lastName, $email, $phone);
        $stmt->execute();
        $newId = (int) $stmt->insert_id;
        $stmt->close();

        echo json_encode([
            'success'   => true,
            'message'   => 'Credit transfer applicant registered',
            'id'        => $newId,
            'table'     => 'credit_transfer_applications',
            'full_name' => trim($firstName . ' ' . $lastName),
            'email'     => $email,
            'phone'     => $phone,
            'ref'       => $userId,
        ]);
        exit;
    }

    if (rsp_email_count($conn, 'upafa_registrations', $email) > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This email is already registered for UPAFA']);
        exit;
    }

    $year            = (int) date('Y');
    $academicYear    = $year . '-' . ($year + 1);
    $today           = date('Y-m-d');
    $commitmentName  = trim($firstName . ' ' . $lastName);
    $pending         = 'Pending';
    $na              = 'N/A';
    $birthDate       = '2000-01-01';
    $yearFrom        = $year - 4;
    $yearTo          = $year;
    $registrationFee = 0.0;
    $tuitionFee      = 0.0;
    $scholarship     = 'No';
    $schInstitution  = '';
    $referredScholarSync  = 'No';
    $refInstitution  = '';
    $doneAt          = 'Office';

    $stmt = $conn->prepare(
        'INSERT INTO upafa_registrations (
            academic_year, last_name, first_name, nationality, birth_place, birth_date,
            highest_education, department, school_name_address, year_from, year_to,
            intended_degree, field_of_study, registration_fees, tuition_fees,
            scholarship, scholarship_institution, referred_by_parrot, ref_institution,
            telephone, email, commitment_name, done_at, done_date
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    if (!$stmt) {
        throw new RuntimeException('Database prepare failed');
    }

    $types = 'sssssssss' . 'ii' . 'ss' . 'dd' . 'sssssssss';
    $stmt->bind_param(
        $types,
        $academicYear,
        $lastName,
        $firstName,
        $na,
        $na,
        $birthDate,
        $pending,
        $pending,
        $pending,
        $yearFrom,
        $yearTo,
        $pending,
        $pending,
        $registrationFee,
        $tuitionFee,
        $scholarship,
        $schInstitution,
        $referredScholarSync,
        $refInstitution,
        $phone,
        $email,
        $commitmentName,
        $doneAt,
        $today
    );
    $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        'success'   => true,
        'message'   => 'UPAFA applicant registered',
        'id'        => $newId,
        'table'     => 'upafa_registrations',
        'full_name' => trim($firstName . ' ' . $lastName),
        'email'     => $email,
        'phone'     => $phone,
        'ref'       => $academicYear,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
}

$conn->close();
