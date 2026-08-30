<?php
/**
 * canada-medical-already-applied.php
 * Page shown when user has already applied
 */

// Get user ID from URL
$user_id = $_GET['id'] ?? '';
if (empty($user_id)) {
    header("Location: canada-medical-exams-request.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Already Submitted | ScholarSync Global</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-700: #374151;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #f8fafc;
            color: var(--gray-700);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .message-container {
            max-width: 600px;
            width: 90%;
            background: white;
            border-radius: 15px;
            padding: 3rem 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
            text-align: center;
        }
        
        .icon-container {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
        }
        
        .icon-container i {
            font-size: 3rem;
            color: white;
        }
        
        .title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-700);
            margin-bottom: 1rem;
        }
        
        .message {
            font-size: 1.125rem;
            color: var(--gray-500);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .info-box {
            background: var(--gray-100);
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem 0;
            border-left: 4px solid var(--warning-color);
        }
        
        .info-box h4 {
            color: var(--warning-color);
            margin-bottom: 1rem;
        }
        
        .info-box ul {
            text-align: left;
            color: var(--gray-600);
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.875rem 2rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            color: white;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            color: white;
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            padding: 0.875rem 2rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .message-container {
                padding: 2rem 1.5rem;
            }
            
            .title {
                font-size: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-primary, .btn-outline-primary {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="message-container">
        <div class="icon-container">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        
        <h1 class="title">Application Already Submitted</h1>
        
        <p class="message">
            You have already submitted a Canada Medical Exams request. We process each application individually and will contact you as soon as possible.
        </p>
        
        <div class="info-box">
            <h4><i class="fas fa-info-circle me-2"></i>What happens next?</h4>
            <ul class="mb-0">
                <li>Your application is currently being reviewed by our team</li>
                <li>You will receive an email update within 3-5 business days</li>
                <li>If additional information is needed, we will contact you directly</li>
                <li>You can check your email for the confirmation and reference number</li>
            </ul>
        </div>
        
        <div class="alert alert-info">
            <i class="fas fa-envelope me-2"></i>
            <strong>Check your email:</strong> You should have received a confirmation email with your reference ID. 
            If you don't see it, please check your spam folder.
        </div>
        
        <div class="action-buttons">
            <a href="mailto:infos@scholarsyncglobal.ca" class="btn btn-outline-primary">
                <i class="fas fa-envelope me-2"></i> Contact Support
            </a>
            <a href="canada-medical-exams-request.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i> Back to Form
            </a>
        </div>
    </div>
</body>
</html>
