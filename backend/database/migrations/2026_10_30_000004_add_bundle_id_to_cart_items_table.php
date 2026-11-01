<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cart line is now either an ordinary product line (product_variant_id
     * set) or a kit line (bundle_id set) - exactly one of the two, enforced
     * app-side by CartItem's saving hook. The second unique index caps one
     * row per kit per cart; NULLs are distinct in both MySQL and SQLite, so
     * ordinary lines (bundle_id null) are unaffected by it, exactly as kit
     * lines (product_variant_id null) are unaffected by the original one.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->change();
            $table->foreignId('bundle_id')->nullable()->after('product_variant_id')
                ->constrained('product_bundles')->nullOnDelete();

            $table->unique(['cart_id', 'bundle_id']);
        });
    }

    public function down(): void
    {
        // Kit lines can't be represented once product_variant_id is
        // required again. Their component reservations are NOT released
        // here (a rollback is a dev-only operation, not a live one).
        DB::table('cart_items')->whereNotNull('bundle_id')->delete();

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'bundle_id']);
            $table->dropConstrainedForeignId('bundle_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable(false)->change();
        });
    }
};
