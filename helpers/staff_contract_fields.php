<?php
declare(strict_types=1);

/**
 * Default draggable field palette for staff contract signing.
 *
 * @param array<string, mixed> $admin
 * @return list<array<string, mixed>>
 */
function pcvc_staff_contract_palette_fields(array $admin): array
{
    $fullName = trim((string) ($admin['full_name'] ?? ''));
    $phone = trim((string) ($admin['phone_number'] ?? ''));
    $email = trim((string) ($admin['email'] ?? ''));
    $nid = trim((string) ($admin['national_id'] ?? ''));
    $address = trim((string) ($admin['address'] ?? ''));
    $dob = trim((string) ($admin['date_of_birth'] ?? ''));
    if ($dob !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        $dob = date('F j, Y', strtotime($dob));
    }
    $position = trim((string) ($admin['position'] ?? ''));
    $nationality = trim((string) ($admin['nationality'] ?? ''));
    $startDate = trim((string) ($admin['employment_start_date'] ?? ''));
    if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $startDate = date('F j, Y', strtotime($startDate));
    }

    return [
        [
            'paletteKey' => 'full_name',
            'type' => 'text',
            'label' => 'Full name',
            'value' => $fullName,
            'wPct' => 28,
            'hPct' => 2.8,
        ],
        [
            'paletteKey' => 'phone',
            'type' => 'phone',
            'label' => 'Phone',
            'value' => $phone,
            'wPct' => 22,
            'hPct' => 2.8,
        ],
        [
            'paletteKey' => 'email',
            'type' => 'email',
            'label' => 'Email',
            'value' => $email,
            'wPct' => 30,
            'hPct' => 2.8,
        ],
        [
            'paletteKey' => 'national_id',
            'type' => 'text',
            'label' => 'National ID',
            'value' => $nid,
            'wPct' => 24,
            'hPct' => 2.8,
        ],
        [
            'paletteKey' => 'address',
            'type' => 'text',
            'label' => 'Address',
            'value' => $address,
            'wPct' => 35,
            'hPct' => 2.8,
        ],
        [
            'paletteKey' => 'date_of_birth',
            'type' => 'text',
            'label' => 'Date of birth',
            'value' => $dob,
            'wPct' => 22,
            'hPct' => 2.8,
        ],
        [
            'paletteKey' => 'position',
            'type' => 'text',
            'label' => 'Position',
            'value' => $position,
            'wPct' => 26,
            'hPct' => 2.8,
        ],
        [
            'paletteKey' => 'nationality',
            'type' => 'text',
            'label' => 'Nationality',
            'value' => $nationality,
            'wPct' => 22,
            'hPct' => 2.8,
        ],
        [
            'paletteKey' => 'start_date',
            'type' => 'text',
            'label' => 'Employment start',
            'value' => $startDate,
            'wPct' => 24,
            'hPct' => 2.8,
        ],
        [
            'paletteKey' => 'signed_date',
            'type' => 'date',
            'label' => 'Signing date',
            'value' => date('Y-m-d'),
            'wPct' => 20,
            'hPct' => 2.8,
        ],
        [
            'paletteKey' => 'custom',
            'type' => 'custom',
            'label' => 'Custom text',
            'value' => '',
            'wPct' => 24,
            'hPct' => 2.8,
        ],
        [
            'paletteKey' => 'signature',
            'type' => 'signature',
            'label' => 'Signature',
            'value' => '',
            'wPct' => 22,
            'hPct' => 6,
        ],
    ];
}

/**
 * @param mixed $raw
 * @return list<array<string, mixed>>
 */
function pcvc_staff_contract_normalize_fields($raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $out = [];
    foreach ($raw as $field) {
        if (!is_array($field)) {
            continue;
        }
        $type = trim((string) ($field['type'] ?? 'text'));
        if (!in_array($type, ['text', 'phone', 'email', 'date', 'custom', 'signature'], true)) {
            $type = 'text';
        }
        $page = max(1, (int) ($field['page'] ?? 1));
        $xPct = max(0.0, min(100.0, (float) ($field['xPct'] ?? 0)));
        $yPct = max(0.0, min(100.0, (float) ($field['yPct'] ?? 0)));
        $wPct = max(3.0, min(80.0, (float) ($field['wPct'] ?? 20)));
        $hPct = max(1.5, min(30.0, (float) ($field['hPct'] ?? 2.8)));
        $label = trim((string) ($field['label'] ?? ''));
        $value = trim((string) ($field['value'] ?? ''));
        $signature = trim((string) ($field['signature'] ?? ''));
        $id = trim((string) ($field['id'] ?? ''));
        if ($id === '') {
            $id = bin2hex(random_bytes(8));
        }

        $out[] = [
            'id' => $id,
            'type' => $type,
            'label' => $label,
            'value' => $value,
            'signature' => $signature,
            'page' => $page,
            'xPct' => $xPct,
            'yPct' => $yPct,
            'wPct' => $wPct,
            'hPct' => $hPct,
        ];
    }

    return $out;
}
