<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // A surcharge for choosing cash on delivery (see
            // PaymentMethod::fee()) - reconciled into grand_total by
            // PaymentService::initiate() at the moment the method is
            // actually chosen, not baked into shipping_price/subtotal, so
            // it shows as its own real line item everywhere an order's
            // totals are broken down (order confirmation, admin order
            // detail).
            $table->decimal('cod_fee', 10, 2)->default(0)->after('shipping_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cod_fee');
        });
    }
};
