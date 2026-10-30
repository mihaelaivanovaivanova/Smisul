<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-day Halloween promo code, -10%, by request - active only on
     * 2026-10-31 (Europe/Sofia local day, converted to UTC since the app
     * runs in UTC - see config/app.php's 'timezone'). updateOrInsert so
     * re-running this migration (e.g. a fresh local DB) never duplicates
     * or errors on the unique `code` column.
     */
    public function up(): void
    {
        DB::table('discount_codes')->updateOrInsert(
            ['code' => 'SPOOKY10'],
            [
                'customer_email' => null,
                'discount_percent' => 10,
                'max_uses' => 500,
                'used_count' => 0,
                'starts_at' => '2026-10-30 22:00:00', // 2026-10-31 00:00:00 Europe/Sofia
                'expires_at' => '2026-10-31 21:59:59', // 2026-10-31 23:59:59 Europe/Sofia
                'is_active' => true,
                'review_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('discount_codes')->where('code', 'SPOOKY10')->delete();
    }
};
