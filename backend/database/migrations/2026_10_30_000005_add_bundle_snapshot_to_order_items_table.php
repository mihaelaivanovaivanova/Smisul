<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * product_variant_id was already nullable here (an order line must
     * survive its variant being deleted - see the create migration). A kit
     * line simply never sets it, carrying bundle_id instead, plus a
     * bundle_name snapshot alongside the existing product_name/sku ones so
     * the row stays self-sufficient if the kit is later renamed or deleted.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('bundle_id')->nullable()->after('product_variant_id')
                ->constrained('product_bundles')->nullOnDelete();
            $table->string('bundle_name')->nullable()->after('bundle_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bundle_id');
            $table->dropColumn('bundle_name');
        });
    }
};
