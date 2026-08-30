<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

abstract class PDFGeneratorBase {
    protected $contract;
    protected $language;
    protected $conn;
    
    public function __construct($conn, int $contractId) {
        $this->conn = $conn;
        $this->contract = $this->fetchContract($contractId);
        $this->language = $this->contract['language'] ?? 'english';
    }
    
    protected function fetchContract(int $contractId): ?array {
        $stmt = $this->conn->prepare("
            SELECT
                pc.id,
                pc.contract_token,
                pc.language,
                pc.status,
                pc.company_name,
                pc.company_email,
                pc.company_phone,
                pc.company_address,
                pc.representative_name,
                pc.representative_title,
                pc.representative_email,
                pc.signed_date,
                COALESCE(NULLIF(pc.signature_image, ''), ps.signature_image) AS signature_image
            FROM partner_contracts pc
            LEFT JOIN partner_signatures ps ON pc.id = ps.contract_id
            WHERE pc.id = ? AND pc.status = 'signed'
            LIMIT 1
        ");
        $stmt->bind_param("i", $contractId);
        $stmt->execute();
        $contract = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $contract ?: null;
    }
    
    protected function processSignatureImage(?string $signatureImage): string {
        if (empty($signatureImage)) {
            return '<div class="signature-placeholder">_________________________</div>';
        }
        
        // Handle different signature formats
        if (strpos($signatureImage, 'data:image/') === 0) {
            return '<img src="' . $this->esc($signatureImage) . '" alt="Signature" class="signature-img">';
        } 
        elseif (preg_match('/^[a-zA-Z0-9\/+\r\n=]+$/', $signatureImage)) {
            return '<img src="data:image/png;base64,' . $this->esc($signatureImage) . '" alt="Signature" class="signature-img">';
        }
        elseif (file_exists($signatureImage)) {
            $base64 = base64_encode(file_get_contents($signatureImage));
            return '<img src="data:image/png;base64,' . $this->esc($base64) . '" alt="Signature" class="signature-img">';
        }
        
        return '<div class="signature-placeholder">_________________________</div>';
    }
    
    protected function getImageAsset(string $relativePath, string $alt, string $cssClass = 'signature-img'): string {
        $path = __DIR__ . '/' . ltrim($relativePath, '/');
        if (!file_exists($path)) {
            return '<div class="signature-placeholder">_________________________</div>';
        }
        $base64 = base64_encode(file_get_contents($path));
        return '<img src="data:image/png;base64,' . $this->esc($base64) . '" alt="' . $this->esc($alt) . '" class="' . $this->esc($cssClass) . '">';
    }

    protected function getManagerSignature(): string {
        return $this->getImageAsset('admin/signature-manager.png', 'Managing Director Signature', 'sig-manager-img');
    }

    protected function getCompanyStamp(): string {
        return $this->getImageAsset('admin/employer-signature.png', 'Company Stamp', 'sig-stamp-img');
    }

    protected function formatSignedDate(?string $date): string {
        $date = trim((string) $date);
        if ($date === '') {
            return '';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return $this->esc($date);
        }
        return date('Y/m/d', $ts);
    }
    
    protected function getEmployerSignature(): string {
        return $this->getCompanyStamp();
    }
    
    protected function esc(?string $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
    
    protected function getBaseStyles(): string {
        return '
        @page {
            size: A4;
            margin: 20mm 15mm 20mm 15mm;
            @bottom-center {
                content: counter(page);
                font-size: 9pt;
                color: #666;
                border-top: 1px solid #ddd;
                padding-top: 5mm;
                width: 100%;
                text-align: center;
            }
        }
        
        body { 
            font-family: "Georgia", "Times New Roman", serif; 
            font-size: 11pt; 
            line-height: 1.6; 
            margin: 0;
            color: #1a1a1a;
            background: #ffffff;
        }
        
        .page-break {
            page-break-before: avoid;
            page-break-after: avoid;
            page-break-inside: avoid;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30pt;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 20pt;
            position: relative;
        }
        
        .header::before {
            content: "";
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: #e74c3c;
        }
        
        h1 { 
            text-align: center; 
            font-size: 22pt; 
            margin: 15pt 0 15pt 0;
            font-weight: bold;
            letter-spacing: 2px;
            color: #2c3e50;
            text-transform: uppercase;
        }
        
        .subtitle {
            text-align: center;
            font-size: 14pt;
            font-style: italic;
            color: #7f8c8d;
            margin-bottom: 25pt;
            letter-spacing: 0.5px;
        }
        
        h2 { 
            font-size: 16pt; 
            font-weight: bold; 
            margin-top: 32pt; 
            margin-bottom: 16pt;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 8pt;
            position: relative;
        }
        
        h2::before {
            content: "";
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 50px;
            height: 2px;
            background: #e74c3c;
        }
        
        h3 { 
            font-size: 14pt; 
            font-weight: bold; 
            margin-top: 24pt; 
            margin-bottom: 12pt;
            color: #34495e;
        }
        
        h4 { 
            font-size: 12pt; 
            font-weight: bold; 
            margin: 18pt 0 10pt 0;
            color: #34495e;
        }
        
        p { 
            margin: 0 0 12pt 0; 
            text-align: justify;
            orphans: 2;
            widows: 2;
            text-indent: 0;
        }
        
        strong { 
            font-weight: bold; 
            color: #2c3e50;
        }
        
        ul, ol {
            margin: 12pt 0;
            padding-left: 25pt;
        }
        
        li {
            margin-bottom: 6pt;
            line-height: 1.7;
        }
        
        .company-info { 
            padding: 18pt; 
            margin: 18pt 0; 
            border: 2px solid #3498db;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.1);
        }
        
        .company-info h4 { 
            margin: 0 0 12pt 0; 
            color: #2c3e50;
            font-size: 14pt;
            border-bottom: 1px solid #3498db;
            padding-bottom: 6pt;
        }
        
        .signature-section {
            margin-top: 50pt;
            page-break-inside: avoid;
            page-break-before: avoid;
            page-break-after: avoid;
        }
        
        .signature-grid { 
            display: flex; 
            justify-content: space-between;
            margin-top: 40pt;
            gap: 50px;
            page-break-inside: avoid;
        }
        
        .signature-box { 
            flex: 1;
            text-align: center;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 35px 25px;
            border: 2px solid #3498db;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.15);
            page-break-inside: avoid;
            position: relative;
        }
        
        .signature-box::before {
            content: "";
            position: absolute;
            top: -1px;
            left: -1px;
            right: -1px;
            bottom: -1px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            z-index: -1;
            border-radius: 15px;
            opacity: 0.1;
        }
        
        .signature-box p { 
            margin: 12pt 0; 
            font-size: 11pt;
            line-height: 1.5;
        }
        
        .signature-box strong {
            font-size: 12pt;
            color: #2c3e50;
        }
        
        .signature-label {
            font-weight: bold;
            font-size: 13pt;
            margin-top: 30pt !important;
            margin-bottom: 20pt !important;
            color: #2c3e50;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            border-top: 2px solid #3498db;
            padding-top: 20pt;
            position: relative;
        }
        
        .signature-label::before {
            content: "";
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 2px;
            background: #e74c3c;
        }
        
        .signature-line { 
            border-bottom: 2px solid #2c3e50;
            min-height: 220px;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin: 20pt 0 25pt 0;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 30px 20px;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            position: relative;
        }
        
        .signature-line::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #3498db, #e74c3c, #3498db);
            border-radius: 12px 12px 0 0;
        }
        
        .signature-img {
            max-height: 180px;
            max-width: 90%;
            width: auto;
            height: auto;
            object-fit: contain;
            image-rendering: auto;
            image-rendering: crisp-edges;
            image-rendering: -moz-crisp-edges;
            image-rendering: pixelated;
            image-rendering: optimize-contrast;
            display: block;
            margin: 0 auto;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }
        
        .signature-placeholder {
            font-size: 18pt;
            letter-spacing: 4px;
            color: #95a5a6;
            font-family: "Courier New", monospace;
            font-weight: 300;
        }
        
        .date-line {
            margin-top: 20pt;
            font-size: 11pt;
            font-style: italic;
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .footer {
            margin-top: 60pt;
            text-align: center;
            font-size: 10pt;
            color: #7f8c8d;
            border-top: 2px solid #3498db;
            padding-top: 20pt;
            page-break-inside: avoid;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20pt;
            border-radius: 12px 12px 0 0;
        }
        
        .company-name-header {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 25pt;
            padding-bottom: 15pt;
            border-bottom: 3px solid #2c3e50;
            display: inline-block;
            color: #2c3e50;
            letter-spacing: 1px;
        }
        
        /* Prevent content from breaking across pages */
        .content-wrapper {
            page-break-after: avoid;
            page-break-before: avoid;
            page-break-inside: avoid;
        }
        
        /* Enhanced print styles */
        @media print {
            body { 
                font-size: 10pt; 
                line-height: 1.5;
            }
            .signature-section { 
                margin-top: 40pt; 
            }
            .signature-img {
                max-height: 160px;
                image-rendering: auto;
                filter: none;
            }
            .signature-box {
                border: 2px solid #2c3e50;
                box-shadow: none;
                page-break-inside: avoid;
                background: #ffffff;
            }
            .signature-line {
                background: #ffffff;
                border: 1px solid #2c3e50;
            }
            .page-break {
                page-break-before: avoid;
                page-break-inside: avoid;
            }
            .header {
                border-bottom: 2px solid #2c3e50;
            }
            h2 {
                border-bottom: 1px solid #2c3e50;
            }
            .company-info {
                background: #ffffff;
                border: 1px solid #2c3e50;
            }
            .footer {
                background: #ffffff;
                border-top: 1px solid #2c3e50;
            }
        }
        ';
    }
    
    protected function createPDF(string $html): string {
        $options = new Options();
        $options->set('defaultFont', 'Georgia');
        $options->set('dpi', 300);
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('defaultPaperSize', 'a4');
        $options->set('defaultPaperOrientation', 'portrait');
        $options->set('debugKeepTemp', false);
        $options->set('debugCss', false);
        $options->set('debugLayout', false);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $pdfDir = __DIR__ . '/contracts/partners';
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0777, true);
        }
        
        $filename = $this->getFilename();
        $pdfPath = $pdfDir . '/' . $filename;
        
        file_put_contents($pdfPath, $dompdf->output());
        
        return $pdfPath;
    }
    
    abstract protected function getFilename(): string;
    abstract protected function getContractContent(): string;
    
    public function generate(): ?string {
        if (!$this->contract) {
            return null;
        }
        
        $html = $this->getContractContent();
        return $this->createPDF($html);
    }
}
