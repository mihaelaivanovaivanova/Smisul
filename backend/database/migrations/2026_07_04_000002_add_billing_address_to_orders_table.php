<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('billing_same_as_shipping')->default(true)->after('shipping_apartment');
            $table->string('billing_country')->nullable()->after('billing_same_as_shipping');
            $table->string('billing_city')->nullable()->after('billing_country');
            $table->string('billing_postal_code')->nullable()->after('billing_city');
            $table->string('billing_address_line')->nullable()->after('billing_postal_code');
            $table->string('billing_apartment')->nullable()->after('billing_address_line');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'billing_same_as_shipping',
                'billing_country',
                'billing_city',
                'billing_postal_code',
                'billing_address_line',
                'billing_apartment',
            ]);
        });
    }
};
