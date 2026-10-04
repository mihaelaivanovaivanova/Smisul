<?php

namespace Database\Seeders;

use App\Enums\ShippingCarrier;
use App\Models\ShippingProviderSetting;
use Illuminate\Database\Seeder;

/**
 * Admin-overridable defaults for the shipping_provider_settings table (see
 * ShippingProviderSettingsService's own docblock: a row only overrides the
 * fields it has non-null values for, everything else keeps falling back to
 * env/hardcoded defaults - this seeder only ever sets cod_fee, never
 * touching real carrier credentials/prices an admin may have configured).
 *
 * Only fills cod_fee in when it's still null - a first-time default, not a
 * value to keep re-applying on every reseed. The Speedy row can easily
 * already exist for an unrelated reason (credentials configured, prices
 * overridden) with cod_fee never touched - firstOrCreate alone would treat
 * that as "already seeded" and skip it, so this checks the column itself,
 * not just row existence. Once an admin has set a real cod_fee (via the
 * Shipping settings panel), re-running this seeder must never stomp it.
 *
 * Only seeds the Speedy row: cash on delivery only exists for Speedy (see
 * PaymentMethod's own docblock) - cod_fee on any other provider's row is
 * never read.
 */
class ShippingProviderSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $row = ShippingProviderSetting::query()->firstOrCreate(['provider' => ShippingCarrier::Speedy->value]);

        if ($row->cod_fee === null) {
            $row->update(['cod_fee' => 0.80]);
        }
    }
}
