<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "регистър на предявените рекламации" (complaints register) ЗЗП
     * чл. 128, ал. 4 requires traders to keep - a distinct legal record
     * from the general contact form (StoreContactMessageRequest/
     * ContactMessageMail), which just emails a message and persists
     * nothing. complaint_number uses the same gap-free DocumentSequence
     * counter pattern as invoice_number (see ComplaintNumberGenerator),
     * assigned immediately at creation rather than lazily, since a
     * register entry exists from the moment it's logged.
     */
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_number')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->text('description');
            $table->string('status', 32)->default('received');
            $table->text('resolution')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
