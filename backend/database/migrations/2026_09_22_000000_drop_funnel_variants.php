<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ad-angle landing page feature (FunnelVariant, "/{product-slug}/
 * {angle-slug}") was scrapped in favor of a single redesigned landing
 * page — drops its table and any content_blocks overrides an admin had
 * already saved under "funnel.variant.{slug}.{section}" keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('funnel_variants');

        DB::table('content_blocks')->where('key', 'like', 'funnel.variant.%')->delete();
    }

    public function down(): void
    {
        // Content-only/structural cleanup with no meaningful prior state
        // to restore to.
    }
};
