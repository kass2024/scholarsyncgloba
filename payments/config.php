<?php
require_once __DIR__ . '/bootstrap.php';

return [
    'app_env' => getenv('PAYMENT_APP_ENV') ?: 'local', // local|sandbox|production
    'default_provider' => getenv('PAYMENT_DEFAULT_PROVIDER') ?: 'mopay', // mopay|stripe

    'mopay' => [
        'account_id' => getenv('MOPAY_ACCOUNT_ID') ?: '',
        'auth_key' => getenv('MOPAY_AUTH_KEY') ?: '',
        'bearer_token' => getenv('MOPAY_BEARER_TOKEN') ?: '',
        'server_base_url' => rtrim((getenv('MOPAY_SERVER_BASE_URL') ?: 'http://41.186.14.66:443/'), '/'),
        'default_country_code' => getenv('MOPAY_DEFAULT_COUNTRY_CODE') ?: 'RW',
        'default_mno' => getenv('MOPAY_DEFAULT_MNO') ?: 'mtn',
        'default_currency' => getenv('MOPAY_DEFAULT_CURRENCY') ?: 'RWF',
        'callback_signing_key' => getenv('MOPAY_CALLBACK_SIGNING_KEY') ?: '',
        // Receiver account for transfer after successful authorization flow.
        'receiver_account_no' => getenv('MOPAY_RECEIVER_ACCOUNT_NO') ?: '',
        'payment_title' => getenv('MOPAY_PAYMENT_TITLE') ?: 'ScholarSync Service Payment',
        'payment_details' => getenv('MOPAY_PAYMENT_DETAILS') ?: 'Service payment from customer',
        // Safety: prevent tiny amounts in transfer flow (avoids debit-without-transfer scenarios).
        'min_transfer_amount' => getenv('MOPAY_MIN_TRANSFER_AMOUNT') ?: 200,
        // Doc note: transfer charge can apply (example says 100 per transfer).
        // Your merchant config may have 0 fee (your SMS shows Fee 0 RWF), so default to 0.
        'transfer_fee' => getenv('MOPAY_TRANSFER_FEE') ?: 0,
    ],

    'stripe' => [
        'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
        'secret_key' => getenv('STRIPE_SECRET_KEY') ?: '',
        'webhook_secret' => getenv('STRIPE_WEBHOOK_SECRET') ?: '',
        // Optional Stripe Connect destination account (acct_xxx)
        'destination_account' => getenv('STRIPE_DESTINATION_ACCOUNT') ?: '',
        'default_currency' => getenv('STRIPE_DEFAULT_CURRENCY') ?: 'usd',
    ],
];

