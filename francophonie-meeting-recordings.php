<?php
/**
 * francophonie-meeting-recordings.php — Cloud recordings for Francophonie meeting invitations only.
 */
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/francophonie_meeting_invitation_schema.php';
require_once __DIR__ . '/helpers/zoom_meeting_api.php';

xander_load_env_file();
fm_meeting_ensure_schema($conn);

if (empty($_SESSION['id'])) {
    header('Location: admin-login.php');
    exit;
}
pcvc_require_staff_or_superadmin($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$zoomStatus = zoom_api_connection_status();
$zoomOk = !empty($zoomStatus['ok']);

$defaultFrom = (new DateTime('now'))->modify('-180 days')->format('Y-m-d');
$defaultTo = (new DateTime('now'))->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Recordings — Francophonie Mobility</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --fm-green:#1e4d2b; --fm-blue:#3661B9; --fm-bg:#f4f6f3; }
        body { background:var(--fm-bg); font-size:.95rem; }
        .hero {
            background:linear-gradient(135deg,var(--fm-green),var(--fm-blue));
            color:#fff; padding:1.25rem 0 1rem; margin-bottom:1rem;
        }
        .panel {
            background:#fff; border:1px solid #e2e8f0; border-radius:14px;
            box-shadow:0 2px 12px rgba(0,0,0,.04);
        }
        .panel-head {
            padding:.85rem 1rem; border-bottom:1px solid #e2e8f0;
            font-weight:600; display:flex; align-items:center; gap:.5rem;
        }
        .panel-body { padding:1rem; }
        .recordings-table-wrap { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
        .recordings-table { margin:0; font-size:.88rem; }
        .recordings-table th { background:#f8fafc; font-weight:600; white-space:nowrap; }
        .recordings-table td { vertical-align:middle; }
        .filter-label { font-size:.8rem; color:#64748b; font-weight:600; }
        .recording-player-modal .modal-content {
            border: none;
            border-radius: 14px;
            overflow: hidden;
        }
        .recording-player-modal .modal-header {
            background: linear-gradient(135deg, var(--fm-green), var(--fm-blue));
            color: #fff;
        }
        .recording-player-modal .btn-close {
            filter: invert(1);
        }
        .recording-player-wrap {
            background: #0f172a;
            min-height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .recording-player-wrap video {
            width: 100%;
            max-height: 70vh;
            background: #000;
            display: block;
        }
        .recording-player-status {
            color: #cbd5e1;
            text-align: center;
            padding: 2rem 1.5rem;
        }
        .recording-player-status i {
            font-size: 2rem;
            margin-bottom: .75rem;
            display: block;
            color: #fbbf24;
        }
        tr.recording-row-active {
            background: #ecfdf5 !important;
        }
    </style>
</head>
<body>

<div class="hero">
    <div class="container-fluid px-3 px-lg-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <h1 class="h4 mb-1"><i class="fas fa-cloud me-2"></i>Meeting Recordings</h1>
                <p class="mb-0 opacity-75 small">Cloud recordings from Francophonie meeting invitations only</p>
            </div>
            <div class="text-end">
                <?php if ($zoomOk): ?>
                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Zoom API connected</span>
                <?php else: ?>
                <span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Zoom API not ready</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-3 px-lg-4 pb-5">
    <div class="panel mb-3">
        <div class="panel-head"><i class="fas fa-search"></i> Search recordings</div>
        <div class="panel-body">
            <form id="searchForm" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="filter-label" for="q">Name / topic / meeting ID / date</label>
                    <input type="search" class="form-control" id="q" name="q" placeholder="e.g. Mobilité, 84893624567, Jul 2 2026">
                </div>
                <div class="col-md-3">
                    <label class="filter-label" for="date_from">From date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= htmlspecialchars($defaultFrom, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3">
                    <label class="filter-label" for="date_to">To date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= htmlspecialchars($defaultTo, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-success flex-grow-1" id="searchBtn" <?= $zoomOk ? '' : 'disabled' ?>>
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                </div>
            </form>
            <div class="small text-muted mt-2">
                Only recordings linked to meetings created in Meeting Invitation are shown. New meetings auto-record to Zoom cloud.
            </div>
        </div>
    </div>

    <div id="listAlert" class="alert d-none mb-3" role="alert"></div>

    <div class="recordings-table-wrap table-responsive">
        <table class="table recordings-table table-hover mb-0">
            <thead>
                <tr>
                    <th>Recording name</th>
                    <th>Date</th>
                    <th>Meeting ID</th>
                    <th>Files</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="recordingsBody">
                <tr id="recordingsLoading">
                    <td colspan="5" class="text-muted small py-4 text-center">
                        <span class="spinner-border spinner-border-sm me-2"></span>Loading recordings…
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="small text-muted mt-2" id="resultsMeta"></div>
</div>

<div class="modal fade recording-player-modal" id="recordingPlayerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="recordingPlayerTitle">Recording</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="recording-player-wrap">
                    <video id="recordingPlayerVideo" controls playsinline preload="metadata" style="display:none;"></video>
                    <div id="recordingPlayerStatus" class="recording-player-status" style="display:none;">
                        <i class="fas fa-hourglass-half" id="recordingPlayerStatusIcon"></i>
                        <div class="fw-semibold mb-1" id="recordingPlayerStatusTitle">The recording is processing</div>
                        <div class="small" id="recordingPlayerStatusText">Zoom is still preparing the MP4 file. This page will check again automatically.</div>
                        <button type="button" class="btn btn-sm btn-outline-light mt-3" id="recordingPlayerRetry" style="display:none;">
                            <i class="fas fa-redo me-1"></i> Check again
                        </button>
                    </div>
                    <div id="recordingPlayerLoading" class="recording-player-status">
                        <span class="spinner-border text-light mb-2"></span>
                        <div>Loading recording…</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div class="small text-muted" id="recordingPlayerMeta"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const form = document.getElementById('searchForm');
    const body = document.getElementById('recordingsBody');
    const meta = document.getElementById('resultsMeta');
    const listAlert = document.getElementById('listAlert');
    const searchBtn = document.getElementById('searchBtn');
    const csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '', JSON_THROW_ON_ERROR) ?>;
    const playerModalEl = document.getElementById('recordingPlayerModal');
    const playerModal = playerModalEl ? bootstrap.Modal.getOrCreateInstance(playerModalEl) : null;
    const playerVideo = document.getElementById('recordingPlayerVideo');
    const playerStatus = document.getElementById('recordingPlayerStatus');
    const playerLoading = document.getElementById('recordingPlayerLoading');
    const playerTitle = document.getElementById('recordingPlayerTitle');
    const playerMeta = document.getElementById('recordingPlayerMeta');
    let activeRow = null;
    let recordingsCache = [];
    let playerPollTimer = null;
    let playerCurrentItem = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showAlert(type, message) {
        if (!listAlert) return;
        listAlert.className = 'alert alert-' + type + ' mb-3';
        listAlert.textContent = message;
        listAlert.classList.remove('d-none');
    }

    function hideAlert() {
        listAlert?.classList.add('d-none');
    }

    function resetPlayerUi() {
        if (playerVideo) {
            playerVideo.pause();
            playerVideo.removeAttribute('src');
            playerVideo.load();
            playerVideo.style.display = 'none';
        }
        playerStatus?.style.setProperty('display', 'none');
        playerLoading?.style.setProperty('display', 'none');
    }

    const playerRetry = document.getElementById('recordingPlayerRetry');
    const playerStatusTitle = document.getElementById('recordingPlayerStatusTitle');
    const playerStatusText = document.getElementById('recordingPlayerStatusText');
    const playerStatusIcon = document.getElementById('recordingPlayerStatusIcon');

    function clearPlayerPoll() {
        if (playerPollTimer) {
            window.clearInterval(playerPollTimer);
            playerPollTimer = null;
        }
    }

    function showPlayerError(title, message) {
        resetPlayerUi();
        playerLoading?.style.setProperty('display', 'none');
        playerStatus?.style.setProperty('display', 'block');
        if (playerStatusIcon) {
            playerStatusIcon.className = 'fas fa-exclamation-circle';
            playerStatusIcon.style.color = '#f87171';
        }
        if (playerStatusTitle) playerStatusTitle.textContent = title;
        if (playerStatusText) playerStatusText.textContent = message;
        if (playerRetry) playerRetry.style.display = 'inline-block';
    }

    function showPlayerProcessing(message, autoPoll) {
        resetPlayerUi();
        playerLoading?.style.setProperty('display', 'none');
        playerStatus?.style.setProperty('display', 'block');
        if (playerStatusIcon) {
            playerStatusIcon.className = 'fas fa-hourglass-half';
            playerStatusIcon.style.color = '#fbbf24';
        }
        if (playerStatusTitle) playerStatusTitle.textContent = 'The recording is processing';
        if (playerStatusText) {
            playerStatusText.textContent = message || 'Zoom is still preparing the MP4 file. This page will check again automatically.';
        }
        if (playerRetry) playerRetry.style.display = 'inline-block';

        clearPlayerPoll();
        if (autoPoll && playerCurrentItem) {
            playerPollTimer = window.setInterval(function () {
                attemptPlayback(playerCurrentItem, true);
            }, 20000);
        }
    }

    async function fetchRecordingStatus(meetingNumber) {
        const res = await fetch(
            'fm_meeting_recording_status.php?meeting_number=' + encodeURIComponent(meetingNumber),
            { credentials: 'same-origin' }
        );
        return res.json();
    }

    function startVideoPlayback(streamUrl) {
        return new Promise(function (resolve, reject) {
            if (!playerVideo) {
                reject(new Error('Video player missing'));
                return;
            }

            playerVideo.onloadeddata = function () {
                playerLoading.style.display = 'none';
                playerStatus.style.display = 'none';
                playerVideo.style.display = 'block';
                resolve();
            };
            playerVideo.onerror = function () {
                reject(new Error('Could not load video stream'));
            };

            const url = streamUrl + (streamUrl.indexOf('?') >= 0 ? '&' : '?') + '_ts=' + Date.now();
            playerVideo.src = url;
            playerVideo.load();
        });
    }

    async function attemptPlayback(item, silent) {
        if (!item || !item.meeting_number) return;

        if (!silent) {
            resetPlayerUi();
            playerLoading?.style.setProperty('display', 'block');
        }

        try {
            const status = await fetchRecordingStatus(item.meeting_number);
            if (!status.success) {
                showPlayerError('Recording unavailable', status.message || 'Could not load recording from Zoom.');
                return;
            }

            if (item.meeting_number && recordingsCache.length) {
                const cached = recordingsCache.find(function (row) {
                    return String(row.meeting_number || '') === String(item.meeting_number);
                });
                if (cached) {
                    cached.recording_status = status.status || cached.recording_status;
                    cached.can_play_inline = !!status.ready;
                }
            }

            if (!status.ready) {
                showPlayerProcessing(status.message, true);
                return;
            }

            clearPlayerPoll();
            const streamUrl = status.stream_url || item.stream_url ||
                ('fm_meeting_recording_stream.php?meeting_number=' + encodeURIComponent(item.meeting_number));
            await startVideoPlayback(streamUrl);
        } catch (err) {
            showPlayerError(
                'Playback failed',
                (err && err.message) ? err.message : 'Could not play this recording. Try again or download the MP4.'
            );
        }
    }

    function openInlinePlayer(item) {
        if (!playerModal || !item) return;

        playerCurrentItem = item;
        clearPlayerPoll();

        if (activeRow) {
            activeRow.classList.remove('recording-row-active');
        }
        activeRow = document.querySelector('tr[data-meeting-number="' + item.meeting_number + '"]');
        activeRow?.classList.add('recording-row-active');

        playerTitle.textContent = item.topic || 'Recording';
        playerMeta.textContent = (item.start_time_display || '') + (item.meeting_number ? ' · Meeting ' + item.meeting_number : '');
        playerModal.show();

        attemptPlayback(item, false);
    }

    if (playerRetry) {
        playerRetry.addEventListener('click', function () {
            if (playerCurrentItem) {
                attemptPlayback(playerCurrentItem, false);
            }
        });
    }

    if (playerModalEl) {
        playerModalEl.addEventListener('hidden.bs.modal', function () {
            clearPlayerPoll();
            playerCurrentItem = null;
            resetPlayerUi();
            activeRow?.classList.remove('recording-row-active');
            activeRow = null;
        });
    }

    function buildRow(item) {
        const topic = escapeHtml(item.topic || 'Untitled recording');
        const topicAttr = escapeHtml(item.topic || '');
        const date = escapeHtml(item.start_time_display || item.start_date || '');
        const meetingNumber = escapeHtml(item.meeting_number || '');
        const files = Number(item.recording_files_count || 0);
        const types = (item.file_types || []).join(', ');
        const size = item.total_size_bytes ? ' · ' + escapeHtml(item.total_size_label || '') : '';
        const playUrl = item.play_url || '';
        const downloadUrl = item.download_url || '';
        const canPlay = item.can_play_inline || item.recording_status === 'completed' || playUrl !== '' || item.stream_url;

        let html = '<tr data-meeting-number="' + meetingNumber + '">';
        html += '<td><div class="fw-semibold">' + topic + '</div>';
        html += '<div class="text-muted small">' + Number(item.duration_minutes || 0) + ' min';
        if (item.invitation_id) {
            html += ' · Invitation #' + Number(item.invitation_id);
        }
        html += '</div></td>';
        html += '<td class="small text-nowrap">' + date + '</td>';
        html += '<td class="small font-monospace">' + meetingNumber + '</td>';
        html += '<td class="small">' + files + ' file(s)';
        if (types) {
            html += '<div class="text-muted">' + escapeHtml(types) + size + '</div>';
        }
        html += '</td><td class="text-end text-nowrap">';
        if (canPlay) {
            html += '<button type="button" class="btn btn-sm btn-success btn-play-recording" data-meeting-number="' + meetingNumber + '" title="Play inline"><i class="fas fa-play"></i></button> ';
        }
        if (downloadUrl) {
            const proxyDownload = 'fm_meeting_recording_stream.php?meeting_number=' + encodeURIComponent(item.meeting_number || '') + '&download=1';
            html += '<a href="' + escapeHtml(proxyDownload) + '" class="btn btn-sm btn-outline-primary" title="Download MP4"><i class="fas fa-download"></i></a> ';
        }
        html += '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-recording" data-meeting-number="' + meetingNumber + '" data-topic="' + topicAttr + '" title="Delete from Zoom cloud"><i class="fas fa-trash"></i></button>';
        html += '</td></tr>';
        return html;
    }

    async function loadRecordings() {
        hideAlert();
        const params = new URLSearchParams(new FormData(form));
        body.innerHTML = '<tr><td colspan="5" class="text-muted small py-4 text-center"><span class="spinner-border spinner-border-sm me-2"></span>Loading recordings…</td></tr>';
        searchBtn.disabled = true;

        try {
            const res = await fetch('fm_meeting_recordings_api.php?' + params.toString(), { credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) {
                body.innerHTML = '<tr><td colspan="5" class="text-danger small py-4 text-center">' + escapeHtml(data.message || 'Failed to load') + '</td></tr>';
                meta.textContent = '';
                return;
            }

            const items = data.items || [];
            recordingsCache = items;
            if (items.length === 0) {
                body.innerHTML = '<tr><td colspan="5" class="text-muted small py-4 text-center">No cloud recordings found for Francophonie meetings in this date range.</td></tr>';
            } else {
                body.innerHTML = items.map(buildRow).join('');
            }
            meta.textContent = items.length + ' recording(s) · ' + (data.date_from || '') + ' to ' + (data.date_to || '');
        } catch (err) {
            body.innerHTML = '<tr><td colspan="5" class="text-danger small py-4 text-center">Could not load recordings.</td></tr>';
            meta.textContent = '';
        } finally {
            searchBtn.disabled = false;
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadRecordings();
    });

    document.querySelector('.recordings-table-wrap')?.addEventListener('click', async function (e) {
        const playBtn = e.target.closest('.btn-play-recording');
        if (playBtn) {
            const meetingNumber = playBtn.getAttribute('data-meeting-number') || '';
            const item = recordingsCache.find(function (row) {
                return String(row.meeting_number || '') === meetingNumber;
            });
            if (!item) {
                showAlert('danger', 'Recording not found. Refresh the list and try again.');
                return;
            }
            openInlinePlayer(item);
            return;
        }

        const btn = e.target.closest('.btn-delete-recording');
        if (!btn) return;

        const meetingNumber = btn.getAttribute('data-meeting-number') || '';
        const topic = btn.getAttribute('data-topic') || 'this recording';
        if (!meetingNumber) return;
        if (!confirm('Permanently delete the cloud recording for "' + topic + '"? This cannot be undone.')) {
            return;
        }

        btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('meeting_number', meetingNumber);
            fd.append('topic', topic);
            const res = await fetch('fm_meeting_recordings_api.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();
            if (!data.success) {
                showAlert('danger', data.message || 'Delete failed');
                btn.disabled = false;
                return;
            }
            showAlert('success', data.message || 'Recording deleted.');
            document.querySelector('tr[data-meeting-number="' + meetingNumber + '"]')?.remove();
            if (!body.querySelector('tr')) {
                body.innerHTML = '<tr><td colspan="5" class="text-muted small py-4 text-center">No recordings in this list.</td></tr>';
            }
        } catch (err) {
            showAlert('danger', 'Delete request failed.');
            btn.disabled = false;
        }
    });

    loadRecordings();
})();
</script>
</body>
</html>
