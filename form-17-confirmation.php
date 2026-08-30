<?php
session_start();
require_once __DIR__ . '/db.php';
$userId = $_GET['id'] ?? '';
$application = [];

if ($userId) {
    $stmt = $conn->prepare("SELECT * FROM form_17_applications WHERE user_id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $application = $result->fetch_assoc() ?? [];
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Submitted - ScholarSync Global</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy-blue: #427431;
            --gold: #E21D1E;
            --success: #28a745;
            --white: #FFFFFF;
            --light-gray: #f8f9fa;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--light-gray) 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .confirmation-container {
            background: var(--white);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(1, 47, 107, 0.1);
            text-align: center;
            border-top: 5px solid var(--success);
        }
        
        .success-icon {
            font-size: 80px;
            color: var(--success);
            margin-bottom: 20px;
            animation: bounce 1s;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        h1 {
            color: var(--navy-blue);
            margin-bottom: 15px;
            font-size: 28px;
        }
        
        .message {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .application-details {
            background: var(--light-gray);
            border-radius: 10px;
            padding: 20px;
            margin: 25px 0;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--navy-blue);
        }
        
        .detail-value {
            color: #555;
        }
        
        .application-id {
            background: var(--navy-blue);
            color: var(--white);
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 18px;
            display: inline-block;
            margin: 20px 0;
        }
        
        .buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--navy-blue), #3661B9);
            color: var(--white);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(1, 47, 107, 0.3);
        }
        
        .btn-secondary {
            background: var(--white);
            color: var(--navy-blue);
            border: 2px solid var(--navy-blue);
        }
        
        .btn-secondary:hover {
            background: rgba(1, 47, 107, 0.05);
            transform: translateY(-2px);
        }
        
        .whats-next {
            background: linear-gradient(135deg, rgba(242, 166, 90, 0.1), rgba(1, 47, 107, 0.05));
            padding: 20px;
            border-radius: 10px;
            margin-top: 25px;
            text-align: left;
        }
        
        .whats-next h3 {
            color: var(--navy-blue);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .whats-next ul {
            list-style: none;
            padding-left: 5px;
        }
        
        .whats-next li {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #555;
        }
        
        .whats-next li i {
            color: var(--success);
        }
        
        @media (max-width: 768px) {
            .confirmation-container {
                padding: 25px;
            }
            
            .buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1>Application Submitted Successfully!</h1>
        
        <div class="message">
            Thank you for submitting your visa application. Your application has been received and is now being processed.
        </div>
        
        <div class="application-id">
            <i class="fas fa-id-card"></i> Application ID: <?php echo htmlspecialchars($userId); ?>
        </div>
        
        <?php if (!empty($application)): ?>
        <div class="application-details">
            <h3 style="color: var(--navy-blue); margin-bottom: 15px; text-align: center;">
                <i class="fas fa-file-alt"></i> Application Summary
            </h3>
            <div class="detail-row">
                <span class="detail-label">Name:</span>
                <span class="detail-value"><?php echo htmlspecialchars($application['prefix'] . ' ' . $application['first_name'] . ' ' . $application['last_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value"><?php echo htmlspecialchars($application['email']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Visa Type:</span>
                <span class="detail-value"><?php echo htmlspecialchars($application['visa_type']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Destination:</span>
                <span class="detail-value"><?php echo htmlspecialchars($application['country_to_visit'] ?? 'Not specified'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Submission Date:</span>
                <span class="detail-value"><?php echo date('F j, Y', strtotime($application['date'] ?? date('Y-m-d'))); ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="whats-next">
            <h3><i class="fas fa-forward"></i> What Happens Next?</h3>
            <ul>
                <li><i class="fas fa-check"></i> Your application is now in our processing queue</li>
                <li><i class="fas fa-check"></i> Our team will review your documents within 2-3 business days</li>
                <li><i class="fas fa-check"></i> You will receive an email confirmation shortly</li>
                <li><i class="fas fa-check"></i> A visa specialist will contact you if additional information is needed</li>
            </ul>
        </div>
        
        <div class="buttons">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-tachometer-alt"></i> Go to Dashboard
            </a>
            <a href="form-17.php" class="btn btn-secondary" onclick="localStorage.removeItem('visaFormData');">
                <i class="fas fa-plus-circle"></i> Start New Application
            </a>
            <a href="contact.php" class="btn btn-secondary">
                <i class="fas fa-headset"></i> Contact Support
            </a>
        </div>
    </div>
    
    <script>
        // Clear any saved form data from localStorage
        localStorage.removeItem('visaFormData');
        localStorage.removeItem('formStep');
        
        // Auto-redirect after 30 seconds
        setTimeout(() => {
            window.location.href = 'dashboard.php';
        }, 30000);
    </script>
</body>
</html>