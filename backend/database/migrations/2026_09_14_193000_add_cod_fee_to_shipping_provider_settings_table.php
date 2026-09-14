<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_provider_settings', function (Blueprint $table) {
            // Only meaningful on the speedy row - cash on delivery is
            // Speedy-only (see PaymentMethod's docblock). Null means "no
            // admin override - use CASH_ON_DELIVERY_FEE / the 0.50 default"
            // (see ShippingProviderSettingsService::codFee()), same shape as
            // the price_* columns above it.
            $table->decimal('cod_fee', 8, 2)->nullable()->after('price_address');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_provider_settings', function (Blueprint $table) {
            $table->dropColumn('cod_fee');
        });
    }
};
