<?php
declare(strict_types=1);

/**
 * ScholarSync Global database connection.
 *
 * All credentials are environment-controlled so this installation never
 * falls back to another application's database.
 */
require_once __DIR__ . '/helpers/env_load.php';
xander_load_env_file();

$host = xander_env_get('DB_HOST') ?: '127.0.0.1';
$user = xander_env_get('DB_USER') ?: 'root';
$pass = xander_env_get('DB_PASS');
$dbname = xander_env_get('DB_NAME') ?: 'scholarsyncglobal';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    if (defined('PCVC_CHAT_API') && PCVC_CHAT_API) {
        throw new RuntimeException('Database connection failed.');
    }
    die('Connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+00:00'");
