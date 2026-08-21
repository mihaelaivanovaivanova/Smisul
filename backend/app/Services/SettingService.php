<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\Payments\ICardConfigurationService;
use App\Services\Shipping\ShippingProviderSettingsService;
use Illuminate\Support\Collection;

/**
 * Backs the admin Settings screen. Only the "editable" groups
 * (general/email/seo/media/system) live in the settings table; Payments
 * and Shipping are DB-backed-with-env-fallback (see ICardConfigurationService
 * / ShippingProviderSettingsService) and are only ever exposed here as a
 * read-only configured/not-configured status — actual credential values are
 * never returned to the admin panel through this endpoint.
 */
class SettingService
{
    public function __construct(
        private readonly ICardConfigurationService $icardConfiguration,
        private readonly ShippingProviderSettingsService $shippingSettings,
    ) {}

    public const EDITABLE_GROUPS = ['general', 'email', 'seo', 'media', 'system'];

    /**
     * Single-key lookup for callers outside the admin Settings screen that
     * just need one value (e.g. the store's own contact details as the
     * "sender" on a BOX NOW shipment — see BoxNowShippingProvider).
     */
    public function get(string $key): ?string
    {
        return Setting::query()->where('key', $key)->value('value');
    }

    /**
     * @return array<string, list<array{key: string, group: string, label: string, type: string, value: string|int|bool|null}>>
     */
    public function allGrouped(): array
    {
        return Setting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group')
            ->map(fn (Collection $settings) => $settings->map(fn (Setting $setting) => [
                'key' => $setting->key,
                'group' => $setting->group,
                'label' => $setting->label,
                'type' => $setting->type,
                'value' => $setting->castValue(),
            ])->all())
            ->all();
    }

    /**
     * The explicitly public subset of settings — merchant identity and
     * contact details for the storefront footer. A whitelist rather than a
     * flag on rows, so a future admin-added setting can never leak into
     * the public payload by accident.
     *
     * @return array<string, string|null>
     */
    public function publicSettings(): array
    {
        $keys = [
            'general.company_name' => 'company_name',
            'general.company_name_en' => 'company_name_en',
            'general.company_manager' => 'company_manager',
            'general.company_id' => 'company_id',
            'general.contact_address' => 'contact_address',
            'general.support_phone' => 'support_phone',
            'general.store_email' => 'store_email',
            'general.same_day_dispatch_cutoff' => 'same_day_dispatch_cutoff',
            'general.social_instagram' => 'social_instagram',
            'general.social_facebook' => 'social_facebook',
            'general.social_tiktok' => 'social_tiktok',
            'general.box_now_banner_enabled' => 'box_now_banner_enabled',
            'general.box_now_badge_enabled' => 'box_now_badge_enabled',
            'general.box_now_banner_message' => 'box_now_banner_message',
        ];

        $settings = Setting::query()->whereIn('key', array_keys($keys))->get()->keyBy('key');

        return collect($keys)
            ->mapWithKeys(function (string $publicKey, string $key) use ($settings) {
                $setting = $settings->get($key);

                if ($setting === null) {
                    return [$publicKey => null];
                }

                // Booleans must come back as real booleans, not the raw '1'/'0'
                // string stored in the column — every other public setting so
                // far has been a string, so this is the first key that needs
                // castValue() rather than the raw ->value.
                if ($setting->type === 'boolean') {
                    return [$publicKey => $setting->castValue()];
                }

                return [$publicKey => $setting->value !== '' ? $setting->value : null];
            })
            ->all();
    }

    /**
     * @param  array<string, string|int|bool|null>  $values  Setting::key => new value
     */
    public function updateMany(array $values): void
    {
        $settings = Setting::query()->whereIn('key', array_keys($values))->get()->keyBy('key');

        foreach ($values as $key => $value) {
            $setting = $settings->get($key);

            if ($setting === null) {
                continue;
            }

            $setting->update([
                'value' => $setting->type === 'boolean' ? (($value ? '1' : '0')) : (string) $value,
            ]);
        }
    }

    /**
     * Read-only status of the env-configured Payments/Shipping providers —
     * whether credentials are present, never the credentials themselves.
     *
     * @return array{payments: array<string, mixed>, shipping: array<string, mixed>}
     */
    public function providerStatus(): array
    {
        $icard = $this->icardConfiguration->status();

        return [
            'payments' => [
                'icard' => [
                    'configured' => $icard['configured'],
                    'environment' => $icard['environment'],
                    'storage_ready' => $icard['storage_ready'],
                ],
            ],
            'shipping' => $this->shippingSettings->status(),
        ];
    }
}
