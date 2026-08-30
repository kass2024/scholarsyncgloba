<?php
/**
 * FINAL POLISHED VERSION — Step 6 validation (Canada version)
 * ---------------------------------------------------------------
 * ✅ Steps 1–5 buffer into session
 * ✅ Step 6 optional file logic added
 * ✅ No blocking when optional files are missing
 * ✅ Full database insert preserved
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/db.php';
/* ---------- Helpers ---------- */
function send_json($arr, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
}
function log_debug($label, $data = null) {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir . '/application_debug.log';
    $msg = "[" . date('Y-m-d H:i:s') . "] $label";
    if ($data !== null) $msg .= " => " . (is_scalar($data) ? $data : print_r($data, true));
    $msg .= "\n";
    @file_put_contents($file, $msg, FILE_APPEND);
}
function generate_application_id(): string {
    return '/' . random_int(1000000000, 1999999999);
}

/* ---------- Inputs ---------- */
$userId       = $_SESSION['user_id'] ?? null;
$stepRaw      = $_POST['step'] ?? null;
$saveAs       = $_POST['save_as'] ?? null;
$universityId = $_POST['university_id'] ?? ($_SESSION['university_id'] ?? null);
$regionId     = $_POST['region_id'] ?? ($_SESSION['region_id'] ?? null);

if (!$userId)  send_json(['status'=>'error','message'=>'Missing user ID'],400);
if (!$stepRaw) send_json(['status'=>'error','message'=>'Missing step'],400);

$cleanStep = 'step' . intval($stepRaw);
log_debug('STEP RECEIVED', ['step'=>$cleanStep, 'save_as'=>$saveAs]);

/* ---------- University Forms ---------- */
$formMap = [
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
    79 =>'form-Niagara-College.php', 
    80 =>'form-University of Saskatchewan (USASK).php',
    81 =>'form-st thomas.php',
];
$form_url = $formMap[$universityId] ?? 'form-usa.php';

/* ---------- Session Init ---------- */
if (!isset($_SESSION['application_data'])) $_SESSION['application_data'] = [];

/* ---------- Buffer Steps 1–5 ---------- */
if ($cleanStep !== 'step6') {
    $storeStep = $saveAs ? 'step' . intval($saveAs) : $cleanStep;
    $_SESSION['application_data'][$storeStep] = $_POST;
    log_debug("BUFFERED $storeStep", $_POST);
    send_json(['status'=>'success','message'=>"Step {$storeStep} saved successfully"]);
}

/* ---------- For Step 6 ---------- */
$isFinalSubmit = ($saveAs === 'final' || (isset($_POST['submitted']) && $_POST['submitted'] == 1));

if (!$isFinalSubmit) {
    // Just saving Step 6 draft or preview → no validation
    $_SESSION['application_data']['step6'] = $_POST;
    log_debug("BUFFERED STEP6 (no validation)", $_POST);
    send_json(['status'=>'success','message'=>"Step 6 data saved (not final)"]);
}

/* ---------- Ensure earlier steps exist ---------- */
if (!isset($_SESSION['application_data']['step5'])) {
    log_debug('BLOCKED EARLY SUBMIT', $_POST);
    send_json(['status'=>'error','message'=>'Please complete all steps before submitting.']);
}

/* ---------- Merge all step data ---------- */
$merged = [];
foreach ($_SESSION['application_data'] as $stepData) {
    $merged = array_merge($merged, $stepData);
}
$merged = array_merge($merged, $_POST);
unset($_SESSION['application_data']);
$_POST = $merged;
log_debug('MERGED FINAL DATA', $_POST);

/* ---------- Normalize ---------- */
foreach (['destination','destination_loan','intended_study_level'] as $f) {
    if (isset($_POST[$f]) && is_array($_POST[$f])) {
        $_POST[$f] = implode(', ', array_filter($_POST[$f]));
    }
}
foreach (['phone_number_display','agent_select'] as $f) unset($_POST[$f]);

/* ---------- Add IDs ---------- */
$appId = generate_application_id();
$_POST = array_merge($_POST, [
    'application_id'   => $appId,
    'submitted'        => 1,
    'incomplete_app'   => 0,
    'application_date' => date('Y-m-d'),
    'form_url'         => $form_url,
    'user_id'          => $userId,
    'university_id'    => $universityId,
    'region_id'        => $regionId
]);
$_SESSION['application_id'] = $appId;

/* ---------- Required Docs Check (ONLY Final Step 6 Submit) ---------- */
/**
 * ✅ Required docs (must exist for all applicants)
 *    - valid_passport
 *    - cv_resume
 *
 * ⚙️ Optional docs (no blocking if missing)
 *    - degree_transcripts (becomes optional when Bachelor is selected)
 *    - high_school_degree
 *    - recommendation_letters
 *    - personal_statement
 *    - english_certificate
 *    - birth_certificate
 *    - payment_proof
 */

// Default required & optional docs
$requiredDocs = ['degree_transcripts', 'valid_passport', 'cv_resume'];
$optionalDocs = [
    'high_school_degree',
    'recommendation_letters',
    'personal_statement',
    'english_certificate',
    'birth_certificate',
    'payment_proof'
];

// 🧠 If "Bachelor" appears in intended_study_level, make degree_transcripts optional
$studyLevel = strtolower($_POST['intended_study_level'] ?? '');
if (strpos($studyLevel, 'bachelor') !== false) {
    $requiredDocs = array_diff($requiredDocs, ['degree_transcripts']);
    $optionalDocs[] = 'degree_transcripts';
    log_debug('Bachelor detected — degree_transcripts marked optional');
}

// Check for missing required docs
$missing = [];
foreach ($requiredDocs as $field) {
    if (empty($_POST[$field])) {
        $missing[] = $field;
    }
}

// Log results for debugging
log_debug('STEP6 DOC CHECK', [
    'missing_required' => $missing,
    'optional_missing' => array_filter($optionalDocs, fn($f) => empty($_POST[$f]))
]);

// Stop submission if required docs are missing
if (!empty($missing)) {
    send_json([
        'status' => 'missing_docs',
        'message' => 'Please upload all required Step 6 documents before submitting.',
        'missing_fields' => $missing,
        'redirect_step' => 'step6'
    ], 400);
}



$missing = [];
foreach ($requiredDocs as $field) {
    if (empty($_POST[$field])) {
        $missing[] = $field;
    }
}

// Log optional + missing required for transparency
log_debug('STEP6 DOC CHECK', [
    'missing_required' => $missing,
    'optional_missing' => array_filter($optionalDocs, fn($f) => empty($_POST[$f]))
]);

if (!empty($missing)) {
    send_json([
        'status' => 'missing_docs',
        'message' => 'Please upload all required Step 6 documents before submitting.',
        'missing_fields' => $missing,
        'redirect_step' => 'step6'
    ], 400);
}

/* ---------- Duplicate Email Check ---------- */
if (!empty($_POST['email']) && !empty($universityId)) {
    $email = trim($_POST['email']);
    $chk = $conn->prepare("SELECT COUNT(*) FROM student_applications WHERE email=? AND university_id=?");
    $chk->bind_param("si", $email, $universityId);
    $chk->execute();
    $chk->bind_result($cnt);
    $chk->fetch();
    $chk->close();
    if ($cnt > 0) {
        log_debug('DUPLICATE EMAIL BLOCK',$email);
        send_json(['status'=>'error','message'=>'This email has already been used for this university.'],400);
    }
}

/* ---------- Prepare Insert ---------- */
$allowedCols = [
 'user_id','first_name','middle_name','last_name','email','area_code','phone_number','gender',
 'country_of_birth','nationality','second_nationality','passport','passport_number','passport_expiry',
 'city_of_birth','dob','address_line1','address_line2','city','state_province','postal_code',
 'application_date','applicant_signature','bachelor_program','masters_program','phd_program',
 'destination','other_destination','destination_loan','other_destination_loan','paying_tuition_fees',
 'paying_cost_living','paying_travel_expenses','criminal_history','disability','emergency_first_name',
 'emergency_last_name','emergency_email','emergency_area_code','emergency_phone_number','emergency_relationship',
 'emergency_same_address','intended_study_level','previous_institution_name','previous_institution_street',
 'previous_institution_city','previous_institution_province','previous_institution_country',
 'previous_institution_post_code','language_of_instruction','previous_study_start','previous_study_graduation',
 'additional_secondary_school','study_gap','post_secondary','visa_rejection','degree_transcripts','high_school_degree',
 'college_attended_since','college_attended_till','college_received_certificate','valid_passport',
 'recommendation_letters','personal_statement','cv_resume','english_certificate','birth_certificate',
 'agent_first_name','agent_last_name','agent_email','payment_proof','comments','form_url','university_id',
 'region_id','incomplete_app','submitted','application_id'
];
$data=[];
foreach($allowedCols as $col){
    if(isset($_POST[$col])) $data[$col]=$_POST[$col];
}
if(!$data){
    log_debug('NO VALID DATA',$_POST);
    send_json(['status'=>'error','message'=>'No valid data to insert.'],400);
}

/* ---------- DB Insert ---------- */
$cols=array_keys($data);
$placeholders=rtrim(str_repeat('?,',count($cols)),',');
$types=str_repeat('s',count($cols));
$sql="INSERT INTO student_applications(".implode(',',$cols).") VALUES($placeholders)";
$stmt=$conn->prepare($sql);
if(!$stmt){send_json(['status'=>'error','message'=>'Prepare failed: '.$conn->error],500);}
$stmt->bind_param($types,...array_values($data));
if(!$stmt->execute()){
    log_debug('EXEC FAILED',$stmt->error);
    send_json(['status'=>'error','message'=>'Database error: '.$stmt->error],500);
}

/* ---------- Success ---------- */
$insertId=$conn->insert_id;
$finalId=$_SESSION['application_id'];
log_debug('INSERT SUCCESS',['insert_id'=>$insertId,'app_id'=>$finalId]);

// Async email
$url=(($_SERVER['HTTP_HOST']??'')==='localhost')
  ? "http://localhost/scholarsyncglobal/send_application_email.php?user_id=$userId"
  : "https://scholarsyncglobal.ca/send_application_email.php?user_id=$userId";
$ch=curl_init($url);
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>1]);
@curl_exec($ch);@curl_close($ch);

send_json(['status'=>'success','application_id'=>$finalId,'insert_id'=>$insertId]);
?>
