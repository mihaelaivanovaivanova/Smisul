<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->decimal('amount', 10, 2);
            $table->decimal('compare_at_amount', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['product_variant_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
