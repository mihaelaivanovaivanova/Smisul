<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Backs the admin Settings screen. Only the "editable" groups
 * (general/email/seo/media/system) live in the settings table; Payments
 * and Shipping are env/config-driven (see Sprints 7-8) and are only ever
 * exposed here as a read-only configured/not-configured status — actual
 * credential values are never returned to the admin panel.
 */
class SettingService
{
    public const EDITABLE_GROUPS = ['general', 'email', 'seo', 'media', 'system'];

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
        return [
            'payments' => [
                'icard' => [
                    'configured' => filled(config('services.icard.mid')) && filled(config('services.icard.originator')),
                    'environment' => config('services.icard.environment'),
                ],
            ],
            'shipping' => [
                'econt' => [
                    'configured' => filled(config('services.shipping.econt.username')),
                ],
                'speedy' => [
                    'configured' => filled(config('services.shipping.speedy.username')),
                ],
                'box_now' => [
                    'configured' => filled(config('services.shipping.box_now.client_id')),
                ],
            ],
        ];
    }
}
