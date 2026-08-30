<?php
declare(strict_types=1);

/**
 * Fixed Credit Transfer pricing (section 7.10 — Bachelor, Masters, PhD).
 */
function pcvc_credit_transfer_tiers(): array
{
    return [
        'bachelor' => [
            'code'     => 'ct-bachelor',
            'title'    => 'Credit Transfer (Bachelor)',
            'item'     => 'Credit Transfer — Bachelor',
            'amount'   => 920.00,
            'currency' => 'USD',
            'label'    => 'Bachelor',
        ],
        'masters' => [
            'code'     => 'ct-masters',
            'title'    => 'Credit Transfer (Masters)',
            'item'     => 'Credit Transfer — Masters',
            'amount'   => 1220.00,
            'currency' => 'USD',
            'label'    => 'Masters',
        ],
        'phd' => [
            'code'     => 'ct-phd',
            'title'    => 'Credit Transfer (PhD)',
            'item'     => 'Credit Transfer — PhD',
            'amount'   => 1620.00,
            'currency' => 'USD',
            'label'    => 'PhD',
        ],
    ];
}

function pcvc_credit_transfer_tier(string $key): ?array
{
    $tiers = pcvc_credit_transfer_tiers();
    return $tiers[$key] ?? null;
}

/**
 * Find or create the shared fee package for a credit transfer tier.
 *
 * @return array{package_id:int,fee_item_id:int,item_name:string,total:float,currency:string,title:string}
 */
function pcvc_ensure_credit_transfer_package(mysqli $conn, string $tierKey): array
{
    $tier = pcvc_credit_transfer_tier($tierKey);
    if (!$tier) {
        throw new InvalidArgumentException('Invalid credit transfer tier');
    }

    $stmt = $conn->prepare(
        'SELECT id, title, currency, total_amount FROM fee_packages WHERE code = ? LIMIT 1'
    );
    $stmt->bind_param('s', $tier['code']);
    $stmt->execute();
    $pkg = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($pkg) {
        $packageId = (int) $pkg['id'];
    } else {
        $code     = $tier['code'];
        $title    = $tier['title'];
        $currency = $tier['currency'];
        $amount   = (float) $tier['amount'];

        $stmt = $conn->prepare(
            'INSERT INTO fee_packages (code, title, currency, total_amount, total_expected)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssdd', $code, $title, $currency, $amount, $amount);
        $stmt->execute();
        $packageId = (int) $conn->insert_id;
        $stmt->close();

        if ($packageId <= 0) {
            throw new RuntimeException('Failed to create credit transfer package');
        }
    }

    $stmt = $conn->prepare(
        'SELECT id, name, amount FROM fee_items WHERE package_id = ? ORDER BY id ASC LIMIT 1'
    );
    $stmt->bind_param('i', $packageId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) {
        $itemName = $tier['item'];
        $amount   = (float) $tier['amount'];
        $currency = $tier['currency'];

        $stmt = $conn->prepare(
            'INSERT INTO fee_items (package_id, name, amount, currency) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('isds', $packageId, $itemName, $amount, $currency);
        $stmt->execute();
        $feeItemId = (int) $conn->insert_id;
        $stmt->close();

        if ($feeItemId <= 0) {
            throw new RuntimeException('Failed to create credit transfer fee item');
        }

        $item = ['id' => $feeItemId, 'name' => $itemName, 'amount' => $amount];
    }

    return [
        'package_id'  => $packageId,
        'fee_item_id' => (int) $item['id'],
        'item_name'   => (string) $item['name'],
        'total'       => (float) $tier['amount'],
        'currency'    => $tier['currency'],
        'title'       => $tier['title'],
    ];
}

/**
 * Paid amount for a student on a specific credit transfer tier package.
 */
function pcvc_credit_transfer_tier_paid(
    mysqli $conn,
    int $applicationId,
    int $packageId
): float {
    $sourceTable = 'credit_transfer_applications';
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(ap.amount_paid), 0)
         FROM application_payments ap
         INNER JOIN fee_items fi ON fi.id = ap.fee_item_id AND fi.package_id = ?
         WHERE ap.application_id = ? AND ap.source_table = ? AND ap.status = 'PAID'"
    );
    $stmt->bind_param('iis', $packageId, $applicationId, $sourceTable);
    $stmt->execute();
    $stmt->bind_result($paid);
    $stmt->fetch();
    $stmt->close();

    return round((float) $paid, 2);
}
