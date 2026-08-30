<?php
require_once __DIR__ . '/db.php';
/* ============================================================
   1. FETCH STAFF THAT HAVE TASKS
============================================================ */
$staffQ = $conn->query("
    SELECT DISTINCT a.id, a.full_name
    FROM admins a
    INNER JOIN staff_tasks t ON t.staff_id = a.id
    ORDER BY a.full_name ASC
");

/* ============================================================
   2. FETCH TASKS (GROUPED BY STAFF)
============================================================ */
$task_map = [];
$tq = $conn->query("
    SELECT staff_id, task_name, extra_responsibility
    FROM staff_tasks
    ORDER BY staff_id
");
while ($r = $tq->fetch_assoc()) {
    $task_map[$r['staff_id']][] = $r;
}

/* ============================================================
   3. FETCH UNIVERSITIES PER STAFF
============================================================ */
$univ_map = [];
$uq = $conn->query("
    SELECT su.staff_id, u.id, u.name
    FROM staff_universities su
    JOIN universities u ON u.id = su.university_id
    ORDER BY u.name ASC
");
while ($r = $uq->fetch_assoc()) {
    $univ_map[$r['staff_id']][] = $r;
}

/* ============================================================
   4. FETCH PLATFORMS PER STAFF
============================================================ */
$plat_map = [];
$pq = $conn->query("
    SELECT sp.staff_id, p.id, p.platform_name
    FROM staff_platforms sp
    JOIN platforms p ON p.id = sp.platform_id
    ORDER BY p.platform_name ASC
");
while ($r = $pq->fetch_assoc()) {
    $plat_map[$r['staff_id']][] = $r;
}

/* ============================================================
   5. MASTER LISTS
============================================================ */
$all_universities = [];
$u = $conn->query("SELECT id, name FROM universities ORDER BY name ASC");
while ($r = $u->fetch_assoc()) $all_universities[] = $r;

$all_platforms = [];
$p = $conn->query("SELECT id, platform_name FROM platforms ORDER BY platform_name ASC");
while ($r = $p->fetch_assoc()) $all_platforms[] = $r;

/* ============================================================
   6. RENDER HTML
============================================================ */
ob_start();

while ($staff = $staffQ->fetch_assoc()):
    $staff_id   = (int)$staff['id'];
    $staffName  = htmlspecialchars($staff['full_name']);

    $tasks      = $task_map[$staff_id] ?? [];
    $task_text  = implode(", ", array_map(fn($t) => $t['task_name'], $tasks));
    $notes      = $tasks[0]['extra_responsibility'] ?? "";
?>

<!-- ================= STAFF CARD ================= -->
<div class="bg-white border rounded-xl shadow mb-6">

    <!-- HEADER -->
    <button onclick="toggleSection('staff<?= $staff_id ?>')"
            class="w-full flex justify-between items-center p-5 hover:bg-gray-100">
        <h3 class="text-xl font-semibold text-green-700"><?= $staffName ?></h3>
        <svg id="icon-staff<?= $staff_id ?>" class="w-6 h-6 transition-transform"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
        </svg>
    </button>

    <!-- BODY -->
    <div id="staff<?= $staff_id ?>" class="hidden p-5 bg-gray-50 space-y-5">

        <!-- TASKS -->
        <label class="font-semibold text-gray-700">Tasks (comma separated)</label>
        <textarea id="editTasks<?= $staff_id ?>"
                  class="w-full border rounded-lg p-3 bg-white h-28"
                  placeholder="Task 1, Task 2, Task 3"><?= htmlspecialchars($task_text) ?></textarea>

        <!-- NOTES -->
        <label class="font-semibold text-gray-700">Additional Responsibilities / Notes</label>
        <textarea id="editNotes<?= $staff_id ?>"
                  class="w-full border rounded-lg p-3 bg-white h-24"><?= htmlspecialchars($notes) ?></textarea>

        <!-- PLATFORMS -->
        <label class="font-semibold text-gray-700">Assigned Platforms</label>
        <div id="editPlatList<?= $staff_id ?>"
             class="grid grid-cols-2 gap-2 border p-3 rounded-lg bg-white">

            <?php foreach ($all_platforms as $p):
                $checked = false;
                foreach ($plat_map[$staff_id] ?? [] as $ap) {
                    if ($ap['id'] == $p['id']) $checked = true;
                }
            ?>
            <label class="flex items-center space-x-2">
                <input type="checkbox" value="<?= $p['id'] ?>" <?= $checked ? "checked" : "" ?>>
                <span><?= htmlspecialchars($p['platform_name']) ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <!-- UNIVERSITIES -->
        <label class="font-semibold text-gray-700">Assigned Universities</label>
        <div id="editUniList<?= $staff_id ?>"
             class="grid grid-cols-2 gap-2 border p-3 rounded-lg bg-white">

            <?php foreach ($all_universities as $u):
                $checked = false;
                foreach ($univ_map[$staff_id] ?? [] as $au) {
                    if ($au['id'] == $u['id']) $checked = true;
                }
            ?>
            <label class="flex items-center space-x-2">
                <input type="checkbox" value="<?= $u['id'] ?>" <?= $checked ? "checked" : "" ?>>
                <span><?= htmlspecialchars($u['name']) ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <!-- SAVE -->
        <button onclick="saveGroupEdit(<?= $staff_id ?>)"
                class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
            💾 Save All Changes
        </button>

    </div>
</div>

<?php endwhile;

echo ob_get_clean();
