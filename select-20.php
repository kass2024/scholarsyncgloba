<?php
require_once __DIR__ . '/db.php';
// Force USA (id = 1)
$selectedRegion = 1;

// Preserve form and user_id
$formUrl = $_GET['form'] ?? 'form-20.php';
$userId = $_GET['id'] ?? ('user-' . time() . '-' . rand(1000, 9999));

// Fetch universities in USA (region_id = 1)
$stmt = $conn->prepare("SELECT * FROM universities WHERE region_id = ?");
$stmt->bind_param("i", $selectedRegion);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Select University (USA Only)</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: 'Roboto', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f0f4f8;
      color: #333;
      padding: 40px 20px;
    }
    h2, h3 {
      text-align: center;
      color: #427431;
      margin-bottom: 20px;
    }
    .form-container {
      background: #fff;
      max-width: 700px;
      margin: 0 auto;
      padding: 25px 30px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    input[type="text"] {
      width: 100%;
      padding: 12px;
      margin: 15px 0;
      border: 1px solid #ccc;
      border-radius: 6px;
      background: #f9f9f9;
      font-size: 1rem;
    }
    input:focus {
      border-color: #427431;
      outline: none;
      background: #fff;
      box-shadow: 0 0 5px rgba(66, 116, 49, 0.25);
    }
    ul {
      list-style: none;
      padding: 0;
      margin-top: 10px;
    }
    li {
      padding: 10px;
      border-bottom: 1px solid #eee;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
    }
    a.apply-link {
      background: #427431;
      color: white;
      padding: 8px 14px;
      border-radius: 6px;
      text-decoration: none;
      font-size: 0.95rem;
      transition: background 0.3s;
      margin-top: 8px;
    }
    a.apply-link:hover {
      background: #356a2a;
    }

    /* Responsive adjustments */
    @media (max-width: 600px) {
      body {
        padding: 20px 10px;
      }
      .form-container {
        padding: 20px;
      }
      h2, h3 {
        font-size: 1.2rem;
      }
      input[type="text"] {
        font-size: 0.95rem;
        padding: 10px;
      }
      li {
        flex-direction: column;
        align-items: flex-start;
      }
      a.apply-link {
        width: 100%;
        text-align: center;
      }
    }
  </style>
</head>

<body>

<div class="form-container">
  <h2>Select University to apply for I-20 </h2>

  <input type="text" id="searchBox" placeholder="Search university..." />

  <ul id="universityList">
    <?php while ($uni = $result->fetch_assoc()): ?>
      <li>
        <?= htmlspecialchars($uni['name']) ?>
        <a class="apply-link"
           href="<?= htmlspecialchars($formUrl) ?>?id=<?= urlencode($userId) ?>&university_id=<?= $uni['id'] ?>&region_id=1">
           Apply Now
        </a>
      </li>
    <?php endwhile; ?>
  </ul>

  <script>
    function filterUniversities() {
      const input = document.getElementById('searchBox').value.toLowerCase();
      document.querySelectorAll('#universityList li').forEach(li => {
        li.style.display = li.innerText.toLowerCase().includes(input) ? '' : 'none';
      });
    }
    document.getElementById('searchBox').addEventListener('keyup', filterUniversities);
  </script>
</div>

</body>
</html>
