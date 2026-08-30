<?php
// Optional: Allow admin IPs to bypass maintenance
$allowed_ips = [
    '127.0.0.1',        // localhost
    '::1',              // IPv6 localhost
    // 'YOUR.IP.ADDRESS', // add your IP if needed
];

if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    http_response_code(503); // Service Unavailable
    header("Retry-After: 3600"); // 1 hour
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ScholarSync Global | Under Maintenance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO -->
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="ScholarSync Global is currently undergoing scheduled maintenance. We will be back shortly.">

    <!-- Styling -->
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            text-align: center;
        }

        .maintenance-box {
            background: rgba(255, 255, 255, 0.08);
            padding: 40px;
            border-radius: 12px;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        h1 {
            font-size: 32px;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }

        p {
            font-size: 16px;
            line-height: 1.6;
            opacity: 0.9;
        }

        .logo {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #f1c40f;
        }

        .footer {
            margin-top: 25px;
            font-size: 13px;
            opacity: 0.7;
        }

        .spinner {
            margin: 25px auto;
            width: 45px;
            height: 45px;
            border: 4px solid rgba(255,255,255,0.2);
            border-top: 4px solid #f1c40f;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<div class="maintenance-box">
    <div class="logo">ScholarSync Global</div>

    <h1>We’ll Be Back Soon</h1>

    <p>
        Our website is currently undergoing scheduled maintenance to improve
        your experience. We appreciate your patience.
    </p>

    <div class="spinner"></div>

    <p>
        Please check back shortly.<br>
        Thank you for choosing ScholarSync Global.
    </p>

    <div class="footer">
        © <?php echo date('Y'); ?> ScholarSync Global. All rights reserved.
    </div>
</div>

</body>
</html>
