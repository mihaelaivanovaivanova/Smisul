<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icard_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('environment', 32)->unique();
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('enabled')->default(false);
            $table->string('mid', 15)->nullable();
            $table->string('mid_name', 191)->default('Smisul');
            $table->string('originator', 32)->nullable();
            $table->string('key_index', 16)->nullable();
            $table->string('key_index_resp', 16)->nullable();
            $table->string('ipg_version', 10)->default('4.5');
            $table->string('currency_numeric', 3)->default('978');
            $table->string('base_url', 500);
            $table->string('modal_js_url', 500);
            $table->string('wallet_js_url', 500);
            $table->string('webhook_url', 500)->nullable();
            $table->text('private_key')->nullable();
            $table->text('public_key')->nullable();
            $table->json('callback_ips')->nullable();
            $table->boolean('apple_pay_enabled')->default(false);
            $table->boolean('google_pay_enabled')->default(false);
            $table->string('apple_merchant_id', 191)->nullable();
            $table->string('apple_merchant_domain', 191)->nullable();
            $table->string('google_merchant_id', 191)->nullable();
            $table->string('google_environment', 16)->default('TEST');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icard_configurations');
    }
};
