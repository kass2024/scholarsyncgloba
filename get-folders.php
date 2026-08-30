<?php
header("Content-Type: application/json");

require_once __DIR__ . '/helpers/materials_pcloud.php';

$token = PCVC_MATERIALS_PCLOUD_TOKEN;

// Only expose the two allowed materials folders (never the full pCloud tree).
$folders = [];
foreach (pcvc_materials_allowed_folder_ids() as $folderId) {
    $result = pcvc_materials_list_folder($folderId, false);
    $folders[] = [
        "folderid" => $folderId,
        "name"     => $result['name'] ?? ("Folder " . $folderId),
        "path"     => "/" . ($result['name'] ?? ("Folder " . $folderId)),
    ];
}

usort($folders, fn($a, $b) => strcmp($a["path"], $b["path"]));

echo json_encode($folders);
