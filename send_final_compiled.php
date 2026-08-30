<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

/* PHPMailer – same as working file */
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;

/* ===============================
   HEADERS & SETTINGS
================================ */
header('Content-Type: application/json');
set_time_limit(300);
ini_set('memory_limit', '512M');
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ===============================
   LOGGER
================================ */
$LOG = __DIR__ . '/logs/final_report.log';
if (!is_dir(dirname($LOG))) mkdir(dirname($LOG), 0755, true);

function logMsg(string $m): void {
    global $LOG;
    file_put_contents($LOG, "[".date("Y-m-d H:i:s")."] $m\n", FILE_APPEND);
}

logMsg("=== FINAL COMPILED START ===");

/* ===============================
   SECURITY
================================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status"=>"error","message"=>"Invalid request"]);
    exit;
}

/* ===============================
   DOMPDF OPTIONS
================================ */
$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);

$pdfDir = __DIR__ . '/pdfs';
if (!is_dir($pdfDir)) mkdir($pdfDir, 0755, true);

/* ===============================
   FETCH STAFF WITH TASKS
================================ */
$staffQ = $conn->query("
    SELECT DISTINCT a.id, a.full_name, a.email, a.position
    FROM admins a
    INNER JOIN staff_tasks st ON st.staff_id = a.id
    WHERE a.email IS NOT NULL AND a.email <> ''
");

if (!$staffQ || $staffQ->num_rows === 0) {
    logMsg("No staff with tasks");
    echo json_encode(["status"=>"error","message"=>"No staff with tasks"]);
    exit;
}

/* ===============================
   OVERALL PDF HOLDER
================================ */
$overallHtml = "<h2 style='color:#006600;'>FINAL STAFF RESPONSIBILITY REPORT</h2><hr>";

/* ===============================
   PROCESS EACH STAFF
================================ */
while ($staff = $staffQ->fetch_assoc()) {

    $staffId = (int)$staff['id'];
    $staffName = htmlspecialchars($staff['full_name']);
    $staffEmail = $staff['email'];
    $position = $staff['position'];

    logMsg("Processing staff ID $staffId");

    /* -------- TASKS -------- */
    $task_html = "<ol>";
    $tq = $conn->query("
        SELECT task_name
        FROM staff_tasks
        WHERE staff_id = $staffId
        ORDER BY task_name
    ");
    while ($t = $tq->fetch_assoc()) {
        $task_html .= "<li>" . htmlspecialchars($t['task_name']) . "</li>";
    }
    $task_html .= "</ol>";

    /* -------- EXTRA RESPONSIBILITIES -------- */
    $extra_html = "<ul>";
    $eq = $conn->query("
        SELECT DISTINCT extra_responsibility
        FROM staff_tasks
        WHERE staff_id = $staffId AND extra_responsibility <> ''
    ");
    while ($e = $eq->fetch_assoc()) {
        $extra_html .= "<li>" . htmlspecialchars($e['extra_responsibility']) . "</li>";
    }
    $extra_html .= "</ul>";

    /* -------- PLATFORMS -------- */
    $plat_html = "<ul>";
    $pq = $conn->query("
        SELECT p.platform_name
        FROM staff_platforms sp
        JOIN platforms p ON p.id = sp.platform_id
        WHERE sp.staff_id = $staffId
    ");
    while ($p = $pq->fetch_assoc()) {
        $plat_html .= "<li>" . htmlspecialchars($p['platform_name']) . "</li>";
    }
    $plat_html .= "</ul>";

    /* -------- UNIVERSITIES -------- */
    $uni_html = "<ul>";
    $uq = $conn->query("
        SELECT u.name
        FROM staff_universities su
        JOIN universities u ON u.id = su.university_id
        WHERE su.staff_id = $staffId
    ");
    while ($u = $uq->fetch_assoc()) {
        $uni_html .= "<li>" . htmlspecialchars($u['name']) . "</li>";
    }
    $uni_html .= "</ul>";

    /* ===============================
       EXACT SAME PDF TEMPLATE
    ================================ */
    $html = "
    <h2 style='color:#006600;'>Visa Consultant Canada – Responsibility Sheet</h2>
    <hr>

    <h3>Staff Member: <strong>$staffName</strong></h3>
    <p><strong>Staff Position:</strong> $position</p>

    <h3>Assigned Tasks</h3>
    $task_html

    <h3>Assigned Platforms</h3>
    $plat_html

    <h3>Assigned Universities</h3>
    $uni_html

    <h3>Additional Responsibilities</h3>
    $extra_html

    <hr>
    <p style='font-size:12px;color:#777;'>Generated automatically by ScholarSync MIS Smart Allocation System</p>
    ";

    /* -------- STAFF PDF -------- */
    $pdf = new Dompdf($options);
    $pdf->loadHtml($html);
    $pdf->setPaper("A4", "portrait");
    $pdf->render();

    $safe = preg_replace('/[^a-zA-Z0-9]/', '_', $staffName);
    $pdfPath = "$pdfDir/task_$safe.pdf";
    file_put_contents($pdfPath, $pdf->output());

    /* -------- SEND EMAIL -------- */
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'scholarsyncglobal.ca';
        $mail->SMTPAuth = true;
        $mail->Username = 'infos@scholarsyncglobal.ca';
        $mail->Password = getenv('SMTP_PASSWORD') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('infos@scholarsyncglobal.ca', 'Visa Consultant Canada');
        $mail->addAddress($staffEmail, $staffName);
        $mail->isHTML(true);
        $mail->Subject = "Updated Responsibility Sheet – Visa Consultant Canada";
        $mail->Body = "
            Hello <strong>$staffName</strong>,<br><br>
            Please find attached your updated responsibility sheet.<br><br>
            Regards,<br><strong>ScholarSync MIS</strong>
        ";
        $mail->addAttachment($pdfPath);
        $mail->send();

        logMsg("Email sent to $staffEmail");

    } catch (Throwable $e) {
        logMsg("MAIL ERROR ($staffEmail): ".$e->getMessage());
    }

    /* -------- ADD TO OVERALL -------- */
    $overallHtml .= $html . "<div style='page-break-after:always'></div>";
}

/* ===============================
   OVERALL PDF
================================ */
$overallPdf = new Dompdf($options);
$overallPdf->loadHtml($overallHtml);
$overallPdf->setPaper("A4", "portrait");
$overallPdf->render();

$overallName = "OVERALL_STAFF_TASKS_" . date("Ymd_His") . ".pdf";
file_put_contents("$pdfDir/$overallName", $overallPdf->output());

logMsg("Overall PDF generated: $overallName");
logMsg("=== FINAL COMPILED END ===");

echo json_encode([
    "status" => "success",
    "message" => "Emails sent and overall PDF generated",
    "pdf_url" => "pdfs/$overallName"
]);
