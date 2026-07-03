<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // The pre-discount price shown at purchase time (Price::compare_at_amount
            // snapshot), so "X was on sale for Y" survives even if the price
            // or promotion is edited/removed later.
            $table->decimal('compare_at_price', 10, 2)->nullable()->after('unit_price');
            // (compare_at_price - unit_price) * quantity when on sale, else 0 —
            // informational: unit_price is already the amount actually
            // charged, this just records how much of it was a markdown.
            $table->decimal('discount_amount', 10, 2)->default(0)->after('line_total');
            $table->string('promotion_name')->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['compare_at_price', 'discount_amount', 'promotion_name']);
        });
    }
};
