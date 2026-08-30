<?php
// ============================================
// INCLUDE HEADER FOR LANGUAGE SWITCHING LOGIC
// ============================================
include 'header.php';

// Set page title with language switching
$pageTitle = $current_lang === 'en' ? 'Partners - ScholarSync Global' : 'Partenaires - ScholarSync Global';

// ============================================
// TRANSLATIONS FOR PARTNERS PAGE
// ============================================

$partners_translations = [
    'en' => [
        // Hero Section
        'hero_title' => 'Our Strategic Partners',
        'hero_subtitle' => 'Global network powering your success',
        'hero_description' => 'Collaborating with industry leaders to provide comprehensive solutions for international education and career development.',
        
        // Partnership Network
        'network_title' => 'Our Partnership Network',
        'network_subtitle' => 'Building bridges across industries and borders',
        
        // Partnership Statistics
        'stats_title' => 'Partnership Impact',
        'stats_subtitle' => 'Numbers that demonstrate our collaborative strength',
        
        'stat1_title' => 'Strategic Alliances',
        'stat1_desc' => 'Active partnerships across sectors',
        
        'stat2_title' => 'Countries Covered',
        'stat2_desc' => 'Global reach through partners',
        
        'stat3_title' => 'Students Supported',
        'stat3_desc' => 'Through partner collaborations',
        
        'stat4_title' => 'Years of Trust',
        'stat4_desc' => 'Building lasting relationships',
        
        // Partnership Categories
        'categories_title' => 'Partnership Categories',
        'categories_subtitle' => 'Comprehensive network across all service areas',
        
        // Featured Partners Grid
        'featured_title' => 'Featured Partners',
        'featured_subtitle' => 'Leading organizations we collaborate with',
        
        // Partnership Benefits
        'benefits_title' => 'Partner Benefits for Students',
        'benefits_subtitle' => 'Exclusive advantages through our collaborations',
        
        'benefit1_title' => 'Priority Access',
        'benefit1_desc' => 'Exclusive early access to opportunities and programs',
        
        'benefit2_title' => 'Special Rates',
        'benefit2_desc' => 'Discounted services and preferential pricing',
        
        'benefit3_title' => 'Streamlined Processes',
        'benefit3_desc' => 'Simplified procedures through integrated systems',
        
        'benefit4_title' => 'Enhanced Support',
        'benefit4_desc' => 'Dedicated assistance from partner organizations',
        
        'benefit5_title' => 'Quality Assurance',
        'benefit5_desc' => 'Vetted and certified partner services',
        
        'benefit6_title' => 'Innovation Access',
        'benefit6_desc' => 'First access to new programs and technologies',
        
        // Partnership Models
        'models_title' => 'Partnership Models',
        'models_subtitle' => 'Different ways we collaborate for your benefit',
        
        'model1_title' => 'Strategic Alliances',
        'model1_desc' => 'Long-term partnerships with shared objectives and resources',
        
        'model2_title' => 'Preferred Provider',
        'model2_desc' => 'Exclusive arrangements with selected service providers',
        
        'model3_title' => 'Academic Collaborations',
        'model3_desc' => 'Direct partnerships with educational institutions',
        
        'model4_title' => 'Industry Partnerships',
        'model4_desc' => 'Collaborations with corporate and industry leaders',
        
        'model5_title' => 'Government Tie-ups',
        'model5_desc' => 'Official partnerships with government agencies',
        
        'model6_title' => 'Technology Integrations',
        'model6_desc' => 'Partnerships with tech providers for enhanced services',
        
        // Partner Success Stories
        'success_title' => 'Partner Success Stories',
        'success_subtitle' => 'Real impact through collaboration',
        
        'story1_title' => 'Scholarship Initiative with Global Bank',
        'story1_desc' => 'Secured $2M in education loans for 150 students through our banking partnership',
        
        'story2_title' => 'University Pathway Program',
        'story2_desc' => 'Created direct admission pathways with 50+ universities worldwide',
        
        'story3_title' => 'Accommodation Network',
        'story3_desc' => 'Established preferred housing options in 30+ countries',
        
        // Partnership Certifications
        'certifications_title' => 'Partnership Certifications',
        'certifications_subtitle' => 'Official recognitions and accreditations',
        
        // Partnership Process
        'process_title' => 'Our Partnership Process',
        'process_subtitle' => 'How we establish and maintain successful collaborations',
        
        'process_step1' => 'Identification & Research',
        'process_step2' => 'Due Diligence & Vetting',
        'process_step3' => 'Agreement & Integration',
        'process_step4' => 'Implementation & Support',
        'process_step5' => 'Monitoring & Evaluation',
        'process_step6' => 'Growth & Expansion',
        
        // Partnership Values
        'values_title' => 'Partnership Values',
        'values_subtitle' => 'Principles guiding our collaborations',
        
        'value1_title' => 'Integrity',
        'value1_desc' => 'Ethical practices and transparent relationships',
        
        'value2_title' => 'Excellence',
        'value2_desc' => 'Commitment to quality in all partnerships',
        
        'value3_title' => 'Innovation',
        'value3_desc' => 'Collaborative development of new solutions',
        
        'value4_title' => 'Sustainability',
        'value4_desc' => 'Long-term mutually beneficial relationships',
        
        'value5_title' => 'Student-Centric',
        'value5_desc' => 'All partnerships designed for student benefit',
        
        'value6_title' => 'Global Perspective',
        'value6_desc' => 'International outlook in all collaborations',
        
        // Partnership Inquiry
        'inquiry_title' => 'Become a Partner',
        'inquiry_subtitle' => 'Join our network of excellence',
        'inquiry_description' => 'We welcome organizations that share our commitment to student success and global education.',
        
        // CTA
        'cta_title' => 'Partner With Us',
        'cta_description' => 'Join our network of trusted partners to create more opportunities for students worldwide.',
        'cta_button' => 'Become a Partner',
        'cta_button2' => 'View Partnership Brochure',
        
        // Page Metadata
        'page_description' => 'Strategic partnerships at ScholarSync Global - collaborating with leading institutions worldwide to provide comprehensive education and career solutions.',
        'page_title' => 'Partners - ScholarSync Global',
    ],
    
    'fr' => [
        // Hero Section
        'hero_title' => 'Nos Partenaires Stratégiques',
        'hero_subtitle' => 'Réseau mondial alimentant votre succès',
        'hero_description' => 'Collaboration avec des leaders du secteur pour offrir des solutions complètes pour l\'éducation internationale.',
        
        // Partnership Network
        'network_title' => 'Notre Réseau de Partenariats',
        'network_subtitle' => 'Construire des ponts à travers industries et frontières',
        
        // Partnership Statistics
        'stats_title' => 'Impact des Partenariats',
        'stats_subtitle' => 'Chiffres démontrant notre force collaborative',
        
        'stat1_title' => 'Alliances Stratégiques',
        'stat1_desc' => 'Partenariats actifs à travers secteurs',
        
        'stat2_title' => 'Pays Couverts',
        'stat2_desc' => 'Portée mondiale via partenaires',
        
        'stat3_title' => 'Étudiants Soutenus',
        'stat3_desc' => 'Grâce aux collaborations partenaires',
        
        'stat4_title' => 'Années de Confiance',
        'stat4_desc' => 'Construction de relations durables',
        
        // Partnership Categories
        'categories_title' => 'Catégories de Partenariats',
        'categories_subtitle' => 'Réseau complet à travers tous domaines de service',
        
        // Featured Partners Grid
        'featured_title' => 'Partenaires en Vedette',
        'featured_subtitle' => 'Organisations leaders avec lesquelles nous collaborons',
        
        // Partnership Benefits
        'benefits_title' => 'Avantages Partenaires pour Étudiants',
        'benefits_subtitle' => 'Avantages exclusifs grâce à nos collaborations',
        
        'benefit1_title' => 'Accès Prioritaire',
        'benefit1_desc' => 'Accès anticipé exclusif aux opportunités et programmes',
        
        'benefit2_title' => 'Tarifs Spéciaux',
        'benefit2_desc' => 'Services à prix réduits et tarifs préférentiels',
        
        'benefit3_title' => 'Processus Rationalisés',
        'benefit3_desc' => 'Procédures simplifiées via systèmes intégrés',
        
        'benefit4_title' => 'Support Amélioré',
        'benefit4_desc' => 'Assistance dédiée des organisations partenaires',
        
        'benefit5_title' => 'Assurance Qualité',
        'benefit5_desc' => 'Services partenaires vérifiés et certifiés',
        
        'benefit6_title' => 'Accès Innovation',
        'benefit6_desc' => 'Premier accès aux nouveaux programmes et technologies',
        
        // Partnership Models
        'models_title' => 'Modèles de Partenariats',
        'models_subtitle' => 'Différentes façons de collaborer pour votre bénéfice',
        
        'model1_title' => 'Alliances Stratégiques',
        'model1_desc' => 'Partenariats à long terme avec objectifs et ressources partagés',
        
        'model2_title' => 'Fournisseur Préféré',
        'model2_desc' => 'Arrangements exclusifs avec fournisseurs sélectionnés',
        
        'model3_title' => 'Collaborations Académiques',
        'model3_desc' => 'Partenariats directs avec institutions éducatives',
        
        'model4_title' => 'Partenariats Industriels',
        'model4_desc' => 'Collaborations avec leaders corporatifs et industriels',
        
        'model5_title' => 'Partenariats Gouvernementaux',
        'model5_desc' => 'Partenariats officiels avec agences gouvernementales',
        
        'model6_title' => 'Intégrations Technologiques',
        'model6_desc' => 'Partenariats avec fournisseurs technologiques',
        
        // Partner Success Stories
        'success_title' => 'Histoires de Réussite',
        'success_subtitle' => 'Impact réel à travers collaboration',
        
        'story1_title' => 'Initiative Bourses avec Banque Mondiale',
        'story1_desc' => 'Sécurisé 2M$ en prêts éducation pour 150 étudiants via notre partenariat bancaire',
        
        'story2_title' => 'Programme Passerelle Universitaire',
        'story2_desc' => 'Créé des chemins d\'admission directs avec 50+ universités',
        
        'story3_title' => 'Réseau Logement',
        'story3_desc' => 'Établi options de logement préférentielles dans 30+ pays',
        
        // Partnership Certifications
        'certifications_title' => 'Certifications Partenaires',
        'certifications_subtitle' => 'Reconnaissances et accréditations officielles',
        
        // Partnership Process
        'process_title' => 'Notre Processus de Partenariat',
        'process_subtitle' => 'Comment nous établissons et maintenons des collaborations réussies',
        
        'process_step1' => 'Identification & Recherche',
        'process_step2' => 'Diligence & Vérification',
        'process_step3' => 'Accord & Intégration',
        'process_step4' => 'Implémentation & Support',
        'process_step5' => 'Surveillance & Évaluation',
        'process_step6' => 'Croissance & Expansion',
        
        // Partnership Values
        'values_title' => 'Valeurs des Partenariats',
        'values_subtitle' => 'Principes guidant nos collaborations',
        
        'value1_title' => 'Intégrité',
        'value1_desc' => 'Pratiques éthiques et relations transparentes',
        
        'value2_title' => 'Excellence',
        'value2_desc' => 'Engagement envers la qualité dans tous partenariats',
        
        'value3_title' => 'Innovation',
        'value3_desc' => 'Développement collaboratif de nouvelles solutions',
        
        'value4_title' => 'Durabilité',
        'value4_desc' => 'Relations mutuellement bénéfiques à long terme',
        
        'value5_title' => 'Centré Étudiant',
        'value5_desc' => 'Tous partenariats conçus pour bénéfice étudiant',
        
        'value6_title' => 'Perspective Mondiale',
        'value6_desc' => 'Vision internationale dans toutes collaborations',
        
        // Partnership Inquiry
        'inquiry_title' => 'Devenir Partenaire',
        'inquiry_subtitle' => 'Rejoignez notre réseau d\'excellence',
        'inquiry_description' => 'Nous accueillons les organisations partageant notre engagement envers le succès des étudiants.',
        
        // CTA
        'cta_title' => 'Devenir Partenaire',
        'cta_description' => 'Rejoignez notre réseau de partenaires de confiance pour créer plus d\'opportunités pour les étudiants.',
        'cta_button' => 'Devenir Partenaire',
        'cta_button2' => 'Voir Brochure Partenariat',
        
        // Page Metadata
        'page_description' => 'Partenariats stratégiques de ScholarSync Global - collaboration avec des institutions leaders mondiales pour offrir des solutions éducatives complètes.',
        'page_title' => 'Partenaires - ScholarSync Global',
    ]
];

// Function to get partners page translation
function pt($key) {
    global $partners_translations, $current_lang;
    
    // Fallback to English if key missing
    if (isset($partners_translations[$current_lang][$key])) {
        return $partners_translations[$current_lang][$key];
    } elseif (isset($partners_translations['en'][$key])) {
        return $partners_translations['en'][$key];
    }
    
    return $key; // Return key itself as last resort
}

// Define partnership categories
$partnership_categories = [
    [
        'title' => 'Educational Institutions',
        'icon' => 'fas fa-university',
        'description' => 'Universities, colleges, and academic organizations worldwide',
        'count' => '500+',
        'color' => '#427431'
    ],
    [
        'title' => 'Financial Partners',
        'icon' => 'fas fa-hand-holding-usd',
        'description' => 'Banks, loan providers, and scholarship organizations',
        'count' => '25+',
        'color' => '#3661B9'
    ],
    [
        'title' => 'Immigration Services',
        'icon' => 'fas fa-passport',
        'description' => 'Visa consultants and immigration law firms',
        'count' => '40+',
        'color' => '#2f5a26'
    ],
    [
        'title' => 'Accommodation Providers',
        'icon' => 'fas fa-home',
        'description' => 'Student housing and accommodation services',
        'count' => '100+',
        'color' => '#427431'
    ],
    [
        'title' => 'Career Development',
        'icon' => 'fas fa-briefcase',
        'description' => 'Recruitment agencies and corporate partners',
        'count' => '75+',
        'color' => '#3661B9'
    ],
    [
        'title' => 'Travel & Logistics',
        'icon' => 'fas fa-plane',
        'description' => 'Airlines, travel agencies, and logistic services',
        'count' => '30+',
        'color' => '#2f5a26'
    ]
];

// Define featured partners
$featured_partners = [
    [
        'name' => 'Global Education Finance',
        'logo_icon' => 'fas fa-landmark',
        'type' => 'Financial Partner',
        'description' => 'Leading education loan provider with competitive rates',
        'since' => '2015',
        'benefits' => ['Low-interest loans', 'Quick approval', 'Flexible repayment']
    ],
    [
        'name' => 'International Student Housing',
        'logo_icon' => 'fas fa-building',
        'type' => 'Accommodation Partner',
        'description' => 'Premium student accommodation across 30+ countries',
        'since' => '2012',
        'benefits' => ['Guaranteed housing', 'All-inclusive packages', '24/7 support']
    ],
    [
        'name' => 'Career Connect Network',
        'logo_icon' => 'fas fa-network-wired',
        'type' => 'Career Partner',
        'description' => 'Global recruitment and career development platform',
        'since' => '2018',
        'benefits' => ['Job placements', 'Career counseling', 'Industry networking']
    ]
];

// Define partnership models
$partnership_models = [
    [
        'title_key' => 'model1_title',
        'icon' => 'fas fa-handshake',
        'description_key' => 'model1_desc',
        'examples' => ['Joint programs', 'Shared resources', 'Co-branded initiatives']
    ],
    [
        'title_key' => 'model2_title',
        'icon' => 'fas fa-star',
        'description_key' => 'model2_desc',
        'examples' => ['Exclusive discounts', 'Priority service', 'Custom solutions']
    ],
    [
        'title_key' => 'model3_title',
        'icon' => 'fas fa-graduation-cap',
        'description_key' => 'model3_desc',
        'examples' => ['Pathway programs', 'Transfer agreements', 'Joint research']
    ],
    [
        'title_key' => 'model4_title',
        'icon' => 'fas fa-industry',
        'description_key' => 'model4_desc',
        'examples' => ['Internship programs', 'Industry training', 'Corporate sponsorships']
    ],
    [
        'title_key' => 'model5_title',
        'icon' => 'fas fa-globe-americas',
        'description_key' => 'model5_desc',
        'examples' => ['Visa facilitation', 'Government scholarships', 'Official recognition']
    ],
    [
        'title_key' => 'model6_title',
        'icon' => 'fas fa-laptop-code',
        'description_key' => 'model6_desc',
        'examples' => ['Platform integration', 'Digital solutions', 'Tech innovation']
    ]
];

// Define success stories
$success_stories = [
    [
        'title_key' => 'story1_title',
        'description_key' => 'story1_desc',
        'impact' => ['150 students funded', '$2M+ secured', '5-year partnership']
    ],
    [
        'title_key' => 'story2_title',
        'description_key' => 'story2_desc',
        'impact' => ['50+ universities', 'Direct admission', 'Reduced processing time']
    ],
    [
        'title_key' => 'story3_title',
        'description_key' => 'story3_desc',
        'impact' => ['30+ countries', 'Safe housing', 'Affordable options']
    ]
];

// Define partnership values
$partnership_values = [
    ['title_key' => 'value1_title', 'icon' => 'fas fa-shield-alt'],
    ['title_key' => 'value2_title', 'icon' => 'fas fa-award'],
    ['title_key' => 'value3_title', 'icon' => 'fas fa-lightbulb'],
    ['title_key' => 'value4_title', 'icon' => 'fas fa-seedling'],
    ['title_key' => 'value5_title', 'icon' => 'fas fa-user-graduate'],
    ['title_key' => 'value6_title', 'icon' => 'fas fa-globe']
];

// Define certifications
$certifications = [
    ['name' => 'ISO 9001:2015', 'icon' => 'fas fa-certificate'],
    ['name' => 'ICEF Certified', 'icon' => 'fas fa-globe'],
    ['name' => 'EAIE Member', 'icon' => 'fas fa-university'],
    ['name' => 'PIER Certified', 'icon' => 'fas fa-award'],
    ['name' => 'Data Security', 'icon' => 'fas fa-lock'],
    ['name' => 'Quality Partner', 'icon' => 'fas fa-star']
];
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo pt('page_description'); ?>">
<title><?php echo pt('page_title'); ?></title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ============================================
   PARTNERS PAGE STYLES
   Professional, collaborative design showcasing partnerships
============================================ */
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
  --shadow-xl: 0 20px 40px rgba(1, 47, 107, 0.15);
  
  /* Transitions */
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-slow: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--bg);
  color: var(--text);
  line-height: 1.6;
  overflow-x: hidden;
  -webkit-font-smoothing: antialiased;
}

/* ===== COMMON STYLES ===== */
.section-padding {
  padding: 80px 20px;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

.section-header {
  text-align: center;
  max-width: 800px;
  margin: 0 auto 60px;
}

.section-title {
  font-size: 2.8rem;
  font-weight: 800;
  color: var(--primary-navy);
  margin-bottom: 15px;
  position: relative;
  display: inline-block;
}

.section-title::after {
  content: '';
  position: absolute;
  bottom: -12px;
  left: 50%;
  transform: translateX(-50%);
  width: 80px;
  height: 5px;
  background: linear-gradient(90deg, var(--accent-gold), var(--accent-teal));
  border-radius: 3px;
}

.section-subtitle {
  font-size: 1.2rem;
  color: var(--accent-gold);
  font-weight: 600;
  margin-bottom: 10px;
  letter-spacing: 0.5px;
}

.section-description {
  font-size: 1.1rem;
  color: var(--text-light);
  line-height: 1.7;
  max-width: 700px;
  margin: 0 auto;
}

/* ===== HERO SECTION ===== */
.partners-hero {
  min-height: 70vh;
  background: linear-gradient(135deg, #427431 0%, #3661B9 50%, #2f5a26 100%);
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  padding: 100px 20px 80px;
  text-align: center;
  color: white;
}

.hero-bg-pattern {
  position: absolute;
  width: 100%;
  height: 100%;
  background: 
    radial-gradient(circle at 20% 30%, rgba(242, 166, 90, 0.1) 0%, transparent 40%),
    radial-gradient(circle at 80% 70%, rgba(45, 212, 191, 0.1) 0%, transparent 40%),
    url('data:image/svg+xml,<svg width="120" height="120" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg"><path d="M40,40 L80,40 L80,80 L40,80 Z" fill="none" stroke="white" stroke-width="1" stroke-opacity="0.05"/><path d="M20,60 L100,60" fill="none" stroke="white" stroke-width="1" stroke-opacity="0.05"/><path d="M60,20 L60,100" fill="none" stroke="white" stroke-width="1" stroke-opacity="0.05"/></svg>');
  background-size: 120px;
}

.hero-content {
  max-width: 900px;
  margin: 0 auto;
  position: relative;
  z-index: 2;
}

.hero-content h1 {
  font-size: 3.5rem;
  font-weight: 900;
  line-height: 1.1;
  margin-bottom: 20px;
  color: white;
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.hero-subtitle {
  font-size: 1.3rem;
  font-weight: 600;
  color: var(--accent-gold);
  margin-bottom: 20px;
  letter-spacing: 1px;
}

.hero-description {
  font-size: 1.2rem;
  color: rgba(255, 255, 255, 0.9);
  max-width: 700px;
  margin: 0 auto;
  line-height: 1.7;
}

/* ===== NETWORK SECTION ===== */
.network-section {
  background: white;
}

.network-visual {
  max-width: 800px;
  margin: 0 auto;
  position: relative;
  height: 400px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.network-center {
  width: 120px;
  height: 120px;
  background: linear-gradient(135deg, var(--primary-navy), var(--secondary-blue));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.5rem;
  font-weight: 700;
  z-index: 2;
  position: relative;
  box-shadow: var(--shadow-xl);
}

.network-node {
  position: absolute;
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, var(--accent-light), var(--teal-light));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
  color: var(--primary-navy);
  transition: var(--transition);
  cursor: pointer;
  z-index: 1;
}

.network-node:hover {
  transform: scale(1.2);
  background: linear-gradient(135deg, var(--accent-gold), var(--accent-teal));
  color: white;
}

.network-node:nth-child(2) { top: 20%; left: 20%; }
.network-node:nth-child(3) { top: 20%; right: 20%; }
.network-node:nth-child(4) { top: 50%; left: 10%; transform: translateY(-50%); }
.network-node:nth-child(5) { top: 50%; right: 10%; transform: translateY(-50%); }
.network-node:nth-child(6) { bottom: 20%; left: 20%; }
.network-node:nth-child(7) { bottom: 20%; right: 20%; }

.network-lines {
  position: absolute;
  width: 100%;
  height: 100%;
  z-index: 0;
}

.network-line {
  position: absolute;
  height: 2px;
  background: linear-gradient(90deg, var(--primary-navy), var(--accent-teal));
  transform-origin: 0 0;
  opacity: 0.3;
}

/* ===== STATS SECTION ===== */
.stats-section {
  background: linear-gradient(135deg, var(--bg) 0%, var(--pure-white) 100%);
  padding: 80px 20px;
}

.stats-grid {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 40px;
  text-align: center;
}

.stat-card {
  padding: 40px 30px;
  background: white;
  border-radius: 20px;
  box-shadow: var(--shadow-md);
  border: 1px solid var(--border-light);
  transition: var(--transition);
}

.stat-card:hover {
  transform: translateY(-10px);
  box-shadow: var(--shadow-lg);
}

.stat-number {
  font-size: 3.5rem;
  font-weight: 800;
  margin-bottom: 10px;
  color: var(--primary-navy);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.stat-number i {
  font-size: 2.5rem;
  color: var(--accent-teal);
}

.stat-card h3 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 10px;
}

.stat-card p {
  color: var(--text-light);
  font-size: 1rem;
  line-height: 1.6;
}

/* ===== CATEGORIES SECTION ===== */
.categories-section {
  background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
}

.categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.category-card {
  background: white;
  padding: 40px 35px;
  border-radius: 20px;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-light);
  transition: var(--transition);
  text-align: center;
  position: relative;
  overflow: hidden;
}

.category-card:hover {
  transform: translateY(-10px);
  box-shadow: var(--shadow-lg);
}

.category-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--primary-navy), var(--secondary-blue));
}

.category-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 25px;
  background: linear-gradient(135deg, var(--primary-light), var(--teal-light));
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  color: var(--primary-navy);
  transition: var(--transition);
}

.category-card:hover .category-icon {
  transform: scale(1.1) rotate(5deg);
}

.category-card h3 {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 15px;
}

.category-card p {
  color: var(--text-light);
  line-height: 1.6;
  margin-bottom: 20px;
  font-size: 1rem;
}

.category-count {
  background: linear-gradient(135deg, var(--accent-light), var(--teal-light));
  color: var(--primary-navy);
  padding: 8px 20px;
  border-radius: 20px;
  font-size: 0.95rem;
  font-weight: 600;
  display: inline-block;
}

/* ===== FEATURED PARTNERS SECTION ===== */
.featured-section {
  background: white;
}

.featured-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.partner-card {
  background: var(--bg);
  padding: 40px 35px;
  border-radius: 20px;
  border: 1px solid var(--border-light);
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.partner-card:hover {
  transform: translateY(-10px);
  box-shadow: var(--shadow-lg);
}

.partner-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--accent-gold), var(--accent-teal));
}

.partner-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 25px;
}

.partner-logo {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, var(--primary-light), var(--teal-light));
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  color: var(--primary-navy);
  flex-shrink: 0;
  transition: var(--transition);
}

.partner-card:hover .partner-logo {
  transform: rotate(10deg) scale(1.1);
}

.partner-info h3 {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 5px;
}

.partner-type {
  color: var(--accent-gold);
  font-weight: 600;
  font-size: 0.95rem;
  letter-spacing: 0.5px;
}

.partner-since {
  color: var(--text-light);
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  gap: 5px;
  margin-top: 5px;
}

.partner-description {
  color: var(--text-light);
  line-height: 1.6;
  margin-bottom: 25px;
  font-size: 1rem;
}

.partner-benefits {
  list-style: none;
  margin-bottom: 30px;
}

.partner-benefits li {
  padding: 8px 0;
  padding-left: 28px;
  position: relative;
  color: var(--text);
  font-size: 0.95rem;
}

.partner-benefits li::before {
  content: '✓';
  position: absolute;
  left: 0;
  color: var(--accent-teal);
  font-weight: 800;
  font-size: 1.2rem;
}

.partner-action {
  margin-top: 25px;
}

.partner-button {
  padding: 12px 24px;
  background: linear-gradient(135deg, var(--primary-navy), var(--secondary-blue));
  color: white;
  border: none;
  border-radius: 10px;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 1rem;
}

.partner-button:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 20px rgba(1, 47, 107, 0.3);
}

/* ===== BENEFITS SECTION ===== */
.benefits-section {
  background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
}

.benefits-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.benefit-card {
  background: white;
  padding: 40px 35px;
  border-radius: 20px;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-light);
  transition: var(--transition);
  text-align: center;
  position: relative;
  overflow: hidden;
}

.benefit-card:hover {
  transform: translateY(-10px);
  box-shadow: var(--shadow-lg);
}

.benefit-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 6px;
  height: 100%;
  background: linear-gradient(to bottom, var(--primary-navy), var(--accent-teal));
}

.benefit-icon {
  width: 70px;
  height: 70px;
  margin: 0 auto 25px;
  background: linear-gradient(135deg, var(--primary-light), var(--teal-light));
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  color: var(--primary-navy);
  transition: var(--transition);
}

.benefit-card:hover .benefit-icon {
  transform: rotate(10deg) scale(1.1);
}

.benefit-card h4 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 15px;
}

.benefit-card p {
  color: var(--text-light);
  line-height: 1.6;
  font-size: 1rem;
}

/* ===== MODELS SECTION ===== */
.models-section {
  background: white;
}

.models-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.model-card {
  background: var(--bg);
  padding: 40px 35px;
  border-radius: 20px;
  border: 1px solid var(--border-light);
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.model-card:hover {
  transform: translateY(-10px);
  box-shadow: var(--shadow-lg);
}

.model-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--accent-gold), var(--accent-teal));
}

.model-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 25px;
}

.model-icon {
  width: 70px;
  height: 70px;
  background: linear-gradient(135deg, var(--primary-light), var(--teal-light));
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  color: var(--primary-navy);
  flex-shrink: 0;
  transition: var(--transition);
}

.model-card:hover .model-icon {
  transform: rotate(10deg) scale(1.1);
}

.model-card h3 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 10px;
}

.model-description {
  color: var(--text-light);
  line-height: 1.6;
  margin-bottom: 25px;
  font-size: 1rem;
}

.model-examples {
  list-style: none;
}

.model-examples li {
  padding: 8px 0;
  padding-left: 28px;
  position: relative;
  color: var(--text);
  font-size: 0.95rem;
}

.model-examples li::before {
  content: '•';
  position: absolute;
  left: 0;
  color: var(--accent-teal);
  font-weight: 800;
  font-size: 1.5rem;
}

/* ===== SUCCESS STORIES SECTION ===== */
.success-section {
  background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
}

.success-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.success-card {
  background: white;
  padding: 40px 35px;
  border-radius: 20px;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-light);
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.success-card:hover {
  transform: translateY(-10px);
  box-shadow: var(--shadow-lg);
}

.success-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 6px;
  height: 100%;
  background: linear-gradient(to bottom, var(--primary-navy), var(--accent-teal));
}

.success-card h3 {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 15px;
}

.success-description {
  color: var(--text-light);
  line-height: 1.6;
  margin-bottom: 25px;
  font-size: 1rem;
}

.success-impact {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
}

.impact-tag {
  background: linear-gradient(135deg, var(--accent-light), var(--teal-light));
  color: var(--primary-navy);
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 600;
}

/* ===== CERTIFICATIONS SECTION ===== */
.certifications-section {
  background: white;
}

.certifications-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 25px;
  margin-top: 50px;
}

.certification-item {
  background: var(--bg);
  padding: 30px 20px;
  border-radius: 16px;
  text-align: center;
  border: 1px solid var(--border-light);
  transition: var(--transition);
}

.certification-item:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-md);
}

.certification-icon {
  font-size: 2.5rem;
  color: var(--primary-navy);
  margin-bottom: 15px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.certification-item h5 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--primary-navy);
}

/* ===== PROCESS SECTION ===== */
.process-section {
  background: linear-gradient(135deg, var(--primary-navy) 0%, var(--dark-blue) 100%);
  color: white;
}

.process-timeline {
  max-width: 1000px;
  margin: 0 auto;
  position: relative;
}

.process-timeline::before {
  content: '';
  position: absolute;
  top: 40px;
  left: 50px;
  right: 50px;
  height: 3px;
  background: rgba(255, 255, 255, 0.3);
  z-index: 1;
}

@media (max-width: 768px) {
  .process-timeline::before {
    display: none;
  }
}

.process-step {
  display: flex;
  align-items: flex-start;
  gap: 30px;
  margin-bottom: 50px;
  position: relative;
  z-index: 2;
}

@media (max-width: 768px) {
  .process-step {
    flex-direction: column;
    text-align: center;
    gap: 20px;
  }
}

.step-number {
  width: 80px;
  height: 80px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  font-weight: 800;
  flex-shrink: 0;
  position: relative;
  border: 4px solid rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  transition: var(--transition);
}

@media (max-width: 768px) {
  .step-number {
    margin: 0 auto;
  }
}

.process-step:hover .step-number {
  background: linear-gradient(135deg, var(--accent-gold), var(--accent-teal));
  transform: scale(1.1);
}

.step-content {
  flex: 1;
  padding-top: 15px;
}

.step-content h4 {
  font-size: 1.4rem;
  font-weight: 700;
  margin-bottom: 10px;
  color: white;
}

/* ===== VALUES SECTION ===== */
.values-section {
  background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
}

.values-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.value-item {
  text-align: center;
  padding: 40px 30px;
  background: white;
  border-radius: 16px;
  border: 1px solid var(--border-light);
  transition: var(--transition);
}

.value-item:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-md);
}

.value-icon {
  width: 70px;
  height: 70px;
  margin: 0 auto 25px;
  background: linear-gradient(135deg, var(--primary-light), var(--teal-light));
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  color: var(--primary-navy);
  transition: var(--transition);
}

.value-item:hover .value-icon {
  transform: rotate(10deg) scale(1.1);
}

.value-item h4 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 15px;
}

.value-item p {
  color: var(--text-light);
  line-height: 1.6;
  font-size: 1rem;
}

/* ===== INQUIRY SECTION ===== */
.inquiry-section {
  background: white;
}

.inquiry-content {
  max-width: 800px;
  margin: 0 auto;
  text-align: center;
}

.inquiry-box {
  background: linear-gradient(135deg, var(--bg) 0%, var(--pure-white) 100%);
  padding: 60px 50px;
  border-radius: 20px;
  border: 2px dashed var(--border);
  margin-top: 40px;
}

.inquiry-box h3 {
  font-size: 2rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 20px;
}

.inquiry-box p {
  color: var(--text-light);
  line-height: 1.7;
  margin-bottom: 30px;
  font-size: 1.1rem;
}

.inquiry-features {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 40px;
}

.inquiry-feature {
  text-align: center;
  padding: 20px;
  background: white;
  border-radius: 12px;
  border: 1px solid var(--border-light);
}

.inquiry-feature i {
  font-size: 2rem;
  color: var(--accent-teal);
  margin-bottom: 10px;
}

.inquiry-feature p {
  font-size: 0.9rem;
  color: var(--text);
  margin: 0;
}

/* ===== CTA SECTION ===== */
.partners-cta {
  background: linear-gradient(135deg, var(--primary-navy) 0%, var(--dark-blue) 100%);
  color: white;
  text-align: center;
  padding: 100px 20px;
  position: relative;
  overflow: hidden;
}

.cta-content {
  max-width: 800px;
  margin: 0 auto;
  position: relative;
  z-index: 2;
}

.cta-content h2 {
  font-size: 2.8rem;
  font-weight: 800;
  margin-bottom: 20px;
  color: white;
}

.cta-content p {
  font-size: 1.2rem;
  opacity: 0.9;
  margin-bottom: 40px;
  line-height: 1.6;
  max-width: 600px;
  margin-left: auto;
  margin-right: auto;
}

.cta-buttons {
  display: flex;
  gap: 20px;
  justify-content: center;
  flex-wrap: wrap;
}

.cta-button {
  padding: 18px 36px;
  font-size: 1.1rem;
  font-weight: 600;
  border-radius: 12px;
  border: none;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 220px;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

.cta-button::after {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
  transition: left 0.7s;
}

.cta-button:hover::after {
  left: 100%;
}

.cta-button-primary {
  background: linear-gradient(135deg, var(--accent-gold), var(--accent-teal));
  color: white;
  box-shadow: 0 8px 25px rgba(242, 166, 90, 0.25);
}

.cta-button-primary:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 35px rgba(242, 166, 90, 0.35);
}

.cta-button-secondary {
  background: transparent;
  color: white;
  border: 2px solid rgba(255, 255, 255, 0.3);
}

.cta-button-secondary:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: white;
  transform: translateY(-5px);
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
@media (max-width: 1200px) {
  .section-title {
    font-size: 2.5rem;
  }
  
  .hero-content h1 {
    font-size: 3rem;
  }
  
  .network-visual {
    height: 350px;
  }
}

@media (max-width: 992px) {
  .section-title {
    font-size: 2.2rem;
  }
  
  .hero-content h1 {
    font-size: 2.5rem;
  }
  
  .categories-grid,
  .models-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .network-visual {
    height: 300px;
  }
}

@media (max-width: 768px) {
  .section-padding {
    padding: 60px 20px;
  }
  
  .section-title {
    font-size: 2rem;
  }
  
  .hero-content h1 {
    font-size: 2.2rem;
  }
  
  .hero-subtitle {
    font-size: 1.1rem;
  }
  
  .hero-description {
    font-size: 1rem;
  }
  
  .categories-grid,
  .models-grid,
  .featured-grid,
  .success-grid {
    grid-template-columns: 1fr;
  }
  
  .cta-content h2 {
    font-size: 2.2rem;
  }
  
  .cta-buttons {
    flex-direction: column;
    align-items: center;
  }
  
  .cta-button {
    width: 100%;
    max-width: 300px;
  }
  
  .benefits-grid,
  .values-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .inquiry-box {
    padding: 40px 30px;
  }
  
  .network-visual {
    height: 250px;
  }
}

@media (max-width: 576px) {
  .section-title {
    font-size: 1.8rem;
  }
  
  .hero-content h1 {
    font-size: 1.8rem;
  }
  
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
  }
  
  .benefits-grid,
  .values-grid {
    grid-template-columns: 1fr;
  }
  
  .certifications-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .network-visual {
    display: none;
  }
  
  .inquiry-features {
    grid-template-columns: 1fr;
  }
}
</style>
</head>
<body>

<!-- Hero Section -->
<section class="partners-hero">
  <div class="hero-bg-pattern"></div>
  <div class="hero-content">
    <h1 class="fade-in"><?php echo pt('hero_title'); ?></h1>
    <p class="hero-subtitle fade-in"><?php echo pt('hero_subtitle'); ?></p>
    <p class="hero-description fade-in"><?php echo pt('hero_description'); ?></p>
  </div>
</section>

<!-- Network Visualization -->
<section class="network-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('network_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('network_subtitle'); ?></p>
    </div>
    
    <div class="network-visual fade-in">
      <div class="network-center">PCV</div>
      <div class="network-node"><i class="fas fa-university"></i></div>
      <div class="network-node"><i class="fas fa-landmark"></i></div>
      <div class="network-node"><i class="fas fa-passport"></i></div>
      <div class="network-node"><i class="fas fa-home"></i></div>
      <div class="network-node"><i class="fas fa-briefcase"></i></div>
      <div class="network-node"><i class="fas fa-plane"></i></div>
      <div class="network-lines">
        <div class="network-line" style="width: 40%; top: 50%; left: 50%; transform: rotate(45deg);"></div>
        <div class="network-line" style="width: 40%; top: 50%; left: 50%; transform: rotate(135deg);"></div>
        <div class="network-line" style="width: 40%; top: 50%; left: 50%; transform: rotate(-45deg);"></div>
        <div class="network-line" style="width: 40%; top: 50%; left: 50%; transform: rotate(-135deg);"></div>
      </div>
    </div>
  </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('stats_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('stats_subtitle'); ?></p>
    </div>
    
    <div class="stats-grid">
      <div class="stat-card fade-in">
        <div class="stat-number"><i class="fas fa-handshake"></i>200+</div>
        <h3><?php echo pt('stat1_title'); ?></h3>
        <p><?php echo pt('stat1_desc'); ?></p>
      </div>
      
      <div class="stat-card fade-in">
        <div class="stat-number"><i class="fas fa-globe"></i>50+</div>
        <h3><?php echo pt('stat2_title'); ?></h3>
        <p><?php echo pt('stat2_desc'); ?></p>
      </div>
      
      <div class="stat-card fade-in">
        <div class="stat-number"><i class="fas fa-users"></i>5,000+</div>
        <h3><?php echo pt('stat3_title'); ?></h3>
        <p><?php echo pt('stat3_desc'); ?></p>
      </div>
      
      <div class="stat-card fade-in">
        <div class="stat-number"><i class="fas fa-calendar-alt"></i>12+</div>
        <h3><?php echo pt('stat4_title'); ?></h3>
        <p><?php echo pt('stat4_desc'); ?></p>
      </div>
    </div>
  </div>
</section>

<!-- Categories Section -->
<section class="categories-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('categories_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('categories_subtitle'); ?></p>
    </div>
    
    <div class="categories-grid">
      <?php foreach($partnership_categories as $category): ?>
      <div class="category-card fade-in">
        <div class="category-icon">
          <i class="<?php echo $category['icon']; ?>"></i>
        </div>
        <h3><?php echo $category['title']; ?></h3>
        <p><?php echo $category['description']; ?></p>
        <span class="category-count"><?php echo $category['count']; ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Featured Partners -->
<section class="featured-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('featured_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('featured_subtitle'); ?></p>
    </div>
    
    <div class="featured-grid">
      <?php foreach($featured_partners as $partner): ?>
      <div class="partner-card fade-in">
        <div class="partner-header">
          <div class="partner-logo">
            <i class="<?php echo $partner['logo_icon']; ?>"></i>
          </div>
          <div class="partner-info">
            <h3><?php echo $partner['name']; ?></h3>
            <p class="partner-type"><?php echo $partner['type']; ?></p>
            <p class="partner-since">
              <i class="fas fa-calendar"></i>
              Partner since <?php echo $partner['since']; ?>
            </p>
          </div>
        </div>
        
        <p class="partner-description"><?php echo $partner['description']; ?></p>
        
        <ul class="partner-benefits">
          <?php foreach($partner['benefits'] as $benefit): ?>
          <li><?php echo $benefit; ?></li>
          <?php endforeach; ?>
        </ul>
        
        <div class="partner-action">
          <button class="partner-button">
            <i class="fas fa-external-link-alt"></i>
            <?php echo $current_lang === 'en' ? 'Learn More' : 'En Savoir Plus'; ?>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Benefits Section -->
<section class="benefits-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('benefits_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('benefits_subtitle'); ?></p>
    </div>
    
    <div class="benefits-grid">
      <div class="benefit-card fade-in">
        <div class="benefit-icon">
          <i class="fas fa-bolt"></i>
        </div>
        <h4><?php echo pt('benefit1_title'); ?></h4>
        <p><?php echo pt('benefit1_desc'); ?></p>
      </div>
      
      <div class="benefit-card fade-in">
        <div class="benefit-icon">
          <i class="fas fa-tags"></i>
        </div>
        <h4><?php echo pt('benefit2_title'); ?></h4>
        <p><?php echo pt('benefit2_desc'); ?></p>
      </div>
      
      <div class="benefit-card fade-in">
        <div class="benefit-icon">
          <i class="fas fa-stream"></i>
        </div>
        <h4><?php echo pt('benefit3_title'); ?></h4>
        <p><?php echo pt('benefit3_desc'); ?></p>
      </div>
      
      <div class="benefit-card fade-in">
        <div class="benefit-icon">
          <i class="fas fa-headset"></i>
        </div>
        <h4><?php echo pt('benefit4_title'); ?></h4>
        <p><?php echo pt('benefit4_desc'); ?></p>
      </div>
      
      <div class="benefit-card fade-in">
        <div class="benefit-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <h4><?php echo pt('benefit5_title'); ?></h4>
        <p><?php echo pt('benefit5_desc'); ?></p>
      </div>
      
      <div class="benefit-card fade-in">
        <div class="benefit-icon">
          <i class="fas fa-rocket"></i>
        </div>
        <h4><?php echo pt('benefit6_title'); ?></h4>
        <p><?php echo pt('benefit6_desc'); ?></p>
      </div>
    </div>
  </div>
</section>

<!-- Models Section -->
<section class="models-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('models_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('models_subtitle'); ?></p>
    </div>
    
    <div class="models-grid">
      <?php foreach($partnership_models as $model): ?>
      <div class="model-card fade-in">
        <div class="model-header">
          <div class="model-icon">
            <i class="<?php echo $model['icon']; ?>"></i>
          </div>
          <div>
            <h3><?php echo pt($model['title_key']); ?></h3>
            <p class="model-description"><?php echo pt($model['description_key']); ?></p>
          </div>
        </div>
        
        <ul class="model-examples">
          <?php foreach($model['examples'] as $example): ?>
          <li><?php echo $example; ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Success Stories -->
<section class="success-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('success_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('success_subtitle'); ?></p>
    </div>
    
    <div class="success-grid">
      <?php foreach($success_stories as $story): ?>
      <div class="success-card fade-in">
        <h3><?php echo pt($story['title_key']); ?></h3>
        <p class="success-description"><?php echo pt($story['description_key']); ?></p>
        
        <div class="success-impact">
          <?php foreach($story['impact'] as $impact): ?>
          <span class="impact-tag"><?php echo $impact; ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Certifications -->
<section class="certifications-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('certifications_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('certifications_subtitle'); ?></p>
    </div>
    
    <div class="certifications-grid">
      <?php foreach($certifications as $cert): ?>
      <div class="certification-item fade-in">
        <div class="certification-icon">
          <i class="<?php echo $cert['icon']; ?>"></i>
        </div>
        <h5><?php echo $cert['name']; ?></h5>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Process Section -->
<section class="process-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title" style="color: white;"><?php echo pt('process_title'); ?></h2>
      <p class="section-description" style="color: rgba(255, 255, 255, 0.9);"><?php echo pt('process_subtitle'); ?></p>
    </div>
    
    <div class="process-timeline">
      <div class="process-step fade-in">
        <div class="step-number">1</div>
        <div class="step-content">
          <h4><?php echo pt('process_step1'); ?></h4>
        </div>
      </div>
      
      <div class="process-step fade-in">
        <div class="step-number">2</div>
        <div class="step-content">
          <h4><?php echo pt('process_step2'); ?></h4>
        </div>
      </div>
      
      <div class="process-step fade-in">
        <div class="step-number">3</div>
        <div class="step-content">
          <h4><?php echo pt('process_step3'); ?></h4>
        </div>
      </div>
      
      <div class="process-step fade-in">
        <div class="step-number">4</div>
        <div class="step-content">
          <h4><?php echo pt('process_step4'); ?></h4>
        </div>
      </div>
      
      <div class="process-step fade-in">
        <div class="step-number">5</div>
        <div class="step-content">
          <h4><?php echo pt('process_step5'); ?></h4>
        </div>
      </div>
      
      <div class="process-step fade-in">
        <div class="step-number">6</div>
        <div class="step-content">
          <h4><?php echo pt('process_step6'); ?></h4>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Values Section -->
<section class="values-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('values_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('values_subtitle'); ?></p>
    </div>
    
    <div class="values-grid">
      <?php foreach($partnership_values as $value): ?>
      <div class="value-item fade-in">
        <div class="value-icon">
          <i class="<?php echo $value['icon']; ?>"></i>
        </div>
        <h4><?php echo pt($value['title_key']); ?></h4>
        <p>
          <?php 
          $desc_key = str_replace('title', 'desc', $value['title_key']);
          echo pt($desc_key);
          ?>
        </p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Inquiry Section -->
<section class="inquiry-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('inquiry_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('inquiry_subtitle'); ?></p>
    </div>
    
    <div class="inquiry-content">
      <p class="section-description"><?php echo pt('inquiry_description'); ?></p>
      
      <div class="inquiry-box fade-in">
        <h3><?php echo $current_lang === 'en' ? 'Why Partner With Us?' : 'Pourquoi Partenariat Avec Nous?'; ?></h3>
        <p><?php echo $current_lang === 'en' ? 'Join our network of trusted partners and benefit from:' : 'Rejoignez notre réseau de partenaires de confiance et bénéficiez de:'; ?></p>
        
        <div class="inquiry-features">
          <div class="inquiry-feature">
            <i class="fas fa-users"></i>
            <p><?php echo $current_lang === 'en' ? 'Access to 5,000+ Students' : 'Accès à 5 000+ Étudiants'; ?></p>
          </div>
          <div class="inquiry-feature">
            <i class="fas fa-globe"></i>
            <p><?php echo $current_lang === 'en' ? 'Global Reach & Exposure' : 'Portée Mondiale & Exposition'; ?></p>
          </div>
          <div class="inquiry-feature">
            <i class="fas fa-chart-line"></i>
            <p><?php echo $current_lang === 'en' ? 'Business Growth Opportunities' : 'Opportunités de Croissance'; ?></p>
          </div>
          <div class="inquiry-feature">
            <i class="fas fa-handshake"></i>
            <p><?php echo $current_lang === 'en' ? 'Trusted Brand Association' : 'Association de Marque de Confiance'; ?></p>
          </div>
        </div>
        
        <button class="cta-button-primary" onclick="window.location.href='become-partner.php'">
          <i class="fas fa-paper-plane"></i>
          <?php echo $current_lang === 'en' ? 'Submit Partnership Inquiry' : 'Soumettre Demande Partenariat'; ?>
        </button>
      </div>
    </div>
  </div>
</section>

<!-- CTA Section -->
<section class="partners-cta">
  <div class="cta-content">
    <h2 class="fade-in"><?php echo pt('cta_title'); ?></h2>
    <p class="fade-in"><?php echo pt('cta_description'); ?></p>
    <div class="cta-buttons">
      <button class="cta-button cta-button-primary fade-in" onclick="window.location.href='become-partner.php'">
        <i class="fas fa-handshake"></i>
        <?php echo pt('cta_button'); ?>
      </button>
      <button class="cta-button cta-button-secondary fade-in" onclick="window.open('partnership-brochure.pdf', '_blank')">
        <i class="fas fa-download"></i>
        <?php echo pt('cta_button2'); ?>
      </button>
    </div>
  </div>
</section>

<?php include 'footer.php'; ?>

<script>
(function() {
  'use strict';
  
  // Animation on scroll
  function animateOnScroll() {
    const elements = document.querySelectorAll('.fade-in');
    elements.forEach(el => {
      const rect = el.getBoundingClientRect();
      if (rect.top <= window.innerHeight * 0.85) {
        el.style.animationPlayState = 'running';
      }
    });
  }

  window.addEventListener('scroll', animateOnScroll);
  window.addEventListener('load', animateOnScroll);

  // Network node click handlers
  document.querySelectorAll('.network-node').forEach((node, index) => {
    node.addEventListener('click', function() {
      const categories = [
        'Educational Institutions',
        'Financial Partners', 
        'Immigration Services',
        'Accommodation Providers',
        'Career Development',
        'Travel & Logistics'
      ];
      
      if (categories[index]) {
        window.location.href = `partners-by-category.php?category=${encodeURIComponent(categories[index])}`;
      }
    });
  });

  // Partner card click handlers
  document.querySelectorAll('.partner-card').forEach(card => {
    card.addEventListener('click', function(e) {
      // Don't trigger if clicking the button
      if (!e.target.closest('.partner-button')) {
        const partnerName = this.querySelector('h3')?.textContent;
        if (partnerName) {
          window.location.href = `partner-details.php?name=${encodeURIComponent(partnerName)}`;
        }
      }
    });
    
    // Make partner cards clickable (except buttons)
    card.style.cursor = 'pointer';
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

  // Add hover effects to process steps
  document.querySelectorAll('.process-step').forEach(step => {
    step.addEventListener('mouseenter', function() {
      const number = this.querySelector('.step-number');
      if (number) {
        number.style.transform = 'scale(1.1)';
      }
    });
    
    step.addEventListener('mouseleave', function() {
      const number = this.querySelector('.step-number');
      if (number) {
        number.style.transform = 'scale(1)';
      }
    });
  });

  // Add parallax effect to hero
  window.addEventListener('scroll', function() {
    const scrolled = window.pageYOffset;
    const hero = document.querySelector('.partners-hero');
    if (hero) {
      const rate = scrolled * 0.5;
      hero.style.backgroundPositionY = rate + 'px';
    }
  });

  // Add staggered animation to network nodes
  document.querySelectorAll('.network-node').forEach((node, index) => {
    node.style.animationDelay = `${index * 0.2}s`;
  });

  // Add interactive network lines
  function animateNetworkLines() {
    const lines = document.querySelectorAll('.network-line');
    lines.forEach((line, index) => {
      line.style.transform = `rotate(${45 + index * 90}deg)`;
      line.style.width = '40%';
      line.style.opacity = '0.3';
    });
  }

  // Initialize network animation
  animateNetworkLines();

})();
</script>

</body>
</html>