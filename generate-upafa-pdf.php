<?php
// generate-pdf.php
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generate a full PDF recap for one registration (all fields + files).
 *
 * @param int     $registrationId
 * @param mysqli  $conn            Active mysqli connection
 * @return string|null             Relative path to saved PDF, or null on failure
 */
function generateUpafaPDF(int $registrationId, mysqli $conn): ?string
{
    // ---- helpers ----
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $fmtMoney = fn($n) => number_format((float)$n, 2, '.', ',');

    // Fetch main row
    $stmt = $conn->prepare("SELECT * FROM upafa_registrations WHERE id=?");
    $stmt->bind_param('i', $registrationId);
    $stmt->execute();
    $res = $stmt->get_result();
    $reg = $res->fetch_assoc();
    $stmt->close();

    if (!$reg) return null;

    // Fetch files
    $fs = $conn->prepare("SELECT file_type, original_name, storage_path, mime_type, size_bytes
                          FROM upafa_registration_files
                          WHERE registration_id=?
                          ORDER BY file_type, id");
    $fs->bind_param('i', $registrationId);
    $fs->execute();
    $filesRes = $fs->get_result();
    $files = [];
    while ($row = $filesRes->fetch_assoc()) {
        $files[$row['file_type']][] = $row;
    }
    $fs->close();

    // Build files HTML (thumbnails for images, names for PDFs)
    $renderFile = function(array $f) use ($h) {
        $path = $f['storage_path'];
        $mime = $f['mime_type'] ?? '';
        $name = $f['original_name'] ?? basename($path);

        if (is_file($path) && str_starts_with($mime, 'image/')) {
            // embed inline as base64 thumbnail
            $data = @file_get_contents($path);
            if ($data !== false) {
                $b64 = base64_encode($data);
                return "<div class='file-chip'>
                          <div class='thumb'><img src='data:{$h($mime)};base64,{$b64}' alt='{$h($name)}'></div>
                          <div class='meta'>{$h($name)}<br><small>{$h($mime)} • ".number_format((int)$f['size_bytes'])." bytes</small></div>
                        </div>";
            }
        }
        // fallback (pdf or unknown)
        return "<div class='file-chip'>
                  <div class='thumb pdf'>PDF/FILE</div>
                  <div class='meta'>{$h($name)}<br><small>{$h($mime)} • ".number_format((int)$f['size_bytes'])." bytes</small></div>
                </div>";
    };

    $secFiles = function($label, $key) use ($files, $renderFile, $h) {
        if (empty($files[$key])) return "";
        $items = array_map($renderFile, $files[$key]);
        return "<div class='files-block'>
                  <div class='files-title'>{$h($label)}</div>
                  <div class='files-list'>".implode('', $items)."</div>
                </div>";
    };

    // Full HTML (ALL fields included)
    $html = "
<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'>
<style>
  @page { size: A4; margin: 18mm 14mm; }
  body{ font-family: DejaVu Sans, Arial, sans-serif; font-size:12px; color:#111; }
  h1,h2,h3{ margin:0 0 8px 0; }
  .hdr{ border-bottom:2px solid #777; padding-bottom:6px; margin-bottom:10px }
  .hdr .brand{ font-weight:700; font-size:14px; letter-spacing:.3px; color:#333 }
  .muted{ color:#666 }
  .grid{ width:100%; border-collapse:collapse; margin-bottom:10px }
  .grid td,.grid th{ border:1px solid #ccc; padding:6px; vertical-align:top }
  .grid th{ background:#f3f3f3; text-align:left }
  .two-col{ width:100%; border-collapse:separate; border-spacing:0 6px; margin:10px 0 }
  .two-col td{ padding:0 }
  .label{ font-weight:600; width:38% }
  .value{ }
  .section{ margin:12px 0 8px }
  .section-title{ font-weight:700; font-size:13px; color:#333; margin-bottom:6px; border-left:4px solid #6a2bc2; padding-left:6px }
  .files-block{ margin:8px 0 10px }
  .files-title{ font-weight:600; margin-bottom:4px; }
  .files-list{ display:flex; flex-wrap:wrap; gap:8px }
  .file-chip{ display:flex; gap:6px; border:1px solid #ddd; padding:6px; border-radius:6px; align-items:center; width: 270px; }
  .file-chip .thumb{ width:48px; height:48px; border:1px solid #ddd; display:flex; align-items:center; justify-content:center; font-size:10px; background:#fafafa }
  .file-chip .thumb img{ max-width:46px; max-height:46px; display:block }
  .file-chip .thumb.pdf{ font-weight:700; color:#444 }
  .file-chip .meta{ font-size:11px; line-height:1.25 }
  .tiny{ font-size:10px; color:#666; margin-top:6px; }
</style>
</head>
<body>

  <div class='hdr'>
    <div class='brand'>U.P.A.F.A. — Registration Recap</div>
    <div>Application ID: <strong>#".$h($registrationId)."</strong></div>
    <div>Academic Year: <strong>".$h($reg['academic_year'])."</strong></div>
  </div>

  <!-- Personal Information -->
  <div class='section'>
    <div class='section-title'>Personal Information</div>
    <table class='grid'>
      <tr><th style='width:28%'>Field</th><th>Value</th></tr>
      <tr><td>First Name</td><td>".$h($reg['first_name'])."</td></tr>
      <tr><td>Last Name</td><td>".$h($reg['last_name'])."</td></tr>
      <tr><td>Nationality</td><td>".$h($reg['nationality'])."</td></tr>
      <tr><td>Place of Birth</td><td>".$h($reg['birth_place'])."</td></tr>
      <tr><td>Date of Birth</td><td>".$h($reg['birth_date'])."</td></tr>
      <tr><td>Highest Education Level</td><td>".$h($reg['highest_education'])."</td></tr>
      <tr><td>Department</td><td>".$h($reg['department'])."</td></tr>
      <tr><td>Attended School Name & Address</td><td>".$h($reg['school_name_address'])."</td></tr>
      <tr><td>Years Attended</td><td>".$h($reg['year_from'])." - ".$h($reg['year_to'])."</td></tr>
    </table>
  </div>

  <!-- Study Plan -->
  <div class='section'>
    <div class='section-title'>Intended Studies</div>
    <table class='grid'>
      <tr><th style='width:28%'>Field</th><th>Value</th></tr>
      <tr><td>Intended Degree</td><td>".$h($reg['intended_degree'])."</td></tr>
      <tr><td>Field of Study</td><td>".$h($reg['field_of_study'])."</td></tr>
    </table>
  </div>

  <!-- Fees -->
  <div class='section'>
    <div class='section-title'>Fees</div>
    <table class='grid'>
      <tr><th style='width:28%'>Item</th><th>Amount</th></tr>
      <tr><td>Registration Fees</td><td>".$h($fmtMoney($reg['registration_fees']))."</td></tr>
      <tr><td>Tuition Fees</td><td>".$h($fmtMoney($reg['tuition_fees']))."</td></tr>
    </table>
  </div>

  <!-- Scholarship & Referral -->
  <div class='section'>
    <div class='section-title'>Scholarship & Referral</div>
    <table class='grid'>
      <tr><th style='width:28%'>Field</th><th>Value</th></tr>
      <tr><td>Scholarship</td><td>".$h($reg['scholarship']).(!empty($reg['scholarship_institution']) ? " — ".$h($reg['scholarship_institution']) : "")."</td></tr>
      <tr><td>Referred by ScholarSync Global</td><td>".$h($reg['referred_by_scholarsync']).(!empty($reg['ref_institution']) ? " — ".$h($reg['ref_institution']) : "")."</td></tr>
    </table>
  </div>

  <!-- Contacts -->
  <div class='section'>
    <div class='section-title'>Contact Details</div>
    <table class='grid'>
      <tr><th style='width:28%'>Field</th><th>Value</th></tr>
      <tr><td>Telephone</td><td>".$h($reg['telephone'])."</td></tr>
      <tr><td>Email</td><td>".$h($reg['email'])."</td></tr>
    </table>
  </div>

  <!-- Commitment -->
  <div class='section'>
    <div class='section-title'>Commitment</div>
    <table class='grid'>
      <tr><th style='width:28%'>Field</th><th>Value</th></tr>
      <tr><td>Name (Mr./Mrs.)</td><td>".$h($reg['commitment_name'])."</td></tr>
      <tr><td>Done at</td><td>".$h($reg['done_at'])."</td></tr>
      <tr><td>Done on</td><td>".$h($reg['done_date'])."</td></tr>
    </table>
  </div>

  <!-- Files -->
  <div class='section'>
    <div class='section-title'>Submitted Files</div>
    ".$secFiles('Passport Photo','passport_photo')."
    ".$secFiles('ID Document','id_document')."
    ".$secFiles('Birth Certificate','birth_certificate')."
    ".$secFiles('Degree & Transcripts (2+ files)','degree_transcript')."
    ".$secFiles('Other Attachments','other_attachment')."
  </div>

  <div class='tiny'>
    Generated automatically by the U.P.A.F.A. admissions system on ".date('Y-m-d H:i').".
  </div>

</body>
</html>";

    // Dompdf render
    if (!class_exists(Options::class)) {
        // Expecting composer autoload
        $autoload = __DIR__.'/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
    }
    if (!class_exists(Options::class)) {
        return null; // Dompdf not available
    }

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Save to /pdfs/YYYY/MM/UPAFA_{id}.pdf
    $pdfDir = 'pdfs/'.date('Y').'/'.date('m');
    if (!is_dir($pdfDir)) mkdir($pdfDir, 0775, true);
    $pdfName = "UPAFA_{$registrationId}.pdf";
    $pdfPath = $pdfDir . '/' . $pdfName;

    file_put_contents($pdfPath, $dompdf->output());
    return $pdfPath;
}
