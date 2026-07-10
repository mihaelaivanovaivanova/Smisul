<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Current-state content, not an audit log — unlike LegalDocument,
        // editing a block updates its row in place rather than inserting a
        // new version, since homepage copy carries no acceptance/compliance
        // requirement that would need a historical trail.
        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191)->unique();
            $table->json('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_blocks');
    }
};
