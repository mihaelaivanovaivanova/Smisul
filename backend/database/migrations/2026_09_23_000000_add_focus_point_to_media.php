<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-settable focus point for a photo whose aspect ratio doesn't match
 * wherever it's displayed cropped (see ProductGallery.tsx's objectPosition
 * usage) — same idea CoreBenefitsSection.tsx's WHY_IMAGES map already
 * hardcodes per hero photo, now editable per Media row instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->float('focus_x')->default(0.5)->after('is_primary');
            $table->float('focus_y')->default(0.5)->after('focus_x');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['focus_x', 'focus_y']);
        });
    }
};
