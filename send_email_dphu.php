<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$userId = $_GET['user_id'] ?? null;
if (!$userId) returnJson(['status' => 'error', 'message' => 'Missing user ID']);

$stmt = $conn->prepare("SELECT * FROM dphu WHERE user_id = ?");
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();
$formData = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : [];
$stmt->close();

if (empty($formData)) returnJson(['status' => 'error', 'message' => 'No data found']);

// ✅ Map of field labels (real display names)
$fieldLabels = [
    "prefix" => "Préfix",
    "prenom" => "Prénom",
    "deuxiemenom" => "Deuxième nom",
    "nomfamille" => "Nom de famille",
    "sexe" => "Sexe",
    "birth_month" => "Naissance - Mois",
    "birth_day" => "Naissance - Jour",
    "birth_year" => "Naissance - Année",
    "adresse" => "Adresse",
    "ville" => "Ville",
    "province" => "Province",
    "postal" => "Code Postal",
    "pays" => "Pays",
    "email" => "E-mail",
    "telephone" => "Téléphone",
    "orgName" => "Nom de l’organisation",
    "position" => "Poste",
    "orgTel" => "Téléphone de l’organisation",
    "orgEmail" => "Email de l’organisation",
    "orgStreet" => "Adresse de l’organisation",
    "orgApt" => "Appartement",
    "orgCity" => "Ville de l’organisation",
    "orgState" => "État/Province",
    "orgZip" => "Code Postal",
    "orgCountry" => "Pays",
    "school1_name" => "École 1 - Nom",
    "school1_field" => "École 1 - Domaine",
    "school1_degree" => "École 1 - Diplôme",
    "school1_from_month" => "École 1 - De (mois)",
    "school1_from_day" => "École 1 - De (jour)",
    "school1_from_year" => "École 1 - De (année)",
    "school1_to_month" => "École 1 - À (mois)",
    "school1_to_day" => "École 1 - À (jour)",
    "school1_to_year" => "École 1 - À (année)",
    "school2_name" => "École 2 - Nom",
    "school2_field" => "École 2 - Domaine",
    "school2_degree" => "École 2 - Diplôme",
    "school2_from_month" => "École 2 - De (mois)",
    "school2_from_day" => "École 2 - De (jour)",
    "school2_from_year" => "École 2 - De (année)",
    "school2_to_month" => "École 2 - À (mois)",
    "school2_to_day" => "École 2 - À (jour)",
    "school2_to_year" => "École 2 - À (année)",
    "school3_name" => "École 3 - Nom",
    "school3_field" => "École 3 - Domaine",
    "school3_degree" => "École 3 - Diplôme",
    "school3_from_month" => "École 3 - De (mois)",
    "school3_from_day" => "École 3 - De (jour)",
    "school3_from_year" => "École 3 - De (année)",
    "school3_to_month" => "École 3 - À (mois)",
    "school3_to_day" => "École 3 - À (jour)",
    "school3_to_year" => "École 3 - À (année)",
    "study_degree" => "Diplôme visé",
    "study_course" => "Programme choisi",
    "study_field" => "Domaine d’étude",
    "study_specialty" => "Spécialité souhaitée",
    "study_language" => "Langue d’étude",
    "english_proficiency" => "Niveau d’anglais",
    "study_additional_info" => "Informations supplémentaires",
];

$studentName = trim(($formData['prenom'] ?? '') . ' ' . ($formData['nomfamille'] ?? ''));
$email = trim($formData['email'] ?? '');

// ✅ Build email body
$excludeFields = [
    'user_id',
    'photo',
    'passport',
    'degree_certificate',
    'academic_transcript',
    'language_proof',
    'recommendation_letters',
    'other_documents',
    'id'
];
$htmlBody = "<h3 style='font-family:Arial;'>📝 Nouvelle soumission DPHU</h3>";
$htmlBody .= "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse: collapse; font-family:Arial;'>";

foreach ($fieldLabels as $key => $label) {
    // 👉 Date de naissance
    if ($key === 'birth_month') {
        $dob = trim(($formData['birth_day'] ?? '') . ' ' . ($formData['birth_month'] ?? '') . ' ' . ($formData['birth_year'] ?? ''));
        $htmlBody .= "<tr><td><strong>Date de naissance</strong></td><td>" . htmlspecialchars($dob) . "</td></tr>";
        continue;
    }

    // 👉 École 1 - De / À
    if ($key === 'school1_from_month') {
        $de = trim(($formData['school1_from_day'] ?? '') . ' ' . ($formData['school1_from_month'] ?? '') . ' ' . ($formData['school1_from_year'] ?? ''));
        $htmlBody .= "<tr><td><strong>École 1 - De</strong></td><td>" . htmlspecialchars($de) . "</td></tr>";
        continue;
    }
    if ($key === 'school1_to_month') {
        $a = trim(($formData['school1_to_day'] ?? '') . ' ' . ($formData['school1_to_month'] ?? '') . ' ' . ($formData['school1_to_year'] ?? ''));
        $htmlBody .= "<tr><td><strong>École 1 - À</strong></td><td>" . htmlspecialchars($a) . "</td></tr>";
        continue;
    }

    // 👉 École 2 - De / À
    if ($key === 'school2_from_month') {
        $de = trim(($formData['school2_from_day'] ?? '') . ' ' . ($formData['school2_from_month'] ?? '') . ' ' . ($formData['school2_from_year'] ?? ''));
        $htmlBody .= "<tr><td><strong>École 2 - De</strong></td><td>" . htmlspecialchars($de) . "</td></tr>";
        continue;
    }
    if ($key === 'school2_to_month') {
        $a = trim(($formData['school2_to_day'] ?? '') . ' ' . ($formData['school2_to_month'] ?? '') . ' ' . ($formData['school2_to_year'] ?? ''));
        $htmlBody .= "<tr><td><strong>École 2 - À</strong></td><td>" . htmlspecialchars($a) . "</td></tr>";
        continue;
    }

    // 👉 École 3 - De / À
    if ($key === 'school3_from_month') {
        $de = trim(($formData['school3_from_day'] ?? '') . ' ' . ($formData['school3_from_month'] ?? '') . ' ' . ($formData['school3_from_year'] ?? ''));
        $htmlBody .= "<tr><td><strong>École 3 - De</strong></td><td>" . htmlspecialchars($de) . "</td></tr>";
        continue;
    }
    if ($key === 'school3_to_month') {
        $a = trim(($formData['school3_to_day'] ?? '') . ' ' . ($formData['school3_to_month'] ?? '') . ' ' . ($formData['school3_to_year'] ?? ''));
        $htmlBody .= "<tr><td><strong>École 3 - À</strong></td><td>" . htmlspecialchars($a) . "</td></tr>";
        continue;
    }

    // 👉 Skip duplicate parts of date fields
    if (in_array($key, [
        'birth_day', 'birth_year',
        'school1_from_day', 'school1_from_year', 'school1_to_day', 'school1_to_year',
        'school2_from_day', 'school2_from_year', 'school2_to_day', 'school2_to_year',
        'school3_from_day', 'school3_from_year', 'school3_to_day', 'school3_to_year',
    ])) {
        continue;
    }

    // 👉 Show normal fields (even if empty)
    $value = $formData[$key] ?? '';
    $htmlBody .= "<tr><td><strong>$label</strong></td><td>" . nl2br(htmlspecialchars($value)) . "</td></tr>";
}

$htmlBody .= "</table>";

// ✅ Consentement
if (isset($formData['agreement'])) {
    $htmlBody .= "<p style='font-family:Arial;margin-top:10px;'><strong>✅ Consentement :</strong> " . ($formData['agreement'] == '1' ? 'Oui' : 'Non') . "</p>";
}

// ✅ Attach files
$basePath = __DIR__ . '/uploads/';

// ✅ Single file fields
$singleFiles = [
    'photo' => 'Photo d’identité',
    'passport' => 'Passeport',
    'degree_certificate' => 'Diplôme',
    'academic_transcript' => 'Relevé de notes',
    'language_proof' => 'Preuve de langue',
];

// ✅ Multi-file fields (arrays encoded as JSON)
$multiFiles = [
    'recommendation_letters' => 'Lettres de recommandation',
    'other_documents' => 'Autres documents',
];

$attachments = [];

// Attach single files
foreach ($singleFiles as $key => $label) {
    if (!empty($formData[$key]) && file_exists($formData[$key])) {
        $attachments[] = $formData[$key];
    }
}

// Attach multi files
foreach ($multiFiles as $key => $label) {
    if (!empty($formData[$key])) {
        $files = json_decode($formData[$key], true);
        if (is_array($files)) {
            foreach ($files as $file) {
                if (!empty($file) && file_exists($file)) {
                    $attachments[] = $file;
                }
            }
        }
    }
}

//Admin Email
try {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8'; // ✅ to fix é, è, etc.
    $mail->isSMTP();
    $mail->Host = 'scholarsyncglobal.ca';
    $mail->SMTPAuth = true;
    $mail->Username = 'infos@scholarsyncglobal.ca';
    $mail->Password = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync Global');
    $mail->addAddress('infos@scholarsyncglobal.ca');
    $mail->addAddress('ukipi2023@gmail.com');
    //$mail->addAddress('admission@dphu.ac.cd');

    $mail->isHTML(true);
    $mail->Subject = "🆕 Nouvelle candidature reçue - $studentName";
    $mail->Body = $htmlBody;

    $basePath = __DIR__ . '/uploads/';

    // ✅ Attach all individual file fields
    $singleFiles = [
        'photo' => 'Photo d’identité',
        'passport' => 'Carte d’identité / Passeport',
        'degree_certificate' => 'Diplôme ou Certificat',
        'academic_transcript' => 'Relevés de notes',
        'language_proof' => 'Preuve de langue',
    ];

    foreach ($singleFiles as $key => $label) {
        if (!empty($formData[$key])) {
            $path = $basePath . basename($formData[$key]);
            if (file_exists($path)) {
                $mail->addAttachment($path, "$label - " . basename($formData[$key]));
            }
        }
    }
// ✅ Attach all files in multi-upload JSON fields
$multiFiles = [
    'recommendation_letters' => 'Lettres de recommandation',
    'other_documents' => 'Autres documents',
];

foreach ($multiFiles as $key => $label) {
    if (!empty($formData[$key])) {
        $decoded = json_decode($formData[$key], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach ($decoded as $filename) {
                $filename = trim($filename);
                if (!empty($filename) && $filename !== '[""]') {
                    $safeFilename = basename($filename);
                    $path = $basePath . $safeFilename;
                    if (file_exists($path)) {
                        $mail->addAttachment($path, "$label - $safeFilename");
                    } else {
                        error_log("⚠️ Attachment file not found: $path");
                    }
                }
            }
        } else {
            error_log("⚠️ Invalid JSON in $key: " . $formData[$key]);
        }
    }
}


    $mail->send();
} catch (Exception $e) {
    returnJson(['status' => 'error', 'message' => 'Admin email failed: ' . $e->getMessage()]);
}


// ✅ Confirmation to student
try {
    if (!empty($email)) {
        $studentMail = new PHPMailer(true);
        $studentMail->isSMTP();
        $studentMail->Host = 'scholarsyncglobal.ca';
        $studentMail->SMTPAuth = true;
        $studentMail->Username = 'infos@scholarsyncglobal.ca';
        $studentMail->Password = getenv('SMTP_PASSWORD') ?: '';
        $studentMail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $studentMail->Port = 465;

        $studentMail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync Global');
        $studentMail->addAddress($email, $studentName);
        $studentMail->isHTML(true);
        $studentMail->Subject = "Confirmation de votre candidature";
        $studentMail->Body = "
            <p>Bonjour $studentName,</p>
            <p>Nous avons bien reçu votre formulaire. Notre équipe l'étudiera et vous contactera sous peu.</p>
            <p>Merci d'avoir choisi ScholarSync Global.</p>
            <p><strong>L'équipe ScholarSync Global</strong></p>
        ";
        $studentMail->send();
    }
} catch (Exception $e) {
    error_log("Student Email Error: " . $e->getMessage());
}

// ✅ Final JSON output
returnJson(['status' => 'success', 'message' => 'Emails sent successfully.']);

function returnJson($data) {
    while (ob_get_level()) ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
