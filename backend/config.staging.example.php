<?php

// Safe structural reference only. Create Backend/.env manually on the
// testing server using backend/.env.example; never create credentials here.
return [
    'app' => [
        'name' => 'Smisul Testing',
        'url' => 'https://sandboxandpayments.smisul.bg',
        'locale' => 'bg',
        'timezone' => 'Europe/Sofia',
        'session_name' => 'SMISUL_TESTING_SESSION',
        'cookie_domain' => null,
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'your_separate_testing_database',
        'username' => 'your_separate_testing_database_user',
        'password' => 'your_testing_database_password',
        'create_database' => false,
    ],
    'admin' => [
        'email' => 'testing-admin@example.com',
        'password' => 'choose_a_unique_testing_admin_password',
    ],
    'mail' => [
        'mailer' => 'log',
        'scheme' => null,
        'host' => 'smtp.testing.example.com',
        'port' => 25,
        'username' => 'testing-mail@example.com',
        'password' => 'your_non_production_mail_password',
        'from_address' => 'testing-mail@example.com',
        'from_name' => 'Smisul Testing',
    ],
    'payments' => [
        'icard' => 'Configure sandbox only through the testing admin panel.',
    ],
];
