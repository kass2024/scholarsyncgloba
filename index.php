<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$available_languages = ['en' => 'English', 'fr' => 'Français'];
if (!isset($_SESSION['current_language'])) {
    $_SESSION['current_language'] = 'en';
}
if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $available_languages)) {
    $_SESSION['current_language'] = $_GET['lang'];
    $url = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: $url");
    exit;
}
$current_lang = $_SESSION['current_language'];

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/korea_event_schema.php';
kep_ensure_schema($conn);
require_once __DIR__ . '/includes/testimonials_lib.php';
$home_testimonials = pcvc_get_published_testimonials($conn, 6);

// ============================================
// TRANSLATIONS FOR INDEX PAGE
// ============================================

$index_translations = [
    'en' => [
        // Hero Section (aligned with scholarsyncglobal.ca)
        'hero_eyebrow' => 'Welcome to ScholarSync Global — your global study & visa partner.',
        'hero_title' => 'Study & visit abroad. Visa applications. One trusted partner.',
        'hero_description' => 'Need help with how you can study and work abroad and visa applications? We reply within 24 hours and support you before and after you obtain your visa.',
        'start_application' => 'Explore services',
        'learn_more' => 'Why ScholarSync',
        'quick_partners' => 'Partners',
        'quick_book' => 'Book appointment',
        'trust_reply' => 'Reply within 24 hours',
        'trust_support' => '24 hr support',
        'study_dest_title' => 'Study & visit abroad',
        'visa_dest_title' => 'Visa application',
        'pillars_title' => 'Why choose ScholarSync Global',
        'pillar1_title' => 'Reputation & experience',
        'pillar1_desc' => 'Our reputation and track record speak for themselves — trusted guidance at every step.',
        'pillar2_title' => 'Ethical practices',
        'pillar2_desc' => 'We adhere to ethical and professional standards in admissions, visas, and client care.',
        'pillar3_title' => 'Customer support',
        'pillar3_desc' => 'Dedicated support including after you have obtained your visa — we stay with you on the journey.',
        'partners_uni_title' => 'Partner universities',
        'partners_uni_sub' => 'Institutions we collaborate with worldwide',
        'contact_banner_title' => 'Feel free to reach out — we\'re here to assist you',
        'contact_phone' => '+250 788 284 544',
        'contact_phone2' => '+250 789 515 593',
        'contact_email' => 'infos@scholarsyncglobal.ca',
        'contact_address' => 'Town Center Building (near Simba Supermarket), 2nd Floor, Door: F2B-022C, Nyarugenge — Kigali, Rwanda',
        
        // Stats Section
        'stats_students' => 'Students Placed',
        'stats_scholarships' => 'Scholarships Awarded',
        'stats_countries' => 'Countries Worldwide',
        'stats_partners' => 'University Partners',
        
        // Features Section (four value props — distinct from pillars above)
        'features_title' => 'Personalized support at every step',
        'feature1_title' => 'Personalized Roadmaps',
        'feature1_desc' => 'Customized plans tailored to your academic and career goals',
        'feature2_title' => 'Expert Guidance',
        'feature2_desc' => 'Certified advisors with 10+ years of industry experience',
        'feature3_title' => 'Global Network',
        'feature3_desc' => 'Direct partnerships with top universities worldwide',
        'feature4_title' => 'Financial Support',
        'feature4_desc' => 'Access to scholarships and education loan programs',
        
        // Services Header
        'services_title' => 'Our Services',
        'services_description' => 'Everything you need to study, work, study loan, credit and move abroad — guided by experts.',
        
        // New: Global Universities Section
        'universities_title' => 'Top Global Universities We Work With',
        'universities_description' => 'Direct partnerships with world-class institutions across continents',
        
        // New: Destination Countries Section
        'destinations_title' => 'Study & Work Destinations',
        'destinations_description' => 'Choose from our most popular study and work abroad destinations',
        
        // New: Process Section
        'process_title' => 'Our 5-Step Success Process',
        'process_description' => 'A proven methodology that ensures your international journey is smooth and successful',
        'process_step1' => 'Initial Consultation',
        'process_step2' => 'Profile Assessment',
        'process_step3' => 'University/Job Matching',
        'process_step4' => 'Application & Visa',
        'process_step5' => 'Pre-Departure & Arrival',
        'process_step1_desc' => 'Free detailed discussion about your goals and aspirations',
        'process_step2_desc' => 'Comprehensive evaluation of your academic and professional profile',
        'process_step3_desc' => 'Match with ideal universities or job opportunities',
        'process_step4_desc' => 'Complete application and visa documentation support',
        'process_step5_desc' => 'Accommodation, travel, and settling-in assistance',
        
        // Enhanced Success Stories
        'testimonials_title' => 'Success Stories',
        'testimonials_subtitle' => 'Real students, real achievements',
        
        // Testimonial 1-10
        'testimonial1' => 'ScholarSync Global helped me secure a 90% scholarship at my dream university in Canada. The entire process was seamless!',
        'testimonial1_name' => 'Sarah M., Computer Science Student',
        'testimonial1_location' => 'University of Toronto, Canada',
        'testimonial1_achievement' => '90% Scholarship Awarded',
        
        'testimonial2' => 'The visa guidance and interview preparation were exceptional. Got my US student visa approved in first attempt.',
        'testimonial2_name' => 'James K., MBA Applicant',
        'testimonial2_location' => 'NYU Stern, USA',
        'testimonial2_achievement' => 'Visa Approved First Attempt',
        
        'testimonial3' => 'From application to arrival, our advisor provided complete support. The job placement service is outstanding.',
        'testimonial3_name' => 'Priya S., Healthcare Professional',
        'testimonial3_location' => 'Berlin, Germany',
        'testimonial3_achievement' => 'Job Placement in 30 Days',
        
        'testimonial4' => 'Received multiple offers from UK universities with scholarships. ScholarSync made the impossible possible!',
        'testimonial4_name' => 'Ahmed R., Engineering Student',
        'testimonial4_location' => 'Imperial College London, UK',
        'testimonial4_achievement' => '3 University Offers',
        
        'testimonial5' => 'The credit transfer process saved me a year of study and significant tuition fees. Excellent service!',
        'testimonial5_name' => 'Lisa T., Business Student',
        'testimonial5_location' => 'University of Sydney, Australia',
        'testimonial5_achievement' => '1 Year Study Saved',
        
        'testimonial6' => 'As a working professional, the team helped me transition to a European job with family relocation support.',
        'testimonial6_name' => 'David L., IT Professional',
        'testimonial6_location' => 'Amsterdam, Netherlands',
        'testimonial6_achievement' => 'Family Relocation Complete',
        
        'testimonial7' => 'Medical residency placement in the US seemed impossible until I worked with ScholarSync Global.',
        'testimonial7_name' => 'Dr. Maria G., Medical Resident',
        'testimonial7_location' => 'Johns Hopkins, USA',
        'testimonial7_achievement' => 'Residency Match Success',
        
        'testimonial8' => 'Full scholarship for PhD in Artificial Intelligence. The guidance was invaluable.',
        'testimonial8_name' => 'Kenji Y., PhD Candidate',
        'testimonial8_location' => 'ETH Zurich, Switzerland',
        'testimonial8_achievement' => 'Full PhD Scholarship',
        
        'testimonial9' => 'Work permit and job placement in Canada within 4 months of getting started with ScholarSync.',
        'testimonial9_name' => 'Rahul P., Software Developer',
        'testimonial9_location' => 'Vancouver, Canada',
        'testimonial9_achievement' => 'Job in 4 Months',
        
        'testimonial10' => 'Study abroad with my spouse seemed complicated, but ScholarSync handled everything perfectly.',
        'testimonial10_name' => 'Sophie & Mark, Couple',
        'testimonial10_location' => 'Dublin, Ireland',
        'testimonial10_achievement' => 'Dual Admission Success',
        
        // New: Partnership Section
        'partners_title' => 'Trusted by Industry Leaders',
        'partners_description' => 'We collaborate with leading educational and financial institutions worldwide',
        
        // Banking & Financial Partners
        'banking_partners_title' => 'Banking & Financial Partners',
        'banking_partners_desc' => 'Trusted banking institutions for student loans and financial services',
        
        // Testing & Certification Partners
        'testing_partners_title' => 'Testing & Certification Partners',
        'testing_partners_desc' => 'Official testing centers and certification bodies for international education',
        
        // Travel & Insurance Partners
        'travel_partners_title' => 'Travel & Insurance Partners',
        'travel_partners_desc' => 'Preferred airlines and insurance providers for international students',
        
        // Technology & Education Partners
        'tech_partners_title' => 'Technology & Education Partners',
        'tech_partners_desc' => 'Leading technology platforms for digital learning and career development',
        
        // New: Blog/Resources Section
        'resources_title' => 'Latest Insights & Resources',
        'resources_description' => 'Stay updated with the latest in international education and career trends',
        'resource1_title' => 'Top 10 Scholarships for 2025',
        'resource1_desc' => 'Complete guide to fully-funded opportunities',
        'resource2_title' => 'Visa Processing Times 2025',
        'resource2_desc' => 'Updated timelines for all major destinations',
        'resource3_title' => 'Job Market Trends Abroad',
        'resource3_desc' => 'In-demand careers in key countries',
        'read_more' => 'Read More',
        
        // CTA Section
        'cta_title' => 'Ready to Begin Your Journey?',
        'cta_description' => 'Book a free consultation with our expert advisors today.',
        'book_consultation' => 'Book Free Consultation',
        'download_brochure' => 'Download Brochure',
        
        // Card Translations
        'card_apply' => 'Apply Now',
        'card_copy' => 'Copy Link',
        'card_continue' => 'Retrieve Application',
        'card_continue_hint' => 'Have a user ID? Retrieve your saved application.',
        'resume_modal_title_tpl' => 'Retrieve your {service} application',
        'resume_modal_sub_tpl' => 'Enter the user ID from your confirmation (example: {example}) — or search by your name / email below.',
        'resume_label' => 'Application user ID',
        'resume_submit' => 'Retrieve',
        'resume_cancel' => 'Cancel',
        'resume_loading' => 'Checking…',
        'resume_error_generic' => 'Something went wrong. Please try again.',
        'resume_error_empty' => 'Please enter your application user ID.',
        'resume_search_label' => 'Search by name or email',
        'resume_search_placeholder' => 'Type at least 2 characters…',
        'resume_search_hint_tpl' => 'Searching {service} applications. Click a result to use that user ID, or copy it.',
        'resume_search_empty' => 'No matching applications.',
        'resume_search_short' => 'Type a longer search.',
        'resume_search_searching' => 'Searching…',
        'resume_copy' => 'Copy',
        'resume_copy_done' => 'Copied!',
        'resume_use' => 'Use',
        
        // Card 1: Study & Work Abroad
        'card1_title' => 'Study & Work Abroad',
        'card1_subtitle' => 'Universities, jobs, visas – all in one place',
        'card1_description' => 'Comprehensive support for your international education and career journey.',
        'card1_point1' => 'University applications',
        'card1_point2' => 'Work visa support',
        'card1_point3' => 'Real advisor guidance',
        
        // Card 2: Scholarships & Loans
        'card2_title' => 'Scholarships & Loans',
        'card2_subtitle' => 'Funding solutions tailored to your needs',
        'card2_description' => 'Access financial assistance programs designed to make your education affordable.',
        'card2_point1' => 'Up to 90% scholarships',
        'card2_point2' => 'Education loans (Canada & USA)',
        'card2_point3' => 'Financial planning support',
        
        // Card 3: I-20 Application
        'card3_title' => 'I-20 Application',
        'card3_subtitle' => 'Fast processing for US institutions',
        'card3_description' => 'Streamlined I-20 application process for US educational institutions.',
        'card3_point1' => 'SEVIS compliant',
        'card3_point2' => 'University coordination',
        'card3_point3' => 'Interview readiness',
        
        // Card 4: Credit Transfer
        'card4_title' => 'Credit Transfer',
        'card4_subtitle' => 'Transfer credits to partner universities',
        'card4_description' => 'Maximize your academic progress by transferring credits.',
        'card4_point1' => 'Transcript evaluation',
        'card4_point2' => 'Course equivalency',
        'card4_point3' => 'Reduced study duration',
        
        // Card 5: Visa Application
        'card5_title' => 'Visa Application',
        'card5_subtitle' => 'Study & visit visas with full guidance',
        'card5_description' => 'Complete visa application support for study and visit purposes.',
        'card5_point1' => 'Document preparation',
        'card5_point2' => 'Mock interviews',
        'card5_point3' => 'High approval rate',
        
        // Card 6: Apply for Job
        'card6_title' => 'Apply for Job',
        'card6_subtitle' => 'Work opportunities across Europe',
        'card6_description' => 'Launch your international career with our job placement services.',
        'card6_point1' => 'Job placement support',
        'card6_point2' => 'Accommodation assistance',
        'card6_point3' => 'Airport pickup',
        
        // Card 7: Canada Medical Exams
        'card7_title' => 'Canada Medical Exams',
        'card7_subtitle' => 'Medical examination services for Canada visa',
        'card7_description' => 'Complete medical examination support for Canadian immigration and visa requirements.',
        'card7_point1' => 'Approved medical centers',
        'card7_point2' => 'Fast processing',
        'card7_point3' => 'Document assistance',
        
        // Card 8: Francophonie Mobility Work Visa
        'card8_title' => 'Canada Francophonie Mobility',
        'card8_subtitle' => 'Work visa — Mobilité Francophone',
        'card8_description' => 'Apply for Canada Francophonie Mobility work visa opportunities with full candidate form support.',
        'card8_point1' => 'French & English profile review',
        'card8_point2' => 'Complete candidate form',
        'card8_point3' => 'Email updates & document review',

        // Card 9: Russian Employment Opportunities (Russia training + work)
        'card9_title' => 'Russian Employment Opportunities',
        'card9_subtitle' => 'Work training with Russian language',
        'card9_description' => 'Participants receive professional training while studying the Russian language and may be placed in one of the following fields. All positions combine practical work experience with Russian language training.',
        'card9_point1' => 'Road Transport Shop (Driver)',
        'card9_point2' => 'Service & Hospitality',
        'card9_point3' => 'Production Operator',
        'card9_point4' => 'Catering Industry (Food Service)',
        'card9_point5' => 'Logistics',
        'card9_point6' => 'Installation Works',
        'card9_point7' => 'Tiling Works',

        // Card 10: South Korea Event Participation
        'card10_title' => 'SOUTH KOREA EVENT PARTICIPATION FORM',
        'card10_subtitle' => 'Event registration and documentation support',
        'card10_description' => 'Register to attend the South Korea event. Submit your basic details together with your passport scan and CV for documentation review.',
        'card10_point1' => 'Passport attachment',
        'card10_point2' => 'CV / resume upload',
        'card10_point3' => 'Personal and event details',
        'card10_point4' => 'Email confirmation with reference ID',
        
        // Page Metadata
        'page_description' => 'ScholarSync Global - Your complete journey to international education and career success. Study abroad, scholarships, visas, and job opportunities.',
        'page_title' => 'ScholarSync Global - Your Complete Journey to Success',

        'home_db_testimonials_title' => 'What our clients say',
        'home_db_testimonials_sub' => 'Real stories from students and professionals we have supported.',
        'home_db_testimonials_view' => 'All testimonials',
        'home_db_testimonials_empty' => '',
    ],
    
    'fr' => [
        // Hero Section
        'hero_eyebrow' => 'Bienvenue chez ScholarSync Global — votre partenaire études et visas.',
        'hero_title' => 'Étudier et voyager à l\'étranger. Visas. Un partenaire de confiance.',
        'hero_description' => 'Besoin d\'aide pour étudier, travailler à l\'étranger ou pour votre visa ? Réponse sous 24 h, accompagnement avant et après l\'obtention du visa.',
        'start_application' => 'Découvrir les services',
        'learn_more' => 'Pourquoi ScholarSync',
        'quick_partners' => 'Partenaires',
        'quick_book' => 'Prendre rendez-vous',
        'trust_reply' => 'Réponse sous 24 h',
        'trust_support' => 'Support 24 h/24',
        'study_dest_title' => 'Étudier et voyager à l\'étranger',
        'visa_dest_title' => 'Demande de visa',
        'pillars_title' => 'Pourquoi choisir ScholarSync Global',
        'pillar1_title' => 'Réputation et expérience',
        'pillar1_desc' => 'Une réputation et un historique solides — un accompagnement fiable à chaque étape.',
        'pillar2_title' => 'Pratiques éthiques',
        'pillar2_desc' => 'Nous respectons des normes éthiques et professionnelles pour les admissions, visas et le suivi client.',
        'pillar3_title' => 'Service client',
        'pillar3_desc' => 'Un soutien dédié, y compris après l\'obtention de votre visa — nous restons à vos côtés.',
        'partners_uni_title' => 'Universités partenaires',
        'partners_uni_sub' => 'Institutions avec lesquelles nous collaborons',
        'contact_banner_title' => 'Contactez-nous — nous sommes là pour vous aider',
        'contact_phone' => '+250 788 284 544',
        'contact_phone2' => '+250 789 515 593',
        'contact_email' => 'infos@scholarsyncglobal.ca',
        'contact_address' => 'Town Center Building (près de Simba Supermarket), 2e étage, Porte : F2B-022C, Nyarugenge — Kigali, Rwanda',
        
        // Stats Section
        'stats_students' => 'Étudiants placés',
        'stats_scholarships' => 'Bourses attribuées',
        'stats_countries' => 'Pays dans le monde',
        'stats_partners' => 'Partenaires universitaires',
        
        // Features Section
        'features_title' => 'Un accompagnement personnalisé à chaque étape',
        'feature1_title' => 'Plans personnalisés',
        'feature1_desc' => 'Plans sur mesure adaptés à vos objectifs académiques et professionnels',
        'feature2_title' => 'Guidance experte',
        'feature2_desc' => 'Conseillers certifiés avec plus de 10 ans d\'expérience',
        'feature3_title' => 'Réseau mondial',
        'feature3_desc' => 'Partenariats directs avec les meilleures universités',
        'feature4_title' => 'Support financier',
        'feature4_desc' => 'Accès aux bourses et programmes de prêts éducatifs',
        
        // Services Header
        'services_title' => 'Nos Services',
        'services_description' => 'Tout ce dont vous avez besoin pour étudier, travailler et déménager à l\'étranger.',
        
        // New: Global Universities Section
        'universities_title' => 'Top Universités Mondiales',
        'universities_description' => 'Partenariats directs avec des institutions de classe mondiale',
        
        // New: Destination Countries Section
        'destinations_title' => 'Destinations d\'Études & Travail',
        'destinations_description' => 'Choisissez parmi nos destinations les plus populaires',
        
        // New: Process Section
        'process_title' => 'Notre Processus en 5 Étapes',
        'process_description' => 'Une méthodologie éprouvée pour un parcours international réussi',
        'process_step1' => 'Consultation Initiale',
        'process_step2' => 'Évaluation de Profil',
        'process_step3' => 'Matching Université/Emploi',
        'process_step4' => 'Candidature & Visa',
        'process_step5' => 'Pré-départ & Arrivée',
        'process_step1_desc' => 'Discussion détaillée gratuite sur vos objectifs',
        'process_step2_desc' => 'Évaluation complète de votre profil académique et professionnel',
        'process_step3_desc' => 'Correspondance avec des universités ou emplois idéaux',
        'process_step4_desc' => 'Support complet pour candidature et visa',
        'process_step5_desc' => 'Assistance logement, voyage et installation',
        
        // Enhanced Success Stories
        'testimonials_title' => 'Histoires de Réussite',
        'testimonials_subtitle' => 'Vrais étudiants, vrais succès',
        
        // Testimonial 1-10
        'testimonial1' => 'ScholarSync Global m\'a aidé à obtenir une bourse de 90% dans mon université de rêve au Canada.',
        'testimonial1_name' => 'Sarah M., Étudiante en informatique',
        'testimonial1_location' => 'Université de Toronto, Canada',
        'testimonial1_achievement' => 'Bourse de 90%',
        
        'testimonial2' => 'Le guide pour le visa était exceptionnel. Visa étudiant américain approuvé du premier coup.',
        'testimonial2_name' => 'James K., Candidat MBA',
        'testimonial2_location' => 'NYU Stern, USA',
        'testimonial2_achievement' => 'Visa Approuvé Première Tentative',
        
        'testimonial3' => 'De la candidature à l\'arrivée, l\'équipe a fourni un soutien complet.',
        'testimonial3_name' => 'Priya S., Professionnelle de la santé',
        'testimonial3_location' => 'Berlin, Allemagne',
        'testimonial3_achievement' => 'Emploi en 30 Jours',
        
        'testimonial4' => 'Plusieurs offres d\'universités britanniques avec bourses. ScholarSync a rendu l\'impossible possible!',
        'testimonial4_name' => 'Ahmed R., Étudiant en ingénierie',
        'testimonial4_location' => 'Imperial College London, UK',
        'testimonial4_achievement' => '3 Offres d\'Université',
        
        'testimonial5' => 'Le transfert de crédits m\'a fait économiser un an d\'études et des frais de scolarité.',
        'testimonial5_name' => 'Lisa T., Étudiante en commerce',
        'testimonial5_location' => 'Université de Sydney, Australie',
        'testimonial5_achievement' => '1 An d\'Études Économisé',
        
        'testimonial6' => 'Transition professionnelle en Europe avec support de relocalisation familiale.',
        'testimonial6_name' => 'David L., Professionnel IT',
        'testimonial6_location' => 'Amsterdam, Pays-Bas',
        'testimonial6_achievement' => 'Relocalisation Familiale',
        
        'testimonial7' => 'Placement en résidence médicale aux États-Unis réussi grâce à ScholarSync.',
        'testimonial7_name' => 'Dr. Maria G., Résidente Médicale',
        'testimonial7_location' => 'Johns Hopkins, USA',
        'testimonial7_achievement' => 'Match Résidence Réussi',
        
        'testimonial8' => 'Bourse complète pour doctorat en intelligence artificielle.',
        'testimonial8_name' => 'Kenji Y., Candidat Doctorat',
        'testimonial8_location' => 'ETH Zurich, Suisse',
        'testimonial8_achievement' => 'Bourse Doctorat Complète',
        
        'testimonial9' => 'Permis de travail et emploi au Canada en 4 mois.',
        'testimonial9_name' => 'Rahul P., Développeur Logiciel',
        'testimonial9_location' => 'Vancouver, Canada',
        'testimonial9_achievement' => 'Emploi en 4 Mois',
        
        'testimonial10' => 'Études à l\'étranger avec conjoint géré parfaitement par ScholarSync.',
        'testimonial10_name' => 'Sophie & Mark, Couple',
        'testimonial10_location' => 'Dublin, Irlande',
        'testimonial10_achievement' => 'Admission Double Réussie',
        
        // New: Partnership Section
        'partners_title' => 'Reconnu par les Leaders',
        'partners_description' => 'Collaboration avec institutions éducatives et financières leaders',
        
        // Banking & Financial Partners
        'banking_partners_title' => 'Partenaires Bancaires & Financiers',
        'banking_partners_desc' => 'Institutions bancaires de confiance pour les prêts étudiants et services financiers',
        
        // Testing & Certification Partners
        'testing_partners_title' => 'Partenaires de Tests & Certification',
        'testing_partners_desc' => 'Centres de tests officiels et organismes de certification pour l\'éducation internationale',
        
        // Travel & Insurance Partners
        'travel_partners_title' => 'Partenaires Voyage & Assurance',
        'travel_partners_desc' => 'Compagnies aériennes et assureurs préférés pour les étudiants internationaux',
        
        // Technology & Education Partners
        'tech_partners_title' => 'Partenaires Technologie & Éducation',
        'tech_partners_desc' => 'Plateformes technologiques leaders pour l\'apprentissage numérique et le développement de carrière',
        
        // New: Blog/Resources Section
        'resources_title' => 'Dernières Ressources & Insights',
        'resources_description' => 'Restez informé des dernières tendances en éducation internationale',
        'resource1_title' => 'Top 10 Bourses 2024',
        'resource1_desc' => 'Guide complet des opportunités entièrement financées',
        'resource2_title' => 'Délais Visa 2024',
        'resource2_desc' => 'Délais mis à jour pour toutes destinations',
        'resource3_title' => 'Tendances Marché du Travail',
        'resource3_desc' => 'Carrières demandées par pays',
        'read_more' => 'Lire Plus',
        
        // CTA Section
        'cta_title' => 'Prêt à commencer votre voyage ?',
        'cta_description' => 'Réservez une consultation gratuite avec nos conseillers experts.',
        'book_consultation' => 'Réserver une consultation',
        'download_brochure' => 'Télécharger la brochure',
        
        // Card Translations
        'card_apply' => 'Postuler maintenant',
        'card_copy' => 'Copier le lien',
        'card_continue' => 'Récupérer la demande',
        'card_continue_hint' => 'Vous avez un identifiant ? Récupérez votre demande enregistrée.',
        'resume_modal_title_tpl' => 'Récupérer votre demande de {service}',
        'resume_modal_sub_tpl' => 'Saisissez l’identifiant utilisateur de votre confirmation (exemple : {example}) — ou recherchez par nom / email ci-dessous.',
        'resume_label' => 'Identifiant utilisateur',
        'resume_submit' => 'Récupérer',
        'resume_cancel' => 'Annuler',
        'resume_loading' => 'Vérification…',
        'resume_error_generic' => 'Une erreur s’est produite. Réessayez.',
        'resume_error_empty' => 'Veuillez saisir votre identifiant utilisateur.',
        'resume_search_label' => 'Rechercher par nom ou email',
        'resume_search_placeholder' => 'Tapez au moins 2 caractères…',
        'resume_search_hint_tpl' => 'Recherche dans les demandes {service}. Cliquez sur un résultat pour utiliser cet identifiant ou copiez-le.',
        'resume_search_empty' => 'Aucune demande correspondante.',
        'resume_search_short' => 'Tapez une recherche plus longue.',
        'resume_search_searching' => 'Recherche…',
        'resume_copy' => 'Copier',
        'resume_copy_done' => 'Copié !',
        'resume_use' => 'Utiliser',
        
        // Card 1: Study & Work Abroad
        'card1_title' => 'Étudier & Travailler à l\'Étranger',
        'card1_subtitle' => 'Universités, emplois, visas – tout au même endroit',
        'card1_description' => 'Soutien complet pour votre parcours international.',
        'card1_point1' => 'Candidatures universitaires',
        'card1_point2' => 'Support visa travail',
        'card1_point3' => 'Guidance conseiller',
        
        // Card 2: Scholarships & Loans
        'card2_title' => 'Bourses & Prêts',
        'card2_subtitle' => 'Solutions de financement adaptées',
        'card2_description' => 'Accédez à des programmes d\'aide financière abordable.',
        'card2_point1' => 'Bourses jusqu\'à 90%',
        'card2_point2' => 'Prêts éducation (Canada & USA)',
        'card2_point3' => 'Support financier',
        
        // Card 3: I-20 Application
        'card3_title' => 'Demande I-20',
        'card3_subtitle' => 'Traitement rapide pour les USA',
        'card3_description' => 'Processus de demande I-20 rationalisé.',
        'card3_point1' => 'Conforme SEVIS',
        'card3_point2' => 'Coordination universitaire',
        'card3_point3' => 'Préparation entretien',
        
        // Card 4: Credit Transfer
        'card4_title' => 'Transfert de Crédits',
        'card4_subtitle' => 'Transférez vos crédits',
        'card4_description' => 'Maximisez vos progrès académiques.',
        'card4_point1' => 'Évaluation relevés',
        'card4_point2' => 'Équivalence de cours',
        'card4_point3' => 'Durée réduite',
        
        // Card 5: Visa Application
        'card5_title' => 'Demande de Visa',
        'card5_subtitle' => 'Visas études & visite',
        'card5_description' => 'Support complet pour les demandes de visa.',
        'card5_point1' => 'Préparation documents',
        'card5_point2' => 'Entretiens simulés',
        'card5_point3' => 'Taux d\'approbation élevé',
        
        // Card 6: Apply for Job
        'card6_title' => 'Postuler à un Emploi',
        'card6_subtitle' => 'Opportunités en Europe',
        'card6_description' => 'Lancez votre carrière internationale.',
        'card6_point1' => 'Support placement',
        'card6_point2' => 'Assistance logement',
        'card6_point3' => 'Transfert aéroport',
        
        // Card 7: Canada Medical Exams
        'card7_title' => 'Examens Médicaux Canada',
        'card7_subtitle' => 'Services d\'examens médicaux pour visa Canada',
        'card7_description' => 'Support complet pour les examens médicaux requis pour l\'immigration canadienne.',
        'card7_point1' => 'Centres médicaux approuvés',
        'card7_point2' => 'Traitement rapide',
        'card7_point3' => 'Assistance documents',
        
        // Card 8: Francophonie Mobility
        'card8_title' => 'Mobilité Francophone Canada',
        'card8_subtitle' => 'Visa travail — Mobilité Francophone',
        'card8_description' => 'Postulez au programme Mobilité Francophone pour opportunités de travail au Canada.',
        'card8_point1' => 'Évaluation profil français & anglais',
        'card8_point2' => 'Formulaire candidat complet',
        'card8_point3' => 'Suivi par email & revue documents',

        // Card 9: Russian Employment Opportunities
        'card9_title' => 'Opportunités d\'Emploi en Russie',
        'card9_subtitle' => 'Formation professionnelle avec le russe',
        'card9_description' => 'Les participants reçoivent une formation professionnelle tout en étudiant le russe et peuvent être placés dans l\'un des domaines suivants. Tous les postes combinent expérience pratique et apprentissage du russe.',
        'card9_point1' => 'Transport routier (Chauffeur)',
        'card9_point2' => 'Service & Hôtellerie',
        'card9_point3' => 'Opérateur de production',
        'card9_point4' => 'Restauration',
        'card9_point5' => 'Logistique',
        'card9_point6' => 'Travaux d\'installation',
        'card9_point7' => 'Travaux de carrelage',

        // Card 10: South Korea Event Participation
        'card10_title' => 'FORMULAIRE DE PARTICIPATION — ÉVÉNEMENT CORÉE DU SUD',
        'card10_subtitle' => 'Inscription à l\'événement et soutien documentaire',
        'card10_description' => 'Inscrivez-vous à l\'événement en Corée du Sud. Envoyez vos informations de base, le scan du passeport et le CV pour examen.',
        'card10_point1' => 'Pièce jointe passeport',
        'card10_point2' => 'Téléversement du CV',
        'card10_point3' => 'Informations personnelles et événement',
        'card10_point4' => 'Confirmation par e-mail avec référence',
        
        // Page Metadata
        'page_description' => 'ScholarSync Global - Votre parcours complet vers la réussite de l\'éducation internationale et de carrière.',
        'page_title' => 'ScholarSync Global - Votre parcours vers le succès',

        'home_db_testimonials_title' => 'Ce que disent nos clients',
        'home_db_testimonials_sub' => 'Témoignages réels d’étudiants et de professionnels que nous avons accompagnés.',
        'home_db_testimonials_view' => 'Tous les témoignages',
        'home_db_testimonials_empty' => '',
    ]
];

// Function to get index translation
function it($key) {
    global $index_translations, $current_lang;
    return isset($index_translations[$current_lang][$key]) ? $index_translations[$current_lang][$key] : $key;
}

// Per-card retrieval metadata (table + placeholder + example) consumed by the Retrieve Application modal.
$cardRetrievalMeta = [
    'admissions' => [
        'service'     => $current_lang === 'fr' ? 'Études & Travail à l\'étranger' : 'Study & Work Abroad',
        'table_label' => 'student_applications',
        'placeholder' => 'user_…',
        'example'     => 'user_1b69091a796e',
    ],
    'scholarships' => [
        'service'     => $current_lang === 'fr' ? 'Bourses & Prêts' : 'Scholarships & Loans',
        'table_label' => 'master_loan_applications',
        'placeholder' => 'user-…',
        'example'     => 'user-1774818151982-9364',
    ],
    'i20' => [
        'service'     => $current_lang === 'fr' ? 'Demande I-20' : 'I-20 Application',
        'table_label' => 'form_20_applications',
        'placeholder' => 'user-…',
        'example'     => 'user-1776097077117-7158',
    ],
    'credit' => [
        'service'     => $current_lang === 'fr' ? 'Transfert de Crédits' : 'Credit Transfer',
        'table_label' => 'credit_transfer_applications',
        'placeholder' => 'credit-…',
        'example'     => 'credit-1749906159-3206',
    ],
    'visa' => [
        'service'     => $current_lang === 'fr' ? 'Demande de Visa' : 'Visa Application',
        'table_label' => 'form_17_applications',
        'placeholder' => 'user-…',
        'example'     => 'user-1767198710165-1742',
    ],
    'jobs' => [
        'service'     => $current_lang === 'fr' ? 'Postuler à un Emploi' : 'Apply for Job',
        'table_label' => 'job_applications',
        'placeholder' => 'user-…',
        'example'     => 'user-1774799070299-1688',
    ],
    'medical' => [
        'service'     => $current_lang === 'fr' ? 'Examens Médicaux Canada' : 'Canada Medical Exams',
        'table_label' => 'canada_medical_exams_requests',
        'placeholder' => 'MED_…',
        'example'     => 'MED_69daa0c4de4a2_1775935684',
    ],
    'francophonie' => [
        'service'     => $current_lang === 'fr' ? 'Mobilité Francophone Canada' : 'Canada Francophonie Mobility',
        'table_label' => 'francophonie_mobility_applications',
        'placeholder' => 'fm_…',
        'example'     => 'fm_a1b2c3d4e5f6_1719150000',
    ],
    'employment' => [
        'service'     => $current_lang === 'fr' ? 'Opportunités d\'Emploi en Russie' : 'Russian Employment Opportunities',
        'table_label' => 'employment_opportunities_applications',
        'placeholder' => 'eo_…',
        'example'     => 'eo_a1b2c3d4e5f6_1719150000',
    ],
    'korea_event' => [
        'service'     => $current_lang === 'fr' ? 'Participation événement Corée du Sud' : 'South Korea Event Participation',
        'table_label' => 'korea_event_applications',
        'placeholder' => 'kep_…',
        'example'     => 'kep_a1b2c3d4e5f6_1719150000',
    ],
];

// Define cards with translation keys
$cards = [
    [
        'id' => 'admissions',
        'icon' => '🎓',
        'title_key' => 'card1_title',
        'subtitle_key' => 'card1_subtitle',
        'description_key' => 'card1_description',
        'points_keys' => ['card1_point1', 'card1_point2', 'card1_point3'],
        'form' => 'student-application.php',
        'color' => '#173c6d'
    ],
    [
        'id' => 'scholarships',
        'icon' => '💰',
        'title_key' => 'card2_title',
        'subtitle_key' => 'card2_subtitle',
        'description_key' => 'card2_description',
        'points_keys' => ['card2_point1', 'card2_point2', 'card2_point3'],
        'form' => 'master-loan.php',
        'color' => '#1ba7a0'
    ],
    [
        'id' => 'i20',
        'icon' => '📄',
        'title_key' => 'card3_title',
        'subtitle_key' => 'card3_subtitle',
        'description_key' => 'card3_description',
        'points_keys' => ['card3_point1', 'card3_point2', 'card3_point3'],
        'form' => 'form-20.php',
        'color' => '#102d55'
    ],
    [
        'id' => 'credit',
        'icon' => '🔁',
        'title_key' => 'card4_title',
        'subtitle_key' => 'card4_subtitle',
        'description_key' => 'card4_description',
        'points_keys' => ['card4_point1', 'card4_point2', 'card4_point3'],
        'form' => 'credit_transfer.php',
        'color' => '#173c6d'
    ],
    [
        'id' => 'visa',
        'icon' => '✈️',
        'title_key' => 'card5_title',
        'subtitle_key' => 'card5_subtitle',
        'description_key' => 'card5_description',
        'points_keys' => ['card5_point1', 'card5_point2', 'card5_point3'],
        'form' => 'visa.php',
        'color' => '#1ba7a0'
    ],
    [
        'id' => 'jobs',
        'icon' => '',
        'title_key' => 'card6_title',
        'subtitle_key' => 'card6_subtitle',
        'description_key' => 'card6_description',
        'points_keys' => ['card6_point1', 'card6_point2', 'card6_point3'],
        'form' => 'job-application.php',
        'color' => '#102d55'
    ],
    [
        'id' => 'medical',
        'icon' => '',
        'title_key' => 'card7_title',
        'subtitle_key' => 'card7_subtitle',
        'description_key' => 'card7_description',
        'points_keys' => ['card7_point1', 'card7_point2', 'card7_point3'],
        'form' => 'canada-medical-exams-request.php',
        'color' => '#f4b52e'
    ],
    [
        'id' => 'francophonie',
        'icon' => '🍁',
        'title_key' => 'card8_title',
        'subtitle_key' => 'card8_subtitle',
        'description_key' => 'card8_description',
        'points_keys' => ['card8_point1', 'card8_point2', 'card8_point3'],
        'form' => 'francophonie-mobility-request.php',
        'color' => '#1ba7a0'
    ],
    [
        'id' => 'employment',
        'icon' => '🇷🇺',
        'title_key' => 'card9_title',
        'subtitle_key' => 'card9_subtitle',
        'description_key' => 'card9_description',
        'points_keys' => [
            'card9_point1',
            'card9_point2',
            'card9_point3',
            'card9_point4',
            'card9_point5',
            'card9_point6',
            'card9_point7',
        ],
        'form' => 'employment-opportunities-request.php',
        'color' => '#173c6d'
    ],
    [
        'id' => 'korea_event',
        'icon' => '🇰🇷',
        'title_key' => 'card10_title',
        'subtitle_key' => 'card10_subtitle',
        'description_key' => 'card10_description',
        'points_keys' => [
            'card10_point1',
            'card10_point2',
            'card10_point3',
            'card10_point4',
        ],
        'form' => 'korea-event-participation-request.php',
        'color' => '#CD2E3A'
    ]
];

// Define testimonials
$testimonials = [
    ['key' => 'testimonial1', 'initial' => 'SM'],
    ['key' => 'testimonial2', 'initial' => 'JK'],
    ['key' => 'testimonial3', 'initial' => 'PS'],
    ['key' => 'testimonial4', 'initial' => 'AR'],
    ['key' => 'testimonial5', 'initial' => 'LT'],
    ['key' => 'testimonial6', 'initial' => 'DL'],
    ['key' => 'testimonial7', 'initial' => 'MG'],
    ['key' => 'testimonial8', 'initial' => 'KY'],
    ['key' => 'testimonial9', 'initial' => 'RP'],
    ['key' => 'testimonial10', 'initial' => 'SM']
];

// Partner universities (from scholarsyncglobal.ca)
$universities = [
    ['name' => 'University Canada West', 'country' => 'Canada', 'rank' => 'Partner'],
    ['name' => 'IC University of Applied Sciences', 'country' => 'Germany', 'rank' => 'Partner'],
    ['name' => 'Pacific Link College', 'country' => 'Canada', 'rank' => 'Partner'],
    ['name' => 'CIMT College', 'country' => 'Canada', 'rank' => 'Partner'],
    ['name' => 'The Language Gallery Canada', 'country' => 'Canada', 'rank' => 'Partner'],
    ['name' => 'SAU', 'country' => 'Canada', 'rank' => 'Partner'],
    ['name' => 'Trebas Institute', 'country' => 'Canada', 'rank' => 'Partner'],
    ['name' => 'University of Niagara Falls Canada', 'country' => 'Canada', 'rank' => 'Partner'],
    ['name' => 'GUS', 'country' => 'International', 'rank' => 'Partner'],
    ['name' => 'GGE', 'country' => 'International', 'rank' => 'Partner'],
    ['name' => 'Lasalle College', 'country' => 'Canada', 'rank' => 'Partner'],
];

$study_abroad_countries = ['Canada', 'USA', 'Germany', 'Turkey', 'Ireland', 'Netherlands', 'Poland', 'India', 'China', 'South Korea'];
$visa_countries = ['Canada', 'USA', 'Germany', 'Turkey', 'China', 'India', 'Azerbaijan', 'Russia', 'Poland', 'Armenia', 'UK', 'Dubai — U.A.E'];

// Define destinations
$destinations = [
    ['country' => 'Canada', 'flag' => '🇨🇦', 'students' => '1500+', 'description' => 'Top destination for quality education and PR'],
    ['country' => 'USA', 'flag' => '🇺🇸', 'students' => '1200+', 'description' => 'World-class universities & research opportunities'],
    ['country' => 'UK', 'flag' => '🇬🇧', 'students' => '900+', 'description' => 'Historic universities & 2-year post-study work'],
    ['country' => 'Australia', 'flag' => '🇦🇺', 'students' => '800+', 'description' => 'Sunny lifestyle & strong job market'],
    ['country' => 'Germany', 'flag' => '🇩🇪', 'students' => '700+', 'description' => 'Tuition-free education & engineering hub'],
    ['country' => 'Netherlands', 'flag' => '🇳🇱', 'students' => '500+', 'description' => 'English-taught programs & innovation center']
];

// Get card parameter from URL for direct access (shared link shows ONLY that card).
$allowed_card_ids = array_column($cards, 'id');
$direct_card = isset($_GET['card']) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $_GET['card']) : null;
if ($direct_card === '' || !in_array($direct_card, $allowed_card_ids, true)) {
    $direct_card = null;
}
$card_only_mode = $direct_card !== null;

$pageTitle = it('page_title');
$pageDescription = it('page_description');
include 'header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
<style>
/* Index landing — modern layout */
.landing-root {
  --lp-green: #173c6d;
  --lp-green-mid: #234f84;
  --lp-blue: #1ba7a0;
  --lp-red: #f4b52e;
  --lp-bg: #f4f6f3;
  --lp-surface: #ffffff;
  --lp-text: #0f172a;
  --lp-muted: #64748b;
  --lp-radius: 16px;
  --lp-shadow: 0 4px 24px rgba(23, 60, 109, 0.08);
  --lp-font: "Plus Jakarta Sans", "Inter", system-ui, sans-serif;
  font-family: var(--lp-font);
  color: var(--lp-text);
  background: var(--lp-bg);
}

.landing-root * { box-sizing: border-box; }

.hero-landing {
  position: relative;
  overflow: hidden;
  padding: clamp(2.5rem, 6vw, 5rem) clamp(1.25rem, 4vw, 3rem) clamp(3rem, 8vw, 5rem);
  background:
    linear-gradient(135deg, rgba(30, 77, 43, 0.92) 0%, rgba(20, 50, 30, 0.95) 45%, rgba(15, 35, 25, 0.98) 100%),
    url("https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=2000&q=60") center/cover no-repeat;
  color: #fff;
}

.hero-landing::after {
  content: "";
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse 80% 60% at 70% 20%, rgba(54, 97, 185, 0.25), transparent 55%);
  pointer-events: none;
}

.hero-inner {
  position: relative;
  z-index: 1;
  max-width: 1120px;
  margin: 0 auto;
  display: grid;
  gap: 2rem;
  grid-template-columns: 1fr;
}

@media (min-width: 900px) {
  .hero-inner { grid-template-columns: 1.15fr 0.85fr; align-items: center; }
}

.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.88);
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.18);
  padding: 0.45rem 0.9rem;
  border-radius: 999px;
  margin-bottom: 1rem;
}

.hero-landing h1 {
  font-size: clamp(1.85rem, 4vw, 2.75rem);
  font-weight: 800;
  line-height: 1.15;
  letter-spacing: -0.03em;
  margin: 0 0 1rem;
}

.hero-lead {
  font-size: 1.05rem;
  line-height: 1.65;
  color: rgba(255,255,255,0.88);
  max-width: 36rem;
  margin: 0 0 1.75rem;
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}

.btn-lp {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.85rem 1.35rem;
  font-size: 0.95rem;
  font-weight: 600;
  border-radius: 12px;
  border: none;
  cursor: pointer;
  font-family: inherit;
  transition: transform 0.2s, box-shadow 0.2s;
}

.btn-lp-primary {
  background: linear-gradient(135deg, #e23636, #b81818);
  color: #fff;
  box-shadow: 0 8px 24px rgba(180, 24, 24, 0.45);
}

.btn-lp-primary:hover { transform: translateY(-2px); }

.btn-lp-ghost {
  background: rgba(255,255,255,0.12);
  color: #fff;
  border: 1px solid rgba(255,255,255,0.35);
}

.btn-lp-ghost:hover { background: rgba(255,255,255,0.2); }

.hero-quick {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: center;
}

.hero-quick a {
  color: #fff;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.9rem;
  padding: 0.4rem 0.85rem;
  border-radius: 8px;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.15);
  transition: background 0.2s;
}

.hero-quick a:hover { background: rgba(255,255,255,0.18); }

.hero-card {
  background: rgba(255,255,255,0.08);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: var(--lp-radius);
  padding: 1.5rem 1.35rem;
}

.hero-card h3 {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  opacity: 0.85;
  margin: 0 0 1rem;
  font-weight: 700;
}

.hero-stat-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.hero-stat {
  padding: 0.75rem 0;
  border-top: 1px solid rgba(255,255,255,0.15);
}

.hero-stat strong { display: block; font-size: 1.35rem; font-weight: 800; }
.hero-stat span { font-size: 0.8rem; opacity: 0.85; }

.trust-bar {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 1.5rem 2.5rem;
  padding: 1rem 1.25rem;
  background: var(--lp-surface);
  border-bottom: 1px solid #e2e8e0;
  font-size: 0.9rem;
  color: var(--lp-muted);
}

.trust-bar i { color: var(--lp-green-mid); margin-right: 0.35rem; }

/* Database testimonials (superadmin-managed) */
.pcvc-testimonials-block {
  padding: clamp(2rem, 5vw, 3rem) clamp(1.25rem, 4vw, 2rem);
  background: linear-gradient(180deg, #f8faf8 0%, var(--lp-bg) 100%);
  border-bottom: 1px solid #e2e8e0;
}
.pcvc-testimonials-inner {
  max-width: 1120px;
  margin: 0 auto;
}
.pcvc-testimonials-head {
  text-align: center;
  margin-bottom: 1.75rem;
}
.pcvc-testimonials-head .pcvc-testimonials-title {
  font-size: clamp(1.35rem, 3vw, 1.75rem);
  font-weight: 800;
  color: var(--lp-green);
  letter-spacing: -0.02em;
  margin: 0 0 0.5rem;
}
.pcvc-testimonials-sub {
  margin: 0;
  font-size: 0.95rem;
  color: var(--lp-muted);
  max-width: 42rem;
  margin-left: auto;
  margin-right: auto;
}
.pcvc-testimonials-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.25rem;
}
@media (min-width: 720px) {
  .pcvc-testimonials-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (min-width: 1024px) {
  .pcvc-testimonials-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
.pcvc-testimonial-card {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
  background: var(--lp-surface);
  border-radius: var(--lp-radius);
  padding: 1.1rem 1.15rem;
  box-shadow: var(--lp-shadow);
  border: 1px solid rgba(30, 77, 43, 0.08);
  min-height: 100%;
}
.pcvc-testimonial-photo-wrap {
  flex-shrink: 0;
  width: 72px;
  height: 72px;
  border-radius: 12px;
  overflow: hidden;
  background: #e8f0e8;
}
.pcvc-testimonial-photo {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.pcvc-testimonial-photo-fallback {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #173c6d, #102d55);
}
.pcvc-testimonial-quote {
  margin: 0 0 0.75rem;
  font-size: 0.88rem;
  line-height: 1.55;
  color: var(--lp-text);
  font-style: italic;
}
.pcvc-testimonial-meta { display: flex; flex-direction: column; gap: 0.15rem; }
.pcvc-testimonial-name {
  font-size: 0.88rem;
  font-weight: 700;
  font-style: normal;
  color: var(--lp-green);
}
.pcvc-testimonial-role {
  font-size: 0.78rem;
  color: var(--lp-muted);
}
.pcvc-testimonials-viewall {
  text-align: center;
  margin-top: 1.5rem;
}
.pcvc-testimonials-viewall a {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-weight: 600;
  font-size: 0.9rem;
  color: #c41e1e;
  text-decoration: none;
}
.pcvc-testimonials-viewall a:hover { text-decoration: underline; }

.dest-section {
  padding: clamp(2.5rem, 5vw, 4rem) clamp(1.25rem, 4vw, 2rem);
  max-width: 1180px;
  margin: 0 auto;
}

.dest-block { margin-bottom: 2.25rem; }
.dest-block:last-child { margin-bottom: 0; }

.dest-block h2 {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--lp-green);
  margin: 0 0 1rem;
  letter-spacing: -0.02em;
}

.pill-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.pill {
  display: inline-block;
  padding: 0.45rem 0.85rem;
  background: var(--lp-surface);
  border: 1px solid #d8e0d8;
  border-radius: 999px;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--lp-text);
  box-shadow: var(--lp-shadow);
}

.pill-visa { border-color: #c5d4ef; background: linear-gradient(180deg, #fff, #f4f7fd); }

.pillars-section {
  padding: 0 clamp(1.25rem, 4vw, 2rem) clamp(3rem, 6vw, 4.5rem);
  max-width: 1180px;
  margin: 0 auto;
}

.pillars-head {
  text-align: center;
  max-width: 40rem;
  margin: 0 auto 2rem;
}

.pillars-head h2 {
  font-size: clamp(1.5rem, 3vw, 2rem);
  font-weight: 800;
  color: var(--lp-green);
  margin: 0 0 0.5rem;
}

.pillars-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1.25rem;
}

.pillar-card {
  background: var(--lp-surface);
  padding: 1.5rem 1.35rem;
  border-radius: var(--lp-radius);
  border: 1px solid #e8ede8;
  box-shadow: var(--lp-shadow);
  transition: transform 0.25s, box-shadow 0.25s;
}

.pillar-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 40px rgba(30, 77, 43, 0.12);
}

.pillar-card .num {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--lp-green), var(--lp-green-mid));
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.9rem;
  margin-bottom: 1rem;
}

.pillar-card h3 {
  font-size: 1.05rem;
  font-weight: 800;
  margin: 0 0 0.5rem;
  color: var(--lp-text);
}

.pillar-card p {
  margin: 0;
  font-size: 0.92rem;
  line-height: 1.55;
  color: var(--lp-muted);
}

.features-section.lp {
  padding: clamp(2.5rem, 5vw, 4rem) clamp(1.25rem, 4vw, 2rem);
  background: var(--lp-surface);
  border-top: 1px solid #e8ede8;
  border-bottom: 1px solid #e8ede8;
}

.features-section.lp .section-header { margin-bottom: 2rem; }

.features-section.lp .section-title {
  font-size: clamp(1.5rem, 3vw, 2rem);
  font-weight: 800;
  color: var(--lp-green);
  text-align: center;
  margin: 0 auto 0.5rem;
  position: relative;
  display: block;
}

.features-section.lp .section-title::after {
  content: "";
  display: block;
  width: 64px;
  height: 4px;
  margin: 0.75rem auto 0;
  border-radius: 4px;
  background: linear-gradient(90deg, var(--lp-red), var(--lp-blue));
}

.features-grid.lp {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.25rem;
}

.feature-card.lp {
  text-align: center;
  padding: 1.5rem 1.15rem;
  background: var(--lp-bg);
  border-radius: var(--lp-radius);
  border: 1px solid #e4ebe4;
  transition: transform 0.2s, box-shadow 0.2s;
}

.feature-card.lp:hover {
  transform: translateY(-4px);
  box-shadow: var(--lp-shadow);
}

.feature-card.lp .feature-icon {
  width: 56px;
  height: 56px;
  margin: 0 auto 1rem;
  background: linear-gradient(135deg, rgba(54,97,185,0.12), rgba(30,77,43,0.1));
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  color: var(--lp-blue);
}

.feature-card.lp h4 {
  font-size: 1rem;
  font-weight: 800;
  color: var(--lp-green);
  margin: 0 0 0.5rem;
}

.feature-card.lp p {
  font-size: 0.88rem;
  color: var(--lp-muted);
  line-height: 1.5;
  margin: 0;
}

.contact-strip-lp {
  padding: clamp(2rem, 4vw, 3rem) clamp(1.25rem, 4vw, 2rem);
  background: linear-gradient(135deg, var(--lp-green) 0%, #163c22 100%);
  color: #fff;
}

.contact-strip-inner {
  max-width: 1000px;
  margin: 0 auto;
  text-align: center;
}

.contact-strip-inner h2 {
  font-size: clamp(1.25rem, 2.5vw, 1.6rem);
  font-weight: 800;
  margin: 0 0 1.25rem;
  line-height: 1.35;
}

.contact-grid-lp {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
  text-align: left;
}

.contact-item-lp {
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 12px;
  padding: 1rem 1.1rem;
}

.contact-item-lp a {
  color: #fff;
  text-decoration: none;
  font-weight: 700;
  word-break: break-word;
}

.contact-item-lp a:hover { text-decoration: underline; }

.contact-item-lp a.contact-phone-second {
  display: block;
  margin-top: 0.45rem;
  font-weight: 600;
  opacity: 0.95;
}

.contact-item-lp small {
  display: block;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  opacity: 0.85;
  margin-bottom: 0.35rem;
}

/* Legacy sections — harmonize */
.landing-root .stats-section {
  background: linear-gradient(180deg, #eef2ee, var(--lp-bg)) !important;
  padding: 3rem 1.25rem !important;
}

.landing-root .services-section {
  background: var(--lp-bg) !important;
}

.landing-root .service-card {
  border-radius: var(--lp-radius) !important;
  box-shadow: var(--lp-shadow) !important;
}

.landing-root .universities-section {
  background: var(--lp-surface) !important;
}

.landing-root .destinations-section {
  background: linear-gradient(180deg, var(--lp-bg), #eef2ee) !important;
}

.landing-root .process-section {
  background: var(--lp-surface) !important;
}

.landing-root .testimonials-section {
  border-radius: 0 !important;
}

.landing-root .resources-section {
  background: var(--lp-bg) !important;
}

.landing-root .cta-section {
  background: linear-gradient(135deg, var(--lp-green-mid), var(--lp-green)) !important;
}

.landing-root .section-title {
  font-family: var(--lp-font);
}

.partners-compact {
  padding: clamp(2rem, 4vw, 3rem) clamp(1.25rem, 4vw, 2rem);
  max-width: 900px;
  margin: 0 auto;
  text-align: center;
}

.partners-compact p {
  color: var(--lp-muted);
  font-size: 0.95rem;
  line-height: 1.6;
  margin: 0;
}

/* Services grid & cards (apply / copy links) */
.section-padding { padding: 70px 20px; }
.section-header { text-align: center; max-width: 800px; margin: 0 auto 50px; }
.section-title {
  font-size: 2.25rem; font-weight: 800; color: var(--lp-green); margin-bottom: 12px;
  position: relative; display: inline-block;
}
.section-title::after {
  content: ""; position: absolute; bottom: -10px; left: 50%; transform: translateX(-50%);
  width: 72px; height: 4px;
  background: linear-gradient(90deg, var(--lp-red), var(--lp-blue)); border-radius: 3px;
}
.section-description { font-size: 1.05rem; color: var(--lp-muted); max-width: 640px; margin: 0 auto; line-height: 1.6; }

.services-grid {
  max-width: 1400px; margin: 0 auto;
  display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 28px; padding: 20px 0;
}
.service-card {
  display: none; background: #fff; border-radius: 20px; padding: 32px 28px;
  box-shadow: var(--lp-shadow); border: 1px solid #e8ede8; transition: transform 0.3s, box-shadow 0.3s;
  position: relative; overflow: hidden;
}
.service-card.show-card { display: block; animation: lpFadeUp 0.5s ease forwards; }
.service-card.highlight-card { box-shadow: 0 0 0 3px rgba(196, 30, 30, 0.35), var(--lp-shadow); transform: translateY(-4px); }
.service-card::before {
  content: ""; position: absolute; top: 0; left: 0; width: 5px; height: 100%;
  background: linear-gradient(to bottom, var(--lp-green), var(--lp-blue));
}
.service-card:hover { transform: translateY(-6px); box-shadow: 0 16px 48px rgba(30, 77, 43, 0.12); }
.card-header { display: flex; align-items: flex-start; gap: 18px; margin-bottom: 20px; }
.card-icon {
  width: 68px; height: 68px; background: linear-gradient(135deg, rgba(30,77,43,0.08), rgba(54,97,185,0.1));
  border-radius: 16px; display: flex; align-items: center; justify-content: center;
  font-size: 30px; flex-shrink: 0;
}
.card-title-group h3 { font-size: 1.25rem; font-weight: 800; color: var(--lp-green); margin: 0 0 6px; }
.card-subtitle { font-size: 0.95rem; color: var(--lp-red); font-weight: 600; margin: 0; }
.card-description { color: var(--lp-muted); line-height: 1.6; margin-bottom: 20px; font-size: 0.95rem; }
.card-features { list-style: none; margin: 0 0 24px; padding: 0; }
.card-features li {
  padding: 10px 0 10px 28px; position: relative; font-size: 0.92rem;
  border-bottom: 1px solid #f1f5f9; color: var(--lp-text);
}
.card-features li:last-child { border-bottom: none; }
.card-features li::before { content: "✓"; position: absolute; left: 0; color: var(--lp-red); font-weight: 800; }
.card-actions { display: flex; flex-wrap: wrap; gap: 12px; }
.card-button {
  flex: 1 1 160px; padding: 14px 18px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer;
  font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit;
  transition: transform 0.2s, box-shadow 0.2s;
}
.apply-button {
  background: linear-gradient(135deg, var(--lp-green), var(--lp-blue)); color: #fff;
  box-shadow: 0 6px 18px rgba(30, 77, 43, 0.2);
}
.apply-button:hover { transform: translateY(-2px); }
.copy-button { background: #fff; color: var(--lp-green); border: 2px solid #e2e8f0; }
.copy-button:hover { background: #f8fafc; border-color: var(--lp-green); }
.resume-button {
  flex: 1 1 100%;
  background: #fff;
  color: var(--lp-blue);
  border: 2px solid var(--lp-blue);
}
.resume-button:hover { background: #f0f6ff; transform: translateY(-2px); }

.resume-modal {
  position: fixed; inset: 0; z-index: 20000;
  display: none; align-items: center; justify-content: center; padding: 20px;
}
.resume-modal.is-open { display: flex; }
.resume-modal__backdrop {
  position: absolute; inset: 0; background: rgba(15, 23, 42, 0.55);
}
.resume-modal__dialog {
  position: relative; z-index: 1; width: 100%; max-width: 440px;
  background: #fff; border-radius: 16px; padding: 24px 22px;
  box-shadow: 0 20px 50px rgba(0,0,0,0.2);
}
.resume-modal__dialog h3 { margin: 0 0 8px; font-size: 1.2rem; color: var(--lp-green); }
.resume-modal__dialog p { margin: 0 0 16px; font-size: 0.9rem; color: var(--lp-muted); line-height: 1.5; }
.resume-modal__dialog label { display: block; font-weight: 600; margin-bottom: 6px; color: var(--lp-text); font-size: 0.9rem; }
.resume-modal__dialog input[type="text"] {
  width: 100%; padding: 12px 14px; border-radius: 10px; border: 1px solid #e2e8f0;
  font-size: 1rem; margin-bottom: 10px;
}
.resume-modal__error { color: #b91c1c; font-size: 0.88rem; min-height: 1.25em; margin: 0 0 12px; }
.resume-modal__hint { font-size: 0.8rem; color: var(--lp-muted); margin: -4px 0 8px; }
.resume-modal__table {
  font-size: 0.75rem; color: var(--lp-muted); margin: -8px 0 14px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
.resume-modal__table strong { color: var(--lp-green); font-weight: 700; }
.resume-modal__divider {
  display: flex; align-items: center; gap: 10px;
  color: var(--lp-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.08em;
  margin: 10px 0 12px;
}
.resume-modal__divider::before,
.resume-modal__divider::after {
  content: ""; flex: 1; height: 1px; background: #e2e8f0;
}
.resume-search-results {
  max-height: 220px; overflow-y: auto;
  border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 12px;
  display: none; background: #fff;
}
.resume-search-results.is-active { display: block; }
.resume-search-results__item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 10px 12px; border-bottom: 1px solid #f1f5f9;
}
.resume-search-results__item:last-child { border-bottom: none; }
.resume-search-results__main { flex: 1; min-width: 0; }
.resume-search-results__name { font-weight: 600; color: var(--lp-text); font-size: 0.95rem; }
.resume-search-results__meta {
  font-size: 0.8rem; color: var(--lp-muted);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  margin-top: 1px;
}
.resume-search-results__id {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.8rem; color: var(--lp-green); margin-top: 4px; word-break: break-all;
  background: #f0f6f1; padding: 2px 6px; border-radius: 6px; display: inline-block;
}
.resume-search-results__actions { display: flex; gap: 6px; flex-shrink: 0; }
.resume-search-results__btn {
  border: 1px solid #e2e8f0; background: #fff; color: var(--lp-text);
  padding: 6px 10px; font-size: 0.78rem; border-radius: 8px; cursor: pointer; font-weight: 600;
}
.resume-search-results__btn:hover { background: #f8fafc; border-color: var(--lp-green); color: var(--lp-green); }
.resume-search-results__btn--primary {
  background: linear-gradient(135deg, var(--lp-green), var(--lp-blue));
  color: #fff; border-color: transparent;
}
.resume-search-results__btn--primary:hover { color: #fff; }
.resume-search-results__empty {
  padding: 14px; text-align: center; color: var(--lp-muted); font-size: 0.88rem;
}
.resume-modal__actions { display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
.resume-modal__actions button {
  padding: 10px 18px; border-radius: 10px; font-weight: 600; cursor: pointer; border: none; font-family: inherit;
}
#resumeModalCancel { background: #f1f5f9; color: var(--lp-text); }
#resumeModalSubmit {
  background: linear-gradient(135deg, var(--lp-green), var(--lp-blue)); color: #fff;
}

.direct-card-header {
  display: none; background: linear-gradient(135deg, var(--lp-green), #163c22);
  color: #fff; padding: 28px 20px; text-align: center; margin: 0 0 32px; border-radius: 0 0 20px 20px;
}
.direct-card-header.show-header { display: block; animation: lpFadeUp 0.4s ease; }
.direct-card-header h2 { margin: 0 0 8px; font-size: 1.5rem; }
.direct-card-header p { opacity: 0.92; margin-bottom: 16px; }
.back-to-all {
  background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.35);
  padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-family: inherit;
}

/* Shared ?card= links: show ONLY the selected service card (no other index content). */
body.card-only-mode > header,
body.card-only-mode .footer-main,
body.card-only-mode .footer-chat-system,
body.card-only-mode .pcvc-pdf-float,
body.card-only-mode [class*="pdf-float"],
body.card-only-mode .floating-action,
body.card-only-mode .wa-float {
  display: none !important;
}
body.card-only-mode .landing-root > *:not(#directCardHeader):not(#services):not(#resumeApplicationModal) {
  display: none !important;
}
body.card-only-mode #directCardHeader {
  display: block !important;
  margin: 0;
  border-radius: 0;
}
body.card-only-mode #services {
  display: block !important;
  padding: 1.25rem 1rem 2.5rem !important;
  min-height: calc(100vh - 120px);
  background: #f4f6f3;
}
body.card-only-mode #services > .section-header {
  display: none !important;
}
body.card-only-mode #services .services-grid {
  display: grid !important;
  grid-template-columns: minmax(0, 480px);
  justify-content: center;
  max-width: 520px;
  margin: 0 auto;
  padding: 0;
  gap: 0;
}
body.card-only-mode #services .service-card {
  display: none !important;
}
body.card-only-mode #services .service-card.show-card {
  display: block !important;
  width: 100%;
  margin: 0;
}
body.card-only-mode {
  background: #f4f6f3;
  margin: 0;
  padding: 0;
}

.universities-grid {
  max-width: 1200px; margin: 0 auto;
  display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;
}
.university-card {
  background: var(--lp-bg); padding: 24px 20px; border-radius: 14px; text-align: center;
  border: 1px solid #e4ebe4; transition: transform 0.2s, box-shadow 0.2s;
}
.university-card:hover { transform: translateY(-4px); box-shadow: var(--lp-shadow); }
.university-flag { font-size: 2rem; margin-bottom: 10px; }
.university-card h4 { font-size: 1.05rem; font-weight: 700; color: var(--lp-green); margin: 0 0 6px; }
.university-country { color: var(--lp-muted); font-size: 0.9rem; margin: 0 0 8px; }
.university-rank {
  display: inline-block; margin-top: 6px; padding: 4px 12px; border-radius: 999px;
  font-size: 0.75rem; font-weight: 600; background: linear-gradient(135deg, var(--lp-red), var(--lp-blue)); color: #fff;
}

.destinations-grid {
  max-width: 1200px; margin: 0 auto;
  display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;
}
.destination-card {
  background: #fff; padding: 24px; border-radius: 14px; border: 1px solid #e8ede8;
  display: flex; align-items: center; gap: 18px; box-shadow: var(--lp-shadow);
}
.destination-flag { font-size: 2.5rem; flex-shrink: 0; }
.destination-info h4 { margin: 0 0 6px; font-size: 1.15rem; color: var(--lp-green); }
.destination-stats { color: var(--lp-red); font-weight: 600; font-size: 0.9rem; margin: 0 0 6px; }
.destination-desc { color: var(--lp-muted); font-size: 0.88rem; margin: 0; line-height: 1.45; }

.process-steps { max-width: 900px; margin: 0 auto; position: relative; }
.process-step { display: flex; gap: 24px; margin-bottom: 36px; align-items: flex-start; }
.step-number {
  width: 56px; height: 56px; flex-shrink: 0; border-radius: 50%;
  background: linear-gradient(135deg, var(--lp-green), var(--lp-blue)); color: #fff;
  display: flex; align-items: center; justify-content: center; font-size: 1.35rem; font-weight: 800;
  border: 4px solid #fff; box-shadow: var(--lp-shadow);
}
.step-content h4 { margin: 0 0 8px; font-size: 1.15rem; color: var(--lp-green); }
.step-content p { margin: 0; color: var(--lp-muted); line-height: 1.55; font-size: 0.95rem; }

.testimonials-section {
  padding: 80px 20px; background: linear-gradient(135deg, var(--lp-green) 0%, #163c22 100%); color: #fff;
}
.testimonials-header { text-align: center; margin-bottom: 40px; }
.testimonials-header .section-title { color: #fff; }
.testimonials-header .section-title::after { background: linear-gradient(90deg, #fff, rgba(255,255,255,0.5)); }
.testimonials-header .section-subtitle { color: rgba(255,255,255,0.88); font-size: 1.05rem; margin: 0; }
.testimonials-track {
  display: flex; gap: 24px; padding: 12px 8px; overflow-x: auto; scroll-behavior: smooth;
  scrollbar-width: none; -ms-overflow-style: none;
}
.testimonials-track::-webkit-scrollbar { display: none; }
.testimonial-card {
  min-width: 320px; flex-shrink: 0; background: rgba(255,255,255,0.1); backdrop-filter: blur(8px);
  border: 1px solid rgba(255,255,255,0.15); border-radius: 18px; padding: 28px 24px;
}
.testimonial-text { font-size: 0.98rem; line-height: 1.65; margin: 0 0 20px; color: rgba(255,255,255,0.95); }
.testimonial-author { display: flex; gap: 14px; align-items: center; }
.author-avatar {
  width: 52px; height: 52px; border-radius: 50%;
  background: linear-gradient(135deg, var(--lp-red), var(--lp-blue)); color: #fff;
  display: flex; align-items: center; justify-content: center; font-weight: 700;
}
.author-info h5 { margin: 0 0 4px; font-size: 0.95rem; }
.author-info p { margin: 0; font-size: 0.82rem; opacity: 0.85; }
.author-achievement {
  display: inline-block; margin-top: 6px; padding: 4px 10px; border-radius: 999px;
  font-size: 0.72rem; font-weight: 600; background: rgba(255,255,255,0.15); color: #fecaca;
}
.testimonial-controls { display: flex; justify-content: center; gap: 12px; margin-top: 28px; }
.testimonial-btn {
  width: 46px; height: 46px; border-radius: 50%; border: 1px solid rgba(255,255,255,0.25);
  background: rgba(255,255,255,0.1); color: #fff; cursor: pointer; transition: background 0.2s;
}
.testimonial-btn:hover { background: rgba(255,255,255,0.2); }
.testimonial-indicators { display: flex; justify-content: center; gap: 8px; margin-top: 20px; }
.indicator {
  width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,0.3); cursor: pointer; transition: transform 0.2s;
}
.indicator.active { background: #fff; transform: scale(1.15); }

.resources-grid {
  max-width: 1100px; margin: 0 auto;
  display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;
}
.resource-card {
  background: #fff; padding: 28px 24px; border-radius: 16px; border: 1px solid #e8ede8; box-shadow: var(--lp-shadow);
}
.resource-icon {
  width: 52px; height: 52px; border-radius: 12px;
  background: linear-gradient(135deg, rgba(30,77,43,0.1), rgba(54,97,185,0.1));
  display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: var(--lp-green); margin-bottom: 16px;
}
.resource-card h4 { margin: 0 0 10px; font-size: 1.1rem; color: var(--lp-green); }
.resource-card p { margin: 0 0 16px; color: var(--lp-muted); font-size: 0.92rem; line-height: 1.55; }
.resource-link { color: var(--lp-red); font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.resource-link:hover { gap: 10px; }

.cta-section {
  padding: 80px 24px; text-align: center;
  background: linear-gradient(135deg, var(--lp-green-mid), var(--lp-green)); color: #fff;
}
.cta-content { max-width: 640px; margin: 0 auto; }
.cta-content h2 { font-size: clamp(1.6rem, 3vw, 2.2rem); margin: 0 0 12px; font-weight: 800; }
.cta-content > p { font-size: 1.05rem; opacity: 0.92; margin-bottom: 28px; line-height: 1.55; }
.cta-buttons { display: flex; flex-wrap: wrap; gap: 14px; justify-content: center; }
.cta-button {
  padding: 14px 26px; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer; font-family: inherit;
  display: inline-flex; align-items: center; gap: 8px; border: none; transition: transform 0.2s;
}
.cta-button-white { background: #fff; color: var(--lp-green); }
.cta-button-outline { background: transparent; color: #fff; border: 2px solid rgba(255,255,255,0.45); }
.cta-button:hover { transform: translateY(-2px); }

@keyframes lpFadeUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
  .services-grid { grid-template-columns: 1fr; }
  .card-actions { flex-direction: column; }
  .hero-stat-row { grid-template-columns: 1fr 1fr; }
  .trust-bar {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    font-size: 0.82rem;
  }
  .contact-grid-lp {
    grid-template-columns: 1fr;
  }
  .hero-actions {
    flex-direction: column;
    align-items: stretch;
  }
  .btn-lp { width: 100%; justify-content: center; }
  .hero-quick {
    flex-direction: column;
    align-items: stretch;
  }
  .hero-quick a { text-align: center; }
}

@media (max-width: 480px) {
  .hero-landing { padding: 1.75rem 1rem 2.25rem; }
  .hero-eyebrow { font-size: 0.65rem; line-height: 1.35; }
  .landing-root h1 { font-size: 1.55rem !important; }
  .hero-lead { font-size: 0.95rem; }
  .hero-stat-row { grid-template-columns: 1fr; }
  .hero-card { padding: 1.1rem; }
  .pill-row { gap: 0.35rem; }
  .pill { font-size: 0.78rem; padding: 0.4rem 0.65rem; }
  .section-padding { padding: 2.5rem 1rem !important; }
  .section-title { font-size: 1.35rem !important; }
}
</style>

<div class="landing-root">

<section class="hero-landing" id="top">
  <div class="hero-inner">
    <div>
      <p class="hero-eyebrow"><i class="fas fa-leaf" aria-hidden="true"></i> <?php echo htmlspecialchars(it('hero_eyebrow')); ?></p>
      <h1><?php echo htmlspecialchars(it('hero_title')); ?></h1>
      <p class="hero-lead"><?php echo htmlspecialchars(it('hero_description')); ?></p>
      <div class="hero-actions">
        <button type="button" class="btn-lp btn-lp-primary" id="scrollToServices">
          <i class="fas fa-arrow-right"></i> <?php echo htmlspecialchars(it('start_application')); ?>
        </button>
        <button type="button" class="btn-lp btn-lp-ghost" id="scrollToFeatures">
          <?php echo htmlspecialchars(it('learn_more')); ?>
        </button>
      </div>
      <div class="hero-quick">
        <a href="partners.php"><?php echo htmlspecialchars(it('quick_partners')); ?></a>
        <a href="contact.php"><?php echo htmlspecialchars(it('quick_book')); ?></a>
      </div>
    </div>
    <div class="hero-card">
      <h3><?php echo htmlspecialchars(it('trust_reply')); ?> · <?php echo htmlspecialchars(it('trust_support')); ?></h3>
      <div class="hero-stat-row">
        <div class="hero-stat">
          <strong>50+</strong>
          <span><?php echo htmlspecialchars(it('stats_students')); ?></span>
        </div>
        <div class="hero-stat">
          <strong>20+</strong>
          <span><?php echo htmlspecialchars(it('stats_countries')); ?></span>
        </div>
        <div class="hero-stat">
          <strong>$100K+</strong>
          <span><?php echo htmlspecialchars(it('stats_scholarships')); ?></span>
        </div>
        <div class="hero-stat">
          <strong>50+</strong>
          <span><?php echo htmlspecialchars(it('stats_partners')); ?></span>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="trust-bar" aria-label="Trust">
  <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars(it('trust_reply')); ?></span>
  <span><i class="fas fa-headset"></i> <?php echo htmlspecialchars(it('trust_support')); ?></span>
  <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars(it('contact_email')); ?></span>
</section>

<?php if (!empty($home_testimonials)): ?>
<div class="pcvc-testimonials-home-wrap">
<?php
    $rows = $home_testimonials;
    $heading = it('home_db_testimonials_title');
    $sub = it('home_db_testimonials_sub');
    $section_class = 'pcvc-testimonials-home';
    include __DIR__ . '/includes/partials/testimonials_public_block.php';
?>
  <div class="pcvc-testimonials-viewall">
    <a href="testimonials.php"><?php echo htmlspecialchars(it('home_db_testimonials_view'), ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
  </div>
</div>
<?php endif; ?>

<section class="dest-section" aria-labelledby="study-dest-heading">
  <div class="dest-block">
    <h2 id="study-dest-heading"><?php echo htmlspecialchars(it('study_dest_title')); ?></h2>
    <div class="pill-row">
      <?php foreach ($study_abroad_countries as $c): ?>
      <span class="pill"><?php echo htmlspecialchars($c); ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="dest-block">
    <h2 id="visa-dest-heading"><?php echo htmlspecialchars(it('visa_dest_title')); ?></h2>
    <div class="pill-row">
      <?php foreach ($visa_countries as $c): ?>
      <span class="pill pill-visa"><?php echo htmlspecialchars($c); ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="pillars-section" id="pillars" aria-labelledby="pillars-heading">
  <div class="pillars-head">
    <h2 id="pillars-heading"><?php echo htmlspecialchars(it('pillars_title')); ?></h2>
  </div>
  <div class="pillars-grid">
    <article class="pillar-card">
      <div class="num">1</div>
      <h3><?php echo htmlspecialchars(it('pillar1_title')); ?></h3>
      <p><?php echo htmlspecialchars(it('pillar1_desc')); ?></p>
    </article>
    <article class="pillar-card">
      <div class="num">2</div>
      <h3><?php echo htmlspecialchars(it('pillar2_title')); ?></h3>
      <p><?php echo htmlspecialchars(it('pillar2_desc')); ?></p>
    </article>
    <article class="pillar-card">
      <div class="num">3</div>
      <h3><?php echo htmlspecialchars(it('pillar3_title')); ?></h3>
      <p><?php echo htmlspecialchars(it('pillar3_desc')); ?></p>
    </article>
  </div>
</section>

<section class="features-section lp section-padding" id="features">
  <div class="section-header">
    <h2 class="section-title"><?php echo htmlspecialchars(it('features_title')); ?></h2>
  </div>
  <div class="features-grid lp">
    <div class="feature-card lp fade-in">
      <div class="feature-icon"><i class="fas fa-road" aria-hidden="true"></i></div>
      <h4><?php echo htmlspecialchars(it('feature1_title')); ?></h4>
      <p><?php echo htmlspecialchars(it('feature1_desc')); ?></p>
    </div>
    <div class="feature-card lp fade-in">
      <div class="feature-icon"><i class="fas fa-user-tie" aria-hidden="true"></i></div>
      <h4><?php echo htmlspecialchars(it('feature2_title')); ?></h4>
      <p><?php echo htmlspecialchars(it('feature2_desc')); ?></p>
    </div>
    <div class="feature-card lp fade-in">
      <div class="feature-icon"><i class="fas fa-globe-americas" aria-hidden="true"></i></div>
      <h4><?php echo htmlspecialchars(it('feature3_title')); ?></h4>
      <p><?php echo htmlspecialchars(it('feature3_desc')); ?></p>
    </div>
    <div class="feature-card lp fade-in">
      <div class="feature-icon"><i class="fas fa-hand-holding-usd" aria-hidden="true"></i></div>
      <h4><?php echo htmlspecialchars(it('feature4_title')); ?></h4>
      <p><?php echo htmlspecialchars(it('feature4_desc')); ?></p>
    </div>
  </div>
</section>

<!-- Direct Card Access Header -->
<div class="direct-card-header<?= $card_only_mode ? ' show-header' : '' ?>" id="directCardHeader">
  <h2>Direct Service Access</h2>
  <p>You are viewing a specific service. Click below to see all available services.</p>
  <button type="button" class="back-to-all" id="backToAll">
    <i class="fas fa-arrow-left"></i>
    Back to All Services
  </button>
</div>

<!-- Services Section -->
<section class="services-section section-padding" id="services">
  <div class="section-header">
    <h2 class="section-title"><?php echo htmlspecialchars(it('services_title')); ?></h2>
    <p class="section-description"><?php echo htmlspecialchars(it('services_description')); ?></p>
  </div>

  <div class="services-grid">
    <?php foreach ($cards as $c): ?>
    <div id="<?= htmlspecialchars($c['id']) ?>" class="service-card <?= ($direct_card === $c['id']) ? 'show-card highlight-card' : (empty($direct_card) ? 'show-card' : '') ?>" data-card="<?= htmlspecialchars($c['id']) ?>" data-form="<?= htmlspecialchars($c['form']) ?>">
      <div class="card-header">
        <div class="card-icon"><?= $c['icon'] ?></div>
        <div class="card-title-group">
          <h3><?= htmlspecialchars(it($c['title_key'])) ?></h3>
          <p class="card-subtitle"><?= htmlspecialchars(it($c['subtitle_key'])) ?></p>
        </div>
      </div>
      <p class="card-description"><?= htmlspecialchars(it($c['description_key'])) ?></p>
      <ul class="card-features">
        <?php foreach ($c['points_keys'] as $pt_key): ?>
        <li><?= htmlspecialchars(it($pt_key)) ?></li>
        <?php endforeach; ?>
      </ul>
      <div class="card-actions">
        <button type="button" class="card-button apply-button">
          <i class="fas fa-paper-plane"></i>
          <?php echo htmlspecialchars(it('card_apply')); ?>
        </button>
        <button type="button" class="card-button copy-button" data-card-id="<?= htmlspecialchars($c['id']) ?>">
          <i class="fas fa-link"></i>
          <?php echo htmlspecialchars(it('card_copy')); ?>
        </button>
        <button type="button" class="card-button resume-button" title="<?php echo htmlspecialchars(it('card_continue_hint')); ?>">
          <i class="fas fa-search"></i>
          <?php echo htmlspecialchars(it('card_continue')); ?>
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<div id="resumeApplicationModal" class="resume-modal" aria-hidden="true">
  <div class="resume-modal__backdrop" id="resumeModalBackdrop"></div>
  <div class="resume-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="resumeModalTitle">
    <h3 id="resumeModalTitle"></h3>
    <p id="resumeModalSub"></p>
    <p class="resume-modal__table" id="resumeModalTable"></p>

    <label for="resumeUserIdInput"><?php echo htmlspecialchars(it('resume_label')); ?></label>
    <input type="text" id="resumeUserIdInput" name="resume_user_id" autocomplete="off" placeholder="" />

    <div class="resume-modal__divider"><span>or</span></div>

    <label for="resumeNameSearchInput"><?php echo htmlspecialchars(it('resume_search_label')); ?></label>
    <input type="text" id="resumeNameSearchInput" name="resume_name_search" autocomplete="off" placeholder="<?php echo htmlspecialchars(it('resume_search_placeholder')); ?>" />
    <p class="resume-modal__hint" id="resumeModalHint"></p>
    <div id="resumeSearchResults" class="resume-search-results" role="listbox" aria-live="polite"></div>

    <p class="resume-modal__error" id="resumeModalError" aria-live="polite"></p>
    <div class="resume-modal__actions">
      <button type="button" id="resumeModalCancel"><?php echo htmlspecialchars(it('resume_cancel')); ?></button>
      <button type="button" id="resumeModalSubmit"><?php echo htmlspecialchars(it('resume_submit')); ?></button>
    </div>
  </div>
</div>

<!-- Partner universities -->
<section class="universities-section section-padding">
  <div class="section-header">
    <h2 class="section-title"><?php echo htmlspecialchars(it('partners_uni_title')); ?></h2>
    <p class="section-description"><?php echo htmlspecialchars(it('partners_uni_sub')); ?></p>
  </div>
  <div class="universities-grid">
    <?php foreach ($universities as $uni): ?>
    <div class="university-card fade-in">
      <div class="university-flag">
        <?php
        $flags = [
          'Canada' => '🇨🇦',
          'UK' => '🇬🇧',
          'Australia' => '🇦🇺',
          'Switzerland' => '🇨🇭',
          'Japan' => '🇯🇵',
          'USA' => '🇺🇸',
          'Germany' => '🇩🇪',
          'Singapore' => '🇸🇬',
          'South Africa' => '🇿🇦',
          'International' => '🌐',
        ];
        echo $flags[$uni['country']] ?? '🏫';
        ?>
      </div>
      <h4><?= htmlspecialchars($uni['name']) ?></h4>
      <p class="university-country">
        <i class="fas fa-map-marker-alt"></i>
        <?= htmlspecialchars($uni['country']) ?>
      </p>
      <span class="university-rank"><?= htmlspecialchars($uni['rank']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Destinations Section -->
<section class="destinations-section section-padding">
  <div class="section-header">
    <h2 class="section-title"><?php echo htmlspecialchars(it('destinations_title')); ?></h2>
    <p class="section-description"><?php echo htmlspecialchars(it('destinations_description')); ?></p>
  </div>
  <div class="destinations-grid">
    <?php foreach ($destinations as $dest): ?>
    <div class="destination-card fade-in">
      <div class="destination-flag"><?= $dest['flag'] ?></div>
      <div class="destination-info">
        <h4><?= htmlspecialchars($dest['country']) ?></h4>
        <p class="destination-stats"><?= htmlspecialchars($dest['students']) ?> Students Placed</p>
        <p class="destination-desc"><?= htmlspecialchars($dest['description']) ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Process Section -->
<section class="process-section section-padding">
  <div class="section-header">
    <h2 class="section-title"><?php echo htmlspecialchars(it('process_title')); ?></h2>
    <p class="section-description"><?php echo htmlspecialchars(it('process_description')); ?></p>
  </div>
  <div class="process-steps">
    <div class="process-step slide-left">
      <div class="step-number">1</div>
      <div class="step-content">
        <h4><?php echo htmlspecialchars(it('process_step1')); ?></h4>
        <p><?php echo htmlspecialchars(it('process_step1_desc')); ?></p>
      </div>
    </div>
    <div class="process-step slide-right">
      <div class="step-number">2</div>
      <div class="step-content">
        <h4><?php echo htmlspecialchars(it('process_step2')); ?></h4>
        <p><?php echo htmlspecialchars(it('process_step2_desc')); ?></p>
      </div>
    </div>
    <div class="process-step slide-left">
      <div class="step-number">3</div>
      <div class="step-content">
        <h4><?php echo htmlspecialchars(it('process_step3')); ?></h4>
        <p><?php echo htmlspecialchars(it('process_step3_desc')); ?></p>
      </div>
    </div>
    <div class="process-step slide-right">
      <div class="step-number">4</div>
      <div class="step-content">
        <h4><?php echo htmlspecialchars(it('process_step4')); ?></h4>
        <p><?php echo htmlspecialchars(it('process_step4_desc')); ?></p>
      </div>
    </div>
    <div class="process-step slide-left">
      <div class="step-number">5</div>
      <div class="step-content">
        <h4><?php echo htmlspecialchars(it('process_step5')); ?></h4>
        <p><?php echo htmlspecialchars(it('process_step5_desc')); ?></p>
      </div>
    </div>
  </div>
</section>

<!-- Testimonials -->
<section class="testimonials-section section-padding">
  <div class="testimonials-header">
    <h2 class="section-title"><?php echo htmlspecialchars(it('testimonials_title')); ?></h2>
    <p class="section-subtitle"><?php echo htmlspecialchars(it('testimonials_subtitle')); ?></p>
  </div>
  <div class="testimonials-container">
    <div class="testimonials-track" id="testimonialsTrack">
      <?php foreach ($testimonials as $testimonial): ?>
      <div class="testimonial-card fade-in">
        <p class="testimonial-text"><?php echo htmlspecialchars(it($testimonial['key'])); ?></p>
        <div class="testimonial-author">
          <div class="author-avatar"><?= htmlspecialchars($testimonial['initial']) ?></div>
          <div class="author-info">
            <h5><?php echo htmlspecialchars(it($testimonial['key'] . '_name')); ?></h5>
            <p><?php echo htmlspecialchars(it($testimonial['key'] . '_location')); ?></p>
            <span class="author-achievement"><?php echo htmlspecialchars(it($testimonial['key'] . '_achievement')); ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="testimonial-controls">
      <button type="button" class="testimonial-btn" id="prevTestimonial" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
      <button type="button" class="testimonial-btn" id="nextTestimonial" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
    </div>
    <div class="testimonial-indicators" id="testimonialIndicators">
      <?php for ($i = 0; $i < count($testimonials); $i++): ?>
      <div class="indicator <?= $i === 0 ? 'active' : '' ?>" data-index="<?= (int) $i ?>"></div>
      <?php endfor; ?>
    </div>
  </div>
</section>

<!-- Industry partners (compact) -->
<section class="partners-section section-padding">
  <div class="partners-compact">
    <div class="section-header">
      <h2 class="section-title"><?php echo htmlspecialchars(it('partners_title')); ?></h2>
      <p class="section-description"><?php echo htmlspecialchars(it('partners_description')); ?></p>
    </div>
    <p><?php echo $current_lang === 'fr'
      ? 'Nous collaborons avec des banques, organismes de tests, compagnies aériennes et plateformes éducatives pour couvrir prêts étudiants, certifications, voyages et apprentissage.'
      : 'We work with banks, testing bodies, airlines, and education platforms so you have support for student loans, exams, travel, and learning — end to end.'; ?></p>
  </div>
</section>

<!-- Contact (from legacy site) -->
<section class="contact-strip-lp" id="contact-strip">
  <div class="contact-strip-inner">
    <h2><?php echo htmlspecialchars(it('contact_banner_title')); ?></h2>
    <div class="contact-grid-lp">
      <div class="contact-item-lp">
        <small><?php echo $current_lang === 'fr' ? 'Téléphone' : 'Phone'; ?></small>
        <a href="tel:+250788284544"><?php echo htmlspecialchars(it('contact_phone')); ?></a>
        <a href="tel:+250789515593" class="contact-phone-second"><?php echo htmlspecialchars(it('contact_phone2')); ?></a>
      </div>
      <div class="contact-item-lp">
        <small>Email</small>
        <a href="mailto:<?php echo htmlspecialchars(it('contact_email')); ?>"><?php echo htmlspecialchars(it('contact_email')); ?></a>
      </div>
      <div class="contact-item-lp">
        <small><?php echo $current_lang === 'fr' ? 'Adresse' : 'Address'; ?></small>
        <span><?php echo htmlspecialchars(it('contact_address')); ?></span>
      </div>
    </div>
  </div>
</section>

<!-- Resources Section -->
<section class="resources-section section-padding">
  <div class="section-header">
    <h2 class="section-title"><?php echo htmlspecialchars(it('resources_title')); ?></h2>
    <p class="section-description"><?php echo htmlspecialchars(it('resources_description')); ?></p>
  </div>
  <div class="resources-grid">
    <div class="resource-card fade-in">
      <div class="resource-icon"><i class="fas fa-award"></i></div>
      <h4><?php echo htmlspecialchars(it('resource1_title')); ?></h4>
      <p><?php echo htmlspecialchars(it('resource1_desc')); ?></p>
      <a href="#" class="resource-link"><?php echo htmlspecialchars(it('read_more')); ?> <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="resource-card fade-in">
      <div class="resource-icon"><i class="fas fa-passport"></i></div>
      <h4><?php echo htmlspecialchars(it('resource2_title')); ?></h4>
      <p><?php echo htmlspecialchars(it('resource2_desc')); ?></p>
      <a href="#" class="resource-link"><?php echo htmlspecialchars(it('read_more')); ?> <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="resource-card fade-in">
      <div class="resource-icon"><i class="fas fa-briefcase"></i></div>
      <h4><?php echo htmlspecialchars(it('resource3_title')); ?></h4>
      <p><?php echo htmlspecialchars(it('resource3_desc')); ?></p>
      <a href="#" class="resource-link"><?php echo htmlspecialchars(it('read_more')); ?> <i class="fas fa-arrow-right"></i></a>
    </div>
  </div>
</section>

<!-- Final CTA -->
<section class="cta-section">
  <div class="cta-content">
    <h2 class="fade-in"><?php echo htmlspecialchars(it('cta_title')); ?></h2>
    <p class="fade-in"><?php echo htmlspecialchars(it('cta_description')); ?></p>
    <div class="cta-buttons">
      <button type="button" class="cta-button cta-button-white" id="bookConsultation">
        <i class="fas fa-calendar-check"></i>
        <?php echo htmlspecialchars(it('book_consultation')); ?>
      </button>
      <button type="button" class="cta-button cta-button-outline" id="downloadBrochure">
        <i class="fas fa-download"></i>
        <?php echo htmlspecialchars(it('download_brochure')); ?>
      </button>
    </div>
  </div>
</section>

</div><!-- .landing-root -->

<script>
(function() {
  'use strict';

  // Get URL parameters
  const urlParams = new URLSearchParams(window.location.search);
  const directCardId = urlParams.get('card');

  // Direct Card Access Logic — shared links show ONLY that card.
  if (directCardId) {
    document.body.classList.add('card-only-mode');
    const headerEl = document.getElementById('directCardHeader');
    if (headerEl) headerEl.classList.add('show-header');

    document.querySelectorAll('.service-card').forEach(card => {
      if (card.dataset.card === directCardId) {
        card.classList.add('show-card', 'highlight-card');
      } else {
        card.classList.remove('show-card', 'highlight-card');
      }
    });
  }

  // Back to All Services — leave card-only mode and restore full index.
  document.getElementById('backToAll')?.addEventListener('click', function() {
    window.location.href = window.location.pathname;
  });

  // Scroll to Services
  const scrollToServicesBtn = document.getElementById('scrollToServices');
  if (scrollToServicesBtn) {
    scrollToServicesBtn.addEventListener('click', function() {
      document.getElementById('services')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    });
  }

  const scrollToFeaturesBtn = document.getElementById('scrollToFeatures');
  if (scrollToFeaturesBtn) {
    scrollToFeaturesBtn.addEventListener('click', function() {
      document.getElementById('pillars')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    });
  }

  // Animation on scroll
  function animateOnScroll() {
    const elements = document.querySelectorAll('.fade-in, .slide-left, .slide-right');
    elements.forEach(el => {
      const rect = el.getBoundingClientRect();
      if (rect.top <= window.innerHeight * 0.85) {
        el.style.animationPlayState = 'running';
      }
    });
  }

  window.addEventListener('scroll', animateOnScroll);
  window.addEventListener('load', animateOnScroll);

  // Enhanced Testimonials Carousel
  const testimonialsTrack = document.getElementById('testimonialsTrack');
  const testimonialIndicators = document.getElementById('testimonialIndicators');
  const indicators = testimonialIndicators.querySelectorAll('.indicator');
  let currentTestimonial = 0;
  const testimonialWidth = 380; // card width + gap

  function updateTestimonials() {
    testimonialsTrack.scrollTo({
      left: currentTestimonial * testimonialWidth,
      behavior: 'smooth'
    });
    
    indicators.forEach((ind, index) => {
      ind.classList.toggle('active', index === currentTestimonial);
    });
  }

  document.getElementById('nextTestimonial').addEventListener('click', () => {
    currentTestimonial = (currentTestimonial + 1) % indicators.length;
    updateTestimonials();
  });

  document.getElementById('prevTestimonial').addEventListener('click', () => {
    currentTestimonial = (currentTestimonial - 1 + indicators.length) % indicators.length;
    updateTestimonials();
  });

  indicators.forEach((ind, index) => {
    ind.addEventListener('click', () => {
      currentTestimonial = index;
      updateTestimonials();
    });
  });

  // Auto-scroll testimonials
  setInterval(() => {
    currentTestimonial = (currentTestimonial + 1) % indicators.length;
    updateTestimonials();
  }, 5000);

  // Fresh application id for each "Apply Now" from the home cards (do not reuse one
  // browser-wide id — that made every form look like the same draft / wrong id for credit transfer).
  function newApplicationUserId() {
    return 'user-' + Date.now() + '-' + Math.floor(Math.random() * 10000);
  }

  // Apply Now Buttons
  document.querySelectorAll('.apply-button').forEach(button => {
    button.addEventListener('click', function(e) {
      e.preventDefault();
      
      const card = this.closest('.service-card');
      if (!card) return;
      
      const form = card.dataset.form;
      const type = card.dataset.card;
      const userId = newApplicationUserId();
      
      let targetUrl = '';
      switch (type) {
        case 'scholarships':
          targetUrl = `loan-providers.php?form=${encodeURIComponent(form)}&id=${encodeURIComponent(userId)}`;
          break;
        case 'credit':
          // credit_transfer.php assigns its own credit-… id; ?id=user-… was wrong and triggered resume UX
          targetUrl = form;
          break;
        case 'visa':
          // Don't pass any ID - let visa.php generate a new one
          targetUrl = 'visa.php?country_id=&region_id=';
          break;
        case 'i20':
          targetUrl = `select-20.php?form=${encodeURIComponent(form)}&id=${encodeURIComponent(userId)}`;
          break;
        case 'medical':
        case 'francophonie':
        case 'employment':
        case 'korea_event':
          targetUrl = form;
          break;
        default:
          targetUrl = `${form}?id=${encodeURIComponent(userId)}`;
      }
      
      window.location.href = targetUrl;
    });
  });

  // MODIFIED: Copy Link Buttons with card-specific URLs
  document.querySelectorAll('.copy-button').forEach(btn => {
    btn.addEventListener('click', function() {
      const cardId = this.dataset.cardId;
      const card = document.getElementById(cardId);
      const cardTitle = card.querySelector('.card-title-group h3').textContent;
      
      // Create card-specific URL
      const url = `${window.location.origin}${window.location.pathname}?card=${cardId}`;
      
      // Modern clipboard API
      navigator.clipboard.writeText(url).then(() => {
        showNotification(`Link copied for: ${cardTitle}`);
        this.innerHTML = '<i class="fas fa-check"></i> Copied';
        this.style.background = '#10B981';
        this.style.color = 'white';
        this.style.borderColor = '#10B981';
        
        setTimeout(() => {
          this.innerHTML = '<i class="fas fa-link"></i> <?php echo it('card_copy'); ?>';
          this.style.background = '';
          this.style.color = '';
          this.style.borderColor = '';
        }, 2000);
      }).catch(() => {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = url;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showNotification(`Link copied for: ${cardTitle}`);
      });
    });
  });

  // Retrieve application (smart retrieval by user_id, plus name/email search)
  const RESUME_TXT = {
    empty:     <?php echo json_encode(it('resume_error_empty')); ?>,
    generic:   <?php echo json_encode(it('resume_error_generic')); ?>,
    loading:   <?php echo json_encode(it('resume_loading')); ?>,
    short:     <?php echo json_encode(it('resume_search_short')); ?>,
    searching: <?php echo json_encode(it('resume_search_searching')); ?>,
    empty_res: <?php echo json_encode(it('resume_search_empty')); ?>,
    copy:      <?php echo json_encode(it('resume_copy')); ?>,
    copy_done: <?php echo json_encode(it('resume_copy_done')); ?>,
    use:       <?php echo json_encode(it('resume_use')); ?>,
    title_tpl: <?php echo json_encode(it('resume_modal_title_tpl')); ?>,
    sub_tpl:   <?php echo json_encode(it('resume_modal_sub_tpl')); ?>,
    hint_tpl:  <?php echo json_encode(it('resume_search_hint_tpl')); ?>,
    table_lbl: <?php echo json_encode($current_lang === 'fr' ? 'Source : table {table}' : 'Source: table {table}'); ?>
  };
  const RESUME_CARD_META = <?php echo json_encode($cardRetrievalMeta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;

  function fillTemplate(tpl, vars) {
    return String(tpl || '').replace(/\{(\w+)\}/g, (m, k) => (vars && vars[k] != null) ? String(vars[k]) : m);
  }

  let resumeCardType = null;
  let resumeSearchTimer = null;
  let resumeSearchAbort = null;
  const resumeModal = document.getElementById('resumeApplicationModal');
  const resumeUserIdInput = document.getElementById('resumeUserIdInput');
  const resumeNameInput = document.getElementById('resumeNameSearchInput');
  const resumeSearchResults = document.getElementById('resumeSearchResults');
  const resumeModalError = document.getElementById('resumeModalError');
  const resumeModalSubmit = document.getElementById('resumeModalSubmit');
  const resumeModalCancel = document.getElementById('resumeModalCancel');
  const resumeModalBackdrop = document.getElementById('resumeModalBackdrop');
  const resumeModalTitle = document.getElementById('resumeModalTitle');
  const resumeModalSub = document.getElementById('resumeModalSub');
  const resumeModalTable = document.getElementById('resumeModalTable');
  const resumeModalHint = document.getElementById('resumeModalHint');

  function clearResumeResults() {
    resumeSearchResults.innerHTML = '';
    resumeSearchResults.classList.remove('is-active');
  }

  function applyResumeCardMeta(cardType) {
    const meta = RESUME_CARD_META[cardType] || { service: cardType, table_label: '', placeholder: '', example: '' };
    resumeModalTitle.textContent = fillTemplate(RESUME_TXT.title_tpl, { service: meta.service });
    resumeModalSub.textContent = fillTemplate(RESUME_TXT.sub_tpl, { service: meta.service, example: meta.example });
    resumeModalHint.textContent = fillTemplate(RESUME_TXT.hint_tpl, { service: meta.service });
    if (meta.table_label) {
      const tplParts = String(RESUME_TXT.table_lbl).split('{table}');
      resumeModalTable.innerHTML =
        escapeHtml(tplParts[0] || '') +
        '<strong>' + escapeHtml(meta.table_label) + '</strong>' +
        escapeHtml(tplParts[1] || '');
    } else {
      resumeModalTable.textContent = '';
    }
    resumeUserIdInput.placeholder = meta.placeholder || '';
  }

  function openResumeModal(cardType) {
    resumeCardType = cardType;
    resumeModalError.textContent = '';
    resumeUserIdInput.value = '';
    resumeNameInput.value = '';
    clearResumeResults();
    applyResumeCardMeta(cardType);
    resumeModal.classList.add('is-open');
    resumeModal.setAttribute('aria-hidden', 'false');
    setTimeout(() => resumeUserIdInput.focus(), 50);
  }

  function closeResumeModal() {
    resumeModal.classList.remove('is-open');
    resumeModal.setAttribute('aria-hidden', 'true');
    resumeCardType = null;
    if (resumeSearchAbort) { resumeSearchAbort.abort(); resumeSearchAbort = null; }
    if (resumeSearchTimer) { clearTimeout(resumeSearchTimer); resumeSearchTimer = null; }
  }

  document.querySelectorAll('.resume-button').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const card = this.closest('.service-card');
      if (!card) return;
      openResumeModal(card.dataset.card);
    });
  });

  resumeModalCancel.addEventListener('click', closeResumeModal);
  resumeModalBackdrop.addEventListener('click', closeResumeModal);

  async function submitResume(uid) {
    if (!uid) {
      resumeModalError.textContent = RESUME_TXT.empty;
      return;
    }
    if (!resumeCardType) return;
    resumeModalSubmit.disabled = true;
    resumeModalError.textContent = RESUME_TXT.loading;
    try {
      const fd = new FormData();
      fd.append('card', resumeCardType);
      fd.append('user_id', uid);
      const r = await fetch('card_retrieve.php', { method: 'POST', body: fd });
      const data = await r.json();
      if (data.status === 'success' && data.redirect_url) {
        window.location.href = data.redirect_url;
        return;
      }
      resumeModalError.textContent = (data && data.message) ? data.message : RESUME_TXT.generic;
    } catch (err) {
      resumeModalError.textContent = RESUME_TXT.generic;
    } finally {
      resumeModalSubmit.disabled = false;
    }
  }

  resumeModalSubmit.addEventListener('click', () => {
    submitResume(resumeUserIdInput.value.trim());
  });
  resumeUserIdInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); submitResume(resumeUserIdInput.value.trim()); }
  });

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function renderResumeResults(items) {
    if (!items || items.length === 0) {
      resumeSearchResults.innerHTML = '<div class="resume-search-results__empty">' + escapeHtml(RESUME_TXT.empty_res) + '</div>';
      resumeSearchResults.classList.add('is-active');
      return;
    }
    const html = items.map((it) => {
      const name = (it.name || '').trim() || '(no name)';
      const meta = [it.email, it.submitted_at].filter(Boolean).join(' • ');
      return (
        '<div class="resume-search-results__item">' +
          '<div class="resume-search-results__main">' +
            '<div class="resume-search-results__name">' + escapeHtml(name) + '</div>' +
            (meta ? '<div class="resume-search-results__meta">' + escapeHtml(meta) + '</div>' : '') +
            '<div class="resume-search-results__id">' + escapeHtml(it.user_id || '') + '</div>' +
          '</div>' +
          '<div class="resume-search-results__actions">' +
            '<button type="button" class="resume-search-results__btn" data-act="copy" data-uid="' + escapeHtml(it.user_id || '') + '">' +
              '<i class="fas fa-copy"></i> ' + escapeHtml(RESUME_TXT.copy) +
            '</button>' +
            '<button type="button" class="resume-search-results__btn resume-search-results__btn--primary" data-act="use" data-uid="' + escapeHtml(it.user_id || '') + '">' +
              '<i class="fas fa-arrow-right"></i> ' + escapeHtml(RESUME_TXT.use) +
            '</button>' +
          '</div>' +
        '</div>'
      );
    }).join('');
    resumeSearchResults.innerHTML = html;
    resumeSearchResults.classList.add('is-active');
  }

  async function runResumeSearch(q) {
    if (!resumeCardType) return;
    if (q.length < 2) {
      resumeSearchResults.innerHTML = '<div class="resume-search-results__empty">' + escapeHtml(RESUME_TXT.short) + '</div>';
      resumeSearchResults.classList.add('is-active');
      return;
    }
    if (resumeSearchAbort) resumeSearchAbort.abort();
    resumeSearchAbort = new AbortController();
    resumeSearchResults.innerHTML = '<div class="resume-search-results__empty">' + escapeHtml(RESUME_TXT.searching) + '</div>';
    resumeSearchResults.classList.add('is-active');
    try {
      const params = new URLSearchParams({ action: 'search', card: resumeCardType, q: q });
      const r = await fetch('card_retrieve.php?' + params.toString(), { signal: resumeSearchAbort.signal });
      const data = await r.json();
      if (data && data.status === 'success') {
        renderResumeResults(data.results || []);
      } else {
        resumeSearchResults.innerHTML = '<div class="resume-search-results__empty">' + escapeHtml((data && data.message) || RESUME_TXT.generic) + '</div>';
      }
    } catch (err) {
      if (err && err.name === 'AbortError') return;
      resumeSearchResults.innerHTML = '<div class="resume-search-results__empty">' + escapeHtml(RESUME_TXT.generic) + '</div>';
    }
  }

  resumeNameInput.addEventListener('input', function() {
    const v = this.value.trim();
    if (resumeSearchTimer) clearTimeout(resumeSearchTimer);
    if (v.length === 0) { clearResumeResults(); return; }
    resumeSearchTimer = setTimeout(() => runResumeSearch(v), 250);
  });

  resumeSearchResults.addEventListener('click', async function(e) {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const uid = btn.getAttribute('data-uid') || '';
    const act = btn.getAttribute('data-act');
    if (!uid) return;
    if (act === 'use') {
      resumeUserIdInput.value = uid;
      submitResume(uid);
      return;
    }
    if (act === 'copy') {
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(uid);
        } else {
          const ta = document.createElement('textarea');
          ta.value = uid;
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
        }
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> ' + escapeHtml(RESUME_TXT.copy_done);
        resumeUserIdInput.value = uid;
        setTimeout(() => { btn.innerHTML = original; }, 1600);
      } catch (err) {
        // ignore
      }
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && resumeModal && resumeModal.classList.contains('is-open')) {
      closeResumeModal();
    }
  });

  // Additional CTA buttons
  document.getElementById('bookConsultation').addEventListener('click', () => {
    window.open('consultation.php', '_blank');
  });

  document.getElementById('downloadBrochure').addEventListener('click', () => {
    window.open('brochure.pdf', '_blank');
  });

  // Notification function
  function showNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: linear-gradient(135deg, #173c6d, #1ba7a0);
      color: white;
      padding: 16px 24px;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      z-index: 10000;
      animation: slideIn 0.3s ease;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 1rem;
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,0.1);
      max-width: 400px;
      word-break: break-word;
    `;
    
    notification.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
    document.body.appendChild(notification);

    setTimeout(() => {
      notification.style.animation = 'slideOut 0.3s ease';
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }

  // Add animation styles
  const style = document.createElement('style');
  style.textContent = `
    @keyframes slideIn {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
    @keyframes slideOut {
      from {
        transform: translateX(0);
        opacity: 1;
      }
      to {
        transform: translateX(100%);
        opacity: 0;
      }
    }
    
    @keyframes slideInDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .fade-in, .slide-left, .slide-right {
      animation-play-state: paused;
    }
    
    .fade-in {
      animation: fadeInUp 0.6s ease forwards;
    }
    
    .slide-left {
      animation: slideInLeft 0.6s ease forwards;
    }
    
    .slide-right {
      animation: slideInRight 0.6s ease forwards;
    }
    
    .hero-landing { transform: translateZ(0); }
    
    /* Partner logo hover effect */
    .partner-card:hover .partner-logo {
      animation: pulse 0.6s ease;
    }
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.1); }
      100% { transform: scale(1.05); }
    }
  `;
  document.head.appendChild(style);

  window.addEventListener('scroll', function() {
    const scrolled = window.pageYOffset;
    const hero = document.querySelector('.hero-landing');
    if (!hero) return;
    const rate = scrolled * 0.35;
    hero.style.transform = `translate3d(0, ${rate}px, 0)`;
  });

  // Initialize floating animations
  document.querySelectorAll('.float-animation').forEach(el => {
    el.style.animationDelay = `${Math.random() * 2}s`;
  });

  // If direct card access, adjust page behavior
  if (directCardId) {
    // Add a subtle background color to highlight the context
    document.body.style.backgroundColor = '#f4f6f3';
    
    // Auto-scroll to the card after page load
    window.addEventListener('load', function() {
      setTimeout(() => {
        const cardElement = document.getElementById(directCardId);
        if (cardElement) {
          cardElement.scrollIntoView({ 
            behavior: 'smooth',
            block: 'center'
          });
        }
      }, 500);
    });
  }

})();
</script>

<?php include 'footer.php'; ?>

</body>
</html>
