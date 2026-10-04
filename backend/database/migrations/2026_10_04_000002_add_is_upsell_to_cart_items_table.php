<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // Set once, at the moment this line is first added, by
            // CartService's server-validated cross-sell eligibility check
            // (see CartService::isEligibleForUpsellPrice()) - never flipped
            // by a later quantity change or re-add, so a line's price basis
            // can't be gamed after the fact. Drives CartPricingService::
            // effectivePrice()'s use of the variant's upsell_amount.
            $table->boolean('is_upsell')->default(false)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('is_upsell');
        });
    }
};
