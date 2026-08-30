<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/company_branding.php';
/* =========================
   SECURITY - DISABLED LOGIN CHECK
========================= */
// Commenting out login check as requested
// if (!isset($_SESSION['admin_id'], $_SESSION['role']) || $_SESSION['role'] !== 'staff') {
//     header("Location: admin-login.php");
//     exit;
// }

// Use a default staff ID if not logged in (for demo purposes)
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = 1; // Default staff ID
    $_SESSION['role'] = 'staff'; // Default role
}

$staffId = (int)$_SESSION['admin_id'];

/* =========================
   ACTIVE RUNNING SESSION
========================= */
$stmt = $conn->prepare("
    SELECT s.*, o.reason
    FROM overtime_sessions s
    JOIN overtime_requests o ON o.id = s.request_id
    WHERE s.staff_id = ? AND s.status = 'running'
    LIMIT 1
");
$stmt->bind_param("i", $staffId);
$stmt->execute();
$activeSession = $stmt->get_result()->fetch_assoc();

/* =========================
   APPROVED REQUEST
========================= */
$approvedRequest = null;
if (!$activeSession) {
    $stmt = $conn->prepare("
        SELECT *
        FROM overtime_requests
        WHERE staff_id = ?
          AND status = 'approved'
        ORDER BY approved_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $staffId);
    $stmt->execute();
    $approvedRequest = $stmt->get_result()->fetch_assoc();
}

/* =========================
   HISTORY + PAYMENT JOIN
========================= */
$stmt = $conn->prepare("
    SELECT 
        o.id,
        o.request_date,
        o.reason,
        o.status,
        o.created_at,
        IFNULL(s.total_minutes,0) AS total_minutes,

        p.amount AS paid_amount,
        p.currency AS paid_currency

    FROM overtime_requests o
    LEFT JOIN overtime_sessions s ON s.request_id = o.id
    LEFT JOIN overtime_payments p ON p.overtime_request_id = o.id

    WHERE o.staff_id = ?
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $staffId);
$stmt->execute();
$history = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ScholarSync Global - Overtime</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
  /* Brand color palette */
  --deep-navy: #427431;
  --secondary-blue: #3661B9;
  --dark-blue: #2f5a26;
  --gold: #E21D1E;
  --white: #FFFFFF;
  
  /* Derived variables */
  --primary: var(--deep-navy);
  --primary-dark: var(--dark-blue);
  --accent: var(--gold);
  --bg: var(--white);
  --card: #f8fafc;
  --text: #1e293b;
  --muted: #64748b;
  --success: #2e7d32;
  --danger: #c62828;
  --shadow: 0 12px 30px rgba(1, 47, 107, 0.12);
}

body {
  font-family: 'Inter', sans-serif;
  background: linear-gradient(180deg, var(--white) 0%, #f0f4f8 100%);
  min-height: 100vh;
}

/* ===== Brand banner ===== */
.pcvc-brand-banner {
  background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
  padding: 20px 0;
  text-align: center;
  box-shadow: 0 4px 12px rgba(0, 39, 101, 0.15);
  margin-bottom: 40px;
}

.logo-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.logo-main {
  font-size: 2.5rem;
  font-weight: 800;
  color: var(--white);
  letter-spacing: 1px;
  position: relative;
  display: inline-block;
}

.logo-main::after {
  content: '🎓';
  position: absolute;
  top: -5px;
  right: -35px;
  font-size: 1.8rem;
}

.logo-subtitle {
  font-size: 1.1rem;
  font-weight: 500;
  color: var(--gold);
  letter-spacing: 0.5px;
}

/* ===== CARD STYLES ===== */
.card {
  border-radius: 22px;
  border: 1px solid rgba(1, 47, 107, 0.1);
  box-shadow: var(--shadow);
  transition: transform 0.3s ease;
}

.card:hover {
  transform: translateY(-3px);
}

.timer {
  font-size: 3rem;
  font-weight: 700;
  color: var(--deep-navy);
  font-family: 'Inter', monospace;
}

.history-card {
  transition: 0.2s;
}

.history-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.hidden {
  display: none;
}

.task-box {
  background: rgba(37, 77, 129, 0.05);
  border-radius: 12px;
  padding: 14px;
  white-space: pre-line;
  border-left: 4px solid var(--gold);
}

/* ===== BUTTON STYLES ===== */
.btn-primary {
  background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
  border: none;
  border-radius: 999px;
  padding: 12px 24px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.btn-primary:hover {
  background: linear-gradient(135deg, var(--dark-blue) 0%, var(--deep-navy) 100%);
  transform: translateY(-2px);
  box-shadow: 0 8px 16px rgba(1, 47, 107, 0.2);
}

.btn-success {
  background: linear-gradient(135deg, var(--success) 0%, #1b5e20 100%);
  border: none;
  border-radius: 999px;
  padding: 14px 28px;
  font-weight: 600;
}

.btn-danger {
  background: linear-gradient(135deg, var(--danger) 0%, #8e0000 100%);
  border: none;
  border-radius: 999px;
  padding: 14px 28px;
  font-weight: 600;
}

.btn-outline-primary {
  border-color: var(--deep-navy);
  color: var(--deep-navy);
  border-radius: 999px;
}

.btn-outline-primary:hover {
  background: var(--deep-navy);
  border-color: var(--deep-navy);
}

/* ===== BADGES ===== */
.badge {
  border-radius: 999px;
  padding: 6px 12px;
  font-weight: 600;
}

.badge.bg-success {
  background: linear-gradient(135deg, var(--success) 0%, #1b5e20 100%) !important;
}

.badge.bg-secondary {
  background: linear-gradient(135deg, var(--muted) 0%, #475569 100%) !important;
}

/* ===== MODAL ===== */
.modal-content {
  border-radius: 22px;
  border: none;
  box-shadow: var(--shadow);
}

.modal-header {
  background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
  color: var(--white);
  border-radius: 22px 22px 0 0;
  padding: 20px 30px;
}

.modal-header .btn-close {
  filter: invert(1);
}

/* ===== FORM CONTROLS ===== */
.form-control {
  border-radius: 12px;
  border: 1px solid rgba(1, 47, 107, 0.2);
  padding: 12px 16px;
  transition: all 0.3s ease;
}

.form-control:focus {
  border-color: var(--gold);
  box-shadow: 0 0 0 0.25rem rgba(242, 166, 90, 0.25);
}

/* ===== RESPONSIVE ===== */
@media (min-width: 768px) {
  .logo-main {
    font-size: 3rem;
  }
  
  .logo-subtitle {
    font-size: 1.3rem;
  }
  
  .container {
    max-width: 800px;
  }
}
</style>
</head>

<body>

<!-- Brand banner -->
<div class="pcvc-brand-banner">
  <div class="logo-container">
    <div class="logo-main"><?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="logo-subtitle">Overtime</div>
  </div>
</div>

<div class="container py-3">
<div class="col-lg-10 mx-auto">

<!-- =========================
   RUNNING SESSION
========================= -->
<?php if ($activeSession): ?>
<div class="card shadow mb-4 text-center p-4 border-3 border-danger">
    <h4 class="text-danger fw-bold">⏱ Overtime Session Running</h4>
    <div id="timer" class="timer">00:00:00</div>
    <p class="text-muted mt-2">Working on: <?= htmlspecialchars($activeSession['reason']) ?></p>
    <button class="btn btn-danger btn-lg rounded-pill mt-3 px-5"
            onclick="stopOvertime(<?= (int)$activeSession['id'] ?>)">
        ⏹ Stop Overtime Session
    </button>
</div>

<!-- =========================
   APPROVED (READY)
========================= -->
<?php elseif ($approvedRequest): ?>
<div class="card shadow mb-4 text-center p-4 border-3 border-success">
    <h4 class="text-success fw-bold">✅ Overtime Approved</h4>
    <p class="text-muted mb-3">You may start your approved overtime session</p>
    <button class="btn btn-success btn-lg rounded-pill px-5"
            onclick="startOvertime(<?= (int)$approvedRequest['id'] ?>)">
        ▶ Start Overtime Session
    </button>
</div>
<?php endif; ?>

<!-- =========================
   REQUEST CARD
========================= -->
<div class="card shadow p-5 text-center mb-5" style="background: linear-gradient(135deg, rgba(1, 47, 107, 0.05) 0%, rgba(242, 166, 90, 0.05) 100%);">
    <h3 class="fw-bold" style="color: var(--deep-navy);">📝 Request Overtime</h3>
    <p class="text-muted mb-4">Submit a request for approval before starting extra work</p>
    <button class="btn btn-primary btn-lg rounded-pill px-5 py-3"
            data-bs-toggle="modal"
            data-bs-target="#otModal">
        + New Overtime Request
    </button>
</div>

<!-- =========================
   HISTORY FILTER
========================= -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold" style="color: var(--deep-navy);">📊 My Overtime History</h4>
    <div class="btn-group" role="group">
        <button class="btn btn-sm btn-outline-primary" onclick="filterDate('today')">Today</button>
        <button class="btn btn-sm btn-outline-primary" onclick="filterDate('week')">This Week</button>
        <button class="btn btn-sm btn-outline-primary" onclick="filterDate('month')">This Month</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="filterDate('all')">All</button>
    </div>
</div>

<!-- =========================
   HISTORY LIST
========================= -->
<?php if ($history->num_rows > 0): ?>
    <?php while ($row = $history->fetch_assoc()): ?>
    <div class="card history-card mb-3 overtime-row"
         data-date="<?= substr($row['created_at'],0,10) ?>">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <strong style="color: var(--deep-navy);">
                    📅 <?= htmlspecialchars($row['request_date']) ?>
                </strong>
                
                <?php if ($row['paid_amount'] !== null): ?>
                    <span class="badge bg-success">
                        💰 PAID (<?= htmlspecialchars($row['paid_currency']) ?>
                        <?= number_format($row['paid_amount'],2) ?>)
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary">
                        <?= match($row['status']) {
                            'approved' => '✅ APPROVED',
                            'pending' => '⏳ PENDING',
                            'rejected' => '❌ REJECTED',
                            default => strtoupper($row['status'])
                        } ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="small text-muted mb-3">
                ⏱ Minutes Worked: <strong><?= (int)$row['total_minutes'] ?></strong>
                • 📅 Created: <?= date('M d, Y', strtotime($row['created_at'])) ?>
            </div>
            
            <div class="task-box">
                <?= nl2br(htmlspecialchars($row['reason'])) ?>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="card shadow text-center p-5">
        <h5 class="text-muted">No overtime records found</h5>
        <p class="text-muted">Submit your first overtime request above</p>
    </div>
<?php endif; ?>

</div>
</div>

<!-- =========================
   MODAL (REQUEST)
========================= -->
<div class="modal fade" id="otModal">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">

<div class="modal-header">
    <h5 class="fw-bold">📋 New Overtime Request</h5>
    <button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body p-4">
<form id="otForm">
    <div class="mb-3">
        <label class="form-label fw-semibold" style="color: var(--deep-navy);">Date</label>
        <input type="date" name="date" class="form-control" required>
    </div>
    
    <div class="mb-3">
        <label class="form-label fw-semibold" style="color: var(--deep-navy);">Expected Hours</label>
        <input type="number" step="0.5" name="hours" min="0.5" max="24"
               class="form-control" placeholder="Enter expected hours (e.g., 2.5)" required>
    </div>
    
    <div class="mb-3">
        <label class="form-label fw-semibold" style="color: var(--deep-navy);">Tasks / Reason</label>
        <small class="text-muted d-block mb-2">Press Enter to add multiple tasks</small>
        
        <div id="taskList">
            <div class="d-flex gap-2 mb-2">
                <span class="fw-bold" style="color: var(--gold);">1.</span>
                <input class="form-control task-field" placeholder="Describe task..." required>
            </div>
        </div>
    </div>

    <input type="hidden" name="reason" id="reason">

    <button class="btn btn-primary w-100 py-3 fw-bold" style="border-radius: 12px;">
        📤 Submit Request
    </button>
</form>
</div>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* ================= TIMER ================= */
<?php if ($activeSession): ?>
let startTime = <?= strtotime($activeSession['start_time']) * 1000 ?>;
const timerEl = document.getElementById('timer');

function updateTimer() {
    const d = Date.now() - startTime;
    const hours = String(Math.floor(d / 3600000)).padStart(2, '0');
    const minutes = String(Math.floor(d % 3600000 / 60000)).padStart(2, '0');
    const seconds = String(Math.floor(d % 60000 / 1000)).padStart(2, '0');
    timerEl.innerText = `${hours}:${minutes}:${seconds}`;
}

// Update immediately and then every second
updateTimer();
setInterval(updateTimer, 1000);
<?php endif; ?>

/* ================= FILTER ================= */
function filterDate(type) {
    const today = new Date();
    
    // Update active button styling
    document.querySelectorAll('.btn-group .btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');

    document.querySelectorAll('.overtime-row').forEach(row => {
        const d = new Date(row.dataset.date);
        let show = true;

        if (type === 'today') {
            show = d.toDateString() === today.toDateString();
        }
        if (type === 'week') {
            const w = new Date();
            w.setDate(today.getDate() - 7);
            show = d >= w;
        }
        if (type === 'month') {
            show = d.getMonth() === today.getMonth() &&
                   d.getFullYear() === today.getFullYear();
        }
        if (type === 'all') show = true;

        row.classList.toggle('hidden', !show);
    });
}

// Initialize with 'All' filter
document.addEventListener('DOMContentLoaded', () => {
    filterDate('all');
    document.querySelector('[onclick="filterDate(\'all\')"]').classList.add('active');
});

/* ================= START / STOP ================= */
async function startOvertime(id) {
    if (!confirm('Start overtime session now?')) return;
    
    try {
        const r = await fetch('start_overtime.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: id })
        });

        const text = await r.text();
        console.log('START RESPONSE:', text);

        const d = JSON.parse(text);
        if (d.success) {
            alert('✅ Overtime session started!');
            location.reload();
        } else {
            alert('❌ ' + d.message);
        }

    } catch (err) {
        console.error('START ERROR:', err);
        alert('❌ Failed to start overtime. Check console.');
    }
}

async function stopOvertime(id) {
    if (!confirm('Stop overtime session and record time?')) return;
    
    try {
        const r = await fetch('stop_overtime.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: id })
        });

        const text = await r.text();
        console.log('STOP RESPONSE:', text);

        const d = JSON.parse(text);
        if (d.success) {
            alert('✅ Overtime session stopped! Time recorded.');
            location.reload();
        } else {
            alert('❌ ' + d.message);
        }

    } catch (err) {
        console.error('STOP ERROR:', err);
        alert('❌ Failed to stop overtime. Check console.');
    }
}

/* ================= TASK INPUT ================= */
const taskList = document.getElementById('taskList');

taskList.addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.classList.contains('task-field')) {
        e.preventDefault();
        
        // Only add new if current field has content
        if (e.target.value.trim() === '') return;
        
        const n = taskList.children.length + 1;
        const d = document.createElement('div');
        d.className = 'd-flex gap-2 mb-2';
        d.innerHTML = `<span class="fw-bold" style="color: var(--gold);">${n}.</span>
                       <input class="form-control task-field" placeholder="Describe task...">`;
        taskList.appendChild(d);
        d.querySelector('input').focus();
    }
});

/* ================= SUBMIT ================= */
const otForm = document.getElementById('otForm');
const submitBtn = otForm.querySelector('button');

otForm.addEventListener('submit', async e => {
    e.preventDefault();

    // Build tasks
    const tasks = [];
    document.querySelectorAll('.task-field').forEach((el, i) => {
        if (el.value.trim()) {
            tasks.push((i + 1) + '. ' + el.value.trim());
        }
    });

    if (tasks.length === 0) {
        alert('❌ Please add at least one task');
        return;
    }

    document.getElementById('reason').value = tasks.join('\n');

    // Spinner
    submitBtn.disabled = true;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = 'Sending… <span class="spinner-border spinner-border-sm ms-2"></span>';

    try {
        const r = await fetch('save_overtime_request.php', {
            method: 'POST',
            body: new FormData(otForm)
        });

        const text = await r.text();
        console.log('SUBMIT RAW RESPONSE:', text);

        let d;
        try {
            d = JSON.parse(text);
        } catch {
            alert('❌ Server returned invalid response. Check console.');
            return;
        }

        if (d.success) {
            alert('✅ Overtime request submitted successfully!');
            location.reload();
        } else {
            alert('❌ ' + d.message);
        }

    } catch (err) {
        console.error('SUBMIT ERROR:', err);
        alert('❌ Network error. Check console & Network tab.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});

// Set default date to today
document.addEventListener('DOMContentLoaded', () => {
    const today = new Date().toISOString().split('T')[0];
    document.querySelector('input[name="date"]').value = today;
});
</script>

</body>
</html>