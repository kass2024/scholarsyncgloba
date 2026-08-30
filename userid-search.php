<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Live Student Search</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; }
    .container { max-width: 800px; }
    .table td, .table th { vertical-align: middle; }
  </style>
</head>
<body>
<div class="container py-5">
  <h3 class="text-center mb-4">🎓 Live Student Search</h3>

  <div class="mb-3">
    <input type="text" id="searchInput" class="form-control" placeholder="Type to search by name, email, or user ID...">
  </div>

  <div id="results"></div>
</div>

<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
  let q = this.value.trim();
  if (q.length > 1) {
    fetch("search_ajax.php?q=" + encodeURIComponent(q))
      .then(res => res.text())
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
