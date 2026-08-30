<?php
// get_attachments.php
require_once __DIR__ . '/db.php';
if (isset($_POST['registration_id'])) {
    $registration_id = intval($_POST['registration_id']);
    
    $query = "SELECT * FROM upafa_registration_files 
              WHERE registration_id = ? 
              ORDER BY file_type";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $registration_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $html = '';
    $fileCount = 0;
    
    while ($file = $result->fetch_assoc()) {
        $fileCount++;
        $icon = getFileIcon($file['mime_type']);
        $size = formatFileSize($file['size_bytes']);
        
        $html .= '<div class="file-badge" title="' . htmlspecialchars($file['original_name']) . 
                 ' (' . $size . ')' . '">' .
                 '<i class="' . $icon . '"></i> ' .
                 htmlspecialchars($file['file_type']) .
                 '</div>';
    }
    
    if ($fileCount === 0) {
        $html = '<span class="text-muted">No files</span>';
    }
    
    echo $html;
}

function getFileIcon($mimeType) {
    if (strpos($mimeType, 'image/') === 0) {
        return 'fas fa-image';
    } elseif (strpos($mimeType, 'application/pdf') === 0) {
        return 'fas fa-file-pdf';
    } elseif (strpos($mimeType, 'application/msword') === 0 || 
              strpos($mimeType, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') === 0) {
        return 'fas fa-file-word';
    } else {
        return 'fas fa-file';
    }
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>