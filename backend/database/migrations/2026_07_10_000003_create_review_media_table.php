<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Architecture placeholder for future review image/video uploads (see the
 * sprint brief: "prepare architecture, do not implement uploads yet").
 * Nothing writes to this table today — no controller, no request, no
 * storage wiring. It exists so the eventual upload feature has a home
 * without a schema migration blocking it, and so Review::media() has a
 * real relation to point at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('type')->default('image');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('review_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_media');
    }
};
