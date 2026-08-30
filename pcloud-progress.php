<?php
header("Content-Type: application/json");

// ---------------------------------------------------------------------
// Validate parameters
// ---------------------------------------------------------------------
if (!isset($_GET["hash"])) {
    echo json_encode([
        "success" => false,
        "message" => "Missing progress hash"
    ]);
    exit;
}

$hash  = $_GET["hash"];
$token = "kqNT7Z8BpwhA0d4MFZVgju0kZbR12PpsX93VWhpTOL5i4jVefcDdX";


// ---------------------------------------------------------------------
// Build uploadprogress URL (fixed)
// pCloud format:
//   https://api.pcloud.com/uploadprogress?access_token=TOKEN&progresshash=HASH
// ---------------------------------------------------------------------
$url = "https://api.pcloud.com/uploadprogress"
     . "?access_token=" . $token
     . "&progresshash=" . $hash;


// ---------------------------------------------------------------------
// Fetch upload progress
// ---------------------------------------------------------------------
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);


// ---------------------------------------------------------------------
// If request failed or invalid JSON
// ---------------------------------------------------------------------
if (!is_array($data)) {
    echo json_encode([
        "success"  => false,
        "finished" => false,
        "message"  => "Invalid response from pCloud",
        "raw"      => $response
    ]);
    exit;
}

// pCloud returns "result != 0" when upload not found or other errors
if (isset($data["result"]) && $data["result"] != 0) {
    echo json_encode([
        "success"  => false,
        "finished" => false,
        "message"  => "Upload not found or internal pCloud error",
        "raw"      => $data
    ]);
    exit;
}


// ---------------------------------------------------------------------
// SUCCESS — return progress to JS
// ---------------------------------------------------------------------
echo json_encode([
    "success"    => true,
    "finished"   => $data["finished"] ?? false,
    "total"      => $data["total"]     ?? 0,
    "uploaded"   => $data["uploaded"]  ?? 0,
    "currentfile"=> $data["currentfile"] ?? null,
    "details"    => $data
]);
