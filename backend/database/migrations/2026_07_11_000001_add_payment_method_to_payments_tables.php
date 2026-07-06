<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Defaults to 'card' — every payment before this migration was
            // a card payment (iCard was the only method), so backfilling
            // isn't needed beyond the column default.
            $table->string('payment_method')->default('card')->after('provider');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            // Denormalized from the parent payment onto each transaction
            // row too — the audit trail (see PaymentTransaction's docblock)
            // should read the method it was for without a join, same
            // reasoning as why `status` itself is copied per-row already.
            $table->string('payment_method')->default('card')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
