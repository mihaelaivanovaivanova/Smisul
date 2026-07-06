<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An immutable, append-only consent audit log — granting or withdrawing
 * consent never updates a row in place, it inserts a new one, so "what did
 * this person actually agree to, and when" can never be silently rewritten.
 * The current state for any (subject, type) pair is just its latest row
 * (see ConsentService::currentFor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();

            // Exactly one of these two identifies who this consent belongs
            // to — a registered user, or an anonymous visitor identified by
            // a client-generated identifier (mirrors the guest cart token
            // pattern). Both may be null->non-null across a person's
            // lifecycle (guest browses, consents to cookies, later
            // registers) — that linkage isn't attempted here; each row is
            // a snapshot of what was known at the time.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_identifier')->nullable();

            $table->string('type');

            // Denormalized version string (not just a legal_document_id
            // FK) so the exact version accepted survives even if the
            // LegalDocument row it came from is ever deleted — matches the
            // sprint's explicit "policy version" field requirement.
            $table->string('version')->nullable();
            $table->foreignId('legal_document_id')->nullable()->constrained()->nullOnDelete();

            $table->boolean('accepted');

            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // Immutable log — no updated_at (see the Consent model).
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'type']);
            $table->index(['guest_identifier', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
