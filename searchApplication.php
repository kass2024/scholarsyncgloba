<?php
require __DIR__.'/db.php';

$q=$_GET['q'] ?? '';

if(strlen($q)<3){
echo json_encode([]);
exit;
}

$stmt=$conn->prepare("
SELECT id,email
FROM student_applications
WHERE email LIKE CONCAT('%', ?, '%')
LIMIT 10
");

$stmt->bind_param("s",$q);
$stmt->execute();

$result=$stmt->get_result();

$data=[];

while($row=$result->fetch_assoc()){
$data[]=$row;
}

header('Content-Type: application/json');

echo json_encode($data);