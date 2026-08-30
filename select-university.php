<?php
session_start();
require_once __DIR__ . '/db.php';
// Only assign new user_id once
if (isset($_GET['id']) && strpos($_GET['id'], 'user-') === 0) {
    $_SESSION['user_id'] = $_GET['id'];

    // Clean URL by redirecting
    $query = $_GET;
    unset($query['id']);
    $cleanUrl = strtok($_SERVER["REQUEST_URI"], '?') . '?' . http_build_query($query);
    header("Location: $cleanUrl");
    exit;
}

// Ensure user_id exists
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'user-' . time() . '-' . rand(1000, 9999);
}
$userId = $_SESSION['user_id'];

// Handle other preserved fields
$regions = $conn->query("SELECT * FROM regions ORDER BY name ASC");
$selectedRegion = $_GET['region_id'] ?? '';
$formUrl = $_GET['form'] ?? 'form-usa.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Select University</title>
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
      padding: 10px;
    }

    .form-container {
      background: #fff;
      max-width: 500px;
      width: 100%;
      margin: 40px auto;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    h2, h3 {
      text-align: center;
      color: #427431;
      margin-bottom: 18px;
      font-size: 1.5rem;
    }

    select, input[type="text"] {
      width: 100%;
      padding: 14px 16px;
      margin-bottom: 16px;
      font-size: 1rem;
      border-radius: 10px;
      border: 1px solid #ccc;
      background: #f9f9f9;
    }

    select:focus, input:focus {
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

    ul#universityList li {
      padding: 14px 16px;
      border-bottom: 1px solid #eee;
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      font-size: 1rem;
    }

    ul#universityList li:hover {
      background: #f0f8ff;
    }

    a.apply-link {
      background: #427431;
      color: white;
      padding: 10px 18px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 500;
      font-size: 0.95rem;
      transition: background 0.3s;
      white-space: nowrap;
    }

    a.apply-link:hover {
      background: #356a2a;
    }

    /* Mobile View */
    @media (max-width: 480px) {
      .form-container {
        padding: 15px;
        margin: 20px 10px;
      }

      h2, h3 {
        font-size: 1.3rem;
      }

      ul#universityList li {
        flex-direction: column;
        align-items: flex-start;
      }

      a.apply-link {
        width: 100%;
        text-align: center;
        padding: 12px 0;
      }
    }
  </style>
</head>

<body>

<div class="form-container">
  <h2>Select a Region</h2>

  <form method="GET" action="select-university.php">
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
    $stmt = $conn->prepare("SELECT * FROM universities WHERE region_id = ?");
    $stmt->bind_param("i", $selectedRegion);
    $stmt->execute();
    $result = $stmt->get_result();
    ?>
    <h3>Select a University</h3>
    <input type="text" id="searchBox" placeholder="Search university..." />
<ul id="universityList">
  <?php while ($uni = $result->fetch_assoc()): ?>
    <?php
      $uniId = (int) $uni['id'];
      $regionId = (int) $uni['region_id']; // Ensure region is available

// Define custom links based on university ID and region ID
if ($selectedRegion == 4 && $uniId == 29) {
    $linkTarget = 'malta-form.php'; // Special case for Malta
} elseif ($uniId === 30) {
    $linkTarget = 'form-polimi.php'; // Special case for Polimi
} elseif ($regionId === 2 && $uniId === 31) {
    $linkTarget = 'form-Canadian-Institute.php'; // Special case for Canadian Institute
} elseif ($regionId === 1 && $uniId === 32) {
    $linkTarget = 'form-Saint-Louis.php'; // ✅ New custom university: Saint-Louis
} elseif ($regionId === 1 && $uniId === 1) {
    $linkTarget = 'form-catholic.php'; // ✅ New custom university: Catholic
} elseif ($uniId === 34) {
    $linkTarget = 'form-Murray.php'; // ✅ New custom university: Murray
} elseif ($uniId === 35) {
    $linkTarget = 'form-kent.php'; // ✅ New custom university: Kent
} elseif ($uniId === 36) {
    $linkTarget = 'form-turkey.php'; // ✅ New custom university: Kent
}elseif ($uniId === 37) {
    $linkTarget = 'form-Fleming.php'; // ✅ New custom university: Kent
}elseif ($uniId === 11) {
    $linkTarget = 'form-niagara.php'; // ✅ New custom university: Kent
}elseif ($uniId === 12) {
    $linkTarget = 'form-west.php'; // ✅ West
} elseif ($uniId === 13) {
    $linkTarget = 'form-trebas.php'; // ✅ Trebas Institute
} elseif ($uniId === 14) {
    $linkTarget = 'form-gallery.php'; // ✅ The Language Gallery
} elseif ($uniId === 15) {
    $linkTarget = 'form-lasalle.php'; // ✅ LaSalle College
} elseif ($uniId === 28) {
    $linkTarget = 'form-windsor.php'; // ✅ University of Windsor
} elseif ($uniId === 41) {
    $linkTarget = 'form-georgia.php'; // ✅ University of Windsor
} elseif ($uniId === 42) {
    $linkTarget = 'dphu.php'; // ✅ University of Windsor
} elseif ($uniId === 44) {
    $linkTarget = 'form-Northeastern-University.php'; // ✅ University of Windsor
} elseif ($uniId === 45) {
    $linkTarget = 'form-West-Florida.php'; // ✅ University of Windsor
} 
elseif ($uniId === 46) {
    $linkTarget = 'form-porto.php'; // ✅ University of Windsor
} 
elseif ($uniId === 19) {
    $linkTarget = 'form-University-Europe.php'; // ✅ University of Windsor
} elseif ($uniId === 47) {
    $linkTarget = 'form-florida.php'; // ✅ University of Windsor
}elseif ($uniId === 48) {
    $linkTarget = 'form-UZBEKISTAN.php'; // ✅ University of Windsor
}
elseif ($uniId === 49) {
    $linkTarget = 'form-upafa.php'; // ✅ University of Windsor
}elseif ($uniId === 50) {
    $linkTarget = 'form-Worcester.php'; // ✅ University of Windsor
}elseif ($uniId === 51) {
    $linkTarget = 'form-rpi.php'; // ✅ University of Windsor
}elseif ($uniId === 52) {
    $linkTarget = 'form-danfold.php'; // ✅ University of Windsor
}elseif ($uniId === 53) {
    $linkTarget = 'form-UBI.php'; // ✅ University of Windsor
}elseif ($uniId === 54) {
    $linkTarget = 'form-manitoba.php'; // ✅ University of Windsor
}elseif ($uniId === 55) {
    $linkTarget = 'form-Pepperdine.php'; // ✅ University of Windsor
}
elseif ($uniId === 1) {
    $linkTarget = 'form-catholic.php'; // ✅ University of Windsor
}elseif ($uniId === 56) {
    $linkTarget = 'form-trent.php'; // ✅ University of Windsor
}elseif ($uniId === 57) {
    $linkTarget = 'form-budapest.php'; // ✅ University of Windsor
}elseif ($uniId === 58) {
    $linkTarget = 'form-ilac.php'; // ✅ University of Windsor
}elseif ($uniId === 59) {
    $linkTarget = 'form-monroe.php'; // ✅ University of Windsor
}elseif ($uniId === 60) {
    $linkTarget = 'form-norquest.php'; // ✅ University of Windsor
}elseif ($uniId === 61) {
    $linkTarget = 'form-indo.php'; // ✅ University of Windsor
}elseif ($uniId === 73) {
    $linkTarget = 'form-teccart.php'; // ✅ University of Windsor
}elseif ($uniId === 78) {
    $linkTarget = 'form-winterschool.php'; // ✅ University of Windsor
}
elseif ($uniId === 79) {
    $linkTarget = 'form-Niagara-College.php'; // ✅ University of Windsor
}
elseif ($uniId === 80) {
    $linkTarget = 'form-University of Saskatchewan (USASK).php'; // ✅ University of Windsor
}
elseif ($uniId === 81) {
    $linkTarget = 'form-st thomas.php'; // ✅ University of Windsor
}
else {
    $linkTarget = $formUrl; // Default form 
}
    ?>
   <li>
  <?= htmlspecialchars($uni['name']) ?>
  <?php $newUserId = 'user-' . time() . '-' . rand(1000, 9999); ?>
  <a class="apply-link"
     href="<?= htmlspecialchars($linkTarget) ?>?id=<?= urlencode($newUserId) ?>&university_id=<?= $uniId ?>&region_id=<?= $selectedRegion ?>">
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
  <?php endif; ?>
</div>

</body>
</html>
