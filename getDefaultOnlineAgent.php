<?php
/**
 * Default consultant for "Online / Website / Social Media" applications.
 * Prefers the superadmin account "Xander" (username or name/email match).
 */
require __DIR__ . '/db.php';
header('Content-Type: application/json');

/* Prefer superadmin Xander */
$sqlPrefer = "
    SELECT first_name, last_name, email
    FROM admins
    WHERE role = 'superadmin'
      AND (
            LOWER(COALESCE(username, '')) = 'xander'
         OR LOWER(COALESCE(username, '')) LIKE '%xander%'
         OR LOWER(COALESCE(email, '')) LIKE '%xander%'
         OR LOWER(COALESCE(first_name, '')) LIKE '%xander%'
         OR LOWER(COALESCE(last_name, '')) LIKE '%xander%'
         OR LOWER(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))) LIKE '%xander%'
         OR LOWER(COALESCE(full_name, '')) LIKE '%xander%'
      )
    ORDER BY id ASC
    LIMIT 1
";

$res = $conn->query($sqlPrefer);
if ($res && ($row = $res->fetch_assoc())) {
    echo json_encode($row);
    exit;
}

/* Fallback: first superadmin (legacy behavior) */
$res = $conn->query("
    SELECT first_name, last_name, email
    FROM admins
    WHERE role = 'superadmin'
    ORDER BY id ASC
    LIMIT 1
");

if ($res && ($row = $res->fetch_assoc())) {
    echo json_encode($row);
} else {
    echo json_encode([]);
}
