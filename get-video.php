<?php

// -------------------
// DEBUG
// -------------------
ini_set("display_errors", 1);
error_reporting(E_ALL);

$debugFile = __DIR__ . "/video_debug.log";
function debugLog($msg) {
    global $debugFile;
    file_put_contents($debugFile, "[".date("Y-m-d H:i:s")."] ".$msg."\n", FILE_APPEND);
}

// -------------------
// Disable all buffering (important for cPanel)
// -------------------
while (ob_get_level()) { ob_end_clean(); }
ini_set("output_buffering", "off");
ini_set("zlib.output_compression", 0);
header("X-Accel-Buffering: no");

// -------------------
// Enable CORS
// -------------------
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Range");
header("Access-Control-Expose-Headers: Content-Length, Content-Range");
header("Accept-Ranges: bytes");

// -------------------
// INPUT
// -------------------
$fileId = $_GET['fileid'] ?? "";
$token  = "kqNT7Z8BpwhA0d4MFZVgju0kZbR12PpsX93VWhpTOL5i4jVefcDdX";

if (!$fileId) {
    debugLog("Missing fileid");
    http_response_code(400);
    exit("Missing fileid");
}

debugLog("Requested fileid: $fileId");

// -------------------
// 1. GET FILE LINK
// -------------------
$apiUrl = "https://api.pcloud.com/getfilelink?fileid=$fileId&access_token=$token";
debugLog("API URL: $apiUrl");

$raw = file_get_contents($apiUrl);
debugLog("API Response: $raw");

$data = json_decode($raw, true);

if (!$data || $data["result"] != 0) {
    debugLog("pCloud error");
    exit("Error retrieving file link");
}

// Extract data
$host  = $data["hosts"][0];
$path  = $data["path"];
$dltag = $data["dwltag"]; // REQUIRED !!
// Build final real video URL
$videoUrl = "https://$host$path?dltag=$dltag";

debugLog("Final playback URL: $videoUrl");

// -------------------
// 2. Handle Range header
// -------------------
$rangeHeader = $_SERVER["HTTP_RANGE"] ?? null;

$headers = get_headers($videoUrl, 1);
if (!$headers || !isset($headers["Content-Length"])) {
    debugLog("Could not read remote headers");
    exit("Streaming error");
}

$fileSize = (int)$headers["Content-Length"];
debugLog("Remote file size: $fileSize");

$start = 0;
$end   = $fileSize - 1;

if ($rangeHeader && preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
    $start = intval($matches[1]);
    if ($matches[2] !== "") $end = intval($matches[2]);

    header("HTTP/1.1 206 Partial Content");
    header("Content-Range: bytes $start-$end/$fileSize");
}

$chunkLength = $end - $start + 1;

header("Content-Type: video/mp4");
header("Content-Length: $chunkLength");

// -------------------
// 3. Stream video (chunked streaming, cPanel safe)
// -------------------
$opts = [
    "http" => [
        "method" => "GET",
        "header" => "Range: bytes=$start-$end"
    ]
];

$context = stream_context_create($opts);
$stream  = fopen($videoUrl, "rb", false, $context);

if (!$stream) {
    debugLog("Cannot open remote stream");
    exit("Cannot open stream");
}

$bufferSize = 1024 * 64; // 64 KB safe chunk for cPanel

while (!feof($stream)) {
    echo fread($stream, $bufferSize);
    flush();
}

fclose($stream);
exit;
