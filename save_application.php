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

    $url = 'https://scholarsyncglobal.ca/send_application_email.php';

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
require_once __DIR__ . '/helpers/mailer.php';
require_once __DIR__ . '/helpers/student_portal_accounts.php';
require_once __DIR__ . '/helpers/study_choices.php';
require_once __DIR__ . '/helpers/urls.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/includes/company_branding.php';
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

function pcvc_get_application_study_choice_summaries(mysqli $conn, int $applicationId): array
{
    if ($applicationId <= 0) {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT
            r.name AS region_name,
            u.name AS university_name,
            pl.name AS level_name,
            p.program_name
        FROM application_study_choices sc
        JOIN regions r ON r.id = sc.region_id
        JOIN universities u ON u.id = sc.university_id
        JOIN program_levels pl ON pl.id = sc.program_level_id
        JOIN programs p ON p.id = sc.program_id
        WHERE sc.application_id = ?
        ORDER BY sc.id ASC
    ");

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $applicationId);
    $stmt->execute();
    $res = $stmt->get_result();

    $choices = [];
    while ($row = $res->fetch_assoc()) {
        $parts = array_filter([
            trim((string)($row['program_name'] ?? '')),
            trim((string)($row['level_name'] ?? '')),
            trim((string)($row['university_name'] ?? '')),
            trim((string)($row['region_name'] ?? ''))
        ], static fn($value) => $value !== '');

        if ($parts) {
            $choices[] = implode(' - ', $parts);
        }
    }

    $stmt->close();
    return $choices;
}

function pcvc_send_student_portal_access_email(string $email, string $studentName = '', int $applicationId = 0): void
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;

    $studentName = trim($studentName) !== '' ? trim($studentName) : 'Student';

    // Ensure portal account exists / reset to default password (same as Share access)
    global $conn;
    try {
        if (isset($conn) && $conn instanceof mysqli) {
            pcvc_student_portal_ensure_account_for_email($conn, $email);
        }
    } catch (Throwable $e) {
        // Don't block submission flow
        debug_log('PORTAL ACCOUNT ENSURE FAILED', $e->getMessage());
    }

    $studyChoices = [];
    try {
        if (isset($conn) && $conn instanceof mysqli) {
            $studyChoices = pcvc_get_application_study_choice_summaries($conn, $applicationId);
        }
    } catch (Throwable $e) {
        debug_log('PORTAL ACCESS STUDY CHOICES LOAD FAILED', $e->getMessage());
    }

    // Build login link (prefill email)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = $scheme . '://' . $host;
    $loginUrl = $base . pcvc_url('/student-login.php') . '?email=' . rawurlencode($email);

    $defaultPw = PCVC_STUDENT_DEFAULT_PASSWORD;
    $subject = 'Your Student Portal Access – ' . PCVC_COMPANY_DISPLAY_NAME;
    $studyChoicesHtml = '';
    $studyChoicesText = '';
    if ($studyChoices) {
        $itemsHtml = '';
        foreach ($studyChoices as $choice) {
            $itemsHtml .= '<li style="margin:0 0 6px 0"><strong>' . htmlspecialchars($choice, ENT_QUOTES, 'UTF-8') . '</strong></li>';
        }
        $studyChoicesHtml = '
        <div style="margin:18px 0 14px 0;padding:14px 16px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff">
          <p style="margin:0 0 10px 0;font-weight:700;color:#0f172a">Your study choices</p>
          <ul style="margin:0;padding-left:18px;color:#1f2937">
            ' . $itemsHtml . '
          </ul>
        </div>';

        $studyChoicesText = "Study choices:\n";
        foreach ($studyChoices as $choice) {
            $studyChoicesText .= "- {$choice}\n";
        }
        $studyChoicesText .= "\n";
    }

    $body = "
      <div style=\"font-family:Arial,sans-serif;line-height:1.6;color:#111\">
        <h2 style=\"margin:0 0 12px 0\">Student Portal Access</h2>
        <p>Hello <strong>" . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
        <p>Thank you for submitting your application. The Admissions Department has prepared your student portal so you can track your application status and upload required materials securely.</p>
        " . $studyChoicesHtml . "
        <p style=\"margin:16px 0\">
          <a href=\"" . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . "\" style=\"
            display:inline-block;background:#3661B9;color:#fff;text-decoration:none;
            padding:10px 14px;border-radius:8px;font-weight:700;
          \">Open Student Portal</a>
        </p>
        <p><strong>Login details</strong></p>
        <ul>
          <li>Email: <strong>" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</strong></li>
          <li>Default password: <strong>" . htmlspecialchars($defaultPw, ENT_QUOTES, 'UTF-8') . "</strong></li>
        </ul>
        <p>If the button doesn’t work, copy/paste this link:</p>
        <p><a href=\"" . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . "</a></p>
        <p style=\"margin-top:18px\">Thank you,<br>Admissions Department<br>" . htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') . "</p>
      </div>
    ";

    $mail = app_mailer();
    $mail->setFrom(PCVC_COMPANY_SUPPORT_EMAIL, PCVC_COMPANY_DISPLAY_NAME . ' - Admission Department');
    $mail->clearReplyTos();
    $mail->addReplyTo(PCVC_COMPANY_SUPPORT_EMAIL, PCVC_COMPANY_DISPLAY_NAME . ' - Admission Department');
    $mail->addAddress($email, $studentName);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->AltBody = "Student Portal Access\n\nHello {$studentName},\n\nThe Admissions Department has prepared your student portal for your application.\n\n{$studyChoicesText}Login: {$loginUrl}\nEmail: {$email}\nPassword: {$defaultPw}\n\nThank you,\nAdmissions Department\n" . PCVC_COMPANY_DISPLAY_NAME . "\n";
    $mail->send();
}

/* =====================================================
   SMART VALIDATION (ANTI-JUNK)
===================================================== */
function json_error(string $message, array $fields = [], int $code = 400): void
{
    http_response_code($code);
    echo json_encode([
        'status'  => 'error',
        'message' => $message,
        'fields'  => (object)$fields,
    ]);
    exit;
}

function norm_ws(string $s): string
{
    $s = trim($s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return $s ?? '';
}

function looks_like_human_name(string $name): bool
{
    $name = norm_ws($name);
    if ($name === '') return false;
    if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 60) return false;

    // Allow letters (unicode), spaces, hyphen, apostrophe, dot.
    if (!preg_match("/^[\\p{L} .'-]+$/u", $name)) return false;

    // Must contain at least 2 letters total.
    if (preg_match_all("/\\p{L}/u", $name, $m) < 2) return false;

    // Block repeated same character junk like "aaaaaa" or "zzzzzz".
    $compact = mb_strtolower(preg_replace('/\s+/u', '', $name), 'UTF-8');
    if ($compact !== '' && preg_match('/(.)\\1\\1\\1/u', $compact)) return false;

    // Block names that are mostly 1–2 unique letters (random spam-ish).
    $lettersOnly = mb_strtolower(preg_replace("/[^\\p{L}]+/u", '', $name), 'UTF-8');
    if ($lettersOnly !== '' && mb_strlen($lettersOnly, 'UTF-8') >= 6) {
        $chars = function_exists('mb_str_split') ? mb_str_split($lettersOnly) : preg_split('//u', $lettersOnly, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($chars)) {
            $unique = array_unique($chars);
            if (count($unique) <= 2) return false;
        }
    }

    return true;
}

function normalize_area_code(?string $areaCode): string
{
    $areaCode = trim((string)$areaCode);
    if ($areaCode === '') return '';
    // keep only digits, preserve leading +
    $digits = preg_replace('/\D+/', '', $areaCode);
    if ($digits === null) $digits = '';
    return $digits === '' ? '' : ('+' . $digits);
}

function normalize_phone_digits(?string $phone): string
{
    $digits = preg_replace('/\D+/', '', (string)$phone);
    return $digits ?? '';
}

function is_valid_phone_pair(string $areaCode, string $phoneDigits): bool
{
    // E.164-like: country code 1–4 digits, national number 6–15 digits.
    if ($areaCode === '' || $phoneDigits === '') return false;
    if (!preg_match('/^\\+\\d{1,4}$/', $areaCode)) return false;
    if (!preg_match('/^\\d{6,15}$/', $phoneDigits)) return false;

    // Block "all same digit" like 0000000 / 11111111.
    if (preg_match('/^(\\d)\\1+$/', $phoneDigits)) return false;

    // Block 6+ repeats anywhere (e.g. 1234444444).
    if (preg_match('/(\\d)\\1{5,}/', $phoneDigits)) return false;

    return true;
}

/**
 * Whether another application row already uses this email (case-insensitive).
 * Excludes the current draft/application id so the same user can save their own row.
 */
function pcvc_applicant_email_taken(mysqli $conn, string $emailNorm, int $excludeApplicationId): bool
{
    if ($emailNorm === '' || !filter_var($emailNorm, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $stmt = $conn->prepare(
        'SELECT id FROM student_applications
         WHERE LOWER(TRIM(email)) = ?
           AND TRIM(COALESCE(email, \'\')) <> \'\'
           AND id <> ?
         LIMIT 1'
    );
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param('si', $emailNorm, $excludeApplicationId);
    $stmt->execute();
    $stmt->bind_result($foundId);
    $has = $stmt->fetch();
    $stmt->close();
    return $has && (int)$foundId > 0;
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
   EMAIL AVAILABILITY CHECK
===================================================== */
if ($action === 'check_email') {
    header('Content-Type: application/json; charset=utf-8');

    $email = strtolower(trim((string)($_GET['email'] ?? '')));
    $excludeApplicationId = (int)($_GET['application_id'] ?? 0);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'status' => 'success',
            'exists' => false,
            'message' => ''
        ]);
        exit;
    }

    $exists = pcvc_applicant_email_taken($conn, $email, $excludeApplicationId);

    echo json_encode([
        'status' => 'success',
        'exists' => $exists,
        'message' => $exists
            ? 'This email is already registered with an existing application.'
            : ''
    ]);
    exit;
}

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

    $assignedPosted = array_key_exists('assigned_to_admin_id', $_POST);
    $assignedToAdminId = null;
    if ($assignedPosted) {
        $tmpAssign = (int)trim((string)($_POST['assigned_to_admin_id'] ?? ''));
        $assignedToAdminId = $tmpAssign > 0 ? $tmpAssign : null;
    }

    $smartIdentitySubmit = (
        $isFinal === 1
        && isset($_POST['smart_identity_submit'])
        && (string)$_POST['smart_identity_submit'] === '1'
    );

    $identityOnlySubmitAllowed = false;
    if ($smartIdentitySubmit && !empty($_POST['application_id'])) {
        $identityAppId = (int)$_POST['application_id'];
        $identityFirst = trim((string)($_POST['first_name'] ?? ''));
        $identityLast = trim((string)($_POST['last_name'] ?? ''));

        // Smart AI final submit: names are required; email/phone may be incomplete and
        // can be completed later from retrieval or the student portal.
        if ($identityAppId > 0 && looks_like_human_name($identityFirst) && looks_like_human_name($identityLast)) {
            $identityOnlySubmitAllowed = true;
        }
    }

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

    /* ===============================
       SMART VALIDATION (SERVER-SIDE)
       - Blocks junk names / invalid phones even if UI is bypassed
    =============================== */
    $fieldErrors = [];

    // Validate email if present (required on final submit unless identity-only smart submit is allowed)
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $emailRequired = ($isFinal === 1 && !$identityOnlySubmitAllowed);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if ($identityOnlySubmitAllowed) {
            // Incomplete AI extraction: drop invalid email so finalize can continue.
            $_POST['email'] = '';
            $email = '';
        } else {
            $fieldErrors['email'] = 'Please enter a valid email address.';
        }
    } elseif ($emailRequired && $email === '') {
        $fieldErrors['email'] = 'Please enter a valid email address.';
    }

    // Validate names if present (required on final submit)
    foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $key => $label) {
        $val = isset($_POST[$key]) ? (string)$_POST[$key] : '';
        if ($isFinal === 1 || trim($val) !== '') {
            if (!looks_like_human_name($val)) {
                $fieldErrors[$key] = $label . ' looks invalid. Use real letters only (no random characters).';
            } else {
                $_POST[$key] = norm_ws($val);
            }
        }
    }

    // Validate phone pair if present (required on final submit unless identity-only smart submit is allowed)
    $areaCode = normalize_area_code($_POST['area_code'] ?? '');
    $phoneDig = normalize_phone_digits($_POST['phone_number'] ?? '');
    $phoneTouched = (trim((string)($_POST['area_code'] ?? '')) !== '' || trim((string)($_POST['phone_number'] ?? '')) !== '');

    $phoneRequired = ($isFinal === 1 && !$identityOnlySubmitAllowed);
    if ($phoneRequired || $phoneTouched) {
        if (!is_valid_phone_pair($areaCode, $phoneDig)) {
            if ($identityOnlySubmitAllowed && !$phoneRequired) {
                // Dial-code-only / partial AI phone should not block Smart AI final submit.
                $_POST['area_code'] = '';
                $_POST['phone_number'] = '';
                $areaCode = '';
                $phoneDig = '';
            } else {
                $fieldErrors['phone_number'] = 'Please enter a valid phone number.';
            }
        } else {
            $_POST['area_code'] = $areaCode;
            $_POST['phone_number'] = $phoneDig;
        }
    }

    // Emergency phone (optional, but if provided must be valid)
    $eArea = normalize_area_code($_POST['emergency_area_code'] ?? '');
    $eDig  = normalize_phone_digits($_POST['emergency_phone_number'] ?? '');
    $eTouched = (trim((string)($_POST['emergency_area_code'] ?? '')) !== '' || trim((string)($_POST['emergency_phone_number'] ?? '')) !== '');
    if ($isFinal === 1 || $eTouched) {
        if ($eTouched && !is_valid_phone_pair($eArea, $eDig)) {
            if ($identityOnlySubmitAllowed) {
                $_POST['emergency_area_code'] = '';
                $_POST['emergency_phone_number'] = '';
            } else {
                $fieldErrors['emergency_phone_number'] = 'Please enter a valid emergency phone number.';
            }
        } elseif ($eTouched) {
            $_POST['emergency_area_code'] = $eArea;
            $_POST['emergency_phone_number'] = $eDig;
        }
    }

    if (!empty($fieldErrors)) {
        json_error('Please correct the highlighted fields and try again.', $fieldErrors, 400);
    }

    if ($isFinal === 1 && $assignedToAdminId !== null) {
        $stStaff = $conn->prepare(
            'SELECT id FROM admins WHERE id = ? AND ' . pcvc_sql_assignable_application_owner_condition() . ' LIMIT 1'
        );
        if (!$stStaff) {
            json_error('Database error while validating staff assignment.', [], 500);
        }
        $stStaff->bind_param('i', $assignedToAdminId);
        $stStaff->execute();
        $stStaff->bind_result($staffRowId);
        $okStaff = $stStaff->fetch();
        $stStaff->close();
        if (!$okStaff || (int)$staffRowId !== $assignedToAdminId) {
            json_error(
                'Please choose a valid staff member in Assign to, or clear the field.',
                ['assigned_to_admin_id' => 'Selected user is not an active staff or superadmin account.'],
                400
            );
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
$appId     = null;

/* ===============================
   1️⃣ PRIORITY: USE application_id
   (Autosave / resume editing)
=============================== */
if (!empty($_POST['application_id'])) {

    $appId = (int)$_POST['application_id'];

    $stmt = $conn->prepare("
        SELECT id
        FROM student_applications
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("i", $appId);
    $stmt->execute();
    $stmt->bind_result($validId);
    $stmt->fetch();
    $stmt->close();

    if (!$validId) {
        throw new Exception('Invalid application reference');
    }

    debug_log('APPLICATION RESUME VIA POST ID', [
        'application_id' => $appId
    ]);
}


/* ===============================
   2️⃣ SECOND: CHECK SESSION OR EMAIL
   (Resume incomplete application safely)
=============================== */
if (!$appId) {

    // Normalize email (important for matching)
    $email = '';
    if (!empty($_POST['email'])) {
        $email = strtolower(trim($_POST['email']));
    }

    $lookupUserId = trim((string)($userId ?? ''));
    $stmt = $conn->prepare("
        SELECT id
        FROM student_applications
        WHERE
            (
                session_id = ?
                OR (email = ? AND email <> '')
                OR (user_id = ? AND user_id <> '')
            )
        AND submitted = 0
        AND deny = 0
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("sss", $sessionId, $email, $lookupUserId);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    if ($row) {

        $appId = (int)$row['id'];

        debug_log('APPLICATION RESUME VIA SESSION/EMAIL', [
            'application_id' => $appId,
            'session_id'     => $sessionId,
            'email'          => $email
        ]);
    }
}

/* ===============================
   3️⃣ CREATE NEW APPLICATION
   (First visit / new person)
=============================== */
if (!$appId) {

    $stmt = $conn->prepare("
        INSERT INTO student_applications
        (session_id, user_id, app_start, created_at)
        VALUES (?, ?, 1, NOW())
    ");

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

    debug_log('NEW APPLICATION CREATED', [
        'application_id' => $appId
    ]);
}

/* ===============================
   ✅ $appId IS NOW FINAL & SAFE
=============================== */

/* ===============================
   UNIQUE APPLICANT EMAIL (blocks duplicate profiles)
   Run once $appId is known; rollback before json_error.
   Any save from step >= 1 (after study choices) or final submit must reject email owned by another row.
=============================== */
$applicantEmailNorm = isset($_POST['email']) ? strtolower(trim((string)$_POST['email'])) : '';
$postStep = isset($_POST['step']) ? (int)$_POST['step'] : -1;
$enforceDuplicateEmailCheck = ($postStep >= 1 || $isFinal === 1);
if (
    $enforceDuplicateEmailCheck
    && $applicantEmailNorm !== ''
    && filter_var($applicantEmailNorm, FILTER_VALIDATE_EMAIL)
    && pcvc_applicant_email_taken($conn, $applicantEmailNorm, $appId)
) {
    $conn->rollback();
    json_error(
        'This email is already used for another application. Please use a different email or contact us if you need to continue an existing application.',
        ['email' => 'This email is already registered with an application.'],
        409
    );
}


/* ===============================
   CREATE USER ID AFTER STEP 1
=============================== */
if (
    isset($_POST['create_user']) &&
    $_POST['create_user'] === '1' &&
    empty($_SESSION['user_id'])
) {

    $userId = 'guest_' . bin2hex(random_bytes(8));
    $_SESSION['user_id'] = $userId;

    $stmt = $conn->prepare("
        UPDATE student_applications
        SET user_id = ?
        WHERE id = ? AND user_id IS NULL
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("si", $userId, $appId);
    $stmt->execute();
    $stmt->close();

    debug_log('USER CREATED & ATTACHED', [
        'user_id'        => $userId,
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
    'destination',
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

        if ($assignedPosted) {
            if ($assignedToAdminId === null) {
                $set[] = 'assigned_to_admin_id=NULL';
            } else {
                $set[] = 'assigned_to_admin_id=' . (int)$assignedToAdminId;
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
            pcvc_ensure_study_choice_schema($conn);

            $choices = json_decode($_POST['study_choices'], true);
            
            if (!is_array($choices)) {
                throw new Exception('study_choices JSON invalid');
            }

            $choices = pcvc_normalize_study_choices($choices);

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
   SEND RESPONSE FIRST (close connection so UI is not blocked)
=============================== */
$successBody = json_encode([
    'status' => 'success',
    'application_id' => $appId
], JSON_UNESCAPED_UNICODE);

require_once __DIR__ . '/helpers/async_http.php';
pcvc_finish_http_response($successBody, 200);

/* ===============================
   ASYNC EMAIL (FINAL ONLY)
=============================== */
debug_log('FINAL SUBMIT CONFIRMED – EMAIL SHOULD SEND', [
    'isFinal' => $isFinal,
    'appId'   => $appId
]);

if ($isFinal === 1) {
    // Student confirmation email (already fire-and-forget HTTP)
    trigger_async_email($appId);

    // Portal credentials — after response is closed (should not block UI)
    try {
        $studentEmail = isset($_POST['email']) ? (string)$_POST['email'] : '';
        $studentName = trim((string)($_POST['first_name'] ?? '') . ' ' . (string)($_POST['last_name'] ?? ''));
        pcvc_send_student_portal_access_email($studentEmail, $studentName, $appId);
        debug_log('PORTAL ACCESS EMAIL SENT', ['email' => $studentEmail, 'appId' => $appId]);
    } catch (Throwable $e) {
        debug_log('PORTAL ACCESS EMAIL FAILED', $e->getMessage());
    }

    require_once __DIR__ . '/helpers/related_program_suggestions.php';

    // Heavy work (assignee, staff notify, AI related proposals) in background worker
    try {
        $fired = pcvc_trigger_related_suggestions_async($appId, true, true);
        debug_log('RELATED SUGGESTIONS ASYNC TRIGGER', [
            'application_id' => $appId,
            'fired' => $fired,
        ]);
        if (!$fired) {
            // Fallback: fast similarity-only (no OpenAI)
            $related = pcvc_process_related_university_suggestions($conn, $appId, true, false);
            debug_log('RELATED PROGRAM SUGGESTIONS FALLBACK', $related);
        }
    } catch (Throwable $e) {
        debug_log('RELATED PROGRAM SUGGESTIONS ERROR', $e->getMessage());
    }
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
/* ======================================================
 * UNIVERSITY → KOREA CHECK (REQUIRED FOR FRONTEND)
 * South Korea country_id = 61
 * ====================================================== */
if ($action === 'university_country' && !empty($_GET['university_id'])) {

    // Clean output buffer (CRITICAL)
    if (ob_get_length()) {
        ob_clean();
    }

    $universityId = (int)$_GET['university_id'];

    $stmt = $conn->prepare("
        SELECT country_id
        FROM universities
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        echo json_encode([
            'country_id' => null,
            'is_korea'   => false
        ]);
        exit;
    }

    $stmt->bind_param("i", $universityId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $countryId = isset($row['country_id']) ? (int)$row['country_id'] : null;

    echo json_encode([
        'country_id' => $countryId,
        'is_korea'   => ($countryId === 61)
    ]);

    exit;
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
