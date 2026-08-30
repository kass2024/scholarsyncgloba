<?php
declare(strict_types=1);

/**
 * Fixed UPAFA required fees (application + credit transfer).
 */
function pcvc_upafa_fee_tiers(): array
{
    return [
        'bachelor_application' => [
            'code'     => 'upa-bach-app',
            'title'    => 'UPAFA — Bachelor Application Fees',
            'item'     => 'Bachelor Application Fees',
            'amount'   => 25.00,
            'currency' => 'USD',
            'label'    => 'Bachelor Application Fees',
            'group'    => 'application',
        ],
        'masters_application' => [
            'code'     => 'upa-mast-app',
            'title'    => 'UPAFA — Masters Application Fees',
            'item'     => 'Masters Application Fees',
            'amount'   => 50.00,
            'currency' => 'USD',
            'label'    => 'Masters Application Fees',
            'group'    => 'application',
        ],
        'phd_application' => [
            'code'     => 'upa-phd-app',
            'title'    => 'UPAFA — PhD Application Fees',
            'item'     => 'PhD Application Fees',
            'amount'   => 75.00,
            'currency' => 'USD',
            'label'    => 'PhD Application Fees',
            'group'    => 'application',
        ],
        'bachelor_credit_transfer' => [
            'code'     => 'upa-bach-ct',
            'title'    => 'UPAFA — Bachelor Credit Transfer Fees',
            'item'     => 'Bachelor Credit Transfer Fees',
            'amount'   => 125.00,
            'currency' => 'USD',
            'label'    => 'Bachelor Credit Transfer Fees',
            'group'    => 'credit_transfer',
        ],
        'masters_credit_transfer' => [
            'code'     => 'upa-mast-ct',
            'title'    => 'UPAFA — Masters Credit Transfer Fees',
            'item'     => 'Masters Credit Transfer Fees',
            'amount'   => 175.00,
            'currency' => 'USD',
            'label'    => 'Masters Credit Transfer Fees',
            'group'    => 'credit_transfer',
        ],
        'phd_credit_transfer' => [
            'code'     => 'upa-phd-ct',
            'title'    => 'UPAFA — PhD Credit Transfer Fees',
            'item'     => 'PhD Credit Transfer Fees',
            'amount'   => 500.00,
            'currency' => 'USD',
            'label'    => 'PhD Credit Transfer Fees',
            'group'    => 'credit_transfer',
        ],
    ];
}

function pcvc_upafa_fee_tier(string $key): ?array
{
    $tiers = pcvc_upafa_fee_tiers();
    return $tiers[$key] ?? null;
}

/**
 * @return array{package_id:int,fee_item_id:int,item_name:string,total:float,currency:string,title:string}
 */
function pcvc_ensure_upafa_fee_package(mysqli $conn, string $tierKey): array
{
    $tier = pcvc_upafa_fee_tier($tierKey);
    if (!$tier) {
        throw new InvalidArgumentException('Invalid UPAFA fee tier');
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
            throw new RuntimeException('Failed to create UPAFA fee package');
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
            throw new RuntimeException('Failed to create UPAFA fee item');
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

function pcvc_upafa_fee_tier_paid(
    mysqli $conn,
    int $applicationId,
    int $packageId,
    string $sourceTable,
    ?int $feeItemId = null
): float {
    $allowed = ['credit_transfer_applications', 'upafa_registrations'];
    if (!in_array($sourceTable, $allowed, true)) {
        return 0.0;
    }

    if ($feeItemId !== null && $feeItemId > 0) {
        $stmt = $conn->prepare(
            "SELECT COALESCE(SUM(amount_paid), 0)
             FROM application_payments
             WHERE application_id = ? AND source_table = ? AND fee_item_id = ? AND status = 'PAID'"
        );
        $stmt->bind_param('isi', $applicationId, $sourceTable, $feeItemId);
    } else {
        $stmt = $conn->prepare(
            "SELECT COALESCE(SUM(ap.amount_paid), 0)
             FROM application_payments ap
             INNER JOIN fee_items fi ON fi.id = ap.fee_item_id AND fi.package_id = ?
             WHERE ap.application_id = ? AND ap.source_table = ? AND ap.status = 'PAID'"
        );
        $stmt->bind_param('iis', $packageId, $applicationId, $sourceTable);
    }

    $stmt->execute();
    $stmt->bind_result($paid);
    $stmt->fetch();
    $stmt->close();

    return round((float) $paid, 2);
}
