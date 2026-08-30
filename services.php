<?php
// ============================================
// INCLUDE HEADER FOR LANGUAGE SWITCHING LOGIC
// ============================================
include 'header.php';

// Set page title with language switching
$pageTitle = $current_lang === 'en' ? 'Services - ScholarSync Global' : 'Services - ScholarSync Global';

// ============================================
// TRANSLATIONS FOR SERVICES PAGE
// ============================================

$services_translations = [
    'en' => [
        // Hero Section
        'hero_title' => 'Our Comprehensive Services',
        'hero_subtitle' => 'End-to-end support for your global education journey',
        'hero_description' => 'From university applications to career placement, we provide complete solutions for international education success.',
        
        // Stats
        'stats_services' => 'Services Offered',
        'stats_success' => 'Success Rate',
        'stats_clients' => 'Happy Clients',
        'stats_countries' => 'Countries Served',
        
        // Main Services
        'main_services_title' => 'Core Services',
        'main_services_subtitle' => 'Our comprehensive suite of education services',
        
        // Service Categories
        'categories_title' => 'Service Categories',
        'categories_subtitle' => 'Complete solutions for every stage of your journey',
        
        'category1_title' => 'Admissions & Applications',
        'category1_desc' => 'Complete university application support from selection to submission',
        'category1_count' => '5,000+ Successes',
        
        'category2_title' => 'Visa & Immigration',
        'category2_desc' => 'Expert guidance for study, work, and family visas worldwide',
        'category2_count' => '98% Approval Rate',
        
        'category3_title' => 'Financial Solutions',
        'category3_desc' => 'Scholarship assistance and education loan programs',
        'category3_count' => '$15M+ Secured',
        
        'category4_title' => 'Career Services',
        'category4_desc' => 'Job placement, internships, and career counseling',
        'category4_count' => '2,000+ Placements',
        
        'category5_title' => 'Academic Support',
        'category5_desc' => 'Credit transfers, course selection, and academic planning',
        'category5_count' => '1,500+ Transfers',
        
        'category6_title' => 'Travel & Logistics',
        'category6_desc' => 'Flight bookings, accommodation, and airport assistance',
        'category6_count' => '3,000+ Arrivals',
        
        // Service Process
        'process_title' => 'Our Service Process',
        'process_subtitle' => 'How we deliver exceptional results',
        
        'process_step1' => 'Consultation & Assessment',
        'process_step2' => 'Custom Strategy Development',
        'process_step3' => 'Document Preparation',
        'process_step4' => 'Application Submission',
        'process_step5' => 'Follow-up & Support',
        'process_step6' => 'Post-Arrival Assistance',
        
        'process_desc1' => 'Detailed analysis of your profile and goals',
        'process_desc2' => 'Personalized roadmap for your success',
        'process_desc3' => 'Professional preparation of all documents',
        'process_desc4' => 'Timely submission with quality assurance',
        'process_desc5' => 'Regular updates and proactive communication',
        'process_desc6' => 'Continuation of support after arrival',
        
        // Detailed Services
        'detailed_title' => 'Detailed Services',
        'detailed_subtitle' => 'In-depth support across all areas',
        
        'service1_title' => 'University Selection & Application',
        'service1_desc' => 'Strategic university matching, essay writing, recommendation letters, portfolio development, and interview preparation.',
        'service1_points' => ['Profile Evaluation', 'University Shortlisting', 'Document Preparation', 'Application Review', 'Interview Coaching'],
        
        'service2_title' => 'Visa & Immigration Services',
        'service2_desc' => 'Complete visa documentation, financial proof preparation, mock interviews, and application tracking.',
        'service2_points' => ['Visa Assessment', 'Document Checklist', 'Financial Documentation', 'Mock Interviews', 'Application Tracking'],
        
        'service3_title' => 'Scholarship & Financial Aid',
        'service3_desc' => 'Scholarship search, application assistance, education loan processing, and financial planning.',
        'service3_points' => ['Scholarship Search', 'Essay Assistance', 'Loan Processing', 'Financial Planning', 'Grant Applications'],
        
        'service4_title' => 'Credit Transfer Evaluation',
        'service4_desc' => 'Transcript evaluation, course equivalency assessment, and transfer credit maximization.',
        'service4_points' => ['Transcript Review', 'Credit Evaluation', 'University Coordination', 'Maximization Strategy', 'Transfer Approval'],
        
        'service5_title' => 'Career & Job Placement',
        'service5_desc' => 'Resume building, interview preparation, job search strategy, and employer networking.',
        'service5_points' => ['Resume Building', 'Interview Preparation', 'Job Search Strategy', 'Employer Networking', 'Offer Negotiation'],
        
        'service6_title' => 'Pre-Departure Services',
        'service6_desc' => 'Accommodation arrangement, flight bookings, insurance, and cultural orientation.',
        'service6_points' => ['Accommodation', 'Flight Booking', 'Insurance', 'Banking Setup', 'Cultural Orientation'],
        
        // Service Features
        'features_title' => 'Why Our Services Stand Out',
        'features_subtitle' => 'What makes us different',
        
        'feature1_title' => 'Personalized Approach',
        'feature1_desc' => 'Custom solutions tailored to your unique needs and goals',
        
        'feature2_title' => 'Expert Team',
        'feature2_desc' => 'Certified professionals with extensive industry experience',
        
        'feature3_title' => 'Transparent Process',
        'feature3_desc' => 'Clear communication and regular updates throughout',
        
        'feature4_title' => 'Global Network',
        'feature4_desc' => 'Direct connections with universities and employers worldwide',
        
        'feature5_title' => 'Proven Results',
        'feature5_desc' => 'Track record of successful placements and satisfied clients',
        
        'feature6_title' => 'Comprehensive Support',
        'feature6_desc' => 'End-to-end assistance from start to finish',
        
        // Packages
        'packages_title' => 'Service Packages',
        'packages_subtitle' => 'Flexible options to suit your needs',
        
        'package1_title' => 'Basic Package',
        'package1_price' => 'From $999',
        'package1_desc' => 'Essential services for budget-conscious students',
        'package1_points' => ['University Shortlisting', 'Application Review', 'Basic Document Check', 'Email Support'],
        
        'package2_title' => 'Standard Package',
        'package2_price' => 'From $1,999',
        'package2_desc' => 'Comprehensive support for most students',
        'package2_points' => ['Full Application Support', 'Visa Assistance', 'Scholarship Search', 'Priority Support'],
        
        'package3_title' => 'Premium Package',
        'package3_price' => 'From $2,999',
        'package3_desc' => 'Complete end-to-end service package',
        'package3_points' => ['All Services Included', 'Dedicated Advisor', 'Airport Pickup', '24/7 Support'],
        
        // Testimonials
        'testimonials_title' => 'Client Success Stories',
        'testimonials_subtitle' => 'What our clients say about our services',
        
        'testimonial1' => 'The visa assistance was exceptional. Got my Canadian study visa in just 3 weeks!',
        'testimonial1_name' => 'Maria Rodriguez',
        'testimonial1_service' => 'Visa Processing',
        
        'testimonial2' => 'ScholarSync Global helped me secure 3 university offers with scholarships totaling $50,000.',
        'testimonial2_name' => 'James Wilson',
        'testimonial2_service' => 'University Applications',
        
        'testimonial3' => 'Career services placed me in a tech job in Germany within 2 months of graduation.',
        'testimonial3_name' => 'Ahmed Khan',
        'testimonial3_service' => 'Job Placement',
        
        // FAQ
        'faq_title' => 'Frequently Asked Questions',
        'faq_subtitle' => 'Common questions about our services',
        
        'faq1_q' => 'How long does the university application process take?',
        'faq1_a' => 'Typically 4-8 weeks for complete application preparation, depending on program requirements.',
        
        'faq2_q' => 'What is your visa success rate?',
        'faq2_a' => 'We maintain a 98% success rate for student visas across all major destinations.',
        
        'faq3_q' => 'Do you guarantee scholarship awards?',
        'faq3_a' => 'While we cannot guarantee awards, 85% of our scholarship applicants receive funding.',
        
        'faq4_q' => 'Can you help with family relocation?',
        'faq4_a' => 'Yes, we provide complete family relocation support including spouse visas and school admissions.',
        
        'faq5_q' => 'What makes your services different from other consultancies?',
        'faq5_a' => 'Our personalized approach, expert team, and comprehensive end-to-end support set us apart.',
        
        // CTA
        'cta_title' => 'Ready to Start Your Journey?',
        'cta_description' => 'Get personalized service recommendations based on your profile and goals',
        'cta_button' => 'Get Service Recommendation',
        'cta_button2' => 'Download Service Catalog',
        
        // Page Metadata
        'page_description' => 'Comprehensive international education services at ScholarSync Global - university applications, visa processing, scholarships, career services, and more.',
        'page_title' => 'Services - ScholarSync Global',
    ],
    
    'fr' => [
        // Hero Section
        'hero_title' => 'Nos Services Complets',
        'hero_subtitle' => 'Support complet pour votre parcours éducatif mondial',
        'hero_description' => 'Des candidatures universitaires au placement professionnel, nous fournissons des solutions complètes.',
        
        // Stats
        'stats_services' => 'Services Offerts',
        'stats_success' => 'Taux de Réussite',
        'stats_clients' => 'Clients Satisfaits',
        'stats_countries' => 'Pays Desservis',
        
        // Main Services
        'main_services_title' => 'Services Principaux',
        'main_services_subtitle' => 'Notre gamme complète de services éducatifs',
        
        // Service Categories
        'categories_title' => 'Catégories de Services',
        'categories_subtitle' => 'Solutions complètes pour chaque étape',
        
        'category1_title' => 'Admissions & Candidatures',
        'category1_desc' => 'Support complet de la sélection à la soumission',
        'category1_count' => '5 000+ Réussites',
        
        'category2_title' => 'Visa & Immigration',
        'category2_desc' => 'Guidance experte pour visas études, travail et famille',
        'category2_count' => '98% Taux d\'Appro.',
        
        'category3_title' => 'Solutions Financières',
        'category3_desc' => 'Assistance bourses et programmes de prêts éducatifs',
        'category3_count' => '15M$+ Obtenus',
        
        'category4_title' => 'Services de Carrière',
        'category4_desc' => 'Placement, stages et conseil professionnel',
        'category4_count' => '2 000+ Placements',
        
        'category5_title' => 'Support Académique',
        'category5_desc' => 'Transferts de crédits et planification académique',
        'category5_count' => '1 500+ Transferts',
        
        'category6_title' => 'Voyage & Logistique',
        'category6_desc' => 'Réservations vols, logement et assistance aéroport',
        'category6_count' => '3 000+ Arrivées',
        
        // Service Process
        'process_title' => 'Notre Processus de Service',
        'process_subtitle' => 'Comment nous obtenons des résultats exceptionnels',
        
        'process_step1' => 'Consultation & Évaluation',
        'process_step2' => 'Développement de Stratégie',
        'process_step3' => 'Préparation des Documents',
        'process_step4' => 'Soumission de Candidature',
        'process_step5' => 'Suivi & Support',
        'process_step6' => 'Assistance Post-Arrivée',
        
        'process_desc1' => 'Analyse détaillée de votre profil et objectifs',
        'process_desc2' => 'Feuille de route personnalisée pour votre succès',
        'process_desc3' => 'Préparation professionnelle de tous documents',
        'process_desc4' => 'Soumission ponctuelle avec assurance qualité',
        'process_desc5' => 'Mises à jour régulières et communication proactive',
        'process_desc6' => 'Continuation du support après arrivée',
        
        // Detailed Services
        'detailed_title' => 'Services Détaillés',
        'detailed_subtitle' => 'Support approfondi dans tous les domaines',
        
        'service1_title' => 'Sélection & Candidature Universitaire',
        'service1_desc' => 'Matching stratégique, rédaction d\'essais, lettres de recommandation et préparation entretiens.',
        'service1_points' => ['Évaluation Profil', 'Sélection Universités', 'Préparation Documents', 'Revue Candidature', 'Coaching Entretien'],
        
        'service2_title' => 'Services Visa & Immigration',
        'service2_desc' => 'Documentation complète, préparation preuves financières et entretiens simulés.',
        'service2_points' => ['Évaluation Visa', 'Checklist Documents', 'Documents Financiers', 'Entretiens Simulés', 'Suivi Candidature'],
        
        'service3_title' => 'Bourses & Aide Financière',
        'service3_desc' => 'Recherche bourses, assistance candidatures et traitement prêts éducatifs.',
        'service3_points' => ['Recherche Bourses', 'Assistance Essais', 'Traitement Prêts', 'Planification Financière', 'Candidatures Subventions'],
        
        'service4_title' => 'Évaluation Transfert Crédits',
        'service4_desc' => 'Évaluation relevés, équivalence cours et maximisation crédits transférés.',
        'service4_points' => ['Revue Relevés', 'Évaluation Crédits', 'Coordination Universitaire', 'Stratégie Maximisation', 'Approbation Transfert'],
        
        'service5_title' => 'Carrière & Placement Professionnel',
        'service5_desc' => 'Rédaction CV, préparation entretiens, stratégie recherche emploi et réseautage employeurs.',
        'service5_points' => ['Rédaction CV', 'Préparation Entretien', 'Stratégie Recherche', 'Réseautage Employeurs', 'Négociation Offre'],
        
        'service6_title' => 'Services Pré-Départ',
        'service6_desc' => 'Arrangement logement, réservations vols, assurance et orientation culturelle.',
        'service6_points' => ['Logement', 'Réservation Vol', 'Assurance', 'Configuration Bancaire', 'Orientation Culturelle'],
        
        // Service Features
        'features_title' => 'Pourquoi Nos Services Se Détachent',
        'features_subtitle' => 'Ce qui nous différencie',
        
        'feature1_title' => 'Approche Personnalisée',
        'feature1_desc' => 'Solutions sur mesure adaptées à vos besoins uniques',
        
        'feature2_title' => 'Équipe d\'Experts',
        'feature2_desc' => 'Professionnels certifiés avec expérience étendue',
        
        'feature3_title' => 'Processus Transparent',
        'feature3_desc' => 'Communication claire et mises à jour régulières',
        
        'feature4_title' => 'Réseau Mondial',
        'feature4_desc' => 'Connexions directes avec universités et employeurs',
        
        'feature5_title' => 'Résultats Éprouvés',
        'feature5_desc' => 'Historique de placements réussis et clients satisfaits',
        
        'feature6_title' => 'Support Complet',
        'feature6_desc' => 'Assistance de début à fin',
        
        // Packages
        'packages_title' => 'Forfaits de Services',
        'packages_subtitle' => 'Options flexibles adaptées à vos besoins',
        
        'package1_title' => 'Forfait Basique',
        'package1_price' => 'À partir de 999$',
        'package1_desc' => 'Services essentiels pour étudiants soucieux du budget',
        'package1_points' => ['Sélection Universités', 'Revue Candidature', 'Vérification Documents Basique', 'Support Email'],
        
        'package2_title' => 'Forfait Standard',
        'package2_price' => 'À partir de 1 999$',
        'package2_desc' => 'Support complet pour la plupart des étudiants',
        'package2_points' => ['Support Candidature Complet', 'Assistance Visa', 'Recherche Bourses', 'Support Prioritaire'],
        
        'package3_title' => 'Forfait Premium',
        'package3_price' => 'À partir de 2 999$',
        'package3_desc' => 'Service complet de début à fin',
        'package3_points' => ['Tous Services Inclus', 'Conseiller Dédié', 'Transfert Aéroport', 'Support 24/7'],
        
        // Testimonials
        'testimonials_title' => 'Histoires de Réussite',
        'testimonials_subtitle' => 'Ce que disent nos clients de nos services',
        
        'testimonial1' => 'L\'assistance visa était exceptionnelle. Visa canadien en 3 semaines seulement!',
        'testimonial1_name' => 'Maria Rodriguez',
        'testimonial1_service' => 'Traitement Visa',
        
        'testimonial2' => 'ScholarSync Global m\'a aidé à obtenir 3 offres universitaires avec bourses totalisant 50 000$.',
        'testimonial2_name' => 'James Wilson',
        'testimonial2_service' => 'Candidatures Universitaires',
        
        'testimonial3' => 'Les services carrière m\'ont placé en Allemagne en 2 mois après diplôme.',
        'testimonial3_name' => 'Ahmed Khan',
        'testimonial3_service' => 'Placement Professionnel',
        
        // FAQ
        'faq_title' => 'Questions Fréquentes',
        'faq_subtitle' => 'Questions communes sur nos services',
        
        'faq1_q' => 'Combien de temps prend le processus de candidature universitaire?',
        'faq1_a' => 'Typiquement 4-8 semaines pour la préparation complète, selon les exigences du programme.',
        
        'faq2_q' => 'Quel est votre taux de succès de visa?',
        'faq2_a' => 'Nous maintenons un taux de succès de 98% pour les visas étudiants dans toutes destinations.',
        
        'faq3_q' => 'Garantissez-vous l\'obtention de bourses?',
        'faq3_a' => 'Bien que nous ne puissions garantir les bourses, 85% de nos candidats en reçoivent.',
        
        'faq4_q' => 'Pouvez-vous aider avec la relocalisation familiale?',
        'faq4_a' => 'Oui, nous fournissons un support complet incluant visas conjoint et admissions scolaires.',
        
        'faq5_q' => 'Qu\'est-ce qui différencie vos services des autres conseils?',
        'faq5_a' => 'Notre approche personnalisée, équipe experte et support complet de début à fin nous distinguent.',
        
        // CTA
        'cta_title' => 'Prêt à Commencer Votre Voyage?',
        'cta_description' => 'Obtenez des recommandations de services personnalisées basées sur votre profil',
        'cta_button' => 'Obtenir Recommandation',
        'cta_button2' => 'Télécharger Catalogue',
        
        // Page Metadata
        'page_description' => 'Services complets d\'éducation internationale - candidatures universitaires, traitement visas, bourses, services carrière et plus.',
        'page_title' => 'Services - ScholarSync Global',
    ]
];

// Function to get services page translation
function st($key) {
    global $services_translations, $current_lang;
    
    // Fallback to English if key missing
    if (isset($services_translations[$current_lang][$key])) {
        return $services_translations[$current_lang][$key];
    } elseif (isset($services_translations['en'][$key])) {
        return $services_translations['en'][$key];
    }
    
    return $key; // Return key itself as last resort
}

// Define main services
$main_services = [
    [
        'title_key' => 'service1_title',
        'icon' => 'fas fa-university',
        'description_key' => 'service1_desc',
        'color' => '#427431',
        'points_keys' => ['service1_point1', 'service1_point2', 'service1_point3', 'service1_point4', 'service1_point5']
    ],
    [
        'title_key' => 'service2_title',
        'icon' => 'fas fa-passport',
        'description_key' => 'service2_desc',
        'color' => '#3661B9',
        'points_keys' => ['service2_point1', 'service2_point2', 'service2_point3', 'service2_point4', 'service2_point5']
    ],
    [
        'title_key' => 'service3_title',
        'icon' => 'fas fa-award',
        'description_key' => 'service3_desc',
        'color' => '#2f5a26',
        'points_keys' => ['service3_point1', 'service3_point2', 'service3_point3', 'service3_point4', 'service3_point5']
    ]
];

// Define detailed services
$detailed_services = [
    [
        'title_key' => 'service1_title',
        'icon' => 'fas fa-graduation-cap',
        'description_key' => 'service1_desc',
        'points_keys' => ['service1_point1', 'service1_point2', 'service1_point3', 'service1_point4', 'service1_point5']
    ],
    [
        'title_key' => 'service2_title',
        'icon' => 'fas fa-plane-departure',
        'description_key' => 'service2_desc',
        'points_keys' => ['service2_point1', 'service2_point2', 'service2_point3', 'service2_point4', 'service2_point5']
    ],
    [
        'title_key' => 'service3_title',
        'icon' => 'fas fa-hand-holding-usd',
        'description_key' => 'service3_desc',
        'points_keys' => ['service3_point1', 'service3_point2', 'service3_point3', 'service3_point4', 'service3_point5']
    ],
    [
        'title_key' => 'service4_title',
        'icon' => 'fas fa-exchange-alt',
        'description_key' => 'service4_desc',
        'points_keys' => ['service4_point1', 'service4_point2', 'service4_point3', 'service4_point4', 'service4_point5']
    ],
    [
        'title_key' => 'service5_title',
        'icon' => 'fas fa-briefcase',
        'description_key' => 'service5_desc',
        'points_keys' => ['service5_point1', 'service5_point2', 'service5_point3', 'service5_point4', 'service5_point5']
    ],
    [
        'title_key' => 'service6_title',
        'icon' => 'fas fa-suitcase-rolling',
        'description_key' => 'service6_desc',
        'points_keys' => ['service6_point1', 'service6_point2', 'service6_point3', 'service6_point4', 'service6_point5']
    ]
];

// Define testimonials
$testimonials = [
    ['key' => 'testimonial1', 'initial' => 'MR'],
    ['key' => 'testimonial2', 'initial' => 'JW'],
    ['key' => 'testimonial3', 'initial' => 'AK']
];

// Define FAQs
$faqs = [
    ['q_key' => 'faq1_q', 'a_key' => 'faq1_a'],
    ['q_key' => 'faq2_q', 'a_key' => 'faq2_a'],
    ['q_key' => 'faq3_q', 'a_key' => 'faq3_a'],
    ['q_key' => 'faq4_q', 'a_key' => 'faq4_a'],
    ['q_key' => 'faq5_q', 'a_key' => 'faq5_a']
];
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo st('page_description'); ?>">
<title><?php echo st('page_title'); ?></title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ============================================
   SERVICES PAGE STYLES
   Professional, service-oriented design
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
.services-hero {
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

/* ===== MAIN SERVICES ===== */
.main-services-section {
  background: white;
}

.main-services-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 40px;
  margin-top: 50px;
}

.main-service-card {
  background: var(--bg);
  padding: 50px 40px;
  border-radius: 20px;
  border: 1px solid var(--border-light);
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.main-service-card:hover {
  transform: translateY(-10px);
  box-shadow: var(--shadow-lg);
}

.main-service-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--accent-gold), var(--accent-teal));
}

.service-header {
  display: flex;
  align-items: flex-start;
  gap: 20px;
  margin-bottom: 25px;
}

.service-icon {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, var(--primary-light), var(--teal-light));
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  color: var(--primary-navy);
  flex-shrink: 0;
  transition: var(--transition);
}

.main-service-card:hover .service-icon {
  transform: rotate(10deg) scale(1.1);
}

.service-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 10px;
}

.service-description {
  color: var(--text-light);
  line-height: 1.7;
  margin-bottom: 25px;
  font-size: 1.05rem;
}

.service-points {
  list-style: none;
  margin-bottom: 30px;
}

.service-points li {
  padding: 10px 0;
  padding-left: 32px;
  position: relative;
  color: var(--text);
  font-size: 0.95rem;
  border-bottom: 1px solid var(--border-light);
}

.service-points li:last-child {
  border-bottom: none;
}

.service-points li::before {
  content: '✓';
  position: absolute;
  left: 0;
  color: var(--accent-teal);
  font-weight: 800;
  font-size: 1.2rem;
}

.service-action {
  margin-top: 25px;
}

.service-button {
  padding: 14px 28px;
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
  text-decoration: none;
}

.service-button:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 20px rgba(1, 47, 107, 0.3);
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

/* ===== PROCESS SECTION ===== */
.process-section {
  background: white;
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
  top: 60px;
  left: 50px;
  right: 50px;
  height: 3px;
  background: var(--border);
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
  width: 120px;
  height: 120px;
  margin: 0 auto 20px;
  background: linear-gradient(135deg, var(--primary-light), var(--teal-light));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  font-weight: 700;
  color: var(--primary-navy);
  border: 8px solid white;
  box-shadow: var(--shadow-md);
  transition: var(--transition);
}

@media (max-width: 768px) {
  .step-circle {
    margin: 0;
    flex-shrink: 0;
    width: 80px;
    height: 80px;
    font-size: 1.5rem;
  }
}

.process-step:hover .step-circle {
  transform: scale(1.1);
  background: linear-gradient(135deg, var(--primary-navy), var(--secondary-blue));
  color: white;
}

.step-content {
  max-width: 200px;
  margin: 0 auto;
}

@media (max-width: 768px) {
  .step-content {
    max-width: none;
    margin: 0;
  }
}

.process-step h4 {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 10px;
}

.process-step p {
  color: var(--text-light);
  font-size: 0.9rem;
  line-height: 1.5;
}

/* ===== DETAILED SERVICES ===== */
.detailed-section {
  background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
}

.detailed-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.detailed-card {
  background: white;
  padding: 40px 35px;
  border-radius: 20px;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-light);
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.detailed-card:hover {
  transform: translateY(-10px);
  box-shadow: var(--shadow-lg);
}

.detailed-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 6px;
  height: 100%;
  background: linear-gradient(to bottom, var(--primary-navy), var(--accent-teal));
}

.detailed-header {
  display: flex;
  align-items: flex-start;
  gap: 20px;
  margin-bottom: 25px;
}

.detailed-icon {
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

.detailed-card:hover .detailed-icon {
  transform: rotate(10deg) scale(1.1);
}

.detailed-card h3 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 10px;
}

.detailed-card p {
  color: var(--text-light);
  line-height: 1.6;
  margin-bottom: 25px;
  font-size: 1rem;
}

.detailed-points {
  list-style: none;
}

.detailed-points li {
  padding: 8px 0;
  padding-left: 28px;
  position: relative;
  color: var(--text);
  font-size: 0.95rem;
}

.detailed-points li::before {
  content: '•';
  position: absolute;
  left: 0;
  color: var(--accent-teal);
  font-weight: 800;
  font-size: 1.5rem;
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

/* ===== PACKAGES SECTION ===== */
.packages-section {
  background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
}

.packages-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.package-card {
  background: white;
  padding: 50px 40px;
  border-radius: 20px;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-light);
  transition: var(--transition);
  position: relative;
  overflow: hidden;
  text-align: center;
}

.package-card:hover {
  transform: translateY(-10px);
  box-shadow: var(--shadow-lg);
}

.package-card.featured {
  border: 2px solid var(--accent-gold);
  transform: scale(1.05);
}

.package-card.featured:hover {
  transform: scale(1.05) translateY(-10px);
}

.package-header {
  margin-bottom: 30px;
}

.package-card h3 {
  font-size: 1.8rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 15px;
}

.package-price {
  font-size: 2.5rem;
  font-weight: 800;
  color: var(--accent-gold);
  margin-bottom: 10px;
}

.package-desc {
  color: var(--text-light);
  line-height: 1.6;
  margin-bottom: 30px;
  font-size: 1rem;
}

.package-features {
  list-style: none;
  margin-bottom: 40px;
  text-align: left;
}

.package-features li {
  padding: 12px 0;
  padding-left: 36px;
  position: relative;
  color: var(--text);
  font-size: 1rem;
  border-bottom: 1px solid var(--border-light);
}

.package-features li:last-child {
  border-bottom: none;
}

.package-features li::before {
  content: '✓';
  position: absolute;
  left: 0;
  color: var(--accent-teal);
  font-weight: 800;
  font-size: 1.2rem;
}

.package-button {
  width: 100%;
  padding: 18px;
  border-radius: 12px;
  border: none;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
  font-size: 1.1rem;
}

.package-button.primary {
  background: linear-gradient(135deg, var(--primary-navy), var(--secondary-blue));
  color: white;
}

.package-button.primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 20px rgba(1, 47, 107, 0.3);
}

.package-button.secondary {
  background: white;
  color: var(--primary-navy);
  border: 2px solid var(--border);
}

.package-button.secondary:hover {
  background: var(--primary-light);
  border-color: var(--primary-navy);
  transform: translateY(-3px);
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

/* ===== FAQ SECTION ===== */
.faq-section {
  background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
}

.faq-container {
  max-width: 800px;
  margin: 0 auto;
}

.faq-item {
  background: white;
  margin-bottom: 20px;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid var(--border-light);
  transition: var(--transition);
}

.faq-item:hover {
  box-shadow: var(--shadow-md);
}

.faq-question {
  padding: 25px 30px;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: white;
}

.faq-question h4 {
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--primary-navy);
  margin: 0;
}

.faq-icon {
  color: var(--accent-teal);
  font-size: 1.2rem;
  transition: var(--transition);
}

.faq-answer {
  padding: 0 30px;
  max-height: 0;
  overflow: hidden;
  transition: all 0.3s ease;
  background: white;
}

.faq-answer p {
  color: var(--text-light);
  line-height: 1.6;
  padding-bottom: 25px;
}

.faq-item.active .faq-answer {
  max-height: 500px;
}

.faq-item.active .faq-icon {
  transform: rotate(180deg);
}

/* ===== CTA SECTION ===== */
.services-cta {
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

.cta-button-white {
  background: white;
  color: var(--primary-navy);
  box-shadow: 0 8px 25px rgba(255, 255, 255, 0.1);
}

.cta-button-white:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 35px rgba(255, 255, 255, 0.2);
}

.cta-button-outline {
  background: transparent;
  color: white;
  border: 2px solid rgba(255, 255, 255, 0.3);
}

.cta-button-outline:hover {
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
}

@media (max-width: 992px) {
  .section-title {
    font-size: 2.2rem;
  }
  
  .hero-content h1 {
    font-size: 2.5rem;
  }
  
  .categories-grid,
  .detailed-grid,
  .packages-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .package-card.featured {
    transform: scale(1);
  }
  
  .package-card.featured:hover {
    transform: scale(1) translateY(-10px);
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
  .detailed-grid,
  .packages-grid,
  .main-services-grid,
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
  
  .main-service-card,
  .detailed-card,
  .package-card {
    padding: 30px 25px;
  }
}
</style>
</head>
<body>

<!-- Hero Section -->
<section class="services-hero">
  <div class="hero-bg-pattern"></div>
  <div class="hero-content">
    <h1 class="fade-in"><?php echo st('hero_title'); ?></h1>
    <p class="hero-subtitle fade-in"><?php echo st('hero_subtitle'); ?></p>
    <p class="hero-description fade-in"><?php echo st('hero_description'); ?></p>
  </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
  <div class="container">
    <div class="stats-grid">
      <div class="stat-item fade-in">
        <div class="stat-number"><i class="fas fa-cogs"></i>25+</div>
        <div class="stat-label"><?php echo st('stats_services'); ?></div>
      </div>
      <div class="stat-item fade-in">
        <div class="stat-number"><i class="fas fa-chart-line"></i>98%</div>
        <div class="stat-label"><?php echo st('stats_success'); ?></div>
      </div>
      <div class="stat-item fade-in">
        <div class="stat-number"><i class="fas fa-users"></i>5,000+</div>
        <div class="stat-label"><?php echo st('stats_clients'); ?></div>
      </div>
      <div class="stat-item fade-in">
        <div class="stat-number"><i class="fas fa-globe"></i>50+</div>
        <div class="stat-label"><?php echo st('stats_countries'); ?></div>
      </div>
    </div>
  </div>
</section>

<!-- Main Services -->
<section class="main-services-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo st('main_services_title'); ?></h2>
      <p class="section-subtitle"><?php echo st('main_services_subtitle'); ?></p>
    </div>
    
    <div class="main-services-grid">
      <div class="main-service-card fade-in">
        <div class="service-header">
          <div class="service-icon">
            <i class="fas fa-university"></i>
          </div>
          <div>
            <h3 class="service-title"><?php echo st('service1_title'); ?></h3>
            <p class="service-description"><?php echo st('service1_desc'); ?></p>
          </div>
        </div>
        
        <ul class="service-points">
          <?php 
          $points = st('service1_points');
          if (is_string($points)) {
              // Handle if it's a string (should be array)
              echo '<li>' . $points . '</li>';
          } else {
              for ($i = 1; $i <= 5; $i++):
                  $point_key = 'service1_point' . $i;
                  if (isset($services_translations[$current_lang][$point_key])) {
                      echo '<li>' . $services_translations[$current_lang][$point_key] . '</li>';
                  }
              endfor;
          }
          ?>
        </ul>
        
        <div class="service-action">
          <button class="service-button">
            <i class="fas fa-arrow-right"></i>
            <?php echo $current_lang === 'en' ? 'Learn More' : 'En Savoir Plus'; ?>
          </button>
        </div>
      </div>
      
      <div class="main-service-card fade-in">
        <div class="service-header">
          <div class="service-icon">
            <i class="fas fa-passport"></i>
          </div>
          <div>
            <h3 class="service-title"><?php echo st('service2_title'); ?></h3>
            <p class="service-description"><?php echo st('service2_desc'); ?></p>
          </div>
        </div>
        
        <ul class="service-points">
          <?php for ($i = 1; $i <= 5; $i++):
              $point_key = 'service2_point' . $i;
              if (isset($services_translations[$current_lang][$point_key])) {
                  echo '<li>' . $services_translations[$current_lang][$point_key] . '</li>';
              }
          endfor; ?>
        </ul>
        
        <div class="service-action">
          <button class="service-button">
            <i class="fas fa-arrow-right"></i>
            <?php echo $current_lang === 'en' ? 'Learn More' : 'En Savoir Plus'; ?>
          </button>
        </div>
      </div>
      
      <div class="main-service-card fade-in">
        <div class="service-header">
          <div class="service-icon">
            <i class="fas fa-award"></i>
          </div>
          <div>
            <h3 class="service-title"><?php echo st('service3_title'); ?></h3>
            <p class="service-description"><?php echo st('service3_desc'); ?></p>
          </div>
        </div>
        
        <ul class="service-points">
          <?php for ($i = 1; $i <= 5; $i++):
              $point_key = 'service3_point' . $i;
              if (isset($services_translations[$current_lang][$point_key])) {
                  echo '<li>' . $services_translations[$current_lang][$point_key] . '</li>';
              }
          endfor; ?>
        </ul>
        
        <div class="service-action">
          <button class="service-button">
            <i class="fas fa-arrow-right"></i>
            <?php echo $current_lang === 'en' ? 'Learn More' : 'En Savoir Plus'; ?>
          </button>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Categories Section -->
<section class="categories-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo st('categories_title'); ?></h2>
      <p class="section-subtitle"><?php echo st('categories_subtitle'); ?></p>
    </div>
    
    <div class="categories-grid">
      <div class="category-card fade-in">
        <div class="category-icon">
          <i class="fas fa-graduation-cap"></i>
        </div>
        <h3><?php echo st('category1_title'); ?></h3>
        <p><?php echo st('category1_desc'); ?></p>
        <span class="category-count"><?php echo st('category1_count'); ?></span>
      </div>
      
      <div class="category-card fade-in">
        <div class="category-icon">
          <i class="fas fa-plane-departure"></i>
        </div>
        <h3><?php echo st('category2_title'); ?></h3>
        <p><?php echo st('category2_desc'); ?></p>
        <span class="category-count"><?php echo st('category2_count'); ?></span>
      </div>
      
      <div class="category-card fade-in">
        <div class="category-icon">
          <i class="fas fa-hand-holding-usd"></i>
        </div>
        <h3><?php echo st('category3_title'); ?></h3>
        <p><?php echo st('category3_desc'); ?></p>
        <span class="category-count"><?php echo st('category3_count'); ?></span>
      </div>
      
      <div class="category-card fade-in">
        <div class="category-icon">
          <i class="fas fa-briefcase"></i>
        </div>
        <h3><?php echo st('category4_title'); ?></h3>
        <p><?php echo st('category4_desc'); ?></p>
        <span class="category-count"><?php echo st('category4_count'); ?></span>
      </div>
      
      <div class="category-card fade-in">
        <div class="category-icon">
          <i class="fas fa-exchange-alt"></i>
        </div>
        <h3><?php echo st('category5_title'); ?></h3>
        <p><?php echo st('category5_desc'); ?></p>
        <span class="category-count"><?php echo st('category5_count'); ?></span>
      </div>
      
      <div class="category-card fade-in">
        <div class="category-icon">
          <i class="fas fa-suitcase-rolling"></i>
        </div>
        <h3><?php echo st('category6_title'); ?></h3>
        <p><?php echo st('category6_desc'); ?></p>
        <span class="category-count"><?php echo st('category6_count'); ?></span>
      </div>
    </div>
  </div>
</section>

<!-- Process Section -->
<section class="process-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo st('process_title'); ?></h2>
      <p class="section-subtitle"><?php echo st('process_subtitle'); ?></p>
    </div>
    
    <div class="process-steps">
      <div class="process-track">
        <div class="process-step fade-in">
          <div class="step-circle">1</div>
          <div class="step-content">
            <h4><?php echo st('process_step1'); ?></h4>
            <p><?php echo st('process_desc1'); ?></p>
          </div>
        </div>
        
        <div class="process-step fade-in">
          <div class="step-circle">2</div>
          <div class="step-content">
            <h4><?php echo st('process_step2'); ?></h4>
            <p><?php echo st('process_desc2'); ?></p>
          </div>
        </div>
        
        <div class="process-step fade-in">
          <div class="step-circle">3</div>
          <div class="step-content">
            <h4><?php echo st('process_step3'); ?></h4>
            <p><?php echo st('process_desc3'); ?></p>
          </div>
        </div>
        
        <div class="process-step fade-in">
          <div class="step-circle">4</div>
          <div class="step-content">
            <h4><?php echo st('process_step4'); ?></h4>
            <p><?php echo st('process_desc4'); ?></p>
          </div>
        </div>
        
        <div class="process-step fade-in">
          <div class="step-circle">5</div>
          <div class="step-content">
            <h4><?php echo st('process_step5'); ?></h4>
            <p><?php echo st('process_desc5'); ?></p>
          </div>
        </div>
        
        <div class="process-step fade-in">
          <div class="step-circle">6</div>
          <div class="step-content">
            <h4><?php echo st('process_step6'); ?></h4>
            <p><?php echo st('process_desc6'); ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Detailed Services -->
<section class="detailed-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo st('detailed_title'); ?></h2>
      <p class="section-subtitle"><?php echo st('detailed_subtitle'); ?></p>
    </div>
    
    <div class="detailed-grid">
      <?php foreach($detailed_services as $service): ?>
      <div class="detailed-card fade-in">
        <div class="detailed-header">
          <div class="detailed-icon">
            <i class="<?php echo $service['icon']; ?>"></i>
          </div>
          <div>
            <h3><?php echo st($service['title_key']); ?></h3>
            <p><?php echo st($service['description_key']); ?></p>
          </div>
        </div>
        
        <ul class="detailed-points">
          <?php 
          // Dynamically get points based on service number
          $service_num = substr($service['title_key'], -1);
          for ($i = 1; $i <= 5; $i++):
              $point_key = 'service' . $service_num . '_point' . $i;
              if (isset($services_translations[$current_lang][$point_key])) {
                  echo '<li>' . $services_translations[$current_lang][$point_key] . '</li>';
              }
          endfor;
          ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Features Section -->
<section class="features-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo st('features_title'); ?></h2>
      <p class="section-subtitle"><?php echo st('features_subtitle'); ?></p>
    </div>
    
    <div class="features-grid">
      <div class="feature-item fade-in">
        <div class="feature-icon">
          <i class="fas fa-user-cog"></i>
        </div>
        <h4><?php echo st('feature1_title'); ?></h4>
        <p><?php echo st('feature1_desc'); ?></p>
      </div>
      
      <div class="feature-item fade-in">
        <div class="feature-icon">
          <i class="fas fa-user-tie"></i>
        </div>
        <h4><?php echo st('feature2_title'); ?></h4>
        <p><?php echo st('feature2_desc'); ?></p>
      </div>
      
      <div class="feature-item fade-in">
        <div class="feature-icon">
          <i class="fas fa-eye"></i>
        </div>
        <h4><?php echo st('feature3_title'); ?></h4>
        <p><?php echo st('feature3_desc'); ?></p>
      </div>
      
      <div class="feature-item fade-in">
        <div class="feature-icon">
          <i class="fas fa-globe"></i>
        </div>
        <h4><?php echo st('feature4_title'); ?></h4>
        <p><?php echo st('feature4_desc'); ?></p>
      </div>
      
      <div class="feature-item fade-in">
        <div class="feature-icon">
          <i class="fas fa-chart-line"></i>
        </div>
        <h4><?php echo st('feature5_title'); ?></h4>
        <p><?php echo st('feature5_desc'); ?></p>
      </div>
      
      <div class="feature-item fade-in">
        <div class="feature-icon">
          <i class="fas fa-road"></i>
        </div>
        <h4><?php echo st('feature6_title'); ?></h4>
        <p><?php echo st('feature6_desc'); ?></p>
      </div>
    </div>
  </div>
</section>

<!-- Packages Section -->
<section class="packages-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo st('packages_title'); ?></h2>
      <p class="section-subtitle"><?php echo st('packages_subtitle'); ?></p>
    </div>
    
    <div class="packages-grid">
      <div class="package-card fade-in">
        <div class="package-header">
          <h3><?php echo st('package1_title'); ?></h3>
          <div class="package-price"><?php echo st('package1_price'); ?></div>
          <p class="package-desc"><?php echo st('package1_desc'); ?></p>
        </div>
        
        <ul class="package-features">
          <?php for ($i = 1; $i <= 4; $i++):
              $point_key = 'package1_point' . $i;
              if (isset($services_translations[$current_lang][$point_key])) {
                  echo '<li>' . $services_translations[$current_lang][$point_key] . '</li>';
              }
          endfor; ?>
        </ul>
        
        <button class="package-button secondary">
          <?php echo $current_lang === 'en' ? 'Get Started' : 'Commencer'; ?>
        </button>
      </div>
      
      <div class="package-card featured fade-in">
        <div class="package-header">
          <h3><?php echo st('package2_title'); ?></h3>
          <div class="package-price"><?php echo st('package2_price'); ?></div>
          <p class="package-desc"><?php echo st('package2_desc'); ?></p>
        </div>
        
        <ul class="package-features">
          <?php for ($i = 1; $i <= 4; $i++):
              $point_key = 'package2_point' . $i;
              if (isset($services_translations[$current_lang][$point_key])) {
                  echo '<li>' . $services_translations[$current_lang][$point_key] . '</li>';
              }
          endfor; ?>
        </ul>
        
        <button class="package-button primary">
          <?php echo $current_lang === 'en' ? 'Most Popular' : 'Le Plus Populaire'; ?>
        </button>
      </div>
      
      <div class="package-card fade-in">
        <div class="package-header">
          <h3><?php echo st('package3_title'); ?></h3>
          <div class="package-price"><?php echo st('package3_price'); ?></div>
          <p class="package-desc"><?php echo st('package3_desc'); ?></p>
        </div>
        
        <ul class="package-features">
          <?php for ($i = 1; $i <= 4; $i++):
              $point_key = 'package3_point' . $i;
              if (isset($services_translations[$current_lang][$point_key])) {
                  echo '<li>' . $services_translations[$current_lang][$point_key] . '</li>';
              }
          endfor; ?>
        </ul>
        
        <button class="package-button secondary">
          <?php echo $current_lang === 'en' ? 'Get Premium' : 'Obtenir Premium'; ?>
        </button>
      </div>
    </div>
  </div>
</section>

<!-- Testimonials -->
<section class="testimonials-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo st('testimonials_title'); ?></h2>
      <p class="section-subtitle"><?php echo st('testimonials_subtitle'); ?></p>
    </div>
    
    <div class="testimonials-grid">
      <?php foreach($testimonials as $testimonial): ?>
      <div class="testimonial-card fade-in">
        <p class="testimonial-text"><?php echo st($testimonial['key']); ?></p>
        <div class="testimonial-author">
          <div class="author-avatar"><?php echo $testimonial['initial']; ?></div>
          <div class="author-info">
            <h5><?php echo st($testimonial['key'] . '_name'); ?></h5>
            <p><?php echo st($testimonial['key'] . '_service'); ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FAQ Section -->
<section class="faq-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo st('faq_title'); ?></h2>
      <p class="section-subtitle"><?php echo st('faq_subtitle'); ?></p>
    </div>
    
    <div class="faq-container">
      <?php foreach($faqs as $index => $faq): ?>
      <div class="faq-item fade-in">
        <div class="faq-question">
          <h4><?php echo st($faq['q_key']); ?></h4>
          <span class="faq-icon"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="faq-answer">
          <p><?php echo st($faq['a_key']); ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA Section -->
<section class="services-cta">
  <div class="cta-content">
    <h2 class="fade-in"><?php echo st('cta_title'); ?></h2>
    <p class="fade-in"><?php echo st('cta_description'); ?></p>
    <div class="cta-buttons">
      <button class="cta-button cta-button-white fade-in" onclick="window.location.href='service-recommendation.php'">
        <i class="fas fa-magic"></i>
        <?php echo st('cta_button'); ?>
      </button>
      <button class="cta-button cta-button-outline fade-in" onclick="window.open('service-catalog.pdf', '_blank')">
        <i class="fas fa-download"></i>
        <?php echo st('cta_button2'); ?>
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

  // FAQ toggle functionality
  document.querySelectorAll('.faq-question').forEach(question => {
    question.addEventListener('click', () => {
      const faqItem = question.parentElement;
      faqItem.classList.toggle('active');
    });
  });

  // Service buttons click handlers
  document.querySelectorAll('.service-button').forEach(button => {
    button.addEventListener('click', function() {
      const serviceCard = this.closest('.main-service-card');
      const serviceTitle = serviceCard.querySelector('.service-title')?.textContent;
      if (serviceTitle) {
        window.location.href = `service-details.php?service=${encodeURIComponent(serviceTitle)}`;
      }
    });
  });

  // Package buttons click handlers
  document.querySelectorAll('.package-button').forEach(button => {
    button.addEventListener('click', function() {
      const packageCard = this.closest('.package-card');
      const packageTitle = packageCard.querySelector('h3')?.textContent;
      window.location.href = `consultation.php?package=${encodeURIComponent(packageTitle)}`;
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

  // Add hover effects to process steps
  document.querySelectorAll('.process-step').forEach(step => {
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
    const hero = document.querySelector('.services-hero');
    if (hero) {
      const rate = scrolled * 0.5;
      hero.style.backgroundPositionY = rate + 'px';
    }
  });

  // Initialize all FAQs as closed
  document.querySelectorAll('.faq-item').forEach(item => {
    item.classList.remove('active');
  });

})();
</script>

</body>
</html>