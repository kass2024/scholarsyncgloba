<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain'); // Ensures clean text response for AJAX

try {
    // Validate required fields
    $required = ['id', 'fullname', 'email', 'phone', 'country', 'membership', 'status', 'appointment_date'];
    foreach ($required as $field) {
        if (!isset($_POST[$field])) {
            throw new Exception("Missing field: $field");
        }
    }

    $id = intval($_POST['id']);
    if ($id <= 0) {
        throw new Exception("Invalid ID");
    }

    $fullname    = trim($_POST['fullname']);
    $email       = trim($_POST['email']);
    $phone       = trim($_POST['phone']);
    $country     = trim($_POST['country']);
    $membership  = trim($_POST['membership']);
    $status      = trim($_POST['status']);
    $appointment = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;

    // Prepare update query
    $sql = "UPDATE members 
            SET fullname = ?, 
                email = ?, 
                phone = ?, 
                country = ?, 
                membership = ?, 
                status = ?, 
                appointment_date = ? 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    if (!$stmt->bind_param("sssssssi", 
        $fullname, $email, $phone, $country, $membership, $status, $appointment, $id
    )) {
        throw new Exception("Bind failed: " . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    // Check if any row was actually updated
    if ($stmt->affected_rows > 0) {
        echo "success";
    } else {
        echo "no_changes"; // record found but nothing changed
    }

    $stmt->close();

} catch (Exception $e) {
    echo "error: " . $e->getMessage();
}

$conn->close();
?>
