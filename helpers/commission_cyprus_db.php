<?php
declare(strict_types=1);

/**
 * Optional second DB (Cyprus applications). Never dies — returns null if unavailable.
 */
function pcvc_commission_cyprus_mysqli(): ?mysqli
{
    static $cached = false;
    static $conn = null;
    if ($cached) {
        return $conn;
    }
    $cached = true;

    $host = getenv('CYPRUS_DB_HOST') ?: 'localhost';
    $db   = getenv('CYPRUS_DB_NAME') ?: 'scholarsyncglobal';
    $user = getenv('CYPRUS_DB_USER') ?: 'root';
    $pass = getenv('CYPRUS_DB_PASS') !== false ? (string) getenv('CYPRUS_DB_PASS') : '';

    $mysqli = mysqli_init();
    if (!$mysqli) {
        $conn = null;
        return null;
    }
    $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
    if (!@$mysqli->real_connect($host, $user, $pass, $db)) {
        $conn = null;
        return null;
    }
    if ($mysqli->connect_error) {
        error_log('[commission] Cyprus DB unavailable: ' . $mysqli->connect_error);
        $conn = null;
        return null;
    }
    $mysqli->set_charset('utf8mb4');
    $conn = $mysqli;
    return $conn;
}
