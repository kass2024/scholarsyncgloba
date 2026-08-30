<?php
session_start();
if (!empty($_GET['id']) && is_string($_GET['id'])) {
    $rid = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['id']);
    if ($rid !== '') {
        $_SESSION['user_id'] = $rid;
    }
}
$_SESSION['user_id'] ??= 'user_' . bin2hex(random_bytes(6));

/* =========================================================
   SMART RETRIEVAL: if the user landed here via ?id=<user_id>
   and a matching student_applications row exists, expose its
   numeric application id so the page can auto-prefill via JS.
========================================================= */
$studentPrefillAppId = 0;
$studentPrefillUserId = '';
$studentPrefillEmail = '';
$studentPrefillFirstName = '';

if (!empty($_GET['id']) && is_string($_GET['id'])) {
    $lookupUserId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['id']);
    if ($lookupUserId !== '') {
        $dbPathTry = __DIR__ . '/db.php';
        if (file_exists($dbPathTry)) {
            require_once $dbPathTry;
            if (isset($conn) && $conn instanceof mysqli) {
                if ($lookupStmt = $conn->prepare(
                    "SELECT id, user_id, email, first_name
                       FROM student_applications
                      WHERE user_id = ?
                      ORDER BY id DESC
                      LIMIT 1"
                )) {
                    $lookupStmt->bind_param('s', $lookupUserId);
                    if ($lookupStmt->execute()) {
                        $lookupRes = $lookupStmt->get_result();
                        if ($lookupRow = $lookupRes->fetch_assoc()) {
                            $studentPrefillAppId    = (int)$lookupRow['id'];
                            $studentPrefillUserId   = (string)$lookupRow['user_id'];
                            $studentPrefillEmail    = (string)($lookupRow['email'] ?? '');
                            $studentPrefillFirstName = (string)($lookupRow['first_name'] ?? '');
                        }
                    }
                    $lookupStmt->close();
                }
            }
        }
    }
}

// Language detection and setting
$lang = $_GET['lang'] ?? $_COOKIE['app_lang'] ?? 'en';
if (in_array($lang, ['en', 'fr'])) {
    setcookie('app_lang', $lang, time() + (86400 * 30), "/");
}

// Bilingual text arrays - ONLY for UI elements, not form field names
$text = [
    'en' => [
        'title' => 'Student Application Form',
        'next' => 'Next',
        'prev' => 'Back',
        'step1_title' => 'Choice of Studies',
        'step1_desc' => 'Follow the steps: pick regions, add universities, then choose one or more program levels and programs for each university. You may select several universities and multiple programs.',
        'step1_flow_1' => 'Region',
        'step1_flow_2' => 'University',
        'step1_flow_3' => 'Level',
        'step1_flow_4' => 'Program',
        'step1_regions_label' => 'Study areas',
        'step1_regions_placeholder' => 'Select one or more regions',
        'step1_regions_help' => 'Start by choosing where you want to study. You can select several regions.',
        'step1_add_panel_title' => 'Add a university',
        'step1_add_panel_help' => 'Pick a university from your selected regions, then click Add. Repeat to apply to more than one university.',
        'step1_add_university_btn' => 'Add university',
        'step1_pick_university' => 'Choose a university…',
        'step1_add_level' => 'Add another program level',
        'step1_remove_row' => 'Remove this row',
        'step1_remove_uni' => 'Remove university',
        'step1_choices_heading' => 'Your universities & programs',
        'step1_filter_summary' => 'Filter list (optional)',
        'step1_search_label' => 'Narrow your list (optional)',
        'step1_search_placeholder' => 'Search university or program name…',
        'step1_clear' => 'Clear',
        'step1_empty_no_region' => 'Select at least one region above to continue.',
        'step1_empty_add_uni' => 'Use “Add a university” below to choose your institutions. You can add multiple universities and several programs per university.',
        'step1_cart_title' => 'Your selections',
        'step1_cart_help' => 'Summarizes universities where you have chosen at least one program.',
        'step1_university' => 'University',
        'step1_level' => 'Program level',
        'step1_program' => 'Programs',
        'step1_remove' => 'Remove',
        'step1_agent_panel_title' => 'Referral & consultant',
        'step1_agent_panel_desc' => 'Tell us how you heard about us. If a member of our team referred you, select them here before uploading documents for Smart AI autofill.',
        'step1_assign_title' => 'Assign to (staff)',
        'step1_assign_desc' => 'Optional: pick a staff member. When you submit the application, they receive an email with the applicant summary and all uploaded documents attached.',
        'staff_assign_placeholder' => 'Search staff by name or email',
        'staff_assign_hint' => 'Suggested staff appear when you click here; type at least 2 letters to search everyone.',
        'staff_assign_clear' => 'Clear',
        'doc_prepare_title' => 'Documents to Prepare Before Starting',
        'doc_prepare_desc' => 'To ensure a smooth application process, please have the following documents available. You will be asked to upload them during the application steps.',
        'doc_formats' => 'Supported formats: PDF, JPG, PNG',
        'doc_list' => ['Valid Passport', 'Degree / Academic Transcripts', 'High School Certificate', 'CV / Resume', 'Recommendation Letter(s)', 'Personal Statement / Motivation Letter', 'English Proficiency Certificate', 'Birth Certificate', 'Application / Payment Proof'],
        
        // Step 2
        'step2_title' => 'Personal Information',
        'step2_desc' => 'Enter details exactly as shown on your passport.',
        'smart_autofill_title' => 'Smart AI autofill',
        'smart_autofill_desc' => 'After choosing your study choice, upload passport, CV, transcripts, degree, birth certificate, or other supporting documents. AI will extract student details and route each recognized file into the existing attachment fields automatically.',
        'smart_autofill_button' => 'Add documents',
        'smart_autofill_start_button' => 'Start analysis',
        'smart_autofill_formats' => 'Supported formats: PDF, DOCX, JPG, JPEG, PNG, WEBP',
        'smart_autofill_hint' => 'Best results: upload passport, CV, and your latest academic documents together.',
        'smart_autofill_gate' => 'Choose at least one study program first. Then add the documents you want to analyze.',
        'smart_autofill_ready' => 'Study choice selected. Add one document now, then add more later before starting the analysis manually.',
        'smart_autofill_processing' => 'Analyzing your documents and extracting student details…',
        'smart_autofill_uploading' => 'Applying extracted details and attaching the recognized documents…',
        'smart_autofill_success' => 'Autofill complete. Please review the fields before continuing.',
        'smart_autofill_partial' => 'Autofill finished with some warnings. Please review the notes below.',
        'smart_autofill_error' => 'Autofill failed. Please try again with clearer documents.',
        'smart_autofill_need_draft' => 'Please save your draft first so uploaded documents can be attached to this application.',
        'smart_autofill_queue_title' => 'Queued documents',
        'smart_autofill_queue_empty' => 'No documents selected yet. Add one or more files, then start the analysis when you are ready.',
        'smart_autofill_queue_ready' => 'Documents are queued. Click Start analysis when you are ready to continue until final submission.',
        'smart_autofill_queue_count' => 'document(s) ready for analysis',
        'smart_autofill_results_title' => 'Recognized documents',
        'smart_autofill_warnings_title' => 'Warnings',
        'smart_autofill_existing_note' => 'These files are not stored in a new place. They are routed into the existing document attachment fields below.',
        'smart_autofill_stage_queue' => 'Documents queued',
        'smart_autofill_stage_draft' => 'Creating draft application',
        'smart_autofill_stage_batch' => 'Analyzing all documents',
        'smart_autofill_stage_fill' => 'Applying extracted details',
        'smart_autofill_stage_route' => 'Routing files to existing fields',
        'smart_autofill_stage_save' => 'Saving extracted form data',
        'smart_autofill_stage_submit' => 'Submitting application',
        'smart_autofill_analyzing_doc' => 'Analyzing document {current} of {total}: {name}…',
        'smart_autofill_analysis_intro' => 'Each document is analyzed separately (2 at a time). Progress updates as each file finishes, then files are routed automatically.',
        'smart_autofill_debug_title' => 'Batch debug details',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'phone_label' => 'Phone Number',
        'phone_placeholder' => 'Enter phone number',
        'phone_help' => 'Select country to auto-fill international code.',
        'gender' => 'Gender',
        'gender_options' => ['Male', 'Female'],
        'dob' => 'Date of Birth',
        'passport' => 'Passport Number',
        'national_id' => 'National ID',
        'birth_country' => 'Country of birth',
        'city_birth' => 'City of Birth',
        'nationality' => 'Nationality',
        'second_nationality' => 'Second nationality',
        
        // Step 3
        'step3_title' => 'Address & Family',
        'step3_desc' => 'Provide your current address and parent information.',
        'address1' => 'Address Line 1',
        'address2' => 'Address Line 2 (optional)',
        'city' => 'City',
        'state' => 'State / Province',
        'postal' => 'Postal Code',
        'parents_title' => 'Parents Information',
        'father_first' => 'Father First Name',
        'father_last' => 'Father Last Name',
        'mother_first' => 'Mother First Name',
        'mother_last' => 'Mother Last Name',
        
        // Step 4
        'step4_title' => 'Emergency Contact',
        'step4_desc' => 'Provide details of a person we can contact in case of emergency.',
        'emergency_first' => 'First Name',
        'emergency_last' => 'Last Name',
        'emergency_email' => 'Email',
        'emergency_phone_label' => 'Emergency Phone',
        'emergency_phone_help' => 'Select country to auto-fill code and validate number length.',
        'relationship' => 'Relationship',
        'same_address' => 'Same Address?',
        'same_address_options' => ['Yes', 'No'],
        
        // Step 5
        'step5_title' => 'Education & Background',
        'step5_desc' => 'Provide details about your previous education and academic background.',
        'institution_name' => 'Institution Name',
        'institution_name_placeholder' => 'e.g. Kigali Secondary School',
        'institution_street' => 'Institution Street',
        'institution_street_placeholder' => 'e.g. KN 123 St',
        'institution_city' => 'Institution City',
        'institution_city_placeholder' => 'e.g. Kigali',
        'institution_province' => 'Institution Province / State',
        'institution_province_placeholder' => 'e.g. Kigali City',
        'institution_country' => 'Institution Country',
        'institution_postal' => 'Postal Code',
        'institution_postal_placeholder' => 'e.g. 00000',
        'language' => 'Language of Instruction',
        'language_options' => ['English', 'French', 'Other'],
        'study_start' => 'Study Start Date',
        'graduation' => 'Graduation / Completion Date',
        'study_gap' => 'Study Gap?',
        'study_gap_placeholder' => 'Explain reason and duration of study gap',
        'secondary_school' => 'Additional Secondary School?',
        'secondary_school_placeholder' => 'Provide school name, location, and years attended',
        'post_secondary' => 'Post Secondary Education?',
        'post_secondary_placeholder' => 'Describe institution, program, and duration',
        'criminal_history' => 'Criminal History?',
        'criminal_history_placeholder' => 'Offense, date, and outcome',
        'disability' => 'Disability?',
        'disability_placeholder' => 'Describe disability and required accommodations',
        'visa_rejection' => 'Visa Rejection History?',
        'visa_rejection_placeholder' => 'Country, year, and reason for refusal',
        'yes_no_options' => ['Yes', 'No'],
        
        // Step 6
        'step6_title' => 'Destination & Finance',
        'step6_desc' => 'Review your study destination and indicate how your education expenses will be covered.',
        'destination_title' => 'Study Destination',
        'preferred_destination' => 'Preferred Destination',
        'preferred_help' => 'Automatically filled based on the region selected in Step 1.',
        'loan_destination' => 'Loan Destination',
        'loan_destination_help' => 'Defaults to your preferred study destination.',
        'other_loan_destination' => 'Other Loan Destination (Optional)',
        'other_loan_placeholder' => 'Different destination covered by loan',
        'finance_title' => 'Financial Responsibility',
        'tuition' => 'Who Pays Tuition?',
        'living_cost' => 'Who Pays Living Cost?',
        'travel' => 'Who Pays Travel?',
        'finance_options' => ['Self', 'Parents', 'Sponsor', 'Loan'],
        
        // Step 7
        'step7_title' => 'Documents Upload',
        'step7_desc' => 'Upload clear, readable documents. Supported formats: PDF, JPG, PNG. Files are validated automatically.',
        'degree_transcripts' => 'Degree / Academic Transcripts',
        'high_school' => 'High School Certificate',
        'passport_doc' => 'Valid Passport',
        'cv_resume' => 'CV / Resume',
        'recommendation' => 'Recommendation Letter(s)',
        'personal_statement' => 'Personal Statement / Motivation Letter',
        'english_certificate' => 'English Proficiency Certificate',
        'birth_certificate' => 'Birth Certificate',
        'payment_proof' => 'Application / Payment Proof',
        'drop_transcripts' => 'Drop transcripts here',
        'drop_certificate' => 'Drop certificate here',
        'drop_passport' => 'Drop passport here',
        'drop_cv' => 'Drop CV here',
        'drop_recommendation' => 'Drop recommendation letters',
        'drop_statement' => 'Drop document here',
        'multiple_files' => 'Multiple files allowed',
        'single_file' => 'Single file',
        'doc_progress_title' => 'AI extraction progress',
        'doc_debug_title' => 'Debug details',
        'doc_stage_prepare' => 'Preparing upload',
        'doc_stage_upload' => 'Uploading document',
        'doc_stage_extract' => 'Extracting text',
        'doc_stage_ai' => 'Running AI analysis',
        'doc_stage_parse' => 'Reading extracted details',
        'doc_stage_save' => 'Saving attachment',
        'referral_title' => 'How did you know us?',
        'referral_help' => 'This helps us assign the correct consultant to your application.',
        'referral_options' => [
            ['text' => 'Online / Website / Social Media', 'value' => 'online'],
            ['text' => 'Through an Agent', 'value' => 'agent']
        ],
        'agent_search_placeholder' => 'Search by name, email, username, or role',
        'agent_first' => 'First Name',
        'agent_last' => 'Last Name',
        'agent_email' => 'Email',
        'agent_help' => 'Start typing to search all registered staff (every role). Pick a row to lock in their details.',
        'comments_placeholder' => 'Additional comments, explanations, or missing document notes',
        'required' => 'Required',
        'save_error_title' => 'Unable to save this step',
        'save_error_ok' => 'OK',
    ],
    'fr' => [
        'title' => 'Formulaire de Demande d\'Étudiant',
        'next' => 'Suivant',
        'prev' => 'Retour',
        'step1_title' => 'Choix d\'études',
        'step1_desc' => 'Suivez les étapes : régions, universités, puis un ou plusieurs niveaux et programmes par université. Vous pouvez sélectionner plusieurs universités et plusieurs programmes.',
        'step1_flow_1' => 'Région',
        'step1_flow_2' => 'Université',
        'step1_flow_3' => 'Niveau',
        'step1_flow_4' => 'Programme',
        'step1_regions_label' => 'Zones d\'études',
        'step1_regions_placeholder' => 'Sélectionnez une ou plusieurs régions',
        'step1_regions_help' => 'Commencez par indiquer où vous souhaitez étudier. Plusieurs régions sont possibles.',
        'step1_add_panel_title' => 'Ajouter une université',
        'step1_add_panel_help' => 'Choisissez une université parmi les régions sélectionnées, puis cliquez sur Ajouter. Répétez pour plusieurs universités.',
        'step1_add_university_btn' => 'Ajouter',
        'step1_pick_university' => 'Choisir une université…',
        'step1_add_level' => 'Ajouter un autre niveau',
        'step1_remove_row' => 'Retirer cette ligne',
        'step1_remove_uni' => 'Retirer l\'université',
        'step1_choices_heading' => 'Vos universités et programmes',
        'step1_filter_summary' => 'Filtrer la liste (facultatif)',
        'step1_search_label' => 'Filtrer la liste (facultatif)',
        'step1_search_placeholder' => 'Rechercher une université ou un programme…',
        'step1_clear' => 'Effacer',
        'step1_empty_no_region' => 'Sélectionnez au moins une région ci-dessus pour continuer.',
        'step1_empty_add_uni' => 'Utilisez « Ajouter une université » ci-dessous pour choisir vos établissements. Vous pouvez en ajouter plusieurs et plusieurs programmes par université.',
        'step1_cart_title' => 'Vos choix',
        'step1_cart_help' => 'Récapitule les universités pour lesquelles au moins un programme est sélectionné.',
        'step1_university' => 'Université',
        'step1_level' => 'Niveau du programme',
        'step1_program' => 'Programmes',
        'step1_remove' => 'Supprimer',
        'step1_agent_panel_title' => 'Parrainage et consultant',
        'step1_agent_panel_desc' => 'Indiquez comment vous nous avez connus. Si un membre de notre équipe vous a orienté, sélectionnez-le ici avant de télécharger des documents pour le remplissage automatique IA.',
        'step1_assign_title' => 'Assigner à (personnel)',
        'step1_assign_desc' => 'Facultatif : choisissez un membre du personnel. À la soumission, il recevra un courriel avec le résumé du candidat et toutes les pièces jointes.',
        'staff_assign_placeholder' => 'Rechercher le personnel par nom ou e-mail',
        'staff_assign_hint' => 'Des suggestions apparaissent au clic ; saisissez au moins 2 lettres pour chercher tout le personnel.',
        'staff_assign_clear' => 'Effacer',
        'doc_prepare_title' => 'Documents à Préparer Avant de Commencer',
        'doc_prepare_desc' => 'Pour assurer un processus de demande fluide, veuillez avoir les documents suivants disponibles. Ils vous seront demandés lors des étapes de la demande.',
        'doc_formats' => 'Formats supportés : PDF, JPG, PNG',
        'doc_list' => ['Passeport Valide', 'Diplômes / Relevés de Notes Académiques', 'Certificat de Lycée', 'CV / Curriculum Vitae', 'Lettre(s) de Recommandation', 'Lettre de Motivation / Déclaration Personnelle', 'Certificat de Compétence en Anglais', 'Certificat de Naissance', 'Preuve de Demande / Paiement'],
        
        // Step 2
        'step2_title' => 'Informations Personnelles',
        'step2_desc' => 'Entrez les détails exactement comme indiqué sur votre passeport.',
        'smart_autofill_title' => 'Remplissage intelligent par IA',
        'smart_autofill_desc' => 'Après avoir choisi vos études, téléchargez le passeport, le CV, les relevés, le diplôme, le certificat de naissance ou d\'autres documents utiles. L\'IA extrait les informations de l\'étudiant puis route chaque fichier reconnu vers les champs de pièces jointes existants.',
        'smart_autofill_button' => 'Ajouter des documents',
        'smart_autofill_start_button' => 'Lancer l analyse',
        'smart_autofill_formats' => 'Formats supportés : PDF, DOCX, JPG, JPEG, PNG, WEBP',
        'smart_autofill_hint' => 'Meilleurs résultats : téléchargez ensemble le passeport, le CV et les derniers documents académiques.',
        'smart_autofill_gate' => 'Choisissez d abord au moins un programme d etudes. Ensuite, ajoutez les documents a analyser.',
        'smart_autofill_ready' => 'Choix d etudes selectionne. Ajoutez un document maintenant puis d autres plus tard avant de lancer l analyse manuellement.',
        'smart_autofill_processing' => 'Analyse des documents et extraction des informations de l\'étudiant…',
        'smart_autofill_uploading' => 'Application des données extraites et rattachement des documents reconnus…',
        'smart_autofill_success' => 'Remplissage terminé. Vérifiez les champs avant de continuer.',
        'smart_autofill_partial' => 'Le remplissage est terminé avec quelques avertissements. Veuillez vérifier les notes ci-dessous.',
        'smart_autofill_error' => 'Le remplissage a échoué. Réessayez avec des documents plus clairs.',
        'smart_autofill_need_draft' => 'Veuillez d\'abord enregistrer le brouillon afin que les documents soient rattachés à cette demande.',
        'smart_autofill_queue_title' => 'Documents en attente',
        'smart_autofill_queue_empty' => 'Aucun document selectionne pour le moment. Ajoutez un ou plusieurs fichiers puis lancez l analyse quand vous etes pret.',
        'smart_autofill_queue_ready' => 'Les documents sont en attente. Cliquez sur Lancer l analyse quand vous etes pret a aller jusqu a la soumission finale.',
        'smart_autofill_queue_count' => 'document(s) pret(s) pour l analyse',
        'smart_autofill_results_title' => 'Documents reconnus',
        'smart_autofill_warnings_title' => 'Avertissements',
        'smart_autofill_existing_note' => 'Ces fichiers ne sont pas stockés dans un nouvel endroit. Ils sont routés vers les champs de pièces jointes existants ci-dessous.',
        'smart_autofill_stage_queue' => 'Documents en attente',
        'smart_autofill_stage_draft' => 'Creation du brouillon',
        'smart_autofill_stage_batch' => 'Analyse de tous les documents',
        'smart_autofill_stage_fill' => 'Application des details extraits',
        'smart_autofill_stage_route' => 'Routage vers les champs existants',
        'smart_autofill_stage_save' => 'Enregistrement des donnees du formulaire',
        'smart_autofill_stage_submit' => 'Soumission de la demande',
        'smart_autofill_analyzing_doc' => 'Analyse du document {current} sur {total} : {name}…',
        'smart_autofill_analysis_intro' => 'Jusqu a 5 documents en parallele sur l API Gemini — en general moins de 90 secondes.',
        'smart_autofill_debug_title' => 'Details de debug du lot',
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'email' => 'Email',
        'phone_label' => 'Numéro de Téléphone',
        'phone_placeholder' => 'Entrez le numéro de téléphone',
        'phone_help' => 'Sélectionnez le pays pour remplir automatiquement le code international.',
        'gender' => 'Genre',
        'gender_options' => ['Homme', 'Femme'],
        'dob' => 'Date de Naissance',
        'passport' => 'Numéro de Passeport',
        'national_id' => 'Carte d\'Identité Nationale',
        'birth_country' => 'Pays de naissance',
        'city_birth' => 'Ville de Naissance',
        'nationality' => 'Nationalité',
        'second_nationality' => 'Deuxième nationalité',
        
        // Step 3
        'step3_title' => 'Adresse & Famille',
        'step3_desc' => 'Fournissez votre adresse actuelle et les informations parentales.',
        'address1' => 'Adresse Ligne 1',
        'address2' => 'Adresse ligne 2 (facultatif)',
        'city' => 'Ville',
        'state' => 'État / Province',
        'postal' => 'Code Postal',
        'parents_title' => 'Informations des Parents',
        'father_first' => 'Prénom du Père',
        'father_last' => 'Nom du Père',
        'mother_first' => 'Prénom de la Mère',
        'mother_last' => 'Nom de la Mère',
        
        // Step 4
        'step4_title' => 'Contact d\'Urgence',
        'step4_desc' => 'Fournissez les détails d\'une personne que nous pouvons contacter en cas d\'urgence.',
        'emergency_first' => 'Prénom',
        'emergency_last' => 'Nom',
        'emergency_email' => 'Email',
        'emergency_phone_label' => 'Téléphone d\'Urgence',
        'emergency_phone_help' => 'Sélectionnez le pays pour remplir automatiquement le code et valider la longueur du numéro.',
        'relationship' => 'Relation',
        'same_address' => 'Même Adresse?',
        'same_address_options' => ['Oui', 'Non'],
        
        // Step 5
        'step5_title' => 'Éducation & Antécédents',
        'step5_desc' => 'Fournissez des détails sur votre éducation précédente et vos antécédents académiques.',
        'institution_name' => 'Nom de l\'Établissement',
        'institution_name_placeholder' => 'ex. Lycée de Kigali',
        'institution_street' => 'Rue de l\'Établissement',
        'institution_street_placeholder' => 'ex. KN 123 Rue',
        'institution_city' => 'Ville de l\'Établissement',
        'institution_city_placeholder' => 'ex. Kigali',
        'institution_province' => 'Province / État de l\'Établissement',
        'institution_province_placeholder' => 'ex. Ville de Kigali',
        'institution_country' => 'Pays de l\'Établissement',
        'institution_postal' => 'Code Postal',
        'institution_postal_placeholder' => 'ex. 00000',
        'language' => 'Langue d\'Enseignement',
        'language_options' => ['Anglais', 'Français', 'Autre'],
        'study_start' => 'Date de Début des Études',
        'graduation' => 'Date d\'Obtention du Diplôme',
        'study_gap' => 'Interruption dans les Études?',
        'study_gap_placeholder' => 'Expliquez la raison et la durée de l\'interruption',
        'secondary_school' => 'École Secondaire Supplémentaire?',
        'secondary_school_placeholder' => 'Fournissez le nom de l\'école, l\'emplacement et les années fréquentées',
        'post_secondary' => 'Éducation Post-Secondaire?',
        'post_secondary_placeholder' => 'Décrivez l\'établissement, le programme et la durée',
        'criminal_history' => 'Antécédents Judiciaires?',
        'criminal_history_placeholder' => 'Infraction, date et résultat',
        'disability' => 'Handicap?',
        'disability_placeholder' => 'Décrivez le handicap et les aménagements requis',
        'visa_rejection' => 'Refus de Visa Antérieur?',
        'visa_rejection_placeholder' => 'Pays, année et raison du refus',
        'yes_no_options' => ['Oui', 'Non'],
        
        // Step 6
        'step6_title' => 'Destination & Finance',
        'step6_desc' => 'Examinez votre destination d\'études et indiquez comment vos frais d\'éducation seront couverts.',
        'destination_title' => 'Destination d\'Études',
        'preferred_destination' => 'Destination Préférée',
        'preferred_help' => 'Rempli automatiquement en fonction de la région sélectionnée à l\'Étape 1.',
        'loan_destination' => 'Destination du Prêt',
        'loan_destination_help' => 'Par défaut, correspond à votre destination d\'études préférée.',
        'other_loan_destination' => 'Autre Destination de Prêt (Optionnelle)',
        'other_loan_placeholder' => 'Destination différente couverte par le prêt',
        'finance_title' => 'Responsabilité Financière',
        'tuition' => 'Qui Paie les Frais de Scolarité?',
        'living_cost' => 'Qui Paie le Coût de la Vie?',
        'travel' => 'Qui Paie les Frais de Voyage?',
        'finance_options' => ['Soi-même', 'Parents', 'Sponsor', 'Prêt'],
        
        // Step 7
        'step7_title' => 'Téléchargement des Documents',
        'step7_desc' => 'Téléchargez des documents clairs et lisibles. Formats supportés : PDF, JPG, PNG. Les fichiers sont validés automatiquement.',
        'degree_transcripts' => 'Diplômes / Relevés de Notes',
        'high_school' => 'Certificat de Lycée',
        'passport_doc' => 'Passeport Valide',
        'cv_resume' => 'CV / Curriculum Vitae',
        'recommendation' => 'Lettre(s) de Recommandation',
        'personal_statement' => 'Lettre de Motivation / Déclaration Personnelle',
        'english_certificate' => 'Certificat de Compétence en Anglais',
        'birth_certificate' => 'Certificat de Naissance',
        'payment_proof' => 'Preuve de Demande / Paiement',
        'drop_transcripts' => 'Déposez les relevés ici',
        'drop_certificate' => 'Déposez le certificat ici',
        'drop_passport' => 'Déposez le passeport ici',
        'drop_cv' => 'Déposez le CV ici',
        'drop_recommendation' => 'Déposez les lettres de recommandation',
        'drop_statement' => 'Déposez le document ici',
        'multiple_files' => 'Plusieurs fichiers autorisés',
        'single_file' => 'Fichier unique',
        'doc_progress_title' => 'Progression de l extraction IA',
        'doc_debug_title' => 'Details de debug',
        'doc_stage_prepare' => 'Preparation du telechargement',
        'doc_stage_upload' => 'Telechargement du document',
        'doc_stage_extract' => 'Extraction du texte',
        'doc_stage_ai' => 'Analyse IA en cours',
        'doc_stage_parse' => 'Lecture des details extraits',
        'doc_stage_save' => 'Enregistrement de la piece jointe',
        'referral_title' => 'Comment nous avez-vous connus?',
        'referral_help' => 'Cela nous aide à attribuer le bon consultant à votre demande.',
        'referral_options' => [
            ['text' => 'En ligne / Site Web / Réseaux Sociaux', 'value' => 'online'],
            ['text' => 'Par l\'intermédiaire d\'un Agent', 'value' => 'agent']
        ],
        'agent_search_placeholder' => 'Rechercher par nom, e-mail, identifiant ou rôle',
        'agent_first' => 'Prénom',
        'agent_last' => 'Nom',
        'agent_email' => 'Email',
        'agent_help' => 'Commencez à taper pour rechercher tout le personnel enregistré (tous les rôles). Choisissez une ligne pour confirmer les coordonnées.',
        'comments_placeholder' => 'Commentaires supplémentaires, explications ou notes sur les documents manquants',
        'required' => 'Obligatoire',
        'save_error_title' => 'Enregistrement impossible',
        'save_error_ok' => 'OK',
    ]
];

$t = $text[$lang];
?>
<!doctype html>
<html lang="<?php echo $lang; ?>">
<head>
<meta charset="utf-8">
<title><?php echo $t['title']; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Language Switcher CSS -->
<style>
.language-switcher {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
}
.language-btn {
    background: white;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 6px 12px;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.language-btn.active {
    background: #0d6efd;
    color: white;
    border-color: #0d6efd;
}
.language-btn:hover {
    background: #f8f9fa;
}
.language-btn.active:hover {
    background: #0b5ed7;
}
</style>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

<!-- Your existing styles (keep all CSS from your original form) -->
<style>
/* =====================================================
   GLOBAL RESET & BASE
===================================================== */
* {
  box-sizing: border-box;
}

body {
  background-color: #f5f7fb;
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
  color: #212529;
}

/* =====================================================
   CARD & LAYOUT
===================================================== */
.card {
  border-radius: 14px;
  border: none;
}

.card-body {
  padding: 2rem;
}

/* =====================================================
   STEP VISIBILITY
===================================================== */
.step {
  display: none;
}

.step.active {
  display: block;
}

/* =====================================================
   PROGRESS BAR (CLEAN & MODERN)
===================================================== */
.progress-step {
  display: flex;
  gap: 8px;
  margin-bottom: 1.75rem;
}

.progress-step span {
  flex: 1;
  height: 6px;
  background: #dee2e6;
  border-radius: 999px;
  transition: background-color .3s ease;
}

.progress-step span.active {
  background: linear-gradient(90deg, #0d6efd, #4f8cff);
}

/* =====================================================
   LABELS
===================================================== */
.form-label {
  font-weight: 600;
  margin-bottom: 6px;
  font-size: 14px;
  color: #343a40;
}

/* =====================================================
   INPUTS & SELECTS (BASE)
===================================================== */
.form-control,
.form-select {
  min-height: 48px;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid #dfe3eb;
  background-color: #fff;
  font-size: 14px;
  transition: border-color .2s ease, box-shadow .2s ease;
}

.form-control::placeholder {
  color: #adb5bd;
}

.form-control:focus,
.form-select:focus {
  border-color: #0d6efd;
  box-shadow: 0 0 0 3px rgba(13,110,253,.15);
  outline: none;
}

/* Disabled */
.form-control:disabled,
.form-select:disabled {
  background-color: #f1f3f6;
  color: #6c757d;
  cursor: not-allowed;
}

/* =====================================================
   SELECT2 – CORE FIX (NO MORE CUT EDGES)
===================================================== */
.select2-container {
  width: 100% !important;
}

/* Main selection */
.select2-container--bootstrap-5 .select2-selection {
  min-height: 48px;
  padding: 6px 10px;
  border-radius: 10px;
  border: 1px solid #dfe3eb;
  display: flex;
  align-items: center;
  background-color: #fff;
}

/* Placeholder text */
.select2-container--bootstrap-5 .select2-selection__placeholder {
  color: #adb5bd;
  font-size: 14px;
}

/* Focus state */
.select2-container--bootstrap-5.select2-container--focus .select2-selection {
  border-color: #0d6efd;
  box-shadow: 0 0 0 3px rgba(13,110,253,.15);
}

/* Disabled Select2 */
.select2-container--bootstrap-5.select2-container--disabled .select2-selection {
  background-color: #f1f3f6;
  color: #6c757d;
}

/* =====================================================
   SELECT2 – MULTI SELECT (PROGRAMS FIX)
===================================================== */
.select2-container--bootstrap-5 .select2-selection--multiple {
  padding: 6px 8px;
  gap: 6px;
  align-items: center;
}

/* Selected chips */
.select2-container--bootstrap-5 .select2-selection__choice {
  background: linear-gradient(135deg, #0d6efd, #4f8cff);
  color: #fff;
  border: none;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 12px;
  display: flex;
  align-items: center;
}

/* Remove "x" spacing issue */
.select2-selection__choice__remove {
  margin-right: 6px;
  color: #fff;
  opacity: .8;
}

.select2-selection__choice__remove:hover {
  opacity: 1;
}

/* =====================================================
   SELECT2 DROPDOWN (CLEAN & ELEGANT)
===================================================== */
.select2-container--bootstrap-5 .select2-dropdown {
  border-radius: 12px;
  border: 1px solid #dfe3eb;
  box-shadow: 0 10px 30px rgba(0,0,0,.08);
  overflow: hidden;
}

/* Options list */
.select2-container--bootstrap-5 .select2-results__options {
  max-height: 240px;
  overflow-y: auto;
}

/* Option */
.select2-container--bootstrap-5 .select2-results__option {
  padding: 12px 16px;
  font-size: 14px;
  cursor: pointer;
}

/* Hover */
.select2-container--bootstrap-5 .select2-results__option--highlighted {
  background-color: #0d6efd;
  color: #fff;
}

/* =====================================================
   BUTTONS
===================================================== */
.btn {
  border-radius: 10px;
  padding: 10px 18px;
  font-weight: 600;
}

.btn-primary {
  background: linear-gradient(135deg, #0d6efd, #4f8cff);
  border: none;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #0b5ed7, #3f7be0);
}

.btn-secondary {
  background-color: #e9ecef;
  border: none;
  color: #343a40;
}

/* =====================================================
   FILE INPUTS
===================================================== */
.upload {
  border-radius: 10px;
}

/* =====================================================
   SMALL SCREENS
===================================================== */
@media (max-width: 768px) {
  .card-body {
    padding: 1.25rem;
  }
}
/* =====================================================
   FINAL FIX – SELECT2 PROGRAMS MULTI-SELECT
   (Fixes broken height, chips, cursor, overflow)
===================================================== */

/* Stop flex breaking the layout */
.select2-container--bootstrap-5 .select2-selection--multiple {
  display: block !important;
  min-height: 48px;
  padding: 8px 12px;
  line-height: 1.4;
  overflow: hidden;
}

/* Proper wrapping for selected items */
.select2-container--bootstrap-5
.select2-selection--multiple
.select2-selection__rendered {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  padding: 0;
  margin: 0;
}

/* Selected program chips */
.select2-container--bootstrap-5
.select2-selection__choice {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  font-size: 12px;
  border-radius: 999px;
  white-space: nowrap;
}

/* Remove button alignment */
.select2-container--bootstrap-5
.select2-selection__choice__remove {
  margin-right: 6px;
  font-weight: 600;
}

/* Inline search input FIX (this was the big problem) */
.select2-container--bootstrap-5
.select2-search--inline
.select2-search__field {
  min-width: 120px;
  height: 32px;
  margin: 0;
  padding: 0;
  line-height: 32px;
  border: none !important;
  outline: none;
  box-shadow: none !important;
}

/* Prevent giant height when many programs */
.select2-container--bootstrap-5
.select2-selection--multiple {
  max-height: 120px;
  overflow-y: auto;
}
/* =====================================================
   SMART ROUNDED FILE UPLOAD PROGRESS
===================================================== */

.upload-progress {
  width: 100%;
  height: 12px;
  background: #e9ecef;
  border-radius: 999px;
  overflow: hidden;
  display: none;
}

.upload-bar {
  height: 100%;
  width: 0%;
  background: linear-gradient(90deg, #0d6efd, #4f8cff);
  border-radius: 999px;
  transition: width .35s ease;
  position: relative;
}

.upload-bar span {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 10px;
  font-weight: 600;
  color: #fff;
  opacity: 0;
  transition: opacity .3s ease;
}

/* Show percentage when progress starts */
.upload-progress.active .upload-bar span {
  opacity: 1;
}

.doc-progress-panel {
  display: none;
  margin-top: 1rem;
  padding: 1rem 1.1rem;
  border: 1px solid #dbeafe;
  border-radius: 16px;
  background: #f8fbff;
}

.doc-progress-panel.active {
  display: block;
}

.doc-stage-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 10px;
  margin-top: 14px;
}

.doc-stage-item {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  padding: 10px 12px;
  font-size: 12px;
  color: #64748b;
}

.doc-stage-item strong {
  display: block;
  color: #0f172a;
  font-size: 13px;
  margin-bottom: 2px;
}

.doc-stage-item.is-active {
  border-color: #60a5fa;
  background: #eff6ff;
  box-shadow: 0 0 0 3px rgba(96, 165, 250, .15);
}

.doc-stage-item.is-done {
  border-color: #86efac;
  background: #f0fdf4;
  color: #166534;
}

.doc-stage-item.is-error {
  border-color: #fca5a5;
  background: #fef2f2;
  color: #991b1b;
}

.doc-debug-wrap {
  margin-top: 14px;
}

.doc-debug-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 8px;
}

.doc-debug-list li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
  padding: 9px 11px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  font-size: 12px;
}

.doc-debug-list span:first-child {
  font-weight: 700;
  color: #334155;
}
/* =====================================================
   UI DEPTH, CONTAINERS & VISUAL HIERARCHY (PRODUCTION)
   Paste at the END of your <style>
===================================================== */

/* Page background – soft, non-flat */
body {
  background: linear-gradient(180deg, #f3f6fb 0%, #eef2f7 100%);
}

/* Main application container (card) */
.card {
  background: #ffffff;
  border-radius: 18px;
  border: 1px solid #e6ebf2;
  box-shadow:
    0 10px 28px rgba(0, 0, 0, 0.04),
    0 4px 10px rgba(0, 0, 0, 0.025);
}

/* Inner spacing consistency */
.card-body {
  padding: 2rem;
}
/* =====================================================
   STEP CONTAINER – STRONG VISUAL SEPARATION
===================================================== */

.step-section {
  position: relative;
  background: #ffffff;
  border-radius: 18px;
  padding: 2.25rem;
  margin-bottom: 2rem;

  border: 1px solid #e2e8f0;

  box-shadow:
    0 12px 28px rgba(15, 23, 42, 0.08),
    0 4px 10px rgba(15, 23, 42, 0.04);
}

/* Accent bar on the left (PRO LOOK) */
.step-section::before {
  content: "";
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 6px;
  background: linear-gradient(180deg, #0d6efd, #4f8cff);
  border-radius: 18px 0 0 18px;
}

/* Step titles */
.step-section h5 {
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
}

/* Step description */
.step-section p {
  font-size: 13px;
  color: #64748b;
}

/* Form fields – subtle contrast improvement */
.form-control,
.form-select {
  background-color: #ffffff;
  border: 1px solid #dbe2ea;
}

/* Hover feedback */
.form-control:hover,
.form-select:hover {
  border-color: #c7d2e2;
}

/* Navigation separator (Back / Next area) */
.form-nav {
  border-top: 1px solid #edf1f7;
  padding-top: 1.25rem;
  margin-top: 2rem;
}

/* Mobile polish */
@media (max-width: 768px) {
  .container {
    padding-left: 12px;
    padding-right: 12px;
  }

  .card {
    border-radius: 14px;
  }

  .card-body {
    padding: 1.25rem;
  }
.card {
  background: #f8fafc;
}

  
}
/* =====================================================
   STUDY SELECTION – MULTI UNIVERSITY UI
===================================================== */

.study-choices {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

/* Study choice card */
.study-choice {
  border-radius: 16px;
  padding: 1.25rem 1.5rem;
  background: #ffffff;
  border: 1px solid #e2e8f0;

  box-shadow:
    0 10px 20px rgba(15, 23, 42, 0.06),
    0 3px 8px rgba(15, 23, 42, 0.04);

  position: relative;
}

/* Header row */
.study-choice-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

/* Region badge */
.region-badge {
  font-size: 12px;
  padding: 6px 10px;
  border-radius: 999px;
  background: linear-gradient(135deg, #0d6efd, #4f8cff);
}

/* Remove button */
.btn-remove {
  background: transparent;
  border: none;
  color: #dc3545;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
}

.btn-remove:hover {
  text-decoration: underline;
}

/* Select spacing consistency */
.study-choice .form-select {
  min-height: 46px;
}

/* Mobile polish */
@media (max-width: 768px) {
  .study-choice {
    padding: 1.1rem;
  }
}
/* ================================
   STUDY SELECTION – PRO UI
================================ */

.study-choices {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.study-choice {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow:
    0 6px 16px rgba(15, 23, 42, 0.05),
    0 2px 6px rgba(15, 23, 42, 0.03);
}

.study-choice-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.region-badge {
  background: linear-gradient(135deg, #2563eb, #4f46e5);
  font-size: 12px;
  font-weight: 600;
  padding: 6px 12px;
  border-radius: 999px;
}

.btn-remove {
  background: none;
  border: none;
  color: #ef4444;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-remove:hover {
  text-decoration: underline;
}

/* —— Choice of studies: layout + responsive —— */
.study-selection {
  max-width: 100%;
  overflow-x: clip;
}
.study-selection-header {
  text-align: left;
}
.study-step-title {
  color: #0f172a;
  letter-spacing: -0.02em;
  font-size: clamp(1.15rem, 4vw, 1.35rem);
}
.study-step-lead {
  line-height: 1.55;
  max-width: 40rem;
}
/* Flow: vertical on phone, row on tablet+ */
.study-flow-timeline {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
  position: relative;
}
.study-flow-timeline-item {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  padding: 0.5rem 0 0.5rem 0.25rem;
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #475569;
  border-left: 3px solid #e2e8f0;
  padding-left: 0.85rem;
  margin-left: 0.6rem;
}
.study-flow-timeline-item:last-child {
  border-left-color: transparent;
}
.study-flow-timeline-item.is-active {
  color: #4338ca;
}
.study-flow-timeline-item.is-active .study-flow-timeline-num {
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff;
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
}
.study-flow-timeline-num {
  flex-shrink: 0;
  width: 1.5rem;
  height: 1.5rem;
  margin-left: -1.6rem;
  margin-top: 0.05rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 800;
  border-radius: 50%;
  background: #f1f5f9;
  color: #64748b;
  border: 2px solid #fff;
  box-shadow: 0 0 0 1px #e2e8f0;
}
.study-flow-timeline-text {
  padding-top: 0.1rem;
  line-height: 1.35;
}
@media (min-width: 576px) {
  .study-flow-timeline {
    flex-direction: row;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 0.5rem;
    border-left: none;
    margin-left: 0;
  }
  .study-flow-timeline-item {
    flex: 1 1 calc(50% - 0.5rem);
    min-width: 140px;
    border-left: none;
    margin-left: 0;
    padding: 0.5rem 0.65rem;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    align-items: center;
    gap: 0.5rem;
  }
  .study-flow-timeline-num {
    margin-left: 0;
  }
}
@media (min-width: 992px) {
  .study-flow-timeline {
    flex-wrap: nowrap;
    gap: 0.65rem;
  }
  .study-flow-timeline-item {
    flex: 1 1 0;
  }
}

.study-selection-stack {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
@media (min-width: 768px) {
  .study-selection-stack {
    gap: 1.25rem;
  }
}

.study-panel {
  position: relative;
  padding: 1rem 1rem;
  border-radius: 14px;
  background: #fff;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
}
@media (min-width: 768px) {
  .study-panel {
    padding: 1.25rem 1.35rem;
    border-radius: 16px;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
  }
}
.study-panel--accent {
  border-color: #c7d2fe;
  box-shadow: 0 4px 20px rgba(99, 102, 241, 0.1);
  background: linear-gradient(180deg, #ffffff 0%, #fafbff 100%);
}
.study-panel--soft {
  border-color: #bfdbfe;
  background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
}
.study-panel--muted {
  border-style: dashed;
  border-color: #cbd5e1;
  background: #fafafa;
}

/* Optional filter: <details> — compact on small screens */
.study-filter-details.study-panel {
  padding-top: 0.35rem;
}
.study-filter-summary {
  list-style: none;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.9rem;
  color: #334155;
  padding: 0.65rem 0.25rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  user-select: none;
}
.study-filter-summary::-webkit-details-marker {
  display: none;
}
.study-filter-summary::after {
  content: "";
  width: 0.5rem;
  height: 0.5rem;
  margin-left: auto;
  border-right: 2px solid #94a3b8;
  border-bottom: 2px solid #94a3b8;
  transform: rotate(45deg);
  transition: transform 0.2s;
}
.study-filter-details[open] .study-filter-summary::after {
  transform: rotate(225deg);
  margin-top: 0.2rem;
}
.study-filter-summary-icon {
  font-size: 1.1rem;
  opacity: 0.85;
}
@media (min-width: 768px) {
  .study-filter-details.study-panel {
    padding-top: 1.25rem;
  }
  .study-filter-summary {
    display: none;
  }
  .study-filter-details .study-filter-body {
    display: block !important;
  }
}

.study-panel-badge {
  position: absolute;
  top: -10px;
  left: 14px;
  background: linear-gradient(135deg, #4f46e5, #6366f1);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  padding: 5px 14px;
  border-radius: 999px;
  box-shadow: 0 6px 16px rgba(79, 70, 229, 0.35);
}
@media (max-width: 575px) {
  .study-panel-badge {
    left: 10px;
    font-size: 10px;
    padding: 4px 10px;
  }
}

.study-touch-btn {
  min-height: 2.75rem;
  padding-top: 0.55rem;
  padding-bottom: 0.55rem;
}
.study-touch-control {
  min-height: 2.75rem;
}

/* Select2 full width inside panels */
.study-selection .select2-container {
  width: 100% !important;
  max-width: 100%;
}
.study-select-fullwidth {
  width: 100%;
}

/* Regions: keep native + Select2 in sync but hide the Select2 widget (custom picker only; no I-beam cursor) */
#regionStep #regions.select-smart + .select2-container {
  display: none !important;
}
#regionStep #regions.select-smart + .select2-container .select2-search--inline,
#regionStep #regions.select-smart + .select2-container .select2-search--dropdown {
  display: none !important;
}

.regions-picker-face:focus {
  border-color: #86b7fe;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
  outline: 0;
}

.study-choices-section {
  min-width: 0;
}

.study-summary-card {
  padding: 1rem 1rem;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}
@media (min-width: 768px) {
  .study-summary-card {
    padding: 1.15rem 1.35rem;
  }
}
.study-summary-aside {
  order: 10;
}
.study-summary-pill {
  background: #eef2ff !important;
  color: #4338ca !important;
  font-weight: 600;
}

.study-cart-list .list-group-item {
  padding-left: 0;
  padding-right: 0;
  border-color: #f1f5f9;
  word-break: break-word;
}

.study-university-card {
  border-radius: 14px;
  padding: 1rem 1rem;
  border: 1px solid #e2e8f0;
  background: #fff;
  box-shadow:
    0 8px 20px rgba(15, 23, 42, 0.06),
    0 2px 6px rgba(15, 23, 42, 0.03);
}
@media (min-width: 768px) {
  .study-university-card {
    border-radius: 18px;
    padding: 1.25rem 1.5rem;
  }
}
.study-uni-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.5rem 0.75rem;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 0.75rem;
}
.study-uni-body {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.study-uni-body .form-label,
.study-level-row-card .form-label {
  font-weight: 600;
  color: #334155;
}
.study-uni-header .btn-remove-uni {
  min-height: 2.25rem;
  margin-left: auto;
  text-align: right;
  white-space: normal;
  max-width: 100%;
}
.study-uni-body .select2-container,
.study-level-row-card .select2-container {
  width: 100% !important;
}
.study-uni-body .select2-container .select2-selection--single,
.study-level-row-card .select2-container .select2-selection--single,
.study-level-row-card .select2-container .select2-selection--multiple {
  min-height: 2.75rem;
}
.study-level-rows {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}
@media (min-width: 768px) {
  .study-level-rows {
    gap: 1rem;
  }
}
.study-level-row-card {
  padding: 0.85rem 0.75rem;
  border-radius: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}
@media (min-width: 768px) {
  .study-level-row-card {
    padding: 1rem 1rem 0.75rem;
  }
}
.study-level-row-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 0.25rem;
}
.btn-add-level {
  font-weight: 600;
  width: 100%;
  min-height: 2.5rem;
}
@media (min-width: 576px) {
  .btn-add-level {
    width: auto;
  }
}

.study-empty {
  border: 1px dashed #cbd5e1;
  border-radius: 14px;
  color: #64748b;
  background: linear-gradient(180deg, #fafbfc 0%, #f1f5f9 100%);
  max-width: 100%;
  margin-left: 0;
  margin-right: 0;
  padding: 1.25rem 1rem;
  text-align: left;
}
@media (min-width: 768px) {
  .study-empty {
    text-align: center;
    max-width: 640px;
    margin-left: auto;
    margin-right: auto;
    padding: 1.75rem;
  }
}

@media (max-width: 575px) {
  #studyAddUniversityPanel .row {
    --bs-gutter-y: 0.9rem;
  }
  .study-uni-header {
    align-items: stretch;
  }
  .study-uni-header .btn-remove-uni {
    width: 100%;
    margin-left: 0;
    text-align: center;
    justify-content: center;
  }
  .study-uni-body {
    gap: 0.85rem;
  }
  .study-level-row-card {
    padding: 0.9rem 0.85rem;
  }
  .study-level-row-card .row {
    --bs-gutter-y: 0.85rem;
  }
  .study-selection .form-text {
    font-size: 0.8rem;
  }
  .study-remove-row-btn {
    width: 100%;
  }
  .study-level-row-actions {
    justify-content: stretch;
  }
}

/* ===============================
   REGION CHIPS – SMART CLOSE
================================ */

.region-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: linear-gradient(135deg, #0d6efd, #4f8cff);
  color: #fff;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}

.region-close {
  cursor: pointer;
  font-size: 14px;
  line-height: 1;
  opacity: 0.85;
}

.region-close:hover {
  opacity: 1;
}

#agent_first_name[readonly],
#agent_last_name[readonly],
#agent_email[readonly] {
    background-color: #f1f3f6;
    cursor: not-allowed;
}

.smart-autofill-card {
  border: 1px solid #c7d2fe;
  border-radius: 18px;
  padding: 1.15rem 1.25rem;
  background: linear-gradient(135deg, #f8fbff 0%, #eef2ff 100%);
  box-shadow: 0 12px 28px rgba(79, 70, 229, 0.08);
}

.smart-autofill-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 10px;
}

.smart-autofill-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  background: #dbeafe;
  color: #1d4ed8;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .03em;
  text-transform: uppercase;
}

.smart-autofill-queue {
  display: none;
  margin-top: 14px;
  padding: 14px 16px;
  border: 1px solid #dbeafe;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.88);
}

.smart-autofill-queue.is-visible {
  display: block;
}

.smart-autofill-queue-list {
  list-style: none;
  margin: 10px 0 0;
  padding: 0;
  display: grid;
  gap: 8px;
}

.smart-autofill-queue-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  font-size: 13px;
  color: #0f172a;
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
  font-size: 18px;
  line-height: 1;
  padding: 0 2px;
}

.smart-autofill-remove:hover {
  color: #991b1b;
}

.smart-autofill-progress-panel {
  display: none;
  align-items: center;
  gap: 18px;
  margin-top: 14px;
  padding: 16px 18px;
  border: 1px solid #dbeafe;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.9);
  transition: box-shadow 0.35s ease, border-color 0.35s ease;
}

.smart-autofill-progress-panel.active {
  display: flex;
}

/* High-attention “working” state (spinning / in progress) */
.smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) {
  border: 2px solid transparent;
  background:
    linear-gradient(#fff, #fff) padding-box,
    linear-gradient(125deg, #38bdf8, #6366f1, #a855f7, #2563eb, #06b6d4) border-box;
  box-shadow:
    0 0 0 1px rgba(99, 102, 241, 0.12),
    0 0 28px rgba(59, 130, 246, 0.35),
    0 14px 36px rgba(79, 70, 229, 0.2);
  animation: smartAutofillPanelPulse 2.4s ease-in-out infinite;
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
  animation: smartAutofillRipple 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
  pointer-events: none;
}

.smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-orb::after {
  content: "";
  position: absolute;
  inset: -10px;
  border-radius: 50%;
  border: 2px solid rgba(168, 85, 247, 0.35);
  animation: smartAutofillRipple 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
  animation-delay: 0.55s;
  pointer-events: none;
}

.smart-autofill-orb-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: conic-gradient(
    from 0deg,
    #2563eb,
    #22d3ee,
    #a855f7,
    #6366f1,
    #3b82f6,
    #2563eb
  );
  animation: smartAutofillSpin 0.85s linear infinite;
  filter: drop-shadow(0 0 10px rgba(59, 130, 246, 0.65))
    drop-shadow(0 0 18px rgba(139, 92, 246, 0.45));
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
  animation: smartAutofillCorePulse 1.35s ease-in-out infinite;
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
  animation: smartAutofillTitleShimmer 2.5s ease-in-out infinite;
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

.smart-autofill-bar-wrap {
  margin-top: 14px;
  width: 100%;
}

.smart-autofill-bar-track {
  height: 8px;
  border-radius: 999px;
  background: #e2e8f0;
  overflow: hidden;
}

.smart-autofill-bar-fill {
  height: 100%;
  width: 0%;
  border-radius: inherit;
  background: linear-gradient(90deg, #2563eb, #06b6d4, #8b5cf6);
  background-size: 200% 100%;
  animation: smartAutofillBarShimmer 1.4s linear infinite;
  transition: width 0.6s ease;
}

.smart-autofill-bar-meta {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-top: 8px;
  font-size: 12px;
  color: #64748b;
}

#smartAutofillLiveStatus {
  text-align: right;
  flex: 1;
  color: #334155;
  font-weight: 600;
}

@keyframes smartAutofillBarShimmer {
  0% { background-position: 0% 50%; }
  100% { background-position: 200% 50%; }
}

@keyframes smartAutofillSpin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@keyframes smartAutofillPanelPulse {
  0%, 100% {
    box-shadow:
      0 0 0 1px rgba(99, 102, 241, 0.14),
      0 0 24px rgba(59, 130, 246, 0.28),
      0 14px 36px rgba(79, 70, 229, 0.18);
  }
  50% {
    box-shadow:
      0 0 0 1px rgba(99, 102, 241, 0.22),
      0 0 38px rgba(59, 130, 246, 0.48),
      0 18px 44px rgba(79, 70, 229, 0.28);
  }
}

@keyframes smartAutofillRipple {
  0% {
    transform: scale(0.88);
    opacity: 0.85;
  }
  70% {
    opacity: 0.15;
  }
  100% {
    transform: scale(1.22);
    opacity: 0;
  }
}

@keyframes smartAutofillCorePulse {
  0%, 100% {
    transform: scale(1);
    box-shadow:
      inset 0 0 0 1px rgba(96, 165, 250, 0.35),
      inset 0 -2px 8px rgba(59, 130, 246, 0.08);
  }
  50% {
    transform: scale(1.04);
    box-shadow:
      inset 0 0 0 1px rgba(59, 130, 246, 0.55),
      inset 0 -2px 10px rgba(37, 99, 235, 0.12);
  }
}

@keyframes smartAutofillTitleShimmer {
  0%, 100% {
    filter: brightness(1);
  }
  50% {
    filter: brightness(1.15);
  }
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

  .smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-orb::before,
  .smart-autofill-progress-panel.active:not(.is-success):not(.is-warning):not(.is-danger) .smart-autofill-orb::after {
    opacity: 0;
  }
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

.smart-autofill-results strong {
  display: block;
  color: #0f172a;
  font-size: 14px;
}

.smart-autofill-results small {
  display: block;
  margin-top: 4px;
  color: #64748b;
  line-height: 1.5;
}

.smart-autofill-stage-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 10px;
  margin-top: 14px;
}

.smart-autofill-stage-item {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  padding: 10px 12px;
  font-size: 12px;
  color: #64748b;
}

.smart-autofill-stage-item strong {
  display: block;
  color: #0f172a;
  font-size: 13px;
  margin-bottom: 2px;
}

.smart-autofill-stage-item.is-active {
  border-color: #60a5fa;
  background: #eff6ff;
  box-shadow: 0 0 0 3px rgba(96, 165, 250, .15);
}

.smart-autofill-stage-item.is-done {
  border-color: #86efac;
  background: #f0fdf4;
  color: #166534;
}

.smart-autofill-stage-item.is-error {
  border-color: #fca5a5;
  background: #fef2f2;
  color: #991b1b;
}

/* =====================================================
   DOCUMENT DROPZONE (STEP 7)
===================================================== */

.doc-dropzone {
  position: relative;
  border: 2px dashed #d1d5db;
  border-radius: 16px;
  padding: 1.4rem;
  background: #f8fafc;
  text-align: center;
  cursor: pointer;
  transition: all .25s ease;
}

.doc-dropzone.multi {
  border-color: #6366f1;
  background: #eef2ff;
}

.doc-dropzone:hover {
  background: #eef2ff;
}

.doc-dropzone.dragover {
  background: #e0e7ff;
  border-color: #4f46e5;
  box-shadow: 0 0 0 4px rgba(99,102,241,.18);
}

.doc-dropzone input[type="file"] {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

.dz-content strong {
  display: block;
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
}

.dz-content span {
  font-size: 12px;
  color: #64748b;
}

/* File preview chips */
.dz-files {
  list-style: none;
  padding: 0;
  margin-top: 10px;
}

.dz-files li {
  display: inline-block;
  margin: 4px 6px 0 0;
  padding: 4px 10px;
  font-size: 12px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  color: #334155;
}

</style>
<!-- ✅ Mobile-only overrides MUST be last -->
<link rel="stylesheet" href="mobile-study-selection.css">
</head>

<body>
<!-- Language Switcher -->
<div class="language-switcher">
    <button class="language-btn <?php echo $lang === 'en' ? 'active' : ''; ?>" data-lang="en">
        <span>🇺🇸</span> English
    </button>
    <button class="language-btn <?php echo $lang === 'fr' ? 'active' : ''; ?>" data-lang="fr">
        <span>🇫🇷</span> Français
    </button>
</div>

<div class="container my-5">
<div class="card shadow-sm">
<div class="card-body">
<!-- SMART APPLICATION RETRIEVAL -->
<div class="card mb-3 border-primary">
<div class="card-body">

<strong>Resume Incomplete Application</strong>

<input
type="text"
id="resume_email_search"
class="form-control mt-2"
placeholder="Type first 3 letters of your email"
autocomplete="off"
>

<div id="resumeResults"
class="list-group mt-2 d-none"></div>

</div>
</div>
<h4 class="fw-semibold mb-3"><?php echo $t['title']; ?></h4>

<!-- ===============================
     STEP PROGRESS
=============================== -->
<div class="progress-step mb-4">
  <span class="active"></span>
  <span></span>
  <span></span>
  <span></span>
  <span></span>
  <span></span>
  <span></span>
</div>

<?php if ($studentPrefillAppId > 0): ?>
<div class="alert alert-info" role="status" style="border-left:4px solid #6366f1;background:#eef2ff;color:#1e3a8a;border-radius:8px;padding:14px 18px;margin-bottom:18px;">
  <strong>
    <?= $lang === 'fr' ? 'Reprise de votre demande' : 'Resuming your application' ?>
  </strong>
  <?php if ($studentPrefillFirstName !== ''): ?>
    &mdash;
    <?= $lang === 'fr'
        ? 'Bonjour ' . htmlspecialchars($studentPrefillFirstName) . ', vos informations sont en cours de chargement.'
        : 'Hi ' . htmlspecialchars($studentPrefillFirstName) . ', your saved details are being loaded.' ?>
  <?php else: ?>
    &mdash;
    <?= $lang === 'fr'
        ? 'Vos informations enregistrées sont en cours de chargement. Continuez les étapes jusqu’à la soumission.'
        : 'Your saved details are being loaded. Continue through the steps until final submission.' ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<form
  id="applicationForm"
  enctype="multipart/form-data"
  data-resume-app-id="<?= (int)$studentPrefillAppId ?>"
  data-resume-user-id="<?= htmlspecialchars($studentPrefillUserId) ?>"
>
<input type="hidden" name="user_id" value="<?=htmlspecialchars($_SESSION['user_id'])?>">
<input type="hidden" name="application_id" id="application_id" value="<?= (int)$studentPrefillAppId ?: '' ?>">

<!-- =====================================================
 STEP 1 : STUDY SELECTION (WITH DOCUMENT CHECKLIST)
===================================================== -->
<div class="step active">

  <!-- ===============================
       DOCUMENT CHECKLIST (STEP 1 ONLY)
  =============================== -->
  <div
    style="
      background: #f6f8ff;
      border: 1px solid #c7d2fe;
      border-left: 6px solid #4f46e5;
      border-radius: 18px;
      padding: 1.6rem 1.8rem;
      margin-bottom: 2.2rem;
      box-shadow: 0 14px 30px rgba(79,70,229,.12);
    "
  >
    <div style="display:flex; gap:16px; align-items:flex-start;">

      <!-- Icon -->
      <div
        style="
          width:44px;
          height:44px;
          border-radius:50%;
          background:linear-gradient(135deg,#4f46e5,#6366f1);
          color:#fff;
          display:flex;
          align-items:center;
          justify-content:center;
          font-size:20px;
          flex-shrink:0;
          box-shadow:0 8px 18px rgba(79,70,229,.45);
        "
      >
        📄
      </div>

      <!-- Content -->
      <div>
        <h6 style="margin:0 0 6px; font-weight:700; color:#0f172a;">
          <?php echo $t['doc_prepare_title']; ?>
        </h6>

        <p style="margin:0 0 14px; font-size:13px; color:#475569; line-height:1.6;">
          <?php echo $t['doc_prepare_desc']; ?>
        </p>

        <ul
          style="
            margin:0;
            padding-left:18px;
            font-size:13px;
            color:#1e293b;
            line-height:1.75;
          "
        >
          <?php foreach ($t['doc_list'] as $item): ?>
          <li><?php echo $item; ?></li>
          <?php endforeach; ?>
        </ul>

        <div style="margin-top:12px; font-size:12px; color:#64748b;">
          <?php echo $t['doc_formats']; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ===============================
       STEP 1 CONTENT
  =============================== -->
  <div class="step-section study-selection">

    <header class="study-selection-header mb-3 mb-lg-4">
      <h5 class="fw-semibold mb-2 study-step-title"><?php echo $t['step1_title']; ?></h5>
      <p class="text-muted small mb-3 mb-lg-4 study-step-lead">
        <?php echo $t['step1_desc']; ?>
      </p>

      <ol class="study-flow-timeline" aria-label="Steps">
        <li class="study-flow-timeline-item is-active">
          <span class="study-flow-timeline-num">1</span>
          <span class="study-flow-timeline-text"><?php echo $t['step1_flow_1']; ?></span>
        </li>
        <li class="study-flow-timeline-item">
          <span class="study-flow-timeline-num">2</span>
          <span class="study-flow-timeline-text"><?php echo $t['step1_flow_2']; ?></span>
        </li>
        <li class="study-flow-timeline-item">
          <span class="study-flow-timeline-num">3</span>
          <span class="study-flow-timeline-text"><?php echo $t['step1_flow_3']; ?></span>
        </li>
        <li class="study-flow-timeline-item">
          <span class="study-flow-timeline-num">4</span>
          <span class="study-flow-timeline-text"><?php echo $t['step1_flow_4']; ?></span>
        </li>
      </ol>
    </header>

    <div class="study-selection-stack">

      <!-- REGIONS -->
      <section class="study-panel study-panel--accent" id="regionStep" aria-labelledby="study-regions-label">
        <div class="study-panel-badge" id="regionHint">
          <?php echo $lang === 'en' ? 'Start here' : 'Commencez ici'; ?>
        </div>

        <h2 id="study-regions-label" class="h6 fw-semibold mb-2 mb-md-3"><?php echo $t['step1_regions_label']; ?></h2>

        <select
          id="regions"
          class="form-select select-smart study-select-fullwidth"
          multiple
          data-placeholder="<?php echo $t['step1_regions_placeholder']; ?>"
        ></select>

        <p class="form-text mt-2 mb-0 small"><?php echo $t['step1_regions_help']; ?></p>
      </section>

      <!-- ADD UNIVERSITY -->
      <section id="studyAddUniversityPanel" class="study-panel study-panel--soft" style="display:none;" aria-labelledby="study-add-uni-label">
        <h2 id="study-add-uni-label" class="h6 fw-semibold mb-2 mb-md-3"><?php echo $t['step1_add_panel_title']; ?></h2>
        <div class="row g-3 align-items-stretch align-items-md-end">
          <div class="col-12 col-lg-8">
            <label for="addUniversitySelect" class="form-label small text-secondary d-lg-none mb-1"><?php echo $t['step1_pick_university']; ?></label>
            <select id="addUniversitySelect" class="form-select rounded-3 study-touch-control study-select-fullwidth" data-placeholder="<?php echo htmlspecialchars($t['step1_pick_university'], ENT_QUOTES, 'UTF-8'); ?>">
              <option value=""><?php echo $t['step1_pick_university']; ?></option>
            </select>
          </div>
          <div class="col-12 col-lg-4">
            <button type="button" id="btnAddUniversity" class="btn btn-primary w-100 rounded-3 fw-semibold study-touch-btn">
              <?php echo $t['step1_add_university_btn']; ?>
            </button>
          </div>
        </div>
        <p class="form-text mb-0 mt-2 small"><?php echo $t['step1_add_panel_help']; ?></p>
      </section>

      <!-- OPTIONAL FILTER: collapsed on small screens -->
      <details class="study-filter-details study-panel study-panel--muted">
        <summary class="study-filter-summary">
          <span class="study-filter-summary-icon" aria-hidden="true">⌕</span>
          <?php echo htmlspecialchars($t['step1_filter_summary'], ENT_QUOTES, 'UTF-8'); ?>
        </summary>
        <div class="study-filter-body pt-2 pt-md-0">
          <label class="form-label fw-semibold mb-2 d-none d-md-block"><?php echo $t['step1_search_label']; ?></label>

          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label for="studySearch" class="form-label small text-secondary d-md-none mb-1"><?php echo $t['step1_search_placeholder']; ?></label>
              <input
                type="text"
                id="studySearch"
                class="form-control rounded-3 study-touch-control"
                placeholder="<?php echo $t['step1_search_placeholder']; ?>"
                autocomplete="off">
            </div>

            <div class="col-12 col-sm-6 col-md-3">
              <label for="searchLevel" class="form-label small text-secondary d-md-none mb-1"><?php echo $lang === 'en' ? 'Level' : 'Niveau'; ?></label>
              <select id="searchLevel" class="form-select rounded-3 study-touch-control study-select-fullwidth">
                <option value=""><?php echo $lang === 'en' ? 'All levels' : 'Tous les niveaux'; ?></option>
              </select>
            </div>

            <div class="col-12 col-sm-6 col-md-3 d-grid">
              <label class="form-label small text-secondary d-md-none mb-1">&nbsp;</label>
              <button
                type="button"
                id="clearSearch"
                class="btn btn-outline-secondary rounded-3 study-touch-btn">
                <?php echo $t['step1_clear']; ?>
              </button>
            </div>
          </div>

          <div id="searchResults" class="mt-2 small text-muted"></div>
        </div>
      </details>

      <!-- USER CARDS + EMPTY -->
      <section class="study-choices-section" aria-labelledby="study-choices-heading">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2 mb-md-3">
          <h2 id="study-choices-heading" class="h6 fw-semibold mb-0"><?php echo $t['step1_choices_heading']; ?></h2>
        </div>
        <div id="studyChoices" class="study-choices"></div>

        <div
          id="studyEmpty"
          class="study-empty"
          data-msg-no-region="<?php echo htmlspecialchars($t['step1_empty_no_region'], ENT_QUOTES, 'UTF-8'); ?>"
          data-msg-add-uni="<?php echo htmlspecialchars($t['step1_empty_add_uni'], ENT_QUOTES, 'UTF-8'); ?>"
        >
          <p class="mb-0 small" id="studyEmptyText"><?php echo htmlspecialchars($t['step1_empty_no_region'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      </section>

      <!-- SUMMARY LAST -->
      <aside id="studyCart" class="study-summary-card study-summary-aside" style="display:none;" aria-labelledby="study-cart-title">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
          <h2 id="study-cart-title" class="h6 fw-semibold mb-0"><?php echo $t['step1_cart_title']; ?></h2>
          <span class="badge rounded-pill study-summary-pill">
            <?php echo $lang === 'en' ? 'Summary' : 'Résumé'; ?>
          </span>
        </div>
        <div class="list-group list-group-flush small study-cart-list"></div>
        <p class="form-text mt-2 mb-0 small"><?php echo $t['step1_cart_help']; ?></p>
      </aside>

      <!-- Referral + agent (before Smart AI autofill so routing happens after consultant is set) -->
      <section
        class="study-panel study-panel--soft mt-4"
        id="step1AgentReferralPanel"
        aria-labelledby="step1-agent-heading"
      >
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
          <div>
            <h2 id="step1-agent-heading" class="h6 fw-semibold mb-1"><?php echo htmlspecialchars($t['step1_agent_panel_title'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="form-text small mb-0"><?php echo htmlspecialchars($t['step1_agent_panel_desc'], ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" for="referral_source">
            <?php echo $t['referral_title']; ?> <span class="text-danger">*</span>
          </label>
          <select
            id="referral_source"
            name="referral_source"
            class="form-select rounded-3 study-touch-control"
            required
          >
            <option value=""><?php echo $lang === 'en' ? 'Select an option' : 'Sélectionnez une option'; ?></option>
            <?php foreach ($t['referral_options'] as $option): ?>
            <option value="<?php echo htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($option['text'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text"><?php echo $t['referral_help']; ?></div>
        </div>

        <div id="agentSection" style="display:none;">
          <div id="agentSearchWrap" class="position-relative mb-2">
            <label class="form-label small fw-semibold" for="agent_search"><?php echo $lang === 'en' ? 'Find a team member' : 'Trouver un membre de l\'équipe'; ?></label>
            <input
              type="text"
              id="agent_search"
              class="form-control rounded-3 study-touch-control mb-0"
              placeholder="<?php echo htmlspecialchars($t['agent_search_placeholder'], ENT_QUOTES, 'UTF-8'); ?>"
              autocomplete="off"
              spellcheck="false"
            >
            <div
              id="agentResults"
              class="list-group position-absolute w-100 d-none shadow-sm border rounded-3 mt-1 overflow-auto agent-results-dropdown"
              style="z-index: 1050; max-height: 260px;"
              role="listbox"
              aria-label="<?php echo $lang === 'en' ? 'Search results' : 'Résultats de recherche'; ?>"
            ></div>
          </div>

          <div class="row mt-3 g-2">
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="agent_first_name"><?php echo $t['agent_first']; ?></label>
              <input
                type="text"
                class="form-control rounded-3"
                name="agent_first_name"
                id="agent_first_name"
                placeholder="<?php echo $lang === 'en' ? 'Auto-filled' : 'Rempli automatiquement'; ?>"
                readonly
                required
              >
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="agent_last_name"><?php echo $t['agent_last']; ?></label>
              <input
                type="text"
                class="form-control rounded-3"
                name="agent_last_name"
                id="agent_last_name"
                placeholder="<?php echo $lang === 'en' ? 'Auto-filled' : 'Rempli automatiquement'; ?>"
                readonly
                required
              >
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="agent_email"><?php echo $t['agent_email']; ?></label>
              <input
                type="email"
                class="form-control rounded-3"
                name="agent_email"
                id="agent_email"
                placeholder="<?php echo $lang === 'en' ? 'Auto-filled' : 'Rempli automatiquement'; ?>"
                readonly
                required
              >
            </div>
          </div>
          <div class="form-text mt-2"><?php echo $t['agent_help']; ?></div>
        </div>

        <div class="mt-4 pt-3 border-top border-secondary-subtle">
          <label class="form-label fw-semibold" for="staff_assign_search"><?php echo htmlspecialchars($t['step1_assign_title'], ENT_QUOTES, 'UTF-8'); ?></label>
          <p class="form-text small mb-2"><?php echo htmlspecialchars($t['step1_assign_desc'], ENT_QUOTES, 'UTF-8'); ?></p>
          <p class="form-text small mb-2 text-body-secondary"><?php echo htmlspecialchars($t['staff_assign_hint'], ENT_QUOTES, 'UTF-8'); ?></p>
          <input type="hidden" id="assigned_to_admin_id" value="">
          <div id="staffAssignSearchWrap" class="position-relative">
            <div class="input-group flex-wrap gap-1">
              <input
                type="text"
                id="staff_assign_search"
                class="form-control rounded-3 study-touch-control flex-grow-1"
                placeholder="<?php echo htmlspecialchars($t['staff_assign_placeholder'], ENT_QUOTES, 'UTF-8'); ?>"
                autocomplete="off"
                spellcheck="false"
                aria-autocomplete="list"
                aria-controls="staff_assign_results"
              >
              <button type="button" class="btn btn-outline-secondary rounded-3" id="staff_assign_clear_btn">
                <?php echo htmlspecialchars($t['staff_assign_clear'], ENT_QUOTES, 'UTF-8'); ?>
              </button>
            </div>
            <div
              id="staff_assign_results"
              class="list-group position-absolute w-100 d-none shadow-sm border rounded-3 mt-1 overflow-auto"
              style="z-index: 1040; max-height: 260px;"
              role="listbox"
              aria-label="<?php echo $lang === 'en' ? 'Staff search results' : 'Résultats personnel'; ?>"
            ></div>
          </div>
        </div>
      </section>

      <div class="smart-autofill-card mt-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
          <div>
            <span class="smart-autofill-pill">AI</span>
            <h6 class="fw-semibold mt-2 mb-2"><?php echo htmlspecialchars($t['smart_autofill_title'], ENT_QUOTES, 'UTF-8'); ?></h6>
            <p class="text-muted small mb-2"><?php echo htmlspecialchars($t['smart_autofill_desc'], ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="form-text"><?php echo htmlspecialchars($t['smart_autofill_existing_note'], ENT_QUOTES, 'UTF-8'); ?></div>
          </div>

          <div class="text-lg-end">
            <div class="smart-autofill-actions">
              <button type="button" class="btn btn-outline-primary px-4" id="smartAutofillTrigger" disabled>
                <?php echo htmlspecialchars($t['smart_autofill_button'], ENT_QUOTES, 'UTF-8'); ?>
              </button>
              <button type="button" class="btn btn-primary px-4" id="smartAutofillStart" disabled>
                <?php echo htmlspecialchars($t['smart_autofill_start_button'], ENT_QUOTES, 'UTF-8'); ?>
              </button>
            </div>
            <input
              type="file"
              id="smartAutofillInput"
              class="d-none"
              multiple
              accept=".pdf,.docx,.jpg,.jpeg,.png,.webp"
            >
            <div id="smartAutofillHelp" class="form-text mt-2">
              <?php echo htmlspecialchars($t['smart_autofill_gate'], ENT_QUOTES, 'UTF-8'); ?><br>
              <?php echo htmlspecialchars($t['smart_autofill_formats'], ENT_QUOTES, 'UTF-8'); ?><br>
              <?php echo htmlspecialchars($t['smart_autofill_hint'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
          </div>
        </div>

        <div id="smartAutofillStatus" class="alert d-none mt-3 mb-0" role="status" aria-live="polite"></div>

        <div id="smartAutofillQueueWrap" class="smart-autofill-queue">
          <div class="small fw-semibold text-body-secondary"><?php echo htmlspecialchars($t['smart_autofill_queue_title'], ENT_QUOTES, 'UTF-8'); ?></div>
          <div id="smartAutofillQueueHint" class="form-text mt-1"><?php echo htmlspecialchars($t['smart_autofill_queue_empty'], ENT_QUOTES, 'UTF-8'); ?></div>
          <ul id="smartAutofillQueue" class="smart-autofill-queue-list"></ul>
        </div>

        <div id="smartAutofillProgressWrap" class="smart-autofill-progress-panel">
          <div class="smart-autofill-orb">
            <div class="smart-autofill-orb-ring"></div>
            <div class="smart-autofill-orb-core" id="smartAutofillProgressText">Ready</div>
          </div>
          <div class="smart-autofill-progress-copy">
            <strong id="smartAutofillProgressLabel"><?php echo htmlspecialchars($t['smart_autofill_processing'], ENT_QUOTES, 'UTF-8'); ?></strong>
            <small id="smartAutofillProgressSubtext"><?php echo htmlspecialchars($t['smart_autofill_hint'], ENT_QUOTES, 'UTF-8'); ?></small>
            <div id="smartAutofillStagePills" class="smart-autofill-stage-pills"></div>
            <div id="smartAutofillProgressBarWrap" class="smart-autofill-bar-wrap d-none">
              <div class="smart-autofill-bar-track">
                <div id="smartAutofillProgressBar" class="smart-autofill-bar-fill"></div>
              </div>
              <div class="smart-autofill-bar-meta">
                <span id="smartAutofillElapsed">0:00</span>
                <span id="smartAutofillLiveStatus">Starting parallel analysis…</span>
              </div>
            </div>
          </div>
        </div>

        <div id="smartAutofillPanels" class="mt-3 d-none">
          <div class="small fw-semibold text-body-secondary mb-2">
            <?php echo htmlspecialchars($t['smart_autofill_results_title'], ENT_QUOTES, 'UTF-8'); ?>
          </div>
          <ul id="smartAutofillResults" class="smart-autofill-results"></ul>

          <div id="smartAutofillWarningsWrap" class="mt-3 d-none">
            <div class="small fw-semibold text-body-secondary mb-2">
              <?php echo htmlspecialchars($t['smart_autofill_warnings_title'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <ul id="smartAutofillWarnings" class="smart-autofill-results"></ul>
          </div>
        </div>

      </div>

    </div>

  </div>
</div>

<!-- ================= TEMPLATES ================= -->
<template id="studyChoiceTemplate">
  <article class="study-choice study-university-card">

    <input type="hidden" class="region-id">

    <div class="study-uni-header">
      <span class="badge region-badge rounded-pill"></span>
      <button type="button" class="btn-remove-uni btn-remove btn btn-link text-danger p-0 small fw-semibold">
        <?php echo $t['step1_remove_uni']; ?>
      </button>
    </div>

    <div class="study-uni-body mt-3">
      <label class="form-label small text-secondary mb-1"><?php echo $t['step1_university']; ?></label>
      <select class="form-select university rounded-3 study-touch-control study-select-fullwidth" disabled></select>

      <div class="study-level-rows mt-3"></div>

      <button type="button" class="btn btn-outline-primary btn-sm rounded-pill btn-add-level mt-2">
        + <?php echo $t['step1_add_level']; ?>
      </button>
    </div>

  </article>
</template>

<template id="studyLevelRowTemplate">
  <div class="study-level-row study-level-row-card">
    <div class="row g-3 align-items-start">
      <div class="col-12 col-lg-5">
        <label class="form-label small mb-1"><?php echo $t['step1_level']; ?></label>
        <select class="form-select level rounded-3 study-touch-control study-select-fullwidth"></select>
      </div>
      <div class="col-12 col-lg-7">
        <label class="form-label small mb-1"><?php echo $t['step1_program']; ?></label>
        <select class="form-select program rounded-3 study-touch-control study-select-fullwidth" multiple></select>
      </div>
      <div class="col-12 study-level-row-actions">
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-3 btn-remove-row study-remove-row-btn">
          <?php echo $t['step1_remove_row']; ?>
        </button>
      </div>
    </div>
  </div>
</template>
<!-- =====================================================
 STEP 2 : PERSONAL INFORMATION (STRICT VALIDATION – FINAL)
===================================================== -->

<div class="step">

  <div class="step-section">

<!-- ================= HEADER ================= -->
<div class="mb-4">
  <h5 class="fw-semibold mb-1"><?php echo $t['step2_title']; ?></h5>
  <p class="text-muted small mb-0">
    <?php echo $t['step2_desc']; ?>
  </p>
</div>

<!-- ================= PERSONAL DETAILS ================= -->
<div class="row">

  <!-- FIRST NAME -->
  <div class="col-md-6 mb-3">
    <label class="form-label"><?php echo $t['first_name']; ?> *</label>
    <input
      type="text"
      class="form-control"
      name="first_name"
      required
      minlength="2"
      maxlength="50"
      pattern="^[-A-Za-z\s']+$"
      placeholder="<?php echo $t['first_name']; ?>"
    >
  </div>

  <!-- LAST NAME -->
  <div class="col-md-6 mb-3">
    <label class="form-label"><?php echo $t['last_name']; ?> *</label>
    <input
      type="text"
      class="form-control"
      name="last_name"
      required
      minlength="2"
      maxlength="50"
      pattern="^[-A-Za-z\s']+$"
      placeholder="<?php echo $t['last_name']; ?>"
    >
  </div>

  <!-- EMAIL -->
  <div class="col-md-6 mb-3">
    <label class="form-label"><?php echo $t['email']; ?> *</label>
    <input
      type="email"
      class="form-control"
      id="applicant_email"
      name="email"
      required
      maxlength="100"
      placeholder="<?php echo $t['email']; ?>"
    >
    <div class="invalid-feedback" id="applicantEmailFeedback"></div>
  </div>

  <!-- PHONE -->
  <div class="col-md-6 mb-3">
    <label class="form-label fw-semibold"><?php echo $t['phone_label']; ?> *</label>

    <input
      type="tel"
      id="intl_phone"
      class="form-control"
      required
    >

    <!-- Hidden fields -->
    <input type="hidden" name="area_code" id="area_code" required>
    <input type="hidden" name="phone_number" id="phone_number" required>

    <div class="form-text">
      <?php echo $t['phone_help']; ?>
    </div>
  </div>

  <!-- GENDER -->
  <div class="col-md-6 mb-3">
    <label class="form-label"><?php echo $t['gender']; ?> *</label>
    <select class="form-select" name="gender" required>
      <option value=""><?php echo $t['gender']; ?></option>
      <?php foreach ($t['gender_options'] as $option): ?>
      <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- DOB -->
  <div class="col-md-6 mb-3">
    <label class="form-label"><?php echo $t['dob']; ?> *</label>
    <input
      type="date"
      class="form-control"
      name="dob"
      required
      max="<?php echo date('Y-m-d'); ?>"
    >
  </div>

</div>

<!-- ================= IDENTITY ================= -->
<div class="row">

  <!-- PASSPORT -->
  <div class="col-md-6 mb-3">
    <label class="form-label"><?php echo $t['passport']; ?> *</label>
    <input
      type="text"
      class="form-control"
      name="passport_number"
      required
      minlength="6"
      maxlength="20"
      pattern="^[A-Z0-9]+$"
      placeholder="<?php echo $t['passport']; ?>"
      style="text-transform:uppercase"
    >
    <div class="form-text">Letters & numbers only (no spaces).</div>
  </div>

  <!-- NATIONAL ID -->
  <div class="col-md-6 mb-3">
    <label class="form-label"><?php echo $t['national_id']; ?> *</label>
    <input
      type="text"
      class="form-control"
      name="student_national_id"
      required
      minlength="5"
      maxlength="30"
      pattern="^[-A-Za-z0-9]+$"
      placeholder="<?php echo $t['national_id']; ?>"
    >
  </div>

  <!-- COUNTRY OF BIRTH -->
  <div class="col-md-4 mb-3">
    <label class="form-label"><?php echo $t['birth_country']; ?> *</label>
    <select
      class="form-select country-select"
      name="country_of_birth"
      required
    >
      <option value=""><?php echo $lang === 'en' ? 'Select Country' : 'Sélectionnez un pays'; ?></option>
    </select>
  </div>

  <!-- CITY -->
  <div class="col-md-4 mb-3">
    <label class="form-label"><?php echo $t['city_birth']; ?> *</label>
    <input
      type="text"
      class="form-control"
      name="city_of_birth"
      required
      minlength="2"
      pattern="^[-A-Za-z\s']+$"
      placeholder="<?php echo $t['city_birth']; ?>"
    >
  </div>

  <!-- NATIONALITY -->
  <div class="col-md-4 mb-3">
    <label class="form-label"><?php echo $t['nationality']; ?> *</label>
    <select
      class="form-select country-select"
      name="nationality"
      required
    >
      <option value=""><?php echo $lang === 'en' ? 'Select Nationality' : 'Sélectionnez une nationalité'; ?></option>
    </select>
  </div>

  <!-- SECOND NATIONALITY (OPTIONAL) -->
  <div class="col-md-6 mb-3">
    <label class="form-label"><?php echo $t['second_nationality']; ?></label>
    <select
      class="form-select country-select"
      name="second_nationality"
    >
      <option value=""><?php echo $lang === 'en' ? 'Optional' : 'Optionnel'; ?></option>
    </select>
  </div>

</div>

  </div>
</div>

<!-- =====================================================
 STEP 3 : ADDRESS & FAMILY (FULLY VALIDATED – NO SKIPS)
===================================================== -->
<div class="step">

  <div class="step-section">

    <!-- ================= HEADER ================= -->
    <div class="mb-4">
      <h5 class="fw-semibold mb-1"><?php echo $t['step3_title']; ?></h5>
      <p class="text-muted small mb-0">
        <?php echo $t['step3_desc']; ?>
      </p>
    </div>

    <!-- ================= ADDRESS ================= -->

    <input
      type="text"
      class="form-control mb-3"
      name="address_line1"
      placeholder="<?php echo $t['address1']; ?>"
      required
    >

    <input
      type="text"
      class="form-control mb-3"
      name="address_line2"
      placeholder="<?php echo $t['address2']; ?>"
      autocomplete="address-line2"
    >

    <div class="row">

      <div class="col-md-4 mb-3">
        <input
          type="text"
          class="form-control"
          name="city"
          placeholder="<?php echo $t['city']; ?>"
          required
        >
      </div>

      <div class="col-md-4 mb-3">
        <input
          type="text"
          class="form-control"
          name="state_province"
          placeholder="<?php echo $t['state']; ?>"
          required
        >
      </div>

      <div class="col-md-4 mb-3">
        <input
          type="text"
          class="form-control"
          name="postal_code"
          placeholder="<?php echo $t['postal']; ?>"
          required
        >
      </div>

    </div>

    <!-- ================= PARENTS ================= -->

    <h6 class="fw-semibold mt-4 mb-3"><?php echo $t['parents_title']; ?></h6>

    <div class="row">

      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="father_first_name"
          placeholder="<?php echo $t['father_first']; ?>"
          required
        >
      </div>

      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="father_last_name"
          placeholder="<?php echo $t['father_last']; ?>"
          required
        >
      </div>

      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="mother_first_name"
          placeholder="<?php echo $t['mother_first']; ?>"
          required
        >
      </div>

      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="mother_last_name"
          placeholder="<?php echo $t['mother_last']; ?>"
          required
        >
      </div>

    </div>

  </div>
</div>

<!-- =====================================================
 STEP 4 : EMERGENCY CONTACT (FULLY VALIDATED – NO SKIPS)
===================================================== -->
<div class="step">

  <div class="step-section">

    <!-- ================= HEADER ================= -->
    <div class="mb-4">
      <h5 class="fw-semibold mb-1"><?php echo $t['step4_title']; ?></h5>
      <p class="text-muted small mb-0">
        <?php echo $t['step4_desc']; ?>
      </p>
    </div>

    <div class="row">

      <!-- First Name -->
      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="emergency_first_name"
          placeholder="<?php echo $t['emergency_first']; ?>"
          required
        >
      </div>

      <!-- Last Name -->
      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="emergency_last_name"
          placeholder="<?php echo $t['emergency_last']; ?>"
          required
        >
      </div>

      <!-- Email -->
      <div class="col-md-6 mb-3">
        <input
          type="email"
          class="form-control"
          name="emergency_email"
          placeholder="<?php echo $t['emergency_email']; ?>"
          required
        >
      </div>

      <!-- Emergency Phone -->
      <div class="col-md-6 mb-3">
        <label class="form-label"><?php echo $t['emergency_phone_label']; ?></label>

        <!-- Visible phone input -->
        <input
          type="tel"
          id="emergency_phone"
          class="form-control"
          placeholder="<?php echo $t['phone_placeholder']; ?>"
          required
        >

        <!-- Hidden fields (KEEP DB STRUCTURE SAME) -->
        <input
          type="hidden"
          name="emergency_area_code"
          id="emergency_area_code"
          required
        >
        <input
          type="hidden"
          name="emergency_phone_number"
          id="emergency_phone_number"
          required
        >

        <div class="form-text">
          <?php echo $t['emergency_phone_help']; ?>
        </div>
      </div>

      <!-- Relationship -->
      <div class="col-md-6 mb-3">
        <input
          type="text"
          class="form-control"
          name="emergency_relationship"
          placeholder="<?php echo $t['relationship']; ?>"
          required
        >
      </div>

      <!-- Same Address -->
      <div class="col-md-6 mb-3">
        <select
          class="form-select"
          name="emergency_same_address"
          required
        >
          <option value=""><?php echo $t['same_address']; ?></option>
          <?php foreach ($t['same_address_options'] as $option): ?>
          <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
          <?php endforeach; ?>
        </select>
      </div>

    </div>

  </div>
</div>
<!-- =====================================================
 STEP 5 : EDUCATION & BACKGROUND (CLEAN & SOLID)
===================================================== -->
<div class="step">
  <div class="step-section">

    <!-- ================= HEADER ================= -->
    <div class="mb-4">
      <h5 class="fw-semibold mb-1"><?php echo $t['step5_title']; ?></h5>
      <p class="text-muted small mb-0">
        <?php echo $t['step5_desc']; ?>
      </p>
    </div>

    <!-- ================= INSTITUTION DETAILS ================= -->

    <div class="mb-3">
      <label class="form-label"><?php echo $t['institution_name']; ?></label>
      <input type="text" class="form-control"
             name="previous_institution_name"
             placeholder="<?php echo $t['institution_name_placeholder']; ?>"
             required>
    </div>

    <div class="mb-3">
      <label class="form-label"><?php echo $t['institution_street']; ?></label>
      <input type="text" class="form-control"
             name="previous_institution_street"
             placeholder="<?php echo $t['institution_street_placeholder']; ?>"
             required>
    </div>

    <div class="mb-3">
      <label class="form-label"><?php echo $t['institution_city']; ?></label>
      <input type="text" class="form-control"
             name="previous_institution_city"
             placeholder="<?php echo $t['institution_city_placeholder']; ?>"
             required>
    </div>

    <div class="mb-3">
      <label class="form-label"><?php echo $t['institution_province']; ?></label>
      <input type="text" class="form-control"
             name="previous_institution_province"
             placeholder="<?php echo $t['institution_province_placeholder']; ?>"
             required>
    </div>

    <div class="mb-3">
      <label class="form-label"><?php echo $t['institution_country']; ?></label>
      <select class="form-select country-select"
              name="previous_institution_country"
              data-placeholder="<?php echo $lang === 'en' ? 'Select Country' : 'Sélectionnez un pays'; ?>"
              required>
        <option value=""><?php echo $lang === 'en' ? 'Select Country' : 'Sélectionnez un pays'; ?></option>
      </select>
    </div>

    <div class="mb-4">
      <label class="form-label"><?php echo $t['institution_postal']; ?></label>
      <input type="text" class="form-control"
             name="previous_institution_post_code"
             placeholder="<?php echo $t['institution_postal_placeholder']; ?>"
             required>
    </div>

    <!-- ================= STUDY INFORMATION ================= -->

    <div class="mb-3">
      <label class="form-label"><?php echo $t['language']; ?></label>
      <select class="form-select"
              name="language_of_instruction"
              required>
        <option value=""><?php echo $lang === 'en' ? 'Select Language' : 'Sélectionnez une langue'; ?></option>
        <?php foreach ($t['language_options'] as $option): ?>
        <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label"><?php echo $t['study_start']; ?></label>
        <input type="date" class="form-control"
               name="previous_study_start"
               required>
      </div>

      <div class="col-md-6 mb-4">
        <label class="form-label"><?php echo $t['graduation']; ?></label>
        <input type="date" class="form-control"
               name="previous_study_graduation"
               required>
      </div>
    </div>

    <!-- ================= BACKGROUND QUESTIONS ================= -->

    <!-- STUDY GAP -->
    <div class="mb-3">
      <label class="form-label"><?php echo $t['study_gap']; ?></label>
      <select class="form-select conditional-select"
              name="study_gap"
              data-followup="study_gap_details"
              required>
        <option value=""><?php echo $lang === 'en' ? 'Select' : 'Sélectionnez'; ?></option>
        <?php foreach ($t['yes_no_options'] as $option): ?>
        <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
        <?php endforeach; ?>
      </select>

      <textarea class="form-control mt-2 conditional-field"
                name="study_gap_details"
                placeholder="<?php echo $t['study_gap_placeholder']; ?>"
                style="display:none;"></textarea>
    </div>

    <!-- ADDITIONAL SECONDARY -->
    <div class="mb-3">
      <label class="form-label"><?php echo $t['secondary_school']; ?></label>
      <select class="form-select conditional-select"
              name="additional_secondary_school"
              data-followup="additional_secondary_details"
              required>
        <option value=""><?php echo $lang === 'en' ? 'Select' : 'Sélectionnez'; ?></option>
        <?php foreach ($t['yes_no_options'] as $option): ?>
        <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
        <?php endforeach; ?>
      </select>

      <textarea class="form-control mt-2 conditional-field"
                name="additional_secondary_details"
                placeholder="<?php echo $t['secondary_school_placeholder']; ?>"
                style="display:none;"></textarea>
    </div>

    <!-- POST SECONDARY -->
    <div class="mb-3">
      <label class="form-label"><?php echo $t['post_secondary']; ?></label>
      <select class="form-select conditional-select"
              name="post_secondary"
              data-followup="post_secondary_details"
              required>
        <option value=""><?php echo $lang === 'en' ? 'Select' : 'Sélectionnez'; ?></option>
        <?php foreach ($t['yes_no_options'] as $option): ?>
        <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
        <?php endforeach; ?>
      </select>

      <textarea class="form-control mt-2 conditional-field"
                name="post_secondary_details"
                placeholder="<?php echo $t['post_secondary_placeholder']; ?>"
                style="display:none;"></textarea>
    </div>

    <!-- CRIMINAL HISTORY -->
    <div class="mb-3">
      <label class="form-label"><?php echo $t['criminal_history']; ?></label>
      <select class="form-select conditional-select"
              name="criminal_history"
              data-followup="criminal_history_details"
              required>
        <option value=""><?php echo $lang === 'en' ? 'Select' : 'Sélectionnez'; ?></option>
        <?php foreach ($t['yes_no_options'] as $option): ?>
        <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
        <?php endforeach; ?>
      </select>

      <textarea class="form-control mt-2 conditional-field"
                name="criminal_history_details"
                placeholder="<?php echo $t['criminal_history_placeholder']; ?>"
                style="display:none;"></textarea>
    </div>

    <!-- DISABILITY -->
    <div class="mb-3">
      <label class="form-label"><?php echo $t['disability']; ?></label>
      <select class="form-select conditional-select"
              name="disability"
              data-followup="disability_details"
              required>
        <option value=""><?php echo $lang === 'en' ? 'Select' : 'Sélectionnez'; ?></option>
        <?php foreach ($t['yes_no_options'] as $option): ?>
        <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
        <?php endforeach; ?>
      </select>

      <textarea class="form-control mt-2 conditional-field"
                name="disability_details"
                placeholder="<?php echo $t['disability_placeholder']; ?>"
                style="display:none;"></textarea>
    </div>

    <!-- VISA REJECTION -->
    <div class="mb-3">
      <label class="form-label"><?php echo $t['visa_rejection']; ?></label>
      <select class="form-select conditional-select"
              name="visa_rejection"
              data-followup="visa_rejection_details"
              required>
        <option value=""><?php echo $lang === 'en' ? 'Select' : 'Sélectionnez'; ?></option>
        <?php foreach ($t['yes_no_options'] as $option): ?>
        <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
        <?php endforeach; ?>
      </select>

      <textarea class="form-control mt-2 conditional-field"
                name="visa_rejection_details"
                placeholder="<?php echo $t['visa_rejection_placeholder']; ?>"
                style="display:none;"></textarea>
    </div>

  </div>
</div>

<!-- =====================================================
 STEP 6 : DESTINATION & FINANCE (PRODUCTION READY)
===================================================== -->

<div class="step">

  <div class="step-section">

    <!-- Step Header -->
    <div class="mb-4">
      <h5 class="fw-semibold mb-1"><?php echo $t['step6_title']; ?></h5>
      <p class="text-muted small mb-0">
        <?php echo $t['step6_desc']; ?>
      </p>
    </div>

    <!-- ================= DESTINATION ================= -->
    <div class="mb-4">

      <h6 class="fw-semibold mb-3"><?php echo $t['destination_title']; ?></h6>

      <div class="row g-3">

        <!-- Preferred Destination -->
        <div class="col-12 col-md-8 col-lg-6">
          <label class="form-label fw-semibold"><?php echo $t['preferred_destination']; ?></label>
          <input
            type="text"
            class="form-control"
            name="destination"
            id="preferredDestination"
            readonly
          >
          <div class="form-text">
            <?php echo $t['preferred_help']; ?>
          </div>
        </div>

      </div>

      <!-- ========== LOAN DESTINATION (MASTER ONLY) ========== -->
      <div class="row g-3 mt-1 loan-section">

        <div class="col-md-6">
          <label class="form-label fw-semibold"><?php echo $t['loan_destination']; ?></label>
          <input
            type="text"
            class="form-control"
            name="destination_loan"
            id="loanDestination"
            readonly
          >
          <div class="form-text">
            <?php echo $t['loan_destination_help']; ?>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold"><?php echo $t['other_loan_destination']; ?></label>
          <input
            type="text"
            class="form-control"
            name="other_destination_loan"
            placeholder="<?php echo $t['other_loan_placeholder']; ?>"
          >
        </div>

      </div>

    </div>

    <!-- ================= FINANCE ================= -->
    <div>

      <h6 class="fw-semibold mb-3"><?php echo $t['finance_title']; ?></h6>

      <div class="row g-3">

        <!-- Tuition -->
        <div class="col-md-4">
          <label class="form-label fw-semibold"><?php echo $t['tuition']; ?></label>
          <select class="form-select finance-select" name="paying_tuition_fees">
            <option value=""><?php echo $lang === 'en' ? 'Select' : 'Sélectionnez'; ?></option>
            <?php foreach ($t['finance_options'] as $option): ?>
            <option value="<?php echo $option; ?>" class="<?php echo $option === 'Loan' ? 'loan-option' : ''; ?>"><?php echo $option; ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Living Cost -->
        <div class="col-md-4">
          <label class="form-label fw-semibold"><?php echo $t['living_cost']; ?></label>
          <select class="form-select finance-select" name="paying_cost_living">
            <option value=""><?php echo $lang === 'en' ? 'Select' : 'Sélectionnez'; ?></option>
            <?php foreach ($t['finance_options'] as $option): ?>
            <option value="<?php echo $option; ?>" class="<?php echo $option === 'Loan' ? 'loan-option' : ''; ?>"><?php echo $option; ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Travel -->
        <div class="col-md-4">
          <label class="form-label fw-semibold"><?php echo $t['travel']; ?></label>
          <select class="form-select finance-select" name="paying_travel_expenses">
            <option value=""><?php echo $lang === 'en' ? 'Select' : 'Sélectionnez'; ?></option>
            <?php foreach ($t['finance_options'] as $option): ?>
            <option value="<?php echo $option; ?>" class="<?php echo $option === 'Loan' ? 'loan-option' : ''; ?>"><?php echo $option; ?></option>
            <?php endforeach; ?>
          </select>
        </div>

      </div>
    </div>

  </div>
</div>
<!-- =====================================================
 STEP 7 : DOCUMENTS, AGENT & COMMENTS (FINAL REBUILD)
===================================================== -->
<div class="step">

  <div class="step-section">

    <!-- ================= HEADER ================= -->
    <div class="mb-4">
      <h5 class="fw-semibold mb-1"><?php echo $t['step7_title']; ?></h5>
      <p class="text-muted small mb-0">
        <?php echo $t['step7_desc']; ?>
      </p>
    </div>

    <!-- ================= DOCUMENT GRID ================= -->
    <div class="row g-4">

      <!-- DEGREE / TRANSCRIPTS (MULTI) -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">
          <?php echo $t['degree_transcripts']; ?> <span class="text-danger">*</span>
        </label>
        <div class="doc-dropzone multi" data-field="degree_transcripts">
          <input type="file" multiple accept=".pdf,.jpg,.png">
          <div class="dz-content">
            <strong><?php echo $t['drop_transcripts']; ?></strong>
            <span><?php echo $t['multiple_files']; ?></span>
          </div>
          <ul class="dz-files"></ul>
        </div>
      </div>

      <!-- HIGH SCHOOL -->
      <div class="col-md-6">
        <label class="form-label fw-semibold"><?php echo $t['high_school']; ?></label>
        <div class="doc-dropzone" data-field="high_school_degree">
          <input type="file" accept=".pdf,.jpg,.png">
          <div class="dz-content">
            <strong><?php echo $t['drop_certificate']; ?></strong>
            <span><?php echo $t['single_file']; ?></span>
          </div>
          <ul class="dz-files"></ul>
        </div>
      </div>

      <!-- PASSPORT -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">
          <?php echo $t['passport_doc']; ?> <span class="text-danger">*</span>
        </label>
        <div class="doc-dropzone" data-field="valid_passport">
          <input type="file" accept=".pdf,.jpg,.png">
          <div class="dz-content">
            <strong><?php echo $t['drop_passport']; ?></strong>
            <span><?php echo $t['single_file']; ?></span>
          </div>
          <ul class="dz-files"></ul>
        </div>
      </div>

      <!-- CV -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">
          <?php echo $t['cv_resume']; ?> <span class="text-danger">*</span>
        </label>
        <div class="doc-dropzone" data-field="cv_resume">
          <input type="file" accept=".pdf,.jpg,.png">
          <div class="dz-content">
            <strong><?php echo $t['drop_cv']; ?></strong>
            <span><?php echo $t['single_file']; ?></span>
          </div>
          <ul class="dz-files"></ul>
        </div>
      </div>

      <!-- RECOMMENDATION (MULTI) -->
      <div class="col-md-6">
        <label class="form-label fw-semibold"><?php echo $t['recommendation']; ?></label>
        <div class="doc-dropzone multi" data-field="recommendation_letters">
          <input type="file" multiple accept=".pdf,.jpg,.png">
          <div class="dz-content">
            <strong><?php echo $t['drop_recommendation']; ?></strong>
            <span><?php echo $t['multiple_files']; ?></span>
          </div>
          <ul class="dz-files"></ul>
        </div>
      </div>

      <!-- PERSONAL STATEMENT -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">
          <?php echo $t['personal_statement']; ?>
        </label>
        <div class="doc-dropzone" data-field="personal_statement">
          <input type="file" accept=".pdf,.jpg,.png">
          <div class="dz-content">
            <strong><?php echo $t['drop_statement']; ?></strong>
            <span><?php echo $t['single_file']; ?></span>
          </div>
          <ul class="dz-files"></ul>
        </div>
      </div>

      <!-- ENGLISH -->
      <div class="col-md-6">
        <label class="form-label fw-semibold"><?php echo $t['english_certificate']; ?></label>
        <div class="doc-dropzone" data-field="english_certificate">
          <input type="file" accept=".pdf,.jpg,.png">
          <div class="dz-content">
            <strong><?php echo $t['drop_certificate']; ?></strong>
            <span><?php echo $t['single_file']; ?></span>
          </div>
          <ul class="dz-files"></ul>
        </div>
      </div>

      <!-- BIRTH -->
      <div class="col-md-6">
        <label class="form-label fw-semibold"><?php echo $t['birth_certificate']; ?></label>
        <div class="doc-dropzone" data-field="birth_certificate">
          <input type="file" accept=".pdf,.jpg,.png">
          <div class="dz-content">
            <strong><?php echo $t['drop_certificate']; ?></strong>
            <span><?php echo $t['single_file']; ?></span>
          </div>
          <ul class="dz-files"></ul>
        </div>
      </div>

      <!-- PAYMENT -->
      <div class="col-md-6">
        <label class="form-label fw-semibold"><?php echo $t['payment_proof']; ?></label>
        <div class="doc-dropzone" data-field="payment_proof">
          <input type="file" accept=".pdf,.jpg,.png">
          <div class="dz-content">
            <strong><?php echo $t['drop_certificate']; ?></strong>
            <span><?php echo $t['single_file']; ?></span>
          </div>
          <ul class="dz-files"></ul>
        </div>
      </div>

    </div>

    <!-- ================= AI VALIDATION ================= -->
    <div id="docProgressPanel" class="doc-progress-panel">
      <div class="small fw-semibold text-body mb-2">
        <?php echo htmlspecialchars($t['doc_progress_title'], ENT_QUOTES, 'UTF-8'); ?>
      </div>

      <div id="docValidationStatus" class="small text-muted"></div>

      <div id="docProgressWrap" class="upload-progress mt-3">
        <div class="upload-bar">
          <span id="docProgressText">0%</span>
        </div>
      </div>

      <div id="docStageList" class="doc-stage-list"></div>

      <div id="docDebugWrap" class="doc-debug-wrap d-none">
        <div class="small fw-semibold text-body mb-2">
          <?php echo htmlspecialchars($t['doc_debug_title'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <ul id="docDebugList" class="doc-debug-list"></ul>
      </div>
    </div>

    <!-- ================= COMMENTS ================= -->
    <div class="mt-4">
      <textarea
        class="form-control"
        name="comments"
        placeholder="<?php echo $t['comments_placeholder']; ?>"
      ></textarea>
    </div>

  </div>
</div>

<!-- ================= NAVIGATION ================= -->
<div class="d-flex justify-content-between mt-4">
  <button type="button" class="btn btn-secondary" id="prevBtn"><?php echo $t['prev']; ?></button>
  <button type="button" class="btn btn-primary" id="nextBtn"><?php echo $t['next']; ?></button>
</div>

</form>

<!-- Save / server validation feedback (Bootstrap modal; requires bundle JS below) -->
<div class="modal fade" id="applicationSaveErrorModal" tabindex="-1" aria-labelledby="applicationSaveErrorModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-semibold" id="applicationSaveErrorModalLabel"><?php echo htmlspecialchars($t['save_error_title'], ENT_QUOTES, 'UTF-8'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-2 application-save-error-body text-body small"></div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal"><?php echo htmlspecialchars($t['save_error_ok'], ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="applicationSuccessModal" tabindex="-1" aria-labelledby="applicationSuccessModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-semibold text-success" id="applicationSuccessModalLabel">Application Submitted</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-2 application-success-body text-body small"></div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>
</div>
</div>
</div>

<link
  rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css"
/>

<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5.min.js"></script>

<!-- Language Switcher Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Language switcher functionality
    document.querySelectorAll('.language-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const lang = this.dataset.lang;
            // Reload page with new language
            const url = new URL(window.location);
            url.searchParams.set('lang', lang);
            window.location.href = url.toString();
        });
    });

    // Update form placeholders when language changes (if using AJAX)
    window.updateFormLanguage = function(lang) {
        // This function can be called if you implement AJAX language switching
        console.log('Language updated to:', lang);
    };
});
</script>

<!-- Bootstrap JS (modal for save errors) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Your existing JavaScript files -->
<script src="application.js"></script>
<script src="study-search.js"></script>

<!-- Your existing inline scripts (keep them as they are - they don't need translation) -->
<script>
"use strict";

/* =====================================================
   GLOBAL SAFETY (shared with application.js)
   Keeps track of uploaded files per field
===================================================== */
window.uploadStatus = window.uploadStatus || {};

/* =====================================================
   PROGRESS CONTROLLER (UI ONLY)
   Single global progress bar controller
===================================================== */
function createProgressController() {
  const panel  = document.getElementById("docProgressPanel");
  const wrap   = document.getElementById("docProgressWrap");
  const bar    = wrap.querySelector(".upload-bar");
  const text   = document.getElementById("docProgressText");
  const status = document.getElementById("docValidationStatus");
  const stageList = document.getElementById("docStageList");
  const debugWrap = document.getElementById("docDebugWrap");
  const debugList = document.getElementById("docDebugList");
  const stageMeta = [
    { id: "prepare", label: <?php echo json_encode($t['doc_stage_prepare'], JSON_UNESCAPED_UNICODE); ?> },
    { id: "upload", label: <?php echo json_encode($t['doc_stage_upload'], JSON_UNESCAPED_UNICODE); ?> },
    { id: "extract", label: <?php echo json_encode($t['doc_stage_extract'], JSON_UNESCAPED_UNICODE); ?> },
    { id: "ai", label: <?php echo json_encode($t['doc_stage_ai'], JSON_UNESCAPED_UNICODE); ?> },
    { id: "parse", label: <?php echo json_encode($t['doc_stage_parse'], JSON_UNESCAPED_UNICODE); ?> },
    { id: "save", label: <?php echo json_encode($t['doc_stage_save'], JSON_UNESCAPED_UNICODE); ?> }
  ];
  let currentStage = "prepare";
  let errorStage = "";

  function renderStages(activeStage = currentStage, failedStage = errorStage) {
    if (!stageList) return;

    const activeIndex = stageMeta.findIndex(stage => stage.id === activeStage);
    const failedIndex = stageMeta.findIndex(stage => stage.id === failedStage);

    stageList.innerHTML = "";

    stageMeta.forEach((stage, index) => {
      const item = document.createElement("div");
      item.className = "doc-stage-item";

      if (failedIndex >= 0 && index === failedIndex) {
        item.classList.add("is-error");
      } else if (activeIndex >= 0 && index < activeIndex) {
        item.classList.add("is-done");
      } else if (stage.id === activeStage && failedIndex < 0) {
        item.classList.add("is-active");
      }

      const title = document.createElement("strong");
      title.textContent = stage.label;
      item.appendChild(title);

      const state = document.createElement("span");
      if (failedIndex >= 0 && index === failedIndex) {
        state.textContent = "Error";
      } else if (activeIndex >= 0 && index < activeIndex) {
        state.textContent = "Done";
      } else if (stage.id === activeStage && failedIndex < 0) {
        state.textContent = "In progress";
      } else {
        state.textContent = "Pending";
      }
      item.appendChild(state);

      stageList.appendChild(item);
    });
  }

  function renderDebug(debug) {
    if (!debugWrap || !debugList) return;

    const rows = [];
    if (debug && typeof debug === "object") {
      if (debug.file_name) rows.push(["File", debug.file_name]);
      if (debug.mime) rows.push(["Mime", debug.mime]);
      if (debug.processing_mode) rows.push(["Mode", debug.processing_mode]);
      if (debug.expected_type) rows.push(["Expected", debug.expected_type]);
      if (debug.detected_type) rows.push(["Detected", debug.detected_type]);
      if (debug.confidence != null && debug.confidence !== "") rows.push(["Confidence", String(debug.confidence)]);
      if (debug.model) rows.push(["AI model", debug.model]);
      if (debug.api_key_status) rows.push(["API key", debug.api_key_status]);
      if (debug.env_path) rows.push(["Env file", debug.env_path]);
      if (debug.log_file) rows.push(["Debug log", debug.log_file]);
      if (Array.isArray(debug.stages) && debug.stages.length) {
        rows.push([
          "Server stages",
          debug.stages.map(stage => `${stage.stage}: ${stage.detail}`).join(" | ")
        ]);
      }
    }

    debugList.innerHTML = "";

    if (!rows.length) {
      debugWrap.classList.add("d-none");
      return;
    }

    rows.forEach(([label, value]) => {
      const li = document.createElement("li");
      const left = document.createElement("span");
      const right = document.createElement("span");
      left.textContent = label;
      right.textContent = value;
      li.appendChild(left);
      li.appendChild(right);
      debugList.appendChild(li);
    });

    debugWrap.classList.remove("d-none");
  }

  /* ---------- Reset UI ---------- */
  if (panel) panel.classList.add("active");
  bar.style.background = "";
  bar.style.width = "0%";
  text.textContent = "0%";
  status.textContent = "";
  currentStage = "prepare";
  errorStage = "";
  renderStages();
  renderDebug(null);

  wrap.style.display = "block";
  wrap.classList.add("active");

  return {
    set(percent, label, stageId = null) {
      percent = Math.max(0, Math.min(100, percent));
      bar.style.width = percent + "%";
      text.textContent = percent + "%";
      if (label) status.textContent = label;
      if (stageId) {
        currentStage = stageId;
        errorStage = "";
        renderStages();
      }
    },

    success(message, debug = null) {
      bar.style.width = "100%";
      text.textContent = "100%";
      status.textContent = message || "Document validated successfully";
      currentStage = "save";
      errorStage = "";
      renderStages();
      renderDebug(debug);
    },

    error(message, debug = null, stageId = null) {
      bar.style.width = "100%";
      bar.style.background = "#dc3545";
      text.textContent = "!";
      status.textContent = message || "Upload failed";
      errorStage = stageId || currentStage || "save";
      renderStages(currentStage, errorStage);
      renderDebug(debug);
    },

    hide(delay = 1200) {
      setTimeout(() => {
        wrap.classList.remove("active");
        if (!debugWrap || debugWrap.classList.contains("d-none")) {
          wrap.style.display = "none";
          if (panel) panel.classList.remove("active");
        }
      }, delay);
    },

    renderDebug(debug) {
      renderDebug(debug);
    }
  };
}

/* =====================================================
   DROPZONE INITIALIZATION
   Works for single & multi-file zones
===================================================== */
document.querySelectorAll(".doc-dropzone").forEach(zone => {

  const input    = zone.querySelector('input[type="file"]');
  const list     = zone.querySelector(".dz-files");
  const field    = zone.dataset.field;
  const multiple = input.hasAttribute("multiple");

  /* ---------- Render selected files ---------- */
  function renderFiles(files) {
    list.innerHTML = "";
    const fileNames = multiple
      ? [...new Set([...(window.uploadStatus[field] || []), ...[...files].map(file => file.name)])]
      : [...files].map(file => file.name);

    fileNames.forEach(name => {
      const li = document.createElement("li");
      li.textContent = name;
      list.appendChild(li);
    });
  }

  /* ---------- Drag & Drop UI ---------- */
  ["dragenter", "dragover"].forEach(evt =>
    zone.addEventListener(evt, e => {
      e.preventDefault();
      zone.classList.add("dragover");
    })
  );

  ["dragleave", "drop"].forEach(evt =>
    zone.addEventListener(evt, e => {
      e.preventDefault();
      zone.classList.remove("dragover");
    })
  );

  zone.addEventListener("drop", e => {
    if (!multiple && e.dataTransfer.files.length > 1) {
      alert("Only one file is allowed for this document.");
      return;
    }
    input.files = e.dataTransfer.files;
    input.dispatchEvent(new Event("change"));
  });

  /* ---------- File selection ---------- */
  input.addEventListener("change", async () => {
    if (!input.files || !input.files.length) return;

    if (!multiple && input.files.length > 1) {
      alert("Only one file allowed.");
      input.value = "";
      return;
    }

    renderFiles(input.files);

  /* ---------- Upload files sequentially ---------- */
for (const file of input.files) {
  await uploadSingleFile(field, file);
}

/* ---------- Lock ONLY single-file inputs ---------- */
if (!multiple) {
  input.disabled = true;
  input.classList.add("is-valid");
} else {
  // Allow multi uploads to continue
  input.value = ""; // reset so user can add more files
}

  });
});

/* =====================================================
   SINGLE FILE UPLOAD (CORE LOGIC)
   One file → one request → safe progress
===================================================== */
function uploadSingleFile(field, file, options = {}) {

  return new Promise((resolve, reject) => {
    const skipAiValidation = options.skipAiValidation === true;
    const smartAutofillBatchToken =
      typeof options.smartAutofillBatchToken === "string"
        ? options.smartAutofillBatchToken
        : "";

    /* ===============================
       SAFETY: TRACK PER FIELD
    =============================== */
    window.uploadStatus[field] = window.uploadStatus[field] || [];

    // Prevent duplicate upload
    if (window.uploadStatus[field].includes(file.name)) {
      resolve();
      return;
    }

    /* ===============================
       UI REFERENCES
    =============================== */
    const zone  = document.querySelector(
      `.doc-dropzone[data-field="${field}"]`
    );
    const input = zone?.querySelector('input[type="file"]');
    const list  = zone?.querySelector('.dz-files');
    const isMulti = input?.hasAttribute("multiple");

    function renderUploadedFileChip(name) {
      if (!list) return;

      if (!isMulti) {
        list.innerHTML = "";
      }

      const exists = [...list.children].some(li => li.textContent === name);
      if (exists) return;

      const li = document.createElement("li");
      li.textContent = name;
      list.appendChild(li);
    }

    const progress = createProgressController();
    progress.set(5, "Preparing document…", "prepare");

    /* ===============================
       BUILD FORM DATA
    =============================== */
    const formData = new FormData();
    formData.append("file", file);
    formData.append("field", field);
    formData.append(
      "first_name",
      document.querySelector('[name="first_name"]')?.value || ""
    );
    formData.append(
      "last_name",
      document.querySelector('[name="last_name"]')?.value || ""
    );
    formData.append("lang", document.documentElement.lang || "en");
    const uploadAppId =
      window.currentApplicationId ||
      document.querySelector('[name="application_id"]')?.value ||
      "";
    if (uploadAppId) {
      formData.append("application_id", String(uploadAppId));
    }
    if (skipAiValidation && smartAutofillBatchToken) {
      formData.append("skip_ai_validation", "1");
      formData.append("smart_autofill_batch_token", smartAutofillBatchToken);
    }

    /* ===============================
       INIT REQUEST
    =============================== */
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "upload_file.php", true);

    let validationTimer = null;

    /* ===============================
       UPLOAD PROGRESS (0 → 60%)
    =============================== */
    xhr.upload.onprogress = e => {
      if (!e.lengthComputable) return;
      const batchRoute = skipAiValidation && smartAutofillBatchToken;
      const percent = Math.round((e.loaded / e.total) * (batchRoute ? 85 : 60));
      progress.set(percent, `Uploading ${file.name}…`, "upload");
    };

    /* ===============================
       UPLOAD COMPLETE → START AI UI
    =============================== */
    xhr.upload.onload = () => {
      if (skipAiValidation && smartAutofillBatchToken) {
        progress.set(80, "Upload complete. Saving classified document…", "save");
        return;
      }

      progress.set(62, "Upload complete. Extracting document text…", "extract");
      validationTimer = startValidationSimulation(progress);
    };

    /* ===============================
       NETWORK ERROR
    =============================== */
    xhr.onerror = () => {
      clearInterval(validationTimer);
      progress.error("Network error during upload", {
        file_name: file.name,
        api_key_status: "unknown"
      }, "upload");
      cleanupFailedFile();
      reject();
    };

    /* ===============================
       SERVER RESPONSE
    =============================== */
    xhr.onload = async () => {
      clearInterval(validationTimer);

      if (xhr.status !== 200) {
        let serverDebug = null;
        try {
          serverDebug = JSON.parse(xhr.responseText || "{}")?.debug || null;
        } catch (err) {}
        progress.error("Server error during validation", serverDebug, "save");
        cleanupFailedFile();
        reject();
        return;
      }

      let response;
      try {
        response = JSON.parse(xhr.responseText);
      } catch {
        progress.error("Invalid server response", {
          file_name: file.name
        }, "parse");
        cleanupFailedFile();
        reject();
        return;
      }

      /* ===============================
         SUCCESS
      =============================== */
      if (response.status === "success") {
        if (!isMulti) {
          window.uploadStatus[field] = [];
        }
        renderUploadedFileChip(file.name);
        window.uploadStatus[field].push(file.name);
        let draftSaveWarning = "";
        if (
          !(skipAiValidation && smartAutofillBatchToken) &&
          response.autofill_fields &&
          typeof window.applyAutofillFields === "function"
        ) {
          window.applyAutofillFields(response.autofill_fields);
          if (typeof window.persistAutofillDraftData === "function") {
            try {
              await window.persistAutofillDraftData(
                window.currentApplicationId ||
                  document.querySelector('[name="application_id"]')?.value ||
                  0,
                response.autofill_fields
              );
            } catch (err) {
              draftSaveWarning =
                err && err.message
                  ? err.message
                  : "Draft auto-save needs another try.";
            }
          }
        }
        if (input) {
          input.classList.add("is-valid");
          if (!isMulti) {
            input.disabled = true;
          } else {
            input.value = "";
          }
        }
        progress.success(
          draftSaveWarning
            ? `${response.message || "Document validated"} ${draftSaveWarning}`
            : (response.message || "Document validated"),
          response.debug || null
        );
        progress.hide();
        resolve(response);
        return;
      }

      /* ===============================
         ❌ VALIDATION FAILED
      =============================== */
      progress.error(response.message || "Document validation failed", response.debug || null, "save");
      cleanupFailedFile();
      reject();
    };

    xhr.send(formData);

    /* =====================================================
       CLEANUP FUNCTION (CRITICAL)
       Removes bad documents safely
    ===================================================== */
    function cleanupFailedFile() {

      // Remove from uploadStatus
      window.uploadStatus[field] =
        (window.uploadStatus[field] || []).filter(
          name => name !== file.name
        );

      // Remove preview chip
      if (list) {
        [...list.children].forEach(li => {
          if (li.textContent === file.name) {
            li.remove();
          }
        });
      }

      // Re-enable input
      if (input) {
        input.disabled = false;
        input.classList.remove("is-valid");
        input.value = "";
      }

      // Hide progress after short delay
      progress.hide(1500);
    }

  });
}

/* =====================================================
   VALIDATION SIMULATION (60 → 98%)
   Runs independently of backend timing
===================================================== */
function startValidationSimulation(progress) {
  let percent = 62;
  const stages = [
    { id: "extract", label: "Extracting document text…", cap: 72 },
    { id: "ai", label: "Sending document to AI analysis…", cap: 84 },
    { id: "parse", label: "Parsing extracted applicant details…", cap: 92 },
    { id: "save", label: "Saving attachment and autofill data…", cap: 98 }
  ];
  let i = 0;

  return setInterval(() => {
    if (percent >= 98) return;
    percent += Math.random() * 4;
    const stage = stages[Math.min(i, stages.length - 1)];
    progress.set(
      Math.min(98, Math.round(percent)),
      stage.label,
      stage.id
    );
    if (percent >= stage.cap && i < stages.length - 1) {
      i++;
    }
  }, 700);
}

(function () {
  const trigger = document.getElementById("smartAutofillTrigger");
  const startButton = document.getElementById("smartAutofillStart");
  const input = document.getElementById("smartAutofillInput");
  const statusEl = document.getElementById("smartAutofillStatus");
  const queueWrap = document.getElementById("smartAutofillQueueWrap");
  const queueHint = document.getElementById("smartAutofillQueueHint");
  const queueEl = document.getElementById("smartAutofillQueue");
  const progressWrap = document.getElementById("smartAutofillProgressWrap");
  const progressText = document.getElementById("smartAutofillProgressText");
  const progressLabel = document.getElementById("smartAutofillProgressLabel");
  const progressSubtext = document.getElementById("smartAutofillProgressSubtext");
  const stagePillsEl = document.getElementById("smartAutofillStagePills");
  const helpEl = document.getElementById("smartAutofillHelp");
  const studyChoicesWrap = document.getElementById("studyChoices");
  const regionsSelect = document.getElementById("regions");
  const panelsEl = document.getElementById("smartAutofillPanels");
  const resultsEl = document.getElementById("smartAutofillResults");
  const warningsWrapEl = document.getElementById("smartAutofillWarningsWrap");
  const warningsEl = document.getElementById("smartAutofillWarnings");
  const progressBarWrap = document.getElementById("smartAutofillProgressBarWrap");
  const progressBar = document.getElementById("smartAutofillProgressBar");
  const elapsedEl = document.getElementById("smartAutofillElapsed");
  const liveStatusEl = document.getElementById("smartAutofillLiveStatus");

  if (
    !trigger || !startButton || !input || !statusEl || !queueWrap || !queueHint || !queueEl ||
    !progressWrap || !progressText || !progressLabel || !progressSubtext || !stagePillsEl ||
    !panelsEl || !resultsEl || !warningsWrapEl || !warningsEl ||
    !progressBarWrap || !progressBar || !elapsedEl || !liveStatusEl
  ) {
    return;
  }

  const texts = {
    processing: <?php echo json_encode($t['smart_autofill_processing'], JSON_UNESCAPED_UNICODE); ?>,
    uploading: <?php echo json_encode($t['smart_autofill_uploading'], JSON_UNESCAPED_UNICODE); ?>,
    success: <?php echo json_encode($t['smart_autofill_success'], JSON_UNESCAPED_UNICODE); ?>,
    partial: <?php echo json_encode($t['smart_autofill_partial'], JSON_UNESCAPED_UNICODE); ?>,
    error: <?php echo json_encode($t['smart_autofill_error'], JSON_UNESCAPED_UNICODE); ?>,
    needDraft: <?php echo json_encode($t['smart_autofill_need_draft'], JSON_UNESCAPED_UNICODE); ?>,
    gate: <?php echo json_encode($t['smart_autofill_gate'], JSON_UNESCAPED_UNICODE); ?>,
    ready: <?php echo json_encode($t['smart_autofill_ready'], JSON_UNESCAPED_UNICODE); ?>,
    queueEmpty: <?php echo json_encode($t['smart_autofill_queue_empty'], JSON_UNESCAPED_UNICODE); ?>,
    queueReady: <?php echo json_encode($t['smart_autofill_queue_ready'], JSON_UNESCAPED_UNICODE); ?>,
    queueCount: <?php echo json_encode($t['smart_autofill_queue_count'], JSON_UNESCAPED_UNICODE); ?>,
    analyzingDoc: <?php echo json_encode($t['smart_autofill_analyzing_doc'], JSON_UNESCAPED_UNICODE); ?>,
    analysisIntro: <?php echo json_encode($t['smart_autofill_analysis_intro'], JSON_UNESCAPED_UNICODE); ?>,
    submitDone: "Application submitted successfully. Any missing details can be edited later from retrieval or the student portal.",
    draftOnly: "Draft saved. No strong personal identity details were found yet, so the application was not submitted.",
    submitAttempt: "Submitting application, sending email, and creating student portal access..."
  };

  const stageMeta = [
    { id: "queue", label: <?php echo json_encode($t['smart_autofill_stage_queue'], JSON_UNESCAPED_UNICODE); ?>, short: "Queue" },
    { id: "draft", label: <?php echo json_encode($t['smart_autofill_stage_draft'], JSON_UNESCAPED_UNICODE); ?>, short: "Draft" },
    { id: "batch", label: <?php echo json_encode($t['smart_autofill_stage_batch'], JSON_UNESCAPED_UNICODE); ?>, short: "AI" },
    { id: "route", label: <?php echo json_encode($t['smart_autofill_stage_route'], JSON_UNESCAPED_UNICODE); ?>, short: "Route" },
    { id: "save", label: <?php echo json_encode($t['smart_autofill_stage_save'], JSON_UNESCAPED_UNICODE); ?>, short: "Save" },
    { id: "submit", label: <?php echo json_encode($t['smart_autofill_stage_submit'], JSON_UNESCAPED_UNICODE); ?>, short: "Send" }
  ];

  const pendingFiles = [];
  let isProcessing = false;
  let autofillTimerId = null;
  let autofillStartedAt = 0;

  function stopAutofillTimer() {
    if (autofillTimerId != null) {
      window.clearInterval(autofillTimerId);
      autofillTimerId = null;
    }
  }

  function startAutofillTimer() {
    stopAutofillTimer();
    autofillStartedAt = Date.now();
    progressBarWrap.classList.remove("d-none");
    progressBar.style.width = "2%";
    elapsedEl.textContent = "0:00";

    autofillTimerId = window.setInterval(() => {
      const elapsedSec = Math.floor((Date.now() - autofillStartedAt) / 1000);
      const mins = Math.floor(elapsedSec / 60);
      const secs = elapsedSec % 60;
      elapsedEl.textContent = `${mins}:${String(secs).padStart(2, "0")}`;
    }, 1000);
  }

  function setAutofillProgress(pct, statusText) {
    progressBar.style.width = `${Math.min(100, Math.max(0, Math.round(pct)))}%`;
    if (statusText) {
      liveStatusEl.textContent = statusText;
    }
  }

  function stopWaitAnimation(successMessage) {
    if (successMessage) {
      liveStatusEl.textContent = successMessage;
    }
  }

  function startWaitAnimation(files) {
    startAutofillTimer();
    setAutofillProgress(
      4,
      `Sending ${files.length} file(s) to Gemini in parallel…`
    );
  }

  function fileKey(file) {
    return [file.name, file.size, file.lastModified].join("::");
  }

  function clearPanels() {
    resultsEl.innerHTML = "";
    warningsEl.innerHTML = "";
    panelsEl.classList.add("d-none");
    warningsWrapEl.classList.add("d-none");
  }

  function resetProgress() {
    stopAutofillTimer();
    stopWaitAnimation();
    progressWrap.className = "smart-autofill-progress-panel";
    progressText.textContent = "Ready";
    progressLabel.textContent = texts.processing;
    progressSubtext.textContent = <?php echo json_encode($t['smart_autofill_hint'], JSON_UNESCAPED_UNICODE); ?>;
    stagePillsEl.innerHTML = "";
    progressBarWrap.classList.add("d-none");
    progressBar.style.width = "0%";
    elapsedEl.textContent = "0:00";
    liveStatusEl.textContent = "";
  }

  function setStatus(kind, message) {
    statusEl.className = "alert mt-3 mb-0";
    if (kind === "success") {
      statusEl.classList.add("alert-success");
    } else if (kind === "warning") {
      statusEl.classList.add("alert-warning");
    } else if (kind === "danger") {
      statusEl.classList.add("alert-danger");
    } else {
      statusEl.classList.add("alert-info");
    }
    statusEl.textContent = message;
    statusEl.classList.remove("d-none");
  }

  function renderStagePills(activeId, kind = "info") {
    stagePillsEl.innerHTML = "";
    const activeIndex = stageMeta.findIndex(stage => stage.id === activeId);

    stageMeta.forEach((stage, index) => {
      const pill = document.createElement("span");
      pill.className = "smart-autofill-stage-pill";
      pill.textContent = stage.label;

      if (activeIndex > -1 && index < activeIndex) {
        pill.classList.add("is-done");
      } else if (stage.id === activeId) {
        pill.classList.add(kind === "danger" ? "is-error" : "is-active");
      }

      if (kind === "success" && activeIndex > -1 && index <= activeIndex) {
        pill.classList.remove("is-active");
        pill.classList.add("is-done");
      }

      if (kind === "warning" && stage.id === activeId) {
        pill.classList.remove("is-active");
        pill.classList.add("is-error");
      }

      stagePillsEl.appendChild(pill);
    });
  }

  function setStage(stageId, message, kind = "info", subtext = "") {
    progressWrap.className = "smart-autofill-progress-panel active";
    if (kind === "success") {
      progressWrap.classList.add("is-success");
    } else if (kind === "warning") {
      progressWrap.classList.add("is-warning");
    } else if (kind === "danger") {
      progressWrap.classList.add("is-danger");
    }

    const stage = stageMeta.find(item => item.id === stageId);
    progressText.textContent = stage?.short || "AI";
    progressLabel.textContent = message;
    progressSubtext.textContent = subtext || stage?.label || "";
    renderStagePills(stageId, kind);
    setStatus(kind, message);
  }

  function renderPanels(documents, warnings) {
    resultsEl.innerHTML = "";
    warningsEl.innerHTML = "";

    if (Array.isArray(documents) && documents.length) {
      documents.forEach(doc => {
        const li = document.createElement("li");
        const title = document.createElement("strong");
        const detail = document.createElement("small");
        title.textContent = `${doc.original_name} -> ${doc.field_label || doc.field || "Unmatched"}`;
        detail.textContent = doc.summary || "";
        li.appendChild(title);
        if (detail.textContent) li.appendChild(detail);
        resultsEl.appendChild(li);
      });
      panelsEl.classList.remove("d-none");
    }

    if (Array.isArray(warnings) && warnings.length) {
      warnings.forEach(message => {
        const li = document.createElement("li");
        const title = document.createElement("strong");
        title.textContent = "Warning";
        li.appendChild(title);
        if (message) {
          const detail = document.createElement("small");
          detail.textContent = message;
          li.appendChild(detail);
        }
        warningsEl.appendChild(li);
      });
      warningsWrapEl.classList.remove("d-none");
      panelsEl.classList.remove("d-none");
    }
  }

  function hasSelectedPrograms() {
    let hasPrograms = false;

    document.querySelectorAll(".study-choice .program").forEach(programEl => {
      const values = window.jQuery ? $(programEl).val() : programEl.value;
      const ids = Array.isArray(values) ? values : values ? [values] : [];
      if (ids.length && ids.some(id => id && String(id).trim() !== "")) {
        hasPrograms = true;
      }
    });

    return hasPrograms;
  }

  function hasCoreApplicantInfo(fields) {
    const values = fields || {};
    const first = String(values.first_name || "").trim();
    const last = String(values.last_name || "").trim();
    // Final Smart AI submit requires both names (matches server looks_like_human_name gate).
    return first.length >= 2 && last.length >= 2;
  }

  function applyMergedAutofillFields(aiFields) {
    if (typeof window.applyAutofillFields === "function") {
      window.applyAutofillFields(aiFields);
    }
    if (typeof window.syncApplicantPhoneHiddenFields === "function") {
      window.syncApplicantPhoneHiddenFields();
    }
    return aiFields || {};
  }

  function renderQueue() {
    queueWrap.classList.add("is-visible");
    queueEl.innerHTML = "";

    if (!pendingFiles.length) {
      queueHint.textContent = texts.queueEmpty;
    } else {
      queueHint.textContent = `${pendingFiles.length} ${texts.queueCount}. ${texts.queueReady}`;
      pendingFiles.forEach(file => {
        const li = document.createElement("li");
        li.className = "smart-autofill-queue-item";

        const name = document.createElement("span");
        name.className = "smart-autofill-queue-name";
        name.textContent = file.name;

        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "smart-autofill-remove";
        remove.setAttribute("aria-label", `Remove ${file.name}`);
        remove.dataset.fileKey = fileKey(file);
        remove.textContent = "×";
        remove.disabled = isProcessing;

        li.appendChild(name);
        li.appendChild(remove);
        queueEl.appendChild(li);
      });
    }
  }

  function updateSmartAutofillAvailability() {
    const ready = hasSelectedPrograms();
    trigger.disabled = !ready || isProcessing;
    startButton.disabled = !ready || isProcessing || !pendingFiles.length;
    if (helpEl) {
      helpEl.innerHTML = `${ready ? texts.ready : texts.gate}<br><?php echo htmlspecialchars($t['smart_autofill_formats'], ENT_QUOTES, 'UTF-8'); ?><br><?php echo htmlspecialchars($t['smart_autofill_hint'], ENT_QUOTES, 'UTF-8'); ?>`;
    }
    renderQueue();
  }

  function readExistingApplicationId() {
    const candidates = [
      window.currentApplicationId,
      document.querySelector('[name="application_id"]')?.value,
      document.getElementById("applicationForm")?.dataset.resumeAppId
    ];
    for (const raw of candidates) {
      const n = Number(raw);
      if (Number.isFinite(n) && n > 0) return n;
    }
    return 0;
  }

  async function ensureDraftExists() {
    const existingId = readExistingApplicationId();
    const fd = new FormData();
    if (existingId > 0) {
      fd.append("application_id", String(existingId));
    }
    const userId = document.querySelector('[name="user_id"]')?.value || "";
    if (userId) {
      fd.append("user_id", userId);
    }
    if (typeof collectStudyChoices === "function") {
      const choices = collectStudyChoices();
      if (choices.length) {
        fd.append("study_choices", JSON.stringify(choices));
      }
    }

    let data = null;
    let res = null;
    try {
      res = await fetch("ensure_application_draft.php", {
        method: "POST",
        body: fd,
        credentials: "same-origin"
      });
      data = await res.json();
    } catch (err) {
      data = null;
    }

    if (!data || data.status !== "success" || !data.application_id) {
      try {
        const fallbackFd = typeof buildApplicationFormData === "function"
          ? buildApplicationFormData({ includeStep: true, stepValue: 0, final: false })
          : fd;
        if (existingId > 0) {
          fallbackFd.set("application_id", String(existingId));
        }
        res = await fetch("save_application.php", {
          method: "POST",
          body: fallbackFd,
          credentials: "same-origin"
        });
        data = await res.json();
      } catch (err) {
        throw new Error(texts.needDraft);
      }
    }

    if (!data || data.status !== "success" || !data.application_id) {
      throw new Error(data?.message || data?.debug || texts.needDraft);
    }

    const applicationId = Number(data.application_id);
    if (!Number.isFinite(applicationId) || applicationId <= 0) {
      throw new Error(texts.needDraft);
    }

    if (typeof syncApplicationIdToForm === "function") {
      syncApplicationIdToForm(applicationId);
    } else {
      window.currentApplicationId = applicationId;
      const hiddenIdField = document.querySelector('input[name="application_id"]');
      if (hiddenIdField) hiddenIdField.value = String(applicationId);
    }

    return applicationId;
  }

  const attachmentFieldLabels = {
    degree_transcripts: <?php echo json_encode($t['degree_transcripts'] ?? 'Degree / Academic Transcripts', JSON_UNESCAPED_UNICODE); ?>,
    high_school_degree: <?php echo json_encode($t['high_school_degree'] ?? 'High School Certificate', JSON_UNESCAPED_UNICODE); ?>,
    valid_passport: <?php echo json_encode($t['valid_passport'] ?? 'Valid Passport', JSON_UNESCAPED_UNICODE); ?>,
    recommendation_letters: <?php echo json_encode($t['recommendation_letters'] ?? 'Recommendation Letter(s)', JSON_UNESCAPED_UNICODE); ?>,
    personal_statement: <?php echo json_encode($t['personal_statement'] ?? 'Personal Statement', JSON_UNESCAPED_UNICODE); ?>,
    cv_resume: <?php echo json_encode($t['cv_resume'] ?? 'CV / Resume', JSON_UNESCAPED_UNICODE); ?>,
    english_certificate: <?php echo json_encode($t['english_certificate'] ?? 'English Certificate', JSON_UNESCAPED_UNICODE); ?>,
    birth_certificate: <?php echo json_encode($t['birth_certificate'] ?? 'Birth Certificate', JSON_UNESCAPED_UNICODE); ?>,
    payment_proof: <?php echo json_encode($t['payment_proof'] ?? 'Payment Proof', JSON_UNESCAPED_UNICODE); ?>
  };

  function guessAttachmentFieldFromFilename(name) {
    const n = String(name || "").toLowerCase();
    if (/\b(passport|passeport)\b/.test(n)) return "valid_passport";
    if (/\b(cv|resume|curriculum|vitae|biodata|bio[\s_-]?data|profile)\b/.test(n)) return "cv_resume";
    if (/\b(transcript|releve|relevé|academic|grade|diploma|degree)\b/.test(n)) return "degree_transcripts";
    if (/\b(high[\s_-]?school|lycee|lycée|baccalaureat|secondary)\b/.test(n)) return "high_school_degree";
    if (/\b(birth[\s_-]?cert|naissance)\b/.test(n)) return "birth_certificate";
    if (/\b(ielts|toefl|english|anglais|duolingo|pte|celpip)\b/.test(n)) return "english_certificate";
    if (/\b(recommend|reference[\s_-]?letter)\b/.test(n)) return "recommendation_letters";
    if (/\b(motivation|personal[\s_-]?statement|cover[\s_-]?letter)\b/.test(n)) return "personal_statement";
    if (/\b(payment|receipt|invoice|proof|paid)\b/.test(n)) return "payment_proof";
    return "";
  }

  function mergeAutofillFields(target, incoming) {
    if (!incoming || typeof incoming !== "object") return;
    const priority = {
      first_name: ["valid_passport", "birth_certificate", "cv_resume"],
      last_name: ["valid_passport", "birth_certificate", "cv_resume"],
      passport_number: ["valid_passport"],
      student_national_id: ["valid_passport", "birth_certificate"],
      dob: ["valid_passport", "birth_certificate"],
      gender: ["valid_passport", "birth_certificate"],
      nationality: ["valid_passport", "birth_certificate", "cv_resume"],
      country_of_birth: ["valid_passport", "birth_certificate"],
      city_of_birth: ["valid_passport", "birth_certificate"],
      email: ["cv_resume", "personal_statement"],
      area_code: ["cv_resume", "personal_statement"],
      phone_number: ["cv_resume", "personal_statement"],
      address_line1: ["cv_resume", "valid_passport", "personal_statement"],
      address_line2: ["cv_resume", "valid_passport"],
      city: ["cv_resume", "valid_passport"],
      state_province: ["cv_resume", "valid_passport"],
      postal_code: ["cv_resume", "valid_passport"]
    };
    if (!target.__mergeScores) target.__mergeScores = {};

    Object.entries(incoming).forEach(([key, value]) => {
      if (value === null || value === undefined || String(value).trim() === "") return;
      const source = incoming.__documentType || "unknown";
      const conf = Number(incoming.__confidence || 0.7);
      const prefs = priority[key] || [];
      const rank = prefs.indexOf(source);
      const score = (rank >= 0 ? (prefs.length - rank) * 100 : 0) + conf * 100;
      const prev = Number(target.__mergeScores[key] || -1);
      if (!target[key] || score >= prev) {
        target[key] = value;
        target.__mergeScores[key] = score;
      }
    });
  }

  function pickContactRefineFile(files) {
    const ranked = files
      .map((file, index) => ({ file, index, name: String(file.name || "").toLowerCase() }))
      .sort((a, b) => {
        const score = (name) => {
          if (/\b(cv|resume|curriculum|vitae)\b/.test(name)) return 3;
          if (/\b(passport|passeport)\b/.test(name)) return 2;
          return 1;
        };
        return score(b.name) - score(a.name);
      });
    return ranked[0]?.file || null;
  }

  function pickPassportRefineFile(files) {
    const ranked = files
      .map((file, index) => ({ file, index, name: String(file.name || "").toLowerCase() }))
      .filter(entry => /\b(passport|passeport|travel|id|identity)\b/.test(entry.name))
      .sort((a, b) => {
        const score = (name) => (/\b(passport|passeport)\b/.test(name) ? 2 : 1);
        return score(b.name) - score(a.name);
      });
    return ranked[0]?.file || null;
  }

  async function refineMissingPassportField(files, applicationId, merged) {
    if (String(merged.fields?.passport_number || "").trim()) return;

    const passportFile = pickPassportRefineFile(files);
    if (!passportFile) return;

    liveStatusEl.textContent = `${passportFile.name} — smart passport number extraction…`;

    const formData = new FormData();
    formData.append("documents[]", passportFile);
    formData.append("document_client_index", String(files.indexOf(passportFile)));
    formData.append("application_id", applicationId);
    formData.append("lang", document.documentElement.lang || "en");
    formData.append("passport_only", "1");

    try {
      const res = await fetch("student_ai_autofill.php", {
        method: "POST",
        body: formData,
        credentials: "same-origin"
      });
      const data = await res.json();
      if (!res.ok || !data || data.status !== "success") return;

      mergeAutofillFields(merged.fields, {
        ...(data.fields || {}),
        __documentType: "valid_passport",
        __confidence: 0.95
      });
      applyMergedAutofillFields(data.fields || {});
      if (Array.isArray(data.warnings)) {
        merged.warnings.push(...data.warnings);
      }
    } catch (err) {
      merged.warnings.push(
        `${passportFile.name}: passport refinement failed (${err?.message || "error"}).`
      );
    }
  }

  async function refineMissingContactFields(files, applicationId, merged) {
    const needsEmail = () => !String(merged.fields?.email || "").trim();
    const needsPhone = () => !String(merged.fields?.phone_number || "").trim();
    if (!needsEmail() && !needsPhone()) return;

    const ranked = files
      .map((file, index) => ({ file, index, name: String(file.name || "").toLowerCase() }))
      .sort((a, b) => {
        const score = (name) => {
          if (/\b(cv|resume|curriculum|vitae|biodata|profile)\b/.test(name)) return 4;
          if (/\b(passport|passeport)\b/.test(name)) return 3;
          if (/\b(statement|recommend|letter)\b/.test(name)) return 2;
          return 1;
        };
        return score(b.name) - score(a.name);
      });

    for (const entry of ranked) {
      if (!needsEmail() && !needsPhone()) break;

      liveStatusEl.textContent = `${entry.file.name} — smart contact extraction (email & phone)…`;

      const formData = new FormData();
      formData.append("documents[]", entry.file);
      formData.append("document_client_index", String(entry.index));
      formData.append("application_id", applicationId);
      formData.append("lang", document.documentElement.lang || "en");
      formData.append("contact_only", "1");

      try {
        const res = await fetch("student_ai_autofill.php", {
          method: "POST",
          body: formData,
          credentials: "same-origin"
        });
        const data = await res.json();
        if (!res.ok || !data || data.status !== "success") continue;

        mergeAutofillFields(merged.fields, {
          ...(data.fields || {}),
          __documentType: guessAttachmentFieldFromFilename(entry.file.name) || "cv_resume",
          __confidence: 0.92
        });
        applyMergedAutofillFields(data.fields || {});
        if (Array.isArray(data.warnings)) {
          merged.warnings.push(...data.warnings);
        }
      } catch (err) {
        merged.warnings.push(
          `${entry.file.name}: contact refinement failed (${err?.message || "error"}).`
        );
      }
    }
  }

  async function analyzeDocumentsBatch(files, applicationId) {
    const merged = {
      status: "success",
      fields: {},
      documents: [],
      warnings: [],
      upload_token: ""
    };
    const ANALYSIS_CONCURRENCY = 3;
    const ANALYSIS_TIMEOUT_MS = 120000;
    let finished = 0;

    async function analyzeOne(file, index) {
      const label = `Document ${index + 1} of ${files.length}: ${file.name}`;
      setStage("batch", texts.processing, "info", label);
      liveStatusEl.textContent = `${file.name} — sending to Gemini…`;

      const formData = new FormData();
      formData.append("documents[]", file);
      formData.append("document_client_index", String(index));
      formData.append("application_id", applicationId);
      formData.append("lang", document.documentElement.lang || "en");

      const controller = new AbortController();
      const timeoutId = window.setTimeout(() => controller.abort(), ANALYSIS_TIMEOUT_MS);

      try {
        const analysisResponse = await fetch("student_ai_autofill.php", {
          method: "POST",
          body: formData,
          credentials: "same-origin",
          signal: controller.signal
        });

        let analysisData = null;
        try {
          analysisData = await analysisResponse.json();
        } catch (parseErr) {
          throw new Error(`${file.name}: invalid server response`);
        }

        if (!analysisResponse.ok || !analysisData || analysisData.status !== "success") {
          merged.warnings.push(`${file.name}: ${analysisData?.message || texts.error}`);
          return null;
        }

        applyMergedAutofillFields(analysisData.fields || {});

        const docMeta = (analysisData.documents && analysisData.documents[0]) || {};
        const fieldsWithMeta = {
          ...(analysisData.fields || {}),
          __documentType: docMeta.field || guessAttachmentFieldFromFilename(file.name) || "unknown",
          __confidence: Number(docMeta.confidence || 0.75)
        };
        mergeAutofillFields(merged.fields, fieldsWithMeta);
        (analysisData.documents || []).forEach(doc => {
          merged.documents.push({
            ...doc,
            client_index: index,
            original_name: doc.original_name || file.name
          });
        });
        merged.warnings.push(...(analysisData.warnings || []));
        if (analysisData.upload_token) {
          merged.upload_token = analysisData.upload_token;
        }

        return analysisData;
      } catch (err) {
        const msg = err && err.name === "AbortError"
          ? `${file.name}: timed out after 2 minutes.`
          : `${file.name}: ${err && err.message ? err.message : texts.error}`;
        merged.warnings.push(msg);
        return null;
      } finally {
        window.clearTimeout(timeoutId);
        finished += 1;
        const analysisPct = 8 + Math.round((finished / files.length) * 47);
        setAutofillProgress(
          analysisPct,
          finished >= files.length
            ? "All documents analyzed — refining extracted details…"
            : `${finished}/${files.length} analyzed — continuing…`
        );
      }
    }

    const taskFactories = files.map((file, index) => () => analyzeOne(file, index));
    await runTasksWithPool(taskFactories, ANALYSIS_CONCURRENCY);

    setAutofillProgress(58, "Checking for missing email, phone, and passport number…");
    await refineMissingContactFields(files, applicationId, merged);
    await refineMissingPassportField(files, applicationId, merged);

    const cleanFields = { ...merged.fields };
    delete cleanFields.__mergeScores;
    applyMergedAutofillFields(cleanFields);

    setAutofillProgress(60, "Analysis complete — routing files next…");

    if (!merged.documents.length && !Object.keys(merged.fields).length) {
      throw new Error(merged.warnings[0] || "No document could be analyzed successfully.");
    }

    return merged;
  }

  async function runTasksWithPool(taskFactories, concurrency) {
    const results = new Array(taskFactories.length);
    let nextIndex = 0;
    const workerCount = Math.max(1, Math.min(concurrency, taskFactories.length));

    async function worker() {
      while (nextIndex < taskFactories.length) {
        const current = nextIndex++;
        results[current] = await taskFactories[current]();
      }
    }

    await Promise.all(Array.from({ length: workerCount }, () => worker()));
    return results;
  }

  function buildUploadQueue(documents, files = []) {
    const multiFieldSet = new Set(["degree_transcripts", "recommendation_letters"]);
    const grouped = new Map();
    const warnings = [];
    const queue = [];

    (Array.isArray(documents) ? documents : []).forEach(doc => {
      if (!doc) return;
      const filenameGuess = guessAttachmentFieldFromFilename(doc.original_name);
      if (filenameGuess) {
        doc.field = filenameGuess;
        doc.field_label = attachmentFieldLabels[filenameGuess] || filenameGuess;
      } else if (!doc.field) {
        const summaryGuess = guessAttachmentFieldFromSummary(doc.summary);
        if (summaryGuess) {
          doc.field = summaryGuess;
          doc.field_label = attachmentFieldLabels[summaryGuess] || summaryGuess;
        } else {
          return;
        }
      }
      if (!doc.field) return;
      if (!grouped.has(doc.field)) grouped.set(doc.field, []);
      grouped.get(doc.field).push(doc);
    });

    for (const [field, docs] of grouped.entries()) {
      const ranked = [...docs].sort(
        (a, b) => Number(b?.confidence || 0) - Number(a?.confidence || 0)
      );

      const selected = multiFieldSet.has(field) ? ranked : ranked.slice(0, 1);
      const skipped = multiFieldSet.has(field) ? [] : ranked.slice(1);

      skipped.forEach(doc => {
        warnings.push(`Skipped ${doc.original_name} because another file was a stronger match for ${doc.field_label || field}.`);
      });

      selected.forEach(doc => queue.push(doc));
    }

    const coveredFields = new Set(queue.map(doc => doc.field));
    const queuedNames = new Set(
      queue.map(doc => String(doc.original_name || "").toLowerCase())
    );
    const queuedIndices = new Set(
      queue.map(doc => Number(doc.client_index))
    );

    (Array.isArray(files) ? files : []).forEach((file, index) => {
      const fileName = String(file.name || "").toLowerCase();
      if (queuedNames.has(fileName) || queuedIndices.has(index)) return;

      const guess = guessAttachmentFieldFromFilename(file.name);
      if (!guess) return;

      if (multiFieldSet.has(guess)) {
        queue.push({
          client_index: index,
          original_name: file.name,
          field: guess,
          field_label: attachmentFieldLabels[guess] || guess,
          confidence: 0.75
        });
        queuedNames.add(fileName);
        queuedIndices.add(index);
        return;
      }

      if (coveredFields.has(guess)) return;

      queue.push({
        client_index: index,
        original_name: file.name,
        field: guess,
        field_label: attachmentFieldLabels[guess] || guess,
        confidence: 0.75
      });
      coveredFields.add(guess);
      queuedNames.add(fileName);
      queuedIndices.add(index);
    });

    const orphanFiles = [];
    (Array.isArray(files) ? files : []).forEach((file, index) => {
      const fileName = String(file.name || "").toLowerCase();
      if (queuedNames.has(fileName) || queuedIndices.has(index)) return;
      orphanFiles.push({ file, index, fileName });
    });

    if (orphanFiles.length === 1 && !coveredFields.has("cv_resume")) {
      const orphan = orphanFiles[0];
      queue.push({
        client_index: orphan.index,
        original_name: orphan.file.name,
        field: "cv_resume",
        field_label: attachmentFieldLabels.cv_resume || "cv_resume",
        confidence: 0.5
      });
      coveredFields.add("cv_resume");
      warnings.push(`Assigned ${orphan.file.name} to CV / Resume because no CV was detected automatically.`);
    } else {
      orphanFiles.forEach(orphan => {
        warnings.push(`Could not auto-route ${orphan.file.name} to a form attachment field.`);
      });
    }

    return { queue, warnings };
  }

  function guessAttachmentFieldFromSummary(summary) {
    const text = String(summary || "").toLowerCase();
    if (!text) return "";
    if (/\b(passport|passeport|travel document)\b/.test(text)) return "valid_passport";
    if (/\b(cv|resume|curriculum vitae|employment history|work experience)\b/.test(text)) return "cv_resume";
    if (/\b(transcript|academic record|grade|diploma|degree)\b/.test(text)) return "degree_transcripts";
    if (/\b(high school|secondary|baccalaureat|lycee|lycée)\b/.test(text)) return "high_school_degree";
    if (/\b(birth certificate|acte de naissance)\b/.test(text)) return "birth_certificate";
    if (/\b(ielts|toefl|english|duolingo|language proficiency)\b/.test(text)) return "english_certificate";
    if (/\b(recommendation|reference letter)\b/.test(text)) return "recommendation_letters";
    if (/\b(personal statement|motivation letter|cover letter)\b/.test(text)) return "personal_statement";
    if (/\b(payment|receipt|invoice|proof of payment)\b/.test(text)) return "payment_proof";
    return "";
  }

  async function routeQueuedDocuments(queue, files, options = {}) {
    const warnings = [];
    let attachFailures = 0;
    let nextIndex = 0;
    let routed = 0;
    const total = queue.length;
    const concurrency = Math.max(
      1,
      Math.min(Number(options.concurrency) || 1, 3, queue.length || 1)
    );

    async function worker() {
      while (nextIndex < queue.length) {
        const currentIndex = nextIndex++;
        const doc = queue[currentIndex];
        let file = files[Number(doc?.client_index)];
        if (!file && doc?.original_name) {
          const wanted = String(doc.original_name).toLowerCase();
          file = files.find(f => String(f.name || "").toLowerCase() === wanted);
        }

        if (!file) {
          warnings.push(`Original file missing for ${doc?.original_name || "document"}.`);
          routed += 1;
          continue;
        }

        setAutofillProgress(
          62 + Math.round((routed / Math.max(total, 1)) * 23),
          `Attaching ${doc.original_name} → ${doc.field_label || doc.field} (${Math.min(routed + 1, total)}/${total})…`
        );

        try {
          await uploadSingleFile(doc.field, file, {
            skipAiValidation: Boolean(options.smartAutofillBatchToken),
            smartAutofillBatchToken: options.smartAutofillBatchToken || ""
          });
        } catch (err) {
          attachFailures++;
          warnings.push(`Failed to attach ${doc.original_name} to ${doc.field_label || doc.field}.`);
        } finally {
          routed += 1;
          setAutofillProgress(
            62 + Math.round((routed / Math.max(total, 1)) * 23),
            routed >= total
              ? "All recognized documents attached — saving form data next…"
              : `Attached ${routed}/${total} — continuing…`
          );
        }
      }
    }

    await Promise.all(
      Array.from({ length: concurrency }, () => worker())
    );

    return { attachFailures, warnings };
  }

  function addPendingFiles(files) {
    const knownKeys = new Set(pendingFiles.map(fileKey));
    files.forEach(file => {
      if (!file || knownKeys.has(fileKey(file))) return;
      pendingFiles.push(file);
      knownKeys.add(fileKey(file));
    });
  }

  function clearPendingFiles() {
    pendingFiles.length = 0;
    renderQueue();
    updateSmartAutofillAvailability();
  }

  trigger.addEventListener("click", () => input.click());

  queueEl.addEventListener("click", event => {
    const removeBtn = event.target.closest(".smart-autofill-remove");
    if (!removeBtn || isProcessing) return;

    const targetKey = removeBtn.dataset.fileKey;
    const nextFiles = pendingFiles.filter(file => fileKey(file) !== targetKey);
    pendingFiles.length = 0;
    nextFiles.forEach(file => pendingFiles.push(file));
    renderQueue();
    updateSmartAutofillAvailability();
  });

  input.addEventListener("change", () => {
    const files = Array.from(input.files || []);
    input.value = "";
    if (!files.length) return;

    addPendingFiles(files);
    clearPanels();
    resetProgress();
    setStatus("info", texts.queueReady);
    setStage("queue", texts.queueReady, "info", `${pendingFiles.length} ${texts.queueCount}`);
    updateSmartAutofillAvailability();
  });

  startButton.addEventListener("click", async () => {
    if (!pendingFiles.length || isProcessing) return;

    isProcessing = true;
    clearPanels();
    updateSmartAutofillAvailability();

    try {
      startAutofillTimer();
      setAutofillProgress(4, "Preparing application draft…");
      setStage("draft", <?php echo json_encode($t['smart_autofill_stage_draft'], JSON_UNESCAPED_UNICODE); ?>, "info", "Preparing your application draft for document routing.");
      const applicationId = await ensureDraftExists();

      const files = [...pendingFiles];

      setStage("batch", texts.processing, "info", texts.analysisIntro);
      setAutofillProgress(8, texts.analysisIntro);
      const analysisData = await analyzeDocumentsBatch(files, applicationId);

      setStage(
        "route",
        texts.uploading,
        "info",
        "Analysis finished. Attaching recognized documents to form fields…"
      );

      const { queue, warnings: queueWarnings } = buildUploadQueue(analysisData.documents || [], files);
      const warnings = [...(analysisData.warnings || []), ...queueWarnings];
      const batchUploadToken = typeof analysisData.upload_token === "string"
        ? analysisData.upload_token
        : "";

      setAutofillProgress(
        62,
        queue.length
          ? `Routing ${queue.length} file(s) to attachment fields…`
          : "No files to route — saving extracted data next…"
      );
      const routeResult = await routeQueuedDocuments(queue, files, {
        concurrency: batchUploadToken ? 5 : 3,
        smartAutofillBatchToken: batchUploadToken
      });
      warnings.push(...routeResult.warnings);
      const attachFailures = routeResult.attachFailures;

      const saveFields = { ...analysisData.fields };
      delete saveFields.__mergeScores;
      const mergedFields = applyMergedAutofillFields(saveFields);

      setStage("save", <?php echo json_encode($t['smart_autofill_stage_save'], JSON_UNESCAPED_UNICODE); ?>, "info", "Saving extracted student details and current study choices.");
      setAutofillProgress(88, "Saving extracted student details…");
      try {
        if (typeof window.persistAutofillDraftData === "function") {
          await window.persistAutofillDraftData(applicationId, mergedFields);
        }
      } catch (err) {
        warnings.push(err && err.message ? err.message : "Autofilled form values were applied, but saving the draft needs another try.");
      }

      renderPanels(analysisData.documents || [], warnings);
      clearPendingFiles();

      if (!hasCoreApplicantInfo(mergedFields)) {
        setAutofillProgress(100, texts.draftOnly);
        setStage("save", texts.draftOnly, "warning", "You can add more documents later and run the analysis again.");
        return;
      }

      if (typeof collectStudyChoices === "function" && collectStudyChoices().length === 0) {
        setStage(
          "submit",
          "Select at least one study program on step 1, then run Start analysis again to submit.",
          "warning",
          "Study choice is required before final submission."
        );
        return;
      }

      if (attachFailures > 0) {
        warnings.push("Some documents could not be attached, but the application will still continue to final submission with the details already extracted.");
        renderPanels(analysisData.documents || [], warnings);
      }

      applyMergedAutofillFields(mergedFields);
      if (typeof window.syncApplicantPhoneHiddenFields === "function") {
        window.syncApplicantPhoneHiddenFields();
      }

      setStage("submit", texts.submitAttempt, "info", "Continuing automatically to the final submission even if only one identity document was provided.");
      setAutofillProgress(94, texts.submitAttempt);
      const submitted = await submitForm({
        autoAssignDefaultAgent: true,
        identityOnlySubmit: true,
        successTitle: "Application Submitted",
        successMessage:
          "Application submitted successfully. If a student email was available, the email and portal access were sent. Related program proposals at other universities were queued and emailed to the assigned person / university admins in charge. You can edit any missing details later from retrieval or the student portal."
      });

      if (!submitted) {
        if (typeof submitForm.duplicateEmailConflict !== "undefined" && submitForm.duplicateEmailConflict) {
          return;
        }
        const detail =
          (typeof submitForm.lastErrorMessage === "string" && submitForm.lastErrorMessage.trim())
            ? submitForm.lastErrorMessage.trim()
            : "The saved draft and attached documents are still available for later editing.";
        setStage(
          "submit",
          "Final submission failed. The extracted application remains saved as a draft.",
          "warning",
          detail
        );
        return;
      }

      setStage("submit", texts.success, "success", texts.submitDone);
      setAutofillProgress(100, texts.submitDone);
    } catch (err) {
      stopWaitAnimation("Process stopped.");
      const message = err && err.message ? err.message : texts.error;
      setStage("batch", message, "danger", "The queued documents were kept so you can adjust them and try again.");
      if (typeof showApplicationSaveError === "function") {
        showApplicationSaveError(message, { title: "Document analysis failed" });
      }
    } finally {
      stopAutofillTimer();
      isProcessing = false;
      updateSmartAutofillAvailability();
    }
  });

  if (studyChoicesWrap) {
    studyChoicesWrap.addEventListener("change", () => setTimeout(updateSmartAutofillAvailability, 50));
    new MutationObserver(() => setTimeout(updateSmartAutofillAvailability, 50)).observe(
      studyChoicesWrap,
      { childList: true, subtree: true }
    );
  }

  if (regionsSelect && window.jQuery) {
    $(regionsSelect).on("change", () => setTimeout(updateSmartAutofillAvailability, 50));
  }

  clearPanels();
  resetProgress();
  renderQueue();
  setTimeout(updateSmartAutofillAvailability, 300);
})();
</script>

<script>
(function () {
  "use strict";

  const searchInput = document.getElementById("agent_search");
  const resultsBox = document.getElementById("agentResults");
  const wrap = document.getElementById("agentSearchWrap");
  const firstEl = document.getElementById("agent_first_name");
  const lastEl = document.getElementById("agent_last_name");
  const emailEl = document.getElementById("agent_email");

  if (!searchInput || !resultsBox || !firstEl || !lastEl || !emailEl) {
    return;
  }

  let debounceTimer = null;
  let controller = null;

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text == null ? "" : String(text);
    return div.innerHTML;
  }

  function hideResults() {
    resultsBox.classList.add("d-none");
    resultsBox.innerHTML = "";
  }

  function runSearch(query) {
    if (controller) {
      controller.abort();
    }
    controller = new AbortController();

    fetch("searchAgents.php?q=" + encodeURIComponent(query), {
      signal: controller.signal,
      cache: "no-store"
    })
      .then((res) => (res.ok ? res.json() : Promise.reject(new Error("Agent search failed"))))
      .then((data) => {
        resultsBox.innerHTML = "";

        if (!Array.isArray(data) || data.length === 0) {
          hideResults();
          return;
        }

        data.forEach((agent) => {
          const item = document.createElement("button");
          item.type = "button";
          item.className = "list-group-item list-group-item-action text-start py-2";
          const name = agent.full_name || [agent.first_name, agent.last_name].filter(Boolean).join(" ").trim() || "—";
          const role = (agent.role && String(agent.role).trim()) || "";
          item.innerHTML =
            "<strong>" +
            escapeHtml(name) +
            "</strong>" +
            (role ? " <span class=\"badge text-bg-light border ms-1\">" + escapeHtml(role) + "</span>" : "") +
            "<br><small class=\"text-muted\">" +
            escapeHtml(agent.email || "") +
            "</small>";

          item.addEventListener("click", () => {
            firstEl.value = agent.first_name || "";
            lastEl.value = agent.last_name || "";
            emailEl.value = agent.email || "";
            searchInput.value = name;
            hideResults();
            firstEl.dispatchEvent(new Event("input", { bubbles: true }));
          });

          resultsBox.appendChild(item);
        });

        resultsBox.classList.remove("d-none");
      })
      .catch((err) => {
        if (err && err.name === "AbortError") return;
        hideResults();
      });
  }

  searchInput.addEventListener("input", function () {
    const query = this.value.trim();
    clearTimeout(debounceTimer);

    if (query.length < 2) {
      if (controller) controller.abort();
      hideResults();
      return;
    }

    debounceTimer = setTimeout(() => runSearch(query), 220);
  });

  document.addEventListener("click", (e) => {
    const root = wrap || searchInput;
    if (!root.contains(e.target)) {
      resultsBox.classList.add("d-none");
    }
  });
})();
</script>
<script>
(function () {
  "use strict";

  const searchInput = document.getElementById("staff_assign_search");
  const resultsBox = document.getElementById("staff_assign_results");
  const wrap = document.getElementById("staffAssignSearchWrap");
  const hiddenId = document.getElementById("assigned_to_admin_id");
  const clearBtn = document.getElementById("staff_assign_clear_btn");

  if (!searchInput || !resultsBox || !hiddenId) {
    return;
  }

  let debounceTimer = null;
  let controller = null;
  let initialStaffCache = null;
  let initialLoadPromise = null;

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text == null ? "" : String(text);
    return div.innerHTML;
  }

  function hideResults() {
    resultsBox.classList.add("d-none");
    resultsBox.innerHTML = "";
  }

  function clearSelection() {
    hiddenId.value = "";
    searchInput.value = "";
    hideResults();
  }

  if (clearBtn) {
    clearBtn.addEventListener("click", clearSelection);
  }

  function renderStaffList(rows) {
    resultsBox.innerHTML = "";

    if (!Array.isArray(rows) || rows.length === 0) {
      hideResults();
      return;
    }

    rows.forEach((row) => {
      const item = document.createElement("button");
      item.type = "button";
      item.className = "list-group-item list-group-item-action text-start py-2";
      const name =
        row.full_name ||
        [row.first_name, row.last_name].filter(Boolean).join(" ").trim() ||
        "—";
      item.innerHTML =
        "<strong>" +
        escapeHtml(name) +
        "</strong><br><small class=\"text-muted\">" +
        escapeHtml(row.email || "") +
        "</small>";

      item.addEventListener("click", () => {
        hiddenId.value = String(row.id || "");
        searchInput.value = name;
        hideResults();
      });

      resultsBox.appendChild(item);
    });

    resultsBox.classList.remove("d-none");
  }

  function loadInitialStaff() {
    const q = searchInput.value.trim();
    if (q.length >= 2) {
      return;
    }

    if (initialStaffCache) {
      renderStaffList(initialStaffCache);
      return;
    }

    if (initialLoadPromise) {
      initialLoadPromise.then((rows) => renderStaffList(rows));
      return;
    }

    initialLoadPromise = fetch("searchStaff.php?initial=1", { cache: "no-store" })
      .then((res) => (res.ok ? res.json() : Promise.reject(new Error("Staff list failed"))))
      .then((data) => {
        initialStaffCache = Array.isArray(data) ? data : [];
        initialLoadPromise = null;
        return initialStaffCache;
      })
      .catch(() => {
        initialLoadPromise = null;
        return [];
      });

    initialLoadPromise.then((rows) => renderStaffList(rows));
  }

  function runSearch(query) {
    if (controller) {
      controller.abort();
    }
    controller = new AbortController();

    fetch("searchStaff.php?q=" + encodeURIComponent(query), {
      signal: controller.signal,
      cache: "no-store"
    })
      .then((res) => (res.ok ? res.json() : Promise.reject(new Error("Staff search failed"))))
      .then((data) => {
        renderStaffList(Array.isArray(data) ? data : []);
      })
      .catch((err) => {
        if (err && err.name === "AbortError") return;
        hideResults();
      });
  }

  searchInput.addEventListener("focus", () => {
    if (searchInput.value.trim().length < 2) {
      loadInitialStaff();
    }
  });

  searchInput.addEventListener("input", function () {
    const query = this.value.trim();
    clearTimeout(debounceTimer);

    if (query.length < 2) {
      if (controller) controller.abort();
      if (query.length === 0) {
        loadInitialStaff();
      } else {
        hideResults();
      }
      return;
    }

    debounceTimer = setTimeout(() => runSearch(query), 220);
  });

  document.addEventListener("click", (e) => {
    const root = wrap || searchInput;
    if (root && !root.contains(e.target)) {
      resultsBox.classList.add("d-none");
    }
  });
})();
</script>
<script>
(function () {
    const firstName = document.getElementById('agent_first_name');
    const lastName  = document.getElementById('agent_last_name');
    const email     = document.getElementById('agent_email');

    if (!firstName || !lastName || !email) return;

    function lockFields() {
        firstName.readOnly = true;
        lastName.readOnly  = true;
        email.readOnly     = true;
    }

    /* 🔒 Hard lock as soon as any value appears */
    function enforceLock() {
        if (
            firstName.value.trim() !== '' ||
            lastName.value.trim() !== '' ||
            email.value.trim() !== ''
        ) {
            lockFields();
        }
    }

    /* Catch ALL ways values can be set */
    ['input', 'change', 'keyup', 'paste'].forEach(evt => {
        firstName.addEventListener(evt, enforceLock);
        lastName.addEventListener(evt, enforceLock);
        email.addEventListener(evt, enforceLock);
    });

    /* Also enforce lock on page load (safety) */
    document.addEventListener('DOMContentLoaded', enforceLock);

})();
</script>
<script>
(function () {

  const loanSections   = document.querySelectorAll(".loan-section");
  const loanOptions    = document.querySelectorAll(".loan-option");
  const financeSelects = document.querySelectorAll(".finance-select");
  const studyChoices   = document.getElementById("studyChoices");

  function normalize(text) {
    return text.toLowerCase().replace(/[^a-z]/g, "");
  }

  function isMasterLevel(name) {
    const v = normalize(name);
    return [
      "master",
      "masters",
      "msc",
      "mba",
      "mphil",
      "mster"
    ].some(k => v.includes(k));
  }

  function clearLoanData() {
    document
      .querySelectorAll('input[name="destination_loan"], input[name="other_destination_loan"]')
      .forEach(i => i.value = "");

    financeSelects.forEach(select => {
      if (select.value === "Loan") {
        select.value = "";
      }
    });
  }

  function applyLoanPolicy() {
    let allowLoan = false;

    document.querySelectorAll(".study-choice .level").forEach(select => {
      const opt = select.selectedOptions[0];
      if (!opt) return;

      const levelName =
        opt.dataset?.name ||
        opt.textContent ||
        "";

      if (isMasterLevel(levelName)) {
        allowLoan = true;
      }
    });

    // Toggle loan destination fields
    loanSections.forEach(section => {
      section.style.display = allowLoan ? "" : "none";
    });

    // Toggle Loan option in finance dropdowns
    loanOptions.forEach(option => {
      option.style.display = allowLoan ? "" : "none";
      option.disabled = !allowLoan;
    });

    if (!allowLoan) {
      clearLoanData();
    }
  }

  // Observe dynamic program changes
  const observer = new MutationObserver(applyLoanPolicy);
  observer.observe(studyChoices, { childList: true, subtree: true });

  // Catch direct changes to level selects
  document.addEventListener("change", e => {
    if (e.target.classList.contains("level")) {
      applyLoanPolicy();
    }
  });

  document.addEventListener("DOMContentLoaded", applyLoanPolicy);

})();
</script>
<script>
(function () {

  const preferredDestination = document.getElementById("preferredDestination");
  const loanDestination      = document.getElementById("loanDestination");
  const loanSections         = document.querySelectorAll(".loan-section");
  const financeSelects       = document.querySelectorAll(".finance-select");
  const studyChoices         = document.getElementById("studyChoices");

  function normalize(text) {
    return text.toLowerCase().replace(/[^a-z]/g, "");
  }

  function isMasterLevel(name) {
    const v = normalize(name);
    return [
      "master",
      "masters",
      "msc",
      "mba",
      "mphil",
      "mster"
    ].some(k => v.includes(k));
  }

  function clearLoanData() {
    if (loanDestination) loanDestination.value = "";

    document
      .querySelectorAll('input[name="other_destination_loan"]')
      .forEach(i => i.value = "");

    financeSelects.forEach(select => {
      if (select.value === "Loan") {
        select.value = "";
      }
    });
  }

  function syncLoanDestination() {
    if (!loanDestination || !preferredDestination) return;

    loanDestination.value = preferredDestination.value || "";
  }

  function applyLoanPolicy() {
    let allowLoan = false;

    document.querySelectorAll(".study-choice .level").forEach(select => {
      const opt = select.selectedOptions[0];
      if (!opt) return;

      const levelName =
        opt.dataset?.name ||
        opt.textContent ||
        "";

      if (isMasterLevel(levelName)) {
        allowLoan = true;
      }
    });

    // Toggle loan destination section
    loanSections.forEach(section => {
      section.style.display = allowLoan ? "" : "none";
    });

    // Toggle Loan option in finance selects
    document.querySelectorAll(".loan-option").forEach(opt => {
      opt.disabled = !allowLoan;
      opt.style.display = allowLoan ? "" : "none";
    });

    if (allowLoan) {
      syncLoanDestination();
    } else {
      clearLoanData();
    }
  }

  /* ===============================
     WATCHERS
  =============================== */

  // When study programs change
  const observer = new MutationObserver(applyLoanPolicy);
  observer.observe(studyChoices, { childList: true, subtree: true });

  // When program level changes
  document.addEventListener("change", e => {
    if (e.target.classList.contains("level")) {
      applyLoanPolicy();
    }
  });

  // 🔁 When preferred destination changes → sync loan destination
  preferredDestination?.addEventListener("input", syncLoanDestination);
  preferredDestination?.addEventListener("change", syncLoanDestination);

  document.addEventListener("DOMContentLoaded", applyLoanPolicy);

})();
</script>
<script>
(function () {

  document.querySelectorAll('.conditional-select').forEach(select => {

    const targetName = select.dataset.followup;
    const field = document.querySelector(
      '.conditional-field[name="' + targetName + '"]'
    );

    if (!field) return;

    function toggle() {
      if (select.value === 'Yes') {
        field.style.display = 'block';
      } else {
        field.style.display = 'none';
        field.value = '';
      }
    }

    // Initial state
    toggle();

    // On change
    select.addEventListener('change', toggle);
  });

})();
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {

  const phoneInput = document.querySelector("#emergency_phone");
  const areaCode   = document.querySelector("#emergency_area_code");
  const phoneNum   = document.querySelector("#emergency_phone_number");

  if (!phoneInput) return;

  const iti = window.intlTelInput(phoneInput, {
    initialCountry: "auto",
    separateDialCode: true,
    nationalMode: true,
    utilsScript:
      "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
    geoIpLookup: function (callback) {
      fetch("https://ipapi.co/json/")
        .then(res => (res.ok ? res.json() : Promise.reject(new Error("geo fail"))))
        .then(data => callback(data && data.country_code ? data.country_code : "US"))
        .catch(() => callback("US"));
    }
  });

  /* ===============================
     LIVE VALIDATION
  =============================== */
  phoneInput.addEventListener("blur", () => {
    if (phoneInput.value.trim() === "") return;

    if (!iti.isValidNumber()) {
      phoneInput.classList.add("is-invalid");
      phoneInput.classList.remove("is-valid");
    } else {
      phoneInput.classList.remove("is-invalid");
      phoneInput.classList.add("is-valid");
    }
  });

  /* ===============================
     SAVE VALUES FOR BACKEND
  =============================== */
  phoneInput.addEventListener("change", syncPhone);
  phoneInput.addEventListener("keyup", syncPhone);

  function syncPhone() {
    if (!iti.isValidNumber()) return;

    areaCode.value = "+" + iti.getSelectedCountryData().dialCode;
    phoneNum.value = iti.getNumber(
      window.intlTelInputUtils.numberFormat.NATIONAL
    );
  }

});
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {

  const phoneInput = document.querySelector("#intl_phone");
  if (!phoneInput) return;

  const iti = window.intlTelInput(phoneInput, {
    initialCountry: "auto",
    nationalMode: true,
    separateDialCode: true,
    autoPlaceholder: "polite",
    preferredCountries: ["us", "gb", "fr", "ca", "de", "rw"],
    geoIpLookup: callback => {
      fetch("https://ipapi.co/json/")
        .then(res => (res.ok ? res.json() : Promise.reject(new Error("geo fail"))))
        .then(data => callback(data && data.country_code ? data.country_code : "us"))
        .catch(() => callback("us"));
    },
    utilsScript:
      "https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.7/build/js/utils.js"
  });

  const areaCodeInput  = document.getElementById("area_code");
  const phoneNumInput  = document.getElementById("phone_number");

  function syncPhoneFields() {
    if (phoneInput.value.trim() === "") {
      areaCodeInput.value = "";
      phoneNumInput.value = "";
      phoneInput.classList.remove("is-invalid", "is-valid");
      return true;
    }

    if (!iti.isValidNumber()) {
      areaCodeInput.value = "";
      phoneNumInput.value = "";
      phoneInput.classList.add("is-invalid");
      phoneInput.classList.remove("is-valid");
      return false;
    }

    const data = iti.getSelectedCountryData();

    areaCodeInput.value = `+${data.dialCode}`;
    phoneNumInput.value = phoneInput.value.replace(/\D/g, "");

    phoneInput.classList.remove("is-invalid");
    phoneInput.classList.add("is-valid");
    return true;
  }

  phoneInput.addEventListener("blur", syncPhoneFields);
  phoneInput.addEventListener("change", syncPhoneFields);
  phoneInput.addEventListener("keyup", syncPhoneFields);

  /* Prevent form submit if invalid (skip when empty — Smart AI can finalize later) */
  const form = phoneInput.closest("form");
  if (form) {
    form.addEventListener("submit", e => {
      if (phoneInput.value.trim() === "") return;
      if (!syncPhoneFields()) {
        e.preventDefault();
        alert("Please enter a valid phone number.");
      }
    });
  }

});
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  // =====================================================
  // REGION SELECTION AS CLOSEABLE TABS - DEBUG VERSION
  // =====================================================
  
  console.log("=== REGION TABS DEBUG START ===");
  
  const regionsSelect = document.getElementById("regions");
  const regionStep = document.getElementById("regionStep");
  
  console.log("regionsSelect:", regionsSelect);
  console.log("regionStep:", regionStep);
  
  if (!regionsSelect || !regionStep) {
    console.error("Missing required elements");
    return;
  }
  
  // Debug: Check if regions are loaded
  console.log("Initial regions options count:", regionsSelect.options.length);
  for (let i = 0; i < regionsSelect.options.length; i++) {
    console.log(`Option ${i}:`, regionsSelect.options[i].value, regionsSelect.options[i].text);
  }
  
  // Create container for selected region tabs
  const selectedRegionsContainer = document.createElement("div");
  selectedRegionsContainer.id = "selectedRegionsContainer";
  selectedRegionsContainer.className = "selected-regions-container mt-3";
  selectedRegionsContainer.style.cssText = `
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    min-height: 40px;
    padding: 8px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
  `;
  
  // Insert the container after the regions select
  regionsSelect.parentNode.insertBefore(selectedRegionsContainer, regionsSelect.nextSibling);
  
  // Function to create a region tab
  function createRegionTab(regionId, regionName) {
    const tab = document.createElement("div");
    tab.className = "region-tab";
    tab.dataset.regionId = regionId;
    tab.style.cssText = `
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      background: linear-gradient(135deg, #0d6efd, #4f8cff);
      color: white;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      box-shadow: 0 2px 4px rgba(13, 110, 253, 0.2);
      transition: all 0.2s ease;
      cursor: default;
    `;
    
    tab.innerHTML = `
      <span class="region-name">${regionName}</span>
      <button type="button" class="region-close-btn" style="
        background: none;
        border: none;
        color: white;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        padding: 0;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s ease;
      " title="Remove region">×</button>
    `;
    
    // Add hover effects
    tab.addEventListener('mouseenter', () => {
      tab.style.transform = 'translateY(-1px)';
      tab.style.boxShadow = '0 4px 8px rgba(13, 110, 253, 0.3)';
    });
    
    tab.addEventListener('mouseleave', () => {
      tab.style.transform = 'translateY(0)';
      tab.style.boxShadow = '0 2px 4px rgba(13, 110, 253, 0.2)';
    });
    
    // Add close functionality
    const closeBtn = tab.querySelector('.region-close-btn');
    closeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      removeRegionTab(regionId);
    });
    
    closeBtn.addEventListener('mouseenter', () => {
      closeBtn.style.backgroundColor = 'rgba(255, 255, 255, 0.2)';
    });
    
    closeBtn.addEventListener('mouseleave', () => {
      closeBtn.style.backgroundColor = 'none';
    });
    
    return tab;
  }
  
  // Function to remove a region tab
  function removeRegionTab(regionId) {
    const tab = selectedRegionsContainer.querySelector(`[data-region-id="${regionId}"]`);
    if (tab) {
      tab.style.transform = 'scale(0.8)';
      tab.style.opacity = '0';
      setTimeout(() => {
        tab.remove();
        updateSelectedRegionsDisplay();
      }, 200);
    }
    
    // Update the select2 value
    const currentValues = $(regionsSelect).val() || [];
    const newValues = currentValues.filter(id => String(id) !== String(regionId));
    $(regionsSelect).val(newValues).trigger('change');
  }
  
  // Function to update the display based on selected regions
  function updateSelectedRegionsDisplay() {
    const selectedRegions = $(regionsSelect).val() || [];
    
    // Clear existing tabs
    selectedRegionsContainer.innerHTML = '';
    
    if (selectedRegions.length === 0) {
      selectedRegionsContainer.innerHTML = `
        <div style="color: #64748b; font-size: 13px; font-style: italic;">
          No regions selected. Select regions above to see them here.
        </div>
      `;
      return;
    }
    
    // Add tabs for each selected region
    selectedRegions.forEach(regionId => {
      const option = regionsSelect.querySelector(`option[value="${regionId}"]`);
      if (option) {
        const tab = createRegionTab(regionId, option.textContent);
        selectedRegionsContainer.appendChild(tab);
      }
    });
  }
  
  /* Picker label sync (set after custom face is created) */
  let syncRegionPickerLabel = function () {};

  // Listen for changes in the regions select
  $(regionsSelect).on('change', function() {
    console.log("=== REGIONS SELECT CHANGE ===");
    console.log("Current values:", $(this).val());
    console.log("Options count:", this.options.length);
    updateSelectedRegionsDisplay();
    syncRegionPickerLabel();
  });
  
  // Debug: Monitor jQuery and Select2
  console.log("jQuery available:", typeof $ !== 'undefined');
  console.log("Select2 available:", typeof $.fn.select2 !== 'undefined');
  
  // Check if Select2 is initialized on regions
  setTimeout(() => {
    const $regions = $('#regions');
    console.log("$regions object:", $regions);
    console.log("Select2 initialized:", $regions.hasClass('select2-hidden-accessible'));
    console.log("Select2 container:", $regions.next('.select2-container'));
    
    updateSelectedRegionsDisplay();
  }, 500);
  
  // Wait for regions to be loaded, then initialize display
  setTimeout(() => {
    updateSelectedRegionsDisplay();
  }, 500);
  
  /* Select2 widget hidden via CSS (#regionStep …); keep programmatic sync */

  const regionPlaceholder =
    regionsSelect.getAttribute("data-placeholder") ||
    "Select one or more regions";

  // Full-width button surface — no text input / no I-beam cursor
  const dropdownTrigger = document.createElement("div");
  dropdownTrigger.className = "regions-dropdown-trigger w-100 position-relative";

  const pickerFace = document.createElement("button");
  pickerFace.type = "button";
  pickerFace.className =
    "form-control text-start d-flex align-items-center justify-content-between study-touch-control rounded-3 regions-picker-face";
  pickerFace.setAttribute("aria-haspopup", "listbox");
  pickerFace.setAttribute("aria-expanded", "false");
  pickerFace.style.cursor = "pointer";
  pickerFace.style.userSelect = "none";

  const pickerLabel = document.createElement("span");
  pickerLabel.className = "regions-picker-label text-body-secondary text-truncate me-2";
  pickerLabel.textContent = regionPlaceholder;

  const pickerChevron = document.createElement("span");
  pickerChevron.className = "small text-primary fw-semibold flex-shrink-0";
  pickerChevron.setAttribute("aria-hidden", "true");
  pickerChevron.textContent = "▾";

  pickerFace.appendChild(pickerLabel);
  pickerFace.appendChild(pickerChevron);
  dropdownTrigger.appendChild(pickerFace);

  const dropdownMenu = document.createElement("div");
  dropdownMenu.className = "regions-dropdown-menu";
  dropdownMenu.style.cssText = `
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    max-height: 220px;
    overflow-y: auto;
    display: none;
    margin-top: 4px;
  `;
  dropdownTrigger.appendChild(dropdownMenu);

  function setRegionMenuOpen(open) {
    dropdownMenu.style.display = open ? "block" : "none";
    pickerFace.setAttribute("aria-expanded", open ? "true" : "false");
  }

  syncRegionPickerLabel = function () {
    const selected = $(regionsSelect).val() || [];
    if (!selected.length) {
      pickerLabel.textContent = regionPlaceholder;
      pickerLabel.classList.add("text-body-secondary");
      return;
    }
    const names = selected.map((id) => {
      const opt = regionsSelect.querySelector(
        'option[value="' + String(id).replace(/"/g, '\\"') + '"]'
      );
      return opt ? opt.textContent : id;
    });
    pickerLabel.textContent = names.join(", ");
    pickerLabel.classList.remove("text-body-secondary");
  };
  
  // Function to populate dropdown menu
  function populateDropdownMenu() {
    // Clear existing items
    dropdownMenu.innerHTML = '';
    
    // Add all region options to the dropdown
    const regionOptions = regionsSelect.querySelectorAll('option');
    let hasOptions = false;
    
    regionOptions.forEach(option => {
      if (option.value) {
        hasOptions = true;
        const item = document.createElement("div");
        item.className = "region-dropdown-item";
        item.style.cssText = `
          padding: 8px 12px;
          cursor: pointer;
          border-bottom: 1px solid #f3f4f6;
          transition: background-color 0.2s ease;
          font-size: 13px;
        `;
        item.textContent = option.textContent;
        item.dataset.value = option.value;
        
        item.addEventListener('mouseenter', () => {
          item.style.backgroundColor = '#f8fafc';
        });
        
        item.addEventListener('mouseleave', () => {
          item.style.backgroundColor = 'white';
        });
        
        item.addEventListener('click', () => {
          const currentValues = ($(regionsSelect).val() || []).map(String);
          const v = String(option.value);
          if (!currentValues.includes(v)) {
            currentValues.push(v);
            $(regionsSelect).val(currentValues).trigger('change');
          }
          setRegionMenuOpen(false);
        });
        
        dropdownMenu.appendChild(item);
      }
    });
    
    if (!hasOptions) {
      dropdownMenu.innerHTML = `
        <div style="padding: 12px; color: #64748b; font-size: 13px; text-align: center;">
          Loading regions...
        </div>
      `;
    }
  }
  
  $(regionsSelect).on("change.regionOptions", function () {
    populateDropdownMenu();
  });

  // Initial population
  populateDropdownMenu();
  
  // Re-populate after regions are loaded (meta fetch timing backup)
  setTimeout(() => {
    populateDropdownMenu();
  }, 1000);
  
  // Insert dropdown trigger before the regions select
  regionsSelect.parentNode.insertBefore(dropdownTrigger, regionsSelect);
  
  pickerFace.addEventListener("click", (e) => {
    e.stopPropagation();
    const open = dropdownMenu.style.display === "none";
    setRegionMenuOpen(open);
  });

  pickerFace.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      const open = dropdownMenu.style.display === "none";
      setRegionMenuOpen(open);
    } else if (e.key === "Escape") {
      setRegionMenuOpen(false);
    }
  });
  
  document.addEventListener("click", (e) => {
    if (!dropdownTrigger.contains(e.target)) {
      setRegionMenuOpen(false);
    }
  });

  syncRegionPickerLabel();
  
  // Hide the original regions select
  regionsSelect.style.display = 'none';
  
  // Add some styling for the empty state
  if (selectedRegionsContainer.querySelector('div')) {
    selectedRegionsContainer.style.alignItems = 'center';
    selectedRegionsContainer.style.justifyContent = 'center';
  }
});
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {

  // =====================================================
  // COMPREHENSIVE FORM VALIDATION SYSTEM
  // =====================================================
  
  // Enhanced name validation patterns
  const NAME_PATTERNS = {
    MEANINGLESS: [
      // Test/fake patterns
      /^(test|demo|sample|asdf|qwer|123|abc|xyz|null|none|na|n\/a|foo|bar|baz|qux|lorem|ipsum|temp|user|guest|admin|student|sample|example|placeholder)$/i,
      /^.{1,2}$/, // Too short
      /^[^a-zA-Z]+$/, // No letters at all
      /^(.)\1+$/, // All same character (aaa, bbb, etc.)
      /^[0-9\s\-_\.]+$/, // Only numbers/symbols
      // Repeating patterns
      /^(.)\1{2,}$/, // Three or more same character
      /^(.)\1(.)\1$/, // ABA pattern
      /^([a-z])\1\1\1\1$/i, // Five same letters
      // Sequential patterns
      /^(abc|def|ghi|jkl|mno|pqr|stu|vwx|yz)$/i, // Sequential letters
      /^(123|234|345|456|567|678|789|890|012)$/i, // Sequential numbers
      // Keyboard patterns
      /^(qwerty|asdf|zxcv|asdfgh|qweasdzxc|123456|123abc)$/i,
      // Common fake words
      /^(fake|dummy|mock|test|sample|demo|placeholder|example|temp|tmp|anon|unknown|n\/a)$/i,
      // Gibberish detection
      /^[a-z]{1,}(.)\1{2,}[a-z]*$/i, // Repeated middle characters
      /^[a-z]{3,}([a-z])\1{2,}$/i, // Repeated ending characters
      // Vowel/consonant imbalance
      /^[aeiou]{3,}$/i, // All vowels
      /^[bcdfghjklmnpqrstvwxyz]{5,}$/i, // All consonants
      // Alternating patterns that look fake
      /^([a-z]{2})\1{1,}$/i, // Repeated 2-letter chunk (abab, cdcdcd)
    ],
    VALID: /^[a-zA-Z\u00C0-\u024F\s'\-\.]{2,50}$/, // Allow international letters, spaces, hyphens, apostrophes, dots
  };

  // Passport validation patterns by country
  const PASSPORT_PATTERNS = {
    default: /^[A-Z0-9]{6,9}$/,
    usa: /^[A-Z0-9]{9}$/,
    uk: /^[A-Z]{9}[0-9]$/,
    canada: /^[A-Z]{2}[0-9]{6}$/,
    australia: /^[A-Z]{1,2}[0-9]{7}$/,
    germany: /^[A-Z]{1}[0-9]{7}$/,
    france: /^[0-9]{2}[A-Z]{2}[0-9]{5}$/,
    india: /^[A-Z]{1}[0-9]{7}$/,
    china: /^[GDE][0-9]{8}$/,
    japan: /^[A-Z]{2}[0-9]{7}$/,
  };

  // Phone validation by country code
  const PHONE_VALIDATION = {
    '+1': { pattern: /^[2-9]\d{2}[2-9]\d{2}\d{4}$/, maxLength: 10, name: 'North America' },
    '+44': { pattern: /^[1-9]\d{9,10}$/, maxLength: 11, name: 'UK' },
    '+33': { pattern: /^[1-9]\d{8}$/, maxLength: 9, name: 'France' },
    '+49': { pattern: /^[1-9]\d{6,11}$/, maxLength: 12, name: 'Germany' },
    '+91': { pattern: /^[6-9]\d{9}$/, maxLength: 10, name: 'India' },
    '+86': { pattern: /^[1-9]\d{10,11}$/, maxLength: 12, name: 'China' },
    '+81': { pattern: /^[1-9]\d{8,9}$/, maxLength: 10, name: 'Japan' },
    '+61': { pattern: /^[2-9]\d{8}$/, maxLength: 9, name: 'Australia' },
    '+92': { pattern: /^[3-9]\d{9}$/, maxLength: 10, name: 'Pakistan' },
    '+234': { pattern: /^[7-9]\d{9}$/, maxLength: 10, name: 'Nigeria' },
    '+254': { pattern: /^[7]\d{8}$/, maxLength: 9, name: 'Kenya' },
    '+256': { pattern: /^[7]\d{8}$/, maxLength: 9, name: 'Uganda' },
    '+250': { pattern: /^[7]\d{8}$/, maxLength: 9, name: 'Rwanda' },
  };

  // Enhanced validation functions
  function validateName(name, fieldName) {
    const trimmed = name.trim();
    
    if (!trimmed) {
      return { valid: false, message: `${fieldName} is required` };
    }
    
    // Check for meaningless patterns
    for (const pattern of NAME_PATTERNS.MEANINGLESS) {
      if (pattern.test(trimmed)) {
        return { valid: false, message: `Please enter a real ${fieldName.toLowerCase()}` };
      }
    }
    
    // Check valid format
    if (!NAME_PATTERNS.VALID.test(trimmed)) {
      return { valid: false, message: `${fieldName} can only contain letters, spaces, hyphens, and apostrophes (2-50 characters)` };
    }
    
    // Additional real name validation
    const nameParts = trimmed.split(/\s+/);
    
    // Check if name has reasonable structure
    if (nameParts.length === 1 && nameParts[0].length < 3) {
      return { valid: false, message: `${fieldName} appears too short for a real name` };
    }
    
    // Check for vowel presence in longer names (most real names have vowels)
    if (trimmed.length > 5 && !/[aeiouAEIOU]/.test(trimmed)) {
      return { valid: false, message: `${fieldName} should contain vowels` };
    }
    
    // Check for reasonable consonant-to-vowel ratio
    const vowelCount = (trimmed.match(/[aeiouAEIOU]/g) || []).length;
    const consonantCount = (trimmed.match(/[bcdfghjklmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ]/g) || []).length;
    
    if (trimmed.length > 8 && consonantCount > vowelCount * 4) {
      return { valid: false, message: `${fieldName} doesn't look like a real name` };
    }
    
    // Check for alternating patterns that look fake
    const isAlternating = /^([a-zA-Z])\1([a-zA-Z])+$/.test(trimmed);
    if (isAlternating && nameParts[0].length >= 4) {
      return { valid: false, message: `${fieldName} appears to be a fake pattern` };
    }
    
    // Check for excessive repetition
    const charCounts = {};
    for (const char of trimmed.toLowerCase()) {
      charCounts[char] = (charCounts[char] || 0) + 1;
    }
    const maxCount = Math.max(...Object.values(charCounts));
    if (maxCount > trimmed.length * 0.6) {
      return { valid: false, message: `${fieldName} has too much repetition` };
    }
    
    return { valid: true, message: '' };
  }

  function validatePassport(passport, countryCode = '') {
    const trimmed = passport.trim().toUpperCase();
    
    if (!trimmed) {
      return { valid: false, message: 'Passport number is required' };
    }
    
    // Remove spaces and special characters
    const cleanPassport = trimmed.replace(/[^A-Z0-9]/g, '');
    
    if (cleanPassport.length < 6 || cleanPassport.length > 12) {
      return { valid: false, message: 'Passport number must be 6-12 characters (letters and numbers only)' };
    }
    
    // Country-specific validation
    let pattern = PASSPORT_PATTERNS.default;
    if (countryCode) {
      const countryLower = countryCode.toLowerCase();
      const countryMap = {
        'us': 'usa', 'usa': 'usa', 'united states': 'usa',
        'gb': 'uk', 'uk': 'uk', 'united kingdom': 'uk',
        'ca': 'canada', 'canada': 'canada',
        'au': 'australia', 'australia': 'australia',
        'de': 'germany', 'germany': 'germany',
        'fr': 'france', 'france': 'france',
        'in': 'india', 'india': 'india',
        'cn': 'china', 'china': 'china',
        'jp': 'japan', 'japan': 'japan',
      };
      
      const countryKey = countryMap[countryLower];
      if (countryKey && PASSPORT_PATTERNS[countryKey]) {
        pattern = PASSPORT_PATTERNS[countryKey];
      }
    }
    
    if (!pattern.test(cleanPassport)) {
      return { valid: false, message: 'Invalid passport number format for selected country' };
    }
    
    return { valid: true, message: '' };
  }

  function validatePhoneByCountry(phone, countryCode) {
    const trimmed = phone.trim();
    
    if (!trimmed) {
      return { valid: false, message: 'Phone number is required' };
    }
    
    // Remove all non-digit characters
    const cleanPhone = trimmed.replace(/\D/g, '');
    
    const countryInfo = PHONE_VALIDATION[countryCode];
    if (!countryInfo) {
      return { valid: false, message: 'Invalid country code for phone validation' };
    }
    
    if (cleanPhone.length !== countryInfo.maxLength) {
      return { valid: false, message: `${countryInfo.name} phone numbers must be exactly ${countryInfo.maxLength} digits` };
    }
    
    if (!countryInfo.pattern.test(cleanPhone)) {
      return { valid: false, message: `Invalid ${countryInfo.name} phone number format` };
    }
    
    return { valid: true, message: '' };
  }

  // Apply validation to form fields
  function applyFieldValidation() {
    // Name fields validation
    const nameFields = ['first_name', 'last_name', 'father_first_name', 'father_last_name', 'mother_first_name', 'mother_last_name', 'emergency_first_name', 'emergency_last_name'];
    
    nameFields.forEach(fieldName => {
      const field = document.querySelector(`[name="${fieldName}"]`);
      if (field) {
        field.addEventListener('blur', function() {
          const validation = validateName(this.value, fieldName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
          showFieldValidation(this, validation);
        });
        
        field.addEventListener('input', function() {
          if (this.classList.contains('is-invalid')) {
            const validation = validateName(this.value, fieldName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
            if (validation.valid) {
              showFieldValidation(this, validation);
            }
          }
        });
      }
    });

    // Passport validation
    const passportField = document.querySelector('[name="passport_number"]');
    if (passportField) {
      passportField.addEventListener('blur', function() {
        const nationalityField = document.querySelector('[name="nationality"]');
        const countryCode = nationalityField ? nationalityField.value : '';
        const validation = validatePassport(this.value, countryCode);
        showFieldValidation(this, validation);
      });
    }

    // Enhanced phone validation
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
      input.addEventListener('blur', function() {
        // Get country code from intl-tel-input instance
        let countryCode = '+1'; // default fallback
        
        // Try to get from intl-tel-input first
        if (window.intlTelInputGlobals && this.id) {
          const iti = window.intlTelInputGlobals.getInstance(this);
          if (iti && iti.getSelectedCountryData()) {
            countryCode = '+' + iti.getSelectedCountryData().dialCode;
          }
        }
        
        // Fallback to hidden field
        if (countryCode === '+1') {
          const hiddenCode = document.getElementById(this.id.replace('phone', 'area_code'));
          if (hiddenCode && hiddenCode.value) {
            countryCode = hiddenCode.value;
          }
        }
        
        // Additional fallback: try to get from nationality field
        if (countryCode === '+1') {
          const nationalityField = document.querySelector('[name="nationality"]');
          if (nationalityField && nationalityField.value) {
            // Map country names to codes
            const countryToCode = {
              'kenya': '+254', 'rwanda': '+250', 'uganda': '+256',
              'nigeria': '+234', 'south africa': '+27', 'tanzania': '+255',
              'ghana': '+233', 'ethiopia': '+251', 'egypt': '+20',
              'morocco': '+212', 'algeria': '+213', 'libya': '+218',
              'sudan': '+249', 'tunisia': '+216', 'zimbabwe': '+263',
              'zambia': '+260', 'mozambique': '+258', 'botswana': '+267',
              'namibia': '+264', 'malawi': '+265', 'lesotho': '+266',
              'swaziland': '+268', 'angola': '+244', 'cameroon': '+237',
              'chad': '+235', 'congo': '+242', 'drc': '+243',
              'gabon': '+241', 'equatorial guinea': '+240', 'sao tome': '+239',
              'cape verde': '+238', 'guinea': '+224', 'guinea-bissau': '+245',
              'senegal': '+221', 'gambia': '+220', 'mali': '+223',
              'burkina faso': '+226', 'niger': '+227', 'benin': '+229',
              'togo': '+228', 'sierra leone': '+232', 'liberia': '+231',
              'ivory coast': '+225', 'burundi': '+257', 'djibouti': '+253',
              'eritrea': '+291', 'somalia': '+252', 'madagascar': '+261',
              'mauritius': '+230', 'seychelles': '+248', 'comoros': '+269',
              'reunion': '+262', 'mayotte': '+262'
            };
            
            const countryLower = nationalityField.value.toLowerCase();
            if (countryToCode[countryLower]) {
              countryCode = countryToCode[countryLower];
            }
          }
        }
        
        const validation = validatePhoneByCountry(this.value, countryCode);
        showFieldValidation(this, validation);
      });
    });
  }

  function showFieldValidation(field, validation) {
    // Remove existing validation states
    field.classList.remove('is-valid', 'is-invalid');
    
    // Remove existing feedback
    const existingFeedback = field.parentNode.querySelector('.invalid-feedback, .valid-feedback');
    if (existingFeedback) {
      existingFeedback.remove();
    }
    
    if (validation.valid) {
      field.classList.add('is-valid');
      const feedback = document.createElement('div');
      feedback.className = 'valid-feedback';
      feedback.textContent = 'Looks good!';
      field.parentNode.appendChild(feedback);
    } else {
      field.classList.add('is-invalid');
      const feedback = document.createElement('div');
      feedback.className = 'invalid-feedback';
      feedback.textContent = validation.message;
      field.parentNode.appendChild(feedback);
    }
  }

  // Make second nationality truly optional
  function makeSecondNationalityOptional() {
    const secondNationalityField = document.querySelector('[name="second_nationality"]');
    if (secondNationalityField) {
      secondNationalityField.removeAttribute('required');
      const label = secondNationalityField.closest('.mb-3').querySelector('.form-label');
      if (label) {
        label.innerHTML = label.innerHTML.replace(' *', '');
      }
    }
  }

  // Enhanced UI visibility improvements
  function enhanceUIVisibility() {
    // Add better contrast and visibility styles
    const style = document.createElement('style');
    style.textContent = `
      /* Enhanced form field styles */
      .form-control, .form-select {
        border-width: 2px;
        font-weight: 500;
        transition: all 0.3s ease;
      }
      
      .form-control:focus, .form-select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        transform: translateY(-1px);
      }
      
      .form-control.is-valid {
        border-color: #198754;
        background-color: #f0fff4;
      }
      
      .form-control.is-invalid {
        border-color: #dc3545;
        background-color: #fff5f5;
      }
      
      .valid-feedback {
        color: #198754;
        font-weight: 600;
        font-size: 0.875rem;
        margin-top: 0.25rem;
      }
      
      .invalid-feedback {
        color: #dc3545;
        font-weight: 600;
        font-size: 0.875rem;
        margin-top: 0.25rem;
      }
      
      /* Enhanced step sections */
      .step-section {
        border: 2px solid #e2e8f0;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        position: relative;
        overflow: hidden;
      }
      
      .step-section::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 8px;
        background: linear-gradient(180deg, #0d6efd, #4f8cff);
        border-radius: 0;
      }
      
      .step-section:hover {
        border-color: #0d6efd;
        box-shadow: 0 8px 25px rgba(13, 110, 253, 0.15);
      }
      
      /* Enhanced labels */
      .form-label {
        font-weight: 700;
        color: #1a202c;
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
      }
      
      /* Required field indicator */
      .form-label .text-danger {
        font-weight: 800;
        font-size: 1.1rem;
      }
      
      /* Enhanced buttons */
      .btn {
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-radius: 12px;
        padding: 12px 24px;
        transition: all 0.3s ease;
      }
      
      .btn-primary {
        background: linear-gradient(135deg, #0d6efd, #4f8cff);
        border: none;
        box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
      }
      
      .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(13, 110, 253, 0.4);
      }
      
      .btn-secondary {
        background: linear-gradient(135deg, #6c757d, #495057);
        border: none;
        box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
      }
      
      .btn-secondary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
      }
      
      /* Enhanced progress bar */
      .progress-step span.active {
        background: linear-gradient(90deg, #0d6efd, #4f8cff);
        box-shadow: 0 2px 8px rgba(13, 110, 253, 0.4);
      }
      
      /* Better focus indicators */
      .form-control:focus, .form-select:focus {
        outline: 3px solid rgba(13, 110, 253, 0.5);
        outline-offset: 2px;
      }
      
      /* Enhanced error states */
      .alert {
        border-radius: 12px;
        border-width: 2px;
        font-weight: 600;
      }
      
      /* Better card shadows */
      .card {
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08), 0 8px 16px rgba(0, 0, 0, 0.04);
        border: 2px solid #e2e8f0;
      }
      
      /* Enhanced dropdowns */
      .select2-container--bootstrap-5 .select2-selection {
        border-width: 2px;
        border-radius: 12px;
      }
      
      .select2-container--bootstrap-5.select2-container--focus .select2-selection {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
      }
    `;
    document.head.appendChild(style);
  }

  // Initialize all enhancements
  applyFieldValidation();
  makeSecondNationalityOptional();
  enhanceUIVisibility();
  
  // Ensure second nationality is truly optional and remove required attribute
  setTimeout(() => {
    const secondNationalityField = document.querySelector('[name="second_nationality"]');
    if (secondNationalityField) {
      secondNationalityField.removeAttribute('required');
      const label = secondNationalityField.closest('.mb-3').querySelector('.form-label');
      if (label && label.innerHTML.includes(' *')) {
        label.innerHTML = label.innerHTML.replace(' *', '');
      }
    }
  }, 100);

  const referralSelect = document.getElementById("referral_source");
  const agentSection   = document.getElementById("agentSection");

  const agentSearch    = document.getElementById("agent_search");
  const agentResults   = document.getElementById("agentResults");

  const firstNameInput = document.getElementById("agent_first_name");
  const lastNameInput  = document.getElementById("agent_last_name");
  const emailInput     = document.getElementById("agent_email");

  if (
    !referralSelect ||
    !agentSection ||
    !firstNameInput ||
    !lastNameInput ||
    !emailInput
  ) {
    return; // safety guard
  }

  /* ===============================
     HELPERS
  =============================== */

  function clearAgentFields() {
    firstNameInput.value = "";
    lastNameInput.value  = "";
    emailInput.value     = "";
    if (agentSearch) agentSearch.value = "";
    if (agentResults) {
      agentResults.innerHTML = "";
      agentResults.classList.add("d-none");
    }
  }

  function lockAgentFields() {
    firstNameInput.readOnly = true;
    lastNameInput.readOnly  = true;
    emailInput.readOnly     = true;
  }

  /* ===============================
     REFERRAL CHANGE HANDLER (FIXED)
  =============================== */

  referralSelect.addEventListener("change", async () => {

    clearAgentFields();

    /* ---------- ONLINE ---------- */
    if (referralSelect.value === "online") {

      agentSection.style.display = "none";

      try {
        const res = await fetch("getDefaultOnlineAgent.php", {
          cache: "no-store"
        });

        const agent = await res.json();

        if (!agent || !agent.email) {
          alert("No default agent available. Please select an agent manually.");
          referralSelect.value = "agent";
          agentSection.style.display = "block";
          return;
        }

        firstNameInput.value = agent.first_name || "";
        lastNameInput.value  = agent.last_name  || "";
        emailInput.value     = agent.email      || "";

        lockAgentFields();

      } catch (err) {
        alert("Failed to auto-assign agent. Please try again.");
        referralSelect.value = "";
      }

    }

    /* ---------- THROUGH AGENT ---------- */
    else if (referralSelect.value === "agent") {

      agentSection.style.display = "block";

      if (agentSearch) {
        agentSearch.focus();
      }

    }

    /* ---------- RESET ---------- */
    else {
      agentSection.style.display = "none";
    }

  });

  /* ===============================
     HARD LOCK (SAFETY)
  =============================== */

  ["input", "change", "paste"].forEach(evt => {
    firstNameInput.addEventListener(evt, lockAgentFields);
    lastNameInput.addEventListener(evt, lockAgentFields);
    emailInput.addEventListener(evt, lockAgentFields);
  });

});
</script>
<script>
(function(){

"use strict";

/* ======================================================
CONFIG
====================================================== */

const MIN_CHARS = 3;
const API_SEARCH = "searchApplication.php";
const API_LOAD   = "loadApplicationData.php";

/* ======================================================
ELEMENTS
====================================================== */

const searchBox  = document.getElementById("resume_email_search");
const resultsBox = document.getElementById("resumeResults");

/* =====================================================================
   AUTO-RESUME ON PAGE LOAD
   If the form is rendered with data-resume-app-id="<id>" (set by PHP
   when the visitor lands here via ?id=<user_id> and we found a matching
   student_applications row), automatically prefill the form by calling
   the same loader path used by the email-search box.
===================================================================== */
const _appForm = document.getElementById("applicationForm");
const _resumeAppId  = _appForm ? parseInt(_appForm.dataset.resumeAppId  || "0", 10) : 0;
const _resumeUserId = _appForm ? (_appForm.dataset.resumeUserId || "")           : "";

async function autoResumeFromUrl(){
  if(!_appForm) return;
  if(!_resumeAppId && !_resumeUserId) return;
  try{
    const url = _resumeAppId
      ? `${API_LOAD}?id=${encodeURIComponent(_resumeAppId)}`
      : `${API_LOAD}?user_id=${encodeURIComponent(_resumeUserId)}`;
    const response = await fetch(url);
    if(!response.ok) return;
    const data = await response.json();
    if(data.status !== "success") return;

    const resolvedAppId = data.id || _resumeAppId;
    if (typeof syncApplicationIdToForm === "function") {
      syncApplicationIdToForm(resolvedAppId);
    } else {
      window.currentApplicationId = resolvedAppId;
      const hiddenIdField = document.querySelector('input[name="application_id"]');
      if(hiddenIdField) hiddenIdField.value = resolvedAppId;
    }

    populateForm(data.application);
    restoreStudySelections(data.study_choices);

    if(searchBox && data.application && data.application.email){
      searchBox.value = data.application.email;
    }
  }
  catch(err){
    console.error("Auto-resume failed:", err);
  }
}

/* Defer to next tick so all init scripts (Select2, countries, etc.) have
   bound their change handlers before we fire change events. */
if(_resumeAppId || _resumeUserId){
  if(document.readyState === "loading"){
    document.addEventListener("DOMContentLoaded", ()=>setTimeout(autoResumeFromUrl, 500));
  } else {
    setTimeout(autoResumeFromUrl, 500);
  }
}

if(!searchBox || !resultsBox) return;

/* ======================================================
STATE
====================================================== */

let debounceTimer = null;
let controller = null;
let selectedIndex = -1;

/* ======================================================
UTILITIES
====================================================== */

function showResults(){
resultsBox.classList.remove("d-none");
}

function hideResults(){
resultsBox.classList.add("d-none");
selectedIndex = -1;
}

function clearResults(){
resultsBox.innerHTML = "";
}

function escapeHtml(text){
const div = document.createElement("div");
div.textContent = text;
return div.innerHTML;
}

/* ======================================================
SEARCH INPUT
====================================================== */

searchBox.addEventListener("input",function(){

const query = this.value.trim();

if(debounceTimer) clearTimeout(debounceTimer);

debounceTimer = setTimeout(()=>{
performSearch(query);
},300);

});

/* ======================================================
SEARCH
====================================================== */

async function performSearch(query){

if(query.length < MIN_CHARS){
hideResults();
clearResults();
return;
}

try{

if(controller) controller.abort();

controller = new AbortController();

resultsBox.innerHTML =
'<div class="list-group-item text-muted">Searching...</div>';

showResults();

const response = await fetch(
`${API_SEARCH}?q=${encodeURIComponent(query)}`,
{signal:controller.signal}
);

if(!response.ok) throw new Error("Search failed");

const data = await response.json();

renderResults(data);

}
catch(error){

if(error.name === "AbortError") return;

console.error(error);

resultsBox.innerHTML =
'<div class="list-group-item text-danger">Search failed</div>';

showResults();

}

}

/* ======================================================
RENDER RESULTS
====================================================== */

function renderResults(data){

clearResults();

if(!Array.isArray(data) || data.length === 0){

resultsBox.innerHTML =
'<div class="list-group-item">No application found</div>';

showResults();
return;
}

data.forEach((app)=>{

const item = document.createElement("button");

item.type = "button";
item.className = "list-group-item list-group-item-action";

item.dataset.id = app.id;

item.innerHTML = `
<strong>${escapeHtml(app.email)}</strong>
<br>
<small class="text-muted">Continue application</small>
`;

item.addEventListener("click",()=>loadApplication(app.id));

resultsBox.appendChild(item);

});

showResults();

}

/* ======================================================
LOAD APPLICATION
====================================================== */

async function loadApplication(id){

try{

resultsBox.innerHTML =
'<div class="list-group-item text-muted">Loading application...</div>';

const response = await fetch(`${API_LOAD}?id=${encodeURIComponent(id)}`);

if(!response.ok) throw new Error("Load failed");

const data = await response.json();

if(data.status !== "success"){
throw new Error("Invalid response");
}

/* restore autosave ID */

if (typeof syncApplicationIdToForm === "function") {
syncApplicationIdToForm(data.id);
} else {
window.currentApplicationId = data.id;

const hiddenIdField = document.querySelector('input[name="application_id"]');
if(hiddenIdField){
    hiddenIdField.value = data.id;
}
}

/* populate form fields */

populateForm(data.application);

/* restore study selections */

restoreStudySelections(data.study_choices);

hideResults();

if(data.application.email){
searchBox.value = data.application.email;
}

window.scrollTo({top:0,behavior:"smooth"});

alert("Application loaded successfully.");

}
catch(error){

console.error(error);

alert("Failed to load application.");

}

}

/* ======================================================
POPULATE FORM
====================================================== */

function populateForm(data){

Object.entries(data).forEach(([field,value])=>{

const elements = document.querySelectorAll(`[name="${field}"]`);

if(!elements.length) return;

elements.forEach(input=>{

if(input.type === "file") return;

if(input.type === "radio"){

if(input.value == value) input.checked = true;

}

else if(input.type === "checkbox"){

input.checked = Boolean(value);

}

else if(input.tagName === "SELECT"){

input.value = value ?? "";

if(window.jQuery && $(input).hasClass("select2-hidden-accessible")){
$(input).trigger("change");
}

}

else if(input.tagName === "TEXTAREA"){

input.value = value ?? "";

}

else{

input.value = value ?? "";

}

});

});

const _assignId = document.getElementById("assigned_to_admin_id");
const _assignSearch = document.getElementById("staff_assign_search");
if (_assignId && data.assigned_to_admin_id != null && String(data.assigned_to_admin_id).trim() !== "") {
  _assignId.value = String(data.assigned_to_admin_id).trim();
}
if (_assignSearch && data.assigned_staff_name != null && String(data.assigned_staff_name).trim() !== "") {
  _assignSearch.value = String(data.assigned_staff_name).trim();
}

if (typeof window.restoreUploadedDocuments === "function") {
window.restoreUploadedDocuments(data);
}

if (typeof window.restorePhoneInputsFromData === "function") {
window.restorePhoneInputsFromData(data);
}

}

/* ======================================================
RESTORE STUDY SELECTIONS
====================================================== */

function restoreStudySelections(choices){

if(!Array.isArray(choices) || !choices.length) return;

const regionSelect = $("#regions");
const studyChoicesWrap = document.getElementById("studyChoices");

if(!regionSelect.length || !studyChoicesWrap) return;

[...studyChoicesWrap.children].forEach(child=>{
if(typeof teardownStudyCard === "function") teardownStudyCard(child);
child.remove();
});

const regionMap = new Map();
const universityMap = new Map();

choices.forEach(choice=>{
const regionId = String(choice.region_id ?? "");
const universityId = String(choice.university_id ?? "");
if(!regionId || !universityId) return;

const regionName = String(choice.region_name ?? "").trim();
if(!regionMap.has(regionId)){
regionMap.set(regionId, regionName);
}

const uniKey = `${regionId}|${universityId}`;
if(!universityMap.has(uniKey)){
universityMap.set(uniKey,{
regionId,
regionName,
universityId,
universityName: String(choice.university_name ?? "").trim(),
levels: new Map()
});
}

const entry = universityMap.get(uniKey);
const levelId = String(choice.program_level_id ?? "");
const programId = String(choice.program_id ?? "");

if(!levelId) return;

if(!entry.levels.has(levelId)){
entry.levels.set(levelId,{
levelId,
levelName: String(choice.level_name ?? "").trim(),
programs: []
});
}

if(programId){
const levelEntry = entry.levels.get(levelId);
if(!levelEntry.programs.some(program => String(program.id) === programId)){
levelEntry.programs.push({
id: programId,
name: String(choice.program_name ?? "").trim()
});
}
}
});

const regionIds = [];
regionMap.forEach((regionName, regionId)=>{
let option = regionSelect.find(`option[value="${regionId}"]`).get(0);
if(!option){
option = new Option(regionName || `Region ${regionId}`, regionId, false, false);
regionSelect.append(option);
} else if(regionName) {
option.text = regionName;
}
regionIds.push(regionId);
});

regionSelect.val(regionIds).trigger("change");

if(typeof createUniversityStudyCard !== "function" || typeof appendLevelProgramRow !== "function"){
return;
}

universityMap.forEach(entry=>{
const university = {
id: Number(entry.universityId),
name: entry.universityName || "University"
};

const card = createUniversityStudyCard(entry.regionId, university, {
autoAppendInitialRow: false
});

const levelRowsWrap = card?.querySelector(".study-level-rows");
if(!levelRowsWrap) return;

if(!entry.levels.size){
appendLevelProgramRow(levelRowsWrap, university);
return;
}

entry.levels.forEach(levelEntry=>{
appendLevelProgramRow(levelRowsWrap, university, {
levelId: levelEntry.levelId,
levelName: levelEntry.levelName,
programs: levelEntry.programs
});
});
});

if(typeof window.refreshAddUniversitySelect === "function"){
Promise.resolve(window.refreshAddUniversitySelect()).catch(console.error);
}

if(typeof window.updateStudyEmptyMessage === "function"){
window.updateStudyEmptyMessage();
}

if(window.buildStudyCart) window.buildStudyCart();

}

/* ======================================================
KEYBOARD NAVIGATION
====================================================== */

searchBox.addEventListener("keydown",function(e){

const items = resultsBox.querySelectorAll(".list-group-item-action");

if(!items.length) return;

if(e.key === "ArrowDown"){
e.preventDefault();
selectedIndex = (selectedIndex+1)%items.length;
}

else if(e.key === "ArrowUp"){
e.preventDefault();
selectedIndex = (selectedIndex-1+items.length)%items.length;
}

else if(e.key === "Enter"){
if(selectedIndex >= 0){
e.preventDefault();
items[selectedIndex].click();
}
}

items.forEach(el=>el.classList.remove("active"));

if(selectedIndex >= 0) items[selectedIndex].classList.add("active");

});

/* ======================================================
CLICK OUTSIDE
====================================================== */

document.addEventListener("click",function(e){

if(!e.target.closest("#resume_email_search") &&
!e.target.closest("#resumeResults")){
hideResults();
}

});

})();
</script>
</body>
</html>