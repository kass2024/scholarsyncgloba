<?php
/**
 * reminders/_util.php
 * - Cron/CLI aware auth gating
 * - JSON helpers
 * - TZ converters
 */

if (!function_exists('is_cron_context')) {
  function is_cron_context(): bool {
    if (php_sapi_name() === 'cli') return true;
    if (defined('REMINDERS_CRON') && REMINDERS_CRON === true) return true;
    if (defined('CRON_KEY') && isset($_GET['cron_key']) && hash_equals(CRON_KEY, (string)$_GET['cron_key'])) return true;
    return false;
  }
}
$IS_CRON = is_cron_context();

if (!$IS_CRON && session_status() === PHP_SESSION_NONE) session_start();
if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
function json_fail($msg, $code = 400, $extra = []) {
  if (!headers_sent()) http_response_code($code);
  echo json_encode(array_merge(['ok'=>false,'msg'=>$msg], (array)$extra), JSON_UNESCAPED_SLASHES);
  exit;
}
function json_ok($data = []) {
  echo json_encode(array_merge(['ok'=>true], (array)$data), JSON_UNESCAPED_SLASHES);
  exit;
}

if (!$IS_CRON) {
  if (empty($_SESSION['id']) || empty($_SESSION['role'])) {
    json_fail('Not authenticated', 401);
  }
}

if (!function_exists('tz_to_utc')) {
  function tz_to_utc($local_dt_str, $tz_name) {
    try { $tz = new DateTimeZone($tz_name ?: 'UTC'); } catch(Exception $e) { $tz = new DateTimeZone('UTC'); }
    $clean = str_replace('T',' ', trim((string)$local_dt_str));
    $dt = date_create($clean, $tz);
    if (!$dt) return null;
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d H:i:s');
  }
}
