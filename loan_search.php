<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Live Loan Applicant Search</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; }
    .container { max-width: 850px; }
  </style>
</head>
<body>
<div class="container py-5">
  <h3 class="text-center mb-4">🔍 Live Loan Applicant Search</h3>

  <input type="text" id="searchInput" class="form-control mb-3" placeholder="Type name, email, or user ID...">

  <div id="results"></div>
</div>

<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
  const query = this.value.trim();
  if (query.length > 1) {
    fetch("loan_search_ajax.php?q=" + encodeURIComponent(query))
      .then(response => response.text())
      .then(data => {
        document.getElementById("results").innerHTML = data;
      });
  } else {
    document.getElementById("results").innerHTML = '';
  }
});
</script>
</body>
</html>
