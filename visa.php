<?php
// ============================================
// SIMPLIFIED VISA APPLICATION FORM
// ============================================

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}
require_once __DIR__ . '/db.php';
include 'header.php';

// ============================================
// HELPER FUNCTIONS
// ============================================

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateSecureId() {
    return 'user-' . time() . '-' . rand(1000, 9999);
}

// ============================================
// APPLICATION ID MANAGEMENT
// ============================================

if (!isset($_GET['id']) || (isset($_GET['new']) && $_GET['new'] === 'true')) {
    $userId = generateSecureId();
    $_SESSION['user_id'] = $userId;
    $_SESSION['current_application_id'] = $userId;
    
    if (!isset($_GET['id'])) {
        header('Location: visa.php?id=' . $userId . 
               (isset($_GET['country_id']) ? '&country_id=' . intval($_GET['country_id']) : '') .
               (isset($_GET['region_id']) ? '&region_id=' . intval($_GET['region_id']) : ''));
        exit;
    }
} else {
    $userId = sanitizeInput($_GET['id']);
    $_SESSION['user_id'] = $userId;
}

// ============================================
// GET COUNTRY AND REGION DATA
// ============================================

$countryId = isset($_GET['country_id']) ? intval($_GET['country_id']) : 0;
$regionId = isset($_GET['region_id']) ? intval($_GET['region_id']) : 0;
$countryName = '';

if ($countryId > 0) {
    $stmt = $conn->prepare("SELECT name FROM countries WHERE id = ?");
    $stmt->bind_param("i", $countryId);
    $stmt->execute();
    $stmt->bind_result($countryName);
    $stmt->fetch();
    $stmt->close();
}

// Get all countries for dropdown
$countries = [];
$countryStmt = $conn->query("SELECT id, name FROM countries ORDER BY name");
if ($countryStmt) {
    while ($row = $countryStmt->fetch_assoc()) {
        $countries[] = [
            'id' => intval($row['id']),
            'name' => htmlspecialchars($row['name'])
        ];
    }
}

// Get existing application data
$data = [];
$visaMobileE164DigitsInit = '';
$existingFiles = [
    'passport_copy' => '',
    'academic_documents' => '',
    'old_visa_copy' => '',
    'passport_photo' => '',
    'cv' => '',
    'signature' => ''
];

if ($userId) {
    $stmt = $conn->prepare("SELECT * FROM form_17_applications WHERE user_id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc() ?? [];

    $visaMobileE164DigitsInit = preg_replace('/\D+/', '', (string) ($data['applicant_mobile'] ?? ''));
    
    // Sanitize data
    foreach ($data as $key => $value) {
        if ($value !== null) {
            $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }
    
    // Check existing files
    $fileFields = ['passport_copy', 'academic_documents', 'old_visa_copy', 'passport_photo', 'cv', 'signature'];
    foreach ($fileFields as $field) {
        if (!empty($data[$field])) {
            $existingFiles[$field] = $data[$field];
        }
    }
    $stmt->close();
}

// ============================================
// VISA TYPES
// ============================================

$visaTypes = [
    'Study Visa' => 'Study Visa',
    'Work Visa' => 'Work Visa',
    'Tourist Visa' => 'Tourist Visa',
    'Business Visa' => 'Business Visa',
    'Transit Visa' => 'Transit Visa',
    'Family Visa' => 'Family Visa',
    'Medical Visa' => 'Medical Visa',
    'Student Exchange Visa' => 'Student Exchange Visa',
    'Working Holiday Visa' => 'Working Holiday Visa',
    'Permanent Resident Visa' => 'Permanent Resident Visa'
];

// Helper functions
function selected($field, $value) {
    global $data;
    return (isset($data[$field]) && $data[$field] == $value) ? 'selected' : '';
}

function checked($field, $value) {
    global $data;
    return (isset($data[$field]) && $data[$field] == $value) ? 'checked' : '';
}

function isFileUploaded($field) {
    global $existingFiles;
    return !empty($existingFiles[$field]);
}

// ============================================
// FILE CONFIGURATION
// ============================================

$allowedFileTypes = [
    'passport_copy' => ['pdf', 'jpg', 'jpeg', 'png'],
    'academic_documents' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
    'old_visa_copy' => ['pdf', 'jpg', 'jpeg', 'png'],
    'passport_photo' => ['jpg', 'jpeg', 'png'],
    'cv' => ['pdf', 'doc', 'docx'],
    'signature' => ['jpg', 'jpeg', 'png', 'pdf']
];

$maxFileSize = 10 * 1024 * 1024; // 10MB
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visa Application — ScholarSync Global</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css">

    <style>
        :root {
            --x-navy: #427431;
            --x-navy-dark: #2f5a26;
            --x-blue-2: #3661B9;
            --x-gold: #E21D1E;
            --x-white: #FFFFFF;
            --x-gray-bg: #F8F9FA;
            --x-border: #E0E0E0;
            --x-text: #1e293b;
            --x-muted: #64748b;
            --x-success: #0d9488;
            --x-danger: #b91c1c;
            --x-radius: 14px;
            --x-radius-sm: 10px;
            --x-shadow: 0 4px 24px rgba(1, 47, 107, 0.08);
            --x-shadow-lg: 0 20px 50px rgba(1, 47, 107, 0.12);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body.visa-form-page {
            font-family: 'DM Sans', system-ui, sans-serif;
            background: linear-gradient(165deg, var(--x-gray-bg) 0%, #eef2f7 45%, #e8edf5 100%);
            color: var(--x-text);
            padding: 0 16px 48px;
            line-height: 1.55;
            min-height: 100vh;
        }

        .iti { width: 100%; }
        .iti__flag-container { z-index: 4; }

        .visa-shell {
            max-width: 920px;
            margin: 0 auto;
            padding-top: 24px;
        }

        .visa-hero {
            background: linear-gradient(135deg, var(--x-navy) 0%, var(--x-blue-2) 55%, var(--x-navy-dark) 100%);
            color: var(--x-white);
            border-radius: var(--x-radius);
            padding: 28px 28px 32px;
            margin-bottom: 24px;
            box-shadow: var(--x-shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .visa-hero::after {
            content: '';
            position: absolute;
            top: -40%;
            right: -15%;
            width: 55%;
            height: 180%;
            background: radial-gradient(circle, rgba(242, 166, 90, 0.22) 0%, transparent 70%);
            pointer-events: none;
        }

        .visa-hero-inner { position: relative; z-index: 1; }

        .visa-hero h1 {
            font-size: clamp(1.35rem, 4vw, 1.75rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .visa-hero p {
            opacity: 0.92;
            font-size: 0.95rem;
            max-width: 36rem;
        }

        .visa-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
            align-items: center;
        }

        .visa-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .visa-pill code {
            font-family: ui-monospace, monospace;
            font-size: 0.8em;
            background: rgba(0,0,0,0.15);
            padding: 2px 8px;
            border-radius: 6px;
        }

        .visa-pill--gold {
            background: rgba(242, 166, 90, 0.25);
            border-color: rgba(242, 166, 90, 0.45);
            color: #fff8f0;
        }

        .visa-card {
            background: var(--x-white);
            border-radius: var(--x-radius);
            box-shadow: var(--x-shadow);
            border: 1px solid rgba(1, 47, 107, 0.06);
            padding: 28px 26px 32px;
            margin-bottom: 20px;
        }

        .visa-stepper {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            max-width: 420px;
            margin: 0 auto 32px;
            position: relative;
        }

        .visa-stepper::before {
            content: '';
            position: absolute;
            top: 22px;
            left: 18%;
            right: 18%;
            height: 3px;
            background: var(--x-border);
            border-radius: 2px;
            z-index: 0;
        }

        .visa-stepper .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .step-circle {
            width: 44px;
            height: 44px;
            margin: 0 auto 10px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.95rem;
            background: var(--x-gray-bg);
            color: var(--x-muted);
            border: 3px solid var(--x-white);
            box-shadow: 0 2px 8px rgba(1, 47, 107, 0.08);
            transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.25s, color 0.25s;
        }

        .step.active .step-circle {
            background: var(--x-navy);
            color: var(--x-white);
            transform: scale(1.06);
        }

        .step.completed .step-circle {
            background: var(--x-success);
            color: var(--x-white);
        }

        .step-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--x-muted);
        }

        .step.active .step-label { color: var(--x-navy); }
        .step.completed .step-label { color: var(--x-success); }

        .form-step { display: none; animation: visaFade 0.4s ease; }
        .form-step.active { display: block; }

        @keyframes visaFade {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .visa-section-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--x-navy);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .visa-section-title i { color: var(--x-gold); }

        .visa-section-hint {
            font-size: 0.875rem;
            color: var(--x-muted);
            margin-bottom: 22px;
        }

        .visa-field-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 18px 20px;
            margin-bottom: 8px;
        }

        .form-group { margin-bottom: 0; }

        label {
            display: block;
            font-weight: 600;
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 8px;
            color: var(--x-blue-2);
        }

        label.required::after { content: ' *'; color: var(--x-danger); }

        input, select, textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--x-border);
            border-radius: var(--x-radius-sm);
            font-size: 1rem;
            font-family: inherit;
            background: var(--x-white);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--x-navy);
            box-shadow: 0 0 0 4px rgba(1, 47, 107, 0.1);
        }

        .visa-mobile-hint {
            font-size: 0.8125rem;
            color: var(--x-muted);
            margin: 0 0 8px;
        }

        .visa-gender-row, .visa-radio-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }

        .visa-radio-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border: 1px solid var(--x-border);
            border-radius: var(--x-radius-sm);
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            font-weight: 500;
            font-size: 0.9375rem;
        }

        .visa-radio-pill:hover { border-color: var(--x-blue-2); background: var(--x-gray-bg); }

        .visa-radio-pill input { width: auto; accent-color: var(--x-navy); }

        .visa-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .visa-type-grid .visa-radio-pill { margin: 0; }

        .btn {
            padding: 14px 22px;
            border: none;
            border-radius: var(--x-radius-sm);
            font-size: 0.9375rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

        .btn-primary {
            background: linear-gradient(135deg, var(--x-navy) 0%, var(--x-blue-2) 100%);
            color: var(--x-white);
            box-shadow: 0 4px 14px rgba(1, 47, 107, 0.35);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(1, 47, 107, 0.4);
        }

        .btn-secondary {
            background: var(--x-white);
            color: var(--x-navy);
            border: 2px solid var(--x-border);
        }

        .btn-secondary:hover:not(:disabled) {
            border-color: var(--x-blue-2);
            background: var(--x-gray-bg);
        }

        .form-buttons {
            margin-top: 32px;
            display: flex;
            gap: 12px;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .file-upload-section { margin-bottom: 28px; }

        .file-upload-section h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--x-navy);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-upload-section h4 i { color: var(--x-gold); }

        .file-upload-card {
            background: linear-gradient(180deg, #fafbfc 0%, var(--x-gray-bg) 100%);
            border: 2px dashed var(--x-border);
            border-radius: var(--x-radius-sm);
            padding: 26px 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.25s, background 0.25s, transform 0.2s;
            position: relative;
        }

        .file-upload-card:hover {
            border-color: var(--x-blue-2);
            background: #f0f4fa;
        }

        .file-upload-card.has-file {
            border-color: var(--x-success);
            border-style: solid;
            background: rgba(13, 148, 136, 0.06);
        }

        .file-upload-card.dragover {
            border-color: var(--x-gold);
            background: rgba(242, 166, 90, 0.12);
            transform: scale(1.01);
        }

        .file-upload-card.uploading { pointer-events: none; opacity: 0.85; }

        .file-icon { font-size: 2.25rem; color: var(--x-navy); margin-bottom: 12px; }
        .file-upload-card.has-file .file-icon { color: var(--x-success); }

        .file-title { font-weight: 600; color: var(--x-text); margin-bottom: 4px; }
        .file-subtitle { font-size: 0.8125rem; color: var(--x-muted); }

        .file-checkmark {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 28px;
            height: 28px;
            background: var(--x-success);
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }

        .file-upload-card.has-file .file-checkmark {
            display: flex;
            animation: visaPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes visaPop {
            from { transform: scale(0); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .file-status { margin-top: 10px; font-size: 0.875rem; color: var(--x-muted); }
        .status-ready { color: var(--x-success); font-weight: 600; }

        .file-info {
            margin-top: 14px;
            padding: 14px 16px;
            background: var(--x-white);
            border-radius: var(--x-radius-sm);
            border: 1px solid var(--x-border);
            display: none;
        }

        .file-info.show { display: block; animation: visaFade 0.35s ease; }

        .file-info-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }

        .file-name { font-weight: 600; color: var(--x-navy); word-break: break-all; }

        .remove-btn {
            background: var(--x-danger);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .progress-bar {
            height: 5px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 12px;
            display: none;
        }

        .progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--x-navy), var(--x-gold));
            transition: width 0.2s ease-out;
        }

        .existing-file {
            background: rgba(13, 148, 136, 0.1);
            border: 1px solid rgba(13, 148, 136, 0.35);
            border-radius: var(--x-radius-sm);
            padding: 16px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .existing-file-info { display: flex; align-items: center; gap: 12px; }
        .existing-file i { color: var(--x-success); font-size: 1.35rem; }

        .visa-terms {
            margin-top: 28px;
            padding: 16px 18px;
            background: var(--x-gray-bg);
            border-radius: var(--x-radius-sm);
            border: 1px solid var(--x-border);
        }

        .visa-terms label {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-transform: none;
            letter-spacing: 0;
            font-size: 0.9375rem;
            color: var(--x-text);
            cursor: pointer;
        }

        .visa-terms input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-top: 2px;
            accent-color: var(--x-navy);
            flex-shrink: 0;
        }

        /* Toast */
        .visa-toast-wrap {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(120px);
            z-index: 100002;
            max-width: min(420px, calc(100vw - 32px));
            opacity: 0;
            transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.35s ease;
            pointer-events: none;
        }

        .visa-toast-wrap.visible {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        .visa-toast {
            background: var(--x-navy-dark);
            color: var(--x-white);
            padding: 14px 18px;
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.25);
            font-size: 0.9375rem;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border-left: 4px solid var(--x-gold);
        }

        .visa-toast.error { border-left-color: #f87171; }
        .visa-toast.success { border-left-color: var(--x-success); }
        .visa-toast i { margin-top: 2px; flex-shrink: 0; }

        /* Submit overlay */
        .visa-submit-overlay {
            position: fixed;
            inset: 0;
            background: rgba(1, 47, 107, 0.88);
            backdrop-filter: blur(8px);
            z-index: 100001;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.35s ease, visibility 0.35s;
        }

        .visa-submit-overlay.visible { opacity: 1; visibility: visible; }

        .visa-submit-card {
            background: var(--x-white);
            border-radius: var(--x-radius);
            padding: 36px 32px;
            max-width: 380px;
            width: 100%;
            text-align: center;
            box-shadow: var(--x-shadow-lg);
            animation: visaPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.05s both;
        }

        .visa-submit-card .spinner {
            width: 52px;
            height: 52px;
            border: 4px solid var(--x-border);
            border-top-color: var(--x-navy);
            border-right-color: var(--x-gold);
            border-radius: 50%;
            margin: 0 auto 20px;
            animation: visaSpin 0.85s linear infinite;
        }

        @keyframes visaSpin { to { transform: rotate(360deg); } }

        .visa-submit-card h3 {
            color: var(--x-navy);
            font-size: 1.2rem;
            margin-bottom: 8px;
        }

        .visa-submit-card p {
            color: var(--x-muted);
            font-size: 0.9rem;
        }

        .visa-submit-dots {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 20px;
        }

        .visa-submit-dots span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--x-gold);
            animation: visaDot 1.2s ease-in-out infinite;
        }

        .visa-submit-dots span:nth-child(2) { animation-delay: 0.15s; }
        .visa-submit-dots span:nth-child(3) { animation-delay: 0.3s; }

        @keyframes visaDot {
            0%, 80%, 100% { transform: scale(0.65); opacity: 0.4; }
            40% { transform: scale(1); opacity: 1; }
        }

        @media (max-width: 768px) {
            .visa-card { padding: 22px 18px; }
            .visa-stepper::before { left: 12%; right: 12%; }
            .form-buttons .btn { flex: 1; min-width: 140px; }
        }
    </style>
</head>
<body class="visa-form-page">
<div class="visa-shell">
    <header class="visa-hero">
        <div class="visa-hero-inner">
            <h1><i class="fas fa-passport" style="opacity:.9"></i> Visa application</h1>
            <p>Complete your details and documents in two quick steps. Progress saves as you go — uploads go to our servers immediately.</p>
            <div class="visa-meta-row">
                <span class="visa-pill"><i class="fas fa-fingerprint"></i> ID <code><?php echo htmlspecialchars($userId); ?></code></span>
                <?php if ($countryName): ?>
                    <span class="visa-pill visa-pill--gold"><i class="fas fa-flag"></i> <?php echo htmlspecialchars($countryName); ?></span>
                <?php endif; ?>
                <?php if (!empty($data)): ?>
                    <span class="visa-pill" style="background:rgba(13,148,136,.25);border-color:rgba(13,148,136,.4);"><i class="fas fa-floppy-disk"></i> Draft on file</span>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="visa-card">
        <div class="visa-stepper">
            <div class="step active" id="stepIndicator1">
                <div class="step-circle">1</div>
                <div class="step-label">Your details</div>
            </div>
            <div class="step" id="stepIndicator2">
                <div class="step-circle">2</div>
                <div class="step-label">Documents</div>
            </div>
        </div>

        <form id="visaForm" enctype="multipart/form-data">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($userId); ?>">
            <input type="hidden" name="country_id" value="<?php echo htmlspecialchars($countryId); ?>">
            <input type="hidden" name="region_id" value="<?php echo htmlspecialchars($regionId); ?>">
            <input type="hidden" name="step" id="formStep" value="step1">

            <div class="form-step active" id="step1">
                <h2 class="visa-section-title"><i class="fas fa-user-circle"></i> Personal information</h2>
                <p class="visa-section-hint">Fields marked with an asterisk are required. You can return to this step later before you submit.</p>

                <div class="visa-field-grid">
                    <div class="form-group">
                        <label class="required">Application date</label>
                        <input type="date" name="date" value="<?php echo $data['date'] ?? date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="required">Consulting company</label>
                        <select name="company" required>
                            <option value="">Select company</option>
                            <option value="SCHOLARSYNC GLOBAL LTD" <?php echo selected('company', 'SCHOLARSYNC GLOBAL LTD'); ?>>SCHOLARSYNC GLOBAL LTD</option>
                        </select>
                    </div>
                </div>

                <div class="visa-field-grid" style="margin-top:8px;">
                    <div class="form-group">
                        <label class="required">Title</label>
                        <select name="prefix" required>
                            <option value="">Select</option>
                            <option value="Mr." <?php echo selected('prefix', 'Mr.'); ?>>Mr.</option>
                            <option value="Mrs." <?php echo selected('prefix', 'Mrs.'); ?>>Mrs.</option>
                            <option value="Ms." <?php echo selected('prefix', 'Ms.'); ?>>Ms.</option>
                            <option value="Dr." <?php echo selected('prefix', 'Dr.'); ?>>Dr.</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">First name</label>
                        <input type="text" name="first_name" value="<?php echo $data['first_name'] ?? ''; ?>" required autocomplete="given-name">
                    </div>
                    <div class="form-group">
                        <label>Middle name</label>
                        <input type="text" name="middle_name" value="<?php echo $data['middle_name'] ?? ''; ?>" autocomplete="additional-name">
                    </div>
                    <div class="form-group">
                        <label class="required">Last name</label>
                        <input type="text" name="last_name" value="<?php echo $data['last_name'] ?? ''; ?>" required autocomplete="family-name">
                    </div>
                </div>

                <div class="visa-field-grid" style="margin-top:8px;">
                    <div class="form-group">
                        <label class="required">Email</label>
                        <input type="email" name="email" value="<?php echo $data['email'] ?? ''; ?>" required autocomplete="email">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="required">WhatsApp / mobile</label>
                        <p class="visa-mobile-hint">Choose your country code, then enter your number. Stored as <strong>digits only</strong> (international format, no +) for messaging.</p>
                        <input type="tel" id="visa_mobile_tel" autocomplete="tel" value="" required>
                        <input type="hidden" name="applicant_mobile" id="applicant_mobile" value="">
                    </div>
                </div>

                <div class="visa-field-grid" style="margin-top:8px;">
                    <div class="form-group">
                        <label class="required">Date of birth</label>
                        <input type="date" name="birthdate" value="<?php echo $data['birthdate'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="required">Gender</label>
                        <div class="visa-gender-row">
                            <label class="visa-radio-pill"><input type="radio" name="gender" value="Male" required <?php echo checked('gender', 'Male'); ?>> Male</label>
                            <label class="visa-radio-pill"><input type="radio" name="gender" value="Female" required <?php echo checked('gender', 'Female'); ?>> Female</label>
                            <label class="visa-radio-pill"><input type="radio" name="gender" value="Other" required <?php echo checked('gender', 'Other'); ?>> Other</label>
                        </div>
                    </div>
                </div>

                <div class="visa-field-grid" style="margin-top:8px;">
                    <div class="form-group">
                        <label class="required">Passport number</label>
                        <input type="text" name="passport_number" value="<?php echo $data['passport_number'] ?? ''; ?>" required>
                    </div>
                </div>

                <div class="visa-field-grid" style="margin-top:8px;">
                    <div class="form-group">
                        <label class="required">Applying from</label>
                        <select name="country_applying_from" required>
                            <option value="">Select country</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?php echo $country['name']; ?>" <?php echo selected('country_applying_from', $country['name']); ?>>
                                    <?php echo $country['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">Country to visit</label>
                        <select name="country_to_visit" required>
                            <option value="">Select country</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?php echo $country['name']; ?>"
                                    <?php echo selected('country_to_visit', $country['name']); ?>
                                    <?php echo ($countryName && $country['name'] == $countryName) ? 'selected' : ''; ?>>
                                    <?php echo $country['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top:20px;">
                    <label class="required">Visa type</label>
                    <div class="visa-type-grid">
                        <?php foreach ($visaTypes as $value => $label): ?>
                            <label class="visa-radio-pill">
                                <input type="radio" name="visa_type" value="<?php echo htmlspecialchars($value); ?>" required <?php echo checked('visa_type', $value); ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-buttons" style="justify-content:flex-end;">
                    <button type="button" class="btn btn-primary" id="btnContinueStep1" onclick="saveStep('step1')">
                        Continue to documents <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <div class="form-step" id="step2">
                <h2 class="visa-section-title"><i class="fas fa-folder-open"></i> Documents</h2>
                <p class="visa-section-hint">Upload each file — it is sent to our servers right away. Required items show a green check when ready.</p>

                <!-- File Upload Templates -->
                <?php 
                $fileSections = [
                    'passport_copy' => [
                        'title' => 'Passport Copy',
                        'required' => true,
                        'accept' => '.pdf,.jpg,.jpeg,.png',
                        'icon' => 'fa-passport'
                    ],
                    'academic_documents' => [
                        'title' => 'Academic Documents',
                        'required' => true,
                        'accept' => '.pdf,.jpg,.jpeg,.png,.doc,.docx',
                        'icon' => 'fa-graduation-cap'
                    ],
                    'old_visa_copy' => [
                        'title' => 'Previous Visa Copy',
                        'required' => true,
                        'accept' => '.pdf,.jpg,.jpeg,.png',
                        'icon' => 'fa-stamp'
                    ],
                    'passport_photo' => [
                        'title' => 'Passport Photo',
                        'required' => true,
                        'accept' => '.jpg,.jpeg,.png',
                        'icon' => 'fa-camera'
                    ],
                    'cv' => [
                        'title' => 'Curriculum Vitae (CV)',
                        'required' => true,
                        'accept' => '.pdf,.doc,.docx',
                        'icon' => 'fa-user-tie'
                    ],
                    'signature' => [
                        'title' => 'Digital Signature',
                        'required' => false,
                        'accept' => '.jpg,.jpeg,.png,.pdf',
                        'icon' => 'fa-signature'
                    ]
                ];
                
                foreach ($fileSections as $field => $section): 
                    $hasFile = isFileUploaded($field);
                ?>
                    <div class="file-upload-section">
                        <h4>
                            <i class="fas <?php echo $section['icon']; ?>"></i>
                            <?php echo $section['title']; ?>
                            <?php if ($section['required']): ?>
                                <span style="color: var(--x-danger); font-size: 0.75rem; font-weight: 700;">Required</span>
                            <?php endif; ?>
                        </h4>
                        
                        <?php if ($hasFile): ?>
                            <div class="existing-file">
                                <div class="existing-file-info">
                                    <i class="fas fa-check-circle"></i>
                                    <div>
                                        <div class="file-name">File already uploaded</div>
                                        <div style="font-size: 12px; color: #6c757d;">Click replace to upload a new file</div>
                                    </div>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-secondary" onclick="replaceFile('<?php echo $field; ?>')">
                                        <i class="fas fa-sync-alt"></i> Replace
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="file-upload-card <?php echo $hasFile ? 'has-file' : ''; ?>" 
                             id="<?php echo $field; ?>_card"
                             style="<?php echo $hasFile ? 'display: none;' : ''; ?>">
                            <div class="file-checkmark">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="file-icon">
                                <i class="fas <?php echo $section['icon']; ?>"></i>
                            </div>
                            <div class="file-title">Click to upload</div>
                            <div class="file-subtitle">or drag and drop</div>
                            <div class="file-subtitle" style="font-size: 12px;">
                                <?php echo strtoupper(str_replace('.', '', $section['accept'])); ?> • Max 10MB
                            </div>
                            
                            <input type="file" 
                                   name="<?php echo $field; ?>" 
                                   id="<?php echo $field; ?>" 
                                   accept="<?php echo $section['accept']; ?>"
                                   style="display: none;"
                                   <?php if ($section['required'] && !$hasFile): ?>required<?php endif; ?>>
                            
                            <div class="file-status">
                                <?php if ($hasFile): ?>
                                    <span class="status-ready"><i class="fas fa-check"></i> Ready</span>
                                <?php else: ?>
                                    <span>No file selected</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="progress-bar" id="<?php echo $field; ?>_progress">
                                <div class="progress-fill" id="<?php echo $field; ?>_progress_fill"></div>
                            </div>
                        </div>
                        
                        <div class="file-info" id="<?php echo $field; ?>_info">
                            <div class="file-info-header">
                                <div>
                                    <div class="file-name" id="<?php echo $field; ?>_name"></div>
                                    <div style="font-size: 12px; color: #6c757d;" id="<?php echo $field; ?>_size"></div>
                                </div>
                                <button type="button" class="remove-btn" onclick="removeFile('<?php echo $field; ?>')">
                                    <i class="fas fa-times"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="visa-terms">
                    <label>
                        <input type="checkbox" name="terms" value="agree" required>
                        <span>I agree to the terms and conditions and confirm the information provided is accurate.</span>
                    </label>
                </div>

                <div class="form-buttons">
                    <button type="button" class="btn btn-secondary" id="btnBackStep2" onclick="prevStep()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitVisa">
                        Submit application <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div id="visaToastWrap" class="visa-toast-wrap" aria-live="polite"><div id="visaToast" class="visa-toast"></div></div>

<div id="visaSubmitOverlay" class="visa-submit-overlay" aria-hidden="true">
    <div class="visa-submit-card">
        <div class="spinner" aria-hidden="true"></div>
        <h3 id="visaOverlayTitle">Submitting your application</h3>
        <p id="visaOverlayText">Securing your documents and confirming your details…</p>
        <div class="visa-submit-dots" aria-hidden="true"><span></span><span></span><span></span></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script>
window.VISA_MOBILE_E164_DIGITS_INIT = <?php echo json_encode($visaMobileE164DigitsInit ?? '', JSON_UNESCAPED_UNICODE); ?>;
</script>
<script>
const CONFIG = {
    maxFileSize: 10 * 1024 * 1024,
    saveEndpoint: 'save_visa.php'
};

let currentStep = 1;
const fileStatus = {};

function showToast(message, type) {
    const wrap = document.getElementById('visaToastWrap');
    const el = document.getElementById('visaToast');
    if (!wrap || !el) return;
    el.className = 'visa-toast' + (type === 'error' ? ' error' : type === 'success' ? ' success' : '');
    const icon = type === 'error' ? 'fa-circle-exclamation' : type === 'success' ? 'fa-circle-check' : 'fa-circle-info';
    el.innerHTML = '<i class="fas ' + icon + '"></i><span>' + message + '</span>';
    wrap.classList.add('visible');
    clearTimeout(showToast._t);
    showToast._t = setTimeout(function () { wrap.classList.remove('visible'); }, 4200);
}

function setSubmitOverlay(visible, title, text) {
    const o = document.getElementById('visaSubmitOverlay');
    if (!o) return;
    if (title) document.getElementById('visaOverlayTitle').textContent = title;
    if (text) document.getElementById('visaOverlayText').textContent = text;
    o.classList.toggle('visible', !!visible);
    o.setAttribute('aria-hidden', visible ? 'false' : 'true');
}

let overlayRotateTimer = null;
function startOverlayPulse() {
    const lines = [
        ['Checking your documents', 'Making sure every required file is on file…'],
        ['Almost there', 'Finalizing your application securely…'],
        ['One moment', 'Talking to our servers…']
    ];
    let i = 0;
    clearInterval(overlayRotateTimer);
    overlayRotateTimer = setInterval(function () {
        i = (i + 1) % lines.length;
        document.getElementById('visaOverlayTitle').textContent = lines[i][0];
        document.getElementById('visaOverlayText').textContent = lines[i][1];
    }, 900);
}

function stopOverlayPulse() {
    clearInterval(overlayRotateTimer);
    overlayRotateTimer = null;
}

function showStep(step) {
    document.querySelectorAll('.form-step').forEach(function (el) { el.classList.remove('active'); });
    document.getElementById('step' + step).classList.add('active');
    document.querySelectorAll('.visa-stepper .step').forEach(function (el, idx) {
        el.classList.remove('active', 'completed');
        if (idx + 1 < step) el.classList.add('completed');
        if (idx + 1 === step) el.classList.add('active');
    });
    currentStep = step;
    document.getElementById('formStep').value = 'step' + step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function prevStep() {
    if (currentStep > 1) showStep(currentStep - 1);
}

function getUserId() {
    var inp = document.querySelector('input[name="user_id"]');
    return inp ? inp.value : '';
}

function uploadFileToServer(file, field, onProgress) {
    return new Promise(function (resolve, reject) {
        var fd = new FormData();
        fd.append('user_id', getUserId());
        fd.append('action', 'upload_file');
        fd.append('field', field);
        fd.append('file', file);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', CONFIG.saveEndpoint);
        xhr.onload = function () {
            try {
                var j = JSON.parse(xhr.responseText);
                if (j.status === 'success') resolve(j);
                else reject(new Error(j.message || 'Upload failed'));
            } catch (e) {
                reject(new Error('Invalid server response'));
            }
        };
        xhr.onerror = function () { reject(new Error('Network error')); };
        if (xhr.upload && onProgress) {
            xhr.upload.onprogress = function (ev) {
                if (ev.lengthComputable) onProgress(ev.loaded / ev.total);
            };
        }
        xhr.send(fd);
    });
}

document.querySelectorAll('.file-upload-card').forEach(function (card) {
    var field = card.id.replace('_card', '');
    var input = document.getElementById(field);
    if (!input) return;

    if (card.classList.contains('has-file')) {
        fileStatus[field] = { uploaded: true, server: true, fileName: 'Saved', fileSize: 0 };
    }

    card.addEventListener('click', function () { input.click(); });

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (eventName) {
        card.addEventListener(eventName, function (e) { e.preventDefault(); e.stopPropagation(); });
    });
    card.addEventListener('dragenter', function () { card.classList.add('dragover'); });
    card.addEventListener('dragover', function () { card.classList.add('dragover'); });
    card.addEventListener('dragleave', function () { card.classList.remove('dragover'); });
    card.addEventListener('drop', function (e) {
        card.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            handleFileSelect(input.files[0], field);
        }
    });
    input.addEventListener('change', function () {
        if (input.files.length) handleFileSelect(input.files[0], field);
    });
    if (!fileStatus[field]) {
        fileStatus[field] = { uploaded: false, fileName: '', fileSize: 0 };
    }
});

function handleFileSelect(file, field) {
    var input = document.getElementById(field);
    if (file.size > CONFIG.maxFileSize) {
        showToast('File is too large. Maximum size is 10MB.', 'error');
        return;
    }
    var ext = file.name.split('.').pop().toLowerCase();
    var allowedExts = document.getElementById(field).accept.replace(/\./g, '').split(',');
    if (allowedExts.indexOf(ext) === -1) {
        showToast('That file type is not allowed for this field.', 'error');
        return;
    }

    var card = document.getElementById(field + '_card');
    var info = document.getElementById(field + '_info');
    var progress = document.getElementById(field + '_progress');
    var progressFill = document.getElementById(field + '_progress_fill');
    var fileName = document.getElementById(field + '_name');
    var fileSize = document.getElementById(field + '_size');

    card.classList.add('uploading');
    card.classList.remove('has-file');
    info.classList.remove('show');
    progress.style.display = 'block';
    progressFill.style.width = '0%';
    fileName.textContent = file.name;
    fileSize.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';

    uploadFileToServer(file, field, function (pct) {
        progressFill.style.width = Math.round(pct * 100) + '%';
    }).then(function () {
        card.classList.remove('uploading');
        card.classList.add('has-file');
        info.classList.add('show');
        progressFill.style.width = '100%';
        setTimeout(function () { progress.style.display = 'none'; }, 400);
        fileStatus[field] = { uploaded: true, server: true, fileName: file.name, fileSize: file.size };
        showToast('Uploaded: ' + file.name, 'success');
    }).catch(function (err) {
        card.classList.remove('uploading');
        progress.style.display = 'none';
        progressFill.style.width = '0%';
        input.value = '';
        showToast(err.message || 'Upload failed', 'error');
    });
}

function removeFile(field) {
    var card = document.getElementById(field + '_card');
    var info = document.getElementById(field + '_info');
    var input = document.getElementById(field);
    card.classList.remove('has-file');
    info.classList.remove('show');
    input.value = '';
    fileStatus[field] = { uploaded: false, fileName: '', fileSize: 0 };
}

function replaceFile(field) {
    var section = document.querySelector('#' + field + '_card').closest('.file-upload-section');
    var existingDiv = section.querySelector('.existing-file');
    var card = document.getElementById(field + '_card');
    var input = document.getElementById(field);
    if (existingDiv) existingDiv.style.display = 'none';
    card.style.display = '';
    input.value = '';
    if (input.hasAttribute('required')) { /* keep */ }
    removeFile(field);
}

function syncVisaMobileHidden() {
    var hidden = document.getElementById('applicant_mobile');
    if (!hidden || !window.visaMobileIti) return false;
    if (!visaMobileIti.isValidNumber()) {
        hidden.value = '';
        return false;
    }
    var digits = visaMobileIti.getNumber().replace(/\D/g, '');
    hidden.value = digits;
    return digits.length >= 8 && digits.length <= 15;
}

function validateStep1() {
    var telEl = document.getElementById('visa_mobile_tel');
    if (window.visaMobileIti) {
        if (!syncVisaMobileHidden()) {
            if (telEl) telEl.style.borderColor = '#b91c1c';
            showToast('Enter a valid mobile number with country code (saved without + for WhatsApp).', 'error');
            return false;
        }
        if (telEl) telEl.style.borderColor = '';
    }

    var form = document.getElementById('step1');
    var required = form.querySelectorAll('[required]');
    var valid = true;
    required.forEach(function (field) {
        if (field.type === 'radio') {
            var radioGroup = form.querySelectorAll('[name="' + field.name + '"]:checked');
            var grp = field.closest('.form-group');
            if (radioGroup.length === 0) {
                if (grp) grp.style.outline = '2px solid #b91c1c';
                valid = false;
            } else if (grp) grp.style.outline = '';
            return;
        }
        if (!String(field.value || '').trim()) {
            field.style.borderColor = '#b91c1c';
            valid = false;
        } else {
            field.style.borderColor = '';
        }
    });
    if (!valid) showToast('Please complete all required fields in step 1.', 'error');
    return valid;
}

function fileFieldReady(field) {
    var card = document.getElementById(field + '_card');
    if (!card) return false;
    var section = card.closest('.file-upload-section');
    var existingDiv = section.querySelector('.existing-file');
    var hasExisting = existingDiv && existingDiv.style.display !== 'none';
    return card.classList.contains('has-file') || hasExisting;
}

function saveStep(stepName) {
    if (stepName === 'step1' && !validateStep1()) return;

    if (stepName === 'step2') {
        var requiredFiles = ['passport_copy', 'academic_documents', 'old_visa_copy', 'passport_photo', 'cv'];
        var ok = true;
        requiredFiles.forEach(function (field) {
            var card = document.getElementById(field + '_card');
            if (!fileFieldReady(field)) {
                card.style.borderColor = '#b91c1c';
                ok = false;
                setTimeout(function () { card.style.borderColor = ''; }, 2200);
            }
        });
        if (!ok) {
            showToast('Please upload all required documents.', 'error');
            return;
        }
    }

    document.getElementById('formStep').value = stepName;
    if (stepName === 'step1' && !syncVisaMobileHidden()) {
        showToast('Please enter a valid mobile number with country code.', 'error');
        return;
    }

    var btn = document.getElementById('btnContinueStep1');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Saving…'; }

    var formData = new FormData(document.getElementById('visaForm'));
    fetch(CONFIG.saveEndpoint, { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.status === 'success') {
                var stepNum = parseInt(stepName.replace('step', ''), 10);
                showStep(stepNum + 1);
                showToast('Saved. Continue with your documents.', 'success');
            } else {
                showToast(data.message || 'Could not save. Try again.', 'error');
            }
        })
        .catch(function () { showToast('Network error. Check your connection.', 'error'); })
        .finally(function () {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = 'Continue to documents <i class="fas fa-arrow-right"></i>';
            }
        });
}

document.getElementById('visaForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validateStep1()) {
        showStep(1);
        return;
    }
    if (!document.querySelector('input[name="terms"]').checked) {
        showToast('Please accept the terms and conditions.', 'error');
        return;
    }
    if (window.visaMobileIti && !syncVisaMobileHidden()) {
        showToast('Please enter a valid mobile number with country code.', 'error');
        return;
    }

    var requiredFiles = ['passport_copy', 'academic_documents', 'old_visa_copy', 'passport_photo', 'cv'];
    var missing = requiredFiles.filter(function (f) { return !fileFieldReady(f); });
    if (missing.length) {
        showToast('Upload every required document before submitting.', 'error');
        return;
    }

    var submitBtn = document.getElementById('btnSubmitVisa');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Sending…';

    requestAnimationFrame(function () {
        setSubmitOverlay(true, 'Submitting your application', 'Securing your documents and confirming your details…');
        startOverlayPulse();
    });

    var formData = new FormData(document.getElementById('visaForm'));
    fetch(CONFIG.saveEndpoint, { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            stopOverlayPulse();
            if (data.status === 'success') {
                stopOverlayPulse();
                document.getElementById('visaOverlayTitle').textContent = 'You\'re all set';
                document.getElementById('visaOverlayText').textContent = 'Redirecting to your confirmation…';
                if (data.redirect && typeof data.redirect === 'string') {
                    setTimeout(function () { window.location.href = data.redirect; }, 450);
                } else {
                    setSubmitOverlay(false);
                    showToast('Application submitted successfully.', 'success');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Submit application <i class="fas fa-paper-plane"></i>';
                }
            } else {
                setSubmitOverlay(false);
                showToast(data.message || 'Submission failed.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Submit application <i class="fas fa-paper-plane"></i>';
            }
        })
        .catch(function (err) {
            stopOverlayPulse();
            setSubmitOverlay(false);
            showToast(err.message || 'Submission error.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Submit application <i class="fas fa-paper-plane"></i>';
        });
});

document.addEventListener('DOMContentLoaded', function () {
    var telInput = document.getElementById('visa_mobile_tel');
    if (telInput && window.intlTelInput) {
        window.visaMobileIti = window.intlTelInput(telInput, {
            initialCountry: 'auto',
            geoIpLookup: function (cb) {
                fetch('https://ipapi.co/json/')
                    .then(function (r) { return r.json(); })
                    .then(function (d) { cb((d && d.country_code) ? d.country_code : 'us'); })
                    .catch(function () { cb('us'); });
            },
            utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js',
            separateDialCode: true,
            preferredCountries: ['us', 'gb', 'ca', 'au', 'rw', 'za', 'ng', 'ke']
        });
        var initDigits = window.VISA_MOBILE_E164_DIGITS_INIT || '';
        if (initDigits.length >= 8) {
            try { visaMobileIti.setNumber('+' + initDigits); } catch (e) { /* ignore */ }
        }
    }
    showStep(1);
});
</script>

</body>
</html>