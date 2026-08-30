<?php
/**
 * validate_payment_proof.php
 * AI-powered payment proof validation service
 */

session_start();
require_once __DIR__ . '/db.php';

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Validate session
if (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Validate input
if (empty($_POST['file_path'])) {
    echo json_encode(['success' => false, 'message' => 'File path is required']);
    exit;
}

$file_path = trim($_POST['file_path']);

// Security: Validate file path is within uploads directory
$base_dir = __DIR__ . '/uploads/';
$full_path = realpath($base_dir . basename($file_path));

if (!$full_path || !file_exists($full_path) || strpos($full_path, realpath($base_dir)) !== 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid file path']);
    exit;
}

try {
    // Perform AI validation
    $validation_result = validatePaymentDocument($full_path);
    
    echo json_encode([
        'success' => true,
        'contains_payment_info' => $validation_result['contains_payment_info'],
        'confidence' => $validation_result['confidence'],
        'details' => $validation_result['details'],
        'message' => $validation_result['message']
    ]);
    
} catch (Exception $e) {
    error_log("Payment validation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed: ' . $e->getMessage()
    ]);
}

function validatePaymentDocument($file_path) {
    // Check file extension
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'])) {
        throw new Exception('Unsupported file format');
    }
    
    // Extract text from document
    $text = extractTextFromDocument($file_path);
    
    if (empty($text)) {
        return [
            'contains_payment_info' => false,
            'confidence' => 0,
            'details' => ['No readable text found in document'],
            'message' => 'Unable to extract text from document'
        ];
    }
    
    // Payment-related keywords and patterns
    $payment_keywords = [
        'payment', 'paid', 'receipt', 'invoice', 'transaction', 'amount', 'total',
        'cash', 'credit', 'debit', 'bank', 'transfer', 'wire', 'deposit', 'fee',
        'cost', 'price', 'charge', 'billing', 'statement', 'proof of payment',
        'confirmation', 'authorized', 'processed', 'completed', 'successful'
    ];
    
    $currency_patterns = [
        '/\$\s*\d+(?:,\d{3})*(?:\.\d{2})?/',  // $1,234.56
        '/\d+(?:,\d{3})*(?:\.\d{2})?\s*\$/',      // 1,234.56$
        '/USD\s*\d+(?:,\d{3})*(?:\.\d{2})?/',    // USD 1,234.56
        '/\d+(?:,\d{3})*(?:\.\d{2})?\s*USD/',    // 1,234.56 USD
        '/CAD\s*\d+(?:,\d{3})*(?:\.\d{2})?/',    // CAD 1,234.56
        '/\d+(?:,\d{3})*(?:\.\d{2})?\s*CAD/',    // 1,234.56 CAD
        '/\d+(?:,\d{3})*(?:\.\d{2})?\s*(?:dollars?|bucks)/i' // 100 dollars
    ];
    
    $transaction_patterns = [
        '/transaction\s*(?:ID|id|number|#)?\s*[:\s]*([A-Z0-9]{8,})/i',
        '/receipt\s*(?:number|#|no)\s*[:\s]*([A-Z0-9]{6,})/i',
        '/invoice\s*(?:number|#|no)\s*[:\s]*([A-Z0-9]{6,})/i',
        '/order\s*(?:ID|id|number|#)\s*[:\s]*([A-Z0-9]{6,})/i',
        '/reference\s*(?:number|#|no)\s*[:\s]*([A-Z0-9]{6,})/i'
    ];
    
    $bank_patterns = [
        '/bank\s*(?:name|account|transfer|wire)/i',
        '/account\s*(?:number|no|#)\s*[:\s]*([A-Z0-9]{6,})/i',
        '/routing\s*(?:number|no|#)\s*[:\s]*([A-Z0-9]{6,})/i',
        '/SWIFT\s*(?:code|bic)\s*[:\s]*([A-Z]{6,})/i'
    ];
    
    // Analyze text for payment indicators
    $text_lower = strtolower($text);
    $found_keywords = [];
    $found_amounts = [];
    $found_transactions = [];
    $found_banking = [];
    
    // Check for payment keywords
    foreach ($payment_keywords as $keyword) {
        if (strpos($text_lower, $keyword) !== false) {
            $found_keywords[] = $keyword;
        }
    }
    
    // Check for currency amounts
    foreach ($currency_patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            $found_amounts = array_merge($found_amounts, $matches[0]);
        }
    }
    
    // Check for transaction IDs
    foreach ($transaction_patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            $found_transactions = array_merge($found_transactions, $matches[1]);
        }
    }
    
    // Check for banking information
    foreach ($bank_patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            $found_banking = array_merge($found_banking, $matches[0]);
        }
    }
    
    // Calculate confidence score
    $confidence = 0;
    $details = [];
    
    // Keywords contribute to confidence
    if (!empty($found_keywords)) {
        $confidence += min(40, count($found_keywords) * 5);
        $details[] = 'Found payment-related terms: ' . implode(', ', array_unique($found_keywords));
    }
    
    // Amounts contribute significantly
    if (!empty($found_amounts)) {
        $confidence += min(35, count($found_amounts) * 10);
        $details[] = 'Found monetary amounts: ' . implode(', ', array_unique($found_amounts));
    }
    
    // Transaction IDs contribute
    if (!empty($found_transactions)) {
        $confidence += min(15, count($found_transactions) * 5);
        $details[] = 'Found transaction/confirmation numbers';
    }
    
    // Banking information contributes
    if (!empty($found_banking)) {
        $confidence += min(10, count($found_banking) * 3);
        $details[] = 'Found banking information';
    }
    
    // Determine if document contains payment info
    $contains_payment_info = $confidence >= 30; // Minimum threshold
    
    // Special cases for common payment document types
    if (preg_match('/receipt|invoice|billing|statement/i', $text_lower)) {
        $confidence = min(100, $confidence + 20);
        $details[] = 'Document appears to be a payment document';
    }
    
    // Check for common payment processor names
    $payment_processors = ['paypal', 'stripe', 'square', 'venmo', 'zelle', 'wise', 'transferwise', 'western union', 'moneygram'];
    foreach ($payment_processors as $processor) {
        if (strpos($text_lower, $processor) !== false) {
            $confidence = min(100, $confidence + 15);
            $details[] = 'Found payment processor: ' . ucfirst($processor);
            break;
        }
    }
    
    return [
        'contains_payment_info' => $contains_payment_info,
        'confidence' => min(100, $confidence),
        'details' => $details,
        'message' => $contains_payment_info ? 
            'Payment information detected with ' . round(min(100, $confidence)) . '% confidence' : 
            'Limited or no payment information detected'
    ];
}

function extractTextFromDocument($file_path) {
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    switch ($extension) {
        case 'pdf':
            return extractTextFromPDF($file_path);
        case 'jpg':
        case 'jpeg':
        case 'png':
            return extractTextFromImage($file_path);
        default:
            throw new Exception('Unsupported file format for text extraction');
    }
}

function extractTextFromPDF($file_path) {
    // Try to use pdftotext if available
    $output = [];
    $return_code = 0;
    
    // Try pdftotext command
    exec('pdftotext "' . escapeshellarg($file_path) . '" -', $output, $return_code);
    
    if ($return_code === 0 && !empty($output)) {
        return implode("\n", $output);
    }
    
    // Fallback: try to read PDF as text (limited but better than nothing)
    $content = file_get_contents($file_path);
    if ($content) {
        // Extract readable strings from PDF
        $text = '';
        if (preg_match_all('/[\x20-\x7E]{4,}/', $content, $matches)) {
            $text = implode("\n", $matches[0]);
        }
        return $text;
    }
    
    return '';
}

function extractTextFromImage($file_path) {
    // Try to use Tesseract OCR if available
    $output = [];
    $return_code = 0;
    
    // Try tesseract command
    exec('tesseract "' . escapeshellarg($file_path) . '" stdout 2>/dev/null', $output, $return_code);
    
    if ($return_code === 0 && !empty($output)) {
        return implode("\n", $output);
    }
    
    // Fallback: use EXIF data or basic image info (very limited)
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($file_path);
        if ($exif && isset($exif['ImageDescription'])) {
            return $exif['ImageDescription'];
        }
    }
    
    return '';
}
?>
