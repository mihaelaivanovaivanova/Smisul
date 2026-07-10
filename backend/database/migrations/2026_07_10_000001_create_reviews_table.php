<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Nullable + nullOnDelete: the reviewed variant may later be
            // hard-deleted (ProductVariant is soft-deleted in normal use,
            // but this keeps the review intact even in that edge case) —
            // product_id is the field everything public actually queries by.
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->string('title');
            $table->text('body');
            $table->string('status', 32)->default('pending');
            $table->boolean('verified_purchase')->default(true);
            $table->unsignedInteger('helpful_count')->default(0);

            $table->text('admin_reply')->nullable();
            $table->timestamp('admin_reply_at')->nullable();
            $table->foreignId('admin_replied_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // One review per product per delivered order — enforces the
            // "not already submitted for that delivered order" business
            // rule at the schema level, not just in ReviewService.
            $table->unique(['order_id', 'product_id']);

            $table->index(['product_id', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
