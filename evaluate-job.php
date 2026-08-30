<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}
$date_filter = $_GET['range'] ?? 'today';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>📊 AI-Powered Job Tracking Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2 class="text-success mb-4">📈 AI-Powered Job Tracking Dashboard</h2>

    <form method="get" class="mb-3">
        <label class="form-label">⏳ Filter by:</label>
        <select name="range" class="form-select" onchange="this.form.submit()">
            <option value="today" <?= $date_filter == 'today' ? 'selected' : '' ?>>Today</option>
            <option value="week" <?= $date_filter == 'week' ? 'selected' : '' ?>>This Week</option>
            <option value="month" <?= $date_filter == 'month' ? 'selected' : '' ?>>This Month</option>
        </select>
    </form>

    <a href="job-entry.php" class="btn btn-primary mb-3">➕ Start New Job</a>

    <div class="card mb-4">
        <div class="card-body" id="summary">
            <h5>📌 Summary</h5>
            <p><strong>Total Jobs Evaluated:</strong> <span id="job_count">—</span></p>
            <p><strong>Total Hours Worked:</strong> <span id="total_hours">—</span></p>
            <p><strong>Average Productivity Score:</strong> <span id="avg_score">—</span></p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">📁 AI Job Insights</h5>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Hours</th>
                        <th>Score</th>
                        <th>AI Suggestion</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Action</th>
                        <th>🔍 Debug</th>
                    </tr>
                    </thead>
                    <tbody id="jobTableBody">
                    <tr><td colspan="11">⏳ Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <a href="admin-dashboard.php" class="btn btn-outline-success mt-3">← Back to Dashboard</a>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const range = "<?= $date_filter ?>";
    const tbody = document.getElementById("jobTableBody");
    const jobCountEl = document.getElementById("job_count");
    const totalHoursEl = document.getElementById("total_hours");
    const avgScoreEl = document.getElementById("avg_score");

    fetch(`jobs-api.php?range=${range}`)
        .then(res => res.json())
        .then(jobs => {
            tbody.innerHTML = "";
            let totalHours = 0, totalScore = 0, evaluatedCount = 0;

            if (!Array.isArray(jobs) || jobs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11">No jobs found</td></tr>';
                return;
            }

            jobs.forEach(job => {
                const row = document.createElement('tr');
                const hours = parseFloat(job.hours_spent).toFixed(2);
                totalHours += parseFloat(hours);

                if (job.end_time) {
                    totalScore += parseInt(job.productivity_score);
                    evaluatedCount++;
                }

                const debugHtml = `<pre style='white-space:pre-wrap;font-size:10px;'>${escape(job.ai_suggestions || '')}</pre>`;

                row.innerHTML = `
                    <td>${job.id}</td>
                    <td>${job.created_at.split(' ')[0]}</td>
                    <td>${escape(job.job_title)}</td>
                    <td>${escape(job.job_description)}</td>
                    <td>${hours}</td>
                    <td>${job.end_time ? job.productivity_score + '%' : '<span class="text-muted">⏳ Not ended</span>'}</td>
                    <td>${job.end_time ? escape(job.ai_suggestions || '—') : '—'}</td>
                    <td>${job.created_at}</td>
                    <td>${job.end_time || '-'}</td>
                    <td>${job.end_time ? '—' : `<a href="end-job.php?job_id=${job.id}" class="btn btn-success btn-sm">✅ End Job</a>`}</td>
                    <td>${debugHtml}</td>
                `;
                tbody.appendChild(row);
            });

            jobCountEl.textContent = evaluatedCount;
            totalHoursEl.textContent = totalHours.toFixed(2);
            avgScoreEl.textContent = evaluatedCount ? (totalScore / evaluatedCount).toFixed(2) + '%' : '0%';
        });

    function escape(text) {
        return text?.replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/\"/g, "&quot;")
                    .replace(/'/g, "&#039;") ?? '';
    }
});
</script>
</body>
</html>