<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove only the old seeded placeholder, preserving all uploaded photos.
        // Keep the file on disk so this cleanup does not delete a shared asset.
        DB::table('media')
            ->where('mediable_type', 'App\\Models\\Product')
            ->whereIn('mediable_id', DB::table('products')->select('id')->where('slug', 'stargalka-za-ezik'))
            ->where('disk', 'public')
            ->where('path', 'products/stargalka-za-ezik-0.svg')
            ->delete();
    }

    public function down(): void
    {
        // Data cleanup: rolling back must not reintroduce the unwanted image.
    }
};
