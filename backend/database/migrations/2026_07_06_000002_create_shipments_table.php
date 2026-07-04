<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            // One shipment per order today — placeOrder() snapshots the
            // customer's chosen courier/office directly onto Order, and a
            // Shipment is only created afterwards, on demand, via
            // ShippingService::createShipment() (see the sprint's "prepare
            // backend support" scope — nothing auto-creates one yet).
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('carrier');
            $table->string('delivery_type');
            $table->string('office_id')->nullable();
            $table->string('office_name')->nullable();

            // Null until the carrier's createShipment() call succeeds.
            $table->string('tracking_number')->nullable()->unique();

            $table->string('status')->default('pending');

            // Snapshot of what the customer was charged for shipping — never
            // re-derived from the order at read time (same rationale as
            // Payment::amount in Sprint 7).
            $table->decimal('price', 10, 2);
            $table->string('currency', 3);

            $table->timestamp('estimated_delivery_at')->nullable();

            // Reference to the carrier's own label document — no automatic
            // label printing in this sprint, this is just where a future
            // admin action would find the label.
            $table->string('label_url')->nullable();

            $table->json('raw_response')->nullable();

            $table->timestamps();

            $table->index(['carrier', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
