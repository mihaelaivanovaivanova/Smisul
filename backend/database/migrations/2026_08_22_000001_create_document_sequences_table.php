<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A gap-free, monotonically-increasing counter per document type
     * (currently just 'invoice') — unlike OrderNumberGenerator's
     * date-prefix + random-suffix scheme, real invoice numbering needs a
     * true ascending sequence with no skipped numbers, which requires a
     * row to lock (see InvoiceNumberGenerator's SELECT ... FOR UPDATE).
     */
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
