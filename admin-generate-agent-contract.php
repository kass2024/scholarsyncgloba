<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/agent_contract_schema.php';
require_once __DIR__ . '/helpers/mail_smtp.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Unauthorized access.');
}

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    exit('Database connection error.');
}

agent_contract_ensure_schema($conn);

$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$contractPage = $basePath . '/agent-contract.php';
$baseUrl      = "{$scheme}://{$host}{$contractPage}";

$contractLink = null;
$message      = null;
$messageType  = 'success';
$emailResult  = null;

$staffAgents = [];
$q = $conn->query("
    SELECT id, first_name, last_name, full_name, email, phone_number, address, role
    FROM admins
    WHERE role IN ('staff', 'agent')
    ORDER BY
      CASE role WHEN 'agent' THEN 0 WHEN 'staff' THEN 1 ELSE 2 END,
      COALESCE(NULLIF(TRIM(full_name), ''), CONCAT(first_name, ' ', last_name)) ASC
");
if ($q) {
    while ($row = $q->fetch_assoc()) {
        $staffAgents[] = $row;
    }
}

$form = [
    'mode'           => 'registered',
    'admin_id'       => '',
    'agent_name'     => '',
    'agent_email'    => '',
    'agent_phone'    => '',
    'agent_address'  => '',
    'agent_title'    => '',
    'agent_type'     => 'agent',
    'effective_date' => date('Y-m-d'),
    'send_email'     => '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['mode']           = ($_POST['mode'] ?? '') === 'external' ? 'external' : 'registered';
    $form['admin_id']       = trim((string) ($_POST['admin_id'] ?? ''));
    $form['agent_name']     = trim((string) ($_POST['agent_name'] ?? ''));
    $form['agent_email']    = trim((string) ($_POST['agent_email'] ?? ''));
    $form['agent_phone']    = trim((string) ($_POST['agent_phone'] ?? ''));
    $form['agent_address']  = trim((string) ($_POST['agent_address'] ?? ''));
    $form['agent_title']    = trim((string) ($_POST['agent_title'] ?? ''));
    $form['agent_type']     = in_array($_POST['agent_type'] ?? '', ['staff', 'agent', 'external'], true)
        ? (string) $_POST['agent_type']
        : 'agent';
    $form['effective_date'] = trim((string) ($_POST['effective_date'] ?? date('Y-m-d')));
    $form['send_email']     = !empty($_POST['send_email']) ? '1' : '0';

    $adminId = null;
    $agentType = $form['agent_type'];

    if ($form['mode'] === 'registered' && $form['admin_id'] !== '' && ctype_digit($form['admin_id'])) {
        $aid = (int) $form['admin_id'];
        $stmt = $conn->prepare("
            SELECT id, first_name, last_name, full_name, email, phone_number, address, role
            FROM admins
            WHERE id = ? AND role IN ('staff', 'agent')
            LIMIT 1
        ");
        $stmt->bind_param('i', $aid);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin) {
            $adminId = (int) $admin['id'];
            $agentType = in_array($admin['role'], ['staff', 'agent'], true) ? $admin['role'] : 'agent';
            if ($form['agent_name'] === '') {
                $form['agent_name'] = trim((string) ($admin['full_name'] ?? ''))
                    ?: trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
            }
            if ($form['agent_email'] === '') {
                $form['agent_email'] = trim((string) ($admin['email'] ?? ''));
            }
            if ($form['agent_phone'] === '') {
                $form['agent_phone'] = trim((string) ($admin['phone_number'] ?? ''));
            }
            if ($form['agent_address'] === '') {
                $form['agent_address'] = trim((string) ($admin['address'] ?? ''));
            }
            if ($form['agent_title'] === '') {
                $form['agent_title'] = $agentType === 'staff' ? 'Staff' : 'Agent';
            }
        } else {
            $message = 'Selected staff/agent was not found.';
            $messageType = 'error';
        }
    } else {
        $agentType = 'external';
        if ($form['agent_title'] === '') {
            $form['agent_title'] = 'Agent';
        }
    }

    if ($message === null) {
        if ($form['mode'] === 'registered') {
            if ($form['agent_name'] === '') {
                $message = 'Please enter the agent/staff full legal name (or business name).';
                $messageType = 'error';
            } elseif ($form['agent_email'] === '' || !filter_var($form['agent_email'], FILTER_VALIDATE_EMAIL)) {
                $message = 'A valid email is required (used for the signing invite and notices).';
                $messageType = 'error';
            }
        } elseif ($form['send_email'] === '1') {
            if ($form['agent_email'] === '' || !filter_var($form['agent_email'], FILTER_VALIDATE_EMAIL)) {
                $message = 'A valid email is required to send the signing link.';
                $messageType = 'error';
            }
        }
        if ($message === null && ($form['effective_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['effective_date']))) {
            $message = 'Please set a valid effective date.';
            $messageType = 'error';
        }
    }

    if ($message === null) {
        // Reuse latest draft for same admin or same email
        $existing = null;
        if ($adminId) {
            $stmt = $conn->prepare("
                SELECT id, contract_token, status
                FROM agent_contracts
                WHERE admin_id = ? AND status IN ('draft','signed')
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->bind_param('i', $adminId);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        if (!$existing && $form['agent_email'] !== '') {
            $emailLookup = $form['agent_email'];
            $stmt = $conn->prepare("
                SELECT id, contract_token, status
                FROM agent_contracts
                WHERE LOWER(TRIM(agent_email)) = LOWER(TRIM(?))
                  AND status = 'draft'
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->bind_param('s', $emailLookup);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if ($existing && $existing['status'] === 'draft') {
            $contractId = (int) $existing['id'];
            $token = (string) $existing['contract_token'];
            if ($adminId === null) {
                $stmt = $conn->prepare("
                    UPDATE agent_contracts SET
                        admin_id = NULL,
                        agent_type = ?,
                        agent_name = ?,
                        agent_email = ?,
                        agent_phone = ?,
                        agent_address = ?,
                        agent_title = ?,
                        effective_date = ?
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    'sssssssi',
                    $agentType,
                    $form['agent_name'],
                    $form['agent_email'],
                    $form['agent_phone'],
                    $form['agent_address'],
                    $form['agent_title'],
                    $form['effective_date'],
                    $contractId
                );
            } else {
                $stmt = $conn->prepare("
                    UPDATE agent_contracts SET
                        admin_id = ?,
                        agent_type = ?,
                        agent_name = ?,
                        agent_email = ?,
                        agent_phone = ?,
                        agent_address = ?,
                        agent_title = ?,
                        effective_date = ?
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    'isssssssi',
                    $adminId,
                    $agentType,
                    $form['agent_name'],
                    $form['agent_email'],
                    $form['agent_phone'],
                    $form['agent_address'],
                    $form['agent_title'],
                    $form['effective_date'],
                    $contractId
                );
            }
            $stmt->execute();
            $stmt->close();
            $contractLink = $baseUrl . '?token=' . $token;
            $message = 'Existing draft contract updated. You can copy the link or email it again.';
        } elseif ($existing && $existing['status'] === 'signed') {
            $contractLink = $baseUrl . '?token=' . $existing['contract_token'];
            $message = 'This person already has a signed agent contract. Showing the existing link.';
        } else {
            $token = bin2hex(random_bytes(32));
            if ($adminId === null) {
                $stmt = $conn->prepare("
                    INSERT INTO agent_contracts
                    (contract_token, status, admin_id, agent_type, agent_name, agent_email, agent_phone, agent_address, agent_title, effective_date, created_at)
                    VALUES (?, 'draft', NULL, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param(
                    'ssssssss',
                    $token,
                    $agentType,
                    $form['agent_name'],
                    $form['agent_email'],
                    $form['agent_phone'],
                    $form['agent_address'],
                    $form['agent_title'],
                    $form['effective_date']
                );
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO agent_contracts
                    (contract_token, status, admin_id, agent_type, agent_name, agent_email, agent_phone, agent_address, agent_title, effective_date, created_at)
                    VALUES (?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param(
                    'sisssssss',
                    $token,
                    $adminId,
                    $agentType,
                    $form['agent_name'],
                    $form['agent_email'],
                    $form['agent_phone'],
                    $form['agent_address'],
                    $form['agent_title'],
                    $form['effective_date']
                );
            }
            $stmt->execute();
            $stmt->close();
            $contractLink = $baseUrl . '?token=' . $token;
            $message = 'New agent contract issued successfully.';
        }

        if ($contractLink && $form['send_email'] === '1') {
            $safeName = htmlspecialchars($form['agent_name'] !== '' ? $form['agent_name'] : 'Agent', ENT_QUOTES, 'UTF-8');
            $safeLink = htmlspecialchars($contractLink, ENT_QUOTES, 'UTF-8');
            $effDisp  = htmlspecialchars(date('F j, Y', strtotime($form['effective_date'])), ENT_QUOTES, 'UTF-8');
            $body = "
<div style='font-family:Arial,sans-serif;font-size:14px;color:#222;line-height:1.6'>
    <p><strong>ScholarSync Global Co. Ltd.</strong></p>
    <p>Dear {$safeName},</p>
    <p>You have been invited to review and electronically sign the <strong>Agent Referral and Commission Agreement</strong> (effective {$effDisp}).</p>
    <p>Please open the secure link below, complete all of your details (including a username and password), draw your signature, and submit. Signing will create your agent account in our system.</p>
    <p style='margin:24px 0'><a href=\"{$safeLink}\" style='display:inline-block;background:#427431;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:600'>Open &amp; Sign Agreement</a></p>
    <p style='font-size:12px;color:#555;word-break:break-all'>Or copy this link:<br>{$safeLink}</p>
    <p>If you have questions, reply to this email or contact <a href='mailto:infos@scholarsyncglobal.ca'>infos@scholarsyncglobal.ca</a>.</p>
    <p style='margin-top:28px'>Kind regards,<br><strong>ScholarSync Global</strong></p>
</div>";

            $sent = sendSMTPMail(
                $form['agent_email'],
                'Please sign: Agent Referral & Commission Agreement — ScholarSync Global',
                $body
            );

            if ($sent) {
                $upd = $conn->prepare("UPDATE agent_contracts SET invite_sent_at = NOW() WHERE contract_token = ?");
                $tok = preg_replace('/^.*token=/', '', $contractLink);
                $upd->bind_param('s', $tok);
                $upd->execute();
                $upd->close();
                $emailResult = 'Signing invitation emailed to ' . $form['agent_email'] . '.';
            } else {
                $emailResult = 'Contract link created, but the email failed to send. Copy the link below and share it manually.';
                $messageType = 'error';
            }
        }
    }
}

$staffJson = [];
foreach ($staffAgents as $a) {
    $name = trim((string) ($a['full_name'] ?? ''))
        ?: trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
    $staffJson[] = [
        'id'      => (int) $a['id'],
        'name'    => $name,
        'email'   => (string) ($a['email'] ?? ''),
        'phone'   => (string) ($a['phone_number'] ?? ''),
        'address' => (string) ($a['address'] ?? ''),
        'role'    => (string) ($a['role'] ?? 'agent'),
        'title'   => (($a['role'] ?? '') === 'staff') ? 'Staff' : 'Agent',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Issue Agent Contract</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root { --primary:#427431; --success:#28a745; --bg:#f8fafc; --card:#fff; --muted:#6c757d; --danger:#b91c1c; }
body { font-family:'Inter',system-ui,sans-serif; background:var(--bg); margin:0; }
.container { max-width:720px; margin:60px auto; background:var(--card); padding:32px; border-radius:14px; box-shadow:0 20px 40px rgba(0,0,0,.08); border-top:4px solid var(--primary); }
h1 { text-align:center; margin:0 0 6px; font-size:22px; }
.subtitle { text-align:center; color:var(--muted); margin-bottom:24px; font-size:14px; }
.form-group { margin-bottom:16px; }
.form-group label { display:block; font-weight:600; margin-bottom:6px; font-size:13px; }
.form-group input, .form-group select, .form-group textarea {
  width:100%; padding:12px; border:2px solid #e5e7eb; border-radius:8px; box-sizing:border-box; font-size:14px;
}
.form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
@media (max-width:640px){ .form-row-2 { grid-template-columns:1fr; } }
.mode-tabs { display:flex; gap:8px; margin-bottom:18px; }
.mode-tabs label {
  flex:1; text-align:center; padding:12px; border:2px solid #e5e7eb; border-radius:10px; cursor:pointer; font-weight:600; font-size:13px;
}
.mode-tabs input { display:none; }
.mode-tabs input:checked + span { color:var(--primary); }
.mode-tabs label:has(input:checked) { border-color:var(--primary); background:#f0f7f0; }
.btn { width:100%; padding:14px; border:none; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; background:var(--primary); color:#fff; margin-top:6px; }
.btn-secondary { background:#374151; }
.btn-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
@media (max-width:640px){ .btn-row { grid-template-columns:1fr; } }
.alert { padding:14px; border-radius:8px; margin-bottom:16px; font-size:14px; }
.alert-success { background:#e6f4ea; color:#1e5631; }
.alert-error { background:#fef2f2; color:var(--danger); }
.link-box { margin-top:22px; padding:18px; background:#f7faf7; border-radius:10px; border:1px solid #d4e4d4; }
.link-box input { width:100%; padding:12px; border:2px solid #e5e7eb; border-radius:8px; margin-bottom:10px; box-sizing:border-box; }
.btn-copy { background:var(--success); color:#fff; width:100%; padding:12px; border:none; border-radius:8px; font-weight:600; cursor:pointer; }
.hint { font-size:12px; color:var(--muted); margin-top:4px; }
.check-row { display:flex; align-items:center; gap:8px; margin:14px 0 6px; font-size:14px; }
.check-row input { width:auto; }
</style>
</head>
<body>
<?php include 'header.php'; ?>
<main class="container">
    <div style="text-align:center;margin-bottom:10px;font-size:12px;font-weight:600;color:var(--primary);">AGENT REFERRAL &amp; COMMISSION</div>
    <h1>Issue Agent Contract</h1>
    <p class="subtitle">Send a signing link to registered staff/agents, or issue a blank link so an unregistered person can fill in their own details and be added as an agent.</p>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($emailResult): ?>
        <div class="alert alert-<?= str_contains($emailResult, 'failed') ? 'error' : 'success' ?>"><?= htmlspecialchars($emailResult) ?></div>
    <?php endif; ?>

    <form method="post" id="issueForm">
        <div class="mode-tabs">
            <label>
                <input type="radio" name="mode" value="registered" <?= $form['mode'] === 'registered' ? 'checked' : '' ?> onchange="toggleMode()">
                <span>Registered staff / agent</span>
            </label>
            <label>
                <input type="radio" name="mode" value="external" <?= $form['mode'] === 'external' ? 'checked' : '' ?> onchange="toggleMode()">
                <span>Not registered (copy / email link)</span>
            </label>
        </div>

        <div class="form-group" id="registeredBlock">
            <label for="admin_id">Select staff or agent</label>
            <select name="admin_id" id="admin_id" onchange="fillFromAdmin()">
                <option value="">— Choose —</option>
                <?php foreach ($staffAgents as $a):
                    $label = trim((string) ($a['full_name'] ?? ''))
                        ?: trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
                    $label .= ' (' . strtoupper((string) $a['role']) . ')';
                    if (!empty($a['email'])) {
                        $label .= ' — ' . $a['email'];
                    }
                    ?>
                    <option value="<?= (int) $a['id'] ?>" <?= (string) $form['admin_id'] === (string) $a['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Missing people? Add them under Staff Management, or use “Not registered”.</p>
        </div>

        <p class="hint" id="externalHint" style="display:none;margin:-6px 0 16px;">
            Leave name and contact blank if you want — they will complete everything on the signing page, including username and password. Their agent account is created when they submit. Email is only required if you click “Issue &amp; Email Link”.
        </p>

        <div class="form-row-2">
            <div class="form-group">
                <label for="agent_name" id="label_agent_name">Full legal / business name *</label>
                <input type="text" name="agent_name" id="agent_name" required value="<?= htmlspecialchars($form['agent_name']) ?>" placeholder="Name as it should appear on the contract">
            </div>
            <div class="form-group">
                <label for="agent_email" id="label_agent_email">Email *</label>
                <input type="email" name="agent_email" id="agent_email" required value="<?= htmlspecialchars($form['agent_email']) ?>" placeholder="signatory@email.com">
            </div>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label for="agent_phone">Phone</label>
                <input type="text" name="agent_phone" id="agent_phone" value="<?= htmlspecialchars($form['agent_phone']) ?>">
            </div>
            <div class="form-group">
                <label for="agent_title">Title</label>
                <input type="text" name="agent_title" id="agent_title" value="<?= htmlspecialchars($form['agent_title']) ?>" placeholder="Agent / Staff / Director…">
            </div>
        </div>

        <div class="form-group">
            <label for="agent_address">Address</label>
            <textarea name="agent_address" id="agent_address" rows="2" placeholder="Physical / business address"><?= htmlspecialchars($form['agent_address']) ?></textarea>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label for="agent_type">Type</label>
                <select name="agent_type" id="agent_type">
                    <option value="agent" <?= $form['agent_type'] === 'agent' ? 'selected' : '' ?>>Agent</option>
                    <option value="staff" <?= $form['agent_type'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                    <option value="external" <?= $form['agent_type'] === 'external' ? 'selected' : '' ?>>External / unregistered</option>
                </select>
            </div>
            <div class="form-group">
                <label for="effective_date">Effective date *</label>
                <input type="date" name="effective_date" id="effective_date" required value="<?= htmlspecialchars($form['effective_date']) ?>">
            </div>
        </div>

        <input type="hidden" name="send_email" id="send_email_flag" value="<?= $form['send_email'] === '1' ? '1' : '0' ?>">

        <div class="btn-row">
            <button class="btn btn-secondary" type="submit" onclick="setSendEmail('0')">Issue &amp; Copy Link</button>
            <button class="btn" type="submit" onclick="setSendEmail('1')">Issue &amp; Email Link</button>
        </div>
    </form>

    <?php if ($contractLink): ?>
        <div class="link-box">
            <label for="contractLink"><strong>Contract Signing Link</strong></label>
            <input type="text" id="contractLink" value="<?= htmlspecialchars($contractLink) ?>" readonly>
            <button class="btn-copy" type="button" onclick="copyLink()">Copy Link</button>
        </div>
    <?php endif; ?>
</main>
<?php include 'footer.php'; ?>
<script>
const STAFF = <?= json_encode($staffJson, JSON_UNESCAPED_UNICODE) ?>;

function toggleMode() {
  const mode = document.querySelector('input[name="mode"]:checked')?.value || 'registered';
  document.getElementById('registeredBlock').style.display = mode === 'registered' ? 'block' : 'none';
  const name = document.getElementById('agent_name');
  const email = document.getElementById('agent_email');
  const hint = document.getElementById('externalHint');
  const nameLabel = document.getElementById('label_agent_name');
  const emailLabel = document.getElementById('label_agent_email');
  if (mode === 'external') {
    document.getElementById('agent_type').value = 'external';
    name.required = false;
    email.required = false;
    if (hint) hint.style.display = 'block';
    if (nameLabel) nameLabel.textContent = 'Full legal / business name (optional)';
    if (emailLabel) emailLabel.textContent = 'Email (needed only to email the link)';
  } else {
    name.required = true;
    email.required = true;
    if (hint) hint.style.display = 'none';
    if (nameLabel) nameLabel.textContent = 'Full legal / business name *';
    if (emailLabel) emailLabel.textContent = 'Email *';
  }
}

function setSendEmail(v) {
  document.getElementById('send_email_flag').value = v;
  const mode = document.querySelector('input[name="mode"]:checked')?.value || 'registered';
  const email = document.getElementById('agent_email');
  if (mode === 'external') {
    email.required = v === '1';
  }
}

function fillFromAdmin() {
  const id = parseInt(document.getElementById('admin_id').value || '0', 10);
  const row = STAFF.find(s => s.id === id);
  if (!row) return;
  document.getElementById('agent_name').value = row.name || '';
  document.getElementById('agent_email').value = row.email || '';
  document.getElementById('agent_phone').value = row.phone || '';
  document.getElementById('agent_address').value = row.address || '';
  document.getElementById('agent_title').value = row.title || '';
  document.getElementById('agent_type').value = row.role === 'staff' ? 'staff' : 'agent';
}

function copyLink() {
  const input = document.getElementById('contractLink');
  input.select();
  navigator.clipboard?.writeText(input.value).catch(() => document.execCommand('copy'));
  if (!navigator.clipboard) document.execCommand('copy');
  alert('Contract link copied.');
}

toggleMode();
</script>
</body>
</html>
