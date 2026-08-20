<?php

namespace App\Services\Shipping;

use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Models\ShippingProviderSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Admin-editable overrides for the two shipping providers' credentials
 * (Speedy: base_url + username + password; BOX NOW: base_url + client_id +
 * client_secret) and their per-delivery-type flat rates — same
 * DB-backed-with-hardcoded-fallback shape as ICardConfigurationService,
 * scaled down (no PEM keys, no environments).
 *
 * A row only overrides the fields it has non-null values for; anything
 * still null falls back to config('services.shipping.{provider}.*') (env)
 * for credentials, or each provider's own hardcoded baseRate() for prices —
 * so an uninitialized install keeps working exactly as before this existed.
 *
 * `enabled` only gates credentials (see credentialsFor()) — prices
 * (priceFor()) apply as soon as they're saved, regardless of that flag.
 * Conflating the two originally meant a saved price silently did nothing
 * until "Enable X" was also checked, which had no obvious connection to
 * price from the admin UI; see priceFor()'s own docblock for why.
 */
class ShippingProviderSettingsService
{
    private const PROVIDERS = [ShippingCarrier::Speedy, ShippingCarrier::BoxNow];

    private const REQUIRED_COLUMNS = [
        'provider', 'enabled', 'base_url', 'username', 'password', 'client_id', 'client_secret',
        'price_office', 'price_locker', 'price_address',
    ];

    public function storageReady(): bool
    {
        try {
            return Schema::hasTable('shipping_provider_settings')
                && Schema::hasColumns('shipping_provider_settings', self::REQUIRED_COLUMNS);
        } catch (Throwable $exception) {
            Log::warning('Could not inspect the shipping_provider_settings table.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resolved credentials for the given provider — DB override where
     * present, env fallback otherwise. Used by the ShippingProviderInterface
     * implementations at request time; never exposed to the browser.
     *
     * @return array<string, mixed>
     */
    public function credentialsFor(string $provider): array
    {
        $fallback = (array) config("services.shipping.{$provider}", []);

        if (! $this->storageReady()) {
            return $fallback;
        }

        try {
            $row = ShippingProviderSetting::query()->where('provider', $provider)->first();
        } catch (Throwable $exception) {
            Log::warning('Could not read shipping provider settings; falling back to env config.', [
                'provider' => $provider,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $fallback;
        }

        if ($row === null || ! $row->enabled) {
            return $fallback;
        }

        return array_merge($fallback, array_filter([
            'base_url' => $row->base_url,
            'username' => $row->username,
            'password' => $row->password,
            'client_id' => $row->client_id,
            'client_secret' => $row->client_secret,
        ], fn ($value) => filled($value)));
    }

    /**
     * Admin-configured flat-rate override for a given provider + delivery
     * type. Deliberately independent of `enabled` — that flag only gates
     * whether credentialsFor() trusts this row's API credentials, since a
     * half-entered credential set could break real carrier calls; a price
     * has no such failure mode, so it applies as soon as it's saved. Returns
     * null when there's no row or that delivery type's price column was
     * left blank, so the caller's own hardcoded default (see each
     * provider's baseRate()) applies unchanged.
     */
    public function priceFor(string $provider, ShippingDeliveryType $deliveryType): ?float
    {
        if (! $this->storageReady()) {
            return null;
        }

        try {
            $row = ShippingProviderSetting::query()->where('provider', $provider)->first();
        } catch (Throwable $exception) {
            Log::warning('Could not read shipping provider price; falling back to the hardcoded default.', [
                'provider' => $provider,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($row === null) {
            return null;
        }

        return match ($deliveryType) {
            ShippingDeliveryType::Office => $row->price_office,
            ShippingDeliveryType::Locker => $row->price_locker,
            ShippingDeliveryType::Address => $row->price_address,
        };
    }

    /**
     * Lightweight status per provider (never decrypts secrets) — feeds the
     * general Settings response's provider badges.
     *
     * @return array<string, array{configured: bool}>
     */
    public function status(): array
    {
        $result = [];

        foreach (self::PROVIDERS as $carrier) {
            $result[$carrier->value] = ['configured' => $this->isConfigured($carrier->value)];
        }

        return $result;
    }

    private function isConfigured(string $provider): bool
    {
        if ($this->storageReady()) {
            try {
                $row = ShippingProviderSetting::query()->where('provider', $provider)->first();
                if ($row !== null) {
                    return (bool) $row->enabled && $this->hasCredentials($provider, $row);
                }
            } catch (Throwable $exception) {
                Log::warning('Could not read shipping provider status.', [
                    'provider' => $provider,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $provider === 'box_now'
            ? filled(config("services.shipping.{$provider}.client_id"))
            : filled(config("services.shipping.{$provider}.username"));
    }

    private function hasCredentials(string $provider, ShippingProviderSetting $row): bool
    {
        return $provider === 'box_now'
            ? filled($row->getRawOriginal('client_id')) && filled($row->getRawOriginal('client_secret'))
            : filled($row->getRawOriginal('username')) && filled($row->getRawOriginal('password'));
    }

    /**
     * Admin-facing list of all providers — real values for base_url/enabled/
     * prices, but only *_configured booleans for every credential field
     * (prices aren't secret, so they're shown and edited directly).
     *
     * @return list<array<string, mixed>>
     */
    public function adminList(): array
    {
        return array_map(function (ShippingCarrier $carrier) {
            $provider = $carrier->value;
            $row = $this->storageReady()
                ? ShippingProviderSetting::query()->where('provider', $provider)->first()
                : null;
            $envDefaults = (array) config("services.shipping.{$provider}", []);

            return [
                'provider' => $provider,
                'label' => $carrier->label(),
                'enabled' => (bool) ($row->enabled ?? false),
                'base_url' => $row->base_url ?? $envDefaults['base_url'] ?? null,
                'username_configured' => filled($row?->getRawOriginal('username')),
                'password_configured' => filled($row?->getRawOriginal('password')),
                'client_id_configured' => filled($row?->getRawOriginal('client_id')),
                'client_secret_configured' => filled($row?->getRawOriginal('client_secret')),
                'price_office' => $row->price_office ?? null,
                'price_locker' => $row->price_locker ?? null,
                'price_address' => $row->price_address ?? null,
                'configured' => $this->isConfigured($provider),
            ];
        }, self::PROVIDERS);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(string $provider, array $values): ShippingProviderSetting
    {
        foreach (['username', 'password', 'client_id', 'client_secret'] as $secret) {
            if (! filled($values[$secret] ?? null)) {
                unset($values[$secret]);
            }
        }

        return ShippingProviderSetting::query()->updateOrCreate(['provider' => $provider], $values);
    }
}
