<?php
/**
 * save-form-upafa.php — REBUILT & HARDENED for cPanel
 * - Reliable file saving to uploads/upafa/YYYY/MM
 * - Inserts every file into upafa_registration_files
 * - Sends email with all attachments (incl. pictures); PDF added first if generated
 * - Verbose logging for each upload decision (why kept/skipped)
 * - ENUM-safe file_type mapping (passport_photo, id_document, birth_certificate, degree_transcript, other_attachment)
 */

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/db.php';

// ----------------- Logging (cPanel safe) -----------------
// ----------------- Logging (HARD cPanel SAFE) -----------------
$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/upafa.log';

if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
@chmod($logDir, 0777);

function logi(string $m): void {
    $file = __DIR__ . '/logs/upafa.log';
    @file_put_contents(
        $file,
        '[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

// FIRST GUARANTEED LOG
logi('SCRIPT STARTED');
logi('FILES RECEIVED: ' . json_encode(array_keys($_FILES)));

/* ----------------- Upload base paths ----------------- */
define('UPAFA_BASE_FS',  rtrim(str_replace('\\','/', __DIR__), '/') . '/uploads/upafa'); // absolute FS
define('UPAFA_BASE_URL', 'uploads/upafa');                                                // relative for DB/links
@mkdir(UPAFA_BASE_FS, 0775, true);

/* ----------------- Optional libraries ---------------- */
$hasMailer = false;

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $hasMailer = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
}
if (!$hasMailer && is_file(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    $hasMailer = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
}
if (is_file(__DIR__ . '/generate-upafa-pdf.php')) {
    require_once __DIR__ . '/generate-upafa-pdf.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* ----------------- Helpers ----------------- */
function post(string $k, $d = '') { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function yn(?string $v): string { return $v === 'Yes' ? 'Yes' : 'No'; }
function ensure_dir(string $p): void {
    if (!is_dir($p)) {
        @mkdir($p, 0775, true);
        @chmod($p, 0775);
    }
}
function random_name(string $ext = ''): string { return bin2hex(random_bytes(16)) . ($ext ? ('.' . $ext) : ''); }
function get_upload_error_msg(int $code): string {
    return [
        UPLOAD_ERR_OK         => 'OK',
        UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL    => 'Partial upload',
        UPLOAD_ERR_NO_FILE    => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
        UPLOAD_ERR_EXTENSION  => 'Stopped by extension',
    ][$code] ?? ('Error code ' . $code);
}
function finfo_mime_detect(string $tmp, string $name): string {
    if (class_exists('finfo')) {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $m = (string)$fi->file($tmp);
        if ($m) return $m;
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $map = [
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp',
        'pdf'=>'application/pdf','doc'=>'application/msword',
        'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'heic'=>'image/heic','heif'=>'image/heif'
    ];
    return $map[$ext] ?? 'application/octet-stream';
}
function guess_ext_from_mime_or_name(string $mime, string $name): string {
    $byMime = [
        'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/heic'=>'heic','image/heif'=>'heif',
        'application/pdf'=>'pdf','application/msword'=>'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx'
    ][$mime] ?? '';
    if ($byMime) return $byMime;
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return $ext ?: 'bin';
}
function http_error(int $code, string $msg): void {
    http_response_code($code);
    echo "<div style='border:1px solid #f5c2c7;background:#f8d7da;padding:12px;color:#842029'>❌ Error: " . htmlspecialchars($msg, ENT_QUOTES) . "</div>";
    exit;
}
/** Read env/const safely */
function envn(array $names, ?string $default = null): ?string {
    foreach ($names as $n) {
        if (defined($n)) { $v = constant($n); if ($v !== '' && $v !== null) return (string)$v; }
        if (isset($_ENV[$n]) && $_ENV[$n] !== '') return (string)$_ENV[$n];
        $g = getenv($n);
        if ($g !== false && $g !== '') return (string)$g;
    }
    return $default;
}
/** Resolve relative DB path to absolute FS path (under script dir) */
function upafa_abs_path(string $p): string {
    if ($p === '') return '';
    if ($p[0] === '/' && is_file($p)) return $p;
    $try = __DIR__ . '/' . ltrim($p, '/');
    return is_file($try) ? $try : $p;
}
/** Map incoming labels to ENUM values present in DB */
function upafa_map_filetype(mysqli $conn, string $label): string {
    $res = $conn->query("SHOW COLUMNS FROM upafa_registration_files LIKE 'file_type'");
    if ($res) {
        $row = $res->fetch_assoc();
        if ($row && stripos($row['Type'] ?? '', 'enum(') === 0) {
            preg_match_all("/'([^']+)'/", $row['Type'], $m);
            $allowed = $m[1] ?? [];
            if (in_array($label, $allowed, true)) return $label;
            $aliases = ['last_degree','last_degree_file','degree','degree_file','transcript','transcript_file','academic_transcript'];
            if (in_array($label, $aliases, true) && in_array('degree_transcript', $allowed, true)) return 'degree_transcript';
            return in_array('other_attachment', $allowed, true) ? 'other_attachment' : ($allowed[0] ?? $label);
        }
    }
    return $label;
}

/**
 * Save uploads for a given field name (single or multiple) with robust checks.
 * Returns list of saved file meta (including absolute fs_path for emailing).
 */
function save_uploaded(string $field, string $fileType): array {
    if (empty($_FILES[$field])) return [];

    $f = $_FILES[$field];
    $multi = is_array($f['name']);

    $folderFs  = rtrim(UPAFA_BASE_FS,  '/') . '/' . date('Y') . '/' . date('m');
    $folderUrl = rtrim(UPAFA_BASE_URL, '/') . '/' . date('Y') . '/' . date('m');
    ensure_dir($folderFs);

    $allowedMimes = [
        'image/jpeg','image/png','image/webp','image/heic','image/heif',
        'application/pdf','application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    $allowedExts = ['jpg','jpeg','png','webp','heic','heif','pdf','doc','docx'];
    $maxBytes = 25 * 1024 * 1024;

    $out = [];
    $n = $multi ? count($f['name']) : 1;

    for ($i = 0; $i < $n; $i++) {
        $name = (string)($multi ? $f['name'][$i]     : $f['name']);
        $tmp  = (string)($multi ? $f['tmp_name'][$i] : $f['tmp_name']);
        $err  = (int)   ($multi ? $f['error'][$i]    : $f['error']);
        $size = (int)   ($multi ? $f['size'][$i]     : $f['size']);

        if ($name === '' && $err === UPLOAD_ERR_NO_FILE) { logi("[$field] No file provided."); continue; }
        if ($err !== UPLOAD_ERR_OK) { logi("[$field] Skip '{$name}' due to upload error: " . get_upload_error_msg($err)); continue; }
        if (!is_file($tmp)) { logi("[$field] Temp file missing for '{$name}'"); continue; }
        if ($size <= 0) { logi("[$field] Zero size for '{$name}'"); continue; }
        if ($size > $maxBytes) { logi("[$field] Too large ({$size}) '{$name}'"); continue; }

        $mime = finfo_mime_detect($tmp, $name);
        $ext  = guess_ext_from_mime_or_name($mime, $name);

        $okByMime = in_array($mime, $allowedMimes, true);
        $okByExt  = in_array($ext,  $allowedExts, true);
        if (!$okByMime && !$okByExt) {
            logi("[$field] Rejected '{$name}' (mime=$mime, ext=$ext) not allowed");
            continue;
        }

        $stored  = random_name($ext);
        $destFs  = $folderFs  . '/' . $stored;
        $destUrl = $folderUrl . '/' . $stored;

        $moved = @move_uploaded_file($tmp, $destFs);
        if (!$moved) $moved = @rename($tmp, $destFs);
        if (!$moved) { $moved = @copy($tmp, $destFs); @unlink($tmp); }

        if (!$moved || !is_file($destFs)) {
            logi("[$field] move failed for '{$name}' -> $destFs");
            continue;
        }

        @chmod($destFs, 0644);
        logi("[$field] SAVED '{$name}' as '$destFs' ($mime, $size bytes)");

        $out[] = [
            'file_type'     => $fileType,
            'original_name' => $name,
            'stored_name'   => $stored,
            'mime_type'     => $mime,
            'size_bytes'    => $size,
            'storage_path'  => $destUrl,
            'fs_path'       => $destFs,
        ];
    }
    return $out;
}

/** Prepare attachments under a size cap; prefer PDF first */
function prepare_attachments(array $files, ?string $pdfPath, int $capMB, int $registrationId): array {
    $capBytes = $capMB * 1024 * 1024;
    $attachments = [];
    $total = 0;
    $omitted = [];

    if ($pdfPath && is_file($pdfPath)) {
        $sz = filesize($pdfPath) ?: 0;
        if ($sz <= $capBytes) {
            $attachments[] = ['path'=>$pdfPath, 'name'=>"UPAFA_Application_{$registrationId}.pdf"];
            $total += $sz;
        } else {
            $omitted[] = "PDF UPAFA_Application_{$registrationId}.pdf (".number_format($sz)." bytes)";
        }
    }

 foreach ($files as $idx => $f) {

    logi("[ATTACH][$idx] -------- START --------");

    // 1️⃣ Resolve absolute file system path (PRIMARY: fs_path)
    $p = $f['fs_path'] ?? '';

    logi("[ATTACH][$idx] fs_path from array: " . ($p ?: 'EMPTY'));

    // 2️⃣ Fallback: rebuild path ONLY if fs_path missing
    if (!$p && !empty($f['storage_path'])) {
        $rebuilt = rtrim(UPAFA_BASE_FS, '/') . '/'
                 . ltrim(str_replace(UPAFA_BASE_URL, '', (string)$f['storage_path']), '/');

        logi("[ATTACH][$idx] Rebuilt path from storage_path: $rebuilt");

        $p = $rebuilt;
    }

    // 3️⃣ Final validation
    if (!$p) {
        logi("[ATTACH][$idx] ❌ NO PATH AVAILABLE for " . ($f['original_name'] ?? 'UNKNOWN'));
        continue;
    }

    if (!is_file($p)) {
        logi("[ATTACH][$idx] ❌ FILE NOT FOUND: $p");
        continue;
    }

    if (!is_readable($p)) {
        logi("[ATTACH][$idx] ❌ FILE NOT READABLE: $p");
        continue;
    }

    // 4️⃣ Size check
    $sz = filesize($p) ?: 0;
    logi("[ATTACH][$idx] File size: {$sz} bytes");

    if ($sz <= 0) {
        logi("[ATTACH][$idx] ❌ ZERO SIZE FILE: $p");
        continue;
    }

    // 5️⃣ Build attachment name
    $type = strtoupper((string)($f['file_type'] ?? 'FILE'));
    $orig = basename((string)($f['original_name'] ?? basename($p)));
    $name = $type . '_' . $orig;

    logi("[ATTACH][$idx] Attachment name: $name");

    // 6️⃣ Size cap decision
    if (($total + $sz) <= $capBytes) {

        $attachments[] = [
            'path' => $p,
            'name' => $name
        ];

        $total += $sz;

        logi("[ATTACH][$idx] ✅ ATTACHED (total={$total}/{$capBytes})");

    } else {

        $omitted[] = "{$name} (" . number_format($sz) . " bytes)";

        logi("[ATTACH][$idx] ⚠️ OMITTED (size cap exceeded)");
    }

    logi("[ATTACH][$idx] -------- END --------");
}

    $note = $omitted ? "Some attachments were omitted to keep email under ~{$capMB}MB:\n - " . implode("\n - ", $omitted) . "\nAll files are saved on the server." : '';
    return ['attachments'=>$attachments, 'note'=>$note];
}

/** SMTP config */
function build_smtp_config(): array {
    $host  = envn(['SMTP_HOST','MAIL_HOST'], 'scholarsyncglobal.ca');
    $user  = envn(['SMTP_USER','MAIL_USERNAME'], 'infos@scholarsyncglobal.ca');
    $pass  = envn(['SMTP_PASSWORD','MAIL_PASSWORD'], '');
    $port  = (int)(envn(['SMTP_PORT','MAIL_PORT'], '465') ?? 465);
    $enc   = strtolower(envn(['SMTP_SECURE','MAIL_ENCRYPTION'], 'ssl') ?? 'ssl');

    $from     = envn(['SMTP_FROM','MAIL_FROM_ADDRESS'], $user);
    $fromName = envn(['SMTP_FROM_NAME','MAIL_FROM_NAME'], 'U.P.A.F.A. Admissions');

    $norm = static function($v): array {
        if (is_array($v)) return array_values(array_filter(array_map('trim', $v)));
        $parts = preg_split('/[;,]/', (string)$v);
        return array_values(array_filter(array_map('trim', $parts ?: [])));
    };

   $to = $norm(envn(['UPAFA_ADMISSIONS_TO'], 'infos@scholarsyncglobal.ca'));

$cc = ['methode@scholarsyncglobal.ca'];
logi('SMTP CC FIXED TO: ' . implode(',', $cc));

$capMB = (int)(envn(['UPAFA_ATTACH_MAX_MB'], '30') ?? 30);


    return [
        'host'=>$host,'user'=>$user,'pass'=>$pass,'port'=>$port,
        'enc'=>in_array($enc,['ssl','tls','none'],true)?$enc:'ssl',
        'from'=>$from,'from_name'=>$fromName,
        'to'=>$to,'cc'=>$cc,'cap_mb'=>$capMB
    ];
}

/** Send email (with SMTP debug logged to upafa.log) */
function send_mail(array $smtp, $to, string $toName, string $subject, string $html, array $attachments = []): void {
    if (!class_exists(PHPMailer::class)) { logi('Mail disabled: PHPMailer not loaded.'); return; }

    $toList = is_array($to) ? $to : preg_split('/[;,]/', (string)$to);
    $toList = array_values(array_filter(array_map('trim', $toList ?: [])));

    $ccList = is_array($smtp['cc']) ? $smtp['cc'] : preg_split('/[;,]/', (string)($smtp['cc'] ?? ''));
    $ccList = array_values(array_filter(array_map('trim', $ccList ?: [])));

    $m = new PHPMailer(true);
    try {
        $m->isSMTP();
        $m->Host     = $smtp['host'];
        $m->SMTPAuth = true;
        $m->Username = $smtp['user'];
        $m->Password = $smtp['pass'];
        $m->Port     = $smtp['port'];

        if ($smtp['enc'] === 'tls')      $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        elseif ($smtp['enc'] === 'ssl')  $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        else                              $m->SMTPSecure = false;

        $m->SMTPDebug   = SMTP::DEBUG_SERVER;
        $m->Debugoutput = static function($str, $level){ error_log('[SMTP]['.$level.'] '.trim($str)); };

        $m->CharSet = 'UTF-8';
        $m->setFrom($smtp['from'] ?: $smtp['user'], $smtp['from_name'] ?: 'U.P.A.F.A. Admissions');

        foreach ($toList as $addr) { if ($addr) $m->addAddress($addr, $toName); }
        foreach ($ccList as $addr) { if ($addr) $m->addCC($addr); }

        $m->isHTML(true);
        $m->Subject = $subject;
        $m->Body    = $html;
        $m->AltBody = strip_tags(str_replace(['<br>','<br/>','<br />','</p>','</li>'], "\n", $html));

       foreach ($attachments as $i => $a) {

    if (!isset($a['path'])) {
        logi("[ATTACH][$i] Invalid attachment entry");
        continue;
    }

    $apath = (string)$a['path'];
    $name  = $a['name'] ?? basename($apath);

    logi("[ATTACH][$i] Trying: $apath");

    if (!is_file($apath)) {
        logi("[ATTACH][$i] NOT FOUND: $apath");
        continue;
    }

    if (!is_readable($apath)) {
        logi("[ATTACH][$i] NOT READABLE: $apath");
        continue;
    }

    $m->addAttachment($apath, $name);
    logi("[ATTACH][$i] ATTACHED");
}


        $m->Timeout = 200;
        $m->send();
        logi('Mail sent to ['.implode(', ',$toList).'] subject='.$subject);
    } catch (MailerException $e) {
        logi('MAIL ERROR: '.$e->getMessage());
    } catch (\Throwable $e) {
        logi('MAIL-OTHER ERROR: '.$e->getMessage());
    }
}

/* ----------------- Only POST ----------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_error(405, 'Method Not Allowed');
}

try {
    /* ---- Required POST ---- */
    $required = [
        'academic_year','last_name','first_name','nationality','birth_place','birth_date',
        'highest_education','department','school_name_address','year_from','year_to',
        'intended_degree','field_of_study','registration_fees','tuition_fees',
        'telephone','email','commitment_name','done_at','done_date'
    ];
    foreach ($required as $k) if (post($k,'')==='') http_error(422, "Required field missing: $k");
    if (!preg_match('/^\s*\d{4}\s*[\/-]\s*\d{4}\s*$/', post('academic_year'))) {
        http_error(422, 'Academic Year must be like 2024/2025 or 2024-2025');
    }

    /* ---- Required FILES must be present ---- */
    $mustSingles = [
        'passport_photo'   => 'Passport Photo',
        'id_document'      => 'Passport / National ID',
        'birth_certificate'=> 'Birth Certificate',
    ];
    foreach ($mustSingles as $field => $label) {
        if (empty($_FILES[$field]) ||
            (is_array($_FILES[$field]['name']) ? count(array_filter($_FILES[$field]['name'])) === 0 : $_FILES[$field]['name'] === '')
        ) {
            http_error(422, "Missing required file: $label");
        }
    }

    /* ---- Begin Tx ---- */
    $conn->begin_transaction();

    $scholarship = yn(post('scholarship','No'));
    $referred    = yn(post('referred_by_scholarsync','No'));
    $sch_inst = ($scholarship === 'Yes' && post('scholarship_institution')!=='') ? post('scholarship_institution') : null;
    $ref_inst = ($referred   === 'Yes' && post('ref_institution')!=='')          ? post('ref_institution')       : null;

    $year_from = (int)post('year_from');
    $year_to   = (int)post('year_to');
    $reg_fee   = (float)post('registration_fees');
    $tui_fee   = (float)post('tuition_fees');

    // Insert registration (NO credit_transfer_money)
    $sql = "INSERT INTO upafa_registrations (
        academic_year,last_name,first_name,nationality,birth_place,birth_date,
        highest_education,department,school_name_address,year_from,year_to,
        intended_degree,field_of_study,registration_fees,tuition_fees,
        scholarship,scholarship_institution,referred_by_scholarsync,ref_institution,
        telephone,email,commitment_name,done_at,done_date
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);

    // 9s + ii + ss + dd + 9s = 24 params total
    $types = 'sssssssss' . 'ii' . 'ss' . 'dd' . 'sssssssss';

    $academic_year       = post('academic_year');
    $last_name           = post('last_name');
    $first_name          = post('first_name');
    $nationality         = post('nationality');
    $birth_place         = post('birth_place');
    $birth_date          = post('birth_date');
    $highest_education   = post('highest_education');
    $department          = post('department');
    $school_name_address = post('school_name_address');
    $intended_degree     = post('intended_degree');
    $field_of_study      = post('field_of_study');
    $telephone           = post('telephone');
    $email               = post('email');
    $commitment_name     = post('commitment_name');
    $done_at             = post('done_at');
    $done_date           = post('done_date');

    $stmt->bind_param(
        $types,
        $academic_year,$last_name,$first_name,$nationality,$birth_place,$birth_date,
        $highest_education,$department,$school_name_address,
        $year_from,$year_to,$intended_degree,$field_of_study,
        $reg_fee,$tui_fee,
        $scholarship,$sch_inst,$referred,$ref_inst,
        $telephone,$email,$commitment_name,$done_at,$done_date
    );
    $stmt->execute();
    if ($stmt->affected_rows !== 1) throw new RuntimeException('Insert failed (registration).');
    $registrationId = (int)$stmt->insert_id;
    $stmt->close();

    /* ---- Save files ---- */
    $filesAll = [];

    foreach ([['passport_photo','passport_photo'],['id_document','id_document'],['birth_certificate','birth_certificate']] as [$field,$type]) {
        $saved = save_uploaded($field, $type);
        if (!$saved) throw new RuntimeException("Required file '$field' could not be saved (type/size/path). Check logs.");
        $filesAll = array_merge($filesAll, $saved);
    }

    foreach ([['last_degree','last_degree'],['last_degree_file','last_degree'],['degree','last_degree'],['degree_file','last_degree'],
              ['transcript_file','transcript_file'],['transcript','transcript_file'],['academic_transcript','transcript_file']] as [$field,$type]) {
        $filesAll = array_merge($filesAll, save_uploaded($field, $type));
    }

    $filesAll = array_merge($filesAll, save_uploaded('other_attachments','other_attachment'));

    if ($filesAll) {
        $ins = $conn->prepare("INSERT INTO upafa_registration_files
            (registration_id,file_type,original_name,stored_name,mime_type,size_bytes,storage_path)
            VALUES (?,?,?,?,?,?,?)");

        foreach ($filesAll as $f) {
            $regId_i = $registrationId;
            $ftype   = upafa_map_filetype($conn, (string)($f['file_type'] ?? 'other_attachment'));
            $oname   = (string)($f['original_name'] ?? '');
            $sname   = (string)($f['stored_name'] ?? '');
            $mime    = (string)($f['mime_type'] ?? 'application/octet-stream');
            $size_i  = (int)($f['size_bytes'] ?? 0);
            $spath   = (string)($f['storage_path'] ?? '');

            $ins->bind_param('issssis', $regId_i, $ftype, $oname, $sname, $mime, $size_i, $spath);
            $ins->execute();
            if ($ins->affected_rows !== 1) throw new RuntimeException('File insert failed: ' . $oname);
        }
        $ins->close();
    } else {
        throw new RuntimeException('No files were saved. Check allowed types/limits.');
    }

    /* ---- Commit before emailing ---- */
    $conn->commit();

    /* ---- Optional PDF ---- */
    $pdfPath = null;
    if (function_exists('generateUpafaPDF')) {
        try { $pdfPath = generateUpafaPDF($registrationId, $conn); }
        catch (\Throwable $e) { logi('[PDF] ' . $e->getMessage()); }
    }

    /* ---- Reload fresh data for email ---- */
    $r = $conn->prepare('SELECT * FROM upafa_registrations WHERE id=?');
    $r->bind_param('i', $registrationId);
    $r->execute();
    $reg = $r->get_result()->fetch_assoc();
    $r->close();

    $rf = $conn->prepare('SELECT * FROM upafa_registration_files WHERE registration_id=? ORDER BY id ASC');
    $rf->bind_param('i', $registrationId);
    $rf->execute();
    $dbFiles = $rf->get_result()->fetch_all(MYSQLI_ASSOC);
    $rf->close();

    /* ---- Prepare email ---- */
    $smtp = build_smtp_config();
    $att  = prepare_attachments($dbFiles, $pdfPath, (int)$smtp['cap_mb'], (int)$registrationId);


    $filesListHtml = '';
    if ($dbFiles) {
        $filesListHtml .= '<ul>';
        foreach ($dbFiles as $f) {
            $filesListHtml .= '<li><strong>'.htmlspecialchars($f['file_type']).'</strong> — '
                            . htmlspecialchars($f['original_name'])
                            . ' ('.htmlspecialchars((string)$f['mime_type']).', '.number_format((int)$f['size_bytes']).' bytes)</li>';
        }
        $filesListHtml .= '</ul>';
    }

    $adminHtml =
        "<h3>New Registration Received</h3>
         <p><strong>Applicant:</strong> ".htmlspecialchars($reg['first_name'])." ".htmlspecialchars($reg['last_name'])."</p>
         <p><strong>Email:</strong> ".htmlspecialchars($reg['email'])."</p>
         <p><strong>Phone:</strong> ".htmlspecialchars($reg['telephone'])."</p>
         <p><strong>Academic Year:</strong> ".htmlspecialchars($reg['academic_year'])."</p>
         <p><strong>Intended Degree:</strong> ".htmlspecialchars($reg['intended_degree'])." — ".htmlspecialchars($reg['field_of_study'])."</p>
         <p><strong>Fees:</strong> Registration ".htmlspecialchars((string)$reg['registration_fees'])." | Tuition ".htmlspecialchars((string)$reg['tuition_fees'])."</p>
         <p><strong>Scholarship:</strong> ".htmlspecialchars($reg['scholarship']).($reg['scholarship_institution'] ? " (".htmlspecialchars($reg['scholarship_institution']).")" : "")."</p>
         <p><strong>Referred by ScholarSync:</strong> ".htmlspecialchars($reg['referred_by_scholarsync']).($reg['ref_institution'] ? " (".htmlspecialchars($reg['ref_institution']).")" : "")."</p>"
         . ($att['note'] ? "<pre style='background:#f8f9fa;padding:10px;border:1px solid #e5e7eb;border-radius:6px'>".htmlspecialchars($att['note'])."</pre>" : "")
         . "<h4>Files</h4>{$filesListHtml}";

    if ($hasMailer) {
        // Admin email (all possible attachments within cap)
        send_mail(
            $smtp,
            $smtp['to'],
            'Admissions',
            "New U.P.A.F.A. Registration #$registrationId — {$reg['first_name']} {$reg['last_name']}",
            $adminHtml,
            $att['attachments']
        );

        // Applicant confirmation — attach PDF only (if any)
        $smtpNoCc = $smtp; $smtpNoCc['cc'] = [];
        $appAttach = [];
        if ($pdfPath && is_file($pdfPath)) $appAttach[] = ['path'=>$pdfPath, 'name'=>"UPAFA_Application_{$registrationId}.pdf"];

        send_mail(
            $smtpNoCc,
            (string)$reg['email'],
            $reg['first_name'].' '.$reg['last_name'],
            "Your U.P.A.F.A. Registration (ID #$registrationId)",
            "<p>Dear <strong>".htmlspecialchars($reg['first_name'])."</strong>,</p>
             <p>Thank you for submitting your registration to U.P.A.F.A.</p>
             <p>Your Application ID is <strong>#{$registrationId}</strong>.</p>
             <p>We will review your application and contact you shortly.</p>
             <p>Best regards,<br>U.P.A.F.A. Admissions</p>",
            $appAttach
        );
    } else {
        logi('PHPMailer unavailable: skipped email send.');
    }

    /* ---- Success response ---- */
    http_response_code(201);
    echo "<div style='text-align:center; font-family: Arial; padding:20px'>
            <h2 style='color:#198754;'>✅ Registration Submitted Successfully!</h2>
            <p>Your Application ID is: <strong>#{$registrationId}</strong></p>"
            . ($pdfPath ? "<p>📄 <a href='".htmlspecialchars($pdfPath, ENT_QUOTES)."' download>Download your PDF</a></p>" : "")
         . "</div>";

} catch (\Throwable $e) {
    try { $conn->rollback(); } catch (\Throwable $x) {}
    error_log('[SAVE-ERROR] '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
    http_response_code(500);
    echo "<div style='border:1px solid #f5c2c7;background:#f8d7da;padding:12px;color:#842029'>❌ Error: "
        . htmlspecialchars($e->getMessage(), ENT_QUOTES)
        . "</div>";
}
