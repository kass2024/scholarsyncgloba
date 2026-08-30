<?php
header("Content-Type: application/json");

require_once __DIR__ . '/helpers/materials_pcloud.php';

$token = PCVC_MATERIALS_PCLOUD_TOKEN;

if (!isset($_GET['folderid'])) {
    echo json_encode([
        "success" => false,
        "files" => [],
        "message" => "No folder ID"
    ]);
    exit;
}

$folder = intval($_GET['folderid']);

if (!pcvc_materials_is_allowed_folder($folder)) {
    echo json_encode([
        "success" => false,
        "files" => [],
        "message" => "Folder not allowed for materials"
    ]);
    exit;
}

$url = "https://api.pcloud.com/listfolder?folderid=$folder&access_token=$token";
$res = json_decode(file_get_contents($url), true);

if (!isset($res["metadata"]["contents"])) {
    echo json_encode([
        "success" => false,
        "files" => [],
        "message" => "No contents found"
    ]);
    exit;
}

$files = [];

foreach ($res["metadata"]["contents"] as $f) {
    if ($f["isfolder"]) continue;

    $mime = $f["contenttype"] ?? "";

    // Determine file type
    $isImage = strpos($mime, "image/") === 0;
    $isVideo = strpos($mime, "video/") === 0;
    $isPDF   = strpos($mime, "application/pdf") === 0;

    // ----------------------------------
    // THUMBNAIL / PREVIEW URL
    // ----------------------------------
    if ($isImage) {
        $preview = "https://api.pcloud.com/getthumb?fileid={$f['fileid']}&size=600x600&access_token=$token&type=auto";
    } elseif ($isVideo) {
        // Generate video thumbnail
        $preview = "https://api.pcloud.com/getvideothumb?fileid={$f['fileid']}&time=1&access_token=$token";
    } elseif ($isPDF) {
        // First page preview
        $preview = "https://api.pcloud.com/getthumb?fileid={$f['fileid']}&size=600x600&access_token=$token&type=auto";
    } else {
        // Default icon
        $preview = "https://cdn-icons-png.flaticon.com/512/833/833524.png";
    }

    // ----------------------------------
    // REAL DOWNLOAD URL
    // ----------------------------------
    // getfilelink returns JSON → we extract path + host
    $linkJson = json_decode(file_get_contents(
        "https://api.pcloud.com/getfilelink?fileid={$f['fileid']}&access_token=$token"
    ), true);

    if (isset($linkJson["hosts"][0]) && isset($linkJson["path"])) {
        $downloadUrl = "https://" . $linkJson["hosts"][0] . $linkJson["path"];
    } else {
        $downloadUrl = "#";
    }

    $files[] = [
        "fileid" => $f["fileid"],
        "name" => $f["name"],
        "size" => $f["size"],
        "mime" => $mime,
        "preview" => $preview,
        "download" => $downloadUrl
    ];
}

echo json_encode([
    "success" => true,
    "files" => $files
]);
