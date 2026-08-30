<?php
require_once __DIR__ . '/db.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;

ob_start();
include 'pdf-template.php';
$html = ob_get_clean();

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("attendance-report.pdf");
