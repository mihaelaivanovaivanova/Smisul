<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store, max-age=0');

$backend = dirname(__DIR__).'/backend';
$configFile = $backend.'/install-config.php';
$lockFile = $backend.'/storage/app/smisul-installed.lock';
$isUpgrade = is_file($lockFile);
$messages = [];
$errors = [];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function envValue(mixed $value): string
{
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if ($value === null) {
        return 'null';
    }

    $value = (string) $value;
    $value = str_replace(["\\", '"', '$', "\r", "\n"], ["\\\\", '\\"', '\\$', '', '\\n'], $value);

    return '"'.$value.'"';
}

function setting(array $config, string $section, string $key): mixed
{
    return $config[$section][$key] ?? null;
}

function importIcardProfiles(mixed $app, string $backend, array &$messages): void
{
    $importFile = $backend.'/storage/app/private/icard-import.php';
    if (! is_file($importFile)) return;

    /** @var array{active_environment?: string, profiles?: array<string, array<string, mixed>>} $payload */
    $payload = require $importFile;
    $profiles = $payload['profiles'] ?? [];
    if (! is_array($profiles) || $profiles === []) {
        throw new RuntimeException('Private iCard import file does not contain profiles.');
    }

    /** @var App\Services\Payments\ICardConfigurationService $service */
    $service = $app->make(App\Services\Payments\ICardConfigurationService::class);
    foreach ($profiles as $environment => $values) {
        if (! in_array($environment, ['sandbox', 'production'], true) || ! is_array($values)) continue;
        $service->update($environment, $values);
    }

    $active = ($payload['active_environment'] ?? 'sandbox') === 'production' && isset($profiles['production'])
        ? 'production'
        : 'sandbox';
    $service->activate($active);

    if (! @unlink($importFile)) {
        throw new RuntimeException('iCard profile was imported, but the temporary private import file could not be deleted.');
    }

    $messages[] = 'Работещият iCard профил е импортиран криптирано и временният secret файл е изтрит.';
}

function render(array $messages, array $errors, bool $showForm): never
{
    $status = $errors === [] && ! $showForm ? 'Готово' : 'Инсталация на Smisul';
    echo '<!doctype html><html lang="bg"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>'.h($status).'</title><style>body{font:16px/1.55 system-ui,sans-serif;background:#f4f6f3;color:#1d2a21;margin:0;padding:32px}.card{max-width:720px;margin:auto;background:#fff;padding:28px;border-radius:14px;box-shadow:0 8px 30px #0001}h1{margin-top:0}.ok{color:#176b37}.err{color:#a12727}li{margin:.5rem 0}input{box-sizing:border-box;width:100%;padding:12px;margin:6px 0 16px;border:1px solid #abb8ae;border-radius:8px}button{padding:12px 20px;border:0;border-radius:8px;background:#1d603c;color:white;font-weight:700;cursor:pointer}code{background:#edf1ed;padding:2px 5px;border-radius:4px}</style></head><body><main class="card"><h1>Инсталация на Smisul</h1>';
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
        echo '<p>Въведете <code>install_token</code> от <code>backend/install-config.php</code>.</p><form method="post" autocomplete="off"><label for="token">Инсталационен ключ</label><input id="token" name="token" type="password" required autofocus><button type="submit">Инсталирай сайта</button></form>';
    }
    echo '</main></body></html>';
    exit;
}

if (! is_file($configFile)) {
    render([], ['Липсва backend/install-config.php. Копирайте backend/install-config.example.php като install-config.php и го попълнете.'], false);
}

/** @var array<string, mixed> $config */
$config = require $configFile;
if (($config['enabled'] ?? false) !== true) {
    render([], ['Инсталаторът е изключен в install-config.php.'], false);
}

if ($isUpgrade) {
    $messages[] = 'Открита е съществуваща инсталация. Ще бъдат приложени само новите database migrations и Laravel cache ще бъде обновен.';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    render($messages, [], true);
}

$expectedToken = (string) ($config['install_token'] ?? '');
$suppliedToken = (string) ($_POST['token'] ?? '');
if (strlen($expectedToken) < 32 || str_starts_with($expectedToken, 'CHANGE_')) {
    render([], ['Задайте собствен install_token с поне 32 знака в install-config.php.'], true);
}
if (! hash_equals($expectedToken, $suppliedToken)) {
    render([], ['Невалиден инсталационен ключ.'], true);
}

if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    $errors[] = 'Необходим е PHP 8.2 или по-нов. Текуща версия: '.PHP_VERSION;
}
foreach (['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'mbstring', 'openssl', 'pdo_mysql', 'session', 'tokenizer', 'xml'] as $extension) {
    if (! extension_loaded($extension)) $errors[] = 'Липсва PHP разширението: '.$extension;
}

if ($isUpgrade) {
    if ($errors !== []) render($messages, $errors, false);

    $autoload = $backend.'/vendor/autoload.php';
    if (! is_file($autoload)) {
        render($messages, ['Липсва backend/vendor/autoload.php. Качете пълната backend папка преди обновяването.'], false);
    }

    try {
        require $autoload;
        /** @var Illuminate\Foundation\Application $app */
        $app = require $backend.'/bootstrap/app.php';
        /** @var Illuminate\Contracts\Console\Kernel $kernel */
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

        $code = $kernel->call('migrate', ['--force' => true]);
        if ($code !== 0) throw new RuntimeException($kernel->output());
        importIcardProfiles($app, $backend, $messages);

        foreach ([['optimize:clear', []], ['config:cache', []]] as [$command, $arguments]) {
            $code = $kernel->call($command, $arguments);
            if ($code !== 0) throw new RuntimeException($kernel->output());
        }

        $messages[] = 'Базата е обновена успешно. Новите iCard таблици и колони са инсталирани.';
        if (@unlink(__FILE__)) $messages[] = 'install.php беше автоматично изтрит.';
    } catch (Throwable $e) {
        $errors[] = 'Laravel обновяването прекъсна: '.$e->getMessage();
        $errors[] = 'През SSH може да изпълните същото от smisul.bg/backend с: php artisan migrate --force';
    }

    render($messages, $errors, false);
}

$required = [
    'app.url' => setting($config, 'app', 'url'),
    'database.host' => setting($config, 'database', 'host'),
    'database.name' => setting($config, 'database', 'name'),
    'database.username' => setting($config, 'database', 'username'),
    'admin.email' => setting($config, 'admin', 'email'),
    'admin.password' => setting($config, 'admin', 'password'),
];
foreach ($required as $name => $value) {
    if (! is_string($value) || trim($value) === '' || preg_match('/(CHANGE_|YOUR-|CPANEL_)/', $value)) {
        $errors[] = 'Попълнете реална стойност за '.$name.' в install-config.php.';
    }
}
if (! filter_var((string) setting($config, 'app', 'url'), FILTER_VALIDATE_URL)) $errors[] = 'app.url не е валиден URL.';
if (! filter_var((string) setting($config, 'admin', 'email'), FILTER_VALIDATE_EMAIL)) $errors[] = 'admin.email не е валиден имейл.';
if (strlen((string) setting($config, 'admin', 'password')) < 12) $errors[] = 'Паролата на администратора трябва да е поне 12 знака.';
if (! is_dir($backend) || ! is_writable($backend)) $errors[] = 'Папката backend не съществува или PHP няма право да записва в нея.';

if ($errors !== []) render($messages, $errors, false);

$db = $config['database'];
try {
    $dbName = (string) $db['name'];
    if (($db['create_database'] ?? false) === true) {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $dbName)) throw new RuntimeException('Името на базата може да съдържа само букви, цифри и долна черта.');
        $pdo = new PDO('mysql:host='.$db['host'].';port='.(int) $db['port'].';charset=utf8mb4', (string) $db['username'], (string) $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$dbName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $messages[] = 'MySQL базата е създадена или вече съществува.';
    }
    $pdo = new PDO('mysql:host='.$db['host'].';port='.(int) $db['port'].';dbname='.$dbName.';charset=utf8mb4', (string) $db['username'], (string) $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->query('SELECT 1');
    $messages[] = 'Връзката с MySQL е успешна.';
} catch (Throwable $e) {
    render($messages, ['Неуспешна връзка с MySQL: '.$e->getMessage()], false);
}

$url = rtrim((string) setting($config, 'app', 'url'), '/');
$host = (string) parse_url($url, PHP_URL_HOST);
$mail = $config['mail'] ?? [];
$appKey = 'base64:'.base64_encode(random_bytes(32));
if (is_file($backend.'/.env')) {
    $oldEnv = (string) file_get_contents($backend.'/.env');
    if (preg_match('/^APP_KEY=["\']?([^"\'\r\n]+)["\']?$/m', $oldEnv, $match)) $appKey = $match[1];
}
$env = [
    'APP_NAME='.envValue(setting($config, 'app', 'name') ?: 'Smisul'),
    'APP_ENV=production',
    'APP_KEY='.envValue($appKey),
    'APP_DEBUG=false',
    'APP_URL='.envValue($url),
    'APP_LOCALE='.envValue(setting($config, 'app', 'locale') ?: 'bg'),
    'APP_FALLBACK_LOCALE=bg',
    'APP_TIMEZONE='.envValue(setting($config, 'app', 'timezone') ?: 'Europe/Sofia'),
    'LOG_CHANNEL=stack',
    'LOG_STACK=single',
    'LOG_LEVEL=warning',
    'DB_CONNECTION=mysql',
    'DB_HOST='.envValue($db['host']),
    'DB_PORT='.(int) $db['port'],
    'DB_DATABASE='.envValue($db['name']),
    'DB_USERNAME='.envValue($db['username']),
    'DB_PASSWORD='.envValue($db['password']),
    'DB_CHARSET=utf8mb4',
    'DB_COLLATION=utf8mb4_unicode_ci',
    'SESSION_DRIVER=database',
    'SESSION_LIFETIME=120',
    'SESSION_ENCRYPT=true',
    'SESSION_PATH=/',
    'SESSION_DOMAIN='.envValue($host),
    'SESSION_SECURE_COOKIE=true',
    'SESSION_SAME_SITE=lax',
    'CACHE_STORE=database',
    'QUEUE_CONNECTION=database',
    'FILESYSTEM_DISK=public',
    'FRONTEND_URL='.envValue($url),
    'FRONTEND_URLS='.envValue($url),
    'SANCTUM_STATEFUL_DOMAINS='.envValue($host.',www.'.$host),
    'ADMIN_EMAIL='.envValue(setting($config, 'admin', 'email')),
    'ADMIN_PASSWORD='.envValue(setting($config, 'admin', 'password')),
    'CONTACT_EMAIL='.envValue($mail['from_address'] ?? setting($config, 'admin', 'email')),
    'MAIL_MAILER='.envValue($mail['mailer'] ?? 'log'),
    'MAIL_SCHEME='.envValue($mail['scheme'] ?? null),
    'MAIL_HOST='.envValue($mail['host'] ?? '127.0.0.1'),
    'MAIL_PORT='.(int) ($mail['port'] ?? 2525),
    'MAIL_USERNAME='.envValue($mail['username'] ?? null),
    'MAIL_PASSWORD='.envValue($mail['password'] ?? null),
    'MAIL_FROM_ADDRESS='.envValue($mail['from_address'] ?? setting($config, 'admin', 'email')),
    'MAIL_FROM_NAME='.envValue($mail['from_name'] ?? setting($config, 'app', 'name')),
];

if (file_put_contents($backend.'/.env', implode(PHP_EOL, $env).PHP_EOL, LOCK_EX) === false) {
    render($messages, ['Неуспешно създаване на backend/.env. Проверете правата за запис.'], false);
}
@chmod($backend.'/.env', 0600);
$messages[] = 'Production .env е създаден.';

$autoload = $backend.'/vendor/autoload.php';
if (! is_file($autoload)) {
    $binary = (string) ($config['composer_binary'] ?? 'composer');
    if (! preg_match('#^[A-Za-z0-9_./\\:-]+$#', $binary)) render($messages, ['Невалидна стойност composer_binary.'], false);
    if (! function_exists('exec')) render($messages, ['Липсва папка vendor и exec() е забранена. Стартирайте composer install в backend и опитайте отново.'], false);
    $command = 'cd '.escapeshellarg($backend).' && '.escapeshellarg($binary).' install --no-dev --optimize-autoloader --no-interaction 2>&1';
    exec($command, $composerOutput, $composerCode);
    if ($composerCode !== 0 || ! is_file($autoload)) render($messages, ['Composer не успя: '.implode("\n", array_slice($composerOutput, -12))], false);
    $messages[] = 'Composer зависимостите са инсталирани.';
}

try {
    require $autoload;
    /** @var Illuminate\Foundation\Application $app */
    $app = require $backend.'/bootstrap/app.php';
    /** @var Illuminate\Contracts\Console\Kernel $kernel */
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    // Deliberately not DatabaseSeeder wholesale, and NOT ProductSeeder /
    // PromotionSeeder specifically: those two create 7 unrelated dev
    // fixture products (fake names, auto-generated SVG placeholder
    // images) plus demo promotions on them - fine for local dev, wrong
    // for a real storefront's first boot. FunnelSeeder must run before
    // ReviewSeeder - the reviews attach to the 'miswak' product it
    // creates.
    $commands = [
        ['migrate', ['--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\AdminSeeder::class, '--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\SettingsSeeder::class, '--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\ContentBlockSeeder::class, '--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\CategorySeeder::class, '--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\LegalDocumentSeeder::class, '--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\FunnelSeeder::class, '--force' => true]],
        ['db:seed', ['--class' => Database\Seeders\ReviewSeeder::class, '--force' => true]],
        ['config:cache', []],
    ];
    foreach ($commands as [$command, $arguments]) {
        $code = $kernel->call($command, $arguments);
        if ($code !== 0) throw new RuntimeException($kernel->output());
    }
    importIcardProfiles($app, $backend, $messages);
    $messages[] = 'Таблиците и началните данни са създадени.';

    $target = $backend.'/storage/app/public';
    $link = __DIR__.'/storage';
    if (! is_dir($target)) mkdir($target, 0775, true);
    if (! file_exists($link) && ! @symlink($target, $link)) {
        $messages[] = 'Базата е готова, но storage връзката не можа да се създаде автоматично. Създайте smisul.bg/root/storage → smisul.bg/backend/storage/app/public от cPanel.';
    } else {
        $messages[] = 'Публичната storage връзка е готова.';
    }

    if (file_put_contents($lockFile, date(DATE_ATOM).PHP_EOL, LOCK_EX) === false) throw new RuntimeException('Не може да се създаде installation lock файл.');
    @chmod($lockFile, 0600);
    $messages[] = 'Инсталацията завърши. Влезте в администрацията с данните от config файла.';
    if (@unlink(__FILE__)) $messages[] = 'install.php беше автоматично изтрит.';
} catch (Throwable $e) {
    $errors[] = 'Laravel инсталацията прекъсна: '.$e->getMessage();
    $errors[] = 'Ако предишен опит е оставил частични таблици, изтрийте ги от празната инсталационна база чрез phpMyAdmin и стартирайте инсталатора отново.';
}

render($messages, $errors, false);
