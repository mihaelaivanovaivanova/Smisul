<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A simple key-value store for the editable admin settings groups
        // (general/email/seo/media/system). Payments/Shipping are
        // deliberately NOT stored here — those are env-driven (see
        // config/services.php from Sprints 7-8) and only ever surfaced to
        // the admin panel as a read-only "is this configured" status, never
        // as editable DB rows, so a secret can never end up sitting in
        // plaintext in this table.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group')->index();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('label');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
