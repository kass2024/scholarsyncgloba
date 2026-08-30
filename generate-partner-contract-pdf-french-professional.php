<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/french-contract-pdf.php';

function generatePartnerContractPDFFrench(int $contractId): ?string {
    global $conn;
    return generateProfessionalFrenchPDF($contractId);
}
