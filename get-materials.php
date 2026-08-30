<?php
require "marketing-openai.php"; // AI helper
require_once __DIR__ . '/helpers/materials_pcloud.php';
//-----------------------------------------------------------
// AJAX ENDPOINT - returns categorized + searchable JSON
// Only from allowed materials folders (no other pCloud folders).
//-----------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header("Content-Type: application/json");
    echo json_encode(pcvc_materials_list_all());
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing Materials</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Video.js CSS -->
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
    <link href="https://unpkg.com/@videojs/themes/dist/city/index.css" rel="stylesheet">
    <!-- Video.js Script -->
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --light-bg: #f8f9fa;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
            --card-hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #2d3748;
            line-height: 1.6;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.5rem;
            padding: 1rem 0;
        }

        .card-box {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid #e9ecef;
        }

        .card-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            border-color: #dee2e6;
        }

        .media-preview {
            position: relative;
            width: 100%;
            min-height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            background-image: linear-gradient(135deg, #f5f7fa 0%, #e4e7eb 100%);
            border-radius: 8px 8px 0 0;
            overflow: hidden;
            padding: 1.5rem;
        }

        .skeleton {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 6px;
        }

        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .thumb {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: white;
            display: none;
            transition: opacity 0.3s ease;
        }
        
        .thumb.loaded {
            display: block;
        }

        .file-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            min-height: 120px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .file-icon i {
            transition: transform 0.2s ease;
            transform: scale(1);
        }

        .card-box:hover .file-icon {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .card-box:hover .file-icon i {
            transform: scale(1.1);
        }

        .file-info {
            padding: 1rem;
        }

        .filename {
            font-weight: 500;
            margin-bottom: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 0.75rem;
        }

        .file-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 6px;
        }

        .btn-preview {
            background-color: #e2e8f0;
            border: none;
            color: #475569;
        }

        .btn-preview:hover {
            background-color: #cbd5e1;
            color: #1e293b;
        }

        .search-box {
            max-width: 400px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 1rem;
        }

        .search-box:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        /* Video Modal */
        .video-modal .modal-dialog {
            max-width: 95%;
            height: 90vh;
            margin: 1rem auto;
        }

        .video-modal .modal-content {
            background: transparent;
            border: none;
            height: 100%;
        }

        .video-modal .modal-body {
            padding: 0;
            height: 100%;
            position: relative;
            background: #000;
        }

        .video-modal iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 8px;
        }

        .video-modal .btn-close {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: rgba(0,0,0,0.5);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.8;
        }

        .video-modal .btn-close:hover {
            opacity: 1;
        }

        .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .play-button:hover {
            transform: translate(-50%, -50%) scale(1.1);
            background-color: white;
        }

        .play-button i {
            color: var(--primary-color);
            font-size: 24px;
            margin-left: 5px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 1rem;
            }
            
            .search-box {
                max-width: 100%;
            }
            
            .file-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }

        /* AI Insights Box */
        #ai-box {
            background: #f8fafc;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 0 8px 8px 0;
            white-space: pre-wrap;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* Section Headers */
        h4 {
            color: #1e293b;
            font-weight: 600;
            margin: 2rem 0 1rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
            display: inline-block;
        }

        .video-js .vjs-big-play-button {
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 68px;
            height: 48px;
            border-radius: 8px;
            border: none;
            background-color: rgba(67, 97, 238, 0.8);
        }

        .video-js .vjs-big-play-button:before {
            position: absolute;
            top: 50%;
            left: 55%;
            transform: translate(-50%, -50%);
            font-size: 24px;
        }

        .video-js:hover .vjs-big-play-button,
        .video-js .vjs-big-play-button:focus {
            background-color: rgba(67, 97, 238, 1);
        }

        .folder-badge {
            background: var(--primary-color);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-left: 10px;
            vertical-align: middle;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h1 class="h3 fw-bold text-primary mb-1">
                📁 Marketing Materials 
                <span class="folder-badge">2 folders</span>
            </h1>
            <p class="text-muted mb-0">Files from the allowed materials folders only</p>
        </div>
        <div class="w-100 w-md-auto">
            <input id="search" class="form-control search-box" placeholder="Search files...">
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom-0">
            <h5 class="mb-0 fw-semibold"><i class="fas fa-robot text-primary me-2"></i>AI-Powered Insights</h5>
        </div>
        <div class="card-body">
            <div id="ai-box">Analyzing your marketing materials...</div>
        </div>
    </div>

    <h4><i class="far fa-images me-2"></i>Images</h4>
    <div id="imagesGrid" class="grid"></div>
    <div id="noImages" class="text-center py-5 d-none">
        <i class="far fa-images fa-3x text-muted mb-3"></i>
        <p class="text-muted">No images found in materials folders</p>
    </div>

    <h4 class="mt-5"><i class="fas fa-video me-2"></i>Videos</h4>
    <div id="videosGrid" class="grid"></div>
    <div id="noVideos" class="text-center py-5 d-none">
        <i class="fas fa-video-slash fa-3x text-muted mb-3"></i>
        <p class="text-muted">No videos found in materials folders</p>
    </div>

    <h4 class="mt-5"><i class="far fa-file-alt me-2"></i>Other Files</h4>
    <div id="othersGrid" class="grid"></div>
    <div id="noOthers" class="text-center py-5 d-none">
        <i class="far fa-folder-open fa-3x text-muted mb-3"></i>
        <p class="text-muted">No other files found in materials folders</p>
    </div>
</div>

<!-- Video Preview Modal -->
<div class="modal fade video-modal" id="videoModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-fullscreen-md-down">
        <div class="modal-content">
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-body p-0">
                <div id="videoContainer" class="h-100 w-100"></div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Video.js -->
<link href="https://vjs.zencdn.net/8.5.2/video-js.css" rel="stylesheet" />
<script src="https://vjs.zencdn.net/8.5.2/video.min.js"></script>
<script>
// Allowed materials folder IDs only (matches PHP whitelist)
const MATERIALS_FOLDER_IDS = [30447221155, 29604175723];

//-----------------------------------------------------------
// FETCH FILES
//-----------------------------------------------------------
async function loadFiles() {
    let res = await fetch('?ajax=1');
    let data = await res.json();
    window.ALL_FILES = data;
    renderAll(data);
    runAISummary(data);
}

//-----------------------------------------------------------
// RENDER ALL CATEGORIES
//-----------------------------------------------------------
function renderAll(data) {
    renderCategory('imagesGrid', data.images, true);
    renderCategory('videosGrid', data.videos, true);
    renderCategory('othersGrid', data.others, false);
}

//-----------------------------------------------------------
// RENDER CATEGORY
//-----------------------------------------------------------
function renderCategory(id, arr, hasThumb) {
    const box = document.getElementById(id);
    const noItemsElement = document.getElementById(`no${id.charAt(0).toUpperCase() + id.slice(1, -5)}`);
    
    box.innerHTML = '';
    
    if (arr.length === 0) {
        if (noItemsElement) noItemsElement.classList.remove('d-none');
        return;
    }
    
    if (noItemsElement) noItemsElement.classList.add('d-none');

    arr.forEach(f => {
        const ext = f.name.split('.').pop().toLowerCase();
        const isVideo = id === 'videosGrid';
        const preview = hasThumb ? getThumb(f.fileid) : '';
        const fileSize = formatFileSize(f.size || 0);
        
        const card = document.createElement('div');
        card.className = 'card-box';
        card.dataset.name = f.name.toLowerCase();
        
        card.innerHTML = `
            <div class="media-preview">
                <div class="skeleton"></div>
                ${hasThumb 
                    ? `<img class='thumb' data-src='${preview}' alt='${f.name}'>` 
                    : `<div class='file-icon'>${getFileIcon(ext)}</div>`
                }
                ${isVideo ? `
                    <div class="play-button" data-video-id="${f.fileid}" data-filename="${f.name}">
                        <i class="fas fa-play"></i>
                    </div>
                ` : ''}
            </div>
            <div class="file-info">
                <div class="filename" title="${f.name}">${f.name}</div>
                <div class="file-meta">
                    <span>${fileSize}</span>
                    <span>${ext.toUpperCase()}</span>
                </div>
                <div class="file-actions">
                    ${isVideo ? `
                        <button class="btn btn-sm btn-preview me-1" data-video-id="${f.fileid}" data-filename="${f.name}">
                            <i class="fas fa-play me-1"></i> Preview
                        </button>
                    ` : ''}
                    <a href="download-pcloud.php?fileid=${f.fileid}&name=${encodeURIComponent(f.name)}" 
                       class="btn btn-sm btn-primary flex-grow-1">
                        <i class="fas fa-download me-1"></i> Download
                    </a>
                </div>
            </div>
        `;
        
        box.appendChild(card);
    });

    // Add event listeners for video previews
    if (id === 'videosGrid') {
        document.querySelectorAll(`#${id} .play-button, #${id} .btn-preview`).forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const videoId = e.currentTarget.getAttribute('data-video-id');
                const fileName = e.currentTarget.getAttribute('data-filename');
                openVideoModal(videoId, fileName);
            });
        });
    }

    lazyLoad();
}

//-----------------------------------------------------------
// PCLOUD REAL THUMBNAIL
//-----------------------------------------------------------
function getThumb(id) {
    return `https://api.pcloud.com/getthumb?fileid=${id}&access_token=kqNT7Z8BpwhA0d4MFZVgju0kZbR12PpsX93VWhpTOL5i4jVefcDdX&size=256x256&type=auto`;
}

//-----------------------------------------------------------
// FILE ICONS FOR OTHER FILE TYPES
//-----------------------------------------------------------
function getFileIcon(ext) {
    // Map of file extensions to Font Awesome icons with colors
    const iconMap = {
        // Documents
        'pdf': { icon: 'file-pdf', color: '#e74c3c' },
        'doc': { icon: 'file-word', color: '#2b579a' },
        'docx': { icon: 'file-word', color: '#2b579a' },
        'xls': { icon: 'file-excel', color: '#1d6f42' },
        'xlsx': { icon: 'file-excel', color: '#1d6f42' },
        'ppt': { icon: 'file-powerpoint', color: '#d24726' },
        'pptx': { icon: 'file-powerpoint', color: '#d24726' },
        'txt': { icon: 'file-alt', color: '#7f8c8d' },
        'rtf': { icon: 'file-alt', color: '#7f8c8d' },
        
        // Archives
        'zip': { icon: 'file-archive', color: '#f39c12' },
        'rar': { icon: 'file-archive', color: '#f39c12' },
        '7z': { icon: 'file-archive', color: '#f39c12' },
        'tar': { icon: 'file-archive', color: '#f39c12' },
        'gz': { icon: 'file-archive', color: '#f39c12' },
        
        // Code
        'js': { icon: 'file-code', color: '#f1c40f' },
        'html': { icon: 'file-code', color: '#e67e22' },
        'css': { icon: 'file-code', color: '#3498db' },
        'php': { icon: 'file-code', color: '#6c5ce7' },
        'json': { icon: 'file-code', color: '#2ecc71' },
        'xml': { icon: 'file-code', color: '#e74c3c' },
        
        // Audio
        'mp3': { icon: 'file-audio', color: '#9b59b6' },
        'wav': { icon: 'file-audio', color: '#8e44ad' },
        'ogg': { icon: 'file-audio', color: '#9b59b6' },
        
        // Default
        'default': { icon: 'file', color: '#7f8c8d' }
    };
    
    const fileType = iconMap[ext] || iconMap['default'];
    return `<i class="fas fa-${fileType.icon}" style="color: ${fileType.color}; font-size: 2.5rem;"></i>`;
}

//-----------------------------------------------------------
// LAZY LOAD
//-----------------------------------------------------------
function lazyLoad() {
    document.querySelectorAll('img.thumb').forEach(img => {
        // Skip if already loaded
        if (img.getAttribute('data-loaded') === 'true') return;
        
        const skeleton = img.previousElementSibling;
        const temp = new Image();
        
        temp.onload = () => {
            img.src = temp.src;
            img.style.display = 'block';
            img.style.opacity = '0';
            img.style.transition = 'opacity 0.3s ease';
            
            // Fade in the image
            setTimeout(() => {
                img.style.opacity = '1';
                if (skeleton) {
                    skeleton.style.display = 'none';
                }
            }, 50);
            
            // Mark as loaded
            img.setAttribute('data-loaded', 'true');
        };
        
        temp.onerror = () => {
            // If image fails to load, show a file icon instead
            console.error(`Failed to load image: ${img.dataset.src}`);
            if (skeleton) {
                skeleton.style.display = 'none';
            }
            
            // Create a fallback icon
            const fallback = document.createElement('div');
            fallback.className = 'file-icon';
            fallback.innerHTML = getFileIcon('image');
            
            // Insert after the failed image
            img.parentNode.insertBefore(fallback, img.nextSibling);
            img.style.display = 'none';
        };
        
        // Start loading the image
        temp.src = img.dataset.src;
    });
}

//-----------------------------------------------------------
// LIVE SEARCH ACROSS ALL CATEGORIES
//-----------------------------------------------------------
document.getElementById('search').addEventListener('input', function() {
    let t = this.value.toLowerCase();
    document.querySelectorAll('.card-box').forEach(c => {
        c.style.display = c.dataset.name.includes(t) ? '' : 'none';
    });
});

//-----------------------------------------------------------
// AI SUMMARY
//-----------------------------------------------------------
async function runAISummary(data) {
    let names = [...data.images, ...data.videos, ...data.others].map(f => f.name);
    let form = new FormData();
    form.append('names', JSON.stringify(names));

    let res = await fetch('marketing-openai.php', { method: 'POST', body: form });
    let txt = await res.text();

    document.getElementById('ai-box').textContent = txt;
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Video modal handling
function openVideoModal(videoId, fileName = '') {
    const modalElement = document.getElementById('videoModal');
    const videoContainer = document.getElementById('videoContainer');
    
    // Create video player with HTML5 video element
    videoContainer.innerHTML = `
        <div class="video-player-container">
            <video id="videoPlayer" class="video-js vjs-big-play-centered" controls preload="auto" data-setup='{"fluid": true}'>
                <source src="get-video.php?fileid=${videoId}" type="video/mp4">
                <p class="vjs-no-js">
                    To view this video please enable JavaScript, and consider upgrading to a
                    web browser that <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>
                </p>
            </video>
            <div class="video-title">${fileName}</div>
        </div>
        <style>
            .video-player-container {
                position: relative;
                width: 100%;
                height: 100%;
                background: #000;
            }
            .video-js {
                width: 100%;
                height: 100%;
            }
            .video-title {
                position: absolute;
                bottom: 10px;
                left: 0;
                right: 0;
                text-align: center;
                color: white;
                padding: 5px 10px;
                background: rgba(0,0,0,0.5);
                font-size: 14px;
            }
        </style>
    `;
    
    // Initialize video.js player if available
    if (typeof videojs !== 'undefined') {
        const player = videojs('videoPlayer');
        player.ready(function() {
            player.play();
        });
    }
    
    // Initialize and show modal
    const videoModal = new bootstrap.Modal(modalElement, {
        backdrop: 'static',
        keyboard: true
    });
    
    videoModal.show();
    
    // Handle modal close
    const handleClose = () => {
        // Destroy Video.js player if it exists
        if (videojs.getPlayer('videoPlayer')) {
            videojs.getPlayer('videoPlayer').dispose();
        }

        // Remove any HTML5 <video> element if present
        const videoEl = videoContainer.querySelector('video');
        if (videoEl) {
            videoEl.pause();
            videoEl.src = '';
        }

        // Clear modal content
        videoContainer.innerHTML = '';

        // Remove listener
        modalElement.removeEventListener('hidden.bs.modal', handleClose);
    };

    // Clean up when modal is closed
    modalElement.addEventListener('hidden.bs.modal', handleClose, { once: true });
    
    // Handle escape key to properly close the modal
    document.addEventListener('keydown', function onEscKey(e) {
        if (e.key === 'Escape') {
            videoModal.hide();
            document.removeEventListener('keydown', onEscKey);
        }
    });
}

// Initialize tooltips
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Debounce search input
let searchTimeout;
document.getElementById('search').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const searchTerm = this.value.trim().toLowerCase();
    
    searchTimeout = setTimeout(() => {
        document.querySelectorAll('.card-box').forEach(card => {
            const cardName = card.dataset.name;
            const shouldShow = searchTerm === '' || cardName.includes(searchTerm);
            card.style.display = shouldShow ? '' : 'none';
        });
        
        // Show/hide section headers based on visibility of their items
        ['images', 'videos', 'others'].forEach(section => {
            const sectionElement = document.getElementById(`${section}Grid`);
            const hasVisibleItems = Array.from(sectionElement.children).some(
                child => child.style.display !== 'none' && child.classList.contains('card-box')
            );
            
            const noItemsElement = document.getElementById(`no${section.charAt(0).toUpperCase() + section.slice(1)}`);
            if (noItemsElement) {
                noItemsElement.style.display = (searchTerm !== '' && !hasVisibleItems) ? 'block' : 'none';
            }
        });
    }, 300);
});

// Initialize everything when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    loadFiles();
    initTooltips();
});
</script>
</body>
</html>