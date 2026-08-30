<?php
require 'db.php'; // must define $conn (mysqli)

header('Content-Type: application/json');

try {

  if (!$conn || $conn->connect_error) {
    throw new Exception("Database connection failed.");
  }

  $sql = "
    SELECT id, name
    FROM countries
    ORDER BY name ASC
  ";

  $result = $conn->query($sql);

  if (!$result) {
    throw new Exception($conn->error);
  }

  $countries = [];

  while ($row = $result->fetch_assoc()) {
    $countries[] = [
      "id"   => (int)$row['id'],
      "name" => $row['name']
    ];
  }

  echo json_encode($countries);

} catch (Exception $e) {

  http_response_code(500);

  echo json_encode([
    "error" => true,
    "message" => $e->getMessage()
  ]);
}
