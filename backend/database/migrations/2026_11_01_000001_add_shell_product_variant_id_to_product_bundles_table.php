<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The kit's backing "shell" catalog variant (see KitShellProductService)
     * - what makes a kit list in the normal catalog and lets its order
     * line carry a real product_variant_id for review eligibility. A
     * direct FK so OrderService::placeOrder() never has to match by slug
     * or SKU on the checkout path.
     */
    public function up(): void
    {
        Schema::table('product_bundles', function (Blueprint $table) {
            $table->foreignId('shell_product_variant_id')
                ->nullable()
                ->after('sort_order')
                ->constrained('product_variants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_bundles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shell_product_variant_id');
        });
    }
};
