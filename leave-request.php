<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
  header('Location: login.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Request Leave - ScholarSync Global</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <style>
    :root {
      --primary: #3f51b5;
      --accent: #00a859;
      --danger: #e74c3c;
      --gray: #f3f3f3;
      --white: #ffffff;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', sans-serif;
      background: var(--gray);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }

    .form-container {
      background: var(--white);
      padding: 2.5rem;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
      max-width: 550px;
      width: 100%;
      position: relative;
    }

    .form-container h2 {
      color: var(--primary);
      text-align: center;
      margin-bottom: 1.5rem;
    }

    .form-group {
      margin-bottom: 1.2rem;
    }

    label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 600;
    }

    input, textarea {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 1rem;
      transition: border-color 0.3s;
    }

    input:focus, textarea:focus {
      border-color: var(--primary);
      outline: none;
    }

    button {
      background-color: var(--primary);
      color: var(--white);
      padding: 0.75rem;
      width: 100%;
      border: none;
      font-size: 1rem;
      font-weight: bold;
      border-radius: 6px;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    button:hover {
      background-color: #2c3e90;
    }

    .success {
      background: #d4edda;
      color: #155724;
      padding: 0.75rem;
      margin-bottom: 1rem;
      border-radius: 6px;
      border: 1px solid #c3e6cb;
    }

    .back-link {
      display: inline-block;
      margin-top: 1rem;
      text-align: center;
      color: var(--accent);
      text-decoration: none;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    /* Overlay Styles */
    .overlay {
      position: absolute;
      top: 0; left: 0;
      width: 100%;
      height: 100%;
      background: rgba(255,255,255,0.9);
      z-index: 10;
      display: none;
      align-items: center;
      justify-content: center;
      flex-direction: column;
    }

    .loader {
      border: 5px solid #f3f3f3;
      border-top: 5px solid var(--primary);
      border-radius: 50%;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
      margin-bottom: 1rem;
    }

    .loading-text {
      font-size: 1rem;
      color: #333;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

  </style>
</head>
<body>

<div class="form-container">
  <h2>Leave Request Form</h2>

  <?php if (isset($_GET['success'])): ?>
    <div class="success">Your leave request has been submitted!</div>
  <?php endif; ?>

  <form id="leaveForm" action="submit-leave.php" method="POST">
    <div class="form-group">
      <label for="leave_range">Leave Date (start to end):</label>
      <input type="text" id="leave_range" name="leave_range" required placeholder="Select date range">
    </div>

    <div class="form-group">
      <label for="reason">Reason for Leave:</label>
      <textarea name="reason" id="reason" rows="4" required placeholder="Example: Feeling unwell, family emergency..."></textarea>
    </div>

    <button type="submit">Submit Request</button>
  </form>

  <!-- Smart Overlay -->
  <div class="overlay" id="loadingOverlay">
    <div class="loader"></div>
    <div class="loading-text">Submitting your request & sending email...</div>
  </div>

  <a href="admin-dashboard.php" class="back-link">← Back to Dashboard</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  flatpickr("#leave_range", {
    mode: "range",
    dateFormat: "Y-m-d"
  });

  document.getElementById('leaveForm').addEventListener('submit', function () {
    document.getElementById('loadingOverlay').style.display = 'flex';
  });
</script>

</body>
</html>
