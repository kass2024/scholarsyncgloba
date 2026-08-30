<?php
// ============================================
// LANGUAGE SWITCHING SYSTEM - HEADER ONLY
// ============================================

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define available languages
$available_languages = ['en' => 'English', 'fr' => 'Français'];

// Get current language from session or default to English
if (!isset($_SESSION['current_language'])) {
    $_SESSION['current_language'] = 'en';
}

// Handle language switching
if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $available_languages)) {
    $_SESSION['current_language'] = $_GET['lang'];
    // Redirect to same page without lang parameter to avoid duplicates
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: $url");
    exit;
}

$current_lang = $_SESSION['current_language'];

// ============================================
// TRANSLATION ARRAYS FOR HEADER ONLY
// ============================================

$header_translations = [
    'en' => [
        // Navigation
        'nav_home' => 'Home',
        'nav_about' => 'About',
        'nav_programs' => 'Programs',
        'nav_services' => 'Services',
        'nav_universities' => 'Universities',
        'nav_partners' => 'Partners',
        'nav_testimonials' => 'Testimonials',
        'nav_contact' => 'Contact',
        'nav_blog' => 'Blog',
        'nav_payment' => 'Payment',
        'nav_pay_service' => 'Payments Hub',
        'nav_student' => 'Student',
        'nav_student_login' => 'Student Login',
        'nav_request_refund' => 'Request Refund',
        'nav_other_payment' => 'Smart Checkout',
        'nav_mtn_momo' => 'MTN Mobile Money',
        'nav_stripe' => 'Stripe Card Payment',
        'get_started' => 'Get Started',
        'admin_login' => 'Admin Login',
        
        // Language switcher
        'current_language' => 'English',
        'switch_to_french' => 'Switch to French',
        'switch_to_english' => 'Switch to English',
    ],
    
    'fr' => [
        // Navigation
        'nav_home' => 'Accueil',
        'nav_about' => 'À propos',
        'nav_programs' => 'Programmes',
        'nav_services' => 'Services',
        'nav_universities' => 'Universités',
        'nav_partners' => 'Partenaires',
        'nav_testimonials' => 'Témoignages',
        'nav_contact' => 'Contact',
        'nav_blog' => 'Blog',
        'nav_payment' => 'Paiement',
        'nav_pay_service' => 'Centre de Paiement',
        'nav_student' => 'Étudiant',
        'nav_student_login' => 'Connexion Étudiant',
        'nav_request_refund' => 'Demande de remboursement',
        'nav_other_payment' => 'Paiement Intelligent',
        'nav_mtn_momo' => 'Mobile Money MTN',
        'nav_stripe' => 'Paiement Carte Stripe',
        'get_started' => 'Commencer',
        'admin_login' => 'Connexion Admin',
        
        // Language switcher
        'current_language' => 'Français',
        'switch_to_french' => 'Passer au français',
        'switch_to_english' => 'Passer à l\'anglais',
    ]
];

// Function to translate header text - only define if not already defined
if (!function_exists('ht')) {
    function ht($key) {
        global $header_translations, $current_lang;
        return isset($header_translations[$current_lang][$key]) ? $header_translations[$current_lang][$key] : $key;
    }
}

// Get page title if not set
$pageTitle = $pageTitle ?? 'ScholarSync Global';
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<?php if (!empty($pageDescription)): ?>
<meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/site-responsive.css?v=4">
<meta name="theme-color" content="#173c6d">
<meta name="format-detection" content="telephone=yes">
<style>
/* ============================================
   HEADER STYLES ONLY
============================================ */
:root {
  --primary: #173c6d;
  --primary-dark: #102d55;
  --primary-light: #1ba7a0;
  --accent: #f4b52e;
  --accent-dark: #d99712;
  --accent-light: #ffd05a;
  --bg: #f8fafc;
  --card: #ffffff;
  --text: #1e293b;
  --text-light: #64748b;
  --border: #e2e8f0;
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

* { 
  box-sizing: border-box; 
  margin: 0; 
  padding: 0; 
}

body { 
  font-family: 'Inter', sans-serif; 
  background: var(--bg); 
  color: var(--text); 
  line-height: 1.6; 
}

/* ===== HEADER ===== */
header { 
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
  padding: 16px 32px; 
  display: flex; 
  align-items: center; 
  justify-content: space-between; 
  gap: 16px;
  box-shadow: var(--shadow-lg); 
  position: sticky; 
  top: 0; 
  z-index: 1000; 
  border-bottom: 3px solid var(--accent);
}

.header-left { 
  display: flex; 
  align-items: center; 
  gap: 14px; 
  flex: 0 0 auto;
}

.header-logo-link {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  line-height: 0;
}

header img { 
  height: 48px; 
  width: auto; 
  transition: var(--transition);
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

header img:hover {
  transform: scale(1.05);
}

.header-brand { 
  color: #fff; 
  font-weight: 700; 
  font-size: 1.05rem; 
  line-height: 1.25;
  white-space: nowrap;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
  flex: 0 1 auto;
  min-width: 0;
}

/* Toolbar: language + CTA + mobile menu (grouped on small screens) */
.header-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

/* Navigation */
nav { 
  display: flex; 
  gap: 24px; 
  align-items: center;
  margin: 0 16px;
  flex: 1 1 auto;
  min-width: 0;
  justify-content: center;
}

nav a { 
  color: #fff; 
  text-decoration: none; 
  font-size: 0.95rem; 
  font-weight: 500; 
  opacity: 0.9; 
  transition: var(--transition); 
  white-space: nowrap;
  padding: 6px 0;
  position: relative;
}

nav a:hover { 
  opacity: 1; 
  transform: translateY(-2px);
}

nav a::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 0;
  height: 2px;
  background: var(--accent);
  transition: width 0.3s ease;
}

nav a:hover::after {
  width: 100%;
}

.get-started { 
  background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
  color: #fff; 
  padding: 12px 24px; 
  border-radius: 8px; 
  text-decoration: none; 
  font-weight: 600; 
  transition: var(--transition);
  white-space: nowrap;
  box-shadow: 0 4px 12px rgba(226, 29, 30, 0.35);
}

.get-started:hover { 
  background: linear-gradient(135deg, var(--accent-light) 0%, var(--accent) 100%);
  transform: translateY(-2px) scale(1.05);
  box-shadow: 0 8px 20px rgba(226, 29, 30, 0.45);
}

.mobile-menu-toggle { 
  display: none; 
  background: rgba(255, 255, 255, 0.1);
  color: #fff; 
  font-size: 1.35rem; 
  cursor: pointer; 
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.28);
  transition: var(--transition);
}

.mobile-menu-toggle:hover {
  background: rgba(255, 255, 255, 0.2);
}

/* Language Switcher Styles */
.language-switcher {
  position: relative;
  margin-left: 0;
}

.portal-switcher {
  position: relative;
}

.portal-switcher-toggle {
  border: none;
}

.portal-switcher-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 8px;
  background: white;
  border-radius: 12px;
  box-shadow: var(--shadow-lg);
  min-width: 210px;
  overflow: hidden;
  display: none;
  z-index: 1001;
  border: 1px solid var(--border);
}

.portal-switcher-dropdown.active {
  display: block;
  animation: fadeIn 0.2s ease;
}

.portal-option {
  padding: 12px 14px;
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: var(--text);
  font-weight: 600;
  transition: var(--transition);
  border-bottom: 1px solid var(--border);
}

.portal-option:last-child {
  border-bottom: none;
}

.portal-option:hover {
  background: var(--bg);
  color: var(--primary);
}

.portal-option i {
  width: 20px;
  text-align: center;
  color: var(--primary);
}

.portal-option:hover i {
  color: var(--accent);
}

.language-switcher-toggle {
  background: rgba(255, 255, 255, 0.15);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.3);
  padding: 10px 16px;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
  font-size: 0.9rem;
  transition: var(--transition);
  backdrop-filter: blur(10px);
  white-space: nowrap;
}

.language-switcher-toggle:hover {
  background: rgba(255, 255, 255, 0.25);
  border-color: var(--accent);
  transform: translateY(-2px);
}

.language-switcher-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 8px;
  background: white;
  border-radius: 8px;
  box-shadow: var(--shadow-lg);
  min-width: 160px;
  overflow: hidden;
  display: none;
  z-index: 1001;
  border: 1px solid var(--border);
}

.language-switcher-dropdown.active {
  display: block;
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

.language-option {
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: var(--text);
  font-weight: 500;
  transition: var(--transition);
  border-bottom: 1px solid var(--border);
}

.language-option:last-child {
  border-bottom: none;
}

.language-option:hover {
  background: var(--bg);
  color: var(--primary);
}

.language-option.active {
  background: linear-gradient(135deg, rgba(30, 58, 95, 0.1) 0%, rgba(255, 140, 66, 0.1) 100%);
  color: var(--primary);
  font-weight: 600;
}

.language-flag {
  font-size: 1.2rem;
}

.language-name {
  flex: 1;
  font-size: 0.9rem;
}

/* Header actions container */
.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

/* Dropdown Menu Styles */
.dropdown {
  position: relative;
  display: inline-block;
}

.dropbtn {
  background-color: transparent;
  color: white;
  padding: 12px 16px;
  font-size: 0.95rem;
  border: none;
  cursor: pointer;
  border-radius: 8px;
  transition: var(--transition);
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
  opacity: 0.9;
}

.dropbtn:hover {
  background-color: rgba(255, 255, 255, 0.15);
  transform: translateY(-2px);
  opacity: 1;
}

.dropbtn i {
  transition: transform 0.3s ease;
}

.dropdown:hover .dropbtn i.fa-chevron-down {
  transform: rotate(180deg);
}

.dropdown-content {
  display: none;
  position: absolute;
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  min-width: 220px;
  box-shadow: var(--shadow-xl);
  z-index: 10050;
  border-radius: 12px;
  top: 100%;
  left: 0;
  margin-top: 0;
  padding-top: 6px;
  border: 1px solid rgba(229, 231, 235, 0.8);
  backdrop-filter: blur(10px);
  opacity: 0;
  transform: translateY(-6px);
  transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
  visibility: hidden;
  pointer-events: auto;
}

/* Invisible bridge so pointer never leaves .dropdown between button and panel */
.dropdown::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  top: 100%;
  height: 10px;
  z-index: 10049;
  display: none;
}
.dropdown:hover::after,
.dropdown.is-open::after {
  display: block;
}

/* Open via .is-open (click) — avoids hover gap that hid the menu before clicks land */
.dropdown.is-open .dropdown-content {
  display: block;
  opacity: 1;
  transform: translateY(0);
  visibility: visible;
}

.dropdown:hover .dropdown-content {
  display: block;
  opacity: 1;
  transform: translateY(0);
  visibility: visible;
}

.dropdown-content a {
  color: #1e293b;
  padding: 14px 20px;
  text-decoration: none;
  display: block;
  transition: all 0.3s ease;
  border-bottom: 1px solid rgba(229, 231, 235, 0.3);
  font-weight: 500;
  position: relative;
  overflow: hidden;
}

.dropdown-content a:first-child {
  border-radius: 12px 12px 0 0;
}

.dropdown-content a:last-child {
  border-radius: 0 0 12px 12px;
  border-bottom: none;
}

.dropdown-content a:hover {
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
  color: white;
  transform: translateX(8px);
  box-shadow: 0 6px 20px rgba(30, 58, 95, 0.35);
  padding-left: 28px;
}

.dropdown-content a i {
  margin-right: 10px;
  width: 20px;
  text-align: center;
}

/* Enhanced styles for full responsiveness */
@media (max-width: 1100px) {
  nav {
    gap: 16px;
  }
  
  nav a, .dropbtn {
    font-size: 0.9rem;
  }
}

@media (max-width: 992px) {
  nav {
    display: none;
  }

  header {
    flex-wrap: wrap;
    align-items: flex-start;
    row-gap: 10px;
    column-gap: 12px;
    padding: 12px 16px;
    border-bottom-width: 2px;
  }

  .header-left {
    order: 1;
    flex: 0 0 auto;
    max-width: calc(100% - 140px);
  }

  .header-brand {
    order: 3;
    flex: 1 1 100%;
    min-width: 0;
    white-space: normal;
    font-size: 0.8125rem;
    font-weight: 600;
    line-height: 1.35;
    letter-spacing: 0.01em;
    padding: 2px 0 0;
  }

  .header-toolbar {
    order: 2;
    margin-left: auto;
    gap: 8px;
  }
  
  .mobile-menu-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  
  .header-actions {
    margin-left: 0;
  }
  
  nav.menu-open {
    display: flex;
    flex-direction: column;
    gap: 16px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    padding: 20px;
    border-radius: 0 0 16px 16px;
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    box-shadow: var(--shadow-lg);
    transition: var(--transition);
    z-index: 999;
    margin: 0;
  }

  nav#mainNav {
    order: 4;
    flex: 1 1 100%;
  }
  
  .language-switcher-toggle {
    padding: 8px 10px;
    font-size: 0.8rem;
  }
  
  .dropdown {
    width: 100%;
    margin: 10px 0;
  }
  
  .dropbtn {
    width: 100%;
    justify-content: space-between;
    background: rgba(255, 255, 255, 0.1);
  }
  
  .dropdown-content {
    position: static;
    box-shadow: none;
    margin-top: 10px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
  }
  
  .dropdown-content a {
    border-left: 3px solid var(--accent);
    padding-left: 20px;
    color: white;
  }
  
  .dropdown-content a:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateX(5px);
  }
  
  .get-started {
    display: none;
  }
}

@media (max-width: 768px) {
  .header-brand {
    font-size: 0.8rem;
  }
  
  header img {
    height: 42px;
  }
  
  .get-started {
    padding: 10px 14px;
    font-size: 0.88rem;
  }
  
  .language-switcher-dropdown {
    right: 0;
  }
}

@media (max-width: 576px) {
  .header-actions {
    gap: 8px;
  }
  
  .get-started {
    padding: 8px 12px;
    font-size: 0.82rem;
  }
  
  .language-switcher-toggle span:not(.language-flag) {
    display: none;
  }
  
  .language-switcher-toggle {
    padding: 8px;
    min-width: 44px;
    justify-content: center;
  }

  .language-switcher-toggle .fa-chevron-down {
    display: none;
  }
}

@media (max-width: 480px) {
  header img {
    height: 38px;
  }
  
  .header-brand {
    font-size: 0.76rem;
  }
  
  .get-started {
    font-size: 0.78rem;
    padding: 8px 10px;
  }

  .mobile-menu-toggle {
    padding: 10px;
    min-width: 44px;
    min-height: 44px;
  }
}
</style>
</head>
<body class="site-body<?= !empty($card_only_mode) ? ' card-only-mode' : '' ?>">
<header>
  <div class="header-left">
    <a href="index.php" class="header-logo-link" aria-label="<?php echo htmlspecialchars(ht('nav_home'), ENT_QUOTES, 'UTF-8'); ?>">
      <img src="scholarsync-global-logo.jpg" alt="" width="48" height="48" onerror="this.style.display='none'">
    </a>
  </div>
  <div class="header-brand">ScholarSync Global</div>
  
  <nav id="mainNav" aria-label="Primary">
    <a href="index.php"><?php echo ht('nav_home'); ?></a>
    <a href="about.php"><?php echo ht('nav_about'); ?></a>
    <a href="programs.php"><?php echo ht('nav_programs'); ?></a>
    <a href="services.php"><?php echo ht('nav_services'); ?></a>
    <a href="universities.php"><?php echo ht('nav_universities'); ?></a>
    <a href="partners.php"><?php echo ht('nav_partners'); ?></a>
    <a href="testimonials.php"><?php echo ht('nav_testimonials'); ?></a>
    <a href="contact.php"><?php echo ht('nav_contact'); ?></a>
   <a href="https://scholarsyncglobal.ca/" target="_blank" rel="noopener">E-Learning</a>

    <div class="dropdown" id="navStudentDropdown">
      <button type="button" class="dropbtn" id="studentDropdownBtn" aria-expanded="false" aria-haspopup="true" aria-controls="studentDropdownMenu">
        <i class="fas fa-user-graduate"></i> <?php echo ht('nav_student'); ?> <i class="fas fa-chevron-down" aria-hidden="true"></i>
      </button>
      <div class="dropdown-content" id="studentDropdownMenu" role="menu">
        <a href="student-login.php" role="menuitem">
          <i class="fas fa-sign-in-alt"></i> <?php echo ht('nav_student_login'); ?>
        </a>
        <a href="refund-request.php" role="menuitem">
          <i class="fas fa-undo"></i> <?php echo ht('nav_request_refund'); ?>
        </a>
      </div>
    </div>
    
    <div class="dropdown" id="navPaymentDropdown">
      <button type="button" class="dropbtn" id="paymentDropdownBtn" aria-expanded="false" aria-haspopup="true" aria-controls="paymentDropdownMenu">
        <i class="fas fa-credit-card"></i> <?php echo ht('nav_payment'); ?> <i class="fas fa-chevron-down" aria-hidden="true"></i>
      </button>
      <div class="dropdown-content" id="paymentDropdownMenu" role="menu">
        <a href="payments/checkout.php" role="menuitem">
          <i class="fas fa-credit-card"></i> <?php echo ht('nav_pay_service'); ?>
        </a>
      </div>
    </div>
  </nav>
  
  <div class="header-toolbar">
    <div class="header-actions">
      <!-- Language Switcher -->
      <div class="language-switcher">
        <button type="button" class="language-switcher-toggle" id="languageToggle" aria-expanded="false" aria-haspopup="listbox" aria-controls="languageDropdown">
          <span class="language-flag" aria-hidden="true">
            <?php echo $current_lang === 'fr' ? '🇫🇷' : '🇬🇧'; ?>
          </span>
          <span class="language-name">
            <?php echo $current_lang === 'fr' ? 'FR' : 'EN'; ?>
          </span>
          <i class="fas fa-chevron-down" aria-hidden="true"></i>
        </button>
        
        <div class="language-switcher-dropdown" id="languageDropdown" role="listbox">
          <a href="?lang=en" class="language-option <?php echo $current_lang === 'en' ? 'active' : ''; ?>">
            <span class="language-flag">🇬🇧</span>
            <span class="language-name">English</span>
            <?php if($current_lang === 'en'): ?>
              <i class="fas fa-check"></i>
            <?php endif; ?>
          </a>
          <a href="?lang=fr" class="language-option <?php echo $current_lang === 'fr' ? 'active' : ''; ?>">
            <span class="language-flag">🇫🇷</span>
            <span class="language-name">Français</span>
            <?php if($current_lang === 'fr'): ?>
              <i class="fas fa-check"></i>
            <?php endif; ?>
          </a>
        </div>
      </div>
      
      <!-- Get Started: choose portal -->
      <div class="portal-switcher" id="portalSwitcher">
        <button type="button" class="get-started portal-switcher-toggle" id="portalToggle" aria-expanded="false" aria-haspopup="listbox" aria-controls="portalDropdown">
          <?php echo ht('get_started'); ?> <i class="fas fa-chevron-down" aria-hidden="true" style="margin-left:8px;"></i>
        </button>
        <div class="portal-switcher-dropdown" id="portalDropdown" role="listbox" aria-label="Choose portal">
          <a href="admin-login.php" class="portal-option">
            <i class="fas fa-user-shield" aria-hidden="true"></i>
            <span>Admin login</span>
          </a>
          <a href="student-login.php" class="portal-option">
            <i class="fas fa-user-graduate" aria-hidden="true"></i>
            <span>Student login</span>
          </a>
        </div>
      </div>
    </div>
    
    <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Menu" aria-expanded="false" aria-controls="mainNav">
      <i class="fas fa-bars" aria-hidden="true"></i>
    </button>
  </div>
</header>

<script>
// Enhanced JavaScript for mobile menu toggle and language switcher
const mobileMenuToggle = document.getElementById('mobileMenuToggle');
const mainNav = document.getElementById('mainNav');
const languageToggle = document.getElementById('languageToggle');
const languageDropdown = document.getElementById('languageDropdown');
const portalToggle = document.getElementById('portalToggle');
const portalDropdown = document.getElementById('portalDropdown');

// Mobile menu toggle
if (mobileMenuToggle && mainNav) {
  function setMenuOpen(open) {
    mainNav.classList.toggle('menu-open', open);
    mobileMenuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    const icon = mobileMenuToggle.querySelector('i');
    if (icon) {
      icon.classList.toggle('fa-bars', !open);
      icon.classList.toggle('fa-times', open);
    }
  }

  mobileMenuToggle.addEventListener('click', (e) => {
    e.stopPropagation();
    setMenuOpen(!mainNav.classList.contains('menu-open'));
  });

  // Close menu when clicking outside
  document.addEventListener('click', (e) => {
    if (!mainNav.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
      setMenuOpen(false);
    }
  });
}

// Language switcher toggle
if (languageToggle && languageDropdown) {
  languageToggle.addEventListener('click', (e) => {
    e.stopPropagation();
    const open = !languageDropdown.classList.contains('active');
    languageDropdown.classList.toggle('active', open);
    languageToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  // Close language dropdown when clicking outside
  document.addEventListener('click', (e) => {
    if (!languageToggle.contains(e.target) && !languageDropdown.contains(e.target)) {
      languageDropdown.classList.remove('active');
      languageToggle.setAttribute('aria-expanded', 'false');
    }
  });

  // Close dropdown when selecting a language
  document.querySelectorAll('.language-option').forEach(option => {
    option.addEventListener('click', () => {
      languageDropdown.classList.remove('active');
      languageToggle.setAttribute('aria-expanded', 'false');
    });
  });
}

// Portal switcher toggle (Get Started)
if (portalToggle && portalDropdown) {
  portalToggle.addEventListener('click', (e) => {
    e.stopPropagation();
    const open = !portalDropdown.classList.contains('active');
    portalDropdown.classList.toggle('active', open);
    portalToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  document.addEventListener('click', (e) => {
    if (!portalToggle.contains(e.target) && !portalDropdown.contains(e.target)) {
      portalDropdown.classList.remove('active');
      portalToggle.setAttribute('aria-expanded', 'false');
    }
  });
}

// Payment dropdown: click to toggle (always works); hover still works via CSS
(function () {
  const payWrap = document.getElementById('navPaymentDropdown');
  const payBtn = document.getElementById('paymentDropdownBtn');
  const payMenu = document.getElementById('paymentDropdownMenu');
  if (!payWrap || !payBtn || !payMenu) return;

  function setOpen(open) {
    payWrap.classList.toggle('is-open', open);
    payBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  payBtn.addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    setOpen(!payWrap.classList.contains('is-open'));
  });

  payMenu.querySelectorAll('a[role="menuitem"]').forEach(function (link) {
    link.addEventListener('click', function () {
      setOpen(false);
    });
  });

  document.addEventListener('click', function (e) {
    if (!payWrap.contains(e.target)) setOpen(false);
  });
})();

// Student dropdown
(function () {
  const wrap = document.getElementById('navStudentDropdown');
  const btn = document.getElementById('studentDropdownBtn');
  const menu = document.getElementById('studentDropdownMenu');
  if (!wrap || !btn || !menu) return;

  function setOpen(open) {
    wrap.classList.toggle('is-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  btn.addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    setOpen(!wrap.classList.contains('is-open'));
  });

  menu.querySelectorAll('a[role="menuitem"]').forEach(function (link) {
    link.addEventListener('click', function () {
      setOpen(false);
    });
  });

  document.addEventListener('click', function (e) {
    if (!wrap.contains(e.target)) setOpen(false);
  });
})();

// Close all dropdowns when pressing Escape key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    if (mainNav) {
      mainNav.classList.remove('menu-open');
      if (mobileMenuToggle) {
        mobileMenuToggle.setAttribute('aria-expanded', 'false');
        const ic = mobileMenuToggle.querySelector('i');
        if (ic) { ic.classList.add('fa-bars'); ic.classList.remove('fa-times'); }
      }
    }
    if (languageDropdown) languageDropdown.classList.remove('active');
    if (languageToggle) languageToggle.setAttribute('aria-expanded', 'false');
    if (portalDropdown) portalDropdown.classList.remove('active');
    if (portalToggle) portalToggle.setAttribute('aria-expanded', 'false');
    const payWrap = document.getElementById('navPaymentDropdown');
    if (payWrap) payWrap.classList.remove('is-open');
    const payBtn = document.getElementById('paymentDropdownBtn');
    if (payBtn) payBtn.setAttribute('aria-expanded', 'false');
  }
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    const targetId = this.getAttribute('href');
    if (targetId === '#') return;
    
    const targetElement = document.querySelector(targetId);
    if (targetElement) {
      e.preventDefault();
      targetElement.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }
  });
});

// Add active state to current page link
document.addEventListener('DOMContentLoaded', () => {
  const currentPage = window.location.pathname.split('/').pop();
  const navLinks = document.querySelectorAll('nav a:not(.dropdown-content a)');
  
  navLinks.forEach(link => {
    const linkPage = link.getAttribute('href');
    if (linkPage === currentPage || 
        (currentPage === '' && linkPage === 'index.php') ||
        (currentPage === 'index.php' && linkPage === '')) {
      link.style.opacity = '1';
      link.style.fontWeight = '600';
      link.style.color = 'var(--accent-light)';
    }
  });
});
</script>