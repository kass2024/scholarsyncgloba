<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/professional-pdf-generator.php';

class FrenchContractPDF extends ProfessionalPDFGenerator {
    
    protected function getMainContent(): string {
        $companyName = $this->esc($this->contract['company_name']);
        
        return "
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
            
            <h3>5.1 Nom de l\'Entreprise : $companyName</h3>
            <ul>
                <li>Recruter et préparer les étudiants</li>
                <li>Assister dans la collecte et la vérification initiale des documents</li>
                <li>Aider à la préparation des candidatures</li>
                <li>Fournir un accompagnement avant le départ</li>
                <li>Maintenir la communication avec les candidats</li>
            </ul>
            
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
            
            <h2>6. DISPOSITIONS FINANCIÈRES</h2>
            <ul>
                <li>Chaque partie conserve le droit de facturer ses propres frais de service aux étudiants selon ses politiques internes.</li>
                <li>ScholarSync Global Co. Ltd s\'engage à payer des frais de service d\'application à Nom de l\'Entreprise : $companyName dès l\'émission d\'une lettre d\'admission officielle.</li>
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
            
            <h2>15. COORDONNÉES</h2>
            
            <div class='party-info'>
                <h4>Nom de l\'Entreprise : $companyName</h4>
                <p><strong>Représentant :</strong> " . $this->esc($this->contract['representative_name']) . "</p>
                <p><strong>Fonction :</strong> " . $this->esc($this->contract['representative_title']) . "</p>
                <p><strong>Email :</strong> " . $this->esc($this->contract['representative_email']) . "</p>
                <p><strong>Téléphone :</strong> " . $this->esc($this->contract['company_phone']) . "</p>
                <p><strong>Adresse complète :</strong> " . $this->esc($this->contract['company_address']) . "</p>
            </div>
            
            <div class='party-info'>
                <h4>ScholarSync Global Co. Ltd</h4>
                <p>Dr Jean Pierre Twajamahoro<br>
                Propriétaire & Directeur Général<br>
                Adresse courriel: infos@scholarsyncglobal.ca<br>
                Téléphone: +1 (438) 290-6688<br>
                294 Rue Vezina App 202<br>
                Lasalle, Quebec H8R 3M9</p>
            </div>
        </div>";
    }
}

function generateProfessionalFrenchPDF(int $contractId): ?string {
    global $conn;
    $generator = new FrenchContractPDF($conn, $contractId);
    return $generator->generate();
}
