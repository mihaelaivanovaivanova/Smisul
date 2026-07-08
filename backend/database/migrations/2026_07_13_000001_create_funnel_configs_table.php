<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singleton row (id=1) holding funnel mode's on/off flag plus which
        // product and three package tiers it currently points at. Kept
        // separate from the generic `settings` table on purpose — that
        // table is rendered wholesale on the admin Settings screen, and a
        // stray boolean there would blur the "dedicated Funnel section"
        // the admin UI is built around.
        Schema::create('funnel_configs', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->json('packages')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_configs');
    }
};
