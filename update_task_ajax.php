<?php
require_once __DIR__ . '/db.php';
header("Content-Type: application/json");

/* ============================================================
   FORCE MYSQL ERRORS (IMPORTANT)
============================================================ */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ============================================================
   VALIDATE REQUEST
============================================================ */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status"=>"error","message"=>"Invalid request"]);
    exit;
}

/* ============================================================
   INPUTS (SAFE)
============================================================ */
$staff_id  = intval($_POST["staff_id"] ?? 0);
$tasks_raw = trim($_POST["tasks"] ?? "");
$notes     = trim($_POST["notes"] ?? "");

/* SAFELY HANDLE ARRAYS (may be missing) */
$uni_ids  = isset($_POST["edit_uni_ids"])  ? (array)$_POST["edit_uni_ids"]  : [];
$plat_ids = isset($_POST["edit_plat_ids"]) ? (array)$_POST["edit_plat_ids"] : [];

if ($staff_id <= 0) {
    echo json_encode(["status"=>"error","message"=>"Invalid staff"]);
    exit;
}

if ($tasks_raw === "") {
    echo json_encode(["status"=>"error","message"=>"Tasks cannot be empty"]);
    exit;
}

/* ============================================================
   CLEAN TASKS
============================================================ */
$tasks = array_filter(array_map("trim", explode(",", $tasks_raw)));

if (!$tasks) {
    echo json_encode(["status"=>"error","message"=>"No valid tasks provided"]);
    exit;
}

/* ============================================================
   TRANSACTION
============================================================ */
try {

    $conn->begin_transaction();

    /* ========================================================
       1️⃣ DELETE OLD TASKS
    ========================================================= */
    $stmt = $conn->prepare("DELETE FROM staff_tasks WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $stmt->close();

    /* ========================================================
       2️⃣ INSERT TASK GROUP
    ========================================================= */
    $stmt = $conn->prepare("
        INSERT INTO staff_tasks (staff_id, task_name, is_custom, extra_responsibility)
        VALUES (?, ?, 1, ?)
    ");

    foreach ($tasks as $t) {
        $stmt->bind_param("iss", $staff_id, $t, $notes);
        $stmt->execute();
    }
    $stmt->close();

    /* ========================================================
       3️⃣ UPDATE UNIVERSITIES
    ========================================================= */
    $stmt = $conn->prepare("DELETE FROM staff_universities WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $stmt->close();

    if (!empty($uni_ids)) {
        $stmt = $conn->prepare("
            INSERT INTO staff_universities (staff_id, university_id)
            VALUES (?, ?)
        ");
        foreach ($uni_ids as $uid) {
            $uid = intval($uid);
            if ($uid > 0) {
                $stmt->bind_param("ii", $staff_id, $uid);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    /* ========================================================
       4️⃣ UPDATE PLATFORMS
    ========================================================= */
    $stmt = $conn->prepare("DELETE FROM staff_platforms WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $stmt->close();

    if (!empty($plat_ids)) {
        $stmt = $conn->prepare("
            INSERT INTO staff_platforms (staff_id, platform_id)
            VALUES (?, ?)
        ");
        foreach ($plat_ids as $pid) {
            $pid = intval($pid);
            if ($pid > 0) {
                $stmt->bind_param("ii", $staff_id, $pid);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    /* ========================================================
       5️⃣ COMMIT
    ========================================================= */
    $conn->commit();

    echo json_encode([
        "status"=>"success",
        "message"=>"Staff responsibilities updated successfully"
    ]);

} catch (Throwable $e) {

    $conn->rollback();

    echo json_encode([
        "status"=>"error",
        "message"=>"Update failed: ".$e->getMessage()
    ]);
}
