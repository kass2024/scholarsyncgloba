<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/testimonials_lib.php';

$admin_id = $_SESSION['id'] ?? null;
if (!$admin_id || !isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: admin-login.php');
    exit;
}

pcvc_ensure_testimonials_table($conn);

$uploadDir = __DIR__ . '/uploads/testimonials';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$flash = $_SESSION['flash_testimonials'] ?? '';
unset($_SESSION['flash_testimonials']);

function testimonials_redirect_flash(string $msg): void
{
    $_SESSION['flash_testimonials'] = $msg;
    header('Location: admin-testimonials.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare('SELECT photo_path FROM site_testimonials WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && $row['photo_path'] !== '') {
                $abs = __DIR__ . '/' . ltrim($row['photo_path'], '/');
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }
            $del = $conn->prepare('DELETE FROM site_testimonials WHERE id = ?');
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();
        }
        testimonials_redirect_flash('Testimonial deleted.');
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $role_title = trim((string) ($_POST['role_title'] ?? ''));
        $quote = trim((string) ($_POST['quote'] ?? ''));
        $sort_order = (int) ($_POST['sort_order'] ?? 0);
        $is_published = isset($_POST['is_published']) ? 1 : 0;

        if ($name === '' || $quote === '') {
            testimonials_redirect_flash('Name and quote are required.');
        }

        $photo_path = '';
        if ($id > 0) {
            $st = $conn->prepare('SELECT photo_path FROM site_testimonials WHERE id = ?');
            $st->bind_param('i', $id);
            $st->execute();
            $prev = $st->get_result()->fetch_assoc();
            $st->close();
            $photo_path = $prev['photo_path'] ?? '';
        }

        if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
            $f = $_FILES['photo'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($ext, $allowed, true)) {
                testimonials_redirect_flash('Invalid image type. Use JPG, PNG, WebP, or GIF.');
            }
            if (($f['size'] ?? 0) > 3 * 1024 * 1024) {
                testimonials_redirect_flash('Image must be 3MB or smaller.');
            }
            $newName = 't_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = $uploadDir . DIRECTORY_SEPARATOR . $newName;
            if (!move_uploaded_file($f['tmp_name'], $dest)) {
                testimonials_redirect_flash('Could not save uploaded image.');
            }
            if ($photo_path !== '') {
                $old = __DIR__ . '/' . ltrim($photo_path, '/');
                if (is_file($old)) {
                    @unlink($old);
                }
            }
            $photo_path = 'uploads/testimonials/' . $newName;
        }

        if ($photo_path === '' && $id === 0) {
            testimonials_redirect_flash('Please upload a photo for new testimonials.');
        }

        if ($id > 0) {
            if ($photo_path === '') {
                $up = $conn->prepare(
                    'UPDATE site_testimonials SET name=?, role_title=?, quote=?, sort_order=?, is_published=? WHERE id=?'
                );
                $up->bind_param('sssiii', $name, $role_title, $quote, $sort_order, $is_published, $id);
            } else {
                $up = $conn->prepare(
                    'UPDATE site_testimonials SET name=?, role_title=?, quote=?, photo_path=?, sort_order=?, is_published=? WHERE id=?'
                );
                $up->bind_param('ssssiii', $name, $role_title, $quote, $photo_path, $sort_order, $is_published, $id);
            }
            $up->execute();
            $up->close();
            testimonials_redirect_flash('Testimonial updated.');
        } else {
            $ins = $conn->prepare(
                'INSERT INTO site_testimonials (name, role_title, quote, photo_path, sort_order, is_published) VALUES (?,?,?,?,?,?)'
            );
            $ins->bind_param('ssssii', $name, $role_title, $quote, $photo_path, $sort_order, $is_published);
            $ins->execute();
            $ins->close();
            testimonials_redirect_flash('Testimonial added.');
        }
    }
}

$rows = [];
$res = $conn->query('SELECT * FROM site_testimonials ORDER BY sort_order ASC, id DESC');
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $eid = (int) $_GET['edit'];
    if ($eid > 0) {
        $st = $conn->prepare('SELECT * FROM site_testimonials WHERE id = ?');
        $st->bind_param('i', $eid);
        $st->execute();
        $edit = $st->get_result()->fetch_assoc();
        $st->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Website testimonials | Superadmin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background: #f1f5f9; }
    .card { border: none; box-shadow: 0 4px 20px rgba(15,23,42,.08); }
    .thumb { width: 56px; height: 56px; object-fit: cover; border-radius: 10px; }
  </style>
</head>
<body>
<div class="container py-4 py-md-5">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h1 class="h3 mb-0">Website testimonials</h1>
      <p class="text-muted small mb-0">Photos and quotes shown on the homepage and testimonials page. Superadmin only.</p>
    </div>
    <a href="admin-dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Dashboard</a>
  </div>

  <?php if ($flash !== ''): ?>
    <div class="alert alert-info py-2"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card p-4">
        <h2 class="h5 mb-3"><?= $edit ? 'Edit testimonial' : 'Add testimonial' ?></h2>
        <form method="post" enctype="multipart/form-data" class="vstack gap-3">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
          <div>
            <label class="form-label">Full name</label>
            <input type="text" name="name" class="form-control" required maxlength="190"
                   value="<?= htmlspecialchars($edit['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div>
            <label class="form-label">Title / program (optional)</label>
            <input type="text" name="role_title" class="form-control" maxlength="255"
                   placeholder="e.g. MSc student, Kigali"
                   value="<?= htmlspecialchars($edit['role_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div>
            <label class="form-label">Quote</label>
            <textarea name="quote" class="form-control" rows="4" required maxlength="4000"><?= htmlspecialchars($edit['quote'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>
          <div>
            <label class="form-label">Photo <?= $edit ? '(leave empty to keep current)' : '' ?></label>
            <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" <?= empty($edit) ? 'required' : '' ?>>
            <?php if (!empty($edit['photo_path'])): ?>
              <div class="mt-2"><img src="<?= htmlspecialchars($edit['photo_path'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="thumb"></div>
            <?php endif; ?>
          </div>
          <div>
            <label class="form-label">Sort order</label>
            <input type="number" name="sort_order" class="form-control" value="<?= (int) ($edit['sort_order'] ?? 0) ?>">
            <div class="form-text">Lower numbers appear first.</div>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_published" id="pub" value="1"
              <?= empty($edit) || !empty($edit['is_published']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="pub">Published on website</label>
          </div>
          <button type="submit" class="btn btn-primary"><?= $edit ? 'Update' : 'Add' ?></button>
          <?php if ($edit): ?>
            <a href="admin-testimonials.php" class="btn btn-outline-secondary">Cancel edit</a>
          <?php endif; ?>
        </form>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="card p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th></th>
                <th>Name</th>
                <th>Order</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
              <tr>
                <td style="width:72px">
                  <?php if ($r['photo_path'] !== ''): ?>
                    <img src="<?= htmlspecialchars($r['photo_path'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="thumb">
                  <?php else: ?>
                    <span class="text-muted small">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></div>
                  <?php if ($r['role_title'] !== ''): ?>
                    <div class="small text-muted"><?= htmlspecialchars($r['role_title'], ENT_QUOTES, 'UTF-8') ?></div>
                  <?php endif; ?>
                </td>
                <td><?= (int) $r['sort_order'] ?></td>
                <td><?= !empty($r['is_published']) ? '<span class="badge text-bg-success">Live</span>' : '<span class="badge text-bg-secondary">Hidden</span>' ?></td>
                <td class="text-end text-nowrap">
                  <a href="admin-testimonials.php?edit=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this testimonial?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (count($rows) === 0): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">No testimonials yet. Add one on the left.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
