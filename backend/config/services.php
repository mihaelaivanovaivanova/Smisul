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
    | iCard Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Placeholder configuration for the hosted-payment-page integration
    | (see App\Services\Payments\ICardPaymentGateway). No real credentials
    | exist yet — merchant_id/secret must be supplied before this can talk
    | to iCard's actual sandbox or production environment.
    |
    */

    'icard' => [
        'merchant_id' => env('ICARD_MERCHANT_ID'),
        'secret' => env('ICARD_SECRET_KEY'),
        'environment' => env('ICARD_ENVIRONMENT', 'sandbox'),
        'base_url' => env('ICARD_BASE_URL', 'https://sandbox.icard.example/api'),
        'return_url' => env('ICARD_RETURN_URL'),
        'cancel_url' => env('ICARD_CANCEL_URL'),
        'webhook_url' => env('ICARD_WEBHOOK_URL'),
    ],

];
