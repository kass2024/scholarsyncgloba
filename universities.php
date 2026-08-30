<?php
// ============================================
// INCLUDE HEADER FOR LANGUAGE SWITCHING LOGIC
// ============================================
include 'header.php';

// Set page title with language switching
$pageTitle = $current_lang === 'en' ? 'Universities - ScholarSync Global' : 'Universités - ScholarSync Global';

// ============================================
// TRANSLATIONS FOR UNIVERSITIES PAGE
// ============================================

$universities_translations = [
    'en' => [
        // Hero Section
        'hero_title' => 'Partner Universities Worldwide',
        'hero_subtitle' => 'Access to elite education across 50+ countries',
        'hero_description' => 'Direct partnerships with top-ranked universities providing exclusive opportunities for international students.',
        
        // Stats
        'stats_universities' => 'Partner Universities',
        'stats_countries' => 'Countries Covered',
        'stats_students' => 'Students Placed',
        'stats_success' => 'Admission Rate',
        
        // Featured Universities
        'featured_title' => 'Top Partner Universities',
        'featured_subtitle' => 'World-renowned institutions in our network',
        
        // Regions
        'regions_title' => 'Global Regions Covered',
        'regions_subtitle' => 'Comprehensive coverage across all continents',
        
        'region1_title' => 'North America',
        'region1_desc' => 'USA & Canada - Top research universities and colleges',
        'region1_count' => '120+ Universities',
        
        'region2_title' => 'Europe',
        'region2_desc' => 'UK, Germany, France, Netherlands, and more',
        'region2_count' => '180+ Universities',
        
        'region3_title' => 'Asia Pacific',
        'region3_desc' => 'Australia, New Zealand, Singapore, Japan',
        'region3_count' => '90+ Universities',
        
        'region4_title' => 'Middle East',
        'region4_desc' => 'UAE, Qatar, Saudi Arabia, Turkey',
        'region4_count' => '40+ Universities',
        
        'region5_title' => 'Africa',
        'region5_desc' => 'South Africa, Kenya, Egypt, Morocco',
        'region5_count' => '30+ Universities',
        
        'region6_title' => 'Latin America',
        'region6_desc' => 'Mexico, Brazil, Argentina, Chile',
        'region6_count' => '25+ Universities',
        
        // University Rankings
        'rankings_title' => 'University Rankings',
        'rankings_subtitle' => 'Partner institutions by global standing',
        
        // University Categories
        'categories_title' => 'University Categories',
        'categories_subtitle' => 'Institutions for every academic profile',
        
        'category1_title' => 'Research Universities',
        'category1_desc' => 'World-class research institutions with PhD programs',
        'category1_count' => 'Top 500 Global',
        
        'category2_title' => 'Liberal Arts Colleges',
        'category2_desc' => 'Undergraduate-focused institutions with personalized education',
        'category2_count' => 'Small Class Sizes',
        
        'category3_title' => 'Technical Institutes',
        'category3_desc' => 'Specialized engineering and technology universities',
        'category3_count' => 'Industry Focused',
        
        'category4_title' => 'Business Schools',
        'category4_desc' => 'AACSB-accredited business and management schools',
        'category4_count' => 'Triple Crown',
        
        'category5_title' => 'Arts & Design Schools',
        'category5_desc' => 'Creative institutions for arts, design, and architecture',
        'category5_count' => 'Portfolio Based',
        
        'category6_title' => 'Medical Schools',
        'category6_desc' => 'Accredited medical and health sciences institutions',
        'category6_count' => 'Clinical Training',
        
        // Top Destinations
        'destinations_title' => 'Top Study Destinations',
        'destinations_subtitle' => 'Most popular countries for international students',
        
        // Partnership Benefits
        'benefits_title' => 'Partnership Benefits',
        'benefits_subtitle' => 'Advantages through our university partnerships',
        
        'benefit1_title' => 'Priority Application Review',
        'benefit1_desc' => 'Faster processing and dedicated admission officers',
        
        'benefit2_title' => 'Exclusive Scholarships',
        'benefit2_desc' => 'Special scholarships reserved for our students',
        
        'benefit3_title' => 'Direct University Contact',
        'benefit3_desc' => 'Direct communication with admission departments',
        
        'benefit4_title' => 'Application Fee Waivers',
        'benefit4_desc' => 'Waived application fees for eligible students',
        
        'benefit5_title' => 'Conditional Offers',
        'benefit5_desc' => 'Offers based on predicted grades',
        
        'benefit6_title' => 'Pathway Programs',
        'benefit6_desc' => 'Guanteed progression upon completion',
        
        // University List
        'list_title' => 'Select Partner Universities',
        'list_subtitle' => 'A sample of our extensive network',
        
        // Testimonials
        'testimonials_title' => 'Student Experiences',
        'testimonials_subtitle' => 'Success stories from our university placements',
        
        'testimonial1' => 'Got accepted into University of Toronto with a 70% scholarship through ScholarSync Global\'s direct partnership.',
        'testimonial1_name' => 'Sarah Chen',
        'testimonial1_university' => 'University of Toronto, Canada',
        
        'testimonial2' => 'Direct pathway to Imperial College London through the foundation program partnership.',
        'testimonial2_name' => 'James Wilson',
        'testimonial2_university' => 'Imperial College London, UK',
        
        'testimonial3' => 'Special industry scholarship from Technical University of Munich secured through ScholarSync Global.',
        'testimonial3_name' => 'Maria Rodriguez',
        'testimonial3_university' => 'TU Munich, Germany',
        
        // Application Support
        'support_title' => 'University Application Support',
        'support_subtitle' => 'How we help you succeed',
        
        'support_step1' => 'University Selection',
        'support_step2' => 'Document Preparation',
        'support_step3' => 'Application Submission',
        'support_step4' => 'Interview Preparation',
        'support_step5' => 'Offer Management',
        'support_step6' => 'Visa Assistance',
        
        // CTA
        'cta_title' => 'Find Your Dream University',
        'cta_description' => 'Get personalized university recommendations based on your profile',
        'cta_button' => 'Get University Match',
        'cta_button2' => 'Download University Guide',
        
        // Page Metadata
        'page_description' => 'Partner universities at ScholarSync Global - access to top-ranked institutions worldwide with exclusive admission opportunities.',
        'page_title' => 'Partner Universities - ScholarSync Global',
    ],
    
    'fr' => [
        // Hero Section
        'hero_title' => 'Universités Partenaires Mondiales',
        'hero_subtitle' => 'Accès à l\'éducation d\'élite dans 50+ pays',
        'hero_description' => 'Partenariats directs avec les meilleures universités offrant des opportunités exclusives.',
        
        // Stats
        'stats_universities' => 'Universités Partenaires',
        'stats_countries' => 'Pays Couverts',
        'stats_students' => 'Étudiants Placés',
        'stats_success' => 'Taux d\'Admission',
        
        // Featured Universities
        'featured_title' => 'Top Universités Partenaires',
        'featured_subtitle' => 'Institutions de renommée mondiale dans notre réseau',
        
        // Regions
        'regions_title' => 'Régions Mondiales Couvertes',
        'regions_subtitle' => 'Couverture complète sur tous continents',
        
        'region1_title' => 'Amérique du Nord',
        'region1_desc' => 'USA & Canada - Universités de recherche de premier plan',
        'region1_count' => '120+ Universités',
        
        'region2_title' => 'Europe',
        'region2_desc' => 'Royaume-Uni, Allemagne, France, Pays-Bas',
        'region2_count' => '180+ Universités',
        
        'region3_title' => 'Asie Pacifique',
        'region3_desc' => 'Australie, Nouvelle-Zélande, Singapour, Japon',
        'region3_count' => '90+ Universités',
        
        'region4_title' => 'Moyen-Orient',
        'region4_desc' => 'Émirats Arabes Unis, Qatar, Arabie Saoudite, Turquie',
        'region4_count' => '40+ Universités',
        
        'region5_title' => 'Afrique',
        'region5_desc' => 'Afrique du Sud, Kenya, Égypte, Maroc',
        'region5_count' => '30+ Universités',
        
        'region6_title' => 'Amérique Latine',
        'region6_desc' => 'Mexique, Brésil, Argentine, Chili',
        'region6_count' => '25+ Universités',
        
        // University Rankings
        'rankings_title' => 'Classements Universitaires',
        'rankings_subtitle' => 'Institutions partenaires par classement mondial',
        
        // University Categories
        'categories_title' => 'Catégories d\'Universités',
        'categories_subtitle' => 'Institutions pour chaque profil académique',
        
        'category1_title' => 'Universités de Recherche',
        'category1_desc' => 'Institutions de recherche de classe mondiale avec programmes doctoraux',
        'category1_count' => 'Top 500 Mondial',
        
        'category2_title' => 'Collèges d\'Arts Libéraux',
        'category2_desc' => 'Institutions de premier cycle avec éducation personnalisée',
        'category2_count' => 'Petites Classes',
        
        'category3_title' => 'Instituts Techniques',
        'category3_desc' => 'Universités spécialisées en ingénierie et technologie',
        'category3_count' => 'Focus Industrie',
        
        'category4_title' => 'Écoles de Commerce',
        'category4_desc' => 'Écoles de commerce accréditées AACSB',
        'category4_count' => 'Triple Couronne',
        
        'category5_title' => 'Écoles d\'Art & Design',
        'category5_desc' => 'Institutions créatives pour arts, design et architecture',
        'category5_count' => 'Basé Portfolio',
        
        'category6_title' => 'Écoles de Médecine',
        'category6_desc' => 'Institutions médicales et sciences de la santé accréditées',
        'category6_count' => 'Formation Clinique',
        
        // Top Destinations
        'destinations_title' => 'Top Destinations d\'Études',
        'destinations_subtitle' => 'Pays les plus populaires pour étudiants internationaux',
        
        // Partnership Benefits
        'benefits_title' => 'Avantages des Partenariats',
        'benefits_subtitle' => 'Avantages grâce à nos partenariats universitaires',
        
        'benefit1_title' => 'Revue de Candidature Prioritaire',
        'benefit1_desc' => 'Traitement plus rapide et responsables admissions dédiés',
        
        'benefit2_title' => 'Bourses Exclusives',
        'benefit2_desc' => 'Bourses spéciales réservées à nos étudiants',
        
        'benefit3_title' => 'Contact Direct Université',
        'benefit3_desc' => 'Communication directe avec départements admissions',
        
        'benefit4_title' => 'Exemptions Frais Candidature',
        'benefit4_desc' => 'Frais de candidature annulés pour étudiants éligibles',
        
        'benefit5_title' => 'Offres Conditionnelles',
        'benefit5_desc' => 'Offres basées sur notes prédites',
        
        'benefit6_title' => 'Programmes Passerelles',
        'benefit6_desc' => 'Progression garantie après achèvement',
        
        // University List
        'list_title' => 'Universités Partenaires Sélectionnées',
        'list_subtitle' => 'Un échantillon de notre réseau étendu',
        
        // Testimonials
        'testimonials_title' => 'Expériences des Étudiants',
        'testimonials_subtitle' => 'Histoires de réussite de nos placements universitaires',
        
        'testimonial1' => 'Accepté à l\'Université de Toronto avec bourse 70% grâce au partenariat direct de ScholarSync Global.',
        'testimonial1_name' => 'Sarah Chen',
        'testimonial1_university' => 'Université de Toronto, Canada',
        
        'testimonial2' => 'Chemin direct vers Imperial College London via le programme fondation partenaire.',
        'testimonial2_name' => 'James Wilson',
        'testimonial2_university' => 'Imperial College London, Royaume-Uni',
        
        'testimonial3' => 'Bourse industrie spéciale de TU Munich obtenue via ScholarSync Global.',
        'testimonial3_name' => 'Maria Rodriguez',
        'testimonial3_university' => 'TU Munich, Allemagne',
        
        // Application Support
        'support_title' => 'Support Candidature Universitaire',
        'support_subtitle' => 'Comment nous vous aidons à réussir',
        
        'support_step1' => 'Sélection Université',
        'support_step2' => 'Préparation Documents',
        'support_step3' => 'Soumission Candidature',
        'support_step4' => 'Préparation Entretien',
        'support_step5' => 'Gestion Offres',
        'support_step6' => 'Assistance Visa',
        
        // CTA
        'cta_title' => 'Trouvez Votre Université de Rêve',
        'cta_description' => 'Obtenez des recommandations universitaires personnalisées basées sur votre profil',
        'cta_button' => 'Obtenir Match Université',
        'cta_button2' => 'Télécharger Guide Universités',
        
        // Page Metadata
        'page_description' => 'Universités partenaires de ScholarSync Global - accès aux meilleures institutions mondiales avec opportunités d\'admission exclusives.',
        'page_title' => 'Universités Partenaires - ScholarSync Global',
    ]
];

// Function to get universities page translation
function ut($key) {
    global $universities_translations, $current_lang;
    
    // Fallback to English if key missing
    if (isset($universities_translations[$current_lang][$key])) {
        return $universities_translations[$current_lang][$key];
    } elseif (isset($universities_translations['en'][$key])) {
        return $universities_translations['en'][$key];
    }
    
    return $key; // Return key itself as last resort
}

// Define featured universities
$featured_universities = [
    [
        'name' => 'University of Toronto',
        'country' => 'Canada',
        'flag' => '🇨🇦',
        'rank' => '#1 in Canada',
        'qs_rank' => 'QS World #21',
        'specializations' => ['Engineering', 'Medicine', 'Business', 'Computer Science'],
        'type' => 'Public Research University',
        'students' => '93,000+',
        'scholarship' => 'Available'
    ],
    [
        'name' => 'Imperial College London',
        'country' => 'United Kingdom',
        'flag' => '🇬🇧',
        'rank' => 'Top 10 Worldwide',
        'qs_rank' => 'QS World #6',
        'specializations' => ['Science', 'Engineering', 'Medicine', 'Business'],
        'type' => 'Public Research University',
        'students' => '19,000+',
        'scholarship' => 'Available'
    ],
    [
        'name' => 'University of Sydney',
        'country' => 'Australia',
        'flag' => '🇦🇺',
        'rank' => '#1 in Australia',
        'qs_rank' => 'QS World #19',
        'specializations' => ['Arts', 'Science', 'Engineering', 'Law'],
        'type' => 'Public Research University',
        'students' => '73,000+',
        'scholarship' => 'Available'
    ]
];

// Define regions
$regions = [
    [
        'name_key' => 'region1_title',
        'icon' => 'fas fa-flag-usa',
        'description_key' => 'region1_desc',
        'count_key' => 'region1_count',
        'countries' => ['USA', 'Canada'],
        'color' => '#427431'
    ],
    [
        'name_key' => 'region2_title',
        'icon' => 'fas fa-landmark',
        'description_key' => 'region2_desc',
        'count_key' => 'region2_count',
        'countries' => ['UK', 'Germany', 'France', 'Netherlands', 'Switzerland'],
        'color' => '#3661B9'
    ],
    [
        'name_key' => 'region3_title',
        'icon' => 'fas fa-globe-asia',
        'description_key' => 'region3_desc',
        'count_key' => 'region3_count',
        'countries' => ['Australia', 'New Zealand', 'Singapore', 'Japan', 'Hong Kong'],
        'color' => '#2f5a26'
    ],
    [
        'name_key' => 'region4_title',
        'icon' => 'fas fa-mosque',
        'description_key' => 'region4_desc',
        'count_key' => 'region4_count',
        'countries' => ['UAE', 'Qatar', 'Saudi Arabia', 'Turkey', 'Oman'],
        'color' => '#427431'
    ],
    [
        'name_key' => 'region5_title',
        'icon' => 'fas fa-globe-africa',
        'description_key' => 'region5_desc',
        'count_key' => 'region5_count',
        'countries' => ['South Africa', 'Kenya', 'Egypt', 'Morocco', 'Ghana'],
        'color' => '#3661B9'
    ],
    [
        'name_key' => 'region6_title',
        'icon' => 'fas fa-globe-americas',
        'description_key' => 'region6_desc',
        'count_key' => 'region6_count',
        'countries' => ['Mexico', 'Brazil', 'Argentina', 'Chile', 'Colombia'],
        'color' => '#2f5a26'
    ]
];

// Define university categories
$categories = [
    [
        'name_key' => 'category1_title',
        'icon' => 'fas fa-flask',
        'description_key' => 'category1_desc',
        'count_key' => 'category1_count'
    ],
    [
        'name_key' => 'category2_title',
        'icon' => 'fas fa-book-open',
        'description_key' => 'category2_desc',
        'count_key' => 'category2_count'
    ],
    [
        'name_key' => 'category3_title',
        'icon' => 'fas fa-cogs',
        'description_key' => 'category3_desc',
        'count_key' => 'category3_count'
    ],
    [
        'name_key' => 'category4_title',
        'icon' => 'fas fa-chart-line',
        'description_key' => 'category4_desc',
        'count_key' => 'category4_count'
    ],
    [
        'name_key' => 'category5_title',
        'icon' => 'fas fa-palette',
        'description_key' => 'category5_desc',
        'count_key' => 'category5_count'
    ],
    [
        'name_key' => 'category6_title',
        'icon' => 'fas fa-heartbeat',
        'description_key' => 'category6_desc',
        'count_key' => 'category6_count'
    ]
];

// Define top destinations
$destinations = [
    ['country' => 'USA', 'flag' => '🇺🇸', 'universities' => '85+', 'rank' => '#1 Destination'],
    ['country' => 'Canada', 'flag' => '🇨🇦', 'universities' => '45+', 'rank' => 'Top for PR'],
    ['country' => 'UK', 'flag' => '🇬🇧', 'universities' => '60+', 'rank' => 'Historic Excellence'],
    ['country' => 'Australia', 'flag' => '🇦🇺', 'universities' => '35+', 'rank' => 'Quality Lifestyle'],
    ['country' => 'Germany', 'flag' => '🇩🇪', 'universities' => '40+', 'rank' => 'Tuition-Free'],
    ['country' => 'Netherlands', 'flag' => '🇳🇱', 'universities' => '25+', 'rank' => 'English Programs']
];

// Define university list
$university_list = [
    ['name' => 'Harvard University', 'country' => 'USA', 'rank' => 'QS #5', 'type' => 'Ivy League'],
    ['name' => 'Stanford University', 'country' => 'USA', 'rank' => 'QS #3', 'type' => 'Private Research'],
    ['name' => 'MIT', 'country' => 'USA', 'rank' => 'QS #1', 'type' => 'Private Research'],
    ['name' => 'University of Oxford', 'country' => 'UK', 'rank' => 'QS #4', 'type' => 'Public Research'],
    ['name' => 'University of Cambridge', 'country' => 'UK', 'rank' => 'QS #2', 'type' => 'Public Research'],
    ['name' => 'ETH Zurich', 'country' => 'Switzerland', 'rank' => 'QS #9', 'type' => 'Public Research'],
    ['name' => 'National University of Singapore', 'country' => 'Singapore', 'rank' => 'QS #8', 'type' => 'Public Research'],
    ['name' => 'University of Tokyo', 'country' => 'Japan', 'rank' => 'QS #28', 'type' => 'Public Research'],
    ['name' => 'University of Melbourne', 'country' => 'Australia', 'rank' => 'QS #14', 'type' => 'Public Research'],
    ['name' => 'University of British Columbia', 'country' => 'Canada', 'rank' => 'QS #47', 'type' => 'Public Research'],
    ['name' => 'University of Amsterdam', 'country' => 'Netherlands', 'rank' => 'QS #58', 'type' => 'Public Research'],
    ['name' => 'University of Cape Town', 'country' => 'South Africa', 'rank' => 'QS #237', 'type' => 'Public Research']
];

// Define benefits
$benefits = [
    ['title_key' => 'benefit1_title', 'icon' => 'fas fa-bolt'],
    ['title_key' => 'benefit2_title', 'icon' => 'fas fa-award'],
    ['title_key' => 'benefit3_title', 'icon' => 'fas fa-phone-alt'],
    ['title_key' => 'benefit4_title', 'icon' => 'fas fa-money-bill-wave'],
    ['title_key' => 'benefit5_title', 'icon' => 'fas fa-file-contract'],
    ['title_key' => 'benefit6_title', 'icon' => 'fas fa-road']
];

// Define testimonials
$testimonials = [
    ['key' => 'testimonial1', 'initial' => 'SC'],
    ['key' => 'testimonial2', 'initial' => 'JW'],
    ['key' => 'testimonial3', 'initial' => 'MR']
];
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo ut('page_description'); ?>">
<title><?php echo ut('page_title'); ?></title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ============================================
   UNIVERSITIES PAGE STYLES
   Academic, prestigious design showcasing elite institutions
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
.universities-hero {
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
    url('data:image/svg+xml,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M20,20 L80,20 L80,80 L20,80 Z" fill="none" stroke="white" stroke-width="0.5" stroke-opacity="0.05"/></svg>');
  background-size: 100px;
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

/* ===== STATS SECTION ===== */
.stats-section {
  background: linear-gradient(135deg, var(--bg) 0%, var(--pure-white) 100%);
  padding: 60px 20px;
  border-bottom: 1px solid var(--border);
}

.stats-grid {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 30px;
  text-align: center;
}

.stat-item {
  padding: 30px 20px;
}

.stat-number {
  font-size: 3.2rem;
  font-weight: 800;
  margin-bottom: 10px;
  color: var(--primary-navy);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.stat-number i {
  font-size: 2.2rem;
  color: var(--accent-teal);
}

.stat-label {
  font-size: 1.1rem;
  font-weight: 500;
  color: var(--text-light);
  letter-spacing: 0.5px;
}

/* ===== FEATURED UNIVERSITIES ===== */
.featured-section {
  background: white;
}

.featured-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.featured-card {
  background: var(--bg);
  padding: 40px 35px;
  border-radius: 20px;
  border: 1px solid var(--border-light);
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.featured-card:hover {
  transform: translateY(-10px);
  box-shadow: var(--shadow-lg);
}

.featured-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--accent-gold), var(--accent-teal));
}

.featured-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 25px;
}

.university-flag {
  font-size: 3.5rem;
  flex-shrink: 0;
}

.university-info h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 5px;
}

.university-location {
  color: var(--text-light);
  font-size: 1rem;
  display: flex;
  align-items: center;
  gap: 8px;
}

.rankings {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  margin-bottom: 25px;
}

.rank-badge {
  background: linear-gradient(135deg, var(--primary-light), var(--teal-light));
  color: var(--primary-navy);
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 600;
}

.rank-badge.primary {
  background: linear-gradient(135deg, var(--accent-gold), var(--accent-teal));
  color: white;
}

.university-details {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
  margin-bottom: 25px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.detail-label {
  font-size: 0.85rem;
  color: var(--text-light);
  font-weight: 500;
}

.detail-value {
  font-size: 1rem;
  font-weight: 600;
  color: var(--primary-navy);
}

.specializations {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 25px;
}

.specialization-tag {
  background: var(--primary-light);
  color: var(--primary-navy);
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 500;
}

.scholarship-badge {
  background: linear-gradient(135deg, var(--accent-gold), var(--accent-teal));
  color: white;
  padding: 10px 20px;
  border-radius: 20px;
  font-size: 0.95rem;
  font-weight: 600;
  display: inline-block;
  text-align: center;
  width: 100%;
}

/* ===== REGIONS SECTION ===== */
.regions-section {
  background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
}

.regions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.region-card {
  background: white;
  padding: 40px 35px;
  border-radius: 20px;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-light);
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.region-card:hover {
  transform: translateY(-10px);
  box-shadow: var(--shadow-lg);
}

.region-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 6px;
  height: 100%;
  background: linear-gradient(to bottom, var(--primary-navy), var(--accent-teal));
}

.region-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 20px;
}

.region-icon {
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

.region-card:hover .region-icon {
  transform: rotate(10deg) scale(1.1);
}

.region-card h3 {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 5px;
}

.region-desc {
  color: var(--text-light);
  line-height: 1.6;
  margin-bottom: 20px;
  font-size: 1rem;
}

.region-countries {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 20px;
}

.country-tag {
  background: var(--primary-light);
  color: var(--primary-navy);
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 500;
}

.region-stats {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 20px;
  border-top: 1px solid var(--border-light);
}

.region-count {
  background: linear-gradient(135deg, var(--accent-light), var(--teal-light));
  color: var(--primary-navy);
  padding: 8px 20px;
  border-radius: 20px;
  font-size: 0.95rem;
  font-weight: 600;
}

.region-link {
  color: var(--accent-teal);
  font-weight: 600;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: var(--transition);
}

.region-link:hover {
  color: var(--primary-navy);
  gap: 12px;
}

/* ===== CATEGORIES SECTION ===== */
.categories-section {
  background: white;
}

.categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.category-card {
  background: var(--bg);
  padding: 40px 35px;
  border-radius: 20px;
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

/* ===== DESTINATIONS SECTION ===== */
.destinations-section {
  background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
}

.destinations-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 25px;
  margin-top: 50px;
}

.destination-card {
  background: white;
  padding: 30px;
  border-radius: 16px;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-light);
  transition: var(--transition);
  display: flex;
  align-items: center;
  gap: 20px;
}

.destination-card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-md);
}

.destination-flag {
  font-size: 3rem;
  flex-shrink: 0;
}

.destination-info h4 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 8px;
}

.destination-universities {
  color: var(--accent-gold);
  font-weight: 600;
  font-size: 0.95rem;
  margin-bottom: 8px;
}

.destination-rank {
  color: var(--text-light);
  font-size: 0.9rem;
  line-height: 1.5;
}

/* ===== UNIVERSITY LIST SECTION ===== */
.list-section {
  background: white;
}

.list-container {
  max-width: 1000px;
  margin: 0 auto;
}

.university-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 30px;
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}

.university-table thead {
  background: linear-gradient(135deg, var(--primary-navy), var(--secondary-blue));
  color: white;
}

.university-table th {
  padding: 20px;
  text-align: left;
  font-weight: 600;
  font-size: 1rem;
}

.university-table tbody tr {
  border-bottom: 1px solid var(--border-light);
  transition: var(--transition);
}

.university-table tbody tr:hover {
  background: var(--primary-light);
}

.university-table td {
  padding: 20px;
  color: var(--text);
}

.university-name {
  font-weight: 600;
  color: var(--primary-navy);
}

.university-country {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-light);
}

.country-flag {
  font-size: 1.2rem;
}

.university-rank {
  background: var(--primary-light);
  color: var(--primary-navy);
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 500;
  display: inline-block;
}

.university-type {
  color: var(--text-light);
  font-size: 0.9rem;
}

.table-actions {
  text-align: center;
  margin-top: 40px;
}

.view-all-btn {
  padding: 14px 36px;
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

.view-all-btn:hover {
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
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--accent-gold), var(--accent-teal));
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

/* ===== TESTIMONIALS SECTION ===== */
.testimonials-section {
  background: white;
}

.testimonials-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.testimonial-card {
  background: var(--bg);
  padding: 40px 35px;
  border-radius: 20px;
  border: 1px solid var(--border-light);
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.testimonial-card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-lg);
}

.testimonial-card::before {
  content: '"';
  position: absolute;
  top: 20px;
  left: 25px;
  font-size: 100px;
  color: rgba(1, 47, 107, 0.1);
  font-family: serif;
  line-height: 1;
  z-index: 0;
}

.testimonial-text {
  font-size: 1.1rem;
  line-height: 1.7;
  color: var(--text);
  margin-bottom: 30px;
  position: relative;
  z-index: 1;
  font-style: italic;
}

.testimonial-author {
  display: flex;
  align-items: center;
  gap: 15px;
}

.author-avatar {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, var(--accent-gold), var(--accent-teal));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.author-info h5 {
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 5px;
  font-size: 1.1rem;
}

.author-info p {
  color: var(--text-light);
  font-size: 0.9rem;
}

/* ===== SUPPORT SECTION ===== */
.support-section {
  background: linear-gradient(135deg, var(--primary-navy) 0%, var(--dark-blue) 100%);
  color: white;
}

.support-steps {
  max-width: 1000px;
  margin: 0 auto;
  position: relative;
}

.support-track {
  display: flex;
  justify-content: space-between;
  position: relative;
  margin-top: 60px;
}

.support-track::before {
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
  .support-track {
    flex-direction: column;
    gap: 40px;
  }
  
  .support-track::before {
    display: none;
  }
}

.support-step {
  text-align: center;
  position: relative;
  z-index: 2;
  flex: 1;
}

@media (max-width: 768px) {
  .support-step {
    display: flex;
    align-items: center;
    gap: 20px;
    text-align: left;
  }
}

.step-circle {
  width: 80px;
  height: 80px;
  margin: 0 auto 20px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
  font-weight: 700;
  color: white;
  border: 4px solid rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  transition: var(--transition);
}

@media (max-width: 768px) {
  .step-circle {
    margin: 0;
    flex-shrink: 0;
    width: 60px;
    height: 60px;
    font-size: 1.5rem;
  }
}

.support-step:hover .step-circle {
  background: linear-gradient(135deg, var(--accent-gold), var(--accent-teal));
  transform: scale(1.1);
}

.support-step h4 {
  font-size: 1.1rem;
  font-weight: 600;
  margin-bottom: 10px;
  color: white;
}

/* ===== CTA SECTION ===== */
.universities-cta {
  background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
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
  color: var(--primary-navy);
}

.cta-content p {
  font-size: 1.2rem;
  color: var(--text-light);
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
  background: linear-gradient(135deg, var(--primary-navy), var(--secondary-blue));
  color: white;
  box-shadow: 0 8px 25px rgba(1, 47, 107, 0.25);
}

.cta-button-primary:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 35px rgba(1, 47, 107, 0.35);
}

.cta-button-secondary {
  background: white;
  color: var(--primary-navy);
  border: 2px solid var(--border);
  box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.cta-button-secondary:hover {
  background: var(--primary-light);
  border-color: var(--primary-navy);
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
}

@media (max-width: 992px) {
  .section-title {
    font-size: 2.2rem;
  }
  
  .hero-content h1 {
    font-size: 2.5rem;
  }
  
  .regions-grid,
  .categories-grid {
    grid-template-columns: repeat(2, 1fr);
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
  
  .stat-number {
    font-size: 2.5rem;
  }
  
  .regions-grid,
  .categories-grid,
  .featured-grid,
  .testimonials-grid {
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
  .destinations-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .university-table {
    display: block;
    overflow-x: auto;
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
  }
  
  .benefits-grid,
  .destinations-grid {
    grid-template-columns: 1fr;
  }
  
  .featured-card,
  .region-card,
  .category-card {
    padding: 30px 25px;
  }
  
  .university-table th,
  .university-table td {
    padding: 15px 10px;
    font-size: 0.9rem;
  }
}
</style>
</head>
<body>

<!-- Hero Section -->
<section class="universities-hero">
  <div class="hero-bg-pattern"></div>
  <div class="hero-content">
    <h1 class="fade-in"><?php echo ut('hero_title'); ?></h1>
    <p class="hero-subtitle fade-in"><?php echo ut('hero_subtitle'); ?></p>
    <p class="hero-description fade-in"><?php echo ut('hero_description'); ?></p>
  </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
  <div class="container">
    <div class="stats-grid">
      <div class="stat-item fade-in">
        <div class="stat-number"><i class="fas fa-university"></i>500+</div>
        <div class="stat-label"><?php echo ut('stats_universities'); ?></div>
      </div>
      <div class="stat-item fade-in">
        <div class="stat-number"><i class="fas fa-globe"></i>50+</div>
        <div class="stat-label"><?php echo ut('stats_countries'); ?></div>
      </div>
      <div class="stat-item fade-in">
        <div class="stat-number"><i class="fas fa-user-graduate"></i>5,000+</div>
        <div class="stat-label"><?php echo ut('stats_students'); ?></div>
      </div>
      <div class="stat-item fade-in">
        <div class="stat-number"><i class="fas fa-chart-line"></i>95%</div>
        <div class="stat-label"><?php echo ut('stats_success'); ?></div>
      </div>
    </div>
  </div>
</section>

<!-- Featured Universities -->
<section class="featured-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo ut('featured_title'); ?></h2>
      <p class="section-subtitle"><?php echo ut('featured_subtitle'); ?></p>
    </div>
    
    <div class="featured-grid">
      <?php foreach($featured_universities as $university): ?>
      <div class="featured-card fade-in">
        <div class="featured-header">
          <div class="university-flag"><?php echo $university['flag']; ?></div>
          <div class="university-info">
            <h3><?php echo $university['name']; ?></h3>
            <p class="university-location">
              <i class="fas fa-map-marker-alt"></i>
              <?php echo $university['country']; ?>
            </p>
          </div>
        </div>
        
        <div class="rankings">
          <span class="rank-badge primary"><?php echo $university['rank']; ?></span>
          <span class="rank-badge"><?php echo $university['qs_rank']; ?></span>
        </div>
        
        <div class="university-details">
          <div class="detail-item">
            <span class="detail-label"><?php echo $current_lang === 'en' ? 'Type' : 'Type'; ?></span>
            <span class="detail-value"><?php echo $university['type']; ?></span>
          </div>
          <div class="detail-item">
            <span class="detail-label"><?php echo $current_lang === 'en' ? 'Students' : 'Étudiants'; ?></span>
            <span class="detail-value"><?php echo $university['students']; ?></span>
          </div>
        </div>
        
        <div class="specializations">
          <?php foreach($university['specializations'] as $spec): ?>
          <span class="specialization-tag"><?php echo $spec; ?></span>
          <?php endforeach; ?>
        </div>
        
        <span class="scholarship-badge">
          <i class="fas fa-award"></i>
          <?php echo $current_lang === 'en' ? 'Scholarships Available' : 'Bourses Disponibles'; ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Regions Section -->
<section class="regions-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo ut('regions_title'); ?></h2>
      <p class="section-subtitle"><?php echo ut('regions_subtitle'); ?></p>
    </div>
    
    <div class="regions-grid">
      <?php foreach($regions as $region): ?>
      <div class="region-card fade-in">
        <div class="region-header">
          <div class="region-icon">
            <i class="<?php echo $region['icon']; ?>"></i>
          </div>
          <div>
            <h3><?php echo ut($region['name_key']); ?></h3>
            <p class="region-desc"><?php echo ut($region['description_key']); ?></p>
          </div>
        </div>
        
        <div class="region-countries">
          <?php foreach($region['countries'] as $country): ?>
          <span class="country-tag"><?php echo $country; ?></span>
          <?php endforeach; ?>
        </div>
        
        <div class="region-stats">
          <span class="region-count"><?php echo ut($region['count_key']); ?></span>
          <a href="#" class="region-link">
            <?php echo $current_lang === 'en' ? 'View Universities' : 'Voir Universités'; ?>
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Categories Section -->
<section class="categories-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo ut('categories_title'); ?></h2>
      <p class="section-subtitle"><?php echo ut('categories_subtitle'); ?></p>
    </div>
    
    <div class="categories-grid">
      <?php foreach($categories as $category): ?>
      <div class="category-card fade-in">
        <div class="category-icon">
          <i class="<?php echo $category['icon']; ?>"></i>
        </div>
        <h3><?php echo ut($category['name_key']); ?></h3>
        <p><?php echo ut($category['description_key']); ?></p>
        <span class="category-count"><?php echo ut($category['count_key']); ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Destinations Section -->
<section class="destinations-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo ut('destinations_title'); ?></h2>
      <p class="section-subtitle"><?php echo ut('destinations_subtitle'); ?></p>
    </div>
    
    <div class="destinations-grid">
      <?php foreach($destinations as $destination): ?>
      <div class="destination-card fade-in">
        <div class="destination-flag"><?php echo $destination['flag']; ?></div>
        <div class="destination-info">
          <h4><?php echo $destination['country']; ?></h4>
          <p class="destination-universities"><?php echo $destination['universities']; ?> Universities</p>
          <p class="destination-rank"><?php echo $destination['rank']; ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- University List -->
<section class="list-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo ut('list_title'); ?></h2>
      <p class="section-subtitle"><?php echo ut('list_subtitle'); ?></p>
    </div>
    
    <div class="list-container">
      <table class="university-table">
        <thead>
          <tr>
            <th><?php echo $current_lang === 'en' ? 'University' : 'Université'; ?></th>
            <th><?php echo $current_lang === 'en' ? 'Country' : 'Pays'; ?></th>
            <th><?php echo $current_lang === 'en' ? 'Ranking' : 'Classement'; ?></th>
            <th><?php echo $current_lang === 'en' ? 'Type' : 'Type'; ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($university_list as $uni): ?>
          <tr class="fade-in">
            <td>
              <div class="university-name"><?php echo $uni['name']; ?></div>
            </td>
            <td>
              <div class="university-country">
                <span class="country-flag">
                  <?php 
                  $flags = [
                    'USA' => '🇺🇸',
                    'UK' => '🇬🇧',
                    'Canada' => '🇨🇦',
                    'Australia' => '🇦🇺',
                    'Germany' => '🇩🇪',
                    'Netherlands' => '🇳🇱',
                    'Switzerland' => '🇨🇭',
                    'Singapore' => '🇸🇬',
                    'Japan' => '🇯🇵',
                    'South Africa' => '🇿🇦'
                  ];
                  echo $flags[$uni['country']] ?? '🏫';
                  ?>
                </span>
                <?php echo $uni['country']; ?>
              </div>
            </td>
            <td>
              <span class="university-rank"><?php echo $uni['rank']; ?></span>
            </td>
            <td>
              <span class="university-type"><?php echo $uni['type']; ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      
      <div class="table-actions">
        <button class="view-all-btn" onclick="window.location.href='university-directory.php'">
          <i class="fas fa-list"></i>
          <?php echo $current_lang === 'en' ? 'View All Universities' : 'Voir Toutes les Universités'; ?>
        </button>
      </div>
    </div>
  </div>
</section>

<!-- Benefits Section -->
<section class="benefits-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo ut('benefits_title'); ?></h2>
      <p class="section-subtitle"><?php echo ut('benefits_subtitle'); ?></p>
    </div>
    
    <div class="benefits-grid">
      <?php foreach($benefits as $benefit): ?>
      <div class="benefit-card fade-in">
        <div class="benefit-icon">
          <i class="<?php echo $benefit['icon']; ?>"></i>
        </div>
        <h4><?php echo ut($benefit['title_key']); ?></h4>
        <p>
          <?php 
          $desc_key = str_replace('title', 'desc', $benefit['title_key']);
          echo ut($desc_key);
          ?>
        </p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Testimonials -->
<section class="testimonials-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo ut('testimonials_title'); ?></h2>
      <p class="section-subtitle"><?php echo ut('testimonials_subtitle'); ?></p>
    </div>
    
    <div class="testimonials-grid">
      <?php foreach($testimonials as $testimonial): ?>
      <div class="testimonial-card fade-in">
        <p class="testimonial-text"><?php echo ut($testimonial['key']); ?></p>
        <div class="testimonial-author">
          <div class="author-avatar"><?php echo $testimonial['initial']; ?></div>
          <div class="author-info">
            <h5><?php echo ut($testimonial['key'] . '_name'); ?></h5>
            <p><?php echo ut($testimonial['key'] . '_university'); ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Support Section -->
<section class="support-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title" style="color: white;"><?php echo ut('support_title'); ?></h2>
      <p class="section-description" style="color: rgba(255, 255, 255, 0.9);"><?php echo ut('support_subtitle'); ?></p>
    </div>
    
    <div class="support-steps">
      <div class="support-track">
        <div class="support-step fade-in">
          <div class="step-circle">1</div>
          <h4><?php echo ut('support_step1'); ?></h4>
        </div>
        
        <div class="support-step fade-in">
          <div class="step-circle">2</div>
          <h4><?php echo ut('support_step2'); ?></h4>
        </div>
        
        <div class="support-step fade-in">
          <div class="step-circle">3</div>
          <h4><?php echo ut('support_step3'); ?></h4>
        </div>
        
        <div class="support-step fade-in">
          <div class="step-circle">4</div>
          <h4><?php echo ut('support_step4'); ?></h4>
        </div>
        
        <div class="support-step fade-in">
          <div class="step-circle">5</div>
          <h4><?php echo ut('support_step5'); ?></h4>
        </div>
        
        <div class="support-step fade-in">
          <div class="step-circle">6</div>
          <h4><?php echo ut('support_step6'); ?></h4>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA Section -->
<section class="universities-cta">
  <div class="cta-content">
    <h2 class="fade-in"><?php echo ut('cta_title'); ?></h2>
    <p class="fade-in"><?php echo ut('cta_description'); ?></p>
    <div class="cta-buttons">
      <button class="cta-button cta-button-primary fade-in" onclick="window.location.href='university-match.php'">
        <i class="fas fa-university"></i>
        <?php echo ut('cta_button'); ?>
      </button>
      <button class="cta-button cta-button-secondary fade-in" onclick="window.open('university-guide.pdf', '_blank')">
        <i class="fas fa-download"></i>
        <?php echo ut('cta_button2'); ?>
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

  // University table row click handlers
  document.querySelectorAll('.university-table tbody tr').forEach(row => {
    row.addEventListener('click', function() {
      const universityName = this.querySelector('.university-name')?.textContent;
      if (universityName) {
        window.location.href = `university-details.php?name=${encodeURIComponent(universityName)}`;
      }
    });
    
    // Make rows clickable
    row.style.cursor = 'pointer';
  });

  // Region card click handlers
  document.querySelectorAll('.region-card').forEach(card => {
    card.addEventListener('click', function() {
      const regionName = this.querySelector('h3')?.textContent;
      if (regionName) {
        window.location.href = `universities-by-region.php?region=${encodeURIComponent(regionName)}`;
      }
    });
    
    // Make region cards clickable
    card.style.cursor = 'pointer';
  });

  // Featured university card click handlers
  document.querySelectorAll('.featured-card').forEach(card => {
    card.addEventListener('click', function() {
      const universityName = this.querySelector('h3')?.textContent;
      if (universityName) {
        window.location.href = `university-details.php?name=${encodeURIComponent(universityName)}`;
      }
    });
    
    // Make featured cards clickable
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

  // Add hover effects to support steps
  document.querySelectorAll('.support-step').forEach(step => {
    step.addEventListener('mouseenter', function() {
      const circle = this.querySelector('.step-circle');
      if (circle) {
        circle.style.transform = 'scale(1.1)';
      }
    });
    
    step.addEventListener('mouseleave', function() {
      const circle = this.querySelector('.step-circle');
      if (circle) {
        circle.style.transform = 'scale(1)';
      }
    });
  });

  // Add parallax effect to hero
  window.addEventListener('scroll', function() {
    const scrolled = window.pageYOffset;
    const hero = document.querySelector('.universities-hero');
    if (hero) {
      const rate = scrolled * 0.5;
      hero.style.backgroundPositionY = rate + 'px';
    }
  });

  // Add staggered animation to table rows
  document.querySelectorAll('.university-table tbody tr').forEach((row, index) => {
    row.style.animationDelay = `${index * 0.1}s`;
  });

})();
</script>

</body>
</html>