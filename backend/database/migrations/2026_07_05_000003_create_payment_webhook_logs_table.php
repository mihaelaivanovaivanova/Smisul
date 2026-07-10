<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();

            // Nullable: a webhook can arrive for a provider_reference we
            // don't recognize (bad actor, stale test data, gateway bug) —
            // still logged for audit, just never linked or processed.
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider');
            $table->string('event_type')->nullable();
            $table->string('provider_reference')->nullable();

            // Deduplication key — derived from the payload (see
            // ICardPaymentGateway::webhookIdempotencyKey()) so the exact
            // same delivery retried by the gateway is recognized and
            // skipped instead of being processed twice.
            $table->string('idempotency_key', 191)->unique();

            $table->boolean('signature_valid');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();

            // Immutable audit log — no updated_at (see PaymentWebhookLog model).
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
    }
};
