<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

abstract class ProfessionalPDFGenerator {
    protected $conn;
    protected $contract;
    protected $language;
    protected $isFrench;
    
    public function __construct($conn, int $contractId) {
        $this->conn = $conn;
        $this->contract = $this->fetchContract($contractId);
        $this->language = $this->contract['language'] ?? 'english';
        $this->isFrench = $this->language === 'french';
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
    
    protected function t(string $english, string $french): string {
        return $this->isFrench ? $french : $english;
    }
    
    protected function esc(?string $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
    
    protected function getImageAsset(string $relativePath, string $alt, string $cssClass = 'sig-pdf-img'): string {
        $path = __DIR__ . '/' . ltrim($relativePath, '/');
        if (!file_exists($path)) {
            return '';
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

    protected function getEmployerSignature(): string {
        return $this->getCompanyStamp();
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
    
    protected function processSignatureImage(?string $signatureImage, string $cssClass = 'signature-img'): string {
        if (empty($signatureImage)) {
            return '<div class="signature-placeholder"></div>';
        }
        
        if (strpos($signatureImage, 'data:image/') === 0) {
            return '<img src="' . $this->esc($signatureImage) . '" alt="Signature" class="' . $this->esc($cssClass) . '">';
        } 
        elseif (preg_match('/^[a-zA-Z0-9\/+\r\n=]+$/', $signatureImage)) {
            return '<img src="data:image/png;base64,' . $this->esc($signatureImage) . '" alt="Signature" class="' . $this->esc($cssClass) . '">';
        }
        elseif (file_exists($signatureImage)) {
            $base64 = base64_encode(file_get_contents($signatureImage));
            return '<img src="data:image/png;base64,' . $this->esc($base64) . '" alt="Signature" class="' . $this->esc($cssClass) . '">';
        }
        
        return '<div class="signature-placeholder"></div>';
    }
    
    protected function getProfessionalStyles(): string {
        return '
        @page {
            size: A4;
            margin: 1.5cm 1.5cm 1.5cm 1.5cm; /* ultra-compact margins */
            @bottom-center {
                content: counter(page);
                font-size: 8pt;
                color: #666;
                font-family: "Georgia", serif;
                border-top: 1px solid #ddd;
                padding-top: 2mm;
                width: 100%;
                text-align: center;
            }
            @top-center {
                content: "";
                border-bottom: 1px solid #ddd;
                padding-bottom: 2mm;
                width: 100%;
            }
        }
        
        * {
            box-sizing: border-box;
        }
        
        body { 
            font-family: "Georgia", "Times New Roman", serif; 
            font-size: 12pt; 
            line-height: 1.4; 
            margin: 0;
            padding: 0;
            color: #1a1a1a;
            background: #ffffff;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            letter-spacing: 0.01em;
        }
        
        .document {
            max-width: 100%;
            margin: 0 auto;
        }
        
        .page-break {
            page-break-before: avoid;
            page-break-after: avoid;
            page-break-inside: avoid;
        }
        
                
        .header {
            text-align: center;
            margin-bottom: 0pt;
            padding-bottom: 0pt;
            border-bottom: 2px solid #1a1a1a;
            position: relative;
        }
        
        h1 { 
            text-align: center; 
            font-size: 22pt; 
            margin: 0pt 0 0pt 0;
            font-weight: bold;
            letter-spacing: 1px;
            color: #1a1a1a;
            text-transform: uppercase;
            line-height: 0.9;
        }
        
        .subtitle {
            text-align: center;
            font-size: 13pt;
            font-style: italic;
            color: #555;
            margin-bottom: 2pt;
            letter-spacing: 0.3px;
        }
        
        h2 { 
            font-size: 16pt; 
            font-weight: bold; 
            margin-top: 2pt; 
            margin-bottom: 1pt;
            color: #1a1a1a;
            border-bottom: 1px solid #1a1a1a;
            padding-bottom: 0pt;
            page-break-after: avoid;
            page-break-inside: avoid;
            text-align: left;
        }
        
        h3 { 
            font-size: 14pt; 
            font-weight: bold; 
            margin-top: 8pt; 
            margin-bottom: 4pt;
            color: #1a1a1a;
            page-break-after: avoid;
            text-align: left;
        }
        
        h4 { 
            font-size: 13pt; 
            font-weight: bold; 
            margin: 6pt 0 3pt 0;
            color: #1a1a1a;
            text-align: left;
        }
        
        p { 
            margin: 0 0 3pt 0; 
            text-align: left !important;
            orphans: 2;
            widows: 2;
            text-indent: 0;
            line-height: 1.4;
            font-size: 12pt;
            font-weight: 400;
            color: #1a1a1a;
            letter-spacing: 0.01em;
        }
        
        strong { 
            font-weight: 700; 
            color: #0d47a1;
            letter-spacing: 0.02em;
        }
        
        ul, ol {
            margin: 2pt 0;
            padding-left: 24pt;
            line-height: 1.4;
            text-align: left;
        }
        
        li {
            margin-bottom: 1pt;
            line-height: 1.4;
            text-align: left;
            font-size: 12pt;
            color: #2c2c2c;
        }
        
        .party-info { 
            padding: 4pt 6pt; 
            margin: 2pt 0; 
            border: 1px solid #e3f2fd;
            background: #f8faff;
            border-radius: 3px;
            page-break-inside: avoid;
        }
        
        .party-info h4 { 
            margin: 0 0 3pt 0; 
            color: #0d47a1;
            font-size: 15pt;
            font-weight: 700;
            border-bottom: 1px solid #e3f2fd;
            padding-bottom: 2pt;
            text-align: left;
            letter-spacing: 0.02em;
        }
        
        .signature-section {
            margin-top: 25pt;
            padding: 15pt 0;
            page-break-inside: avoid;
            page-break-before: avoid;
            page-break-after: avoid;
            border-top: 2px solid #e3f2fd;
        }
        
        .signature-container {
            display: flex;
            justify-content: space-between;
            gap: 50px;
            margin-top: 15pt;
            page-break-inside: avoid;
        }
        
        .signature-block {
            flex: 1;
            min-width: 0;
            page-break-inside: avoid;
            padding: 8pt;
            border: 1px solid #e8eaf6;
            border-radius: 4px;
            background: #fafbff;
        }
        
        .signature-box {
            border: 1px solid #e3f2fd;
            padding: 12px 15px;
            text-align: center;
            background: #ffffff;
            page-break-inside: avoid;
            border-radius: 4px;
        }
        
        .company-title {
            font-size: 15pt;
            font-weight: 700;
            margin-bottom: 8pt;
            text-align: center;
            color: #0d47a1;
            border-bottom: 2px solid #0d47a1;
            padding-bottom: 4pt;
            letter-spacing: 0.02em;
        }
        
        .representative-info {
            margin-bottom: 6pt;
            text-align: left;
            font-size: 11pt;
            color: #424242;
        }
        
        .representative-info p {
            margin: 1pt 0;
            text-align: left;
            font-size: 11pt;
            line-height: 1.3;
            color: #424242;
        }
        
        .signature-label {
            font-weight: 700;
            font-size: 12pt;
            margin: 12pt 0 8pt 0;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-top: 1px solid #e3f2fd;
            padding-top: 6pt;
            color: #0d47a1;
        }
        
        .signature-area {
            border: none;
            min-height: 300px;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin: 8pt 0;
            background: #ffffff;
            padding: 25px;
            position: relative;
        }
        
        .signature-img {
            max-height: 280px;
            max-width: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            transform: scale(1.2);
            transform-origin: center;
        }
        
        .date-line {
            margin-top: 6pt;
            text-align: left;
            font-size: 11pt;
            color: #424242;
        }

        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12pt;
            page-break-inside: avoid;
        }

        .sig-table td {
            vertical-align: top;
            width: 50%;
            padding: 10pt 12pt;
            font-size: 11pt;
            line-height: 1.45;
            color: #424242;
        }

        .sig-table td + td {
            border-left: 1px solid #e3f2fd;
        }

        .sig-party-title {
            font-size: 12pt;
            font-weight: 700;
            margin: 0 0 10pt 0;
            color: #0d47a1;
        }

        .sig-detail-line {
            margin: 0 0 8pt 0;
        }

        .sig-manager-img {
            max-height: 52pt;
            max-width: 220pt;
            display: block;
            margin: 4pt 0 8pt 0;
        }

        .sig-stamp-img {
            max-height: 100pt;
            max-width: 220pt;
            display: block;
            margin: 4pt 0 8pt 0;
        }

        .partner-sig-img {
            max-height: 72pt;
            max-width: 100%;
            display: block;
            margin: 4pt 0 8pt 0;
        }

        .partner-sig-box {
            border: 1px dashed #9ca3af;
            min-height: 72pt;
            padding: 6pt;
            margin: 4pt 0 8pt 0;
            background: #ffffff;
        }
        
        /* Print optimization - Enhanced for Smart UI */
        @media print {
            body { 
                font-size: 12pt; 
                line-height: 1.4;
                letter-spacing: 0.01em;
                color: #1a1a1a;
            }
            
            .header {
                margin-bottom: 0pt;
                padding-bottom: 0pt;
            }
            
            h1 {
                margin: 0pt 0 0pt 0;
                line-height: 0.9;
                font-size: 22pt;
                letter-spacing: 0.02em;
            }
            
            .subtitle {
                margin-bottom: 2pt;
                font-size: 13pt;
            }
            
            h2 {
                font-size: 16pt;
                margin-top: 2pt;
                margin-bottom: 1pt;
                padding-bottom: 0pt;
                color: #0d47a1;
                font-weight: 700;
                letter-spacing: 0.02em;
            }
            
            h3 {
                font-size: 14pt;
                margin-top: 8pt;
                margin-bottom: 4pt;
                font-weight: 700;
                color: #1a1a1a;
            }
            
            h4 {
                font-size: 13pt;
                margin: 6pt 0 3pt 0;
                font-weight: 700;
                color: #1a1a1a;
            }
            
            p {
                margin: 0 0 3pt 0;
                text-align: left !important;
                line-height: 1.4;
                font-size: 12pt;
                font-weight: 400;
                color: #1a1a1a;
                letter-spacing: 0.01em;
            }
            
            strong {
                font-weight: 700;
                color: #0d47a1;
                letter-spacing: 0.02em;
            }
            
            ul, ol {
                margin: 2pt 0;
                padding-left: 24pt;
                line-height: 1.4;
            }
            
            li {
                margin-bottom: 1pt;
                line-height: 1.4;
                font-size: 12pt;
                color: #2c2c2c;
            }
            
            .party-info {
                border: 1px solid #e3f2fd;
                background: #f8faff;
                padding: 4pt 6pt;
                margin: 2pt 0;
                border-radius: 3px;
            }
            
            .party-info h4 {
                margin: 0 0 3pt 0;
                padding-bottom: 2pt;
                color: #0d47a1;
                font-size: 15pt;
                font-weight: 700;
                border-bottom: 1px solid #e3f2fd;
                letter-spacing: 0.02em;
            }
            
            .signature-section { 
                margin-top: 25pt;
                padding: 15pt 0;
                border-top: 2px solid #e3f2fd;
            }
            
            .signature-container {
                display: flex;
                justify-content: space-between;
                gap: 50px;
                margin-top: 15pt;
            }
            
            .signature-block {
                flex: 1;
                min-width: 0;
                padding: 8pt;
                border: 1px solid #e8eaf6;
                border-radius: 4px;
                background: #fafbff;
            }
            
            .signature-box {
                border: 1px solid #e3f2fd;
                background: #ffffff;
                padding: 12px 15px;
                border-radius: 4px;
            }
            
            .company-title {
                font-size: 15pt;
                font-weight: 700;
                margin-bottom: 8pt;
                color: #0d47a1;
                border-bottom: 2px solid #0d47a1;
                padding-bottom: 4pt;
                letter-spacing: 0.02em;
            }
            
            .representative-info {
                margin-bottom: 6pt;
                font-size: 11pt;
                color: #424242;
            }
            
            .representative-info p {
                margin: 1pt 0;
                font-size: 11pt;
                line-height: 1.3;
                color: #424242;
            }
            
            .signature-label {
                font-weight: 700;
                font-size: 12pt;
                margin: 12pt 0 8pt 0;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                border-top: 1px solid #e3f2fd;
                padding-top: 6pt;
                color: #0d47a1;
            }
            
            .signature-area {
                border: none;
                background: #ffffff;
                min-height: 300px;
                padding: 25px;
                margin: 8pt 0;
            }
            
            .signature-img {
                max-height: 280px;
                max-width: 100%;
                border-radius: 2px;
                transform: scale(1.2);
                transform-origin: center;
            }
            
            .date-line {
                margin-top: 6pt;
                font-size: 12pt;
                font-style: italic;
                color: #666;
                font-weight: 500;
            }
            
            .footer {
                border-top: none;
                margin-top: 20pt;
                padding-top: 10pt;
            }
            
            .page-break {
                page-break-before: avoid;
                page-break-inside: avoid;
            }
        }
        
        /* Ensure no content is cut off */
        .avoid-break {
            page-break-inside: avoid;
            page-break-before: avoid;
            page-break-after: avoid;
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
    
    protected function getFilename(): string {
        $prefix = $this->isFrench ? 'contrat-partenariat' : 'partner-contract';
        return $prefix . '-' . $this->contract['id'] . '-' . date('Y-m-d') . '.pdf';
    }
    
    protected function getContractTitle(): string {
        return $this->t('Strategic Partnership Agreement', 'Accord de Partenariat Stratégique');
    }
    
    protected function getSubtitle(): string {
        return $this->t('A Professional Partnership for Global Education Services', 'Un Partenariat Professionnel pour les Services d\'Éducation Mondiale');
    }
    
    protected function getPartiesSection(): string {
        $partnerName = $this->esc($this->contract['company_name']);
        $repName = $this->esc($this->contract['representative_name']);
        $repTitle = $this->esc($this->contract['representative_title']);
        $repEmail = $this->esc($this->contract['representative_email']);
        $companyEmail = $this->esc($this->contract['company_email']);
        $companyPhone = $this->esc($this->contract['company_phone']);
        $companyAddress = $this->esc($this->contract['company_address']);
        
        $between = $this->t('Between', 'Entre');
        $and = $this->t('and', 'et');
        $companyName = $this->t('Company Name', 'Nom de l\'Entreprise');
        $representative = $this->t('Representative', 'Représentant');
        $position = $this->t('Position', 'Fonction');
        $email = $this->t('Email', 'Email');
        $phone = $this->t('Phone', 'Téléphone');
        $fullAddress = $this->t('Full Address', 'Adresse complète');
        
        $parrotInfo = $this->t(
            'ScholarSync Global Co. Ltd<br>
            Dr Jean Pierre Twajamahoro<br>
            Owner & Managing Director<br>
            Email: infos@scholarsyncglobal.ca<br>
            Phone: +1 (438) 290-6688<br>
            Rwanda Address: Rwanda - Kigali<br>
            Town Center Building (near Simba Supermarket),<br>
            2nd Floor, Door: F2B-022C, Nyarugenge<br>
            Canada Address:<br>
            294 Rue Vezina App 202; Lasalle, Quebec H8R 3M9',
            
            'ScholarSync Global Co. Ltd<br>
            Dr Jean Pierre Twajamahoro<br>
            Propriétaire & Directeur Général<br>
            Adresse courriel: infos@scholarsyncglobal.ca<br>
            Téléphone: +1 (438) 290-6688<br>
            Adresse au Rwanda: Rwanda - Kigali<br>
            Town Center Building (near Simba Supermarket),<br>
            2nd Floor, Door: F2B-022C, Nyarugenge<br>
            Adresse au Canada:<br>
            294 Rue Vezina App 202; Lasalle, Quebec H8R 3M9'
        );
        
        return "<h2>1. " . $this->t('PARTIES', 'PARTIES') . "</h2><p><strong>$between</strong></p><div class='party-info'><h4>$partnerName</h4><p><strong>$companyName:</strong> $partnerName</p><p><strong>$representative:</strong> $repName</p><p><strong>$position:</strong> $repTitle</p><p><strong>$email:</strong> $companyEmail</p><p><strong>$phone:</strong> $companyPhone</p><p><strong>$fullAddress:</strong> $companyAddress</p></div><p><strong>$and</strong></p><div class='party-info'><h4>ScholarSync Global Co. Ltd</h4><p>$parrotInfo</p></div>";
    }
    
    protected function getSignatureSection(): string {
        $partnerName = $this->esc($this->contract['company_name']);
        $repName = $this->esc($this->contract['representative_name']);
        $repTitle = $this->esc($this->contract['representative_title']);
        $partnerEmail = $this->esc($this->contract['representative_email'] ?: $this->contract['company_email']);
        $partnerPhone = $this->esc($this->contract['company_phone']);
        $partnerAddress = $this->esc($this->contract['company_address']);
        $signedDate = $this->formatSignedDate($this->contract['signed_date'] ?? '');
        
        $partnerSignature = $this->processSignatureImage($this->contract['signature_image'] ?? null, 'partner-sig-img');
        $managerSignature = $this->getManagerSignature();
        $companyStamp = $this->getCompanyStamp();
        
        $signaturesTitle = $this->t('15. SIGNATURES', '15. SIGNATURES');
        $executedBy = $this->t(
            'This Strategic Partnership Agreement is executed by authorized representatives of both parties on the date indicated below:',
            'Cet Accord de Partenariat Stratégique est exécuté par les représentants autorisés des deux parties à la date indiquée ci-dessous :'
        );
        $leftTitle = $this->t('For ScholarSync Global Co. Ltd', 'Pour ScholarSync Global Co. Ltd');
        $rightTitle = $this->t('Partner Company', 'Entreprise Partenaire');
        $nameLabel = $this->t('Name', 'Nom');
        $titleLabel = $this->t('Title', 'Fonction');
        $signatureLabel = $this->t('Signature', 'Signature');
        $stampLabel = $this->t('Company Stamp', 'Cachet de l\'entreprise');
        $dateLabel = $this->t('Date', 'Date');
        $companyNameLabel = $this->t('Company Name', 'Nom de l\'entreprise');
        $repNameLabel = $this->t('Representative Name', 'Nom du représentant');
        $emailLabel = $this->t('Email', 'Courriel');
        $phoneLabel = $this->t('Phone', 'Téléphone');
        $addressLabel = $this->t('Company Address', 'Adresse de l\'entreprise');
        
        $parrotRepName = $this->t('Dr. Jean Pierre Twajamahoro', 'Dr Jean Pierre Twajamahoro');
        $parrotRepTitle = $this->t('Owner & Managing Director', 'Propriétaire & Directeur Général');
        
        return "
        <div class='signature-section'>
            <h2>$signaturesTitle</h2>
            <p>$executedBy</p>
            <table class='sig-table'>
                <tr>
                    <td>
                        <p class='sig-party-title'>$leftTitle</p>
                        <p class='sig-detail-line'><strong>$nameLabel:</strong> $parrotRepName</p>
                        <p class='sig-detail-line'><strong>$titleLabel:</strong> $parrotRepTitle</p>
                        <p class='sig-detail-line'><strong>$signatureLabel:</strong></p>
                        $managerSignature
                        <p class='sig-detail-line'><strong>$stampLabel:</strong></p>
                        $companyStamp
                        <p class='sig-detail-line'><strong>$dateLabel:</strong> $signedDate</p>
                    </td>
                    <td>
                        <p class='sig-party-title'>$rightTitle</p>
                        <p class='sig-detail-line'><strong>$companyNameLabel:</strong> $partnerName</p>
                        <p class='sig-detail-line'><strong>$repNameLabel:</strong> $repName</p>
                        <p class='sig-detail-line'><strong>$titleLabel:</strong> $repTitle</p>
                        <p class='sig-detail-line'><strong>$emailLabel:</strong> $partnerEmail</p>
                        <p class='sig-detail-line'><strong>$phoneLabel:</strong> $partnerPhone</p>
                        <p class='sig-detail-line'><strong>$addressLabel:</strong> $partnerAddress</p>
                        <p class='sig-detail-line'><strong>$signatureLabel:</strong></p>
                        <div class='partner-sig-box'>$partnerSignature</div>
                        <p class='sig-detail-line'><strong>$dateLabel:</strong> $signedDate</p>
                    </td>
                </tr>
            </table>
        </div>";
    }
    
    protected function getFooterSection(): string {
        $footerText = $this->t(
            'This agreement constitutes the entire understanding between parties and supersedes all prior discussions, negotiations, and agreements.<br>IN WITNESS WHEREOF, parties have executed this Strategic Partnership Agreement as of date indicated above.',
            'Cet accord constitue l\'entente complète entre les parties et remplace toutes les discussions, négociations et accords antérieurs.<br>EN FOI DE QUOI, les parties ont exécuté cet Accord de Partenariat Stratégique à la date indiquée ci-dessus.'
        );
        
        return "<div class='footer avoid-break'><p>$footerText</p></div>";
    }
    
    abstract protected function getMainContent(): string;
    
    public function generate(): ?string {
        if (!$this->contract) {
            return null;
        }
        
        $html = '<!DOCTYPE html><html lang="' . ($this->isFrench ? 'fr' : 'en') . '"><head><meta charset="utf-8"><title>' . $this->getContractTitle() . '</title><style>' . $this->getProfessionalStyles() . '</style></head><body><div class="document"><div class="header"><h1>' . $this->getContractTitle() . '</h1><div class="subtitle">' . $this->getSubtitle() . '</div></div>' . $this->getPartiesSection() . $this->getMainContent() . $this->getSignatureSection() . $this->getFooterSection() . '</div></body></html>';
        
        return $this->createPDF($html);
    }
}
