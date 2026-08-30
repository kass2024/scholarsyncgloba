<?php
require_once __DIR__ . '/db.php';
$q = $_POST['query'] ?? '';

$stmt = $conn->prepare("SELECT full_name FROM admins WHERE full_name LIKE ? LIMIT 10");
$like = "%$q%";
$stmt->bind_param("s", $like);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<div>No staff found</div>";
    exit;
}

while ($row = $res->fetch_assoc()) {
    echo "<div>" . htmlspecialchars($row['full_name']) . "</div>";
}
