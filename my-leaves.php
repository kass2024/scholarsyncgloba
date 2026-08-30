<?php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['admin_id'])) {
  header('Location: login.php');
  exit;
}

$admin_id = $_SESSION['admin_id'];

$query = "
  SELECT lr.leave_date, lr.reason, lr.status, lr.requested_at, lr.reviewed_at, a.full_name AS reviewed_by_name
  FROM leave_requests lr
  LEFT JOIN admins a ON lr.reviewed_by = a.id
  WHERE lr.admin_id = ?
  ORDER BY lr.requested_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>My Leave Requests</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f0f2f5;
      padding: 20px;
      margin: 0;
    }

    h2 {
      color: #3f51b5;
      text-align: center;
      margin-bottom: 20px;
    }

    .table-wrapper {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      box-shadow: 0 0 10px rgba(0,0,0,0.05);
      border-radius: 8px;
      min-width: 800px;
    }

    th, td {
      padding: 12px 16px;
      border-bottom: 1px solid #eee;
      text-align: left;
    }

    th {
      background-color: #3f51b5;
      color: #fff;
    }

    tr:nth-child(even) {
      background-color: #f9f9f9;
    }

    .status {
      font-weight: bold;
      text-transform: capitalize;
    }

    .status.pending {
      color: orange;
    }

    .status.approved {
      color: green;
    }

    .status.rejected {
      color: red;
    }

    .back-link {
      margin-top: 20px;
      display: inline-block;
      color: #00a859;
      text-decoration: none;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    @media screen and (max-width: 768px) {
      table {
        font-size: 0.9rem;
      }
    }
  </style>
</head>
<body>

<h2>My Leave Requests</h2>

<div class="table-wrapper">
<table>
  <thead>
    <tr>
      <th>Date(s)</th>
      <th>Reason</th>
      <th>Requested At</th>
      <th>Status</th>
      <th>Reviewed By</th>
      <th>Reviewed At</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['leave_date']) ?></td>
          <td><?= htmlspecialchars($row['reason']) ?></td>
          <td><?= date('Y-m-d H:i', strtotime($row['requested_at'])) ?></td>
          <td class="status <?= $row['status'] ?>"><?= $row['status'] ?></td>
          <td><?= $row['reviewed_by_name'] ?? '—' ?></td>
          <td><?= $row['reviewed_at'] ? date('Y-m-d H:i', strtotime($row['reviewed_at'])) : '—' ?></td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="6" style="text-align:center;">No leave requests submitted yet.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
</div>

<a class="back-link" href="admin-dashboard.php">← Back to Dashboard</a>

</body>
</html>
