<?php
/**
 * SET CHAT MODE – SCHOLARSYNC GLOBAL
 * Switch between AI and Live Agent
 */
require_once __DIR__ . '/../db.php';
// ================= SECURITY =================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: chat-dashboard.php');
    exit;
}

$session = $_POST['session'] ?? '';
$mode    = $_POST['mode'] ?? '';

if (!$session || !in_array($mode, ['ai', 'human'], true)) {
    header('Location: chat-dashboard.php');
    exit;
}

// ================= UPDATE MODE =================
$stmt = $conn->prepare("
    UPDATE chat_sessions
    SET mode = ?, updated_at = NOW()
    WHERE session_id = ?
");

if (!$stmt) {
    die('DB Error: ' . $conn->error);
}

$stmt->bind_param('ss', $mode, $session);
$stmt->execute();
$stmt->close();

// ================= REDIRECT BACK =================
header('Location: chat-dashboard.php?session=' . urlencode($session));
exit;
