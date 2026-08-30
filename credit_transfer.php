<?php
session_start();
require_once __DIR__ . '/includes/brand_logo.php';
$parrotBrandLogoHref = scholarsync_brand_logo_href(__DIR__);

// Resolve application user_id: explicit ?id= resumes that application; otherwise start a new draft id.
if (isset($_GET['id']) && trim((string)$_GET['id']) !== '') {
    $userId = trim((string)$_GET['id']);
} else {
    $userId = 'credit-' . time() . '-' . rand(1000, 9999);
}
$_SESSION['credit_user_id'] = $userId;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/credit_transfer_programs.php';
/* No INSERT on page load — avoids empty DB rows. Row is created on final submit or first smart-upload (see upload_credit_transfer_file.php). */
$creditPrefillRow = null;
$creditApplicationComplete = false;
$pfStmt = $conn->prepare('SELECT * FROM credit_transfer_applications WHERE user_id = ? LIMIT 1');
if ($pfStmt) {
    $pfStmt->bind_param('s', $userId);
    $pfStmt->execute();
    $pfRes = $pfStmt->get_result();
    if ($pfRes && ($r = $pfRes->fetch_assoc())) {
        $creditPrefillRow = $r;
        $filesOk = !empty(trim((string)($r['current_degree'] ?? '')))
            && !empty(trim((string)($r['current_transcripts'] ?? '')))
            && !empty(trim((string)($r['passport_or_id'] ?? '')))
            && !empty(trim((string)($r['academic_cv'] ?? '')))
            && !empty(trim((string)($r['payment_proof'] ?? '')));
        $creditApplicationComplete = $filesOk;
    }
    $pfStmt->close();
}

function credit_transfer_has_saved_progress(?array $r): bool
{
    if (!$r) {
        return false;
    }
    foreach (['email', 'first_name', 'last_name', 'university', 'proposed_program'] as $k) {
        if (!empty(trim((string)($r[$k] ?? '')))) {
            return true;
        }
    }
    foreach (['current_degree', 'current_transcripts', 'passport_or_id', 'academic_cv', 'payment_proof'] as $k) {
        if (!empty(trim((string)($r[$k] ?? '')))) {
            return true;
        }
    }
    $edu = trim((string)($r['education_levels'] ?? ''));
    if ($edu !== '' && $edu !== '[]' && strcasecmp($edu, 'null') !== 0) {
        return true;
    }
    $cert = trim((string)($r['certification_levels'] ?? ''));
    if ($cert !== '' && $cert !== '[]' && strcasecmp($cert, 'null') !== 0) {
        return true;
    }
    return false;
}

$creditShowResumeBanner = $creditPrefillRow && credit_transfer_has_saved_progress($creditPrefillRow);
$creditShowSubmittedFreshBanner = isset($_GET['fresh']) && (string)$_GET['fresh'] === '1';

// ============================================
// INCLUDE HEADER FOR LANGUAGE SWITCHING LOGIC
// ============================================
include 'header.php';

// ============================================
// TRANSLATIONS FOR CREDIT TRANSFER PAGE
// ============================================

$credit_translations = [
    'en' => [
        'page_title' => 'Credit Transfer & Certification | ScholarSync Global',
        'page_description' => 'Apply for credit transfer and certification with our partner universities worldwide.',
        'form_title' => 'Credit Transfer & Certification Application',
        'form_subtitle' => 'One form: academic choices, optional Smart AI, documents, and your profile — submit when ready.',
        'single_page_intro' => 'Optional: use Smart AI to read your documents and fill matching fields. You can complete everything manually if you prefer.',
        'form_hero_single' => 'Credit transfer application',
        'form_hero_single_lead' => 'Fill academic information, attach documents, add your personal details, then submit once. Nothing is saved to our database until you submit (or you use Smart AI file routing, which creates your application record for those files only).',
        
        // Personal Information Section
        'personal_info' => 'Personal Information',
        'student_name' => 'Student Name',
        'first_name' => 'First Name',
        'middle_name' => 'Middle Name',
        'last_name' => 'Last Name',
        'birth_date' => 'Birth Date',
        'gender' => 'Gender',
        'contact_address' => 'Contact Address',
        'street_address' => 'Street Address',
        'address_line_2' => 'Apartment, Suite, Building (Optional)',
        'city' => 'City',
        'state' => 'State/Province',
        'postal_code' => 'Postal/ZIP Code',
        'email_address' => 'Email Address',
        'contact_numbers' => 'Contact Numbers',
        'mobile_number' => 'Mobile Number',
        'phone_number' => 'Phone Number',
        'work_number' => 'Work Number',
        'current_company' => 'Current Company/Organization',
        
        // Academic Information Section
        'academic_info' => 'Academic Information',
        'current_education_level' => 'Current Level of Education',
        'desired_certification' => 'Desired Certification Level',
        'current_program' => 'Current Program',
        'select_university' => 'Select University',
        'proposed_program' => 'Proposed Program',
        
        // Required Documents Section
        'required_documents' => 'Required Documents',
        'degree_certificate' => 'Current Degree Certificate',
        'academic_transcripts' => 'Current Academic Transcripts',
        'passport_id' => 'Valid Passport or National ID',
        'academic_cv' => 'Academic CV/Resume',
        'payment_proof' => 'Payment Proof',
        'additional_comments' => 'Additional Comments',
        
        // Buttons and Labels
        'continue_to_academic' => 'Continue to documents & AI',
        'back_to_personal' => 'Back to program choice',
        'submit_application' => 'Submit application',
        'step1_label' => 'Program & goals',
        'step2_label' => 'Documents & profile',
        'step2_intro' => 'Upload your documents, run Smart AI to fill your details, then we submit and email your confirmation.',
        'auto_submitting' => 'Submitting your application and sending confirmation email…',
        'auto_submit_review' => 'Review your application in the portal later if anything still needs updating.',
        'manual_submit_hint' => 'If you prefer not to use AI, fill all fields and tap Submit.',
        'submit_success_title' => 'Application submitted successfully',
        'submit_success_body' => 'Your details and documents are saved. A confirmation email is sent when a real email address was on the form. You can start another application below.',
        'submit_success_dismiss' => 'Dismiss',
        
        // Hints and Tips
        'legal_first_name' => 'Legal first name',
        'family_name' => 'Family name',
        'email_hint' => 'We\'ll send application updates to this email',
        'education_hint' => 'Select all that apply to your current education status',
        'program_hint' => 'Select university first, then type to filter available programs',
        'upafa_catalogue' => 'Download UPAFA 2025 program catalogue (PDF)',
        'comments_hint' => 'Optional: Share your motivation, special circumstances, or questions',
        
        // Progress Messages
        'processing_application' => 'Processing Your Application',
        'please_wait' => 'Please wait while we submit your information...',
        'saving_info' => 'Saving Personal Information',
        'saving_academic' => 'Saving academic information',
        'saving_details' => 'Please wait while we save your details...',
        'academic_saved_progress' => 'Academic choices saved successfully.',
        'submitting_application' => 'Submitting Your Application',
        'may_take_moment' => 'This may take a moment...',
        
        // Progress Steps
        'validating_data' => 'Validating Data',
        'uploading_files' => 'Uploading Files',
        'saving_information' => 'Saving Information',
        'finalizing' => 'Finalizing',
        
        // File Upload
        'click_to_upload' => 'Click to upload',
        'or_drag_drop' => 'or drag and drop files here',
        'max_10mb' => 'PDF, JPG, PNG, DOC (Max 10MB)',
        'accepted_formats' => 'Accepted: .pdf, .jpg, .jpeg, .png, .doc, .docx',
        
        // Gender Options
        'gender_male' => 'Male',
        'gender_female' => 'Female',
        'gender_other' => 'Other',
        
        // Education Levels
        'edu_high_school' => 'High School Certificate',
        'edu_ordinary_diploma' => 'Ordinary Diploma (2 years)',
        'edu_advanced_diploma' => 'Advanced Diploma (3 years)',
        'edu_bachelor_no_degree' => 'Bachelor (No Degree)',
        'edu_bachelor_lower' => 'Bachelor (Lower Division)',
        'edu_bachelor_upper' => 'Bachelor (Upper Division)',
        'edu_masters_lower' => 'Masters (Lower Division)',
        'edu_masters_upper' => 'Masters (Upper Division)',
        
        // Certification Levels
        'cert_bachelor' => 'Bachelor',
        'cert_masters' => 'Masters',
        'cert_phd' => 'PhD',

        'smart_autofill_title' => 'Smart AI autofill',
        'smart_autofill_desc' => 'Upload passport, CV, transcripts, degree, or other supporting documents. AI extracts your details and routes each file into the matching attachment fields on this form (same storage as a manual upload).',
        'smart_autofill_existing_note' => 'Nothing is stored in a separate place—recognized files go straight into the file fields below.',
        'smart_autofill_button' => 'Add documents',
        'smart_autofill_start' => 'Start analysis',
        'smart_autofill_gate' => 'Choose university and proposed program first, then add documents for AI.',
        'smart_autofill_formats' => 'Supported: PDF, DOCX, JPG, JPEG, PNG, WEBP.',
        'smart_autofill_hint' => 'Tip: upload passport, CV, and your latest academic documents together for best results.',
        'smart_autofill_queue_empty' => 'No documents selected yet. Add one or more files, then start the analysis when you are ready.',
        'smart_autofill_processing' => 'Analyzing documents…',
        'smart_autofill_ready' => 'Ready to analyze your documents.',
        'smart_autofill_error' => 'Analysis failed. Please try again.',
        'smart_autofill_queue_count' => 'file(s) queued',
        'smart_autofill_complete' => 'Analysis complete. Submitting your application now—you can add any missing details later.',
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
    ],
    
    'fr' => [
        'page_title' => 'Transfert de Crédits & Certification | ScholarSync Global',
        'page_description' => 'Postulez pour le transfert de crédits et la certification avec nos universités partenaires.',
        'form_title' => 'Demande de Transfert de Crédits & Certification',
        'form_subtitle' => 'Un seul formulaire : choix académiques, IA facultative, documents et profil — soumettez lorsque vous êtes prêt.',
        'single_page_intro' => 'Facultatif : utilisez l’IA pour analyser vos documents et remplir les champs correspondants. Vous pouvez tout remplir manuellement.',
        'form_hero_single' => 'Demande de transfert de crédits',
        'form_hero_single_lead' => 'Renseignez le parcours académique, joignez les documents, complétez votre profil, puis soumettez une seule fois. Aucune donnée n’est enregistrée en base tant que vous n’avez pas soumis (sauf si vous utilisez le routage des fichiers par l’IA, qui crée alors l’enregistrement pour ces fichiers).',
        
        // Personal Information Section
        'personal_info' => 'Informations Personnelles',
        'student_name' => 'Nom de l\'Étudiant',
        'first_name' => 'Prénom',
        'middle_name' => 'Deuxième Prénom',
        'last_name' => 'Nom de Famille',
        'birth_date' => 'Date de Naissance',
        'gender' => 'Genre',
        'contact_address' => 'Adresse de Contact',
        'street_address' => 'Adresse',
        'address_line_2' => 'Appartement, Suite, Bâtiment (Optionnel)',
        'city' => 'Ville',
        'state' => 'État/Province',
        'postal_code' => 'Code Postal',
        'email_address' => 'Adresse Email',
        'contact_numbers' => 'Numéros de Contact',
        'mobile_number' => 'Numéro Mobile',
        'phone_number' => 'Numéro de Téléphone',
        'work_number' => 'Numéro Professionnel',
        'current_company' => 'Entreprise/Organisation Actuelle',
        
        // Academic Information Section
        'academic_info' => 'Informations Académiques',
        'current_education_level' => 'Niveau d\'Éducation Actuel',
        'desired_certification' => 'Niveau de Certification Désiré',
        'current_program' => 'Programme Actuel',
        'select_university' => 'Sélectionner l\'Université',
        'proposed_program' => 'Programme Proposé',
        
        // Required Documents Section
        'required_documents' => 'Documents Requis',
        'degree_certificate' => 'Certificat de Diplôme Actuel',
        'academic_transcripts' => 'Relevés de Notes Actuels',
        'passport_id' => 'Passeport Valide ou Carte d\'Identité Nationale',
        'academic_cv' => 'CV Académique',
        'payment_proof' => 'Preuve de Paiement',
        'additional_comments' => 'Commentaires Additionnels',
        
        // Buttons and Labels
        'continue_to_academic' => 'Continuer vers documents & IA',
        'back_to_personal' => 'Retour au choix du programme',
        'submit_application' => 'Soumettre la demande',
        'step1_label' => 'Programme & objectifs',
        'step2_label' => 'Documents & profil',
        'step2_intro' => 'Téléversez vos documents, lancez l’IA pour remplir vos informations, puis nous soumettons la demande et envoyons l’email de confirmation.',
        'auto_submitting' => 'Soumission de votre demande et envoi de l’email de confirmation…',
        'auto_submit_review' => 'Vous pourrez vérifier ou compléter les informations manquantes plus tard si nécessaire.',
        'manual_submit_hint' => 'Sans IA : remplissez tous les champs puis appuyez sur Soumettre.',
        'submit_success_title' => 'Demande envoyée avec succès',
        'submit_success_body' => 'Vos informations et documents sont enregistrés. Un email de confirmation est envoyé lorsqu’une adresse courriel valide était indiquée. Vous pouvez commencer une nouvelle demande ci-dessous.',
        'submit_success_dismiss' => 'Fermer',
        
        // Hints and Tips
        'legal_first_name' => 'Prénom légal',
        'family_name' => 'Nom de famille',
        'email_hint' => 'Nous enverrons les mises à jour de la demande à cet email',
        'education_hint' => 'Sélectionnez tout ce qui s\'applique à votre statut d\'éducation actuel',
        'program_hint' => 'Sélectionnez d\'abord l\'université, puis tapez pour filtrer les programmes disponibles',
        'upafa_catalogue' => 'Télécharger le catalogue des programmes UPAFA 2025 (PDF)',
        'comments_hint' => 'Optionnel : Partagez votre motivation, circonstances spéciales ou questions',
        
        // Progress Messages
        'processing_application' => 'Traitement de Votre Demande',
        'please_wait' => 'Veuillez patienter pendant que nous soumettons vos informations...',
        'saving_info' => 'Sauvegarde des Informations Personnelles',
        'saving_academic' => 'Sauvegarde des informations académiques',
        'saving_details' => 'Veuillez patienter pendant que nous sauvegardons vos détails...',
        'academic_saved_progress' => 'Choix académiques enregistrés.',
        'submitting_application' => 'Soumission de Votre Demande',
        'may_take_moment' => 'Cela peut prendre un moment...',
        
        // Progress Steps
        'validating_data' => 'Validation des Données',
        'uploading_files' => 'Téléchargement des Fichiers',
        'saving_information' => 'Sauvegarde des Informations',
        'finalizing' => 'Finalisation',
        
        // File Upload
        'click_to_upload' => 'Cliquez pour télécharger',
        'or_drag_drop' => 'ou glissez-déposez les fichiers ici',
        'max_10mb' => 'PDF, JPG, PNG, DOC (Max 10MB)',
        'accepted_formats' => 'Accepté : .pdf, .jpg, .jpeg, .png, .doc, .docx',
        
        // Gender Options
        'gender_male' => 'Homme',
        'gender_female' => 'Femme',
        'gender_other' => 'Autre',
        
        // Education Levels
        'edu_high_school' => 'Certificat d\'Études Secondaires',
        'edu_ordinary_diploma' => 'Diplôme Ordinaire (2 ans)',
        'edu_advanced_diploma' => 'Diplôme Avancé (3 ans)',
        'edu_bachelor_no_degree' => 'Bachelor (Sans Diplôme)',
        'edu_bachelor_lower' => 'Bachelor (Division Inférieure)',
        'edu_bachelor_upper' => 'Bachelor (Division Supérieure)',
        'edu_masters_lower' => 'Masters (Division Inférieure)',
        'edu_masters_upper' => 'Masters (Division Supérieure)',
        
        // Certification Levels
        'cert_bachelor' => 'Bachelor',
        'cert_masters' => 'Masters',
        'cert_phd' => 'Doctorat',

        'smart_autofill_title' => 'Remplissage automatique IA',
        'smart_autofill_desc' => 'Téléversez passeport, CV, relevés de notes, diplôme ou autres pièces. L\'IA extrait vos informations et classe chaque fichier dans le champ correspondant (même stockage qu\'un envoi manuel).',
        'smart_autofill_existing_note' => 'Aucun stockage séparé : les fichiers reconnus vont directement dans les champs de pièces jointes.',
        'smart_autofill_button' => 'Ajouter des documents',
        'smart_autofill_start' => 'Lancer l\'analyse',
        'smart_autofill_gate' => 'Choisissez d’abord l’université et le programme proposé, puis ajoutez les documents pour l’IA.',
        'smart_autofill_formats' => 'Formats : PDF, DOCX, JPG, JPEG, PNG, WEBP.',
        'smart_autofill_hint' => 'Astuce : téléversez passeport, CV et documents académiques récents ensemble.',
        'smart_autofill_queue_empty' => 'Aucun document sélectionné. Ajoutez des fichiers puis lancez l\'analyse.',
        'smart_autofill_processing' => 'Analyse des documents…',
        'smart_autofill_ready' => 'Prêt à analyser vos documents.',
        'smart_autofill_error' => 'L\'analyse a échoué. Réessayez.',
        'smart_autofill_queue_count' => 'fichier(s) en file',
        'smart_autofill_complete' => 'Analyse terminée. Envoi automatique de la demande — vous pourrez compléter les détails manquants plus tard.',
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
    ]
];

// Function to get credit transfer translation
function ct($key) {
    global $credit_translations, $current_lang;
    return isset($credit_translations[$current_lang][$key]) ? $credit_translations[$current_lang][$key] : $key;
}

$creditHasDbRow = $creditPrefillRow !== null;
$pf = $creditPrefillRow ?? [];
$eduChecked = json_decode($pf['education_levels'] ?? '[]', true);
if (!is_array($eduChecked)) {
    $eduChecked = [];
}
$certChecked = json_decode($pf['certification_levels'] ?? '[]', true);
if (!is_array($certChecked)) {
    $certChecked = [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="description" content="<?php echo ct('page_description'); ?>">
  <title><?php echo ct('page_title'); ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* ===== Brand color theme ===== */
    :root {
      --navy-blue: #427431;
      --secondary-blue: #3661B9;
      --dark-blue: #2f5a26;
      --gold: #E21D1E;
      --white: #FFFFFF;
      --light-gray: #f8f9fa;
      --medium-gray: #e9ecef;
      --dark-gray: #6c757d;
      --success: #28a745;
      --danger: #dc3545;
      --warning: #ffc107;
      --shadow: 0 10px 30px rgba(1, 47, 107, 0.1);
      --transition: all 0.3s ease;
    }

    * { 
      box-sizing: border-box; 
      margin: 0; 
      padding: 0; 
    }

    body { 
      font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif; 
      background: linear-gradient(135deg, var(--light-gray) 0%, var(--medium-gray) 100%);
      color: var(--dark-blue);
      min-height: 100vh;
      padding: 20px;
      line-height: 1.6;
    }

    /* ===== HEADER & LOGO ===== */
    .header {
      text-align: center;
      margin-bottom: 30px;
      padding: 20px 0;
    }

    .logo-container {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 15px;
      margin-bottom: 15px;
    }

    .logo-container .brand-logo-img {
      height: 56px;
      width: auto;
      max-width: 200px;
      object-fit: contain;
      display: block;
    }

    .logo-text {
      font-size: 28px;
      font-weight: 700;
      background: linear-gradient(90deg, var(--navy-blue), var(--dark-blue));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      letter-spacing: 1px;
    }

    .logo-subtext {
      color: var(--dark-gray);
      font-size: 14px;
      font-weight: 500;
      letter-spacing: 2px;
      text-transform: uppercase;
      margin-top: 5px;
    }

    /* ===== FORM CONTAINER ===== */
    .form-container { 
      background: var(--white); 
      max-width: 1000px; 
      margin: 0 auto; 
      padding: 40px; 
      border-radius: 20px; 
      box-shadow: var(--shadow);
      position: relative;
      overflow: hidden;
    }

    .smart-autofill-card {
      border: 1px solid var(--medium-gray);
      border-radius: 14px;
      padding: 1.25rem 1.5rem;
      background: #fafbfd;
    }
    .smart-autofill-pill {
      display: inline-block;
      font-size: 0.65rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      padding: 0.2rem 0.45rem;
      border-radius: 999px;
      background: var(--secondary-blue);
      color: #fff;
      vertical-align: middle;
      margin-right: 0.35rem;
    }
    .smart-autofill-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: flex-end; }
    .smart-autofill-queue {
      border: 1px dashed var(--medium-gray);
      border-radius: 10px;
      padding: 0.75rem 1rem;
      min-height: 72px;
      background: #fff;
    }
    .smart-autofill-queue-list { list-style: none; margin: 0; padding: 0; }
    .smart-autofill-remove {
      border: none; background: transparent; color: var(--danger); cursor: pointer; font-size: 0.85rem;
    }

    .smart-autofill-queue-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 10px 12px;
      border: 1px solid var(--medium-gray);
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
      animation: ctSmartAutofillPanelPulse 2.4s ease-in-out infinite;
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
      animation: ctSmartAutofillRipple 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
      pointer-events: none;
    }
    .smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-orb::after {
      content: "";
      position: absolute;
      inset: -10px;
      border-radius: 50%;
      border: 2px solid rgba(168, 85, 247, 0.35);
      animation: ctSmartAutofillRipple 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
      animation-delay: 0.55s;
      pointer-events: none;
    }
    .smart-autofill-orb-ring {
      position: absolute;
      inset: 0;
      border-radius: 50%;
      background: conic-gradient(from 0deg, #2563eb, #22d3ee, #a855f7, #6366f1, #3b82f6, #2563eb);
      animation: ctSmartAutofillSpin 0.85s linear infinite;
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
      animation: ctSmartAutofillCorePulse 1.35s ease-in-out infinite;
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
    .smart-autofill-progress-copy {
      flex: 1 1 auto;
      min-width: 0;
    }
    .smart-autofill-progress-copy strong {
      display: block;
      font-size: 15px;
      color: #0f172a;
    }
    .smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-progress-copy strong {
      font-size: 16px;
      font-weight: 700;
      background: linear-gradient(90deg, #1e40af, #6366f1, #0e7490);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      animation: ctSmartAutofillTitleShimmer 2.5s ease-in-out infinite;
    }
    .smart-autofill-progress-copy small {
      display: block;
      margin-top: 4px;
      color: #64748b;
      line-height: 1.5;
    }
    .smart-autofill-stage-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 12px;
    }
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
    .smart-autofill-stage-pill.is-done::before {
      background: #16a34a;
    }
    .smart-autofill-stage-pill.is-error {
      border-color: #fca5a5;
      background: #fef2f2;
      color: #991b1b;
    }
    .smart-autofill-stage-pill.is-error::before {
      background: #dc2626;
    }
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

    @keyframes ctSmartAutofillSpin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    @keyframes ctSmartAutofillPanelPulse {
      0%, 100% {
        box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.14), 0 0 24px rgba(59, 130, 246, 0.28), 0 14px 36px rgba(79, 70, 229, 0.18);
      }
      50% {
        box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.22), 0 0 38px rgba(59, 130, 246, 0.48), 0 18px 44px rgba(79, 70, 229, 0.28);
      }
    }
    @keyframes ctSmartAutofillRipple {
      0% { transform: scale(0.88); opacity: 0.85; }
      70% { opacity: 0.15; }
      100% { transform: scale(1.22); opacity: 0; }
    }
    @keyframes ctSmartAutofillCorePulse {
      0%, 100% {
        transform: scale(1);
        box-shadow: inset 0 0 0 1px rgba(96, 165, 250, 0.35), inset 0 -2px 8px rgba(59, 130, 246, 0.08);
      }
      50% {
        transform: scale(1.04);
        box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.55), inset 0 -2px 10px rgba(37, 99, 235, 0.12);
      }
    }
    @keyframes ctSmartAutofillTitleShimmer {
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

    .form-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 5px;
      background: linear-gradient(90deg, var(--navy-blue), var(--gold));
    }

    .credit-resume-banner {
      background: #e8f4ea;
      border: 1px solid var(--navy-blue);
      color: #2f5a26;
      padding: 14px 18px;
      border-radius: 12px;
      margin-bottom: 24px;
      font-size: 14px;
      line-height: 1.5;
    }
    .credit-resume-banner--complete {
      background: #fff8e6;
      border-color: #c4a000;
      color: #5c4a00;
    }

    .credit-success-banner {
      background: linear-gradient(135deg, #ecfdf5 0%, #e0f2fe 100%);
      border: 1px solid #10b981;
      color: #0f172a;
      padding: 16px 18px;
      border-radius: 14px;
      margin-bottom: 24px;
      font-size: 14px;
      line-height: 1.55;
      box-shadow: 0 6px 24px rgba(16, 185, 129, 0.12);
    }
    .credit-success-banner strong {
      display: block;
      font-size: 1.05rem;
      color: #047857;
      margin-bottom: 0.35rem;
    }
    .credit-success-banner .credit-success-actions {
      margin-top: 12px;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
    }
    .credit-success-banner button {
      cursor: pointer;
      border: 0;
      border-radius: 10px;
      padding: 8px 16px;
      font-size: 13px;
      font-weight: 600;
      background: var(--navy-blue);
      color: #fff;
    }
    .credit-success-banner button:hover {
      filter: brightness(1.05);
    }

    .ct-wizard-hero {
      margin-bottom: 1.35rem;
      padding: 1.25rem 1.35rem;
      border-radius: 16px;
      background: linear-gradient(135deg, rgba(66, 116, 49, 0.1) 0%, rgba(54, 97, 185, 0.08) 100%);
      border: 1px solid rgba(66, 116, 49, 0.22);
      text-align: center;
    }
    .ct-wizard-hero h2 {
      font-size: 1.2rem;
      color: var(--dark-blue);
      margin: 0 0 0.4rem;
      font-weight: 700;
    }
    .ct-wizard-hero p { margin: 0; color: var(--dark-gray); font-size: 0.95rem; line-height: 1.5; }
    .ct-program-card {
      border: 1px solid var(--medium-gray);
      border-radius: 18px;
      padding: 1.35rem 1.35rem 1.5rem;
      margin-bottom: 1.25rem;
      background: linear-gradient(180deg, #fff 0%, #f9fafb 100%);
      box-shadow: 0 8px 32px rgba(47, 90, 38, 0.07);
    }
    .ct-step2-banner {
      margin: 0 0 1.25rem;
      padding: 1rem 1.2rem;
      border-radius: 14px;
      background: linear-gradient(135deg, #f0f7ff 0%, #f5f0ff 100%);
      border: 1px solid #dbeafe;
      font-size: 0.95rem;
      color: #334155;
      line-height: 1.55;
    }
    .ct-manual-hint {
      font-size: 0.88rem;
      color: var(--dark-gray);
      margin: 1rem 0 0;
      text-align: center;
    }

    /* ===== STEP INDICATOR ===== */
    .step-indicator {
      display: flex;
      justify-content: space-between;
      margin-bottom: 40px;
      position: relative;
      padding: 0 20px;
    }

    .step-indicator::before {
      content: '';
      position: absolute;
      top: 15px;
      left: 10%;
      right: 10%;
      height: 3px;
      background: var(--medium-gray);
      z-index: 1;
    }

    .step {
      position: relative;
      z-index: 2;
      text-align: center;
      flex: 1;
    }

    .step-circle {
      width: 35px;
      height: 35px;
      background: var(--white);
      border: 3px solid var(--medium-gray);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 10px;
      font-weight: 600;
      color: var(--dark-gray);
      transition: var(--transition);
    }

    .step.active .step-circle {
      background: var(--navy-blue);
      border-color: var(--navy-blue);
      color: var(--white);
      transform: scale(1.1);
    }

    .step.completed .step-circle {
      background: var(--gold);
      border-color: var(--gold);
      color: var(--white);
    }

    .step-label {
      font-size: 14px;
      font-weight: 600;
      color: var(--dark-gray);
    }

    .step.active .step-label {
      color: var(--navy-blue);
    }

    /* ===== FORM SECTIONS ===== */
    .form-step { 
      display: none; 
      animation: fadeIn 0.5s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .form-step.active { 
      display: block; 
    }

    .form-section {
      margin-bottom: 30px;
    }

    .section-title {
      color: var(--navy-blue);
      font-size: 20px;
      font-weight: 600;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid var(--gold);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .section-title i {
      color: var(--gold);
    }

    /* ===== FORM ELEMENTS ===== */
    .form-group {
      margin-bottom: 25px;
    }

    label { 
      display: block; 
      font-weight: 600; 
      margin-bottom: 8px; 
      color: var(--dark-blue);
      font-size: 15px;
    }

    label.required::after {
      content: ' *';
      color: var(--danger);
    }

    input, select, textarea { 
      width: 100%; 
      padding: 14px 18px; 
      border: 2px solid var(--medium-gray); 
      border-radius: 10px; 
      font-size: 16px; 
      transition: var(--transition);
      background: var(--white);
      color: var(--dark-blue);
    }

    input:focus, select:focus, textarea:focus {
      outline: none;
      border-color: var(--navy-blue);
      box-shadow: 0 0 0 3px rgba(1, 47, 107, 0.1);
    }

    textarea { 
      resize: vertical; 
      min-height: 120px;
      font-family: inherit;
    }

    .inline-inputs { 
      display: flex; 
      flex-wrap: wrap; 
      gap: 15px; 
    }

    .inline-inputs > * { 
      flex: 1 1 calc(33.333% - 15px); 
      min-width: 150px;
    }

    /* ===== CHECKBOX GRID ===== */
    .checkbox-grid { 
      display: grid; 
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); 
      gap: 12px; 
      margin-top: 10px; 
    }

    .checkbox-grid label { 
      display: flex; 
      align-items: flex-start; 
      gap: 12px; 
      font-weight: normal; 
      line-height: 1.45;
      cursor: pointer;
      padding: 14px 16px;
      border-radius: 12px;
      border: 1px solid var(--medium-gray);
      background: #fff;
      transition: var(--transition);
    }

    .checkbox-grid label:hover {
      background: rgba(66, 116, 49, 0.06);
      border-color: rgba(66, 116, 49, 0.35);
      transform: translateY(-1px);
    }

    .checkbox-grid label:has(input:checked) {
      border-color: var(--navy-blue);
      background: rgba(66, 116, 49, 0.08);
      box-shadow: 0 0 0 2px rgba(66, 116, 49, 0.12);
    }

    .checkbox-grid input[type="checkbox"] {
      width: 20px;
      height: 20px;
      accent-color: var(--navy-blue);
    }

    /* ===== FIXED FILE UPLOAD - CRITICAL FIX ===== */
    .file-upload-container {
      margin-top: 10px;
    }

    .file-upload-wrapper {
      position: relative;
      border: 3px dashed var(--medium-gray);
      border-radius: 12px;
      padding: 25px 20px;
      text-align: center;
      color: var(--dark-gray);
      transition: var(--transition);
      background: var(--light-gray);
      cursor: pointer;
      min-height: 150px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .file-upload-wrapper:hover {
      border-color: var(--navy-blue);
      background: rgba(1, 47, 107, 0.02);
    }

    .file-upload-wrapper.dragover {
      border-color: var(--gold);
      background: rgba(242, 166, 90, 0.05);
    }

    .file-upload-wrapper.has-file {
      border-color: var(--success);
      background: rgba(40, 167, 69, 0.05);
    }

    .file-upload-wrapper input[type="file"] {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      opacity: 0;
      cursor: pointer;
      z-index: 10;
    }

    .upload-icon {
      font-size: 40px;
      color: var(--navy-blue);
      margin-bottom: 10px;
      transition: var(--transition);
    }

    .file-upload-wrapper:hover .upload-icon {
      color: var(--gold);
      transform: scale(1.1);
    }

    .upload-text {
      font-weight: 600;
      margin-bottom: 5px;
      font-size: 16px;
      color: var(--dark-blue);
    }

    .upload-hint {
      font-size: 13px;
      color: var(--dark-gray);
      margin-bottom: 15px;
    }

    .file-preview {
      margin-top: 15px;
      font-size: 14px;
      font-weight: 500;
      padding: 10px 15px;
      border-radius: 8px;
      background: rgba(40, 167, 69, 0.1);
      color: var(--success);
      width: 100%;
      text-align: center;
      display: none;
    }

    .file-preview i {
      margin-right: 8px;
    }

    .file-error {
      background: rgba(220, 53, 69, 0.1);
      color: var(--danger);
    }

    .file-size {
      font-size: 12px;
      color: var(--dark-gray);
      margin-left: 5px;
    }

    .file-requirements {
      font-size: 12px;
      color: var(--dark-gray);
      margin-top: 5px;
      font-style: italic;
    }

    /* ===== BUTTONS ===== */
    .form-buttons { 
      margin-top: 40px; 
      display: flex; 
      gap: 15px; 
      justify-content: space-between;
      padding-top: 25px;
      border-top: 1px solid var(--medium-gray);
    }

    .btn {
      padding: 16px 30px;
      border: none;
      border-radius: 10px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      min-width: 150px;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--navy-blue), var(--secondary-blue));
      color: var(--white);
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, var(--dark-blue), var(--navy-blue));
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(1, 47, 107, 0.3);
    }

    .btn-secondary {
      background: var(--white);
      color: var(--navy-blue);
      border: 2px solid var(--navy-blue);
    }

    .btn-secondary:hover {
      background: rgba(1, 47, 107, 0.05);
      transform: translateY(-2px);
    }

    .btn-gold {
      background: linear-gradient(135deg, var(--gold), #e6953e);
      color: var(--white);
    }

    .btn-gold:hover {
      background: linear-gradient(135deg, #e6953e, var(--gold));
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(242, 166, 90, 0.3);
    }

    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none !important;
      box-shadow: none !important;
    }

    /* ===== SMART PROGRESS OVERLAY ===== */
    .progress-overlay { 
      position: fixed; 
      top:0; 
      left:0; 
      width:100%; 
      height:100%; 
      background: rgba(255, 255, 255, 0.95); 
      z-index:9999; 
      display:none; 
      align-items:center; 
      justify-content:center; 
      flex-direction: column;
    }

    .progress-container {
      width: 90%;
      max-width: 500px;
      background: var(--white);
      border-radius: 20px;
      padding: 40px;
      box-shadow: var(--shadow);
      text-align: center;
    }

    .progress-icon {
      font-size: 60px;
      color: var(--navy-blue);
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
      color: var(--navy-blue);
      margin-bottom: 10px;
    }

    .progress-subtitle {
      color: var(--dark-gray);
      margin-bottom: 30px;
    }

    .progress-bar {
      height: 10px;
      background: var(--medium-gray);
      border-radius: 5px;
      overflow: hidden;
      margin-bottom: 20px;
    }

    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--navy-blue), var(--gold));
      width: 0%;
      transition: width 0.5s ease;
      border-radius: 5px;
    }

    .progress-text {
      font-size: 14px;
      color: var(--dark-gray);
      font-weight: 500;
    }

    .progress-steps {
      display: flex;
      justify-content: space-between;
      margin-top: 20px;
      font-size: 12px;
      color: var(--dark-gray);
    }

    .progress-step {
      position: relative;
      text-align: center;
      flex: 1;
    }

    .progress-step.active {
      color: var(--navy-blue);
      font-weight: 600;
    }

    /* ===== HINTS & VALIDATION ===== */
    .hint { 
      font-size: 13px; 
      color: var(--dark-gray); 
      margin-top: 8px; 
      font-style: italic;
    }
    #upafaCatalogueLink {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      margin-top: 8px;
      font-style: normal;
      font-weight: 600;
      color: var(--navy-blue);
      text-decoration: none;
    }
    #upafaCatalogueLink:hover { text-decoration: underline; }

    .error-message {
      color: var(--danger);
      font-size: 14px;
      margin-top: 5px;
      display: none;
    }

    .success-message {
      color: var(--success);
      font-size: 14px;
      margin-top: 5px;
      display: none;
    }

    /* ===== DRAG & DROP STYLES ===== */
    .drag-drop-hint {
      font-size: 12px;
      color: var(--navy-blue);
      margin-top: 8px;
      font-weight: 500;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
      .form-container { 
        padding: 25px; 
      }
      
      .inline-inputs { 
        flex-direction: column; 
      }
      
      .inline-inputs > * { 
        width: 100%; 
      }
      
      .checkbox-grid { 
        grid-template-columns: 1fr; 
      }
      
      .form-buttons { 
        flex-direction: column; 
      }
      
      .btn { 
        width: 100%; 
      }
      
      .step-indicator {
        padding: 0 10px;
      }
      
      .step-label {
        font-size: 12px;
      }
      
      .file-upload-wrapper {
        min-height: 120px;
        padding: 20px 15px;
      }
    }

    @media (max-width: 480px) {
      body { 
        padding: 10px; 
      }
      
      .form-container { 
        padding: 20px; 
      }
      
      .logo-text {
        font-size: 24px;
      }
      
      .upload-icon {
        font-size: 32px;
      }
      
      .upload-text {
        font-size: 14px;
      }
    }
  </style>
</head>
<body>

<!-- SMART PROGRESS OVERLAY -->
<div id="progressOverlay" class="progress-overlay">
  <div class="progress-container">
    <div class="progress-icon">
      <i class="fas fa-graduation-cap"></i>
    </div>
    <h3 class="progress-title" id="progressTitle"><?php echo ct('processing_application'); ?></h3>
    <p class="progress-subtitle" id="progressSubtitle"><?php echo ct('please_wait'); ?></p>
    
    <div class="progress-bar">
      <div class="progress-fill" id="progressFill"></div>
    </div>
    <div class="progress-text" id="progressText">0% Complete</div>
    
    <div class="progress-steps">
      <div class="progress-step" id="step1Progress"><?php echo ct('validating_data'); ?></div>
      <div class="progress-step" id="step2Progress"><?php echo ct('uploading_files'); ?></div>
      <div class="progress-step" id="step3Progress"><?php echo ct('saving_information'); ?></div>
      <div class="progress-step" id="step4Progress"><?php echo ct('finalizing'); ?></div>
    </div>
  </div>
</div>

<div class="header">
  <div class="logo-container">
    <img class="brand-logo-img" src="<?php echo htmlspecialchars($parrotBrandLogoHref); ?>" alt="ScholarSync Global" width="180" height="56" decoding="async" />
    <div>
      <div class="logo-text">ScholarSync Global</div>
      <div class="logo-subtext"><?php echo ct('form_title'); ?></div>
    </div>
  </div>
  <p style="color: var(--dark-gray); max-width: 600px; margin: 0 auto; font-size: 15px;">
    <?php echo ct('form_subtitle'); ?>
  </p>
</div>

<div class="form-container">
  <?php if ($creditShowSubmittedFreshBanner): ?>
  <div class="credit-success-banner" id="creditSubmittedFreshBanner" role="status">
    <strong><?php echo htmlspecialchars(ct('submit_success_title'), ENT_QUOTES, 'UTF-8'); ?></strong>
    <?php echo htmlspecialchars(ct('submit_success_body'), ENT_QUOTES, 'UTF-8'); ?>
    <div class="credit-success-actions">
      <button type="button" onclick="creditDismissSubmittedBanner()"><?php echo htmlspecialchars(ct('submit_success_dismiss'), ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($creditShowResumeBanner): ?>
  <div class="credit-resume-banner" role="status">
    <?php if ($creditApplicationComplete): ?>
      <?php echo $current_lang === 'fr'
        ? 'Demande récupérée. Tous les documents sont enregistrés — vous pouvez ajuster vos informations ou téléverser de nouvelles versions, puis soumettre à nouveau.'
        : 'Application retrieved. All documents are on file — you may update your information or upload new versions, then resubmit.'; ?>
    <?php else: ?>
      <?php echo $current_lang === 'fr'
        ? 'Reprise de la demande : vos informations sont chargées. Vérifiez tout puis soumettez.'
        : 'Resuming your application: your details are loaded. Review everything, then submit.'; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <form id="creditForm" enctype="multipart/form-data" data-save="save_credit_transfer.php" data-app-complete="<?= $creditApplicationComplete ? '1' : '0' ?>" data-has-db-row="<?= $creditPrefillRow ? '1' : '0' ?>">
    <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">

      <div class="ct-wizard-hero">
        <h2><i class="fas fa-file-signature" style="color:var(--navy-blue);"></i> <?php echo htmlspecialchars(ct('form_hero_single'), ENT_QUOTES, 'UTF-8'); ?></h2>
        <p><?php echo htmlspecialchars(ct('form_hero_single_lead'), ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
      <div class="ct-program-card">
      <div class="section-title ct-section-head">
        <i class="fas fa-graduation-cap"></i>
        <?php echo ct('academic_info'); ?>
      </div>
      <div class="form-section">
        <div class="form-group">
          <label class="required"><?php echo ct('current_education_level'); ?></label>
          <div class="checkbox-grid">
            <label><input type="checkbox" name="edu_level[]" value="High School Certificate"<?= in_array('High School Certificate', $eduChecked, true) ? ' checked' : '' ?>> <?php echo ct('edu_high_school'); ?></label>
            <label><input type="checkbox" name="edu_level[]" value="Ordinary Diploma of 2 years"<?= in_array('Ordinary Diploma of 2 years', $eduChecked, true) ? ' checked' : '' ?>> <?php echo ct('edu_ordinary_diploma'); ?></label>
            <label><input type="checkbox" name="edu_level[]" value="Advanced Diploma of 3 years"<?= in_array('Advanced Diploma of 3 years', $eduChecked, true) ? ' checked' : '' ?>> <?php echo ct('edu_advanced_diploma'); ?></label>
            <label><input type="checkbox" name="edu_level[]" value="Bachelor without Degree"<?= in_array('Bachelor without Degree', $eduChecked, true) ? ' checked' : '' ?>> <?php echo ct('edu_bachelor_no_degree'); ?></label>
            <label><input type="checkbox" name="edu_level[]" value="Bachelor with Lower Division"<?= in_array('Bachelor with Lower Division', $eduChecked, true) ? ' checked' : '' ?>> <?php echo ct('edu_bachelor_lower'); ?></label>
            <label><input type="checkbox" name="edu_level[]" value="Bachelor with Upper Division"<?= in_array('Bachelor with Upper Division', $eduChecked, true) ? ' checked' : '' ?>> <?php echo ct('edu_bachelor_upper'); ?></label>
            <label><input type="checkbox" name="edu_level[]" value="Masters with Lower Division"<?= in_array('Masters with Lower Division', $eduChecked, true) ? ' checked' : '' ?>> <?php echo ct('edu_masters_lower'); ?></label>
            <label><input type="checkbox" name="edu_level[]" value="Masters with Upper Division"<?= in_array('Masters with Upper Division', $eduChecked, true) ? ' checked' : '' ?>> <?php echo ct('edu_masters_upper'); ?></label>
          </div>
          <div class="hint"><?php echo ct('education_hint'); ?></div>
        </div>
        <div class="form-group">
          <label class="required"><?php echo ct('desired_certification'); ?></label>
          <div class="checkbox-grid">
            <label><input type="checkbox" name="cert_level[]" value="Bachelor"<?= in_array('Bachelor', $certChecked, true) ? ' checked' : '' ?>> <?php echo ct('cert_bachelor'); ?></label>
            <label><input type="checkbox" name="cert_level[]" value="Masters"<?= in_array('Masters', $certChecked, true) ? ' checked' : '' ?>> <?php echo ct('cert_masters'); ?></label>
            <label><input type="checkbox" name="cert_level[]" value="PhD"<?= in_array('PhD', $certChecked, true) ? ' checked' : '' ?>> <?php echo ct('cert_phd'); ?></label>
          </div>
        </div>
        <?php $uniSel = $pf['university'] ?? ''; ?>
        <div class="form-group">
          <label class="required"><?php echo ct('current_program'); ?></label>
          <input type="text" name="current_program" placeholder="<?php echo $current_lang === 'fr' ? 'ex. Bachelor of Business Administration' : 'e.g., Bachelor of Business Administration'; ?>" required value="<?= htmlspecialchars($pf['current_program'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="required"><?php echo ct('select_university'); ?></label>
          <select name="university" id="university" required>
            <option value="" disabled<?= $uniSel === '' ? ' selected' : '' ?>><?php echo $current_lang === 'fr' ? 'Choisissez votre université' : 'Choose your university'; ?></option>
            <option value="UPAFA"<?= $uniSel === 'UPAFA' ? ' selected' : '' ?>>Université Africaine Franco-Arabe (UPAFA)</option>
            <option value="DPHU"<?= $uniSel === 'DPHU' ? ' selected' : '' ?>>Distant Production house University (DPHU)</option>
            <option value="IST"<?= $uniSel === 'IST' ? ' selected' : '' ?>>Institut Supérieur de Burkina Faso (IST)</option>
            <option value="USOJ"<?= $uniSel === 'USOJ' ? ' selected' : '' ?>>University of Saint Joseph Mbarara (USOJ)</option>
          </select>
          <?php $upafaCatalogueRel = pcvc_credit_transfer_catalogue_path('UPAFA'); ?>
          <a id="upafaCatalogueLink" href="<?= htmlspecialchars((string) $upafaCatalogueRel, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"<?= $uniSel === 'UPAFA' ? '' : ' style="display:none;"' ?>>
            <i class="fas fa-file-pdf"></i> <?php echo htmlspecialchars(ct('upafa_catalogue'), ENT_QUOTES, 'UTF-8'); ?>
          </a>
        </div>
        <div class="form-group">
          <label class="required"><?php echo ct('proposed_program'); ?></label>
          <input type="text" name="proposed_program" id="proposed_program" list="programOptions" placeholder="<?php echo $current_lang === 'fr' ? 'Commencez à taper pour rechercher des programmes...' : 'Start typing to search programs...'; ?>" required autocomplete="off" value="<?= htmlspecialchars($pf['proposed_program'] ?? '') ?>">
          <datalist id="programOptions"></datalist>
          <div class="hint">
            <i class="fas fa-lightbulb"></i> <?php echo ct('program_hint'); ?>
          </div>
        </div>
      </div>
      </div>

      <p class="ct-step2-banner"><?php echo htmlspecialchars(ct('single_page_intro'), ENT_QUOTES, 'UTF-8'); ?></p>
      <div class="smart-autofill-card" style="margin-top:0;margin-bottom:1.25rem;">
        <div style="display:flex;flex-wrap:wrap;gap:1rem;justify-content:space-between;align-items:flex-start;">
          <div style="flex:1;min-width:220px;">
            <span class="smart-autofill-pill">AI</span>
            <h6 style="font-weight:600;margin-top:0.5rem;margin-bottom:0.5rem;"><?php echo htmlspecialchars(ct('smart_autofill_title'), ENT_QUOTES, 'UTF-8'); ?></h6>
            <p style="color:var(--dark-gray);font-size:0.9rem;margin-bottom:0.5rem;"><?php echo htmlspecialchars(ct('smart_autofill_desc'), ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="hint"><?php echo htmlspecialchars(ct('smart_autofill_existing_note'), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
          <div style="text-align:right;min-width:200px;">
            <div class="smart-autofill-actions" style="justify-content:flex-end;">
              <button type="button" class="btn btn-secondary" id="ctSmartAutofillTrigger" disabled><?php echo htmlspecialchars(ct('smart_autofill_button'), ENT_QUOTES, 'UTF-8'); ?></button>
              <button type="button" class="btn btn-primary" id="ctSmartAutofillStart" disabled><?php echo htmlspecialchars(ct('smart_autofill_start'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
            <input type="file" id="ctSmartAutofillInput" class="d-none" multiple accept=".pdf,.docx,.jpg,.jpeg,.png,.webp" style="display:none;">
            <div id="ctSmartAutofillHelp" class="hint" style="margin-top:0.5rem;">
              <?php echo htmlspecialchars(ct('smart_autofill_gate'), ENT_QUOTES, 'UTF-8'); ?><br>
              <?php echo htmlspecialchars(ct('smart_autofill_formats'), ENT_QUOTES, 'UTF-8'); ?><br>
              <?php echo htmlspecialchars(ct('smart_autofill_hint'), ENT_QUOTES, 'UTF-8'); ?>
            </div>
          </div>
        </div>
        <div id="ctSmartAutofillStatus" class="alert d-none mt-3 mb-0 py-2" role="status" style="display:none;margin-top:0.75rem;"></div>
        <div class="smart-autofill-queue mt-3">
          <div class="fw-semibold small mb-1"><?php echo htmlspecialchars(ct('smart_autofill_queue_title'), ENT_QUOTES, 'UTF-8'); ?></div>
          <div id="ctSmartAutofillQueueHint" class="form-text small"><?php echo htmlspecialchars(ct('smart_autofill_queue_empty'), ENT_QUOTES, 'UTF-8'); ?></div>
          <ul id="ctSmartAutofillQueue" class="smart-autofill-queue-list mt-2"></ul>
        </div>
        <div id="ctSmartAutofillProgressWrap" class="smart-autofill-progress-panel" aria-live="polite">
          <div class="smart-autofill-orb">
            <div class="smart-autofill-orb-ring"></div>
            <div class="smart-autofill-orb-core" id="ctSmartAutofillProgressText">—</div>
          </div>
          <div class="smart-autofill-progress-copy">
            <strong id="ctSmartAutofillProgressLabel"><?php echo htmlspecialchars(ct('smart_autofill_processing'), ENT_QUOTES, 'UTF-8'); ?></strong>
            <small id="ctSmartAutofillProgressSubtext"><?php echo htmlspecialchars(ct('smart_autofill_hint'), ENT_QUOTES, 'UTF-8'); ?></small>
            <div id="ctSmartAutofillStagePills" class="smart-autofill-stage-pills"></div>
          </div>
        </div>
        <div id="ctSmartAutofillPanels" class="mt-3" style="display:none;">
          <div class="fw-semibold small mb-2 text-secondary"><?php echo htmlspecialchars(ct('smart_autofill_results_title'), ENT_QUOTES, 'UTF-8'); ?></div>
          <ul id="ctSmartAutofillResults" class="smart-autofill-results"></ul>
          <div id="ctSmartAutofillWarningsWrap" class="mt-3" style="display:none;">
            <div class="fw-semibold small mb-2 text-secondary"><?php echo htmlspecialchars(ct('smart_autofill_warnings_title'), ENT_QUOTES, 'UTF-8'); ?></div>
            <ul id="ctSmartAutofillWarnings" class="smart-autofill-results"></ul>
          </div>
        </div>
      </div>
        <div class="section-title mt-5">
          <i class="fas fa-file-upload"></i>
          <?php echo ct('required_documents'); ?>
        </div>

      <div class="form-section">
        <!-- FIXED FILE UPLOAD COMPONENTS -->
        <?php
        $hasDeg = !empty(trim((string)($pf['current_degree'] ?? '')));
        $hasTr = !empty(trim((string)($pf['current_transcripts'] ?? '')));
        $hasPass = !empty(trim((string)($pf['passport_or_id'] ?? '')));
        $hasCv = !empty(trim((string)($pf['academic_cv'] ?? '')));
        $hasPay = !empty(trim((string)($pf['payment_proof'] ?? '')));
        ?>
        <div class="form-group">
          <label class="required"><?php echo ct('degree_certificate'); ?></label>
          <div class="file-upload-container">
            <div class="file-upload-wrapper<?= $hasDeg ? ' has-file' : '' ?>" id="degreeUploadWrapper">
              <input type="file" name="current_degree" id="current_degree" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"<?= $hasDeg ? '' : ' required' ?> data-server-file="<?= $hasDeg ? '1' : '0' ?>">
              <div class="upload-icon">
                <i class="fas fa-file-certificate"></i>
              </div>
              <div class="upload-text"><?php echo ct('click_to_upload'); ?> <?php echo ct('degree_certificate'); ?></div>
              <div class="upload-hint"><?php echo ct('max_10mb'); ?></div>
              <div class="drag-drop-hint"><?php echo ct('or_drag_drop'); ?></div>
              <div class="file-preview" id="degreePreview"<?= $hasDeg ? ' style="display:block"' : '' ?>><?php if ($hasDeg): ?><i class="fas fa-check-circle"></i> <a href="<?= htmlspecialchars($pf['current_degree']) ?>" target="_blank" rel="noopener"><?php echo $current_lang === 'fr' ? 'Document déjà enregistré' : 'Document already on file'; ?></a><?php endif; ?></div>
            </div>
            <div class="file-requirements"><?php echo ct('accepted_formats'); ?></div>
          </div>
        </div>

        <div class="form-group">
          <label class="required"><?php echo ct('academic_transcripts'); ?></label>
          <div class="file-upload-container">
            <div class="file-upload-wrapper<?= $hasTr ? ' has-file' : '' ?>" id="transcriptUploadWrapper">
              <input type="file" name="current_transcripts" id="current_transcripts" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"<?= $hasTr ? '' : ' required' ?> data-server-file="<?= $hasTr ? '1' : '0' ?>">
              <div class="upload-icon">
                <i class="fas fa-file-alt"></i>
              </div>
              <div class="upload-text"><?php echo ct('click_to_upload'); ?> <?php echo ct('academic_transcripts'); ?></div>
              <div class="upload-hint"><?php echo ct('max_10mb'); ?></div>
              <div class="drag-drop-hint"><?php echo ct('or_drag_drop'); ?></div>
              <div class="file-preview" id="transcriptPreview"<?= $hasTr ? ' style="display:block"' : '' ?>><?php if ($hasTr): ?><i class="fas fa-check-circle"></i> <a href="<?= htmlspecialchars($pf['current_transcripts']) ?>" target="_blank" rel="noopener"><?php echo $current_lang === 'fr' ? 'Document déjà enregistré' : 'Document already on file'; ?></a><?php endif; ?></div>
            </div>
            <div class="file-requirements"><?php echo ct('accepted_formats'); ?></div>
          </div>
        </div>

        <div class="form-group">
          <label class="required"><?php echo ct('passport_id'); ?></label>
          <div class="file-upload-container">
            <div class="file-upload-wrapper<?= $hasPass ? ' has-file' : '' ?>" id="passportUploadWrapper">
              <input type="file" name="passport_or_id" id="passport_or_id" accept=".pdf,.jpg,.jpeg,.png"<?= $hasPass ? '' : ' required' ?> data-server-file="<?= $hasPass ? '1' : '0' ?>">
              <div class="upload-icon">
                <i class="fas fa-id-card"></i>
              </div>
              <div class="upload-text"><?php echo ct('click_to_upload'); ?> <?php echo $current_lang === 'fr' ? 'Document d\'identité' : 'ID Document'; ?></div>
              <div class="upload-hint"><?php echo ct('max_10mb'); ?></div>
              <div class="drag-drop-hint"><?php echo ct('or_drag_drop'); ?></div>
              <div class="file-preview" id="passportPreview"<?= $hasPass ? ' style="display:block"' : '' ?>><?php if ($hasPass): ?><i class="fas fa-check-circle"></i> <a href="<?= htmlspecialchars($pf['passport_or_id']) ?>" target="_blank" rel="noopener"><?php echo $current_lang === 'fr' ? 'Document déjà enregistré' : 'Document already on file'; ?></a><?php endif; ?></div>
            </div>
            <div class="file-requirements"><?php echo $current_lang === 'fr' ? 'Accepté : .pdf, .jpg, .jpeg, .png' : 'Accepted: .pdf, .jpg, .jpeg, .png'; ?></div>
          </div>
        </div>

        <div class="form-group">
          <label class="required"><?php echo ct('academic_cv'); ?></label>
          <div class="file-upload-container">
            <div class="file-upload-wrapper<?= $hasCv ? ' has-file' : '' ?>" id="cvUploadWrapper">
              <input type="file" name="academic_cv" id="academic_cv" accept=".pdf,.doc,.docx"<?= $hasCv ? '' : ' required' ?> data-server-file="<?= $hasCv ? '1' : '0' ?>">
              <div class="upload-icon">
                <i class="fas fa-file-contract"></i>
              </div>
              <div class="upload-text"><?php echo ct('click_to_upload'); ?> <?php echo ct('academic_cv'); ?></div>
              <div class="upload-hint"><?php echo ct('max_10mb'); ?></div>
              <div class="drag-drop-hint"><?php echo ct('or_drag_drop'); ?></div>
              <div class="file-preview" id="cvPreview"<?= $hasCv ? ' style="display:block"' : '' ?>><?php if ($hasCv): ?><i class="fas fa-check-circle"></i> <a href="<?= htmlspecialchars($pf['academic_cv']) ?>" target="_blank" rel="noopener"><?php echo $current_lang === 'fr' ? 'Document déjà enregistré' : 'Document already on file'; ?></a><?php endif; ?></div>
            </div>
            <div class="file-requirements"><?php echo $current_lang === 'fr' ? 'Accepté : .pdf, .doc, .docx' : 'Accepted: .pdf, .doc, .docx'; ?></div>
          </div>
        </div>

        <div class="form-group">
          <label class="required"><?php echo ct('payment_proof'); ?></label>
          <div class="file-upload-container">
            <div class="file-upload-wrapper<?= $hasPay ? ' has-file' : '' ?>" id="paymentUploadWrapper">
              <input type="file" name="payment_proof" id="payment_proof" accept=".pdf,.jpg,.jpeg,.png"<?= $hasPay ? '' : ' required' ?> data-server-file="<?= $hasPay ? '1' : '0' ?>">
              <div class="upload-icon">
                <i class="fas fa-receipt"></i>
              </div>
              <div class="upload-text"><?php echo ct('click_to_upload'); ?> <?php echo ct('payment_proof'); ?></div>
              <div class="upload-hint"><?php echo ct('max_10mb'); ?></div>
              <div class="drag-drop-hint"><?php echo ct('or_drag_drop'); ?></div>
              <div class="file-preview" id="paymentPreview"<?= $hasPay ? ' style="display:block"' : '' ?>><?php if ($hasPay): ?><i class="fas fa-check-circle"></i> <a href="<?= htmlspecialchars($pf['payment_proof']) ?>" target="_blank" rel="noopener"><?php echo $current_lang === 'fr' ? 'Document déjà enregistré' : 'Document already on file'; ?></a><?php endif; ?></div>
            </div>
            <div class="file-requirements"><?php echo $current_lang === 'fr' ? 'Accepté : .pdf, .jpg, .jpeg, .png' : 'Accepted: .pdf, .jpg, .jpeg, .png'; ?></div>
          </div>
        </div>

        <div class="form-group">
          <label><?php echo ct('additional_comments'); ?></label>
          <textarea name="comments" placeholder="<?php echo $current_lang === 'fr' ? 'Toute information supplémentaire que vous souhaitez partager avec le comité d\'admission...' : 'Any additional information you\'d like to share with the admissions committee...'; ?>"><?= htmlspecialchars($pf['comments'] ?? '') ?></textarea>
          <div class="hint"><?php echo ct('comments_hint'); ?></div>
        </div>

      <div class="section-title mt-4">
        <i class="fas fa-user-circle"></i>
        <?php echo ct('personal_info'); ?>
      </div>
      <div class="form-group">
        <label class="required"><?php echo ct('student_name'); ?></label>
        <div class="inline-inputs">
          <div>
            <input type="text" name="first_name" placeholder="<?php echo ct('first_name'); ?>" required value="<?= htmlspecialchars($pf['first_name'] ?? '') ?>">
            <div class="hint"><?php echo ct('legal_first_name'); ?></div>
          </div>
          <div>
            <input type="text" name="middle_name" placeholder="<?php echo ct('middle_name'); ?>" value="<?= htmlspecialchars($pf['middle_name'] ?? '') ?>">
            <div class="hint"><?php echo $current_lang === 'fr' ? 'Optionnel' : 'Optional'; ?></div>
          </div>
          <div>
            <input type="text" name="last_name" placeholder="<?php echo ct('last_name'); ?>" required value="<?= htmlspecialchars($pf['last_name'] ?? '') ?>">
            <div class="hint"><?php echo ct('family_name'); ?></div>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="required"><?php echo ct('birth_date'); ?></label>
        <div class="inline-inputs">
          <select name="birth_month" required>
            <option value=""><?php echo $current_lang === 'fr' ? 'Mois' : 'Month'; ?></option>
            <?php foreach (range(1, 12) as $m): ?>
              <?php $bm = sprintf('%02d', $m); ?>
              <option value="<?= $bm ?>"<?= (isset($pf['birth_month']) && (string)$pf['birth_month'] === $bm) ? ' selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="birth_day" required>
            <option value=""><?php echo $current_lang === 'fr' ? 'Jour' : 'Day'; ?></option>
            <?php foreach (range(1, 31) as $d): ?>
              <?php $bd = sprintf('%02d', $d); ?>
              <option value="<?= $bd ?>"<?= (isset($pf['birth_day']) && (string)$pf['birth_day'] === $bd) ? ' selected' : '' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
          <select name="birth_year" required>
            <option value=""><?php echo $current_lang === 'fr' ? 'Année' : 'Year'; ?></option>
            <?php $currentYear = date('Y'); ?>
            <?php foreach (range($currentYear, $currentYear - 80) as $y): ?>
              <option value="<?= $y ?>"<?= (isset($pf['birth_year']) && (string)$pf['birth_year'] === (string)$y) ? ' selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="required"><?php echo ct('gender'); ?></label>
        <select name="gender" required>
          <option value="" disabled<?= empty($pf['gender']) ? ' selected' : '' ?>><?php echo $current_lang === 'fr' ? 'Sélectionner le Genre' : 'Select Gender'; ?></option>
          <option value="Male"<?= (($pf['gender'] ?? '') === 'Male') ? ' selected' : '' ?>><?php echo ct('gender_male'); ?></option>
          <option value="Female"<?= (($pf['gender'] ?? '') === 'Female') ? ' selected' : '' ?>><?php echo ct('gender_female'); ?></option>
          <option value="Other"<?= (($pf['gender'] ?? '') === 'Other') ? ' selected' : '' ?>><?php echo ct('gender_other'); ?></option>
        </select>
      </div>
      <div class="form-group">
        <label><?php echo ct('contact_address'); ?></label>
        <input type="text" name="street_address" placeholder="<?php echo ct('street_address'); ?>" value="<?= htmlspecialchars($pf['street_address'] ?? '') ?>">
        <input type="text" name="address_line_2" placeholder="<?php echo ct('address_line_2'); ?>" class="mt-3" value="<?= htmlspecialchars($pf['address_line_2'] ?? '') ?>">
        <div class="inline-inputs mt-3">
          <input type="text" name="city" placeholder="<?php echo ct('city'); ?>" value="<?= htmlspecialchars($pf['city'] ?? '') ?>">
          <input type="text" name="state" placeholder="<?php echo ct('state'); ?>" value="<?= htmlspecialchars($pf['state'] ?? '') ?>">
          <input type="text" name="postal_code" placeholder="<?php echo ct('postal_code'); ?>" value="<?= htmlspecialchars($pf['postal_code'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="required"><?php echo ct('email_address'); ?></label>
        <input type="email" name="email" placeholder="your.email@example.com" required value="<?= htmlspecialchars($pf['email'] ?? '') ?>">
        <div class="hint"><?php echo ct('email_hint'); ?></div>
      </div>
      <div class="form-group">
        <label><?php echo ct('contact_numbers'); ?></label>
        <div class="inline-inputs">
          <input type="text" name="mobile_number" placeholder="<?php echo ct('mobile_number'); ?>" value="<?= htmlspecialchars($pf['mobile_number'] ?? '') ?>">
          <input type="text" name="phone_number" placeholder="<?php echo ct('phone_number'); ?>" value="<?= htmlspecialchars($pf['phone_number'] ?? '') ?>">
          <input type="text" name="work_number" placeholder="<?php echo ct('work_number'); ?>" value="<?= htmlspecialchars($pf['work_number'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label><?php echo ct('current_company'); ?></label>
        <input type="text" name="company" placeholder="<?php echo $current_lang === 'fr' ? 'Nom de l\'Entreprise (Optionnel)' : 'Company Name (Optional)'; ?>" value="<?= htmlspecialchars($pf['company'] ?? '') ?>">
      </div>
      </div>

      <p class="ct-manual-hint"><?php echo htmlspecialchars(ct('manual_submit_hint'), ENT_QUOTES, 'UTF-8'); ?></p>

      <div class="form-buttons">
        <button type="submit" class="btn btn-primary" id="submitButton">
          <i class="fas fa-paper-plane"></i>
          <?php echo ct('submit_application'); ?>
        </button>
      </div>
  </form>
</div>

<?php include 'footer.php'; ?>

<script>
/* ===== Brand colors ===== */
const COLORS = {
  navyBlue: '#427431',
  secondaryBlue: '#3661B9',
  darkBlue: '#2f5a26',
  gold: '#E21D1E',
  white: '#FFFFFF',
  success: '#28a745',
  danger: '#dc3545'
};

/* ===== PROGRAM DATA (static in helpers/credit_transfer_programs.php — not from e-learning API) ===== */
const PROGRAMS = <?= json_encode(pcvc_credit_transfer_programs(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

/* ===== FORM STATE ===== */
let uploadedFiles = {
  current_degree: null,
  current_transcripts: null,
  passport_or_id: null,
  academic_cv: null,
  payment_proof: null
};

/* ===== UTILITY FUNCTIONS ===== */
function creditNormalizeAiGenderForForm(form) {
  if (!form) return;
  var el = form.querySelector('select[name="gender"]');
  if (!el) return;
  var v = (el.value || '').trim();
  var map = { Homme: 'Male', Femme: 'Female', Autre: 'Other', homme: 'Male', femme: 'Female', autre: 'Other' };
  if (map[v]) {
    el.value = map[v];
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }
}

function creditDismissSubmittedBanner() {
  var el = document.getElementById('creditSubmittedFreshBanner');
  if (el) el.remove();
  try {
    var u = new URL(window.location.href);
    u.searchParams.delete('fresh');
    var qs = u.searchParams.toString();
    history.replaceState(null, '', u.pathname + (qs ? '?' + qs : '') + u.hash);
  } catch (e) { /* ignore */ }
}

/* ===== FILE UPLOAD HANDLING - FIXED ===== */
function setupFileUploadHandlers() {
  const fileFields = [
    { id: 'current_degree', wrapper: 'degreeUploadWrapper', preview: 'degreePreview' },
    { id: 'current_transcripts', wrapper: 'transcriptUploadWrapper', preview: 'transcriptPreview' },
    { id: 'passport_or_id', wrapper: 'passportUploadWrapper', preview: 'passportPreview' },
    { id: 'academic_cv', wrapper: 'cvUploadWrapper', preview: 'cvPreview' },
    { id: 'payment_proof', wrapper: 'paymentUploadWrapper', preview: 'paymentPreview' }
  ];

  fileFields.forEach(field => {
    const input = document.getElementById(field.id);
    const wrapper = document.getElementById(field.wrapper);
    const preview = document.getElementById(field.preview);
    
    if (!input || !wrapper || !preview) return;
    
    // Click handler for wrapper
    wrapper.addEventListener('click', (e) => {
      if (e.target !== input) {
        input.click();
      }
    });
    
    // File change handler
    input.addEventListener('change', function(e) {
      if (this.files.length > 0) {
        handleFileSelect(this.files[0], field.id, wrapper, preview);
      }
    });
    
    // Drag and drop handlers
    wrapper.addEventListener('dragover', (e) => {
      e.preventDefault();
      wrapper.classList.add('dragover');
    });
    
    wrapper.addEventListener('dragleave', () => {
      wrapper.classList.remove('dragover');
    });
    
    wrapper.addEventListener('drop', (e) => {
      e.preventDefault();
      wrapper.classList.remove('dragover');
      
      if (e.dataTransfer.files.length > 0) {
        input.files = e.dataTransfer.files;
        handleFileSelect(e.dataTransfer.files[0], field.id, wrapper, preview);
      }
    });
  });
}

function handleFileSelect(file, fieldName, wrapper, previewEl) {
  // Validate file size (10MB max)
  const maxSize = 10 * 1024 * 1024; // 10MB
  
  if (file.size > maxSize) {
    previewEl.innerHTML = `<i class="fas fa-exclamation-circle"></i> <?php echo $current_lang === 'fr' ? 'Fichier trop volumineux ! Max 10MB' : 'File too large! Max 10MB'; ?>`;
    previewEl.classList.add('file-error');
    previewEl.style.display = 'block';
    wrapper.classList.remove('has-file');
    wrapper.classList.add('dragover');
    
    // Reset input
    const input = document.getElementById(fieldName);
    input.value = '';
    
    uploadedFiles[fieldName] = null;
    return;
  }
  
  // Validate file type based on field
  const validExtensions = getValidExtensions(fieldName);
  const fileExt = file.name.split('.').pop().toLowerCase();
  
  if (!validExtensions.includes(fileExt)) {
    previewEl.innerHTML = `<i class="fas fa-exclamation-circle"></i> <?php echo $current_lang === 'fr' ? 'Type de fichier invalide ! Autorisé :' : 'Invalid file type! Allowed:'; ?> ${validExtensions.join(', ')}`;
    previewEl.classList.add('file-error');
    previewEl.style.display = 'block';
    wrapper.classList.remove('has-file');
    
    // Reset input
    const input = document.getElementById(fieldName);
    input.value = '';
    
    uploadedFiles[fieldName] = null;
    return;
  }
  
  // Success - update UI
  const fileSize = (file.size / 1024 / 1024).toFixed(2);
  previewEl.innerHTML = `
    <i class="fas fa-check-circle"></i> 
    <strong>${file.name}</strong>
    <span class="file-size">(${fileSize} MB)</span>
  `;
  previewEl.classList.remove('file-error');
  previewEl.style.display = 'block';
  wrapper.classList.add('has-file');
  wrapper.classList.remove('dragover');
  
  // Store file reference
  uploadedFiles[fieldName] = file;
  
  console.log(`File attached: ${fieldName} - ${file.name} (${fileSize} MB)`);
}

function getValidExtensions(fieldName) {
  switch(fieldName) {
    case 'current_degree':
    case 'current_transcripts':
      return ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    case 'passport_or_id':
    case 'payment_proof':
      return ['pdf', 'jpg', 'jpeg', 'png'];
    case 'academic_cv':
      return ['pdf', 'doc', 'docx'];
    default:
      return ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
  }
}

/* ===== SMART PROGRAM POPULATION ===== */
function populatePrograms(university) {
  const datalist = document.getElementById('programOptions');
  const programInput = document.getElementById('proposed_program');
  const catalogueLink = document.getElementById('upafaCatalogueLink');
  
  datalist.innerHTML = '';
  programInput.value = '';
  if (catalogueLink) {
    catalogueLink.style.display = university === 'UPAFA' ? '' : 'none';
  }
  
  const data = PROGRAMS[university];
  if (!data) return;
  
  if (Array.isArray(data)) {
    data.forEach(name => {
      const opt = document.createElement('option');
      opt.value = name;
      datalist.appendChild(opt);
    });
  } else {
    const counts = {};
    for (const category in data) {
      if (!Array.isArray(data[category])) continue;
      data[category].forEach(name => {
        counts[name] = (counts[name] || 0) + 1;
      });
    }
    for (const category in data) {
      if (!Array.isArray(data[category])) continue;
      data[category].forEach(name => {
        const opt = document.createElement('option');
        opt.value = counts[name] > 1 ? `${name} (${category})` : name;
        opt.dataset.category = category;
        datalist.appendChild(opt);
      });
    }
  }
}

/* ===== SMART PROGRESS BAR ===== */
function showProgress(title, subtitle) {
  const overlay = document.getElementById('progressOverlay');
  const progressTitle = document.getElementById('progressTitle');
  const progressSubtitle = document.getElementById('progressSubtitle');
  
  progressTitle.textContent = title;
  progressSubtitle.textContent = subtitle;
  overlay.style.display = 'flex';
  
  document.getElementById('progressFill').style.width = '0%';
  document.getElementById('progressText').textContent = '0% Complete';
  
  document.querySelectorAll('.progress-step').forEach(step => {
    step.classList.remove('active');
  });
  document.getElementById('step1Progress').classList.add('active');
}

function updateProgress(percent, step, message) {
  const progressFill = document.getElementById('progressFill');
  const progressText = document.getElementById('progressText');
  
  progressFill.style.width = `${percent}%`;
  progressText.textContent = message || `${percent}% Complete`;
  
  document.querySelectorAll('.progress-step').forEach(el => {
    el.classList.remove('active');
  });
  
  if (step >= 1 && step <= 4) {
    document.getElementById(`step${step}Progress`).classList.add('active');
  }
}

function hideProgress() {
  document.getElementById('progressOverlay').style.display = 'none';
}

/* ===== FORM VALIDATION ===== */
function validateStep2(silent) {
  silent = !!silent;
  const requiredFiles = ['current_degree', 'current_transcripts', 'passport_or_id', 'academic_cv', 'payment_proof'];
  const missingFiles = [];
  
  requiredFiles.forEach(fieldName => {
    const input = document.getElementById(fieldName);
    if (!input) return;
    const hasServer = input.getAttribute('data-server-file') === '1';
    if (hasServer) return;
    if (!input.files || input.files.length === 0) {
      missingFiles.push(fieldName.replace(/_/g, ' '));
    }
  });
  
  if (missingFiles.length > 0) {
    if (!silent) {
      alert(`<?php echo $current_lang === 'fr' ? 'Veuillez télécharger tous les fichiers requis :' : 'Please upload all required files:' ?>\n\n• ${missingFiles.join('\n• ')}`);
    }
    return false;
  }
  
  for (const fieldName of requiredFiles) {
    const input = document.getElementById(fieldName);
    if (!input || !input.files || input.files.length === 0) continue;
    if (input.files[0].size > 10 * 1024 * 1024) {
      if (!silent) {
        alert(`<?php echo $current_lang === 'fr' ? 'Fichier trop volumineux :' : 'File too large:' ?> ${fieldName.replace(/_/g, ' ')}\n<?php echo $current_lang === 'fr' ? 'La taille maximale des fichiers est de 10MB' : 'Maximum file size is 10MB' ?>`);
      }
      return false;
    }
  }
  
  return true;
}

function creditProgramSelectionValid() {
  const uni = document.getElementById('university');
  const proposedEl = document.getElementById('proposed_program');
  if (!uni || !proposedEl) return false;
  const uniVal = uni.value;
  const proposed = proposedEl.value.trim();
  if (!uniVal || !proposed) return false;
  let programExists = false;
  const programs = PROGRAMS[uniVal];
  if (Array.isArray(programs)) {
    programExists = programs.includes(proposed);
  } else if (programs && typeof programs === 'object') {
    for (const category in programs) {
      if (programs[category].includes(proposed.replace(` (${category})`, ''))) {
        programExists = true;
        break;
      }
    }
  }
  return programExists;
}

function creditPersonalComplete() {
  const form = document.getElementById('creditForm');
  if (!form) return false;
  const req = ['first_name', 'last_name', 'birth_month', 'birth_day', 'birth_year', 'gender', 'email'];
  for (let i = 0; i < req.length; i++) {
    const el = form.querySelector('[name="' + req[i] + '"]');
    if (!el || !String(el.value || '').trim()) return false;
  }
  return true;
}

/* ===== FINAL SUBMISSION ===== */
document.getElementById('creditForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const form = this;
  const partialAutofillSubmit = window.__creditPartialSubmitOnce === true;
  if (partialAutofillSubmit) {
    window.__creditPartialSubmitOnce = false;
  }

  try {
    const submitBtn = document.getElementById('submitButton');
    if (!partialAutofillSubmit) {
      if (!validateStep2()) {
        return;
      }
      if (!document.getElementById('university').value) {
        alert('<?php echo $current_lang === 'fr' ? 'Veuillez sélectionner une université.' : 'Please select a University.' ?>');
        return;
      }
      if (!creditProgramSelectionValid()) {
        alert('<?php echo $current_lang === 'fr' ? 'Veuillez sélectionner un programme proposé valide parmi les suggestions.' : 'Please select a valid Proposed Program from the suggestions.' ?>');
        return;
      }
      if (!creditPersonalComplete()) {
        alert('<?php echo $current_lang === 'fr' ? 'Veuillez remplir tous les champs personnels obligatoires.' : 'Please complete all required personal fields.' ?>');
        return;
      }
    } else if (!document.getElementById('university').value) {
      alert('<?php echo $current_lang === 'fr' ? 'Veuillez sélectionner une université.' : 'Please select a University.' ?>');
      return;
    }

    const formData = new FormData(form);
    formData.append('step', 'step2');
    if (partialAutofillSubmit) {
      formData.append('partial_submit', '1');
    }
    
    if (submitBtn) submitBtn.disabled = true;
    
    // Show detailed progress overlay
    showProgress('<?php echo ct('submitting_application'); ?>', '<?php echo ct('may_take_moment'); ?>');
    
    // Real progress updates
    const progressUpdates = [
      {percent: 10, step: 1, message: '<?php echo $current_lang === 'fr' ? 'Validation des données du formulaire...' : 'Validating form data...' ?>'},
      {percent: 25, step: 1, message: '<?php echo $current_lang === 'fr' ? 'Vérification des champs requis...' : 'Checking required fields...' ?>'},
      {percent: 40, step: 2, message: '<?php echo $current_lang === 'fr' ? 'Préparation des téléchargements...' : 'Preparing file uploads...' ?>'},
      {percent: 60, step: 2, message: '<?php echo $current_lang === 'fr' ? 'Téléchargement des documents...' : 'Uploading documents...' ?>'},
      {percent: 75, step: 3, message: '<?php echo $current_lang === 'fr' ? 'Sauvegarde des informations académiques...' : 'Saving academic information...' ?>'},
      {percent: 90, step: 3, message: '<?php echo $current_lang === 'fr' ? 'Finalisation de la soumission...' : 'Finalizing submission...' ?>'},
      {percent: 95, step: 4, message: '<?php echo $current_lang === 'fr' ? 'Envoi de la confirmation...' : 'Sending confirmation...' ?>'},
      {percent: 100, step: 4, message: '<?php echo $current_lang === 'fr' ? 'Soumission terminée !' : 'Submission complete!' ?>'}
    ];
    
    // Animate progress
    const progressTickMs = partialAutofillSubmit ? 120 : 300;
    for (let i = 0; i < progressUpdates.length; i++) {
      setTimeout(() => {
        const update = progressUpdates[i];
        updateProgress(update.percent, update.step, update.message);
      }, i * progressTickMs);
    }
    
    try {
      console.log('Submitting form with files...');
      
      const response = await fetch(form.getAttribute('data-save'), {
        method: 'POST',
        body: formData
      });
      
      const data = await response.json();
      
      const finalDelayMs = partialAutofillSubmit ? 500 : 2500;
      // Final delay for animation
      setTimeout(() => {
        if (data.status === 'success') {
          updateProgress(100, 4, '<?php echo $current_lang === 'fr' ? 'Demande soumise avec succès !' : 'Application submitted successfully!' ?>');
          
          setTimeout(() => {
            hideProgress();
            const msg = <?php echo json_encode(ct('submit_success_title'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>
              + '\n\n' + <?php echo json_encode($current_lang === 'fr' ? 'ID de la demande :' : 'Application ID:', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>
              + ' ' + (data.user_id || '');
            alert('✅ ' + msg);
            window.location.href = 'credit_transfer.php?fresh=1';
          }, partialAutofillSubmit ? 400 : 1000);
        } else {
          hideProgress();
          alert(`❌ ${data.message}`);
          if (submitBtn) submitBtn.disabled = false;
        }
      }, finalDelayMs);
      
    } catch (error) {
      hideProgress();
      alert(`❌ <?php echo $current_lang === 'fr' ? 'Échec de la soumission :' : 'Submission failed:' ?> ${error.message}`);
      if (submitBtn) submitBtn.disabled = false;
    }
  } finally {
    form.noValidate = false;
  }
});

const CT_AUTOFILL_TEXT = <?php echo json_encode([
  'gate' => ct('smart_autofill_gate'),
  'formats' => ct('smart_autofill_formats'),
  'hint' => ct('smart_autofill_hint'),
  'queueReady' => ct('smart_autofill_ready'),
  'queueCount' => ct('smart_autofill_queue_count'),
  'processing' => ct('smart_autofill_processing'),
  'error' => ct('smart_autofill_error'),
  'complete' => ct('smart_autofill_complete'),
  'uploading' => ct('smart_autofill_uploading'),
  'detailBatch' => ct('smart_autofill_detail_batch'),
  'detailRoute' => ct('smart_autofill_detail_route'),
  'detailDone' => ct('smart_autofill_detail_done'),
  'autoSubmitReview' => ct('auto_submit_review'),
  'autoSubmitting' => ct('auto_submitting'),
], JSON_UNESCAPED_UNICODE); ?>;

const CT_STAGE_META = <?php echo json_encode([
  ['id' => 'queue', 'label' => ct('smart_autofill_stage_queue'), 'short' => $current_lang === 'fr' ? 'Liste' : 'Queue'],
  ['id' => 'batch', 'label' => ct('smart_autofill_stage_batch'), 'short' => 'AI'],
  ['id' => 'route', 'label' => ct('smart_autofill_stage_route'), 'short' => $current_lang === 'fr' ? 'Env.' : 'Save'],
  ['id' => 'done', 'label' => ct('smart_autofill_stage_done'), 'short' => 'OK'],
], JSON_UNESCAPED_UNICODE); ?>;

(function () {
  const progressWrap = document.getElementById('ctSmartAutofillProgressWrap');
  const progressText = document.getElementById('ctSmartAutofillProgressText');
  const progressLabel = document.getElementById('ctSmartAutofillProgressLabel');
  const progressSubtext = document.getElementById('ctSmartAutofillProgressSubtext');
  const stagePillsEl = document.getElementById('ctSmartAutofillStagePills');
  const panelsEl = document.getElementById('ctSmartAutofillPanels');
  const resultsEl = document.getElementById('ctSmartAutofillResults');
  const warningsWrapEl = document.getElementById('ctSmartAutofillWarningsWrap');
  const warningsEl = document.getElementById('ctSmartAutofillWarnings');

  if (!progressWrap || !progressText || !progressLabel || !progressSubtext || !stagePillsEl || !panelsEl || !resultsEl || !warningsWrapEl || !warningsEl) {
    return;
  }

  let pendingFiles = [];
  let isProcessing = false;
  let batchToken = '';

  function fileKey(file) {
    return [file.name, file.size, file.lastModified].join('::');
  }

  function setField(name, val) {
    if (val == null || val === '') return;
    const el = document.querySelector('#creditForm [name="' + name + '"]');
    if (!el || el.type === 'file') return;
    el.value = val;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function applyFields(fields) {
    Object.keys(fields || {}).forEach(function (k) {
      setField(k, fields[k]);
    });
  }

  function resetProgress() {
    progressWrap.className = 'smart-autofill-progress-panel';
    progressText.textContent = '\u2014';
    progressLabel.textContent = CT_AUTOFILL_TEXT.processing;
    progressSubtext.textContent = CT_AUTOFILL_TEXT.hint;
    stagePillsEl.innerHTML = '';
  }

  function setStatus(type, msg) {
    var el = document.getElementById('ctSmartAutofillStatus');
    if (!el) return;
    el.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-info', 'alert-warning');
    el.style.display = 'block';
    el.classList.add('alert');
    if (type === 'error') {
      el.classList.add('alert-danger');
    } else if (type === 'success') {
      el.classList.add('alert-success');
    } else if (type === 'warning') {
      el.classList.add('alert-warning');
    } else {
      el.classList.add('alert-info');
    }
    el.textContent = msg;
  }

  function renderStagePills(activeId, kind) {
    stagePillsEl.innerHTML = '';
    const activeIndex = CT_STAGE_META.findIndex(function (s) { return s.id === activeId; });
    CT_STAGE_META.forEach(function (stage, index) {
      var pill = document.createElement('span');
      pill.className = 'smart-autofill-stage-pill';
      pill.textContent = stage.label;
      if (activeIndex > -1 && index < activeIndex) {
        pill.classList.add('is-done');
      } else if (stage.id === activeId) {
        pill.classList.add(kind === 'danger' ? 'is-error' : 'is-active');
      }
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

    var stage = CT_STAGE_META.find(function (item) { return item.id === stageId; });
    progressText.textContent = stage ? stage.short : 'AI';
    progressLabel.textContent = message;
    progressSubtext.textContent = subtext || (stage ? stage.label : '');
    renderStagePills(stageId, kind);
    if (kind === 'danger') setStatus('error', message);
    else if (kind === 'success') setStatus('success', message);
    else if (kind === 'warning') setStatus('warning', message);
    else setStatus('info', message);

    try {
      progressWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (e) { /* ignore */ }
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

  function academicGateOk() {
    var u = document.getElementById('university');
    var p = document.getElementById('proposed_program');
    return !!(u && u.value && p && String(p.value || '').trim());
  }

  function updateAvailability() {
    const trig = document.getElementById('ctSmartAutofillTrigger');
    const start = document.getElementById('ctSmartAutofillStart');
    var gate = academicGateOk();
    if (trig) trig.disabled = isProcessing || !gate;
    if (start) start.disabled = isProcessing || pendingFiles.length === 0 || !gate;
    const help = document.getElementById('ctSmartAutofillHelp');
    if (help) {
      var gateLine = gate ? '' : ('<br><span style="opacity:.88;font-size:0.85rem">' + CT_AUTOFILL_TEXT.gate + '</span>');
      help.innerHTML = CT_AUTOFILL_TEXT.formats + '<br>' + CT_AUTOFILL_TEXT.hint + gateLine;
    }
  }

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
    const ul = document.getElementById('ctSmartAutofillQueue');
    const hint = document.getElementById('ctSmartAutofillQueueHint');
    if (!ul) return;
    ul.innerHTML = '';
    if (!pendingFiles.length) {
      if (hint) {
        hint.style.display = 'block';
        hint.textContent = <?php echo json_encode(ct('smart_autofill_queue_empty'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
      }
      return;
    }
    if (hint) {
      hint.style.display = 'block';
      hint.textContent = pendingFiles.length + ' ' + CT_AUTOFILL_TEXT.queueCount + '. ' + CT_AUTOFILL_TEXT.queueReady;
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
      rm.setAttribute('aria-label', 'Remove');
      rm.textContent = '\u00d7';
      rm.disabled = isProcessing;
      rm.dataset.fileKey = fileKey(file);
      li.appendChild(name);
      li.appendChild(rm);
      ul.appendChild(li);
    });
  }

  document.getElementById('ctSmartAutofillQueue').addEventListener('click', function (event) {
    var btn = event.target.closest('.smart-autofill-remove');
    if (!btn || isProcessing) return;
    var key = btn.dataset.fileKey;
    pendingFiles = pendingFiles.filter(function (f) { return fileKey(f) !== key; });
    renderQueue();
    updateAvailability();
  });

  function uploadOne(field, file) {
    return new Promise(function (resolve, reject) {
      var fd = new FormData();
      fd.append('file', file);
      fd.append('field', field);
      var uidEl = document.querySelector('#creditForm input[name="user_id"]');
      fd.append('user_id', uidEl ? uidEl.value : '');
      fd.append('skip_ai_validation', '1');
      fd.append('smart_autofill_batch_token', batchToken);
      fd.append('lang', document.documentElement.lang || 'en');
      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'upload_credit_transfer_file.php');
      xhr.onload = function () {
        try {
          var j = JSON.parse(xhr.responseText || '{}');
          if (xhr.status >= 200 && xhr.status < 300 && j.status === 'success') resolve(j);
          else reject(new Error(j.message || 'Upload failed'));
        } catch (e) {
          reject(e);
        }
      };
      xhr.onerror = function () { reject(new Error('Network error')); };
      xhr.send(fd);
    });
  }

  function markCreditAutofillFile(field, relPath) {
    if (!relPath) return;
    var map = {
      current_degree: { wrap: 'degreeUploadWrapper', prev: 'degreePreview' },
      current_transcripts: { wrap: 'transcriptUploadWrapper', prev: 'transcriptPreview' },
      passport_or_id: { wrap: 'passportUploadWrapper', prev: 'passportPreview' },
      academic_cv: { wrap: 'cvUploadWrapper', prev: 'cvPreview' },
      payment_proof: { wrap: 'paymentUploadWrapper', prev: 'paymentPreview' }
    };
    var m = map[field];
    if (!m) return;
    var inp = document.getElementById(field);
    var wrap = document.getElementById(m.wrap);
    var prev = document.getElementById(m.prev);
    if (inp) {
      inp.removeAttribute('required');
      inp.setAttribute('data-server-file', '1');
      inp.value = '';
    }
    if (wrap) wrap.classList.add('has-file');
    if (prev) {
      prev.style.display = 'block';
      prev.textContent = '';
      var icon = document.createElement('i');
      icon.className = 'fas fa-check-circle';
      prev.appendChild(icon);
      prev.appendChild(document.createTextNode(' '));
      var a = document.createElement('a');
      a.href = 'download.php?f=' + encodeURIComponent(btoa(unescape(encodeURIComponent(relPath)))) + '&inline=1';
      a.target = '_blank';
      a.rel = 'noopener';
      a.textContent = <?php echo json_encode($current_lang === 'fr' ? 'Document déjà enregistré' : 'Document already on file', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
      prev.appendChild(a);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var trig = document.getElementById('ctSmartAutofillTrigger');
    var input = document.getElementById('ctSmartAutofillInput');
    var start = document.getElementById('ctSmartAutofillStart');
    var u = document.getElementById('university');
    var p = document.getElementById('proposed_program');
    if (u) u.addEventListener('change', updateAvailability);
    if (p) {
      p.addEventListener('change', updateAvailability);
      p.addEventListener('input', updateAvailability);
    }
    if (trig && input) trig.addEventListener('click', function () { input.click(); });
    if (input) {
      input.addEventListener('change', function () {
        var files = Array.from(input.files || []);
        input.value = '';
        if (!files.length) return;
        addPendingFiles(files);
        clearPanels();
        resetProgress();
        setStage('queue', CT_AUTOFILL_TEXT.queueReady, 'info', pendingFiles.length + ' ' + CT_AUTOFILL_TEXT.queueCount);
        renderQueue();
        updateAvailability();
      });
    }
    if (start) {
      start.addEventListener('click', function () {
        if (!pendingFiles.length || isProcessing) return;
        isProcessing = true;
        clearPanels();
        updateAvailability();
        resetProgress();
        setStage('batch', CT_AUTOFILL_TEXT.processing, 'info', CT_AUTOFILL_TEXT.detailBatch);

        var uidEl = document.querySelector('#creditForm input[name="user_id"]');
        var uid = uidEl ? uidEl.value : '';
        var fd = new FormData();
        var filesSnapshot = pendingFiles.slice();
        filesSnapshot.forEach(function (f) { fd.append('documents[]', f); });
        fd.append('application_id', uid);
        fd.append('lang', document.documentElement.lang || 'en');

        fetch('credit_transfer_ai_autofill.php', { method: 'POST', body: fd })
          .then(function (res) { return res.json().then(function (data) { return { res: res, data: data }; }); })
          .then(function (_ref) {
            var res = _ref.res;
            var data = _ref.data;
            if (!res.ok || !data || data.status !== 'success') {
              throw new Error((data && data.message) ? data.message : CT_AUTOFILL_TEXT.error);
            }
            batchToken = data.upload_token || '';
            applyFields(data.fields || {});

            setStage('route', CT_AUTOFILL_TEXT.uploading, 'info', CT_AUTOFILL_TEXT.detailRoute);

            var docs = Array.isArray(data.documents) ? data.documents : [];
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
                    if (j && j.file_path) markCreditAutofillFile(field, j.file_path);
                    return j;
                  })
                );
              }
            });
            return Promise.all(uploads).then(function () { return data; });
          })
          .then(function (data) {
            var warnings = Array.isArray(data.warnings) ? data.warnings.slice() : [];
            renderPanels(data.documents || [], warnings);
            pendingFiles = [];
            renderQueue();
            window.setTimeout(function () {
              setStage('done', CT_AUTOFILL_TEXT.autoSubmitting, 'success', CT_AUTOFILL_TEXT.detailDone);
              if (warnings.length) {
                setStatus('info', CT_AUTOFILL_TEXT.autoSubmitReview);
              }
              var f = document.getElementById('creditForm');
              if (f) {
                window.__creditPartialSubmitOnce = true;
                creditNormalizeAiGenderForForm(f);
                f.noValidate = true;
                try {
                  f.requestSubmit();
                } catch (subErr) {
                  f.noValidate = false;
                  window.__creditPartialSubmitOnce = false;
                  setStatus('error', subErr && subErr.message ? subErr.message : '<?php echo $current_lang === 'fr' ? 'La soumission automatique a été bloquée. Utilisez le bouton Soumettre.' : 'Auto-submit was blocked. Use the Submit button.' ?>');
                }
              }
            }, 100);
          })
          .catch(function (e) {
            resetProgress();
            setStage('batch', e.message || CT_AUTOFILL_TEXT.error, 'danger', CT_AUTOFILL_TEXT.error);
          })
          .finally(function () {
            isProcessing = false;
            updateAvailability();
          });
      });
    }
    clearPanels();
    resetProgress();
    renderQueue();
    updateAvailability();
  });
})();

/* ===== INITIALIZATION ===== */
document.addEventListener('DOMContentLoaded', () => {
  const universitySelect = document.getElementById('university');
  if (universitySelect) {
    universitySelect.addEventListener('change', function(e) {
      populatePrograms(e.target.value);
    });
    if (universitySelect.value) {
      populatePrograms(universitySelect.value);
    }
  }

  setupFileUploadHandlers();

  var freshOk = document.getElementById('creditSubmittedFreshBanner');
  if (freshOk) {
    freshOk.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  console.log('Form initialized with file upload handlers ready.');
});
</script>

</body>
</html>