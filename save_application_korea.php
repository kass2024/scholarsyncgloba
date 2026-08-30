<?php
declare(strict_types=1);
session_start();
ob_start();
header('Content-Type: application/json; charset=utf-8');

function trigger_async_email(int $applicationId): void
{
    debug_log('ASYNC EMAIL TRIGGER CALLED', [
        'application_id' => $applicationId
    ]);

    if ($applicationId <= 0) {
        debug_log('ASYNC EMAIL ABORTED', 'Invalid application ID');
        return;
    }

    $url = 'https://scholarsyncglobal.ca/send_application_email_korea.php';

    $payload = http_build_query([
        'application_id' => $applicationId,
        'source'         => 'final_submit'
    ]);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST              => true,
        CURLOPT_POSTFIELDS        => $payload,
        CURLOPT_RETURNTRANSFER    => true,  // 🔴 MUST BE TRUE FOR DEBUG
        CURLOPT_TIMEOUT_MS        => 2000,  // allow enough time
        CURLOPT_CONNECTTIMEOUT_MS => 1000,
        CURLOPT_FRESH_CONNECT     => true,
        CURLOPT_FORBID_REUSE      => true,
        CURLOPT_SSL_VERIFYPEER    => true,
        CURLOPT_SSL_VERIFYHOST    => 2,
    ]);

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    $error    = curl_error($ch);
    $info     = curl_getinfo($ch);

    curl_close($ch);

    debug_log('ASYNC EMAIL CURL RESULT', [
        'errno'     => $errno,
        'error'     => $error,
        'http_code' => $info['http_code'] ?? null,
        'response'  => $response
    ]);
}
require_once __DIR__ . '/db.php';
function debug_log(string $label, $data = null): void
{
    $logFile = __DIR__ . '/email_debug.log';

    $msg = '[' . date('Y-m-d H:i:s') . '] ' . $label;

    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $msg .= ' :: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            $msg .= ' :: ' . (string)$data;
        }
    }

    file_put_contents($logFile, $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}


/* =====================================================
   DB CHECK
===================================================== */
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Database connection not initialized']);
    exit;
}

/* =====================================================
   SESSION CHECK
===================================================== */
$userId = $_SESSION['user_id'] ?? null; // may be null before step 1


$action = $_GET['action'] ?? null;

/* =====================================================
   LOAD META (REGIONS)
===================================================== */
if ($action === 'load_meta') {
    $regions = [];
    $res = $conn->query("SELECT id, name FROM regions ORDER BY name");
    while ($row = $res->fetch_assoc()) $regions[] = $row;

    echo json_encode(['regions'=>$regions]);
    exit;
}

/* =====================================================
   UNIVERSITIES BY REGION
===================================================== */
if ($action === 'universities') {
    $regionId = (int)($_GET['region_id'] ?? 0);

    $stmt = $conn->prepare(
        "SELECT id, name FROM universities WHERE region_id=? ORDER BY name"
    );
    $stmt->bind_param("i", $regionId);
    $stmt->execute();

    $res = $stmt->get_result();
    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;

    echo json_encode($data);
    exit;
}

/* =====================================================
   PROGRAM LEVELS BY UNIVERSITY ✅ FIXED POSITION
===================================================== */
if ($action === 'program_levels') {
    $universityId = (int)($_GET['university_id'] ?? 0);
    if ($universityId <= 0) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT DISTINCT pl.id, pl.name, pl.abbreviation
        FROM programs p
        JOIN program_levels pl ON pl.id = p.program_level_id
        WHERE p.university_id = ? AND p.is_active = 1
        ORDER BY pl.id
    ");
    $stmt->bind_param("i", $universityId);
    $stmt->execute();

    $res = $stmt->get_result();
    $levels = [];
    while ($row = $res->fetch_assoc()) $levels[] = $row;

    echo json_encode($levels);
    exit;
}

/* =====================================================
   PROGRAMS BY UNIVERSITY + LEVEL
===================================================== */
if ($action === 'programs') {

    $universityId = (int)($_GET['university_id'] ?? 0);
    $levelId      = (int)($_GET['program_level_id'] ?? 0);

    // ✅ Guard: always return valid JSON
    if ($universityId <= 0 || $levelId <= 0) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT id, program_name
        FROM programs
        WHERE university_id=? AND program_level_id=? AND is_active=1
        ORDER BY program_name
    ");

    if (!$stmt) {
        echo json_encode([]);
        exit;
    }

    $stmt->bind_param("ii", $universityId, $levelId);
    $stmt->execute();

    $res = $stmt->get_result();
    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;

    echo json_encode($data);
    exit;
}


/* =====================================================
   COUNTRIES BY REGION
===================================================== */
/* =====================================================
   LOAD COUNTRIES (CLEAN & SAFE)
===================================================== */
if ($action === 'countries') {

    // Prevent any accidental output
    ob_clean();

    $sql = "SELECT id, name FROM countries ORDER BY name";

    $res = $conn->query($sql);

    if (!$res) {
        echo json_encode([]);
        exit;
    }

    $countries = [];

    while ($row = $res->fetch_assoc()) {
        $countries[] = [
            'id'   => (int)$row['id'],
            'name' => $row['name']
        ];
    }

    echo json_encode($countries);
    exit;
}
/* =====================================================
   ALL PROGRAM LEVELS (FOR SEARCH FILTER)
===================================================== */
if ($action === 'program_levels_all') {

    $res = $conn->query("
        SELECT DISTINCT id, name
        FROM program_levels
        ORDER BY id
    ");

    $levels = [];
    while ($row = $res->fetch_assoc()) {
        $levels[] = [
            'id'   => (int)$row['id'],
            'name' => $row['name']
        ];
    }

    echo json_encode($levels);
    exit;
}

/* =====================================================
   LIVE STUDY SEARCH (REGION + LEVEL + PROGRAM)
   FINAL – PRODUCTION READY
===================================================== */
if ($action === 'study_search') {

    /* ===============================
       OUTPUT SAFETY
    =============================== */
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    /* ===============================
       INPUT NORMALIZATION
    =============================== */

    $regionsRaw = $_GET['regions'] ?? null;
    $levelId    = (int)($_GET['level'] ?? 0);
    $query      = trim((string)($_GET['q'] ?? ''));

    // Regions are mandatory
    if ($regionsRaw === null || $regionsRaw === '') {
        echo json_encode([]);
        exit;
    }

    // Accept: regions[]=1&regions[]=2 OR regions=1,2
    if (is_array($regionsRaw)) {
        $regionIds = array_map('intval', $regionsRaw);
    } else {
        $regionIds = array_map('intval', explode(',', (string)$regionsRaw));
    }

    // Remove invalid IDs
    $regionIds = array_values(array_filter($regionIds, static fn($v) => $v > 0));

    if (!$regionIds) {
        echo json_encode([]);
        exit;
    }

    /* ===============================
       SQL CONSTRUCTION
    =============================== */

    $placeholders = implode(',', array_fill(0, count($regionIds), '?'));

    $sql = "
        SELECT
            r.id   AS region_id,
            r.name AS region_name,
            u.id   AS university_id,
            u.name AS university_name,
            pl.id  AS level_id,
            pl.name AS level_name,
            p.id   AS program_id,
            p.program_name
        FROM programs p
        INNER JOIN universities u ON u.id = p.university_id
        INNER JOIN regions r ON r.id = u.region_id
        INNER JOIN program_levels pl ON pl.id = p.program_level_id
        WHERE r.id IN ($placeholders)
          AND p.is_active = 1
    ";

    $types  = str_repeat('i', count($regionIds));
    $params = $regionIds;

    // Optional: program level filter
    if ($levelId > 0) {
        $sql .= " AND pl.id = ?";
        $types .= 'i';
        $params[] = $levelId;
    }

    // Optional: search term
    if ($query !== '') {
        $sql .= " AND (u.name LIKE ? OR p.program_name LIKE ?)";
        $types .= 'ss';
        $like = '%' . $query . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= "
        ORDER BY u.name ASC, p.program_name ASC
        LIMIT 50
    ";

    /* ===============================
       EXECUTION
    =============================== */

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode([]);
        exit;
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $res = $stmt->get_result();
    $output = [];

    while ($row = $res->fetch_assoc()) {
        $output[] = [
            'region_id'       => (int)$row['region_id'],
            'region_name'     => $row['region_name'],
            'university_id'   => (int)$row['university_id'],
            'university_name' => $row['university_name'],
            'level_id'        => (int)$row['level_id'],
            'level_name'      => $row['level_name'],
            'program_id'      => (int)$row['program_id'],
            'program_name'    => $row['program_name']
        ];
    }

    $stmt->close();

    echo json_encode($output);
    exit;
}

/* =====================================================
   AUTOSAVE / FINAL SUBMIT (DEBUG + PRODUCTION SAFE)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 debug_log('POST REQUEST RECEIVED', $_POST);
    $isFinal = isset($_POST['final']) ? 1 : 0;
    /* ===============================
   FINAL SUBMIT – AGENT REQUIRED
=============================== */
if ($isFinal === 1) {

    if (
        empty($_POST['agent_first_name']) ||
        empty($_POST['agent_last_name']) ||
        empty($_POST['agent_email'])
    ) {
        http_response_code(400);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Agent selection is required before final submission.'
        ]);
        exit;
    }
}


    try {

        /* ===============================
           START TRANSACTION  ✅ REQUIRED
        =============================== */
       debug_log('Starting DB transaction');

if (!$conn->begin_transaction()) {
    debug_log('Transaction start failed', $conn->error);
    throw new Exception('Failed to start transaction');
}


 /* ===============================
   APPLICATION RESOLUTION (FINAL)
   ONE SESSION → MANY APPLICATIONS
=============================== */

$sessionId = session_id();
$appId = null;

/* ===============================
   1️⃣ USE EXISTING APPLICATION
   (Editing / autosave)
=============================== */
if (!empty($_POST['application_id'])) {

    $appId = (int)$_POST['application_id'];

    $stmt = $conn->prepare(
        "SELECT id
         FROM student_applications
         WHERE id = ? AND session_id = ?"
    );
    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("is", $appId, $sessionId);
    $stmt->execute();
    $stmt->bind_result($validId);
    $stmt->fetch();
    $stmt->close();

    if (!$validId) {
        throw new Exception('Invalid application reference');
    }
}

/* ===============================
   2️⃣ CREATE NEW APPLICATION
   (New person)
=============================== */
if (!$appId) {

    $stmt = $conn->prepare(
        "INSERT INTO student_applications
         (session_id, user_id, app_start, created_at)
         VALUES (?, ?, 1, NOW())"
    );
    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("ss", $sessionId, $userId);
    $stmt->execute();
    $appId = $stmt->insert_id;
    $stmt->close();

    if (!$appId) {
        throw new Exception('Failed to create application');
    }
}

/* ===============================
   ✅ $appId IS NOW FINAL & SAFE
=============================== */

/* ===============================
   CREATE USER ID AFTER STEP 1
=============================== */
if (
    isset($_POST['create_user']) &&
    $_POST['create_user'] === '1' &&
    empty($_SESSION['user_id'])
) {
    // Always string-based user ID
    $userId = 'guest_' . bin2hex(random_bytes(8));
    $_SESSION['user_id'] = $userId;

    $stmt = $conn->prepare(
        "UPDATE student_applications
         SET user_id = ?
         WHERE id = ? AND user_id IS NULL"
    );
    if (!$stmt) throw new Exception($conn->error);

    $stmt->bind_param("si", $userId, $appId);
    $stmt->execute();
    $stmt->close();

    debug_log('USER CREATED & ATTACHED', [
        'user_id' => $userId,
        'application_id' => $appId
    ]);
}


        /* ===============================
           UPDATE MAIN APPLICATION DATA
        =============================== */
$allowed = [

    /* ===============================
       PERSONAL INFORMATION
    =============================== */
    'first_name','last_name','email',
    'area_code','phone_number','gender',
    'country_of_birth','city_of_birth','dob',
    'nationality','second_nationality',
    'passport_number','student_national_id',

    /* ===============================
       ADDRESS
    =============================== */
    'address_line1','address_line2',
    'city','state_province','postal_code',

    /* ===============================
       DESTINATION & FINANCE
    =============================== */
    'destination','other_destination',
    'destination_loan','other_destination_loan',
    'paying_tuition_fees',
    'paying_cost_living',
    'paying_travel_expenses',

    /* ===============================
       EDUCATION – PREVIOUS STUDY
    =============================== */
    'previous_institution_name',
    'previous_institution_street',
    'previous_institution_city',
    'previous_institution_province',
    'previous_institution_country',
    'previous_institution_post_code',
    'language_of_instruction',
    'previous_study_start',
    'previous_study_graduation',

    /* ===============================
       EDUCATION – BACKGROUND QUESTIONS
    =============================== */
    'additional_secondary_school',
    'additional_secondary_details',

    'study_gap',
    'study_gap_details',

    'post_secondary',
    'post_secondary_details',

    'criminal_history',
    'criminal_history_details',

    'disability',
    'disability_details',

    'visa_rejection',
    'visa_rejection_details',

    /* ===============================
       EMERGENCY CONTACT
    =============================== */
    'emergency_first_name',
    'emergency_last_name',
    'emergency_email',
    'emergency_area_code',
    'emergency_phone_number',
    'emergency_relationship',
    'emergency_same_address',

    /* ===============================
       FAMILY
    =============================== */
    'father_first_name','father_last_name',
    'mother_first_name','mother_last_name',

    /* ===============================
       AGENT
    =============================== */
    'agent_first_name','agent_last_name','agent_email',

    /* ===============================
       COMMENTS
    =============================== */
    'comments'
];


        $set = [];
        $vals = [];
        $types = '';

        foreach ($allowed as $f) {
            if (isset($_POST[$f])) {
                $set[] = "$f=?";
                $vals[] = trim((string)$_POST[$f]);
                $types .= 's';
            }
        }

        if ($isFinal) {
            $set[] = "submitted=1";
            $set[] = "application_date=?";
            $vals[] = date('Y-m-d');
            $types .= 's';
        }

        if ($set) {
    $sql = "UPDATE student_applications
            SET session_id = ?, ".implode(',', $set)."
            WHERE id = ?";

    // ⬇️ CRITICAL: prepend session_id
    array_unshift($vals, $sessionId);
    $types = 's' . $types;

    // ⬇️ append id
    $vals[] = $appId;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception($conn->error);

    $params = [];
    $params[] = & $types;

    foreach ($vals as $k => $v) {
        $params[] = & $vals[$k];
    }

    call_user_func_array([$stmt, 'bind_param'], $params);

    $stmt->execute();
    $stmt->close();
}

        /* ===============================
           SAVE STUDY CHOICES (CRITICAL)
        =============================== */
        if (!empty($_POST['study_choices'])) {

            $choices = json_decode($_POST['study_choices'], true);
            
            if (!is_array($choices)) {
                throw new Exception('study_choices JSON invalid');
            }

            // Clear old
            $stmt = $conn->prepare(
                "DELETE FROM application_study_choices WHERE application_id=?"
            );
            if (!$stmt) throw new Exception($conn->error);

            $stmt->bind_param("i", $appId);
            $stmt->execute();
            $stmt->close();

            // Insert new
            $stmt = $conn->prepare(
                "INSERT INTO application_study_choices
                (application_id, region_id, university_id, program_level_id, program_id)
                VALUES (?,?,?,?,?)"
            );
            if (!$stmt) throw new Exception($conn->error);

            foreach ($choices as $i => $c) {
                if (
                    empty($c['region_id']) ||
                    empty($c['university_id']) ||
                    empty($c['program_level_id']) ||
                    empty($c['program_id'])
                ) {
                    continue;
                }

               $regionId  = (int)$c['region_id'];
$univId    = (int)$c['university_id'];
$levelId   = (int)$c['program_level_id'];
$programId = (int)$c['program_id'];

$stmt->bind_param(
    "iiiii",
    $appId,
    $regionId,
    $univId,
    $levelId,
    $programId
);


                if (!$stmt->execute()) {
                    throw new Exception("Insert failed at choice index $i");
                }
            }

            $stmt->close();
        }

/* ===============================
   COMMIT SUCCESS
=============================== */
$conn->commit();

/* ===============================
   SEND RESPONSE FIRST
=============================== */
echo json_encode([
    'status' => 'success',
    'application_id' => $appId
]);

// Flush response to browser immediately
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    @ob_end_flush();
    flush();
}

/* ===============================
   ASYNC EMAIL (FINAL ONLY)
=============================== */
debug_log('FINAL SUBMIT CONFIRMED – EMAIL SHOULD SEND', [
    'isFinal' => $isFinal,
    'appId'   => $appId
]);

if ($isFinal === 1) {
    trigger_async_email($appId);
}

exit;

    } catch (Throwable $e) {

        $conn->rollback();

        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Submission failed',
            'debug'   => $e->getMessage()
        ]);
        exit;
    }
}

/* =====================================================
   FALLBACK – INVALID ACTION (NEVER RETURN EMPTY)
===================================================== */
http_response_code(400);
echo json_encode([
    'status' => 'error',
    'message' => 'Invalid or missing action',
    'action_received' => $action
]);
exit;
