<?php
require_once __DIR__ . '/db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

// DEBUG LOGGING FUNCTION
function debugLog($msg) {
    file_put_contents(__DIR__ . '/debug_upload.log', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

debugLog("===== Upload triggered =====");

// Step 1: Validate user ID
$userId = $_POST['user_id'] ?? '';
if (!$userId) {
    debugLog("❌ Missing user ID");
    echo json_encode(['status' => 'error', 'message' => 'Missing user ID']);
    exit;
}

debugLog("✅ User ID: $userId");

// Step 2: Allowed fields
$allowedMultiFields = ['recommendation_letters', 'other_documents'];
$field = '';

// Step 3: Detect uploaded field
foreach ($allowedMultiFields as $f) {
    if (!empty($_FILES[$f]['name'][0])) {
        $field = $f;
        break;
    }
}
if (!$field) {
    debugLog("❌ No valid file field detected");
    echo json_encode(['status' => 'error', 'message' => 'No valid file field detected']);
    exit;
}

debugLog("📂 Detected field: $field");

// Step 4: Validate uploaded files
$files = $_FILES[$field];
$fileCount = count($files['name']);
$maxFiles = 5;

if ($fileCount > $maxFiles) {
    debugLog("❌ Too many files: $fileCount (max $maxFiles)");
    echo json_encode(['status' => 'error', 'message' => "Too many files. Max allowed: $maxFiles"]);
    exit;
}

// Step 5: Confirm user ID exists
$check = $conn->prepare("SELECT `$field` FROM dphu WHERE user_id = ? LIMIT 1");
$check->bind_param("s", $userId);
$check->execute();
$check->bind_result($existingJson);
$check->fetch();
$check->close();

debugLog("✅ DB check passed");

$existingFiles = json_decode($existingJson ?? '[]', true);
$savedPaths = [];

// Step 6: Process uploads
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

for ($i = 0; $i < $fileCount; $i++) {
    if ($files['error'][$i] === UPLOAD_ERR_OK) {
        $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $filename = $userId . '_' . $field . '_' . uniqid() . '.' . $ext;
        $target = $uploadDir . $filename;

        if (move_uploaded_file($files['tmp_name'][$i], $target)) {
            $path = 'uploads/' . $filename;
            $savedPaths[] = $path;
            debugLog("✅ Uploaded: $path");
        } else {
            debugLog("❌ Move failed: {$files['name'][$i]}");
        }
    } else {
        debugLog("❌ Upload error on file index $i");
    }
}

// Step 7: Save to DB
if (!empty($savedPaths)) {
    $allPaths = array_merge($existingFiles ?: [], $savedPaths);
    $jsonPaths = json_encode($allPaths, JSON_UNESCAPED_SLASHES);

    $stmt = $conn->prepare("UPDATE dphu SET `$field` = ? WHERE user_id = ?");
    $stmt->bind_param('ss', $jsonPaths, $userId);

    if ($stmt->execute()) {
        debugLog("✅ DB updated for field $field");
        echo json_encode(['status' => 'success', 'paths' => $savedPaths]);
    } else {
        debugLog("❌ DB update error: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'DB update error: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    debugLog("❌ No files uploaded");
    echo json_encode(['status' => 'error', 'message' => 'No files uploaded']);
}

$conn->close();
debugLog("✅ Upload process completed.\n");
