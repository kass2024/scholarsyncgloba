<?php
require 'db.php'; // Your database connection

$date = date('Y-m-d');

// Get list of distinct admins who worked today
$admins = $conn->query("SELECT DISTINCT admin_id FROM jobs WHERE DATE(created_at) = '$date'");
while ($admin = $admins->fetch_assoc()) {
    $admin_id = $admin['admin_id'];

    // Calculate totals
    $result = $conn->query("
        SELECT 
            COUNT(*) AS total_jobs,
            SUM(hours_spent) AS total_hours,
            AVG(productivity_score) AS avg_score
        FROM jobs
        WHERE admin_id = $admin_id AND DATE(created_at) = '$date'
    ");
    $row = $result->fetch_assoc();

    // Insert or update
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
}

echo "Summary updated for $date.";
?>
