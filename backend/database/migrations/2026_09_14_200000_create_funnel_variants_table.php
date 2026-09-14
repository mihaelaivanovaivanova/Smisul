<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An "angle" landing page for the same funnel: a different
        // commercial (whitening, fresh breath, ...) sends traffic to
        // /{product-slug}/{variant-slug} instead of "/", where product_id
        // and packages are null until an admin explicitly overrides them
        // (falling back to the base FunnelConfig otherwise — most angles
        // sell the exact same product/packages, just a different story).
        // Content overrides live in the existing content_blocks table
        // under "funnel.variant.{slug}.{section}" keys (see
        // FunnelContentService) rather than a column here, so a variant
        // only needs to store the sections it actually customizes.
        Schema::create('funnel_variants', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->json('packages')->nullable();
            $table->boolean('is_active')->default(true);
            // Ad-to-page message match: falls back to the funnel's shared
            // seo.funnelTitle/funnelDescription copy when null.
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_variants');
    }
};
