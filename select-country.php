<?php
session_start();
require_once __DIR__ . '/db.php';
// Get or generate user ID
$userId = $_GET['id'] ?? ('user-' . time() . '-' . rand(1000, 9999));
$_SESSION['user_id'] = $userId; // Store in session for later use

// Load regions from database
$regions = $conn->query("SELECT * FROM regions ORDER BY name ASC");
$selectedRegion = $_GET['region_id'] ?? '';

// Set form URL
$formUrl = $_GET['form'] ?? 'visa.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Select Country</title>
  <style>
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: 'Segoe UI', Roboto, sans-serif;
  background: #eef2f7;
  padding: 20px;
}

.form-container {
  background: #fff;
  max-width: 500px;
  width: 100%;
  margin: 40px auto;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

h2, h3 {
  text-align: center;
  color: #427431;
  margin-bottom: 20px;
  font-size: 1.5rem;
}

select, input[type=text] {
  width: 100%;
  padding: 14px 16px;
  margin-bottom: 20px;
  font-size: 1rem;
  border-radius: 10px;
  border: 1px solid #ccc;
  background: #f9f9f9;
}

select:focus, input[type=text]:focus {
  border-color: #427431;
  background: #fff;
  outline: none;
  box-shadow: 0 0 5px rgba(66, 116, 49, 0.25);
}

.country-list-container {
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid #ddd;
  border-radius: 8px;
  background: #fdfdfd;
  margin-top: 10px;
}

ul#countryList {
  list-style: none;
  margin: 0;
  padding: 0;
}

ul#countryList li {
  padding: 14px 16px;
  border-bottom: 1px solid #eee;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  font-size: 1rem;
}

ul#countryList li:hover {
  background: #f0f8ff;
}

a.apply-link {
  background: #427431;
  color: #fff;
  padding: 10px 18px;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 500;
  font-size: 0.95rem;
  white-space: nowrap;
}

a.apply-link:hover {
  background: #356a2a;
}

/* Mobile Optimizations */
@media (max-width: 480px) {
  .form-container {
    padding: 16px;
    margin: 20px 10px;
  }

  h2, h3 {
    font-size: 1.3rem;
  }

  select, input[type=text] {
    font-size: 1rem;
    padding: 12px;
  }

  ul#countryList li {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  a.apply-link {
    width: 100%;
    text-align: center;
    font-size: 1rem;
  }
}

</style>
</head>
<body>

<div class="form-container">
  <h2>Select a Region</h2>
  <form method="GET" action="select-country.php">
    <input type="hidden" name="form" value="<?= htmlspecialchars($formUrl) ?>">
    <input type="hidden" name="id" value="<?= htmlspecialchars($userId) ?>">
    <select name="region_id" onchange="this.form.submit()">
      <option value="">-- Choose Region --</option>
      <?php while ($region = $regions->fetch_assoc()): ?>
        <option value="<?= $region['id'] ?>" <?= $selectedRegion == $region['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($region['name']) ?>
        </option>
      <?php endwhile; ?>
    </select>
  </form>

  <?php if (!empty($selectedRegion)): ?>
    <?php
    $stmt = $conn->prepare("SELECT * FROM countries WHERE region_id = ?");
    $stmt->bind_param("i", $selectedRegion);
    $stmt->execute();
    $result = $stmt->get_result();
    ?>
    <h3>Select a Country</h3>
    <input type="text" id="searchBox" placeholder="Search country...">

    <div class="country-list-container">
      <ul id="countryList">
        <?php while ($country = $result->fetch_assoc()): ?>
          <li>
            <?= htmlspecialchars($country['name']) ?>
            <a class="apply-link"
               href="<?= htmlspecialchars($formUrl) ?>?id=<?= urlencode($userId) ?>&region_id=<?= $selectedRegion ?>&country_id=<?= $country['id'] ?>">
              Apply Now
            </a>
          </li>
        <?php endwhile; ?>
      </ul>
    </div>

    <script>
      document.getElementById('searchBox').addEventListener('keyup', function () {
        const input = this.value.toLowerCase();
        document.querySelectorAll('#countryList li').forEach(li => {
          li.style.display = li.innerText.toLowerCase().includes(input) ? '' : 'none';
        });
      });
    </script>
  <?php endif; ?>
</div>

</body>
</html>
