<?php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['admin_id'])) {
    die("Access denied.");
}

$admin_id = $_SESSION['admin_id'];
$admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM admins WHERE id = '$admin_id'"));
$admin_username = $admin['username'];
$admin_lastname = $admin['last_name'] ?: $admin['username'];

$selected_user_id = $_GET['user_id'] ?? null;
$admin_is_active = 0;

// Mark messages as read when thread clicked
if ($selected_user_id) {
    mysqli_query($conn, "
        UPDATE chat_messages 
        SET is_read_by_admin = 1 
        WHERE user_id = '" . mysqli_real_escape_string($conn, $selected_user_id) . "'
    ");

    $rowActive = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT admin_is_active 
        FROM chat_conversations 
        WHERE user_id = '" . mysqli_real_escape_string($conn, $selected_user_id) . "'
    "));
    if ($rowActive) {
        $admin_is_active = (int)$rowActive['admin_is_active'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Chat Panel (<?= htmlspecialchars($admin_lastname) ?>)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body { background: #f5f7fb; padding: 20px; font-family: 'Segoe UI', sans-serif; }
        .chat-panel { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 20px; max-width: 1400px; margin: auto; }
        .chat-sidebar { background: #f9fafb; border-radius: 10px; padding: 10px; height: 700px; overflow-y: auto; }
        .chat-thread { display: flex; justify-content: space-between; align-items: center; padding: 10px; margin-bottom: 5px; border-radius: 8px; background: #fff; border: 1px solid #ddd; cursor: pointer; transition: background 0.2s; }
        .chat-thread:hover { background: #f0f8ff; }
        .badge-unread { background: red; color: white; border-radius: 10px; padding: 2px 6px; font-size: 12px; margin-left: 6px; }
        .chat-box { background: #fdfdfd; border: 1px solid #e1e1e1; border-radius: 10px; padding: 15px; height: 550px; overflow-y: auto; }
        .chat-message { margin-bottom: 10px; }
        .chat-message.admin .msg-content { background: #d1eaff; color: #0c3c78; padding: 10px 15px; border-radius: 12px; display: inline-block; max-width: 75%; margin-left: auto; }
        .chat-message.ai .msg-content { background: #f0f0f0; color: #333; padding: 10px 15px; border-radius: 12px; display: inline-block; max-width: 75%; }
        .chat-message.student .msg-content { background: #d1f0d1; color: #222; padding: 10px 15px; border-radius: 12px; display: inline-block; max-width: 75%; }
        .send-box { margin-top: 10px; display: flex; gap: 10px; }
        .send-box textarea { flex: 1; resize: none; }
        .sound-btn { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }
    </style>
</head>
<body>

<div class="chat-panel">
    <h3 class="mb-4">📢 Admin Chat Panel (<?= htmlspecialchars($admin_lastname) ?>)</h3>

    <div class="row g-4">
        <div class="col-md-3">
            <div class="chat-sidebar" id="chat-threads">
                <!-- Threads will load here -->
            </div>
        </div>

        <div class="col-md-9">
            <?php if ($selected_user_id): ?>
                <?php
                $userInfo = mysqli_fetch_assoc(mysqli_query($conn, "
                    SELECT email, phone_number 
                    FROM student_chat_users 
                    WHERE user_id = '" . mysqli_real_escape_string($conn, $selected_user_id) . "'
                "));
                $chatUserInfo = ($userInfo) 
                    ? htmlspecialchars($userInfo['email'] . ' | ' . $userInfo['phone_number']) 
                    : "No contact info";
                ?>
                <div class="d-flex justify-content-between mb-2">
                    <h5>
                        Chat with user <span class="text-primary fw-bold"><?= htmlspecialchars($selected_user_id) ?></span>
                        (<?= $chatUserInfo ?>)
                    </h5>
                    <div>
                        <button id="mark-closed" class="btn btn-warning btn-sm me-2">Mark Closed</button>
                        <a href="admin_chat.php" class="btn btn-secondary btn-sm">← Back</a>
                    </div>
                </div>

                <div class="mb-2">
                    <input type="checkbox" id="admin_active_toggle" <?= $admin_is_active ? 'checked' : '' ?>>
                    <span id="admin_status_label" class="ms-2"><?= $admin_is_active ? '🟢 Online' : '🔴 Offline' ?></span>
                </div>

                <div class="chat-box mb-3" id="chat-box"></div>

                <div class="send-box">
                    <textarea id="admin-reply" class="form-control" placeholder="Type your reply..." rows="2"></textarea>
                    <button class="btn btn-primary" onclick="sendAdminReply('<?= htmlspecialchars($selected_user_id) ?>', '<?= htmlspecialchars($admin_username) ?>')">Send</button>
                </div>
            <?php else: ?>
                <p>Select a chat thread to view messages.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<audio id="notify-sound" src="notify.mp3" preload="auto"></audio>
<button class="btn btn-info sound-btn" id="sound-toggle">🔔 Sound On</button>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let soundEnabled = true;
let lastLoadedId = 0;
let firstLoad = true;

function loadThreads() {
    $.get('load_threads_admin.php', function(html) {
        $('#chat-threads').html(html);
    });
}

function loadChat(userId) {
    $.get('load_chat_admin.php', { user_id: userId }, function(response) {
        if (response && response.messages) {
            let html = '';
            response.messages.forEach(msg => {
                html += msg.html;
            });
            $('#chat-box').html(html);
            $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);

            const newMessages = response.messages.filter(msg => msg.id > lastLoadedId);
            if (newMessages.length > 0) {
                lastLoadedId = newMessages[newMessages.length - 1].id;

                if (!firstLoad) {
                    const shouldPlaySound = newMessages.some(msg =>
                        msg.sender === 'student'
                    );

                    if (soundEnabled && shouldPlaySound) {
                        const notifySound = document.getElementById('notify-sound');
                        notifySound.play().catch(err => {
                            console.warn('Sound blocked:', err);
                        });
                    }
                }
            }
            firstLoad = false;
        }
    }, 'json');
}

function sendAdminReply(userId, senderUsername) {
    const message = $('#admin-reply').val().trim();
    if (!message) return;

    $.post('send_chat.php', {
        user_id: userId,
        message: message,
        sender: senderUsername
    }, function(resp) {
        $('#admin-reply').val('');
        loadChat(userId);
        loadThreads(); // update unread
    }, 'json');
}

<?php if ($selected_user_id): ?>
$(document).ready(function() {
    loadChat('<?= htmlspecialchars($selected_user_id) ?>');
    setInterval(function() {
        loadChat('<?= htmlspecialchars($selected_user_id) ?>');
    }, 5000);

    $('#admin-reply').keypress(function(e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            sendAdminReply('<?= htmlspecialchars($selected_user_id) ?>', '<?= htmlspecialchars($admin_username) ?>');
        }
    });

    $('#admin_active_toggle').on('change', function() {
        const active = this.checked ? 1 : 0;
        $.post('update_admin_status.php', {
            user_id: '<?= htmlspecialchars($selected_user_id) ?>',
            active: active
        }, function(resp) {
            const label = active ? '🟢 Online' : '🔴 Offline';
            $('#admin_status_label').text(label);
        });
    });

    $('#mark-closed').on('click', function() {
        $.post('mark_closed.php', {
            user_id: '<?= htmlspecialchars($selected_user_id) ?>'
        }, function(resp) {
            alert(resp);
            window.location.href = 'admin_chat.php';
        });
    });
});
<?php endif; ?>

$(document).ready(function() {
    loadThreads();
    setInterval(loadThreads, 5000);

    $('#sound-toggle').click(function() {
        soundEnabled = !soundEnabled;
        $(this).text(soundEnabled ? '🔔 Sound On' : '🔕 Sound Off');
    });
});
</script>

</body>
</html>
