<?php

// Safe structural reference only. This Laravel application loads server
// settings from backend/.env; do not rename this file to config.php.
return [
    'app' => [
        'name' => 'Smisul',
        'url' => 'https://example.com',
        'locale' => 'bg',
        'timezone' => 'Europe/Sofia',
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'your_database_name',
        'username' => 'your_database_username',
        'password' => 'your_database_password',
        'create_database' => false,
    ],
    'admin' => [
        'email' => 'admin@example.com',
        'password' => 'change_this_password',
    ],
    'mail' => [
        'mailer' => 'log',
        'scheme' => null,
        'host' => 'smtp.example.com',
        'port' => 25,
        'username' => 'mail@example.com',
        'password' => 'your_mail_password',
        'from_address' => 'mail@example.com',
        'from_name' => 'Smisul',
    ],
];
