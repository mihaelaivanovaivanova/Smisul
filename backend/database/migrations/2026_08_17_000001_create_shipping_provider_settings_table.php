<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->unique();
            $table->boolean('enabled')->default(false);
            $table->string('base_url', 500)->nullable();
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_provider_settings');
    }
};
