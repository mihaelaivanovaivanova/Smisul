<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Nullable + backfilled to 'address' for pre-Sprint-8 rows,
            // since the checkout form previously never captured a delivery
            // type at all.
            $table->string('shipping_delivery_type')->nullable()->after('shipping_carrier');
            $table->string('shipping_office_id')->nullable()->after('shipping_delivery_type');
            $table->string('shipping_office_name')->nullable()->after('shipping_office_id');
        });

        DB::table('orders')->whereNull('shipping_delivery_type')->update(['shipping_delivery_type' => 'address']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_delivery_type', 'shipping_office_id', 'shipping_office_name']);
        });
    }
};
