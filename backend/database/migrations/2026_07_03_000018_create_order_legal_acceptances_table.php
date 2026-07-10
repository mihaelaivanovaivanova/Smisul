<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // restrict, not cascade/null: a legal document that has ever
            // been accepted on an order must never be deletable — that
            // acceptance record is the compliance evidence.
            $table->foreignId('legal_document_id')->constrained()->restrictOnDelete();

            $table->dateTime('accepted_at');
            $table->timestamps();

            $table->unique(['order_id', 'legal_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_legal_acceptances');
    }
};
