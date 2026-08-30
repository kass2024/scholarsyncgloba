<?php
/**
 * francophonie-meeting-host.php — Host meeting in browser (Zoom Meeting SDK).
 */
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/francophonie_meeting_invitation_schema.php';
require_once __DIR__ . '/helpers/zoom_meeting_sdk.php';
require_once __DIR__ . '/helpers/zoom_meeting_api.php';
require_once __DIR__ . '/helpers/zoom_meeting_coop_headers.php';
require_once __DIR__ . '/helpers/fm_meeting_avatars.php';
fm_zoom_send_coop_headers();

xander_load_env_file();
fm_meeting_ensure_schema($conn);

if (empty($_SESSION['id'])) {
    header('Location: admin-login.php');
    exit;
}
pcvc_require_staff_or_superadmin($conn);

$invitationId = (int) ($_GET['invitation_id'] ?? 0);
$topic = 'Meeting';
$sdkAuth = null;
$sdkError = '';
$startUrl = '';
$adminName = 'Host';
$adminEmail = '';

$publicBase = fm_zoom_public_base_url();
$requestBase = fm_zoom_request_base_url();
$assetBase = $requestBase . '/assets/zoom-meetingsdk';
$meetingJs = fm_zoom_meeting_js_file();
$assetsOk = fm_zoom_sdk_assets_installed();

if ($invitationId > 0) {
    $st = $conn->prepare(
        'SELECT topic, zoom_meeting_number, zoom_password, zoom_start_url FROM francophonie_mobility_meeting_invitations WHERE id = ? LIMIT 1'
    );
    $st->bind_param('i', $invitationId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if ($row) {
        $topic = (string) ($row['topic'] ?? $topic);
        $meetingNumber = (string) ($row['zoom_meeting_number'] ?? '');
        $password = (string) ($row['zoom_password'] ?? '');
        $startUrl = (string) ($row['zoom_start_url'] ?? '');

        $hostIdentity = zoom_api_resolve_host_join_identity(false);
        $adminName = $hostIdentity['name'];
        $adminEmail = $hostIdentity['email'];

        if ($adminEmail === '') {
            $sdkError = 'Could not load Zoom host profile. Check ZOOM_HOST_USER_ID and Zoom API credentials in .env.';
        }

        if ($sdkError === '' && $meetingNumber !== '') {
            $plBase = fm_meeting_learning_frontend_base();
            if ($plBase !== '') {
                $embedPath = fm_meeting_embed_room_path($meetingNumber, 1, $password, $adminName, $adminEmail !== '' ? $adminEmail : null);
                header('Location: ' . $plBase . $embedPath);
                exit;
            }

            $sdkResult = zoom_sdk_build_join_payload(
                $meetingNumber,
                $adminName,
                1,
                $password,
                $adminEmail !== '' ? $adminEmail : null,
                true
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
    $sdkError = 'Missing invitation_id.';
}

if (!$assetsOk && $sdkError === '') {
    $sdkError = 'Zoom Meeting SDK files are missing. Run: npm install (in scholarsyncglobal folder).';
}
if (!zoom_sdk_is_configured() && $sdkError === '') {
    $sdkError = 'Zoom embed credentials missing. Set ZOOM_EMBED_CLIENT_ID and ZOOM_EMBED_CLIENT_SECRET in .env.';
}

$leaveUrl = $requestBase . '/francophonie-meeting-invitation.php';
$zoomLibUrl = $assetBase . '/dist/lib';
$zoomCssHref = $assetBase . '/dist/ui/zoom-meetingsdk.css';
$hostAttendanceMeta = [
    'invitation_id' => $invitationId,
    'participant_type' => 'host',
    'participant_name' => is_array($sdkAuth) ? (string) ($sdkAuth['user_name'] ?? $adminName) : $adminName,
    'participant_email' => is_array($sdkAuth) ? (string) ($sdkAuth['user_email'] ?? $adminEmail) : $adminEmail,
];
$avatarBranding = fm_meeting_host_avatar_branding(
    $conn,
    (int) ($_SESSION['id'] ?? 0),
    is_array($sdkAuth) ? (string) ($sdkAuth['user_name'] ?? $adminName) : $adminName,
    $adminEmail,
    $requestBase
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Host meeting — <?= htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/francophonie-zoom-room.css?v=10">
    <style>
        html, body { margin:0; padding:0; background:#1a1a1a; font-family:Arial,sans-serif; }
        #zmmtg-root { display:none; }
        html.zoom-client-meeting-active #zmmtg-root,
        body.zoom-client-meeting-active #zmmtg-root { display:block; }
        .host-boot {
            position:fixed; inset:0; z-index:5; display:flex; flex-direction:column;
            align-items:center; justify-content:center; gap:1rem; padding:1.5rem;
            text-align:center; background:#0f172a; color:#e2e8f0;
            transition:opacity .35s ease;
        }
        .host-boot.hidden { opacity:0; pointer-events:none; }
        .host-boot .err { color:#fecaca; background:#7f1d1d55; padding:.75rem 1rem; border-radius:8px; max-width:560px; }
        .spinner {
            width:42px; height:42px; border:3px solid #334155; border-top-color:#22c55e;
            border-radius:50%; animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .back-link {
            position:fixed; top:12px; left:12px; z-index:2147483000;
            background:#1e293b; color:#fff; text-decoration:none; padding:.45rem .8rem;
            border-radius:8px; font-size:.85rem; border:1px solid #334155;
        }
    </style>
</head>
<body>
<a class="back-link" href="francophonie-meeting-invitation.php">&larr; Invitations</a>
<div id="zmmtg-root"></div>
<div class="host-boot" id="hostBoot">
    <div class="spinner" id="hostSpinner"></div>
    <div id="hostBootTitle">Starting Zoom meeting…</div>
    <div style="color:#94a3b8;font-size:.9rem;max-width:520px"><?= htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') ?></div>
    <?php if ($adminName !== '' && $adminName !== 'Host'): ?>
    <div style="color:#94a3b8;font-size:.85rem">Host: <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <div class="err" id="hostBootErr" style="display:none"></div>
    <?php if ($startUrl !== ''): ?>
    <a id="hostFallback" href="<?= htmlspecialchars($startUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" style="display:none;margin-top:8px;color:#93c5fd;font-size:.85rem">Open in Zoom desktop app instead</a>
    <?php endif; ?>
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
    var attendanceMeta = <?= json_encode($hostAttendanceMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var avatarBranding = <?= json_encode($avatarBranding, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var fmAttendanceId = 0;

    var boot = document.getElementById('hostBoot');
    var bootErr = document.getElementById('hostBootErr');
    var bootTitle = document.getElementById('hostBootTitle');
    var spinner = document.getElementById('hostSpinner');
    var fallback = document.getElementById('hostFallback');

    setTimeout(function () { if (fallback) fallback.style.display = 'inline-block'; }, 20000);

    function recordAttendance(action) {
        var body = Object.assign({ action: action }, attendanceMeta);
        if (action === 'leave' && fmAttendanceId > 0) body.attendance_id = fmAttendanceId;
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
        boot.classList.remove('hidden');
        boot.style.display = 'flex';
        bootTitle.textContent = 'Could not start meeting';
        spinner.style.display = 'none';
        bootErr.style.display = 'block';
        bootErr.textContent = msg;
        if (fallback) fallback.style.display = 'inline-block';
    }

    function hideBoot() {
        boot.classList.add('hidden');
        setTimeout(function () { boot.style.display = 'none'; }, 350);
    }

    if (serverError) { showError(serverError); return; }
    if (!sdk || !sdk.signature) { showError('SDK credentials missing.'); return; }
    if (!window.FmZoomRoom || typeof FmZoomRoom.startMeeting !== 'function') {
        showError('Zoom loader missing. Hard-refresh (Ctrl+F5) and try again.');
        return;
    }

    FmZoomRoom.startMeeting({
        sdk: sdk,
        leaveUrl: leaveUrl,
        zoomLibUrl: zoomLibUrl,
        assetBase: assetBase,
        meetingJs: meetingJs,
        zoomCssHref: zoomCssHref,
        isHost: true,
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
</body>
</html>
