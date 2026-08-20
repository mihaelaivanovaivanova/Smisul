<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Previously validated at checkout (PlaceOrderRequest) but never
            // persisted — it only ever decided whether billing_address.* was
            // required, then got discarded. Needed now to know, at Delivered
            // time, whether to email an invoice (see SendOrderStatusEmails).
            $table->boolean('wants_invoice')->default(false)->after('customer_vat_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('wants_invoice');
        });
    }
};
