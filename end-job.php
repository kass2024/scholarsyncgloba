<?php
session_start();
require_once __DIR__ . '/db.php';
require_once 'openai.php';

date_default_timezone_set('UTC'); // Always use UTC for backend timestamps

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$admin_id = $_SESSION['admin_id'];
$job_id = $_GET['job_id'] ?? null;

if (!$job_id || !is_numeric($job_id)) {
    die("❌ Missing or invalid job ID.");
}

// Step 1: Fetch job
$stmt = $conn->prepare("SELECT job_title, job_description, created_at, end_time FROM jobs WHERE id = ? AND admin_id = ?");
$stmt->bind_param("ii", $job_id, $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    die("❌ Job not found.");
}

if (!empty($job['end_time'])) {
    header("Location: tracking.php?msg=Job already ended");
    exit;
}

// Step 2: Time tracking
$end_time = date('Y-m-d H:i:s');
$start_unix = strtotime($job['created_at']);
$end_unix = strtotime($end_time);
$hours_spent = round(($end_unix - $start_unix) / 3600, 2);

// Step 3: AI Evaluation
$title = trim($job['job_title']);
$desc = trim($job['job_description']);
$score = 0;
$suggestion = "⚠️ AI evaluation failed.";
$remarks = "No specific suggestion extracted.";

if (strlen($title) >= 5 && strlen($desc) >= 5) {
    $ai = getAIInsights($title, $desc);
    $score = (int) ($ai['score'] ?? 0);
    $suggestion = $ai['suggestion'] ?? $suggestion;

    if (preg_match('/Suggestion:\s*(.+)/i', $suggestion, $matches)) {
        $remarks = trim($matches[1]);
    }
}

// Step 4: Save updates
$stmt = $conn->prepare("
    UPDATE jobs 
    SET end_time = ?, hours_spent = ?, productivity_score = ?, ai_suggestions = ?, remarks = ?
    WHERE id = ? AND admin_id = ?
");
$stmt->bind_param("sdsssii", $end_time, $hours_spent, $score, $suggestion, $remarks, $job_id, $admin_id);
$stmt->execute();
$stmt->close();

// Step 5: Update daily summary
function updateJobSummary($conn, $admin_id) {
    $date = date('Y-m-d');

    $res = $conn->query("
        SELECT COUNT(*) AS total_jobs, 
               SUM(hours_spent) AS total_hours, 
               AVG(productivity_score) AS avg_score
        FROM jobs 
        WHERE admin_id = $admin_id AND DATE(created_at) = '$date'
    ");
    $row = $res->fetch_assoc();

    $stmt = $conn->prepare("
        INSERT INTO job_summary (admin_id, summary_date, total_jobs, total_hours, avg_productivity_score)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            total_jobs = VALUES(total_jobs),
            total_hours = VALUES(total_hours),
            avg_productivity_score = VALUES(avg_productivity_score)
    ");
    $stmt->bind_param("isidd", $admin_id, $date, $row['total_jobs'], $row['total_hours'], $row['avg_score']);
    $stmt->execute();
    $stmt->close();
}

updateJobSummary($conn, $admin_id);

// Step 6: Redirect
header("Location: tracking.php?msg=✅ Job ended and evaluated");
exit;
?>
