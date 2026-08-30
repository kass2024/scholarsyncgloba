<?php
declare(strict_types=1);

/**
 * Test attendance WhatsApp template send to one admin.
 *
 * Usage:
 *   php tools/test_attendance_whatsapp.php --admin-id=1
 *   php tools/test_attendance_whatsapp.php --admin-id=1 --checkout
 */

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers/daily_attendance_notify.php';

$adminId = 0;
$checkout = false;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--admin-id=')) {
        $adminId = (int) substr($arg, 11);
    }
    if ($arg === '--checkout') {
        $checkout = true;
    }
}

if ($adminId <= 0) {
    fwrite(STDERR, "Usage: php tools/test_attendance_whatsapp.php --admin-id=ID [--checkout]\n");
    exit(1);
}

$stmt = $conn->prepare("
    SELECT id, full_name, first_name, last_name, email, phone_number, role
    FROM admins WHERE id = ? LIMIT 1
");
$stmt->bind_param('i', $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    fwrite(STDERR, "Admin #$adminId not found.\n");
    exit(1);
}

$phone = pcvc_admin_resolve_whatsapp_phone($admin);
echo "Admin: {$admin['full_name']} (id $adminId)\n";
echo "Phone (admins.phone_number): " . ($phone !== '' ? $phone : '(empty)') . "\n";

if ($phone === '') {
    fwrite(STDERR, "No phone_number on this admin. Update admins.phone_number first.\n");
    exit(1);
}

$date = date('Y-m-d');
$summary = [
    'admin_id'        => $adminId,
    'name'            => $admin['full_name'] ?: 'Staff',
    'email'           => $admin['email'] ?? '',
    'phone'           => $phone,
    'admin_row'       => $admin,
    'date'            => $date,
    'status'          => 'full',
    'check_in_label'  => '8:30 AM',
    'check_out_label' => '5:00 PM',
    'work_label'      => '6h 30m',
    'salary_label'    => 'RWF 3,998',
];

$cfg = $checkout
    ? pcvc_checkout_whatsapp_template_config()
    : pcvc_daily_whatsapp_template_config();

echo "Template: {$cfg['name']} (lang {$cfg['lang']}, {$cfg['params']} params)\n";
echo "Mode: " . ($checkout ? 'checkout' : 'daily') . "\n";

$result = pcvc_attendance_whatsapp_send_summary(
    $summary,
    $cfg
);

echo "Sent: " . ($result['sent'] ? 'yes' : 'no') . "\n";
echo "To E.164: " . ($result['to'] ?? '') . "\n";
if (!$result['sent']) {
    echo "Error: " . $result['error'] . "\n";
    if ($result['detail'] !== '') {
        echo "Detail: " . $result['detail'] . "\n";
    }
    exit(1);
}

echo "OK — check WhatsApp on the admin phone.\n";
