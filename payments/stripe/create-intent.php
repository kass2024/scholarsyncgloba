<?php
$cfg = require __DIR__ . '/../config.php';
$stripe = $cfg['stripe'];

$amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;
$currency = isset($_POST['currency']) ? strtolower(trim((string)$_POST['currency'])) : 'usd';
$reference = isset($_POST['reference']) ? trim((string)$_POST['reference']) : '';
$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
$dryRun = isset($_POST['dry_run']) && (string)$_POST['dry_run'] === '1';

if ($amount <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid amount']);
    exit;
}
if ($reference === '') {
    $reference = 'SCHOLARSYNC_STRIPE_' . date('Ymd_His');
}

$payload = [
    'amount' => $amount,
    'currency' => $currency,
    'description' => 'ScholarSync payment',
    'metadata[reference]' => $reference,
];

if ($stripe['destination_account'] !== '') {
    $payload['transfer_data[destination]'] = $stripe['destination_account'];
}

if ($dryRun) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'dry_run' => true, 'payload' => $payload, 'message' => 'No Stripe API request sent']);
    exit;
}

$secretKey = $stripe['secret_key'] ?? '';
if ($secretKey === '') {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'Missing STRIPE_SECRET_KEY in .env']);
    exit;
}

$payload['payment_method_types[]'] = 'card';
if ($email !== '') {
    $payload['receipt_email'] = $email;
}

$ch = curl_init('https://api.stripe.com/v1/payment_intents');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
curl_setopt($ch, CURLOPT_USERPWD, $secretKey . ':');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$resp = curl_exec($ch);
$err = curl_error($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json; charset=UTF-8');

if ($err) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $err]);
    exit;
}

$json = json_decode((string)$resp, true);
if ($status < 200 || $status >= 300) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => ($json['error']['message'] ?? ('Stripe HTTP ' . $status)), 'stripe_response' => $json]);
    exit;
}

// Expected: client_secret, id, status...
$clientSecret = $json['client_secret'] ?? null;
$piId = $json['id'] ?? null;
$piStatus = $json['status'] ?? null;

echo json_encode([
    'ok' => true,
    'dry_run' => false,
    'client_secret' => $clientSecret,
    'payment_intent_id' => $piId,
    'status' => $piStatus,
    'reference' => $reference,
]);


