<?php

// This file is copied to /smisul.bg/backend/install-config.php,
// outside the public /smisul.bg/root directory.
// Fill it in before opening https://your-domain.bg/install.php.
return [
    'enabled' => true,

    // Use at least 32 random characters. It is requested by the installer page.
    'install_token' => 'CHANGE_THIS_TO_A_LONG_RANDOM_INSTALL_TOKEN',

    'app' => [
        'name' => 'Smisul',
        'url' => 'https://YOUR-DOMAIN.BG',
        'locale' => 'bg',
        'timezone' => 'Europe/Sofia',
    ],

    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'CPANEL_DATABASE',
        'username' => 'CPANEL_DB_USER',
        'password' => 'CHANGE_ME',
        // Usually leave false: create the database in cPanel first.
        'create_database' => false,
    ],

    'admin' => [
        'email' => 'admin@YOUR-DOMAIN.BG',
        'password' => 'CHANGE_TO_A_LONG_RANDOM_PASSWORD',
    ],

    'mail' => [
        // Use "log" until SMTP credentials are available, then change to "smtp".
        'mailer' => 'log',
        'scheme' => null,
        'host' => '127.0.0.1',
        'port' => 587,
        'username' => null,
        'password' => null,
        'from_address' => 'contact@YOUR-DOMAIN.BG',
        'from_name' => 'Smisul',
    ],

    // Used only if vendor/autoload.php is missing and PHP permits exec().
    'composer_binary' => 'composer',
];
