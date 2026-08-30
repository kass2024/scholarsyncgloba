<?php
declare(strict_types=1);

/**
 * Public one-time video invite — upload or record only.
 * Usage: fm-video-invite.php?t=TOKEN
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';

fm_ensure_schema($conn);

$token = trim((string) ($_GET['t'] ?? ''));
$row = null;
$blockedReason = '';

if ($token !== '' && preg_match('/^[a-f0-9]{32}$/i', $token)) {
    $st = $conn->prepare(
        'SELECT id, reference_id, first_name, last_name,
                video_file, video_pcloud_link, video_invite_token,
                video_invite_opened_at, video_invite_used_at
         FROM francophonie_mobility_applications
         WHERE video_invite_token = ? LIMIT 1'
    );
    $st->bind_param('s', $token);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: null;
    $st->close();

    if ($row) {
        if (!empty($row['video_invite_used_at'])) {
            $blockedReason = 'used';
        } elseif (trim((string) ($row['video_pcloud_link'] ?? '')) !== ''
            || trim((string) ($row['video_file'] ?? '')) !== '') {
            $blockedReason = 'has_video';
        } else {
            // Mark first open (does not consume the link — submit does).
            if (empty($row['video_invite_opened_at'])) {
                $open = $conn->prepare(
                    'UPDATE francophonie_mobility_applications
                     SET video_invite_opened_at = NOW()
                     WHERE id = ? AND video_invite_opened_at IS NULL LIMIT 1'
                );
                $id = (int) $row['id'];
                $open->bind_param('i', $id);
                $open->execute();
                $open->close();
            }
        }
    }
} else {
    $blockedReason = 'invalid';
}

$canUpload = $row && $blockedReason === '';
$ownerName = $row ? trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) : '';
$ref = $row ? (string) ($row['reference_id'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Upload Introduction Video — Francophonie Mobility</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--fm-green:#1e4d2b;--fm-blue:#3661B9}
body{background:#f4f6f3;font-family:Segoe UI,system-ui,sans-serif}
.hero{background:linear-gradient(135deg,#1e4d2b,#3661B9);color:#fff;padding:1.5rem 0;margin-bottom:1.25rem}
.card{border:0;border-radius:14px;box-shadow:0 8px 24px rgba(15,23,42,.08)}
.btn-fm{background:var(--fm-green);border-color:var(--fm-green);color:#fff}
.btn-fm:hover{background:#163a20;border-color:#163a20;color:#fff}
</style>
</head>
<body>
<div class="hero">
  <div class="container" style="max-width:820px">
    <div class="small opacity-75">Canada Francophonie Mobility</div>
    <h1 class="h4 mb-0">Introduction Video (one-time link)</h1>
  </div>
</div>
<div class="container pb-5" style="max-width:820px">
<?php if (!$row || $blockedReason === 'invalid'): ?>
  <div class="alert alert-warning">This video upload link is invalid or no longer available.</div>
<?php elseif ($blockedReason === 'used' || $blockedReason === 'has_video'): ?>
  <div class="card">
    <div class="card-body text-center py-5">
      <div class="text-success mb-3" style="font-size:2.5rem"><i class="fas fa-check-circle"></i></div>
      <h2 class="h5">Video already submitted</h2>
      <p class="text-muted mb-0">This one-time link has been used<?= $ref !== '' ? ' for reference <code>' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') . '</code>' : '' ?>.</p>
    </div>
  </div>
<?php else: ?>
  <div class="card mb-3">
    <div class="card-body">
      <h2 class="h5 mb-1"><?= htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="text-muted small mb-3">Reference <code><?= htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') ?></code> · upload or record only (max 1 minute)</p>

      <div class="alert alert-light border mb-3 py-3">
        <div class="fw-semibold mb-2"><i class="fas fa-list-check me-1 text-danger"></i> Key Points for a 1-Minute Self-Recording Interview Video (English)</div>
        <p class="small mb-2 text-muted"><strong>Total Length:</strong> 60 seconds</p>
        <ol class="small mb-2 ps-3">
          <li><strong>Introduction</strong> (8–10 seconds)</li>
          <li><strong>Education &amp; Qualifications</strong> (8–10 seconds)</li>
          <li><strong>Professional Experience</strong> (12–15 seconds)</li>
          <li><strong>Key Skills</strong> (8–10 seconds)</li>
          <li><strong>French Language Ability</strong> (5–7 seconds)</li>
          <li><strong>Why Canada &amp; Mobilité Francophone</strong> (7–8 seconds)</li>
          <li><strong>Closing</strong> (5–7 seconds)</li>
        </ol>
        <p class="small mb-2 text-muted">
          <strong>Simple Formula to Remember:</strong><br>
          <strong>WHO YOU ARE → WHAT YOU STUDIED → WHAT YOU DO → YOUR SKILLS → FRENCH → WHY CANADA → WHY HIRE YOU</strong>
        </p>
        <div class="small text-muted mb-0">
          <strong>Recommended Time Allocation (1 Minute):</strong>
          Introduction 15% · Education 15% · Experience 25% · Skills 15% · French Ability 10% · Why Canada 10% · Closing 10%
        </div>
      </div>

      <div id="errorBox" class="alert alert-danger d-none"></div>
      <div id="successBox" class="alert alert-success d-none">
        <i class="fas fa-check me-1"></i> Video submitted successfully. This link is now closed.
      </div>

      <div id="videoPanel">
        <div class="d-flex flex-wrap gap-2 mb-3">
          <button type="button" class="btn btn-outline-primary btn-sm" id="videoUploadBtn">
            <i class="fas fa-upload me-1"></i> Upload video
          </button>
          <button type="button" class="btn btn-outline-danger btn-sm" id="videoRecordBtn">
            <i class="fas fa-video me-1"></i> Record live
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="videoStopBtn">
            <i class="fas fa-stop me-1"></i> Stop recording
          </button>
          <button type="button" class="btn btn-outline-dark btn-sm d-none" id="videoClearBtn">
            <i class="fas fa-trash me-1"></i> Remove
          </button>
          <span class="align-self-center small text-muted d-none" id="videoTimerLabel">Recording: <strong id="videoTimer">0:00</strong> / 1:00</span>
        </div>
        <input type="file" id="videoFileInput" class="d-none" accept="video/*,.mp4,.webm,.mov,.m4v">
        <video id="videoPreview" class="w-100 rounded border bg-dark mb-2" style="min-height:200px;max-height:360px" playsinline muted></video>
        <div class="progress mb-2 d-none" id="videoProgressWrap" style="height:8px">
          <div class="progress-bar" id="videoProgressBar" style="width:0%"></div>
        </div>
        <div class="small text-muted mb-3" id="videoStatus">No video yet — upload a file or start a live recording (auto-stops at 1 minute).</div>
        <button type="button" class="btn btn-fm" id="submitVideoBtn" disabled>
          <i class="fas fa-paper-plane me-1"></i> Submit video (one-time)
        </button>
      </div>
    </div>
  </div>
<?php endif; ?>
</div>

<?php if ($canUpload): ?>
<script>
(function () {
  const INVITE_TOKEN = <?= json_encode($token) ?>;
  let mediaStream = null;
  let mediaRecorder = null;
  let recordedChunks = [];
  let videoPreviewUrl = '';
  let recordTimer = null;
  let recordSeconds = 0;
  let videoData = null;
  const MAX_RECORD_SECONDS = 60;

  function showError(msg) {
    const box = document.getElementById('errorBox');
    box.textContent = msg;
    box.classList.remove('d-none');
  }
  function clearError() {
    document.getElementById('errorBox').classList.add('d-none');
  }
  function formatTimer(sec) {
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return m + ':' + String(s).padStart(2, '0');
  }
  function stopRecordTimer() {
    if (recordTimer) { clearInterval(recordTimer); recordTimer = null; }
    document.getElementById('videoTimerLabel').classList.add('d-none');
  }
  function startRecordTimer() {
    recordSeconds = 0;
    const label = document.getElementById('videoTimerLabel');
    const timerEl = document.getElementById('videoTimer');
    if (recordTimer) clearInterval(recordTimer);
    label.classList.remove('d-none');
    timerEl.textContent = '0:00';
    recordTimer = setInterval(() => {
      recordSeconds += 1;
      timerEl.textContent = formatTimer(recordSeconds);
      if (recordSeconds >= MAX_RECORD_SECONDS) {
        document.getElementById('videoStatus').textContent = '1-minute limit reached — stopping…';
        stopLiveRecord();
      }
    }, 1000);
  }
  function setReady(data) {
    videoData = data;
    document.getElementById('videoClearBtn').classList.toggle('d-none', !data);
    document.getElementById('submitVideoBtn').disabled = !data;
  }
  function clearVideo() {
    if (videoPreviewUrl) { URL.revokeObjectURL(videoPreviewUrl); videoPreviewUrl = ''; }
    setReady(null);
    const preview = document.getElementById('videoPreview');
    preview.removeAttribute('src');
    preview.srcObject = null;
    preview.load();
    preview.muted = true;
    preview.controls = false;
    document.getElementById('videoStatus').textContent = 'No video yet — upload a file or start a live recording.';
    document.getElementById('videoProgressWrap').classList.add('d-none');
  }
  function uploadVideoBlob(blob, source, filename) {
    return new Promise((resolve, reject) => {
      if (videoPreviewUrl) URL.revokeObjectURL(videoPreviewUrl);
      videoPreviewUrl = URL.createObjectURL(blob);
      const preview = document.getElementById('videoPreview');
      preview.srcObject = null;
      preview.src = videoPreviewUrl;
      preview.muted = false;
      preview.controls = true;

      const fd = new FormData();
      fd.append('file', blob, filename || ('intro-' + source + '.webm'));
      fd.append('source', source);
      const xhr = new XMLHttpRequest();
      const wrap = document.getElementById('videoProgressWrap');
      const bar = document.getElementById('videoProgressBar');
      const status = document.getElementById('videoStatus');
      wrap.classList.remove('d-none');
      bar.style.width = '0%';
      status.textContent = 'Uploading video to pCloud…';
      xhr.open('POST', 'fm_upload_video.php');
      xhr.upload.onprogress = e => {
        if (e.lengthComputable) bar.style.width = Math.round((e.loaded / e.total) * 100) + '%';
      };
      xhr.onload = () => {
        let data;
        try { data = JSON.parse(xhr.responseText || '{}'); }
        catch (err) { reject(new Error('Video upload failed')); return; }
        if (!data.success) { reject(new Error(data.message || 'Video upload failed')); return; }
        setReady(data);
        status.textContent = 'Video ready on pCloud — click Submit video to finish.';
        bar.style.width = '100%';
        resolve(data);
      };
      xhr.onerror = () => reject(new Error('Network error during video upload'));
      xhr.send(fd);
    });
  }
  async function startLiveRecord() {
    try {
      if (mediaRecorder && mediaRecorder.state !== 'inactive') return;
      mediaStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
      const preview = document.getElementById('videoPreview');
      preview.srcObject = mediaStream;
      preview.muted = true;
      preview.controls = false;
      await preview.play();
      recordedChunks = [];
      const mime = MediaRecorder.isTypeSupported('video/webm;codecs=vp9,opus')
        ? 'video/webm;codecs=vp9,opus'
        : (MediaRecorder.isTypeSupported('video/webm') ? 'video/webm' : '');
      mediaRecorder = mime ? new MediaRecorder(mediaStream, { mimeType: mime }) : new MediaRecorder(mediaStream);
      mediaRecorder.ondataavailable = e => { if (e.data && e.data.size > 0) recordedChunks.push(e.data); };
      mediaRecorder.onstop = async () => {
        stopRecordTimer();
        if (mediaStream) { mediaStream.getTracks().forEach(t => t.stop()); mediaStream = null; }
        document.getElementById('videoStopBtn').classList.add('d-none');
        document.getElementById('videoRecordBtn').classList.remove('d-none');
        const blob = new Blob(recordedChunks, { type: mediaRecorder.mimeType || 'video/webm' });
        try {
          clearError();
          await uploadVideoBlob(blob, 'record', 'intro-record.webm');
        } catch (err) {
          showError(err.message || 'Recording upload failed');
        }
      };
      mediaRecorder.start(1000);
      startRecordTimer();
      document.getElementById('videoRecordBtn').classList.add('d-none');
      document.getElementById('videoStopBtn').classList.remove('d-none');
      document.getElementById('videoStatus').textContent = 'Recording… auto-stops at 3:00.';
    } catch (err) {
      stopRecordTimer();
      showError('Camera/microphone permission is required to record live.');
    }
  }
  function stopLiveRecord() {
    stopRecordTimer();
    if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
  }

  document.getElementById('videoUploadBtn').addEventListener('click', () => document.getElementById('videoFileInput').click());
  document.getElementById('videoRecordBtn').addEventListener('click', startLiveRecord);
  document.getElementById('videoStopBtn').addEventListener('click', stopLiveRecord);
  document.getElementById('videoClearBtn').addEventListener('click', clearVideo);
  document.getElementById('videoFileInput').addEventListener('change', async function () {
    const file = this.files && this.files[0];
    this.value = '';
    if (!file) return;
    try {
      clearError();
      await uploadVideoBlob(file, 'upload', file.name);
    } catch (err) {
      showError(err.message || 'Video upload failed');
    }
  });

  document.getElementById('submitVideoBtn').addEventListener('click', async function () {
    if (!videoData) return;
    if (!confirm('Submit this video now? This one-time link will close after success.')) return;
    clearError();
    this.disabled = true;
    document.getElementById('videoStatus').textContent = 'Saving video to your application…';
    try {
      const fd = new FormData();
      fd.append('invite_token', INVITE_TOKEN);
      fd.append('video_source', videoData.source || 'upload');
      fd.append('video_pcloud_fileid', videoData.pcloud_fileid || '');
      fd.append('video_pcloud_link', videoData.pcloud_link || '');
      const res = await fetch('fm_save_video_invite.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Save failed');
      document.getElementById('videoPanel').classList.add('d-none');
      document.getElementById('successBox').classList.remove('d-none');
    } catch (err) {
      showError(err.message || 'Could not save video');
      this.disabled = false;
    }
  });
})();
</script>
<?php endif; ?>
</body>
</html>
