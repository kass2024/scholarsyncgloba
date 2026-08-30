<?php
// ============================================
// INCLUDE HEADER FOR LANGUAGE SWITCHING LOGIC
// ============================================
include 'header.php';

// Set page title with language switching
$pageTitle = $current_lang === 'en' ? 'Programs - ScholarSync Global' : 'Programmes - ScholarSync Global';

// ============================================
// TRANSLATIONS FOR PROGRAMS PAGE
// ============================================

$programs_translations = [
    'en' => [
        // Hero Section
        'hero_title' => 'Our Academic Programs',
        'hero_subtitle' => 'Discover pathways to global education success',
        'hero_description' => 'Comprehensive range of undergraduate, graduate, and professional programs tailored to your career aspirations.',
        
        // Stats
        'stats_programs' => 'Programs Offered',
        'stats_countries' => 'Partner Countries',
        'stats_students' => 'Students Enrolled',
        'stats_success' => 'Success Rate',
        
        // Featured Programs
        'featured_title' => 'Featured Programs',
        'featured_subtitle' => 'Top choices for international students',
        
        // Program Categories
        'categories_title' => 'Program Categories',
        'categories_subtitle' => 'Find your perfect academic pathway',
        
        'category1_title' => 'Undergraduate Programs',
        'category1_desc' => 'Bachelor\'s degrees in diverse fields from global universities',
        'category1_count' => '450+ Programs',
        
        'category2_title' => 'Graduate Programs',
        'category2_desc' => 'Master\'s and PhD programs with research opportunities',
        'category2_count' => '300+ Programs',
        
        'category3_title' => 'Professional Certifications',
        'category3_desc' => 'Industry-recognized certifications for career advancement',
        'category3_count' => '200+ Certifications',
        
        'category4_title' => 'Language Programs',
        'category4_desc' => 'Intensive language courses and pathway programs',
        'category4_count' => '15 Languages',
        
        'category5_title' => 'Exchange Programs',
        'category5_desc' => 'Short-term study abroad and cultural exchange',
        'category5_count' => '50+ Universities',
        
        'category6_title' => 'Online Programs',
        'category6_desc' => 'Flexible remote learning with international accreditation',
        'category6_count' => '150+ Online',
        
        // Popular Fields
        'fields_title' => 'Popular Study Fields',
        'fields_subtitle' => 'In-demand disciplines with global opportunities',
        
        'field1_title' => 'Business & Management',
        'field1_desc' => 'MBA, Finance, Marketing, Entrepreneurship',
        'field1_count' => '180 Programs',
        
        'field2_title' => 'Engineering & Technology',
        'field2_desc' => 'CS, AI, Mechanical, Civil, Electrical',
        'field2_count' => '220 Programs',
        
        'field3_title' => 'Health Sciences',
        'field3_desc' => 'Medicine, Nursing, Pharmacy, Public Health',
        'field3_count' => '120 Programs',
        
        'field4_title' => 'Arts & Humanities',
        'field4_desc' => 'Literature, History, Philosophy, Languages',
        'field4_count' => '95 Programs',
        
        'field5_title' => 'Social Sciences',
        'field5_desc' => 'Psychology, Sociology, Economics, Political Science',
        'field5_count' => '110 Programs',
        
        'field6_title' => 'STEM Programs',
        'field6_desc' => 'Science, Technology, Engineering, Mathematics',
        'field6_count' => '250 Programs',
        
        // Program Levels
        'levels_title' => 'Program Levels',
        'levels_subtitle' => 'From foundation to doctoral studies',
        
        'level1_title' => 'Foundation Programs',
        'level1_desc' => 'Bridge courses for university preparation',
        'level1_duration' => '6-12 months',
        
        'level2_title' => 'Diploma Programs',
        'level2_desc' => 'Career-focused technical education',
        'level2_duration' => '1-2 years',
        
        'level3_title' => 'Bachelor\'s Degrees',
        'level3_desc' => 'Undergraduate degrees with specialization',
        'level3_duration' => '3-4 years',
        
        'level4_title' => 'Master\'s Degrees',
        'level4_desc' => 'Advanced studies with research components',
        'level4_duration' => '1-2 years',
        
        'level5_title' => 'Doctoral Programs',
        'level5_desc' => 'PhD and research-intensive programs',
        'level5_duration' => '3-5 years',
        
        'level6_title' => 'Executive Education',
        'level6_desc' => 'Short courses for working professionals',
        'level6_duration' => '3-12 months',
        
        // Program Features
        'features_title' => 'Program Features',
        'features_subtitle' => 'What makes our programs exceptional',
        
        'feature1_title' => 'Global Recognition',
        'feature1_desc' => 'Degrees recognized worldwide with accreditation',
        
        'feature2_title' => 'Industry Partnerships',
        'feature2_desc' => 'Direct connections with leading employers',
        
        'feature3_title' => 'Research Opportunities',
        'feature3_desc' => 'Access to cutting-edge research facilities',
        
        'feature4_title' => 'Career Services',
        'feature4_desc' => 'Dedicated placement and career support',
        
        'feature5_title' => 'Flexible Options',
        'feature5_desc' => 'Full-time, part-time, and online formats',
        
        'feature6_title' => 'Scholarship Access',
        'feature6_desc' => 'Financial aid and scholarship assistance',
        
        // Destination Countries
        'destinations_title' => 'Study Destinations',
        'destinations_subtitle' => 'Top countries for international education',
        
        // Testimonials
        'testimonials_title' => 'Student Experiences',
        'testimonials_subtitle' => 'Success stories from our program alumni',
        
        'testimonial1' => 'The MBA program through ScholarSync Global transformed my career. Excellent guidance throughout!',
        'testimonial1_name' => 'Alex Chen',
        'testimonial1_program' => 'MBA, London Business School',
        
        'testimonial2' => 'Got accepted into my dream Computer Science program with scholarship assistance.',
        'testimonial2_name' => 'Sarah Johnson',
        'testimonial2_program' => 'MSc Computer Science, University of Toronto',
        
        'testimonial3' => 'Professional certification opened doors to international job opportunities.',
        'testimonial3_name' => 'David Kim',
        'testimonial3_program' => 'Project Management Certification',
        
        // Application Process
        'process_title' => 'Application Process',
        'process_subtitle' => 'Simple steps to start your journey',
        
        'step1' => 'Program Selection',
        'step2' => 'Document Preparation',
        'step3' => 'Application Submission',
        'step4' => 'Admission Decision',
        'step5' => 'Visa Processing',
        'step6' => 'Pre-Departure',
        
        // CTA
        'cta_title' => 'Find Your Perfect Program',
        'cta_description' => 'Schedule a free consultation with our academic advisors',
        'cta_button' => 'Book Consultation',
        'cta_button2' => 'Download Program Guide',
        
        // Page Metadata
        'page_description' => 'Explore academic programs at ScholarSync Global - undergraduate, graduate, professional certifications, and language programs for international education.',
        'page_title' => 'Academic Programs - ScholarSync Global',
    ],
    
    'fr' => [
        // Hero Section
        'hero_title' => 'Nos Programmes Académiques',
        'hero_subtitle' => 'Découvrez les chemins vers la réussite éducative mondiale',
        'hero_description' => 'Gamme complète de programmes de licence, master et professionnels adaptés à vos aspirations.',
        
        // Stats
        'stats_programs' => 'Programmes Offerts',
        'stats_countries' => 'Pays Partenaires',
        'stats_students' => 'Étudiants Inscrits',
        'stats_success' => 'Taux de Réussite',
        
        // Featured Programs
        'featured_title' => 'Programmes en Vedette',
        'featured_subtitle' => 'Choix populaires pour étudiants internationaux',
        
        // Program Categories
        'categories_title' => 'Catégories de Programmes',
        'categories_subtitle' => 'Trouvez votre parcours académique idéal',
        
        'category1_title' => 'Programmes de Licence',
        'category1_desc' => 'Licences dans divers domaines d\'universités mondiales',
        'category1_count' => '450+ Programmes',
        
        'category2_title' => 'Programmes de Master/Doctorat',
        'category2_desc' => 'Masters et doctorats avec opportunités de recherche',
        'category2_count' => '300+ Programmes',
        
        'category3_title' => 'Certifications Professionnelles',
        'category3_desc' => 'Certifications reconnues par l\'industrie',
        'category3_count' => '200+ Certifications',
        
        'category4_title' => 'Programmes Linguistiques',
        'category4_desc' => 'Cours intensifs et programmes passerelles',
        'category4_count' => '15 Langues',
        
        'category5_title' => 'Programmes d\'Échange',
        'category5_desc' => 'Études à l\'étranger et échanges culturels',
        'category5_count' => '50+ Universités',
        
        'category6_title' => 'Programmes en Ligne',
        'category6_desc' => 'Apprentissage à distance flexible avec accréditation',
        'category6_count' => '150+ En ligne',
        
        // Popular Fields
        'fields_title' => 'Domaines d\'Études Populaires',
        'fields_subtitle' => 'Disciplines demandées avec opportunités mondiales',
        
        'field1_title' => 'Commerce & Gestion',
        'field1_desc' => 'MBA, Finance, Marketing, Entrepreneuriat',
        'field1_count' => '180 Programmes',
        
        'field2_title' => 'Ingénierie & Technologie',
        'field2_desc' => 'Informatique, IA, Mécanique, Génie Civil',
        'field2_count' => '220 Programmes',
        
        'field3_title' => 'Sciences de la Santé',
        'field3_desc' => 'Médecine, Soins Infirmiers, Pharmacie',
        'field3_count' => '120 Programmes',
        
        'field4_title' => 'Arts & Sciences Humaines',
        'field4_desc' => 'Littérature, Histoire, Philosophie, Langues',
        'field4_count' => '95 Programmes',
        
        'field5_title' => 'Sciences Sociales',
        'field5_desc' => 'Psychologie, Sociologie, Économie',
        'field5_count' => '110 Programmes',
        
        'field6_title' => 'Programmes STEM',
        'field6_desc' => 'Science, Technologie, Ingénierie, Mathématiques',
        'field6_count' => '250 Programmes',
        
        // Program Levels
        'levels_title' => 'Niveaux de Programmes',
        'levels_subtitle' => 'De la fondation aux études doctorales',
        
        'level1_title' => 'Programmes de Fondation',
        'level1_desc' => 'Cours passerelles pour préparation universitaire',
        'level1_duration' => '6-12 mois',
        
        'level2_title' => 'Programmes de Diplôme',
        'level2_desc' => 'Éducation technique axée sur la carrière',
        'level2_duration' => '1-2 ans',
        
        'level3_title' => 'Licences',
        'level3_desc' => 'Diplômes de licence avec spécialisation',
        'level3_duration' => '3-4 ans',
        
        'level4_title' => 'Masters',
        'level4_desc' => 'Études avancées avec composantes de recherche',
        'level4_duration' => '1-2 ans',
        
        'level5_title' => 'Programmes Doctoraux',
        'level5_desc' => 'Doctorats et programmes de recherche',
        'level5_duration' => '3-5 ans',
        
        'level6_title' => 'Éducation Exécutive',
        'level6_desc' => 'Cours courts pour professionnels',
        'level6_duration' => '3-12 mois',
        
        // Program Features
        'features_title' => 'Caractéristiques des Programmes',
        'features_subtitle' => 'Ce qui rend nos programmes exceptionnels',
        
        'feature1_title' => 'Reconnaissance Mondiale',
        'feature1_desc' => 'Diplômes reconnus mondialement avec accréditation',
        
        'feature2_title' => 'Partenariats Industriels',
        'feature2_desc' => 'Connexions directes avec employeurs leaders',
        
        'feature3_title' => 'Opportunités de Recherche',
        'feature3_desc' => 'Accès aux installations de recherche avancées',
        
        'feature4_title' => 'Services de Carrière',
        'feature4_desc' => 'Support de placement et carrière dédié',
        
        'feature5_title' => 'Options Flexibles',
        'feature5_desc' => 'Formats à temps plein, temps partiel et en ligne',
        
        'feature6_title' => 'Accès aux Bourses',
        'feature6_desc' => 'Aide financière et assistance aux bourses',
        
        // Destination Countries
        'destinations_title' => 'Destinations d\'Études',
        'destinations_subtitle' => 'Top pays pour l\'éducation internationale',
        
        // Testimonials
        'testimonials_title' => 'Expériences des Étudiants',
        'testimonials_subtitle' => 'Histoires de réussite de nos anciens',
        
        'testimonial1' => 'Le programme MBA via ScholarSync Global a transformé ma carrière. Excellent accompagnement!',
        'testimonial1_name' => 'Alex Chen',
        'testimonial1_program' => 'MBA, London Business School',
        
        'testimonial2' => 'Accepté dans mon programme d\'informatique de rêve avec assistance bourse.',
        'testimonial2_name' => 'Sarah Johnson',
        'testimonial2_program' => 'MSc Informatique, Université de Toronto',
        
        'testimonial3' => 'Certification professionnelle ouverte aux opportunités d\'emploi international.',
        'testimonial3_name' => 'David Kim',
        'testimonial3_program' => 'Certification Gestion de Projet',
        
        // Application Process
        'process_title' => 'Processus de Candidature',
        'process_subtitle' => 'Étapes simples pour commencer votre voyage',
        
        'step1' => 'Sélection de Programme',
        'step2' => 'Préparation des Documents',
        'step3' => 'Soumission de Candidature',
        'step4' => 'Décision d\'Admission',
        'step5' => 'Traitement du Visa',
        'step6' => 'Pré-Départ',
        
        // CTA
        'cta_title' => 'Trouvez Votre Programme Idéal',
        'cta_description' => 'Planifiez une consultation gratuite avec nos conseillers',
        'cta_button' => 'Réserver Consultation',
        'cta_button2' => 'Télécharger le Guide',
        
        // Page Metadata
        'page_description' => 'Explorez les programmes académiques de ScholarSync Global - licences, masters, certifications professionnelles et programmes linguistiques.',
        'page_title' => 'Programmes Académiques - ScholarSync Global',
    ]
];

// Function to get programs page translation
function pt($key) {
    global $programs_translations, $current_lang;
    
    // Fallback to English if key missing
    if (isset($programs_translations[$current_lang][$key])) {
        return $programs_translations[$current_lang][$key];
    } elseif (isset($programs_translations['en'][$key])) {
        return $programs_translations['en'][$key];
    }
    
    return $key; // Return key itself as last resort
}

// Define featured programs
$featured_programs = [
    [
        'title' => 'MBA Global Leadership',
        'icon' => 'fas fa-briefcase',
        'description' => '12-month intensive MBA with international business focus',
        'duration' => '12 Months',
        'format' => 'Full-time',
        'locations' => ['USA', 'UK', 'Canada'],
        'rank' => 'Top 50 Global'
    ],
    [
        'title' => 'MSc Computer Science',
        'icon' => 'fas fa-laptop-code',
        'description' => 'Advanced computing with AI and machine learning specialization',
        'duration' => '18 Months',
        'format' => 'Full-time/Part-time',
        'locations' => ['Canada', 'Germany', 'Australia'],
        'rank' => 'Top 100 Worldwide'
    ],
    [
        'title' => 'International Business Diploma',
        'icon' => 'fas fa-globe',
        'description' => 'Career-focused business education with global perspective',
        'duration' => '8 Months',
        'format' => 'Accelerated',
        'locations' => ['UK', 'Singapore', 'UAE'],
        'rank' => 'Professional Certification'
    ]
];

// Define destinations
$destinations = [
    ['country' => 'USA', 'flag' => '🇺🇸', 'programs' => '300+', 'rank' => '#1 Destination'],
    ['country' => 'Canada', 'flag' => '🇨🇦', 'programs' => '250+', 'rank' => 'Top for PR'],
    ['country' => 'UK', 'flag' => '🇬🇧', 'programs' => '200+', 'rank' => 'Historic Excellence'],
    ['country' => 'Australia', 'flag' => '🇦🇺', 'programs' => '180+', 'rank' => 'Quality Lifestyle'],
    ['country' => 'Germany', 'flag' => '🇩🇪', 'programs' => '150+', 'rank' => 'Tuition-Free'],
    ['country' => 'Netherlands', 'flag' => '🇳🇱', 'programs' => '120+', 'rank' => 'Innovation Hub']
];

// Define testimonials
$testimonials = [
    ['key' => 'testimonial1', 'initial' => 'AC'],
    ['key' => 'testimonial2', 'initial' => 'SJ'],
    ['key' => 'testimonial3', 'initial' => 'DK']
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
   PROGRAMS PAGE STYLES
   Modern, structured design with clear hierarchy
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
.programs-hero {
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
    radial-gradient(circle at 80% 70%, rgba(45, 212, 191, 0.1) 0%, transparent 40%);
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

/* ===== FEATURED PROGRAMS ===== */
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
  align-items: flex-start;
  gap: 20px;
  margin-bottom: 25px;
}

.featured-icon {
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

.featured-card:hover .featured-icon {
  transform: rotate(10deg) scale(1.1);
}

.featured-title {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 10px;
}

.featured-description {
  color: var(--text-light);
  line-height: 1.6;
  margin-bottom: 25px;
  font-size: 1rem;
}

.featured-details {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  margin-bottom: 25px;
}

.featured-detail {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  color: var(--text-light);
}

.featured-detail i {
  color: var(--accent-teal);
}

.featured-locations {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 25px;
}

.location-tag {
  background: var(--primary-light);
  color: var(--primary-navy);
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 500;
}

.featured-rank {
  background: linear-gradient(135deg, var(--accent-gold), var(--accent-teal));
  color: white;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 600;
  display: inline-block;
  margin-top: 10px;
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
  position: relative;
  overflow: hidden;
  text-align: center;
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

/* ===== FIELDS SECTION ===== */
.fields-section {
  background: white;
}

.fields-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.field-card {
  background: var(--bg);
  padding: 35px 30px;
  border-radius: 16px;
  border: 1px solid var(--border-light);
  transition: var(--transition);
  position: relative;
}

.field-card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-md);
}

.field-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 6px;
  height: 100%;
  background: linear-gradient(to bottom, var(--accent-teal), var(--accent-gold));
}

.field-header {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 20px;
}

.field-icon {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, var(--primary-light), var(--teal-light));
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  color: var(--primary-navy);
  flex-shrink: 0;
}

.field-card h3 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 5px;
}

.field-description {
  color: var(--text-light);
  font-size: 0.95rem;
  line-height: 1.6;
  margin-bottom: 15px;
}

.field-count {
  color: var(--accent-gold);
  font-weight: 600;
  font-size: 0.9rem;
}

/* ===== LEVELS SECTION ===== */
.levels-section {
  background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
}

.levels-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.level-card {
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

.level-card:hover {
  transform: translateY(-10px);
  box-shadow: var(--shadow-lg);
}

.level-card::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--primary-navy), var(--accent-teal));
}

.level-number {
  width: 60px;
  height: 60px;
  margin: 0 auto 25px;
  background: linear-gradient(135deg, var(--primary-navy), var(--secondary-blue));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.8rem;
  font-weight: 700;
  border: 4px solid white;
  box-shadow: var(--shadow-md);
}

.level-card h3 {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 15px;
}

.level-card p {
  color: var(--text-light);
  line-height: 1.6;
  margin-bottom: 20px;
  font-size: 1rem;
}

.level-duration {
  background: linear-gradient(135deg, var(--accent-light), var(--teal-light));
  color: var(--primary-navy);
  padding: 8px 20px;
  border-radius: 20px;
  font-size: 0.95rem;
  font-weight: 600;
  display: inline-block;
}

/* ===== FEATURES SECTION ===== */
.features-section {
  background: white;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.feature-item {
  text-align: center;
  padding: 40px 30px;
  background: var(--bg);
  border-radius: 16px;
  border: 1px solid var(--border-light);
  transition: var(--transition);
}

.feature-item:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-md);
}

.feature-icon {
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

.feature-item:hover .feature-icon {
  transform: rotate(10deg) scale(1.1);
}

.feature-item h4 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 15px;
}

.feature-item p {
  color: var(--text-light);
  line-height: 1.6;
  font-size: 1rem;
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

.destination-programs {
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

/* ===== TESTIMONIALS SECTION ===== */
.testimonials-section {
  background: white;
  position: relative;
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

/* ===== PROCESS SECTION ===== */
.process-section {
  background: linear-gradient(135deg, var(--primary-navy) 0%, var(--dark-blue) 100%);
  color: white;
}

.process-steps {
  max-width: 1000px;
  margin: 0 auto;
  position: relative;
}

.process-track {
  display: flex;
  justify-content: space-between;
  position: relative;
  margin-top: 60px;
}

.process-track::before {
  content: '';
  position: absolute;
  top: 30px;
  left: 50px;
  right: 50px;
  height: 3px;
  background: rgba(255, 255, 255, 0.3);
  z-index: 1;
}

@media (max-width: 768px) {
  .process-track {
    flex-direction: column;
    gap: 40px;
  }
  
  .process-track::before {
    display: none;
  }
}

.process-step {
  text-align: center;
  position: relative;
  z-index: 2;
  flex: 1;
}

@media (max-width: 768px) {
  .process-step {
    display: flex;
    align-items: center;
    gap: 20px;
    text-align: left;
  }
}

.step-circle {
  width: 60px;
  height: 60px;
  margin: 0 auto 20px;
  background: linear-gradient(135deg, var(--accent-gold), var(--accent-teal));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  font-weight: 700;
  color: white;
  border: 4px solid rgba(255, 255, 255, 0.2);
}

@media (max-width: 768px) {
  .step-circle {
    margin: 0;
    flex-shrink: 0;
  }
}

.process-step h4 {
  font-size: 1.1rem;
  font-weight: 600;
  margin-bottom: 10px;
}

/* ===== CTA SECTION ===== */
.programs-cta {
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
  
  .categories-grid,
  .fields-grid,
  .levels-grid {
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
  
  .categories-grid,
  .fields-grid,
  .levels-grid,
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
  
  .features-grid {
    grid-template-columns: repeat(2, 1fr);
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
  
  .features-grid {
    grid-template-columns: 1fr;
  }
  
  .destinations-grid {
    grid-template-columns: 1fr;
  }
}
</style>
</head>
<body>

<!-- Hero Section -->
<section class="programs-hero">
  <div class="hero-bg-pattern"></div>
  <div class="hero-content">
    <h1 class="fade-in"><?php echo pt('hero_title'); ?></h1>
    <p class="hero-subtitle fade-in"><?php echo pt('hero_subtitle'); ?></p>
    <p class="hero-description fade-in"><?php echo pt('hero_description'); ?></p>
  </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
  <div class="container">
    <div class="stats-grid">
      <div class="stat-item fade-in">
        <div class="stat-number"><i class="fas fa-graduation-cap"></i>1,200+</div>
        <div class="stat-label"><?php echo pt('stats_programs'); ?></div>
      </div>
      <div class="stat-item fade-in">
        <div class="stat-number"><i class="fas fa-globe"></i>50+</div>
        <div class="stat-label"><?php echo pt('stats_countries'); ?></div>
      </div>
      <div class="stat-item fade-in">
        <div class="stat-number"><i class="fas fa-users"></i>5,000+</div>
        <div class="stat-label"><?php echo pt('stats_students'); ?></div>
      </div>
      <div class="stat-item fade-in">
        <div class="stat-number"><i class="fas fa-chart-line"></i>98%</div>
        <div class="stat-label"><?php echo pt('stats_success'); ?></div>
      </div>
    </div>
  </div>
</section>

<!-- Featured Programs -->
<section class="featured-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('featured_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('featured_subtitle'); ?></p>
    </div>
    
    <div class="featured-grid">
      <?php foreach($featured_programs as $program): ?>
      <div class="featured-card fade-in">
        <div class="featured-header">
          <div class="featured-icon">
            <i class="<?php echo $program['icon']; ?>"></i>
          </div>
          <div>
            <h3 class="featured-title"><?php echo $program['title']; ?></h3>
            <p class="featured-description"><?php echo $program['description']; ?></p>
          </div>
        </div>
        
        <div class="featured-details">
          <div class="featured-detail">
            <i class="fas fa-clock"></i>
            <span><?php echo $program['duration']; ?></span>
          </div>
          <div class="featured-detail">
            <i class="fas fa-calendar"></i>
            <span><?php echo $program['format']; ?></span>
          </div>
        </div>
        
        <div class="featured-locations">
          <?php foreach($program['locations'] as $location): ?>
          <span class="location-tag"><?php echo $location; ?></span>
          <?php endforeach; ?>
        </div>
        
        <span class="featured-rank"><?php echo $program['rank']; ?></span>
      </div>
      <?php endforeach; ?>
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
      <div class="category-card fade-in">
        <div class="category-icon">
          <i class="fas fa-user-graduate"></i>
        </div>
        <h3><?php echo pt('category1_title'); ?></h3>
        <p><?php echo pt('category1_desc'); ?></p>
        <span class="category-count"><?php echo pt('category1_count'); ?></span>
      </div>
      
      <div class="category-card fade-in">
        <div class="category-icon">
          <i class="fas fa-scroll"></i>
        </div>
        <h3><?php echo pt('category2_title'); ?></h3>
        <p><?php echo pt('category2_desc'); ?></p>
        <span class="category-count"><?php echo pt('category2_count'); ?></span>
      </div>
      
      <div class="category-card fade-in">
        <div class="category-icon">
          <i class="fas fa-certificate"></i>
        </div>
        <h3><?php echo pt('category3_title'); ?></h3>
        <p><?php echo pt('category3_desc'); ?></p>
        <span class="category-count"><?php echo pt('category3_count'); ?></span>
      </div>
      
      <div class="category-card fade-in">
        <div class="category-icon">
          <i class="fas fa-language"></i>
        </div>
        <h3><?php echo pt('category4_title'); ?></h3>
        <p><?php echo pt('category4_desc'); ?></p>
        <span class="category-count"><?php echo pt('category4_count'); ?></span>
      </div>
      
      <div class="category-card fade-in">
        <div class="category-icon">
          <i class="fas fa-exchange-alt"></i>
        </div>
        <h3><?php echo pt('category5_title'); ?></h3>
        <p><?php echo pt('category5_desc'); ?></p>
        <span class="category-count"><?php echo pt('category5_count'); ?></span>
      </div>
      
      <div class="category-card fade-in">
        <div class="category-icon">
          <i class="fas fa-laptop"></i>
        </div>
        <h3><?php echo pt('category6_title'); ?></h3>
        <p><?php echo pt('category6_desc'); ?></p>
        <span class="category-count"><?php echo pt('category6_count'); ?></span>
      </div>
    </div>
  </div>
</section>

<!-- Popular Fields -->
<section class="fields-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('fields_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('fields_subtitle'); ?></p>
    </div>
    
    <div class="fields-grid">
      <div class="field-card fade-in">
        <div class="field-header">
          <div class="field-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <div>
            <h3><?php echo pt('field1_title'); ?></h3>
            <span class="field-count"><?php echo pt('field1_count'); ?></span>
          </div>
        </div>
        <p class="field-description"><?php echo pt('field1_desc'); ?></p>
      </div>
      
      <div class="field-card fade-in">
        <div class="field-header">
          <div class="field-icon">
            <i class="fas fa-cogs"></i>
          </div>
          <div>
            <h3><?php echo pt('field2_title'); ?></h3>
            <span class="field-count"><?php echo pt('field2_count'); ?></span>
          </div>
        </div>
        <p class="field-description"><?php echo pt('field2_desc'); ?></p>
      </div>
      
      <div class="field-card fade-in">
        <div class="field-header">
          <div class="field-icon">
            <i class="fas fa-heartbeat"></i>
          </div>
          <div>
            <h3><?php echo pt('field3_title'); ?></h3>
            <span class="field-count"><?php echo pt('field3_count'); ?></span>
          </div>
        </div>
        <p class="field-description"><?php echo pt('field3_desc'); ?></p>
      </div>
      
      <div class="field-card fade-in">
        <div class="field-header">
          <div class="field-icon">
            <i class="fas fa-palette"></i>
          </div>
          <div>
            <h3><?php echo pt('field4_title'); ?></h3>
            <span class="field-count"><?php echo pt('field4_count'); ?></span>
          </div>
        </div>
        <p class="field-description"><?php echo pt('field4_desc'); ?></p>
      </div>
      
      <div class="field-card fade-in">
        <div class="field-header">
          <div class="field-icon">
            <i class="fas fa-users"></i>
          </div>
          <div>
            <h3><?php echo pt('field5_title'); ?></h3>
            <span class="field-count"><?php echo pt('field5_count'); ?></span>
          </div>
        </div>
        <p class="field-description"><?php echo pt('field5_desc'); ?></p>
      </div>
      
      <div class="field-card fade-in">
        <div class="field-header">
          <div class="field-icon">
            <i class="fas fa-atom"></i>
          </div>
          <div>
            <h3><?php echo pt('field6_title'); ?></h3>
            <span class="field-count"><?php echo pt('field6_count'); ?></span>
          </div>
        </div>
        <p class="field-description"><?php echo pt('field6_desc'); ?></p>
      </div>
    </div>
  </div>
</section>

<!-- Program Levels -->
<section class="levels-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('levels_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('levels_subtitle'); ?></p>
    </div>
    
    <div class="levels-grid">
      <div class="level-card fade-in">
        <div class="level-number">1</div>
        <h3><?php echo pt('level1_title'); ?></h3>
        <p><?php echo pt('level1_desc'); ?></p>
        <span class="level-duration"><?php echo pt('level1_duration'); ?></span>
      </div>
      
      <div class="level-card fade-in">
        <div class="level-number">2</div>
        <h3><?php echo pt('level2_title'); ?></h3>
        <p><?php echo pt('level2_desc'); ?></p>
        <span class="level-duration"><?php echo pt('level2_duration'); ?></span>
      </div>
      
      <div class="level-card fade-in">
        <div class="level-number">3</div>
        <h3><?php echo pt('level3_title'); ?></h3>
        <p><?php echo pt('level3_desc'); ?></p>
        <span class="level-duration"><?php echo pt('level3_duration'); ?></span>
      </div>
      
      <div class="level-card fade-in">
        <div class="level-number">4</div>
        <h3><?php echo pt('level4_title'); ?></h3>
        <p><?php echo pt('level4_desc'); ?></p>
        <span class="level-duration"><?php echo pt('level4_duration'); ?></span>
      </div>
      
      <div class="level-card fade-in">
        <div class="level-number">5</div>
        <h3><?php echo pt('level5_title'); ?></h3>
        <p><?php echo pt('level5_desc'); ?></p>
        <span class="level-duration"><?php echo pt('level5_duration'); ?></span>
      </div>
      
      <div class="level-card fade-in">
        <div class="level-number">6</div>
        <h3><?php echo pt('level6_title'); ?></h3>
        <p><?php echo pt('level6_desc'); ?></p>
        <span class="level-duration"><?php echo pt('level6_duration'); ?></span>
      </div>
    </div>
  </div>
</section>

<!-- Program Features -->
<section class="features-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('features_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('features_subtitle'); ?></p>
    </div>
    
    <div class="features-grid">
      <div class="feature-item fade-in">
        <div class="feature-icon">
          <i class="fas fa-globe"></i>
        </div>
        <h4><?php echo pt('feature1_title'); ?></h4>
        <p><?php echo pt('feature1_desc'); ?></p>
      </div>
      
      <div class="feature-item fade-in">
        <div class="feature-icon">
          <i class="fas fa-handshake"></i>
        </div>
        <h4><?php echo pt('feature2_title'); ?></h4>
        <p><?php echo pt('feature2_desc'); ?></p>
      </div>
      
      <div class="feature-item fade-in">
        <div class="feature-icon">
          <i class="fas fa-flask"></i>
        </div>
        <h4><?php echo pt('feature3_title'); ?></h4>
        <p><?php echo pt('feature3_desc'); ?></p>
      </div>
      
      <div class="feature-item fade-in">
        <div class="feature-icon">
          <i class="fas fa-briefcase"></i>
        </div>
        <h4><?php echo pt('feature4_title'); ?></h4>
        <p><?php echo pt('feature4_desc'); ?></p>
      </div>
      
      <div class="feature-item fade-in">
        <div class="feature-icon">
          <i class="fas fa-calendar-alt"></i>
        </div>
        <h4><?php echo pt('feature5_title'); ?></h4>
        <p><?php echo pt('feature5_desc'); ?></p>
      </div>
      
      <div class="feature-item fade-in">
        <div class="feature-icon">
          <i class="fas fa-award"></i>
        </div>
        <h4><?php echo pt('feature6_title'); ?></h4>
        <p><?php echo pt('feature6_desc'); ?></p>
      </div>
    </div>
  </div>
</section>

<!-- Destinations -->
<section class="destinations-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('destinations_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('destinations_subtitle'); ?></p>
    </div>
    
    <div class="destinations-grid">
      <?php foreach($destinations as $destination): ?>
      <div class="destination-card fade-in">
        <div class="destination-flag"><?php echo $destination['flag']; ?></div>
        <div class="destination-info">
          <h4><?php echo $destination['country']; ?></h4>
          <p class="destination-programs"><?php echo $destination['programs']; ?> Programs</p>
          <p class="destination-rank"><?php echo $destination['rank']; ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Testimonials -->
<section class="testimonials-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo pt('testimonials_title'); ?></h2>
      <p class="section-subtitle"><?php echo pt('testimonials_subtitle'); ?></p>
    </div>
    
    <div class="testimonials-grid">
      <?php foreach($testimonials as $testimonial): ?>
      <div class="testimonial-card fade-in">
        <p class="testimonial-text"><?php echo pt($testimonial['key']); ?></p>
        <div class="testimonial-author">
          <div class="author-avatar"><?php echo $testimonial['initial']; ?></div>
          <div class="author-info">
            <h5><?php echo pt($testimonial['key'] . '_name'); ?></h5>
            <p><?php echo pt($testimonial['key'] . '_program'); ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Application Process -->
<section class="process-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title" style="color: white;"><?php echo pt('process_title'); ?></h2>
      <p class="section-description" style="color: rgba(255, 255, 255, 0.9);"><?php echo pt('process_subtitle'); ?></p>
    </div>
    
    <div class="process-steps">
      <div class="process-track">
        <div class="process-step fade-in">
          <div class="step-circle">1</div>
          <h4><?php echo pt('step1'); ?></h4>
        </div>
        
        <div class="process-step fade-in">
          <div class="step-circle">2</div>
          <h4><?php echo pt('step2'); ?></h4>
        </div>
        
        <div class="process-step fade-in">
          <div class="step-circle">3</div>
          <h4><?php echo pt('step3'); ?></h4>
        </div>
        
        <div class="process-step fade-in">
          <div class="step-circle">4</div>
          <h4><?php echo pt('step4'); ?></h4>
        </div>
        
        <div class="process-step fade-in">
          <div class="step-circle">5</div>
          <h4><?php echo pt('step5'); ?></h4>
        </div>
        
        <div class="process-step fade-in">
          <div class="step-circle">6</div>
          <h4><?php echo pt('step6'); ?></h4>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA Section -->
<section class="programs-cta">
  <div class="cta-content">
    <h2 class="fade-in"><?php echo pt('cta_title'); ?></h2>
    <p class="fade-in"><?php echo pt('cta_description'); ?></p>
    <div class="cta-buttons">
      <button class="cta-button cta-button-primary fade-in" onclick="window.location.href='consultation.php'">
        <i class="fas fa-calendar-check"></i>
        <?php echo pt('cta_button'); ?>
      </button>
      <button class="cta-button cta-button-secondary fade-in" onclick="window.open('program-guide.pdf', '_blank')">
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

  // Add hover effects
  document.querySelectorAll('.category-card, .featured-card, .level-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
      const icon = this.querySelector('.category-icon, .featured-icon, .level-number');
      if (icon) {
        icon.style.transform = 'scale(1.1)';
      }
    });
    
    card.addEventListener('mouseleave', function() {
      const icon = this.querySelector('.category-icon, .featured-icon, .level-number');
      if (icon) {
        icon.style.transform = 'scale(1)';
      }
    });
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

  // Add click handlers for featured cards
  document.querySelectorAll('.featured-card').forEach(card => {
    card.addEventListener('click', function() {
      const title = this.querySelector('.featured-title')?.textContent;
      if (title) {
        window.location.href = `program-details.php?program=${encodeURIComponent(title)}`;
      }
    });
    
    // Make cursor pointer for featured cards
    card.style.cursor = 'pointer';
  });

})();
</script>

</body>
</html>