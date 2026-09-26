<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-created manual orders (phone/in-person sales entered straight
     * into the admin panel) skip email entirely — every real checkout order
     * still always provides one, this just stops the column from rejecting
     * the manual-order path.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_email')->nullable(false)->change();
        });
    }
};
