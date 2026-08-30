<?php
require_once __DIR__ . '/db.php';
require 'vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// ✅ Ensure logs directory exists
if (!is_dir("logs")) {
    mkdir("logs", 0777, true);
}

// ✅ Custom logger
function logError($message) {
    $file = "logs/error_log.txt";
    $timestamp = date("Y-m-d H:i:s");
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    $lineInfo = isset($backtrace[0]['file']) ? $backtrace[0]['file'] . ":" . $backtrace[0]['line'] : "N/A";
    $postData = print_r($_POST, true);

    $log = "[$timestamp] ERROR in $lineInfo\n$message\nPOST DATA:\n$postData\n----------------------\n";
    file_put_contents($file, $log, FILE_APPEND);
}

// ✅ Helper to format cells (center + italic)
function styleCell($sheet, $cell) {
    $style = $sheet->getStyle($cell);
    $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $style->getFont()->setItalic(true);
}

// ✅ Collect POST data safely
$fields = [
    'name','age','intake','passport_place',
    'y10','s10','p10','n10',
    'y12','s12','p12','n12',
    'ydip','sdip','pdip','ndip',
    'ybach','sbach','pbach','nbach',
    'ymas','smas','pmas','nmas',
    'work','gre','english','ielts','budget',
    'country1','other1','country2','other2','country3','other3'
];

$data = [];
foreach ($fields as $f) {
    $data[$f] = isset($_POST[$f]) ? trim($_POST[$f]) : null;
}

// ✅ Basic validation
if (empty($data['name']) || empty($data['age']) || empty($data['intake']) || empty($data['passport_place'])) {
    echo "Error: Please fill in all required fields.";
    exit;
}

// ✅ Insert into DB
$sql = "
  INSERT INTO student_queries
  (name, age, intake, passport_place,
   y10, s10, p10, n10,
   y12, s12, p12, n12,
   ydip, sdip, pdip, ndip,
   ybach, sbach, pbach, nbach,
   ymas, smas, pmas, nmas,
   work, gre, english, ielts, budget,
   country1, other1, country2, other2, country3, other3)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    logError("Prepare failed: " . $conn->error);
    echo "Something went wrong. Please try again later.";
    exit;
}

if (!$stmt->bind_param(
    "sisssssssssssssssssssssssssssssssss",
    $data['name'], $data['age'], $data['intake'], $data['passport_place'],
    $data['y10'], $data['s10'], $data['p10'], $data['n10'],
    $data['y12'], $data['s12'], $data['p12'], $data['n12'],
    $data['ydip'], $data['sdip'], $data['pdip'], $data['ndip'],
    $data['ybach'], $data['sbach'], $data['pbach'], $data['nbach'],
    $data['ymas'], $data['smas'], $data['pmas'], $data['nmas'],
    $data['work'], $data['gre'], $data['english'], $data['ielts'], $data['budget'],
    $data['country1'], $data['other1'], $data['country2'], $data['other2'], $data['country3'], $data['other3']
)) {
    logError("Bind failed: " . $stmt->error);
    echo "Something went wrong. Please try again later.";
    exit;
}

if (!$stmt->execute()) {
    logError("Execute failed: " . $stmt->error);
    echo "Something went wrong. Please try again later.";
    exit;
}
$stmt->close();


// -------------------
// ✅ Excel Export (with untouched template)
// -------------------
$templatePath = __DIR__ . "/European_enquiry_form.xlsx";
if (!file_exists($templatePath)) {
    logError("Template not found: $templatePath");
    echo "Something went wrong. Please try again later.";
    exit;
}

$spreadsheet = IOFactory::load($templatePath);
$sheet = $spreadsheet->getActiveSheet();

// Candidate Info
$sheet->setCellValue("C2", $data['name']);    styleCell($sheet, "C2");
$sheet->setCellValue("E2", $data['age']);     styleCell($sheet, "E2");
$sheet->setCellValue("E3", $data['passport_place']); styleCell($sheet, "E3");
$sheet->setCellValue("C3", $data['intake']);  styleCell($sheet, "C3");

// Education section
$cells = [
    "C5"=>$data['y10'], "D5"=>$data['s10'], "E5"=>$data['p10'], "F5"=>$data['n10'],
    "C6"=>$data['y12'], "D6"=>$data['s12'], "E6"=>$data['p12'], "F6"=>$data['n12'],
    "C7"=>$data['ydip'], "D7"=>$data['sdip'], "E7"=>$data['pdip'], "F7"=>$data['ndip'],
    "C8"=>$data['ybach'], "D8"=>$data['sbach'], "E8"=>$data['pbach'], "F8"=>$data['nbach'],
    "C9"=>$data['ymas'], "D9"=>$data['smas'], "E9"=>$data['pmas'], "F9"=>$data['nmas'],
    "C11"=>$data['work'], "E11"=>$data['gre'],
    "C12"=>$data['english'], "E12"=>trim($data['country1'] . " " . $data['other1']),
    "C13"=>$data['ielts'], "E13"=>trim($data['country2'] . " " . $data['other2']),
    "C14"=>$data['budget'], "E14"=>trim($data['country3'] . " " . $data['other3'])
];

foreach ($cells as $cell => $value) {
    $sheet->setCellValue($cell, $value);
    styleCell($sheet, $cell);
}

// ✅ Do not touch row 16 onwards

// Save Excel
if (!is_dir("uploads")) {
    mkdir("uploads", 0777, true);
}
$filename = "uploads/student_" . preg_replace("/[^a-zA-Z0-9]/", "_", $data['name']) . "_" . time() . ".xlsx";
$writer = IOFactory::createWriter($spreadsheet, "Xlsx");

try {
    $writer->save($filename);
} catch (Exception $e) {
    logError("Excel save failed: " . $e->getMessage());
    echo "Something went wrong. Please try again later.";
    exit;
}


// -------------------
// ✅ Send Email
// -------------------
include 'send_email_indo.php';
try {
    if (!sendStudentEmail($data, $filename)) {
        echo "Form saved but email failed to send.";
        exit;
    }
} catch (Exception $e) {
    logError("Email send failed: " . $e->getMessage());
    echo "Something went wrong. Please try again later.";
    exit;
}

// ✅ Success message
echo "Form saved and email sent successfully.";
