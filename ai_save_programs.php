<?php
/**
 * =====================================================
 * AI / MANUAL PROGRAM SAVE API
 * -----------------------------------------------------
 * - Manual mode: level_id comes from UI
 * - AI mode: level auto-detected from program name
 * - Uses JSON payload only
 * - Transaction safe
 * =====================================================
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/program_ai_utils.php';
header('Content-Type: application/json');

/* =====================================================
   1. AUTHORIZATION
===================================================== */
if (
  !isset($_SESSION['role']) ||
  $_SESSION['role'] !== 'superadmin'
) {
  http_response_code(403);
  echo json_encode([
    'ok'  => false,
    'msg' => 'Unauthorized'
  ]);
  exit;
}

/* =====================================================
   2. INPUT (JSON ONLY)
===================================================== */
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
  echo json_encode([
    'ok'  => false,
    'msg' => 'Invalid JSON payload'
  ]);
  exit;
}

$university_id = (int)($data['university_id'] ?? 0);
$programs      = $data['programs'] ?? [];
$ui_level_id   = isset($data['level_id']) ? (int)$data['level_id'] : 0;

if ($university_id <= 0 || !is_array($programs) || empty($programs)) {
  echo json_encode([
    'ok'  => false,
    'msg' => 'Invalid input data'
  ]);
  exit;
}

/* =====================================================
   3. MODE DETECTION
===================================================== */
$mode = $ui_level_id > 0 ? 'manual' : 'ai';

/* =====================================================
   4. LEVEL KEYWORDS (INTERNATIONAL + PROFESSIONAL)
===================================================== */

$LEVEL_KEYWORDS = [

  /* -------------------------------------------------
     6 → DOCTORATE / PHD (GLOBAL)
  ------------------------------------------------- */
  6 => [
    // Common
    'phd', 'ph.d',
    'doctor', 'doctoral', 'doctorate',

    // International variants
    'dphil',                 // UK
    'dr.',                   // Some EU
    'psyd',                  // Psychology
    'edd',                   // Education
    'dba',                   // Business
    'md ', 'm.d',            // Medical Doctor
    'dvm',                   // Veterinary
    'dr med',                // Germany
    'doktor',                // EU
    'doctor of philosophy',
    'doctor of education',
    'doctor of business'
  ],

  /* -------------------------------------------------
     7 → MBA / EXECUTIVE MBA
  ------------------------------------------------- */
  7 => [
    'mba',
    'emba',
    'executive mba',
    'international mba',
    'global mba',
    'professional mba',
    'master of business administration'
  ],

  /* -------------------------------------------------
     5 → MASTER (INTERNATIONAL)
  ------------------------------------------------- */
  5 => [
    // Full titles
    'master of',
    'master of arts',
    'master of science',
    'master of engineering',
    'master of education',
    'master of nursing',
    'master of laws',
    'master of public health',
    'master of architecture',
    'master of finance',
    'master of management',

    // Abbreviations
    'msc', 'm.sc',
    'ma ', 'm.a',
    'ms ', 'm.s',
    'meng', 'm.eng',
    'med ', 'm.ed',
    'mph',                  // Public Health
    'mlaw', 'llm',           // Law
    'mfa',                  // Fine Arts
    'm.arch',               // Architecture
    'mfin',                 // Finance

    // International
    'magister',             // EU / Latin
    'postgraduate degree'
  ],

  /* -------------------------------------------------
     4 → BACHELOR / UNDERGRADUATE
  ------------------------------------------------- */
  4 => [
    // Full titles
    'bachelor of',
    'bachelor of arts',
    'bachelor of science',
    'bachelor of engineering',
    'bachelor of education',
    'bachelor of commerce',
    'bachelor of business',

    // Abbreviations
    'ba ', 'b.a',
    'bsc', 'b.sc',
    'beng', 'b.eng',
    'bed', 'b.ed',
    'bcom', 'b.com',
    'bs ', 'b.s',

    // International
    'licence',              // France
    'licentiate',           // EU
    'undergraduate degree'
  ],

  /* -------------------------------------------------
     3 → DIPLOMA
  ------------------------------------------------- */
  3 => [
    'diploma',
    'advanced diploma',
    'graduate diploma',
    'postgraduate diploma',
    'pg diploma',
    'professional diploma',
    'higher diploma',
    'technical diploma'
  ],

  /* -------------------------------------------------
     2 → CERTIFICATE
  ------------------------------------------------- */
  2 => [
    'certificate',
    'cert',
    'certification',
    'postgraduate certificate',
    'pg certificate',
    'professional certificate',
    'graduate certificate'
  ],

  /* -------------------------------------------------
     1 → SHORT COURSE / EXCHANGE / NON-DEGREE
  ------------------------------------------------- */
  1 => [
    'short course',
    'short program',
    'exchange',
    'visiting student',
    'summer school',
    'winter school',
    'executive training',
    'professional training',
    'workshop',
    'bootcamp',
    'DEC',
    'microcredential'
  ]
];

/* =====================================================
   5. LEVEL DETECTION FUNCTION
===================================================== */
function detectProgramLevel(string $program, array $map, mysqli $conn): ?int
{
  return pcvc_detect_ai_program_level($program, $map, $conn);
}

/* =====================================================
   6. PREPARE STATEMENTS
===================================================== */
$checkLevelExists = mysqli_prepare(
  $conn,
  "SELECT id FROM program_levels WHERE id=? LIMIT 1"
);

$checkDuplicate = mysqli_prepare(
  $conn,
  "SELECT id FROM programs
   WHERE university_id=? AND program_level_id=? AND program_name=?
   LIMIT 1"
);

$insertProgram = mysqli_prepare(
  $conn,
  "INSERT INTO programs
   (university_id, program_level_id, program_name, created_at)
   VALUES (?,?,?,NOW())"
);

/* =====================================================
   7. CLASSIFY PROGRAMS
===================================================== */
$valid   = [];
$invalid = [];

foreach ($programs as $rawName) {

  // Normalize name
  $name = trim(preg_replace('/\s+/', ' ', (string)$rawName));
  if ($name === '') {
    continue;
  }

  /* -----------------------------
     MANUAL MODE
  ----------------------------- */
  if ($mode === 'manual') {
    $valid[] = [
      'name'  => $name,
      'level' => $ui_level_id
    ];
    continue;
  }

  /* -----------------------------
     AI MODE
  ----------------------------- */
  $name = pcvc_normalize_ai_program_name($name);
  if ($name === '') {
    continue;
  }

  $levelId = detectProgramLevel($name, $LEVEL_KEYWORDS, $conn);

  if (!$levelId) {
    $invalid[] = [
      'program' => $name,
      'reason'  => 'Level not detected'
    ];
    continue;
  }

  // Ensure level exists globally
  mysqli_stmt_bind_param($checkLevelExists, 'i', $levelId);
  mysqli_stmt_execute($checkLevelExists);
  mysqli_stmt_store_result($checkLevelExists);

  if (mysqli_stmt_num_rows($checkLevelExists) === 0) {
    $invalid[] = [
      'program' => $name,
      'reason'  => 'Invalid program level'
    ];
    continue;
  }

  $valid[] = [
    'name'  => $name,
    'level' => $levelId
  ];
}

/* =====================================================
   8. NO VALID PROGRAMS
===================================================== */
if (empty($valid)) {
  echo json_encode([
    'ok'      => false,
    'msg'     => 'No valid programs after validation',
    'invalid' => $invalid
  ]);
  exit;
}

/* =====================================================
   9. INSERT (TRANSACTION SAFE)
===================================================== */
mysqli_begin_transaction($conn);

try {

  $inserted = 0;
  $skipped  = 0;

  foreach ($valid as $p) {

    // Check duplicate
    mysqli_stmt_bind_param(
      $checkDuplicate,
      'iis',
      $university_id,
      $p['level'],
      $p['name']
    );
    mysqli_stmt_execute($checkDuplicate);
    mysqli_stmt_store_result($checkDuplicate);

    if (mysqli_stmt_num_rows($checkDuplicate) > 0) {
      $skipped++;
      continue;
    }

    // Insert
    mysqli_stmt_bind_param(
      $insertProgram,
      'iis',
      $university_id,
      $p['level'],
      $p['name']
    );
    mysqli_stmt_execute($insertProgram);
    $inserted++;
  }

  mysqli_commit($conn);

  echo json_encode([
    'ok'        => true,
    'mode'      => $mode,
    'inserted'  => $inserted,
    'skipped'   => $skipped,
    'rejected'  => count($invalid),
    'invalid'   => $invalid
  ]);

} catch (Throwable $e) {

  mysqli_rollback($conn);

  echo json_encode([
    'ok'  => false,
    'msg' => 'Database transaction failed'
  ]);
}
