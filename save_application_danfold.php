<?php
// save_application_danfold.php
declare(strict_types=1);
header('Content-Type: application/json');

// --- DB ---
require_once __DIR__.'/db_danfold.php';

// --- Small helpers ---
function json_out(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload);
  exit;
}
function ensure_dir(string $dir): void {
  if (!is_dir($dir)) mkdir($dir, 0775, true);
}
function yn(?string $v): ?string {
  if ($v === null) return null;
  return ($v === 'Yes') ? 'Yes' : 'No';
}
function norm_json($val): ?string {
  if ($val === null || $val === '') return null;
  // If it's already a JSON string, keep it if valid
  if (is_string($val)) {
    json_decode($val);
    return (json_last_error() === JSON_ERROR_NONE) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE);
  }
  // arrays/objects -> encode
  return json_encode($val, JSON_UNESCAPED_UNICODE);
}
function save_upload(array $file, string $destDir): ?string {
  if (!isset($file['error'])) return null;
  if (is_array($file['error'])) return null; // handled elsewhere
  if ((int)$file['error'] !== UPLOAD_ERR_OK) return null;
  $ext  = pathinfo($file['name'] ?? '', PATHINFO_EXTENSION);
  $name = bin2hex(random_bytes(8)) . ($ext ? '.' . strtolower($ext) : '');
  ensure_dir($destDir);
  $path = rtrim($destDir, '/').'/'.$name;
  if (!move_uploaded_file($file['tmp_name'], $path)) return null;
  return $path;
}
function save_multi_upload(string $key, string $destDir, int $applicationId, PDO $pdo, string $kind): void {
  if (!isset($_FILES[$key])) return;
  $f = $_FILES[$key];
  $isArray = is_array($f['name']);
  if ($isArray) {
    $n = count($f['name']);
    for ($i=0; $i<$n; $i++) {
      if ((int)$f['error'][$i] !== UPLOAD_ERR_OK) continue;
      $ext  = pathinfo($f['name'][$i] ?? '', PATHINFO_EXTENSION);
      $name = bin2hex(random_bytes(8)).($ext ? '.'.strtolower($ext) : '');
      ensure_dir($destDir);
      $path = rtrim($destDir,'/').'/'.$name;
      if (move_uploaded_file($f['tmp_name'][$i], $path)) {
        $stmt = $pdo->prepare("INSERT INTO application_files (application_id, file_kind, path) VALUES (?,?,?)");
        $stmt->execute([$applicationId, $kind, $path]);
      }
    }
  } else {
    $path = save_upload($f, $destDir);
    if ($path) {
      $stmt = $pdo->prepare("INSERT INTO application_files (application_id, file_kind, path) VALUES (?,?,?)");
      $stmt->execute([$applicationId, $kind, $path]);
    }
  }
}

try {
  // 1) Whitelist of columns we allow to write on `applications`
  //    (aligned to your schema; unknown keys are ignored)
  $COLS = [
    // Step 1 — basics, addresses, passport
    'in_au','usi','lodging_country','gs_decl','sig1_dataurl','title','first_name','last_name','middle_name',
    'changed_name','prev_first','prev_last','gender','dob','email','mobile','em_first','em_last','em_phone','em_relation',
    'ov_street','ov_line2','ov_city','ov_state','ov_post','ov_country',
    'au_street','au_line2','au_city','au_state','au_post','au_country',
    'po_street','po_line2','po_city','po_state','po_post','po_country',
    'citizenship','birth_country','birth_city','passport_no','passport_exp','passport_file',

    // Step 2 — course
    'prev_enrolled','danid','multi_course','courses_json','start_date','reason',

    // Step 3 — visa + history
    'has_visa','visa_type','visa_exp','visa_file',
    'refused_other_country','refused_countries_txt','refused_au_q','refused_au_txt',
    'prot_apply_q','prot_apply_txt','prot_issued_q','prot_issued_txt',
    'breach_q','breach_txt','refused_provider_q','refused_provider_txt',
    'travel_q','travel_txt','crime_q','crime_txt',

    // Step 4 — disability/family/OSHC
    'has_disability','disability_type','disability_form',
    'health_q','health_txt',
    'marital','marriage_date','marriage_cert','spouse_in_au','spouse_visa','spouse_visa_exp',
    'deps_json',
    'has_oshc','oshc_provider','oshc_number','oshc_exp','oshc_type',

    // Step 5 — academics & English
    'y12_course','y12_when','y12_school','y12_country','y12_file',
    'has_tertiary','tertiary_json','ct_q','rpl_q',
    'eng_level','other_lang_q','other_lang',
    'cert4_q','cert4_file','etest_q','etest_name','etest_file',
    'elicos_q','elicos_json','elicos_file',

    // Step 6 — accommodation/employment/funds
    'accom_q','accom_type_json','accom_start','accom_end','airport',
    'emp_status','employment_json',
    'costs_understand','funds_declare','fund_source_json','upfront_q','upfront_amt',

    // Step 7 — student declaration
    'student_name','sig2_dataurl','decl_date',

    // Step 8 — agent/CRM/verification
    'agent_q','agency_name','agent_name','agent_phone','agent_email','sig3_dataurl','agent_date',
    'zoho_crm','pipeline','stage_begin','verif_email',
  ];

  // 2) Normalise incoming $_POST to match DB expectations
  $IN = $_POST;

  // Yes/No enums
  foreach ([
    'in_au','changed_name','prev_enrolled','multi_course','has_visa',
    'refused_other_country','refused_au_q','prot_apply_q','prot_issued_q',
    'breach_q','refused_provider_q','travel_q','crime_q',
    'has_disability','health_q','has_oshc','has_tertiary','ct_q','rpl_q',
    'cert4_q','etest_q','elicos_q','accom_q','costs_understand','funds_declare','upfront_q',
    'agent_q','spouse_in_au','airport'
  ] as $k) {
    if (isset($IN[$k])) $IN[$k] = yn($IN[$k]);
  }

  // Checkbox groups & repeaters (accept array or JSON string)
  foreach (['courses_json','deps_json','tertiary_json','elicos_json','employment_json','accom_type_json','fund_source_json'] as $k) {
    if (isset($IN[$k])) $IN[$k] = norm_json($IN[$k]);
  }

  // Signatures may arrive as sig1/sig2/sig3 (frontend dataURL) -> map to *_dataurl columns if needed
  if (!empty($IN['sig1'])) $IN['sig1_dataurl'] = $IN['sig1'];
  if (!empty($IN['sig2'])) $IN['sig2_dataurl'] = $IN['sig2'];
  if (!empty($IN['sig3'])) $IN['sig3_dataurl'] = $IN['sig3'];

  // 3) Identify target row: upsert by id
  $id = isset($IN['id']) && ctype_digit((string)$IN['id']) ? (int)$IN['id'] : null;

  // 4) Build a filtered data map -> only whitelisted, only provided keys
  $data = [];
  foreach ($COLS as $c) {
    if (array_key_exists($c, $IN)) $data[$c] = $IN[$c];
  }

  // 5) Begin transaction for atomic save + files
  $pdo->beginTransaction();

  // 6) Insert new skeleton row if no id yet
  if ($id === null) {
    // Minimal seed; we can insert with the currently provided subset
    if (empty($data)) $data['created_at'] = date('Y-m-d H:i:s'); // ensure at least one column
    $cols = array_keys($data);
    $ph   = array_fill(0, count($cols), '?');
    $sql  = "INSERT INTO applications (".implode(',', $cols).") VALUES (".implode(',', $ph).")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));
    $id = (int)$pdo->lastInsertId();
  } else {
    // Ensure the row exists; if not, create it
    $exists = $pdo->prepare("SELECT id FROM applications WHERE id=?");
    $exists->execute([$id]);
    if (!$exists->fetchColumn()) {
      $cols = array_keys($data);
      $ph   = array_fill(0, count($cols), '?');
      $sql  = "INSERT INTO applications (id,".implode(',', $cols).") VALUES (?,".implode(',', $ph).")";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(array_merge([$id], array_values($data)));
    } else {
      // Update only provided keys
      if (!empty($data)) {
        $sets = [];
        foreach (array_keys($data) as $c) { $sets[] = "{$c}=?"; }
        $sql = "UPDATE applications SET ".implode(',', $sets)." WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $vals = array_values($data);
        $vals[] = $id;
        $stmt->execute($vals);
      }
    }
  }

  // 7) File handling (single-file fields)
  $safeEmail     = isset($IN['email']) ? preg_replace('/[^a-z0-9_\-@.]+/i','_', $IN['email']) : 'unknown';
  $today         = date('Ymd_His');
  $rootUploadDir = __DIR__.'/uploads';
  ensure_dir($rootUploadDir);
  $dest = $rootUploadDir."/{$id}_{$safeEmail}_{$today}";
  ensure_dir($dest);

  $singleFiles = [
    'passport_file','visa_file','disability_form','marriage_cert',
    'y12_file','cert4_file','etest_file','elicos_file'
  ];
  $fileUpdates = [];
  foreach ($singleFiles as $sf) {
    if (!empty($_FILES[$sf]) && is_uploaded_file($_FILES[$sf]['tmp_name'])) {
      $path = save_upload($_FILES[$sf], $dest);
      if ($path) $fileUpdates[$sf] = $path;
    }
  }
  if (!empty($fileUpdates)) {
    $sets = []; $vals = [];
    foreach ($fileUpdates as $k=>$v) { $sets[] = "{$k}=?"; $vals[] = $v; }
    $vals[] = $id;
    $sql = "UPDATE applications SET ".implode(',', $sets)." WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($vals);
  }

  // 8) Multi-file buckets into application_files
  save_multi_upload('disability_docs', $dest, $id, $pdo, 'disability_docs');
  save_multi_upload('health_docs',     $dest, $id, $pdo, 'health_docs');

  // 9) Optional: normalise child tables from JSON snapshots if provided this round
  $childJsons = [
    'deps_json'      => ['table'=>'application_dependants', 'cols'=>['full_name','dob','relation']],
    'tertiary_json'  => ['table'=>'application_tertiary',   'cols'=>['course_name','school','completed']],
    'elicos_json'    => ['table'=>'application_elicos',     'cols'=>['provider','level','start_date']],
    'employment_json'=> ['table'=>'application_employment', 'cols'=>['employer','period','position']],
  ];
  foreach ($childJsons as $key => $cfg) {
    if (!empty($IN[$key])) {
      $rows = json_decode((string)$IN[$key], true);
      if (is_array($rows) && $rows) {
        // Simple approach: append rows (you can add de-dup/replace logic if you want)
        $place = '(' . implode(',', array_fill(0, count($cfg['cols'])+1, '?')) . ')';
        $sql = "INSERT INTO {$cfg['table']} (application_id,".implode(',', $cfg['cols']).") VALUES {$place}";
        $stmt = $pdo->prepare($sql);
        foreach ($rows as $r) {
          $vals = [$id];
          foreach ($cfg['cols'] as $c) { $vals[] = $r[$c] ?? null; }
          $stmt->execute($vals);
        }
      }
    }
  }

  $pdo->commit();
  json_out(200, ['success'=>true, 'id'=>$id]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  // You can log $e->getMessage() to a file here for debugging.
  json_out(500, ['success'=>false, 'error'=>'Save failed.']);
}
