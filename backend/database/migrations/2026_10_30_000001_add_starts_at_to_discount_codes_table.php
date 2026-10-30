<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            // Null = no lower bound (every code before this had none) -
            // expires_at already covers "valid until"; this adds "valid
            // from", for a code that should only activate at a future/
            // specific date rather than immediately on creation (e.g. a
            // one-day promo code). See DiscountCode::isRedeemable().
            $table->timestamp('starts_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->dropColumn('starts_at');
        });
    }
};
