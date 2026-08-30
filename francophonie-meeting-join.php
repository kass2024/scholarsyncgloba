<?php
/**
 * francophonie-meeting-join.php — Participant join in browser (no Zoom desktop app).
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/francophonie_meeting_invitation_schema.php';
require_once __DIR__ . '/helpers/francophonie_meeting_attendance.php';
require_once __DIR__ . '/helpers/zoom_meeting_sdk.php';
require_once __DIR__ . '/helpers/zoom_meeting_coop_headers.php';
require_once __DIR__ . '/helpers/fm_meeting_avatars.php';
fm_zoom_send_coop_headers();

xander_load_env_file();
fm_meeting_ensure_schema($conn);

$invitationId = (int) ($_GET['invitation_id'] ?? $_POST['invitation_id'] ?? 0);
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$guestToken = trim((string) ($_GET['guest_token'] ?? $_POST['guest_token'] ?? ''));
$isGuestRequest = isset($_GET['guest']) || isset($_POST['guest']);
$postedName = trim((string) ($_POST['display_name'] ?? ''));
$postedEmail = trim((string) ($_POST['display_email'] ?? ''));

$topic = 'Meeting';
$sdkAuth = null;
$sdkError = '';
$displayName = '';
$userEmail = '';
$gateMode = false;
$guestForm = false;
$inviteeId = 0;
$sourceType = '';
$sourceId = 0;
$participantType = 'guest';

$publicBase = fm_zoom_public_base_url();
$requestBase = fm_zoom_request_base_url();
$assetBase = $requestBase . '/assets/zoom-meetingsdk';
$meetingJs = fm_zoom_meeting_js_file();
$assetsOk = fm_zoom_sdk_assets_installed();
$leaveUrl = $requestBase . '/' . fm_meeting_participant_join_path($invitationId, $token !== '' ? $token : null) . '&left=1';

if ($invitationId > 0) {
    $st = $conn->prepare(
        'SELECT topic, zoom_meeting_number, zoom_password, start_time, guest_join_token
         FROM francophonie_mobility_meeting_invitations WHERE id = ? LIMIT 1'
    );
    $st->bind_param('i', $invitationId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if ($row) {
        $topic = (string) ($row['topic'] ?? $topic);
        $meetingNumber = (string) ($row['zoom_meeting_number'] ?? '');
        $password = (string) ($row['zoom_password'] ?? '');
        $storedGuestToken = trim((string) ($row['guest_join_token'] ?? ''));
        if ($storedGuestToken === '') {
            $storedGuestToken = fm_meeting_ensure_guest_join_token($conn, $invitationId);
        }

        if ($token !== '') {
            $ist = $conn->prepare(
                'SELECT id, source_type, source_id, recipient_name, recipient_email
                 FROM francophonie_mobility_meeting_invitees
                 WHERE invitation_id = ? AND join_token = ? LIMIT 1'
            );
            $ist->bind_param('is', $invitationId, $token);
            $ist->execute();
            $invitee = $ist->get_result()->fetch_assoc();
            $ist->close();

            if ($invitee) {
                $inviteeId = (int) $invitee['id'];
                $displayName = trim((string) ($invitee['recipient_name'] ?? ''));
                $userEmail = trim((string) ($invitee['recipient_email'] ?? ''));
                $sourceType = (string) ($invitee['source_type'] ?? '');
                $sourceId = (int) ($invitee['source_id'] ?? 0);
                $participantType = 'invitee';
            } else {
                $sdkError = 'Invalid or expired invitation link.';
            }
        } elseif ($isGuestRequest) {
            if ($guestToken === '' || !hash_equals($storedGuestToken, $guestToken)) {
                $sdkError = 'Invalid external guest link.';
            } elseif ($postedName !== '') {
                $displayName = $postedName;
                $userEmail = $postedEmail;
                $participantType = 'guest';
            } else {
                $guestForm = true;
                $gateMode = true;
            }
        } else {
            $sdkError = 'Open the personal link from your invitation email, or ask the organizer for the external guest link.';
        }

        if (!$gateMode && $sdkError === '' && $displayName === '') {
            $displayName = 'Guest';
        }

        if (!$gateMode && $sdkError === '' && $meetingNumber !== '') {
            $plBase = fm_meeting_learning_frontend_base();
            if ($plBase !== '') {
                $embedPath = fm_meeting_embed_room_path(
                    $meetingNumber,
                    0,
                    $password,
                    $displayName,
                    $userEmail !== '' ? $userEmail : null
                );
                header('Location: ' . $plBase . $embedPath);
                exit;
            }

            $sdkResult = zoom_sdk_build_join_payload(
                $meetingNumber,
                $displayName,
                0,
                $password,
                $userEmail !== '' ? $userEmail : null,
                false
            );
            if ($sdkResult['ok']) {
                $sdkAuth = $sdkResult['sdk'];
                if ($password !== '') {
                    $sdkAuth['password_candidates'] = array_values(array_unique([$password, '']));
                }
            } else {
                $sdkError = (string) ($sdkResult['message'] ?? 'SDK auth failed');
            }
        }
    } else {
        $sdkError = 'Meeting invitation not found.';
    }
} else {
    $sdkError = 'Missing invitation link.';
}

if (!$assetsOk && $sdkError === '' && !$gateMode) {
    $sdkError = 'Zoom Meeting SDK files are missing on the server.';
}
if (!zoom_sdk_is_configured() && $sdkError === '' && !$gateMode) {
    $sdkError = 'Zoom embed credentials are not configured.';
}

$zoomLibUrl = $assetBase . '/dist/lib';
$zoomCssHref = $assetBase . '/dist/ui/zoom-meetingsdk.css';
$left = isset($_GET['left']);
$attendanceMeta = [
    'invitation_id' => $invitationId,
    'invitee_id' => $inviteeId,
    'join_token' => $token,
    'participant_type' => $participantType,
    'participant_name' => $displayName,
    'participant_email' => $userEmail,
    'source_type' => $sourceType,
    'source_id' => $sourceId,
];
$avatarBranding = fm_meeting_participant_avatar_branding(
    $conn,
    $invitationId,
    $displayName,
    $userEmail,
    $publicBase
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Join meeting — <?= htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if (!$gateMode && !$left && $sdkError === ''): ?>
    <link rel="stylesheet" href="assets/css/francophonie-zoom-room.css?v=10">
    <?php endif; ?>
    <style>
        html, body { background:#1a1a1a; font-family:Arial,sans-serif; }
        .join-boot, .join-gate {
            position:fixed; inset:0; z-index:5; display:flex; flex-direction:column;
            align-items:center; justify-content:center; gap:1rem; padding:1.5rem;
            text-align:center; background:#0f172a; color:#e2e8f0;
        }
        .join-boot.hidden { opacity:0; pointer-events:none; transition:opacity .3s; }
        .join-boot .err { color:#fecaca; background:#7f1d1d55; padding:.75rem 1rem; border-radius:8px; max-width:560px; }
        .join-gate { background:linear-gradient(160deg,#0f172a,#1e293b); }
        .join-gate .card {
            background:#fff; color:#0f172a; border-radius:12px; padding:2rem; max-width:420px; width:100%;
            box-shadow:0 20px 50px rgba(0,0,0,.35);
        }
        .join-gate input {
            width:100%; padding:.65rem .85rem; border:1px solid #cbd5e1; border-radius:8px; font-size:1rem; margin-bottom:.75rem;
        }
        .join-gate label { display:block; text-align:left; font-size:.85rem; font-weight:600; margin-bottom:.35rem; }
        .join-gate button {
            width:100%; margin-top:.25rem; padding:.75rem; border:none; border-radius:8px;
            background:linear-gradient(135deg,#1e4d2b,#3661B9); color:#fff; font-weight:600; font-size:1rem; cursor:pointer;
        }
        .join-gate .hint { font-size:.85rem; color:#64748b; margin-top:.75rem; }
        .spinner {
            width:42px; height:42px; border:3px solid #334155; border-top-color:#22c55e;
            border-radius:50%; animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
    </style>
</head>
<body>
<?php if ($left): ?>
<div class="join-gate">
    <div class="card">
        <h2 style="margin:0 0 .5rem">You left the meeting</h2>
        <p style="color:#64748b;margin:0 0 1rem"><?= htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') ?></p>
        <a href="<?= htmlspecialchars(fm_meeting_participant_join_path($invitationId, $token !== '' ? $token : null), ENT_QUOTES, 'UTF-8') ?>"
           style="display:inline-block;background:#1e4d2b;color:#fff;text-decoration:none;padding:.65rem 1.25rem;border-radius:8px;font-weight:600">Rejoin in browser</a>
    </div>
</div>
<?php elseif ($sdkError !== '' && !$guestForm): ?>
<div class="join-gate">
    <div class="card">
        <h2 style="margin:0 0 .5rem">Cannot join meeting</h2>
        <p style="color:#64748b;margin:0;font-size:.95rem"><?= htmlspecialchars($sdkError, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</div>
<?php elseif ($gateMode && $guestForm): ?>
<div class="join-gate">
    <div class="card">
        <h2 style="margin:0 0 .25rem">External guest — join in browser</h2>
        <p style="color:#64748b;margin:0 0 1.25rem;font-size:.95rem"><?= htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') ?></p>
        <form method="post" action="">
            <input type="hidden" name="invitation_id" value="<?= (int) $invitationId ?>">
            <input type="hidden" name="guest" value="1">
            <input type="hidden" name="guest_token" value="<?= htmlspecialchars($guestToken, ENT_QUOTES, 'UTF-8') ?>">
            <label for="display_name">Your full name</label>
            <input id="display_name" name="display_name" required maxlength="120" placeholder="Full name" autofocus>
            <label for="display_email">Your email</label>
            <input id="display_email" name="display_email" type="email" required maxlength="190" placeholder="email@example.com">
            <button type="submit">Join meeting in browser</button>
        </form>
        <p class="hint">For invited applicants, use the personal link from your email — your name is filled in automatically.</p>
    </div>
</div>
<?php else: ?>
<div id="zmmtg-root"></div>
<div class="join-boot" id="joinBoot">
    <div class="spinner" id="joinSpinner"></div>
    <div id="joinBootTitle">Joining meeting in browser…</div>
    <div style="color:#94a3b8;font-size:.9rem;max-width:520px"><?= htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') ?></div>
    <?php if ($displayName !== ''): ?>
    <div style="color:#94a3b8;font-size:.85rem">As <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?><?php if ($userEmail !== ''): ?> &lt;<?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?>&gt;<?php endif; ?></div>
    <?php endif; ?>
    <div class="err" id="joinBootErr" style="display:none"></div>
</div>

<script src="assets/js/francophonie-zoom-room.js?v=10"></script>
<script>
(function () {
    var sdk = <?= $sdkAuth ? json_encode($sdkAuth, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null' ?>;
    var serverError = <?= json_encode($sdkError, JSON_UNESCAPED_UNICODE) ?>;
    var leaveUrl = <?= json_encode($leaveUrl, JSON_UNESCAPED_UNICODE) ?>;
    var zoomLibUrl = <?= json_encode($zoomLibUrl, JSON_UNESCAPED_UNICODE) ?>;
    var assetBase = <?= json_encode($assetBase, JSON_UNESCAPED_UNICODE) ?>;
    var meetingJs = <?= json_encode($meetingJs, JSON_UNESCAPED_UNICODE) ?>;
    var zoomCssHref = <?= json_encode($zoomCssHref, JSON_UNESCAPED_UNICODE) ?>;
    var attendanceMeta = <?= json_encode($attendanceMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var avatarBranding = <?= json_encode($avatarBranding, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var fmAttendanceId = 0;

    var boot = document.getElementById('joinBoot');
    var bootErr = document.getElementById('joinBootErr');
    var bootTitle = document.getElementById('joinBootTitle');
    var spinner = document.getElementById('joinSpinner');

    function recordAttendance(action) {
        var body = Object.assign({ action: action }, attendanceMeta);
        if (action === 'leave' && fmAttendanceId > 0) {
            body.attendance_id = fmAttendanceId;
        }
        var payload = JSON.stringify(body);
        if (action === 'leave' && navigator.sendBeacon) {
            navigator.sendBeacon('record_fm_meeting_attendance.php', new Blob([payload], { type: 'application/json' }));
            return;
        }
        fetch('record_fm_meeting_attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: payload,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (d && d.ok && d.attendance_id) fmAttendanceId = d.attendance_id;
        }).catch(function () {});
    }

    window.addEventListener('beforeunload', function () {
        if (fmAttendanceId > 0) recordAttendance('leave');
    });

    function showError(msg) {
        boot.style.display = 'flex';
        boot.classList.remove('hidden');
        bootTitle.textContent = 'Could not join meeting';
        spinner.style.display = 'none';
        bootErr.style.display = 'block';
        bootErr.textContent = msg;
    }

    function hideBoot() {
        boot.classList.add('hidden');
        setTimeout(function () { boot.style.display = 'none'; }, 350);
    }

    if (serverError) { showError(serverError); return; }
    if (!sdk || !sdk.signature) { showError('SDK credentials missing.'); return; }
    if (!window.FmZoomRoom || typeof FmZoomRoom.startMeeting !== 'function') {
        showError('Zoom loader missing. Refresh the page.');
        return;
    }

    FmZoomRoom.startMeeting({
        sdk: sdk,
        leaveUrl: leaveUrl,
        zoomLibUrl: zoomLibUrl,
        assetBase: assetBase,
        meetingJs: meetingJs,
        zoomCssHref: zoomCssHref,
        isHost: false,
        avatarBranding: avatarBranding,
        onStatus: function (msg) { bootTitle.textContent = msg; },
        onPreJoin: function () { hideBoot(); },
        onJoined: function () {
            recordAttendance('join');
            hideBoot();
        },
        onError: function (msg) { showError(msg); }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
