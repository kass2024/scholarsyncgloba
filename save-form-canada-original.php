<?php
// --- Force JSON Response and Error Logging ---
header('Content-Type: application/json');
ob_start(); // Avoid HTML output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

session_start();
require_once __DIR__ . '/db.php';
try {
    $userId = $_SESSION['user_id'] ?? null;
    $step = $_POST['step'] ?? null;
    $universityId = $_POST['university_id'] ?? ($_SESSION['university_id'] ?? null);

    if (!$userId || !$step) throw new Exception('Missing user ID or step.');
    $cleanStep = is_numeric($step) ? "step$step" : strtolower(trim($step));

   // --- Map form URL ---
$formMappings = [
    11 => 'form-niagara.php',
    12 => 'form-west.php',
    13 => 'form-trebas.php',
    14 => 'form-gallery.php',
    15 => 'form-lasalle.php',
    28 => 'form-windsor.php',
    31 => 'form-canadian-institue.php',
    37 => 'form-Fleming.php',
    44 =>'form-Northeastern-University.php',   
    54 =>'form-manitoba.php',
    56 =>'form-trent.php',
    58 =>'form-ilac.php',
    60 =>'form-norquest.php', 
    73 =>'form-teccart.php', 
];

    $form_url = $formMappings[$universityId] ?? 'form-usa.php';

    $fields = [];
    $params = [];
    $types = "";

    switch ($cleanStep) {
        case 'step1':
            $fields = [
                'first_name', 'last_name', 'email', 'area_code', 'phone_number', 'gender',
                'country_of_birth', 'nationality', 'second_nationality', 'city_of_birth', 'dob',
                'address_line1', 'address_line2', 'city', 'state_province', 'postal_code',
                'application_date', 'form_url', 'university_id', 'region_id'
            ];
            $_POST['form_url'] = $form_url;
            $_SESSION['university_id'] = $universityId;
            $_SESSION['region_id'] = $_POST['region_id'] ?? null;

           // --- Duplicate check ---
            $emailCheck = $_POST['email'] ?? '';
            $duplicateStmt = $conn->prepare("SELECT COUNT(*) FROM student_applications WHERE email = ? AND university_id = ?");
            $duplicateStmt->bind_param("si", $emailCheck, $universityId);
            $duplicateStmt->execute();
            $duplicateStmt->bind_result($duplicateCount);
            $duplicateStmt->fetch();
            $duplicateStmt->close();
            if ($duplicateCount > 0) {
                throw new Exception('This email has already been used to apply to this university.');
            }
            break;

        case 'step2':
            $fields = ['bachelor_program', 'masters_program', 'phd_program', 'destination', 'other_destination',
                'advanced_diploma_program', 'college_diploma_program', 'college_certificate_program', 'graduate_certificate_program'];
            $_POST['destination'] = is_array($_POST['destination']) ? implode(', ', $_POST['destination']) : ($_POST['destination'] ?? '');
            $_POST['bachelor_program'] = $_POST['advanced_diploma_program']
                ?? $_POST['college_diploma_program']
                ?? $_POST['college_certificate_program']
                ?? $_POST['bachelor_program']
                ?? null;
            $_POST['masters_program'] = $_POST['graduate_certificate_program'] ?? $_POST['masters_program'] ?? null;
            break;

        case 'step3':
    $fields = [
        'destination_loan',
        'paying_tuition_fees',
        'paying_cost_living',
        'paying_travel_expenses',
        'criminal_history',
        'disability',
        'emergency_first_name',
        'emergency_last_name',
        'emergency_email'
    ];

    $_POST['destination_loan'] = 'Canada'; // ✅ Always save as "Canada"

    // Disable loan-related fields for non-master's
    $masters = $_POST['masters_program'] ?? '';
    if (empty($masters)) {
        $_POST['paying_tuition_fees'] = $_POST['paying_cost_living'] = $_POST['paying_travel_expenses'] = null;
        $_POST['destination_loan'] = null;
    }
    break;

        case 'step4':
            $fields = ['emergency_area_code', 'emergency_phone_number', 'emergency_relationship',
                'emergency_same_address', 'intended_study_level', 'previous_institution_name',
                'previous_institution_street', 'previous_institution_city', 'previous_institution_province',
                'previous_institution_country', 'previous_institution_post_code', 'language_of_instruction'];
            $_POST['intended_study_level'] = is_array($_POST['intended_study_level']) ? implode(', ', $_POST['intended_study_level']) : $_POST['intended_study_level'];
            break;

        case 'step5':
            $fields = ['previous_study_start', 'previous_study_graduation', 'additional_secondary_school',
                'study_gap', 'post_secondary', 'passport', 'visa_rejection',
                'degree_transcripts', 'high_school_degree', 'valid_passport'];
            break;

        case 'step6':
            $fields = ['recommendation_letters', 'personal_statement', 'cv_resume', 'english_certificate', 'birth_certificate',
                'agent_first_name', 'agent_last_name', 'agent_email', 'payment_proof', 'comments'];
            break;

        default:
            throw new Exception('Invalid step provided.');
    }

    // --- Add standard fields ---
    $fields[] = 'is_read';
    $_POST['is_read'] = 0;

    foreach ($fields as $field) {
        $params[] = $_POST[$field] ?? null;
        $types .= 's';
    }

    // --- Determine if user exists ---
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM student_applications WHERE user_id = ?");
    $checkStmt->bind_param("s", $userId);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();

    // --- Build SQL ---
    if ($count > 0) {
        $setClause = implode(' = ?, ', $fields) . ' = ?';
        $sql = "UPDATE student_applications SET $setClause WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $params[] = $userId;
        $types .= 's';
        error_log("SQL UPDATE: $sql");
    } else {
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $sql = "INSERT INTO student_applications (user_id, " . implode(',', $fields) . ") VALUES (?, $placeholders)";
        $stmt = $conn->prepare($sql);
        $params = array_merge([$userId], $params);
        $types = 's' . $types;
        error_log("SQL INSERT: $sql");
    }

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        // Optional email trigger
        if ($cleanStep === 'step6') {
            $url = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')
                ? "http://localhost/scholarsyncglobal/send_application_email.php?user_id=$userId"
                : "https://scholarsyncglobal.ca/send_application_email.php?user_id=$userId";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            curl_exec($ch);
            curl_close($ch);
        }

        echo json_encode(['status' => 'success', 'user_id' => $userId]);
    } else {
        throw new Exception("Database error: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();
    ob_end_flush();

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    error_log("SAVE-FORM ERROR: " . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'user_id' => $userId ?? null,
            'step' => $step ?? null,
            'clean_step' => $cleanStep ?? null
        ]
    ]);
}
?>
