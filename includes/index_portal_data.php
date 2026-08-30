<?php
/**
 * Application portal copy-link IDs (legacy pay.php used ?card=; we also support ?portal=)
 */
$GLOBALS['index_portal_card_ids'] = ['Admissions', 'loan-apply', 'I-20-Application', 'credit_transfer', 'Visa-Application'];

$portal_translations = [
    'en' => [
        'page_title' => 'Study Abroad Application Platform',
        'hub_heading' => 'Payments & applications',
        'hub_sub' => 'Pay securely with MoPay, continue a saved application, or access the staff dashboard.',
        'hub_pay' => 'Pay with MoPay',
        'hub_portal' => 'Application portal',
        'hub_staff' => 'Staff login',
        'hub_register' => 'Create account',
        'staff_forgot' => 'Forgot password?',
        'staff_title' => 'Staff login',
        'staff_note' => 'All administrator roles sign in here. You will be redirected to the dashboard.',
        'staff_user' => 'Username',
        'staff_pass' => 'Password',
        'staff_submit' => 'Sign in securely',
        'portal_header' => 'Application portal',
        'retrieve_title' => 'Retrieve your application',
        'retrieve_description' => 'If you already started an application, enter your Application ID to continue.',
        'retrieve_placeholder' => 'Enter Application ID (e.g. user-XXXXXXXXXX)',
        'retrieve_button' => 'Continue',
        'view_details' => 'View details',
        'apply_now' => 'Apply now',
        'copy_link' => 'Copy link',
        'close' => 'Close',
        'enter_application_id' => 'Please enter your Application ID.',
        'id_not_found' => 'Application ID not found.',
        'error_retrieving' => 'Error retrieving application.',
        'link_copied' => 'Link copied',
        'link_copied_error' => 'Failed to copy link',
        'chat_with_us' => 'Chat with us',
        'start_chat' => 'Start chat',
        'email' => 'Email',
        'whatsapp_number' => 'WhatsApp number',
        'type_message' => 'Type your message…',
        'live_chat' => 'Live chat',
        'enter_email' => 'Please enter your email and WhatsApp number.',
        'failed_save' => 'Could not save chat info. Please try again.',
        'card_university' => 'UNIVERSITY ADMISSION PORTAL',
        'card_loan' => 'STUDY LOAN APPLICATION PORTAL',
        'card_i20' => 'I-20 APPLICATION PORTAL',
        'card_credit' => 'CREDIT TRANSFER APPLICATION PORTAL',
        'card_visa' => 'VISIT & STUDY VISA APPLICATION PORTAL',
    ],
    'fr' => [
        'page_title' => 'Plateforme de candidature',
        'hub_heading' => 'Paiements et candidatures',
        'hub_sub' => 'Payez avec MoPay, reprenez une candidature ou connectez-vous au tableau de bord.',
        'hub_pay' => 'Payer avec MoPay',
        'hub_portal' => 'Portail de candidature',
        'hub_staff' => 'Connexion équipe',
        'hub_register' => 'Créer un compte',
        'staff_forgot' => 'Mot de passe oublié ?',
        'staff_title' => 'Connexion équipe',
        'staff_note' => 'Tous les rôles administrateur utilisent ce portail.',
        'staff_user' => 'Nom d’utilisateur',
        'staff_pass' => 'Mot de passe',
        'staff_submit' => 'Se connecter',
        'portal_header' => 'Portail de candidature',
        'retrieve_title' => 'Reprendre votre candidature',
        'retrieve_description' => 'Si vous avez déjà commencé, entrez votre ID de candidature.',
        'retrieve_placeholder' => 'ID de candidature (ex. user-XXXXXXXXXX)',
        'retrieve_button' => 'Continuer',
        'view_details' => 'Voir les détails',
        'apply_now' => 'Postuler',
        'copy_link' => 'Copier le lien',
        'close' => 'Fermer',
        'enter_application_id' => 'Veuillez entrer votre ID de candidature.',
        'id_not_found' => 'ID introuvable.',
        'error_retrieving' => 'Erreur lors de la récupération.',
        'link_copied' => 'Lien copié',
        'link_copied_error' => 'Échec de la copie',
        'chat_with_us' => 'Discuter',
        'start_chat' => 'Commencer',
        'email' => 'E-mail',
        'whatsapp_number' => 'WhatsApp',
        'type_message' => 'Votre message…',
        'live_chat' => 'Chat',
        'enter_email' => 'E-mail et WhatsApp requis.',
        'failed_save' => 'Enregistrement impossible.',
        'card_university' => 'PORTAIL D’ADMISSION UNIVERSITAIRE',
        'card_loan' => 'PORTAIL PRÊT ÉTUDIANT',
        'card_i20' => 'PORTAIL I-20',
        'card_credit' => 'PORTAIL TRANSFERT DE CRÉDITS',
        'card_visa' => 'PORTAIL VISA VISITE & ÉTUDES',
    ],
];

function portal_t(string $key): string
{
    global $portal_translations, $current_lang;
    return $portal_translations[$current_lang][$key] ?? $portal_translations['en'][$key] ?? $key;
}

/** @var array<int, array<string, string>> */
$portal_cards = [
    [
        'title_key' => 'card_university',
        'pdf' => 'form-usa.pdf',
        'form' => 'student-application.php',
        'card_id' => 'Admissions',
        'type' => 'university',
    ],
    [
        'title_key' => 'card_loan',
        'pdf' => 'master-loan.pdf',
        'form' => 'master-loan.php',
        'card_id' => 'loan-apply',
        'type' => 'loan',
    ],
    [
        'title_key' => 'card_i20',
        'pdf' => 'form-8.pdf',
        'form' => 'form-20.php',
        'card_id' => 'I-20-Application',
        'type' => 'i20',
    ],
    [
        'title_key' => 'card_credit',
        'pdf' => 'credit_transfer.pdf',
        'form' => 'credit_transfer.php',
        'card_id' => 'credit_transfer',
        'type' => 'credit',
    ],
    [
        'title_key' => 'card_visa',
        'pdf' => 'form-17.pdf',
        'form' => 'visa.php',
        'card_id' => 'Visa-Application',
        'type' => 'visa',
    ],
];

$portal_card_param = $_GET['portal'] ?? null;
if ($portal_card_param === null && isset($_GET['card'])) {
    $cand = (string) $_GET['card'];
    if (in_array($cand, $GLOBALS['index_portal_card_ids'], true)) {
        $portal_card_param = $cand;
    }
}
