<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Snapshot of which code (if any) this order redeemed - kept
            // even if the discount_codes row is later deleted, same spirit
            // as the other customer/address snapshot columns on this table.
            $table->string('discount_code', 191)->nullable()->after('discount_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('discount_code');
        });
    }
};
