<?php
// Upload page locked to allowed materials pCloud folders only
session_start();
require_once __DIR__ . '/includes/company_branding.php';
require_once __DIR__ . '/helpers/materials_pcloud.php';

$ALLOWED_FOLDER_IDS = pcvc_materials_allowed_folder_ids();
$MATERIALS_FOLDERS = [];
foreach ($ALLOWED_FOLDER_IDS as $fid) {
    $info = pcvc_materials_list_folder((int)$fid, false);
    $MATERIALS_FOLDERS[] = [
        'folderid' => (int)$fid,
        'name' => $info['name'] ?? ('Folder ' . $fid),
    ];
}
$DEFAULT_FOLDER_ID = $ALLOWED_FOLDER_IDS[0];

// Check if user is logged in (optional - uncomment if you want authentication)
// if (!isset($_SESSION['id'])) {
//     header("Location: login.php");
//     exit();
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ScholarSync - File Manager</title>
    
    <!-- Brand color variables -->
    <style>
        :root {
            --deep-navy: #427431;
            --secondary-blue: #3661B9;
            --dark-blue: #2f5a26;
            --gold: #E21D1E;
            --white: #FFFFFF;
            --light-bg: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --success: #2e7d32;
            --danger: #c62828;
            --warning: #ed6c02;
            --info: #0288d1;
            --border-light: #e2e8f0;
        }
    </style>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(180deg, var(--white) 0%, #f0f4f8 100%);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* ===== Brand header ===== */
        .pcvc-brand-banner {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            padding: 12px 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 3px solid var(--gold);
        }

        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo-main {
            font-size: 2rem;
            font-weight: 800;
            color: var(--white);
            letter-spacing: 1px;
            position: relative;
            display: inline-block;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .logo-main::after {
            content: '📁';
            position: absolute;
            top: -8px;
            right: -35px;
            font-size: 1.8rem;
            filter: drop-shadow(2px 2px 2px rgba(0,0,0,0.3));
        }

        .logo-subtitle {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--gold);
            letter-spacing: 1px;
            border-left: 3px solid var(--gold);
            padding-left: 20px;
            text-transform: uppercase;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }

        /* ===== MAIN CONTAINER ===== */
        .main-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 24px;
        }

        /* ===== PAGE HEADER ===== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--deep-navy);
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }

        .page-title i {
            color: var(--gold);
            font-size: 32px;
        }

        .folder-badge {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            border-left: 3px solid var(--gold);
        }

        .folder-badge i {
            color: var(--gold);
        }

        /* ===== UPLOAD CARD ===== */
        .upload-card {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(1, 47, 107, 0.12);
            border: 1px solid var(--border-light);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .upload-header {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f6 100%);
            padding: 16px 24px;
            border-bottom: 2px solid var(--gold);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .upload-header h5 {
            margin: 0;
            font-weight: 700;
            color: var(--deep-navy);
            font-size: 18px;
        }

        .upload-header i {
            color: var(--gold);
            font-size: 24px;
        }

        .upload-body {
            padding: 30px;
        }

        /* ===== DROP ZONE ===== */
        .drop-zone {
            border: 3px dashed var(--border-light);
            border-radius: 16px;
            padding: 50px 30px;
            text-align: center;
            background: var(--light-bg);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .drop-zone:hover {
            border-color: var(--gold);
            background: rgba(242, 166, 90, 0.02);
        }

        .drop-zone.dragover {
            border-color: var(--deep-navy);
            background: rgba(1, 47, 107, 0.02);
            transform: scale(1.01);
            box-shadow: 0 10px 30px rgba(1, 47, 107, 0.1);
        }

        .drop-icon {
            font-size: 64px;
            color: var(--deep-navy);
            margin-bottom: 15px;
        }

        .drop-zone h4 {
            font-weight: 700;
            color: var(--deep-navy);
            margin-bottom: 10px;
        }

        .drop-zone p {
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        .btn-browse {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-browse:hover {
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--deep-navy) 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(1, 47, 107, 0.3);
            color: var(--white);
        }

        .btn-browse i {
            font-size: 18px;
        }

        /* ===== SELECTED FILES SECTION ===== */
        .selected-section {
            margin-top: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h5 {
            font-weight: 700;
            color: var(--deep-navy);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge-count {
            background: var(--gold);
            color: var(--deep-navy);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .btn-upload-now {
            background: linear-gradient(135deg, var(--success) 0%, #1b5e20 100%);
            color: var(--white);
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-upload-now:hover:not(:disabled) {
            background: linear-gradient(135deg, #1b5e20 0%, #0a3622 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(46, 125, 50, 0.3);
            color: var(--white);
        }

        .btn-upload-now:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ===== QUEUE GRID ===== */
        .queue-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .queue-item {
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-light);
            box-shadow: 0 4px 12px rgba(1, 47, 107, 0.08);
            position: relative;
            transition: all 0.3s ease;
        }

        .queue-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(1, 47, 107, 0.15);
            border-color: var(--gold);
        }

        .queue-item .preview-container {
            height: 140px;
            overflow: hidden;
            position: relative;
            background: #f8fafc;
        }

        .queue-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .queue-item:hover img {
            transform: scale(1.05);
        }

        .queue-item .file-info {
            padding: 12px;
            background: var(--white);
        }

        .queue-item .file-name {
            font-weight: 600;
            font-size: 13px;
            color: var(--text-dark);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .queue-item .file-size {
            font-size: 11px;
            color: var(--text-muted);
        }

        .remove-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(198, 40, 40, 0.9);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.2s ease;
            z-index: 10;
            opacity: 0;
            transform: scale(0.8);
        }

        .queue-item:hover .remove-btn {
            opacity: 1;
            transform: scale(1);
        }

        .remove-btn:hover {
            background: var(--danger);
            transform: scale(1.1) !important;
        }

        /* ===== PROGRESS CONTAINER ===== */
        .progress-container {
            margin-top: 30px;
            background: var(--light-bg);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border-light);
        }

        .progress-container h6 {
            font-weight: 700;
            color: var(--deep-navy);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .progress {
            height: 12px;
            border-radius: 30px;
            background: var(--border-light);
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--deep-navy), var(--gold));
            transition: width 0.3s ease;
            position: relative;
        }

        #progressText {
            text-align: center;
            margin-top: 10px;
            font-size: 14px;
            font-weight: 600;
            color: var(--deep-navy);
        }

        /* ===== FILES IN FOLDER SECTION ===== */
        .files-section {
            margin-top: 40px;
        }

        .files-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .files-header h3 {
            font-weight: 700;
            color: var(--deep-navy);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .refresh-btn {
            background: var(--white);
            border: 2px solid var(--border-light);
            color: var(--deep-navy);
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .refresh-btn:hover {
            border-color: var(--gold);
            background: rgba(242, 166, 90, 0.05);
            transform: rotate(180deg);
        }

        /* ===== FILE GRID - MATCHING SCREENSHOT ===== */
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .file-card {
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-light);
            box-shadow: 0 2px 8px rgba(1, 47, 107, 0.08);
            transition: all 0.2s ease;
        }

        .file-card:hover {
            border-color: var(--gold);
            box-shadow: 0 8px 20px rgba(1, 47, 107, 0.12);
        }

        .file-preview {
            width: 100%;
            height: 200px;
            background: #f5f7fa;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .file-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .file-icon-large {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f6 100%);
            font-size: 64px;
            color: var(--deep-navy);
        }

        .file-info {
            padding: 16px;
        }

        .file-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 16px;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .file-type {
            background: var(--light-bg);
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            color: var(--deep-navy);
            text-transform: uppercase;
        }

        .file-size {
            font-size: 13px;
            color: var(--text-muted);
        }

        .file-actions {
            display: flex;
            gap: 10px;
        }

        .btn-download {
            flex: 1;
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 10px 0;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s ease;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-download:hover {
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--deep-navy) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(1, 47, 107, 0.2);
            color: var(--white);
        }

        .btn-delete {
            width: 42px;
            height: 42px;
            background: var(--white);
            border: 2px solid var(--danger);
            color: var(--danger);
            border-radius: 6px;
            font-size: 18px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .btn-delete:hover {
            background: var(--danger);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(198, 40, 40, 0.2);
        }

        .file-type-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(1, 47, 107, 0.9);
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
            border: 1px solid var(--gold);
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--light-bg);
            border-radius: 16px;
            border: 2px dashed var(--border-light);
            grid-column: 1 / -1;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: var(--deep-navy);
        }

        .empty-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .empty-text {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* ===== LOADING OVERLAY ===== */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border-light);
            border-radius: 50%;
            border-top-color: var(--deep-navy);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ===== TOAST NOTIFICATIONS ===== */
        .toast-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 9998;
        }

        .toast-notification {
            background: var(--white);
            border-radius: 8px;
            padding: 12px 20px;
            margin-top: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid;
            animation: slideIn 0.3s ease;
        }

        .toast-notification.success {
            border-left-color: var(--success);
        }

        .toast-notification.error {
            border-left-color: var(--danger);
        }

        .toast-notification.info {
            border-left-color: var(--info);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* ===== SKELETON LOADING ===== */
        .skeleton-preview {
            width: 100%;
            height: 200px;
            background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }

        .skeleton-line {
            height: 16px;
            background: #e2e8f0;
            border-radius: 4px;
            margin: 8px 16px;
        }

        .skeleton-line.short {
            width: 60%;
        }

        .skeleton-line.medium {
            width: 80%;
        }

        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header-container {
                padding: 0 16px;
            }
            
            .logo-main {
                font-size: 1.5rem;
            }
            
            .logo-main::after {
                right: -25px;
                font-size: 1.3rem;
            }
            
            .logo-subtitle {
                font-size: 1rem;
                padding-left: 12px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .upload-body {
                padding: 20px;
            }
            
            .drop-zone {
                padding: 30px 15px;
            }
            
            .drop-icon {
                font-size: 48px;
            }
            
            .queue-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 15px;
            }
            
            .file-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .file-preview {
                height: 180px;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .btn-upload-now {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .logo-container {
                gap: 10px;
            }
            
            .logo-main {
                font-size: 1.3rem;
            }
            
            .logo-subtitle {
                font-size: 0.8rem;
                padding-left: 8px;
            }
            
            .queue-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Print styles */
        @media print {
            .pcvc-brand-banner,
            .upload-card,
            .btn-upload-now,
            .btn-delete,
            .refresh-btn {
                display: none !important;
            }
        }
    </style>
</head>

<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Brand header -->
<div class="pcvc-brand-banner">
    <div class="header-container">
        <div class="logo-container">
            <div class="logo-main"><?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="logo-subtitle">File manager</div>
        </div>
    </div>
</div>

<main class="main-container">
    
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="bi bi-cloud-upload"></i>
            Upload Materials
        </h1>
        
        <div class="folder-badge">
            <i class="bi bi-folder-check"></i>
            Allowed folders only
        </div>
    </div>
    
    <!-- Upload Card -->
    <div class="upload-card">
        <div class="upload-header">
            <i class="bi bi-upload"></i>
            <h5>Upload New Files</h5>
        </div>
        
        <div class="upload-body">
            <!-- Destination folder (whitelist only) -->
            <div class="mb-4">
                <label for="folderSelect" class="form-label fw-semibold" style="color: var(--deep-navy);">
                    <i class="bi bi-folder2"></i> Upload destination folder
                </label>
                <select id="folderSelect" class="form-select" style="max-width: 480px;">
                    <?php foreach ($MATERIALS_FOLDERS as $folder): ?>
                        <option value="<?= (int)$folder['folderid'] ?>">
                            <?= htmlspecialchars($folder['name'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$folder['folderid'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Uploads are restricted to these two pCloud folders only.</div>
            </div>

            <!-- Drop Zone -->
            <div class="drop-zone" id="dropZone">
                <div class="drop-icon">
                    <i class="bi bi-cloud-arrow-up"></i>
                </div>
                <h4>Drag & Drop files here</h4>
                <p>or click to browse from your computer</p>
                <input type="file" id="fileInput" hidden multiple>
                <button class="btn-browse" id="chooseBtn">
                    <i class="bi bi-folder2-open"></i>
                    Choose Files
                </button>
            </div>
            
            <!-- Selected Files Section -->
            <div class="selected-section" id="selectedSection" style="display: none;">
                <div class="section-header">
                    <h5>
                        <i class="bi bi-files"></i>
                        Files Selected
                        <span class="badge-count" id="selectedCount">0</span>
                    </h5>
                    
                    <button class="btn-upload-now" id="uploadNowBtn" disabled>
                        <i class="bi bi-cloud-upload"></i>
                        Upload Selected Files
                    </button>
                </div>
                
                <!-- Queue Grid -->
                <div id="queueGrid" class="queue-grid"></div>
            </div>
            
            <!-- Progress Container -->
            <div class="progress-container" id="progressContainer" style="display: none;">
                <h6>
                    <i class="bi bi-arrow-repeat"></i>
                    Upload in Progress...
                </h6>
                <div class="progress">
                    <div id="progressBar" class="progress-bar" style="width: 0%"></div>
                </div>
                <div id="progressText">0% uploaded</div>
            </div>
        </div>
    </div>
    
    <!-- Files in allowed materials folders -->
    <div class="files-section">
        <div class="files-header">
            <h3>
                <i class="bi bi-folder2-open"></i>
                Files List
            </h3>
            
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <select id="viewFolderSelect" class="form-select" style="width: auto; min-width: 260px;">
                    <option value="all">All allowed folders</option>
                    <?php foreach ($MATERIALS_FOLDERS as $folder): ?>
                        <option value="<?= (int)$folder['folderid'] ?>">
                            <?= htmlspecialchars($folder['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="refresh-btn" onclick="loadFiles()">
                    <i class="bi bi-arrow-clockwise"></i>
                    Refresh
                </button>
            </div>
        </div>
        
        <!-- Files Grid -->
        <div id="filesGrid" class="file-grid">
            <!-- Files will be loaded here -->
        </div>
    </div>
</main>

<script>
const ALLOWED_FOLDER_IDS = <?= json_encode(array_map('intval', $ALLOWED_FOLDER_IDS), JSON_UNESCAPED_SLASHES) ?>;
const MATERIALS_FOLDERS = <?= json_encode($MATERIALS_FOLDERS, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const DEFAULT_FOLDER_ID = <?= (int)$DEFAULT_FOLDER_ID ?>;
const ACCESS_TOKEN = <?= json_encode(PCVC_MATERIALS_PCLOUD_TOKEN) ?>;
let uploadQueue = [];

const fileInput = document.getElementById("fileInput");
const dropZone = document.getElementById("dropZone");
const queueGrid = document.getElementById("queueGrid");
const uploadNowBtn = document.getElementById("uploadNowBtn");
const selectedSection = document.getElementById("selectedSection");
const selectedCount = document.getElementById("selectedCount");
const loadingOverlay = document.getElementById("loadingOverlay");
const toastContainer = document.getElementById("toastContainer");
const folderSelect = document.getElementById("folderSelect");
const viewFolderSelect = document.getElementById("viewFolderSelect");

function getSelectedUploadFolderId() {
    const id = parseInt(folderSelect.value, 10);
    return ALLOWED_FOLDER_IDS.includes(id) ? id : DEFAULT_FOLDER_ID;
}

function isAllowedFolderId(folderId) {
    return ALLOWED_FOLDER_IDS.includes(parseInt(folderId, 10));
}

// ===== TOAST NOTIFICATION SYSTEM =====
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ===== FORMAT FILE SIZE =====
function formatFileSize(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// ===== GET THUMBNAIL URL =====
function getThumbnailUrl(fileId) {
    return `https://api.pcloud.com/getthumb?fileid=${fileId}&access_token=${ACCESS_TOKEN}&size=256x256&type=auto`;
}

// ===== GET DOWNLOAD LINK =====
function getDownloadLink(fileId, filename) {
    return `https://api.pcloud.com/getfilelink?fileid=${fileId}&access_token=${ACCESS_TOKEN}&filename=${encodeURIComponent(filename)}`;
}

// ===== LOAD FILES FROM ALLOWED MATERIALS FOLDERS ONLY =====
async function loadFiles() {
    const grid = document.getElementById("filesGrid");
    
    // Show skeleton loading
    grid.innerHTML = Array(4).fill(0).map(() => `
        <div class="file-card">
            <div class="skeleton-preview"></div>
            <div class="skeleton-line"></div>
            <div class="skeleton-line short"></div>
            <div style="display: flex; gap: 10px; padding: 16px;">
                <div class="skeleton-line" style="flex: 1; height: 42px;"></div>
                <div class="skeleton-line" style="width: 42px; height: 42px;"></div>
            </div>
        </div>
    `).join('');
    
    try {
        const viewValue = viewFolderSelect.value;
        const folderIds = viewValue === 'all'
            ? ALLOWED_FOLDER_IDS.slice()
            : (isAllowedFolderId(viewValue) ? [parseInt(viewValue, 10)] : ALLOWED_FOLDER_IDS.slice());

        const files = [];
        const seen = new Set();

        for (const folderId of folderIds) {
            if (!isAllowedFolderId(folderId)) continue;

            const response = await fetch(`https://api.pcloud.com/listfolder?folderid=${folderId}&recursive=1&access_token=${ACCESS_TOKEN}`);
            const data = await response.json();

            if (!data || data.result !== 0 || !data.metadata) {
                continue;
            }

            const folderName = data.metadata.name || ('Folder ' + folderId);
            const collect = (items) => {
                (items || []).forEach(item => {
                    if (item.isfolder) {
                        if (item.contents) collect(item.contents);
                        return;
                    }
                    if (seen.has(item.fileid)) return;
                    seen.add(item.fileid);
                    files.push({ ...item, source_folderid: folderId, source_foldername: folderName });
                });
            };
            collect(data.metadata.contents || []);
        }
        
        grid.innerHTML = "";
        
        if (files.length === 0) {
            grid.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">📁</div>
                    <div class="empty-title">No Files Found</div>
                    <div class="empty-text">Upload your first file to get started</div>
                </div>
            `;
            return;
        }
        
        // Sort files by name
        files.sort((a, b) => a.name.localeCompare(b.name));
        
        // Process each file
        for (const file of files) {
            const isImage = file.name.match(/\.(jpg|jpeg|png|gif|webp|bmp)$/i);
            const fileSize = formatFileSize(file.size);
            const fileExt = file.name.split('.').pop().toUpperCase() || 'FILE';
            
            const fileCard = document.createElement('div');
            fileCard.className = 'file-card';
            
            // Get download link
            let downloadUrl = '#';
            try {
                const linkResponse = await fetch(`https://api.pcloud.com/getfilelink?fileid=${file.fileid}&access_token=${ACCESS_TOKEN}`);
                const linkData = await linkResponse.json();
                if (linkData && linkData.hosts && linkData.hosts[0]) {
                    downloadUrl = `https://${linkData.hosts[0]}${linkData.path}`;
                }
            } catch (e) {
                console.error('Error getting download link:', e);
            }
            
            // Preview section
            let previewHtml = '';
            if (isImage) {
                const thumbUrl = getThumbnailUrl(file.fileid);
                previewHtml = `
                    <div class="file-preview">
                        <img src="${thumbUrl}" alt="${file.name}" 
                             onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'file-icon-large\'><i class=\'bi bi-image\'></i></div>'">
                        <span class="file-type-badge">${fileExt}</span>
                    </div>
                `;
            } else {
                previewHtml = `
                    <div class="file-preview">
                        <div class="file-icon-large">
                            <i class="bi bi-file-earmark"></i>
                        </div>
                        <span class="file-type-badge">${fileExt}</span>
                    </div>
                `;
            }
            
            fileCard.innerHTML = `
                ${previewHtml}
                <div class="file-info">
                    <div class="file-name" title="${file.name}">${file.name}</div>
                    <div class="file-meta">
                        <span class="file-type">${fileExt}</span>
                        <span class="file-size">${fileSize}</span>
                    </div>
                    <div class="file-meta" style="margin-top: -8px; margin-bottom: 12px;">
                        <span class="file-size">${file.source_foldername || ''}</span>
                    </div>
                    <div class="file-actions">
                        <a href="${downloadUrl}" class="btn-download" target="_blank">
                            <i class="bi bi-download"></i> Download
                        </a>
                        <button class="btn-delete" onclick="deleteFile(${file.fileid})" title="Delete file">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            
            grid.appendChild(fileCard);
        }
        
    } catch (error) {
        console.error("Error loading files:", error);
        showToast("Error connecting to server", "error");
        grid.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">⚠️</div>
                <div class="empty-title">Connection Error</div>
                <div class="empty-text">Please try refreshing the page</div>
            </div>
        `;
    }
}

// ===== ADD FILES TO QUEUE =====
function addToQueue(files) {
    for (let file of files) {
        // Check for duplicates
        const isDuplicate = uploadQueue.some(f => 
            f.name === file.name && f.size === file.size
        );
        
        if (!isDuplicate) {
            uploadQueue.push(file);
        }
    }
    
    renderQueue();
    
    if (uploadQueue.length > 0) {
        selectedSection.style.display = 'block';
    }
}

function renderQueue() {
    queueGrid.innerHTML = "";
    uploadNowBtn.disabled = uploadQueue.length === 0;
    selectedCount.textContent = uploadQueue.length;
    
    if (uploadQueue.length === 0) {
        selectedSection.style.display = 'none';
        return;
    }
    
    uploadQueue.forEach((file, index) => {
        const isImage = file.type.startsWith('image/');
        const preview = isImage ? URL.createObjectURL(file) : "https://cdn-icons-png.flaticon.com/512/716/716784.png";
        const fileSize = formatFileSize(file.size);
        
        const item = document.createElement('div');
        item.className = 'queue-item';
        
        item.innerHTML = `
            <div class="preview-container">
                <img src="${preview}" alt="${file.name}">
            </div>
            <div class="file-info">
                <div class="file-name" title="${file.name}">${file.name}</div>
                <div class="file-size">${fileSize}</div>
            </div>
            <button class="remove-btn" onclick="removeFromQueue(${index})">
                <i class="bi bi-x"></i>
            </button>
        `;
        
        queueGrid.appendChild(item);
    });
}

function removeFromQueue(index) {
    uploadQueue.splice(index, 1);
    renderQueue();
}

// ===== DRAG AND DROP HANDLERS =====
dropZone.addEventListener("dragover", (e) => {
    e.preventDefault();
    dropZone.classList.add("dragover");
});

dropZone.addEventListener("dragleave", () => {
    dropZone.classList.remove("dragover");
});

dropZone.addEventListener("drop", (e) => {
    e.preventDefault();
    dropZone.classList.remove("dragover");
    addToQueue(e.dataTransfer.files);
});

// ===== FILE SELECT HANDLER =====
document.getElementById("chooseBtn").onclick = () => {
    fileInput.click();
};

fileInput.onchange = () => {
    addToQueue(fileInput.files);
    fileInput.value = ''; // Reset input
};

// ===== UPLOAD FILES =====
uploadNowBtn.onclick = async () => {
    if (uploadQueue.length === 0) return;

    const folderId = getSelectedUploadFolderId();
    if (!isAllowedFolderId(folderId)) {
        showToast("Upload blocked: invalid folder", "error");
        return;
    }
    
    // Show loading
    loadingOverlay.style.display = 'flex';
    
    const formData = new FormData();
    formData.append("folderid", folderId);
    uploadQueue.forEach(file => formData.append("files[]", file));
    
    try {
        const response = await fetch("upload-pcloud.php", {
            method: "POST",
            body: formData
        });
        
        const result = await response.json();
        
        if (!result.success) {
            showToast("Upload failed: " + (result.message || result.error || "Unknown error"), "error");
            loadingOverlay.style.display = 'none';
            return;
        }
        
        const hash = result.progresshash;
        
        // Show progress container
        const progressContainer = document.getElementById("progressContainer");
        const progressBar = document.getElementById("progressBar");
        const progressText = document.getElementById("progressText");
        
        progressContainer.style.display = "block";
        loadingOverlay.style.display = 'none';
        
        // Poll for progress
        let finished = false;
        
        while (!finished) {
            await new Promise(resolve => setTimeout(resolve, 800));
            
            const progressResponse = await fetch("pcloud-progress.php?hash=" + hash);
            const progressData = await progressResponse.json();
            
            if (!progressData.success) continue;
            
            const percent = progressData.total > 0 
                ? Math.floor((progressData.uploaded / progressData.total) * 100) 
                : 0;
            
            progressBar.style.width = percent + "%";
            progressText.innerText = percent + "% uploaded";
            
            finished = progressData.finished;
        }
        
        progressBar.style.width = "100%";
        progressText.innerText = "Upload complete!";
        
        showToast("Files uploaded successfully!", "success");
        
        // Clear queue and reload files
        uploadQueue = [];
        renderQueue();
        loadFiles();
        
        // Hide progress after 2 seconds
        setTimeout(() => {
            progressContainer.style.display = "none";
        }, 2000);
        
    } catch (error) {
        console.error("Upload error:", error);
        showToast("Upload failed: Connection error", "error");
        loadingOverlay.style.display = 'none';
    }
};

// ===== DELETE FILE =====
async function deleteFile(fileId) {
    if (!confirm("Are you sure you want to delete this file?")) return;
    
    loadingOverlay.style.display = 'flex';
    
    try {
        // Call pCloud API directly to delete the file
        const response = await fetch(`https://api.pcloud.com/deletefile?fileid=${fileId}&access_token=${ACCESS_TOKEN}`);
        const result = await response.json();
        
        if (result && result.result === 0) {
            showToast("File deleted successfully", "success");
            loadFiles(); // Reload the file list
        } else {
            showToast("Delete failed: " + (result.error || "Unknown error"), "error");
        }
    } catch (error) {
        console.error("Delete error:", error);
        showToast("Delete failed: Connection error", "error");
    }
    
    loadingOverlay.style.display = 'none';
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    // Ctrl + U to trigger file upload
    if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
        e.preventDefault();
        fileInput.click();
    }
    
    // Ctrl + Shift + R to refresh files
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'R') {
        e.preventDefault();
        loadFiles();
    }
});

// ===== INITIAL LOAD =====
document.addEventListener('DOMContentLoaded', () => {
    loadFiles();
});

if (viewFolderSelect) {
    viewFolderSelect.addEventListener('change', () => loadFiles());
}
</script>

</body>
</html>