<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
require_once __DIR__ . '/helpers/fm_mobility_contract_schema.php';

header('Content-Type: application/json; charset=UTF-8');

fm_ensure_schema($conn);
fm_contract_ensure_schema($conn);

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode(['possible_match' => false]);
    exit;
}

$emailInput = trim($data['email'] ?? '');

if ($emailInput === '' || strlen($emailInput) < 3) {
    echo json_encode(['possible_match' => false]);
    exit;
}

$sql = "
    SELECT
        id,
        reference_id,
        first_name,
        last_name,
        email,
        phone_area_code,
        phone_number,
        date_of_birth,
        passport_number,
        address,
        age,
        nationality,
        country_of_residence,
        profession,
        years_experience,
        highest_degree,
        field_of_study,
        university_name,
        country_of_study,
        graduation_year,
        french_level,
        french_tef,
        french_tcf,
        english_level,
        english_toefl,
        english_ielts,
        has_wes,
        status
    FROM francophonie_mobility_applications
    WHERE email LIKE ?
    ORDER BY
        CASE WHEN LOWER(email) = LOWER(?) THEN 0 ELSE 1 END,
        id DESC
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['possible_match' => false]);
    exit;
}

$likeEmail = '%' . $emailInput . '%';
$stmt->bind_param('ss', $likeEmail, $emailInput);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) {
    echo json_encode(['possible_match' => false]);
    exit;
}

$fullName = trim(preg_replace('/\s+/', ' ', ($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')));

$phone = '';
if (!empty($app['phone_area_code']) || !empty($app['phone_number'])) {
    $phone = '+' . ltrim((string) ($app['phone_area_code'] ?? ''), '+') . ' ' . trim((string) ($app['phone_number'] ?? ''));
    $phone = trim($phone);
}

$address = trim((string) ($app['address'] ?? ''));
if ($address === '' && ($app['country_of_residence'] ?? '') !== '' && ($app['country_of_residence'] ?? '') !== 'N/A') {
    $address = 'Country of Residence: ' . $app['country_of_residence'];
}

$frenchCerts = [];
if (!empty($app['french_tef'])) {
    $frenchCerts[] = 'TEF';
}
if (!empty($app['french_tcf'])) {
    $frenchCerts[] = 'TCF';
}

$englishCerts = [];
if (!empty($app['english_toefl'])) {
    $englishCerts[] = 'TOEFL';
}
if (!empty($app['english_ielts'])) {
    $englishCerts[] = 'IELTS';
}

echo json_encode([
    'possible_match' => true,
    'applicant' => [
        'id'                  => (int) $app['id'],
        'reference_id'        => $app['reference_id'] ?? '',
        'full_name'           => $fullName,
        'email'               => $app['email'] ?? '',
        'phone'               => $phone,
        'date_of_birth'       => $app['date_of_birth'] ?? '',
        'passport_number'     => $app['passport_number'] ?? '',
        'nationality'         => ($app['nationality'] ?? '') !== 'N/A' ? ($app['nationality'] ?? '') : '',
        'address'             => $address,
        'profession'          => ($app['profession'] ?? '') !== 'N/A' ? ($app['profession'] ?? '') : '',
        'years_experience'    => ($app['years_experience'] ?? '') !== '0' ? ($app['years_experience'] ?? '') : '',
        'highest_degree'      => ($app['highest_degree'] ?? '') !== 'N/A' ? ($app['highest_degree'] ?? '') : '',
        'field_of_study'      => ($app['field_of_study'] ?? '') !== 'N/A' ? ($app['field_of_study'] ?? '') : '',
        'university_name'     => ($app['university_name'] ?? '') !== 'N/A' ? ($app['university_name'] ?? '') : '',
        'country_of_study'    => ($app['country_of_study'] ?? '') !== 'N/A' ? ($app['country_of_study'] ?? '') : '',
        'graduation_year'     => ($app['graduation_year'] ?? '') !== '0000' ? ($app['graduation_year'] ?? '') : '',
        'french_level'        => $app['french_level'] ?? '',
        'french_certificates' => implode(', ', $frenchCerts),
        'english_level'       => $app['english_level'] ?? '',
        'english_certificates'=> implode(', ', $englishCerts),
        'has_wes'             => $app['has_wes'] ?? '',
        'status'              => $app['status'] ?? '',
    ],
]);
