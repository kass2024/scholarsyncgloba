<?php
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/helpers/env_load.php';

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;

$credentialsPath = xander_env_get('FCM_SERVICE_ACCOUNT_JSON');
$projectId = xander_env_get('FCM_PROJECT_ID');
if ($credentialsPath === '' || $projectId === '' || !is_readable($credentialsPath)) {
    http_response_code(503);
    exit('Push notifications are not configured.');
}

$credentials = ServiceAccountCredentials::fromJsonFile(
    $credentialsPath,
    ['https://www.googleapis.com/auth/firebase.messaging']
);

// Get OAuth token
$accessToken = $credentials->fetchAuthToken()['access_token'];

// POST params from PHP
$deviceToken = $_POST['device_token'] ?? '';
$userId = $_POST['user_id'] ?? 'unknown';

if (empty($deviceToken)) {
    die('Error: device_token is required!');
}

// Build FCM payload
$payload = [
    'message' => [
        'token' => $deviceToken,
        'notification' => [
            'title' => 'New Message',
            'body'  => 'Student sent a message',
        ],
        'android' => [
            'priority' => 'HIGH',
            'notification' => [
                'sound' => 'notify'  // plays /res/raw/notify.mp3
            ]
        ],
        'data' => [
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'user_id' => $userId,
            'type' => 'chat_message'
        ]
    ]
];

// Send request
$client = new Client();

try {
    $response = $client->post(
        "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json; UTF-8',
            ],
            'json' => $payload
        ]
    );

    echo "✅ Push sent: " . $response->getBody();

} catch (Exception $e) {
    echo "❌ Error sending push: " . $e->getMessage();
}
