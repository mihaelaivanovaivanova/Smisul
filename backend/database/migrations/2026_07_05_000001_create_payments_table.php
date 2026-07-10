<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 64);
            $table->string('status', 32);

            // Snapshot of what's being charged — never re-derived from the
            // order at read time, so a later order edit (there isn't one
            // today, but never rely on it) can't silently change what this
            // payment record says was charged.
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);

            // Our own idempotency/correlation key, generated before the
            // first gateway call and never reused — safe to send to the
            // gateway as a client reference. Unique so re-initiating an
            // already-initiated payment is a detectable no-op, not a
            // silent duplicate.
            $table->uuid('transaction_reference')->unique();

            // The gateway's own identifier for this payment, learned from
            // its response/webhook — null until the first gateway
            // interaction succeeds.
            $table->string('provider_reference', 191)->nullable()->unique();

            $table->text('redirect_url')->nullable();

            // The gateway's response body, with anything sensitive (see
            // PaymentService/ICardPaymentGateway — card data never reaches
            // us at all under the hosted-flow model) already stripped
            // before this is written.
            $table->json('raw_response')->nullable();

            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
