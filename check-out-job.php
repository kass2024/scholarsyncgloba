<?php
// check-out-job.php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$attendance_id = $_SESSION['attendance_id'] ?? 1; // fallback if attendance tracking is not implemented

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_id = $_POST['job_id'] ?? null;

    if (!$job_id) {
        echo json_encode(['status' => 'error', 'message' => 'Missing job ID.']);
        exit;
    }

    // Fetch job record
    $stmt = $conn->prepare("SELECT id, created_at FROM jobs WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $job_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Job not found.']);
        exit;
    }

    $job = $result->fetch_assoc();
    $stmt->close();

    $start_time = strtotime($job['created_at']);
    $end_time = time();
    $hours_spent = round(($end_time - $start_time) / 3600, 2); // convert seconds to hours

    // Update start/end time and hours spent
    $stmt = $conn->prepare("UPDATE jobs SET start_time = FROM_UNIXTIME(?), end_time = FROM_UNIXTIME(?), hours_spent = ? WHERE id = ?");
    $stmt->bind_param("iidi", $start_time, $end_time, $hours_spent, $job_id);
    $stmt->execute();
    $stmt->close();

    // Redirect to evaluator
    header("Location: evaluate-job.php?job_id=$job_id");
    exit;
} else {
    // Render a minimal form to select job to check out
    $jobs = $conn->query("SELECT id, job_title, job_description FROM jobs WHERE admin_id = $admin_id AND end_time IS NULL");
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>✅ Check Out Job</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 30px; }
            .box { max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            h2 { color: #007bff; text-align: center; }
            select, button { width: 100%; padding: 10px; margin-top: 15px; font-size: 16px; }
        </style>
    </head>
    <body>
    <div class="box">
        <h2>⏳ End Your Job</h2>
        <form method="POST">
            <label for="job_id">Select Active Job:</label>
            <select name="job_id" id="job_id" required>
                <option value="">-- Choose a job to finish --</option>
                <?php while ($row = $jobs->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>">
                        <?= htmlspecialchars($row['job_title']) ?> - <?= htmlspecialchars(substr($row['job_description'], 0, 50)) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="submit">✔️ Finish and Evaluate</button>
        </form>
    </div>
    </body>
    </html>
    <?php
}
?>
