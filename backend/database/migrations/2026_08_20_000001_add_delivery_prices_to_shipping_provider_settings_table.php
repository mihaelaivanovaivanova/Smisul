<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_provider_settings', function (Blueprint $table) {
            // One column per ShippingDeliveryType — not every provider uses
            // every column (BOX NOW only ever reads price_locker, Speedy
            // only price_office/price_address today), but keeping them
            // generic here means a provider gaining a new delivery type
            // later doesn't need another migration. Null means "no admin
            // override — use the provider's own hardcoded flat rate" (see
            // ShippingProviderSettingsService::priceFor()).
            $table->decimal('price_office', 8, 2)->nullable()->after('client_secret');
            $table->decimal('price_locker', 8, 2)->nullable()->after('price_office');
            $table->decimal('price_address', 8, 2)->nullable()->after('price_locker');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_provider_settings', function (Blueprint $table) {
            $table->dropColumn(['price_office', 'price_locker', 'price_address']);
        });
    }
};
