<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable audit log of every status a shipment has passed through
        // — same pattern as payment_transactions/payment_webhook_logs in
        // Sprint 7 (see ShipmentStatusEvent::UPDATED_AT = null).
        Schema::create('shipment_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('description')->nullable();

            // When the carrier says the event happened, distinct from
            // created_at (when we recorded it — could lag behind on a
            // delayed tracking sync).
            $table->dateTime('occurred_at');

            $table->json('raw_payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['shipment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_status_events');
    }
};
