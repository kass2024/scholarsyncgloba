<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/english-contract-pdf.php';

function generatePartnerContractPDF(int $contractId): ?string {
    global $conn;
    return generateProfessionalEnglishPDF($contractId);
}
