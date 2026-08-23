<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Assigned once, on first actual issuance (on-demand download or
            // the delivered-order invoice email — see InvoiceNumberGenerator),
            // never regenerated — a document must keep the same number on
            // every subsequent view. Distinct from order_number: that's the
            // customer-facing order reference (assigned at checkout, every
            // order gets one); this is the accounting document's own number
            // (assigned only if/when an invoice is actually issued).
            $table->string('invoice_number')->nullable()->unique()->after('wants_invoice');
            $table->timestamp('invoice_issued_at')->nullable()->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'invoice_issued_at']);
        });
    }
};
