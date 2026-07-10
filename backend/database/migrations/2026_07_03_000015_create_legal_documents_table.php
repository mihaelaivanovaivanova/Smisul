<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('version', 32);
            $table->string('title');
            $table->text('content')->nullable();
            // Exactly one row per type is current at a time — checkout only
            // ever offers/requires the current version. Publishing a new
            // version means inserting a new row and flipping this flag,
            // never mutating an existing (possibly already-accepted) row.
            $table->boolean('is_current')->default(true);
            $table->dateTime('published_at');
            $table->timestamps();

            $table->unique(['type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
