<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>PDF Layout Preview</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .preview-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #1a1a1a;
        }
        .preview-title {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            color: #1a1a1a;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .preview-subtitle {
            font-size: 14px;
            font-style: italic;
            color: #555;
            margin: 10px 0 0 0;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            background: #fafafa;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .warning {
            color: #dc3545;
            font-weight: bold;
        }
        .test-controls {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #e8f4fd;
            border-radius: 8px;
        }
        .btn {
            padding: 12px 24px;
            margin: 0 10px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn:hover {
            opacity: 0.8;
        }
        .preview-box {
            border: 2px solid #ddd;
            border-radius: 8px;
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
        }
        .preview-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 15px 0;
            color: #1a1a1a;
        }
        .preview-content {
            font-size: 14px;
            line-height: 1.6;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-success {
            background: #28a745;
        }
        .status-error {
            background: #dc3545;
        }
        .status-warning {
            background: #ffc107;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='preview-header'>
            <h1 class='preview-title'>PDF Layout Preview System</h1>
            <p class='preview-subtitle'>Test and confirm that 1. PARTIES starts on page 1</p>
        </div>";

// Get contracts for testing
$stmt = $conn->prepare("SELECT id, language, company_name, status FROM partner_contracts ORDER BY id DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
$contracts = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($contracts)) {
    echo "<div class='test-section'>
            <h3 class='warning'>No Contracts Found</h3>
            <p>No contracts available for testing. Please create a test contract first.</p>
        </div>";
} else {
    echo "<div class='test-section'>
            <h3>Available Contracts for Testing</h3>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr style='background: #f8f9fa;'>
                    <th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Contract ID</th>
                    <th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Company</th>
                    <th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Language</th>
                    <th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Status</th>
                    <th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Actions</th>
                </tr>";
    
    foreach ($contracts as $contract) {
        $statusClass = $contract['status'] === 'signed' ? 'status-success' : 'status-warning';
        $statusText = ucfirst($contract['status']);
        
        echo "<tr>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$contract['id']}</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($contract['company_name']) . "</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>" . ucfirst($contract['language']) . "</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>
                        <span class='status-indicator $statusClass'></span>
                        $statusText
                    </td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>";
        
        if ($contract['status'] === 'signed') {
            echo "<a href='?test={$contract['id']}&lang={$contract['language']}' class='btn btn-success'>Test PDF Layout</a>";
            echo "<a href='?preview={$contract['id']}&lang={$contract['language']}' class='btn btn-primary'>Preview HTML</a>";
        } else {
            echo "<span style='color: #6c757d;'>Not signed</span>";
        }
        
        echo "</td></tr>";
    }
    
    echo "</table></div>";
    
    // Handle test requests
    if (isset($_GET['test']) && isset($_GET['lang'])) {
        $contractId = (int)$_GET['test'];
        $language = $_GET['lang'];
        
        echo "<div class='preview-box'>
            <h3 class='preview-title'>Testing PDF Layout - Contract ID: $contractId ($language)</h3>";
            
        // Generate PDF
        if ($language === 'french') {
            require_once __DIR__ . '/generate-partner-contract-pdf-french-professional.php';
            if (function_exists('generatePartnerContractPDFFrench')) {
                $pdf = generatePartnerContractPDFFrench($contractId);
                if ($pdf && file_exists($pdf)) {
                    $fileSize = number_format(filesize($pdf) / 1024, 2);
                    echo "<div class='success'>
                        <span class='status-indicator status-success'></span>
                        PDF Generated Successfully!
                        <br>File: " . basename($pdf) . "
                        <br>Size: $fileSize KB
                        <br><a href='contracts/partners/" . basename($pdf) . "' target='_blank' class='btn btn-primary'>Download PDF</a>
                    </div>";
                } else {
                    echo "<div class='warning'>
                        <span class='status-indicator status-error'></span>
                        PDF Generation Failed
                        <br>Please check error logs
                    </div>";
                }
            }
        } else {
            require_once __DIR__ . '/generate-partner-contract-pdf-professional.php';
            if (function_exists('generatePartnerContractPDF')) {
                $pdf = generatePartnerContractPDF($contractId);
                if ($pdf && file_exists($pdf)) {
                    $fileSize = number_format(filesize($pdf) / 1024, 2);
                    echo "<div class='success'>
                        <span class='status-indicator status-success'></span>
                        PDF Generated Successfully!
                        <br>File: " . basename($pdf) . "
                        <br>Size: $fileSize KB
                        <br><a href='contracts/partners/" . basename($pdf) . "' target='_blank' class='btn btn-primary'>Download PDF</a>
                    </div>";
                } else {
                    echo "<div class='warning'>
                        <span class='status-indicator status-error'></span>
                        PDF Generation Failed
                        <br>Please check error logs
                    </div>";
                }
            }
        }
        echo "</div>";
    }
    
    // Handle preview requests
    if (isset($_GET['preview']) && isset($_GET['lang'])) {
        $contractId = (int)$_GET['preview'];
        $language = $_GET['lang'];
        
        echo "<div class='preview-box'>
            <h3 class='preview-title'>HTML Preview - Contract ID: $contractId ($language)</h3>";
            
        // Generate HTML preview using the same system
        if ($language === 'french') {
            require_once __DIR__ . '/french-contract-pdf.php';
            if (class_exists('FrenchContractPDF')) {
                $generator = new FrenchContractPDF($conn, $contractId);
                $html = $generator->generate();
                echo "<div class='preview-content'>
                    <h4>Generated HTML Structure:</h4>
                    <textarea style='width: 100%; height: 400px; font-family: monospace; font-size: 12px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;' readonly>" . htmlspecialchars(substr($html, 0, 2000)) . "...</textarea>
                    <p><strong>Note:</strong> This shows the actual HTML structure that will be converted to PDF.</p>
                </div>";
            }
        } else {
            require_once __DIR__ . '/english-contract-pdf.php';
            if (class_exists('EnglishContractPDF')) {
                $generator = new EnglishContractPDF($conn, $contractId);
                $html = $generator->generate();
                echo "<div class='preview-content'>
                    <h4>Generated HTML Structure:</h4>
                    <textarea style='width: 100%; height: 400px; font-family: monospace; font-size: 12px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;' readonly>" . htmlspecialchars(substr($html, 0, 2000)) . "...</textarea>
                    <p><strong>Note:</strong> This shows the actual HTML structure that will be converted to PDF.</p>
                </div>";
            }
        }
        echo "</div>";
    }
}

echo "
    <div class='test-controls'>
        <h3>Layout Verification Tests</h3>
        <p><strong>What to check:</strong></p>
        <ul style='text-align: left; max-width: 600px; margin: 0 auto;'>
            <li>✅ Title appears on page 1</li>
            <li>✅ Subtitle follows title</li>
            <li>✅ <strong>1. PARTIES</strong> starts directly after title</li>
            <li>✅ No empty space between title and first section</li>
            <li>✅ No page break before first section</li>
            <li>✅ Content flows naturally on page 1</li>
        </ul>
        
        <p><strong>Expected Page 1 Layout:</strong></p>
        <div style='text-align: center; padding: 20px; background: #e8f4fd; border-radius: 8px; margin: 10px 0;'>
            <div style='font-size: 18px; font-weight: bold; margin-bottom: 10px;'>PAGE 1 SHOULD CONTAIN:</div>
            <div style='text-align: left;'>
                <div style='margin: 2px 0;'><strong>STRATEGIC PARTNERSHIP AGREEMENT</strong> (Title)</div>
                <div style='margin: 2px 0;'><strong>Professional Partnership Description</strong> (Subtitle)</div>
                <div style='margin: 2px 0;'><strong>1. PARTIES</strong> (First Section)</div>
                <div style='margin: 2px 0; color: #dc3545;'><strong>2. SCOPE OF PARTNERSHIP</strong> (Second Section)</div>
                <div style='margin: 2px 0; color: #dc3545;'><strong>3. PRIMARY MISSION</strong> (Third Section)</div>
                <div style='margin: 2px 0; color: #dc3545;'><strong>4. ROLES AND RESPONSIBILITIES</strong> (Fourth Section)</div>
                <div style='margin: 2px 0; color: #dc3545;'><strong>5. FINANCIAL ARRANGEMENTS</strong> (Fifth Section)</div>
                <div style='margin: 2px 0; color: #dc3545;'><strong>6. ADDED VALUE</strong> (Sixth Section)</div>
                <div style='margin: 2px 0; color: #dc3545;'><strong>7. COMMUNICATION AND COORDINATION</strong> (Seventh Section)</div>
                <div style='margin: 2px 0; color: #dc3545;'><strong>ScholarSync Global Co. Ltd</strong> (Continues on same page)</div>
                <div style='margin: 2px 0; color: #28a745;'><strong>NO WHITE SPACE</strong></div>
                <div style='margin: 2px 0; color: #28a745;'><strong>NO PAGE BREAK</strong></div>
                <div style='margin: 2px 0; color: #28a745;'><strong>ULTRA-COMPACT</strong></div>
                <div style='margin: 2px 0; color: #28a745;'><strong>MAXIMUM PAGE UTILIZATION</strong></div>
            </div>
        </div>
        
        <div style='text-align: center; margin-top: 30px;'>
            <a href='?' class='btn btn-primary'>Refresh Contract List</a>
        </div>
    </div>
</body>
</html>";
?>
