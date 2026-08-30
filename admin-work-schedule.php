<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';
// Only allow superadmins
if (!isset($_SESSION['admin_id'])) exit("❌ Unauthorized access.");
$admin_id = $_SESSION['admin_id'];

$role_stmt = $conn->prepare("SELECT role FROM admins WHERE id = ?");
$role_stmt->bind_param("i", $admin_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();

if ($role !== 'superadmin') exit("❌ Access denied. Superadmin only.");

// Track success
$success_message = "";

// Save submitted schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $break = intval($_POST['allowed_break_minutes']);

    $check = $conn->prepare("SELECT id FROM work_schedule WHERE user_id = ?");
    $check->bind_param("s", $user_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE work_schedule SET start_time = ?, end_time = ?, allowed_break_minutes = ? WHERE user_id = ?");
        $stmt->bind_param("ssis", $start_time, $end_time, $break, $user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO work_schedule (user_id, start_time, end_time, allowed_break_minutes) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $user_id, $start_time, $end_time, $break);
    }
    $stmt->execute();

    // Redirect after save
    header("Location: admin-dashboard.php"); // You can change to 'admin-work-schedule.php' to stay on same page
    exit;
}

// Fetch staff and their schedules
$staff_result = $conn->query("SELECT id, full_name FROM admins WHERE role = 'staff'");
$schedules = $conn->query("SELECT * FROM work_schedule")->fetch_all(MYSQLI_ASSOC);
$schedule_map = [];
foreach ($schedules as $s) $schedule_map[$s['user_id']] = $s;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin – Work Schedules</title>
    <style>
        body { font-family: Arial; background: #f9f9f9; padding: 20px; }
        .card {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.05);
            max-width: 500px;
        }
        h2 { color: #007bff; }
        label { display: block; margin-top: 10px; }
        input, select {
            padding: 8px;
            width: 100%;
            margin-top: 5px;
        }
        button {
            margin-top: 10px;
            background: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        a.back {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            background: #6c757d;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
        }
    </style>
</head>
<body>

<h2>📅 Work Schedule Configuration</h2>

<?php while ($row = $staff_result->fetch_assoc()):
    $uid = $row['id'];
    $schedule = $schedule_map[$uid] ?? ['start_time' => '', 'end_time' => '', 'allowed_break_minutes' => 60];
?>
<div class="card">
    <form method="POST">
        <h3><?= htmlspecialchars($row['full_name']) ?></h3>
        <input type="hidden" name="user_id" value="<?= $uid ?>">
        <label>Start Time:</label>
        <input type="time" name="start_time" value="<?= $schedule['start_time'] ?>">
        <label>End Time:</label>
        <input type="time" name="end_time" value="<?= $schedule['end_time'] ?>">
        <label>Allowed Break (minutes):</label>
        <input type="number" name="allowed_break_minutes" value="<?= $schedule['allowed_break_minutes'] ?>">
        <button type="submit">💾 Save Schedule</button>
    </form>
</div>
<?php endwhile; ?>

<a href="admin-dashboard.php" class="back">🔙 Back to Dashboard</a>

</body>
</html>
