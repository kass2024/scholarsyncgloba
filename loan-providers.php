<?php
require_once __DIR__ . '/db.php';
// ============================================
// INCLUDE HEADER FOR LANGUAGE SWITCHING LOGIC
// ============================================
include 'header.php';

$providers = [];
$sql = "SELECT * FROM loan_providers ORDER BY name ASC";
$result = mysqli_query($conn, $sql);
if ($result && mysqli_num_rows($result) > 0) {
  while ($row = mysqli_fetch_assoc($result)) {
    $providers[] = $row;
  }
}

$formUrl = $_GET['form'] ?? 'master-loan.php';
$userId = $_GET['id'] ?? ('user-' . time() . '-' . rand(1000, 9999));

// ============================================
// TRANSLATIONS FOR LOAN PROVIDERS PAGE
// ============================================

$loan_translations = [
    'en' => [
        'page_title' => 'Select Loan Provider | ScholarSync Global',
        'page_description' => 'Choose a trusted financial partner to continue your education loan application.',
        'main_title' => 'Select a Loan Provider',
        'main_description' => 'Choose a trusted financial partner to continue your education loan application.',
        'search_placeholder' => 'Search loan providers...',
        'apply_button' => 'Apply',
        'no_providers' => 'No loan providers available at the moment.',
        'header_title' => 'Education Loan Providers',
        'header_subtitle' => 'Trusted banking institutions for student loans and financial services',
    ],
    
    'fr' => [
        'page_title' => 'Sélectionner un Prêteur | ScholarSync Global',
        'page_description' => 'Choisissez un partenaire financier de confiance pour continuer votre demande de prêt étudiant.',
        'main_title' => 'Sélectionnez un Prêteur',
        'main_description' => 'Choisissez un partenaire financier de confiance pour continuer votre demande de prêt étudiant.',
        'search_placeholder' => 'Rechercher des prêteurs...',
        'apply_button' => 'Postuler',
        'no_providers' => 'Aucun prêteur disponible pour le moment.',
        'header_title' => 'Prêteurs pour Prêts Éducation',
        'header_subtitle' => 'Institutions bancaires de confiance pour les prêts étudiants et services financiers',
    ]
];

// Function to get loan translation
function lt($key) {
    global $loan_translations, $current_lang;
    return isset($loan_translations[$current_lang][$key]) ? $loan_translations[$current_lang][$key] : $key;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo lt('page_description'); ?>">
<title><?php echo lt('page_title'); ?></title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
  /* Brand colors (ScholarSync Global logo) */
  --primary-navy: #427431;
  --secondary-blue: #3661B9;
  --dark-blue: #2f5a26;
  --accent-gold: #E21D1E;
  --accent-teal: #2DD4BF;
  --pure-white: #FFFFFF;
  
  /* Derived Colors */
  --primary-light: rgba(1, 47, 107, 0.08);
  --accent-light: rgba(242, 166, 90, 0.12);
  --teal-light: rgba(45, 212, 191, 0.1);
  
  /* Neutral Colors */
  --bg: #F8FAFC;
  --bg-light: #FFFFFF;
  --card: #FFFFFF;
  --text: #1E293B;
  --text-light: #64748B;
  --text-muted: #94A3B8;
  --border: #E2E8F0;
  --border-light: #F1F5F9;
  
  /* Shadows */
  --shadow-sm: 0 2px 4px rgba(1, 47, 107, 0.04);
  --shadow-md: 0 4px 12px rgba(1, 47, 107, 0.08);
  --shadow-lg: 0 8px 20px rgba(1, 47, 107, 0.12);
  
  /* Transitions */
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background: linear-gradient(135deg, #FFFFFF 0%, #F8FAFC 100%);
  color: var(--text);
  min-height: 100vh;
  line-height: 1.6;
  overflow-x: hidden;
  -webkit-font-smoothing: antialiased;
}

/* ===== HEADER STYLING ===== */
.page-header {
  background: linear-gradient(135deg, var(--primary-navy) 0%, var(--dark-blue) 100%);
  color: white;
  text-align: center;
  padding: 80px 20px 60px;
  position: relative;
  overflow: hidden;
}

.page-header::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: 
    radial-gradient(circle at 20% 80%, rgba(242, 166, 90, 0.1) 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, rgba(45, 212, 191, 0.1) 0%, transparent 50%);
}

.header-content {
  max-width: 800px;
  margin: 0 auto;
  position: relative;
  z-index: 2;
}

.header-icon {
  width: 80px;
  height: 80px;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  color: var(--accent-gold);
  margin: 0 auto 25px;
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.page-header h1 {
  font-size: 2.8rem;
  font-weight: 800;
  margin-bottom: 15px;
  background: linear-gradient(135deg, #FFFFFF 0%, rgba(255, 255, 255, 0.9) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.page-header .subtitle {
  font-size: 1.2rem;
  color: rgba(255, 255, 255, 0.85);
  max-width: 600px;
  margin: 0 auto;
  line-height: 1.7;
}

/* ===== PAGE SECTION ===== */
.page-section {
  padding: 40px 20px 100px;
  max-width: 1000px;
  margin: -40px auto 0;
  position: relative;
  z-index: 3;
}

/* ===== CARD ===== */
.card {
  background: var(--card);
  border-radius: 24px;
  padding: 50px 45px;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--border-light);
  position: relative;
  overflow: hidden;
}

.card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 6px;
  background: linear-gradient(90deg, var(--accent-gold), var(--accent-teal));
}

/* ===== TITLE ===== */
.card-header {
  text-align: center;
  margin-bottom: 40px;
}

.card-header h2 {
  font-size: 2.5rem;
  font-weight: 800;
  margin-bottom: 15px;
  background: linear-gradient(135deg, var(--primary-navy) 0%, var(--dark-blue) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  position: relative;
  display: inline-block;
}

.card-header h2::after {
  content: '';
  position: absolute;
  bottom: -12px;
  left: 50%;
  transform: translateX(-50%);
  width: 80px;
  height: 4px;
  background: linear-gradient(90deg, var(--accent-gold), var(--accent-teal));
  border-radius: 2px;
}

.card-header p {
  color: var(--text-light);
  font-size: 1.1rem;
  max-width: 600px;
  margin: 25px auto 0;
  line-height: 1.7;
}

/* ===== SEARCH ===== */
.search-box {
  margin-bottom: 35px;
  position: relative;
}

.search-box input {
  width: 100%;
  padding: 18px 25px 18px 55px;
  border-radius: 16px;
  border: 2px solid var(--border);
  font-size: 1rem;
  background: var(--bg);
  color: var(--text);
  transition: var(--transition);
  font-weight: 500;
}

.search-box input:focus {
  outline: none;
  border-color: var(--accent-teal);
  background: var(--pure-white);
  box-shadow: 0 0 0 4px rgba(45, 212, 191, 0.15);
}

.search-box i {
  position: absolute;
  left: 22px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--accent-teal);
  font-size: 1.1rem;
  pointer-events: none;
}

.search-box input::placeholder {
  color: var(--text-muted);
  font-weight: 500;
}

/* ===== PROVIDER LIST ===== */
.provider-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.provider-item {
  background: var(--bg);
  border-radius: 18px;
  padding: 25px 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 25px;
  transition: var(--transition);
  border: 1px solid var(--border-light);
  position: relative;
  overflow: hidden;
}

.provider-item::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background: linear-gradient(to bottom, var(--accent-gold), var(--accent-teal));
  opacity: 0;
  transition: opacity 0.3s ease;
}

.provider-item:hover {
  background: var(--pure-white);
  transform: translateY(-5px);
  box-shadow: var(--shadow-md);
  border-color: var(--accent-teal);
}

.provider-item:hover::before {
  opacity: 1;
}

.provider-item:hover .provider-icon {
  background: linear-gradient(135deg, var(--accent-gold), var(--accent-teal));
  color: var(--pure-white);
  transform: rotate(10deg) scale(1.1);
}

.provider-content {
  display: flex;
  align-items: center;
  gap: 20px;
  flex: 1;
}

.provider-icon {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, var(--primary-light), var(--teal-light));
  border-radius: 15px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: var(--primary-navy);
  transition: var(--transition);
  flex-shrink: 0;
}

.provider-info {
  flex: 1;
}

.provider-name {
  font-weight: 700;
  font-size: 1.2rem;
  color: var(--primary-navy);
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.provider-name i {
  color: var(--accent-teal);
  font-size: 0.9rem;
}

.provider-type {
  color: var(--text-light);
  font-size: 0.95rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 8px;
}

.provider-type i {
  color: var(--accent-gold);
  font-size: 0.9rem;
}

/* ===== APPLY BUTTON ===== */
.apply-link {
  background: linear-gradient(135deg, var(--primary-navy) 0%, var(--secondary-blue) 100%);
  color: var(--pure-white);
  padding: 14px 32px;
  border-radius: 12px;
  font-size: 1rem;
  font-weight: 700;
  text-decoration: none;
  transition: var(--transition);
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 10px;
  position: relative;
  overflow: hidden;
  z-index: 1;
}

.apply-link::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: left 0.7s;
  z-index: -1;
}

.apply-link:hover {
  background: linear-gradient(135deg, var(--dark-blue), var(--primary-navy));
  transform: translateY(-3px);
  box-shadow: 0 10px 25px rgba(1, 47, 107, 0.25);
}

.apply-link:hover::before {
  left: 100%;
}

/* ===== NO RESULTS MESSAGE ===== */
.no-results {
  text-align: center;
  padding: 50px 30px;
  color: var(--text-light);
  display: none;
}

.no-results i {
  font-size: 48px;
  color: var(--accent-teal);
  margin-bottom: 20px;
  opacity: 0.7;
}

.no-results h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--primary-navy);
  margin-bottom: 10px;
}

/* ===== ANIMATIONS ===== */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.fade-in {
  opacity: 0;
  animation: fadeInUp 0.6s ease forwards;
}

/* ===== RESPONSIVE DESIGN ===== */
@media (max-width: 992px) {
  .page-header h1 {
    font-size: 2.4rem;
  }
  
  .card {
    padding: 40px 35px;
  }
  
  .card-header h2 {
    font-size: 2.2rem;
  }
}

@media (max-width: 768px) {
  .page-header {
    padding: 60px 20px 50px;
  }
  
  .page-header h1 {
    font-size: 2rem;
  }
  
  .page-section {
    padding: 30px 20px 80px;
    margin-top: -30px;
  }
  
  .card {
    padding: 35px 25px;
  }
  
  .card-header h2 {
    font-size: 1.8rem;
  }
  
  .provider-item {
    flex-direction: column;
    align-items: stretch;
    gap: 20px;
    padding: 25px;
  }
  
  .provider-content {
    width: 100%;
  }
  
  .apply-link {
    width: 100%;
    justify-content: center;
  }
}

@media (max-width: 576px) {
  .page-header h1 {
    font-size: 1.8rem;
  }
  
  .page-header .subtitle {
    font-size: 1rem;
  }
  
  .card-header h2 {
    font-size: 1.6rem;
  }
  
  .card-header p {
    font-size: 1rem;
  }
  
  .provider-icon {
    width: 50px;
    height: 50px;
    font-size: 20px;
  }
  
  .provider-name {
    font-size: 1.1rem;
  }
  
  .search-box input {
    padding: 16px 20px 16px 50px;
    font-size: 0.95rem;
  }
  
  .apply-link {
    padding: 14px 25px;
    font-size: 0.95rem;
  }
}

@media (max-width: 480px) {
  .page-header {
    padding: 50px 15px 40px;
  }
  
  .card {
    padding: 30px 20px;
  }
  
  .provider-item {
    padding: 20px;
  }
  
  .provider-content {
    flex-direction: column;
    text-align: center;
    gap: 15px;
  }
}
</style>
</head>

<body>

<?php
// Header is already included at the top for language logic
// We'll create a custom header section for this page
?>

<!-- PAGE HEADER -->
<header class="page-header">
  <div class="header-content">
    <div class="header-icon">
      <i class="fas fa-university"></i>
    </div>
    <h1 class="fade-in"><?php echo lt('header_title'); ?></h1>
    <p class="subtitle fade-in"><?php echo lt('header_subtitle'); ?></p>
  </div>
</header>

<!-- MAIN CONTENT -->
<section class="page-section">
  <div class="card fade-in">
    <div class="card-header">
      <h2><?php echo lt('main_title'); ?></h2>
      <p><?php echo lt('main_description'); ?></p>
    </div>

    <!-- SEARCH BOX -->
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input type="text" 
             id="searchBox" 
             placeholder="<?php echo lt('search_placeholder'); ?>"
             autocomplete="off" />
    </div>

    <!-- PROVIDER LIST -->
    <ul class="provider-list" id="providerList">
      <?php if (!empty($providers)): ?>
        <?php foreach ($providers as $provider): ?>
          <?php
          // Determine icon based on provider name
          $icon = 'fa-university'; // default
          $type = 'Banking Institution';
          
          if (stripos($provider['name'], 'CIBC') !== false) {
            $icon = 'fa-landmark';
          } elseif (stripos($provider['name'], 'RBC') !== false) {
            $icon = 'fa-piggy-bank';
          } elseif (stripos($provider['name'], 'Scotiabank') !== false) {
            $icon = 'fa-building-columns';
          } elseif (stripos($provider['name'], 'BMO') !== false) {
            $icon = 'fa-building';
          } elseif (stripos($provider['name'], 'TD') !== false) {
            $icon = 'fa-bank';
          }
          ?>
          <li class="provider-item fade-in">
            <div class="provider-content">
              <div class="provider-icon">
                <i class="fas <?php echo $icon; ?>"></i>
              </div>
              <div class="provider-info">
                <div class="provider-name">
                  <i class="fas fa-check-circle"></i>
                  <?= htmlspecialchars($provider['name']) ?>
                </div>
                <div class="provider-type">
                  <i class="fas fa-tag"></i>
                  <?php echo $current_lang === 'fr' ? 'Institution Bancaire' : 'Banking Institution'; ?>
                </div>
              </div>
            </div>
            <a class="apply-link"
               href="<?= htmlspecialchars($formUrl) ?>?id=<?= urlencode($userId) ?>&provider_id=<?= $provider['id'] ?>">
              <i class="fas fa-paper-plane"></i>
              <?php echo lt('apply_button'); ?>
            </a>
          </li>
        <?php endforeach; ?>
      <?php else: ?>
        <!-- NO PROVIDERS MESSAGE -->
        <div class="no-results fade-in" id="noResults">
          <i class="fas fa-info-circle"></i>
          <h3><?php echo $current_lang === 'fr' ? 'Information' : 'Information'; ?></h3>
          <p><?php echo lt('no_providers'); ?></p>
        </div>
        <script>
          document.getElementById('noResults').style.display = 'block';
        </script>
      <?php endif; ?>
    </ul>
  </div>
</section>

<?php include 'footer.php'; ?>

<script>
// Search functionality
function filterProviders() {
  const input = document.getElementById('searchBox').value.toLowerCase();
  const providerItems = document.querySelectorAll('#providerList .provider-item');
  const noResults = document.getElementById('noResults');
  let visibleCount = 0;
  
  providerItems.forEach(item => {
    const providerName = item.querySelector('.provider-name').textContent.toLowerCase();
    if (providerName.includes(input)) {
      item.style.display = '';
      visibleCount++;
    } else {
      item.style.display = 'none';
    }
  });
  
  // Show/hide no results message
  if (noResults) {
    if (visibleCount === 0 && providerItems.length > 0) {
      noResults.style.display = 'block';
    } else {
      noResults.style.display = 'none';
    }
  }
}

// Initialize search
document.addEventListener('DOMContentLoaded', function() {
  const searchBox = document.getElementById('searchBox');
  if (searchBox) {
    searchBox.addEventListener('keyup', filterProviders);
    searchBox.addEventListener('search', filterProviders);
  }
  
  // Add animation delays to items
  const fadeElements = document.querySelectorAll('.fade-in');
  fadeElements.forEach((el, index) => {
    el.style.animationDelay = `${index * 0.1}s`;
  });
});

// Add smooth scroll to card on load
window.addEventListener('load', function() {
  const card = document.querySelector('.card');
  if (card) {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    
    setTimeout(() => {
      card.style.opacity = '1';
      card.style.transform = 'translateY(0)';
    }, 300);
  }
});
</script>

</body>
</html>