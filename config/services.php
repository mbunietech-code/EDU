<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Firebase Cloud Messaging — push notifications for the MHub app.
    | credentials: path to the service-account JSON (Project settings ->
    | Service accounts -> Generate new private key). project_id is read from
    | that file when not set here.
    */
    'fcm' => [
        'enabled' => env('FCM_ENABLED', true),
        'credentials' => env('FCM_CREDENTIALS', storage_path('app/firebase/service-account.json')),
        'project_id' => env('FCM_PROJECT_ID'),
    ],

    /*
    | Mbunie VPN control plane (vpn.mbuniehub.com). partner_secret must equal
    | EDUHUB_PARTNER_SECRET on the VPN side; it signs every partner API call.
    */
    'mvpn' => [
        'url' => rtrim((string) env('MVPN_URL', 'https://vpn.mbuniehub.com'), '/'),
        'partner_secret' => env('MVPN_PARTNER_SECRET'),
        'timeout' => (int) env('MVPN_TIMEOUT', 20),
        'download_android' => env('MVPN_DOWNLOAD_ANDROID', 'https://vpn.mbuniehub.com/storage/downloads/Mbunie-VPN-1.0.6.apk'),
        'download_windows' => env('MVPN_DOWNLOAD_WINDOWS', 'https://vpn.mbuniehub.com/storage/downloads/Mbunie-VPN-Setup-1.0.6.exe'),
    ],

];
