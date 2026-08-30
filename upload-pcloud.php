<?php
header("Content-Type: application/json");

require_once __DIR__ . '/helpers/materials_pcloud.php';

// -------------------------------------------------
// CONFIG
// -------------------------------------------------
$token = PCVC_MATERIALS_PCLOUD_TOKEN;


// -------------------------------------------------
// VALIDATION — uploads only into allowed materials folders
// -------------------------------------------------
if (!isset($_POST['folderid'])) {
    echo json_encode(["success" => false, "message" => "No folder selected"]);
    exit;
}

$folderid = intval($_POST['folderid']);

if (!pcvc_materials_is_allowed_folder($folderid)) {
    echo json_encode([
        "success" => false,
        "message" => "Upload blocked: folder is not an allowed materials folder",
        "allowed" => pcvc_materials_allowed_folder_ids(),
    ]);
    exit;
}

if (!isset($_FILES['files']) || count($_FILES['files']['tmp_name']) === 0) {
    echo json_encode(["success" => false, "message" => "No files uploaded"]);
    exit;
}

// Unique progress hash
$progressHash = bin2hex(random_bytes(16));


// -------------------------------------------------
// pCloud official upload endpoint
// -------------------------------------------------
$uploadUrl = "https://api.pcloud.com/uploadfile?access_token=" . $token;


// -------------------------------------------------
// Prepare POST fields
// -------------------------------------------------
$postFields = [
    "folderid"        => $folderid,
    "progresshash"    => $progressHash,
    "nopartial"       => 1,
    "renameifexists"  => 1
];

// Add files
foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
    $postFields["file$i"] = curl_file_create(
        $tmp,
        mime_content_type($tmp),
        $_FILES["files"]["name"][$i]
    );
}


// -------------------------------------------------
// EXECUTE UPLOAD
// -------------------------------------------------
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL            => $uploadUrl,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postFields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);

$response = curl_exec($ch);
curl_close($ch);

$uploadJSON = json_decode($response, true);


// -------------------------------------------------
// ERROR HANDLING
// -------------------------------------------------
if (!isset($uploadJSON["result"]) || $uploadJSON["result"] != 0) {
    echo json_encode([
        "success" => false,
        "message" => "Upload error",
        "response" => $uploadJSON
    ]);
    exit;
}


// -------------------------------------------------
// SUCCESS
// -------------------------------------------------
echo json_encode([
    "success"      => true,
    "progresshash" => $progressHash,
    "response"     => $uploadJSON
]);
