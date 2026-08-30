<?php
// reminders/save_reminder.php
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
require_once __DIR__ . '/../db.php';
function jfail($msg, $code = 400){
  http_response_code($code);
  echo json_encode(['ok'=>false,'msg'=>$msg], JSON_UNESCAPED_SLASHES);
  exit;
}
function jok($data = []){
  echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_SLASHES);
  exit;
}

if (!isset($_SESSION['id'])) {
  jfail('Not authorized', 401);
}

/* ----- accept both JSON and regular form ----- */
$payload = null;
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  if ($raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $payload = $decoded;
  }
}
$in = ($payload && is_array($payload)) ? $payload : $_POST;

/* ----- fields ----- */
$creator        = (int)$_SESSION['id'];          // ← from session only
$title          = trim((string)($in['title'] ?? ''));
$description    = trim((string)($in['description'] ?? ''));
$tz_input       = trim((string)($in['timezone'] ?? 'UTC'));      // from UI select
$start_at_input = trim((string)($in['start_at'] ?? ''));         // datetime-local or free text
$repeat_rule    = trim((string)($in['repeat_rule'] ?? 'none'));  // none|daily|weekly|monthly|hourly
$audience       = trim((string)($in['audience'] ?? 'me'));       // me|role|specific_admin|custom_email
$aud_val        = trim((string)($in['audience_value'] ?? ''));
$channels_arr   = $in['channels'] ?? ['dashboard','email'];

if ($title === '' || $start_at_input === '') {
  jfail('Missing title or start time');
}

/* ----- validate timezone from UI ----- */
$validTZs = timezone_identifiers_list();
$timezone_name = in_array($tz_input, $validTZs, true) ? $tz_input : 'UTC';

/* ----- normalize channels ----- */
$channels_arr = array_map('trim', (array)$channels_arr);
$channels_arr = array_values(array_unique($channels_arr));
$channels_arr = array_intersect($channels_arr, ['dashboard','email']);
$channels_set = $channels_arr ? implode(',', $channels_arr) : 'dashboard';

/* ----- parse local datetime with the selected TZ, compute UTC ----- */
try {
  $tz = new DateTimeZone($timezone_name);

  // Try strict HTML datetime-local first (YYYY-MM-DDTHH:MM)
  $dtLocal = DateTime::createFromFormat('Y-m-d\TH:i', $start_at_input, $tz);

  // If not matched (other formats like "09/06/2025 04:30 PM"), fall back to parser
  if (!$dtLocal) {
    $dtLocal = new DateTime($start_at_input, $tz);
  }
  if (!$dtLocal) throw new Exception('Bad date');

  // store user's local choice (as wall time in their timezone)
  $start_at_sql = $dtLocal->format('Y-m-d H:i:s');

  // compute UTC for cron comparison
  $dtUTC = clone $dtLocal;
  $dtUTC->setTimezone(new DateTimeZone('UTC'));
  $next_fire_sql = $dtUTC->format('Y-m-d H:i:s');
} catch (Throwable $e) {
  jfail('Invalid date/time');
}

/* ----- audience & repeat guards ----- */
$allowed_audience = ['me','role','specific_admin','custom_email'];
if (!in_array($audience, $allowed_audience, true)) {
  $audience = 'me';
}
if ($audience === 'me') {
  // Storing creator ID in audience_value is harmless; the cron uses creator_admin_id for 'me'.
  $aud_val = (string)$creator;
}

$allowed_repeat = ['none','daily','weekly','monthly','hourly'];
if (!in_array($repeat_rule, $allowed_repeat, true)) {
  $repeat_rule = 'none';
}

/* ----- insert ----- */
$sql = "INSERT INTO reminder_events
  (title, description, channels, creator_admin_id, audience, audience_value,
   start_at, timezone, repeat_rule, next_fire_at, is_active)
  VALUES (?,?,?,?,?,?,?,?,?,?,1)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  jfail('DB error (prep): '.$conn->error, 500);
}

$ok = $stmt->bind_param(
  'sssissssss',
  $title,
  $description,
  $channels_set,
  $creator,
  $audience,
  $aud_val,
  $start_at_sql,    // LOCAL time as chosen in UI
  $timezone_name,   // Selected TZ (e.g., Africa/Nairobi)
  $repeat_rule,
  $next_fire_sql    // UTC instant for cron/ticker
) && $stmt->execute();

if (!$ok) {
  jfail('DB error: '.$conn->error, 500);
}

jok(['id' => $stmt->insert_id, 'msg' => 'Saved']);
