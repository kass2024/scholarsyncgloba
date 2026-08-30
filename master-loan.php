<?php
// ============================================
// INCLUDE HEADER FOR LANGUAGE SWITCHING LOGIC
// ============================================
include 'header.php';

// ============================================
// TRANSLATIONS FOR MASTER LOAN FORM
// ============================================

$loan_form_translations = [
    'en' => [
        'page_title' => 'Masters Loan Application | ScholarSync Global',
        'page_description' => 'Complete your Masters loan application with ScholarSync Global.',
        
        // Form Sections
        'main_title' => 'MASTERS LOAN APPLICATION FORM',
        'step1_title' => 'Step 1: Personal Information',
        'step2_title' => 'Step 2: Loan Application Details',
        'step3_title' => 'Step 3: Citizenship & Reference',
        'step4_title' => 'Step 4: Upload Required Documents',
        
        // Notice
        'important_note' => 'NOTE: You will be contacted by <strong>Priya Shukla</strong> – Senior Loan Officer.',
        'contact_email' => 'priya.shukla@applyboard.com',
        'contact_phone' => '+91 92050 66409',
        
        // Labels
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'gender' => 'Gender *',
        'please_select' => 'Please Select',
        'male' => 'Male',
        'female' => 'Female',
        'dob' => 'Date of Birth *',
        'phone' => 'Phone Number',
        'email' => 'Email Address',
        'address' => 'Address',
        'street_address' => 'Street Address',
        'street_address2' => 'Street Address Line 2',
        'city' => 'City',
        'state' => 'State / Province',
        'postal_code' => 'Postal / Zip Code',
        
        // Step 2
        'loan_reason' => 'What brings you to apply for loan? *',
        'loan_reason_options' => ['Thinking about studying', 'Applied, awaiting admission', 'Accepted, yet to start classes', 'Enrolled, attending classes', 'Transferring to a new school'],
        'masters_program' => 'NAME OF MASTERS PROGRAM YOU WILL ATTEND *',
        'school_name' => 'Name of the school you will attend. *',
        'degree_type' => 'Type of degree you are planning to pursue? *',
        'degree_options' => ['Masters', 'MBA', 'Other'],
        'application_type' => 'Application Type *',
        'usa_option' => 'Tuition and Living Allowance in USA',
        'canada_option' => 'Tuition Fees Only in Canada',
        'europe_option' => 'Tuition Fees Only in Europe',
        'intake' => 'Intake Apply For *',
        'spring_intake' => 'Spring/winter Intake',
        'summer_intake' => 'Summer Intake',
        'fall_intake' => 'Fall Intake',
        
        // Step 3
        'citizenship' => 'What\'s your citizenship country? *',
        'has_visa' => 'Do you already have a visa to study in the United States? *',
        'has_ssn' => 'Do you have a U.S. Social Security Number? *',
        'yes' => 'YES',
        'no' => 'NO',
        'reference_name' => 'Full Name of Reference Person *',
        'reference_email' => 'Email of Reference Person *',
        'reference_phone' => 'Phone Number of Reference Person *',
        'reference_relationship' => 'Relationship with Reference Person *',
        
        // Step 4 - Document Labels
        'acceptance_letter' => 'Acceptance Letter or Offer for Admission',
        'bachelor_degree' => 'Bachelor Degree',
        'bachelor_transcript' => 'Bachelor Transcript',
        'cv' => 'CV',
        'id' => 'ID',
        'valid_passport' => 'VALID PASSPORT',
        'english_certificate' => 'ENGLISH CERTIFICATE',
        'admission_fees' => 'ADMISSION FEES',
        'scholarship_letter' => 'Scholarship Letter (if applicable)',
        'bank_statement' => 'Your Bank Statement or Sponsor Bank Statement (if applicable)',
        
        // Certification
        'certification_title' => 'Applicant Certification',
        'certification_text' => 'I certify that the information I have provided above is true to the best of my knowledge and belief.',
        'date_signed' => 'Date Signed by Applicant *',
        
        // Buttons
        'save_next' => 'Save & Next',
        'previous' => 'Previous',
        'submit_application' => 'Submit Application',
        
        // Placeholders
        'enter_program' => 'Enter program name',
        'phone_placeholder' => '(000) 000-0000',
        'email_placeholder' => 'example@example.com',
        'select_or_type' => 'Select or type school name',
        
        // Progress Messages
        'uploading' => 'Uploading...',
        'submitting' => 'Submitting...',
        'almost_done' => 'Almost done...',
        'submitted' => 'Submitted! 🎉',
        'saving_step' => 'Saving your information...',
        'validating_data' => 'Validating your data...',
        'preparing_files' => 'Preparing files for upload...',
        'uploading_files' => 'Uploading documents...',
        'finalizing' => 'Finalizing submission...',
        'sending_confirmation' => 'Sending confirmation...',
        
        // Success Messages
        'success_saved' => 'Information saved successfully!',
        'success_submitted' => 'Application submitted successfully!',
        'email_sent' => 'Application submitted and email sent to admin!',
        'email_failed' => 'Submission saved, but failed to email admin: ',
        
        // Error Messages
        'error_saving' => 'Error saving your information',
        'network_error' => 'Network or server error',
        'upload_failed' => 'Upload failed!',
        
        // Provider Text
        'applying_with' => 'Applying with:',
        'education_loan' => 'Education Loan Application',

        'smart_autofill_title' => 'Smart AI autofill',
        'smart_autofill_desc' => 'Upload passport, CV, transcripts, degree, or other documents. AI extracts your details and routes each file into the matching upload fields on this form.',
        'smart_autofill_existing_note' => 'Files are saved on your application record the same way as a manual upload.',
        'smart_autofill_button' => 'Add documents',
        'smart_autofill_start' => 'Start analysis',
        'smart_autofill_gate' => 'Save step 1 (personal information) first, then you can run document analysis.',
        'smart_autofill_formats' => 'Supported: PDF, DOCX, JPG, JPEG, PNG, WEBP.',
        'smart_autofill_hint' => 'Tip: upload passport, CV, and academic documents together for best results.',
        'smart_autofill_queue_empty' => 'No documents selected yet.',
        'smart_autofill_processing' => 'Analyzing documents…',
        'smart_autofill_ready' => 'Ready to analyze.',
        'smart_autofill_error' => 'Analysis failed.',
        'smart_autofill_complete' => 'Analysis finished. Your details and routed documents were applied. Review the form and continue when you are ready—nothing was submitted automatically.',
        'smart_autofill_uploading' => 'Saving recognized documents into your upload fields…',
        'smart_autofill_stage_queue' => 'Documents queued',
        'smart_autofill_stage_batch' => 'AI document analysis',
        'smart_autofill_stage_route' => 'Attaching files to fields',
        'smart_autofill_stage_done' => 'Autofill complete',
        'smart_autofill_detail_batch' => 'Reading each file and extracting your details with AI.',
        'smart_autofill_detail_route' => 'Uploading classified files into the matching attachment fields.',
        'smart_autofill_detail_done' => 'Review the form and continue when you are ready.',
        'smart_autofill_results_title' => 'Recognized documents',
        'smart_autofill_warnings_title' => 'Warnings',
        'smart_autofill_queue_title' => 'Queued documents',
        'smart_autofill_queue_ready' => 'Files are queued. Click Start analysis when you are ready.',
        'smart_autofill_queue_count' => 'file(s) queued',
    ],
    
    'fr' => [
        'page_title' => 'Demande de Prêt Master | ScholarSync Global',
        'page_description' => 'Complétez votre demande de prêt Master avec ScholarSync Global.',
        
        // Form Sections
        'main_title' => 'FORMULAIRE DE DEMANDE DE PRÊT MASTER',
        'step1_title' => 'Étape 1 : Informations Personnelles',
        'step2_title' => 'Étape 2 : Détails de la Demande de Prêt',
        'step3_title' => 'Étape 3 : Citoyenneté & Référence',
        'step4_title' => 'Étape 4 : Télécharger les Documents Requis',
        
        // Notice
        'important_note' => 'NOTE : Vous serez contacté par <strong>Priya Shukla</strong> – Agent Principal de Prêt.',
        'contact_email' => 'priya.shukla@applyboard.com',
        'contact_phone' => '+91 92050 66409',
        
        // Labels
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'gender' => 'Genre *',
        'please_select' => 'Veuillez Sélectionner',
        'male' => 'Homme',
        'female' => 'Femme',
        'dob' => 'Date de Naissance *',
        'phone' => 'Numéro de Téléphone',
        'email' => 'Adresse Email',
        'address' => 'Adresse',
        'street_address' => 'Adresse',
        'street_address2' => 'Ligne d\'Adresse 2',
        'city' => 'Ville',
        'state' => 'État / Province',
        'postal_code' => 'Code Postal',
        
        // Step 2
        'loan_reason' => 'Pourquoi demandez-vous un prêt ? *',
        'loan_reason_options' => ['En réflexion sur les études', 'Postulé, en attente d\'admission', 'Accepté, pas encore commencé', 'Inscrit, suivant les cours', 'Transfert vers une nouvelle école'],
        'masters_program' => 'NOM DU PROGRAMME DE MASTER QUE VOUS ALLEZ SUIVRE *',
        'school_name' => 'Nom de l\'école que vous allez fréquenter. *',
        'degree_type' => 'Type de diplôme que vous prévoyez de poursuivre ? *',
        'degree_options' => ['Master', 'MBA', 'Autre'],
        'application_type' => 'Type de Demande *',
        'usa_option' => 'Frais de Scolarité et Allocation de Vie aux États-Unis',
        'canada_option' => 'Frais de Scolarité Seulement au Canada',
        'europe_option' => 'Frais de Scolarité Seulement en Europe',
        'intake' => 'Sélection d\'Entrée *',
        'spring_intake' => 'Entrée Printemps/hiver',
        'summer_intake' => 'Entrée Été',
        'fall_intake' => 'Entrée Automne',
        
        // Step 3
        'citizenship' => 'Quel est votre pays de citoyenneté ? *',
        'has_visa' => 'Avez-vous déjà un visa pour étudier aux États-Unis ? *',
        'has_ssn' => 'Avez-vous un numéro de sécurité sociale américain ? *',
        'yes' => 'OUI',
        'no' => 'NON',
        'reference_name' => 'Nom Complet de la Personne de Référence *',
        'reference_email' => 'Email de la Personne de Référence *',
        'reference_phone' => 'Numéro de Téléphone de la Personne de Référence *',
        'reference_relationship' => 'Relation avec la Personne de Référence *',
        
        // Step 4 - Document Labels
        'acceptance_letter' => 'Lettre d\'Acceptation ou Offre d\'Admission',
        'bachelor_degree' => 'Diplôme de Licence',
        'bachelor_transcript' => 'Relevé de Notes de Licence',
        'cv' => 'CV',
        'id' => 'Pièce d\'Identité',
        'valid_passport' => 'PASSEPORT VALIDE',
        'english_certificate' => 'CERTIFICAT D\'ANGLAIS',
        'admission_fees' => 'FRAIS D\'ADMISSION',
        'scholarship_letter' => 'Lettre de Bourse (le cas échéant)',
        'bank_statement' => 'Votre Relevé Bancaire ou Relevé Bancaire du Garant (le cas échéant)',
        
        // Certification
        'certification_title' => 'Certification du Demandeur',
        'certification_text' => 'Je certifie que les informations fournies ci-dessus sont vraies au meilleur de ma connaissance et de ma conviction.',
        'date_signed' => 'Date Signée par le Demandeur *',
        
        // Buttons
        'save_next' => 'Sauvegarder & Suivant',
        'previous' => 'Précédent',
        'submit_application' => 'Soumettre la Demande',
        
        // Placeholders
        'enter_program' => 'Entrer le nom du programme',
        'phone_placeholder' => '(000) 000-0000',
        'email_placeholder' => 'exemple@exemple.com',
        'select_or_type' => 'Sélectionnez ou tapez le nom de l\'école',
        
        // Progress Messages
        'uploading' => 'Téléchargement...',
        'submitting' => 'Soumission...',
        'almost_done' => 'Presque terminé...',
        'submitted' => 'Soumis ! 🎉',
        'saving_step' => 'Sauvegarde de vos informations...',
        'validating_data' => 'Validation de vos données...',
        'preparing_files' => 'Préparation des fichiers...',
        'uploading_files' => 'Téléchargement des documents...',
        'finalizing' => 'Finalisation...',
        'sending_confirmation' => 'Envoi de la confirmation...',
        
        // Success Messages
        'success_saved' => 'Informations sauvegardées avec succès !',
        'success_submitted' => 'Demande soumise avec succès !',
        'email_sent' => 'Demande soumise et email envoyé à l\'administrateur !',
        'email_failed' => 'Soumission sauvegardée, mais échec d\'envoi d\'email à l\'admin : ',
        
        // Error Messages
        'error_saving' => 'Erreur lors de la sauvegarde',
        'network_error' => 'Erreur réseau ou serveur',
        'upload_failed' => 'Échec du téléchargement !',
        
        // Provider Text
        'applying_with' => 'Demande pour :',
        'education_loan' => 'Demande de Prêt Éducation',

        'smart_autofill_title' => 'Remplissage automatique IA',
        'smart_autofill_desc' => 'Téléversez passeport, CV, relevés ou autres documents. L\'IA extrait vos informations et classe chaque fichier dans le bon champ.',
        'smart_autofill_existing_note' => 'Les fichiers sont enregistrés sur votre demande comme un envoi manuel.',
        'smart_autofill_button' => 'Ajouter des documents',
        'smart_autofill_start' => 'Lancer l\'analyse',
        'smart_autofill_gate' => 'Enregistrez d\'abord l\'étape 1 (informations personnelles).',
        'smart_autofill_formats' => 'Formats : PDF, DOCX, JPG, JPEG, PNG, WEBP.',
        'smart_autofill_hint' => 'Astuce : téléversez passeport, CV et documents académiques ensemble.',
        'smart_autofill_queue_empty' => 'Aucun document sélectionné.',
        'smart_autofill_processing' => 'Analyse des documents…',
        'smart_autofill_ready' => 'Prêt à analyser.',
        'smart_autofill_error' => 'L\'analyse a échoué.',
        'smart_autofill_complete' => 'Analyse terminée. Vos informations et documents ont été appliqués. Vérifiez le formulaire puis continuez—aucune soumission automatique.',
        'smart_autofill_uploading' => 'Enregistrement des documents reconnus dans vos champs de téléversement…',
        'smart_autofill_stage_queue' => 'Documents en file',
        'smart_autofill_stage_batch' => 'Analyse IA des documents',
        'smart_autofill_stage_route' => 'Rattachement des fichiers',
        'smart_autofill_stage_done' => 'Remplissage terminé',
        'smart_autofill_detail_batch' => 'Lecture de chaque fichier et extraction de vos informations par l’IA.',
        'smart_autofill_detail_route' => 'Téléversement des fichiers classés vers les champs correspondants.',
        'smart_autofill_detail_done' => 'Vérifiez le formulaire puis continuez quand vous êtes prêt.',
        'smart_autofill_results_title' => 'Documents reconnus',
        'smart_autofill_warnings_title' => 'Avertissements',
        'smart_autofill_queue_title' => 'Documents en file',
        'smart_autofill_queue_ready' => 'Les fichiers sont en file. Cliquez sur Lancer l’analyse quand vous êtes prêt.',
        'smart_autofill_queue_count' => 'fichier(s) en file',
    ]
];

function lft($key) {
    global $loan_form_translations, $current_lang;
    return isset($loan_form_translations[$current_lang][$key]) ? $loan_form_translations[$current_lang][$key] : $key;
}

// ============================================
// DATABASE — load application row
// ============================================
require_once __DIR__ . '/db.php';

$providerId = $_GET['provider_id'] ?? null;
$providerName = '';
$formData = [];

if (!isset($_GET['id'])) {
    $userId = 'user-' . time() . '-' . rand(1000, 9999);
    $redirectUrl = 'master-loan.php?id=' . rawurlencode($userId);
    if ($providerId !== null && $providerId !== '') {
        $redirectUrl .= '&provider_id=' . rawurlencode((string)$providerId);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

$userId = (string)$_GET['id'];
$_SESSION['loan_user_id'] = $userId;

$boot = $conn->prepare('INSERT IGNORE INTO master_loan_applications (user_id) VALUES (?)');
if ($boot) {
    $boot->bind_param('s', $userId);
    @$boot->execute();
    $boot->close();
}

$st = $conn->prepare('SELECT * FROM master_loan_applications WHERE user_id = ? LIMIT 1');
if ($st) {
    $st->bind_param('s', $userId);
    $st->execute();
    $res = $st->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $formData = $row;
    }
    $st->close();
}

if ($providerId === null || $providerId === '') {
    $providerId = $formData['loan_provider_id'] ?? null;
}

if (!empty($providerId)) {
    $pid = (int)$providerId;
    $p = $conn->prepare('SELECT name FROM loan_providers WHERE id = ? LIMIT 1');
    if ($p) {
        $p->bind_param('i', $pid);
        $p->execute();
        $p->bind_result($pname);
        if ($p->fetch()) {
            $providerName = (string)$pname;
        }
        $p->close();
    }
}

function loan_form_json_list(?string $raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }
    $d = json_decode($raw, true);
    return is_array($d) ? array_values(array_filter(array_map('strval', $d), static fn($x) => trim($x) !== '')) : [];
}

function loan_field_display_string(?string $raw): string
{
    if ($raw === null) {
        return '';
    }
    $t = trim((string)$raw);
    if ($t === '') {
        return '';
    }
    $d = json_decode($t, true);
    if (is_array($d)) {
        $flat = array_values(array_filter(array_map(static fn($x) => trim((string)$x), $d), static fn($x) => $x !== ''));
        return implode(', ', $flat);
    }
    return $t;
}

function checked($field, $value) {
    global $formData;
    if (!isset($formData[$field])) {
        return '';
    }
    $v = $formData[$field];
    if (is_string($v) && str_starts_with(trim($v), '[')) {
        $list = loan_form_json_list($v);
        return in_array((string)$value, $list, true) ? 'checked' : '';
    }
    return ((string)$v === (string)$value) ? 'checked' : '';
}

function selected($field, $value) {
    global $formData;
    if (!isset($formData[$field])) {
        return '';
    }
    $v = $formData[$field];
    if (is_string($v) && str_starts_with(trim($v), '[')) {
        $list = loan_form_json_list($v);
        return in_array((string)$value, $list, true) ? 'selected' : '';
    }
    return ((string)$v === (string)$value) ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo lft('page_description'); ?>">
<title><?php echo lft('page_title'); ?></title>

<!-- External Styles -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* =========================================================
   MASTER LOAN FORM STYLES
   Compatible with header.php styles
========================================================= */

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
  --shadow-sm: 0 2px 8px rgba(1, 47, 107, 0.05);
  --shadow-md: 0 10px 25px rgba(1, 47, 107, 0.08);
  --shadow-lg: 0 18px 40px rgba(1, 47, 107, 0.1);
  
  /* Border Radius */
  --radius-sm: 8px;
  --radius-md: 14px;
  --radius-lg: 22px;
  
  /* Transitions */
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ===== FORM PAGE HEADER ===== */
.form-page-header {
  background: linear-gradient(135deg, var(--primary-navy) 0%, var(--dark-blue) 100%);
  color: white;
  text-align: center;
  padding: 40px 20px;
  position: relative;
  overflow: hidden;
  margin-top: 80px; /* Account for fixed header */
}

.form-page-header::before {
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

.form-header-content {
  max-width: 800px;
  margin: 0 auto;
  position: relative;
  z-index: 2;
}

.form-page-header h1 {
  font-size: 2.2rem;
  font-weight: 800;
  margin-bottom: 15px;
  background: linear-gradient(135deg, #FFFFFF 0%, rgba(255, 255, 255, 0.9) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.form-page-header p {
  color: rgba(255, 255, 255, 0.85);
  font-size: 1.1rem;
  max-width: 600px;
  margin: 0 auto;
}

/* ===== PAGE SPACING ===== */
.page-section {
  padding: 40px 20px 100px;
  max-width: 1000px;
  margin: 0 auto;
  position: relative;
  z-index: 3;
}

/* ===== FORM CONTAINER ===== */
.form-container {
  background: var(--card);
  border-radius: var(--radius-lg);
  padding: 50px 45px;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--border-light);
  position: relative;
  overflow: hidden;
}

.form-container::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 6px;
  background: linear-gradient(90deg, var(--accent-gold), var(--accent-teal));
}

/* ===== HEADINGS ===== */
h2 {
  font-size: 2.4rem;
  font-weight: 800;
  text-align: center;
  margin-bottom: 18px;
  background: linear-gradient(135deg, var(--primary-navy), var(--dark-blue));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  letter-spacing: -0.03em;
  position: relative;
}

h2::after {
  content: '';
  position: absolute;
  bottom: -12px;
  left: 50%;
  transform: translateX(-50%);
  width: 100px;
  height: 4px;
  background: linear-gradient(90deg, var(--accent-gold), var(--accent-teal));
  border-radius: 2px;
}

h3 {
  font-size: 1.6rem;
  font-weight: 700;
  text-align: center;
  margin: 32px 0 22px;
  color: var(--primary-navy);
  padding-bottom: 10px;
  border-bottom: 2px solid var(--border-light);
}

/* ===== LABELS ===== */
label {
  font-weight: 600;
  font-size: 0.95rem;
  color: var(--text);
  margin-top: 20px;
  display: block;
}

label.required::after {
  content: ' *';
  color: #DC2626;
}

/* ===== INPUTS ===== */
input[type="text"],
input[type="email"],
input[type="date"],
input[type="tel"],
select,
textarea {
  width: 100%;
  padding: 14px 18px;
  margin-top: 6px;
  border-radius: var(--radius-md);
  border: 2px solid var(--border);
  font-size: 0.95rem;
  background: var(--bg);
  color: var(--text);
  transition: var(--transition);
  font-family: inherit;
}

input:focus,
select:focus,
textarea:focus {
  outline: none;
  background: var(--pure-white);
  border-color: var(--accent-teal);
  box-shadow: 0 0 0 4px rgba(45, 212, 191, 0.15);
}

/* ===== INLINE INPUTS ===== */
.inline-inputs {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.inline-inputs input {
  flex: 1 1 100%;
}

/* ===== TEXTAREA ===== */
textarea {
  resize: vertical;
  min-height: 110px;
  font-family: inherit;
}

/* ===== UPLOAD BOX ===== */
.upload-box {
  width: 100%;
  border: 2px dashed var(--border);
  border-radius: var(--radius-md);
  padding: 22px;
  text-align: center;
  color: var(--text-light);
  background: var(--bg);
  margin-top: 10px;
  transition: var(--transition);
  cursor: pointer;
  position: relative;
}

.upload-box:hover {
  border-color: var(--accent-teal);
  background: var(--pure-white);
  transform: translateY(-2px);
}

.upload-box input[type="file"] {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}

/* ===== FORM STEPS ===== */
.form-step {
  display: none;
}

.form-step.active {
  display: block;
  animation: fadeUp .4s ease;
}

@keyframes fadeUp {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* ===== RADIO / CHECKBOX GROUPS ===== */
.radio-group,
.checkbox-group {
  margin-top: 10px;
}

.radio-group label,
.checkbox-group label {
  display: block;
  margin-top: 10px;
  font-size: 0.95rem;
  color: var(--text);
  padding: 10px 15px;
  border-radius: var(--radius-md);
  background: var(--bg);
  cursor: pointer;
  transition: var(--transition);
  border: 2px solid transparent;
}

.radio-group label:hover,
.checkbox-group label:hover {
  background: var(--primary-light);
  border-color: var(--accent-teal);
}

.radio-group input[type="radio"]:checked + label,
.checkbox-group input[type="checkbox"]:checked + label {
  background: linear-gradient(135deg, var(--primary-light), var(--teal-light));
  border-color: var(--accent-teal);
  font-weight: 600;
  color: var(--primary-navy);
}

/* ===== IMPORTANT NOTICE ===== */
.important-notice {
  background: linear-gradient(135deg, #E0F7FA, #E1F5FE);
  padding: 18px 22px;
  margin: 26px 0 32px;
  border-left: 5px solid var(--accent-teal);
  border-radius: var(--radius-md);
  font-weight: 600;
  color: var(--primary-navy);
  box-shadow: var(--shadow-sm);
}

.important-notice a {
  color: var(--primary-navy);
  text-decoration: none;
  font-weight: 700;
  transition: var(--transition);
}

.important-notice a:hover {
  color: var(--accent-gold);
  text-decoration: underline;
}

/* Smart AI autofill progress (aligned with student application UX) */
.smart-autofill-queue-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: #fff;
  font-size: 13px;
  color: #0f172a;
  margin-bottom: 8px;
}
.smart-autofill-queue-name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.smart-autofill-remove {
  border: none;
  background: transparent;
  color: #dc2626;
  cursor: pointer;
  font-size: 1.1rem;
  line-height: 1;
  padding: 0 2px;
}
.smart-autofill-progress-panel {
  display: none;
  align-items: center;
  gap: 18px;
  margin-top: 14px;
  padding: 16px 18px;
  border: 1px solid #dbeafe;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.95);
  transition: box-shadow 0.35s ease, border-color 0.35s ease;
}
.smart-autofill-progress-panel.active {
  display: flex;
}
.smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) {
  border: 2px solid transparent;
  background:
    linear-gradient(#fff, #fff) padding-box,
    linear-gradient(125deg, #38bdf8, #6366f1, #a855f7, #2563eb, #06b6d4) border-box;
  box-shadow:
    0 0 0 1px rgba(99, 102, 241, 0.12),
    0 0 28px rgba(59, 130, 246, 0.35),
    0 14px 36px rgba(79, 70, 229, 0.2);
  animation: mlSmartAutofillPanelPulse 2.4s ease-in-out infinite;
}
.smart-autofill-orb {
  position: relative;
  width: 92px;
  height: 92px;
  flex-shrink: 0;
}
.smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-orb {
  width: 104px;
  height: 104px;
}
.smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-orb::before {
  content: "";
  position: absolute;
  inset: -10px;
  border-radius: 50%;
  border: 3px solid rgba(59, 130, 246, 0.55);
  animation: mlSmartAutofillRipple 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
  pointer-events: none;
}
.smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-orb::after {
  content: "";
  position: absolute;
  inset: -10px;
  border-radius: 50%;
  border: 2px solid rgba(168, 85, 247, 0.35);
  animation: mlSmartAutofillRipple 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
  animation-delay: 0.55s;
  pointer-events: none;
}
.smart-autofill-orb-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: conic-gradient(from 0deg, #2563eb, #22d3ee, #a855f7, #6366f1, #3b82f6, #2563eb);
  animation: mlSmartAutofillSpin 0.85s linear infinite;
  filter: drop-shadow(0 0 10px rgba(59, 130, 246, 0.65)) drop-shadow(0 0 18px rgba(139, 92, 246, 0.45));
}
.smart-autofill-orb-ring::after {
  content: "";
  position: absolute;
  inset: 10px;
  border-radius: 50%;
  background: #f8fbff;
}
.smart-autofill-orb-core {
  position: absolute;
  inset: 18px;
  border-radius: 50%;
  background: radial-gradient(circle at 30% 25%, #ffffff 0%, #eff6ff 55%, #e0f2fe 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 8px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.04em;
  color: #1e3a8a;
  box-shadow:
    inset 0 0 0 1px rgba(96, 165, 250, 0.35),
    inset 0 -2px 8px rgba(59, 130, 246, 0.08);
}
.smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-orb-core {
  inset: 20px;
  font-size: 12px;
  color: #172554;
  animation: mlSmartAutofillCorePulse 1.35s ease-in-out infinite;
}
.smart-autofill-progress-panel.is-success .smart-autofill-orb-ring,
.smart-autofill-progress-panel.is-warning .smart-autofill-orb-ring,
.smart-autofill-progress-panel.is-danger .smart-autofill-orb-ring {
  animation: none;
}
.smart-autofill-progress-panel.is-success .smart-autofill-orb-ring {
  background: conic-gradient(from 0deg, #16a34a, #86efac, #16a34a);
}
.smart-autofill-progress-panel.is-warning .smart-autofill-orb-ring {
  background: conic-gradient(from 0deg, #d97706, #fcd34d, #d97706);
}
.smart-autofill-progress-panel.is-danger .smart-autofill-orb-ring {
  background: conic-gradient(from 0deg, #dc2626, #fca5a5, #dc2626);
}
.smart-autofill-progress-copy { flex: 1 1 auto; min-width: 0; }
.smart-autofill-progress-copy strong { display: block; font-size: 15px; color: #0f172a; }
.smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-progress-copy strong {
  font-size: 16px;
  font-weight: 700;
  background: linear-gradient(90deg, #1e40af, #6366f1, #0e7490);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  animation: mlSmartAutofillTitleShimmer 2.5s ease-in-out infinite;
}
.smart-autofill-progress-copy small {
  display: block;
  margin-top: 4px;
  color: #64748b;
  line-height: 1.5;
}
.smart-autofill-stage-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.smart-autofill-stage-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 11px;
  border-radius: 999px;
  border: 1px solid #dbeafe;
  background: #fff;
  color: #64748b;
  font-size: 12px;
  font-weight: 600;
}
.smart-autofill-stage-pill::before {
  content: "";
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #cbd5e1;
}
.smart-autofill-stage-pill.is-active {
  border-color: #93c5fd;
  background: #eff6ff;
  color: #1d4ed8;
}
.smart-autofill-stage-pill.is-active::before {
  background: #2563eb;
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
}
.smart-autofill-stage-pill.is-done {
  border-color: #86efac;
  background: #f0fdf4;
  color: #166534;
}
.smart-autofill-stage-pill.is-done::before { background: #16a34a; }
.smart-autofill-stage-pill.is-error {
  border-color: #fca5a5;
  background: #fef2f2;
  color: #991b1b;
}
.smart-autofill-stage-pill.is-error::before { background: #dc2626; }
.smart-autofill-results {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 10px;
}
.smart-autofill-results li {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 12px 14px;
}
.smart-autofill-results strong { display: block; font-size: 0.95rem; color: #0f172a; }
.smart-autofill-results small { display: block; margin-top: 4px; color: #64748b; line-height: 1.45; }

@keyframes mlSmartAutofillSpin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
@keyframes mlSmartAutofillPanelPulse {
  0%, 100% {
    box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.14), 0 0 24px rgba(59, 130, 246, 0.28), 0 14px 36px rgba(79, 70, 229, 0.18);
  }
  50% {
    box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.22), 0 0 38px rgba(59, 130, 246, 0.48), 0 18px 44px rgba(79, 70, 229, 0.28);
  }
}
@keyframes mlSmartAutofillRipple {
  0% { transform: scale(0.88); opacity: 0.85; }
  70% { opacity: 0.15; }
  100% { transform: scale(1.22); opacity: 0; }
}
@keyframes mlSmartAutofillCorePulse {
  0%, 100% {
    transform: scale(1);
    box-shadow: inset 0 0 0 1px rgba(96, 165, 250, 0.35), inset 0 -2px 8px rgba(59, 130, 246, 0.08);
  }
  50% {
    transform: scale(1.04);
    box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.55), inset 0 -2px 10px rgba(37, 99, 235, 0.12);
  }
}
@keyframes mlSmartAutofillTitleShimmer {
  0%, 100% { filter: brightness(1); }
  50% { filter: brightness(1.15); }
}
@media (prefers-reduced-motion: reduce) {
  .smart-autofill-orb-ring,
  .smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger),
  .smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-orb::before,
  .smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-orb::after,
  .smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-orb-core,
  .smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-progress-copy strong {
    animation: none !important;
  }
  .smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-progress-copy strong {
    color: #0f172a;
    background: none;
    -webkit-background-clip: unset;
    background-clip: unset;
  }
}

/* ===== FORM BUTTONS ===== */
.form-buttons {
  margin-top: 40px;
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 14px;
}

.form-buttons button {
  padding: 16px 28px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  flex: 1 1 48%;
  transition: var(--transition);
  letter-spacing: 0.03em;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  position: relative;
  overflow: hidden;
}

.form-buttons button::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: left 0.7s;
}

.form-buttons button:hover::before {
  left: 100%;
}

.next-btn {
  background: linear-gradient(135deg, var(--primary-navy), var(--secondary-blue));
  color: var(--pure-white);
}

.next-btn:hover {
  background: linear-gradient(135deg, var(--dark-blue), var(--primary-navy));
  transform: translateY(-3px);
  box-shadow: 0 10px 25px rgba(1, 47, 107, 0.25);
}

.prev-btn {
  background: var(--pure-white);
  color: var(--primary-navy);
  border: 2px solid var(--primary-navy);
}

.prev-btn:hover {
  background: var(--primary-light);
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(1, 47, 107, 0.15);
}

.submit-btn {
  background: linear-gradient(135deg, var(--accent-gold), #E6953E);
  color: var(--pure-white);
}

.submit-btn:hover {
  background: linear-gradient(135deg, #E6953E, var(--accent-gold));
  transform: translateY(-3px);
  box-shadow: 0 10px 25px rgba(242, 166, 90, 0.25);
}

/* ===== ENHANCED PROGRESS BAR ===== */
.progress-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.98);
  z-index: 9999;
  display: none;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  backdrop-filter: blur(5px);
}

.progress-container {
  width: 90%;
  max-width: 500px;
  background: var(--card);
  border-radius: var(--radius-lg);
  padding: 40px;
  box-shadow: var(--shadow-lg);
  text-align: center;
  border: 1px solid var(--border-light);
}

.progress-icon {
  font-size: 60px;
  color: var(--primary-navy);
  margin-bottom: 20px;
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
}

.progress-title {
  font-size: 24px;
  font-weight: 700;
  color: var(--primary-navy);
  margin-bottom: 10px;
}

.progress-subtitle {
  color: var(--text-light);
  margin-bottom: 30px;
  font-size: 0.95rem;
}

.progress-bar {
  height: 12px;
  background: var(--border);
  border-radius: 999px;
  overflow: hidden;
  margin-bottom: 20px;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--primary-navy), var(--accent-teal));
  width: 0%;
  transition: width 0.5s ease;
  border-radius: 999px;
}

.progress-text {
  font-size: 14px;
  color: var(--text-light);
  font-weight: 500;
  margin-bottom: 20px;
}

.progress-steps {
  display: flex;
  justify-content: space-between;
  margin-top: 20px;
  font-size: 12px;
  color: var(--text-light);
}

.progress-step {
  position: relative;
  text-align: center;
  flex: 1;
  padding: 5px;
  transition: var(--transition);
}

.progress-step.active {
  color: var(--primary-navy);
  font-weight: 600;
  transform: translateY(-2px);
}

/* ===== SELECT2 CUSTOMIZATION ===== */
.select2-container--default .select2-selection--multiple {
  border: 2px solid var(--border) !important;
  border-radius: var(--radius-md) !important;
  background: var(--bg) !important;
  min-height: 46px !important;
  padding: 3px !important;
}

.select2-container--default.select2-container--focus .select2-selection--multiple {
  border-color: var(--accent-teal) !important;
  box-shadow: 0 0 0 4px rgba(45, 212, 191, 0.15) !important;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
  background: linear-gradient(135deg, var(--primary-light), var(--teal-light)) !important;
  border: 1px solid var(--accent-teal) !important;
  border-radius: var(--radius-sm) !important;
  color: var(--primary-navy) !important;
  font-weight: 500 !important;
}

/* ===== RESPONSIVE DESIGN ===== */
@media (max-width: 768px) {
  .form-page-header {
    margin-top: 70px;
    padding: 30px 20px;
  }
  
  .form-page-header h1 {
    font-size: 1.8rem;
  }
  
  .page-section {
    padding: 30px 20px 80px;
  }

  .form-container {
    padding: 32px 22px;
  }

  h2 {
    font-size: 2rem;
  }

  h3 {
    font-size: 1.35rem;
  }

  .form-buttons {
    flex-direction: column;
  }

  .form-buttons button {
    width: 100%;
    flex: 1 1 100%;
  }

  .inline-inputs {
    flex-direction: column;
  }
  
  .important-notice {
    padding: 15px 18px;
    font-size: 0.95rem;
  }
  
  .progress-container {
    padding: 30px 20px;
  }
}

@media (max-width: 480px) {
  .form-page-header h1 {
    font-size: 1.6rem;
  }
  
  .form-container {
    padding: 25px 18px;
  }
  
  h2 {
    font-size: 1.8rem;
  }
  
  .form-buttons button {
    padding: 14px 20px;
    font-size: 0.95rem;
  }
  
  .upload-box {
    padding: 18px 15px;
  }
}
</style>
</head>
<body>

<!-- FORM PAGE HEADER -->
<header class="form-page-header">
  <div class="form-header-content">
    <h1><?php echo lft('main_title'); ?></h1>
    <p><?php echo lft('education_loan'); ?></p>
  </div>
</header>

<!-- SMART PROGRESS OVERLAY -->
<div id="progressOverlay" class="progress-overlay">
  <div class="progress-container">
    <div class="progress-icon">
      <i class="fas fa-graduation-cap"></i>
    </div>
    <h3 class="progress-title" id="progressTitle"><?php echo lft('submitting'); ?></h3>
    <p class="progress-subtitle" id="progressSubtitle"><?php echo lft('saving_step'); ?></p>
    
    <div class="progress-bar">
      <div class="progress-fill" id="progressFill"></div>
    </div>
    <div class="progress-text" id="progressText">0%</div>
    
    <div class="progress-steps">
      <div class="progress-step" id="step1Progress"><?php echo lft('validating_data'); ?></div>
      <div class="progress-step" id="step2Progress"><?php echo lft('preparing_files'); ?></div>
      <div class="progress-step" id="step3Progress"><?php echo lft('uploading_files'); ?></div>
      <div class="progress-step" id="step4Progress"><?php echo lft('finalizing'); ?></div>
    </div>
  </div>
</div>

<!-- MAIN CONTENT -->
<section class="page-section">
  <div class="form-container">
    <h2><?php echo lft('main_title'); ?></h2>
    
    <?php if ($providerName): ?>
    <div class="important-notice" style="text-align: center;">
      <i class="fas fa-university"></i> <?php echo lft('applying_with'); ?> 
      <strong><?php echo htmlspecialchars($providerName); ?></strong>
    </div>
    <?php endif; ?>
    
    <div class="important-notice">
      <i class="fas fa-info-circle"></i> 
      <?php echo str_replace(
        ['<strong>Priya Shukla</strong>', 'priya.shukla@applyboard.com', '+91 92050 66409'],
        ['<strong>Priya Shukla</strong>', '<a href="mailto:priya.shukla@applyboard.com">priya.shukla@applyboard.com</a>', '<a href="tel:+919205066409">+91 92050 66409</a>'],
        lft('important_note')
      ); ?>
    </div>

    <div class="smart-autofill-card" style="margin:1.25rem 0;padding:1.25rem 1.5rem;border:1px solid var(--border);border-radius:var(--radius-md);background:#f8fafc;">
      <div style="display:flex;flex-wrap:wrap;gap:1rem;justify-content:space-between;align-items:flex-start;">
        <div style="flex:1;min-width:220px;">
          <span style="display:inline-block;font-size:0.65rem;font-weight:700;letter-spacing:0.06em;padding:0.2rem 0.45rem;border-radius:999px;background:var(--secondary-blue);color:#fff;margin-right:0.35rem;">AI</span>
          <h3 style="font-size:1.05rem;font-weight:600;margin:0.5rem 0;"><?php echo htmlspecialchars(lft('smart_autofill_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
          <p style="color:var(--text-light);font-size:0.9rem;margin-bottom:0.5rem;"><?php echo htmlspecialchars(lft('smart_autofill_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
          <p style="color:var(--text-muted);font-size:0.85rem;margin:0;"><?php echo htmlspecialchars(lft('smart_autofill_existing_note'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div style="text-align:right;min-width:200px;">
          <div style="display:flex;flex-wrap:wrap;gap:0.5rem;justify-content:flex-end;">
            <button type="button" class="btn-secondary" id="mlSmartAutofillTrigger" disabled style="padding:10px 18px;border-radius:10px;border:1px solid var(--border);background:#fff;cursor:pointer;"><?php echo htmlspecialchars(lft('smart_autofill_button'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="btn-primary" id="mlSmartAutofillStart" disabled style="padding:10px 18px;border-radius:10px;border:none;background:var(--primary-navy);color:#fff;cursor:pointer;"><?php echo htmlspecialchars(lft('smart_autofill_start'), ENT_QUOTES, 'UTF-8'); ?></button>
          </div>
          <input type="file" id="mlSmartAutofillInput" multiple accept=".pdf,.docx,.jpg,.jpeg,.png,.webp" style="display:none;">
          <div id="mlSmartAutofillHelp" class="hint" style="margin-top:0.5rem;font-size:0.85rem;color:var(--text-light);text-align:left;"></div>
        </div>
      </div>
      <div id="mlSmartAutofillStatus" style="display:none;margin-top:0.75rem;padding:0.5rem 0.75rem;border-radius:8px;font-size:0.9rem;"></div>
      <div style="margin-top:1rem;border:1px dashed var(--border);border-radius:10px;padding:0.75rem 1rem;background:#fff;">
        <div style="font-weight:600;font-size:0.85rem;margin-bottom:0.35rem;"><?php echo htmlspecialchars(lft('smart_autofill_queue_title'), ENT_QUOTES, 'UTF-8'); ?></div>
        <div id="mlSmartAutofillQueueHint" style="font-size:0.85rem;color:var(--text-light);"><?php echo htmlspecialchars(lft('smart_autofill_queue_empty'), ENT_QUOTES, 'UTF-8'); ?></div>
        <ul id="mlSmartAutofillQueue" style="list-style:none;margin:0.5rem 0 0;padding:0;"></ul>
      </div>

      <div id="mlSmartAutofillProgressWrap" class="smart-autofill-progress-panel" aria-live="polite">
        <div class="smart-autofill-orb">
          <div class="smart-autofill-orb-ring"></div>
          <div class="smart-autofill-orb-core" id="mlSmartAutofillProgressText">—</div>
        </div>
        <div class="smart-autofill-progress-copy">
          <strong id="mlSmartAutofillProgressLabel"><?php echo htmlspecialchars(lft('smart_autofill_processing'), ENT_QUOTES, 'UTF-8'); ?></strong>
          <small id="mlSmartAutofillProgressSubtext"><?php echo htmlspecialchars(lft('smart_autofill_hint'), ENT_QUOTES, 'UTF-8'); ?></small>
          <div id="mlSmartAutofillStagePills" class="smart-autofill-stage-pills"></div>
        </div>
      </div>

      <div id="mlSmartAutofillPanels" style="display:none;margin-top:1rem;">
        <div style="font-weight:600;font-size:0.85rem;margin-bottom:0.5rem;color:var(--text-light);"><?php echo htmlspecialchars(lft('smart_autofill_results_title'), ENT_QUOTES, 'UTF-8'); ?></div>
        <ul id="mlSmartAutofillResults" class="smart-autofill-results"></ul>
        <div id="mlSmartAutofillWarningsWrap" style="display:none;margin-top:1rem;">
          <div style="font-weight:600;font-size:0.85rem;margin-bottom:0.5rem;color:var(--text-light);"><?php echo htmlspecialchars(lft('smart_autofill_warnings_title'), ENT_QUOTES, 'UTF-8'); ?></div>
          <ul id="mlSmartAutofillWarnings" class="smart-autofill-results"></ul>
        </div>
      </div>
    </div>

    <form id="applicationForm" enctype="multipart/form-data" data-loan-autofill-ready="<?= (!empty(trim((string)($formData['first_name'] ?? ''))) && !empty(trim((string)($formData['email'] ?? '')))) ? '1' : '0' ?>">
      <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">
      <input type="hidden" name="loan_provider_id" value="<?= htmlspecialchars($providerId) ?>">

      <!-- Step 1: Personal Information -->
      <div class="form-step active" id="step1">
        <h3><?php echo lft('step1_title'); ?></h3>
        <div class="inline-inputs">
          <input type="text" name="first_name" placeholder="<?php echo lft('first_name'); ?>" required 
                 value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>">
          <input type="text" name="last_name" placeholder="<?php echo lft('last_name'); ?>" required 
                 value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>">
        </div>
        
        <label class="required"><?php echo lft('gender'); ?></label>
        <select name="gender" required>
          <option value=""><?php echo lft('please_select'); ?></option>
          <option value="Male" <?= selected('gender', 'Male') ?>><?php echo lft('male'); ?></option>
          <option value="Female" <?= selected('gender', 'Female') ?>><?php echo lft('female'); ?></option>
        </select>
        
        <label class="required"><?php echo lft('dob'); ?></label>
        <input type="date" name="dob" required value="<?= htmlspecialchars($formData['dob'] ?? '') ?>">
        
        <div class="inline-inputs">
          <input type="tel" name="phone_number" placeholder="<?php echo lft('phone_placeholder'); ?>" required 
                 value="<?= htmlspecialchars($formData['phone_number'] ?? '') ?>">
          <input type="email" name="email" placeholder="<?php echo lft('email_placeholder'); ?>" required 
                 value="<?= htmlspecialchars($formData['email'] ?? '') ?>">
        </div>
        
        <label><?php echo lft('address'); ?></label>
        <input type="text" name="address1" placeholder="<?php echo lft('street_address'); ?>" 
               value="<?= htmlspecialchars($formData['address1'] ?? '') ?>">
        <input type="text" name="address2" placeholder="<?php echo lft('street_address2'); ?>" 
               value="<?= htmlspecialchars($formData['address2'] ?? '') ?>">
        
        <div class="inline-inputs">
          <input type="text" name="city" placeholder="<?php echo lft('city'); ?>" 
                 value="<?= htmlspecialchars($formData['city'] ?? '') ?>">
          <input type="text" name="state" placeholder="<?php echo lft('state'); ?>" 
                 value="<?= htmlspecialchars($formData['state'] ?? '') ?>">
        </div>
        
        <input type="text" name="postal_code" placeholder="<?php echo lft('postal_code'); ?>" 
               value="<?= htmlspecialchars($formData['postal_code'] ?? '') ?>">
        
        <div class="form-buttons">
          <button type="button" class="next-btn" data-next="2">
            <i class="fas fa-arrow-right"></i>
            <?php echo lft('save_next'); ?>
          </button>
        </div>
      </div>

      <!-- Step 2: Program & Application Details -->
      <div class="form-step" id="step2">
        <h3><?php echo lft('step2_title'); ?></h3>

        <label class="required"><?php echo lft('loan_reason'); ?></label>
        <select name="loan_reason[]" class="select2-multiple" multiple required>
          <?php
          $options = lft('loan_reason_options');
          if (is_array($options)) {
            $selectedOptions = isset($formData['loan_reason']) ? explode(',', $formData['loan_reason']) : [];
            foreach ($options as $opt) {
              $selected = in_array($opt, $selectedOptions) ? 'selected' : '';
              echo "<option value=\"$opt\" $selected>$opt</option>";
            }
          }
          ?>
        </select>

        <label class="required"><?php echo lft('masters_program'); ?></label>
        <input type="text" name="masters_program_name" placeholder="<?php echo lft('enter_program'); ?>" required 
               value="<?= htmlspecialchars(loan_field_display_string($formData['masters_program_name'] ?? '')) ?>">

        <label class="required"><?php echo lft('school_name'); ?></label>
        <select name="school_name[]" class="select2-multiple" multiple="multiple" required>
          <option value="Harvard University">Harvard University</option>
          <option value="Stanford University">Stanford University</option>
          <option value="MIT">MIT</option>
          <option value="University of Toronto">University of Toronto</option>
          <option value="University of British Columbia">University of British Columbia</option>
          <option value="University of Oxford">University of Oxford</option>
          <option value="University of Cambridge">University of Cambridge</option>
          <option value="University of Melbourne">University of Melbourne</option>
          <option value="ETH Zurich">ETH Zurich</option>
          <option value="National University of Singapore">National University of Singapore</option>
        </select>

        <label class="required"><?php echo lft('degree_type'); ?></label>
        <select name="degree_type" required>
          <option value=""><?php echo lft('please_select'); ?></option>
          <?php
          $degreeOptions = lft('degree_options');
          if (is_array($degreeOptions)) {
            foreach ($degreeOptions as $degree) {
              $selected = (isset($formData['degree_type']) && $formData['degree_type'] == $degree) ? 'selected' : '';
              echo "<option value=\"$degree\" $selected>$degree</option>";
            }
          }
          ?>
        </select>

        <label class="required"><?php echo lft('application_type'); ?></label>
        <div class="radio-group">
          <label>
            <input type="radio" name="application_type" value="USA" required <?= checked('application_type', 'USA') ?>>
            <?php echo lft('usa_option'); ?>
          </label>
          <label>
            <input type="radio" name="application_type" value="Canada" required <?= checked('application_type', 'Canada') ?>>
            <?php echo lft('canada_option'); ?>
          </label>
          <label>
            <input type="radio" name="application_type" value="Europe" required <?= checked('application_type', 'Europe') ?>>
            <?php echo lft('europe_option'); ?>
          </label>
        </div>

        <label class="required"><?php echo lft('intake'); ?></label>
        <div class="radio-group">
          <label>
            <input type="radio" name="intake" value="Spring" required <?= checked('intake', 'Spring') ?>>
            <?php echo lft('spring_intake'); ?>
          </label>
          <label>
            <input type="radio" name="intake" value="Summer" required <?= checked('intake', 'Summer') ?>>
            <?php echo lft('summer_intake'); ?>
          </label>
          <label>
            <input type="radio" name="intake" value="Fall" required <?= checked('intake', 'Fall') ?>>
            <?php echo lft('fall_intake'); ?>
          </label>
        </div>

        <div class="form-buttons">
          <button type="button" class="prev-btn" data-prev="1">
            <i class="fas fa-arrow-left"></i>
            <?php echo lft('previous'); ?>
          </button>
          <button type="button" class="next-btn" data-next="3">
            <i class="fas fa-arrow-right"></i>
            <?php echo lft('save_next'); ?>
          </button>
        </div>
      </div>

      <!-- Step 3: Citizenship & Reference -->
      <div class="form-step" id="step3">
        <h3><?php echo lft('step3_title'); ?></h3>

        <label class="required"><?php echo lft('citizenship'); ?></label>
        <select name="citizenship_country" required>
          <option value=""><?php echo lft('please_select'); ?></option>
          <option value="United States" <?= selected('citizenship_country', 'United States') ?>>United States</option>
          <option value="Canada" <?= selected('citizenship_country', 'Canada') ?>>Canada</option>
          <option value="India" <?= selected('citizenship_country', 'India') ?>>India</option>
          <option value="United Kingdom" <?= selected('citizenship_country', 'United Kingdom') ?>>United Kingdom</option>
          <option value="Australia" <?= selected('citizenship_country', 'Australia') ?>>Australia</option>
          <option value="France" <?= selected('citizenship_country', 'France') ?>>France</option>
          <option value="Germany" <?= selected('citizenship_country', 'Germany') ?>>Germany</option>
          <option value="China" <?= selected('citizenship_country', 'China') ?>>China</option>
          <option value="Japan" <?= selected('citizenship_country', 'Japan') ?>>Japan</option>
          <option value="Singapore" <?= selected('citizenship_country', 'Singapore') ?>>Singapore</option>
          <option value="Brazil" <?= selected('citizenship_country', 'Brazil') ?>>Brazil</option>
          <option value="Mexico" <?= selected('citizenship_country', 'Mexico') ?>>Mexico</option>
          <option value="South Africa" <?= selected('citizenship_country', 'South Africa') ?>>South Africa</option>
          <option value="Nigeria" <?= selected('citizenship_country', 'Nigeria') ?>>Nigeria</option>
          <option value="Kenya" <?= selected('citizenship_country', 'Kenya') ?>>Kenya</option>
        </select>

        <label class="required"><?php echo lft('has_visa'); ?></label>
        <div class="radio-group">
          <label>
            <input type="radio" name="has_visa" value="YES" required <?= checked('has_visa', 'YES') ?>>
            <?php echo lft('yes'); ?>
          </label>
          <label>
            <input type="radio" name="has_visa" value="NO" required <?= checked('has_visa', 'NO') ?>>
            <?php echo lft('no'); ?>
          </label>
        </div>

        <label class="required"><?php echo lft('has_ssn'); ?></label>
        <div class="radio-group">
          <label>
            <input type="radio" name="has_ssn" value="YES" required <?= checked('has_ssn', 'YES') ?>>
            <?php echo lft('yes'); ?>
          </label>
          <label>
            <input type="radio" name="has_ssn" value="NO" required <?= checked('has_ssn', 'NO') ?>>
            <?php echo lft('no'); ?>
          </label>
        </div>

        <label class="required"><?php echo lft('reference_name'); ?></label>
        <div class="inline-inputs">
          <input type="text" name="ref_first_name" placeholder="<?php echo lft('first_name'); ?>" required 
                 value="<?= htmlspecialchars($formData['ref_first_name'] ?? '') ?>">
          <input type="text" name="ref_last_name" placeholder="<?php echo lft('last_name'); ?>" required 
                 value="<?= htmlspecialchars($formData['ref_last_name'] ?? '') ?>">
        </div>
        
        <label class="required"><?php echo lft('reference_email'); ?></label>
        <input type="email" name="ref_email" required value="<?= htmlspecialchars($formData['ref_email'] ?? '') ?>">
        
        <label class="required"><?php echo lft('reference_phone'); ?></label>
        <input type="tel" name="ref_phone" placeholder="<?php echo lft('phone_placeholder'); ?>" required 
               value="<?= htmlspecialchars($formData['ref_phone'] ?? '') ?>">
        
        <label class="required"><?php echo lft('reference_relationship'); ?></label>
        <input type="text" name="ref_relationship" required 
               value="<?= htmlspecialchars($formData['ref_relationship'] ?? '') ?>">

        <div class="form-buttons">
          <button type="button" class="prev-btn" data-prev="2">
            <i class="fas fa-arrow-left"></i>
            <?php echo lft('previous'); ?>
          </button>
          <button type="button" class="next-btn" data-next="4">
            <i class="fas fa-arrow-right"></i>
            <?php echo lft('save_next'); ?>
          </button>
        </div>
      </div>

      <!-- Step 4: Upload Documents & Certification -->
      <div class="form-step" id="step4">
        <h3><?php echo lft('step4_title'); ?></h3>
        
        <?php
        $fileFields = [
          'acceptance_letter' => lft('acceptance_letter'),
          'bachelor_degree' => lft('bachelor_degree'),
          'bachelor_transcript' => lft('bachelor_transcript'),
          'cv' => lft('cv'),
          'id_document' => lft('id'),
          'valid_passport' => lft('valid_passport'),
          'english_certificate' => lft('english_certificate'),
          'admission_fees' => lft('admission_fees'),
          'scholarship_letter' => lft('scholarship_letter'),
          'bank_statement' => lft('bank_statement')
        ];
        
        foreach ($fileFields as $field => $label) {
          echo "<label class='required'>$label</label>";
          echo "<div class='upload-box'>";
          echo "<input type='file' id='$field' name='$field' data-field='$field' data-user='" . htmlspecialchars($userId) . "' accept='.pdf,.jpg,.jpeg,.png,.doc,.docx'>";
          echo "<i class='fas fa-cloud-upload-alt' style='font-size: 24px; margin-bottom: 10px; color: var(--accent-teal);'></i><br>";
          echo "<span style='color: var(--text); font-weight: 600;'>" . ($current_lang === 'fr' ? 'Cliquez pour télécharger' : 'Click to upload') . "</span><br>";
          echo "<small style='color: var(--text-light);'>" . ($current_lang === 'fr' ? 'PDF, JPG, PNG, DOC (Max 10MB)' : 'PDF, JPG, PNG, DOC (Max 10MB)') . "</small>";
          echo "</div>";
          
          if (!empty($formData[$field])) {
            $href = htmlspecialchars($formData[$field], ENT_QUOTES, 'UTF-8');
            echo "<p style='margin-top: 8px; font-size: 0.9rem; color: #10B981;'>";
            echo "<i class='fas fa-check-circle'></i> " . ($current_lang === 'fr' ? 'Déjà téléchargé :' : 'Previously uploaded:') . " ";
            echo "<a href='" . $href . "' target='_blank' rel='noopener' style='color: var(--accent-teal); text-decoration: none; font-weight: 600;'>";
            echo ($current_lang === 'fr' ? 'Voir le fichier' : 'View File');
            echo "</a></p>";
          }
        }
        ?>

        <hr style="margin: 40px 0; border-top: 2px dashed var(--border);">

        <h3><?php echo lft('certification_title'); ?></h3>
        <p style="margin-bottom: 20px; color: var(--text-light);"><?php echo lft('certification_text'); ?></p>

        <div class="inline-inputs">
          <input type="text" name="applicant_first_name" placeholder="<?php echo lft('first_name'); ?>" required 
                 value="<?= htmlspecialchars($formData['applicant_first_name'] ?? ($formData['first_name'] ?? '')) ?>">
          <input type="text" name="applicant_last_name" placeholder="<?php echo lft('last_name'); ?>" required 
                 value="<?= htmlspecialchars($formData['applicant_last_name'] ?? ($formData['last_name'] ?? '')) ?>">
        </div>

        <label class="required"><?php echo lft('date_signed'); ?></label>
        <input type="date" name="date_signed" required value="<?= htmlspecialchars($formData['date_signed'] ?? '') ?>">

        <div class="form-buttons">
          <button type="button" class="prev-btn" data-prev="3">
            <i class="fas fa-arrow-left"></i>
            <?php echo lft('previous'); ?>
          </button>
          <button type="button" class="submit-btn" onclick="submitFinalStep()">
            <i class="fas fa-paper-plane"></i>
            <?php echo lft('submit_application'); ?>
          </button>
        </div>
      </div>
    </form>
  </div>
</section>

<!-- Include footer.php -->
<?php include 'footer.php'; ?>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// ============================================
// FORM STEP MANAGEMENT
// ============================================
let currentStep = 1;

function showStep(step) {
  // Hide all steps
  document.querySelectorAll('.form-step').forEach(el => {
    el.classList.remove('active');
  });
  
  // Show current step
  const stepElement = document.getElementById(`step${step}`);
  if (stepElement) {
    stepElement.classList.add('active');
    currentStep = step;
  }
  
  // Scroll to top of form
  document.querySelector('.form-container').scrollIntoView({ 
    behavior: 'smooth', 
    block: 'start' 
  });
}

// ============================================
// SMART PROGRESS BAR SYSTEM
// ============================================
function showProgress(title, subtitle) {
  const overlay = document.getElementById('progressOverlay');
  const progressTitle = document.getElementById('progressTitle');
  const progressSubtitle = document.getElementById('progressSubtitle');
  
  if (overlay && progressTitle && progressSubtitle) {
    progressTitle.textContent = title;
    progressSubtitle.textContent = subtitle;
    overlay.style.display = 'flex';
    
    document.getElementById('progressFill').style.width = '0%';
    document.getElementById('progressText').textContent = '0%';
    
    // Reset all steps
    document.querySelectorAll('.progress-step').forEach(step => {
      step.classList.remove('active');
    });
    document.getElementById('step1Progress').classList.add('active');
  }
}

function updateProgress(percent, step, message) {
  const progressFill = document.getElementById('progressFill');
  const progressText = document.getElementById('progressText');
  
  if (progressFill && progressText) {
    progressFill.style.width = `${percent}%`;
    progressText.textContent = message || `${percent}%`;
    
    // Update active step
    document.querySelectorAll('.progress-step').forEach(el => {
      el.classList.remove('active');
    });
    
    if (step >= 1 && step <= 4) {
      const stepElement = document.getElementById(`step${step}Progress`);
      if (stepElement) {
        stepElement.classList.add('active');
      }
    }
  }
}

function hideProgress() {
  const overlay = document.getElementById('progressOverlay');
  if (overlay) {
    overlay.style.display = 'none';
  }
}

// ============================================
// INITIALIZATION
// ============================================
$(document).ready(function() {
  // Initialize Flatpickr for date inputs
  flatpickr("input[type='date']", {
    dateFormat: "Y-m-d",
    altInput: true,
    altFormat: "F j, Y",
    maxDate: "today"
  });

  // Initialize Select2
  $('.select2-multiple').select2({
    placeholder: "<?php echo $current_lang === 'fr' ? 'Sélectionnez des options' : 'Select options'; ?>",
    allowClear: true,
    width: '100%'
  });

  // Button event listeners
  $('.next-btn').on('click', function() {
    const nextStep = parseInt($(this).data('next'));
    saveStep(currentStep, nextStep);
  });

  $('.prev-btn').on('click', function() {
    const prevStep = parseInt($(this).data('prev'));
    showStep(prevStep);
  });
});

// ============================================
// STEP SAVING WITH PROGRESS BAR
// ============================================
function saveStep(currentStepNum, nextStep) {
  const form = $('#applicationForm')[0];
  if (!validateStep(currentStepNum)) {
    alert('<?php echo $current_lang === "fr" ? "Veuillez remplir tous les champs requis" : "Please fill all required fields"; ?>');
    return;
  }

  const fd = new FormData(form);
  fd.append('step', 'step' + currentStepNum);

  const button = $(`[data-next="${nextStep}"]`);
  button.prop('disabled', true);

  showProgress('<?php echo lft('saving_step'); ?>', '<?php echo lft('validating_data'); ?>');
  updateProgress(40, 2, '40%');

  fetch('save_master_loan.php', { method: 'POST', body: fd })
    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
    .then(function (_ref) {
      updateProgress(100, 4, '100%');
      if (!_ref.ok || !_ref.j || _ref.j.status !== 'success') {
        throw new Error((_ref.j && _ref.j.message) ? _ref.j.message : '<?php echo lft('error_saving'); ?>');
      }
      if (currentStepNum === 1) {
        $('#applicationForm').attr('data-loan-autofill-ready', '1');
        if (typeof window.updateMlAutofillGate === 'function') window.updateMlAutofillGate();
      }
      hideProgress();
      showStep(nextStep);
      showNotification('✅ <?php echo lft('success_saved'); ?>');
    })
    .catch(function (err) {
      hideProgress();
      alert('<?php echo lft('error_saving'); ?>: ' + (err && err.message ? err.message : '<?php echo lft('network_error'); ?>'));
    })
    .finally(function () {
      button.prop('disabled', false);
    });
}

// ============================================
// FILE UPLOAD HANDLER (saves via step4)
// ============================================
$(document).ready(function () {
  $('#applicationForm input[type=file]').on('change', function () {
    const input = this;
    const field = $(input).attr('name');
    const file = input.files && input.files[0];
    if (!file || !field) return;

    if (file.size > 10 * 1024 * 1024) {
      alert('<?php echo $current_lang === "fr" ? "Le fichier est trop volumineux (max 10MB)" : "File is too large (max 10MB)"; ?>');
      input.value = '';
      return;
    }

    const fd = new FormData(document.getElementById('applicationForm'));
    fd.set('step', 'step4');

    showProgress('<?php echo lft('uploading'); ?>', '<?php echo lft('preparing_files'); ?>');
    updateProgress(30, 2, '30%');

    fetch('save_master_loan.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (_ref) {
        if (!_ref.ok || !_ref.j || _ref.j.status !== 'success') {
          throw new Error((_ref.j && _ref.j.message) ? _ref.j.message : '<?php echo lft('upload_failed'); ?>');
        }
        updateProgress(100, 4, '100%');
        hideProgress();
        showNotification('✅ <?php echo $current_lang === 'fr' ? 'Document enregistré' : 'Document saved'; ?>');
        setTimeout(function () { window.location.reload(); }, 600);
      })
      .catch(function (err) {
        hideProgress();
        alert((err && err.message) ? err.message : '<?php echo lft('upload_failed'); ?>');
        input.value = '';
      });
  });
});

// ============================================
// FORM VALIDATION
// ============================================
function validateStep(step) {
  let isValid = true;
  const stepElement = document.getElementById(`step${step}`);

  const requiredInputs = stepElement.querySelectorAll('[required]');
  requiredInputs.forEach(input => {
    if (!input.value.trim()) {
      isValid = false;
      input.style.borderColor = '#DC2626';
      input.style.boxShadow = '0 0 0 4px rgba(220, 38, 38, 0.15)';

      setTimeout(() => {
        input.style.borderColor = '';
        input.style.boxShadow = '';
      }, 3000);
    }
  });

  return isValid;
}

// ============================================
// FINAL SUBMISSION
// ============================================
function submitFinalStep() {
  if (!validateStep(4)) {
    alert('<?php echo $current_lang === "fr" ? "Veuillez remplir tous les champs requis" : "Please fill all required fields"; ?>');
    return;
  }

  const form = document.getElementById('applicationForm');
  const fd = new FormData(form);
  fd.append('step', 'step4');

  showProgress('<?php echo lft('submitting'); ?>', '<?php echo lft('validating_data'); ?>');
  updateProgress(20, 1, '20%');

  fetch('save_master_loan.php', { method: 'POST', body: fd })
    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
    .then(function (_ref) {
      if (!_ref.ok || !_ref.j || _ref.j.status !== 'success') {
        throw new Error((_ref.j && _ref.j.message) ? _ref.j.message : '<?php echo lft('error_saving'); ?>');
      }
      updateProgress(70, 3, '70%');
      const uid = document.querySelector('input[name="user_id"]').value;
      return fetch('send_loan_email.php?user_id=' + encodeURIComponent(uid))
        .then(function (er) { return er.json().then(function (ej) { return { er: er, ej: ej }; }); })
        .then(function (mailRes) {
          updateProgress(100, 4, '100%');
          hideProgress();
          if (!mailRes.er.ok || !mailRes.ej || mailRes.ej.status !== 'success') {
            alert('<?php echo lft('success_submitted'); ?>\n<?php echo lft('email_failed'); ?>' + ((mailRes.ej && mailRes.ej.message) || ''));
          } else {
            alert('<?php echo lft('success_submitted'); ?>\n<?php echo lft('email_sent'); ?>');
          }
          window.location.href = 'index.php';
        });
    })
    .catch(function (err) {
      hideProgress();
      alert('<?php echo lft('error_saving'); ?>: ' + (err && err.message ? err.message : '<?php echo lft('network_error'); ?>'));
    });
}

const ML_AUTOFILL_TEXT = <?php echo json_encode([
  'gate' => lft('smart_autofill_gate'),
  'formats' => lft('smart_autofill_formats'),
  'hint' => lft('smart_autofill_hint'),
  'queueEmpty' => lft('smart_autofill_queue_empty'),
  'queueReady' => lft('smart_autofill_queue_ready'),
  'queueCount' => lft('smart_autofill_queue_count'),
  'processing' => lft('smart_autofill_processing'),
  'error' => lft('smart_autofill_error'),
  'complete' => lft('smart_autofill_complete'),
  'uploading' => lft('smart_autofill_uploading'),
  'detailBatch' => lft('smart_autofill_detail_batch'),
  'detailRoute' => lft('smart_autofill_detail_route'),
  'detailDone' => lft('smart_autofill_detail_done'),
], JSON_UNESCAPED_UNICODE); ?>;

const ML_STAGE_META = <?php echo json_encode([
  ['id' => 'queue', 'label' => lft('smart_autofill_stage_queue'), 'short' => $current_lang === 'fr' ? 'Liste' : 'Queue'],
  ['id' => 'batch', 'label' => lft('smart_autofill_stage_batch'), 'short' => 'AI'],
  ['id' => 'route', 'label' => lft('smart_autofill_stage_route'), 'short' => $current_lang === 'fr' ? 'Env.' : 'Save'],
  ['id' => 'done', 'label' => lft('smart_autofill_stage_done'), 'short' => 'OK'],
], JSON_UNESCAPED_UNICODE); ?>;

(function () {
  const progressWrap = document.getElementById('mlSmartAutofillProgressWrap');
  const progressText = document.getElementById('mlSmartAutofillProgressText');
  const progressLabel = document.getElementById('mlSmartAutofillProgressLabel');
  const progressSubtext = document.getElementById('mlSmartAutofillProgressSubtext');
  const stagePillsEl = document.getElementById('mlSmartAutofillStagePills');
  const panelsEl = document.getElementById('mlSmartAutofillPanels');
  const resultsEl = document.getElementById('mlSmartAutofillResults');
  const warningsWrapEl = document.getElementById('mlSmartAutofillWarningsWrap');
  const warningsEl = document.getElementById('mlSmartAutofillWarnings');

  if (!progressWrap || !progressText || !progressLabel || !progressSubtext || !stagePillsEl || !panelsEl || !resultsEl || !warningsWrapEl || !warningsEl) {
    return;
  }

  let pendingFiles = [];
  let isProcessing = false;
  let batchToken = '';

  function fileKey(file) {
    return [file.name, file.size, file.lastModified].join('::');
  }

  function gateOk() {
    return typeof window.jQuery !== 'undefined' && $('#applicationForm').attr('data-loan-autofill-ready') === '1';
  }

  function setField(name, val) {
    if (val == null || val === '') return;
    const el = document.querySelector('#applicationForm [name="' + name + '"]');
    if (!el || el.type === 'file') return;
    el.value = val;
    if (window.jQuery && $(el).hasClass('select2-hidden-accessible')) {
      $(el).val(val).trigger('change');
    } else {
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  function applyFields(fields) {
    Object.keys(fields || {}).forEach(function (k) { setField(k, fields[k]); });
  }

  function resetProgress() {
    progressWrap.className = 'smart-autofill-progress-panel';
    progressText.textContent = '\u2014';
    progressLabel.textContent = ML_AUTOFILL_TEXT.processing;
    progressSubtext.textContent = ML_AUTOFILL_TEXT.hint;
    stagePillsEl.innerHTML = '';
  }

  function setStatus(type, msg) {
    var el = document.getElementById('mlSmartAutofillStatus');
    if (!el) return;
    el.style.display = 'block';
    el.style.background = type === 'error' ? '#fdeaea' : type === 'success' ? '#e8f5e9' : type === 'warning' ? '#fff8e6' : '#e7f1ff';
    el.textContent = msg;
  }

  function renderStagePills(activeId, kind) {
    stagePillsEl.innerHTML = '';
    var activeIndex = ML_STAGE_META.findIndex(function (s) { return s.id === activeId; });
    ML_STAGE_META.forEach(function (stage, index) {
      var pill = document.createElement('span');
      pill.className = 'smart-autofill-stage-pill';
      pill.textContent = stage.label;
      if (activeIndex > -1 && index < activeIndex) pill.classList.add('is-done');
      else if (stage.id === activeId) pill.classList.add(kind === 'danger' ? 'is-error' : 'is-active');
      if (kind === 'success' && activeIndex > -1 && index <= activeIndex) {
        pill.classList.remove('is-active');
        pill.classList.add('is-done');
      }
      if (kind === 'warning' && stage.id === activeId) {
        pill.classList.remove('is-active');
        pill.classList.add('is-error');
      }
      stagePillsEl.appendChild(pill);
    });
  }

  function setStage(stageId, message, kind, subtext) {
    kind = kind || 'info';
    subtext = subtext || '';
    progressWrap.className = 'smart-autofill-progress-panel active';
    progressWrap.classList.remove('is-success', 'is-warning', 'is-danger');
    if (kind === 'success') progressWrap.classList.add('is-success');
    else if (kind === 'warning') progressWrap.classList.add('is-warning');
    else if (kind === 'danger') progressWrap.classList.add('is-danger');
    var stage = ML_STAGE_META.find(function (item) { return item.id === stageId; });
    progressText.textContent = stage ? stage.short : 'AI';
    progressLabel.textContent = message;
    progressSubtext.textContent = subtext || (stage ? stage.label : '');
    renderStagePills(stageId, kind);
    if (kind === 'danger') setStatus('error', message);
    else if (kind === 'success') setStatus('success', message);
    else if (kind === 'warning') setStatus('warning', message);
    else setStatus('info', message);
    try { progressWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e) {}
  }

  function clearPanels() {
    resultsEl.innerHTML = '';
    warningsEl.innerHTML = '';
    warningsWrapEl.style.display = 'none';
    panelsEl.style.display = 'none';
  }

  function renderPanels(documents, warnings) {
    resultsEl.innerHTML = '';
    warningsEl.innerHTML = '';
    var hasDocs = Array.isArray(documents) && documents.length;
    var hasWarn = Array.isArray(warnings) && warnings.length;
    if (!hasDocs && !hasWarn) return;
    if (hasDocs) {
      documents.forEach(function (doc) {
        var li = document.createElement('li');
        var title = document.createElement('strong');
        var detail = document.createElement('small');
        title.textContent = (doc.original_name || '') + ' \u2192 ' + (doc.field_label || doc.field || '?');
        detail.textContent = doc.summary || '';
        li.appendChild(title);
        if (detail.textContent) li.appendChild(detail);
        resultsEl.appendChild(li);
      });
    }
    if (hasWarn) {
      warnings.forEach(function (message) {
        var li = document.createElement('li');
        var title = document.createElement('strong');
        title.textContent = 'Warning';
        li.appendChild(title);
        if (message) {
          var detail = document.createElement('small');
          detail.textContent = message;
          li.appendChild(detail);
        }
        warningsEl.appendChild(li);
      });
      warningsWrapEl.style.display = 'block';
    }
    panelsEl.style.display = 'block';
  }

  function updateHelp() {
    const help = document.getElementById('mlSmartAutofillHelp');
    if (!help) return;
    if (gateOk()) {
      help.innerHTML = ML_AUTOFILL_TEXT.formats + '<br>' + ML_AUTOFILL_TEXT.hint;
    } else {
      help.innerHTML = ML_AUTOFILL_TEXT.gate + '<br>' + ML_AUTOFILL_TEXT.formats + '<br>' + ML_AUTOFILL_TEXT.hint;
    }
  }

  window.updateMlAutofillGate = function () {
    const ready = gateOk() && !isProcessing;
    const trig = document.getElementById('mlSmartAutofillTrigger');
    const start = document.getElementById('mlSmartAutofillStart');
    if (trig) trig.disabled = !ready || isProcessing;
    if (start) start.disabled = !ready || isProcessing || pendingFiles.length === 0;
    updateHelp();
  };

  function addPendingFiles(files) {
    var known = {};
    pendingFiles.forEach(function (f) { known[fileKey(f)] = 1; });
    Array.prototype.forEach.call(files || [], function (f) {
      if (!f || known[fileKey(f)]) return;
      pendingFiles.push(f);
      known[fileKey(f)] = 1;
    });
  }

  function renderQueue() {
    const ul = document.getElementById('mlSmartAutofillQueue');
    const hint = document.getElementById('mlSmartAutofillQueueHint');
    if (!ul) return;
    ul.innerHTML = '';
    if (!pendingFiles.length) {
      if (hint) {
        hint.style.display = 'block';
        hint.textContent = ML_AUTOFILL_TEXT.queueEmpty;
      }
      return;
    }
    if (hint) {
      hint.style.display = 'block';
      hint.textContent = pendingFiles.length + ' ' + ML_AUTOFILL_TEXT.queueCount + '. ' + ML_AUTOFILL_TEXT.queueReady;
    }
    pendingFiles.forEach(function (file) {
      var li = document.createElement('li');
      li.className = 'smart-autofill-queue-item';
      var name = document.createElement('span');
      name.className = 'smart-autofill-queue-name';
      name.textContent = file.name;
      var rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'smart-autofill-remove';
      rm.textContent = '\u00d7';
      rm.disabled = isProcessing;
      rm.dataset.fileKey = fileKey(file);
      li.appendChild(name);
      li.appendChild(rm);
      ul.appendChild(li);
    });
  }

  document.getElementById('mlSmartAutofillQueue').addEventListener('click', function (event) {
    var btn = event.target.closest('.smart-autofill-remove');
    if (!btn || isProcessing) return;
    var key = btn.dataset.fileKey;
    pendingFiles = pendingFiles.filter(function (f) { return fileKey(f) !== key; });
    renderQueue();
    window.updateMlAutofillGate();
  });

  function uploadOne(field, file) {
    return new Promise(function (resolve, reject) {
      var fd = new FormData();
      fd.append('file', file);
      fd.append('field', field);
      fd.append('user_id', document.querySelector('input[name="user_id"]').value);
      fd.append('skip_ai_validation', '1');
      fd.append('smart_autofill_batch_token', batchToken);
      fd.append('lang', document.documentElement.lang || 'en');
      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'upload_master_loan_file.php');
      xhr.onload = function () {
        try {
          var j = JSON.parse(xhr.responseText || '{}');
          if (xhr.status >= 200 && xhr.status < 300 && j.status === 'success') resolve(j);
          else reject(new Error(j.message || 'Upload failed'));
        } catch (e) { reject(e); }
      };
      xhr.onerror = function () { reject(new Error('Network')); };
      xhr.send(fd);
    });
  }

  function markLoanAutofillFile(field, relPath) {
    if (!relPath) return;
    var inp = document.getElementById(field);
    if (!inp) return;
    inp.value = '';
    var box = inp.closest('.upload-box');
    if (!box || !box.parentNode) return;
    var next = box.nextSibling;
    while (next && next.nodeType === 1 && next.classList && next.classList.contains('ml-autofill-file-note')) {
      var rm = next;
      next = next.nextSibling;
      rm.remove();
    }
    var p = document.createElement('p');
    p.className = 'ml-autofill-file-note';
    p.style.cssText = 'margin-top:8px;font-size:0.9rem;color:#10B981;';
    var icon = document.createElement('i');
    icon.className = 'fas fa-check-circle';
    p.appendChild(icon);
    p.appendChild(document.createTextNode(' '));
    var a = document.createElement('a');
    a.href = 'download.php?f=' + encodeURIComponent(btoa(unescape(encodeURIComponent(relPath)))) + '&inline=1';
    a.target = '_blank';
    a.rel = 'noopener';
    a.style.cssText = 'color: var(--accent-teal); text-decoration: none; font-weight: 600;';
    a.textContent = <?php echo json_encode($current_lang === 'fr' ? 'Voir le fichier' : 'View file', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
    p.appendChild(a);
    box.parentNode.insertBefore(p, box.nextSibling);
  }

  document.addEventListener('DOMContentLoaded', function () {
    var trig = document.getElementById('mlSmartAutofillTrigger');
    var input = document.getElementById('mlSmartAutofillInput');
    var start = document.getElementById('mlSmartAutofillStart');
    if (trig && input) trig.addEventListener('click', function () { input.click(); });
    if (input) {
      input.addEventListener('change', function () {
        var files = Array.from(input.files || []);
        input.value = '';
        if (!files.length) return;
        addPendingFiles(files);
        clearPanels();
        resetProgress();
        setStage('queue', ML_AUTOFILL_TEXT.queueReady, 'info', pendingFiles.length + ' ' + ML_AUTOFILL_TEXT.queueCount);
        renderQueue();
        window.updateMlAutofillGate();
      });
    }
    if (start) {
      start.addEventListener('click', function () {
        if (!pendingFiles.length || isProcessing || !gateOk()) return;
        isProcessing = true;
        clearPanels();
        window.updateMlAutofillGate();
        resetProgress();
        setStage('batch', ML_AUTOFILL_TEXT.processing, 'info', ML_AUTOFILL_TEXT.detailBatch);

        var uid = document.querySelector('input[name="user_id"]').value;
        var fd = new FormData();
        var filesSnapshot = pendingFiles.slice();
        filesSnapshot.forEach(function (f) { fd.append('documents[]', f); });
        fd.append('application_id', uid);
        fd.append('lang', document.documentElement.lang || 'en');

        fetch('master_loan_ai_autofill.php', { method: 'POST', body: fd })
          .then(function (r) { return r.json().then(function (data) { return { res: r, data: data }; }); })
          .then(function (_ref) {
            if (!_ref.res.ok || !_ref.data || _ref.data.status !== 'success') {
              throw new Error((_ref.data && _ref.data.message) ? _ref.data.message : ML_AUTOFILL_TEXT.error);
            }
            batchToken = _ref.data.upload_token || '';
            applyFields(_ref.data.fields || {});

            setStage('route', ML_AUTOFILL_TEXT.uploading, 'info', ML_AUTOFILL_TEXT.detailRoute);

            var docs = Array.isArray(_ref.data.documents) ? _ref.data.documents : [];
            var byField = new Map();
            docs.forEach(function (d) {
              if (!d || !d.field) return;
              var cur = byField.get(d.field);
              if (!cur || (Number(d.confidence) || 0) > (Number(cur.confidence) || 0)) byField.set(d.field, d);
            });
            var uploads = [];
            byField.forEach(function (meta, field) {
              var file = filesSnapshot[Number(meta.client_index)];
              if (file) {
                uploads.push(
                  uploadOne(field, file).then(function (j) {
                    if (j && j.file_path) markLoanAutofillFile(field, j.file_path);
                    return j;
                  })
                );
              }
            });
            return Promise.all(uploads).then(function () { return _ref.data; });
          })
          .then(function (data) {
            var warnings = Array.isArray(data.warnings) ? data.warnings.slice() : [];
            renderPanels(data.documents || [], warnings);
            pendingFiles = [];
            renderQueue();
            var endKind = warnings.length ? 'warning' : 'success';
            setStage('done', ML_AUTOFILL_TEXT.complete, endKind, ML_AUTOFILL_TEXT.detailDone);
          })
          .catch(function (e) {
            resetProgress();
            setStage('batch', e.message || ML_AUTOFILL_TEXT.error, 'danger', ML_AUTOFILL_TEXT.error);
          })
          .finally(function () {
            isProcessing = false;
            window.updateMlAutofillGate();
          });
      });
    }
    clearPanels();
    resetProgress();
    renderQueue();
    window.updateMlAutofillGate();
  });
})();

// ============================================
// NOTIFICATION FUNCTION
// ============================================
function showNotification(message) {
  const notification = document.createElement('div');
  notification.className = 'notification';
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg, var(--primary-navy), var(--secondary-blue));
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
`;
document.head.appendChild(style);
</script>

</body>
</html>