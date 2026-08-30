<?php
session_start();
require_once __DIR__ . '/db.php';
// Fetch role of logged-in user
$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    die("Access denied. Not logged in.");
}
$admin_id_safe = mysqli_real_escape_string($conn, $admin_id);
$admin_result = mysqli_query($conn, "SELECT role FROM admins WHERE id = '$admin_id_safe'");
$admin = mysqli_fetch_assoc($admin_result);
$role = strtolower($admin['role'] ?? 'standard');

// Get flag from URL
$flag = $_GET['flag'] ?? null;

// Allowed flags
$validFlags = [
    'incomplete_app', 'submitted', 'sent_to_platform', 'admit', 'i20_sent', 'sevis_paid',
    'visa_scheduled', 'visa_approved', 'enrolled', 'addn_doc', 'deny', 'app_start'
];

if (!in_array($flag, $validFlags)) {
    die("Invalid or missing flag.");
}

// Build query depending on role
if ($role === 'catholic university of america') {
    // Catholic user: show only Catholic-specific students
    $query = "
        SELECT id, 'student_applications' AS source, first_name, last_name, email, phone_number, 'Catholic University of America' AS destination
        FROM student_applications
        WHERE `$flag` = 1 AND university_id = 1 AND region_id = 1
        ORDER BY id DESC
    ";
} else {
    // All other roles: show from all sources
    $query = "
        SELECT id, 'student_applications' AS source, first_name, last_name, email, phone_number, destination
        FROM student_applications
        WHERE `$flag` = 1

        UNION ALL

        SELECT id, 'malta_applications' AS source, name AS first_name, surname AS last_name, email, contact_number AS phone_number, 'Malta' AS destination
        FROM malta_applications
        WHERE `$flag` = 1

        UNION ALL

        SELECT id, 'turkey_applications' AS source, first_name, last_name, email, mobile AS phone_number, 'Turkey' AS destination
        FROM turkey_applications
        WHERE `$flag` = 1

        ORDER BY id DESC
    ";
}

$result = $conn->query($query);
$students = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Applicants with <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $flag))) ?> Status</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8f9fa;
    }
    .container {
      margin-top: 40px;
    }
    h3 {
      font-size: 1.5rem;
    }
    .badge {
      font-size: 0.85rem;
      padding: 5px 8px;
    }
    @media print {
  body {
    background: white;
  }

  .btn, .dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate {
    display: none !important;
  }

  table {
    font-size: 12px;
    border-collapse: collapse !important;
  }

  table th, table td {
    border: 1px solid #000 !important;
    padding: 6px;
  }

  h3 {
    font-size: 18px;
    margin-bottom: 20px;
  }
}
@media print {
  footer::after {
    content: "Printed on <?= date('Y-m-d H:i') ?>";
    display: block;
    margin-top: 30px;
    text-align: right;
    font-size: 10px;
    color: #555;
  }
}

  </style>
</head>
<body>
  <div class="container">
    <h3 class="mb-4 text-primary">
      Applicants with <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $flag))) ?></strong> Status
    </h3>
<div class="d-flex justify-content-end mb-3">
  <button onclick="window.print()" class="btn btn-outline-primary">
    🖨️ Print or Save as PDF
  </button>
</div>

    <?php if (empty($students)): ?>
      <div class="alert alert-warning">No applicants found for this flag.</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped table-bordered" id="applicantTable">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Destination</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $i => $s): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= htmlspecialchars(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')) ?></td>
              <td><?= htmlspecialchars($s['email'] ?? '-') ?></td>
              <td><?= htmlspecialchars($s['phone_number'] ?? '-') ?></td>
              <td><?= htmlspecialchars($s['destination'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script>
    $(document).ready(function () {
      $('#applicantTable').DataTable({
        pageLength: 10,
        order: [[0, 'asc']],
        language: {
          search: "🔍 Search:",
          lengthMenu: "Show _MENU_ entries",
          info: "Showing _START_ to _END_ of _TOTAL_ applicants",
          paginate: {
            next: "Next",
            previous: "Prev"
          },
          emptyTable: "No data available"
        }
      });
    });
  </script>
  <footer></footer>

</body>
</html>
