<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The guest "Add a review" wizard dropped its title field (by request —
     * it collects just a star rating and a free-text review, no separate
     * headline) — the older authenticated review form still asks for one
     * (StoreReviewRequest keeps requiring it there), so this only relaxes
     * the column, not that flow's own validation.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }
};
