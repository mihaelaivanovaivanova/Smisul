<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('operation_status', 32)->nullable()->after('gateway_transaction_reference');
            $table->string('operation_code', 64)->nullable()->after('operation_status');
            $table->string('operation_message', 191)->nullable()->after('operation_code');
            $table->string('approval_code', 64)->nullable()->after('operation_message');
            $table->string('masked_pan', 64)->nullable()->after('approval_code');
            $table->string('card_type', 64)->nullable()->after('masked_pan');
            $table->string('cardholder_name', 191)->nullable()->after('card_type');
            $table->dateTime('paid_at')->nullable()->after('completed_at');
        });

        Schema::table('payment_webhook_logs', function (Blueprint $table) {
            $table->string('error_message', 191)->nullable()->after('signature_valid');
        });

        // Card expiry must not remain in application storage. iCard keeps
        // the real card details behind the encrypted reusable CardToken.
        DB::table('stored_payment_methods')->update([
            'expiry_month' => null,
            'expiry_year' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('payment_webhook_logs', function (Blueprint $table) {
            $table->dropColumn('error_message');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'operation_status', 'operation_code', 'operation_message',
                'approval_code', 'masked_pan', 'card_type', 'cardholder_name', 'paid_at',
            ]);
        });
    }
};
