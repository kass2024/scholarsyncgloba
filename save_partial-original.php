<?php
/**
 * save_partial.php — rebuilt version
 * Preserves all original logic + adds duplicate email check (same email + same university)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/db.php';
/* ---------- Safe HTML helper ---------- */
function h($v): string {
    if ($v === null) return '';
    if (is_array($v)) $v = implode(', ', array_map(fn($x) => is_scalar($x) ? (string)$x : '', $v));
    if (is_bool($v))  $v = $v ? '1' : '0';
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------- Response helpers ---------- */
function wants_json(): bool {
    if (!empty($_GET['ajax']) && $_GET['ajax'] === '1') return true;
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
    return false;
}

function send_json($payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function send_html_success($title, string $body): void {
    header('Content-Type: text/html; charset=utf-8'); ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($title) ?></title>
        <style>
            :root { color-scheme: light dark; }
            body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Arial; margin:0; padding:2rem; display:grid; place-items:center; background:#f7f7f7; }
            .card { max-width:720px; width:100%; background:#fff; border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,.08); padding:2rem 2.25rem; }
            h1 { margin:0 0 .5rem; font-size:1.5rem; }
            p { margin:.5rem 0; line-height:1.5; }
            .meta { margin-top:1rem; font-size:.9375rem; color:#555; }
            .actions { margin-top:1.25rem; display:flex; gap:.75rem; flex-wrap:wrap; }
            .btn { appearance:none; border:0; border-radius:10px; padding:.7rem 1rem; text-decoration:none; display:inline-block; }
            .btn-primary { background:#0a7cff; color:#fff; }
        </style>
    </head>
    <body>
    <div class="card">
        <h1>✅ Application saved successfully</h1>
        <?= $body ?>
        <div class="actions">
            <a class="btn btn-primary" href="https://scholarsyncglobal.ca/">OK</a>
        </div>
    </div>
    </body>
    </html>
<?php exit; }

/* ---------- Input extraction ---------- */
$userId       = $_SESSION['user_id'] ?? null;
$stepRaw      = $_POST['step'] ?? null;
$universityId = $_POST['university_id'] ?? ($_SESSION['university_id'] ?? null);

$cleanStep = null;
if (is_numeric($stepRaw)) $cleanStep = 'step' . $stepRaw;
elseif (is_string($stepRaw) && $stepRaw !== '') $cleanStep = strtolower(trim($stepRaw));

/* Detect single-page UOBS */
$isSingleUOBS = !$cleanStep && (
    isset($_POST['applicant_signature']) ||
    isset($_POST['first_name']) ||
    isset($_POST['passport_expiry']) ||
    (isset($_POST['submitted']) && $_POST['submitted'] == '1')
);

if (!$userId) send_json(['status' => 'error', 'message' => 'Missing user ID.'], 400);
if (!$cleanStep && !$isSingleUOBS) send_json(['status' => 'error', 'message' => 'Missing or invalid step.'], 400);

/* ---------- University setup ---------- */
if ($isSingleUOBS) {
    $universityId = 48;
    $_POST['university_id'] = 48;
    $_SESSION['university_id'] = 48;
}

$universityName = 'Unknown University';
if ($universityId) {
    if ($uStmt = $conn->prepare("SELECT name FROM universities WHERE id = ?")) {
        $uStmt->bind_param("i", $universityId);
        $uStmt->execute();
        $uStmt->bind_result($universityName);
        $uStmt->fetch();
        $uStmt->close();
    }
}

$formMappings = [
    1=>'form-catholic.php',30=>'form-polimi.php',32=>'form-Saint-Louis.php',40=>'form-Paris.php',
    34=>'form-Murray.php',35=>'form-kent.php',45=>'form-West-Florida.php',46=>'form-porto.php',
    19=>'form-University-Europe.php',47=>'form-florida.php',48=>'form-UZBEKISTAN.php',50=>'form-Worcester.php',
    51=>'form-rpi.php',53=>'form-UBI.php',55=>'form-Pepperdine.php',57=>'form-budapest.php',59=>'form-monroe.php'
];
$form_url = $formMappings[$universityId] ?? 'form-usa.php';

/* ---------- Helpers ---------- */
function generate_application_id(): string {
    return '/' . str_pad((string)random_int(1000000000, 1999999999), 10, '0', STR_PAD_LEFT);
}
function move_uploads(array $file, string $destDir, bool $multiple = false): array {
    $saved = [];
    if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
    if ($multiple && isset($file['name'])) {
        $count = is_array($file['name']) ? count($file['name']) : 0;
        for ($i=0; $i<$count; $i++) {
            if (($file['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'][$i])) {
                $ext=pathinfo((string)$file['name'][$i],PATHINFO_EXTENSION);
                $name=uniqid('f_',true).($ext?'.'.$ext:'');
                $path=rtrim($destDir,'/\\').DIRECTORY_SEPARATOR.$name;
                if (move_uploaded_file($file['tmp_name'][$i],$path)) $saved[]=$path;
            }
        }
    } elseif (isset($file['name']) && ($file['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
        $ext=pathinfo((string)$file['name'],PATHINFO_EXTENSION);
        $name=uniqid('f_',true).($ext?'.'.$ext:'');
        $path=rtrim($destDir,'/\\').DIRECTORY_SEPARATOR.$name;
        if (move_uploaded_file($file['tmp_name'],$path)) $saved[]=$path;
    }
    return $saved;
}

/* ---------- Locate or prepare target app ---------- */
$incomingAppId='';
if (isset($_POST['application_id']) && is_string($_POST['application_id'])) $incomingAppId=trim($_POST['application_id']);
elseif (isset($_SESSION['application_id']) && is_string($_SESSION['application_id'])) $incomingAppId=trim($_SESSION['application_id']);

$forceNew = isset($_POST['new_application']) && (string)$_POST['new_application']==='1';
$targetRowId=null; $targetAppId=null;

if ($incomingAppId!=='') {
    if ($find=$conn->prepare("SELECT id, application_id FROM student_applications WHERE user_id=? AND application_id=? LIMIT 1")) {
        $find->bind_param("ss",$userId,$incomingAppId);
        $find->execute();
        $find->bind_result($targetRowId,$targetAppId);
        $found=$find->fetch();
        $find->close();
        if (!$found){$targetRowId=null;$targetAppId=$incomingAppId;}
    }
}

/* Wizard may reuse draft */
if ($targetRowId===null && !$forceNew && !$isSingleUOBS) {
    if ($findLatest=$conn->prepare("SELECT id,application_id FROM student_applications WHERE user_id=? AND submitted=0 ORDER BY updated_at DESC LIMIT 1")) {
        $findLatest->bind_param("s",$userId);
        $findLatest->execute();
        $findLatest->bind_result($targetRowId,$targetAppId);
        $findLatest->fetch();
        $findLatest->close();
    }
}

/* ---------- Build field list ---------- */
$fields=[]; $params=[]; $types="";
$_POST['form_url']=$form_url;

/* ---------- Single-page (UOBS) ---------- */
if ($isSingleUOBS) {
    /* Duplicate check for single-page too */
    if (!empty($_POST['email'])) {
        $emailCheck = trim($_POST['email']);
        $duplicateStmt = $conn->prepare("SELECT COUNT(*) FROM student_applications WHERE email=? AND university_id=48");
        $duplicateStmt->bind_param("s", $emailCheck);
        $duplicateStmt->execute();
        $duplicateStmt->bind_result($duplicateCount);
        $duplicateStmt->fetch();
        $duplicateStmt->close();
        if ($duplicateCount > 0) {
            send_json(['status'=>'error','message'=>'This email has already been used to apply to this university.'],400);
        }
    }

    if (!$targetRowId) {
        $targetAppId=$targetAppId?:generate_application_id();
        $_POST['application_id']=$targetAppId;
        $_SESSION['application_id']=$targetAppId;
    } else {
        $_SESSION['application_id']=$targetAppId;
    }

    $fields=[
        'bachelor_program','masters_program','phd_program','intended_study_level',
        'last_name','first_name','middle_name','gender','dob',
        'city_of_birth','country_of_birth','nationality',
        'passport','passport_expiry',
        'destination','address_line1','area_code','phone_number','email',
        'previous_institution_name','previous_institution_street',
        'previous_study_start','previous_study_graduation','high_school_degree',
        'post_secondary','previous_institution_city',
        'college_attended_since','college_attended_till','degree_transcripts','college_received_certificate',
        'application_date','applicant_signature','valid_passport',
        'form_url','university_id'
    ];

    $_POST['university_id']=$universityId;
    if (empty($_POST['application_date'])) $_POST['application_date']=date('Y-m-d');

    $intended=trim((string)($_POST['intended_study_level']??''));
    foreach(['bachelor_program','masters_program','phd_program'] as $deg){
        $checked=isset($_POST[$deg]) && (string)$_POST[$deg]==='1';
        $_POST[$deg]=$checked?$intended:'';
    }

    $fields[]='app_start'; $fields[]='incomplete_app'; $fields[]='submitted';
    $_POST['app_start']=1;
    $_POST['submitted']=isset($_POST['submitted'])&&$_POST['submitted']=='1'?1:0;
    $_POST['incomplete_app']=$_POST['submitted']?0:1;

    $destDir=__DIR__.'/uploads/applications/'.$userId.'/'.($_SESSION['application_id']??$targetAppId??'new');

    if (!empty($_POST['degree_transcripts']) && empty($_POST['college_received_certificate'])) {
        $_POST['college_received_certificate']=$_POST['degree_transcripts'];
    }

    if (!empty($_FILES['passport_scan']) && is_array($_FILES['passport_scan'])) {
        $saved=move_uploads($_FILES['passport_scan'],$destDir,false);
        if (!empty($saved)){
            $rel=str_replace(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, __DIR__ . DIRECTORY_SEPARATOR),'',$saved[0]);
            $_POST['valid_passport']=$rel;
        }
    }
    if (!empty($_FILES['degree_transcripts']) && is_array($_FILES['degree_transcripts'])) {
        $saved=move_uploads($_FILES['degree_transcripts'],$destDir,true);
        if (!empty($saved)){
            $relList=array_map(fn($p)=>str_replace(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, __DIR__ . DIRECTORY_SEPARATOR),'',$p),$saved);
            $_POST['degree_transcripts']=json_encode($relList,JSON_UNESCAPED_SLASHES);
        }
    }

/* ---------- Wizard mode ---------- */
} else {
    switch($cleanStep){
        case 'step1':
            $fields=[
                'first_name','last_name','email','area_code','phone_number','gender',
                'country_of_birth','nationality','second_nationality','city_of_birth','dob',
                'address_line1','address_line2','city','state_province','postal_code',
                'application_date','form_url','university_id','region_id'
            ];
            $_POST['form_url']=$form_url;
            $_SESSION['university_id']=$universityId;
            $_SESSION['region_id']=$_POST['region_id']??null;

            /* Duplicate email check (like save_canada.php) */
            if (!empty($_POST['email']) && !empty($universityId)) {
                $emailCheck=trim($_POST['email']);
                $duplicateStmt=$conn->prepare("SELECT COUNT(*) FROM student_applications WHERE email=? AND university_id=?");
                $duplicateStmt->bind_param("si",$emailCheck,$universityId);
                $duplicateStmt->execute();
                $duplicateStmt->bind_result($duplicateCount);
                $duplicateStmt->fetch();
                $duplicateStmt->close();
                if ($duplicateCount>0){
                    send_json(['status'=>'error','message'=>'This email has already been used to apply to this university.'],400);
                }
            }

            $fields[]='app_start'; $fields[]='incomplete_app';
            $_POST['app_start']=1; $_POST['incomplete_app']=1;

            if (!$targetRowId){
                $fields[]='application_id';
                $targetAppId=$targetAppId?:generate_application_id();
                $_POST['application_id']=$targetAppId;
                $_SESSION['application_id']=$targetAppId;
            } else {
                $_SESSION['application_id']=$targetAppId;
            }
            break;

        case 'step2':
            $fields=['bachelor_program','masters_program','phd_program','destination','other_destination','advanced_diploma_program','college_diploma_program','college_certificate_program','graduate_certificate_program'];
            if(isset($_POST['destination'])) $_POST['destination']=is_array($_POST['destination'])?implode(', ',$_POST['destination']):$_POST['destination'];
            $intended=trim((string)($_POST['intended_study_level']??''));
            foreach(['bachelor_program','masters_program','phd_program'] as $deg){$checked=isset($_POST[$deg])&&(string)$_POST[$deg]==='1';$_POST[$deg]=$checked?$intended:'';}
            break;

        case 'step3':
            $fields=['destination_loan','other_destination_loan','paying_tuition_fees','paying_cost_living','paying_travel_expenses','criminal_history','disability','emergency_first_name','emergency_last_name','emergency_email'];
            if(isset($_POST['destination_loan'])) $_POST['destination_loan']=is_array($_POST['destination_loan'])?implode(', ',$_POST['destination_loan']):$_POST['destination_loan'];
            break;

        case 'step4':
            $fields=['emergency_area_code','emergency_phone_number','emergency_relationship','emergency_same_address','intended_study_level','previous_institution_name','previous_institution_street','previous_institution_city','previous_institution_province','previous_institution_country','previous_institution_post_code','language_of_instruction'];
            if(isset($_POST['intended_study_level'])) $_POST['intended_study_level']=is_array($_POST['intended_study_level'])?implode(', ',$_POST['intended_study_level']):$_POST['intended_study_level'];
            break;

        case 'step5':
            $fields=['previous_study_start','previous_study_graduation','additional_secondary_school','study_gap','post_secondary','passport','visa_rejection','degree_transcripts','high_school_degree','valid_passport'];
            break;

        case 'step6':
            $fields=['recommendation_letters','personal_statement','cv_resume','english_certificate','birth_certificate','agent_first_name','agent_last_name','agent_email','payment_proof','comments'];
            $fields[]='submitted'; $fields[]='incomplete_app'; $fields[]='application_date';
            $_POST['submitted']=1; $_POST['incomplete_app']=0;
            if(empty($_POST['application_date'])) $_POST['application_date']=date('Y-m-d');
            break;

        default: send_json(['status'=>'error','message'=>'Invalid step.'],400);
    }
}

/* ---------- Common flags ---------- */
$fields[]='is_read'; $_POST['is_read']=0;

/* Force region_id=3 for UOBS */
if((string)$universityId==='48'){
    if(!in_array('region_id',$fields,true)) $fields[]='region_id';
    $_POST['region_id']=3;
}

/* ---------- Bind ---------- */
foreach($fields as $field){$params[]=$_POST[$field]??null;$types.="s";}

/* ---------- Insert or update ---------- */
$stmt=null;$action=null;
if($targetRowId){
    $setClause=implode(" = ?, ",$fields)." = ?";
    $sql="UPDATE student_applications SET $setClause WHERE id=? AND user_id=?";
    $stmt=$conn->prepare($sql);
    if(!$stmt){send_json(['status'=>'error','message'=>$conn->error],500);}
    $params[]=(string)$targetRowId; $params[]=(string)$userId; $types.="ss";
    $stmt->bind_param($types,...$params);
    $action='updated';
}else{
    if(!in_array('application_id',$fields,true)){
        $fields[]='application_id';
        $params[]=$targetAppId?:generate_application_id();
        $types.="s";
        $_SESSION['application_id']=end($params);
    }
    $placeholders=rtrim(str_repeat("?,",count($fields)+1),",");
    $sql="INSERT INTO student_applications (user_id,".implode(",",$fields).") VALUES ($placeholders)";
    $stmt=$conn->prepare($sql);
    if(!$stmt){send_json(['status'=>'error','message'=>$conn->error],500);}
    $params=array_merge([(string)$userId],$params);
    $stmt->bind_param("s".$types,...$params);
    $action='inserted';
}

/* ---------- Execute & notify ---------- */
if($stmt->execute()){
    if(!empty($_POST['application_id'])) $_SESSION['application_id']=$_POST['application_id'];
    elseif(!empty($targetAppId)) $_SESSION['application_id']=$targetAppId;
    $finalAppId=$_SESSION['application_id']??($targetAppId??null);

    $shouldEmail=($cleanStep==='step6')||($isSingleUOBS&&(int)($_POST['submitted']??0)===1);
    if($shouldEmail){
        $url=(($_SERVER['HTTP_HOST']??'')==='localhost'||($_SERVER['HTTP_HOST']??'')==='127.0.0.1')
            ?"http://localhost/scholarsyncglobal/send_application_email.php?user_id=$userId"
            :"https://scholarsyncglobal.ca/send_application_email.php?user_id=$userId";
        $ch=curl_init($url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_TIMEOUT,1);
        @curl_exec($ch);@curl_close($ch);
    }

    if($isSingleUOBS && !wants_json()){
        $body="<p>Thank you, <strong>".h($_POST['first_name']??'Applicant')."</strong>.</p>";
        $body.="<p>Your application <code>".h((string)$finalAppId)."</code> has been <strong>saved</strong>"
             .($shouldEmail?" and a confirmation email has been triggered.":".")."</p>";
        $body.="<div class='meta'>University: <strong>".h($universityName)."</strong><br>Form: <strong>".h($form_url)."</strong></div>";
        send_html_success('Application Saved',$body);
    }

    send_json(['status'=>'success','user_id'=>$userId,'application_id'=>$finalAppId,'action'=>$action,'mode'=>$isSingleUOBS?'single':'wizard','step'=>$cleanStep]);
}else{
    $err=$stmt->error?:$conn->error;
    if($isSingleUOBS && !wants_json()){
        header('Content-Type: text/html; charset=utf-8');
        echo "<p>❌ Could not save the application. Error: <code>".h($err)."</code></p>"; exit;
    }
    send_json(['status'=>'error','message'=>$err],500);
}
$stmt->close(); $conn->close();
?>
