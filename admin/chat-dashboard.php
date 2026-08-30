<?php
require_once __DIR__ . '/../db.php';
session_start();

// ================= DELETE SESSION =================
if (isset($_POST['delete_session']) && isset($_POST['session_id'])) {
    $session_id = $_POST['session_id'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete messages first (foreign key constraint)
        $stmt = $conn->prepare("DELETE FROM chat_messages WHERE session_id = ?");
        $stmt->bind_param('s', $session_id);
        $stmt->execute();
        $stmt->close();
        
        // Delete session
        $stmt = $conn->prepare("DELETE FROM chat_sessions WHERE session_id = ?");
        $stmt->bind_param('s', $session_id);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        // Redirect to clear POST data and active session
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to delete chat: " . $e->getMessage();
    }
}

// ================= SET CHAT MODE =================
if (isset($_POST['set_mode']) && isset($_POST['session']) && isset($_POST['mode'])) {
    $session_id = $_POST['session'];
    $mode = $_POST['mode'];
    
    $stmt = $conn->prepare("UPDATE chat_sessions SET mode = ?, updated_at = NOW() WHERE session_id = ?");
    $stmt->bind_param('ss', $mode, $session_id);
    $stmt->execute();
    $stmt->close();
    
    // Reload to show updated mode
    header('Location: ?session=' . urlencode($session_id));
    exit();
}

// ================= SEND MESSAGE =================
if (isset($_POST['send_message']) && isset($_POST['session']) && isset($_POST['message'])) {
    $session_id = $_POST['session'];
    $message = $_POST['message'];
    $sender = 'agent'; // Since only agents can send in human mode
    
    $stmt = $conn->prepare("INSERT INTO chat_messages (session_id, sender, message) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $session_id, $sender, $message);
    $stmt->execute();
    $stmt->close();
    
    // Update session timestamp
    $stmt = $conn->prepare("UPDATE chat_sessions SET updated_at = NOW() WHERE session_id = ?");
    $stmt->bind_param('s', $session_id);
    $stmt->execute();
    $stmt->close();
    
    // Reload messages
    header('Location: ?session=' . urlencode($session_id));
    exit();
}

// ================= FETCH ALL SESSIONS =================
$sessions = [];
$result = $conn->query("
  SELECT session_id, mode, status, updated_at
  FROM chat_sessions
  WHERE status = 'open'
  ORDER BY updated_at DESC
");

while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}

// ================= ACTIVE SESSION =================
$activeSession = $_GET['session'] ?? '';
$messages = [];
$activeMode = null;

if ($activeSession) {
    // Fetch messages
    $stmt = $conn->prepare("
        SELECT sender, message, created_at
        FROM chat_messages
        WHERE session_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->bind_param('s', $activeSession);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch mode
    $stmt = $conn->prepare("
        SELECT mode FROM chat_sessions WHERE session_id = ?
    ");
    $stmt->bind_param('s', $activeSession);
    $stmt->execute();
    $stmt->bind_result($activeMode);
    $stmt->fetch();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Live Chat Dashboard - ScholarSync Global Visa</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    /* ScholarSync brand (logo: forest green #427431, maple red #E21D1E, royal blue #3661B9) */
    --xander-primary: #427431;
    --xander-secondary: #3661B9;
    --xander-accent: #E21D1E;
    --xander-light: #f4faf6;
    --xander-dark: #2f5a26;
    --xander-success: #22C55E;
    --xander-danger: #EF4444;
    --xander-warning: #F59E0B;
    --xander-info: #3B82F6;
    --xander-gray-100: #F8FAFC;
    --xander-gray-200: #E2E8F0;
    --xander-gray-300: #CBD5E1;
    --xander-gray-400: #94A3B8;
    --xander-gray-500: #64748B;
    --xander-gray-600: #475569;
    --xander-gray-700: #334155;
    --xander-gray-800: #1E293B;
    --xander-shadow: 0 4px 20px rgba(42, 92, 170, 0.08);
    --xander-radius: 12px;
    --xander-radius-sm: 8px;
    --xander-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--xander-light);
    color: var(--xander-gray-800);
    line-height: 1.6;
    min-height: 100vh;
    padding: 0;
}

/* ===== MAIN CONTAINER ===== */
.chat-dashboard {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 0;
    height: 100vh;
    max-width: 100%;
    overflow: hidden;
}

/* ===== CHAT LIST PANEL ===== */
.chat-list-panel {
    background: white;
    border-right: 1px solid var(--xander-gray-200);
    display: flex;
    flex-direction: column;
    height: 100vh;
    overflow: hidden;
}

/* ===== CHAT LIST HEADER - CLEANED ===== */
.chat-list-header {
    padding: 24px;
    background: white;
    border-bottom: 1px solid var(--xander-gray-200);
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.chat-list-header h1 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--xander-primary);
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-subtitle {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.chat-count {
    background: var(--xander-primary);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* ===== CHAT LIST ===== */
.chat-list-container {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.chat-item {
    padding: 18px 24px;
    border-bottom: 1px solid var(--xander-gray-200);
    cursor: pointer;
    transition: var(--xander-transition);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

.chat-item:hover {
    background: var(--xander-gray-100);
}

.chat-item.active {
    background: linear-gradient(90deg, rgba(42, 92, 170, 0.1) 0%, rgba(58, 123, 218, 0.05) 100%);
    border-left: 4px solid var(--xander-accent);
}

.chat-item.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--xander-accent);
}

.chat-info {
    flex: 1;
    min-width: 0;
}

.chat-id {
    font-weight: 600;
    color: var(--xander-gray-800);
    margin-bottom: 8px;
    font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.85rem;
}

.chat-mode {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: var(--xander-radius-sm);
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.chat-mode.ai {
    background: rgba(34, 197, 94, 0.1);
    color: var(--xander-success);
    border: 1px solid rgba(34, 197, 94, 0.2);
}

.chat-mode.human {
    background: rgba(239, 68, 68, 0.1);
    color: var(--xander-danger);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.chat-time {
    color: var(--xander-gray-500);
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 4px;
}

.chat-actions {
    opacity: 0;
    transition: var(--xander-transition);
}

.chat-item:hover .chat-actions {
    opacity: 1;
}

.delete-btn {
    background: var(--xander-gray-100);
    color: var(--xander-gray-500);
    border: 1px solid var(--xander-gray-300);
    width: 32px;
    height: 32px;
    border-radius: var(--xander-radius-sm);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--xander-transition);
}

.delete-btn:hover {
    background: var(--xander-danger);
    color: white;
    border-color: var(--xander-danger);
    transform: scale(1.05);
}

.empty-state {
    padding: 60px 24px;
    text-align: center;
    color: var(--xander-gray-400);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state p {
    font-size: 1rem;
    color: var(--xander-gray-500);
}

/* ===== CHAT VIEW PANEL ===== */
.chat-view-panel {
    display: flex;
    flex-direction: column;
    height: 100vh;
    overflow: hidden;
}

/* ===== CHAT HEADER - SIMPLIFIED ===== */
.chat-header {
    padding: 20px 24px;
    background: white;
    border-bottom: 1px solid var(--xander-gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 80px;
}

.session-info {
    flex: 1;
    min-width: 0;
}

.session-id {
    font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
    color: var(--xander-gray-500);
    font-size: 0.85rem;
    word-break: break-all;
    background: var(--xander-gray-100);
    padding: 6px 10px;
    border-radius: var(--xander-radius-sm);
    display: inline-block;
    margin-top: 4px;
}

/* ===== MODE CONTROLS - IMPROVED ===== */
.mode-controls {
    display: flex;
    align-items: center;
    gap: 16px;
}

.mode-switch {
    display: flex;
    background: var(--xander-gray-100);
    padding: 4px;
    border-radius: var(--xander-radius-sm);
    border: 1px solid var(--xander-gray-200);
}

.mode-btn {
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: var(--xander-transition);
    background: transparent;
    color: var(--xander-gray-600);
    font-size: 0.9rem;
}

.mode-btn:hover {
    color: var(--xander-primary);
}

.mode-btn.active {
    background: white;
    color: var(--xander-primary);
    box-shadow: var(--xander-shadow);
}

.mode-status {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: var(--xander-radius-sm);
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: var(--xander-gray-100);
    color: var(--xander-gray-600);
}

.mode-status.ai {
    background: rgba(34, 197, 94, 0.1);
    color: var(--xander-success);
}

.mode-status.human {
    background: rgba(239, 68, 68, 0.1);
    color: var(--xander-danger);
}

/* ===== MESSAGES ===== */
.messages-container {
    flex: 1;
    padding: 24px;
    overflow-y: auto;
    background: var(--xander-gray-100);
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.message {
    max-width: 75%;
    padding: 16px 20px;
    border-radius: var(--xander-radius);
    position: relative;
    word-wrap: break-word;
    animation: messageSlide 0.3s ease-out;
}

@keyframes messageSlide {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message.user {
    background: white;
    align-self: flex-start;
    border: 1px solid var(--xander-gray-200);
    border-top-left-radius: 4px;
}

.message.ai {
    background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
    color: white;
    align-self: flex-end;
    border-top-right-radius: 4px;
}

.message.agent {
    background: linear-gradient(135deg, var(--xander-accent) 0%, #E86B1F 100%);
    color: white;
    align-self: flex-end;
    border-top-right-radius: 4px;
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.sender {
    font-weight: 700;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.sender.user {
    color: var(--xander-gray-600);
}

.sender.ai, .sender.agent {
    color: rgba(255, 255, 255, 0.9);
}

.message-time {
    font-size: 0.75rem;
    opacity: 0.8;
}

.message.user .message-time {
    color: var(--xander-gray-500);
}

.message.ai .message-time,
.message.agent .message-time {
    color: rgba(255, 255, 255, 0.8);
}

.message-content {
    line-height: 1.6;
    font-size: 0.95rem;
}

/* ===== REPLY AREA ===== */
.reply-container {
    padding: 20px 24px;
    background: white;
    border-top: 1px solid var(--xander-gray-200);
}

.reply-form {
    display: flex;
    gap: 12px;
    align-items: flex-end;
}

.reply-form textarea {
    flex: 1;
    padding: 14px 16px;
    border: 1px solid var(--xander-gray-300);
    border-radius: var(--xander-radius);
    font-family: inherit;
    font-size: 0.95rem;
    resize: none;
    min-height: 60px;
    max-height: 120px;
    transition: var(--xander-transition);
    background: var(--xander-gray-100);
    line-height: 1.5;
}

.reply-form textarea:focus {
    outline: none;
    border-color: var(--xander-primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(42, 92, 170, 0.1);
}

.reply-form button {
    background: linear-gradient(135deg, var(--xander-primary) 0%, var(--xander-secondary) 100%);
    color: white;
    border: none;
    padding: 14px 28px;
    border-radius: var(--xander-radius);
    font-weight: 600;
    cursor: pointer;
    transition: var(--xander-transition);
    display: flex;
    align-items: center;
    gap: 8px;
    height: 60px;
}

.reply-form button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(42, 92, 170, 0.2);
}

.reply-form button:disabled {
    background: var(--xander-gray-300);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.ai-mode-notice {
    background: linear-gradient(135deg, #22C55E 0%, #16A34A 100%);
    color: white;
    padding: 16px 24px;
    margin: 0 24px 24px;
    border-radius: var(--xander-radius);
    text-align: center;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.9; }
}

.ai-mode-notice span {
    background: rgba(255, 255, 255, 0.2);
    padding: 4px 12px;
    border-radius: 20px;
    cursor: pointer;
    transition: var(--xander-transition);
}

.ai-mode-notice span:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* ===== NO CHAT SELECTED ===== */
.no-chat-selected {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    text-align: center;
    color: var(--xander-gray-400);
}

.no-chat-selected i {
    font-size: 4rem;
    margin-bottom: 24px;
    color: var(--xander-gray-300);
}

.no-chat-selected h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--xander-gray-600);
    margin-bottom: 12px;
}

.no-chat-selected p {
    color: var(--xander-gray-500);
    max-width: 400px;
    line-height: 1.6;
}

/* ===== DELETE MODAL ===== */
.delete-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    padding: 32px;
    border-radius: var(--xander-radius);
    width: 90%;
    max-width: 480px;
    box-shadow: var(--xander-shadow);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.modal-header i {
    color: var(--xander-warning);
    font-size: 1.5rem;
}

.modal-header h3 {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--xander-gray-800);
}

.modal-content p {
    color: var(--xander-gray-600);
    margin-bottom: 28px;
    line-height: 1.6;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.modal-btn {
    padding: 10px 24px;
    border-radius: var(--xander-radius-sm);
    font-weight: 600;
    cursor: pointer;
    transition: var(--xander-transition);
    border: none;
    font-size: 0.95rem;
}

.cancel-btn {
    background: var(--xander-gray-200);
    color: var(--xander-gray-700);
}

.cancel-btn:hover {
    background: var(--xander-gray-300);
}

.confirm-delete-btn {
    background: var(--xander-danger);
    color: white;
}

.confirm-delete-btn:hover {
    background: #dc2626;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1200px) {
    .chat-dashboard {
        grid-template-columns: 320px 1fr;
    }
}

@media (max-width: 768px) {
    .chat-dashboard {
        grid-template-columns: 1fr;
        height: auto;
    }
    
    .chat-list-panel {
        height: auto;
        max-height: 50vh;
    }
    
    .chat-view-panel {
        height: auto;
        min-height: 50vh;
    }
    
    .chat-header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
    
    .mode-controls {
        width: 100%;
        justify-content: space-between;
    }
    
    .message {
        max-width: 90%;
    }
}
</style>
</head>

<body>

<!-- Delete Confirmation Modal -->
<div class="delete-modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Delete Chat Session</h3>
        </div>
        <p>Are you sure you want to delete this chat session? This action cannot be undone. All messages in this session will be permanently deleted.</p>
        <div class="modal-actions">
            <button class="modal-btn cancel-btn" id="cancelDelete">Cancel</button>
            <form method="post" style="display: inline;">
                <input type="hidden" name="session_id" id="deleteSessionId">
                <button type="submit" name="delete_session" class="modal-btn confirm-delete-btn">Delete Permanently</button>
            </form>
        </div>
    </div>
</div>

<div class="chat-dashboard">
    <!-- Chat List Panel -->
    <div class="chat-list-panel">
        <div class="chat-list-header">
            <h1><i class="fas fa-comments"></i> Live Chat Dashboard</h1>
            <div class="header-subtitle">
                <div class="chat-count">
                    <i class="fas fa-bolt"></i>
                    <?php echo count($sessions); ?> Active
                </div>
            </div>
        </div>
        
        <div class="chat-list-container">
            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No active chat sessions</p>
                </div>
            <?php else: ?>
                <?php foreach ($sessions as $s): ?>
                    <div class="chat-item <?= $activeSession === $s['session_id'] ? 'active' : '' ?>" 
                         onclick="window.location.href='?session=<?= htmlspecialchars($s['session_id']) ?>'">
                        <div class="chat-info">
                            <div class="chat-id"><?= htmlspecialchars(substr($s['session_id'], 0, 20)) ?>…</div>
                            <div class="chat-meta">
                                <span class="chat-mode <?= $s['mode'] ?>">
                                    <i class="fas fa-<?= $s['mode'] === 'ai' ? 'robot' : 'user-headset' ?>"></i>
                                    <?= strtoupper($s['mode']) ?>
                                </span>
                                <span class="chat-time">
                                    <i class="far fa-clock"></i> <?= date('H:i', strtotime($s['updated_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="chat-actions">
                            <button type="button" class="delete-btn" onclick="event.stopPropagation(); showDeleteModal('<?= htmlspecialchars($s['session_id']) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Chat View Panel -->
    <div class="chat-view-panel">
        <?php if ($activeSession): ?>
            <!-- Chat Header -->
            <div class="chat-header">
                <div class="session-info">
                    <div class="session-id"><?= htmlspecialchars($activeSession) ?></div>
                </div>
                
                <div class="mode-controls">
                    <div class="mode-switch">
                        <form method="post" style="display: contents;">
                            <input type="hidden" name="session" value="<?= htmlspecialchars($activeSession) ?>">
                            <input type="hidden" name="mode" value="ai">
                            <button type="submit" name="set_mode" class="mode-btn ai <?= $activeMode === 'ai' ? 'active' : '' ?>">
                                <i class="fas fa-robot"></i> AI
                            </button>
                        </form>
                        
                        <form method="post" style="display: contents;">
                            <input type="hidden" name="session" value="<?= htmlspecialchars($activeSession) ?>">
                            <input type="hidden" name="mode" value="human">
                            <button type="submit" name="set_mode" class="mode-btn human <?= $activeMode === 'human' ? 'active' : '' ?>">
                                <i class="fas fa-user-headset"></i> Agent
                            </button>
                        </form>
                    </div>
                    
                    <div class="mode-status <?= $activeMode ?>">
                        <i class="fas fa-<?= $activeMode === 'ai' ? 'robot' : 'user-headset' ?>"></i>
                        <?= strtoupper($activeMode) ?> MODE
                    </div>
                </div>
            </div>
            
            <!-- Messages -->
            <div class="messages-container" id="messagesContainer">
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-slash"></i>
                        <p>No messages in this chat yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $m): ?>
                        <div class="message <?= $m['sender'] ?>">
                            <div class="message-header">
                                <span class="sender <?= $m['sender'] ?>">
                                    <i class="fas fa-<?= $m['sender'] === 'ai' ? 'robot' : ($m['sender'] === 'agent' ? 'user-headset' : 'user') ?>"></i>
                                    <?php 
                                        $senderLabel = $m['sender'] === 'ai' ? 'AI Assistant' : 
                                                      ($m['sender'] === 'agent' ? 'Live Agent' : 'User');
                                        echo $senderLabel;
                                    ?>
                                </span>
                                <span class="message-time"><?= $m['created_at'] ?></span>
                            </div>
                            <div class="message-content">
                                <?= nl2br(htmlspecialchars($m['message'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Reply Area -->
            <?php if ($activeMode === 'human'): ?>
                <form method="post" class="reply-container">
                    <input type="hidden" name="session" value="<?= htmlspecialchars($activeSession) ?>">
                    <div class="reply-form">
                        <textarea name="message" placeholder="Type your reply as a live agent…" required></textarea>
                        <button type="submit" name="send_message">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="ai-mode-notice">
                    <i class="fas fa-robot"></i>
                    AI mode is enabled — switch to <span onclick="document.querySelector('.mode-btn.human').click()">Live Agent</span> to reply manually
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- No Chat Selected -->
            <div class="no-chat-selected">
                <i class="far fa-comments"></i>
                <h3>Select a chat to begin</h3>
                <p>Choose a chat session from the list to view and respond to messages</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Delete modal functionality
const deleteModal = document.getElementById('deleteModal');
const deleteSessionIdInput = document.getElementById('deleteSessionId');
const cancelDeleteBtn = document.getElementById('cancelDelete');

function showDeleteModal(sessionId) {
    deleteSessionIdInput.value = sessionId;
    deleteModal.style.display = 'flex';
}

function hideDeleteModal() {
    deleteModal.style.display = 'none';
}

// Close modal when clicking cancel or outside modal
cancelDeleteBtn.addEventListener('click', hideDeleteModal);
deleteModal.addEventListener('click', function(e) {
    if (e.target === deleteModal) {
        hideDeleteModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideDeleteModal();
    }
});

// Auto-focus textarea when in human mode
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.querySelector('textarea[name="message"]');
    if (textarea) {
        textarea.focus();
        
        // Auto-resize textarea
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }
    
    // Auto-scroll to bottom of messages
    const messagesContainer = document.getElementById('messagesContainer');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
        // Smooth scroll for new messages
        const observer = new MutationObserver(function() {
            messagesContainer.scrollTo({
                top: messagesContainer.scrollHeight,
                behavior: 'smooth'
            });
        });
        
        observer.observe(messagesContainer, { childList: true, subtree: true });
    }
    
    // Add animation to active chat items
    document.querySelectorAll('.chat-item.active').forEach(item => {
        item.style.animation = 'messageSlide 0.3s ease-out';
    });
});

// Real-time updates simulation
setInterval(function() {
    if (window.location.search.includes('session=')) {
        // Check for new messages by reloading the page
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newMessages = doc.querySelectorAll('.message');
                const currentMessages = document.querySelectorAll('.message');
                
                if (newMessages.length > currentMessages.length) {
                    // If new messages detected, reload the page
                    window.location.reload();
                }
            });
    }
}, 5000); // Check every 5 seconds
</script>

</body>
</html>