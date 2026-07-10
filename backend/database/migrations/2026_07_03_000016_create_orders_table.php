<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 191)->unique();

            // Nullable: guest checkout is supported. Set null (not cascaded)
            // on user deletion so historical orders survive account removal.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // A guest's only way to reload their confirmation page — proof
            // of ownership without an account. Unused for authenticated
            // orders (ownership is user_id).
            $table->uuid('guest_access_token')->nullable()->unique();

            $table->string('status', 32)->default('pending');
            $table->string('currency', 3);

            // --- Customer information (snapshot at order time) ---
            $table->string('customer_first_name');
            $table->string('customer_last_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->string('customer_company')->nullable();
            $table->string('customer_vat_number')->nullable();
            $table->text('delivery_notes')->nullable();

            // --- Shipping address (snapshot at order time) ---
            $table->string('shipping_country');
            $table->string('shipping_city');
            $table->string('shipping_postal_code');
            $table->string('shipping_address_line');
            $table->string('shipping_apartment')->nullable();

            // --- Delivery method (snapshot — see ShippingMethodService) ---
            $table->string('shipping_carrier');
            $table->string('shipping_method_label');
            $table->decimal('shipping_price', 10, 2);

            // --- Totals (snapshot; mirrors CartTotals' shape) ---
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2);

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
