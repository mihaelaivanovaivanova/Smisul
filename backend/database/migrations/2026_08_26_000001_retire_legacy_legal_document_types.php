<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEGACY_TYPES = ['cookie_policy', 'returns_policy'];

    public function up(): void
    {
        // These policies were merged into privacy_policy and
        // right_of_withdrawal. Preserve their immutable historical rows and
        // foreign-key references, but stop exposing them as current checkout
        // documents alongside their replacements.
        DB::table('legal_documents')
            ->whereIn('type', self::LEGACY_TYPES)
            ->where('is_current', true)
            ->update([
                'is_current' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('legal_documents')
            ->whereIn('type', self::LEGACY_TYPES)
            ->update([
                'is_current' => true,
                'updated_at' => now(),
            ]);
    }
};
