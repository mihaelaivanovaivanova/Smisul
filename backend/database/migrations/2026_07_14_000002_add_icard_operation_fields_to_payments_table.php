<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_environment', 32)->nullable()->after('provider');
            $table->string('gateway_transaction_reference', 191)->nullable()->index()->after('provider_reference');
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('amount');
            $table->dateTime('reversed_at')->nullable()->after('completed_at');
            $table->dateTime('refunded_at')->nullable()->after('reversed_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['gateway_transaction_reference']);
            $table->dropColumn(['gateway_environment', 'gateway_transaction_reference', 'refunded_amount', 'reversed_at', 'refunded_at']);
        });
    }
};

