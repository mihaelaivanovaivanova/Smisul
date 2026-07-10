<?php

declare(strict_types=1);

$source = $argv[1] ?? 'C:/Users/vlado/MiswakWebsite/prisma/dev.db';
$target = $argv[2] ?? dirname(__DIR__).'/deployment/private/icard-import.php';

if (! is_file($source)) {
    fwrite(STDERR, "MiswakWebsite database was not found.\n");
    exit(1);
}

$pdo = new PDO('sqlite:'.$source, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$settings = $pdo->query('SELECT * FROM IcardSettings WHERE id = \'default\' LIMIT 1')->fetch(PDO::FETCH_ASSOC);

if (! is_array($settings) || trim((string) ($settings['sandboxPrivateKeyBase64'] ?? '')) === '') {
    fwrite(STDERR, "The working sandbox iCard profile was not found.\n");
    exit(1);
}

$profile = static function (array $row, string $environment): ?array {
    $prefix = $environment === 'production' ? 'production' : 'sandbox';
    $field = static fn (string $name): string => trim((string) ($row[$prefix.ucfirst($name)] ?? ''));

    if ($field('mid') === '' || $field('privateKeyBase64') === '' || $field('publicKeyBase64') === '') {
        return null;
    }

    return [
        'enabled' => true,
        'mid' => $field('mid'),
        'mid_name' => $field('midName'),
        'originator' => $field('originator'),
        'key_index' => trim((string) ($row['keyIndex'] ?? '1')),
        'key_index_resp' => trim((string) ($row['keyIndexResp'] ?? '1')),
        'ipg_version' => trim((string) ($row['ipgVersion'] ?? '4.5')),
        'currency_numeric' => trim((string) ($row['currencyNumeric'] ?? '978')),
        'base_url' => $field('apiUrl'),
        'modal_js_url' => $field('modalJsUrl'),
        'wallet_js_url' => $field('walletJsUrl'),
        // The Miswak localhost notify URL must never be copied to Smisul.
        // Null makes Laravel derive https://smisul.bg/api/v1/payments/webhook/icard.
        'webhook_url' => null,
        'private_key' => $field('privateKeyBase64'),
        'public_key' => $field('publicKeyBase64'),
        'callback_ips' => ['82.119.81.211'],
        'apple_pay_enabled' => false,
        'google_pay_enabled' => false,
        'apple_merchant_id' => null,
        'apple_merchant_domain' => null,
        'google_merchant_id' => null,
        'google_environment' => $environment === 'production' ? 'PRODUCTION' : 'TEST',
    ];
};

$profiles = [];
foreach (['sandbox', 'production'] as $environment) {
    $values = $profile($settings, $environment);
    if ($values !== null) $profiles[$environment] = $values;
}

$payload = [
    'active_environment' => ($settings['activeEnvironment'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox',
    'profiles' => $profiles,
];

$directory = dirname($target);
if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
    throw new RuntimeException('Could not create private export directory.');
}

$contents = "<?php\n\n// Generated locally from MiswakWebsite. Never commit or place under the public root.\nreturn ".var_export($payload, true).";\n";
if (file_put_contents($target, $contents, LOCK_EX) === false) {
    throw new RuntimeException('Could not write the private iCard import.');
}
@chmod($target, 0600);

echo "Private iCard import prepared without printing credentials.\n";
