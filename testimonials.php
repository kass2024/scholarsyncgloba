<?php
// ============================================
// TESTIMONIALS PAGE WITH LANGUAGE SWITCHING
// ============================================
include 'header.php';

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/testimonials_lib.php';
$page_db_testimonials = pcvc_get_published_testimonials($conn, 24);

// Set page title with language switching
$pageTitle = $current_lang === 'en' ? 'Testimonials - ScholarSync Global' : 'Témoignages - ScholarSync Global';

// ============================================
// TRANSLATIONS FOR TESTIMONIALS PAGE
// ============================================

$testimonials_translations = [
    'en' => [
        // Hero Section
        'hero_title' => 'Customer Testimonials',
        'hero_subtitle' => 'Hear from customers who have achieved their dreams with us',
        'hero_description' => 'Real stories from international customers who transformed their lives through global education.',
        
        // Page Intro
        'intro_title' => 'Success Stories',
        'intro_description' => 'Our customers\' success is our greatest achievement. Read about their journeys and how ScholarSync Global helped them reach their academic and career goals.',
        'stats_customers' => 'Customers Helped',
        'stats_success' => 'Success Rate',
        'stats_countries' => 'Countries Represented',
        'stats_scholarships' => 'Scholarships Awarded',
        
        // Featured Testimonials
        'featured_title' => 'Featured Testimonials',
        'featured_subtitle' => 'Inspiring stories from our alumni',
        
        // Testimonial Categories
        'categories_title' => 'Customer Experiences By Category',
        'categories_subtitle' => 'Browse testimonials by study area',
        
        'category1_title' => 'Business & MBA',
        'category1_count' => '45+ Stories',
        
        'category2_title' => 'Engineering & Tech',
        'category2_count' => '38+ Stories',
        
        'category3_title' => 'Medical & Health',
        'category3_count' => '32+ Stories',
        
        'category4_title' => 'Arts & Humanities',
        'category4_count' => '28+ Stories',
        
        'category5_title' => 'Scholarship Success',
        'category5_count' => '65+ Stories',
        
        'category6_title' => 'Visa Success',
        'category6_count' => '95+ Stories',
        
        // Detailed Testimonials
        'testimonials_title' => 'Detailed Testimonials',
        'testimonials_subtitle' => 'In-depth stories from our customers',
        
        // Testimonial 1
        'testimonial1_name' => 'Alex Chen',
        'testimonial1_country' => 'China → USA',
        'testimonial1_program' => 'MBA at Harvard Business School',
        'testimonial1_quote' => 'ScholarSync Global didn\'t just help me get into Harvard - they helped me prepare for success. The interview coaching and essay guidance were invaluable. My counselor stayed with me through every step, even helping me negotiate my scholarship package.',
        'testimonial1_story' => 'After working in finance for 5 years, I wanted to pivot to tech leadership. ScholarSync Global helped me craft a compelling narrative that highlighted my transferable skills. The mock interviews were incredibly realistic and prepared me for the toughest questions.',
        'testimonial1_achievement' => 'Full scholarship + $15,000 stipend',
        
        // Testimonial 2
        'testimonial2_name' => 'Sarah Johnson',
        'testimonial2_country' => 'USA → Canada',
        'testimonial2_program' => 'MSc Computer Science at University of Toronto',
        'testimonial2_quote' => 'As a female in tech, I faced unique challenges. ScholarSync Global\'s advisors connected me with women in tech mentors and helped me highlight my leadership in diversity initiatives. The result? Acceptance to my top-choice program with funding!',
        'testimonial2_story' => 'I was hesitant about applying internationally, but ScholarSync Global\'s step-by-step guidance made the process manageable. They helped me identify research professors whose work aligned with my interests, which made my application stand out.',
        'testimonial2_achievement' => 'Research assistantship + tuition waiver',
        
        // Testimonial 3
        'testimonial3_name' => 'David Kim',
        'testimonial3_country' => 'South Korea → Germany',
        'testimonial3_program' => 'Mechanical Engineering at TU Munich',
        'testimonial3_quote' => 'Studying in Germany with zero tuition fees seemed too good to be true. ScholarSync Global helped me navigate the complex application requirements and taught me German to B1 level. Their visa success rate is truly remarkable.',
        'testimonial3_story' => 'The blocked account requirement and health insurance confused me, but my advisor walked me through every document. They even connected me with Korean customers already studying in Munich for peer support.',
        'testimonial3_achievement' => 'Admitted to tuition-free program',
        
        // Testimonial 4
        'testimonial4_name' => 'Maria Rodriguez',
        'testimonial4_country' => 'Mexico → UK',
        'testimonial4_program' => 'Medicine at University of Oxford',
        'testimonial4_quote' => 'Getting into Oxford Medical School felt impossible, but ScholarSync Global\'s team believed in me. They helped me prepare for the BMAT and interviews, and their connections with current customers gave me insider insights.',
        'testimonial4_story' => 'The UK medical application process is incredibly competitive for international customers. ScholarSync Global\'s advisors helped me showcase my clinical experience and research in a way that aligned with Oxford\'s values.',
        'testimonial4_achievement' => 'Conditional offer to Oxford Medicine',
        
        // Testimonial 5
        'testimonial5_name' => 'Kenji Tanaka',
        'testimonial5_country' => 'Japan → Australia',
        'testimonial5_program' => 'PhD in Environmental Science at ANU',
        'testimonial5_quote' => 'My research proposal was rejected three times before working with ScholarSync Global. Their academic writing experts helped me refine my methodology and connect with the right supervisor. Now I\'m conducting groundbreaking climate research.',
        'testimonial5_story' => 'The PhD application process in Australia requires finding a supervisor first. ScholarSync Global helped me identify professors whose research matched mine and coached me on how to approach them professionally.',
        'testimonial5_achievement' => 'Full PhD scholarship + living stipend',
        
        // Testimonial 6
        'testimonial6_name' => 'Aisha Mohammed',
        'testimonial6_country' => 'Nigeria → Netherlands',
        'testimonial6_program' => 'International Business at Rotterdam School of Management',
        'testimonial6_quote' => 'As an entrepreneur, I needed a program that valued practical experience over perfect grades. ScholarSync Global helped me build a portfolio that showcased my startup successes, which impressed the admissions committee.',
        'testimonial6_story' => 'The Dutch education system was unfamiliar to me, but ScholarSync Global\'s Netherlands specialist explained everything clearly. They even helped me find housing in Rotterdam before I arrived.',
        'testimonial6_achievement' => 'Entrepreneurship scholarship award',
        
        // Video Testimonials
        'video_title' => 'Video Testimonials',
        'video_subtitle' => 'Hear directly from our customers',
        'video1_title' => 'MBA Journey to Stanford',
        'video2_title' => 'Engineering Success in Canada',
        'video3_title' => 'Medical School Dream Realized',
        
        // Statistics Section
        'stats_title' => 'By The Numbers',
        'stats_subtitle' => 'Our impact in customer success',
        
        'stat1_title' => 'Admission Rate',
        'stat1_value' => '98%',
        'stat1_desc' => 'of our customers receive university offers',
        
        'stat2_title' => 'Visa Success',
        'stat2_value' => '96%',
        'stat2_desc' => 'first-time visa approval rate',
        
        'stat3_title' => 'Scholarship Value',
        'stat3_value' => '$4.2M+',
        'stat3_desc' => 'in scholarships secured for customers',
        
        'stat4_title' => 'Customer Satisfaction',
        'stat4_value' => '4.9/5',
        'stat4_desc' => 'average customer rating',
        
        // Common Quotes
        'common_quotes_title' => 'What Customers Say',
        'quote1' => 'ScholarSync Global made my dream of studying abroad a reality.',
        'quote2' => 'The support and guidance I received was exceptional.',
        'quote3' => 'I couldn\'t have done it without their help with scholarships and visas.',
        'quote4' => 'Professional, reliable, and truly committed to customer success.',
        'quote5' => 'They understood my background and tailored their approach.',
        'quote6' => 'The network of alumni and current customers was invaluable.',
        
        // Timeline Section
        'timeline_title' => 'Customer Journey Timeline',
        'timeline_subtitle' => 'From dream to reality',
        
        'timeline1_title' => 'Initial Consultation',
        'timeline1_desc' => 'Understanding goals and creating roadmap',
        
        'timeline2_title' => 'University Selection',
        'timeline2_desc' => 'Matching with ideal programs and locations',
        
        'timeline3_title' => 'Application Preparation',
        'timeline3_desc' => 'Essays, recommendations, and documentation',
        
        'timeline4_title' => 'Interview Coaching',
        'timeline4_desc' => 'Mock interviews and preparation',
        
        'timeline5_title' => 'Visa Assistance',
        'timeline5_desc' => 'Document preparation and interview practice',
        
        'timeline6_title' => 'Pre-departure Support',
        'timeline6_desc' => 'Housing, orientation, and transition',
        
        // CTA Section
        'cta_title' => 'Start Your Success Story',
        'cta_description' => 'Join thousands of customers who have achieved their dreams with ScholarSync Global',
        'cta_button1' => 'Book Free Consultation',
        'cta_button2' => 'View More Stories',
        
        // Page Metadata
        'page_description' => 'Read customer testimonials and success stories from ScholarSync Global. Real experiences from international customers who achieved their study abroad dreams.',
        'page_title' => 'Customer Testimonials - ScholarSync Global',

        'db_wall_title' => 'Client stories',
        'db_wall_sub' => 'Photos and quotes from people we have supported.',
    ],
    
    'fr' => [
        // Hero Section
        'hero_title' => 'Témoignages de Clients',
        'hero_subtitle' => 'Écoutez les clients qui ont réalisé leurs rêves avec nous',
        'hero_description' => 'Histoires réelles de clients internationaux qui ont transformé leur vie grâce à l\'éducation mondiale.',
        
        // Page Intro
        'intro_title' => 'Histoires de Réussite',
        'intro_description' => 'La réussite de nos clients est notre plus grande réussite. Découvrez leurs parcours et comment ScholarSync Global les a aidés à atteindre leurs objectifs académiques et professionnels.',
        'stats_customers' => 'Clients Aidés',
        'stats_success' => 'Taux de Réussite',
        'stats_countries' => 'Pays Représentés',
        'stats_scholarships' => 'Bourses Attribuées',
        
        // Featured Testimonials
        'featured_title' => 'Témoignages en Vedette',
        'featured_subtitle' => 'Histoires inspirantes de nos anciens',
        
        // Testimonial Categories
        'categories_title' => 'Expériences par Catégorie',
        'categories_subtitle' => 'Parcourez les témoignages par domaine d\'étude',
        
        'category1_title' => 'Commerce & MBA',
        'category1_count' => '45+ Histoires',
        
        'category2_title' => 'Ingénierie & Technologie',
        'category2_count' => '38+ Histoires',
        
        'category3_title' => 'Médical & Santé',
        'category3_count' => '32+ Histoires',
        
        'category4_title' => 'Arts & Sciences Humaines',
        'category4_count' => '28+ Histoires',
        
        'category5_title' => 'Réussite de Bourses',
        'category5_count' => '65+ Histoires',
        
        'category6_title' => 'Succès de Visa',
        'category6_count' => '95+ Histoires',
        
        // Detailed Testimonials
        'testimonials_title' => 'Témoignages Détaillés',
        'testimonials_subtitle' => 'Histoires approfondies de nos clients',
        
        // Testimonial 1
        'testimonial1_name' => 'Alex Chen',
        'testimonial1_country' => 'Chine → USA',
        'testimonial1_program' => 'MBA à Harvard Business School',
        'testimonial1_quote' => 'ScholarSync Global ne m\'a pas seulement aidé à entrer à Harvard - ils m\'ont préparé à réussir. Le coaching d\'entretien et les conseils pour les essais étaient inestimables. Mon conseiller est resté avec moi à chaque étape, m\'aidant même à négocier ma bourse.',
        'testimonial1_story' => 'Après 5 ans en finance, je voulais me tourner vers le leadership technologique. ScholarSync Global m\'a aidé à créer un récit convaincant mettant en valeur mes compétences transférables. Les simulations d\'entretien étaient incroyablement réalistes.',
        'testimonial1_achievement' => 'Bourse complète + 15 000$ de bourse de subsistance',
        
        // Testimonial 2
        'testimonial2_name' => 'Sarah Johnson',
        'testimonial2_country' => 'USA → Canada',
        'testimonial2_program' => 'MSc Informatique à l\'Université de Toronto',
        'testimonial2_quote' => 'En tant que femme en tech, j\'ai rencontré des défis uniques. Les conseillers de ScholarSync Global m\'ont connectée avec des mentors féminins en tech et m\'ont aidée à mettre en valeur mon leadership dans les initiatives de diversité.',
        'testimonial2_story' => 'J\'hésitais à postuler à l\'international, mais l\'accompagnement étape par étape de ScholarSync Global a rendu le processus gérable. Ils m\'ont aidée à identifier les professeurs dont les recherches correspondaient à mes intérêts.',
        'testimonial2_achievement' => 'Assistanat de recherche + exonération des frais de scolarité',
        
        // Testimonial 3
        'testimonial3_name' => 'David Kim',
        'testimonial3_country' => 'Corée du Sud → Allemagne',
        'testimonial3_program' => 'Génie Mécanique à TU Munich',
        'testimonial3_quote' => 'Étudier en Allemagne avec des frais de scolarité nuls semblait trop beau pour être vrai. ScholarSync Global m\'a aidé à naviguer les exigences complexes et m\'a enseigné l\'allemand jusqu\'au niveau B1.',
        'testimonial3_story' => 'Le compte bloqué et l\'assurance maladie me causaient des soucis, mais mon conseiller m\'a expliqué chaque document. Ils m\'ont même connecté avec des clients coréens déjà à Munich.',
        'testimonial3_achievement' => 'Admis à un programme sans frais de scolarité',
        
        // Testimonial 4
        'testimonial4_name' => 'Maria Rodriguez',
        'testimonial4_country' => 'Mexique → Royaume-Uni',
        'testimonial4_program' => 'Médecine à l\'Université d\'Oxford',
        'testimonial4_quote' => 'Entrer à l\'école de médecine d\'Oxford semblait impossible, mais l\'équipe de ScholarSync Global a cru en moi. Ils m\'ont préparée pour le BMAT et les entretiens.',
        'testimonial4_story' => 'Le processus d\'admission en médecine au Royaume-Uni est très compétitif pour les clients internationaux. Les conseillers de ScholarSync Global m\'ont aidée à présenter mon expérience clinique.',
        'testimonial4_achievement' => 'Offre conditionnelle pour Oxford Médecine',
        
        // Testimonial 5
        'testimonial5_name' => 'Kenji Tanaka',
        'testimonial5_country' => 'Japon → Australie',
        'testimonial5_program' => 'PhD en Sciences Environnementales à ANU',
        'testimonial5_quote' => 'Ma proposition de recherche a été rejetée trois fois avant de travailler avec ScholarSync Global. Leurs experts en rédaction académique m\'ont aidé à affiner ma méthodologie.',
        'testimonial5_story' => 'Le processus de candidature au doctorat en Australie nécessite de trouver un superviseur d\'abord. ScholarSync Global m\'a aidé à identifier les professeurs dont les recherches correspondaient aux miennes.',
        'testimonial5_achievement' => 'Bourse de doctorat complète + allocation de subsistance',
        
        // Testimonial 6
        'testimonial6_name' => 'Aisha Mohammed',
        'testimonial6_country' => 'Nigeria → Pays-Bas',
        'testimonial6_program' => 'Commerce International à Rotterdam School of Management',
        'testimonial6_quote' => 'En tant qu\'entrepreneure, j\'avais besoin d\'un programme valorisant l\'expérience pratique. ScholarSync Global m\'a aidée à construire un portfolio mettant en valeur mes réussites entrepreneuriales.',
        'testimonial6_story' => 'Le système éducatif néerlandais m\'était inconnu, mais le spécialiste Pays-Bas de ScholarSync Global a tout expliqué clairement. Ils m\'ont même aidée à trouver un logement à Rotterdam.',
        'testimonial6_achievement' => 'Bourse entrepreneuriat attribuée',
        
        // Video Testimonials
        'video_title' => 'Témoignages Vidéo',
        'video_subtitle' => 'Écoutez directement nos clients',
        'video1_title' => 'Parcours MBA vers Stanford',
        'video2_title' => 'Succès en Ingénierie au Canada',
        'video3_title' => 'Rêve d\'École de Médecine Réalisé',
        
        // Statistics Section
        'stats_title' => 'En Chiffres',
        'stats_subtitle' => 'Notre impact sur la réussite client',
        
        'stat1_title' => 'Taux d\'Admission',
        'stat1_value' => '98%',
        'stat1_desc' => 'de nos clients reçoivent des offres d\'université',
        
        'stat2_title' => 'Succès Visa',
        'stat2_value' => '96%',
        'stat2_desc' => 'taux d\'approbation de visa en première tentative',
        
        'stat3_title' => 'Valeur des Bourses',
        'stat3_value' => '4,2M$+',
        'stat3_desc' => 'en bourses obtenues pour les clients',
        
        'stat4_title' => 'Satisfaction Client',
        'stat4_value' => '4,9/5',
        'stat4_desc' => 'note moyenne des clients',
        
        // Common Quotes
        'common_quotes_title' => 'Ce Que Disent Les Clients',
        'quote1' => 'ScholarSync Global a rendu mon rêve d\'étudier à l\'étranger réalité.',
        'quote2' => 'Le soutien et l\'accompagnement que j\'ai reçus étaient exceptionnels.',
        'quote3' => 'Je n\'aurais pas pu le faire sans leur aide pour les bourses et les visas.',
        'quote4' => 'Professionnels, fiables et véritablement engagés dans la réussite client.',
        'quote5' => 'Ils ont compris mon parcours et adapté leur approche.',
        'quote6' => 'Le réseau d\'anciens et de clients actuels était inestimable.',
        
        // Timeline Section
        'timeline_title' => 'Chronologie du Parcours Client',
        'timeline_subtitle' => 'Du rêve à la réalité',
        
        'timeline1_title' => 'Consultation Initiale',
        'timeline1_desc' => 'Compréhension des objectifs et création de feuille de route',
        
        'timeline2_title' => 'Sélection d\'Université',
        'timeline2_desc' => 'Correspondance avec programmes et lieux idéaux',
        
        'timeline3_title' => 'Préparation de Candidature',
        'timeline3_desc' => 'Essais, recommandations et documentation',
        
        'timeline4_title' => 'Coaching d\'Entretien',
        'timeline4_desc' => 'Simulations d\'entretien et préparation',
        
        'timeline5_title' => 'Assistance Visa',
        'timeline5_desc' => 'Préparation de documents et pratique d\'entretien',
        
        'timeline6_title' => 'Soutien Pré-Départ',
        'timeline6_desc' => 'Logement, orientation et transition',
        
        // CTA Section
        'cta_title' => 'Commencez Votre Histoire de Réussite',
        'cta_description' => 'Rejoignez les milliers de clients qui ont réalisé leurs rêves avec ScholarSync Global',
        'cta_button1' => 'Réserver Consultation Gratuite',
        'cta_button2' => 'Voir Plus d\'Histoires',
        
        // Page Metadata
        'page_description' => 'Lisez les témoignages de clients et histoires de réussite de ScholarSync Global. Expériences réelles de clients internationaux.',
        'page_title' => 'Témoignages de Clients - ScholarSync Global',

        'db_wall_title' => 'Témoignages clients',
        'db_wall_sub' => 'Photos et citations de personnes que nous avons accompagnées.',
    ]
];

// Function to get testimonials page translation
function tt($key) {
    global $testimonials_translations, $current_lang;
    
    // Fallback to English if key missing
    if (isset($testimonials_translations[$current_lang][$key])) {
        return $testimonials_translations[$current_lang][$key];
    } elseif (isset($testimonials_translations['en'][$key])) {
        return $testimonials_translations['en'][$key];
    }
    
    return $key; // Return key itself as last resort
}

// Define testimonial categories
$categories = [
    [
        'title_key' => 'category1_title',
        'count_key' => 'category1_count',
        'icon' => 'fas fa-chart-line',
        'color' => 'linear-gradient(135deg, #3B82F6, #1D4ED8)'
    ],
    [
        'title_key' => 'category2_title',
        'count_key' => 'category2_count',
        'icon' => 'fas fa-cogs',
        'color' => 'linear-gradient(135deg, #10B981, #047857)'
    ],
    [
        'title_key' => 'category3_title',
        'count_key' => 'category3_count',
        'icon' => 'fas fa-heartbeat',
        'color' => 'linear-gradient(135deg, #EF4444, #DC2626)'
    ],
    [
        'title_key' => 'category4_title',
        'count_key' => 'category4_count',
        'icon' => 'fas fa-palette',
        'color' => 'linear-gradient(135deg, #8B5CF6, #7C3AED)'
    ],
    [
        'title_key' => 'category5_title',
        'count_key' => 'category5_count',
        'icon' => 'fas fa-award',
        'color' => 'linear-gradient(135deg, #F59E0B, #D97706)'
    ],
    [
        'title_key' => 'category6_title',
        'count_key' => 'category6_count',
        'icon' => 'fas fa-passport',
        'color' => 'linear-gradient(135deg, #06B6D4, #0891B2)'
    ]
];

// Define detailed testimonials
$testimonials = [
    [
        'key_prefix' => 'testimonial1',
        'initial' => 'AC',
        'rating' => 5,
        'program_color' => '#1D4ED8'
    ],
    [
        'key_prefix' => 'testimonial2',
        'initial' => 'SJ',
        'rating' => 5,
        'program_color' => '#047857'
    ],
    [
        'key_prefix' => 'testimonial3',
        'initial' => 'DK',
        'rating' => 5,
        'program_color' => '#DC2626'
    ],
    [
        'key_prefix' => 'testimonial4',
        'initial' => 'MR',
        'rating' => 5,
        'program_color' => '#7C3AED'
    ],
    [
        'key_prefix' => 'testimonial5',
        'initial' => 'KT',
        'rating' => 5,
        'program_color' => '#D97706'
    ],
    [
        'key_prefix' => 'testimonial6',
        'initial' => 'AM',
        'rating' => 5,
        'program_color' => '#0891B2'
    ]
];

// Define video testimonials
$videos = [
    [
        'title_key' => 'video1_title',
        'thumbnail' => 'video1.jpg',
        'duration' => '4:32'
    ],
    [
        'title_key' => 'video2_title',
        'thumbnail' => 'video2.jpg',
        'duration' => '3:45'
    ],
    [
        'title_key' => 'video3_title',
        'thumbnail' => 'video3.jpg',
        'duration' => '5:18'
    ]
];

// Define statistics
$statistics = [
    [
        'title_key' => 'stat1_title',
        'value_key' => 'stat1_value',
        'desc_key' => 'stat1_desc',
        'icon' => 'fas fa-graduation-cap'
    ],
    [
        'title_key' => 'stat2_title',
        'value_key' => 'stat2_value',
        'desc_key' => 'stat2_desc',
        'icon' => 'fas fa-passport'
    ],
    [
        'title_key' => 'stat3_title',
        'value_key' => 'stat3_value',
        'desc_key' => 'stat3_desc',
        'icon' => 'fas fa-money-bill-wave'
    ],
    [
        'title_key' => 'stat4_title',
        'value_key' => 'stat4_value',
        'desc_key' => 'stat4_desc',
        'icon' => 'fas fa-star'
    ]
];

// Define common quotes
$common_quotes = ['quote1', 'quote2', 'quote3', 'quote4', 'quote5', 'quote6'];

// Define timeline
$timeline = [
    ['title_key' => 'timeline1_title', 'desc_key' => 'timeline1_desc', 'icon' => 'fas fa-comments'],
    ['title_key' => 'timeline2_title', 'desc_key' => 'timeline2_desc', 'icon' => 'fas fa-search'],
    ['title_key' => 'timeline3_title', 'desc_key' => 'timeline3_desc', 'icon' => 'fas fa-file-alt'],
    ['title_key' => 'timeline4_title', 'desc_key' => 'timeline4_desc', 'icon' => 'fas fa-microphone'],
    ['title_key' => 'timeline5_title', 'desc_key' => 'timeline5_desc', 'icon' => 'fas fa-stamp'],
    ['title_key' => 'timeline6_title', 'desc_key' => 'timeline6_desc', 'icon' => 'fas fa-plane']
];
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo tt('page_description'); ?>">
<title><?php echo tt('page_title'); ?></title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ============================================
   TESTIMONIALS PAGE STYLES
   Modern, engaging design with focus on stories
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
.testimonials-hero {
  min-height: 60vh;
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
    radial-gradient(circle at 20% 30%, rgba(242, 166, 90, 0.15) 0%, transparent 40%),
    radial-gradient(circle at 80% 70%, rgba(45, 212, 191, 0.15) 0%, transparent 40%);
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

/* ===== INTRO SECTION ===== */
.intro-section {
  background: white;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 30px;
  margin: 60px auto;
  max-width: 1000px;
}

.stat-intro {
  text-align: center;
  padding: 30px 20px;
  background: var(--bg);
  border-radius: 16px;
  border: 1px solid var(--border-light);
  transition: var(--transition);
}

.stat-intro:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-md);
}

.stat-intro .number {
  font-size: 2.5rem;
  font-weight: 800;
  color: var(--primary-navy);
  margin-bottom: 10px;
}

.stat-intro .label {
  font-size: 1rem;
  color: var(--text-light);
  font-weight: 500;
}

/* ===== CATEGORIES SECTION ===== */
.categories-section {
  background: var(--bg);
}

.categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 25px;
  margin-top: 50px;
}

.category-card {
  background: white;
  padding: 30px;
  border-radius: 16px;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-light);
  transition: var(--transition);
  display: flex;
  align-items: center;
  gap: 20px;
  cursor: pointer;
}

.category-card:hover {
  transform: translateY(-8px);
  box-shadow: var(--shadow-lg);
}

.category-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
  flex-shrink: 0;
}

.category-info h3 {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 5px;
}

.category-count {
  font-size: 0.9rem;
  color: var(--accent-gold);
  font-weight: 600;
}

/* ===== DETAILED TESTIMONIALS ===== */
.testimonials-detailed {
  background: white;
}

.testimonials-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
  gap: 40px;
  margin-top: 50px;
}

@media (max-width: 768px) {
  .testimonials-grid {
    grid-template-columns: 1fr;
  }
}

.testimonial-card {
  background: var(--bg);
  padding: 40px;
  border-radius: 20px;
  border: 1px solid var(--border-light);
  box-shadow: var(--shadow-md);
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.testimonial-card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-xl);
}

.testimonial-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 6px;
  height: 100%;
}

.testimonial-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 25px;
}

.author-avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 1.8rem;
  flex-shrink: 0;
  border: 4px solid white;
  box-shadow: var(--shadow-md);
}

.author-info {
  flex: 1;
}

.author-name {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 5px;
}

.author-country {
  font-size: 0.9rem;
  color: var(--accent-gold);
  font-weight: 600;
  margin-bottom: 5px;
}

.author-program {
  font-size: 1rem;
  color: var(--text);
  font-weight: 500;
}

.star-rating {
  display: flex;
  gap: 5px;
  margin-top: 10px;
}

.star-rating i {
  color: #FBBF24;
  font-size: 1rem;
}

.testimonial-quote {
  font-size: 1.2rem;
  line-height: 1.7;
  color: var(--text);
  margin-bottom: 25px;
  padding-left: 20px;
  border-left: 4px solid var(--accent-teal);
  font-style: italic;
}

.testimonial-story {
  color: var(--text-light);
  line-height: 1.6;
  margin-bottom: 25px;
  background: white;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid var(--border-light);
}

.achievement-badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: linear-gradient(135deg, var(--accent-light), var(--teal-light));
  color: var(--primary-navy);
  padding: 10px 20px;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 600;
}

.achievement-badge i {
  color: var(--accent-gold);
}

/* ===== VIDEO TESTIMONIALS ===== */
.video-section {
  background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
}

.videos-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.video-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: var(--shadow-md);
  transition: var(--transition);
}

.video-card:hover {
  transform: translateY(-8px);
  box-shadow: var(--shadow-xl);
}

.video-thumbnail {
  position: relative;
  height: 200px;
  background: linear-gradient(135deg, var(--primary-navy), var(--secondary-blue));
  display: flex;
  align-items: center;
  justify-content: center;
}

.play-button {
  width: 70px;
  height: 70px;
  background: rgba(255, 255, 255, 0.9);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--primary-navy);
  font-size: 1.5rem;
  cursor: pointer;
  transition: var(--transition);
}

.play-button:hover {
  background: white;
  transform: scale(1.1);
}

.video-duration {
  position: absolute;
  bottom: 15px;
  right: 15px;
  background: rgba(0, 0, 0, 0.7);
  color: white;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
}

.video-info {
  padding: 25px;
}

.video-info h3 {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 10px;
}

/* ===== STATISTICS SECTION ===== */
.stats-section {
  background: linear-gradient(135deg, var(--primary-navy) 0%, var(--dark-blue) 100%);
  color: white;
}

.stats-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 40px;
  margin-top: 60px;
}

.stat-item {
  text-align: center;
  padding: 30px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  transition: var(--transition);
}

.stat-item:hover {
  background: rgba(255, 255, 255, 0.15);
  transform: translateY(-5px);
}

.stat-icon {
  font-size: 2.5rem;
  color: var(--accent-gold);
  margin-bottom: 20px;
}

.stat-value {
  font-size: 3rem;
  font-weight: 800;
  margin-bottom: 10px;
  color: white;
}

.stat-title {
  font-size: 1.1rem;
  font-weight: 600;
  margin-bottom: 10px;
  color: var(--accent-teal);
}

.stat-desc {
  font-size: 0.95rem;
  color: rgba(255, 255, 255, 0.8);
  line-height: 1.5;
}

/* ===== COMMON QUOTES ===== */
.quotes-section {
  background: white;
}

.quotes-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 25px;
  margin-top: 50px;
}

.quote-card {
  background: var(--bg);
  padding: 30px;
  border-radius: 16px;
  border: 1px solid var(--border-light);
  position: relative;
  transition: var(--transition);
}

.quote-card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-md);
}

.quote-card::before {
  content: '"';
  position: absolute;
  top: 10px;
  left: 15px;
  font-size: 60px;
  color: rgba(1, 47, 107, 0.1);
  font-family: serif;
  line-height: 1;
}

.quote-text {
  font-size: 1.1rem;
  line-height: 1.6;
  color: var(--text);
  padding-left: 20px;
  position: relative;
  z-index: 1;
}

/* ===== TIMELINE SECTION ===== */
.timeline-section {
  background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
}

.timeline-container {
  max-width: 1000px;
  margin: 60px auto 0;
  position: relative;
}

.timeline-container::before {
  content: '';
  position: absolute;
  top: 50px;
  left: 50%;
  transform: translateX(-50%);
  width: 2px;
  height: calc(100% - 100px);
  background: var(--accent-teal);
}

@media (max-width: 768px) {
  .timeline-container::before {
    left: 30px;
  }
}

.timeline-item {
  display: flex;
  align-items: center;
  margin-bottom: 60px;
  position: relative;
}

.timeline-item:nth-child(odd) {
  flex-direction: row-reverse;
}

@media (max-width: 768px) {
  .timeline-item,
  .timeline-item:nth-child(odd) {
    flex-direction: row;
    align-items: flex-start;
  }
}

.timeline-content {
  flex: 1;
  padding: 0 40px;
}

@media (max-width: 768px) {
  .timeline-content {
    padding-left: 70px;
    padding-right: 0;
  }
}

.timeline-icon {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, var(--accent-gold), var(--accent-teal));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.5rem;
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  border: 4px solid white;
  box-shadow: var(--shadow-md);
}

@media (max-width: 768px) {
  .timeline-icon {
    left: 30px;
    transform: translateX(-50%);
  }
}

.timeline-content h4 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 10px;
}

.timeline-content p {
  color: var(--text-light);
  line-height: 1.6;
}

/* ===== CTA SECTION ===== */
.testimonials-cta {
  background: linear-gradient(135deg, #427431 0%, #3661B9 100%);
  text-align: center;
  padding: 100px 20px;
  color: white;
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
  color: rgba(255, 255, 255, 0.9);
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
  background: white;
  color: var(--primary-navy);
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.cta-button-secondary:hover {
  background: #f8fafc;
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

.delay-1 { animation-delay: 0.1s; }
.delay-2 { animation-delay: 0.2s; }
.delay-3 { animation-delay: 0.3s; }
.delay-4 { animation-delay: 0.4s; }
.delay-5 { animation-delay: 0.5s; }

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
  
  .testimonials-grid {
    grid-template-columns: 1fr;
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
  
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .categories-grid {
    grid-template-columns: 1fr;
  }
  
  .videos-grid {
    grid-template-columns: 1fr;
  }
  
  .stats-container {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .quotes-grid {
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
}

@media (max-width: 576px) {
  .section-title {
    font-size: 1.8rem;
  }
  
  .hero-content h1 {
    font-size: 1.8rem;
  }
  
  .stats-grid,
  .stats-container {
    grid-template-columns: 1fr;
  }
  
  .timeline-content h4 {
    font-size: 1.1rem;
  }
}

/* Superadmin-managed testimonials (photos + quotes) */
.testimonials-db-wall {
  padding: 56px 20px 64px;
  background: linear-gradient(180deg, #f0fdf4 0%, #f8fafc 100%);
  border-bottom: 1px solid var(--border);
}
.testimonials-db-wall .pcvc-testimonials-inner {
  max-width: 1200px;
  margin: 0 auto;
}
.testimonials-db-wall .pcvc-testimonials-title {
  font-size: 2rem;
  color: var(--primary-navy);
  text-align: center;
}
.testimonials-db-wall .pcvc-testimonials-head {
  text-align: center;
  margin-bottom: 2rem;
}
.testimonials-db-wall .pcvc-testimonial-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 1.25rem;
  box-shadow: var(--shadow-md);
}
.testimonials-db-wall .pcvc-testimonial-photo-wrap {
  width: 88px;
  height: 88px;
  border-radius: 14px;
}
.testimonials-db-wall .pcvc-testimonials-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.25rem;
}
@media (min-width: 768px) {
  .testimonials-db-wall .pcvc-testimonials-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (min-width: 1100px) {
  .testimonials-db-wall .pcvc-testimonials-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
</head>
<body>

<!-- Hero Section -->
<section class="testimonials-hero">
  <div class="hero-bg-pattern"></div>
  <div class="hero-content">
    <h1 class="fade-in"><?php echo tt('hero_title'); ?></h1>
    <p class="hero-subtitle fade-in"><?php echo tt('hero_subtitle'); ?></p>
    <p class="hero-description fade-in"><?php echo tt('hero_description'); ?></p>
  </div>
</section>

<?php if (!empty($page_db_testimonials)): ?>
<div class="testimonials-db-wall">
<?php
  $rows = $page_db_testimonials;
  $heading = tt('db_wall_title');
  $sub = tt('db_wall_sub');
  $section_class = 'testimonials-page-db';
  include __DIR__ . '/includes/partials/testimonials_public_block.php';
?>
</div>
<?php endif; ?>

<!-- Intro Section -->
<section class="intro-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo tt('intro_title'); ?></h2>
      <p class="section-description"><?php echo tt('intro_description'); ?></p>
    </div>
    
    <div class="stats-grid">
      <div class="stat-intro fade-in">
        <div class="number">5,000+</div>
        <div class="label"><?php echo tt('stats_customers'); ?></div>
      </div>
      <div class="stat-intro fade-in delay-1">
        <div class="number">98%</div>
        <div class="label"><?php echo tt('stats_success'); ?></div>
      </div>
      <div class="stat-intro fade-in delay-2">
        <div class="number">65+</div>
        <div class="label"><?php echo tt('stats_countries'); ?></div>
      </div>
      <div class="stat-intro fade-in delay-3">
        <div class="number">$4.2M+</div>
        <div class="label"><?php echo tt('stats_scholarships'); ?></div>
      </div>
    </div>
  </div>
</section>

<!-- Categories Section -->
<section class="categories-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo tt('categories_title'); ?></h2>
      <p class="section-subtitle"><?php echo tt('categories_subtitle'); ?></p>
    </div>
    
    <div class="categories-grid">
      <?php foreach($categories as $index => $category): ?>
      <div class="category-card fade-in" style="--delay: <?php echo $index * 0.1; ?>s">
        <div class="category-icon" style="background: <?php echo $category['color']; ?>">
          <i class="<?php echo $category['icon']; ?>"></i>
        </div>
        <div class="category-info">
          <h3><?php echo tt($category['title_key']); ?></h3>
          <p class="category-count"><?php echo tt($category['count_key']); ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Featured Testimonials -->
<section class="testimonials-detailed section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo tt('featured_title'); ?></h2>
      <p class="section-subtitle"><?php echo tt('featured_subtitle'); ?></p>
    </div>
    
    <div class="testimonials-grid">
      <?php foreach($testimonials as $testimonial): ?>
      <div class="testimonial-card fade-in">
        <div class="testimonial-header">
          <div class="author-avatar" style="background: <?php echo $testimonial['program_color']; ?>">
            <?php echo $testimonial['initial']; ?>
          </div>
          <div class="author-info">
            <h3 class="author-name"><?php echo tt($testimonial['key_prefix'] . '_name'); ?></h3>
            <p class="author-country"><?php echo tt($testimonial['key_prefix'] . '_country'); ?></p>
            <p class="author-program"><?php echo tt($testimonial['key_prefix'] . '_program'); ?></p>
            <div class="star-rating">
              <?php for($i = 0; $i < $testimonial['rating']; $i++): ?>
              <i class="fas fa-star"></i>
              <?php endfor; ?>
            </div>
          </div>
        </div>
        
        <blockquote class="testimonial-quote">
          "<?php echo tt($testimonial['key_prefix'] . '_quote'); ?>"
        </blockquote>
        
        <div class="testimonial-story">
          <?php echo tt($testimonial['key_prefix'] . '_story'); ?>
        </div>
        
        <div class="achievement-badge">
          <i class="fas fa-trophy"></i>
          <?php echo tt($testimonial['key_prefix'] . '_achievement'); ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Video Testimonials -->
<section class="video-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo tt('video_title'); ?></h2>
      <p class="section-subtitle"><?php echo tt('video_subtitle'); ?></p>
    </div>
    
    <div class="videos-grid">
      <?php foreach($videos as $video): ?>
      <div class="video-card fade-in">
        <div class="video-thumbnail">
          <div class="play-button">
            <i class="fas fa-play"></i>
          </div>
          <span class="video-duration"><?php echo $video['duration']; ?></span>
        </div>
        <div class="video-info">
          <h3><?php echo tt($video['title_key']); ?></h3>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Statistics Section -->
<section class="stats-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title" style="color: white;"><?php echo tt('stats_title'); ?></h2>
      <p class="section-description" style="color: rgba(255, 255, 255, 0.9);"><?php echo tt('stats_subtitle'); ?></p>
    </div>
    
    <div class="stats-container">
      <?php foreach($statistics as $index => $stat): ?>
      <div class="stat-item fade-in">
        <div class="stat-icon">
          <i class="<?php echo $stat['icon']; ?>"></i>
        </div>
        <div class="stat-value"><?php echo tt($stat['value_key']); ?></div>
        <h3 class="stat-title"><?php echo tt($stat['title_key']); ?></h3>
        <p class="stat-desc"><?php echo tt($stat['desc_key']); ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Common Quotes Section -->
<section class="quotes-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo tt('common_quotes_title'); ?></h2>
    </div>
    
    <div class="quotes-grid">
      <?php foreach($common_quotes as $index => $quote_key): ?>
      <div class="quote-card fade-in">
        <p class="quote-text"><?php echo tt($quote_key); ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Timeline Section -->
<section class="timeline-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo tt('timeline_title'); ?></h2>
      <p class="section-subtitle"><?php echo tt('timeline_subtitle'); ?></p>
    </div>
    
    <div class="timeline-container">
      <?php foreach($timeline as $index => $step): ?>
      <div class="timeline-item fade-in">
        <div class="timeline-icon">
          <i class="<?php echo $step['icon']; ?>"></i>
        </div>
        <div class="timeline-content">
          <h4><?php echo tt($step['title_key']); ?></h4>
          <p><?php echo tt($step['desc_key']); ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA Section -->
<section class="testimonials-cta">
  <div class="cta-content">
    <h2 class="fade-in"><?php echo tt('cta_title'); ?></h2>
    <p class="fade-in"><?php echo tt('cta_description'); ?></p>
    <div class="cta-buttons">
      <button class="cta-button cta-button-primary fade-in" onclick="window.location.href='consultation.php'">
        <i class="fas fa-calendar-check"></i>
        <?php echo tt('cta_button1'); ?>
      </button>
      <button class="cta-button cta-button-secondary fade-in" onclick="window.location.href='#testimonials-detailed'">
        <i class="fas fa-comment-dots"></i>
        <?php echo tt('cta_button2'); ?>
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

  // Video play functionality
  document.querySelectorAll('.play-button').forEach(button => {
    button.addEventListener('click', function(e) {
      e.preventDefault();
      const videoCard = this.closest('.video-card');
      const title = videoCard.querySelector('h3').textContent;
      alert('Playing video: ' + title + '\n\nIn a real implementation, this would open a modal or play the video.');
    });
  });

  // Category card click handlers
  document.querySelectorAll('.category-card').forEach(card => {
    card.addEventListener('click', function() {
      const title = this.querySelector('h3').textContent;
      alert('Filtering testimonials by category: ' + title + '\n\nIn a real implementation, this would filter the testimonials grid.');
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

  // Add testimonial card hover effects
  document.querySelectorAll('.testimonial-card').forEach(card => {
    const avatar = card.querySelector('.author-avatar');
    const originalTransform = avatar.style.transform;
    
    card.addEventListener('mouseenter', function() {
      avatar.style.transform = originalTransform + ' scale(1.1) rotate(5deg)';
    });
    
    card.addEventListener('mouseleave', function() {
      avatar.style.transform = originalTransform;
    });
  });

  // Initialize animations
  animateOnScroll();

})();
</script>

</body>
</html>