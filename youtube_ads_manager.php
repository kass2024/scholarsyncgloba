<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
/* =====================================================
   HELPERS
===================================================== */
function shortLink(?string $url): string {
    if (!$url) return '-';
    $clean = preg_replace('#^https?://#', '', $url);
    return strlen($clean) > 28 ? substr($clean, 0, 28) . '…' : $clean;
}

/* =====================================================
   ADD NEW AD (MIN 1 SUBTOPIC, UNLIMITED)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {

    $topicTitle = trim($_POST['new_topic'] ?? '');
    $subtopics  = array_filter(array_map('trim', $_POST['new_subtopics'] ?? []));

    if ($topicTitle === '') {
        die('Main topic is required.');
    }

    if (count($subtopics) < 1) {
        die('At least one subtopic is required.');
    }

    $conn->begin_transaction();
    try {

        /* ---- MAIN TOPIC ---- */
        $stmt = $conn->prepare("INSERT INTO ad_topics (topic_title) VALUES (?)");
        $stmt->bind_param("s", $topicTitle);
        $stmt->execute();
        $topicId = $conn->insert_id;

        $presenter = $_POST['presenter'] ?: 93;
        $status    = $_POST['status'] ?: 'Planned';
        $serial    = 'ADT-' . date('Y') . '-' . time();

        /* ---- PREPARED STATEMENTS ---- */
        $subStmt = $conn->prepare("
            INSERT INTO ad_subtopics (topic_id, subtopic_title)
            VALUES (?, ?)
        ");

        $adStmt = $conn->prepare("
            INSERT INTO youtube_advertisements
            (serial_no, main_topic_id, sub_topic_id, planned_date,
             presenter_id, youtube_channel, youtube_link, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($subtopics as $sub) {

            /* ---- SUBTOPIC ---- */
            $subStmt->bind_param("is", $topicId, $sub);
            $subStmt->execute();
            $subId = $conn->insert_id;

            /* ---- AD ---- */
            $adStmt->bind_param(
                "siisisss",
                $serial,
                $topicId,
                $subId,
                $_POST['planned_date'],
                $presenter,
                $_POST['youtube_channel'],
                $_POST['youtube_link'],
                $status
            );
            $adStmt->execute();
        }

        $conn->commit();

    } catch (Throwable $e) {
        $conn->rollback();
        die("Add failed");
    }

    header("Location: youtube_ads_manager.php");
    exit;
}

/* =====================================================
   UPDATE AD + UPDATE SUBTOPICS (EXTENDED)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {

    $conn->begin_transaction();
    try {

        /* ---- UPDATE AD (ORIGINAL LOGIC) ---- */
        $stmt = $conn->prepare("
            UPDATE youtube_advertisements SET
                planned_date   = ?,
                presenter_id   = ?,
                youtube_channel= ?,
                youtube_link   = ?,
                status         = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "sisssi",
            $_POST['planned_date'],
            $_POST['presenter'],
            $_POST['youtube_channel'],
            $_POST['youtube_link'],
            $_POST['status'],
            $_POST['id']
        );
        $stmt->execute();

        /* ---- UPDATE SUBTOPICS (NEW, SAFE) ---- */
        if (!empty($_POST['edit_subtopics']) && !empty($_POST['topic_id'])) {

            $subUpdate = $conn->prepare("
                UPDATE ad_subtopics
                SET subtopic_title = ?
                WHERE id = ? AND topic_id = ?
            ");

            foreach ($_POST['edit_subtopics'] as $sid => $title) {
                $title = trim($title);
                if ($title !== '') {
                    $subUpdate->bind_param("sii", $title, $sid, $_POST['topic_id']);
                    $subUpdate->execute();
                }
            }
        }

        $conn->commit();

    } catch (Throwable $e) {
        $conn->rollback();
        die("Edit failed");
    }

    header("Location: youtube_ads_manager.php");
    exit;
}

/* =====================================================
   FETCH DATA (ROWSPAN SAFE)
===================================================== */
$q = $conn->query("
SELECT 
    a.id,
    a.serial_no,
    a.planned_date,
    a.presenter_id,
    a.youtube_channel,
    a.youtube_link,
    a.status,
    COALESCE(ad.full_name,'Unknown') AS presenter,
    t.id AS topic_id,
    t.topic_title,
    s.id AS sub_id,
    s.subtopic_title
FROM ad_topics t
JOIN ad_subtopics s ON s.topic_id = t.id
JOIN youtube_advertisements a ON a.sub_topic_id = s.id
LEFT JOIN admins ad ON ad.id = COALESCE(a.presenter_id,93)
ORDER BY t.id, s.id
");

$data = [];
while ($row = $q->fetch_assoc()) {
    $data[$row['topic_id']]['topic']  = $row['topic_title'];
    $data[$row['topic_id']]['serial'] = $row['serial_no'];
    $data[$row['topic_id']]['rows'][] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>YouTube Advertisement Planner</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body{background:#f4f8f5}
th{background:#2e6a2c!important;color:#fff}
.badge-Planned{background:#ffc107}
.badge-Completed{background:#198754}
.subtopic{padding-left:20px}
</style>
</head>

<body class="p-4">
<div class="container">

<div class="card shadow-sm p-4">
<div class="d-flex justify-content-between mb-3">
<h4 class="text-success fw-bold">YouTube Advertisement Planner</h4>
<button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">➕ Add</button>
</div>

<table class="table table-bordered align-middle">
<thead>
<tr>
<th>Serial</th>
<th>Main Topic</th>
<th>Subtopic</th>
<th>Date</th>
<th>Presenter</th>
<th>Channel</th>
<th>YouTube</th>
<th>Status</th>
<th class="text-center">Action</th>
</tr>
</thead>
<tbody>

<?php foreach ($data as $topic): ?>
<?php $rowspan = count($topic['rows']); ?>

<?php foreach ($topic['rows'] as $i => $r): ?>
<tr>
<?php if ($i === 0): ?>
<td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($topic['serial']) ?></td>
<td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($topic['topic']) ?></td>
<?php endif; ?>

<td class="subtopic">• <?= htmlspecialchars($r['subtopic_title']) ?></td>
<td><?= $r['planned_date'] ?></td>
<td><?= $r['presenter'] ?></td>
<td><?= $r['youtube_channel'] ?></td>
<td>
<a href="<?= $r['youtube_link'] ?>" target="_blank"><?= shortLink($r['youtube_link']) ?></a>
</td>
<td>
<span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span>
</td>
<td class="text-center">
<button class="btn btn-sm btn-primary"
onclick='openEdit(<?= json_encode([
    "id"=>$r["id"],
    "topic_id"=>$r["topic_id"],
    "planned_date"=>$r["planned_date"],
    "youtube_channel"=>$r["youtube_channel"],
    "youtube_link"=>$r["youtube_link"],
    "status"=>$r["status"],
    "presenter_id"=>$r["presenter_id"]
]) ?>)'>
<i class="bi bi-pencil"></i>
</button>
</td>
</tr>
<?php endforeach; ?>
<?php endforeach; ?>

</tbody>
</table>
</div>
</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addModal">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<form method="POST">
<input type="hidden" name="action" value="add">

<div class="modal-header bg-success text-white">
<h5>Add Advertisement Topic</h5>
<button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body row g-3">
<input class="form-control" name="new_topic" placeholder="Main Topic" required>

<div id="subtopics">
<input class="form-control mb-2" name="new_subtopics[]" placeholder="Subtopic 1" required>
</div>

<button type="button" class="btn btn-outline-secondary btn-sm" onclick="addSub()">+ Add Subtopic</button>

<input type="date" class="form-control mt-3" name="planned_date" required>

<select name="presenter" class="form-select">
<option value="">Select Presenter</option>
<?php
$p=$conn->query("SELECT id,full_name FROM admins WHERE role='staff'");
while($x=$p->fetch_assoc()){
echo "<option value='{$x['id']}'>{$x['full_name']}</option>";
}
?>
</select>

<input class="form-control" name="youtube_channel" placeholder="YouTube Channel">
<input class="form-control" name="youtube_link" placeholder="YouTube URL">

<select name="status" class="form-select">
<option value="Planned">Planned</option>
<option value="Completed">Completed</option>
</select>
</div>

<div class="modal-footer">
<button class="btn btn-success">Save</button>
</div>
</form>
</div>
</div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<form method="POST">
<input type="hidden" name="action" value="edit">
<input type="hidden" name="id">
<input type="hidden" name="topic_id">

<div class="modal-header bg-primary text-white">
<h5>Edit Advertisement</h5>
<button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body row g-3">
<div id="editSubtopics"></div>

<input type="date" name="planned_date" class="form-control" required>
<input name="youtube_channel" class="form-control">
<input name="youtube_link" class="form-control">

<select name="presenter" class="form-select">
<?php
$p=$conn->query("SELECT id,full_name FROM admins WHERE role='staff'");
while($x=$p->fetch_assoc()){
echo "<option value='{$x['id']}'>{$x['full_name']}</option>";
}
?>
</select>

<select name="status" class="form-select">
<option value="Planned">Planned</option>
<option value="Completed">Completed</option>
</select>
</div>

<div class="modal-footer">
<button class="btn btn-primary">Update</button>
</div>
</form>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function addSub(){
    const d=document.createElement('input');
    d.className='form-control mb-2';
    d.name='new_subtopics[]';
    d.placeholder='Another Subtopic';
    document.getElementById('subtopics').appendChild(d);
}
function openEdit(d){
    const m=document.getElementById('editModal');
    m.querySelector('[name=id]').value=d.id;
    m.querySelector('[name=topic_id]').value=d.topic_id;
    m.querySelector('[name=planned_date]').value=d.planned_date;
    m.querySelector('[name=youtube_channel]').value=d.youtube_channel;
    m.querySelector('[name=youtube_link]').value=d.youtube_link;
    m.querySelector('[name=status]').value=d.status;
    m.querySelector('[name=presenter]').value=d.presenter_id;

    fetch('fetch_subtopics.php?topic_id='+d.topic_id)
        .then(r=>r.json())
        .then(list=>{
            const box=document.getElementById('editSubtopics');
            box.innerHTML='';
            list.forEach(s=>{
                box.innerHTML+=`
                <input class="form-control mb-2"
                       name="edit_subtopics[${s.id}]"
                       value="${s.subtopic_title}">
                `;
            });
        });

    new bootstrap.Modal(m).show();
}
</script>

</body>
</html>
