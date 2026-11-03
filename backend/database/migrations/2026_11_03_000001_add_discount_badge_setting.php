<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Same pattern as 2026_08_21_000002_add_box_now_marketing_settings.php - a
 * migration (not just the seeder) so an already-installed site picks this
 * up on its next ordinary `migrate --force` deploy, no manual seeding step.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'general.discount_badge_enabled'],
            [
                'group' => 'general',
                'type' => 'boolean',
                'label' => 'Show discount code floating badge',
                // Defaults off (unlike the Box Now badge trio, which
                // default on) - both badges share the same corner, and
                // the Box Now setting is already on for every existing
                // install, so defaulting this on too would silently show
                // both overlapping the moment this migration runs. An
                // admin flips this on (and the Box Now one off) from the
                // same Settings page when they're ready to swap promos.
                'value' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'general.discount_badge_enabled')->delete();
    }
};
