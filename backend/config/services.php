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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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
    |--------------------------------------------------------------------------
    | iCard Payment Gateway (IPG API, Redirect Checkout / IPGPurchase)
    |--------------------------------------------------------------------------
    |
    | Matches iCard's real IPG API (protocol v4.5): requests are RSA-signed
    | and submitted to IPG as a browser form POST, not a simple redirect
    | URL — see App\Services\Payments\ICardPaymentGateway. Key files are
    | never committed (see backend/.gitignore) — supply real paths via env.
    |
    */

    'icard' => [
        'mid' => env('ICARD_MID'),
        'mid_name' => env('ICARD_MID_NAME', 'Smisul'),
        'originator' => env('ICARD_ORIGINATOR'),
        'key_index' => env('ICARD_KEY_INDEX'),
        'key_index_resp' => env('ICARD_KEY_INDEX_RESP'),
        'ipg_version' => env('ICARD_IPG_VERSION', '4.5'),

        // ISO 4217 numeric currency code for the MID's fixed settlement
        // currency (978 = EUR) — IPG rejects a currency that doesn't match
        // the MID, so this isn't derived per-payment.
        'currency_numeric' => env('ICARD_CURRENCY_NUMERIC', '978'),

        'environment' => env('ICARD_ENVIRONMENT', 'sandbox'),
        'base_url' => env('ICARD_BASE_URL', 'https://dev-ipg.icards.eu/sandbox/'),

        'private_key_path' => env('ICARD_PRIVATE_KEY_PATH', storage_path('icard/private_key.pem')),
        'public_key_path' => env('ICARD_PUBLIC_KEY_PATH', storage_path('icard/public_key.pem')),

        'return_url' => env('ICARD_RETURN_URL'),
        'cancel_url' => env('ICARD_CANCEL_URL'),
        'webhook_url' => env('ICARD_WEBHOOK_URL'),
    ],

];
