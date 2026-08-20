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

        // iCard's own hosted JS: modal_js_url renders the embedded card
        // payment overlay given a token from IPGPaymentToken (see
        // ICardPaymentGateway::createModalSession); wallet_js_url is the
        // separate Apple Pay/Google Pay SDK (ICardIpgGAPay).
        'modal_js_url' => env('ICARD_MODAL_JS_URL', 'https://dev-ipg.icards.eu/sandbox/js/payment-modal.js'),
        'wallet_js_url' => env('ICARD_WALLET_JS_URL', 'https://dev-ipg.icards.eu/sandbox/js/icard-g-a-pay.min.js'),

        'private_key_path' => env('ICARD_PRIVATE_KEY_PATH', storage_path('icard/private_key.pem')),
        'public_key_path' => env('ICARD_PUBLIC_KEY_PATH', storage_path('icard/public_key.pem')),

        'webhook_url' => env('ICARD_WEBHOOK_URL'),
        'callback_ips' => env('ICARD_CALLBACK_IPS', ''),

        // Provider-specific wallet toggles — distinct from the app-level
        // apple_pay.enabled/google_pay.enabled below. Both must be true for
        // a wallet method to actually show up (see
        // PaymentService::availablePaymentMethods): this one specifically
        // means "iCard's own merchant configuration has the wallet turned
        // on", which the app-level flag can't know on its own. See
        // docs/wallet-payments.md.
        'apple_pay_enabled' => (bool) env('ICARD_APPLE_PAY_ENABLED', false),
        'google_pay_enabled' => (bool) env('ICARD_GOOGLE_PAY_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet payment methods (Apple Pay / Google Pay)
    |--------------------------------------------------------------------------
    |
    | App-level master switches — see docs/wallet-payments.md. Effective
    | availability is this flag AND the matching services.icard.*_enabled
    | flag (PaymentService::availablePaymentMethods), so a wallet method
    | can be disabled from either side independently. Both default to
    | false: enabling either wallet in production requires real iCard
    | merchant wallet configuration, and Apple Pay additionally requires a
    | registered Apple Merchant ID and a domain-verification file hosted on
    | a real HTTPS domain — neither exists in this project yet.
    |
    */

    'apple_pay' => [
        'enabled' => (bool) env('APPLE_PAY_ENABLED', false),

        // Apple's own domain-verification/merchant setup — placeholders
        // only; see docs/wallet-payments.md for what real values require.
        'merchant_id' => env('APPLE_PAY_MERCHANT_ID'),
        'merchant_domain' => env('APPLE_PAY_MERCHANT_DOMAIN'),
    ],

    'google_pay' => [
        'enabled' => (bool) env('GOOGLE_PAY_ENABLED', false),

        // 'TEST' renders Google's test-mode button/flow; 'PRODUCTION'
        // requires a Google Pay Business Console merchant ID paired with
        // iCard as the payment gateway — see docs/wallet-payments.md.
        'environment' => env('GOOGLE_PAY_ENVIRONMENT', 'TEST'),
        'merchant_id' => env('GOOGLE_PAY_MERCHANT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping providers (Speedy, BOX NOW)
    |--------------------------------------------------------------------------
    |
    | Speedy is still sandbox/demo configuration — it falls back to a flat
    | rate when its API is unreachable, so checkout keeps working without
    | real credentials; only shipment creation/tracking require them to
    | actually succeed. BOX NOW is configured against its real production
    | API (see App\Services\Shipping\BoxNowShippingProvider).
    |
    */

    'shipping' => [
        'speedy' => [
            'base_url' => env('SPEEDY_BASE_URL', 'https://api.speedy.bg/v1/'),
            'username' => env('SPEEDY_USERNAME'),
            'password' => env('SPEEDY_PASSWORD'),
        ],
        'box_now' => [
            'base_url' => env('BOX_NOW_BASE_URL', 'https://api-production.boxnow.bg/api/v1/'),
            'client_id' => env('BOX_NOW_CLIENT_ID'),
            'client_secret' => env('BOX_NOW_CLIENT_SECRET'),
            // This storefront has no BOX NOW-registered warehouse — orders
            // are fulfilled by dropping the parcel off at a locker in
            // person, and "any-apm" is BOX NOW's own documented origin
            // locationId for exactly that flow (see their partner API
            // guide, section 4.4). Override via .env if a real warehouse
            // location is registered with BOX NOW later.
            'origin_location_id' => env('BOX_NOW_ORIGIN_LOCATION_ID', 'any-apm'),
        ],
    ],

];
