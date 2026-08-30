<?php
require_once "database.php";

$result = $conn->query("
    SELECT * FROM campaigns 
    ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Broadcast Campaigns</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f5f7fb;
        }

        .card {
            border: none;
            border-radius: 10px;
        }

        .progress {
            height: 8px;
        }

        .badge {
            padding: 6px 10px;
            font-size: 12px;
        }
    </style>
</head>
<body>

<div class="container mt-5 mb-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>📊 Broadcast Campaigns</h3>
        <a href="broadcast_create.php" class="btn btn-primary">
            ➕ New Broadcast
        </a>
    </div>

    <div class="card shadow p-4">

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Campaign</th>
                        <th>Total</th>
                        <th>Sent</th>
                        <th>Delivered</th>
                        <th>Read</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>

                <tbody>

                <?php if($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 

                        $total = (int)$row['total_recipients'];
                        $sent = (int)$row['sent_count'];
                        $delivered = (int)$row['delivered_count'];
                        $read = (int)$row['read_count'];

                        $progress = $total > 0 ? round(($sent / $total) * 100) : 0;

                        // Status badge color
                        $statusColor = "secondary";
                        if ($row['status'] === "queued") $statusColor = "warning";
                        if ($row['status'] === "sending") $statusColor = "info";
                        if ($row['status'] === "completed") $statusColor = "success";
                        if ($row['status'] === "paused") $statusColor = "dark";
                        if ($row['status'] === "failed") $statusColor = "danger";
                    ?>

                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($row['campaign_name']) ?></strong>
                        </td>

                        <td><?= $total ?></td>
                        <td><?= $sent ?></td>
                        <td><?= $delivered ?></td>
                        <td><?= $read ?></td>

                        <td style="min-width:150px;">
                            <div class="progress">
                                <div class="progress-bar bg-success"
                                     style="width: <?= $progress ?>%">
                                </div>
                            </div>
                            <small><?= $progress ?>%</small>
                        </td>

                        <td>
                            <span class="badge bg-<?= $statusColor ?>">
                                <?= ucfirst($row['status']) ?>
                            </span>
                        </td>

                        <td>
                            <?= isset($row['created_at']) ? date("d M Y H:i", strtotime($row['created_at'])) : "-" ?>
                        </td>
                    </tr>

                    <?php endwhile; ?>
                <?php else: ?>

                    <tr>
                        <td colspan="8" class="text-center text-muted">
                            No campaigns found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

    </div>

</div>

<!-- Optional Auto Refresh -->
<script>
setTimeout(function(){
    location.reload();
}, 15000); // refresh every 15 seconds
</script>

</body>
</html>