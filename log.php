<?php
// log.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

$data = json_decode(file_get_contents("php://input"), true);
$time = date("Y-m-d H:i:s");

$line = sprintf(
    "[%s] %s | %s\n",
    $time,
    $data['source'] ?? 'unknown',
    json_encode($data)
);

file_put_contents($logDir . "/attendance.log", $line, FILE_APPEND);
