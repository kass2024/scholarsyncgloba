<?php
// ✅ Start clean: buffer + strict error logging
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 🧠 Save crash logs
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err) {
        file_put_contents(__DIR__ . '/php_crash_log.txt', json_encode($err, JSON_PRETTY_PRINT));
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
$response = ['status' => 'error', 'message' => ''];
$step = $_POST['step'] ?? null;
if (!$step) returnJson(['status' => 'error', 'message' => 'Missing step.']);

if ($step == '1') {
    if (!empty($_POST['user_id'])) {
        // Coming from frontend re-submission or new entry
        $userId = trim($_POST['user_id']);
        $_SESSION['user_id'] = $userId;
    } else {
        // Generate new
        $userId = uniqid('user-');
        $_SESSION['user_id'] = $userId;
    }
} else {
    $userId = $_SESSION['user_id'] ?? trim($_POST['user_id'] ?? '');
}

if (!$userId) returnJson(['status' => 'error', 'message' => 'Missing user ID.']);

$stepName = "step$step";

// 🧾 Define form fields
$fieldMap = [
    'step1' => ["prefix", "prenom", "deuxiemenom", "nomfamille", "sexe", "birth_month", "birth_day", "birth_year", "adresse", "ville", "province", "postal", "pays", "email", "telephone"],
    'step2' => ["orgName", "position", "orgTel", "orgEmail", "orgStreet", "orgApt", "orgCity", "orgState", "orgZip", "orgCountry"],
    'step3' => [
        "school1_name", "school1_field", "school1_degree", "school1_from_month", "school1_from_day", "school1_from_year",
        "school1_to_month", "school1_to_day", "school1_to_year",
        "school2_name", "school2_field", "school2_degree", "school2_from_month", "school2_from_day", "school2_from_year",
        "school2_to_month", "school2_to_day", "school2_to_year",
        "school3_name", "school3_field", "school3_degree", "school3_from_month", "school3_from_day", "school3_from_year",
        "school3_to_month", "school3_to_day", "school3_to_year"
    ],
    'step4' => ["study_degree", "study_course", "study_field", "study_specialty", "study_language", "english_proficiency", "study_additional_info"],
    'step5' => [],
    'step6' => ["agreement"]
];

if (!isset($fieldMap[$stepName])) {
    returnJson(['status' => 'error', 'message' => 'Invalid step.']);
}

// 📁 Upload handler
if ($stepName === 'step5') {
    function uploadFile($key) {
        if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
            $dir = __DIR__ . '/uploads/';
            if (!file_exists($dir)) mkdir($dir, 0777, true);
            $filename = uniqid() . '_' . basename($_FILES[$key]['name']);
            $path = $dir . $filename;
            return move_uploaded_file($_FILES[$key]['tmp_name'], $path) ? 'uploads/' . $filename : '';
        }
        return '';
    }
function uploadMultipleFiles($key) {
    $savedFiles = [];

    if (isset($_FILES[$key]) && is_array($_FILES[$key]['name'])) {
        $fileCount = count($_FILES[$key]['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES[$key]['error'][$i] === UPLOAD_ERR_OK) {
                $dir = __DIR__ . '/uploads/';
                if (!file_exists($dir)) mkdir($dir, 0777, true);
                $filename = uniqid() . '_' . basename($_FILES[$key]['name'][$i]);
                $path = $dir . $filename;
                if (move_uploaded_file($_FILES[$key]['tmp_name'][$i], $path)) {
                    $savedFiles[] = 'uploads/' . $filename;
                }
            }
        }
    }

    return json_encode($savedFiles);
}

$data = [
    'photo' => uploadFile('passport_photo'),
    'passport' => uploadFile('national_id_or_passport'),
    'degree_certificate' => uploadFile('diploma_certificate'),
    'academic_transcript' => uploadFile('academic_transcripts'),
    'language_proof' => uploadFile('language_proof'),
    'recommendation_letters' => uploadMultipleFiles('recommendation_letters'),
    'other_documents' => uploadMultipleFiles('other_documents')
];

} else {
    $data = [];
    foreach ($fieldMap[$stepName] as $field) {
        $data[$field] = mysqli_real_escape_string($conn, trim($_POST[$field] ?? ''));
    }
    if ($stepName === 'step6') {
        $data['agreement'] = isset($_POST['agreement']) ? '1' : '0';
    }
}

// 🧾 SQL logic
if ($step == '1') {
    $cols = implode(", ", array_keys($data));
    $placeholders = implode(", ", array_fill(0, count($data), "?"));
    $sql = "INSERT INTO dphu (user_id, $cols) VALUES (?, $placeholders)";
    $types = 's' . str_repeat('s', count($data));
    $params = array_merge([$userId], array_values($data));
} else {
    $set = implode(" = ?, ", array_keys($data)) . " = ?";
    $sql = "UPDATE dphu SET $set WHERE user_id = ?";
    $types = str_repeat('s', count($data)) . 's';
    $params = array_merge(array_values($data), [$userId]);
}

$stmt = $conn->prepare($sql);
if (!$stmt) returnJson(['status' => 'error', 'message' => 'SQL Error: ' . $conn->error]);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $response['status'] = 'success';
    if ($step == '1') $response['user_id'] = $userId;

    // ✉️ Step 6: Trigger email
    if ($step == '6') {
        $url = ($_SERVER['HTTP_HOST'] === 'localhost')
            ? "http://localhost/scholarsyncglobal/send_email_dphu.php?user_id=" . urlencode($userId)
            : "https://scholarsyncglobal.ca/send_email_dphu.php?user_id=" . urlencode($userId);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);

        $responseCurl = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);

        $responseCurl = trim($responseCurl);
        $jsonResult = json_decode($responseCurl, true);

        $emailSuccess = false;
        if (
            ($jsonResult && ($jsonResult['status'] ?? '') === 'success') ||
            strpos($responseCurl, 'Emails sent successfully') !== false
        ) {
            $emailSuccess = true;
        } else {
            if (json_last_error() !== JSON_ERROR_NONE) {
                file_put_contents(__DIR__ . '/email_response_error.log', $responseCurl);
            }
        }

        file_put_contents(__DIR__ . '/curl_debug_output.html',
            "URL: $url\n\n" .
            "Response:\n$responseCurl\n\n" .
            "Decoded:\n" . print_r($jsonResult, true) . "\n\n" .
            "HTTP Status: " . ($curlInfo['http_code'] ?? 'N/A') . "\n" .
            "Error: $curlError\n" .
            "Info:\n" . print_r($curlInfo, true)
        );

        if (!$emailSuccess) {
            $response['status'] = 'partial';
            $response['message'] = 'Saved, but email failed (timeout or invalid JSON).';
        }
    }
} else {
    $response['message'] = $stmt->error;
}

$stmt->close();
$conn->close();

// 📤 Final JSON
returnJson($response);

// 🔒 Safe response
function returnJson($data) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
