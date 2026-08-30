<?php
// reminders/cron_tick.php
define('REMINDERS_CRON', true);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/_util.php';

mysqli_set_charset($conn, 'utf8mb4');

function die500($m){ http_response_code(500); echo $m; exit; }

/* Fetch due reminders in UTC (fires at user’s local wall-clock via stored timezone) */
$sql = "SELECT * FROM reminder_events
        WHERE is_active = 1
          AND next_fire_at <= UTC_TIMESTAMP()
        ORDER BY next_fire_at ASC
        LIMIT 200";
$res = mysqli_query($conn, $sql);
if (!$res) die500('DB error: '.mysqli_error($conn));

/* Helpers */
function add_admin(&$ids, $id){ $id = (int)$id; if ($id>0) $ids[$id] = true; }
function add_email(&$es, $e){ $e = trim((string)$e); if ($e!=='') $es[strtolower($e)] = true; }

/* Prepared helpers */
function admins_by_role(mysqli $conn, string $role): array {
  $out = [];
  if ($stmt = $conn->prepare("SELECT id,email FROM admins WHERE role=?")) {
    $stmt->bind_param('s', $role);
    if ($stmt->execute() && ($rs = $stmt->get_result())) {
      while ($r = $rs->fetch_assoc()) $out[] = $r;
    }
  }
  return $out;
}
function admin_by_id(mysqli $conn, int $id): ?array {
  if ($stmt = $conn->prepare("SELECT id,email FROM admins WHERE id=? LIMIT 1")) {
    $stmt->bind_param('i', $id);
    if ($stmt->execute() && ($rs = $stmt->get_result())) {
      if ($r = $rs->fetch_assoc()) return $r;
    }
  }
  return null;
}
function all_admins_with_email(mysqli $conn): array {
  $out = [];
  $rs = $conn->query("SELECT id,email FROM admins WHERE email IS NOT NULL AND email<>''");
  while ($rs && ($r = $rs->fetch_assoc())) $out[] = $r;
  return $out;
}

while ($ev = mysqli_fetch_assoc($res)) {
  mysqli_begin_transaction($conn);
  try {
    /* Channels */
    $channelsCsv = $ev['channels'] ?: 'email,dashboard';
    $channels = array_flip(array_filter(array_map('trim', explode(',', $channelsCsv))));

    /* Audience → admin IDs + emails */
    $adminIdsSet = [];   // sets to dedupe
    $emailsSet   = [];

    $aud  = (string)$ev['audience'];
    $audv = trim((string)($ev['audience_value'] ?? ''));

    if ($aud === 'me') {
      // ✅ FIX: also resolve the creator's email
      if (!empty($ev['creator_admin_id'])) {
        $creatorId = (int)$ev['creator_admin_id'];
        add_admin($adminIdsSet, $creatorId);
        if ($r = admin_by_id($conn, $creatorId)) {
          add_email($emailsSet, $r['email'] ?? '');
        }
      }
    } elseif ($aud === 'role' && $audv !== '') {
      foreach (admins_by_role($conn, $audv) as $r) {
        add_admin($adminIdsSet, $r['id']);
        add_email($emailsSet, $r['email'] ?? '');
      }
    } elseif ($aud === 'specific_admin') {
      $aid = (int)$audv;
      if ($aid > 0) {
        if ($r = admin_by_id($conn, $aid)) {
          add_admin($adminIdsSet, $r['id']);
          add_email($emailsSet, $r['email'] ?? '');
        }
      }
    } elseif ($aud === 'custom_email') {
      add_email($emailsSet, $audv);
    } else {
      // fallback: all admins who have an email
      foreach (all_admins_with_email($conn) as $r) {
        add_admin($adminIdsSet, $r['id']);
        add_email($emailsSet, $r['email'] ?? '');
      }
    }

    $adminIds = array_map('intval', array_keys($adminIdsSet));
    $emails   = array_keys($emailsSet);

    $title = (string)$ev['title'];
    $body  = (string)($ev['description'] ?? '');
    $link  = null; // set if you have deep links

    /* (A) Bell notifications (dashboard) — unchanged */
    if (isset($channels['dashboard']) && $adminIds) {
      $t = mysqli_real_escape_string($conn, $title);
      $b = mysqli_real_escape_string($conn, $body);
      $linkSql = $link ? "'".mysqli_real_escape_string($conn, $link)."'" : "NULL";

      $vals_with_created = [];
      $vals_no_created   = [];
      foreach ($adminIds as $aid) {
        $aid = (int)$aid;
        $vals_with_created[] = "($aid,'$t','$b',$linkSql,0,UTC_TIMESTAMP())";
        $vals_no_created[]   = "($aid,'$t','$b',$linkSql,0)";
      }

      // Try with created_at first; fall back if the column doesn't exist.
      $ins1 = "INSERT INTO notifications (admin_id,title,body,link_url,is_read,created_at) VALUES ".implode(',', $vals_with_created);
      if (!mysqli_query($conn, $ins1)) {
        $ins2 = "INSERT INTO notifications (admin_id,title,body,link_url,is_read) VALUES ".implode(',', $vals_no_created);
        if (!mysqli_query($conn, $ins2)) {
          throw new Exception('insert notifications failed: '.mysqli_error($conn));
        }
      }
    }

    /* (B) Email queue — minimal addition; doesn't disturb other behavior */
    if (isset($channels['email']) && $emails) {
      $t = mysqli_real_escape_string($conn, $title);
      $b = mysqli_real_escape_string($conn, $body);
      $scheduleAt = $ev['next_fire_at']; // keep the real fire time (UTC)
      $schedEsc = mysqli_real_escape_string($conn, $scheduleAt);

      $vals = [];
      foreach ($emails as $em) {
        $emEsc = mysqli_real_escape_string($conn, $em);
        $vals[] = "(".(int)$ev['id'].",'{$emEsc}','{$t}','{$b}','{$schedEsc}')";
      }
      if ($vals) {
        $ins = "INSERT INTO reminder_emails_queue
                  (reminder_id, send_to, subject, body, scheduled_at_utc)
                VALUES ".implode(',', $vals);
        if (!mysqli_query($conn, $ins)) {
          throw new Exception('insert queue failed: '.mysqli_error($conn));
        }
      }
    }

    /* (C) Advance repeat or deactivate (preserve local wall-clock) — unchanged */
    $rule = (string)($ev['repeat_rule'] ?? 'none'); // none|daily|weekly|monthly|hourly
    $tz   = (string)($ev['timezone'] ?? 'UTC');

    if ($rule === 'none') {
      $conn->query("UPDATE reminder_events SET is_active=0 WHERE id=".(int)$ev['id']." LIMIT 1");
    } else {
      $interval =
        ($rule === 'daily')   ? "1 DAY"   :
        (($rule === 'weekly') ? "1 WEEK"  :
        (($rule === 'monthly')? "1 MONTH" : "1 HOUR"));

      $qid    = (int)$ev['id'];
      $tz_sql = mysqli_real_escape_string($conn, $tz);

      // Convert current UTC → local TZ, add interval in LOCAL time, then back to UTC.
      $advance = "
        UPDATE reminder_events
           SET next_fire_at = CONVERT_TZ(
                                  DATE_ADD(CONVERT_TZ(next_fire_at,'UTC','{$tz_sql}'), INTERVAL {$interval} ),
                                  '{$tz_sql}','UTC'
                               )
         WHERE id={$qid}
         LIMIT 1";
      if (!mysqli_query($conn, $advance)) {
        throw new Exception('advance failed: '.mysqli_error($conn));
      }
    }

    mysqli_commit($conn);
  } catch (Throwable $e) {
    mysqli_rollback($conn);
    error_log('[cron_tick] '.$e->getMessage());
    // continue to next event
  }
}

echo "ok\n";
