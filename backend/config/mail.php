<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        // Registered via Mail::extend() in AppServiceProvider::boot() -
        // Laravel has no built-in Brevo transport the way it does for ses/
        // postmark/resend/mailgun, so this driver name only resolves once
        // that extend() call has run.
        'brevo' => [
            'transport' => 'brevo',
            'key' => env('BREVO_API_KEY'),
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Promotional "From" Address
    |--------------------------------------------------------------------------
    |
    | A separate sender identity for every mailable that carries a
    | List-Unsubscribe header (see HasUnsubscribeLink) - the admin "Send
    | broadcast" bulk button (BroadcastMail) and the 30-day order reminder
    | (OrderThirtyDayReminderMail) - deliberately NOT the same address as
    | order confirmations/review confirmations above, which never carry one.
    | Gmail's Promotions-tab classifier weighs sender-level reputation
    | (aggregated across every recipient of that address) far more than any
    | one email's own content, so bulk/opt-outable mail sharing an address
    | with purely transactional mail drags the latter into Promotions too.
    | Splitting them keeps that reputation from bleeding across the two -
    | the reminder and the broadcast sharing this address with each other is
    | fine, since both are already opt-outable/promotional-adjacent by
    | nature (unlike order confirmations).
    |
    */

    'promotional_from' => [
        'address' => env('MAIL_PROMOTIONAL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
        'name' => env('MAIL_PROMOTIONAL_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel'))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Administrator Notification Address
    |--------------------------------------------------------------------------
    |
    | Where order-placed notifications are sent. Reuses the same account
    | seeded as the sole administrator (see AdminSeeder) — there is no
    | separate "store notifications" mailbox concept yet.
    |
    */

    'admin_address' => env('ADMIN_EMAIL'),

    'order_notification_addresses' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('ORDER_NOTIFICATION_EMAILS', 'admin@smisul.bg,filchevweb@gmail.com,mihaela.ivanova.ivanova@gmail.com')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Contact Form Address
    |--------------------------------------------------------------------------
    |
    | Where the public contact form (frontend footer) delivers submissions.
    | Defaults to the real published contact mailbox rather than null, since
    | this form has no purpose if nothing is configured to receive it.
    |
    */

    'contact_address' => env('CONTACT_EMAIL', 'contact@smisul.bg'),

];
