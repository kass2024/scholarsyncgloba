<?php
// ScholarSync Global notifications endpoint
// Shows all notifications for the logged-in admin with pagination and inline mark-read.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';
$admin_id = $_SESSION['id'] ?? null;
if (!$admin_id || !isset($_SESSION['role'])) {
  header("Location: admin-login.php");
  exit;
}
$admin_id = (int)$admin_id;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- Handle "Mark all read" ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
  if ($stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE admin_id=? AND is_read=0")) {
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
  }
  header("Location: notifications.php?marked=all");
  exit;
}

// ---------- Pagination ----------
$perPage = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

// Total count
$total = 0;
if ($stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE admin_id=?")) {
  $stmt->bind_param('i', $admin_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res && ($r = $res->fetch_assoc())) $total = (int)$r['c'];
}
$pages = max(1, (int)ceil($total / $perPage));

// Fetch page rows (DESC newest first)
$rows = [];
if ($stmt = $conn->prepare(
  "SELECT id, title, body, link_url, is_read, COALESCE(created_at, NOW()) AS created_at
     FROM notifications
    WHERE admin_id=?
    ORDER BY id DESC
    LIMIT ? OFFSET ?"
)) {
  $stmt->bind_param('iii', $admin_id, $perPage, $offset);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($res && ($row = $res->fetch_assoc())) $rows[] = $row;
}

// Unread count (for badge)
$unread = 0;
if ($stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE admin_id=? AND is_read=0")) {
  $stmt->bind_param('i', $admin_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res && ($c = $res->fetch_assoc())) $unread = (int)$c['c'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Notifications</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background:#f5f7fb; font-family: 'Segoe UI', system-ui, -apple-system, Arial, sans-serif; }
    .card { box-shadow: 0 4px 12px rgba(0,0,0,0.06); border:none; }
    .notif-row.unread { background:#f9fbff; }
    .notif-title { font-weight:600; }
    .notif-body { white-space: pre-wrap; }
    .badge-soft { background:#eef3ff; color:#0d6efd; }
  </style>
</head>
<body>
<div class="container py-4">

  <div class="d-flex align-items-center mb-3">
    <a href="admin-dashboard.php" class="btn btn-outline-secondary me-2">
      <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
    <h3 class="m-0">Notifications</h3>
    <span class="badge ms-2 <?= $unread ? 'text-bg-danger' : 'text-bg-secondary' ?>">
      <?= (int)$unread ?> unread
    </span>
    <div class="ms-auto">
      <form method="post" class="d-inline">
        <button class="btn btn-outline-primary" name="mark_all_read" value="1" type="submit">
          <i class="bi bi-check2-all me-1"></i> Mark all read
        </button>
      </form>
    </div>
  </div>

  <?php if (isset($_GET['marked']) && $_GET['marked']==='all'): ?>
    <div class="alert alert-success py-2">All notifications marked as read.</div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body p-0">
      <?php if (!$rows): ?>
        <div class="p-4 text-center text-muted">No notifications yet.</div>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($rows as $n): ?>
            <div class="list-group-item notif-row <?= $n['is_read'] ? '' : 'unread' ?>">
              <div class="d-flex">
                <div class="flex-grow-1">
                  <div class="d-flex align-items-center gap-2">
                    <div class="notif-title"><?= h($n['title']) ?></div>
                    <?php if (!$n['is_read']): ?>
                      <span class="badge badge-soft">new</span>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($n['body'])): ?>
                    <div class="small notif-body mt-1"><?= nl2br(h($n['body'])) ?></div>
                  <?php endif; ?>
                  <div class="text-muted small mt-1">
                    <i class="bi bi-clock"></i>
                    <?= h(date('Y-m-d H:i', strtotime($n['created_at']))) ?>
                  </div>
                </div>
                <div class="ms-3 text-nowrap">
                  <?php if (!empty($n['link_url'])): ?>
                    <a class="btn btn-sm btn-outline-secondary me-1" href="<?= h($n['link_url']) ?>" target="_blank" rel="noopener">
                      <i class="bi bi-box-arrow-up-right"></i> Open
                    </a>
                  <?php endif; ?>
                  <?php if (!$n['is_read']): ?>
                    <button class="btn btn-sm btn-outline-success mark-read" data-id="<?= (int)$n['id'] ?>">
                      <i class="bi bi-check2"></i> Mark read
                    </button>
                  <?php else: ?>
                    <span class="badge text-bg-light"><i class="bi bi-check2"></i> Read</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- pagination -->
  <?php if ($pages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page<=1?'disabled':'' ?>">
          <a class="page-link" href="?page=<?= max(1,$page-1) ?>">Prev</a>
        </li>
        <?php
          $start = max(1, $page-2);
          $end = min($pages, $page+2);
          for ($i=$start; $i<=$end; $i++):
        ?>
          <li class="page-item <?= $i===$page?'active':'' ?>">
            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page>=$pages?'disabled':'' ?>">
          <a class="page-link" href="?page=<?= min($pages,$page+1) ?>">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Inline mark-read: posts to reminders/mark_read.php (expects {ok:true})
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.mark-read');
  if (!btn) return;
  const id = btn.dataset.id;
  try {
    const res = await fetch('reminders/mark_read.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({id})
    });
    const j = await res.json();
    if (j && j.ok) {
      const row = btn.closest('.notif-row');
      if (row) row.classList.remove('unread');
      btn.replaceWith(Object.assign(document.createElement('span'), {
        className: 'badge text-bg-light',
        innerHTML: '<i class="bi bi-check2"></i> Read'
      }));
      // Update unread badge text
      const badge = document.querySelector('.badge.ms-2');
      if (badge) {
        const m = /(\d+)/.exec(badge.textContent);
        const current = m ? parseInt(m[1],10) : 0;
        const next = Math.max(0, current - 1);
        badge.classList.toggle('text-bg-danger', next > 0);
        badge.classList.toggle('text-bg-secondary', next === 0);
        badge.textContent = next + ' unread';
      }
    }
  } catch (_) {
    // silent fail
  }
});
</script>
</body>
</html>
