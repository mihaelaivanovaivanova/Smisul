<?php

// Copy this file manually on the testing server to:
// backend/install-config.staging.php
// Never commit or upload the filled-in file.
return [
    'environment' => 'staging',
    'enabled' => true,

    // Generate at least 32 random characters. Enter this once in the browser.
    'install_token' => 'CHANGE_THIS_TO_A_LONG_RANDOM_STAGING_INSTALL_TOKEN',

    'app' => [
        'name' => 'Smisul Testing',
        'url' => 'https://sandboxandpayments.smisul.bg',
        'locale' => 'bg',
        'timezone' => 'Europe/Sofia',
        'session_cookie' => 'SMISUL_TESTING_SESSION',
    ],

    // Create this empty database and its dedicated user in cPanel first.
    // The database name must contain test, testing, sandbox, or staging.
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'cpanel_smisul_sandbox',
        'username' => 'cpanel_sandbox_user',
        'password' => 'CHANGE_TO_THE_SANDBOX_DATABASE_PASSWORD',
    ],

    'admin' => [
        'email' => 'sandbox-admin@example.com',
        'password' => 'CHANGE_TO_A_UNIQUE_LONG_SANDBOX_ADMIN_PASSWORD',
    ],

    // Keep mail in log mode until non-production SMTP is configured.
    'mail' => [
        'mailer' => 'log',
        'scheme' => null,
        'host' => '127.0.0.1',
        'port' => 2525,
        'username' => null,
        'password' => null,
        'from_address' => 'sandbox@example.com',
        'from_name' => 'Smisul Testing',
    ],
];
