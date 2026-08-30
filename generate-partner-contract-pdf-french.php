<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/pdf-generator-base.php';

class FrenchPDFGenerator extends PDFGeneratorBase {
    
    protected function getFilename(): string {
        return 'contrat-partenariat-' . $this->contract['id'] . '-' . date('Y-m-d') . '.pdf';
    }
    
    protected function getContractContent(): string {
        $partnerSignatureHtml = $this->processSignatureImage($this->contract['signature_image']);
        $managerSignatureHtml = $this->getManagerSignature();
        $companyStampHtml = $this->getCompanyStamp();
        $signedDate = $this->formatSignedDate($this->contract['signed_date'] ?? '');
        $partnerEmail = $this->esc($this->contract['representative_email'] ?: $this->contract['company_email']);
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="utf-8">
        <title>Accord de Partenariat Stratégique</title>
        <style>' . $this->getBaseStyles() . '</style>
        </head>
        <body>
        
        <div class="content-wrapper">
        <div class="header">
            <h1>Accord de Partenariat Stratégique</h1>
            <div class="subtitle">Un Partenariat Professionnel pour les Services d\'Éducation Mondiale</div>
        </div>
        
        <div>
            <h4>1. PARTIES</h4>
            <p><strong>Entre</strong></p>
            
            <p><strong>Nom de l\'Entreprise :</strong> ' . $this->esc($this->contract['company_name']) . '</p>
            <p><strong>Représentant :</strong> ' . $this->esc($this->contract['representative_name']) . '</p>
            <p><strong>Fonction :</strong> ' . $this->esc($this->contract['representative_title']) . '</p>
            <p><strong>Email :</strong> ' . $this->esc($this->contract['company_email']) . '</p>
            <p><strong>Téléphone :</strong> ' . $this->esc($this->contract['company_phone']) . '</p>
            <p><strong>Adresse complète :</strong> ' . $this->esc($this->contract['company_address']) . '</p>
            
            <p><strong>et</strong></p>
            
            <p><strong>ScholarSync Global Co. Ltd</strong><br>
            Dr Jean Pierre Twajamahoro<br>
            Propriétaire & Directeur Général<br>
            Adresse courriel: infos@scholarsyncglobal.ca<br>
            Téléphone: +1 (438) 290-6688<br>
            Adresse au Rwanda: Rwanda - Kigali<br>
            Town Center Building (near Simba Supermarket),<br>
            2nd Floor, Door: F2B-022C, Nyarugenge<br>
            Adresse au Canada:<br>
            294 Rue Vezina App 202; Lasalle, Quebec H8R 3M9</p>
        </div>
        
        <h2>2. OBJET DE L\'ACCORD</h2>
        
        <p>L\'objectif principal de cet accord est de mettre en place un système complet et structuré d\'accompagnement des étudiants, comprenant :</p>
        <ul>
            <li>L\'évaluation des documents et de l\'éligibilité</li>
            <li>La sélection des universités/établissements</li>
            <li>L\'obtention de l\'admission</li>
            <li>L\'assistance pour les bourses partielles et les prêts étudiants (si applicable)</li>
            <li>Le conseil et l\'obtention de visa</li>
            <li>L\'organisation du voyage</li>
            <li>L\'accueil à l\'aéroport et l\'assistance à l\'installation dans le pays de destination</li>
        </ul>
        
        <h2>3. PORTÉE DU PARTENARIAT</h2>
        
        <h3>3.1 Recrutement et Conseil aux Étudiants</h3>
        <ul>
            <li>Identification et recrutement d\'étudiants qualifiés</li>
            <li>Orientation académique et professionnelle adaptée aux opportunités internationales</li>
        </ul>
        
        <h3>3.2 Évaluation des Documents et Processus d\'Admission</h3>
        <ul>
            <li>Vérification complète des documents et de l\'éligibilité</li>
            <li>Sélection des universités et programmes à l\'international</li>
            <li>Préparation et soumission des candidatures</li>
            <li>Obtention des lettres d\'admission</li>
            <li>Assistance pour les bourses et prêts étudiants (si applicable)</li>
        </ul>
        
        <h3>3.3 Traitement des Visas et Immigration</h3>
        <ul>
            <li>Conseil en visa sous la supervision du Dr Jean Pierre Twajamahoro</li>
            <li>Vérification des documents selon les lois du pays de destination</li>
            <li>Traitement et suivi des demandes de visa</li>
        </ul>
        
        <h3>3.4 Services de Voyage et Pré-départ</h3>
        <ul>
            <li>Planification du voyage et assistance pour les vols</li>
            <li>Orientation avant le départ</li>
        </ul>
        
        <h3>3.5 Accueil à l\'Aéroport et Installation (Engagement Clé)</h3>
        <ul>
            <li>Organisation de l\'accueil à l\'aéroport dans le pays de destination</li>
            <li>Assistance pour le logement initial</li>
            <li>Aide à l\'installation à l\'arrivée</li>
            <li>Coordination avec les partenaires locaux</li>
        </ul>
        
        <h2>4. MISSION PRINCIPALE</h2>
        
        <p>Les deux parties conviennent d\'opérer comme un cabinet global de conseil en éducation internationale, offrant :</p>
        <ul>
            <li>Un modèle de service « De l\'Évaluation à l\'Installation »</li>
            <li>Couvrant toutes les destinations internationales</li>
            <li>Incluant admission, visa, voyage et accompagnement à l\'arrivée</li>
            <li>Ce partenariat garantit une transition fluide depuis l\'évaluation initiale jusqu\'à l\'installation complète à l\'étranger</li>
        </ul>
        
        <h2>5. RÔLES ET RESPONSABILITÉS</h2>
        
        <div class="page-break">
        <h3>5.1 Nom de l\'Entreprise : ' . $this->esc($this->contract['company_name']) . '</h3>
        <ul>
            <li>Recruter et préparer les étudiants</li>
            <li>Assister dans la collecte et la vérification initiale des documents</li>
            <li>Aider à la préparation des candidatures</li>
            <li>Fournir un accompagnement avant le départ</li>
            <li>Maintenir la communication avec les candidats</li>
        </ul>
        </div>
        
        <div class="page-break">
        <h3>5.2 ScholarSync Global Co. Ltd</h3>
        <p>(Représentée par Dr Jean Pierre Twajamahoro, Propriétaire & Directeur Général)</p>
        <ul>
            <li>Effectuer l\'évaluation initiale des documents et de l\'éligibilité</li>
            <li>Aider à la sélection des universités/établissements</li>
            <li>Assister dans l\'obtention des admissions</li>
            <li>Fournir une assistance pour les bourses partielles et prêts étudiants (si applicable)</li>
            <li>Fournir des services professionnels de conseil et traitement des visas</li>
            <li>Assurer la conformité avec les lois d\'immigration des pays de destination</li>
            <li>Gérer les documents et procédures de visa</li>
            <li>Coordonner la planification du voyage</li>
            <li>Organiser l\'accueil à l\'aéroport et l\'assistance à l\'installation dans le pays de destination</li>
            <li>Fournir un accompagnement après l\'arrivée si nécessaire</li>
        </ul>
        </div>
        
        <h2>6. DISPOSITIONS FINANCIÈRES</h2>
        
        <ul>
            <li>Chaque partie conserve le droit de facturer ses propres frais de service aux étudiants selon ses politiques internes.</li>
            <li>ScholarSync Global Co. Ltd s\'engage à payer des frais de service d\'application à Nom de l\'Entreprise : ' . $this->esc($this->contract['company_name']) . ' dès l\'émission d\'une lettre d\'admission officielle.</li>
            <li>Les frais convenus pour application sont :</li>
            <li> Canada : 125 CAD par étudiant</li>
            <li> États-Unis : 100 USD par étudiant</li>
            <li> Europe : 100 EUR par étudiant</li>
            <li> Asie : 100 USD par étudiant</li>
            <li>Le paiement doit être effectué immédiatement après l\'obtention de la lettre d\'admission, selon les modalités convenues.</li>
            <li>Les deux parties s\'engagent à assurer transparence et traçabilité financière.</li>
        </ul>
        
        <h2>7. VALEUR AJOUTÉE</h2>
        
        <p>Ce partenariat offre :</p>
        <ul>
            <li>Un service complet de l\'évaluation à l\'installation</li>
            <li>Une amélioration du taux de réussite des admissions et visas</li>
            <li>Une assistance financière (bourses et prêts)</li>
            <li>Une arrivée sécurisée et une intégration réussie à l\'étranger</li>
        </ul>
        
        <h2>8. COMMUNICATION ET COORDINATION</h2>
        
        <ul>
            <li>Désignation de représentants dédiés</li>
            <li>Suivi continu des dossiers étudiants</li>
            <li>Communication en temps réel</li>
        </ul>
        
        <h2>9. CONFIDENTIALITÉ</h2>
        
        <p>Toutes les informations échangées restent strictement confidentielles.</p>
        
        <h2>10. CONFORMITÉ ET ÉTHIQUE</h2>
        
        <ul>
            <li>Respect total des lois internationales</li>
            <li>Engagement éthique et transparent</li>
            <li>Tolérance zéro pour la fraude</li>
        </ul>
        
        <h2>11. DURÉE ET RÉSILIATION</h2>
        
        <ul>
            <li>Entrée en vigueur à la signature</li>
            <li>Valide pour 1 ans</li>
            <li>Résiliation avec préavis écrit de 30 jours</li>
            <li>Finalisation des dossiers en cours obligatoire</li>
        </ul>
        
        <h2>12. RÉSOLUTION DES LITIGES</h2>
        
        <ul>
            <li>Résolution à l\'amiable</li>
            <li>Arbitrage si nécessaire</li>
        </ul>
        
        <h2>13. FORCE MAJEURE</h2>
        
        <p>Aucune partie ne sera responsable en cas de circonstances indépendantes de sa volonté.</p>
        
        <h2>14. CONCLUSION</h2>
        
        <p>Cet accord représente un partenariat stratégique global, visant à fournir des services complets d\'éducation internationale, de l\'évaluation des documents jusqu\'à l\'accueil et l\'installation à l\'étranger.</p>
        
        <div class="signature-section">
            <h2>15. SIGNATURES</h2>
            <p>Cet Accord de Partenariat Stratégique est exécuté par les représentants autorisés des deux parties à la date indiquée ci-dessous :</p>
            
            <table style="width:100%;border-collapse:collapse;margin-top:12pt;">
                <tr>
                    <td style="width:50%;vertical-align:top;padding:10px 12px 10px 0;">
                        <p style="font-weight:700;color:#0d47a1;margin:0 0 10px 0;">Pour ScholarSync Global Co. Ltd</p>
                        <p><strong>Nom :</strong> Dr Jean Pierre Twajamahoro</p>
                        <p><strong>Fonction :</strong> Propriétaire & Directeur Général</p>
                        <p><strong>Signature :</strong></p>
                        ' . $managerSignatureHtml . '
                        <p><strong>Cachet de l\'entreprise :</strong></p>
                        ' . $companyStampHtml . '
                        <p><strong>Date :</strong> ' . $signedDate . '</p>
                    </td>
                    <td style="width:50%;vertical-align:top;padding:10px 0 10px 12px;border-left:1px solid #e5e7eb;">
                        <p style="font-weight:700;color:#0d47a1;margin:0 0 10px 0;">Entreprise Partenaire</p>
                        <p><strong>Nom de l\'entreprise :</strong> ' . $this->esc($this->contract['company_name']) . '</p>
                        <p><strong>Nom du représentant :</strong> ' . $this->esc($this->contract['representative_name']) . '</p>
                        <p><strong>Fonction :</strong> ' . $this->esc($this->contract['representative_title']) . '</p>
                        <p><strong>Courriel :</strong> ' . $partnerEmail . '</p>
                        <p><strong>Téléphone :</strong> ' . $this->esc($this->contract['company_phone']) . '</p>
                        <p><strong>Adresse :</strong> ' . $this->esc($this->contract['company_address']) . '</p>
                        <p><strong>Signature :</strong></p>
                        <div style="border:1px dashed #9ca3af;padding:6px;min-height:72px;">' . $partnerSignatureHtml . '</div>
                        <p><strong>Date :</strong> ' . $signedDate . '</p>
                    </td>
                </tr>
            </table>
            
            <div class="footer">
                <p>Cet accord constitue l\'entente complète entre les parties et remplace toutes les discussions, négociations et accords antérieurs.</p>
                <p>EN FOI DE QUOI, les parties ont exécuté cet Accord de Partenariat Stratégique à la date indiquée ci-dessus.</p>
            </div>
        </div>
        
        </div>
        </body>
        </html>
        ';
    }
}

function generatePartnerContractPDFFrench(int $contractId): ?string {
    global $conn;
    $generator = new FrenchPDFGenerator($conn, $contractId);
    return $generator->generate();
}
