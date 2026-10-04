<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            // Admin-configurable, lower than amount - the price a cart line
            // charges only when added through a specific cross-sell upsell
            // flow (see cart_items.is_upsell / CartPricingService::
            // effectivePrice()), never shown or charged on the product's
            // own page. Null means "no upsell price configured".
            $table->decimal('upsell_amount', 10, 2)->nullable()->after('compare_at_amount');
        });
    }

    public function down(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->dropColumn('upsell_amount');
        });
    }
};
