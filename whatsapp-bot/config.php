<?php

//declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/env_bootstrap.php';

define('WHATSAPP_TOKEN', pcvc_env('WHATSAPP_TOKEN'));
define('PHONE_NUMBER_ID', pcvc_env('WHATSAPP_PHONE_NUMBER_ID'));
$__waOpenai = pcvc_env('WHATSAPP_OPENAI_KEY');
define('OPENAI_KEY', $__waOpenai !== '' ? $__waOpenai : pcvc_env('OPENAI_API_KEY'));
unset($__waOpenai);

define('DB_HOST', pcvc_env('WHATSAPP_DB_HOST', pcvc_env('DB_HOST', 'localhost')));
define('DB_USER', pcvc_env('WHATSAPP_DB_USER', pcvc_env('DB_USER')));
define('DB_PASS', pcvc_env('WHATSAPP_DB_PASS', pcvc_env('DB_PASS')));
define('DB_NAME', pcvc_env('WHATSAPP_DB_NAME', pcvc_env('DB_NAME')));

define('WHATSAPP_API_VERSION', 'v19.0');

define(
    'WHATSAPP_BASE_URL',
    'https://graph.facebook.com/' .
    WHATSAPP_API_VERSION . '/' .
    PHONE_NUMBER_ID . '/messages'
);

define('BROADCAST_TEMPLATE_NAME', 'promo_image_broadcast');

define('MAX_BATCH_SEND', 20);
define('SEND_DELAY_SECONDS', 1);
