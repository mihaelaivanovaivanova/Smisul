<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately separate from the existing promotions table -
        // Promotion is an auto-applied product/category sale markdown
        // (Product::activePromotions()), never something a customer types
        // in; this is the opposite shape - a single code, entered at
        // checkout, usually restricted to one customer's email, redeemed
        // at most max_uses times. Reusing promotions.code for this would
        // conflate two semantically different concepts and risk an
        // unrelated sale promotion's code becoming enterable at checkout.
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 191)->unique();

            // Null = anyone can redeem it (a general code an admin could
            // hand out). Set = only that address can redeem it - the shape
            // every review-reward code uses (see IssueReviewRewardCode).
            // Compared case-insensitively at validation time.
            $table->string('customer_email')->nullable();

            $table->decimal('discount_percent', 5, 2);

            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('used_count')->default(0);

            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            // Traceability + idempotency guard: at most one code per
            // review, even if ReviewApproved somehow fires twice for the
            // same review (see IssueReviewRewardCode's own check, which
            // this unique index backs up at the schema level).
            $table->foreignId('review_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index('customer_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};
