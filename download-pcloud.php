<?php

$token = "kqNT7Z8BpwhA0d4MFZVgju0kZbR12PpsX93VWhpTOL5i4jVefcDdX";

if (!isset($_GET["fileid"])) {
    die("Missing file ID");
}

$fileid = $_GET["fileid"];
$realName = isset($_GET["name"]) ? $_GET["name"] : ("file_" . $fileid);

// Call getfilelink
$api = "https://api.pcloud.com/getfilelink?fileid=$fileid&access_token=$token";
$response = file_get_contents($api);
$data = json_decode($response, true);

if (!$data || $data["result"] != 0) {
    die("❌ Cannot generate file link");
}

$downloadUrl = "https://" . $data["hosts"][0] . $data["path"];

// Send file to user with CORRECT filename
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"" . $realName . "\"");
header("Content-Transfer-Encoding: binary");

readfile($downloadUrl);
exit;
?>
