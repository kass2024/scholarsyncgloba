<?php
require_once __DIR__ . '/vendor/autoload.php';

use setasign\Fpdi\Fpdi;
/**
 * Generate filled application PDF based on DB record.
 *
 * @param int $appId - Application ID
 * @param mysqli $conn - Database connection
 * @return string - Relative PDF file path
 * @throws Exception if PDF template or data is missing
 */
function generateApplicationPDF($appId, $conn) {
    $templatePath = __DIR__ . "/pdfs/IEU Application form Malta.pdf";
    $pdfOutputPath = __DIR__ . "/pdfs/Malta_Application_$appId.pdf";

    if (!file_exists($templatePath)) {
        throw new Exception("❌ PDF template not found at: $templatePath");
    }

    $stmt = $conn->prepare("SELECT * FROM malta_applications WHERE id = ?");
    $stmt->bind_param("i", $appId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("❌ No application found for ID: $appId");
    }

    $data = $result->fetch_assoc();
    $pdf = new Fpdi();
    $pdf->AddPage();
    $pdf->setSourceFile($templatePath);
    $tplIdx = $pdf->importPage(1);
    $pdf->useTemplate($tplIdx);

    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);

    // Value helper
    function val($key, $data) {
        return isset($data[$key]) ? $data[$key] : '';
    }

    // Decode safely
    function json_vals($key, $data) {
        $val = val($key, $data);
        if (empty($val)) return [];
        $decoded = json_decode($val, true);
        return is_array($decoded) ? $decoded : explode(',', $val);
    }

    // --- Session
    $pdf->SetFont('Arial', 'B', 8); // Bold, 8pt
    $pdf->SetTextColor(0, 0, 139);  // Dark Blue (RGB: 0, 0, 139)
   $pdf->SetXY(19, 47.3);  $pdf->Write(0, val('session_from', $data));
    $pdf->SetXY(29, 47.3);  $pdf->Write(0, val('session_to', $data));

    // --- Degree
     $pdf->SetFont('Arial', 'B', 12); // Bold, 8pt
    $pdf->SetTextColor(0, 0, 139);  // Dark Blue (RGB: 0, 0, 139)
    $degrees = json_vals('degree_program', $data);
    if (in_array('Bachelor', $degrees))      { $pdf->SetXY(77, 67);  $pdf->Write(0, 'v'); }
    if (in_array('Master', $degrees))        { $pdf->SetXY(120, 67);  $pdf->Write(0, 'v'); }
    if (in_array('Postgraduate', $degrees))  { $pdf->SetXY(165, 67); $pdf->Write(0, 'v'); }

    // --- Program Details
      $pdf->SetFont('Arial', 'B', 10); // Bold, 8pt
    $pdf->SetTextColor(0, 0, 139);  // Dark Blue (RGB: 0, 0, 139)
    $pdf->SetXY(57, 74);  $pdf->Write(0, val('specialty', $data));
    $pdf->SetXY(39, 80);  $pdf->Write(0, val('alt1', $data));
    $pdf->SetXY(39, 86);  $pdf->Write(0, val('alt2', $data));

    $pdf->SetFont('Arial', 'B', 12); // Bold, 8pt
    $pdf->SetTextColor(0, 0, 139);  // Dark Blue (RGB: 0, 0, 139)
    $modes = json_vals('mode_of_study', $data);
    if (in_array('Online', $modes))            { $pdf->SetXY(69, 93);  $pdf->Write(0, 'v'); }
    if (in_array('Offline', $modes))           { $pdf->SetXY(110, 93);  $pdf->Write(0, 'v'); }
    if (in_array('Not yet decided', $modes))   { $pdf->SetXY(159, 93); $pdf->Write(0, 'v'); }

    // --- Personal Info
    $pdf->SetFont('Arial', 'B', 10); // Bold, 8pt
    $pdf->SetTextColor(0, 0, 139);  // Dark Blue (RGB: 0, 0, 139)
    $pdf->SetXY(34, 111);  $pdf->Write(0, val('surname', $data));
    $pdf->SetXY(89, 111);  $pdf->Write(0, val('name', $data));
    $pdf->SetXY(134, 11); $pdf->Write(0, val('middle_name', $data));

    $pdf->SetFont('Arial', 'B', 12); // Bold, 8pt
    $pdf->SetTextColor(0, 0, 139);  // Dark Blue (RGB: 0, 0, 139)
    if (val('gender', $data) === 'male')    { $pdf->SetXY(54, 121); $pdf->Write(0, 'v'); }
    if (val('gender', $data) === 'female')  { $pdf->SetXY(77, 121); $pdf->Write(0, 'v'); }
    if (val('gender', $data) === 'other')   { $pdf->SetXY(100, 121); $pdf->Write(0, 'v'); }
    $pdf->SetXY(139, 121);  $pdf->Write(0, val('marital_status', $data));

    $pdf->SetFont('Arial', 'B', 8); // Bold, 8pt
    $pdf->SetTextColor(0, 0, 139);  // Dark Blue (RGB: 0, 0, 139)
    $pdf->SetXY(39, 127); $pdf->Write(0, val('dob', $data));
    $pdf->SetXY(104, 127); $pdf->Write(0, val('birth_place', $data));
    $pdf->SetXY(163, 127); $pdf->Write(0, val('nationality', $data));
    $pdf->SetXY(51, 134); $pdf->Write(0, val('passport_no', $data));
    $pdf->SetXY(116, 134); $pdf->Write(0, val('issue_date', $data));
    $pdf->SetXY(169,134); $pdf->Write(0, val('expiry_date', $data));
    $pdf->SetXY(49, 140); $pdf->Write(0, val('address', $data));
    $pdf->SetXY(45, 148); $pdf->Write(0, val('contact_number', $data));
    $pdf->SetXY(122,148); $pdf->Write(0, val('email', $data));
    $pdf->SetXY(95, 155); $pdf->Write(0, val('visa_country', $data));

    // --- School
    $pdf->SetXY(39, 172); $pdf->Write(0, val('school_name', $data));
    $pdf->SetXY(41, 177); $pdf->Write(0, val('school_address', $data));
    $pdf->SetXY(41, 183); $pdf->Write(0, val('school_from', $data));
    $pdf->SetXY(78, 183); $pdf->Write(0, val('school_to', $data));
    $pdf->SetXY(142, 183); $pdf->Write(0, val('school_certificate', $data));

    // --- College
    $pdf->SetXY(79, 201); $pdf->Write(0, val('college_name', $data));
    $pdf->SetXY(62, 208); $pdf->Write(0, val('college_address', $data));
    $pdf->SetXY(41, 214); $pdf->Write(0, val('college_from', $data));
    $pdf->SetXY(79, 214); $pdf->Write(0, val('college_to', $data));
    $pdf->SetXY(143, 214); $pdf->Write(0, val('college_certificate', $data));

    // --- Malta Study Experience
    if (val('studied_malta', $data) === 'Yes') { $pdf->SetXY(90, 220); $pdf->Write(0, 'v'); }
    if (val('studied_malta', $data) === 'No')  { $pdf->SetXY(114, 220); $pdf->Write(0, 'v'); }
    $pdf->SetXY(35, 224); $pdf->Write(0, val('studied_malta_info', $data));

    if (val('malta_lang', $data) === 'Yes') { $pdf->SetXY(89, 228); $pdf->Write(0, 'v'); }
    if (val('malta_lang', $data) === 'No')  { $pdf->SetXY(113, 228); $pdf->Write(0, 'v'); }
    $pdf->SetXY(35, 240); $pdf->Write(0, val('malta_lang_info', $data));

    // --- Signature
    $pdf->SetXY(30, 273); $pdf->Write(0, val('signed_date', $data));
    $pdf->SetXY(143,273); $pdf->Write(0, val('signature', $data));

    $pdf->Output('F', $pdfOutputPath);
    return "pdfs/Malta_Application_$appId.pdf";
}
