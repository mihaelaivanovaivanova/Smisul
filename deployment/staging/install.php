<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Cache-Control: no-store, max-age=0');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

const STAGING_HOST = 'sandboxandpayments.smisul.bg';

$requestHost = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
$backend = dirname(__DIR__).'/backend';
$configFile = $backend.'/install-config.staging.php';
$lockFile = $backend.'/storage/app/smisul-staging-installed.lock';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function envValue(mixed $value): string
{
    if (is_bool($value)) return $value ? 'true' : 'false';
    if ($value === null) return 'null';
    $value = str_replace(["\\", '"', '$', "\r", "\n"], ["\\\\", '\\"', '\\$', '', '\\n'], (string) $value);
    return '"'.$value.'"';
}

function render(array $messages, array $errors, bool $showForm = false): never
{
    http_response_code($errors === [] ? 200 : 400);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Smisul sandbox installation</title><style>body{font:16px/1.55 system-ui,sans-serif;background:#f4f6f3;color:#1d2a21;margin:0;padding:32px}.card{max-width:760px;margin:auto;background:#fff;padding:28px;border-radius:14px;box-shadow:0 8px 30px #0001}.ok{color:#176b37}.err{color:#a12727}input{box-sizing:border-box;width:100%;padding:12px;margin:6px 0 16px;border:1px solid #abb8ae;border-radius:8px}button{padding:12px 20px;border:0;border-radius:8px;background:#1d603c;color:#fff;font-weight:700}</style></head><body><main class="card"><h1>Smisul sandbox installation</h1>';
    if ($messages !== []) {
        echo '<ul class="ok">';
        foreach ($messages as $message) echo '<li>'.h((string) $message).'</li>';
        echo '</ul>';
    }
    if ($errors !== []) {
        echo '<ul class="err">';
        foreach ($errors as $error) echo '<li>'.h((string) $error).'</li>';
        echo '</ul>';
    }
    if ($showForm) {
        echo '<p>This one-time installer only accepts a new, empty sandbox database.</p><form method="post" autocomplete="off"><label for="token">Installation token</label><input id="token" name="token" type="password" required autofocus><button type="submit">Install sandbox</button></form>';
    }
    echo '</main></body></html>';
    exit;
}

if (! hash_equals(STAGING_HOST, $requestHost)) {
    render([], ['This installer is restricted to the sandbox hostname.']);
}
if (is_file($lockFile) || is_file($backend.'/.env')) {
    render([], ['The sandbox is already installed. Remove install.php and use manual, testing-only migrations for later updates.']);
}
if (! is_file($configFile)) {
    render([], ['Missing private backend/install-config.staging.php. Create it manually from the staging example.']);
}

/** @var array<string, mixed> $config */
$config = require $configFile;
if (($config['enabled'] ?? false) !== true || ($config['environment'] ?? '') !== 'staging') {
    render([], ['The private install config is not explicitly enabled for staging.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') render([], [], true);

$expectedToken = (string) ($config['install_token'] ?? '');
$suppliedToken = (string) ($_POST['token'] ?? '');
if (strlen($expectedToken) < 32 || str_starts_with($expectedToken, 'CHANGE_')) {
    render([], ['Set a unique install token of at least 32 characters in the private staging config.'], true);
}
if (! hash_equals($expectedToken, $suppliedToken)) render([], ['Invalid installation token.'], true);

$errors = [];
if (version_compare(PHP_VERSION, '8.2.0', '<')) $errors[] = 'PHP 8.2 or newer is required.';
foreach (['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'mbstring', 'openssl', 'pdo_mysql', 'session', 'tokenizer', 'xml'] as $extension) {
    if (! extension_loaded($extension)) $errors[] = 'Missing PHP extension: '.$extension;
}

$app = $config['app'] ?? [];
$db = $config['database'] ?? [];
$admin = $config['admin'] ?? [];
$mail = $config['mail'] ?? [];
$url = rtrim((string) ($app['url'] ?? ''), '/');
$dbName = (string) ($db['name'] ?? '');
$required = [
    'database host' => $db['host'] ?? null,
    'database name' => $dbName,
    'database username' => $db['username'] ?? null,
    'database password' => $db['password'] ?? null,
    'administrator email' => $admin['email'] ?? null,
    'administrator password' => $admin['password'] ?? null,
];
foreach ($required as $name => $value) {
    if (! is_string($value) || trim($value) === '' || preg_match('/CHANGE_|CPANEL_|YOUR_|example\.com/i', $value)) $errors[] = 'Replace the placeholder for '.$name.'.';
}
if ($url !== 'https://'.STAGING_HOST) $errors[] = 'The application URL must be exactly https://'.STAGING_HOST.'.';
if (! preg_match('/(test|testing|sandbox|staging)/i', $dbName)) $errors[] = 'The database name must visibly identify it as testing or sandbox.';
if (! filter_var((string) ($admin['email'] ?? ''), FILTER_VALIDATE_EMAIL)) $errors[] = 'The administrator email is invalid.';
if (strlen((string) ($admin['password'] ?? '')) < 14) $errors[] = 'The sandbox administrator password must be at least 14 characters.';
if (! is_dir($backend) || ! is_writable($backend)) $errors[] = 'The sibling backend directory is missing or not writable.';
$autoload = $backend.'/vendor/autoload.php';
if (! is_file($autoload)) $errors[] = 'backend/vendor/autoload.php is missing. Complete the FTPS deployment first.';
if ($errors !== []) render([], $errors);

try {
    $pdo = new PDO(
        'mysql:host='.$db['host'].';port='.(int) ($db['port'] ?? 3306).';dbname='.$dbName.';charset=utf8mb4',
        (string) $db['username'],
        (string) $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :database');
    $statement->execute(['database' => $dbName]);
    if ((int) $statement->fetchColumn() !== 0) throw new RuntimeException('The selected sandbox database is not empty.');
} catch (Throwable $exception) {
    render([], ['Sandbox database validation failed: '.$exception->getMessage()]);
}

$host = STAGING_HOST;
$appKey = 'base64:'.base64_encode(random_bytes(32));
$env = [
    'APP_NAME='.envValue($app['name'] ?? 'Smisul Testing'),
    'APP_ENV=staging',
    'APP_KEY='.envValue($appKey),
    'APP_DEBUG=false',
    'APP_URL='.envValue($url),
    'APP_LOCALE='.envValue($app['locale'] ?? 'bg'),
    'APP_FALLBACK_LOCALE=bg',
    'APP_TIMEZONE='.envValue($app['timezone'] ?? 'Europe/Sofia'),
    'LOG_CHANNEL=stack',
    'LOG_STACK=single',
    'LOG_LEVEL=warning',
    'DB_CONNECTION=mysql',
    'DB_HOST='.envValue($db['host']),
    'DB_PORT='.(int) ($db['port'] ?? 3306),
    'DB_DATABASE='.envValue($dbName),
    'DB_USERNAME='.envValue($db['username']),
    'DB_PASSWORD='.envValue($db['password']),
    'DB_CHARSET=utf8mb4',
    'DB_COLLATION=utf8mb4_unicode_ci',
    'SESSION_DRIVER=database',
    'SESSION_LIFETIME=120',
    'SESSION_ENCRYPT=true',
    'SESSION_COOKIE='.envValue($app['session_cookie'] ?? 'SMISUL_TESTING_SESSION'),
    'SESSION_PATH=/',
    'SESSION_DOMAIN=null',
    'SESSION_SECURE_COOKIE=true',
    'SESSION_SAME_SITE=lax',
    'CACHE_STORE=database',
    'QUEUE_CONNECTION=database',
    'FILESYSTEM_DISK=public',
    'FRONTEND_URL='.envValue($url),
    'FRONTEND_URLS='.envValue($url),
    'SANCTUM_STATEFUL_DOMAINS='.envValue($host),
    'ADMIN_EMAIL='.envValue($admin['email']),
    'ADMIN_PASSWORD='.envValue($admin['password']),
    'CONTACT_EMAIL='.envValue($mail['from_address'] ?? $admin['email']),
    'MAIL_MAILER='.envValue($mail['mailer'] ?? 'log'),
    'MAIL_SCHEME='.envValue($mail['scheme'] ?? null),
    'MAIL_HOST='.envValue($mail['host'] ?? '127.0.0.1'),
    'MAIL_PORT='.(int) ($mail['port'] ?? 2525),
    'MAIL_USERNAME='.envValue($mail['username'] ?? null),
    'MAIL_PASSWORD='.envValue($mail['password'] ?? null),
    'MAIL_FROM_ADDRESS='.envValue($mail['from_address'] ?? $admin['email']),
    'MAIL_FROM_NAME='.envValue($mail['from_name'] ?? $app['name'] ?? 'Smisul Testing'),
    'ICARD_ENVIRONMENT=sandbox',
    'ICARD_MID=null',
    'ICARD_ORIGINATOR=null',
];

if (file_put_contents($backend.'/.env', implode(PHP_EOL, $env).PHP_EOL, LOCK_EX) === false) {
    render([], ['Could not create backend/.env. Check server permissions.']);
}
@chmod($backend.'/.env', 0600);

$messages = ['Created an isolated staging environment file.'];
try {
    // These empty runtime directories are not part of the deployment package.
    // They must exist before Laravel boots on a freshly emptied hosting account.
    foreach ([$backend.'/storage/app/public', $backend.'/storage/app/private', $backend.'/storage/logs', $backend.'/storage/framework/cache/data', $backend.'/storage/framework/sessions', $backend.'/storage/framework/views'] as $directory) {
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) throw new RuntimeException('Could not create a required runtime directory.');
    }

    require $autoload;
    /** @var IlluminateFoundationApplication $laravel */
    $laravel = require $backend.'/bootstrap/app.php';
    /** @var IlluminateContractsConsoleKernel $kernel */
    $kernel = $laravel->make(IlluminateContractsConsoleKernel::class);
    $commands = [
        ['migrate', ['--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\AdminSeeder::class, '--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\SettingsSeeder::class, '--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\ContentBlockSeeder::class, '--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\CategorySeeder::class, '--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\ProductSeeder::class, '--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\PromotionSeeder::class, '--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\LegalDocumentSeeder::class, '--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\FunnelSeeder::class, '--force' => true]],
        ['config:cache', []],
    ];
    foreach ($commands as [$command, $arguments]) {
        $code = $kernel->call($command, $arguments);
        if ($code !== 0) throw new RuntimeException($kernel->output());
    }

    $link = __DIR__.'/storage';
    $target = $backend.'/storage/app/public';
    if (! file_exists($link) && ! @symlink($target, $link)) $messages[] = 'Create root/storage as a cPanel symlink to backend/storage/app/public.';

    if (file_put_contents($lockFile, date(DATE_ATOM).PHP_EOL, LOCK_EX) === false) throw new RuntimeException('Could not create the staging installation lock.');
    @chmod($lockFile, 0600);
    $messages[] = 'Created the complete application schema and safe initial testing data.';
    $messages[] = 'Configure only iCard sandbox credentials through the testing admin panel.';

    if (@unlink($configFile)) $messages[] = 'Removed the temporary private install configuration.';
    if (@unlink(__FILE__)) $messages[] = 'Removed the one-time public installer.';
} catch (Throwable $exception) {
    render($messages, ['Installation stopped: '.$exception->getMessage(), 'Delete the partial testing database and backend/.env before retrying. Never use the production database.']);
}

render($messages, []);
