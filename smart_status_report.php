<?php
// Enable error reporting for development (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database connection
require_once __DIR__ . '/db.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;

// Status categories
$status_fields = [
    'incomplete_app', 'submitted', 'sent_to_platform', 'admit', 'i20_sent', 'sevis_paid',
    'visa_scheduled', 'visa_approved', 'enrolled', 'addn_doc', 'deny', 'app_start'
];
$status_priority = [
    'deny',
    'enrolled',
    'visa_approved',
    'visa_scheduled',
    'sevis_paid',
    'i20_sent',
    'admit',
    'app_paid',
    'sent_to_platform',
    'submitted',
    'addn_doc',
    'incomplete_app',
    'app_start'
];

// Fetch applicants grouped by status
$columns = implode(', ', array_merge(['first_name', 'email', 'phone_number'], $status_fields));
$query = "SELECT $columns FROM student_applications";
$result = $conn->query($query);

$applicants = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        foreach ($status_priority as $status) {
            if (!empty($row[$status]) && $row[$status] == 1) {
                $row['status'] = $status;
                break;
            }
        }
        if (!empty($row['status'])) {
            $applicants[$row['status']][] = $row;
        }
    }
} else {
    die("Database error: " . $conn->error);
}

// Excel Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $rowIndex = 1;

        foreach ($applicants as $status => $group) {
            $sheet->setCellValue("A$rowIndex", "Status: " . ucfirst(str_replace('_', ' ', $status)));
            $rowIndex++;

            $sheet->setCellValue("A$rowIndex", "Name");
            $sheet->setCellValue("B$rowIndex", "Email");
            $sheet->setCellValue("C$rowIndex", "Phone");
            $sheet->setCellValue("D$rowIndex", "Status");
            $rowIndex++;

            foreach ($group as $a) {
                $sheet->setCellValue("A$rowIndex", $a['first_name']);
                $sheet->setCellValue("B$rowIndex", $a['email']);
                $sheet->setCellValue("C$rowIndex", $a['phone_number']);
                $sheet->setCellValue("D$rowIndex", ucfirst(str_replace('_', ' ', $a['status'])));
                $rowIndex++;
            }
            $rowIndex++;
        }

        if (ob_get_length()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="application_status_report.xlsx"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } catch (Exception $e) {
        die("Excel export failed: " . $e->getMessage());
    }
}

// PDF Export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    ob_start(); ?>
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
            th, td { border: 1px solid #ccc; padding: 5px; }
            th { background-color: #f0f0f0; }
        </style>
    </head>
    <body>
        <h2>Student Application Status Report</h2>
        <?php foreach ($applicants as $status => $group): ?>
            <h3>Status: <?= ucfirst(str_replace('_', ' ', $status)) ?></h3>
            <table>
                <tr>
                    <th>Name</th><th>Email</th><th>Phone</th><th>Status</th>
                </tr>
                <?php foreach ($group as $app): ?>
                    <tr>
                        <td><?= htmlspecialchars($app['first_name']) ?></td>
                        <td><?= htmlspecialchars($app['email']) ?></td>
                        <td><?= htmlspecialchars($app['phone_number']) ?></td>
                        <td><?= ucfirst(str_replace('_', ' ', $app['status'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endforeach; ?>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("application_status_report.pdf");
    exit;
}
?>

<!-- ----------------- HTML Preview Page ------------------ -->
<!DOCTYPE html>
<html>
<head>
    <title>Student Application Report</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; }
        h2 { background-color: #f4f4f4; padding: 10px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 40px; }
        th, td { border: 1px solid #ddd; padding: 10px; }
        th { background-color: #e9e9e9; }
        .export-buttons {
            margin-bottom: 20px;
        }
        .export-buttons a {
            display: inline-block;
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
        }
        .export-buttons a:hover {
            background-color: #427431;
        }
    </style>
</head>
<body>
    <h1>Student Application Report - Grouped by Status</h1>
    <div class="export-buttons">
        <a href="?export=excel">📥 Download Excel</a>
        <a href="?export=pdf">📄 Download PDF</a>
    </div>

    <?php if (!empty($applicants)): ?>
        <?php foreach ($applicants as $status => $group): ?>
            <h2>Status: <?= ucfirst(str_replace('_', ' ', $status)) ?></h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($group as $applicant): ?>
                        <tr>
                            <td><?= htmlspecialchars($applicant['first_name']) ?></td>
                            <td><?= htmlspecialchars($applicant['email']) ?></td>
                            <td><?= htmlspecialchars($applicant['phone_number']) ?></td>
                            <td><?= ucfirst(str_replace('_', ' ', $applicant['status'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No applicant data found.</p>
    <?php endif; ?>
</body>
</html>
