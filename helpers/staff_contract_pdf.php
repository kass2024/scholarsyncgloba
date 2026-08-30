<?php

declare(strict_types=1);



$pcvcPdfAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($pcvcPdfAutoload)) {
    require_once $pcvcPdfAutoload;
}

require_once __DIR__ . '/staff_contract_schema.php';

require_once __DIR__ . '/staff_contract_fields.php';

require_once __DIR__ . '/contract_signature_image.php';



use setasign\Fpdi\Fpdi;



/**

 * Stamp employee fields (text + signature) at dynamic positions onto a PDF contract.

 *

 * @param list<array<string, mixed>> $fields

 */

function pcvc_staff_contract_build_signed_pdf_dynamic(

    string $sourceAbsPath,

    array $fields,

    string $outputAbsPath

): void {

    if (!is_file($sourceAbsPath)) {

        throw new RuntimeException('Source contract PDF not found.');

    }



    $fields = pcvc_staff_contract_normalize_fields($fields);

    if ($fields === []) {

        throw new RuntimeException('No fields to place on the contract.');

    }



    $byPage = [];

    foreach ($fields as $field) {

        $page = (int) $field['page'];

        $byPage[$page][] = $field;

    }



    pcvc_staff_contract_ensure_dirs();

    $tmpFiles = [];



    try {

        $pdf = new Fpdi();

        $pdf->SetAutoPageBreak(false);

        $pageCount = $pdf->setSourceFile($sourceAbsPath);



        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {

            $tplId = $pdf->importPage($pageNo);

            $size = $pdf->getTemplateSize($tplId);

            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);

            $pdf->useTemplate($tplId);



            $pageW = (float) $size['width'];

            $pageH = (float) $size['height'];

            $pageFields = $byPage[$pageNo] ?? [];



            foreach ($pageFields as $field) {

                $x = ($field['xPct'] / 100.0) * $pageW;

                $y = ($field['yPct'] / 100.0) * $pageH;

                $w = ($field['wPct'] / 100.0) * $pageW;

                $h = ($field['hPct'] / 100.0) * $pageH;



                if (($field['type'] ?? '') === 'signature') {

                    $sigData = trim((string) ($field['signature'] ?? ''));

                    if ($sigData === '') {

                        continue;

                    }

                    $sigPng = contract_signature_to_display_png($sigData);

                    if ($sigPng === null) {

                        $sigPng = contract_signature_raw_bytes($sigData);

                    }

                    if ($sigPng === null || $sigPng === '') {

                        continue;

                    }

                    $tmpSig = pcvc_staff_contract_upload_dir() . '/signatures/tmp_' . bin2hex(random_bytes(8)) . '.png';

                    if (file_put_contents($tmpSig, $sigPng) === false) {

                        continue;

                    }

                    $tmpFiles[] = $tmpSig;

                    $pdf->Image($tmpSig, $x, $y, $w, $h, 'PNG');

                    continue;

                }



                $text = trim((string) ($field['value'] ?? ''));

                if ($text === '') {

                    continue;

                }

                if (($field['type'] ?? '') === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {

                    $text = date('F j, Y', strtotime($text));

                }



                $fontSize = max(7.0, min(12.0, $h * 0.55));

                $pdf->SetFont('Helvetica', '', $fontSize);

                $pdf->SetTextColor(15, 23, 42);

                $pdf->SetXY($x, $y);

                $pdf->Cell($w, $h, $text, 0, 0, 'L');

            }

        }



        $outDir = dirname($outputAbsPath);

        if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {

            throw new RuntimeException('Could not create signed PDF directory.');

        }



        $pdf->Output('F', $outputAbsPath);

    } finally {

        foreach ($tmpFiles as $tmp) {

            @unlink($tmp);

        }

    }

}



/**

 * @deprecated Use pcvc_staff_contract_build_signed_pdf_dynamic()

 */

/**
 * Stamp only the employee signature image on the last page (name/date already in document).
 */
function pcvc_staff_contract_stamp_employee_signature_pdf(
    string $sourceAbsPath,
    string $signatureDataUrl,
    string $outputAbsPath
): void {
    if (!is_file($sourceAbsPath)) {
        throw new RuntimeException('Source contract PDF not found.');
    }

    $sigPng = contract_signature_to_display_png($signatureDataUrl);
    if ($sigPng === null) {
        $sigPng = contract_signature_raw_bytes($signatureDataUrl);
    }
    if ($sigPng === null || $sigPng === '') {
        throw new RuntimeException('Invalid signature image.');
    }

    pcvc_staff_contract_ensure_dirs();
    $tmpSig = pcvc_staff_contract_upload_dir() . '/signatures/tmp_' . bin2hex(random_bytes(8)) . '.png';
    if (file_put_contents($tmpSig, $sigPng) === false) {
        throw new RuntimeException('Could not prepare signature image.');
    }

    try {
        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pageCount = $pdf->setSourceFile($sourceAbsPath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplId);
            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);

            if ($pageNo === $pageCount) {
                $pageW = (float) $size['width'];
                $pageH = (float) $size['height'];
                $sigW = min(50.0, $pageW * 0.3);
                $sigH = 16.0;
                $x = 20.0;
                // Below "Employee Name" / on the "Signature:" line in the employee block.
                $y = max(20.0, $pageH - 46.0);

                $pdf->Image($tmpSig, $x, $y, $sigW, $sigH, 'PNG');
            }
        }

        $outDir = dirname($outputAbsPath);
        if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
            throw new RuntimeException('Could not create signed PDF directory.');
        }

        $pdf->Output('F', $outputAbsPath);
    } finally {
        @unlink($tmpSig);
    }
}

function pcvc_staff_contract_build_signed_pdf(

    string $sourceAbsPath,

    string $signatureDataUrl,

    string $typedName,

    string $signedDate,

    string $outputAbsPath

): void {

    $fields = [

        [

            'id' => 'legacy_name',

            'type' => 'text',

            'label' => 'Name',

            'value' => $typedName,

            'signature' => '',

            'page' => 9999,

            'xPct' => 8,

            'yPct' => 82,

            'wPct' => 50,

            'hPct' => 2.5,

        ],

        [

            'id' => 'legacy_sig',

            'type' => 'signature',

            'label' => 'Signature',

            'value' => '',

            'signature' => $signatureDataUrl,

            'page' => 9999,

            'xPct' => 8,

            'yPct' => 86,

            'wPct' => 30,

            'hPct' => 7,

        ],

        [

            'id' => 'legacy_date',

            'type' => 'date',

            'label' => 'Date',

            'value' => $signedDate,

            'signature' => '',

            'page' => 9999,

            'xPct' => 8,

            'yPct' => 93,

            'wPct' => 40,

            'hPct' => 2.5,

        ],

    ];



    if (!is_file($sourceAbsPath)) {

        throw new RuntimeException('Source contract PDF not found.');

    }



    $pdfProbe = new Fpdi();

    $pageCount = $pdfProbe->setSourceFile($sourceAbsPath);

    foreach ($fields as &$field) {

        if ((int) ($field['page'] ?? 0) === 9999) {

            $field['page'] = $pageCount;

        }

    }

    unset($field);



    pcvc_staff_contract_build_signed_pdf_dynamic($sourceAbsPath, $fields, $outputAbsPath);

}
