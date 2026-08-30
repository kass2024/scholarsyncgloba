<?php
/**
 * SEND MESSAGE – LIVE AGENT
 * Only allowed when chat mode = human
 */
require_once __DIR__ . '/../db.php';
// ================= SECURITY =================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: chat-dashboard.php');
    exit;
}

$session = $_POST['session'] ?? '';
$message = trim($_POST['message'] ?? '');

if (!$session || $message === '') {
    header('Location: chat-dashboard.php');
    exit;
}

/* ================= CHECK MODE ================= */
$stmt = $conn->prepare("
    SELECT mode
    FROM chat_sessions
    WHERE session_id = ?
");
if (!$stmt) {
    die('DB Error (mode check): ' . $conn->error);
}

$stmt->bind_param('s', $session);
$stmt->execute();
$stmt->bind_result($mode);
$stmt->fetch();
$stmt->close();

if ($mode !== 'human') {
    // Block replies in AI mode
    header('Location: chat-dashboard.php?session=' . urlencode($session));
    exit;
}

/* ================= INSERT AGENT MESSAGE ================= */
$stmt = $conn->prepare("
    INSERT INTO chat_messages (session_id, sender, message, created_at)
    VALUES (?, 'agent', ?, NOW())
");
if (!$stmt) {
    die('DB Error (insert message): ' . $conn->error);
}

$stmt->bind_param('ss', $session, $message);
$stmt->execute();
$stmt->close();

/* ================= UPDATE SESSION TIMESTAMP ================= */
$stmt = $conn->prepare("
    UPDATE chat_sessions
    SET updated_at = NOW()
    WHERE session_id = ?
");
if ($stmt) {
    $stmt->bind_param('s', $session);
    $stmt->execute();
    $stmt->close();
}

/* ================= REDIRECT BACK ================= */
header('Location: chat-dashboard.php?session=' . urlencode($session));
exit;
