<?php
/**
 * Seed 7.13 Canada Visit Visa – With Invitation Letter for Record Payment.
 *
 * Adds fee_packages + fee_items (code p710b) and syncs renumbered package titles
 * (7.14–7.20) to match student-contract.php / generate-contract-pdf.php.
 *
 * Usage:
 *   php seed_canada_visit_visa_invitation_package.php
 *   http://localhost/scholarsyncglobal/seed_canada_visit_visa_invitation_package.php
 *
 * Safe to re-run (skips existing rows; updates titles when they differ).
 */
declare(strict_types=1);

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/db.php';

/** @return array<string, string> code => title */
function pcvc_fee_package_title_updates(): array
{
    return [
        'p710'  => '7.12 Canada Visit Visa',
        'p710b' => '7.13 Canada Visit Visa – With Invitation Letter',
        'p711'  => '7.14 USA Visit Visa',
        'p712'  => '7.15 Europe Visit Visa',
        'p713'  => '7.16 Asia Visit Visa',
        'p714'  => '7.17 SHORT COURSES-CANADA',
        'p715'  => '7.18 STUDY PhD IN CANADA-USA-EUROPE & ASIA',
        'p716'  => '7.19 WES EVALUATION – INTERNATIONAL EQUIVALENCE',
        'p717'  => '7.20 GUARANTEED EVALUATION SUPPORT!',
    ];
}

/** @return list<array{name:string,amount:float}> */
function pcvc_canada_visit_invitation_fee_items(): array
{
    return [
        ['name' => 'Document Preparation and Visa Application Screening', 'amount' => 815.00],
        ['name' => 'Visa Application Fee', 'amount' => 100.00],
        ['name' => 'Biometrics Fee', 'amount' => 85.00],
        ['name' => 'Service Fee (After Visa Approval)', 'amount' => 2000.00],
    ];
}

function pcvc_out(string $line): void
{
    echo $line . PHP_EOL;
}

function pcvc_get_package_id_by_code(mysqli $conn, string $code): ?int
{
    $stmt = $conn->prepare('SELECT id FROM fee_packages WHERE code = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['id'] : null;
}

function pcvc_ensure_fee_package(
    mysqli $conn,
    string $code,
    string $title,
    string $currency,
    float $totalAmount
): array {
    $existingId = pcvc_get_package_id_by_code($conn, $code);
    if ($existingId !== null) {
        return ['id' => $existingId, 'created' => false];
    }

    $stmt = $conn->prepare(
        'INSERT INTO fee_packages (code, title, currency, total_amount, total_expected)
         VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('sssdd', $code, $title, $currency, $totalAmount, $totalAmount);
    if (!$stmt->execute()) {
        throw new RuntimeException('Insert fee_packages failed: ' . $stmt->error);
    }
    $packageId = (int) $conn->insert_id;
    $stmt->close();

    if ($packageId <= 0) {
        throw new RuntimeException('Insert fee_packages returned no id');
    }

    return ['id' => $packageId, 'created' => true];
}

function pcvc_ensure_fee_item(
    mysqli $conn,
    int $packageId,
    string $name,
    float $amount,
    string $currency
): array {
    $stmt = $conn->prepare(
        'SELECT id FROM fee_items WHERE package_id = ? AND name = ? LIMIT 1'
    );
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('is', $packageId, $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        return ['id' => (int) $row['id'], 'created' => false];
    }

    $stmt = $conn->prepare(
        'INSERT INTO fee_items (package_id, name, amount, currency) VALUES (?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('isds', $packageId, $name, $amount, $currency);
    if (!$stmt->execute()) {
        throw new RuntimeException('Insert fee_items failed: ' . $stmt->error);
    }
    $itemId = (int) $conn->insert_id;
    $stmt->close();

    return ['id' => $itemId, 'created' => true];
}

function pcvc_update_package_title(mysqli $conn, string $code, string $title): string
{
    $stmt = $conn->prepare(
        'UPDATE fee_packages SET title = ? WHERE code = ? AND title <> ?'
    );
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('sss', $title, $code, $title);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        return 'updated';
    }

    return pcvc_get_package_id_by_code($conn, $code) !== null ? 'unchanged' : 'missing';
}

$lines = [
    'Seed 7.13 Canada Visit Visa – With Invitation Letter (Record Payment)',
    'Database: ' . ($conn->host_info ?? 'connected'),
    str_repeat('-', 72),
];

try {
    if (!$conn->begin_transaction()) {
        throw new RuntimeException('Could not start transaction');
    }

    $pkgCode = 'p710b';
    $pkgTitle = pcvc_fee_package_title_updates()[$pkgCode];
    $currency = 'CAD';
    $totalAmount = 3000.00;

    $pkg = pcvc_ensure_fee_package($conn, $pkgCode, $pkgTitle, $currency, $totalAmount);
    $lines[] = sprintf(
        'Package %s: %s (id=%d)',
        $pkgCode,
        $pkg['created'] ? 'INSERTED' : 'already exists',
        $pkg['id']
    );

    foreach (pcvc_canada_visit_invitation_fee_items() as $item) {
        $result = pcvc_ensure_fee_item(
            $conn,
            $pkg['id'],
            $item['name'],
            $item['amount'],
            $currency
        );
        $lines[] = sprintf(
            '  fee_item "%s": %s (id=%d, CAD %.2f)',
            $item['name'],
            $result['created'] ? 'INSERTED' : 'already exists',
            $result['id'],
            $item['amount']
        );
    }

    $lines[] = '';
    $lines[] = 'Sync renumbered package titles:';

    foreach (pcvc_fee_package_title_updates() as $code => $title) {
        if ($code === 'p710b') {
            continue;
        }
        $status = pcvc_update_package_title($conn, $code, $title);
        $lines[] = sprintf('  %s => %s [%s]', $code, $title, $status);
    }

    if (!$conn->commit()) {
        throw new RuntimeException('Commit failed');
    }

    $lines[] = '';
    $lines[] = 'Verify:';

    $verifyStmt = $conn->prepare(
        'SELECT fp.id, fp.code, fp.title, fp.total_amount, fp.currency,
                COUNT(fi.id) AS item_count
         FROM fee_packages fp
         LEFT JOIN fee_items fi ON fi.package_id = fp.id
         WHERE fp.code = ?
         GROUP BY fp.id, fp.code, fp.title, fp.total_amount, fp.currency
         LIMIT 1'
    );
    $verifyStmt->bind_param('s', $pkgCode);
    $verifyStmt->execute();
    $verify = $verifyStmt->get_result()->fetch_assoc();
    $verifyStmt->close();

    if ($verify) {
        $lines[] = sprintf(
            '  %s | id=%s | total=%s %s | fee_items=%s',
            $verify['code'],
            $verify['id'],
            $verify['total_amount'],
            $verify['currency'],
            $verify['item_count']
        );
    }

    $lines[] = '';
    $lines[] = 'Done. Refresh Applicants Management → Record Payment to see the new option.';
} catch (Throwable $e) {
    $conn->rollback();
    $lines[] = '';
    $lines[] = 'ERROR: ' . $e->getMessage();
    pcvc_out(implode(PHP_EOL, $lines));
    if (!$isCli) {
        http_response_code(500);
    }
    exit(1);
}

pcvc_out(implode(PHP_EOL, $lines));
