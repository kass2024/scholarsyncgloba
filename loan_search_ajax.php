<?php
require_once __DIR__ . '/db.php';
$q = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';

if ($q !== '') {
    $sql = "SELECT user_id, first_name, last_name, email 
            FROM master_loan_applications 
            WHERE user_id LIKE '%$q%' 
               OR first_name LIKE '%$q%' 
               OR last_name LIKE '%$q%' 
               OR email LIKE '%$q%' 
            LIMIT 30";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        echo "<div class='table-responsive'>
              <table class='table table-bordered table-striped'>
                <thead class='table-light'>
                  <tr>
                    <th>User ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Email</th>
                  </tr>
                </thead>
                <tbody>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>" . htmlspecialchars($row['user_id']) . "</td>
                    <td>" . htmlspecialchars($row['first_name']) . "</td>
                    <td>" . htmlspecialchars($row['last_name']) . "</td>
                    <td>" . htmlspecialchars($row['email']) . "</td>
                  </tr>";
        }
        echo "</tbody></table></div>";
    } else {
        echo "<div class='alert alert-warning'>No results found for <strong>" . htmlspecialchars($q) . "</strong>.</div>";
    }
}
$conn->close();
?>
