<?php
declare(strict_types=1);

/**
 * Create a one-off package + single fee item for manual "Other" payments.
 * Persists to fee_packages and fee_items (same tables as predefined packages).
 */
function pcvc_create_custom_fee_package(
    mysqli $conn,
    string $title,
    string $itemName,
    string $currency,
    float $totalAmount
): array {
    $title = trim($title);
    $itemName = trim($itemName) !== '' ? trim($itemName) : $title;
    $currency = strtoupper(trim($currency));
    $totalAmount = round($totalAmount, 2);

    if ($title === '' || $currency === '' || $totalAmount <= 0) {
        throw new InvalidArgumentException('Invalid custom package data');
    }

    $code = 'cust-' . date('YmdHis') . '-' . strtolower(bin2hex(random_bytes(3)));

    $stmt = $conn->prepare(
        'INSERT INTO fee_packages (code, title, currency, total_amount, total_expected)
         VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare package insert');
    }
    $stmt->bind_param('sssdd', $code, $title, $currency, $totalAmount, $totalAmount);
    $stmt->execute();
    $packageId = (int) $conn->insert_id;
    $stmt->close();

    if ($packageId <= 0) {
        throw new RuntimeException('Failed to create custom package');
    }

    $stmt = $conn->prepare(
        'INSERT INTO fee_items (package_id, name, amount, currency)
         VALUES (?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare fee item insert');
    }
    $stmt->bind_param('isds', $packageId, $itemName, $totalAmount, $currency);
    $stmt->execute();
    $feeItemId = (int) $conn->insert_id;
    $stmt->close();

    if ($feeItemId <= 0) {
        throw new RuntimeException('Failed to create custom fee item');
    }

    return [
        'package_id'  => $packageId,
        'fee_item_id' => $feeItemId,
        'item_name'   => $itemName,
    ];
}
