<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable audit log (no updated_at) — same pattern as
        // OrderStatusHistory/PaymentTransaction from Sprint 7. user_id and
        // email are both nullable independently: a failed login may never
        // resolve to a real user, so the attempted email is kept for the
        // audit trail even when there's no user_id to attach it to.
        Schema::create('authentication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('event');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authentication_logs');
    }
};
