<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * чл. 54, ал. 2 ЗЗП (Art. 13(2) of Directive 2011/83/EU) caps a
     * withdrawal refund's delivery-cost component at "the least costly
     * type of standard delivery offered" - offered AT THE TIME OF THAT
     * ORDER, not whatever's cheapest when the refund is later processed.
     * Prices change (carrier price edits, promos like the BOX NOW
     * free-shipping window ending) - without a snapshot, correctly
     * calculating a refund weeks/months after placement is guesswork.
     * Stores the full available-methods list (not just the minimum) so
     * there's an auditable record of what was actually offered, not just
     * a derived number - see Order::cheapestStandardShippingPriceAtPlacement().
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('shipping_rate_snapshot')->nullable()->after('shipping_price');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_rate_snapshot');
        });
    }
};
