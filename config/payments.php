<?php

return [

    /*
    | Secret part of the gateway callback URLs. Set a long random value and use
    | it when registering callback URLs on the AzamPay / ClickPesa dashboards:
    |   https://YOUR-DOMAIN/api/payments/callback/azampay/{PAYMENT_CALLBACK_TOKEN}
    |   https://YOUR-DOMAIN/api/payments/callback/clickpesa/{PAYMENT_CALLBACK_TOKEN}
    */
    'callback_token' => env('PAYMENT_CALLBACK_TOKEN'),

    // A push with no answer after this many minutes is shown as expired.
    'pending_timeout_minutes' => (int) env('PAYMENT_PENDING_TIMEOUT', 10),

    'gateways' => [

        'azampay' => [
            'label' => 'AzamPay',
            'enabled' => (bool) env('AZAMPAY_ENABLED', false),
            'environment' => env('AZAMPAY_ENV', 'sandbox'), // sandbox | live
            'app_name' => env('AZAMPAY_APP_NAME'),
            'client_id' => env('AZAMPAY_CLIENT_ID'),
            'client_secret' => env('AZAMPAY_CLIENT_SECRET'),
            'api_key' => env('AZAMPAY_API_KEY'),
            'urls' => [
                'sandbox' => [
                    'auth' => 'https://authenticator-sandbox.azampay.co.tz',
                    'checkout' => 'https://sandbox.azampay.co.tz',
                ],
                'live' => [
                    'auth' => 'https://authenticator.azampay.co.tz',
                    'checkout' => 'https://checkout.azampay.co.tz',
                ],
            ],
            // Network shown to the customer => AzamPay "provider" value.
            'networks' => [
                'Mpesa' => 'Vodacom M-Pesa',
                'Tigo' => 'Mixx by Yas (Tigo Pesa)',
                'Airtel' => 'Airtel Money',
                'Halopesa' => 'Halopesa',
                'Azampesa' => 'AzamPesa',
            ],
        ],

        'clickpesa' => [
            'label' => 'ClickPesa',
            'enabled' => (bool) env('CLICKPESA_ENABLED', false),
            'base_url' => env('CLICKPESA_BASE_URL', 'https://api.clickpesa.com/third-parties'),
            'client_id' => env('CLICKPESA_CLIENT_ID'),
            'api_key' => env('CLICKPESA_API_KEY'),
        ],

    ],

];
