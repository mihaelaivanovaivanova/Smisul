<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();

            // What happened: initiated | return | cancel_return | webhook |
            // status_check — every gateway interaction the payment went
            // through, in order, forming its own audit trail independent
            // of (but cross-referenceable with) OrderStatusHistory.
            $table->string('type');
            $table->string('status');

            // The safe portion of whatever the gateway sent/returned at
            // this step — never card data (see the payments table comment).
            $table->json('raw_payload')->nullable();

            // Immutable audit log — no updated_at (see PaymentTransaction model).
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
