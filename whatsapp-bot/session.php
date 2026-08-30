<?php

require_once "database.php";

/* =========================================================
   ENTERPRISE SESSION MANAGER
   Secure | SQL Injection Safe | Scalable | Production Ready
========================================================= */

define('SESSION_TIMEOUT_SECONDS', 86400); // 24 hours

/* =========================================================
   CHECK IF SESSION IS OPEN
========================================================= */

function isSessionOpen($phone)
{
    global $conn;

    if (empty($phone)) {
        return false;
    }

    try {

        $stmt = $conn->prepare(
            "SELECT last_message_time 
             FROM sessions 
             WHERE phone = ? 
             LIMIT 1"
        );

        if (!$stmt) {
            logSessionError("PREPARE_FAILED", $conn->error, $phone);
            return false;
        }

        $stmt->bind_param("s", $phone);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return false;
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        $lastTime = strtotime($row['last_message_time']);

        if (!$lastTime) {
            return false;
        }

        return (time() - $lastTime) < SESSION_TIMEOUT_SECONDS;

    } catch (Exception $e) {

        logSessionError("SESSION_CHECK_EXCEPTION", $e->getMessage(), $phone);
        return false;
    }
}

/* =========================================================
   CREATE OR UPDATE SESSION
========================================================= */

function updateSession($phone)
{
    global $conn;

    if (empty($phone)) {
        return false;
    }

    $now = date("Y-m-d H:i:s");

    try {

        $stmt = $conn->prepare(
            "INSERT INTO sessions (phone, last_message_time)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE last_message_time = VALUES(last_message_time)"
        );

        if (!$stmt) {
            logSessionError("PREPARE_FAILED", $conn->error, $phone);
            return false;
        }

        $stmt->bind_param("ss", $phone, $now);
        $stmt->execute();
        $stmt->close();

        return true;

    } catch (Exception $e) {

        logSessionError("SESSION_UPDATE_EXCEPTION", $e->getMessage(), $phone);
        return false;
    }
}

/* =========================================================
   OPTIONAL: FORCE CLOSE SESSION
========================================================= */

function closeSession($phone)
{
    global $conn;

    if (empty($phone)) {
        return false;
    }

    try {

        $stmt = $conn->prepare(
            "DELETE FROM sessions WHERE phone = ?"
        );

        if (!$stmt) {
            logSessionError("PREPARE_FAILED", $conn->error, $phone);
            return false;
        }

        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->close();

        return true;

    } catch (Exception $e) {

        logSessionError("SESSION_CLOSE_EXCEPTION", $e->getMessage(), $phone);
        return false;
    }
}

/* =========================================================
   CLEAN EXPIRED SESSIONS (OPTIONAL CRON JOB)
========================================================= */

function cleanExpiredSessions()
{
    global $conn;

    $expiryTime = date("Y-m-d H:i:s", time() - SESSION_TIMEOUT_SECONDS);

    try {

        $stmt = $conn->prepare(
            "DELETE FROM sessions WHERE last_message_time < ?"
        );

        if (!$stmt) {
            logSessionError("PREPARE_FAILED", $conn->error);
            return false;
        }

        $stmt->bind_param("s", $expiryTime);
        $stmt->execute();
        $stmt->close();

        return true;

    } catch (Exception $e) {

        logSessionError("SESSION_CLEAN_EXCEPTION", $e->getMessage());
        return false;
    }
}

/* =========================================================
   SESSION ERROR LOGGER
========================================================= */

function logSessionError($type, $message, $phone = null)
{
    $logFile = __DIR__ . "/session_error_log.txt";
    $time = date("Y-m-d H:i:s");

    $entry = "[$time] TYPE: $type";

    if ($phone) {
        $entry .= " | USER: $phone";
    }

    $entry .= " | MESSAGE: " . substr($message, 0, 1000) . PHP_EOL;

    file_put_contents($logFile, $entry, FILE_APPEND);
}