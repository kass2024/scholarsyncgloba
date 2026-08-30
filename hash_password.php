<?php
// hash_password.php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = $_POST['password'];
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    echo "<strong>Plain Password:</strong> " . htmlspecialchars($password) . "<br>";
    echo "<strong>Hashed Password:</strong> <code>" . htmlspecialchars($hashed) . "</code>";
} else {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Password Hash Generator</title>
</head>
<body>
  <h2>Generate Bcrypt Hashed Password</h2>
  <form method="post">
    <label>Enter Password:</label><br>
    <input type="text" name="password" required>
    <br><br>
    <button type="submit">Generate Hash</button>
  </form>
</body>
</html>
<?php } ?>
