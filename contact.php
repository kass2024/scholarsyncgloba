<?php
// ============================================
// CONTACT PAGE WITH EMAIL FUNCTIONALITY
// ============================================
include 'header.php';

// Set page title with language switching
$pageTitle = $current_lang === 'en' ? 'Contact Us - ScholarSync Global' : 'Contactez-nous - ScholarSync Global';

// ============================================
// TRANSLATIONS FOR CONTACT PAGE
// ============================================

$contact_translations = [
    'en' => [
        // Hero Section
        'hero_title' => 'Contact Us',
        'hero_subtitle' => 'Reply within 24 hours · Study & visa support',
        'hero_description' => 'Questions about study abroad, visas, or scholarships? Contact ScholarSync Global — we assist before and after you obtain your visa.',
        
        // Contact Methods
        'contact_methods_title' => 'Get In Touch',
        'contact_methods_subtitle' => 'Multiple ways to connect with us',
        
        // Global Offices
        'offices_title' => 'Our main office',
        'offices_subtitle' => 'Rwanda — Kigali',
        
        // Office Details (primary)
        'sanfrancisco_title' => 'Rwanda — Kigali',
        'sanfrancisco_address' => 'Town Center Building (near Simba Supermarket), 2nd Floor, Door: F2B-022C, Nyarugenge',
        'sanfrancisco_phone' => '+250 788 284 544 · +250 789 515 593',
        'sanfrancisco_hours' => 'Email: infos@scholarsyncglobal.ca · We reply within 24 hours',
        
        'muhanga_title' => 'Regional inquiries',
        'muhanga_address' => 'Town Center Building (near Simba Supermarket), 2nd Floor, Door: F2B-022C, Nyarugenge',
        'muhanga_phone' => '+250 788 284 544 · +250 789 515 593',
        'muhanga_hours' => 'infos@scholarsyncglobal.ca',
        
        'nairobi_title' => 'Nairobi Office',
        'nairobi_address' => 'Nairobi, Kenya',
        'nairobi_phone' => '+254 744 111 121',
        'nairobi_hours' => 'Mon-Fri: 9:00 AM - 5:00 PM EAT',
        
        'kampala_title' => 'Kampala Office',
        'kampala_address' => 'Kampala, Uganda',
        'kampala_phone' => '+256 767 418 006',
        'kampala_hours' => 'Mon-Fri: 9:00 AM - 5:00 PM EAT',
        
        // Contact Form
        'form_title' => 'Send Us a Message',
        'form_subtitle' => 'We\'ll respond within 24 hours',
        'form_name' => 'Your Name',
        'form_email' => 'Email Address',
        'form_phone' => 'Phone Number',
        'form_country' => 'Current Country',
        'form_subject' => 'Subject',
        'form_message' => 'Your Message',
        'form_submit' => 'Send Message',
        'form_success' => 'Message sent successfully!',
        'form_error' => 'Please fill in all required fields.',
        
        // Subjects
        'subject_general' => 'General Inquiry',
        'subject_programs' => 'Program Information',
        'subject_admissions' => 'Admissions Assistance',
        'subject_scholarship' => 'Scholarship Information',
        'subject_visa' => 'Visa Assistance',
        'subject_other' => 'Other',
        
        // Contact Information
        'info_title' => 'Contact Information',
        'info_email' => 'infos@scholarsyncglobal.ca',
        'info_emergency' => 'For urgent inquiries',
        'info_social' => 'Connect with us on social media',
        
        // FAQ Section
        'faq_title' => 'Frequently Asked Questions',
        'faq_subtitle' => 'Quick answers to common questions',
        
        'faq1_q' => 'What is your response time?',
        'faq1_a' => 'We respond to all inquiries within 24 hours during business days.',
        
        'faq2_q' => 'Do you offer free consultations?',
        'faq2_a' => 'Yes, we offer free 30-minute initial consultations to discuss your goals.',
        
        'faq3_q' => 'How can I schedule a meeting?',
        'faq3_a' => 'Use our contact form or call any of our offices to schedule an appointment.',
        
        'faq4_q' => 'What documents do I need for consultation?',
        'faq4_a' => 'Bring your academic transcripts, passport, and any test scores if available.',
        
        // CTA Section
        'cta_title' => 'Ready to Start Your Journey?',
        'cta_description' => 'Book a free consultation with our expert advisors',
        'cta_button' => 'Schedule Consultation',
        'cta_button2' => 'Download Brochure',
        
        // Page Metadata
        'page_description' => 'Contact ScholarSync Global - Get in touch with our global offices for international education assistance.',
        'page_title' => 'Contact Us - ScholarSync Global',
    ],
    
    'fr' => [
        // Hero Section
        'hero_title' => 'Contactez-nous',
        'hero_subtitle' => 'Connectez-vous avec notre équipe mondiale',
        'hero_description' => 'Nous sommes là pour vous aider à atteindre vos objectifs d\'éducation internationale. Contactez-nous pour une assistance personnalisée.',
        
        // Contact Methods
        'contact_methods_title' => 'Prenez Contact',
        'contact_methods_subtitle' => 'Plusieurs façons de nous contacter',
        
        // Global Offices
        'offices_title' => 'Notre bureau principal',
        'offices_subtitle' => 'Rwanda — Kigali',
        
        // Office Details
        'sanfrancisco_title' => 'Rwanda — Kigali',
        'sanfrancisco_address' => 'Town Center Building (près de Simba Supermarket), 2e étage, Porte : F2B-022C, Nyarugenge',
        'sanfrancisco_phone' => '+250 788 284 544 · +250 789 515 593',
        'sanfrancisco_hours' => 'Courriel : infos@scholarsyncglobal.ca · Réponse sous 24 h',
        
        'muhanga_title' => 'Demandes régionales',
        'muhanga_address' => 'Town Center Building (près de Simba Supermarket), 2e étage, Porte : F2B-022C, Nyarugenge',
        'muhanga_phone' => '+250 788 284 544 · +250 789 515 593',
        'muhanga_hours' => 'infos@scholarsyncglobal.ca',
        
        'nairobi_title' => 'Bureau de Nairobi',
        'nairobi_address' => 'Nairobi, Kenya',
        'nairobi_phone' => '+254 744 111 121',
        'nairobi_hours' => 'Lun-Ven: 9:00 AM - 5:00 PM EAT',
        
        'kampala_title' => 'Bureau de Kampala',
        'kampala_address' => 'Kampala, Uganda',
        'kampala_phone' => '+256 767 418 006',
        'kampala_hours' => 'Lun-Ven: 9:00 AM - 5:00 PM EAT',
        
        // Contact Form
        'form_title' => 'Envoyez-nous un Message',
        'form_subtitle' => 'Nous répondons dans les 24 heures',
        'form_name' => 'Votre Nom',
        'form_email' => 'Adresse Email',
        'form_phone' => 'Numéro de Téléphone',
        'form_country' => 'Pays Actuel',
        'form_subject' => 'Sujet',
        'form_message' => 'Votre Message',
        'form_submit' => 'Envoyer le Message',
        'form_success' => 'Message envoyé avec succès!',
        'form_error' => 'Veuillez remplir tous les champs requis.',
        
        // Subjects
        'subject_general' => 'Demande Générale',
        'subject_programs' => 'Information sur les Programmes',
        'subject_admissions' => 'Assistance aux Admissions',
        'subject_scholarship' => 'Information sur les Bourses',
        'subject_visa' => 'Assistance Visa',
        'subject_other' => 'Autre',
        
        // Contact Information
        'info_title' => 'Informations de Contact',
        'info_email' => 'infos@scholarsyncglobal.ca',
        'info_emergency' => 'Pour les demandes urgentes',
        'info_social' => 'Connectez-vous avec nous sur les réseaux sociaux',
        
        // FAQ Section
        'faq_title' => 'Questions Fréquemment Posées',
        'faq_subtitle' => 'Réponses rapides aux questions courantes',
        
        'faq1_q' => 'Quel est votre temps de réponse?',
        'faq1_a' => 'Nous répondons à toutes les demandes dans les 24 heures les jours ouvrables.',
        
        'faq2_q' => 'Offrez-vous des consultations gratuites?',
        'faq2_a' => 'Oui, nous offrons des consultations initiales gratuites de 30 minutes pour discuter de vos objectifs.',
        
        'faq3_q' => 'Comment puis-je programmer un rendez-vous?',
        'faq3_a' => 'Utilisez notre formulaire de contact ou appelez l\'un de nos bureaux pour programmer un rendez-vous.',
        
        'faq4_q' => 'Quels documents sont nécessaires pour la consultation?',
        'faq4_a' => 'Apportez vos relevés de notes académiques, passeport et tout résultat de test si disponible.',
        
        // CTA Section
        'cta_title' => 'Prêt à Commencer Votre Voyage?',
        'cta_description' => 'Réservez une consultation gratuite avec nos conseillers experts',
        'cta_button' => 'Programmer une Consultation',
        'cta_button2' => 'Télécharger la Brochure',
        
        // Page Metadata
        'page_description' => 'Contactez ScholarSync Global - Prenez contact avec nos bureaux mondiaux pour une assistance en éducation internationale.',
        'page_title' => 'Contactez-nous - ScholarSync Global',
    ]
];

// Function to get contact page translation
function ct($key) {
    global $contact_translations, $current_lang;
    
    // Fallback to English if key missing
    if (isset($contact_translations[$current_lang][$key])) {
        return $contact_translations[$current_lang][$key];
    } elseif (isset($contact_translations['en'][$key])) {
        return $contact_translations['en'][$key];
    }
    
    return $key; // Return key itself as last resort
}

// ============================================
// EMAIL PROCESSING FUNCTIONALITY
// ============================================

$email_sent = false;
$form_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate form data
    $name = filter_var(trim($_POST['name'] ?? ''), FILTER_SANITIZE_STRING);
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = filter_var(trim($_POST['phone'] ?? ''), FILTER_SANITIZE_STRING);
    $country = filter_var(trim($_POST['country'] ?? ''), FILTER_SANITIZE_STRING);
    $subject = filter_var(trim($_POST['subject'] ?? ''), FILTER_SANITIZE_STRING);
    $message = filter_var(trim($_POST['message'] ?? ''), FILTER_SANITIZE_STRING);
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($message)) {
        $form_error = ct('form_error');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_error = 'Please enter a valid email address.';
    } else {
        // Email configuration
        $to = 'infos@scholarsyncglobal.ca';
        $email_subject = "New Contact Form: $subject";
        
        // Build email headers
        $headers = "From: $name <$email>\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        // Build email body
        $email_body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #427431; color: white; padding: 20px; text-align: center; }
                .content { background: #f8fafc; padding: 20px; border: 1px solid #e2e8f0; }
                .field { margin-bottom: 15px; }
                .label { font-weight: bold; color: #427431; }
                .value { padding: 8px; background: white; border: 1px solid #e2e8f0; border-radius: 4px; }
                .footer { background: #f1f5f9; padding: 15px; text-align: center; color: #64748b; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>New Contact Form Submission</h2>
                    <p>ScholarSync Global</p>
                </div>
                <div class='content'>
                    <div class='field'>
                        <div class='label'>Name:</div>
                        <div class='value'>$name</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Email:</div>
                        <div class='value'>$email</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Phone:</div>
                        <div class='value'>$phone</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Country:</div>
                        <div class='value'>$country</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Subject:</div>
                        <div class='value'>$subject</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Message:</div>
                        <div class='value' style='min-height: 100px;'>$message</div>
                    </div>
                </div>
                <div class='footer'>
                    <p>This email was sent from the contact form on ScholarSync Global website.</p>
                    <p>IP Address: " . $_SERVER['REMOTE_ADDR'] . " | Time: " . date('Y-m-d H:i:s') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Send email
        if (mail($to, $email_subject, $email_body, $headers)) {
            $email_sent = true;
            
            // Optional: Store in database or send to CRM
            // save_contact_submission($name, $email, $phone, $country, $subject, $message);
        } else {
            $form_error = 'There was an error sending your message. Please try again later.';
        }
    }
}

// Main office (Kigali) — map & office card
$offices = [
    [
        'title_key' => 'sanfrancisco_title',
        'address_key' => 'sanfrancisco_address',
        'phone_key' => 'sanfrancisco_phone',
        'hours_key' => 'sanfrancisco_hours',
        'flag' => '🇷🇼',
        'color' => '#10B981',
        'timezone' => 'CAT',
        'lat' => -1.94385,
        'lng' => 30.06045,
    ],
];

// Define subjects
$subjects = [
    'subject_general',
    'subject_programs',
    'subject_admissions',
    'subject_scholarship',
    'subject_visa',
    'subject_other'
];

// Define FAQ
$faqs = [
    ['q_key' => 'faq1_q', 'a_key' => 'faq1_a'],
    ['q_key' => 'faq2_q', 'a_key' => 'faq2_a'],
    ['q_key' => 'faq3_q', 'a_key' => 'faq3_a'],
    ['q_key' => 'faq4_q', 'a_key' => 'faq4_a']
];
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo ct('page_description'); ?>">
<title><?php echo ct('page_title'); ?></title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ============================================
   CONTACT PAGE STYLES
   Professional, accessible design with interactive elements
============================================ */
:root {
  /* Official brand colors */
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
.contact-hero {
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

/* ===== CONTACT METHODS SECTION ===== */
.contact-methods {
  background: white;
}

.methods-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 30px;
  margin-top: 50px;
}

.method-card {
  text-align: center;
  padding: 40px 30px;
  background: var(--bg);
  border-radius: 20px;
  border: 1px solid var(--border-light);
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.method-card:hover {
  transform: translateY(-8px);
  box-shadow: var(--shadow-lg);
}

.method-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--accent-gold), var(--accent-teal));
}

.method-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 25px;
  background: linear-gradient(135deg, var(--primary-light), var(--teal-light));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  color: var(--primary-navy);
  transition: var(--transition);
}

.method-card:hover .method-icon {
  transform: rotate(15deg) scale(1.1);
}

.method-card h3 {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 15px;
}

.method-card p {
  color: var(--text-light);
  line-height: 1.6;
  margin-bottom: 20px;
}

.method-contact {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--accent-gold);
}

/* ===== OFFICES SECTION ===== */
.offices-section {
  background: linear-gradient(135deg, #F8FAFC 0%, #F0F4F8 100%);
}

.offices-container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 40px;
  margin-top: 50px;
}

@media (max-width: 992px) {
  .offices-container {
    grid-template-columns: 1fr;
  }
}

/* Map Container */
.map-container {
  background: white;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: var(--shadow-lg);
  position: relative;
  min-height: 500px;
}

.map-placeholder {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #427431, #3661B9);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: white;
  padding: 40px;
}

.map-placeholder i {
  font-size: 4rem;
  margin-bottom: 20px;
  color: var(--accent-teal);
}

.map-placeholder h3 {
  font-size: 1.8rem;
  margin-bottom: 15px;
}

.map-placeholder p {
  opacity: 0.8;
  text-align: center;
  max-width: 400px;
  margin-bottom: 30px;
}

.map-marker {
  position: absolute;
  width: 16px;
  height: 16px;
  background: var(--accent-gold);
  border: 3px solid white;
  border-radius: 50%;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
  cursor: pointer;
  transition: var(--transition);
}

.map-marker:hover {
  transform: scale(1.5);
}

.marker-info {
  position: absolute;
  bottom: 30px;
  left: 50%;
  transform: translateX(-50%);
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: var(--shadow-xl);
  max-width: 300px;
  width: 90%;
  z-index: 10;
}

.marker-info h4 {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 10px;
}

.marker-info p {
  color: var(--text-light);
  margin-bottom: 5px;
  font-size: 0.9rem;
}

/* Offices List */
.offices-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.office-card {
  background: white;
  padding: 30px;
  border-radius: 16px;
  border: 1px solid var(--border-light);
  transition: var(--transition);
  cursor: pointer;
  position: relative;
  overflow: hidden;
}

.office-card:hover {
  transform: translateX(10px);
  box-shadow: var(--shadow-md);
}

.office-card.active {
  border-color: var(--accent-teal);
  background: var(--teal-light);
}

.office-header {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 15px;
}

.office-flag {
  font-size: 2rem;
  flex-shrink: 0;
}

.office-info h3 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 5px;
}

.office-address {
  color: var(--text-light);
  font-size: 0.95rem;
  margin-bottom: 10px;
}

.office-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.office-detail {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text);
  font-size: 0.95rem;
}

.office-detail i {
  color: var(--accent-teal);
  width: 20px;
}

/* ===== CONTACT FORM SECTION ===== */
.form-section {
  background: white;
}

.form-container {
  max-width: 800px;
  margin: 0 auto;
  background: var(--bg);
  padding: 50px;
  border-radius: 24px;
  box-shadow: var(--shadow-lg);
}

@media (max-width: 768px) {
  .form-container {
    padding: 30px 20px;
  }
}

.form-message {
  padding: 20px;
  border-radius: 12px;
  margin-bottom: 30px;
  text-align: center;
  font-weight: 600;
}

.form-message.success {
  background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
  color: #065F46;
  border: 1px solid #34D399;
}

.form-message.error {
  background: linear-gradient(135deg, #FEE2E2, #FECACA);
  color: #991B1B;
  border: 1px solid #F87171;
}

.contact-form {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 25px;
}

@media (max-width: 768px) {
  .contact-form {
    grid-template-columns: 1fr;
  }
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--primary-navy);
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 5px;
}

.form-group label.required::after {
  content: '*';
  color: #EF4444;
}

.form-group input,
.form-group select,
.form-group textarea {
  padding: 14px 16px;
  border: 2px solid var(--border);
  border-radius: 12px;
  font-size: 1rem;
  font-family: 'Inter', sans-serif;
  background: white;
  transition: var(--transition);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: var(--accent-teal);
  box-shadow: 0 0 0 3px rgba(45, 212, 191, 0.1);
}

.form-group textarea {
  min-height: 150px;
  resize: vertical;
}

.submit-button {
  grid-column: 1 / -1;
  padding: 18px 40px;
  background: linear-gradient(135deg, var(--primary-navy), var(--secondary-blue));
  color: white;
  border: none;
  border-radius: 12px;
  font-size: 1.1rem;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
}

.submit-button:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 25px rgba(1, 47, 107, 0.2);
}

.submit-button:active {
  transform: translateY(-1px);
}

.submit-button i {
  transition: transform 0.3s ease;
}

.submit-button:hover i {
  transform: translateX(5px);
}

/* ===== CONTACT INFO SECTION ===== */
.contact-info-section {
  background: linear-gradient(135deg, var(--primary-navy) 0%, var(--dark-blue) 100%);
  color: white;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 40px;
  margin-top: 50px;
}

.info-card {
  text-align: center;
  padding: 40px 30px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  transition: var(--transition);
}

.info-card:hover {
  background: rgba(255, 255, 255, 0.15);
  transform: translateY(-5px);
}

.info-icon {
  font-size: 2.5rem;
  color: var(--accent-gold);
  margin-bottom: 20px;
}

.info-card h3 {
  font-size: 1.3rem;
  font-weight: 700;
  margin-bottom: 15px;
}

.info-content {
  font-size: 1rem;
  opacity: 0.9;
  line-height: 1.6;
}

.info-email {
  color: var(--accent-teal);
  font-weight: 600;
  font-size: 1.1rem;
  word-break: break-all;
}

.social-links {
  display: flex;
  justify-content: center;
  gap: 12px;
  margin-top: 20px;
  flex-wrap: wrap;
  max-width: 340px;
  margin-left: auto;
  margin-right: auto;
}

.contact-social-grid {
  max-width: 100%;
}

.social-link.social-whatsapp:hover {
  background: #25d366;
  border-color: #25d366;
}

.social-link {
  width: 45px;
  height: 45px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.2rem;
  transition: var(--transition);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.social-link:hover {
  background: var(--accent-gold);
  transform: translateY(-3px);
}

/* ===== FAQ SECTION ===== */
.faq-section {
  background: white;
}

.faq-container {
  max-width: 800px;
  margin: 50px auto 0;
}

.faq-item {
  background: var(--bg);
  border-radius: 16px;
  margin-bottom: 20px;
  overflow: hidden;
  border: 1px solid var(--border-light);
  transition: var(--transition);
}

.faq-item:hover {
  border-color: var(--accent-teal);
}

.faq-question {
  padding: 25px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--primary-navy);
}

.faq-question i {
  color: var(--accent-teal);
  transition: transform 0.3s ease;
}

.faq-item.active .faq-question i {
  transform: rotate(180deg);
}

.faq-answer {
  padding: 0 30px;
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease, padding 0.3s ease;
  color: var(--text-light);
  line-height: 1.6;
}

.faq-item.active .faq-answer {
  padding: 0 30px 25px;
  max-height: 500px;
}

/* ===== CTA SECTION ===== */
.contact-cta {
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
  
  .methods-grid {
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
  
  .methods-grid {
    grid-template-columns: 1fr;
  }
  
  .info-grid {
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
  
  .office-card {
    padding: 20px;
  }
}
.info-grid{
  display:grid;
  grid-template-columns: repeat(4,1fr);
  gap:30px;
  margin-top:50px;
}

.info-card{
  background:#294e86;
  padding:40px 30px;
  border-radius:20px;
  text-align:center;
  color:white;
}

.info-icon{
  font-size:28px;
  margin-bottom:20px;
  color:#ffb55a;
}

.info-email{
  color:#3fd0c9;
  font-weight:500;
}

.info-desc{
  margin-top:10px;
  font-size:0.9rem;
  opacity:0.9;
}

.social-links{
  margin-top:15px;
}

.social-link{
  display:inline-flex;
  width:40px;
  height:40px;
  align-items:center;
  justify-content:center;
  border-radius:50%;
  border:1px solid rgba(255,255,255,0.3);
  margin:0 5px;
  color:white;
}
</style>
</head>
<body>

<!-- Hero Section -->
<section class="contact-hero">
  <div class="hero-bg-pattern"></div>
  <div class="hero-content">
    <h1 class="fade-in"><?php echo ct('hero_title'); ?></h1>
    <p class="hero-subtitle fade-in"><?php echo ct('hero_subtitle'); ?></p>
    <p class="hero-description fade-in"><?php echo ct('hero_description'); ?></p>
  </div>
</section>

<!-- Contact Methods -->
<section class="contact-methods section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo ct('contact_methods_title'); ?></h2>
      <p class="section-subtitle"><?php echo ct('contact_methods_subtitle'); ?></p>
    </div>
    
    <div class="methods-grid">
      <div class="method-card fade-in">
        <div class="method-icon">
          <i class="fas fa-phone-alt"></i>
        </div>
        <h3>Phone Call</h3>
        <p>Speak directly with our education consultants for immediate assistance.</p>
        <p class="method-contact"><?php echo ct('sanfrancisco_phone'); ?></p>
      </div>
      
      <div class="method-card fade-in delay-1">
        <div class="method-icon">
          <i class="fas fa-envelope"></i>
        </div>
        <h3>Email</h3>
        <p>Send detailed inquiries and documents for comprehensive review.</p>
        <p class="method-contact"><?php echo ct('info_email'); ?></p>
      </div>
      
      <div class="method-card fade-in delay-2">
        <div class="method-icon">
          <i class="fas fa-calendar-alt"></i>
        </div>
        <h3>Consultation</h3>
        <p>Book a personalized virtual or in-person meeting with our experts.</p>
        <p class="method-contact">Book Online</p>
      </div>
      
      <div class="method-card fade-in delay-3">
        <div class="method-icon">
          <i class="fas fa-comments"></i>
        </div>
        <h3>Live Chat</h3>
        <p>Get instant answers to your questions through our website chat.</p>
        <p class="method-contact">Available 24/7</p>
      </div>
    </div>
  </div>
</section>

<!-- Global Offices & Interactive Map -->
<section class="offices-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo ct('offices_title'); ?></h2>
      <p class="section-subtitle"><?php echo ct('offices_subtitle'); ?></p>
    </div>
    
    <div class="offices-container">
      <!-- Interactive Map -->
      <div class="map-container fade-in">
        <div class="map-placeholder">
          <i class="fas fa-globe-americas"></i>
          <h3>INTERACTIVE MAP</h3>
          <p>Click on any office location to view details. Our global presence ensures we can assist you no matter where you are.</p>
          <div style="margin-top: 20px; text-align: center;">
            <div style="display: inline-block; background: var(--accent-gold); color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
              <i class="fas fa-map-marker-alt"></i> 4 Global Offices
            </div>
          </div>
        </div>
        
        <!-- Map Markers -->
        <div class="map-marker" style="top: 35%; left: 20%;" onclick="selectOffice(0)"></div>
        <div class="map-marker" style="top: 55%; left: 50%;" onclick="selectOffice(1)"></div>
        <div class="map-marker" style="top: 50%; left: 55%;" onclick="selectOffice(2)"></div>
        <div class="map-marker" style="top: 52%; left: 53%;" onclick="selectOffice(3)"></div>
        
        <!-- Map Info Box -->
        <div class="marker-info" id="mapInfo">
          <h4 id="infoTitle"><?php echo ct('sanfrancisco_title'); ?></h4>
          <p id="infoAddress"><?php echo ct('sanfrancisco_address'); ?></p>
          <p id="infoPhone"><?php echo ct('sanfrancisco_phone'); ?></p>
          <p id="infoHours"><?php echo ct('sanfrancisco_hours'); ?></p>
        </div>
      </div>
      
      <!-- Offices List -->
      <div class="offices-list">
        <?php foreach($offices as $index => $office): ?>
        <div class="office-card fade-in <?php echo $index === 0 ? 'active' : ''; ?>" 
             onclick="selectOffice(<?php echo $index; ?>)"
             id="officeCard<?php echo $index; ?>">
          <div class="office-header">
            <div class="office-flag"><?php echo $office['flag']; ?></div>
            <div class="office-info">
              <h3><?php echo ct($office['title_key']); ?></h3>
              <p class="office-address"><?php echo ct($office['address_key']); ?></p>
            </div>
          </div>
          
          <div class="office-details">
            <div class="office-detail">
              <i class="fas fa-phone"></i>
              <span><?php echo ct($office['phone_key']); ?></span>
            </div>
            <div class="office-detail">
              <i class="fas fa-clock"></i>
              <span><?php echo ct($office['hours_key']); ?></span>
            </div>
            <div class="office-detail">
              <i class="fas fa-globe"></i>
              <span>Timezone: <?php echo $office['timezone']; ?></span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- Contact Form -->
<section class="form-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo ct('form_title'); ?></h2>
      <p class="section-subtitle"><?php echo ct('form_subtitle'); ?></p>
    </div>
    
    <div class="form-container fade-in">
      <?php if($email_sent): ?>
      <div class="form-message success">
        <i class="fas fa-check-circle"></i> <?php echo ct('form_success'); ?>
        <p style="margin-top: 10px; font-size: 0.9rem;">We'll get back to you within 24 hours.</p>
      </div>
      <?php elseif($form_error): ?>
      <div class="form-message error">
        <i class="fas fa-exclamation-circle"></i> <?php echo $form_error; ?>
      </div>
      <?php endif; ?>
      
      <form method="POST" action="" class="contact-form">
        <div class="form-group">
          <label for="name" class="required"><?php echo ct('form_name'); ?></label>
          <input type="text" id="name" name="name" required 
                 value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
          <label for="email" class="required"><?php echo ct('form_email'); ?></label>
          <input type="email" id="email" name="email" required 
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
          <label for="phone"><?php echo ct('form_phone'); ?></label>
          <input type="tel" id="phone" name="phone" 
                 value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
          <label for="country"><?php echo ct('form_country'); ?></label>
          <input type="text" id="country" name="country" 
                 value="<?php echo htmlspecialchars($_POST['country'] ?? ''); ?>">
        </div>
        
        <div class="form-group full-width">
          <label for="subject" class="required"><?php echo ct('form_subject'); ?></label>
          <select id="subject" name="subject" required>
            <?php foreach($subjects as $subject_key): ?>
            <option value="<?php echo ct($subject_key); ?>" 
                    <?php echo (($_POST['subject'] ?? '') === ct($subject_key)) ? 'selected' : ''; ?>>
              <?php echo ct($subject_key); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group full-width">
          <label for="message" class="required"><?php echo ct('form_message'); ?></label>
          <textarea id="message" name="message" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
        </div>
        
        <button type="submit" class="submit-button">
          <i class="fas fa-paper-plane"></i>
          <?php echo ct('form_submit'); ?>
        </button>
      </form>
    </div>
  </div>
</section>

<!-- Contact Information -->
<section class="contact-info-section section-padding">
  <div class="container">

    <div class="section-header">
      <h2 class="section-title" style="color:white;">
        <?php echo ct('info_title'); ?>
      </h2>
    </div>

    <div class="info-grid">

      <!-- Primary Email -->
      <div class="info-card fade-in">
        <div class="info-icon">
          <i class="fas fa-envelope-open-text"></i>
        </div>

        <h3>Primary Email</h3>

        <p class="info-content">
          <a href="mailto:<?php echo ct('info_email'); ?>" class="info-email">
            <?php echo ct('info_email'); ?>
          </a>
        </p>

        <p class="info-desc">
          For general inquiries and document submissions
        </p>
      </div>


      <!-- Partnership Email -->
      <div class="info-card fade-in">
        <div class="info-icon">
          <i class="fas fa-handshake"></i>
        </div>

        <h3>Partnership Email</h3>

        <p class="info-content">
          <a href="mailto:infos@scholarsyncglobal.ca" class="info-email">
            infos@scholarsyncglobal.ca
          </a>
        </p>

        <p class="info-desc">
          For partnerships and collaboration opportunities
        </p>
      </div>


      <!-- Phone -->
      <div class="info-card fade-in">
        <div class="info-icon">
          <i class="fas fa-headset"></i>
        </div>

        <h3><?php echo ct('info_emergency'); ?></h3>

        <p class="info-content">
          <strong><?php echo ct('sanfrancisco_phone'); ?></strong><br>
          Available during business hours
        </p>

        <p class="info-desc">
          For time-sensitive admission and visa matters
        </p>
      </div>


      <!-- Social -->
      <div class="info-card fade-in">
        <div class="info-icon">
          <i class="fas fa-share-alt"></i>
        </div>

        <h3><?php echo ct('info_social'); ?></h3>

        <div class="social-links contact-social-grid">
          <a href="https://www.scholarsyncglobal.ca/" class="social-link" target="_blank" rel="noopener noreferrer" title="Website"><i class="fas fa-globe"></i></a>
          <a href="https://www.facebook.com/share/163KCdfJVW/" class="social-link" target="_blank" rel="noopener noreferrer" title="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="https://www.instagram.com/scholarsyncglobal/" class="social-link" target="_blank" rel="noopener noreferrer" title="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="https://x.com/scholarsyncglobal" class="social-link" target="_blank" rel="noopener noreferrer" title="X"><i class="fab fa-x-twitter"></i></a>
          <a href="https://www.linkedin.com/company/scholarsyncglobal/" class="social-link" target="_blank" rel="noopener noreferrer" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
          <a href="https://pin.it/7894Jdlgv" class="social-link" target="_blank" rel="noopener noreferrer" title="Pinterest"><i class="fab fa-pinterest-p"></i></a>
          <a href="https://www.tiktok.com/@scholarsyncglobal" class="social-link" target="_blank" rel="noopener noreferrer" title="TikTok"><i class="fab fa-tiktok"></i></a>
          <a href="https://youtube.com/@scholarsyncglobal" class="social-link" target="_blank" rel="noopener noreferrer" title="YouTube"><i class="fab fa-youtube"></i></a>
          <a href="https://chat.whatsapp.com/DuKFfLQwRXWGDatXJSlvOf" class="social-link social-whatsapp" target="_blank" rel="noopener noreferrer" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
        </div>

        <p class="info-desc">
          Follow us for updates and student stories
        </p>
      </div>

    </div>

  </div>
</section>
<!-- FAQ Section -->
<section class="faq-section section-padding">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php echo ct('faq_title'); ?></h2>
      <p class="section-subtitle"><?php echo ct('faq_subtitle'); ?></p>
    </div>
    
    <div class="faq-container">
      <?php foreach($faqs as $index => $faq): ?>
      <div class="faq-item fade-in">
        <div class="faq-question" onclick="toggleFAQ(this)">
          <?php echo ct($faq['q_key']); ?>
          <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
          <?php echo ct($faq['a_key']); ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA Section -->
<section class="contact-cta">
  <div class="cta-content">
    <h2 class="fade-in"><?php echo ct('cta_title'); ?></h2>
    <p class="fade-in"><?php echo ct('cta_description'); ?></p>
    <div class="cta-buttons">
      <button class="cta-button cta-button-primary fade-in" onclick="window.location.href='consultation.php'">
        <i class="fas fa-calendar-check"></i>
        <?php echo ct('cta_button'); ?>
      </button>
      <button class="cta-button cta-button-secondary fade-in" onclick="window.open('brochure.pdf', '_blank')">
        <i class="fas fa-download"></i>
        <?php echo ct('cta_button2'); ?>
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

  // Office selection functionality
  window.selectOffice = function(index) {
    // Remove active class from all office cards
    document.querySelectorAll('.office-card').forEach(card => {
      card.classList.remove('active');
    });
    
    // Add active class to selected card
    const selectedCard = document.getElementById('officeCard' + index);
    if (selectedCard) {
      selectedCard.classList.add('active');
    }
    
    // Update map info box
    const offices = <?php echo json_encode($offices); ?>;
    const office = offices[index];
    
    document.getElementById('infoTitle').textContent = office.title_key === 'sanfrancisco_title' ? 
      'San Francisco Office' : 
      office.title_key === 'muhanga_title' ? 'Rwanda Office' :
      office.title_key === 'nairobi_title' ? 'Nairobi Office' : 'Kampala Office';
    
    document.getElementById('infoAddress').textContent = 
      office.address_key === 'sanfrancisco_address' ? 'San Francisco, CA, USA' :
      office.address_key === 'muhanga_address' ? 'Muhanga, Rwanda' :
      office.address_key === 'nairobi_address' ? 'Nairobi, Kenya' : 'Kampala, Uganda';
    
    document.getElementById('infoPhone').textContent = 
      office.phone_key === 'sanfrancisco_phone' ? '+1 (450) 390-8614' :
      office.phone_key === 'muhanga_phone' ? '+250 788 242 069' :
      office.phone_key === 'nairobi_phone' ? '+254 744 111 121' : '+256 767 418 006';
    
    document.getElementById('infoHours').textContent = 
      office.hours_key === 'sanfrancisco_hours' ? 'Mon-Fri: 9:00 AM - 6:00 PM PST' :
      office.hours_key === 'muhanga_hours' ? 'Mon-Fri: 9:00 AM - 5:00 PM CAT' :
      office.hours_key === 'nairobi_hours' ? 'Mon-Fri: 9:00 AM - 5:00 PM EAT' : 'Mon-Fri: 9:00 AM - 5:00 PM EAT';
  };

  // FAQ toggle functionality
  window.toggleFAQ = function(element) {
    const faqItem = element.closest('.faq-item');
    const isActive = faqItem.classList.contains('active');
    
    // Close all FAQ items
    document.querySelectorAll('.faq-item').forEach(item => {
      item.classList.remove('active');
    });
    
    // Open clicked item if it wasn't active
    if (!isActive) {
      faqItem.classList.add('active');
    }
  };

  // Form validation and enhancement
  const contactForm = document.querySelector('.contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', function(e) {
      const requiredFields = this.querySelectorAll('[required]');
      let isValid = true;
      
      requiredFields.forEach(field => {
        if (!field.value.trim()) {
          isValid = false;
          field.style.borderColor = '#EF4444';
          field.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
        } else {
          field.style.borderColor = '';
          field.style.boxShadow = '';
        }
      });
      
      if (!isValid) {
        e.preventDefault();
        alert('Please fill in all required fields.');
      } else {
        // Show loading state
        const submitButton = this.querySelector('.submit-button');
        if (submitButton) {
          submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
          submitButton.disabled = true;
        }
      }
    });
  }

  // Phone number formatting
  const phoneInput = document.getElementById('phone');
  if (phoneInput) {
    phoneInput.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      if (value.length > 0) {
        value = '+' + value;
        if (value.length > 4) {
          value = value.substring(0, 4) + ' ' + value.substring(4);
        }
        if (value.length > 8) {
          value = value.substring(0, 8) + ' ' + value.substring(8);
        }
        if (value.length > 12) {
          value = value.substring(0, 12) + ' ' + value.substring(12);
        }
      }
      e.target.value = value;
    });
  }

  // Character counter for message
  const messageInput = document.getElementById('message');
  if (messageInput) {
    const charCounter = document.createElement('div');
    charCounter.className = 'char-counter';
    charCounter.style.fontSize = '0.8rem';
    charCounter.style.color = 'var(--text-muted)';
    charCounter.style.textAlign = 'right';
    charCounter.style.marginTop = '5px';
    messageInput.parentNode.appendChild(charCounter);
    
    function updateCharCount() {
      const length = messageInput.value.length;
      charCounter.textContent = `${length} characters (minimum 50 recommended)`;
      charCounter.style.color = length < 50 ? '#EF4444' : 'var(--text-muted)';
    }
    
    messageInput.addEventListener('input', updateCharCount);
    updateCharCount();
  }

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

  // Initialize animations
  animateOnScroll();

})();
</script>

</body>
</html>