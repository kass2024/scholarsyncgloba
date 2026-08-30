<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
require_once __DIR__ . '/helpers/fm_mobility_contract_schema.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Unauthorized access.');
}

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    exit('Database connection error.');
}

fm_ensure_schema($conn);
fm_contract_ensure_schema($conn);

$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$contractPage = $basePath . '/fm-mobility-contract.php';
$baseUrl      = "{$scheme}://{$host}{$contractPage}";

$contractLink = null;
$message      = null;
$linkedApplicant = null;
$preselectAppId = !empty($_GET['application_id']) ? (int) $_GET['application_id'] : (!empty($_POST['application_id']) ? (int) $_POST['application_id'] : 0);

$applicants = [];
$appResult = $conn->query("
    SELECT id, reference_id, first_name, last_name, email, status
    FROM francophonie_mobility_applications
    ORDER BY created_at DESC
    LIMIT 500
");
if ($appResult) {
    while ($row = $appResult->fetch_assoc()) {
        $applicants[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = !empty($_POST['application_id']) ? (int) $_POST['application_id'] : null;

    if ($applicationId) {
        $stmt = $conn->prepare("
            SELECT id, first_name, last_name, email, phone_area_code, phone_number,
                   date_of_birth, passport_number, address, nationality, country_of_residence
            FROM francophonie_mobility_applications
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $linkedApplicant = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($linkedApplicant) {
            $stmt = $conn->prepare("
                SELECT contract_token
                FROM fm_mobility_contracts
                WHERE application_id = ?
                  AND status IN ('draft','signed')
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->bind_param('i', $applicationId);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existing) {
                $contractLink = $baseUrl . '?token=' . $existing['contract_token'];
                $message = 'Existing contract found for this applicant. Reusing the same link.';
            }
        }
    }

    if (!$contractLink) {
        $contractToken = bin2hex(random_bytes(32));

        $extName = null;
        $extEmail = null;
        $extPhone = null;
        $extNationality = null;
        $extAddress = null;
        $extDob = null;
        $extPassport = null;

        if ($linkedApplicant) {
            $extName = trim(($linkedApplicant['first_name'] ?? '') . ' ' . ($linkedApplicant['last_name'] ?? ''));
            $extEmail = $linkedApplicant['email'] ?? null;
            $area = ltrim((string) ($linkedApplicant['phone_area_code'] ?? ''), '+');
            $num  = trim((string) ($linkedApplicant['phone_number'] ?? ''));
            $extPhone = ($area !== '' || $num !== '') ? '+' . $area . ' ' . $num : null;
            $nat = $linkedApplicant['nationality'] ?? '';
            $extNationality = ($nat !== '' && $nat !== 'N/A') ? $nat : null;
            $addr = trim((string) ($linkedApplicant['address'] ?? ''));
            if ($addr !== '') {
                $extAddress = $addr;
            } else {
                $res = $linkedApplicant['country_of_residence'] ?? '';
                $extAddress = ($res !== '' && $res !== 'N/A') ? 'Country of Residence: ' . $res : null;
            }
            $extDob = !empty($linkedApplicant['date_of_birth']) ? $linkedApplicant['date_of_birth'] : null;
            $extPassport = !empty($linkedApplicant['passport_number']) ? $linkedApplicant['passport_number'] : null;
        }

        $stmt = $conn->prepare("
            INSERT INTO fm_mobility_contracts
            (contract_token, application_id, status,
             external_client_name, external_client_email, external_client_phone,
             external_client_dob, external_client_nationality, external_client_passport,
             external_client_address, created_at)
            VALUES (?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $appIdForBind = $applicationId;
        $stmt->bind_param(
            'sisssssss',
            $contractToken,
            $appIdForBind,
            $extName,
            $extEmail,
            $extPhone,
            $extDob,
            $extNationality,
            $extPassport,
            $extAddress
        );
        $stmt->execute();
        $stmt->close();

        $contractLink = $baseUrl . '?token=' . $contractToken;
        $message = $linkedApplicant
            ? 'New contract issued and linked to applicant ' . htmlspecialchars($linkedApplicant['first_name'] . ' ' . $linkedApplicant['last_name'])
            : 'New contract issued successfully.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Issue Francophonie Mobility Contract</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root {
    --primary: #2d5a27;
    --primary-light: #427431;
    --accent: #c0392b;
    --success: #28a745;
    --bg: #f0f4f0;
    --card: #ffffff;
    --text-muted: #6c757d;
    --border: #d4e4d4;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--bg);
    margin: 0;
}

.container {
    max-width: 680px;
    margin: 60px auto;
    background: var(--card);
    padding: 36px;
    border-radius: 16px;
    box-shadow: 0 20px 50px rgba(45, 90, 39, 0.12);
    border-top: 4px solid var(--primary);
}

.header-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #e8f5e9;
    color: var(--primary);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 16px;
}

h1 {
    margin: 0 0 6px;
    font-size: 24px;
    color: #1a2e1a;
}

.subtitle {
    color: var(--text-muted);
    margin-bottom: 28px;
    font-size: 14px;
    line-height: 1.5;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    color: #374151;
    margin-bottom: 8px;
}

.form-group select {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    background: #fff;
    box-sizing: border-box;
}

.form-group select:focus {
    outline: none;
    border-color: var(--primary-light);
}

.form-hint {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 6px;
}

.btn {
    width: 100%;
    padding: 14px;
    font-size: 16px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    transition: opacity 0.2s;
}

.btn:hover { opacity: 0.92; }

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: #fff;
}

.btn-success {
    background: var(--success);
    color: #fff;
    margin-top: 10px;
}

.btn-back {
    background: #e9ecef;
    margin-bottom: 20px;
    width: auto;
    padding: 10px 18px;
    font-size: 14px;
}

.alert-success {
    background: #e6f4ea;
    color: #1e5631;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 18px;
    font-size: 14px;
    border-left: 4px solid var(--success);
}

.link-box {
    margin-top: 28px;
    padding: 20px;
    background: #f7faf7;
    border-radius: 10px;
    border: 1px solid var(--border);
}

.link-box label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 8px;
    color: #374151;
}

.link-box input {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
    box-sizing: border-box;
    background: #fff;
}

.applicant-preview {
    background: #fafbfc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 13px;
    color: #4b5563;
    margin-top: 8px;
    display: none;
}

.applicant-preview.visible { display: block; }
</style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="container">
    <a href="admin-dashboard.php">
        <button class="btn btn-back" type="button">← Back to Dashboard</button>
    </a>

    <div class="header-badge">🍁 Francophonie Mobility Program</div>
    <h1>Issue E-Sign Contract</h1>
    <p class="subtitle">
        Generate a secure signing link for a Francophonie Mobility applicant.
        Optionally link to an existing application for auto-fill of client details.
    </p>

    <?php if ($message): ?>
        <div class="alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="application_id">Link to Mobility Applicant (optional)</label>
            <select name="application_id" id="application_id">
                <option value="">— No applicant link (manual entry) —</option>
                <?php foreach ($applicants as $app): ?>
                    <?php
                    $label = sprintf(
                        '%s %s (%s) — %s [%s]',
                        $app['first_name'],
                        $app['last_name'],
                        $app['reference_id'],
                        $app['email'],
                        ucfirst(str_replace('_', ' ', $app['status']))
                    );
                    $selected = $preselectAppId > 0 && (int) $app['id'] === $preselectAppId;
                    ?>
                    <option value="<?= (int) $app['id'] ?>" <?= $selected ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="form-hint">When linked, the applicant's name, email, phone, and nationality will be pre-filled on the contract.</p>
            <div class="applicant-preview" id="applicantPreview"></div>
        </div>

        <button class="btn btn-primary" type="submit">📄 Issue / Retrieve Contract Link</button>
    </form>

    <?php if ($contractLink): ?>
        <div class="link-box">
            <label for="contractLink">Contract Signing Link</label>
            <input type="text" id="contractLink" value="<?= htmlspecialchars($contractLink) ?>" readonly>
            <button class="btn btn-success" type="button" onclick="copyLink()">📋 Copy Link</button>
            <div id="copyMsg" style="display:none;text-align:center;color:var(--success);margin-top:10px;font-size:14px;">
                ✔ Contract link copied to clipboard
            </div>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>

<script>
const applicants = <?= json_encode($applicants, JSON_UNESCAPED_UNICODE) ?>;
const select = document.getElementById('application_id');
const preview = document.getElementById('applicantPreview');

function updatePreview() {
    const id = parseInt(select.value, 10);
    if (!id) {
        preview.classList.remove('visible');
        preview.textContent = '';
        return;
    }
    const app = applicants.find(a => parseInt(a.id, 10) === id);
    if (app) {
        preview.innerHTML = '<strong>Selected:</strong> ' + app.first_name + ' ' + app.last_name +
            ' &middot; Ref: ' + app.reference_id + ' &middot; ' + app.email;
        preview.classList.add('visible');
    }
}
select.addEventListener('change', updatePreview);
updatePreview();

function copyLink() {
    const input = document.getElementById('contractLink');
    const msg = document.getElementById('copyMsg');
    input.select();
    navigator.clipboard?.writeText(input.value).catch(() => document.execCommand('copy'));
    if (!navigator.clipboard) document.execCommand('copy');
    msg.style.display = 'block';
    setTimeout(() => msg.style.display = 'none', 2500);
}
</script>
</body>
</html>
