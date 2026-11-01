<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A "kit" (e.g. the 1-Person Kit) - sold as ONE cart/order line at ONE
     * stored price, while stock is reserved/decremented against its real
     * component ProductVariants (product_bundle_items below) rather than a
     * bundle-level stock field of its own. See BundleService and the
     * bundle branches in CartService/OrderService.
     */
    public function up(): void
    {
        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('currency', 3)->default('EUR');
            // The single, final kit price - the store is single-currency
            // (EUR) today. Never derived from the components; their live
            // sum is only ever used as the "you save" compare-at basis
            // (see ProductBundle::retailValue()).
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['product_bundle_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundle_items');
        Schema::dropIfExists('product_bundles');
    }
};
