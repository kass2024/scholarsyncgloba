<?php
require_once __DIR__ . '/db.php';
header("Content-Type: application/json");

// Enable errors only during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ============================================================
   VALIDATE REQUEST
============================================================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit;
}

$staff_id             = intval($_POST['staff_id'] ?? 0);
$platform_tasks       = $_POST['platform_tasks'] ?? [];
$custom_tasks         = $_POST['custom_tasks'] ?? [];
$extra_responsibility = trim($_POST['extra_responsibility'] ?? '');
$university_ids       = $_POST['university_ids'] ?? [];
$platform_ids         = $_POST['platform_ids'] ?? [];
$action               = $_POST['action'] ?? "save_only";
$force_save           = intval($_POST['force_save'] ?? 0);

if ($staff_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid staff selected"]);
    exit;
}

/* ============================================================
   DUPLICATE PREVENTION
============================================================ */
$exists = $conn->query("SELECT id FROM staff_tasks WHERE staff_id=$staff_id LIMIT 1");

if ($exists->num_rows > 0 && $force_save == 0) {
    echo json_encode([
        "status" => "warning",
        "message" => "This staff already has tasks. Review before adding more.",
        "confirm_required" => true
    ]);
    exit;
}

/* ============================================================
   VALIDATE INPUT
============================================================ */
if (empty($platform_tasks) && empty($custom_tasks)) {
    echo json_encode(["status" => "error", "message" => "Please add at least one task"]);
    exit;
}

/* ============================================================
   CLEAN CUSTOM TASKS (comma, newline, numbered formats)
============================================================ */
$clean_custom = [];

foreach ($custom_tasks as $raw) {

    $parts = preg_split('/[\n,]+/', $raw);

    foreach ($parts as $t) {
        $t = trim($t);
        if ($t !== "") {
            $t = preg_replace('/^\d+[\.\)\-\s]+/', '', $t); // strip numbering
            $clean_custom[] = $t;
        }
    }
}

/* ============================================================
   INSERT TASKS
============================================================ */
$insert_ids = [];

$stmt = $conn->prepare("
    INSERT INTO staff_tasks (staff_id, task_name, is_custom, extra_responsibility)
    VALUES (?, ?, ?, ?)
");

foreach ($platform_tasks as $t) {
    $is_custom = 0;
    $stmt->bind_param("isis", $staff_id, $t, $is_custom, $extra_responsibility);
    $stmt->execute();
    $insert_ids[] = $stmt->insert_id;
}

foreach ($clean_custom as $t) {
    $is_custom = 1;
    $stmt->bind_param("isis", $staff_id, $t, $is_custom, $extra_responsibility);
    $stmt->execute();
    $insert_ids[] = $stmt->insert_id;
}

$stmt->close();

/* ============================================================
   SAVE UNIVERSITY ASSIGNMENTS
============================================================ */
foreach ($university_ids as $uid) {
    $uid = intval($uid);
    if ($uid > 0) {
        $conn->query("
            INSERT IGNORE INTO staff_universities (staff_id, university_id)
            VALUES ($staff_id, $uid)
        ");
    }
}

/* ============================================================
   SAVE PLATFORM ASSIGNMENTS
============================================================ */
foreach ($platform_ids as $pid) {
    $pid = intval($pid);
    if ($pid > 0) {
        $conn->query("
            INSERT IGNORE INTO staff_platforms (staff_id, platform_id)
            VALUES ($staff_id, $pid)
        ");
    }
}

/* ============================================================
   FETCH STAFF DATA
============================================================ */
$staff = $conn->query("SELECT full_name, email, position FROM admins WHERE id=$staff_id")->fetch_assoc();
$staff_name     = htmlspecialchars($staff["full_name"]);
$staff_email    = $staff["email"];
$staff_position = $staff["position"] ?? 'Not Assigned';


/* ============================================================
   STOP EARLY IF SAVE ONLY
============================================================ */
if ($action === "save_only") {
    echo json_encode(["status" => "success", "message" => "Saved (no email sent)"]);
    exit;
}

/* ============================================================
   FETCH INSERTED TASKS FOR PDF
============================================================ */
$task_list = [];

if (!empty($insert_ids)) {
    $ids = implode(",", $insert_ids);
    $q = $conn->query("SELECT task_name FROM staff_tasks WHERE id IN ($ids)");

    while ($r = $q->fetch_assoc()) {
        $task_list[] = trim($r['task_name']);
    }
}

sort($task_list);

/* BUILD NUMBERED HTML LIST */
$task_html = "<ol>";
foreach ($task_list as $t) {
    $task_html .= "<li>" . htmlspecialchars($t) . "</li>";
}
$task_html .= "</ol>";

/* ============================================================
   HANDLE EXTRA RESPONSIBILITIES (comma → numbered list)
============================================================ */
$extra_html = "";

if (strpos($extra_responsibility, ",") !== false) {

    $parts = array_filter(array_map('trim', explode(",", $extra_responsibility)));

    $extra_html = "<ol>";
    foreach ($parts as $x) {
        $extra_html .= "<li>" . htmlspecialchars($x) . "</li>";
    }
    $extra_html .= "</ol>";

} else {

    $extra_html = "<p>" . nl2br(htmlspecialchars($extra_responsibility)) . "</p>";
}

/* ============================================================
   GET UNIVERSITIES
============================================================ */
$uni_html = "<ul>";

if (!empty($university_ids)) {
    $ids = implode(",", array_map('intval', $university_ids));
    $q = $conn->query("SELECT name FROM universities WHERE id IN ($ids)");

    while ($r = $q->fetch_assoc()) {
        $uni_html .= "<li>" . htmlspecialchars($r['name']) . "</li>";
    }
}

$uni_html .= "</ul>";

/* ============================================================
   GET PLATFORMS
============================================================ */
$plat_html = "<ul>";

if (!empty($platform_ids)) {
    $ids = implode(",", array_map('intval', $platform_ids));
    $q = $conn->query("SELECT platform_name FROM platforms WHERE id IN ($ids)");

    while ($r = $q->fetch_assoc()) {
        $plat_html .= "<li>" . htmlspecialchars($r['platform_name']) . "</li>";
    }
}

$plat_html .= "</ul>";

/* ============================================================
   GENERATE PDF
============================================================ */
require_once __DIR__ . "/vendor/autoload.php";
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set("isRemoteEnabled", true);

$pdf = new Dompdf($options);

$html = "
<h2 style='color:#006600;'>Visa Consultant Canada – Responsibility Sheet</h2>
<hr>

<h3>Staff Member: <strong>$staff_name</strong></h3>
<p><strong>Staff Position:</strong> {$staff_position}</p>

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

$pdf->loadHtml($html);
$pdf->setPaper("A4", "portrait");
$pdf->render();

$pdf_path = __DIR__ . "/pdfs/task_" . time() . ".pdf";
file_put_contents($pdf_path, $pdf->output());

/* ============================================================
   SEND EMAIL
============================================================ */
require_once "PHPMailer/src/PHPMailer.php";
require_once "PHPMailer/src/SMTP.php";
require_once "PHPMailer/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
$mail->CharSet = "UTF-8";

try {
    $mail->isSMTP();
    $mail->Host       = 'scholarsyncglobal.ca';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'infos@scholarsyncglobal.ca';
    $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    $mail->setFrom('infos@scholarsyncglobal.ca', 'Visa Consultant Canada');
    $mail->addAddress($staff_email, $staff_name);

    $mail->isHTML(true);
    $mail->Subject = "Updated Responsibility Sheet – Visa Consultant Canada";
    $mail->Body = "
        Hello <strong>$staff_name</strong>,<br><br>
        Please find attached your updated responsibility sheet.<br><br>
        Regards,<br>
        <strong>ScholarSync MIS</strong>
    ";

    $mail->addAttachment($pdf_path);

    $mail->send();

    echo json_encode(["status" => "success", "message" => "Tasks saved and email sent"]);
} catch (Exception $e) {

    echo json_encode([
        "status" => "error",
        "message" => "Saved but email sending failed: " . $mail->ErrorInfo
    ]);
}

?>
