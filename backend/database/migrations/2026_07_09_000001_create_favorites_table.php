<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Enforces "a customer cannot favorite the same product/variant
            // twice" at the schema level, not just in application code.
            $table->unique(['user_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
