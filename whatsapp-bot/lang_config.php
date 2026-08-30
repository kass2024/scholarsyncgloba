<?php
// lang_config.php
require_once "config.php";

class LanguageManager {
    private $conn;
    private $defaultLang = 'en';
    
    // Translations array
    private $translations = [
        'en' => [
            'welcome' => "👋 Welcome to Visa Consultant Canada!\n\nPlease select your preferred language:\n1️⃣ English\n2️⃣ French\n3️⃣ Kinyarwanda\n\nReply with 1, 2, or 3",
            'language_selected' => "✅ Language set to English. How can we assist you today?",
            'main_menu' => "📋 *Main Menu*\n\nPlease select a service:\n\n1️⃣ University Admission\n2️⃣ Study Visa\n3️⃣ Visit Visa\n4️⃣ Study Loan\n5️⃣ I-20 Application\n6️⃣ Credit Transfer\n7️⃣ Scholarship Guidance\n8️⃣ Track Application\n9️⃣ Contact Us\n\nReply with the number or describe your need",
            'contact_info' => "📞 *Contact Information*\n\n🌐 Website: https://scholarsyncglobal.ca\n📧 Email: infos@scholarsyncglobal.ca\n📱 WhatsApp: +1 450 823 1811\n\n*Regional Offices:*\n🇷🇼 Rwanda: Nduba, Gasabo, Kigali City\n🇰🇪 Kenya: +254 798 854944 / +254 745 277231\n🇬🇭 Ghana: +233 59 340 0478\n🇿🇲 Zambia: +260 972 968 285\n🇰🇷 South Korea: +82 10 9862 0978",
            'track_application' => "🔍 To track your application:\n\n1️⃣ Visit: https://scholarsyncglobal.ca\n2️⃣ Login with your credentials\n3️⃣ Check your application status\n\nNeed login help? Reply with HELP",
            'university_admission' => "🎓 *University Admission*\n\nWe help with applications to Canadian universities.\n\nTo proceed:\n1️⃣ Visit: https://scholarsyncglobal.ca\n2️⃣ Fill the admission form\n3️⃣ Upload your documents\n\nNeed guidance? Reply with DETAILS",
            'study_visa' => "📄 *Study Visa Application*\n\nRequired documents typically:\n• Valid passport\n• Letter of acceptance\n• Proof of funds\n• IELTS/TOEFL scores\n• Study plan\n\nStart application: https://scholarsyncglobal.ca",
            'visit_visa' => "✈️ *Visit Visa Application*\n\nRequirements:\n• Valid passport\n• Travel itinerary\n• Proof of accommodation\n• Financial statements\n• Return flight booking\n\nApply: https://scholarsyncglobal.ca",
            'study_loan' => "💰 *Study Loan Application*\n\nWe connect you with education loan providers.\n\nRequirements:\n• Admission letter\n• Academic records\n• Parent/guardian documents\n• Collateral details\n\nStart: https://scholarsyncglobal.ca",
            'i20_application' => "📝 *I-20 Application*\n\nFor US studies:\n• Complete SEVIS form\n• Financial evidence\n• Passport copy\n• Academic records\n\nApply via our website",
            'credit_transfer' => "🔄 *Credit Transfer*\n\nWe assess your previous education for credit transfer to Canadian institutions.\n\nSubmit transcripts for evaluation",
            'scholarship' => "🏆 *Scholarship Guidance*\n\nWe help identify and apply for:\n• Merit-based scholarships\n• Need-based grants\n• Country-specific awards\n\nCheck eligibility on our website",
            'escalate_human' => "⏳ Your request has been escalated to our team. A consultant will contact you shortly.\n\nFor urgent matters, call: +1 450 823 1811",
            'invalid_input' => "❌ Invalid option. Please reply with 1, 2, or 3 for language selection.",
            'help' => "Need assistance? Reply with:\n• MENU - Main menu\n• CONTACT - Contact info\n• TRACK - Track application\n• HUMAN - Talk to consultant",
            'goodbye' => "Thank you for contacting Visa Consultant Canada. Have a great day! 👋",
            'language_switch' => "To change language, reply with:\n• ENGLISH\n• FRENCH\n• KINYARWANDA"
        ],
        'fr' => [
            'welcome' => "👋 Bienvenue chez Visa Consultant Canada!\n\nVeuillez sélectionner votre langue préférée:\n1️⃣ Anglais\n2️⃣ Français\n3️⃣ Kinyarwanda\n\nRépondez avec 1, 2 ou 3",
            'language_selected' => "✅ Langue définie sur Français. Comment pouvons-nous vous aider aujourd'hui?",
            'main_menu' => "📋 *Menu Principal*\n\nSélectionnez un service:\n\n1️⃣ Admission universitaire\n2️⃣ Visa d'études\n3️⃣ Visa de visiteur\n4️⃣ Prêt d'études\n5️⃣ Demande I-20\n6️⃣ Transfert de crédits\n7️⃣ Orientation bourses\n8️⃣ Suivi dossier\n9️⃣ Nous contacter\n\nRépondez avec le numéro ou décrivez votre besoin",
            'contact_info' => "📞 *Coordonnées*\n\n🌐 Site web: https://scholarsyncglobal.ca\n📧 Email: infos@scholarsyncglobal.ca\n📱 WhatsApp: +1 450 823 1811\n\n*Bureaux régionaux:*\n🇷🇼 Rwanda: Nduba, Gasabo, Kigali City\n🇰🇪 Kenya: +254 798 854944 / +254 745 277231\n🇬🇭 Ghana: +233 59 340 0478\n🇿🇲 Zambie: +260 972 968 285\n🇰🇷 Corée du Sud: +82 10 9862 0978",
            'track_application' => "🔍 Pour suivre votre dossier:\n\n1️⃣ Visitez: https://scholarsyncglobal.ca\n2️⃣ Connectez-vous avec vos identifiants\n3️⃣ Vérifiez le statut\n\nBesoin d'aide pour la connexion? Répondez AIDE",
            'university_admission' => "🎓 *Admission universitaire*\n\nNous aidons avec les demandes d'admission aux universités canadiennes.\n\nPour procéder:\n1️⃣ Visitez: https://scholarsyncglobal.ca\n2️⃣ Remplissez le formulaire d'admission\n3️⃣ Téléchargez vos documents\n\nBesoin de conseils? Répondez DÉTAILS",
            'study_visa' => "📄 *Demande de visa d'études*\n\nDocuments généralement requis:\n• Passeport valide\n• Lettre d'acceptation\n• Preuve de fonds\n• Scores IELTS/TOEFL\n• Plan d'études\n\nCommencer: https://scholarsyncglobal.ca",
            'visit_visa' => "✈️ *Demande de visa visiteur*\n\nExigences:\n• Passeport valide\n• Itinéraire de voyage\n• Preuve d'hébergement\n• Relevés bancaires\n• Réservation vol retour\n\nPostuler: https://scholarsyncglobal.ca",
            'study_loan' => "💰 *Demande de prêt d'études*\n\nNous vous mettons en relation avec des fournisseurs de prêts éducatifs.\n\nExigences:\n• Lettre d'admission\n• Relevés académiques\n• Documents parent/tuteur\n• Détails garantie\n\nCommencer: https://scholarsyncglobal.ca",
            'i20_application' => "📝 *Demande I-20*\n\nPour études aux États-Unis:\n• Formulaire SEVIS\n• Preuve financière\n• Copie passeport\n• Relevés académiques\n\nPostuler via notre site web",
            'credit_transfer' => "🔄 *Transfert de crédits*\n\nNous évaluons vos études antérieures pour transfert de crédits vers des institutions canadiennes.\n\nSoumettez vos relevés pour évaluation",
            'scholarship' => "🏆 *Orientation bourses*\n\nNous aidons à identifier et postuler pour:\n• Bourses au mérite\n• Subventions basées sur les besoins\n• Prix spécifiques par pays\n\nVérifiez l'éligibilité sur notre site",
            'escalate_human' => "⏳ Votre demande a été transmise à notre équipe. Un consultant vous contactera sous peu.\n\nPour urgences, appelez: +1 450 823 1811",
            'invalid_input' => "❌ Option invalide. Veuillez répondre avec 1, 2 ou 3 pour la sélection de la langue.",
            'help' => "Besoin d'aide? Répondez avec:\n• MENU - Menu principal\n• CONTACT - Coordonnées\n• SUIVI - Suivi dossier\n• HUMAIN - Parler à un consultant",
            'goodbye' => "Merci d'avoir contacté Visa Consultant Canada. Bonne journée! 👋",
            'language_switch' => "Pour changer de langue, répondez avec:\n• ENGLISH\n• FRENCH\n• KINYARWANDA"
        ],
        'rw' => [
            'welcome' => "👋 Murakaza neza kuri Visa Consultant Canada!\n\nHitamo ururimi:\n1️⃣ Icyongereza\n2️⃣ Igifaransa\n3️⃣ Ikinyarwanda\n\nShyiramo 1, 2 cyangwa 3",
            'language_selected' => "✅ Wahisemo Ikinyarwanda. Twagufasha iki uyu munsi?",
            'main_menu' => "📋 *Menu Nyamukuru*\n\nHitamo serivisi:\n\n1️⃣ Kwiyandikisha muri kaminuza\n2️⃣ Visa y'amasomo\n3️⃣ Visa y'uruzendo\n4️⃣ Inguzanyo y'amasomo\n5️⃣ I-20 Application\n6️⃣ Kurekura amasomo\n7️⃣ Ubufasha bwa scholarship\n8️⃣ Kugenzura dosiye\n9️⃣ Twandikire\n\nShyiramo numero cyangwa usobanure icyo ushaka",
            'contact_info' => "📞 *Aho Duherereye*\n\n🌐 Urubuga: https://scholarsyncglobal.ca\n📧 Imeli: infos@scholarsyncglobal.ca\n📱 WhatsApp: +1 450 823 1811\n\n*Ibiro byo mu karere:*\n🇷🇼 Rwanda: Nduba, Gasabo, Kigali City\n🇰🇪 Kenya: +254 798 854944 / +254 745 277231\n🇬🇭 Ghana: +233 59 340 0478\n🇿🇲 Zambiya: +260 972 968 285\n🇰🇷 Koreya y'Epfo: +82 10 9862 0978",
            'track_application' => "🔍 Kugenzura dosiye yawe:\n\n1️⃣ Sura: https://scholarsyncglobal.ca\n2️⃣ Injira ukoresheje konti yawe\n3️⃣ Rebe uko dosiye yawe igeze\n\nUkeneye ubufasha bwo kwinjira? Shyiramo UBUFASHA",
            'university_admission' => "🎓 *Kwiyandikisha muri kaminuza*\n\nDufasha mu kwiyandikisha muri kaminuza zo muri Kanada.\n\nUko wakora:\n1️⃣ Sura: https://scholarsyncglobal.ca\n2️⃣ Uzuza formula\n3️⃣ Shyiramo inyandiko zawe\n\nUkeneye ubufasha? Shyiramo IBISOBANURO",
            'study_visa' => "📄 *Gusaba visa y'amasomo*\n\nInyandiko zisabwa:\n• Pasi poro\n• Letter of acceptance\n• Proof of funds\n• Amanota ya IELTS/TOEFL\n• Gahunda y'amasomo\n\nTangira: https://scholarsyncglobal.ca",
            'visit_visa' => "✈️ *Gusaba visa y'uruzendo*\n\nIbyisabwa:\n• Pasi poro\n• Gahunda y'urugendo\n• Aho uzabera\n• Impapuro z'amafaranga\n• Tiketi y'indege\n\nSaba: https://scholarsyncglobal.ca",
            'study_loan' => "💰 *Gusaba inguzanyo y'amasomo*\n\nDufasha kubona inguzanyo y'amasomo.\n\nIbyisabwa:\n• Letter of admission\n• Amanota\n• Inyandiko z'ababyeyi\n• Ibisobanuro by'ishingiro\n\nTangira: https://scholarsyncglobal.ca",
            'i20_application' => "📝 *I-20 Application*\n\nKwigana muri Amerika:\n• SEVIS formula\n• Proof of funds\n• Pasi poro kopi\n• Amanota\n\nSaba kuri urubuga rwacu",
            'credit_transfer' => "🔄 *Kurekura amasomo*\n\nDusuzuma amasomo wize hose kugirango arekurwe muri Kanada.\n\nOhereza amanota yawe kugirango asuzumwe",
            'scholarship' => "🏆 *Ubufasha bwa scholarship*\n\nDufasha kubona:\n• Scholarships zishingiye ku manota\n• Ubufasha bw'abenyeje\n• Ibihembo by'igihugu\n\nReba ibisabwa kuri urubuga rwacu",
            'escalate_human' => "⏳ Icyifuzo cyawe cyagejejwe ku itsinda ryacu. Umujyanama azakuvugisha vuba.\n\nNiba wihuta, hamagara: +1 450 823 1811",
            'invalid_input' => "❌ Ntago byemewe. Shyiramo 1, 2 cyangwa 3 kugirango uhisemo ururimi.",
            'help' => "Ukeneye ubufasha? Shyiramo:\n• MENU - Menu nyamukuru\n• TWANDIKIRE - Aho duherereye\n• KUGENZURA - Kugenzura dosiye\n• UMUNTU - Kuvugana n'umujyanama",
            'goodbye' => "Murakoze cyane kuza kuri Visa Consultant Canada. Umunya mwiza! 👋",
            'language_switch' => "Kugirango uhindure ururimi, shyiramo:\n• ENGLISH\n• FRENCH\n• KINYARWANDA"
        ]
    ];

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getUserLanguage($phone) {
        $stmt = $this->conn->prepare("SELECT language_code FROM user_languages WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['language_code'];
        }
        
        return $this->defaultLang;
    }

    public function setUserLanguage($phone, $lang) {
        if (!in_array($lang, ['en', 'fr', 'rw'])) {
            return false;
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO user_languages (phone, language_code) VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE language_code = VALUES(language_code)"
        );
        $stmt->bind_param("ss", $phone, $lang);
        return $stmt->execute();
    }

    public function translate($key, $lang = null) {
        if (!$lang) {
            $lang = $this->defaultLang;
        }
        
        return isset($this->translations[$lang][$key]) 
            ? $this->translations[$lang][$key] 
            : $this->translations['en'][$key] ?? $key;
    }

    public function getLanguageCode($input) {
        $input = strtolower(trim($input));
        
        // Check for numeric selection
        if ($input === '1' || $input === 'english' || $input === 'en') {
            return 'en';
        }
        if ($input === '2' || $input === 'french' || $input === 'fr' || $input === 'français') {
            return 'fr';
        }
        if ($input === '3' || $input === 'kinyarwanda' || $input === 'rw') {
            return 'rw';
        }
        
        return null;
    }
}