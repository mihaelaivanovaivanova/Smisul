<?php

namespace App\Services\Payments;

use App\Exceptions\Payment\PaymentGatewayException;
use App\Models\ICardConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class ICardConfigurationService
{
    private const REQUIRED_COLUMNS = [
        'environment', 'is_active', 'enabled', 'mid', 'mid_name', 'originator',
        'key_index', 'key_index_resp', 'ipg_version', 'currency_numeric',
        'base_url', 'modal_js_url', 'wallet_js_url', 'webhook_url',
        'private_key', 'public_key', 'callback_ips', 'apple_pay_enabled',
        'google_pay_enabled', 'apple_merchant_id', 'apple_merchant_domain',
        'google_merchant_id', 'google_environment',
    ];

    /**
     * A partially deployed migration must not take down the complete Settings
     * screen. This check is also used by the admin endpoint to return a useful
     * upgrade instruction instead of Laravel's generic production error page.
     */
    public function storageReady(): bool
    {
        try {
            return Schema::hasTable('icard_configurations')
                && Schema::hasColumns('icard_configurations', self::REQUIRED_COLUMNS);
        } catch (Throwable $exception) {
            Log::warning('Could not inspect the iCard configuration table.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /** @return array<string, mixed> */
    public function active(?string $environment = null): array
    {
        $environment ??= $this->activeEnvironment();

        if ($this->storageReady()) {
            $row = ICardConfiguration::query()->where('environment', $environment)->first();
            if ($row !== null) {
                return $this->fromModel($row);
            }
        }

        return $this->fromEnvironment($environment);
    }

    public function activeEnvironment(): string
    {
        if ($this->storageReady()) {
            try {
                $active = ICardConfiguration::query()->where('is_active', true)->value('environment');
                if (is_string($active)) return $active;
            } catch (Throwable $exception) {
                Log::warning('Could not read the active iCard environment.', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return (string) config('services.icard.environment', 'sandbox');
    }

    /**
     * Lightweight provider status for the general Settings response. It never
     * decrypts PEM keys, so a stale APP_KEY cannot break unrelated settings.
     *
     * @return array{configured: bool, environment: string, storage_ready: bool}
     */
    public function status(): array
    {
        $environment = $this->activeEnvironment();
        $fallback = $this->fromEnvironment($environment);

        if (! $this->storageReady()) {
            return [
                'configured' => $this->isConfigured($fallback),
                'environment' => $environment,
                'storage_ready' => false,
            ];
        }

        try {
            $row = ICardConfiguration::query()->where('environment', $environment)->first();
            if ($row === null) {
                return [
                    'configured' => $this->isConfigured($fallback),
                    'environment' => $environment,
                    'storage_ready' => true,
                ];
            }

            return [
                'configured' => (bool) $row->enabled
                    && filled($row->mid)
                    && filled($row->originator)
                    && filled($row->getRawOriginal('private_key'))
                    && filled($row->getRawOriginal('public_key')),
                'environment' => $environment,
                'storage_ready' => true,
            ];
        } catch (Throwable $exception) {
            Log::warning('Could not read iCard provider status.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'configured' => false,
                'environment' => $environment,
                'storage_ready' => false,
            ];
        }
    }

    public function assertReady(?string $environment = null): array
    {
        $config = $this->active($environment);
        $missing = [];
        foreach (['mid', 'originator', 'key_index', 'key_index_resp', 'private_key', 'public_key', 'base_url', 'webhook_url'] as $key) {
            if (! filled($config[$key] ?? null)) $missing[] = $key;
        }
        if ($missing !== []) {
            throw PaymentGatewayException::requestFailed('configuration incomplete: '.implode(', ', $missing));
        }
        if (($config['enabled'] ?? false) !== true) {
            throw PaymentGatewayException::requestFailed($config['environment'].' configuration is disabled');
        }

        return $config;
    }

    /** @return list<array<string, mixed>> */
    public function adminProfiles(): array
    {
        return array_map(function (string $environment) {
            $row = ICardConfiguration::query()->where('environment', $environment)->first();
            $config = $row === null
                ? $this->fromEnvironment($environment)
                : $this->publicValuesFromModel($row);

            return array_merge(array_diff_key($config, array_flip(['private_key', 'public_key'])), [
                'private_key_configured' => $row === null
                    ? filled($config['private_key'] ?? null)
                    : filled($row->getRawOriginal('private_key')),
                'public_key_configured' => $row === null
                    ? filled($config['public_key'] ?? null)
                    : filled($row->getRawOriginal('public_key')),
                'is_active' => $this->activeEnvironment() === $environment,
            ]);
        }, ['sandbox', 'production']);
    }

    /** @param array<string, mixed> $values */
    public function update(string $environment, array $values): ICardConfiguration
    {
        $row = ICardConfiguration::query()->where('environment', $environment)->first();
        $current = $row === null
            ? array_diff_key($this->fromEnvironment($environment), array_flip(['private_key', 'public_key']))
            : $this->publicValuesFromModel($row);

        foreach (['private_key', 'public_key'] as $secret) {
            if (! filled($values[$secret] ?? null)) {
                unset($values[$secret]);
                continue;
            }

            $values[$secret] = $this->normalizeKey(
                (string) $values[$secret],
                $secret === 'private_key' ? 'private' : 'public',
            );
        }

        $values['callback_ips'] = $this->callbackIps($values['callback_ips'] ?? []);

        return ICardConfiguration::query()->updateOrCreate(
            ['environment' => $environment],
            array_merge(array_diff_key($current, array_flip(['environment', 'is_active'])), $values),
        );
    }

    public function activate(string $environment): void
    {
        DB::transaction(function () use ($environment) {
            ICardConfiguration::query()->update(['is_active' => false]);
            $profile = ICardConfiguration::query()->firstOrCreate(
                ['environment' => $environment],
                $this->fromEnvironment($environment),
            );
            $profile->update(['is_active' => true]);
        });
    }

    /** @return array<string, mixed> */
    private function fromModel(ICardConfiguration $row): array
    {
        try {
            return array_merge($this->publicValuesFromModel($row), [
                'private_key' => $row->private_key,
                'public_key' => $row->public_key,
            ]);
        } catch (Throwable $exception) {
            Log::error('Stored iCard keys could not be decrypted. Check that APP_KEY was not changed.', [
                'environment' => $row->environment,
                'exception' => $exception::class,
            ]);

            throw PaymentGatewayException::requestFailed('stored keys could not be decrypted; re-save them in Settings > Payments');
        }
    }

    /** @return array<string, mixed> */
    private function publicValuesFromModel(ICardConfiguration $row): array
    {
        return [
            'environment' => $row->environment,
            'is_active' => (bool) $row->is_active,
            'enabled' => (bool) $row->enabled,
            'mid' => $row->mid,
            'mid_name' => $row->mid_name,
            'originator' => $row->originator,
            'key_index' => $row->key_index,
            'key_index_resp' => $row->key_index_resp,
            'ipg_version' => $row->ipg_version,
            'currency_numeric' => $row->currency_numeric,
            'base_url' => $row->base_url,
            'modal_js_url' => $row->modal_js_url,
            'wallet_js_url' => $row->wallet_js_url,
            'webhook_url' => $row->webhook_url ?: $this->defaultWebhookUrl(),
            'callback_ips' => $this->callbackIps($row->callback_ips ?? []),
            'apple_pay_enabled' => (bool) $row->apple_pay_enabled,
            'google_pay_enabled' => (bool) $row->google_pay_enabled,
            'apple_merchant_id' => $row->apple_merchant_id,
            'apple_merchant_domain' => $row->apple_merchant_domain,
            'google_merchant_id' => $row->google_merchant_id,
            'google_environment' => $row->google_environment,
        ];
    }

    /** @param array<string, mixed> $config */
    private function isConfigured(array $config): bool
    {
        return ($config['enabled'] ?? false) === true
            && filled($config['mid'] ?? null)
            && filled($config['originator'] ?? null)
            && filled($config['private_key'] ?? null)
            && filled($config['public_key'] ?? null);
    }

    /** @return array<string, mixed> */
    private function fromEnvironment(string $environment): array
    {
        $isProduction = $environment === 'production';
        $isConfiguredEnvironment = config('services.icard.environment', 'sandbox') === $environment;
        $privatePath = (string) config('services.icard.private_key_path');
        $publicPath = (string) config('services.icard.public_key_path');

        return [
            'environment' => $environment,
            'is_active' => config('services.icard.environment') === $environment,
            'enabled' => $isConfiguredEnvironment && filled(config('services.icard.mid')),
            'mid' => $isConfiguredEnvironment ? config('services.icard.mid') : null,
            'mid_name' => config('services.icard.mid_name', 'Smisul'),
            'originator' => $isConfiguredEnvironment ? config('services.icard.originator') : null,
            'key_index' => $isConfiguredEnvironment ? config('services.icard.key_index') : null,
            'key_index_resp' => $isConfiguredEnvironment ? config('services.icard.key_index_resp') : null,
            'ipg_version' => config('services.icard.ipg_version', '4.5'),
            'currency_numeric' => config('services.icard.currency_numeric', '978'),
            'base_url' => $isConfiguredEnvironment ? config('services.icard.base_url') : ($isProduction ? 'https://ipg.icard.com/' : 'https://dev-ipg.icards.eu/sandbox/'),
            'modal_js_url' => $isConfiguredEnvironment ? config('services.icard.modal_js_url') : ($isProduction ? 'https://ipg.icard.com/js/payment-modal.js' : 'https://dev-ipg.icards.eu/sandbox/js/payment-modal.js'),
            'wallet_js_url' => $isConfiguredEnvironment ? config('services.icard.wallet_js_url') : ($isProduction ? 'https://ipg.icard.com/js/icard-g-a-pay.min.js' : 'https://dev-ipg.icards.eu/sandbox/js/icard-g-a-pay.min.js'),
            'webhook_url' => config('services.icard.webhook_url') ?: $this->defaultWebhookUrl(),
            'private_key' => $isConfiguredEnvironment && is_readable($privatePath) ? file_get_contents($privatePath) : null,
            'public_key' => $isConfiguredEnvironment && is_readable($publicPath) ? file_get_contents($publicPath) : null,
            'callback_ips' => $this->callbackIps(explode(',', (string) config('services.icard.callback_ips', ''))),
            'apple_pay_enabled' => (bool) config('services.icard.apple_pay_enabled') && (bool) config('services.apple_pay.enabled'),
            'google_pay_enabled' => (bool) config('services.icard.google_pay_enabled') && (bool) config('services.google_pay.enabled'),
            'apple_merchant_id' => config('services.apple_pay.merchant_id'),
            'apple_merchant_domain' => config('services.apple_pay.merchant_domain'),
            'google_merchant_id' => config('services.google_pay.merchant_id'),
            'google_environment' => config('services.google_pay.environment', 'TEST'),
        ];
    }

    private function defaultWebhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/v1/payments/webhook/icard';
    }

    /** Accept the same complete-PEM or Base64 key formats as MiswakWebsite. */
    public function normalizeKey(string $value, string $kind): string
    {
        $value = trim(str_replace(["\\r\\n", "\\n", "\\r"], "\n", $value));
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        if (preg_match('/-----BEGIN ([A-Z ]+KEY)-----(.*?)-----END \\1-----/s', $value, $matches)) {
            $label = trim($matches[1]);
            $body = preg_replace('~[^A-Za-z0-9+/=]~', '', $matches[2]) ?: '';
            if ($body === '') throw new InvalidArgumentException('The iCard key PEM body is empty.');

            $pem = "-----BEGIN {$label}-----\n".trim(chunk_split($body, 64, "\n"))."\n-----END {$label}-----";
        } else {
            if (str_contains($value, '-----BEGIN')) {
                throw new InvalidArgumentException('The iCard PEM key has incomplete BEGIN/END lines.');
            }

            $compact = preg_replace('/\s+/', '', $value) ?: '';
            if ($compact === '' || ! preg_match('~^[A-Za-z0-9+/=]+$~', $compact)) {
                throw new InvalidArgumentException('The iCard key is not valid PEM or Base64.');
            }

            $decoded = base64_decode($compact, true);
            if ($decoded !== false && str_contains($decoded, '-----BEGIN')) {
                return $this->normalizeKey($decoded, $kind);
            }

            $label = $kind === 'private' ? 'PRIVATE KEY' : 'PUBLIC KEY';
            $pem = "-----BEGIN {$label}-----\n".trim(chunk_split($compact, 64, "\n"))."\n-----END {$label}-----";
        }

        $valid = $kind === 'private' ? openssl_pkey_get_private($pem) : openssl_pkey_get_public($pem);
        if ($valid === false) throw new InvalidArgumentException("The iCard {$kind} key cannot be read by OpenSSL.");

        return $pem;
    }

    /** @param mixed $values @return list<string> */
    private function callbackIps(mixed $values): array
    {
        $values = is_array($values) ? $values : [];
        $values[] = '82.119.81.211';
        if (app()->environment(['local', 'testing'])) $values[] = '127.0.0.1';

        return array_values(array_unique(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            $values,
        ))));
    }
}
