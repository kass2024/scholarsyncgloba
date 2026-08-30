<?php
session_start();
require_once __DIR__ . '/db.php';
// Auth check
$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header("Location: admin-login.php");
    exit;
}

$admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM admins WHERE id = " . intval($admin_id)));
$role = $admin['role'] ?? 'standard';
$email = $admin['email'] ?? '';
$fullName = $admin['full_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Agent Applications Report</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" />
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" />
</head>
<body class="bg-light">
<div class="container py-4">
  <h2 class="mb-3">Agent Applications Report</h2>
  
  <a href="admin-dashboard.php" class="btn btn-secondary mb-3">← Back to Dashboard</a>

<?php
if ($role === 'superadmin') {
    // Get agents (exclude Catholic university)
    $agents = mysqli_query($conn, "
        SELECT email, full_name
        FROM admins
        WHERE role NOT IN ('Catholic university of America')
        ORDER BY full_name ASC
    ");
    ?>
    <form method="GET" class="mb-4">
      <label for="agent_email" class="form-label">Filter by Agent:</label>
      <select name="agent_email" id="agent_email" class="form-select" onchange="this.form.submit()">
        <option value="">-- All Agents (show all students) --</option>
        <?php while ($row = mysqli_fetch_assoc($agents)): ?>
            <option value="<?= htmlspecialchars($row['email']) ?>"
              <?= ($_GET['agent_email'] ?? '') === $row['email'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($row['full_name']) ?> (<?= htmlspecialchars($row['email']) ?>)
            </option>
        <?php endwhile; ?>
      </select>
    </form>
    <?php
    $filterEmail = $_GET['agent_email'] ?? '';
    echo "<p><strong>Superadmin view:</strong> " . ($filterEmail ? htmlspecialchars($filterEmail) : 'All students') . "</p>";
} else {
    $filterEmail = $email;
    echo "<p><strong>Your students:</strong> " . htmlspecialchars($email) . "</p>";
}

// Build query
$query = "
SELECT s.*, u.name AS university_name, r.name AS region_name, a.full_name AS agent_full_name
FROM student_applications s
LEFT JOIN universities u ON u.id = s.university_id
LEFT JOIN regions r ON r.id = s.region_id
LEFT JOIN admins a ON a.email = s.agent_email
WHERE 1 = 1
";

// If superadmin → exclude Catholic university students
if ($role === 'superadmin') {
    $query .= " AND (a.role NOT IN ('Catholic university of America') OR a.role IS NULL) ";
}

// If filtering by agent
if ($filterEmail) {
    $query .= " AND s.agent_email = '" . mysqli_real_escape_string($conn, $filterEmail) . "' ";
}

$query .= " ORDER BY s.application_date DESC ";
$result = mysqli_query($conn, $query);

$totalStudents = mysqli_num_rows($result);
?>

<div class="alert alert-info mb-3">
    Showing <strong><?= $totalStudents ?></strong> students <?= ($filterEmail ? "for agent <strong>" . htmlspecialchars($filterEmail) . "</strong>" : "for <strong>all agents</strong>") ?>.
</div>

<?php if ($totalStudents > 0): ?>
<table id="agentTable" class="table table-striped table-bordered">
  <thead>
    <tr>
      <th>#</th>
      <th>Student Name</th>
      <th>Email</th>
      <th>Phone</th>
      <th>University</th>
      <th>Region</th>
      <th>Application Date</th>
      <th>Agent Name</th>
      <th>Agent Email</th>
    </tr>
  </thead>
  <tbody>
    <?php $i = 1;
    while ($row = mysqli_fetch_assoc($result)): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td><?= htmlspecialchars($row['area_code'] . ' ' . $row['phone_number']) ?></td>
        <td><?= htmlspecialchars($row['university_name'] ?: '-') ?></td>
        <td><?= htmlspecialchars($row['region_name'] ?: '-') ?></td>
        <td><?= htmlspecialchars($row['application_date']) ?></td>
        <td><?= htmlspecialchars($row['agent_full_name'] ?: '-') ?></td>
        <td><?= htmlspecialchars($row['agent_email']) ?></td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>
<?php else: ?>
    <p class="alert alert-warning">No students found<?= $filterEmail ? ' for this agent.' : '.' ?></p>
<?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
$(document).ready(function () {
  $('#agentTable').DataTable({
    dom: 'Bfrtip',
    buttons: ['copy', 'csv', 'excel', 'print'],
    pageLength: 25
  });
});
</script>

</body>
</html>
